# Optional email two-factor authentication

Email two-factor authentication is **off by default** for every existing and new account. It is not required for the owner or Managers. Each user controls their own setting under **Profile settings → Email two-factor authentication**. User management does not let Managers edit other accounts or let an API payload change somebody else's 2FA setting.

## Turn it on

1. Configure and verify real email delivery on the host using [mail setup](MAIL.md). The local Haven example sender and disabled mail setting are not a working inbox-delivery configuration.
2. Sign in normally and open Profile settings.
3. Select **Require an email code when I sign in**, enter the current password and save.
4. Enter the six-digit code sent to the account's existing email and confirm.

The setting remains **off** until the code is successfully verified. If mail fails, the operation fails without enabling it. Emails cannot be changed in Profile settings. Sender branding comes from the site's private mail config.

After confirmation the current browser remains signed in, other sessions are revoked, and a change notification is attempted by email. Notification-delivery failure does not undo a successfully verified change.

## Sign-in behavior

- **Off:** email and password sign-in works as before, even when outgoing mail is disabled.
- **On:** valid email/password starts a pending verification session and sends a code. The user receives no authenticated API access until they enter the correct code. The login screen provides **Send a new code** and **Back to sign in**.
- Refreshing the login page preserves an unexpired pending step in the same PHP session.
- If delivery fails for an opted-in account, password-only login is not allowed as a fallback. Correct mail delivery or use private terminal recovery.

Codes expire within **10 minutes**, are stored only as salted password hashes and can be used once. Resending replaces the previous code and does not extend the original password-verified session's 10-minute lifetime. The user must start with their password again after that time.

## Turn it off

Uncheck the setting, enter the current password, save and confirm the emailed code. Until confirmation the existing setting remains enabled. Other sessions are revoked after the change. **Start again** returns to the password step; a new request replaces the previous challenge for that purpose.

Password resets/changes and sensitive account changes invalidate outstanding challenges through session versions and challenge removal. Password recovery **does not switch 2FA off**. Ban/deletion also prevents pending codes from signing in.

## Limits and safeguards

- All mutation endpoints require CSRF protection.
- Login-code challenges are bound to the pending PHP session; knowing a code without the password-verified session is insufficient.
- Each challenge permits at most five incorrect codes.
- Sending, including resend and setup, is limited to three per user and twenty per IP per fifteen minutes.
- Verification is limited to ten attempts per user and sixty per IP per fifteen minutes, in addition to the per-challenge limit. New challenge identifiers cannot reset account limits.
- Codes, passwords, challenge hashes and reset links are not returned in API responses or recorded in activity summaries. The activity feed records enabling/disabling and verified sign-in.
- There is no production test code, fixed OTP, public bypass endpoint or forced enrollment policy.

This is email-based verification and depends on mailbox security. It does not offer the phishing resistance of passkeys or hardware-backed factors. See [OWASP's MFA guidance](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html).

## Private recovery

If an opted-in owner cannot receive email, fix mail first. An authorized server operator can explicitly disable the affected account's factor through the private terminal:

```sh
php php/commands/two-factor-recovery.php owner@example.com --confirm=DISABLE-2FA
```

Replace the example email with the actual account. This requires trusted terminal/filesystem/database access; it is not a web endpoint. It disables only that account's 2FA, revokes its sessions, clears pending codes/reset tokens and records a terminal-recovery activity. It does not change the password, role or ban status. Do not run it routinely or expose the command through a public PHP wrapper.

## Deployment and maintenance

Migration `011_optional_email_two_factor.php` adds a default-off user flag and the `two_factor_challenges` table. It has been applied locally. On another host, use maintenance mode during deployment, back up, deploy the PHP/frontend release, then run:

```sh
php php/migrate.php migrate
php php/commands/health-check.php --production
```

Reopen only after successful checks. No account is automatically enrolled by migration.

Default maintenance cleanup includes expired 2FA challenges. Preview only that target with:

```sh
php php/commands/cleanup.php --dry-run --only=two-factor
```

Backup restore preserves the stored opt-in flag but discards restored verification challenges and revokes sessions. A full restore from a snapshot predating this feature needs migration before the new application serves traffic.

## Verification

```sh
npm run test:two-factor
```

This starts an isolated local PHP server and disposable database. Native PHP mail is routed into private temporary capture files, so no external emails are sent. It covers opt-in/out, owner and Manager behavior, default password-only login, pending access denial, code hashing/session binding, expiry, retries/resends, replay, CSRF, mail failure, ban/reset invalidation and private recovery. See [permanent integration tests](TESTING.md).
