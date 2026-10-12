#!/bin/sh
set -eu

umask 027

mkdir -p \
    bootstrap/cache \
    storage/app/imports \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan optimize
fi

exec "$@"
