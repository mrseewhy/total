# Add and manage content modules

The single module registry is `php/config/content-modules.json`. Both PHP and React read it. Existing modules are Blog, Services, Events, and Properties. The generic controller, model, validation, editor, and public renderer serve all of them. The old per-module wrapper classes have been removed.

## The simplest way to add a standard module

On your LOCAL computer, from the project root:

```sh
php php/commands/make-module.php testimonials "Testimonials" testimonial
php php/migrate.php migrate
```

That is the registration process. The first command creates one JSON registry entry and one numbered migration. The second creates the table using the shared content schema. If the development server is running, it refreshes the generated public-page manifest when the registry changes.

You now have:

- Admin CRUD: `/site-admin/testimonials`, including create/edit/preview routes.
- An automatic sidebar entry and activity filter.
- Guarded API routes: `/api/testimonials` and `/api/testimonials/{id}`, plus bulk deletion.
- Public listing: `/testimonials` and published details at `/testimonials/{slug}`.
- Media selection/use protection, rich content, drafts/publication, SEO, and activity recording.
- Automatic sitemap registration and an editable listing entry in SEO metadata.
- Public navbar/footer links by default.

No new controller, model, validator wrapper, route mapping, TypeScript union, sidebar entry, or sitemap mapping is needed. Create actual records from the admin after migrating; the scaffold command does not invent content.

The example above has NOT been permanently added to this project. A disposable module was used during verification and removed.

## Customize an entry

The generated entry is easy to edit:

```json
"testimonials": {
  "label": "Testimonials",
  "singular": "testimonial",
  "path": "testimonials",
  "profile": "content",
  "scheduling": false
}
```

The JSON key is the table/API/admin identifier. It uses lowercase letters, numbers, and underscores (up to 20 characters), starting with a letter. The public `path` is one lowercase URL segment and may contain hyphens. It must be unique and cannot shadow private/system routes.

| Field | Meaning | Default |
|---|---|---|
| label | Plural name in the admin/public listing | Required |
| singular | Name in create/edit controls | Required |
| path | Public URL segment | Required |
| profile | Existing field/rendering behavior | content |
| description | Public listing introduction/default description | Global SEO description |
| icon | Sidebar Lucide icon | FileText |
| scheduling | Allow future scheduled publication | false |
| navigation | Include in public navbar/footer | true |

Available icons: FileText, BriefcaseBusiness, CalendarDays, House, MessageSquare, Folder. Sidebar item order follows registry order. Setting navigation false only hides public navigation links; it does not protect or disable the module. Every registered module remains available to authenticated Admins and Managers in the admin. User management retains its separate Admin-only authorization.

Profiles are existing behaviors, not module IDs:

- `content`: title, slug, excerpt, rich content, featured image, publication, SEO.
- `article`: the same fields, with article presentation/structured data.
- `event`: adds location and start/end dates, with event presentation/validation/schema.
- `property`: adds price/currency, location, bedrooms/bathrooms, listing type, availability and gallery.
- `service`: standard fields with service cards, contact CTA and Service structured data.

To scaffold an article module with a different public path:

```sh
php php/commands/make-module.php guides "Guides" guide advice article
php php/migrate.php migrate
```

This creates admin/API/table `guides`, public `/advice`, and enables scheduling. The optional arguments are `[public-path] [profile]`; the path defaults to the ID with underscores replaced by hyphens, and profile defaults to content. You can change description, icon, navigation, or scheduling in the registry afterward.

Do not rename an existing ID/table or public path casually after content is live. An ID rename is a schema/data migration, not just a label change. Public path changes require a redirect plan; existing slug redirects operate within a module's current public path. Change label/singular freely when only renaming the interface.

## Build and deploy

1. Scaffold locally and run migrations locally.
2. Create a draft, publish it, verify its public page and permissions.
3. Commit the changed JSON and new migration to main.
4. Merge main into the deploy worktree, build, and commit/push the complete built dist.
5. Pull on the host, copy private PHP/config files, run migrations against the live production config, then publish the built files as described in the deployment guide.

The registry is versioned application configuration, not a server-local credential file. Never edit it only on the host: PHP and the compiled React routes must deploy from the same version. No Node process or runtime table creation is added to shared hosting. Use a maintenance window if publishing a registry change before its migration would expose missing tables.

The scaffold command creates files only; it does not connect to the database, run migrations, push Git, or deploy. Duplicate/invalid modules fail before writing a new entry. Use the existing migration runner rather than exposing module/table creation through the web.

## Removing a module

Remove its JSON entry locally, rebuild, and deploy to hide its API/admin/public routes, sidebar, and sitemap entries. Existing database content, media associations, metadata overrides, and historical activity are retained. Removing configuration does not delete data. Restore the entry to manage retained content; plan explicit data cleanup/migrations if permanently retiring a module. Do not run destructive migration rollbacks merely to hide a feature.

## When a custom feature needs more code

This workflow handles modules that fit the shared content fields and existing profiles. A booking system, commerce workflow, or feature needing new relationships/permissions still needs explicit schema, business logic, validation, and UI. Add custom typed columns/indexes through new migrations when needed. Do not force unrelated data into arbitrary details JSON simply to avoid modeling it.

The public-page route discovery remains available for handcrafted static React pages in src/routes/publicRoutes.tsx. Content-module routes are generated from the JSON registry instead; do not declare those same paths a second time in the static route list.

## Shared implementation

- php/config/content-modules.json: the only per-module registry.
- php/config/ContentModules.php and src/content/moduleRegistry.ts: registry validation/defaults.
- php/commands/make-module.php: optional local scaffold command.
- php/database/content-schema.php: shared migration table creation/removal helpers.
- php/controllers/ContentController.php, models/Content.php, validation/ContentValidation.php: shared guarded CRUD.
- src/content/modules.ts: inferred module IDs and frontend registry access.
- scripts/public-pages.ts: merges module listings into the generated route manifest.

Run npm run test:modules, npm run test:routes, npm run build, and npm run lint before deployment. Existing database rows and module URLs are preserved by this refactor; it does not require another migration for the four existing modules.
