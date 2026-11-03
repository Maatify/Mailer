#!/usr/bin/env bash
# Compares an installed package directory (the real Composer dist) with the archive of the
# qualified commit (`git archive <sha>`, honoring .gitattributes): the same files with the same
# content, and a composer.json identical to the qualified manifest.
#
# Usage: scripts/release/compare-package.sh <installed-package-dir> <qualified-commit-sha>
# Exit 0 when they are identical, 1 when they differ, 2 on a usage error.
set -euo pipefail

installed="${1:?Usage: scripts/release/compare-package.sh <installed-package-dir> <qualified-commit-sha>}"
sha="${2:?Usage: scripts/release/compare-package.sh <installed-package-dir> <qualified-commit-sha>}"
root="$(cd "$(dirname "$0")/../.." && pwd)"

if [ ! -d "${installed}" ]; then
    echo "Not a directory: ${installed}" >&2
    exit 2
fi
if ! git -C "${root}" cat-file -e "${sha}^{commit}" 2>/dev/null; then
    echo "The qualified commit ${sha} is not available in this checkout (fetch the full history)." >&2
    exit 2
fi

expected="$(mktemp -d)"
trap 'rm -rf "${expected}"' EXIT
git -C "${root}" archive "${sha}" | tar -x -C "${expected}"

status=0
if ! cmp -s "${expected}/composer.json" "${installed}/composer.json"; then
    echo "The installed composer.json differs from the manifest of ${sha}." >&2
    status=1
fi
if ! diff -rq "${expected}" "${installed}" >&2; then
    echo "The installed files differ from the archive of ${sha} (missing, extra or changed files)." >&2
    status=1
fi
if [ "${status}" -eq 0 ]; then
    echo "The installed package is identical to the archive of ${sha}."
fi
exit "${status}"
