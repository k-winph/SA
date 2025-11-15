#!/bin/bash
set -euo pipefail

APP_ROOT="/var/www/html"
STORAGE_DIR="${APP_ROOT}/storage"
STATE_DIR="${STORAGE_DIR}/app"
MIGRATION_FLAG="${STATE_DIR}/.migrated"
COMPOSER_FLAG="${STATE_DIR}/.composer_ready"
NODE_MODULES_FLAG="${STATE_DIR}/.node_modules_ready"
BUILD_FLAG="${STATE_DIR}/.build_ready"
BOOTSTRAP_FLAG="${STATE_DIR}/.bootstrap_ready"

cd "${APP_ROOT}"

ensure_env_file() {
    if [ ! -f "${APP_ROOT}/.env" ] && [ -f "${APP_ROOT}/.env.example" ]; then
        cp "${APP_ROOT}/.env.example" "${APP_ROOT}/.env"
    fi
}

ensure_app_key() {
    if grep -qE '^APP_KEY=.+$' "${APP_ROOT}/.env"; then
        return
    fi

    php artisan key:generate --force --ansi
}

wait_for_service() {
    local host=$1
    local port=$2
    if [ -z "${host}" ] || [ -z "${port}" ]; then
        return
    fi

    echo "Waiting for ${host}:${port}..."
    until nc -z "${host}" "${port}"; do
        sleep 2
    done
    echo "${host}:${port} is available."
}

wait_for_bootstrap() {
    if [ -f "${BOOTSTRAP_FLAG}" ]; then
        return
    fi

    echo "Waiting for application bootstrap to finish..."
    until [ -f "${BOOTSTRAP_FLAG}" ]; do
        sleep 3
    done
    echo "Bootstrap complete."
}

perform_bootstrap() {
    if [ -f "${BOOTSTRAP_FLAG}" ]; then
        echo "Bootstrap already complete. Skipping heavy setup."
        return
    fi

    if [ ! -f "${COMPOSER_FLAG}" ]; then
        composer install --prefer-dist --no-progress --no-interaction
        touch "${COMPOSER_FLAG}"
    fi

    if [ ! -f "${NODE_MODULES_FLAG}" ]; then
        npm install
        touch "${NODE_MODULES_FLAG}"
    fi

    if [ ! -f "${BUILD_FLAG}" ]; then
        npm run build
        touch "${BUILD_FLAG}"
    fi

    ensure_app_key
    php artisan storage:link --ansi || true

    if [ -n "${DB_HOST:-}" ]; then
        wait_for_service "${DB_HOST}" "${DB_PORT:-3306}"
    fi

    if [ -n "${REDIS_HOST:-}" ]; then
        wait_for_service "${REDIS_HOST}" "${REDIS_PORT:-6379}"
    fi

    if [ ! -f "${MIGRATION_FLAG}" ]; then
        php artisan migrate --force --ansi || true
        touch "${MIGRATION_FLAG}"
    fi

    php artisan optimize:clear --ansi || true
    touch "${BOOTSTRAP_FLAG}"
}

run_setup_tasks() {
    ensure_env_file

    mkdir -p \
        "${STATE_DIR}" \
        "${STORAGE_DIR}/framework/cache/data" \
        "${STORAGE_DIR}/framework/sessions" \
        "${STORAGE_DIR}/framework/views" \
        "${STORAGE_DIR}/logs"

    chown -R www-data:www-data "${STORAGE_DIR}" "${APP_ROOT}/bootstrap/cache" || true

    if [ "${WAIT_FOR_BOOTSTRAP:-0}" = "1" ]; then
        wait_for_bootstrap
    else
        perform_bootstrap
    fi
}

run_setup_tasks

echo "Starting command: $*"
exec "$@"
