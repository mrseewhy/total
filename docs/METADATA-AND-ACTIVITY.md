# Reusable metadata and activity modules

## Scope and access

SEO metadata is at /site-admin/seo. Both Admins and Managers can edit it, matching content permissions. The form controls metadata only: search/browser title, descriptions, sharing images, language/locale, and noindex. Visible React copy, page layouts, credentials, production domain, and filesystem paths are unchanged.

Activity is at /site-admin/activity; the dashboard also shows the five newest entries. The full feed shows 20 per page with module filtering and refresh. Managers can see staff activity except user-management entries, which are filtered on the backend. Neither role has an API to change or delete the feed.

Run `php php/migrate.php migrate` on every installation to apply migration 009 before deploying code that reads these tables. The local database has already been migrated. Deploy backend and frontend together; on production use a maintenance window or a compatible staged release when applying this schema change.

## Editing metadata

1. Open SEO metadata and set SEO defaults: site name, fallback description/image, language, locale, and optional site-wide noindex.
2. Choose Home, About, Contact, Blog listing, Events listing, or Properties listing.
3. Set a search title, meta description, and optional absolute HTTP(S) sharing-image URL. An uploaded media image can be used by combining your configured domain with its /uploads/... URL.
4. Leave a page text field blank to use its existing fallback; clear an override and save to restore that fallback.
5. Enable page noindex when the page should be omitted from search and the sitemap. A listing's noindex applies to that listing and its pagination, not every published detail record; use each record's SEO option for that.
6. Save, then reload the public page. For initial-source verification use the PHP production entry rather than the Vite development shell.

Metadata precedence:

- Database SEO defaults override the corresponding PHP branding/default values when nonempty.
- The production domain always comes from private PHP configuration, not the admin form.
- Static-page/listing metadata overrides its route defaults. Existing explicit route descriptions still take precedence over the global fallback description.
- Blog/event/property detail metadata stays in the existing content editor. These detail routes are not arbitrary entries in the page-metadata table.
- Localhost is always noindex. Server configuration can disable indexing regardless of database flags. The global database noindex flag can disable indexing but cannot override a server restriction.
- Individual noindex pages are removed from the pages sitemap; global noindex produces an empty sitemap. Canonicals remain generated from the configured domain and route, with pagination preserved.

Settings change SEO output and organization/schema metadata; they do not rename the React logo or change visible page introductions. Existing route defaults are preserved when there are no saved overrides. Errors, private routes, and unavailable records retain their own status and indexing protections.

## Add a new React page automatically

1. Build the React component yourself.
2. Import it and add its route in `src/routes/publicRoutes.tsx`, for example `{ path: "services", element: <ServicesPage /> }`.
3. During `npm run dev`, Vite regenerates the route manifest when you save this file. `/services` now appears in the SEO selector, resolves through PHP with generated defaults, and is included in the sitemap unless noindex applies.
4. Edit its metadata in the admin when you want to override the generated defaults. No PHP registration, database migration, or manual sitemap entry is required for a new static page.
5. For production run the normal build/push/pull deployment. `dist/route-manifest.json` must be copied with the built website. The shared host only reads JSON; it does not run Node or scan TypeScript.

The shared metadata loader is attached automatically to public route leaves. An explicit custom loader is preserved; if you add one, compose the shared metadata payload into its result so SeoBoundary still receives `seo`. Protected/admin routes stay in `src/routes/index.tsx`, outside the public route list.

Optional `handle.seo` defaults live next to the route, with literal fields `label`, `title`, `description`, `heading`, `schema`, and `noindex`. `{siteName}` in a default title is replaced with the configured SEO site name. A parent layout's noindex restriction applies to its children and cannot be cleared by an admin override. These defaults do not edit visible React content. Existing Home/About/Contact defaults were moved here without changing their content.

The generator understands literal route arrays, nested/pathless layouts, index routes, and absolute children beneath their parent. Keep path/children/SEO values explicit literals. Computed paths, spreads, and factory-generated arrays fail generation rather than silently publishing incomplete metadata. It parses the TypeScript syntax without executing page components.

Parameters such as `:slug`, optional paths, and wildcards are not standalone pages in the admin/sitemap. Existing blog/event/property detail records continue through their publication-aware PHP handlers. Standard data-driven content modules now use the [module registry](CONTENT-MODULES.md), which supplies their backend resolver and public routes. Unrelated custom workflows still need explicit business logic. Arbitrary unknown URLs still return 404.

Removing a static route removes it from the generated registry and sitemap and makes PHP return 404. Its saved metadata is retained in the database so reintroducing the same path restores overrides. Adding a component file alone does not create a route, a menu link, or full server rendering of its React body. For a new static page PHP emits its metadata plus a generic heading/intro; implement extra initial body content in seo/body.php only if needed. Existing rich dynamic content rendering is unchanged.

See [Automatic public-page discovery](AUTOMATIC-PAGES.md) for examples and deployment troubleshooting.

## Record activity in another module

After permission checks, validation, and a successful operation, call the shared helper:

```php
require_once __DIR__ . '/../functions/activity.php';
recordActivity(
    $db,
    $actor,              // Authenticated server-side user, never client-supplied identity
    'posts',            // Module
    'updated',          // Action
    $post['id'],        // Target identifier
    'Updated ' . $post['title'] . '.'
);
```

Never pass the request body, passwords/hashes, reset tokens, session IDs, email contents, contact messages, or credentials to the helper. Use a short generated summary. The database preserves actor name/id snapshots so historical entries remain understandable after an account is deleted. It does not store IP addresses or email addresses as dedicated feed fields.

The feed records successful content create/update/delete (including publication status in save summaries), media upload/details/delete, user create/edit/ban/unban/delete, profile/password changes, successful login/logout/password reset, SEO changes, and contact read/unread actions. Bulk deletions create one count summary. Scheduled visibility changes are computed at read time and do not generate a background publication event. Existing records and actions before this feature are not reconstructed. Public contact/newsletter submissions are not copied into the staff feed.

The feed is operational history, not a tamper-proof compliance audit. Logging happens after successful mutations and is best-effort: if logging fails, the saved operation still succeeds and PHP logs the recording error. Feed reads are authenticated and cannot accept caller-supplied actors or entries. Database/server administrators retain access to the underlying table. No automatic retention/deletion policy is enabled yet; include activity_log in database backups and plan retention according to the site's needs.

Content modules registered in php/config/content-modules.json automatically receive activity filtering and route support; no per-module ActivityController/ActivityFeed edits are needed. For custom non-content modules, register their filter explicitly. Add a test that a successful mutation appears, a failed/unauthorized one does not, and no secret values are included.

## Files

- src/routes/publicRoutes.tsx — public routes and optional metadata defaults.
- scripts/public-pages.ts — build/dev manifest generator.
- public/route-manifest.json / dist/route-manifest.json — generated public-page registry (do not edit manually).
- php/models/SeoMetadata.php / validation/SeoMetadataValidation.php / controllers/MetadataController.php — metadata storage and API.
- src/components/admin/MetadataForm.tsx — shared defaults/page form.
- php/functions/activity.php — reusable recordActivity helper.
- php/models/Activity.php / controllers/ActivityController.php — protected pagination/filtering.
- src/components/admin/ActivityFeed.tsx — dashboard and full-feed views.
- php/migrations/009_metadata_and_activity.php — schema.
