#!/usr/bin/env bash
# Fails on whitespace defects introduced since the comparison base.
# CI_DIFF_BASE: pull_request -> base sha; push -> previous sha; all-zero (first
# push of a branch, possibly many commits) -> the merge base with origin/master,
# so content the branch inherited from it is not checked again, and the empty tree
# (the whole final tree) when there is no common history; empty (workflow_dispatch)
# or equal to HEAD -> merge-base with origin/master, then HEAD's parent, then the
# empty tree, so HEAD is never compared with itself.
set -euo pipefail

head_sha="$(git rev-parse HEAD)"
base="${CI_DIFF_BASE:-}"

if [[ "${base}" =~ ^0+$ ]]; then
    base="$(git merge-base HEAD origin/master 2>/dev/null || true)"
    if [[ -z "${base}" ]]; then
        base="$(git hash-object -t tree /dev/null)"
    fi
elif [[ -z "${base}" ]]; then
    base="$(git merge-base HEAD origin/master 2>/dev/null || true)"
fi
if [[ -z "${base}" || "$(git rev-parse "${base}^{commit}" 2>/dev/null || true)" == "${head_sha}" ]]; then
    base="$(git rev-parse --verify -q HEAD~1 || git hash-object -t tree /dev/null)"
fi

echo "Whitespace check: ${base} -> ${head_sha}"
git diff --check "${base}" "${head_sha}"
