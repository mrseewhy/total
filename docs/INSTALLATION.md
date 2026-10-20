# Installation and health checks

These commands run with ordinary PHP CLI on your computer or the shared host. There is no web installer, Composer dependency, background worker or production Node process. They can run from any working directory when given the correct script path.

## First installation

1. Create an empty **MySQL database and database user** in your hosting panel. Grant that user access to that database, including the schema permissions required by migrations. The installer connects to a database; it does not create the database or hosting account.
2. Build the frontend locally with `npm ci` and `npm run build`. Follow [first deployment](FIRST-DEPLOYMENT.md) to push/pull the release and deploy `dist` into your actual document root. Keep the private `php` directory outside the document root. Preserve `uploads/.htaccess` when copying the build.
3. Open SSH or the hosting panel's terminal, change to your checkout, and run:

   ```sh
   php php/commands/install.php
   ```

   PHP CLI needs version 8.3+, PDO MySQL, mbstring, fileinfo and sessions. It also needs `passthru` for migrations. Run under the hosting account that owns the deployment, not root.

4. If `php/config.php` does not exist, enter the database host, port (normally 3306), database name, username and password. Password input is hidden. The file is created with owner-only permissions; values are never printed. If PHP runs as a different account, arrange a trusted group/read permission with your host rather than using 777.
5. If `php/auth.config.local.php` is missing, enter the website root URL, such as `https://example.com`. Local development can use `http://localhost:5173`. Hosted URLs require HTTPS. Subdirectory installations are not supported by this command. Enter a valid password-reset sender email, or leave it blank and configure delivery later.
6. If `php/paths.config.local.php` is missing, enter the **absolute document root**, such as `/home/ACCOUNT/public_html`. For local development, use the existing `public` folder. The selected folder must exist and must not contain private PHP. Existing local configuration files are always preserved.
7. The installer prepares missing upload/private-storage directories, checks upload protection is deployed, and asks whether to run migrations. Enter `yes` to continue. **Back up before applying migrations to an existing project.** Entering anything else stops before migrations; configuration already created is retained.
8. When the users table is empty, enter the owner name, email and a password of 12–72 bytes, twice. Password input is hidden. The owner is an Admin. Existing active owners and accounts are preserved. An existing users table with no active Admin requires account recovery; installation does not create a replacement owner.
9. Run the health check below, then sign in at `/site-admin/login`. No sample content is inserted.

The current complete migration set requires MySQL, even though the connection helper supports other PDO drivers. The installer deliberately stops for other database types.

The hidden-input wizard requires a Unix terminal (`stty` and PHP `shell_exec`). If your host disables those functions or has no terminal, use the manual setup in [first deployment](FIRST-DEPLOYMENT.md); never put passwords in command arguments. Existing manually created configuration is supported.

### Rerunning and recovering

Rerunning preserves private configuration, existing users and content. It applies only pending migrations after confirmation, and does not reset passwords. Correct mistakes by editing the relevant private configuration file, then rerun. If a step fails, earlier successful steps remain; the entire installation is not one transaction. MySQL schema changes cannot all be rolled back automatically.

An existing `seo.config.local.php` is also preserved. Its `site_url` can override the authentication URL. Check it when changing domains. Without a separate SEO/environment override, canonical URLs follow the configured authentication URL. See [SEO configuration](SEO.md).

The installer does not alter hosting permissions, send mail, deploy files, enable HTTPS, or create backups.

## Read-only health check

```sh
php php/commands/health-check.php
php php/commands/health-check.php --production
php php/commands/health-check.php --production --json
```

It checks:

- PHP version and required extensions.
- Database connectivity, migration history, required core/module tables, and an active owner.
- Public-directory placement, runtime directory access, upload protection files and required public files.
- Frontend build presence and the generated main-page manifest.
- Reset/canonical URLs, production localhost mistakes, URL consistency, sender configuration and secure-cookie configuration.
- Private-file permissions and CLI session-directory access.

It never runs migrations, writes configuration, creates a database/table, modifies records or sends email. Connection failures return a generic diagnostic without printing database credentials. It checks schema/table availability, not every column, index, row or database privilege.

`PASS` means the local check succeeded. `WARN` needs review and may be intentional in development/staging. `FAIL` needs correction. Exit codes are:

| Exit | Meaning |
| --- | --- |
| 0 | No failed checks; warnings may still exist. |
| 1 | At least one failed check or invalid option. |
| 2 | Warnings only when `--strict` is supplied. |

Example strict deployment check:

```sh
php php/commands/health-check.php --production --strict
```

Production mode always warns about the hosting checks CLI cannot prove, so strict production mode requires reviewing those warnings rather than treating them as application failures. Verify HTTPS, secure cookies over HTTP, the real web document root, Apache execution restrictions, web-user permissions, reset-email delivery and backup restoration separately. CLI and web PHP can use different versions/configuration/users.

For routine updates, back up, deploy the release, run `php php/migrate.php migrate`, then run the production health check. You do not need to rerun the installation wizard for every release.

## Tests

```sh
npm run test:console
```

This covers URL validation, private configuration permissions/overwrite protection, and a read-only JSON health check with missing configuration.

A separate integration test creates and drops a uniquely named MySQL database, runs a fresh installation through terminal prompts, verifies migrations/password hashing, reruns safely, and checks health/pending migration reporting:

```sh
npm run test:installation:e2e
```

**Development only:** it reads your local database credentials, requires CREATE/DROP DATABASE privileges and a Unix terminal API, and must never use production credentials. The existing application's tables/configuration are not changed. Temporary databases/files are removed on normal success or failure; an abruptly killed process may require manual cleanup of its `install_test_...` database.

## Backup and recovery commands

First run `php php/commands/maintenance.php down`, then use `php php/commands/backup.php --offline` for a private database/upload/configuration archive after stopping writes. Verify it with `php php/commands/restore.php /private/backup.zip --verify`. Restore requires `--offline --confirm=RESTORE` and makes a recovery backup first. Read [the full backup and restore guide](BACKUP-AND-RESTORE.md) before replacing data.

## Maintenance and cleanup

[Maintenance mode and cleanup](MAINTENANCE.md) documents request draining, safe backup/restore/deployment workflows and configurable retention. Cleanup defaults to dry run: `php php/commands/cleanup.php`. Enable deletion explicitly with `--apply` only after reviewing the retention policy. Preserve all `php/storage` runtime files during deployment and explicitly reopen with `maintenance.php up` after successful checks.

## Outgoing mail after installation

The installer preserves mail configuration and does not enable outgoing mail automatically. Set the host-approved sender and `enabled => true` in private `php/mail.config.local.php` after enabling PHP mail at the provider. See [mail configuration](MAIL.md); the authentication sender prompt is retained as a legacy fallback.
