#!/usr/bin/env sh
set -eu

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"
: "${RELEASE_ID:?RELEASE_ID is required}"
: "${HEALTHCHECK_URL:?HEALTHCHECK_URL is required}"

release_path="${DEPLOY_PATH}/releases/${RELEASE_ID}"
current_path="${DEPLOY_PATH}/current"
temporary_link="${DEPLOY_PATH}/.current-${RELEASE_ID}"
previous_release=""

if [ -L "${current_path}" ]; then
    previous_release="$(readlink "${current_path}")"
fi

test -d "${release_path}"
test -f "${release_path}/vendor/autoload.php"
test -f "${release_path}/public/build/manifest.json"
test -f "${DEPLOY_PATH}/shared/.env"

ln -sfn "${DEPLOY_PATH}/shared/.env" "${release_path}/.env"
ln -sfn "${DEPLOY_PATH}/shared/storage" "${release_path}/storage"

cd "${release_path}"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

ln -s "${release_path}" "${temporary_link}"
php -r 'if (! rename($argv[1], $argv[2])) { exit(1); }' "${temporary_link}" "${current_path}"

rollback() {
    if [ -n "${previous_release}" ]; then
        rollback_link="${DEPLOY_PATH}/.rollback-${RELEASE_ID}"
        ln -s "${previous_release}" "${rollback_link}"
        php -r 'if (! rename($argv[1], $argv[2])) { exit(1); }' "${rollback_link}" "${current_path}"
        cd "${previous_release}"
        php artisan queue:restart
    fi
}

validate_release() {
    php artisan queue:restart || return 1
    php artisan schedule:interrupt || return 1

    if php artisan list --raw | grep -q '^horizon:terminate'; then
        php artisan horizon:terminate || return 1
    fi

    php artisan deployment:heartbeat --wait=30 || return 1
    curl --fail --silent --show-error --retry 5 "${HEALTHCHECK_URL}" || return 1
}

trap 'rollback; exit 1' INT TERM HUP

if ! validate_release; then
    rollback
    trap - INT TERM HUP
    exit 1
fi

trap - INT TERM HUP
