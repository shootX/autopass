# Autopass — Memory

## 2026-10-12
- Pest added (`pestphp/pest`, `pest-plugin-laravel`); run `cd backend && vendor/bin/pest`. Tests use SQLite in-memory; APP_KEY/JWT_SECRET test values are in phpunit.xml.
- Migration `2026_09_23_201500_make_user_profile_fields_nullable` is Postgres-only SQL; it now no-ops on SQLite (production Postgres path unchanged).
- OTP TTL = 15 min (`AuthController::OTP_TTL_MINUTES`). OTPs are deleted after successful use.
- `set_password` now requires `{temp_code, code, password}`.
- Sandbox/test payment routes exist only when APP_ENV != production.
- Seeder passwords come from `SEED_ADMIN_PASSWORD`, `SEED_MANAGER_PASSWORD`, `SEED_CLIENT_PASSWORD`, `SEED_PARTNER_PASSWORD` (random if empty).

## 2026-10-12 (P1)
- Rate limiters are defined in `AppServiceProvider::boot()`: `otp`, `login`, `voucher`, `public`.
- CORS: Laravel's global `HandleCors` + `config/cors.php`. Empty `CORS_ALLOWED_ORIGINS` = no browser origin is allowed. The mobile app is not affected.
- Voucher ownership: `vouchers.partner_id`. Unassigned vouchers (NULL) **cannot** be checked/used by any partner (fail closed).
  Backfill: before deploying, an admin sets the partner on each voucher (admin → Vouchers → edit → Partners), or via SQL
  `UPDATE vouchers SET partner_id = <id> WHERE id IN (...)`. If there is only one partner: `UPDATE vouchers SET partner_id = (SELECT id FROM partners LIMIT 1) WHERE partner_id IS NULL;`.
- Admin destructive actions use `window.deleteRequest(url)` (`resources/views/components/delete-request.blade.php`), included in the admin and partner layouts.
- BE-09 rule: a manager can approve walk-ins (no booking today) and bookings at their own branch; a booking today at another branch → 403. Each approval → `package_washes` row (branch, manager, appointment or walk_in).
- Tests: the JWT guard caches the user between requests in one test; call `freshAuth()` (P1HardeningTest) when switching actors.

## 2026-10-12 (PM decisions)
- Vouchers are never deleted on use anymore: `user_vouchers.used_at` / `used_by_partner_id`. `User::vouchers()` filters `used_at IS NULL`.
- Voucher SMS brute-force lock: RateLimiter key `voucher-use:{temp_code}`, 5 attempts / 10 min, then the SMS code is deleted.
- `CORS_ALLOWED_ORIGINS` default in `.env.example`: `https://wash.socialsave.cc`.
