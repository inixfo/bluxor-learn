# Backend Mapping

This audit reflects the current React frontend in `design/project/src` and its centralized mock data in `src/data/store.ts`.

| Status | Frontend Screen | Route | Existing Data | Backend Requirement | API Needed |
| --- | --- | --- | --- | --- | --- |
| Partially implemented | Home | `/` | Still uses `store.ts`; backend has `/home` but UI is not wired | Published catalog, featured/new products, bundles, categories, testimonials/content blocks, newsletter capture | `GET /api/v1/home`, `POST /api/v1/newsletter-subscriptions` |
| Partially implemented | Product listing | `/products` | Wired to backend with local mock fallback | Published catalog search/filter/sort with pagination; category counts | `GET /api/v1/products`, `GET /api/v1/categories` |
| Partially implemented | Product detail | `/p/:slug` | Wired to backend with local mock fallback; related products still local | Product/bundle detail by slug, live prices/offers/files summary, related products; buy link carries offer ID | `GET /api/v1/catalog/{slug}`, `GET /api/v1/catalog/{slug}/offers` |
| Frontend only | Search overlay | Navbar modal | Filters local `products` | Public product search, trending terms | `GET /api/v1/search/products?q=...`, `GET /api/v1/search/trending` |
| Implemented | Login | `/login` | Wired to session API | Secure session login, remember option, role-aware redirect, rate limiting | `POST /api/v1/auth/login`, `GET /api/v1/auth/me` |
| Implemented | Register | `/register` | Wired to session API | Account creation, email verification, optional post-purchase registration path | `POST /api/v1/auth/register`, `POST /api/v1/auth/email/verification-notification` |
| Implemented | Forgot password | `/forgot-password` | Wired to backend reset request | Password reset request without enumeration | `POST /api/v1/auth/forgot-password` |
| Implemented | Reset password | `/reset-password` | Wired to backend reset route | Token validation and password update | `POST /api/v1/auth/reset-password` |
| Implemented | Checkout | `/checkout` | Wired to backend quote/order/PipraPay initiate, including landing-page offer keys; local fallback remains | Guest checkout, authenticated checkout, server-side totals, coupon validation, PipraPay payment initiation | `POST /api/v1/checkout/quote`, `POST /api/v1/checkout/orders`, `POST /api/v1/payments/piprapay/initiate` |
| Implemented | Purchase success | `/checkout/success` | Verifies PipraPay redirect when `pp_id` is present, then loads receipt/guest access APIs | Verified payment result, order receipt, guest access token, account creation CTA | `POST /api/v1/payments/piprapay/success`, `GET /api/v1/checkout/orders/{orderNumber}/receipt`, `GET /api/v1/guest/orders/{orderNumber}` |
| Implemented | Customer overview | `/account` | Wired to account overview API | Protected customer summary: recent orders, latest download, recommendations | `GET /api/v1/account/overview` |
| Implemented | Customer library | `/account/library` | Wired to entitlement-backed API | Entitlement-backed owned products and recommendations | `GET /api/v1/account/library` |
| Implemented | Customer library detail | `/account/library/:id` | Wired to entitlement-protected detail API | Entitlement-protected product resources and download actions | `GET /api/v1/account/library/{productId}`, `POST /api/v1/account/downloads/{fileId}` |
| Implemented | Customer orders | `/account/orders`, `/account/orders/:orderNumber` | Wired to account order list/detail APIs | Authenticated account order history; claimed guest purchases after verified email | `GET /api/v1/account/orders`, `GET /api/v1/account/orders/{orderNumber}` |
| Implemented | Customer downloads | `/account/downloads` | Wired to download list and signed URL creation | Entitlement-backed downloadable files and signed URL creation | `GET /api/v1/account/downloads`, `POST /api/v1/account/downloads/{fileId}` |
| Implemented | Customer profile | `/account/profile` | Wired to profile/password APIs | Profile update, password change, email-change verification, TOTP-ready security | `GET /api/v1/account/profile`, `PATCH /api/v1/account/profile`, `PUT /api/v1/account/password` |
| Partially implemented | Admin dashboard | `/admin` | UI uses static metrics; backend summary exists but UI not wired | Role-protected store KPIs, sales chart, funnel, traffic sources, recent orders, top products | `GET /api/v1/admin/dashboard?range=...` |
| Implemented | Admin products | `/admin/products` | Wired to backend list/search/filter | Admin product table with search/filter/pagination, CRUD states | `GET /api/v1/admin/products` |
| Partially implemented | Admin product editor | `/admin/products/new`, `/admin/products/:id/edit` | Wired for create/update/publish/status and protected paid file upload; media/gallery upload and bundle associations still partial | Product create/update, pricing, media upload, protected file upload, bundles, SEO, publish/archive | `GET/POST /api/v1/admin/products`, `GET/PATCH /api/v1/admin/products/{id}`, `POST /api/v1/admin/products/{id}/files`, `POST /api/v1/admin/products/{id}/publish`, `POST /api/v1/admin/products/{id}/archive` |
| Partially implemented | Admin orders | `/admin/orders` | Wired to backend list, existing modal, and full refund action | Order search/filter/pagination, order details, status history, invoice, refund controls | `GET /api/v1/admin/orders`, `POST /api/v1/admin/orders/{order}/refund` |
| Implemented | Admin customers | `/admin/customers` | Wired to email-aggregated registered/guest customer reporting with V1 LTV | Registered customers and guest purchasers, order counts, LTV, entitlements | `GET /api/v1/admin/customers` |
| Implemented | Admin coupons | `/admin/coupons` | Wired to list/create/edit/pause/archive APIs | Coupon CRUD, status, limits, usage tracking | `GET/POST /api/v1/admin/coupons`, `GET/PATCH /api/v1/admin/coupons/{coupon}`, `POST /api/v1/admin/coupons/{coupon}/pause`, `DELETE /api/v1/admin/coupons/{coupon}` |
| Backend only | Admin analytics | `/admin/analytics` | UI static; backend minimal summary exists | First-party analytics reports with date ranges and landing page version attribution | `GET /api/v1/admin/analytics/summary`, `GET /api/v1/admin/analytics/funnel`, `GET /api/v1/admin/analytics/products`, `GET /api/v1/admin/analytics/landing-pages` |
| Implemented | Public landing page | `/go/:slug` | Native Laravel-served HTML/CSS package with trusted runtime injection | Published landing page by slug, live context, runtime checkout/analytics, no uploaded JS | `GET /go/{slug}`, `GET /go/{slug}/{path}`, `GET /landing-runtime/lbx-runtime.v2.js` |
| Implemented | Admin landing pages | `/admin/landing-pages` | Wired to backend list and actual analytics summary | Landing page packages, versions, status, conversion/revenue metrics | `GET /api/v1/admin/landing-pages`, `GET /api/v1/admin/landing-pages/{id}/analytics` |
| Implemented | Admin landing page detail | `/admin/landing-pages/:id` | Wired to backend detail/version/preview/publish/download APIs plus offer assignment editor | Version list, immutable package downloads, preview, publish/unpublish, restore, offer assignment, delete/archive | `GET /api/v1/admin/landing-pages/{id}`, `PATCH /api/v1/admin/landing-pages/{id}/offers`, `POST /api/v1/admin/landing-pages/{id}/versions/{version}/publish`, `GET /api/v1/admin/landing-page-versions/{version}/preview-url`, `GET /api/v1/admin/landing-page-versions/{version}/download` |
| Implemented | Admin upload landing page | `/admin/landing-pages/upload` | Wired to Schema V2 ZIP upload/validation/publish | ZIP upload, no-JS validation, manifest parsing, offer assignment, preview, publish | `POST /api/v1/admin/landing-pages/uploads`, `POST /api/v1/admin/landing-pages/{id}/versions/{version}/publish` |
| Backend only | Admin settings | `/admin/settings` | Static settings form/fake save; backend get/update exists | Site, brand, payment, email, analytics, storage settings with masked secrets | `GET /api/v1/admin/settings`, `PATCH /api/v1/admin/settings/{section}` |
| Implemented | Admin/customer layouts | `/admin/*`, `/account/*` | Session-aware guards added; backend admin role middleware exists | Session-aware route guards and role authorization | `GET /api/v1/auth/me`, `POST /api/v1/auth/logout` |

## Mock Data To Replace

- `products`, `bundles`, `categories`, `testimonials`
- `adminOrders`, `adminCustomers`, `landingPages`, `coupons`
- `customerOrders`, `libraryItems`
- Static dashboard metrics, funnel arrays, traffic arrays, chart arrays
- Demo auth state in `Navbar.tsx`
- Fake checkout coupon/total/payment success logic
- Fake admin/customer save toasts where persistence is required
