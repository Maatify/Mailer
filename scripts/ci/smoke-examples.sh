#!/usr/bin/env bash
# Smoke-executes the standalone examples against the local SMTP stand-in
# (tests/Support/smtp_server.php: implicit TLS, a per-run self-signed certificate trusted through
# openssl.cafile). No real mail is sent. The example settings come from the process environment,
# exposed to $_ENV with variables_order=EGPCS, exactly as the examples document.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
work="$(mktemp -d)"
server_pid=""
cleanup() {
    if [[ -n "${server_pid}" ]]; then kill "${server_pid}" 2>/dev/null || true; wait "${server_pid}" 2>/dev/null || true; fi
    rm -rf "${work}"
}
trap cleanup EXIT

pem="${work}/server.pem"
events="${work}/events.log"
php "${root}/harness/certificate.php" "${pem}"
# shellcheck disable=SC2016
port="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); echo substr(stream_socket_get_name($s, false), 10);')"
php "${root}/tests/Support/smtp_server.php" "${port}" "${pem}" "${events}" >/dev/null 2>&1 &
server_pid=$!
started=""
for _ in $(seq 1 50); do
    if (exec 3<>"/dev/tcp/127.0.0.1/${port}") 2>/dev/null; then started=1; break; fi
    sleep 0.1
done
[[ -n "${started}" ]] || { echo "FAIL the local SMTP stand-in did not start" >&2; exit 1; }

run_example() {
    : > "${events}"
    env SMTP_HOST=127.0.0.1 SMTP_PORT="${port}" SMTP_USER=user SMTP_PASS=password \
        SMTP_FROM_MAIL=office@example.test SMTP_FROM_NAME="Site Office" \
        EMAIL_SITE_URL=https://site.example/ EMAIL_SITE_LOGO=logo.png EMAIL_SITE_NAME=site \
        php -d display_errors=0 -d variables_order=EGPCS -d "openssl.cafile=${pem}" "${root}/examples/$1" "${@:2}"
}

# Prints the messages the stand-in received, one per block, for the checks below.
messages() {
    # shellcheck disable=SC2016
    php -r 'foreach (explode("\n", (string)file_get_contents($argv[1])) as $l) { $e = $l === "" ? null : json_decode($l, true); if (is_array($e) && ($e["command"] ?? "") === "DATA") { echo "=== MESSAGE\n", $e["message"], "\n"; } }' "${events}"
}

expect() {
    local label="$1" needle="$2" haystack="$3"
    if ! grep -qE -- "${needle}" <<<"${haystack}"; then
        echo "FAIL ${label}: expected to match: ${needle}" >&2
        echo "${haystack}" >&2
        exit 1
    fi
    echo "ok   ${label}"
}

out="$(run_example send-confirmation-code.php person@example.test Person)"
expect "send-confirmation-code (sent)" '^sent$' "${out}"
msg="$(messages)"
expect "send-confirmation-code (default subject)" '^Subject: Confirm Code' "${msg}"
expect "send-confirmation-code (recipient)" '^To: Person <person@example\.test>' "${msg}"
expect "send-confirmation-code (code rendered)" '482913' "${msg}"

if out="$(run_example send-confirmation-code.php reject@example.test)"; then
    echo "FAIL send-confirmation-code (refused recipient): expected a non-zero exit" >&2
    exit 1
fi
expect "send-confirmation-code (refused recipient reported)" '^not sent$' "${out}"

out="$(run_example custom-subjects.php customer@example.test)"
expect "custom-subjects (both sent)" 'confirm code: sent' "${out}"
expect "custom-subjects (temporary password sent)" 'temporary password: sent' "${out}"
msg="$(messages)"
expect "custom-subjects (provider subject)" '^Subject: Your verification code' "${msg}"
expect "custom-subjects (second provider subject)" '^Subject: Your new password' "${msg}"

out="$(run_example contact-form.php visitor@example.test "Visitor Name" "Hello from the form")"
expect "contact-form (sent)" '^sent$' "${out}"
msg="$(messages)"
expect "contact-form (message to the site)" '^To: Site Office <office@example\.test>' "${msg}"
expect "contact-form (visitor as Reply-To)" '^Reply-To: Visitor Name <visitor@example\.test>' "${msg}"
expect "contact-form (acknowledgement)" '^Subject: RE: Website contact form' "${msg}"
# The site's copy must carry the visitor's text and must not attribute it to the site's own address
# (the template's `email` variable is the recipient, which for this message is the site).
site_copy="$(awk '/^=== MESSAGE/{n++} n==1' <<<"${msg}")"
expect "contact-form (visitor text reaches the site)" 'Hello from the form' "${site_copy}"
expect "contact-form (site copy says it came from the form)" 'sent this message through the contact form' "${site_copy}"
if grep -qE '^Message from ' <<<"${site_copy}"; then
    echo "FAIL contact-form (site copy attributes the message to the site's own address)" >&2
    echo "${site_copy}" >&2
    exit 1
fi
echo "ok   contact-form (site copy is not attributed to the site address)"
echo "Examples smoke execution passed."
