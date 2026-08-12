# Payments

PipraPay is the active production payment provider. Core order/payment state stays provider-neutral through `PaymentCompletionService`, `PaymentEvent`, `PaymentTransaction`, and the `PaymentGateway` contract.

## Flow

1. Frontend submits a product, bundle, or landing-page offer plus optional coupon.
2. Backend reloads the offer and calculates trusted totals.
3. Backend creates a pending order and purchase-time order item snapshot.
4. Frontend calls `POST /api/v1/payments/piprapay/initiate`.
5. Backend calls PipraPay Create Charge with server-authoritative amount/currency and safe metadata: `order_uuid`, `order_number`, `payment_attempt_uuid`.
6. Browser redirects to PipraPay hosted checkout.
7. Redirect and webhook handlers verify the `pp_id` through PipraPay Verify Payment before settlement.
8. The completion service records an idempotency event, marks the order paid once, creates entitlements once, records purchase analytics once, and queues one purchase email.

## Verification

The app does not trust browser redirect status, frontend totals, query-string status, or webhook payloads alone. Verified PipraPay responses must match:

- internal order UUID and order number metadata
- provider payment ID
- completed/successful provider state
- order total amount
- order currency

Amount or currency mismatches return HTTP 422 and leave the order pending for review.

## Webhook Security

The webhook endpoint is `POST /api/v1/payments/piprapay/webhook`. It requires JSON, validates the PipraPay API-key header according to current PipraPay docs, and then performs server-side Verify Payment before marking an order paid.

## Idempotency

`payment_events` has a unique `(gateway, event_key)` constraint. Redirect callbacks, webhooks, retries, reloads, and webhook-after-redirect flows can happen in any order without duplicating entitlements, transactions, analytics conversions, or purchase emails.

## Refunds

Admins can request a full refund through `POST /api/v1/admin/orders/{order}/refund` with `confirm=true`.

The backend:

- refuses non-paid orders
- stores a refund attempt with an idempotency key
- calls PipraPay Refund Payment
- marks the order/payment refunded only after provider success
- revokes related entitlements by setting `status=revoked`, `revoked_at`, `revocation_reason`, and `revocation_reference`
- logs refund and entitlement mutation audit records

Partial refunds are intentionally not mapped to entitlement revocation in V1.

## Manual Test Status

Automated tests mock PipraPay HTTP responses. No live non-production PipraPay payment was completed in this phase because credentials were not available in the repository/session.
