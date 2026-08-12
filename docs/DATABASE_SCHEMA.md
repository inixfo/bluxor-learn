# Database Schema

V1 backend target: PostgreSQL, UTC timestamps, money stored in integer minor units, soft deletes or archival for commerce records.

## Identity And Access

- `users`: id, uuid, name, email, email_verified_at, phone, password, remember_token, status, last_login_at, timestamps, soft deletes.
- `roles`: id, name, timestamps.
- `permissions`: id, name, timestamps.
- `role_user`: user_id, role_id.
- `permission_role`: permission_id, role_id.
- `password_reset_tokens`: email, token, created_at.
- `sessions`: Laravel session storage if database sessions are enabled.
- `audit_logs`: id, actor_user_id, action, auditable_type, auditable_id, metadata jsonb, ip_hash, user_agent_hash, created_at.

## Catalog

- `categories`: id, uuid, name, name_bn, slug, description, image_path, status, sort_order, timestamps.
- `tags`: id, name, slug, timestamps.
- `products`: id, uuid, category_id nullable, name, name_bn, slug, short_description, description, product_type, status, regular_price_minor, sale_price_minor nullable, currency, cover_image_path, featured_image_path, featured, published_at, metadata jsonb, timestamps, soft deletes.
- `product_tag`: product_id, tag_id.
- `product_files`: id, uuid, product_id, name, file_type, file_size_bytes, storage_disk, storage_path, version, download_limit nullable, download_expiration_days nullable, status, sort_order, timestamps.
- `bundles`: id, uuid, name, name_bn, slug, description, status, cover_image_path, regular_value_minor, bundle_price_minor, sale_price_minor nullable, currency, published_at, timestamps, soft deletes.
- `bundle_product`: bundle_id, product_id, sort_order.

## Commerce

- `coupons`: id, code, type, amount_minor nullable, percentage_bps nullable, status, starts_at, expires_at, usage_limit nullable, per_customer_limit nullable, minimum_order_minor, currency, metadata jsonb, timestamps.
- `coupon_product`: coupon_id, product_id.
- `coupon_bundle`: coupon_id, bundle_id.
- `coupon_usages`: id, coupon_id, order_id, user_id nullable, customer_email, used_at.
- `orders`: id, uuid, order_number, user_id nullable, customer_name, customer_email, customer_phone, order_status, payment_status, subtotal_minor, discount_minor, total_minor, currency, coupon_id nullable, landing_page_version_id nullable, metadata jsonb, timestamps.
- `order_items`: id, order_id, purchasable_type, purchasable_id, product_id nullable, bundle_id nullable, product_name, product_slug, quantity, unit_price_minor, discount_minor, total_minor, currency, snapshot jsonb, timestamps.
- `payment_transactions`: id, uuid, order_id, gateway, provider_transaction_id nullable, provider_reference nullable, amount_minor, currency, status, initiated_at, paid_at nullable, failed_at nullable, raw_response jsonb, timestamps.
- `payment_events`: id, gateway, event_key, provider_transaction_id nullable, order_id nullable, processed_at nullable, payload_hash, payload jsonb, timestamps. Unique index on `gateway,event_key`.
- `order_status_history`: id, order_id, from_status nullable, to_status, actor_user_id nullable, reason nullable, metadata jsonb, created_at.
- `entitlements`: id, uuid, user_id nullable, order_id, order_item_id, product_id, customer_email, status, granted_at, expires_at nullable, revoked_at nullable, timestamps. Unique active entitlement guard on order_item_id/product_id.

## Downloads

- `download_events`: id, entitlement_id, user_id nullable, order_id, product_id, product_file_id, customer_email, ip_hash nullable, user_agent_hash nullable, downloaded_at.
- `guest_access_tokens`: id, order_id, token_hash, email, expires_at, last_used_at nullable, revoked_at nullable, timestamps.

## Landing Pages

- `landing_pages`: id, uuid, name, slug, status, primary_product_id nullable, published_version_id nullable, timestamps, soft deletes.
- `landing_page_versions`: id, uuid, landing_page_id, version_number, package_path, manifest jsonb, entry_path, checksum, sdk_version, status, created_by, published_at nullable, timestamps. Versions are immutable after validation.
- `landing_page_offers`: id, landing_page_id, landing_page_version_id nullable, offer_type, product_id nullable, bundle_id nullable, sort_order, is_primary, timestamps.
- `landing_page_validation_results`: id, landing_page_version_id, status, checks jsonb, warnings jsonb, errors jsonb, created_at.

## Analytics

- `analytics_visitors`: id, visitor_key_hash, first_seen_at, last_seen_at.
- `analytics_sessions`: id, visitor_id, session_key_hash, landing_page_id nullable, landing_page_version_id nullable, started_at, ended_at nullable, utm jsonb, referrer nullable, device jsonb, geo jsonb.
- `analytics_events`: id, event_uuid, visitor_id nullable, session_id nullable, user_id nullable, order_id nullable, landing_page_id nullable, landing_page_version_id nullable, product_id nullable, bundle_id nullable, event_name, properties jsonb, occurred_at. Unique index on `event_uuid`.
- `analytics_daily_summaries`: id, date, timezone, landing_page_id nullable, landing_page_version_id nullable, product_id nullable, visitors, cta_clicks, checkout_started, payment_initiated, purchases, revenue_minor, currency, timestamps.

## Settings And Content

- `settings`: id, group, key, value jsonb, encrypted, timestamps. Secrets are encrypted and never returned unmasked.
- `media_assets`: id, uuid, visibility, disk, path, original_name, mime_type, size_bytes, uploaded_by, metadata jsonb, timestamps.
- `newsletter_subscriptions`: id, email, status, source, metadata jsonb, timestamps.
