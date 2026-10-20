# PHP mail configuration

The website uses the hosting provider's PHP `mail()` transport. There is no SMTP client, email-server process or new Composer dependency in the application. Your host must enable/configure outgoing PHP mail. Application configuration alone cannot enable a function disabled by the provider or configure the host's delivery service.

## Project files

- `php/mail.config.php`: shared defaults and environment-variable support.
- `php/mail.config.local.example.php`: versioned template for each website.
- `php/mail.config.local.php`: private website-specific settings, ignored by Git.
- `php/functions/mail.php`: reusable `sendSiteMail()` helper and password-reset email.

For this local project, the private config contains:

```php
<?php
return [
    'enabled' => false,
    'transport' => 'mail',
    'from_address' => 'noreply@haven.example',
    'from_name' => 'Haven',
    'reply_to' => '',
];
```

`noreply@haven.example` is a placeholder. Replace it with the sender accepted by the actual website's host before enabling delivery. Change these settings separately for each project; do not invent a sender on a domain you do not control. No actual email was sent while implementing this configuration.

## Set up on the shared host

1. Enable PHP `mail()` through your hosting provider. Confirm PHP has `mbstring`, and ask which sender address/domain is accepted. Follow the provider's domain-authentication instructions if required.
2. In the live **private PHP directory**, create `mail.config.local.php` from the example if it does not already exist. Git push/pull does not transfer this ignored private file. Keep an existing configuration rather than overwriting it during updates.
3. Set `from_address` to the approved sender, `from_name` to the website name, and optionally `reply_to` to a real inbox for replies. Set `enabled` to `true` when ready. Leave `transport` as `mail`.
4. Set `app_url` in `php/auth.config.local.php` to the website's actual HTTPS root URL, such as `https://example.com`. Password-reset links use this trusted configuration; they never use the request Host header. Localhost HTTP is supported for local testing only. Do not leave localhost in production reset links.
5. Run:

   ```sh
   php php/commands/health-check.php --production
   ```

   The check validates settings, detects example sender domains and reports whether PHP exposes `mail()`. CLI and web PHP can have different settings; check the web PHP configuration with your host too. The health check does not send mail or establish inbox delivery.
6. With maintenance mode off, request a password reset for your own existing account. Check your inbox/spam folder and complete the reset. The form deliberately returns the same message for existing/non-existing accounts and for delivery failures. That message alone is not proof of sending; inspect the private host PHP error log and provider delivery logs if mail does not arrive. The application never logs reset links or tokens.

Keep mail disabled on local machines unless you have deliberately configured a local capture transport. A local PHP installation exposing `mail()` does not necessarily have a working delivery service.

## Settings and compatibility

| Setting | Meaning |
| --- | --- |
| `enabled` | Boolean application switch. Defaults to false. |
| `transport` | Must be `mail`; no SMTP credentials are needed by this implementation. |
| `from_address` | One valid host-approved sender email. |
| `from_name` | Website display name, 1–100 bytes; also used in reset-email subjects. |
| `reply_to` | Optional fixed reply address; blank omits the header. |

Environment alternatives are `MAIL_ENABLED=1`, `MAIL_FROM`, `MAIL_FROM_NAME` and `MAIL_REPLY_TO`. Private local settings take precedence. PHP does **not** automatically read Vite's `.env.local`; use actual PHP/server environment variables or the private PHP file.

The existing `mail_from` in authentication configuration remains a fallback when a mail-specific sender/environment value is absent. Prefer `from_address` in the new private mail config for new websites. The installer still accepts the existing sender prompt and reminds you to enable/configure mail explicitly afterward. It does not enable delivery automatically.

The private mail config is included in new backup archives. By default restores preserve the current host configuration; `--with-config` can replace archived private settings, including mail. Review sender/domain/enablement after moving a site.

## Reuse in other features

```php
require_once __DIR__ . '/../functions/mail.php';
sendSiteMail($recipient, 'Your subject', "Your plain-text message.");
```

Use only from a feature that is explicitly intended to send a transactional message, after validation, permission/rate checks and a successful write where appropriate. Header fields use the fixed configuration; form inputs are never used as a From header. The helper rejects control-character injection and invalid recipients, encodes UTF-8 headers and plain-text content, and does not accept extra sendmail command arguments.

Password recovery and opted-in email two-factor verification send messages. Factor changes also attempt a security notification. See [optional 2FA](TWO-FACTOR.md). Saving contact inquiries or newsletter subscribers does not automatically send notifications or campaigns. This helper is not a bulk newsletter delivery system.

## Testing and delivery limits

```sh
npm run test:mail
```

The tests replace PHP's mail function with a local capture stub. They verify configuration/defaults, sender/reply headers, UTF-8/quoted-printable encoding, Haven branding, trusted reset URLs, disabled/unavailable transports, rejected handoff and header-injection rejection. They do not send external email.

PHP `mail()` returning true means the message was accepted for delivery, not that it arrived in the inbox. Verify actual delivery on the host. See the official [PHP mail manual](https://www.php.net/manual/en/function.mail.php).

After changing PHP mail configuration on a host that caches PHP files, use the provider’s PHP/OPcache refresh procedure if changes are not yet reflected. Check effective configuration and actual inbox delivery before enabling optional email 2FA.
