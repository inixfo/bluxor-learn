# Learn by Bluxor Production Deployment

Target host: Hetzner `ubuntu-4gb-hel1-1`.

Deploy under:

```bash
mkdir -p /docker/apps/learn-bluxor
cd /docker/apps/learn-bluxor
```

This deployment uses the existing server infrastructure:

- Nginx Proxy Manager on public ports 80/81/443.
- Existing Docker network `proxy`.
- Existing MySQL 8 container `mysql-server` on `proxy:3306`.
- Existing PipraPay service at `https://pay.bluxor.com`.

Do not create a database container, Nginx Proxy Manager container, Certbot container, or host port binding for Learn by Bluxor.

## Services

| Service | Container | Networks | Public ports |
|---|---|---|---|
| `web` | `learn-bluxor-web` | `learn-internal`, `proxy` | none, exposes `80` only |
| `app` | `learn-bluxor-app` | `learn-internal`, `proxy` | none |
| `queue` | `learn-bluxor-queue` | `learn-internal`, `proxy` | none |
| `scheduler` | `learn-bluxor-scheduler` | `learn-internal`, `proxy` | none |
| `redis` | `learn-bluxor-redis` | `learn-internal` only | none |

Traffic flow:

```text
Cloudflare -> Nginx Proxy Manager -> learn-bluxor-web:80 -> Laravel/PHP-FPM
```

## Create MySQL Database/User

Connect to the existing MySQL container:

```bash
docker exec -it mysql-server mysql -uroot -p
```

Run:

```sql
CREATE DATABASE IF NOT EXISTS learn_bluxor
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'learn_bluxor'@'%'
IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';

ALTER USER 'learn_bluxor'@'%'
IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';

GRANT ALL PRIVILEGES ON learn_bluxor.* TO 'learn_bluxor'@'%';

FLUSH PRIVILEGES;
```

Do not place root MySQL credentials in Learn by Bluxor files.

## Environment

Copy the example and fill secrets:

```bash
cp .env.docker.example .env.docker
nano .env.docker
```

Required production values include:

```env
APP_NAME="Learn by Bluxor"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://learn.bluxor.com
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=mysql-server
DB_PORT=3306
DB_DATABASE=learn_bluxor
DB_USERNAME=learn_bluxor
DB_PASSWORD=

REDIS_HOST=learn-bluxor-redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis

LANDING_PAGE_PUBLIC_ORIGIN=https://learn.bluxor.com
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
CORS_ALLOWED_ORIGINS=https://learn.bluxor.com
```

Also configure:

- `PIPRAPAY_BASE_URL=https://pay.bluxor.com`
- `PIPRAPAY_API_KEY`
- `PIPRAPAY_CURRENCY=BDT`
- PipraPay callbacks:
  - `https://learn.bluxor.com/api/v1/payments/piprapay/webhook`
  - `https://learn.bluxor.com/api/v1/payments/piprapay/success`
- Brevo SMTP:
  - `MAIL_MAILER=smtp`
  - `MAIL_HOST=smtp-relay.brevo.com`
  - `MAIL_PORT=587`
  - `MAIL_ENCRYPTION=tls`
  - `MAIL_USERNAME`
  - `MAIL_PASSWORD`
  - `MAIL_FROM_ADDRESS`
  - `MAIL_FROM_NAME`
  - `ADMIN_NOTIFICATION_EMAIL`
- Google OAuth:
  - `GOOGLE_CLIENT_ID`
  - `GOOGLE_CLIENT_SECRET`
  - `GOOGLE_REDIRECT_URI=https://learn.bluxor.com/api/v1/auth/google/callback`
- Meta Pixel + Conversions API:
  - `META_PIXEL_ENABLED`
  - `META_PIXEL_ID`
  - `META_CAPI_ENABLED`
  - `META_CAPI_ACCESS_TOKEN`
  - `META_GRAPH_API_VERSION`
  - `META_CAPI_TEST_EVENT_CODE`
  - `META_CAPI_TIMEOUT_SECONDS`
  - `META_MARKETING_CONSENT_REQUIRED`
  - `META_PIXEL_ALLOW_LOCALHOST`

Learn uses the installed PipraPay V3 Redirect Checkout API:

```text
POST /api/checkout/redirect
POST /api/verify-payment
POST /api/refund-payment
```

Outgoing PipraPay requests use the `MHS-PIPRAPAY-API-KEY` header. The installed production API uses singular `verify-payment`.

Google Console configuration:

```text
Authorized JavaScript origin: https://learn.bluxor.com
Authorized redirect URI: https://learn.bluxor.com/api/v1/auth/google/callback
```

Product and category images are written to Laravel's public disk and served under `/storage/*`. The Docker deployment persists that disk with `learn_bluxor_public_storage`, mounted into both `app` and `web`, so uploaded media survives image rebuilds and service recreation.

## Build And Start

```bash
cd /docker/apps/learn-bluxor
docker compose config
docker compose build
docker compose up -d
docker compose ps
```

Check migration status before applying migrations:

```bash
docker compose run --rm app php artisan migrate:status
docker compose exec app php artisan migrate --force
```

This release adds the `meta_conversion_events` migration for durable Meta CAPI delivery state.

Create the first admin manually:

```bash
docker compose exec app php artisan user:create-admin
```

Do not seed a production admin password.

## Nginx Proxy Manager

Create one Proxy Host.

Details:

```text
Domain Names: learn.bluxor.com
Scheme: http
Forward Hostname/IP: learn-bluxor-web
Forward Port: 80
Websockets Support: ON
Block Common Exploits: ON
```

SSL:

```text
Request a new Let's Encrypt certificate
Force SSL: ON
HTTP/2 Support: ON
```

Advanced config:

```nginx
client_max_body_size 64m;

proxy_read_timeout 300s;
proxy_send_timeout 300s;

proxy_set_header Host $host;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Proto https;
proxy_set_header X-Forwarded-Ssl on;
proxy_set_header X-Forwarded-Port 443;
proxy_set_header HTTPS on;
```

Internal test before DNS/SSL:

```bash
docker exec -it nginx-proxy-manager \
curl -I http://learn-bluxor-web:80
```

If this fails, verify both `nginx-proxy-manager` and `learn-bluxor-web` are attached to the `proxy` network.

## Cloudflare

DNS:

```text
Type: A
Name: learn
Value: <Hetzner server IP>
TTL: Auto
```

If Let's Encrypt issuance fails, temporarily disable the Cloudflare proxy. After NPM has a valid certificate, Cloudflare proxy may be enabled again.

SSL mode must be `Full` or `Full (Strict)`. Never use `Flexible`.

## Routing

Internal Nginx routes:

- `/` to the React/Vite SPA.
- `/api/*` to Laravel.
- `/go/*` to Laravel native landing pages.
- `/lp/*` to legacy redirects.
- `/landing-preview/*` to Laravel previews.
- `/landing-runtime/*` to the trusted LBX runtime.
- `/health` and `/health/ready` to Laravel.

Publishing a landing page with slug `n8n-freelancer` automatically serves:

```text
https://learn.bluxor.com/go/n8n-freelancer
```

No NPM edit, Docker restart, DNS change, or Nginx reload is required per landing page.

## Logs

```bash
docker logs learn-bluxor-web --tail=100
docker logs learn-bluxor-app --tail=100
docker logs learn-bluxor-queue --tail=100
docker logs learn-bluxor-scheduler --tail=100
docker logs learn-bluxor-redis --tail=100
docker logs -f learn-bluxor-app
```

## Smoke Tests

After NPM is configured:

```bash
curl -I https://learn.bluxor.com
curl -I https://learn.bluxor.com/health
curl -I https://learn.bluxor.com/health/ready
curl -I https://learn.bluxor.com/api/v1/products
```

Optional script:

```bash
MAIN_URL=https://learn.bluxor.com ./scripts/smoke-test.sh
```

Before NPM is configured:

```bash
INTERNAL_NPM_TEST=true ./scripts/smoke-test.sh
```

## Restart/Rebuild

```bash
docker compose restart
```

```bash
docker compose down
docker compose up -d --build
```

Do not use `docker compose down -v` during normal deployment because that removes persistent Learn by Bluxor volumes.

## MySQL Compatibility Verification

The Laravel test suite may use SQLite locally. On the server, after creating the MySQL database/user, verify MySQL migrations before public launch:

```bash
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan migrate --force
docker compose exec app php artisan test --do-not-cache-result
curl -I https://learn.bluxor.com/health/ready
```

This verifies Laravel boot, migrations, readiness, product/admin/customer/order/payment/landing tests, Redis queues, and MySQL connectivity in the production container network.
