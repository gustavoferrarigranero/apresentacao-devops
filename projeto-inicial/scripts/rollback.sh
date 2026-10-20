#!/usr/bin/env sh
set -eu

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${TARGET_RELEASE:?TARGET_RELEASE is required}"
: "${HEALTHCHECK_URL:?HEALTHCHECK_URL is required}"

target_path="${DEPLOY_PATH}/releases/${TARGET_RELEASE}"

test -d "${target_path}"
printf '%s\n' "Rollback target prepared at ${target_path}"
