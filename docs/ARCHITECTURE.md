# Architecture

The approved frontend remains in `design/project`. The backend is a Laravel 12 API in `backend`.

## Runtime Shape

```text
React/Vite frontend
  |
  | HTTPS JSON API, cookie auth, CSRF
  v
Laravel backend
  |
  |-- PostgreSQL in production
  |-- Redis for cache/queues in production
  |-- S3-compatible private object storage
  |-- SMTP/API mail transport
  |-- PipraPay payment adapter
  |-- First-party analytics tables
```

## Backend Modules

- Catalog: categories, tags, products, protected product files, bundles.
- Commerce: server-side quotes, orders, order items, coupons, PipraPay payment transactions, refunds.
- Access: entitlements, download events, guest access tokens.
- Identity: users, roles, session auth, email verification-ready account model.
- Landing pages: static package records, immutable versions, validation service.
- Landing runtime: native `/go/{slug}` delivery for safe Schema V2 HTML/CSS packages plus trusted first-party runtime.
- Analytics: raw event table with purchase attribution hooks.
- Admin: role-protected API group for products, orders, refunds, aggregated customers/LTV, coupons, analytics, audit logs, landing pages, and settings.

## Frontend Integration

The frontend now has `src/services/api/*` for API calls and adapters. Existing UI components remain unchanged where possible. Public product listing/detail and checkout are connected to backend endpoints with local mock fallback for design preview when the backend is unavailable.
