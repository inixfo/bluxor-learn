# API Contract

Base namespace: `/api/v1`. Responses use JSON with a top-level `data` key.

## Checkout And Payments

- `POST /checkout/quote`: server-side price/coupon calculation.
- `POST /checkout/orders`: create pending guest or authenticated order.
- `POST /payments/piprapay/initiate`: create a PipraPay charge and return hosted checkout URL.
- `GET|POST /payments/piprapay/success`: browser return handler; verifies `pp_id` server-side.
- `GET|POST /payments/piprapay/cancel`: cancellation/failure handler.
- `POST /payments/piprapay/webhook`: PipraPay webhook; validates API-key header and verifies payment server-side.
- `GET /checkout/orders/{orderNumber}/receipt`: receipt and access state.
- `GET /guest/orders/{orderNumber}`: guest order detail plus signed downloads when the guest token is valid and order is paid.
- `GET /guest/downloads/{fileId}/{entitlementId}`: temporary signed guest download endpoint.

The frontend may send IDs and coupon codes. It must not send trusted prices, discounts, totals, or entitlement decisions.

## Admin

Admin endpoints require an authenticated user with the `admin` role.

- `GET /admin/dashboard`
- `GET /admin/products`
- `POST /admin/products`
- `GET /admin/products/{id}`
- `PATCH /admin/products/{id}`
- `POST /admin/products/{id}/publish`
- `POST /admin/products/{id}/archive`
- `POST /admin/products/{id}/files`
- `GET /admin/orders`
- `POST /admin/orders/{order}/refund`
- `GET /admin/customers`: email-aggregated registered and guest customer reporting with V1 LTV.
- `GET /admin/offer-items?type=product|bundle&q=`
- `GET /admin/coupons`
- `POST /admin/coupons`
- `GET /admin/coupons/{coupon}`
- `PATCH /admin/coupons/{coupon}`
- `POST /admin/coupons/{coupon}/pause`
- `DELETE /admin/coupons/{coupon}`
- `GET /admin/analytics/summary`
- `GET /admin/audit-logs`
- `GET /admin/audit-logs/{auditLog}`
- `GET /admin/settings`
- `PATCH /admin/settings/{section}`
- `GET /admin/landing-pages`
- `POST /admin/landing-pages/uploads`
- `GET /admin/landing-pages/{landingPage}`
- `PATCH /admin/landing-pages/{landingPage}/offers`
- `POST /admin/landing-pages/{landingPage}/versions/{version}/publish`
- `POST /admin/landing-pages/{landingPage}/unpublish`
- `GET /admin/landing-page-versions/{version}/preview-url`
- `GET /admin/landing-page-versions/{version}/download`
- `GET /admin/landing-pages/{landingPage}/analytics`

## Landing Page Runtime

- `GET /go/{slug}`: serves the published Schema V2 landing package entry with trusted runtime.
- `GET /go/{slug}/{path}`: serves published landing package assets.
- `GET /landing-runtime/lbx-runtime.v2.js`: first-party trusted landing runtime.
- `GET /lp/{slug}`: compatibility redirect to `/go/{slug}`.
- `GET /api/v1/landing-pages/{slug}/context`: public page/product/offer context.
- `POST /api/v1/analytics/events`: limited event ingestion for SDK events.
