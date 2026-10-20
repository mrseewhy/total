# Blog, events, properties, and media

## Using the dashboard

Sign in as the site owner (Admin) or a Manager. Both roles can manage all content and media. Only the owner can manage users. Dashboard user creation creates Managers only; an API caller cannot promote a Manager to Admin, change an account email, or manage another Admin account. The trusted server-side first-admin command remains available for setup.

Admin navigation:

- **Blog** (`/site-admin/posts`): searchable, paginated posts with draft, scheduled, and published filters. Select one or all items on the current page for confirmed bulk deletion.
- **Media library** (`/site-admin/media`): image upload, previews, search, attachment details, selection, and confirmed individual/bulk deletion.
- **Events** (`/site-admin/events`): create/edit/preview/delete events with location and start/end times.
- **Properties** (`/site-admin/properties`): create/edit/preview/delete listings with price, currency, location, sale/rent type, bedrooms, bathrooms, availability, featured image, and gallery.
- **Profile settings**: edit your own name and change your password with current-password verification. Email is read-only and PHP rejects changes.

Content lists show 20 records per page. Media shows 24 thumbnails per page. Selection in the content lists resets on page/filter changes; media selections can span pages. Bulk operations accept at most 100 IDs and are all-or-nothing.

## Writing and publishing

Create a post and enter its title. A slug is generated until you edit the slug yourself. Slugs must be unique within the content type and contain lowercase ASCII letters, numbers, and hyphens. Excerpts are optional.

The React editor uses BlockNote. Its static formatting toolbar stays above the document, pinned below the save header while scrolling through the editor. Format headings, lists, text, quotes, code, or tables. **Insert image** opens the shared media picker; images can also be uploaded through BlockNote's upload handler. JPEG/PNG/WebP/GIF images are supported; arbitrary external image URLs and embedded HTML are not accepted by the backend. Links may use HTTP(S), mailto, tel, fragments, or local paths.

The static-toolbar integration follows [BlockNote's official example](https://www.blocknotejs.org/examples/ui-components/static-formatting-toolbar). Editor code/styles are loaded lazily on editor/preview pages rather than on the public home page.

- **Draft**: saves without public visibility.
- **Published**: visible immediately through the public content API.
- **Scheduled** (blog only): choose a future date/time. The UI shows your browser timezone and converts to UTC for PHP/database storage. Saving an existing published post preserves its publication date.

Scheduling does not depend on cron: public queries only return published/scheduled records whose `publish_at` has arrived. A due scheduled post is displayed as published in the admin using `effective_status`; the underlying stored status can remain `scheduled`. Changing it to Draft removes public visibility again. Configure the server clock correctly.

Edit pages warn before navigating away with unsaved changes. Save drafts before opening the private preview from the list. Preview renders saved BlockNote JSON read-only and includes the featured image, gallery, and feature-specific details.

Public listing/detail pages now exist at /blog, /events, and /properties with SEO metadata and publication-aware visibility. See [SEO setup](SEO.md). Do not use the private admin API as a public data source.

For the complete Git build/push/pull process, production configuration, and directory layout, follow the [deployment guide](DEPLOYMENT.md).

## Media storage on shared hosting

Original files are stored in **`public/uploads/`** by default (override the document root using `php/paths.config.local.php` for `public_html`) and served at `/uploads/<random-filename>`. PHP generates 128-bit random names and selects extensions from detected image MIME, never from the supplied extension. Attachment metadata lives in the `media` database table.

Limits:

- JPEG, PNG, WebP, GIF only (no SVG, HTML, scripts, arbitrary documents, audio, or video).
- Maximum 8 MB per file; UI uploads at most 10 files in one batch, one request per file.
- Maximum 10,000 pixels in either dimension and 40 megapixels total.
- PHP verifies upload status, image headers, and MIME with fileinfo/getimagesize.
- The host's `upload_max_filesize` and `post_max_size` must permit the desired size (for example, 8M and 10M).

`public/uploads/.htaccess` disables executable handlers and directory listing for Apache and denies dangerous file types. Preserve this file. If using another web server, configure equivalent static-only serving; do not execute scripts from the uploads directory.

PHP needs write permission on `public/uploads/` and **`php/storage/`**. Use appropriate ownership and permissions for your host, not globally writable permissions. Storage is private and is used briefly to quarantine files during deletion. A database rollback restores quarantined files. Used images cannot be deleted: remove the image from every saved post/event/property (including drafts) first. The attachment detail view links to those records.

Featured images, property galleries, and inline BlockNote image URLs are tracked in `content_media`. Deleting content releases its references but keeps files in the media library. Unsaved editor changes are not tracked until saved. If an unused file is deleted while someone is editing, saving an invalid reference is rejected.

Deletion is permanent, with a modal confirmation. Database and media deletions acquire a shared lock row to prevent races between saving content and deleting attachments. No thumbnail derivatives or image transformations are generated; the UI uses browser-sized previews of the original files.

### Deployment

1. Back up the database and uploads together.
2. Build with `npm run build` and deploy the frontend assets to the PHP host's public document root.
3. Deploy `php/` outside the public document root, preserving its relationship to `public/api/index.php`, or adjust that entry point deliberately.
4. Run `php php/migrate.php migrate` for new schema changes.
5. Preserve existing uploads when deploying a new build. Vite copies `public/` into `dist/`; do not blindly overwrite or erase the live uploads folder with a developer's copy. Treat uploads as persistent site data, not disposable build output.
6. Preserve both root and API `.htaccess` files. The root rule serves public pages through the PHP SEO entry point; `/api` is handled by the nested API rewrite rules.
7. Configure HTTPS cookies, database credentials, mail delivery, and directory permissions using the authentication guide.

## Module registry

`php/config/content-modules.json` now drives all content modules. Existing per-module wrapper classes have been removed. Follow [Content modules](CONTENT-MODULES.md) to add a standard module with the scaffold command and migration.

## Modular source layout

```text
php/migrations/007_content_and_media.php
php/models/Content.php              Shared content persistence, visibility, references
php/models/Media.php                Image persistence/files and usage protection
php/validation/ContentValidation.php Shared rules and BlockNote validation
php/validation/MediaValidation.php   Image uploads and attachment details
php/controllers/ContentController.php Shared guarded content actions
php/controllers/MediaController.php Media route target
src/api/content.ts                  CMS types and API functions
src/validation/content.ts           Frontend rules
src/components/admin/content/      Lists, editor, read-only preview, image fields
src/components/admin/media/        Library browser, picker, attachment details
src/pages/admin/AdminContent.tsx     Lazy route composition
src/pages/admin/AdminMedia.tsx       Library page
```

Posts, events, and properties have separate tables with common title/slug/excerpt/BlockNote content/status/publication/author/timestamp columns. Their validated extra fields are serialized into the `details` TEXT column. This keeps the current generic workflow small. Add typed columns and indexes through new migrations when a feature needs database-level filtering (for example property price ranges), rather than building public search around scanning JSON in PHP.

Migrations already applied must not be edited; add new numbered migrations for schema changes. The new migration supports MySQL, PostgreSQL, and SQLite; this does not retroactively make older MySQL-specific migrations portable.

## API reference

All management endpoints require an active Admin or Manager session. All mutations require CSRF.

For each of `posts`, `events`, and `properties`:

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/{kind}?page=1&search=&status=` | Paginated list |
| GET | `/api/{kind}/{id}` | Full saved document |
| POST | `/api/{kind}` | Create |
| PATCH | `/api/{kind}/{id}` | Update |
| DELETE | `/api/{kind}/{id}` | Delete one |
| POST | `/api/{kind}/bulk-delete` | Delete selected JSON `ids` |
| GET | `/api/public/{kind}/{slug}` | Public detail; 404 for draft or future schedule |

Payload: title, slug, excerpt, content (BlockNote JSON array), status, publish_at (UTC ISO date for scheduling), featured_media_id (number or null), details (feature-specific object). Start/end event dates also use UTC ISO dates. Unpublished event dates/location can remain empty; published events require location and a start date with end after start. Properties require valid price/currency/type/availability and integer bedroom/bathroom counts, with location required to publish.

Media:

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/media?page=1&search=` | Attachment list and usage |
| GET | `/api/media/{id}` | Attachment details |
| POST | `/api/media` | Multipart `file` upload |
| PATCH | `/api/media/{id}` | title, alt_text, caption |
| DELETE | `/api/media/{id}` | Delete unused file |
| POST | `/api/media/bulk-delete` | Delete selected unused files |

Rate limits: 120 content saves, 60 content deletions, and 100 media mutations per user per 10 minutes. Limits are shared across the three content types; media has its own action key. Rejection returns 429 with Retry-After. Backend validation rejects unsafe URLs, invalid states, duplicate slugs, malformed selections, and dangling media IDs.

## Verification

Local MySQL/API tests covered Manager access and denied user management, immutable email, upload/type checks, media serving, metadata, drafts, scheduled visibility before/after due time, event/property CRUD, validation, used-image protection, and bulk deletion. Browser checks covered BlockNote draft saving, library image insertion, saved preview, scheduled publication controls, and pinned toolbar layout. Temporary accounts/content/files were removed after testing. PHP syntax, frontend build, and lint were checked.

## Services

Services now use the same content editor, drafts/publication, media, SEO and activity modules. See [Services](SERVICES.md) for routes, permissions, migration 010 and the optional six-service seed command.
