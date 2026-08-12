# Analytics Architecture

The admin dashboards currently show revenue, orders, customers, visitors, conversion rate, AOV, top products, top landing pages, funnel, traffic sources, and devices. These must be produced from first-party analytics and verified commerce records.

## Event Model

Core events:

- `page_view`
- `landing_page_view`
- `product_view`
- `cta_click`
- `checkout_started`
- `payment_initiated`
- `payment_failed`
- `purchase`
- `download`
- `account_registered`
- `login`

Every event has a unique event UUID for deduplication.

## Identity

- Anonymous visitor ID in a privacy-conscious cookie.
- Session ID for visit grouping.
- Optional user ID after login.
- Optional checkout email/order ID after checkout.
- Preserve anonymous history when identity becomes known.

## Landing Page Attribution

Events and orders store both `landing_page_id` and `landing_page_version_id`. A sale from version 3 remains attributed to version 3 even after version 4 is published.

## Reporting

V1 stores raw events and calculates landing-page admin metrics from indexed event rows:

- visitors
- CTA clicks
- checkout started
- payment initiated
- purchases
- revenue

All reporting accepts server-side date ranges and timezone conversion. Store timestamps in UTC; default display timezone is configurable and initially expected to be `Asia/Dhaka`.

The public SDK posts anonymous visitor/session IDs. The backend stores hashes and does not fingerprint visitors.

## Deduplication

Purchase analytics should be emitted by the verified payment/order completion service, not by success page reloads. Payment webhook repeats must not create duplicate purchase events.
