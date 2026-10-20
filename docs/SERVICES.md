# Services

Services reuse the existing content CRUD model, validation, BlockNote editor, media selection, previews, SEO fields, deletion confirmation, activity logger, and publication-aware public renderer.

## Admin

Open /site-admin/services. Admins and Managers can create, read, edit, preview, publish/unpublish, and delete services. User-management permissions are unchanged.

- Draft and Published statuses are supported. Scheduling remains specific to blog posts.
- Fields: title, unique slug, excerpt, rich content, optional featured image, SEO title/description, and noindex.
- Lists support title search, status filtering, 20-item pagination, row selection, and bulk deletion.
- Deletion opens the existing confirmation modal. Attached images remain in Media; images in use by saved services cannot be deleted.
- All private API writes require authentication and CSRF and share the content-write/delete rate limits. Validation runs in React and PHP.
- Successful create/update/delete actions appear under the Services activity filter.

## Public

/services lists published services with links to /services/{slug}. Drafts and deleted services return 404. Changes to a published slug create the existing permanent redirect. Services are linked from the public navbar and footer.

The listing is registered in the public React route manifest, so its metadata is editable under SEO metadata. Individual service SEO is edited in the service editor. Detail pages include Service structured data and are included in /sitemap-services-1.xml when published and indexable. The normal global/local indexing restrictions remain in force.

## Installation and initial content

Apply migration 010 on a new deployment or existing installation:

```sh
php php/migrate.php migrate
```

After creating the owner administrator, optionally install the same six initial services:

```sh
php php/commands/seed-services.php
```

This CLI-only command validates content through ContentValidation and Content, attributes it to the first active Admin, and records creation activity. Existing matching slugs are skipped; it does not overwrite edited services. It creates:

1. Property Sales
2. Residential Rentals
3. Property Management
4. Property Marketing
5. Relocation Support
6. Property Search and Viewings

The migration and initial services have already been applied locally. On the host, run commands from the live private application's parent directory, as explained in the deployment guide. Production content does not transfer through Git automatically; the optional seed command creates these records separately.

## Module files and API

- php/config/content-modules.json — Services registration
- php/models/Content.php, validation/ContentValidation.php, controllers/ContentController.php — shared implementation
- php/migrations/010_services.php
- php/commands/seed-services.php

GET/POST /api/services; GET/PATCH/DELETE /api/services/{id}; POST /api/services/bulk-delete. Public published-record JSON is available at GET /api/public/services/{slug}. Existing shared React content components manage Services under /site-admin/services, /new, /{id}/edit, and /{id}/preview.

Verification covered Manager access, rejected unauthorized/CSRF writes, validation, duplicate slugs, drafts, edits/publication, media usage protection, search/pagination, redirects, noindex/sitemaps, bulk deletion and activity recording. Browser checks covered service creation, the delete-confirmation modal, all six public cards, service details, and compact sidebar spacing.

New modules use the [shared registry workflow](CONTENT-MODULES.md); Services no longer needs its own wrapper classes.
