#!/bin/sh
set -eu

: "${APP_KEY:?APP_KEY must be supplied}"
: "${DB_PASSWORD:?DB_PASSWORD must be supplied}"
: "${REDIS_PASSWORD:?REDIS_PASSWORD must be supplied}"

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

exec "$@"
