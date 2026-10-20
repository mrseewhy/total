# Total: PHP + React

A modular PHP API and React website designed for shared hosting. React is compiled locally; production runs standard PHP and serves the generated assets.

- [Maintenance mode and cleanup](docs/MAINTENANCE.md)
- [Backup and restore](docs/BACKUP-AND-RESTORE.md)
- [Installation wizard and health checks](docs/INSTALLATION.md)
- [First deployment: every step](docs/FIRST-DEPLOYMENT.md) — exact files, values, SEO configuration, host setup, and release commands.
- [Shared-host Git deployment](docs/DEPLOYMENT.md) — first installation, configuration, build/push/pull updates, backups, and verification.
- [Add content modules with one command](docs/CONTENT-MODULES.md)
- [Reusable form pattern](docs/FORMS.md)
- [Backend guide](docs/BACKEND-GUIDE.md) — reusable feature structure.
- [Security review and repeatable checks](docs/SECURITY-REVIEW.md)
- [PHP mail configuration](docs/MAIL.md)
- [Optional email two-factor authentication](docs/TWO-FACTOR.md)
- [Permanent integration tests](docs/TESTING.md)
- [Authentication](docs/AUTHENTICATION.md)
- [User management](docs/USER-MANAGEMENT.md)
- [Content and media](docs/CONTENT-MANAGEMENT.md)
- [Services](docs/SERVICES.md)
- [SEO](docs/SEO.md)
- [Metadata editing and activity feed](docs/METADATA-AND-ACTIVITY.md)
- [Automatic public-page discovery](docs/AUTOMATIC-PAGES.md)

Local frontend: `npm ci` then `npm run dev`. Configure `.env.local` with `PHP_API_URL=http://localhost:8000`. Run the PHP development API with `php -S localhost:8000 -t public`. Copy `php/config.example.php` to `php/config.php`, enter your database settings, then run `php php/migrate.php migrate`.

Production: follow the deployment guide; the Vite development server is not needed on the host.
