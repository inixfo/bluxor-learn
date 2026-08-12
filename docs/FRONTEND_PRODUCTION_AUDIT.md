# Frontend Production Audit

Scope: React/Vite routes in `design/project/src/App.tsx`, shared layouts, API services, navigation, and production-visible data usage.

| Route | Screen | Current State | Mock Data | Missing API | Missing UX | Final Status |
|---|---|---|---|---|---|---|
| `/` | Home | Uses `/api/v1/home` for featured products, categories, bundles, and new arrivals | Removed fake catalog/testimonials/newsletter form | None for active sections | External newsletter/social integrations are not shown | Production ready |
| `/products` | Product catalog | Uses `/api/v1/products` and `/api/v1/categories` | Removed local product fallback | Public bundle list endpoint not currently exposed | Bundle filter shows honest unavailable state | Connected |
| `/p/:slug` | Product/bundle detail | Uses `/api/v1/catalog/{slug}` and backend checkout identifiers | Removed local fallback product/bundle data | Related-products endpoint by canonical category slug is limited | Related products hidden if unavailable | Production ready |
| `/checkout` | Checkout | Uses backend quote, order creation, and PipraPay initiation | Removed fake price, fake coupon, fake success fallback | None | Field-level validation display is basic | Production ready |
| `/checkout/success` | Payment result | Uses server receipt and optional guest token downloads | No fake success mutation | None | Pending/failed routes are not separate React routes | Connected |
| `/login` | Login | Uses backend session login through shared auth context | None | None | Backend validation details are summarized | Production ready |
| `/register` | Register | Uses backend registration through shared auth context | None | None | Legal copy is minimal pending final review | Production ready |
| `/forgot-password` | Forgot password | Uses backend auth endpoint | None | None | Basic status messaging | Production ready |
| `/reset-password` | Reset password | Uses backend auth endpoint | None | None | Basic status messaging | Production ready |
| `/account` | Customer overview | Uses backend account overview | Removed recommended fixture cards | None | Shows latest/recent summaries only | Production ready |
| `/account/library` | Customer library | Uses backend entitlements | Removed fixture recommendations | None | None | Production ready |
| `/account/library/:id` | Library detail | Uses backend entitlement detail and signed download requests | None | None | "Read Online" disabled in backend terms until reader exists | Connected |
| `/account/orders` | Customer orders | Uses backend account orders | None | None | Pagination not yet surfaced | Connected |
| `/account/orders/:orderNumber` | Customer order detail | Uses backend account order detail | None | None | None | Production ready |
| `/account/downloads` | Downloads | Uses backend signed download authorization | None | None | None | Production ready |
| `/account/profile` | Profile | Uses backend profile/password APIs | None | None | Email change is intentionally disabled | Production ready |
| `/admin` | Admin dashboard | Uses backend dashboard summary | Removed fake metrics/charts/orders/products | Date-range metrics endpoint not available | Unsupported chart claims hidden | Production ready |
| `/admin/products` | Admin products | Uses backend product list/search/filter | None | Sales/revenue per product not exposed | Columns show `-` where unsupported | Connected |
| `/admin/products/new` | Product create | Uses backend create/publish/file upload after save | None | Gallery upload not implemented server-side | Gallery upload disabled | Connected |
| `/admin/products/:id/edit` | Product edit | Uses backend detail/update/publish/file upload | None | Bundle editor not implemented server-side | Bundle tab states next slice | Connected |
| `/admin/orders` | Admin orders | Uses backend orders/refund | None | Invoice endpoint not implemented | Invoice action is disabled until an endpoint exists | Connected |
| `/admin/customers` | Admin customers | Uses backend customer aggregation | None | Detail route not exposed | List only | Production ready |
| `/admin/coupons` | Coupons | Uses backend coupon CRUD/pause/archive | None | Advanced product/bundle restriction picker is basic | Works for core coupon fields | Connected |
| `/admin/analytics` | Analytics | Uses backend analytics summary | Removed fake funnel/UTM/product revenue table | Date-filtered analytics not exposed | Date range shown as all-time | Connected |
| `/admin/landing-pages` | Landing list | Uses backend landing pages and analytics | None | None | Metrics loaded per page | Production ready |
| `/admin/landing-pages/:id` | Landing detail | Uses backend detail/offers/versions/preview/publish/download/analytics | None | Drag reorder persists by array order only | Product/bundle search is basic | Production ready |
| `/admin/landing-pages/upload` | Landing upload | Uses backend V2 ZIP upload/validation/preview/publish | None | Product picker is ID input | Upload progress is spinner-only | Connected |
| `/admin/settings` | Settings | Uses backend settings for safe general fields | Removed fake payment/email/security/storage controls | Most secret settings are environment-managed | Unsupported sections are read-only explanatory | Production ready |
| `/admin/audit-logs` | Audit logs | Uses backend audit logs/filtering | None | Pagination not surfaced | Detail shown inline/basic | Production ready |
| `/about`, `/contact`, `/help`, `/faq`, `/terms`, `/privacy`, `/refund-policy` | Static pages | Minimal routed pages so footer links are not dead | No fake business data | Final legal/support content pending | Copy requires final review | Connected |
| `*` | Not found | Production 404 screen | None | None | None | Production ready |

## Production Data Rules

- Active product, category, order, customer, coupon, landing, audit, checkout, and admin metrics now come from API modules.
- `src/data/store.ts` contains only shared UI types and formatting helpers; demo business records were removed.
- Unsupported backend capabilities are hidden, disabled, or labeled as environment-managed rather than mocked.
