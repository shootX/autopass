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
