# Dashboard and branding

The sidebar order is defined in `src/components/admin/navigation.ts`. Content links come from the shared module registry, so removing a content module removes its navigation item and overview card. Keep registry entries in your preferred order.

The protected `GET /api/dashboard` endpoint returns counts and registered main pages. Individual content detail routes are excluded. The Pages screen is for viewing these routes; it does not create or edit page content. Newly registered public pages appear after the route manifest regenerates (automatically in development, during the build for deployment).

User navigation and user counts are visible only to Admins. Managers retain access to other modules. Contact unread counts refresh on navigation, every 30 seconds, and immediately after a read-status change.

Media Library shows all registered uploads by default, including unattached images. Its usage filter finds images without content-media references across the entire paginated library. “Unattached” does not prove an image is safe to delete if you manually hardcode its URL outside managed content.

Use the shared logo wherever branding is needed:

```tsx
<Logo />
<Logo variant="light" />
```

`dark` uses dark text for light backgrounds; `light` uses white text for dark backgrounds. Edit `src/components/Logo.tsx` to change the logo throughout the React website, authentication screens and admin layout. SEO site-name settings remain separate from the visible logo.
