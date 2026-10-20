# Adding backend features

This project uses React for the interface and PHP JSON endpoints for data. MySQL is the default; the PDO connection also supports PostgreSQL and SQLite. There is no ORM or dependency container.

## Structure

```text
public/api/index.php           Entry point: initialize and dispatch the router
public/api/.htaccess           Apache API rewrites
php/config.php                Database configuration
php/db.php                    PDO connection
php/Router.php                HTTP method/path matching
php/routes.php                Endpoint registration
php/controllers/              Request orchestration
php/validation/               Shared checks and feature rules
php/models/                   Feature-specific database operations
php/database/crud.php         Shared prepared-statement CRUD functions
php/http/request.php          JSON request decoding
php/http/response.php         JSON responses and HTTP status
php/middleware/rateLimit.php  Existing database rate limiter
php/migrations/               Ordered schema changes
php/migrate.php               Migration runner
```

Request flow: route → controller → validation → model → shared CRUD → database. The controller returns the HTTP response. The model never reads request input or sends responses. Validation never opens a database connection.

## Standard content modules: use the registry

For Blog/Services/Events/Properties-style CRUD, follow [Content modules](CONTENT-MODULES.md). Run `php php/commands/make-module.php testimonials "Testimonials" testimonial`, then `php php/migrate.php migrate`. The registry supplies the API, sidebar, routes, validation profile, editor, activity, public pages and sitemap. No per-module wrapper classes or repeated mappings are required.

## Custom features outside the shared content workflow

1. Define the fields, allowed operations, routes, and access requirements. Only expose operations the feature needs. Subscribers currently has create and read; shared update/delete functions do not automatically create endpoints.
2. Add the next numbered migration in `php/migrations/`. Return an array containing `up` and `down` callables accepting PDO. Use driver-specific SQL where necessary. Do not edit a migration already applied to an existing database: add another migration for schema changes. Existing subscriber creation SQL remains MySQL-specific; the generic CRUD helpers do not make migrations portable.
3. Create `php/validation/CustomFeatureValidation.php`, following `SubscriberValidation.php`. Return only validated, normalized fields. Reuse `Validator` checks; add feature rules here. Define separate create/update validation when requirements differ. For updates, validate only supported fields and decide which may be omitted. PHP validation is authoritative; React validation improves user feedback.
4. Create `php/models/CustomFeature.php`, accepting a PDO connection. Hardcode the table and allowed columns here. Map validated fields explicitly rather than passing the entire request into CRUD. Put feature queries here.
5. Create `php/controllers/CustomFeatureController.php`. Decode the request, validate, obtain the connection, enforce access/rate limits, call the model, and return JSON. Catch expected validation errors separately from database/internal failures. Log internal failures; return generic messages to users.
6. Register only the required routes in `php/routes.php`, using `[$controller, 'action']`. The router supports placeholders such as `/posts/{id}`; handlers receive parameters in path order. Validate IDs before querying. Register specific paths before conflicting parameter paths.
7. In React, POST JSON to `/api/...`; handle HTTP status and `success`, loading, errors, and success. Keep frontend rules consistent with PHP. Send the `website` honeypot as a string for newsletter signup.
8. Test validation failures, successful saves, duplicates, missing records, pagination, and access rules. Check stored data, not just the toast.

## Shared database function reference

All functions take PDO first. Conditions are equality checks combined with AND; null uses IS NULL. They are not a general query language. Write custom prepared SQL in a model for joins, search, or more complex operations.

| Function | Arguments after PDO | Result |
|---|---|---|
| `createRecord` | table, data | Affected row count, not the inserted ID |
| `readRecord` | table, columns, nonempty conditions | One row or null; assumes an `id` column for ordering |
| `readRecords` | table, columns, conditions, limit, offset, orderBy, direction | Array of rows |
| `countRecords` | table, optional conditions | Matching row count |
| `updateRecords` | table, data, nonempty conditions | Driver-reported affected row count |
| `deleteRecords` | table, nonempty conditions | Deleted row count |

Read defaults: 20 records, offset 0, ordered by `id DESC`; maximum limit 1000. Pagination offset is `(page - 1) * pageSize`. Validate pagination inputs in your controller. Use `countRecords` to return totals. Update row counts can be zero when data is unchanged; do not automatically treat zero as “not found.”

Values use bound parameters. Identifiers are validated and quoted, but table/column names must still come from developer code. Never allow request bodies to select arbitrary tables or columns. Update/delete require conditions, but the caller must choose conditions narrow enough for the operation.

## Subscriber example

`POST /api/subscribers` uses `SubscriberController::create`, `SubscriberValidation::create`, and `Subscriber::create`.

- JSON must be an object.
- Honeypot `website` must be empty if supplied; malformed values are rejected.
- Email is trimmed, limited to 254 bytes, and checked with PHP email validation.
- Valid attempts are limited by the existing newsletter rate limiter (3 per 600 seconds).
- Success: 201. Validation: 422. Duplicate: 409. Rate limit: 429. Internal failure: 500.

`GET /api/subscribers?page=1` returns `{success: true, data: [{id, email, created_at}], pagination: {page, perPage, total, pages}}`. PHP caps each page at 20 rows, orders by newest ID, and clamps pages past the end to the last page. Invalid page values return 422. The React API module is `src/api/subscribers.ts`; the page owns fetching/loading/errors and the table receives rows and pagination as props.

`GET /api/subscribers?export=csv` streams all subscribers as CSV, independent of the displayed page. Dates in the export are UTC. Subscriber reads and CSV exports require an active Admin or Manager session.

Migration `003_add_subscriber_created_at.php` adds a nullable timestamp. New subscribers are stored with UTC timestamps; existing records remain null because historical dates are unknown. The UI displays a dash for missing dates. Dummy subscriber data has been removed.

## Commands

From the project root:

```sh
php php/migrate.php status
php php/migrate.php migrate
php php/migrate.php rollback
npm run build
npm run lint
```

Rollback reverses the latest batch and can delete its tables/data. The migration runner now reads `php/migrations/`; existing migration filenames are retained so recorded history still matches. Migrations were moved, not rerun by this restructure.

Local React uses `PHP_API_URL` in `.env.local` through Vite's `/api` proxy. Production needs PHP routing and React deep-link rewrites on the host. Keep `php/` outside the public document root. Deploy backend source as well as the frontend build; Vite only packages the public frontend assets.

## Verification checklist

- PHP syntax checks pass for every changed PHP file.
- Check shared CRUD and validation behavior using an isolated test database.
- Test POST through the actual Vite proxy and PHP server.
- Empty honeypot succeeds; filled/malformed honeypot never saves.
- Invalid JSON/email never saves.
- Valid email is stored once; duplicates return 409 (unless rate-limited).
- Read returns the saved record.
- Loading and success/error UI behave correctly.
- Remove only uniquely named test records after verification.
- Build and lint pass.

## Contact inquiries

Contact uses the same modular flow: `ContactController`, `ContactValidation`, `Contact` model, and shared CRUD. Migration `004_create_contacts.php` creates the table. New rows always use the database default `is_read = false`; public callers cannot override status.

- `POST /api/contacts`: name, email, optional phone, interest, message, and empty website honeypot. Valid inquiries are limited to 3 per connecting IP per 600 seconds, independently of newsletter limits. Returns 201 or 422/429/500.
- `GET /api/contacts?page=1`: newest first, 20 per page, with pagination metadata. Read status is a JSON boolean.
- `PATCH /api/contacts/{id}/read`: accepts a JSON boolean `{ "is_read": true }` or false; returns the updated inquiry. Invalid IDs/status return 422, missing inquiries return 404.

The Contact page submits through `src/api/contacts.ts`. The admin `ContactInbox` lists inquiries, shows details, marks a viewed inquiry read, and allows switching back to unread. Submission success replaces the public form with a thank-you message. Contact lists and read-status updates now require an active Admin or Manager session; read-status updates also require a CSRF token.


## Authentication

See [Authentication setup and workflow](AUTHENTICATION.md) for initial administrator creation, session behavior, protected routes, rate limits, and password recovery.

For roles and account operations, see [User management](USER-MANAGEMENT.md).

For the CMS modules, shared media picker, scheduling, and upload deployment, see [Content management](CONTENT-MANAGEMENT.md).

See [SEO setup](SEO.md) for the PHP frontend entry point, public content routes, metadata, and sitemaps.

## Reusable frontend forms and field errors

Use [the shared form pattern](FORMS.md) for new feature forms. It standardizes pending states, field/global errors and success feedback. PHP field validation uses `Validator::field()` / `Validator::fields()` and the existing API exception boundary; business-specific validation stays in its module.
