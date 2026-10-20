# Backup and restore

These are PHP CLI tools for the current MySQL-based CMS. They do not add a web endpoint or background process. They operate on the database configured in `php/config.php` and the uploads folder under the configured public directory.

## What is saved

Each dated ZIP contains:

- `database.sql`: the configured database's tables/data, views, triggers, routines and database events, as exported by the MySQL client.
- `uploads/`: registered and unattached upload files, including upload execution-protection rules.
- `config/`: `config.php` and any existing `auth.config.local.php`, `seo.config.local.php`, `paths.config.local.php`, `maintenance.config.local.php`, `mail.config.local.php`.
- `manifest.json`: UTC creation time, file sizes, SHA-256 checksums, PHP version and Git commit when Git/repository information is available.

The backup does **not** include application source/build files, Git history, `.env` files, server environment variables, PHP sessions, server configuration, email inboxes or arbitrary files outside these locations. Keep code/releases in Git and securely retain any separate host/environment configuration. If Git is unavailable, the recorded revision is null; record the release yourself.

Archives are **not encrypted**. They include personal data, password hashes and database credentials. The default folder is private `php/storage/backups`, outside the document root; archives use owner-only permissions and the default folder is ignored by Git. Restrict access, copy backups off-host through a secure channel, and encrypt off-host copies using your chosen backup storage tool. A backup on the same hosting account alone is insufficient protection against account loss.

## Requirements on shared hosting

- PHP CLI with `ZipArchive` and `proc_open` enabled.
- Compatible `mysqldump` and `mysql` CLI clients in PATH. MariaDB clients can be supplied explicitly as well; test the client/server combination at your host.
- A dedicated database for this application. Restore replaces **all** objects/data in that database, not just CMS tables.
- Database permissions needed to read/export and recreate its objects, including routines/events if present. A restricted account/client may fail; the command will not publish an incomplete archive as successful.
- Space for the archive, temporary SQL dump and expanded restore files. Restore also creates a pre-restore backup and stages a second uploads directory.
- A writable private storage folder and, for restore, a writable public-directory parent of `uploads` for the final directory swap.

If your host disables CLI/process execution or does not provide these clients, use its database-and-file backup facilities instead. There is no PHP SQL-parser fallback. Node and Composer are not required on the host.

## 1. Stop writes

Before backing up or restoring, run:

```sh
php php/commands/maintenance.php down
```

This blocks PHP/API traffic and waits for current PHP requests to finish. Backup/restore now require enabled maintenance and an exclusive traffic lock. Also pause external scripts, cron jobs, direct database/file writers, SQL events and migrations. `--offline` acknowledges these external writers are paused; it does not enable maintenance by itself. After successful backup/restore and checks, reopen with `php php/commands/maintenance.php up`. Failures leave the site offline. See [maintenance mode](MAINTENANCE.md) for locking and host requirements.

## 2. Create a backup

From the checkout on the shared host:

```sh
php php/commands/backup.php --offline
```

It prints the completed private ZIP path. No database password is accepted through command arguments or printed. Client credentials are passed through a temporary owner-only MySQL option file in a private work directory and removed afterward.

Choose an existing private parent for another backup folder:

```sh
php php/commands/backup.php --offline --directory=/home/ACCOUNT/backups
```

The backup directory may be created, but its parent must already exist. Paths inside the configured public directory, source `public`, or `dist` are rejected. Custom destinations outside the repository are recommended; do not commit archives from a custom folder.

If clients are not in PATH:

```sh
php php/commands/backup.php --offline --mysqldump=/usr/bin/mysqldump
```

A successful dump is packaged into a temporary `.partial` file, then renamed to the final ZIP. Files and directories that are symlinks are not followed for uploads/configuration. No automatic retention/deletion is applied.

## 3. Verify and store it

```sh
php php/commands/restore.php /home/ACCOUNT/backups/backup-FILENAME.zip --verify
```

This checks format, allowed paths, entry counts, sizes and every file checksum in private temporary staging. It does not import SQL or replace uploads/configuration. It does create/remove staging files and uses the command lock. The expanded-size safety limit is 20 GiB; the manifest is limited to 5 MB and the archive to 100,000 entries.

Checksums detect corruption; they do **not** establish who produced an archive. Only restore backups you trust. The SQL contains executable database statements, and optional configuration restoration writes PHP files. Verification does not inspect SQL semantics or prove a backup is malware-free.

Copy the verified archive off the hosting account and test restoring it into an isolated environment. Reopen with `php php/commands/maintenance.php up` when backup is finished.

## 4. Restore data

First recover/deploy the matching code release from Git and configure the intended target database/public path. Use a separate database and uploads directory for a rehearsal. On a replacement host, create the empty database/user and configure private files first; the dump itself contains the schema, so migrations do not need to run before restore.

Take the target website offline as above, then:

```sh
php php/commands/restore.php /home/ACCOUNT/backups/backup-FILENAME.zip --offline --confirm=RESTORE
```

The command:

1. Validates and stages the archive before changing data.
2. Checks the MySQL client can connect to the **currently configured database**.
3. Creates and prints a fresh pre-restore recovery backup of the target database/uploads/configuration. If this fails, import does not start.
4. Stages the incoming uploads on the destination filesystem.
5. Removes the target database's existing views, routines, events and tables, then imports the archived SQL.
6. Invalidates existing browser sessions through session versions and discards restored password-reset tokens and pending two-factor codes. Each user's optional two-factor setting is preserved. Those authentication details deliberately differ from the saved snapshot.
7. Replaces uploads, removing files created after the snapshot. Previous uploads are retained if an intermediate failure requires manual recovery.
8. Preserves current server configuration by default.

Custom options include `--mysql=/usr/bin/mysql`, `--mysqldump=/usr/bin/mysqldump` and `--directory=/home/ACCOUNT/backups` for recovery backups/staging.

**Restore is not a transaction across MySQL and the filesystem.** MySQL schema imports can fail after changes have started. If it fails, keep the site offline, retain the printed recovery ZIP/previous-upload path, correct the problem and recover from the pre-restore backup. Do not delete recovery files until you have verified the result. A killed process may leave private `.work-*`, `.partial` or public dot-prefixed staging/previous-upload directories; inspect and clean them only after recovery.

## 5. Optional configuration recovery

For recovery onto the **same deployment**, explicitly request replacement of the archived private configuration files:

```sh
php php/commands/restore.php /home/ACCOUNT/backups/backup-FILENAME.zip --offline --confirm=RESTORE --with-config
```

Import still targets the database configured **before** this command. Archived configuration is copied only after the data import, and can change subsequent database credentials, URLs and filesystem paths. Do not use `--with-config` when moving to a different host/database/path. Files absent from the archive are left unchanged. On a new host, inspect archived configuration privately and transfer only values appropriate for that host.

After restoring:

```sh
php php/commands/health-check.php --production
```

Sign in again, check public pages, media, content totals, inquiries, permissions and a password-reset flow. Verify canonical/reset URLs and host email settings. Run `php php/commands/maintenance.php up` when satisfied, then verify public/Admin pages. Health can check the database while maintenance remains enabled. If you restored older code/data and want to upgrade, follow the normal release process and migrations after verifying the restore first.

## Automated regression test

```sh
npm run test:backup:e2e
```

Development only: this reads local MySQL configuration, requires CREATE/DROP DATABASE privileges and the MySQL CLI clients, creates a uniquely named database and copied files, and removes them afterward. It tests backup checksums/permissions, private destinations, verification/confirmation, corrupt/unsafe archive rejection, database and upload replacement, recovery backups, session revocation, explicit configuration restoration, empty-database recovery and recovery after a simulated failed import. Never point it at production credentials. It does not restore or change the application's existing database.

See the official [MySQL dump documentation](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html) for snapshot/object permissions and [MySQL client binary mode](https://dev.mysql.com/doc/refman/8.0/en/mysql-command-options.html) for import behavior.
