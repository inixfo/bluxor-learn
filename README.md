# Learn by Bluxor

This workspace contains the approved React frontend design in `design/project` and a Laravel backend in `backend`.

## Local Apps

- Frontend: http://127.0.0.1:5173
- Backend API: http://127.0.0.1:8000/api/v1
- Backend health: http://127.0.0.1:8000

The backend local `.env` uses a writable sqlite database in the Windows temp directory because the scaffolded sqlite file under `backend/database` is locked in this sandbox. Production configuration is documented in `backend/.env.example` and targets PostgreSQL, Redis, and S3-compatible private storage.

## Current Coverage

Implemented:

- Laravel API routing under `/api/v1`
- Catalog/category/product/bundle endpoints
- Session auth endpoints and role middleware
- React login, registration, forgot/reset password, logout, and route guards
- Product, bundle, order, payment, entitlement, landing-page, analytics, and settings schema
- Guest checkout order creation with server-side pricing
- Hashed guest access tokens and verified-email claiming for paid guest purchases
- Coupon quote calculation
- Coupon admin create/edit/pause/archive with restrictions foundation
- PipraPay Create Charge, Verify Payment, webhook handling, and refunds with idempotent payment completion
- Entitlement creation for product and bundle purchases
- Queued purchase confirmation emails for registered customers and guests
- Admin audit log foundation for sensitive mutations
- Signed customer and guest download URLs backed by private product files
- Customer dashboard, library, orders, downloads, and profile API integration
- Admin product list/editor basics plus admin order/refund/customer/coupon/audit list integration
- Landing-page package engine: Schema V2 HTML/CSS/assets-only ZIP validation, immutable versions, preview, publish/restore, native public `/go/{slug}` serving, trusted runtime injection, live product/offer context, and analytics capture
- `landing-page-sdk/starter` with local build and package commands
- Seeded admin/customer/products/bundle/coupon/order/landing page data
- React API client with CSRF cookie support
- React product listing, product detail, and checkout integration with backend fallback

Development seed credentials:

- Admin: `admin@learn.bluxor.test` / `password`
- Customer: `rakib@example.com` / `password`

## Useful Commands

Backend:

```bash
cd backend
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Frontend:

```bash
cd design/project
npm.cmd install --cache .npm-cache --prefer-offline
npm.cmd run dev -- --host 127.0.0.1 --port 5173
```

Verification:

```bash
cd backend
vendor/bin/phpunit --do-not-cache-result

cd ../design/project
npm.cmd run typecheck
npm.cmd run build

cd ../../landing-page-sdk/starter
npm.cmd run build
npm.cmd run package
```

Current backend suite: 23 tests, 114 assertions.
