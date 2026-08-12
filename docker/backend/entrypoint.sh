#!/bin/sh
set -eu

mkdir -p \
  storage/app/private \
  storage/app/public \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ "${WAIT_FOR_DB:-true}" = "true" ] && [ -n "${DB_HOST:-}" ] && [ -n "${DB_PORT:-}" ]; then
  php -r '
    $host = getenv("DB_HOST");
    $port = (int) getenv("DB_PORT");
    $deadline = time() + (int) (getenv("DB_WAIT_SECONDS") ?: 30);
    do {
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($socket) {
            fclose($socket);
            exit(0);
        }
        sleep(2);
    } while (time() < $deadline);
    fwrite(STDERR, "Database {$host}:{$port} was not reachable before timeout.\n");
    exit(1);
  '
fi

if [ "${RUN_LARAVEL_OPTIMIZE:-false}" = "true" ]; then
  su -s /bin/sh www-data -c "php artisan config:cache"
  su -s /bin/sh www-data -c "php artisan view:cache"
fi

exec docker-php-entrypoint "$@"
