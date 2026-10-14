#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# compose can hand us the container before mysql accepts connections
echo "waiting for $DB_HOST:$DB_PORT"
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT")) ? 0 : 1);'; do
    sleep 2
done

php artisan migrate --force --seed

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
