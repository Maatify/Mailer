#!/usr/bin/env bash
# Consumer Verification Harness (TESTING_STANDARD §3.4), run identically locally and in CI.
# It builds the package artifact from the committed tree (`git archive HEAD`), installs it into a
# clean consumer project through Composer, and runs a realistic mail workflow through the
# production autoload against a local SMTP stand-in (implicit TLS, per-run self-signed
# certificate trusted through openssl.cafile). The whole cycle runs twice, each from a fresh
# temporary state with its own SMTP stand-in, event log and consumer root.
#
# Usage: harness/run.sh [latest|lowest]. `lowest` resolves the consumer's whole dependency graph
# with --prefer-lowest --prefer-stable, like the repository gate.
#
# No real mail is sent. Optional local-only knob: HARNESS_COMPOSER_ARGS (never set in CI), e.g.
# when the local PHP is below the declared patch floor; it also skips the platform check, which
# CI always runs.
set -euo pipefail

mode="${1:-latest}"
case "${mode}" in
    latest) update_flags=() ;;
    lowest) update_flags=(--prefer-lowest --prefer-stable) ;;
    *) echo "Unknown dependency mode: ${mode}" >&2; exit 2 ;;
esac

root="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC2206
composer_args=(${HARNESS_COMPOSER_ARGS:-})
work="$(mktemp -d)"
server_pid=""

fail() {
    echo "HARNESS FAILURE: $*" >&2
    exit 1
}

stop_server() {
    if [[ -n "${server_pid}" ]]; then
        kill "${server_pid}" 2>/dev/null || true
        wait "${server_pid}" 2>/dev/null || true
        server_pid=""
    fi
}

cleanup() {
    stop_server
    rm -rf "${work}"
}
trap cleanup EXIT

for run in 1 2; do
    echo "== Consumer harness run ${run}/2 (dependencies: ${mode})"
    state="${work}/run-${run}"
    package="${state}/package"
    consumer="${state}/consumer"
    events="${state}/events.log"
    pem="${state}/server.pem"
    mkdir -p "${package}" "${consumer}"
    : > "${events}"

    php "${root}/harness/certificate.php" "${pem}" || fail "could not create the stand-in certificate"
    # shellcheck disable=SC2016
    port="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); echo substr(stream_socket_get_name($s, false), 10);')"
    php "${root}/tests/Support/smtp_server.php" "${port}" "${pem}" "${events}" >/dev/null 2>&1 &
    server_pid=$!
    started=""
    for _ in $(seq 1 50); do
        if (exec 3<>"/dev/tcp/127.0.0.1/${port}") 2>/dev/null; then started=1; break; fi
        sleep 0.1
    done
    [[ -n "${started}" ]] || fail "the local SMTP stand-in did not start"

    git -C "${root}" archive HEAD | tar -x -C "${package}"
    cp -R "${root}/harness/consumer/templates" "${consumer}/templates"
    cp "${root}/harness/consumer/workflow.php" "${consumer}/workflow.php"
    sed "s|__PACKAGE_DIR__|${package}|" "${root}/harness/consumer/composer.json" > "${consumer}/composer.json"

    (cd "${consumer}" && composer update ${update_flags[@]+"${update_flags[@]}"} --no-interaction --prefer-dist --no-progress ${composer_args[@]+"${composer_args[@]}"}) \
        || fail "Composer could not install the package into the consumer"
    # shellcheck disable=SC2016
    resolved="$(cd "${consumer}" && composer show --format=json | php -r '$p = json_decode((string)stream_get_contents(STDIN), true)["installed"]; foreach ($p as $x) { if (str_starts_with($x["name"], "maatify/")) { $o[] = $x["name"] . " " . $x["version"]; } } echo implode(", ", $o ?? []);')"
    echo "   consumer resolved: ${resolved}"
    if [[ -z "${HARNESS_COMPOSER_ARGS:-}" ]]; then
        (cd "${consumer}" && composer check-platform-reqs) || fail "platform requirements are not satisfied"
    fi

    output="$(cd "${consumer}" && php -d "openssl.cafile=${pem}" workflow.php "${port}")" \
        || fail "the consumer workflow did not run"
    echo "${output}"
    stop_server

    # Observed results, production-autoload/test-autoload independence, and the SMTP traffic.
    # shellcheck disable=SC2016
    php -r '
        $r = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
        $events = array_map(
            static fn (string $line): mixed => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            array_values(array_filter(explode("\n", (string)file_get_contents($argv[2])))),
        );
        $messages = [];
        foreach ($events as $e) {
            if (($e["command"] ?? "") === "DATA") { $messages[] = (string)$e["message"]; }
        }
        $commands = array_column($events, "command");
        $has = static fn (int $i, string $needle): bool => isset($messages[$i]) && preg_match($needle, $messages[$i]) === 1;
        $checks = [
            "package loaded from the consumer vendor/" => $r["loaded_from_vendor"] === true,
            "test autoload and namespace are absent" => $r["test_autoload_absent"] === true,
            "message sent" => $r["results"]["message"] === true,
            "admin message and acknowledgement sent" => $r["results"]["admin_message"] === true,
            "refused recipient reported as not sent" => $r["results"]["refused_recipient"] === false,
            "SMTP authentication was used" => in_array("AUTH", $commands, true),
            "three messages reached the server" => count($messages) === 3,
            "message content and subject delivered" => $has(0, "/^Subject: CONSUMER-SUBJECT\r?$/m")
                && $has(0, "/^To: Person <person@example\.test>\r?$/m") && str_contains($messages[0] ?? "", "CONSUMER-BODY"),
            "admin message reaches the site with the visitor as Reply-To" => $has(1, "/^To: Site Office <office@example\.test>\r?$/m")
                && $has(1, "/^Reply-To: Visitor Name <visitor@example\.test>\r?$/m"),
            "acknowledgement replies to the site" => $has(2, "/^To: Visitor Name <visitor@example\.test>\r?$/m")
                && $has(2, "/^Reply-To: Site Office <office@example\.test>\r?$/m")
                && $has(2, "/^Subject: RE: CONSUMER-VISITOR-SUBJECT\r?$/m"),
        ];
        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
        if ($failed !== []) {
            fwrite(STDERR, "HARNESS FAILURE: " . implode("; ", $failed) . "\n");
            exit(1);
        }
        echo "   checks passed: " . implode(", ", array_keys($checks)) . "\n";
    ' "${output}" "${events}"
done

echo "OK: consumer harness passed twice from clean states."
