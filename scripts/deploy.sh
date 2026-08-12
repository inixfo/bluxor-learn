#!/usr/bin/env sh
set -eu

COMPOSE_FILES="${COMPOSE_FILES:- -f docker-compose.yml}"
COMPOSE="docker compose --env-file .env.docker $COMPOSE_FILES"

if [ ! -f .env.docker ]; then
  echo "Missing .env.docker. Copy .env.docker.example and fill secrets first." >&2
  exit 1
fi

$COMPOSE config >/dev/null
$COMPOSE build
$COMPOSE up -d

echo "Run migrations after verifying the external mysql-server database/user:"
echo "  $COMPOSE run --rm app php artisan migrate:status"
echo "  $COMPOSE exec app php artisan migrate --force"
echo
echo "Create the first admin manually:"
echo "  $COMPOSE exec app php artisan user:create-admin"
echo
$COMPOSE ps
