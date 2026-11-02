#!/usr/bin/env bash
# Repository-owned quality gate, run identically locally and in CI
# (CI_WORKFLOW_STANDARD §2.1). Usage: scripts/ci/verify.sh [latest|lowest]
# Optional: CI_DIFF_BASE=<commit> sets the whitespace comparison base.
set -euo pipefail

mode="${1:-latest}"
cd "$(dirname "$0")/../.."

case "$mode" in
    latest) update_flags=() ;;
    lowest) update_flags=(--prefer-lowest --prefer-stable) ;;
    *) echo "Unknown dependency mode: $mode" >&2; exit 2 ;;
esac

# Actual interpreter patch floor first, before any Composer work (CI_WORKFLOW_STANDARD §7.1).
php scripts/ci/check-php-floor.php

composer --version
composer validate --strict

composer update ${update_flags[@]+"${update_flags[@]}"} --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer dump-autoload --optimize --strict-psr --no-interaction

php scripts/ci/check-php-constraint.php

find src tests scripts harness -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

scripts/ci/check-whitespace.sh

composer analyse

composer test

harness/run.sh "${mode}"

composer audit --no-interaction --abandoned=fail
