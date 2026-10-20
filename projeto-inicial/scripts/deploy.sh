#!/usr/bin/env sh
set -eu

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${RELEASE_ID:?RELEASE_ID is required}"
: "${HEALTHCHECK_URL:?HEALTHCHECK_URL is required}"

release_path="${DEPLOY_PATH}/releases/${RELEASE_ID}"

test -d "${release_path}"
printf '%s\n' "Release prepared at ${release_path}"
