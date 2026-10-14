# Stage 3 implementation and verification — 2026-10-14

Base: `f7517518915c916a8014bfe8c2e1687f18256ec0` (main inspected on 2026-10-14).
Local branch: `codex/stage3-product-fixes`.
Publication: blocked by GitHub integration HTTP 403, `Resource not accessible by integration`.
No remote branch/PR, main merge or deployment was performed.

## Fixed behavior

1. AddCar sends catalog id provenance, brand/model ids, year and normalized plate. It stores the server-created car id. Catalog generation years are used when available; a manual fallback is identified explicitly. EditCar loads an owned car directly from its URL, preserves year and rejects stale route data.
2. Booking rejects a foreign car, services outside the selected branch, past times, closed hours and duplicate exact slots (including the same car across branches). Appointment and services are saved in one transaction. Branch/car row locks coordinate concurrent writers on a native database.
3. Administrative password changes revoke existing user credentials through SessionRevoker.
4. Repeated package checkout requests reuse a recent matching pending order and checkout URL. Every paid payment has its own uniquely linked package benefit; replaying the same callback cannot grant twice.
5. Expired/exhausted packages permit a new purchase. Expiration notification no longer deletes history and records that notification once.
6. Statuses agree across API, frontend, admin, partner and reports: 0 pending, 1 confirmed, 2 cancelled, 3 completed, 4 expired, 5 rescheduled. Terminal history is retained and excluded from client upcoming bookings. Completed statistics use 3.
7. Six translation dictionaries cover referenced keys. Booking errors follow the requested supported locale. The checker rejects empty/missing values and has a regression test.
8. npm 11 clean installation works with the checked-in optional-platform lock records. Existing dependency versions were retained; no new project runtime dependency was introduced.

## Verification completed locally

| Check | Result | Command |
| --- | --- | --- |
| Full backend feature suite (SQLite) | 131 passed, 695 assertions | `cd backend && vendor/bin/pest` |
| Main frontend | 92 passed | `cd gewash && npm test` |
| Main types/build/lint | Passed | `npm run build:check && npm run lint` |
| Voucher frontend | 9 passed; build/lint passed | `cd gewash/voucher-checker && npm test && npm run build && npm run lint` |
| Translation dictionaries | 373 referenced keys, no missing keys in six files | `cd gewash && npm run i18n:check` |
| Translation checker regression | Passed | `cd gewash && npm run i18n:test` |
| Clean install | Both projects passed with Node 24.19.0 / npm 11.9.0 | `npm ci` in fresh dependency directories |

Twenty new backend regressions and seven new frontend regressions are included. Test providers are faked/mocked; no real payment, SMS or email was sent. PHP changes were formatted with the existing Laravel Pint dependency.

## Required follow-up verification

- **Native PostgreSQL concurrency:** a PostgreSQL 16 CI job with database-backed cache is included but has not run. PostgreSQL-WASM ran the 20 feature regressions successfully and one file-cache run passed all 22 checks. Later parallel runs were unstable: database-cache checkout produced a PDO error, and the latest file-cache run reported a booking race failure before completing. These results do not certify native PostgreSQL locking or production concurrency. Run `CACHE_STORE=database vendor/bin/pest -c phpunit.pgsql.xml` against an isolated database ending in `_test`; inspect both race tests before release. Do not replace these checks with the successful SQLite suite.
- **Visual and browser E2E:** not executed. Chromium installation failed because downloaded archives were truncated. React tests use a DOM test environment and mocked API responses; they do not prove every panel, pixel, mobile layout or browser flow is correct.
- **Car catalog:** documented generations endpoint and supported year aliases were tested with fake responses. Authenticated live response fields and coverage must be verified with an actual CarAPI key. The manual year range is a fallback, not verified catalog data.
- **External delivery:** actual SMTP one-time-password delivery, SMSOffice requests, payment checkout/callback and push delivery need sandbox credentials/configuration. Existing test payment was retained as requested; SMS remains mocked.
- **Booking duration/capacity:** the current data model has no service-duration or bay-capacity rule. Validation prevents duplicate exact timestamps; it does not infer overlapping durations or introduce an unapproved capacity policy.
- **Migration:** the additive migration converts legacy boolean appointment statuses to integer codes and adds package-payment linkage/expiration-notification metadata. Its down migration retains integer statuses to avoid collapsing history. Validate against a copy of the target database before deployment; no target database was accessed.

This is a reviewable local implementation, with the release checks above still open. It is not an all-product or deployment approval.
