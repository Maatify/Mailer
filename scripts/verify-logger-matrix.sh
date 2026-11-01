#!/usr/bin/env bash
# Runs the regression suite against the given maatify/logger releases in a
# throwaway copy, so the working tree and composer.json stay untouched.
# Usage: scripts/verify-logger-matrix.sh [logger-version ...]  (default: 5.0.1 5.1.0 5.1.1 5.1.2 7.0.0)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
versions=("$@")
if [ "${#versions[@]}" -eq 0 ]; then
    versions=(5.0.1 5.1.0 5.1.1 5.1.2 7.0.0)
fi

for version in "${versions[@]}"; do
    work="$(mktemp -d)"
    cp -R "$root/composer.json" "$root/phpunit.xml" "$root/mailer" "$root/tests" "$work/"
    (
        cd "$work"
        composer require --no-interaction --no-progress --quiet "maatify/logger:=${version}" --with-all-dependencies
        echo "== maatify/logger ${version} (PHP $(php -r 'echo PHP_VERSION;'))"
        vendor/bin/phpunit --testsuite regression
    )
    rm -rf "$work"
done
