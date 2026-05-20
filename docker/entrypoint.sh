#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force
fi

echo "Waiting for MySQL..."
until php -r "exit(@fsockopen('mysql', 3306) === false ? 1 : 0);"; do
    sleep 2
done
echo "MySQL port reachable."

php artisan migrate --force || true
php artisan storage:link || true

chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
