# Controlled test deployment — 2026-10-14

Target: https://wash.socialsave.cc/ (test server; no real customers yet).
Reviewed upstream: PR #27, `37187366804d114b3b8f678348dd9e28b5d6991d`.
Goal: a working hosted environment for designated testers, with production-style error/security settings and simulated transactions.

## What is verified

- PR #27 contains the exact exported Stage 3 code. Its final upstream commit changed documentation only. Main remains `f751751`; PR is open, unmerged and Draft.
- GitHub Actions run 38543496588 succeeded for frontend and backend. Native PostgreSQL 16/database-cache checks succeeded, including the booking and package checkout race checks. The PR description's “CI has not run” statement is now outdated.
- Follow-up hardening removes login token response logging and displays a localized network-error alert. The service worker now stores public assets only, respects no-store/private directives, and clears the old AutoPass cache. Personal HTML pages and API requests are never served through its offline cache. Navigation to uncached pages requires a connection.
- Eleven new regression tests exercise these changes. The full main frontend suite passes 103/103, build:check and lint pass, six dictionaries cover 374 referenced keys with no gaps, and the translation checker test passes.
- Example endpoint values now use the intended wash/CarAPI hosts. The voucher example no longer points to an expired ngrok tunnel.

## Browser observations (current hosted version)

Landing, `/auth`, `/renew-password`, `/register`, `/privacy`, and the admin login reached through `/dashboard` render. The auth screen was visually inspected. No site application console error was observed in the inspected pages; unrelated browser-extension errors were excluded.

No credentials, OTPs, registrations, bookings or payments were submitted. These observations do not prove Stage 3/follow-up deployment, all private panels, all layouts, or full end-to-end flows. No deploy commit marker is available on the site.

Polish still open: anonymous `/help` redirects to login despite its landing-page link; the public privacy text is English-only; terms and privacy share one landing footer destination. These do not prevent a closed test, but must be assessed before opening to customers.

## Required hosted configuration

Check the actual server values without printing credentials. `.env.example` is a template, not evidence of server configuration.

| Area | Requirement for this closed test |
| --- | --- |
| Release | Stage 3 plus this follow-up from one recorded commit; build frontend and deploy its matching backend. |
| Error handling | `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, existing APP_KEY/JWT_SECRET preserved. |
| Frontend | API/base URLs point to the hosted backend; `VITE_BYPASS_AUTH=false`; voucher mock disabled; build with Node 24.19.0/npm 11.9.0. |
| SMS (no API key yet) | `SMS_DRIVER=mock`, `SMS_MOCK_INBOX_ENABLED=true`; codes visible only through authenticated admin Inbox. Scheduler prunes expired Inbox content. Never return codes in public API responses. |
| Test payments | Keep simulated checkout. For production-style app env: enable `TEST_PAYMENT_ALLOW_PRODUCTION=true` and restrict `TEST_PAYMENT_PHONES` to designated testers; admins are already allowed. Verify this remains a simulation and does not initiate real payment requests. |
| Email | Use working SMTP or a private test SMTP catcher for temporary partner/corporate passwords. `MAIL_MAILER=log` alone does not deliver an email to the recipient. Do not bypass mandatory password change. |
| Car catalog | Actual `CARAPI_SECRET` configured server-side and `CARAPI_URL=https://car.socialsave.cc/api/v1`; verify response provenance is carapi/catalog where covered. Manual fallback must remain explicit for uncovered generation years. |
| Server routing | Existing nginx must keep SPA deep links, Laravel API, admin/corporate panels, payment callbacks and static assets correctly routed. The repository nginx example is SPA-only, not a complete replacement for the current server config. |
| Background work | Queue worker runs for the matching release; Laravel scheduler runs for OTP expiry, notifications and package expiration. Database cache/session/queue migrations exist. |
| Access | Use test accounts for each role; no real customer data or messages. Keys are configured through the server's secret mechanism, never committed or pasted into chat. |

## Migration and rollback gate

Back up the target test database/configuration and keep the previous release. Run all pending migrations first on an isolated copy of that database. Record migrate status before/after and check row counts, existing appointment statuses and package history. New status migration rejects unknown legacy statuses rather than silently rewriting them.

Do not use migrate:fresh, seed destructive data, rotate existing secrets, or apply an unreviewed nginx replacement. No copy of the hosted database or SSH/control-panel connection was available in this review.

The Stage 3 down migration deliberately keeps integer appointment status types. A safe rollback is restoration of the recorded database backup and matching prior release; do not assume rolling back the code alone restores old semantics.

## Acceptance after test deployment

Record the deployed commit and actual URL; verify migrations, frontend/backend version match, public entry pages and refreshed deep links. Inspect the updated service worker and confirm the old cache is removed after activation.

Using disposable designated test accounts, exercise customer registration/OTP via admin Inbox, login/reset, brand/model/year and direct car edit, branch/service/hour validation, one complete booking, manager confirmation/reschedule/completion, simulated payment/package/QR, partner voucher challenge, and corporate invitation/password-change/report flows. Check role isolation and logout on each panel, inspect user-visible errors, verify private pages are not stored in the service-worker cache, and test narrow mobile and desktop widths.

No real payment, SMS or email is authorized by this report. Use simulation/test inboxes unless the user separately requests real delivery. Report each failed or skipped flow explicitly; do not replace browser results with the passing unit tests.

## Readiness conclusion

The code is a candidate for a controlled test deployment after this follow-up is uploaded. Hosted configuration, database-copy migration, server rollout and role-based browser acceptance remain open. This report does not certify readiness for real customers or imply that a deployment was performed.
