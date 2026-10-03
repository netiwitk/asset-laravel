#!/bin/sh
# Container entrypoint for the demo. The free plan has no persistent disk, so every
# start (deploy or wake-up after idling) begins with fresh sample data.
set -e

# No secret to manage: a new key per start only invalidates sessions, and the data resets anyway.
export APP_KEY="${APP_KEY:-$(php artisan key:generate --show)}"

touch database/database.sqlite
php artisan migrate:fresh --seed --force
php artisan optimize

exec frankenphp php-server --root public/ --listen ":${PORT:-8080}"
