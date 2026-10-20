# Maintenance mode and cleanup

These PHP CLI commands need no queue, Composer dependency, web installer or production Node process. Maintenance mode works without a database connection. Cleanup uses the configured database and defaults to a dry run.

## Maintenance mode

```sh
php php/commands/maintenance.php status
php php/commands/maintenance.php down
php php/commands/maintenance.php up
```

`down` publishes a private marker and waits up to 30 seconds for existing PHP page/API requests to finish. New PHP requests receive **HTTP 503**, `Retry-After` and `Cache-Control: no-store`. Public pages show a simple maintenance page; API calls return JSON. All API access is blocked, including Admins, login, subscriptions, inquiries and uploads. There is no public bypass URL, cookie or secret token.

Options:

```sh
php php/commands/maintenance.php down --message="We are updating the website. Please try again shortly." --retry-after=300 --wait=30
php php/commands/maintenance.php status --json
```

The retry value is advice to clients, not an automatic reopening time. Run `up` explicitly when ready. Allowed retry values are 60–86400 seconds, wait values 0–60 seconds, and messages 1–300 bytes without control characters. Messages are escaped for HTML.

If draining times out, the command fails **and leaves maintenance enabled**. Wait for slow requests to finish, investigate them if necessary, then rerun `down`. Do not start a backup/restore merely because a failed command left the marker enabled: those commands also check that they can obtain an exclusive traffic lock.

### What the lock protects

PHP requests hold a shared file lock through request shutdown. Backup, restore and migration commands obtain exclusive locks. This closes the race between a request starting and maintenance being enabled. `up` refuses while a protected backup/restore/migration/cleanup operation is running. Cleanup uses a shared work lock, so ordinary traffic can continue during cleanup; backup/restore/migration cannot run concurrently with it.

Backups/restores now **require maintenance to be enabled**, in addition to their existing explicit `--offline` acknowledgement. They preserve maintenance mode even after a failure; they never reopen the site automatically. Verification-only restores do not require maintenance.

Migrations acquire the same exclusive work lock even during initial installation, when a maintenance marker may not exist. On an existing site, use `down` first so the whole deployment remains offline across all commands.

### Limits and hosting requirements

- This gate covers the PHP entry points. Static images/assets can still be served, and already cached HTML may remain visible. API writes are blocked. The Vite development server still serves React HTML locally; its PHP API is gated. Production PHP serves the full maintenance page.
- Pause external cron jobs, imports, direct database/file writers and SQL events yourself. A hosting panel or another checkout/process that does not use these helpers can bypass the cooperative lock. There is no host-wide or distributed lock.
- Keep `php/storage` and its runtime files across deployments. Do not delete/replace live lock files or the storage directory during a release: doing so creates separate lock inodes and defeats coordination. Normal Git pulls preserve these ignored runtime files; avoid destructive rsync/delete rules for runtime folders.
- PHP CLI and web PHP need compatible access to the same private storage directory/files. Shared hosting normally runs both as your hosting account; otherwise arrange a trusted group with the host. Do not use 777. Filesystem locking must work reliably on that storage.
- If storage/locking fails, PHP requests fail closed with 503. Correct permissions and inspect the marker with CLI. `status`/health do not modify mode or require a database to read its state.
- Deploy the new private helpers and updated public entry points before relying on this mode for the first time. Old releases without the gate will not honor the marker.

## Backup workflow

Run each command only after the preceding one succeeds:

```sh
php php/commands/maintenance.php down
php php/commands/backup.php --offline
php php/commands/restore.php /private/backups/backup-FILENAME.zip --verify
php php/commands/maintenance.php up
```

Replace the example path with the path printed by backup. Copy the archive off-host securely. See [backup and restore](BACKUP-AND-RESTORE.md) for requirements and unencrypted archive handling.

## Restore workflow

```sh
php php/commands/maintenance.php down
php php/commands/restore.php /private/backups/backup-FILENAME.zip --offline --confirm=RESTORE
php php/commands/health-check.php --production
php php/commands/maintenance.php up
```

Health will warn that maintenance is enabled; that is expected here. It can still check the database/schema. If import or checks fail, stop and keep maintenance enabled. After reopening, verify the website and sign in again. There is no Admin bypass for browser checks while it is down; rehearse full browser recovery in an isolated environment.

## Release workflow

For subsequent deployments once the gate is installed:

1. Pause external writers; run `maintenance.php down`.
2. Create and retain a backup.
3. Pull/deploy the release using the existing shared-host guide, preserving uploads, private configuration and all `php/storage` runtime files.
4. Run `php php/migrate.php migrate`.
5. Run the production health check. Correct failures before proceeding; maintenance warnings are expected while down.
6. Run `maintenance.php up`, then verify public pages, Admin sign-in and key features.

Do not automatically call `up` in a shell trap after a failed migration/restore. That could expose a partially updated database.

## Cleanup commands

Preview eligible records without deleting anything:

```sh
php php/commands/cleanup.php
php php/commands/cleanup.php --dry-run --json
```

Apply the configured policy explicitly:

```sh
php php/commands/cleanup.php --apply
php php/commands/cleanup.php --apply --only=resets,rate-limits
php php/commands/cleanup.php --dry-run --only=activity
```

Supported targets are `resets`, `two-factor`, `rate-limits` and `activity`. Output reports eligible, deleted and remaining counts. Invalid/duplicate options and conflicting `--apply --dry-run` are rejected. Success exits 0; failures exit 1. Records successfully deleted by earlier batches remain deleted if a later batch fails.

Cleanup never deletes users, content, subscribers, inquiries, media, uploads, backups, private configuration or host log files. It does not purge PHP session files. Cleanup is a command you run manually or explicitly configure in the hosting panel; no scheduled job has been installed.

### Retention configuration

Defaults live in `php/maintenance.config.php`. Override them in ignored private `php/maintenance.config.local.php`:

```php
<?php
return [
    'activity_days' => 90,
    'rate_limit_days' => 2,
    'batch_size' => 500,
    'max_rows_per_table' => 10000,
];
```

- **Two-factor codes:** expired verification challenges are eligible; active challenges remain. Use `--only=two-factor` to select them.
- **Password resets:** only tokens with `expires_at <= now` are eligible. Active tokens stay intact.
- **Rate limits:** only rows whose window started at least two days ago are eligible by default. The limiter explicitly supports windows up to one day (`RATE_LIMIT_MAX_WINDOW_SECONDS`); this bound makes cleanup safe for active limits. Retention must be at least two days, and longer than that maximum. All current application limits are shorter. If you extend that bound later, also adjust retention validation.
- **Activity:** records older than 90 days are eligible by default. Set `activity_days => 0` to retain indefinitely. Supported nonzero retention is 1–3650 days. Review your project's retention needs before using `--apply`.
- **Batching:** 1–1000 records per transaction; 1–100000 deletions per table per run. Repeat if eligible records remain. Counts can change with concurrent normal traffic.

Times use UTC. Timestamp predicates are rechecked at deletion so a token or limiter renewed after selection is preserved. Batching limits write transactions, although counting/filtering large tables still takes database work. The command can run online, but refuses to overlap protected exclusive operations or another cleanup.

Local maintenance retention overrides are included in new backup archives. The maintenance marker and lock files are runtime state and are never included in backups or restored over a running lock.

## Verification

```sh
npm run test:maintenance:e2e
npm run test:backup:e2e
npm run test:installation:e2e
```

Development only: these require local MySQL with disposable database creation privileges. Never run with production credentials. The maintenance test uses an isolated copied application/server and verifies 503 responses, safe messages, draining, reopening protection, resumption, cleanup batching and preservation of active tokens/limits. It does not toggle maintenance or apply cleanup on your real project.

No schema migration is required for these changes.
