# Production Features Phase 2

## Email

Laravel sends mail through environment-managed SMTP settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Learn by Bluxor"
ADMIN_NOTIFICATION_EMAIL=
```

Purchase confirmations use the existing queue worker. Admins can queue a safe diagnostic email from Settings > Email without exposing stored SMTP credentials.

## Product And Category Images

Product cover images and category images accept JPEG, PNG, and WEBP up to 5 MB. Uploads are stored on Laravel's `public` disk under `/storage/*` using generated UUID filenames. The production Docker stack persists this disk with `learn_bluxor_public_storage`, mounted into both the Laravel app and the web container.

## Landing Product Reassignment

Admin landing pages support changing `primary_product_id` after creation. The V2 package, `/go/{slug}` route, CSP, uploaded-JS prohibition, and trusted runtime remain unchanged. Runtime `data-lbx-*` bindings resolve from the new associated product automatically.

## Content Pages And Contact

Footer/support/legal pages are backed by editable `content_pages` records. Required public routes:

```text
/about
/contact
/help
/faq
/download-help
/terms
/privacy
/refund-policy
```

Contact submissions are stored in `contact_inquiries`, queue a notification email to `ADMIN_NOTIFICATION_EMAIL`, and create an admin notification.

## Categories

Admin category management supports list, create, edit, activate/deactivate, delete safety, display order, and optional image upload. Public category listings load only active categories ordered by `sort_order`, and `/categories/{slug}` shows associated products.

## Notifications

Admin notifications are stored in `admin_notifications` and exposed through admin-only polling endpoints. Notifications are created for successful orders, refunds, and contact submissions. Duplicate payment callbacks do not create duplicate paid-order notifications because notification identity is keyed by event type and entity.

## Google Authentication

Google OAuth uses the existing Laravel web session guard. Required environment:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://learn.bluxor.com/api/v1/auth/google/callback
```

OAuth state is stored in the session and validated on callback. Return destinations are restricted to safe internal paths such as `/login`, `/checkout`, and `/account`. Checkout state is preserved by returning to `/checkout` with its original query string. Google does not supply the PipraPay mobile number, so checkout still requires the customer to enter it before payment initiation.
