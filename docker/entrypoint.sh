#!/usr/bin/env sh
set -eu

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
fi

php artisan config:clear --no-interaction || true
php artisan route:clear --no-interaction || true
php artisan view:clear --no-interaction || true

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
