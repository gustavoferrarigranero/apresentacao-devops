#!/usr/bin/env sh
set -eu

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${TARGET_RELEASE:?TARGET_RELEASE is required}"
: "${HEALTHCHECK_URL:?HEALTHCHECK_URL is required}"

target_path="${DEPLOY_PATH}/releases/${TARGET_RELEASE}"
current_path="${DEPLOY_PATH}/current"
temporary_link="${DEPLOY_PATH}/.rollback-${TARGET_RELEASE}"
previous_release=""

if [ -L "${current_path}" ]; then
    previous_release="$(readlink "${current_path}")"
fi

test -d "${target_path}"
test -f "${target_path}/vendor/autoload.php"
test -f "${target_path}/public/build/manifest.json"

ln -s "${target_path}" "${temporary_link}"
php -r 'if (! rename($argv[1], $argv[2])) { exit(1); }' "${temporary_link}" "${current_path}"

recover() {
    if [ -n "${previous_release}" ]; then
        recovery_link="${DEPLOY_PATH}/.recovery-${TARGET_RELEASE}"
        ln -s "${previous_release}" "${recovery_link}"
        php -r 'if (! rename($argv[1], $argv[2])) { exit(1); }' "${recovery_link}" "${current_path}"
        cd "${previous_release}"
        php artisan queue:restart
    fi
}

validate_target() {
    cd "${target_path}" || return 1
    php artisan config:cache || return 1
    php artisan route:cache || return 1
    php artisan view:cache || return 1
    php artisan queue:restart || return 1
    php artisan schedule:interrupt || return 1

    if php artisan list --raw | grep -q '^horizon:terminate'; then
        php artisan horizon:terminate || return 1
    fi

    php artisan deployment:heartbeat --wait=30 || return 1
    curl --fail --silent --show-error --retry 5 "${HEALTHCHECK_URL}" || return 1
}

trap 'recover; exit 1' INT TERM HUP

if ! validate_target; then
    recover
    trap - INT TERM HUP
    exit 1
fi

trap - INT TERM HUP
