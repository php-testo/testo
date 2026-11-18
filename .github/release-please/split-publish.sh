#!/usr/bin/env bash
#
# Usage: split-publish.sh <directory> <owner/repo> <branch> <tag>
#
# Publishes <directory> of the monorepo checkout as the content of <branch> in
# the target repository, then tags that commit <tag>. Retries the publication
# a few times; fails if every attempt fails.
#
# An existing <tag> in the target repository means the release is already
# published, and a newer tag there means publishing would roll <branch> back;
# both stop the script without pushing.
#
# Env: GITHUB_TOKEN with `contents: write` on the target repository.

set -euo pipefail

if [ "$#" -ne 4 ] || [ -z "$1" ] || [ -z "$2" ] || [ -z "$3" ] || [ -z "$4" ]; then
    echo "::error::Usage: split-publish.sh <directory> <owner/repo> <branch> <tag>, all non-empty; got: $*"
    exit 1
fi

if [ ! -d "$1" ]; then
    echo "::error::Package directory $1 does not exist."
    exit 1
fi

if [ -z "${GITHUB_TOKEN:-}" ]; then
    echo "::error::GITHUB_TOKEN is not set; pass a token with write access to $2."
    exit 1
fi

directory="$(realpath "$1")"
repository="$2"
branch="$3"
tag="$4"

url="https://x-access-token:${GITHUB_TOKEN}@github.com/${repository}.git"
message="$(git log -1 --format=%s)"
work="$(mktemp -d)"

# Exit status of publish() when there is nothing to retry.
readonly REFUSED=3

publish() {
    # Plain `ls-remote` prints nothing for an absent ref and fails only on a lookup
    # error; its output is assigned on a line of its own so `set -e` sees that failure.
    local tags newest
    tags="$(git ls-remote --tags --refs "${url}" | sed 's|.*refs/tags/||')"
    if grep -qxF "${tag}" <<< "${tags}"; then
        echo "Tag ${tag} already exists in ${repository}; nothing to publish."
        return 0
    fi
    # Each major series has its own branch, and `sort -V` ranks a pre-release
    # above its release: compare plain X.Y.Z releases of the same major only.
    if [[ "${tag}" =~ ^([0-9]+)\.[0-9]+\.[0-9]+$ ]]; then
        newest="$( { grep -E "^${BASH_REMATCH[1]}\.[0-9]+\.[0-9]+$" <<< "${tags}" || true; echo "${tag}"; } \
            | sort -V | tail -n 1)"
        if [ "${newest}" != "${tag}" ]; then
            echo "::error::${repository} already has ${newest}, newer than ${tag}; publishing would roll ${branch} back."
            return "${REFUSED}"
        fi
    fi

    local heads
    heads="$(git ls-remote --heads "${url}" "refs/heads/${branch}")"
    rm -rf "${work}/repo"
    if [ -n "${heads}" ]; then
        git clone --quiet --depth 1 --branch "${branch}" "${url}" "${work}/repo"
    else
        git init --quiet --initial-branch "${branch}" "${work}/repo"
        git -C "${work}/repo" remote add origin "${url}"
    fi

    find "${work}/repo" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
    cp -a "${directory}/." "${work}/repo/"

    local git=(git -C "${work}/repo"
        -c user.name='github-actions[bot]'
        -c user.email='41898282+github-actions[bot]@users.noreply.github.com')
    "${git[@]}" add --all
    if "${git[@]}" diff --cached --quiet; then
        echo "No changes in ${directory}; tagging the current ${branch} head."
    else
        "${git[@]}" commit --quiet --message "${message}"
    fi
    "${git[@]}" tag --annotate "${tag}" --message "Publishing ${tag}"

    # Atomic: a rejected branch never leaves a tag behind on an orphan commit.
    "${git[@]}" push --atomic origin "HEAD:refs/heads/${branch}" "refs/tags/${tag}"
}

for attempt in 1 2 3; do
    # Not `if publish`: a function called as a condition runs with `set -e` off.
    set +e
    (set -e; publish)
    status=$?
    set -e
    case "${status}" in
        0) exit 0 ;;
        "${REFUSED}") exit 1 ;;
    esac
    echo "::warning::Publishing ${tag} to ${repository} failed (attempt ${attempt})."
    if [ "${attempt}" -lt 3 ]; then
        sleep 30
    fi
done

echo "::error::Could not publish ${tag} to ${repository}."
exit 1
