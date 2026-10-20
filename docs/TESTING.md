# Permanent integration tests

The former temporary CMS/user audit scripts are now versioned in `scripts/integration/`, with the isolated runner `scripts/integration-e2e.py`. No test depends on files in `/tmp`. The runner creates its own copied application, schema, accounts, uploads, PHP sessions and loopback PHP server for **each suite**.

## Run the full suite

1. Install dependencies and build locally:

   ```sh
   npm ci
   npm run build
   ```

2. Configure local MySQL in private `php/config.php`. Use development credentials that can CREATE/DROP DATABASE. The runner rejects remote database hosts and currently requires MySQL. Never use production credentials or a tunnel to production.
3. Run:

   ```sh
   npm run test:integration -- --local
   ```

The `--local` argument explicitly acknowledges creation/deletion of disposable databases. It creates uniquely named `integration_...` databases, applies all migrations, seeds a fixture owner and six services, and removes each database and temporary directory on normal success or failure. Existing application tables/uploads/configuration are not modified. No existing Vite/PHP development server is needed.

Each PHP test server gets a private session directory, its own port and disabled opcode caching so deliberate test configuration changes apply immediately. Mail is disabled in ordinary suites. For the 2FA suite PHP's mail handoff is captured to local `.eml` files through a fixture-specific sendmail command; nothing goes to an external mail server. There is no test transport, endpoint or bypass added to the production app.

The CMS reference suites assume the bundled posts/services/events/properties modules. If a derived project deliberately removes a reference module, adapt/select the relevant cases; the module-registry unit tests remain separate. The runner uses the built route manifest, so rebuild after changing public routes/modules.

## Coverage

| Suite | Checks |
| --- | --- |
| `auth` | Password-only sign-in, session bootstrap, CSRF, throttling, profile/password changes, session revocation, single-use password reset and logout. |
| `users` | Admin create/edit/ban/unban/delete, password changes, duplicates, Manager denial and owner/self protections. |
| `content` | Blog/event/property CRUD, drafts, schedules before/after publication, validation, immutable email, media type/upload/usage guards, bulk delete and preview data. |
| `services` | Seeded public listing/detail/schema, Manager CRUD, validation, duplicate slug, media, search/pagination, redirects, sitemap and activity. |
| `metadata_activity` | Metadata save/reset/fallbacks, visible content unchanged, noindex/sitemap behavior, role access, activity action coverage and pagination. |
| `dashboard` | Role-specific user counts, main-page discovery, module count parity and unattached media filtering. |
| `submissions` | Server pagination/CSV across 41 records, field/honeypot validation, initial unread state, read/unread and dashboard counts. |
| `two_factor` | Optional email enrollment/disable, owner and Manager opt-in, password-only default, local email capture, pending access denial, session binding, expiry, retry/resend throttling, replay, outages and recovery. |
| `security` | Protected endpoints, CSRF/session rotation, request limits, honeypots, form persistence/throttling, stored markup/link escaping, SQL-like searches, reset replay/stale credentials and safe bootstrap failures/headers. |

## Run one suite

```sh
npm run test:integration -- --local --suite=users
npm run test:integration -- --local --suite=content
npm run test:integration -- --local --suite=two_factor
```

Shortcut for the factor suite:

```sh
npm run test:two-factor
```

Each suite starts from a fresh database, so counters/state cannot leak between cases. Individual case files refuse to run outside the runner's marked fixture. A suite exits nonzero if a check fails, and subsequent suites stop. An abruptly killed process may leave an `integration_...` database or temporary directory; confirm it is a test artifact before manually cleaning it.

## Other permanent checks

```sh
npm run test:mail
npm run test:forms
npm run test:modules
npm run test:routes
npm run test:security
npm run test:console
npm run lint
npm run build
```

Operational command integration tests are also retained:

```sh
npm run test:installation:e2e
npm run test:backup:e2e
npm run test:maintenance:e2e
```

They create disposable databases and exercise installation/rerun safety, backup restoration (including import failure), maintenance gating/draining and retention cleanup. The older `test:security:e2e` command remains available for a running local development API; prefer the isolated runner's `security` suite because it does not exercise your actual development database.

Tests do not prove real shared-host Apache/TLS behavior, production permissions, email inbox delivery or host isolation. Complete those deployment checks on the intended host.
