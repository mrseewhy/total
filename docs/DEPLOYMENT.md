# Deploying Total to shared hosting with Git

**Start with [First deployment: every step](FIRST-DEPLOYMENT.md)** for a full ordered walkthrough, exact configuration file contents, SEO changes, and launch checks. This document is the shorter deployment reference.

This application runs as ordinary PHP plus compiled JavaScript/CSS. Production needs no Node server, Composer install, queue worker, or process manager. Node is needed only on your computer to compile React. PHP handles API requests and initial SEO HTML. MySQL stores content, users, rate limits, and media metadata; image files live in uploads. Scheduled content becomes visible when its publication time arrives, without cron. Password recovery uses PHP mail(), so actual delivery must be tested with your host.

## 1. Hosting requirements

Use Linux Apache or compatible LiteSpeed hosting with .htaccess/rewrite support, HTTPS, MySQL, PHP 8.3+ (the version targeted by this project), PDO MySQL, fileinfo, sessions, JSON, and readline/CLI access for the administrator command. Select the same PHP version for terminal commands and web requests. Confirm `php -v` and `php -m`; some hosts require a version-specific PHP binary.

Git pull requires SSH/terminal access or cPanel Git Version Control. This guide uses terminal commands plus rsync, so confirm both are available. A host without terminal PHP needs a different migration/administrator provisioning procedure; do not expose those CLI scripts through a browser.

In cPanel PHP settings use upload_max_filesize at least 8M and post_max_size larger than that, for example 16M. Enable error logging and disable display_errors in production. Sessions and PHP temporary uploads must be writable. Do not use chmod 777: normally directories are 755 and files 644, with private config 600 when your PHP handler runs as your account.

Current complete migration support is MySQL: the connection supports PostgreSQL and SQLite, but migration 001 uses MySQL AUTO_INCREMENT. Use MySQL for this deployment.

## 2. What is deployed

Recommended standard shared-host layout:

```text
/home/ACCOUNT/
├── repositories/total/       Git checkout, outside the website
│   ├── php/                 Versioned backend source
│   └── dist/                Prebuilt website, committed on deploy branch
├── php/                     Live private backend, outside document root
│   ├── config.php           Production database credentials; never committed
│   ├── auth.config.local.php
│   ├── paths.config.local.php
│   ├── seo.config.local.php  Optional branding overrides
│   └── storage/             Runtime files
└── public_html/             Domain document root
    ├── .htaccess
    ├── index.php            PHP frontend and SEO entry
    ├── index.html           Built HTML template used by index.php
    ├── assets/              Compiled React, CSS, fonts
    ├── api/
    │   ├── .htaccess
    │   └── index.php
    └── uploads/             Persistent uploaded images
```

The live php directory MUST be immediately beside the document root: public/index.php loads ../php and api/index.php loads ../../php. Do not place the whole repository in public_html. Do not point the domain at the repository root or serve index.html directly. Use the supplied DirectoryIndex index.php rule.

If a domain has a different document root, put php beside THAT root and set public_directory to that root. This layout assumes one application owns the sibling php directory; multiple sites need separate parent folders, not a shared backend directory. Deploy at the domain root, not example.com/subfolder: API, uploads, and frontend routes currently use absolute root paths.

## 3. Prepare Git locally once

No Git repository was present during this review. Create a private remote repository first, then replace REMOTE_URL below. No remote has been created or code pushed for you.

```sh
git init -b main
git add .
git status --short
# Inspect staged filenames before committing: private config and uploads must be absent.
git commit -m "Initial application"
git remote add origin REMOTE_URL
git push -u origin main
```

.gitignore now excludes database credentials, local server overrides, .env files, uploads/runtime data, and generated public assets. Examples remain versioned. If credentials were previously committed elsewhere, ignoring them does not remove them from history: remove them from tracking and rotate exposed credentials before deployment.

Keep main as source. Use a separate deploy branch/worktree to include dist without mixing generated assets into normal development:

```sh
git worktree add ../total-deploy -b deploy
cd ../total-deploy
npm ci
npm run lint
npm run build
git add -f dist
git commit -m "Build shared-host release"
git push -u origin deploy
```

Use a supported Node version locally (Node 22.12+ is suitable for the current Vite toolchain). The production host does not run npm. The -f is intentional because dist is normally ignored. Review the build before committing: Vite copies public files, so dist/uploads must contain only .htaccess/.gitignore, never development uploads. Remove any copied development image files from dist/uploads before staging. Never put secrets in VITE_* variables or public/.

## 4. Prepare the hosting account once

In cPanel create a MySQL database and database user, grant that user privileges on that database, and record the full account-prefixed database/user names. Enable HTTPS for your domain.

Connect through SSH or cPanel Terminal. Replace ACCOUNT and REMOTE_URL throughout:

```sh
mkdir -p /home/ACCOUNT/repositories
cd /home/ACCOUNT/repositories
git clone --branch deploy --single-branch REMOTE_URL total
mkdir -p /home/ACCOUNT/php/storage
mkdir -p /home/ACCOUNT/public_html/uploads
```

For private Git repositories configure a read-only deploy key with the Git provider. Never paste a personal access token into a committed file or command URL.

Copy the backend and built website. These commands intentionally do not use --delete, preserve live uploads, and include dotfiles such as .htaccess:

```sh
cd /home/ACCOUNT/repositories/total
rsync -a --exclude='config.php' --exclude='*.config.local.php' --exclude='storage/' --exclude='.migrate.lock' php/ /home/ACCOUNT/php/
rsync -a --exclude='uploads/' dist/ /home/ACCOUNT/public_html/
cp dist/uploads/.htaccess /home/ACCOUNT/public_html/uploads/.htaccess
```

This simple copy is not an atomic release switch. Use a short maintenance window for releases with incompatible database/backend/frontend changes. Old asset files are retained so already-open browser sessions can finish loading their previous bundle. Review removed PHP files and obsolete endpoints deliberately rather than blindly deleting runtime folders.

Create production-only config:

```sh
cp /home/ACCOUNT/php/config.example.php /home/ACCOUNT/php/config.php
cp /home/ACCOUNT/php/auth.config.local.example.php /home/ACCOUNT/php/auth.config.local.php
cp /home/ACCOUNT/php/paths.config.local.example.php /home/ACCOUNT/php/paths.config.local.php
```

Edit those three files in cPanel File Manager or a terminal editor:

- config.php: MySQL host, database, username, password and port from cPanel. Do not use the local test credentials.
- auth.config.local.php: app_url = https://your-domain.com; secure_cookie = true.
- mail.config.local.php: enabled = true after host setup; transport = mail; from_address = the host-approved sender; from_name = your website name; reply_to optional. See [mail setup](MAIL.md).
- paths.config.local.php: public_directory = /home/ACCOUNT/public_html, using the actual full document root. Media uploads/deletions use this path.

Optional: copy seo.config.local.example.php to seo.config.local.php to customize branding/default image or set allow_indexing false on staging. Canonical URLs inherit auth app_url unless site_url is explicitly overridden. Local overrides take precedence over environment/default settings.

PHP does not automatically read .env or Vite .env.local. No new .env is required for this deployment. PHP_API_URL is a development proxy setting only; production uses same-origin /api requests.

Initialize production tables and create YOUR administrator:

```sh
cd /home/ACCOUNT
php php/migrate.php status
php php/migrate.php migrate
php php/commands/create-admin.php
```

Use the live php directory for these commands, not the checkout copy: it contains production config. The administrator command prompts for a password and hashes it. Run it once to provision the owner, then use user management for managers. Production starts with an empty database; git pull does not transfer local content or uploads.

## 5. Every subsequent release

On your development computer, commit and push source from the main worktree:

```sh
git add .
git commit -m "Describe the change"
git push origin main
```

Then in the deploy worktree:

```sh
cd ../total-deploy
git merge main
npm ci
npm run lint
npm run build
git add -f dist
git commit -m "Build shared-host release"
git push origin deploy
```

The deploy branch has its own build commits, so merge main into it rather than expecting a fast-forward. Resolve any conflicts before building. Main does not contain dist, so ordinary source changes generally merge cleanly. If the build output did not change, skip the build commit and push the merge. Do not merge deploy back into main.

On the host, back up the live database, uploads, and private configuration first. Record the current commit (`git rev-parse HEAD`), then:

```sh
cd /home/ACCOUNT/repositories/total
git pull --ff-only origin deploy
rsync -a --exclude='config.php' --exclude='*.config.local.php' --exclude='storage/' --exclude='.migrate.lock' php/ /home/ACCOUNT/php/
cd /home/ACCOUNT
php php/migrate.php migrate
cd /home/ACCOUNT/repositories/total
rsync -a --exclude='uploads/' dist/ /home/ACCOUNT/public_html/
cp dist/uploads/.htaccess /home/ACCOUNT/public_html/uploads/.htaccess
```

Run each command only after the previous one succeeds. Stop if migration fails; do not continue publishing the frontend. MySQL schema changes are not fully transactional: inspect and repair partial migrations rather than repeatedly retrying blindly. Plan backward-compatible migrations or maintenance time. Backups must be restorable, not just created.

Production settings, database records, uploaded images, and runtime storage are intentionally not replaced by these commands. Back them up separately from Git.

For a code rollback, check out a verified previous release commit in the host repository and repeat the copy procedure; ensure its code remains compatible with the current database. Do not run migrate rollback blindly: it can drop tables/data. Restore a database backup only when required by the release plan.

## 6. Verify the deployed application

- Open /, /about, /contact, /blog, /events, /properties and published detail URLs. Reload a detail URL directly.
- View page source: title, description, canonical and readable content must be present before JavaScript. Production canonical URLs must use your HTTPS domain.
- Check /robots.txt and /sitemap.xml. Draft/future content must remain unavailable; admin pages must be noindex.
- Submit newsletter and contact forms; confirm saved rows in the admin, thank-you states, unread status, and validation/rate limiting.
- Sign in at /site-admin/login; check /admin and /dashboard aliases, sign-out, session access, and a Manager's inability to manage users.
- Create a draft post, upload/select an image, publish, verify the public page, and verify media deletion protections. Test scheduled content with a short future publication time; dates are stored UTC.
- Send and complete a password recovery email. PHP mail() accepting a message does not establish inbox delivery. Ask the host about mail configuration/SPF/DKIM if it fails.
- Confirm private configuration and source cannot be fetched through the website; check error logs for unexpected failures.

If home loads but deep links/API fail, inspect .htaccess support and the real document root. If uploads save in the wrong folder, check paths.config.local.php. If canonical/reset links still use localhost, check the LIVE auth.config.local.php. If a built asset is missing, verify that the latest dist assets and index.html were copied together. The existing PHP preview router is for local testing, not a production server.

## cPanel Git interface instead of terminal Git

You may clone outside public_html and use Update from Remote to pull deploy through cPanel Git Version Control. Copying/publishing remains a separate step; pull alone does not publish files to the live folders. cPanel's Deploy HEAD Commit automation requires a .cpanel.yml file with your actual account paths. None is supplied automatically because the account/document-root paths are unknown. Keep the explicit procedure above until those paths are established.

References: [cPanel Git deployment](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/), [cPanel Git Version Control](https://docs.cpanel.net/cpanel/files/git-version-control/), [Vite production builds](https://vite.dev/guide/build).

## Security verification after deployment

Follow [the security review](SECURITY-REVIEW.md) for the tested protections and outstanding host checks. JSON API requests must use `Content-Type: application/json`; the body limit is 1,100,000 bytes. Media uploads use multipart requests and their own file-size limit. Verify `nosniff`, framing/referrer/CSP headers and no-store on authenticated/API responses. Confirm HTTPS enables secure cookies and HSTS, including when the host terminates TLS at a proxy. Never run the local integration security test with production database credentials.

## Installation wizard and health check

For a first installation, `php php/commands/install.php` guides you through private configuration, pending migrations and the first owner account. It preserves existing configuration/accounts. Read [the installation guide](INSTALLATION.md) for prerequisites and exact prompts. After deploying and migrating, run `php php/commands/health-check.php --production`. Use `--json` for structured results. The check is read-only; hosting HTTP/email/backup verification remains separate.

## Backup and recovery commands

First run `php php/commands/maintenance.php down`, then use `php php/commands/backup.php --offline` for a private database/upload/configuration archive after stopping writes. Verify it with `php php/commands/restore.php /private/backup.zip --verify`. Restore requires `--offline --confirm=RESTORE` and makes a recovery backup first. Read [the full backup and restore guide](BACKUP-AND-RESTORE.md) before replacing data.

## Maintenance and cleanup

[Maintenance mode and cleanup](MAINTENANCE.md) documents request draining, safe backup/restore/deployment workflows and configurable retention. Cleanup defaults to dry run: `php php/commands/cleanup.php`. Enable deletion explicitly with `--apply` only after reviewing the retention policy. Preserve all `php/storage` runtime files during deployment and explicitly reopen with `maintenance.php up` after successful checks.

## Optional 2FA release

Migration `011_optional_email_two_factor.php` must be applied with this release before serving the new PHP code. Use maintenance mode, back up, deploy, migrate and health-check, then reopen. All users remain opted out. Verify mail delivery before opting in through Profile settings. See [2FA](TWO-FACTOR.md).
