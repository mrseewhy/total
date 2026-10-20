# SEO setup and maintenance

## Production settings

Edit `php/seo.config.php` before deployment:

- **site_url**: your real HTTPS canonical domain, with no trailing slash. The SITE_URL environment variable takes precedence; otherwise the auth config's app_url is used. Do not leave localhost in production.
- **name, description, language, locale**: the real site identity and language.
- **default_image**: a public, absolute image URL for static pages and content without featured images. Currently this uses the real-estate hero photo already used by the site; replace it with a branded share image when available.
- **allow_indexing**: controlled by SEO_INDEXING. Set SEO_INDEXING=0 on staging. Localhost/127.0.0.1/::1 automatically receive noindex and a disallow-all robots file.

Production configuration and Git deployment are documented in the [deployment guide](DEPLOYMENT.md). Use ignored `auth.config.local.php` and `seo.config.local.php` overrides so pulls preserve server-specific values.

PHP does not automatically load Vite's `.env.local`. Set server environment variables or edit PHP config. Authentication reset links use `php/auth.config.php`; keep that URL consistent with the SEO domain.

## Public routes

- `/`, `/about`, `/contact`
- `/blog` and `/blog/{slug}`
- `/events` and `/events/{slug}`
- `/properties` and `/properties/{slug}`

Listings show only published content and due scheduled posts, with 12 records per page. Pagination uses `?page=2` and self-referencing canonicals; page 1 canonicalizes to the base listing URL. Tracking/query parameters are omitted from canonical URLs. Invalid pages, unpublished content, future scheduled posts, and unknown URLs produce real 404 responses through PHP. The React router shows a corresponding not-found page rather than silently redirecting everything home.

New public listing and detail pages were added because the project previously had only the three static pages. Public articles use a safe PHP renderer for BlockNote JSON; they do not download the editing UI. HTML is built from escaped text, known block types, and safe links, not raw editor HTML.

## Initial HTML and navigation

**Production must serve `public/index.php` (copied into `dist/index.php` by Vite), not just the static index.html.** Root `.htaccess` selects PHP and routes public deep links through it while preserving API routes and static assets.

PHP loads the built `index.html` shell, injects page-specific metadata, JSON-LD, and readable page HTML, then embeds the matching page-data payload for React. Search/social crawlers and visitors without JavaScript receive useful initial content. React enhances the page and updates metadata on client navigation without duplicating canonical or description tags.

The existing Vite dev server still serves the development HTML shell; metadata/content arrives through `/api/seo`. Use the PHP production preview below to test raw initial HTML. This distinction matters for social preview bots that do not run JavaScript.

```sh
npm run build
php -S 127.0.0.1:8001 -t dist php/preview-router.php
```

For local simulation of a real canonical domain, set SITE_URL for that temporary preview process. Do not point public DNS at a development server.

The frontend build includes PHP entry files and rewrite rules from `public/`, but not private application files. On shared hosting, deploy `php/` outside the public document root as described in the content management guide, and deploy the contents of dist into the public document root. Preserve live uploads. Ensure Apache permits the supplied rewrite/DirectoryIndex rules; other servers need equivalent configuration.

## Metadata and structured data

Each public page has:

- Unique title and description, canonical URL, robots directive.
- Open Graph title, description, type, site name, locale, image, and canonical URL.
- Twitter summary-large-image metadata.
- Organization/RealEstateAgent, WebSite, and breadcrumb JSON-LD.
- AboutPage/ContactPage for static pages; CollectionPage/ItemList for listings; Article for blog posts; Event for events; RealEstateListing/Offer/Accommodation for properties.

Markup uses saved data. No reviews, ratings, street addresses, or business contact details are invented. Current event locations are free-text, and detailed business address/contact data is not configured. Add accurate structured address information if needed for richer search features. Valid markup does not guarantee rankings or rich-result eligibility.

For each post/event/property, the editor's **Search & social preview** section supports:

- SEO title (up to 70 bytes); blank uses a generated title with the site name.
- Meta description (up to 170 bytes); blank uses excerpt, then readable content.
- Hide from search results (`noindex`). This is not privacy or draft protection: the published URL still loads and can be linked from listings.

Featured images provide social images and alt text from the media library. Set useful alternative text in attachment details. Publication and modification times use UTC. Scheduled content becomes indexable only when due.

Changing a slug records a permanent redirect. PHP sends 301 from the old path to the latest published URL; React navigation follows the same redirect. Redirect chains are collapsed. If the target is a draft, scheduled in the future, or deleted, the old path returns 404 rather than exposing it. Slug history is not a substitute for manual migration of old URLs from another site.

## Robots and sitemaps

- `/robots.txt`: configured Sitemap URL; disallows API crawling on production. Local/staging blocks all crawling.
- `/sitemap.xml`: index of static/listing and content sitemaps.
- `/sitemap-pages.xml`: public static/listing pages and their valid pagination URLs.
- `/sitemap-posts-1.xml`, `/sitemap-events-1.xml`, `/sitemap-properties-1.xml`: up to 5,000 visible, indexable detail URLs per shard; additional shards are generated as needed.

Sitemaps update directly from the database, including due schedules. They exclude drafts, future posts, custom-noindex detail pages, and admin/auth routes. lastmod reflects the later of the stored update time and publication time. Sitemap responses are not cached across publication transitions.

Admin, login, password-reset, aliases, and preview pages receive noindex/nofollow metadata; PHP also sends X-Robots-Tag. API responses receive noindex/nofollow headers. Admin routes are not blocked in production robots.txt so crawlers can read their noindex directives. Authentication, not robots rules, protects private records.

## Source layout

```text
php/seo.config.php                 Site identity, origin, indexing switch
php/seo/PageData.php                Shared metadata and public page data
php/seo/blocks.php                  Escaped BlockNote-to-HTML renderer
php/seo/head.php                    Initial metadata and JSON-LD
php/seo/body.php                    Initial readable page content
php/seo/sitemap.php                 Publication-aware sitemap generation
php/controllers/SeoController.php  Public React page-data endpoint
php/migrations/008_seo.php          Per-content metadata and slug history
public/index.php                   PHP production frontend entry
public/.htaccess                   Frontend/robots/sitemap rewrites
src/seo/                           Loader and navigation metadata updates
src/pages/site/PublicContentPage.tsx Public listings, details, 404 page
```

## Verification and release checks

Implementation tests covered raw PHP HTML (before JavaScript), metadata uniqueness/escaping, canonical pagination, JSON-LD parsing, draft/future exclusions, noindex flags, real 404s, sitemap XML, robots output, and two-step slug changes resolving to one final 301. Temporary fixtures were removed. Browser checks verified client-navigation metadata cleanup and route titles.

Before going live:

1. Configure the real HTTPS domain and intentional indexing setting.
2. Request the live page source and confirm meaningful HTML, one canonical, and correct social tags.
3. Verify drafts/future posts return 404 without relying on React.
4. Check `/robots.txt` does not disallow the production site, and submit `/sitemap.xml` through your search-console account.
5. Validate representative live pages using Google Rich Results Test and Schema.org validator; no external account submission was performed here.
6. Preserve redirects, metadata, database, and uploads in backups.

References: [Google JavaScript SEO](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics), [noindex behavior](https://developers.google.com/search/docs/crawling-indexing/block-indexing), [sitemap lastmod](https://developers.google.com/search/blog/2023/06/sitemaps-lastmod-ping), [Schema.org RealEstateListing](https://schema.org/RealEstateListing).

## Admin metadata editing

Metadata defaults and registered public-page overrides can now be edited at `/site-admin/seo`. They are stored in the database; production domain and server-level indexing restrictions remain in PHP configuration. Apply migration 009 before deploying this feature. See [Metadata and activity guide](METADATA-AND-ACTIVITY.md) for field precedence, new-page registration, and activity logging.

## Automatic route registration

Public static React routes in `src/routes/publicRoutes.tsx` are now discovered at dev/build time and exported to `route-manifest.json`. PHP uses this for SEO editing, route defaults, and sitemap inclusion. The old manual PHP page registry has been removed. See [Automatic public-page discovery](AUTOMATIC-PAGES.md).
