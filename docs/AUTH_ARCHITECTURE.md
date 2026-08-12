# Auth Architecture

Use Laravel session authentication with HTTP-only cookies and CSRF protection for the React SPA. Do not store session tokens in localStorage.

## Roles

Initial roles:

- `customer`
- `admin`

Prepared roles:

- `editor`
- `support`

Admin routes under `/admin` must require the `admin` role. Customer account routes under `/account` must require an authenticated user and resource ownership.

## Customer Auth

Supported flows:

- Registration
- Login/logout
- Remember session
- Email verification
- Forgot/reset password
- Profile management
- Change password
- Verified-email claiming of previous guest purchases

Guest checkout remains available without registration. Orders have nullable `user_id`, required `customer_email`, and optional `customer_name`/`customer_phone`.

## Guest Purchase Claiming

When a user verifies an email address, the backend looks for paid guest orders and entitlements with the same normalized email and null `user_id`. Eligible records are associated with the verified account inside a transaction.

Unverified accounts never receive historical purchases.

## Security Controls

- CSRF on state-changing requests.
- Session regeneration after login.
- Rate limits for login, registration, forgot password, checkout, and payment callbacks.
- Generic forgot-password responses to reduce email enumeration.
- Email verification claims previous paid guest purchases for the same email.
- Account and admin frontend layouts call `/auth/me` before rendering protected screens.

Still planned:

- Model policies for every customer/admin resource beyond route/session checks.
- Audit logs for important admin mutations.
- TOTP-ready admin profile fields and settings.
