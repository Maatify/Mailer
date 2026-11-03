#!/usr/bin/env bash
# Published Artifact Verification: installs the exact released version from the
# real Composer distribution channel (Packagist) into a clean consumer, with no
# platform override, and checks what actually got installed: install type,
# package reference, installed files, and the public classes. Prints a record.
#
# A qualification run requires a supported PHP patch (scripts/ci/check-php-floor.php)
# and never overrides Composer platform requirements. VERIFY_PUBLISHED_DRY_RUN=1
# allows a local rehearsal on any PHP (it adds --ignore-platform-req=php); its
# record is labelled DRY RUN and never reports PASS.
#
# The Composer environment is isolated (CI_WORKFLOW_STANDARD §2.6): temporary
# COMPOSER_HOME and cache, every inherited COMPOSER_* variable removed, and the
# consumer pins Packagist as its only repository. The actual install source is
# read from vendor/composer/installed.json and must be `dist`.
#
# Usage: scripts/release/verify-published.sh <version> <expected-commit-sha>
set -euo pipefail

version="${1:?Usage: scripts/release/verify-published.sh <version> <expected-commit-sha>}"
expected="${2:?Usage: scripts/release/verify-published.sh <version> <expected-commit-sha>}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
dry_run="${VERIFY_PUBLISHED_DRY_RUN:-0}"
package="maatify/mailer"

if [[ ! "${expected}" =~ ^[0-9a-f]{40}$ ]]; then
    echo "The expected commit must be a full 40-character lowercase SHA." >&2
    exit 2
fi
platform_args=()
if [ "${dry_run}" = "1" ]; then
    platform_args=(--ignore-platform-req=php)
else
    php "${root}/scripts/ci/check-php-floor.php"
fi

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
cd "${work}"

# Isolate Composer from the host: no inherited COMPOSER_* settings, no global
# config, auth or cache.
while IFS= read -r name; do
    unset "${name}"
done < <(compgen -e | grep '^COMPOSER_' || true)
export COMPOSER_HOME="${work}/composer-home"
export COMPOSER_CACHE_DIR="${work}/composer-cache"
export COMPOSER_NO_INTERACTION=1
mkdir -p "${COMPOSER_HOME}" "${COMPOSER_CACHE_DIR}"

printf '%s\n' '{"name":"consumer/verify","require":{},"repositories":[{"type":"composer","url":"https://repo.packagist.org"},{"packagist.org":false}],"config":{"allow-plugins":false,"preferred-install":"dist"}}' > composer.json

install_flags=(--no-progress --prefer-dist)
if [ "${dry_run}" = "1" ] && [ "${VERIFY_PUBLISHED_PREFER_SOURCE:-0}" = "1" ]; then
    # Test knob (dry run only): forces a source install to prove the check fails.
    install_flags=(--no-progress --prefer-source)
fi

# Packagist can lag behind a fresh tag; retry the install for a few minutes.
installed=0
for attempt in $(seq 1 18); do
    if composer require "${install_flags[@]}" ${platform_args[@]+"${platform_args[@]}"} "${package}:${version}" >install.log 2>&1; then
        installed=1
        break
    fi
    echo "Install attempt ${attempt} failed; retrying in 10 seconds." >&2
    sleep 10
done
if [ "${installed}" -ne 1 ]; then
    cat install.log >&2
    echo "Could not install ${package}:${version} from Packagist." >&2
    exit 1
fi

results=()
check() {
    local label="$1" ok="$2"
    if [ "${ok}" = "1" ]; then results+=("PASS ${label}"); else results+=("FAIL ${label}"); fi
}

locked="$(php -r '
$lock = json_decode((string)file_get_contents("composer.lock"), true, 512, JSON_THROW_ON_ERROR);
foreach ($lock["packages"] as $p) {
    if ($p["name"] === $argv[1]) {
        echo $p["version"], "|", $p["dist"]["type"] ?? "", "|", $p["dist"]["reference"] ?? "", "|", $p["source"]["reference"] ?? "", "|", $p["dist"]["url"] ?? "";
    }
}' "${package}")"
IFS='|' read -r got_version dist_type dist_ref source_ref dist_url <<<"${locked}"

install_source="$(php -r '
$installed = json_decode((string)file_get_contents("vendor/composer/installed.json"), true, 512, JSON_THROW_ON_ERROR);
foreach ($installed["packages"] as $p) {
    if ($p["name"] === $argv[1]) {
        echo $p["installation-source"] ?? "unknown";
    }
}' "${package}")"

check "installed version is ${version}" "$([ "${got_version}" = "${version}" ] && echo 1 || echo 0)"
check "the lock records a zip dist (type ${dist_type:-none})" "$([ "${dist_type}" = "zip" ] && echo 1 || echo 0)"
check "Composer installed it from dist (installation-source ${install_source:-unknown})" "$([ "${install_source}" = "dist" ] && echo 1 || echo 0)"
check "dist reference is ${expected}" "$([ "${dist_ref}" = "${expected}" ] && echo 1 || echo 0)"
check "source reference is ${expected}" "$([ "${source_ref}" = "${expected}" ] && echo 1 || echo 0)"

files="$(cd "vendor/${package}" && find . -type f | sed 's|^\./||' | sort)"
required=(composer.json LICENSE README.md CHANGELOG.md SECURITY.md CONTRIBUTING.md CODE_OF_CONDUCT.md llms.txt
    MAILER_PACKAGE_REFERENCE.md docs/guides/USAGE_GUIDE.md
    src/Service/Mailer.php src/Adapter/MailerSender.php src/Contract/SubjectProviderInterface.php src/Enum/MailSubjectEnum.php
    examples/send-confirmation-code.php examples/custom-subjects.php examples/contact-form.php
    examples/templates/email/__header.html.twig examples/templates/email/confirm.html.twig)
missing=0
for path in "${required[@]}"; do
    grep -qxF "${path}" <<<"${files}" || { echo "Missing from the installed package: ${path}" >&2; missing=1; }
done
check "every required file is installed" "$([ "${missing}" -eq 0 ] && echo 1 || echo 0)"
leak=0
if grep -qE '^(\.github/|harness/|scripts/|tests/|phpstan\.neon$|phpunit\.xml$|\.gitattributes$|\.gitignore$)' <<<"${files}"; then
    echo "Development files are present in the installed package." >&2
    leak=1
fi
check "no development files are installed" "$([ "${leak}" -eq 0 ] && echo 1 || echo 0)"

classes="$(php -r '
require "vendor/autoload.php";
echo class_exists("Maatify\\Mailer\\Service\\Mailer") ? "1" : "0";
echo class_exists("Maatify\\Mailer\\Adapter\\MailerSender") ? "1" : "0";
echo interface_exists("Maatify\\Mailer\\Contract\\SubjectProviderInterface") ? "1" : "0";
echo enum_exists("Maatify\\Mailer\\Enum\\MailSubjectEnum") ? "1" : "0";
echo class_exists("Maatify\\Mailer\\Mailer") ? "1" : "0";
echo class_exists("Maatify\\Mailer\\MailerSender") ? "1" : "0";')"
check "new public types autoload" "$([ "${classes:0:4}" = "1111" ] && echo 1 || echo 0)"
check "the 5.x class names are gone" "$([ "${classes:4:2}" = "00" ] && echo 1 || echo 0)"

echo
echo "== Published Artifact Verification record"
echo "Package:     ${package}"
echo "Version:     ${got_version:-unknown}"
echo "Expected:    ${expected}"
echo "Dist URL:    ${dist_url:-none}"
echo "Dist ref:    ${dist_ref:-none}"
echo "Source ref:  ${source_ref:-none}"
echo "PHP:         $(php -r 'echo PHP_VERSION;')"
echo "Composer:    $(composer --version 2>/dev/null | head -n 1)"
echo "Install src: ${install_source:-unknown} (preferred-install $(composer config preferred-install 2>/dev/null || echo unknown))"
echo "Isolation:   COMPOSER_HOME and cache are temporary; no inherited COMPOSER_* variables; packagist.org is the only repository"
[ "${dry_run}" = "1" ] && echo "Mode:        DRY RUN (not a qualification)" || echo "Mode:        qualification (no platform override)"
echo "Installed files:"
sed 's/^/  /' <<<"${files}"
failed=0
for line in "${results[@]}"; do
    echo "${line}"
    [[ "${line}" == FAIL* ]] && failed=1
done
if [ "${failed}" -ne 0 ]; then
    echo "Result:      FAIL"
elif [ "${dry_run}" = "1" ]; then
    echo "Result:      DRY RUN COMPLETE (no qualification)"
else
    echo "Result:      PASS"
fi
exit "${failed}"
