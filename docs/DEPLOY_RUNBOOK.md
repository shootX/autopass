# Autopass — Deploy runbook

Written 2026-10-12, for deploying the audit fixes (PRs #5–#13) to https://wash.socialsave.cc.
Only run this with Shota's go-ahead. `APP_ROOT` below means the server checkout (aaPanel site dir, e.g. `/www/wwwroot/<site>`). The web server user is `www` on aaPanel.

## 0. Merge order (GitHub)

All PRs target `main`. Order checked with a local trial merge on 2026-10-12:

| # | PR | Why here |
|---|----|----------|
| 1 | #7 P0 backend security | Base of #8. Breaking change: `set_password` now needs `code`, so **deploy together with #9**. |
| 2 | #8 P1 backend hardening | Branched from #7. Contains #7's commit, so merge it after #7. Needs the `.env` keys and voucher backfill below. |
| 3 | #5 project docs | Docs only. Conflicts only in `docs/TASKS.md` / `docs/MEMORY.md` (keep both sides). |
| 4 | #6 FE-11 manager routing | First frontend PR. Adds vitest to `gewash/package.json`. |
| 5 | #9 SPA set_password code, FE-03/04 | Required by #7. Conflicts with #6 in `gewash/package.json`/lock → rebase on main after #6. |
| 6 | #10 FE-06–10 (token expiry, lending logged-in redirect) | Conflicts with #6 in `package.json`/lock and `App.tsx` → rebase. Contains the UX-12 frontend part (`lending/logged-in-redirect.js`). |
| 7 | #11 MOB-* | Conflicts in `package.json`/lock and `App.tsx` → rebase after #10. |
| 8 | #12 PWA, dead deps | Conflicts in `package.json`/lock → rebase after #11. |
| 9 | #13 UX audit fixes | Last. Touches `partner/account.blade.php` (also changed in #8 for UX-30) and `lending/*` (also in #10). Rebase and keep the #8 rule: `current_password` field **not** `required`. |

Every PR conflicts on `docs/TASKS.md` and `docs/MEMORY.md`; resolve by keeping both entries.
For package-lock conflicts: take main's version, re-apply the PR's `package.json` change, then run `npm install`.
PR #1 (old visual redesign) is not part of this release.

## 1. Before deploying

```bash
cd APP_ROOT && git rev-parse HEAD > /root/autopass-prev-commit.txt   # needed for rollback
mysqldump/pg_dump <db> > /root/autopass-$(date +%F-%H%M).sql          # DB is pgsql per .env.example
cp backend/.env /root/autopass-env-$(date +%F).bak
```

## 2. Server `backend/.env`: set and verify

| Key | Value | Why |
|-----|-------|-----|
| `APP_ENV` | `production` | BE-06/02. BE-15: sandbox/testpay routes are only registered outside production |
| `APP_DEBUG` | `false` | BE-02: no stack traces |
| `LOG_LEVEL` | `error` | |
| `CORS_ALLOWED_ORIGINS` | `https://wash.socialsave.cc` (comma-separated if there are more) | #8: if empty, browsers block the SPA's API calls |
| `JWT_TTL` | `43200` (30 days; code default since 2026-10-12, FE-08) | Set explicitly or leave unset to use the default |
| `JWT_SECRET`, `APP_KEY` | must be set (unchanged) | |
| `SEED_ADMIN_PASSWORD`, `SEED_MANAGER_PASSWORD`, `SEED_CLIENT_PASSWORD`, `SEED_PARTNER_PASSWORD` | leave empty in production (random) | BE-13. Never run seeders on production |
| `FLITT_PAYMENT_KEY`, `TBC_*` | live values, no `test` | |
| `SMSOFFICE_API_KEY`, `SMSOFFICE_SENDER` | set | OTP SMS. Without them registration can't work in production |

Check: `php artisan about --only=environment` should show env production, debug OFF.

## 3. Deploy steps

```bash
cd APP_ROOT
git fetch origin && git checkout main && git pull --ff-only
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan down --retry=60
php artisan migrate --force          # #8: vouchers.partner_id, package_washes, user_vouchers.used_at, promo typo fix (UX-09)
# voucher backfill (section 5) BEFORE php artisan up
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Frontend:
```bash
cd APP_ROOT/gewash && npm ci && VITE_BUILD_COMMIT=$(git rev-parse --short HEAD) npm run build
cd APP_ROOT/lending && sh build.sh
```

## 4. Permissions (BE-07)

During the audit the admin panel returned 500 until storage permissions were fixed by hand. Make the fix permanent:
```bash
cd APP_ROOT/backend
chown -R www:www storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
php artisan storage:link   # only if public/storage is missing
```
Do not run artisan as root after this. Use `sudo -u www php artisan ...`, or files created in `storage/` will be root-owned again.

## 5. Voucher partner backfill (#8, BE-19)

No automatic backfill: the schema has no voucher→partner relation (vouchers only have a category; partners have no voucher or category link), so it can't be derived. Assign manually as below.

After #8, vouchers with `partner_id IS NULL` **cannot be redeemed** (fail closed).
```sql
-- see what needs assigning
SELECT v.id, v.name, v.partner_id FROM vouchers v ORDER BY v.id;
SELECT id, login, name FROM partners;
-- assign explicitly
UPDATE vouchers SET partner_id = <partner_id> WHERE id IN (<ids>);
-- or, only if there is exactly one partner
UPDATE vouchers SET partner_id = (SELECT id FROM partners ORDER BY id LIMIT 1) WHERE partner_id IS NULL;
-- verify: should return 0
SELECT COUNT(*) FROM vouchers WHERE partner_id IS NULL AND deleted = false;
```
Admins can also set it later in Admin → Vouchers → Edit → Partners.

## 6. nginx (UX-12 / FE-09 + security headers)

Audit UX-12: "logged-in / refresh/bookmark → lending (nginx root = marketing)". `/` serves the marketing page, so a logged-in user who refreshes or opens a bookmark lands there.
Fix: keep `/` = lending, but #10 adds `lending/logged-in-redirect.js`, which sends users with a token to the app. SPA paths must fall back to the SPA `index.html`, never to the lending page.

```nginx
server {
    server_name wash.socialsave.cc;
    root APP_ROOT/lending/dist;

    # security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(self), geolocation=(), microphone=()" always;  # camera: manager QR scanner
    # add a Content-Security-Policy once tested in Report-Only mode
    server_tokens off;

    location = / { try_files /index.html =404; }

    # Laravel: API, admin, corporate panel, payments, callbacks
    location ~ ^/(api|dashboard|login|logout|password|partner|payment|callback|ref|storage|up)(/|$) {
        root APP_ROOT/backend/public;
        try_files $uri /index.php?$query_string;
    }
    location ~ \.php$ {
        root APP_ROOT/backend/public;
        fastcgi_pass unix:/tmp/php-cgi-84.sock;   # aaPanel PHP 8.4 socket, adjust
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SPA: every other path → gewash index.html (no fallback to lending)
    location /assets/ { root APP_ROOT/gewash/dist; expires 1y; add_header Cache-Control "public, immutable"; }
    location / {
        root APP_ROOT/gewash/dist;
        try_files $uri /index.html;
        add_header Cache-Control "no-cache";
    }

    location ~ /\.(?!well-known) { deny all; }
}
```
Run `nginx -t` before `nginx -s reload`. Note that `add_header` inside a `location` replaces the server-level headers, so repeat the security headers there or use an include file.

## 7. Build / commit stamp (DEPLOY-01)

The audit couldn't prove which commit was live. After every deploy:
```bash
cd APP_ROOT
C=$(git rev-parse --short HEAD); T=$(date -Iseconds)
printf '{"commit":"%s","built_at":"%s"}\n' "$C" "$T" | tee gewash/dist/version.json lending/dist/version.json backend/public/version.json
```
Check with `curl -s https://wash.socialsave.cc/version.json`. Also log every deploy (date, commit, who) in `docs/MEMORY.md`.

## 8. Smoke test after deploy

- `curl -s https://wash.socialsave.cc/api/promo` returns only title/description/url.
- `curl -si -X POST https://wash.socialsave.cc/api/partner/voucher/check` → 401.
- `curl -si https://wash.socialsave.cc/payment/sandbox` → 404.
- A missing API route returns JSON 404 with no stack trace.
- Register → SMS → verify → set password works in the SPA (needs #9).
- Admin: delete and ban buttons work (now POST/DELETE).
- Manager: QR scan → approve works.

## 9. Rollback

```bash
cd APP_ROOT/backend
php artisan down
# migrations in this release are additive; roll back the 4 from 2026-10-12 only if needed:
php artisan migrate:rollback --step=4 --force   # check `php artisan migrate:status` first
git -C APP_ROOT checkout $(cat /root/autopass-prev-commit.txt)
composer install --no-dev --optimize-autoloader
cp /root/autopass-env-<date>.bak .env   # only if .env changes caused the problem
php artisan optimize:clear && php artisan up
# frontend: rebuild gewash/lending from the previous commit, or restore the dist backups
```
Data note: after rolling back `user_vouchers.used_at`, vouchers used since the deploy will reappear as unused. If that matters, restore the DB dump instead.

## 10. Test payment in production (2026-10-13)

By default `/api/testpay` and `/payment/sandbox` return 404 when `APP_ENV=production`. To let QA test payments on production, set the following in the server `backend/.env`:

```
TEST_PAYMENT_ALLOW_PRODUCTION=true
TEST_PAYMENT_PHONES=+995555000000,+995555000001
```

- Even with the flag on, test payments are open only to admins (role 2) and the numbers in `TEST_PAYMENT_PHONES`. Everyone else gets 403.
- Every existing server check still applies: the order must be a test order (`test_mode`), the one-time access hash and its expiry are checked, the owner must still be an allowed tester, and a payment is credited only once.
- Flitt/TBC webhooks are unchanged: no user auth, and the signature and amount are checked.
- Afterwards run `php artisan config:clear` (or `config:cache`). To turn it off, set `TEST_PAYMENT_ALLOW_PRODUCTION=false`.
