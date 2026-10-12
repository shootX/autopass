# Autopass — Memory

## 2026-10-12
- Pest added (`pestphp/pest`, `pest-plugin-laravel`); run `cd backend && vendor/bin/pest`. Tests use SQLite in-memory; APP_KEY/JWT_SECRET test values are in phpunit.xml.
- Migration `2026_09_23_201500_make_user_profile_fields_nullable` is Postgres-only SQL; it now no-ops on SQLite (production Postgres path unchanged).
- OTP TTL = 15 min (`AuthController::OTP_TTL_MINUTES`). OTPs are deleted after successful use.
- `set_password` now requires `{temp_code, code, password}`.
- Sandbox/test payment routes exist only when APP_ENV != production.
- Seeder passwords come from `SEED_ADMIN_PASSWORD`, `SEED_MANAGER_PASSWORD`, `SEED_CLIENT_PASSWORD`, `SEED_PARTNER_PASSWORD` (random if empty).
