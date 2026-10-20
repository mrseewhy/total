# First deployment: every step, including SEO

Follow this document from top to bottom for the first installation. It uses the existing application and a standard shared-host public_html directory. All account names/domains here are EXAMPLES. Nothing has been deployed automatically.

## 1. Write down your actual hosting details

Before running commands, fill this in:

| Item | Example | Where to obtain it |
|---|---|---|
| Hosting account username | myaccount | cPanel account information |
| Account home directory | /home/myaccount | File Manager or terminal `pwd` after login |
| Domain | example.com | Your hosting account/domain provider |
| Final website URL | https://example.com | Pick HTTPS and either www or non-www consistently |
| Website document root | /home/myaccount/public_html | cPanel Domains → document root |
| Git remote | git@github.com:YOURNAME/total.git | Your own private repository |
| Database hostname | localhost | Hosting database information; do not assume |
| Database name | myaccount_total | After creating it in cPanel |
| Database username | myaccount_totaluser | After creating it in cPanel |
| Database password | Your unique password | You create this on the host |
| Sender address | website@example.com | A real sender accepted by your hosting mail service |

Replace /home/myaccount, example.com, and the Git URL in EVERY command/file below. Do not paste your database password into Git or this document.

The instructions assume SSH/cPanel Terminal, Git, rsync, and PHP CLI are available. If your plan does not offer these, ask the host before starting: Git pull alone cannot run the application's migrations or owner-account creation. cPanel menu names vary between providers.

## 2. Prepare the domain, HTTPS, PHP, and database on the HOST

1. Add the domain in cPanel Domains and confirm its document root. Point its DNS at the host using the records supplied by that host. Wait until the domain resolves correctly.
2. Enable the host's SSL certificate/AutoSSL. Open https://example.com and confirm it has a valid certificate before enabling secure login cookies.
3. Select PHP 8.3 or newer for the domain. In Terminal run `php -v` and ensure the CLI version is also suitable. A host may require a different executable for PHP 8.3; substitute that executable in all PHP commands below.
4. Check `php -m`. The app uses PDO with pdo_mysql, fileinfo, sessions, JSON, and standard image-inspection functions. The owner-account CLI uses readline. Confirm Apache/LiteSpeed permits the supplied .htaccess rewrite rules.
5. In the hosting PHP settings set upload_max_filesize to at least 8M and post_max_size to 16M or larger. Enable log_errors and turn display_errors off. PHP's session directory and temporary upload directory must be writable.
6. In cPanel MySQL Databases (or Database Wizard), create the database and user. Add that user to the database and grant the privileges needed to create/alter tables and read/write rows. Record the full prefixed database/user names.
7. Create/authorize the sender mailbox or sender identity required by the host. The application currently sends password resets through PHP mail(), not an SMTP client. Confirm the host supports it.

Use MySQL for this deployment. Although db.php can connect to other database types, the full migration set is not portable yet.

## 3. Prepare source and Git on your LOCAL COMPUTER

Open Terminal in the existing project folder:

```sh
cd /Users/olusegun/Desktop/my-projects/total
npm ci
npm run lint
npm run build
```

If any command fails, resolve it before proceeding. The build can report a large lazy editor chunk warning; that warning alone is not a failed build.

Create an EMPTY private Git repository with your chosen Git provider. Do not initialize a second README on the remote. Then:

```sh
git init -b main
git add .
git status --short
```

Inspect the filenames BEFORE committing. php/config.php, .env.local, local configuration overrides, uploaded images, node_modules, and dist must not be staged on main. Their exclusions have been added. If any secrets were already committed in a different repository, stop and remove/rotate them; .gitignore does not erase past commits.

```sh
git commit -m "Initial application"
git remote add origin git@github.com:YOURNAME/total.git
git push -u origin main
```

These initialization commands are for a project without Git. If you already initialized it after reading the previous guide, use the existing repository/remote instead of running init/add-origin again. Inspect with `git status` and `git remote -v`.

## 4. Build a DEPLOY branch locally

Your shared host does not need Node. Build React on your computer and include its output on a separate deploy branch:

```sh
cd /Users/olusegun/Desktop/my-projects/total
git worktree add ../total-deploy -b deploy
cd ../total-deploy
npm ci
npm run lint
npm run build
```

The second worktree is a separate directory for release preparation. Keep writing application code in total (main). Use total-deploy only to merge source and prepare releases.

Inspect dist: it must contain index.php, index.html, route-manifest.json, .htaccess, assets/, api/index.php, api/.htaccess, and uploads/.htaccess. route-manifest.json is generated from the public React routes; deploy it with every build. Reveal hidden files in your file viewer or run `ls -la dist` and `ls -la dist/api`. There must be no real development images in dist/uploads; a fresh worktree should contain only its tracked protection files. Never ship sample uploads accidentally.

```sh
git add -f dist
git commit -m "Build first shared-host release"
git push -u origin deploy
```

The -f includes dist on this branch despite its normal ignore rule. Do not merge deploy back into main.

## 5. Clone the DEPLOY branch on the HOST

Open cPanel Terminal or connect over SSH. These are HOST commands, not commands for your Mac:

```sh
mkdir -p /home/myaccount/repositories
cd /home/myaccount/repositories
git clone --branch deploy --single-branch git@github.com:YOURNAME/total.git total
```

For a private repository authorize the host with a read-only deploy key in the Git provider. Use the provider/host's documented SSH setup. Do not insert a token into a repository URL or committed file. If using cPanel Git Version Control, set the clone path to /home/myaccount/repositories/total, select deploy, and use Update from Remote when updating.

The checkout is NOT the public website. Keep it outside public_html.

## 6. Copy the application into its LIVE folders on the HOST

First confirm public_html is the actual domain root and does not contain another website you need to preserve. Back up any existing site before replacing matching files. This installation uses /home/myaccount/php; if another application already owns that folder, choose a separate parent/document-root arrangement before proceeding.

```sh
mkdir -p /home/myaccount/php/storage
mkdir -p /home/myaccount/public_html/uploads
cd /home/myaccount/repositories/total
rsync -a --exclude='config.php' --exclude='*.config.local.php' --exclude='storage/' --exclude='.migrate.lock' php/ /home/myaccount/php/
rsync -a --exclude='uploads/' dist/ /home/myaccount/public_html/
cp dist/uploads/.htaccess /home/myaccount/public_html/uploads/.htaccess
```

Execute commands one at a time and stop on errors. Do not add --delete. The exclusions preserve server configuration, storage, and live uploads during future deployments.

The required live layout is:

```text
/home/myaccount/php/                 PRIVATE backend and configuration
/home/myaccount/public_html/         PUBLIC built website
/home/myaccount/public_html/api/     PUBLIC PHP API entry
/home/myaccount/public_html/uploads/ PUBLIC images
```

The php folder must be immediately beside public_html because the entry files load it through relative paths. Only public_html is the document root. Do not upload the whole repository into it.

## 7. Create the LIVE database configuration on the HOST

```sh
cp /home/myaccount/php/config.example.php /home/myaccount/php/config.php
```

Open /home/myaccount/php/config.php in cPanel File Manager's editor or your terminal editor. Replace its contents with your real values:

```php
<?php
declare(strict_types=1);
return [
    'db_type' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myaccount_total',
    'username' => 'myaccount_totaluser',
    'password' => 'YOUR_REAL_DATABASE_PASSWORD',
];
```

Change host/port if your host specifies different ones. Preserve quotes and commas. If a password contains a single quote or backslash, escape it correctly in the PHP string. Save the file. Never use the local root user/test database credentials on production.

## 8. Create LIVE website/login/email settings on the HOST

```sh
cp /home/myaccount/php/auth.config.local.example.php /home/myaccount/php/auth.config.local.php
```

Edit /home/myaccount/php/auth.config.local.php:

```php
<?php
declare(strict_types=1);
return [
    'app_url' => 'https://example.com',
    'mail_from' => 'website@example.com',
    'secure_cookie' => true,
];
```

Change app_url to the exact final HTTPS origin, without a trailing slash or page path. If the real site uses www, use https://www.example.com here and in SEO. Change mail_from to the accepted sender. Keep secure_cookie true on HTTPS production. Setting true on a plain HTTP local site prevents the browser from sending login cookies, so do not copy this production override into your current localhost environment.

These settings control password-reset links and cookie security. Creating the file on the HOST does not change your local development environment.

## 9. Create the LIVE image-storage setting on the HOST

```sh
cp /home/myaccount/php/paths.config.local.example.php /home/myaccount/php/paths.config.local.php
```

Edit /home/myaccount/php/paths.config.local.php:

```php
<?php
declare(strict_types=1);
return [
    'public_directory' => '/home/myaccount/public_html',
];
```

Use the absolute filesystem document root, not a URL. Do not append /uploads; PHP adds it. This is essential because the development folder is named public while the shared-host folder is commonly public_html. Confirm PHP can write public_html/uploads and php/storage. Use appropriate ownership; do not make them globally writable with chmod 777.

## 10. Create the LIVE SEO settings on the HOST

This is the SEO change mentioned earlier. You do NOT need to edit the versioned seo.config.php directly. Create its ignored local override instead:

```sh
cp /home/myaccount/php/seo.config.local.example.php /home/myaccount/php/seo.config.local.php
```

Edit /home/myaccount/php/seo.config.local.php. A complete example is:

```php
<?php
declare(strict_types=1);
return [
    'site_url' => 'https://example.com',
    'name' => 'Haven Real Estate',
    'language' => 'en',
    'locale' => 'en_NG',
    'description' => 'Find homes for sale and rent in Lagos with Haven Real Estate.',
    'default_image' => 'https://example.com/social-preview.jpg',
    'allow_indexing' => false,
];
```

Change EACH of the following deliberately:

| Setting | What to enter |
|---|---|
| site_url | Exact final HTTPS origin. This controls canonical links, sitemap URLs, and structured-data URLs. Match auth app_url. It can be omitted if you want it to inherit auth app_url, but explicitly setting both makes this walkthrough unambiguous. |
| name | Your real business/site name. Keep Haven only if that is the brand you are deploying. |
| language | HTML language code for your content, for example en. |
| locale | Social metadata locale, for example en_NG for English/Nigeria. |
| description | An accurate site description. Individual static pages and content can have their own descriptions. |
| default_image | An absolute HTTPS URL of an image that actually exists and loads publicly. Do not leave the example filename if you have not uploaded it. |
| allow_indexing | Leave false during setup. Change to true in step 14 when the production site is ready. Keep false on staging. |

For the example default image, create a branded 1200×630 JPEG named social-preview.jpg locally. Add it to the SOURCE public/ folder, commit it to main, and include it in your next deploy build; Vite copies it to the built website. Alternatively upload a real image through Media after login and use its full https://example.com/uploads/... URL. Open the image URL directly to confirm it works. You may retain the existing Unsplash default_image by omitting this override until you have your branded image.

There are two separate concepts: allow_indexing false deliberately produces noindex; a real production site_url gives the correct URLs. A domain must be real even while indexing is disabled. Localhost is automatically noindex regardless of this flag.

Some CONTENT remains hardcoded for the Haven/Lagos demonstration. Changing name above does not replace all website text. If deploying under another brand, edit the following SOURCE files locally, then rebuild/redeploy:

- src/components/site/Navbar.tsx and Footer.tsx: visible logo/name/footer/location.
- src/pages/site/HomePage.tsx, AboutPage.tsx, ContactPage.tsx and site components: business copy, locations, illustrative properties and imagery.
- src/routes/publicRoutes.tsx: static-page SEO defaults and initial fallback headings.
- php/config/content-modules.json: content-module listing descriptions.
- php/seo/PageData.php: structured-data areaServed (currently Lagos).
- php/seo/body.php: inspect the initial HTML fallback copy and keep it consistent with the React page content.

Do not claim a different service area in structured data from the visible site. For posts/events/properties, use their SEO fields in the admin editor when you want a custom search title/description. Leave their noindex option off for content intended to appear in search. Draft/future content is excluded automatically.

## 11. Check configuration and create database tables on the HOST

These commands use LIVE files in /home/myaccount/php, not the repository copy:

```sh
cd /home/myaccount
php -l php/config.php
php -l php/auth.config.local.php
php -l php/paths.config.local.php
php -l php/seo.config.local.php
php php/migrate.php status
php php/migrate.php migrate
php php/migrate.php status
```

All PHP syntax checks must report no syntax errors. After migration, all migrations should report Completed. This creates production tables, not your local data. An empty production install is expected.

If database connection fails, verify database host, full prefixed names, password, user privileges, and PDO MySQL. If a migration fails, stop and inspect the error/log; MySQL table changes can partly commit. Do not publish blindly or run rollback as a repair shortcut.

## 12. Create the owner ADMIN on the HOST

```sh
cd /home/myaccount
php php/commands/create-admin.php
```

Enter your real owner name, email, password, and confirmation when prompted. The password must be 12–72 bytes. Keep this account for the site owner. Open https://example.com/site-admin/login and sign in. Create Managers from Users for people managing content; Managers cannot manage user accounts. There is no public registration page.

If you cannot log in, check HTTPS, secure cookies, PHP session storage, database setup, and /api/auth/me. Do not disable authentication or CSRF as a workaround.

If you want the six initial Services on the new host, run `php php/commands/seed-services.php` from the same live directory after creating the administrator. This is optional content seeding, separate from migrations, and existing matching service slugs are preserved. See [Services](SERVICES.md).

## 13. Test while indexing is still OFF

Complete these checks before announcing the site:

1. Open Home, About, Contact, Blog, Events, Properties. Reload a published detail URL directly. No route should depend on first visiting Home.
2. Submit the newsletter and contact forms with your own test details. Confirm the thank-you state and records in the admin. Confirm contact enquiries start unread and can be marked read.
3. Try invalid input and confirm useful errors. Confirm repeated requests eventually receive rate limiting; do not accidentally lock yourself out while testing login.
4. Upload an image in Media, select it in a draft post, save, preview, publish, and open the public article. Confirm its image loads from your domain. Delete only your disposable tests when finished.
5. Create an event/property and verify its public page. Check scheduling with a short future publication time. Backend dates use UTC.
6. Create a Manager and check that content/media work but user management is denied. Check profile changes and sign-out.
7. Request a password reset for your owner account. Confirm the email actually arrives and the link begins with your production HTTPS domain. Complete the flow. If mail does not arrive, examine host mail configuration/logs rather than assuming the request's success response proves delivery.
8. View PAGE SOURCE (not just the browser Elements panel) on a published page. It should already contain its title, description, canonical URL, structured data and readable content. The PHP index.php must serve the request.
9. Open /robots.txt and /sitemap.xml. Follow the sitemap links. Check no localhost URLs remain. An empty content sitemap is normal until you publish content.
10. Visit a nonexistent URL; it should return a real 404. Confirm private PHP/configuration is outside the document root and unavailable from the web. Review PHP error logs.

Standard .htaccess files must be present in public_html/, public_html/api/, and public_html/uploads/. If deep links or API calls fail, verify them and the host's rewrite support. If another existing index file overrides the supplied PHP DirectoryIndex, resolve that host-specific conflict.

## 14. Enable production indexing and finish launch

On the HOST, edit /home/myaccount/php/seo.config.local.php:

```php
'allow_indexing' => true,
```

Keep the real domain in site_url and auth app_url. Save, then view page source again: public published pages should use index,follow; admin pages must remain noindex. Reopen robots.txt and sitemap.xml to check their production domain URLs. Search indexing is not instant or guaranteed merely because the flags are correct.

Add/verify the domain with your chosen search-engine webmaster service and submit https://example.com/sitemap.xml through its interface. Follow its current verification instructions. Domain verification records/tokens come from that service; none are provided by this project.

Use your host's HTTPS/canonical-host redirect feature so HTTP and any alternate www/non-www host redirect to your chosen final origin. Canonical metadata itself does not perform that redirect. Confirm redirects do not loop.

If you use hosting/CDN page caching, bypass /api/* and authenticated/admin pages; do not cache private session responses. Purge any cached setup/noindex pages when launching.

## 15. Back up separately from Git

Back up the production database, public_html/uploads, php/config.php, all php/*.config.local.php files, and any manually uploaded public assets. Store backups privately outside public_html. Confirm you can restore them. Git contains application code/builds, not live content, users, credentials, or media files.

## 16. Every later release: LOCAL steps

In the main SOURCE worktree:

```sh
cd /Users/olusegun/Desktop/my-projects/total
# Make and verify your changes.
git add .
git commit -m "Describe your change"
git push origin main
```

Then prepare the release worktree:

```sh
cd /Users/olusegun/Desktop/my-projects/total-deploy
git merge main
npm ci
npm run lint
npm run build
git add -f dist
git commit -m "Build shared-host release"
git push origin deploy
```

Stop on conflicts/build errors. Resolve merge conflicts before building. If nothing changed in dist, skip that build commit; push any merge/source commits that exist. Keep configuration changes in the appropriate place: shared behavior in versioned source; host-specific values in live ignored local files.

## 17. Every later release: HOST steps

1. Back up production data/config/uploads and record the current release commit.
2. Use a maintenance window when changes cannot safely run alongside the previous code. This simple deployment copies files; it is not an atomic release switch.
3. Run these commands, stopping on any failure:

```sh
cd /home/myaccount/repositories/total
git rev-parse HEAD
git pull --ff-only origin deploy
rsync -a --exclude='config.php' --exclude='*.config.local.php' --exclude='storage/' --exclude='.migrate.lock' php/ /home/myaccount/php/
cd /home/myaccount
php php/migrate.php migrate
cd /home/myaccount/repositories/total
rsync -a --exclude='uploads/' dist/ /home/myaccount/public_html/
cp dist/uploads/.htaccess /home/myaccount/public_html/uploads/.htaccess
```

4. Retest public pages, direct URLs, login, forms and the changed feature. Check logs and purge relevant host/CDN caches.
5. Do NOT recreate config files, rerun the owner-account command, erase uploads, or replace production with the local test database on normal updates.
6. Retained old assets support already-open browser sessions. Review obsolete PHP files/endpoints explicitly when removing features; these copy commands do not delete files that disappeared from Git.

If a release needs rollback, redeploy a known previous code/build commit only after checking database compatibility. A destructive migrate rollback can delete production data; use the release's planned database restore/repair procedure instead.

## Exactly what must change before launch

| File/location | Required change | Where |
|---|---|---|
| Domain/DNS/SSL | Real domain, correct host, valid HTTPS | Hosting/domain panels |
| php/config.php | Production database credentials | HOST live private folder |
| php/auth.config.local.php | HTTPS app_url, accepted mail_from, secure_cookie true | HOST live private folder |
| php/paths.config.local.php | Absolute public_html/document-root directory | HOST live private folder |
| php/seo.config.local.php | Production site_url, truthful branding/description/image; indexing true only at launch | HOST live private folder |
| Branded social image | Upload/build a real image if replacing default_image | Source public/ + release, or Media |
| Haven/Lagos copy and schema | Change if deploying for a different business | LOCAL source, then rebuild/push/pull |
| Database tables | Run migrations once and on later schema changes | HOST CLI |
| Owner account | Create your own Admin once | HOST CLI |

No new .env is needed. Do not put production values into .env.local expecting PHP to read it. Git pull retrieves the release; copying files, retaining local configuration, and running migrations finish deployment.

For additional layout/rollback notes and official hosting references, see [Deployment reference](DEPLOYMENT.md).

## Installation wizard and health check

For a first installation, `php php/commands/install.php` guides you through private configuration, pending migrations and the first owner account. It preserves existing configuration/accounts. Read [the installation guide](INSTALLATION.md) for prerequisites and exact prompts. After deploying and migrating, run `php php/commands/health-check.php --production`. Use `--json` for structured results. The check is read-only; hosting HTTP/email/backup verification remains separate.

## Maintenance for subsequent releases

Once the updated PHP entry points and maintenance helpers are deployed, follow [the maintenance workflow](MAINTENANCE.md) before future backups, restores and releases. It blocks PHP/API traffic, drains active requests and protects reopening. Retention cleanup is previewed with `php php/commands/cleanup.php`; no automatic deletion or schedule is enabled.

## Configure outgoing email for this website

Use private `php/mail.config.local.php` for sender name/address, optional reply address and the application enable switch. Defaults leave mail disabled. Set the real host-approved sender before setting `enabled => true`; the Haven example sender is a placeholder. Keep the real HTTPS reset URL in `auth.config.local.php`. [PHP mail setup](MAIL.md) gives the full steps, environment options and verification process.
