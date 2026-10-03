# Demo image for Render (free plan): FrankenPHP serves Laravel, SQLite lives inside the container.
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions intl pdo_sqlite zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# post-autoload-dump publishes Filament's assets into public/ (they are not in git).
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && chmod +x docker/start.sh

CMD ["docker/start.sh"]
