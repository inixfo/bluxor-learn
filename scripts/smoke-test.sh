#!/usr/bin/env sh
set -eu

MAIN_URL="${MAIN_URL:-https://learn.bluxor.com}"
COMPOSE_FILES="${COMPOSE_FILES:- -f docker-compose.yml}"
COMPOSE="docker compose --env-file .env.docker $COMPOSE_FILES"

if [ "${INTERNAL_NPM_TEST:-false}" = "true" ]; then
  echo "Checking Learn by Bluxor through the existing NPM container..."
  docker exec -i nginx-proxy-manager curl -fsSI http://learn-bluxor-web:80 >/dev/null
fi

echo "Checking public app root..."
curl -fsSI "$MAIN_URL/" >/dev/null

echo "Checking Laravel health..."
curl -fsSI "$MAIN_URL/health" >/dev/null

echo "Checking Laravel readiness..."
curl -fsSI "$MAIN_URL/health/ready" >/dev/null

echo "Checking products API..."
curl -fsSI "$MAIN_URL/api/v1/products" >/dev/null

if [ -n "${TEST_LANDING_SLUG:-}" ]; then
  echo "Checking native landing route..."
  curl -fsSI "$MAIN_URL/go/$TEST_LANDING_SLUG" >/dev/null
fi

echo "Checking Redis..."
$COMPOSE exec -T redis redis-cli ping | grep -q PONG

echo "Checking app can resolve external MySQL host..."
$COMPOSE exec -T app php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT"), $errno, $errstr, 5) ? 0 : 1);'

echo "Smoke test passed."
