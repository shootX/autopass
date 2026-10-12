# Autopass — Tasks

## 2026-10-12 — P0 backend security (branch `fix/p0-backend-security`)
Source: autopass-audit-report-2026-10-10.pdf
- [x] BE-01 Partner voucher routes (`/api/partner/voucher/*`) behind `auth:partners` (middleware was attached after `group()` and never applied). Login stays public.
- [x] BE-06/02 OTP no longer returned in `/api/register` response; `.env.example` now production-safe (APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=error, no test payment key, live TBC URL). Server `.env` untouched.
- [x] BE-08 `DELETE /api/packages/{userPackage}/remove` checks ownership (403 otherwise).
- [x] BE-12 `/api/register/set_password` requires `code` (OTP) + `temp_code`, type `verify`, 15 min expiry, deleted after use. `/register/verify` checks type + expiry. Password reset codes are single use with 15 min expiry.
- [x] BE-13 Hard-coded passwords removed (register placeholder → random; seeders → `SEED_*_PASSWORD` env or random).
- [x] BE-15 `/payment/sandbox` (web) and `/api/testpay` not registered when APP_ENV=production.
- [ ] Mobile app: send `code` to `/register/set_password` (breaking change — coordinate with Mobile).
- [ ] Deploy: confirm server `.env` has APP_ENV=production so BE-15 takes effect.

## 2026-10-12 — P1 backend hardening (branch `fix/p1-backend-hardening`, based on P0 branch)
- [x] BE-04 `/api/promo` returns only `title, description, url`, throttled (`public`, 60/min).
- [x] BE-05 All admin dashboard deletes (16 routes) and partner panel car delete are `DELETE` + CSRF (method spoofing via `window.deleteRequest`). GET returns 405. Fixed duplicate route name (`admin.clients.cars.delete`) and removed the "edit" icon in client packages that pointed to the car delete URL.
- [x] BE-09 `managerApprove` (updated per PM): walk-ins allowed; 403 only if the car's booking today is at another branch; every approval is recorded in `package_washes` on the manager's branch.
- [x] BE-10 `editMyCar`/`removeMyCar` return 404 `success:false` when the car isn't the user's; edit only accepts `plate` + model (no mass assignment).
- [x] BE-11 `managerApprove` returns 403 to non-managers (was an empty 200).
- [x] BE-14 Auth controller no longer returns `$e->getMessage()`; errors are `report()`ed.
- [x] BE-16 `CarCatalog` falls back to local `car_brands`/`car_models` when CarAPI is unconfigured or failing.
- [x] BE-17 `/api/checkqr/{qr}` returns user `id, name, surname` only; 404 when the QR is unknown.
- [x] Rate limits: `otp` (5/min per IP, 20/h per phone+IP), `login` (10/min per IP, 5/min per phone/login), `voucher` (20/min per partner/user).
- [x] CORS: `config/cors.php`, origins from `CORS_ALLOWED_ORIGINS`; `CorsMiddleware` no longer forces `*`.
- [x] Vouchers tied to partners: `vouchers.partner_id` (nullable FK), admin form select, partner check/use only for own vouchers.
- [ ] Before deploy: set `CORS_ALLOWED_ORIGINS` on the server and backfill `vouchers.partner_id`.
- [x] Admin `clients/{id}/ban` is POST + CSRF (forms in clients and managers lists).

## 2026-10-12 — PM decisions + audit part 2 (same branch)
- [x] (d) Ban client → POST + CSRF.
- [x] (c) BE-09 walk-ins re-allowed, recorded on own branch (`package_washes.walk_in`).
- [x] (a) `.env.example` `CORS_ALLOWED_ORIGINS=https://wash.socialsave.cc`.
- [x] BE-18 (part 2) `voucher/use`: 5 wrong codes → SMS code deleted, 429; code expires after 10 min; constant-time compare.
- [x] BE-19 (part 2) `user_vouchers.used_at` + `used_by_partner_id`; use marks instead of deleting; used vouchers hidden from client lists and can't be checked again.
- [ ] Not backend / not in scope: FE-03/04/07/08/11/12, MOB-*, UX-*, DEPLOY-01 (build stamp), BE-07 (server storage permissions, ops).
- [ ] Open: JWT TTL default is 90 days (`JWT_TTL=129600`, related to FE-08) — shorten? Needs a decision because it logs mobile users out sooner.

## 2026-10-12 — UX backend + deploy runbook (PR #8)
- [x] UX-09 promo typo „ჭკკვიანი“ → „ჭკვიანი“: text is in the DB (`promos`), fixed by an idempotent data migration `2026_10_12_000004_fix_promo_typo`.
- [x] UX-30 corporate account: current password required only when setting a new password.
- [x] `docs/DEPLOY_RUNBOOK.md` (.env keys, BE-07 permissions, nginx/UX-12 + security headers, DEPLOY-01 stamp, voucher backfill, rollback, merge order #5–#13).
