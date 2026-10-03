#!/bin/sh
# Container entrypoint for the demo. The free plan has no persistent disk, so every
# start (deploy or wake-up after idling) begins with fresh sample data.
set -e

# No secret to manage: a new key per start only invalidates sessions, and the data resets anyway.
export APP_KEY="${APP_KEY:-$(php artisan key:generate --show)}"

touch database/database.sqlite
php artisan migrate:fresh --seed --force
php artisan optimize

# The image's Caddyfile serves public/. Cap PHP threads: FrankenPHP starts 2 per CPU it sees,
# Render hosts expose many CPUs, and the free plan has only 512 MB of memory.
export SERVER_NAME=":${PORT:-8080}"
export FRANKENPHP_CONFIG="${FRANKENPHP_CONFIG:-num_threads 4}"
exec frankenphp run --config /etc/frankenphp/Caddyfile
