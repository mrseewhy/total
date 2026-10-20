# Authentication setup and workflow

The app uses PHP sessions, not browser-stored access tokens. React displays the authenticated user from the session API. PHP enforces access on every private request. There is no public registration.

## First administrator

The authentication migration has been applied to the local database. For a new installation:

```sh
php php/migrate.php migrate
php php/commands/create-admin.php
```

The command prompts for name, email, password, and confirmation. Password entry is hidden on macOS/Linux; on Windows the command currently echoes input. Passwords must be 12–72 bytes. Emails are normalized to lowercase. Passwords are hashed using PHP `password_hash(PASSWORD_DEFAULT)`. The command does not accept a password argument, so it need not appear in shell history. No permanent administrator or default password was created by the implementation.

Open `/site-admin/login` and sign in using that account. Do not use the temporary accounts from verification: they have been deleted.

## Roles

Admins manage users. Managers access the dashboard, subscribers, contacts, and their own profile. See [User management](USER-MANAGEMENT.md).

## Pages

- `/site-admin/login`: sign in and return to the requested admin page.
- `/site-admin/forgot-password`: request a recovery link.
- `/site-admin/reset-password?token=...`: choose and confirm a new password.
- `/site-admin/profile`: edit your name, see your email/role, and change your password by providing the current password. Email changes are intentionally not exposed without a verification flow.
- All other admin pages require authentication. The sidebar and dropdown both perform a real POST logout.

## Backend structure

```text
php/auth.config.php                 Cookie and recovery-email settings
php/commands/create-admin.php       CLI administrator creation
php/controllers/AuthController.php Session, login, logout, recovery actions
php/controllers/ProfileController.php Profile/password actions
php/validation/AuthValidation.php   Email/password rules
php/models/User.php                 User persistence and public account fields
php/models/PasswordReset.php        Hashed, expiring, single-use reset tokens
php/functions/session.php          Cookie/session initialization and rotation
php/functions/mail.php             Reset-email delivery using PHP mail()
php/middleware/auth.php            Current user and Admin authorization
php/middleware/csrf.php            CSRF verification
src/api/auth.ts                     Shared authenticated fetch and CSRF handling
src/auth/                           React auth state/provider
src/components/auth/               Auth layout and protected route boundary
src/pages/auth/                     Login/recovery pages
```

Routes are registered in `php/routes.php`. Private subscriber/contact routes use `requireStaff()` for Admins and Managers. User-management routes use `requireAdmin()`. Mutations additionally require `verifyCsrf()`. Public newsletter/contact POST routes remain public and retain their honeypots and independent rate limits.

To protect a new private route, check `requireStaff()` or `requireAdmin()` according to its allowed roles before calling the controller. Also check `verifyCsrf()` before state changes. For example, follow the existing contact read-status route. Do not rely on the React route guard to secure the API.

React private API modules use `authenticatedFetch()` so session expiry is detected and CSRF headers are attached to writes. Profile/auth actions use `authAction()`. Do not store passwords, session IDs, or CSRF values in localStorage.

## API

| Method / path | Behavior |
|---|---|
| GET `/api/auth/me` | Returns user or null plus a CSRF token; starts an anonymous session if needed |
| POST `/api/auth/login` | Requires CSRF and credentials; rotates session ID and token on success |
| POST `/api/auth/logout` | Requires CSRF; destroys session and expires cookie |
| PATCH `/api/auth/profile` | Requires Admin session and CSRF; updates name |
| POST `/api/auth/password` | Requires Admin session, CSRF, current password, new password, confirmation |
| POST `/api/auth/forgot-password` | Requires CSRF; returns a generic response regardless of account existence |
| POST `/api/auth/reset-password` | Requires CSRF, token, new password, confirmation |

A normal browser request includes the session cookie automatically. `X-CSRF-Token` must match the session token for the protected actions. `/auth/me` is the bootstrap; its token is valid even before login. Sessions are regenerated on login/password change. Responses from session handling are marked no-store.

Status codes: 401 unauthenticated/invalid login; 403 wrong role; 419 missing or invalid CSRF; 422 validation/invalid reset; 429 throttled; 500 internal failure. Server exceptions are logged without exposing database errors to the browser.

## Session rules

Cookie: `HttpOnly`, `SameSite=Lax`, path `/`, session lifetime in the browser. PHP strict session IDs and cookie-only sessions are enabled. Secure cookies are automatic when PHP sees HTTPS; explicitly set `secure_cookie` true for production behind a TLS-terminating proxy. Do not blindly trust forwarded headers.

Sessions expire after 30 minutes of API inactivity or 12 hours total. Each protected API call loads the current user, verifies active status and the required role, and checks the session version. Password changes increment the version, invalidating other sessions while rotating the current session. Password reset invalidates all existing sessions. User role or activation changes take effect on the next private request.

## Rate limits

Uses the existing database limiter and server-provided connecting IP:

- Login: 5 attempts per normalized email per 15 minutes; 30 attempts per IP per 15 minutes. A successful login clears the email counter. The IP limit counts successful and failed attempts.
- Recovery request: 3 per normalized email per hour; 20 per IP per hour.
- Reset-token submission: 10 per IP per 15 minutes.
- Authenticated password changes: 5 per user per 15 minutes.

429 responses include Retry-After. These are temporary cooldowns, not permanent account locks. Configure trusted proxies deliberately if hosting changes which address PHP sees.

## Password recovery delivery

Configure the trusted HTTPS `app_url` and secure cookies in private `php/auth.config.local.php`. Configure sending separately in `php/mail.config.local.php`: `enabled`, `transport` (`mail`), `from_address`, `from_name` and optional `reply_to`. Delivery defaults to disabled. Legacy authentication `mail_from` remains a fallback, but new projects should use the dedicated mail config.

Follow [PHP mail configuration](MAIL.md) for exact per-website setup, environment variables and health checks. Your host must enable/configure PHP `mail()`; the app does not run an email server. No real email was sent during implementation. Delivery failures are logged without reset tokens/links; the public recovery response stays generic to avoid account enumeration.

Tokens contain 32 random bytes, expire after 30 minutes, are stored only as SHA-256 hashes, and can be consumed once. Creating a new link replaces the previous one. Tokens are not returned by the API or logged. Reset consumption and password updates run in a transaction.

## Verification performed

Local MySQL/API checks covered anonymous access rejection (including CSV export), missing CSRF, login email throttling, successful login, profile update, password change, invalidation of another session, token consumption/reuse rejection, and logout. A temporary admin was also used to check real browser login, account details, and logout; temporary users were removed. Email transport must still be verified after configuration.

## Optional email two-factor authentication

Users can opt in through Profile settings; every account starts with it disabled. Enabling/disabling requires the current password and an emailed confirmation code. Enabled sign-ins require an email code after the password; other accounts sign in normally. See [2FA setup/recovery](TWO-FACTOR.md) and [permanent integration tests](TESTING.md).
