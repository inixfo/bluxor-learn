# Frontend Route Inventory

Source of truth: `design/project/src/App.tsx`.

| Route | Component | Layout | Auth Boundary | Primary Backend Dependency | Notes |
|---|---|---|---|---|---|
| `/` | `pages/Home.tsx` | Public | Public | `GET /api/v1/home` | Shows only backend catalog sections with data. |
| `/products` | `pages/Products.tsx` | Public | Public | `GET /api/v1/products`, `GET /api/v1/categories` | Bundle filter is unavailable until a public bundle index exists. |
| `/p/:slug` | `pages/ProductDetail.tsx` | Public | Public | `GET /api/v1/catalog/{slug}` | Product and bundle IDs are taken from the API before checkout. |
| `/login` | `pages/auth/Login.tsx` | Public | Guest-friendly | `POST /api/v1/auth/login` | Updates shared auth context. |
| `/register` | `pages/auth/Register.tsx` | Public | Guest-friendly | `POST /api/v1/auth/register` | Updates shared auth context. |
| `/forgot-password` | `pages/auth/ForgotPassword.tsx` | Public | Public | `POST /api/v1/auth/forgot-password` | Server response drives status copy. |
| `/reset-password` | `pages/auth/ResetPassword.tsx` | Public | Public | `POST /api/v1/auth/reset-password` | Requires token/email query state. |
| `/about` | `pages/StaticPage.tsx` | Public | Public | None | Placeholder legal/company copy for final content review. |
| `/contact` | `pages/StaticPage.tsx` | Public | Public | None | Placeholder support copy. |
| `/help` | `pages/StaticPage.tsx` | Public | Public | None | Placeholder help copy. |
| `/faq` | `pages/StaticPage.tsx` | Public | Public | None | Placeholder FAQ copy. |
| `/terms` | `pages/StaticPage.tsx` | Public | Public | None | Needs final legal review. |
| `/privacy` | `pages/StaticPage.tsx` | Public | Public | None | Needs final legal review. |
| `/refund-policy` | `pages/StaticPage.tsx` | Public | Public | None | Needs final legal review. |
| `/checkout` | `pages/checkout/Checkout.tsx` | Checkout | Public/Customer | `POST /api/v1/checkout/quote`, `POST /api/v1/checkout/orders`, `POST /api/v1/payments/piprapay/initiate` | Requires backend quote before placing an order. |
| `/checkout/success` | `pages/checkout/PurchaseSuccess.tsx` | Checkout | Public/Customer | `GET /api/v1/checkout/orders/{order}/receipt`, optional guest order endpoint | UI does not mark success until a paid receipt is returned. |
| `/account` | `pages/customer/Overview.tsx` | Customer | Authenticated customer | `GET /api/v1/account/overview` | Protected by `CustomerLayout`. |
| `/account/library` | `pages/customer/Library.tsx` | Customer | Authenticated customer | `GET /api/v1/account/library` | Entitlement-backed only. |
| `/account/library/:id` | `pages/customer/LibraryDetail.tsx` | Customer | Authenticated customer | `GET /api/v1/account/library/{id}`, `POST /api/v1/account/downloads/{file}` | Download URLs are requested just in time. |
| `/account/orders` | `pages/customer/Orders.tsx` | Customer | Authenticated customer | `GET /api/v1/account/orders` | Backend order history. |
| `/account/orders/:orderNumber` | `pages/customer/OrderDetail.tsx` | Customer | Authenticated customer | `GET /api/v1/account/orders/{orderNumber}` | Backend order detail. |
| `/account/downloads` | `pages/customer/Downloads.tsx` | Customer | Authenticated customer | `GET /api/v1/account/downloads`, `POST /api/v1/account/downloads/{file}` | Protected file access. |
| `/account/profile` | `pages/customer/Profile.tsx` | Customer | Authenticated customer | `GET/PATCH /api/v1/account/profile`, `PUT /api/v1/account/password` | Email change remains disabled. |
| `/admin` | `pages/admin/Dashboard.tsx` | Admin | Admin role | `GET /api/v1/admin/dashboard` | No synthetic charts. |
| `/admin/products` | `pages/admin/Products.tsx` | Admin | Admin role | `GET /api/v1/admin/products` | Shows unsupported metrics as unavailable. |
| `/admin/products/new` | `pages/admin/ProductEditor.tsx` | Admin | Admin role | `POST /api/v1/admin/products` | File upload requires saved product. |
| `/admin/products/:id/edit` | `pages/admin/ProductEditor.tsx` | Admin | Admin role | `GET/PATCH /api/v1/admin/products/{id}` | Publish and protected file upload are API-backed. |
| `/admin/orders` | `pages/admin/Orders.tsx` | Admin | Admin role | `GET /api/v1/admin/orders`, `POST /api/v1/admin/orders/{id}/refund` | Invoice export is disabled until implemented. |
| `/admin/customers` | `pages/admin/Customers.tsx` | Admin | Admin role | `GET /api/v1/admin/customers` | List and aggregation only. |
| `/admin/coupons` | `pages/admin/Coupons.tsx` | Admin | Admin role | Coupon CRUD admin endpoints | Core coupon fields are API-backed. |
| `/admin/analytics` | `pages/admin/Analytics.tsx` | Admin | Admin role | `GET /api/v1/admin/analytics/summary` | All-time backend summary. |
| `/admin/landing-pages` | `pages/admin/LandingPages.tsx` | Admin | Admin role | Landing page admin endpoints | Includes per-page analytics calls. |
| `/admin/landing-pages/:id` | `pages/admin/LandingPageDetail.tsx` | Admin | Admin role | Landing detail/offers/versions/analytics endpoints | Native `/go/{slug}` architecture. |
| `/admin/landing-pages/upload` | `pages/admin/UploadLandingPage.tsx` | Admin | Admin role | V2 landing upload/validate/preview/publish endpoints | Product/bundle offer IDs are entered explicitly. |
| `/admin/settings` | `pages/admin/Settings.tsx` | Admin | Admin role | `GET/PATCH /api/v1/admin/settings/{section}` | Only safe general settings are editable. |
| `/admin/audit-logs` | `pages/admin/AuditLogs.tsx` | Admin | Admin role | `GET /api/v1/admin/audit-logs` | Filter-backed audit history. |
| `*` | `pages/NotFound.tsx` | None | Public | None | Production 404. |
