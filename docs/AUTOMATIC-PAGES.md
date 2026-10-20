# Automatic public-page discovery

Create your own React pages. Register each public route once, in src/routes/publicRoutes.tsx. The backend's metadata editor and sitemap automatically pick up concrete public URLs from that route list.

## New page example

Create src/pages/site/ServicesPage.tsx:

```tsx
export default function ServicesPage() {
  return <main><h1>Our services</h1><p>Describe your services here.</p></main>;
}
```

Import it in src/routes/publicRoutes.tsx and add an entry inside publicRoutes:

```tsx
import ServicesPage from "../pages/site/ServicesPage";

// Inside the existing publicRoutes array:
{ path: "services", element: <ServicesPage /> },
```

Save while npm run dev is running. Open /services and Admin → SEO metadata; Services (/services) is available automatically. PHP generates a default title such as Services | Haven Real Estate and uses the configured default description/sharing image. Edit those metadata values in the admin as usual. The React page's visible layout/text remain your code.

You do not edit php/seo/PageData.php, a PHP registry, or sitemap.php to register this new static page. You do not run migrations just to add a route. Navigation/footer links remain your design decision and are not inserted automatically.

## Optional defaults

```tsx
{
  path: "services",
  element: <ServicesPage />,
  handle: {
    seo: {
      label: "Our services",
      title: "Our services | {siteName}",
      description: "Explore our property search and viewing services.",
      schema: "WebPage",
    },
  },
},
```

All handle.seo fields are optional. Supported fields are label, title, description, heading (initial PHP fallback heading), schema (WebPage/AboutPage/ContactPage), and noindex. Saved database overrides take priority over title/description defaults. noindex in route code is a restriction and cannot be disabled through the admin. Existing localhost/server restrictions also remain in force.

Nested literal children and index routes work. For example a company layout with an index child and a team child registers /company and /company/team. Pathless layout wrappers do not produce their own page. A layout without an index does not imply a valid base URL. Explicit paths and literal arrays are required so discovery is deterministic; computed factories/spreads produce a clear generation error.

Keep all actual public page declarations in this file. Protected routes stay outside it in src/routes/index.tsx. Admin aliases, /site-admin paths, and /api paths are excluded defensively even if mistakenly added to the public file. These SEO exclusions do not implement authentication: continue using the existing protected route and PHP permission patterns.

## Dynamic pages

A URL pattern such as /blog/:slug cannot be listed as an actual page. Blog/event/property records already have their own backend handlers, publication rules, SEO fields, and sitemap generation. New records in those modules are automatic.

For a standard new content module, use the [shared registry workflow](CONTENT-MODULES.md): the generic backend supplies the model/resolver and sitemap data source. Handcrafted dynamic routes outside that system still require their own resolver. A route manifest cannot infer database records, drafts, permissions, or publication times. The generator excludes parameters, optional paths, and wildcards from static discovery, so it will not create bogus sitemap URLs or turn every unknown URL into 200.

The shared loader is automatically attached to page leaves that do not supply one. If a page needs a custom loader, preserve the shared metadata result in that loader's return value; custom loaders are intentionally not overwritten.

## Development and deployment

1. npm run dev generates public/route-manifest.json at startup and refreshes it when the public route declarations change.
2. npm run build generates the same manifest and Vite copies it into dist/route-manifest.json.
3. Commit the built dist on the deploy branch and push/pull as documented. The usual rsync of dist/ into public_html/ includes the manifest.
4. PHP reads route-manifest.json from the public_directory configured in php/paths.config.local.php. No build service or Node process is required on the shared host.
5. Missing/corrupt manifests fail explicitly instead of accepting arbitrary paths. Rebuild/redeploy the complete release, and check public_directory if PHP cannot locate it.

The generated development manifest is ignored by Git; the built copy is included when you force-add dist on the deploy branch. Never hand-edit it. The manifest contains only public route defaults, never credentials or private admin routes.

Removing a route removes it from the selector/sitemap after regeneration and restores a PHP 404. Existing database overrides are retained for possible reuse, but they do not resurrect a removed route. Unknown paths remain 404.

Automatic metadata discovery is not React server rendering. New static pages receive correct initial PHP metadata and a generic heading/intro; their component body is rendered by React. Existing Home/About/Contact fallback bodies and dynamic article/property/event rendering remain intact. Add additional PHP body rendering only when you need the full new page content available without JavaScript.

## Verification

Run npm run test:routes for index/nested route discovery, private/dynamic exclusions, noindex inheritance, unsupported declarations, removals, and invalid-manifest cleanup. Run npm run build and npm run lint before deploying.
