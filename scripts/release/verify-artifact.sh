#!/usr/bin/env bash
# Release Artifact Verification for an exact commit of this repository.
# Builds the distribution archive from <ref> (git archive, honoring
# .gitattributes), checks its content and links, validates the manifest, and
# runs the consumer harness in latest and lowest dependency modes. Prints a
# record (target, commit, tree, PHP, results) to attach to the release review.
#
# This verifies the candidate artifact before publication. After publication the
# real Composer dist must be verified separately.
#
# The qualification run is strict: it requires a supported PHP patch (the
# security floors of scripts/ci/check-php-floor.php) and refuses any Composer
# platform override (HARNESS_COMPOSER_ARGS). RELEASE_VERIFY_DRY_RUN=1 skips both
# for a local rehearsal; its record is labelled DRY RUN and never reports PASS.
#
# Usage: scripts/release/verify-artifact.sh <ref>
set -euo pipefail

ref="${1:?Usage: scripts/release/verify-artifact.sh <ref>}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
dry_run="${RELEASE_VERIFY_DRY_RUN:-0}"

if [ "${dry_run}" != "1" ]; then
    # A qualification never runs with a Composer override of the platform requirements.
    for name in HARNESS_COMPOSER_ARGS COMPOSER_IGNORE_PLATFORM_REQS COMPOSER_IGNORE_PLATFORM_REQ; do
        if [ -n "${!name+x}" ]; then
            echo "Refusing to qualify a release with ${name} set (platform overrides are not allowed)." >&2
            exit 2
        fi
    done
    php "${root}/scripts/ci/check-php-floor.php"
fi

commit="$(git -C "${root}" rev-parse --verify "${ref}^{commit}")"
tree="$(git -C "${root}" rev-parse "${commit}^{tree}")"
# The newest CHANGELOG section is the target: undated before publication, dated
# after it (the live default branch records the actual date).
target="$(git -C "${root}" show "${commit}:CHANGELOG.md" | sed -n -E 's/^## \[([0-9][0-9A-Za-z.+-]*)\]( - [0-9]{4}-[0-9]{2}-[0-9]{2})?$/\1/p' | head -n 1)"
if [ -z "${target}" ]; then
    echo "No exact-target section found in CHANGELOG.md at ${commit}." >&2
    exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
if [ "${dry_run}" != "1" ]; then
    # Isolate Composer from the host (CI_WORKFLOW_STANDARD 2.6): no inherited COMPOSER_* setting
    # that could change dependency resolution, and a temporary home and cache.
    while IFS= read -r name; do
        unset "${name}"
    done < <(compgen -e | grep '^COMPOSER_' || true)
    export COMPOSER_HOME="${work}/composer-home"
    export COMPOSER_CACHE_DIR="${work}/composer-cache"
    mkdir -p "${COMPOSER_HOME}" "${COMPOSER_CACHE_DIR}"
fi
git clone --quiet "${root}" "${work}/repo"
git -C "${work}/repo" checkout --quiet --detach "${commit}"

results=()
run() {
    local label="$1"
    shift
    echo "-- ${label}"
    if (cd "${work}/repo" && "$@"); then
        results+=("PASS ${label}")
    else
        results+=("FAIL ${label}")
    fi
}

run "composer validate --strict" composer validate --strict
run "archive content and links" scripts/ci/check-archive.sh HEAD
run "consumer harness (latest)" harness/run.sh latest
run "consumer harness (lowest)" harness/run.sh lowest

echo
echo "== Release Artifact Verification record"
echo "Target:  ${target}"
echo "Commit:  ${commit}"
echo "Tree:    ${tree}"
echo "PHP:     $(php -r 'echo PHP_VERSION;')"
[ "${dry_run}" = "1" ] && echo "Mode:    DRY RUN (not a qualification)" || echo "Mode:    qualification"
failed=0
for line in "${results[@]}"; do
    echo "${line}"
    [[ "${line}" == FAIL* ]] && failed=1
done
if [ "${failed}" -ne 0 ]; then
    echo "Result:  FAIL"
elif [ "${dry_run}" = "1" ]; then
    echo "Result:  DRY RUN COMPLETE (no qualification)"
else
    echo "Result:  PASS"
fi
exit "${failed}"
