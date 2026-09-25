#!/usr/bin/env bash
#
# Usage: verify-packagist.sh <vendor/package> <version> <repository-url>
#
# Waits until <version> of <vendor/package> is listed on Packagist. When the
# GitHub hook missed the tag, asks Packagist to re-crawl the repository and
# waits once more; fails if the version is still missing.
#
# Env: PACKAGIST_USERNAME, PACKAGIST_TOKEN (safe API token). Without the token
# the re-crawl is skipped and a miss fails right away.

set -euo pipefail

package="$1"
version="$2"
repository="$3"

listed() {
    curl -fsS "https://repo.packagist.org/p2/${package}.json" 2>/dev/null \
        | jq -e --arg p "${package}" --arg v "${version}" \
            '.packages[$p] // [] | any(.version == $v)' >/dev/null
}

# <attempts> polls, 30 s apart.
wait_listed() {
    for _ in $(seq 1 "$1"); do
        listed && return 0
        sleep 30
    done
    return 1
}

if wait_listed 20; then
    echo "${package} ${version} is on Packagist."
    exit 0
fi

if [ -z "${PACKAGIST_TOKEN:-}" ]; then
    echo "::error::${package} ${version} is not on Packagist after 10 minutes, and PACKAGIST_TOKEN is not set to trigger an update."
    exit 1
fi

echo "::warning::${package} ${version} is not on Packagist after 10 minutes; triggering a package update."
curl -fsS -X POST -H 'Content-Type: application/json' \
    "https://packagist.org/api/update-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_TOKEN}" \
    -d "{\"repository\":\"${repository}\"}"
echo

if wait_listed 10; then
    echo "${package} ${version} is on Packagist after the triggered update."
    exit 0
fi

echo "::error::${package} ${version} is still not on Packagist after a triggered update."
exit 1
