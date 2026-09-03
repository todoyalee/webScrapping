#!/bin/sh
set -e

# Let compose (or `docker run -e`) override the baked-in .env defaults. Only the
# keys that differ between environments are touched; anything unset is left as
# built into the image.
for key in APP_ENV APP_DEBUG APP_URL \
           DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
           FRONTEND_ORIGIN \
           SCRAPER_TARGET_URL \
           PROXY_SERVICE_ENABLED PROXY_SERVICE_URL PROXY_SERVICE_TOKEN; do
    eval "value=\${$key+set}"
    [ "$value" = "set" ] || continue
    eval "value=\$$key"
    if grep -q "^${key}=" .env; then
        sed -i "s#^${key}=.*#${key}=${value}#" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
done

php artisan config:clear >/dev/null 2>&1 || true

exec "$@"
