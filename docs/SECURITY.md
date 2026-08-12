# Security Notes

## Network Exposure

Only `learn-bluxor-web` is reachable by Nginx Proxy Manager over the external `proxy` Docker network, and it uses `expose: "80"` with no host `ports:`.

Private services have no public ports:

- `learn-bluxor-app`
- `learn-bluxor-queue`
- `learn-bluxor-scheduler`
- `learn-bluxor-redis`

`app`, `queue`, and `scheduler` also join `proxy` only so Laravel can reach the existing `mysql-server:3306`.

## HTTPS And Trusted Proxies

Production request path:

```text
Cloudflare -> Nginx Proxy Manager -> learn-bluxor-web -> learn-bluxor-app
```

Laravel trusts private Docker proxy ranges from `TRUSTED_PROXIES` and uses forwarded host/proto/port headers to generate HTTPS URLs and secure cookies.

Recommended:

```env
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
SESSION_SECURE_COOKIE=true
CORS_ALLOWED_ORIGINS=https://learn.bluxor.com
```

Cloudflare SSL mode must be `Full` or `Full (Strict)`, never `Flexible`.

## Upload Limits

Upload limits are aligned for product files and landing ZIPs:

- Nginx Proxy Manager advanced config: `client_max_body_size 64m`
- Internal Nginx: `client_max_body_size 64m`
- PHP: `upload_max_filesize=64M`, `post_max_size=70M`
- Laravel landing package validation remains authoritative.

## Landing Package V2

The native `/go/{slug}` landing architecture remains server-rendered and security constrained:

- V2 packages only.
- No uploaded JavaScript.
- No inline scripts.
- No event handlers.
- No `javascript:` URLs.
- Trusted `/landing-runtime/lbx-runtime.v2.js` only.
- Dynamic prices and checkout context are server-authoritative.

## Secrets

Keep these only in `.env.docker` or a host secret manager:

- `APP_KEY`
- `DB_PASSWORD`
- `PIPRAPAY_API_KEY`
- SMTP credentials
- object storage credentials

Do not expose secrets through Vite/frontend environment variables.
