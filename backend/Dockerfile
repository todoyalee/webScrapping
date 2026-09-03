# syntax=docker/dockerfile:1

# --- vendor: install PHP dependencies with Composer ---
FROM composer:2 AS vendor
WORKDIR /app
COPY database/ database/
COPY composer.json composer.lock ./
# Retry a couple of times: package downloads occasionally drop mid-stream.
RUN for i in 1 2 3; do \
      composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader && break; \
      echo "composer install failed, retry $i" && sleep 5; \
    done

# --- app: PHP runtime ---
FROM php:8.4-cli AS app
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev unzip \
 && docker-php-ext-install pdo_mysql zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Build the runtime .env. `php artisan serve` only forwards .env (plus a short
# allow-list) to its worker process, so container defaults live here, not only
# in compose. Values are overridable at run time via the entrypoint.
RUN cp .env.example .env \
 && sed -i 's#^DB_HOST=.*#DB_HOST=mysql#' .env \
 && sed -i 's#^PROXY_SERVICE_ENABLED=.*#PROXY_SERVICE_ENABLED=true#' .env \
 && sed -i 's#^PROXY_SERVICE_URL=.*#PROXY_SERVICE_URL=http://proxy-service:9000#' .env \
 && sed -i 's#^LOG_CHANNEL=.*#LOG_CHANNEL=stderr#' .env \
 && php artisan key:generate --force

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["docker-entrypoint.sh"]

# Compose gates startup on the MySQL healthcheck. Migrate (blocking), kick off
# the first scrape in the background so the API is available immediately, then
# serve. The frontend polls, so seeded products appear as they land.
CMD ["sh", "-c", "php artisan migrate --force && (php artisan db:seed --force || true &) && php artisan serve --host=0.0.0.0 --port=8000"]
