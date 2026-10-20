# Security and improvement review

Reviewed locally on 19 October 2026. This review covers the React application, PHP API, authentication, authorization, forms, content, media, exports, SEO responses, database access and deployment configuration. It includes fixes and local integration tests; it is not a guarantee that every vulnerability has been found or a test of your actual shared host.

## Issues fixed

| Area | Finding and change |
| --- | --- |
| JSON requests | Unbounded reads and permissive content types allowed unnecessary resource consumption and simple cross-origin submissions. JSON endpoints now require `application/json`, cap bodies at 1,100,000 bytes, limit nesting and reject malformed/non-object JSON. Responses use 415, 413 or 422 as appropriate. Multipart media uploads retain their separate validation. |
| Password changes | Concurrent password reset, profile password changes and login rehashes could act on stale credentials. Sensitive user operations now serialize through the management lock, recheck credentials/session versions and conditionally update hashes. Sensitive administrative changes increment session versions atomically. |
| Login throttling | A blocked IP could still allocate account-specific limiter rows. Account limiter checks now stop when the IP check fails. |
| CSV exports | Formula detection missed whitespace, line-feed and fullwidth prefixes. PHP and React exports now escape matching dangerous cells consistently and quote embedded quotes. |
| Error handling | Bootstrap failures could escape normal API error handling. Bootstrap now runs inside the error boundary; production entry points suppress displayed PHP errors and return generic failures. Server logs retain diagnostics. |
| Response headers | Added MIME-sniffing, framing, referrer and baseline content-security protections. Sensitive HTML and API responses are not stored. HTTPS responses add HSTS. Static Apache responses receive baseline headers when `mod_headers` is available. |
| Upload validation | Malformed upload metadata now fails validation before file inspection. Existing image MIME/type/size checks and generated filenames remain. |
| Profile updates | Added a per-user update limit to reduce repeated writes and activity-feed spam. |
| Redirect paths | Reject malformed paths containing backslashes, control characters, excessive length or a leading double slash before page redirects. |

The CSP is a **baseline** (`frame-ancestors`, `base-uri`, `object-src`, `form-action`); it does not restrict all scripts/styles. A stricter policy needs testing with the editor and the rest of the UI. HSTS depends on PHP correctly detecting HTTPS at your host.

CSV escaping reduces formula execution on initial import. Spreadsheet software can reinterpret values after saving/reopening; exports must still be treated as untrusted data. See [OWASP CSV Injection](https://community.owasp.org/attacks/CSV_Injection).

## Other improvements made

- Public content rendering now decodes fetched records directly and batches image metadata lookups, removing repeated per-card record and media-usage queries.
- Fixed production sitemap routing for module identifiers containing underscores.
- Added repeatable security regression tests without adding application dependencies.

## Verification

The local checks cover:

- Anonymous access denial and Manager denial for user management; Admin user create/edit/ban/unban/delete and owner/self protection.
- Session rotation, CSRF rejection, session-version revocation, login throttling, reset token expiry/replacement/single use, and stale password-change rejection.
- Newsletter/contact validation, honeypots, submission limits, ignoring spoofed forwarded-IP headers, and initial unread status.
- JSON content-type/body/depth limits and generic bootstrap errors.
- Stored markup escaping, rejection of script URLs, harmless SQL-like search input and PHP/React CSV parity.
- Content CRUD, draft/scheduled visibility, media usage protection, pagination, slug redirects, metadata, sitemap and activity permissions.
- Built production responses: private configuration paths return 404, reset pages use no-store/no-referrer, public pages receive security headers, and service sitemap returns XML.

`npm run build`, `npm run lint`, all 12 security/module/route unit tests and syntax checks for 72 PHP files passed. The local integration suites passed. `npm audit` reported **zero known dependency advisories** on this date. That result does not assess PHP, MySQL, Apache or the hosting provider.

To repeat the committed checks:

```sh
npm run test:security
npm run test:modules
npm run test:routes
npm run build
npm run lint
```

For the integration security test, first run the local PHP API and Vite servers as described in the README, with an isolated development database:

```sh
npm run test:security:e2e
```

This test creates and removes disposable records and temporarily resets/restores local limiter counters. **Never run it with production database credentials.** The broader CMS/user/metadata regression suites used during this review were temporary local audit harnesses, not all committed as permanent tests.

## Remaining priorities

1. **Verify the real shared host.** Keep private PHP/configuration outside `public_html`; confirm HTTPS, secure session cookies, Apache rewrite/header rules and that uploads cannot execute PHP. The local PHP preview does not prove Apache `.htaccess` behavior. Confirm access to `.env`, private config, logs and backups fails. Follow [the deployment guide](FIRST-DEPLOYMENT.md).
2. **Test backups and outbound email.** Restore a database-and-upload backup into a separate environment. Verify password-reset delivery and the production reset URL after configuring your domain. This review did not send external mail or deploy anything.
3. **Set retention and storage policies.** Expired reset tokens, rate-limit entries and activity records need an agreed cleanup policy. Uploads have per-file and request limits but no aggregate account/disk quota. Monitor shared-host storage and choose retention before automating deletion.
4. **Consider owner MFA.** Password authentication is implemented; an authenticator-based second factor would further protect the owner account.
5. **Reduce reset timing differences if needed.** Forgot-password responses use generic text, but synchronous mail delivery for a known account can create timing differences. This remains a potential enumeration side channel; no timing exploit was demonstrated. A carefully bounded delivery mechanism can improve this if required.
6. **Keep dependencies and the host runtime updated.** Repeat dependency audits, and check the host's supported PHP/database versions. The editor is lazy-loaded but still generates a large bundle; optimize it if admin loading becomes a problem.

The activity feed is best-effort application logging, not a tamper-proof audit ledger. Database portability also needs work before claiming full SQLite/PostgreSQL support: the initial migration remains MySQL-specific. Neither requires expanding this lightweight shared-host architecture now.

No schema migration or new environment variable is required for these security fixes. Existing deploy configuration still applies.

## References

- [OWASP password reset guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
- [OWASP CSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP HTTP security headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html)

## Follow-up: optional factor and retained tests

Optional email two-factor authentication is now available in Profile settings, default off; [the factor guide](TWO-FACTOR.md) describes behavior and recovery. The previous temporary CMS/user/auth/metadata checks have been retained in [the isolated integration runner](TESTING.md), alongside additional submissions and 2FA checks. Earlier statements about missing optional MFA or temporary-only suites describe the original review date.
