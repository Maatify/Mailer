#!/usr/bin/env bash
# Inspects the archive Composer would ship for the committed tree
# (`git archive <ref>`, default HEAD; which honors .gitattributes export-ignore): every
# consumer-facing file must be present and no development file may leak in.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
ref="${1:-HEAD}"
listing="$(git -C "${root}" archive "${ref}" | tar -t)"

required=(
    composer.json LICENSE README.md CHANGELOG.md SECURITY.md CONTRIBUTING.md CODE_OF_CONDUCT.md
    llms.txt MAILER_PACKAGE_REFERENCE.md docs/guides/USAGE_GUIDE.md
    src/Service/Mailer.php src/Adapter/MailerSender.php
    src/Contract/SubjectProviderInterface.php src/Enum/MailSubjectEnum.php
    examples/send-confirmation-code.php examples/custom-subjects.php examples/contact-form.php
    examples/templates/email/__header.html.twig examples/templates/email/confirm.html.twig
)
excluded=(.github/ harness/ scripts/ tests/ phpstan.neon phpunit.xml .gitattributes .gitignore)

status=0
for path in "${required[@]}"; do
    if ! grep -qxF "${path}" <<<"${listing}"; then
        echo "Required file missing from the archive: ${path}" >&2
        status=1
    fi
done
for path in "${excluded[@]}"; do
    if grep -qE "^${path//./\\.}" <<<"${listing}"; then
        echo "Development path present in the archive: ${path}" >&2
        status=1
    fi
done
if [ "${status}" -ne 0 ]; then
    exit "${status}"
fi
echo "Archive content check passed."

extracted="$(mktemp -d)"
trap 'rm -rf "${extracted}"' EXIT
git -C "${root}" archive "${ref}" | tar -x -C "${extracted}"
php "${root}/scripts/ci/check-markdown-links.php" "${extracted}"
