# მეხსიერება — autopass

ბოლო განახლება: 2026-10-10. `main` არის `dfe84dc`. წყარო: `git log`, PR #1–#4, `memory.md`, `fix.md`.

## მიმდინარე მდგომარეობა

autopass `main`-ზეა. კლიენტის აპი (`gewash`) forest/lime პალიტრაზე და v4 მობილურ ეკრანებზეა. ადმინი და voucher-checker იგივე ბრენდზეა. მარკეტინგის გვერდი `lending/`-შია და ჯერ დეპლოი ამ რეპოდან არ ჩანს. კორპორატიული ავტოპარკი და TBC checkout დამატებულია 2026-10-10.

ღიაა: draft PR #1 (ნავი/ოქრო), `tsc -b`, `fix.md`-ის ფული / ანგარიში / ბიზნეს ლოგიკა, ინტეგრაციის ხარვეზები, landing-ის `#` ბმულები. სია არის `docs/TASKS.md`. `in progress` დავალება არ არის.

`memory.md` და `fix.md` რეპოს ფესვში რჩება. ისინი 2026-10-07 იმპორტში შევიდა. ეს ფაილი მათ არ ცვლის.

## გადაწყვეტილებები

### 2026-10-07

- ცოცხალი კოდი `wash.socialsave.cc`-დან შემოვიდა ერთ commit-ად (`d14001c`). ავტორი Shota Chkhvirkia (`shootX`).
- ბრენდი არის forest `#14482F` და lime `#B5DD3A`, არა Geocar-ის navy/gold (`eaac4d2`, PR #2). ლოგო, თვითჰოსტირებული Plus Jakarta Sans და Noto Sans Georgian, სტატიკური დემო რუკა. ცოცხალი PMTiles რუკა მხოლოდ `VITE_LIVE_MAP=true`.
- ფასი და რეიტინგი მაკეტიდან არ იწერება. პაკეტის რიცხვი API-დან მოდის (შემდეგ PR #3-შიც იგივე წესია).
- ბაზის კონფიგი არის PostgreSQL (`DB_CONNECTION=pgsql`, ბაზა `autopass`). `memory.md` აღწერს ლოკალურ აწეულ სქემას. ახალი მიგრაცია PostgreSQL-ზე იწერება, `enum()->change()` არ გამოიყენება.
- ენის კოდი არის `ka`. ძველი `ge` იკითხება როგორც `ka`.

### 2026-10-09

- კლიენტის მობილური UI არის v4: თეთრი ეკრანი, ink პანელი, lime მოქმედება (PR #3, `dd54748`). დესკტოპი ინარჩუნებს sidebar-ს `1024px`-დან.
- Onboarding ერთხელ ჩანს, გასაღები `autopass.onboarding.v4`.
- მარკეტინგის გვერდი სტატიკურია, `lending/`-ში, ზღვარი `760px` (PR #4, `ece763f`). აპის მისამართი ერთადერთი მნიშვნელობაა: `window.AUTOPASS_APP_BASE`.
- სტორის ბეჯი `/register`-ზე მიდის, სანამ აპი სტორში არ არის.

### 2026-10-10

- ჯავშანი ინახება არჩეულ დღეს. ნაგულისხმევი ენა და ადმინის სიები QA-ს ტექსტს დაემთხვა (`dfe84dc`).
- კორპორატიული კლიენტი მართავს ავტოპარკს `/partner` პანელიდან და `/api/corporate/vehicles`-დან.
- ბარათის დაბრუნება TBC checkout-ზეა. წარმატების გვერდი არის `{APP_URL}/payment-success`. Flitt webhook კოდში რჩება (`/callback/flitt/payment`).
- PR #1 (ნავი `#12284b`, ოქრო `#e4b23c`) `main`-ის დიზაინი არ არის. ის draft-ად ღიაა. მოქმედი დიზაინი არის `docs/DESIGN.md`.

## ბაგები

თარიღი არის რეპოში გამოჩენის თარიღი. `fix.md` ცალკე fix-commit-ს არ უკავშირდება. ის `d14001c`-თან ერთად შემოვიდა.

### 2026-10-07 — დახურული (`fix.md`, „გაკეთებულია“)

- დადასტურების push აღარ იწყება სათაურით „ჩანაწერი უარყოფილია“. დადასტურებას და გადატანას საკუთარი ტექსტი აქვს, `ka` / `ru` / `en`.
- თარგმანის ჩატვირთვა 8 წამში ჩერდება. ცუდი პასუხი არსებულ ტექსტს არ შლის.
- ჯავშნის push-ში ცარიელი სახელის ნაცვლად ჩანს ტელეფონი.
- მენიუ ელოდება როლს, რომ მენეჯერი კლიენტის ეკრანზე ერთი წამით არ მოხვდეს.
- ჩანაწერის სტატუსს და QR-ს მხოლოდ იმ ფილიალის მენეჯერი ცვლის. QR-ს მხოლოდ მენეჯერი ხსნის.
- წაშლილია გამოუყენებელი ასლები `src/pages`, `src/Layouts`, `src/Calendars`, `src/ui`, `src/Skeletons` და ფესვის დუბლიკატები.

გამოსწორება: ეს უკვე იმპორტირებულ კოდშია. რეგისტრაციას სახელი და მეილი განზრახ არ დაემატა (`fix.md`: ეკრანის დიზაინი).

### 2026-10-07 — ღიაა, გამოსწორება არ აქვს

`fix.md`-ის სამი ბლოკი: ფული, ანგარიში, ბიზნეს ლოგიკა. დეტალი და დავალების ID: `docs/TASKS.md` T-03, T-04, T-05.

### 2026-10-09 — ნაწილობრივ

- PR #3-ის follow-up: რუკის ბარათზე აკლდა `Branches.call` და ჩანდა ტელეფონი. ახლანდელ `gewash`-ში არც ეს გასაღები და არც `555 112 233` აღარ არის. დანარჩენი integration ხარვეზი T-07-შია.
- PR #3 წერდა, რომ `/payment/return` მიდის `https://app.geocar.ge/`. 2026-10-10-ის `TbcCheckoutController::resultUrl` აბრუნებს `{APP_URL}/payment-success`-ს. Push-ის ბმულები `app.geocar.ge`-ზე კვლავ დარჩა (T-09).

### 2026-10-10

- ჯავშანი არჩეულ დღეს არ ინახებოდა. გამოსწორება: `dfe84dc` (`useCreateAppointment` და `AppointmentsController`).
- ქართული ტექსტი და ადმინის სიები QA-ს არ ემთხვეოდა. გამოსწორება: იგივე commit, `backend/public/lang`, `gewash/ka.json`, admin Blade.
- `fix.md` წერდა ხელმოუწერელ Flitt callback-ს, `Package::find()->first()`-ს, `order_id = time()`-ს და `public/flitt.log`-ს. `dfe84dc`-ის შემდეგ callback ამოწმებს ხელმოწერას (`FlittCheckout::valid`), ჩარიცხვა ერთ `TbcPayment`-ზე ერთხელ ხდება (`grantNow`), პაკეტი იძებნება ტიპით და რეცხვის რაოდენობით, იდენტიფიკატორი შემთხვევითია. `flitt.log` აღარ იწერება.

ღიაა: `testpay`-ის `time()` იდენტიფიკატორი, პაკეტის წაშლა და მენეჯერის დადასტურება, ვაუჩერის ყიდვა ქულამდე, ცარიელი rectoken-განახლება (T-03). ასევე `tsc -b` (T-02), სტატუს `3` (T-06), კალენდრის ტაბი `/shop`-ზე (T-08).

## ცვლილებები

### 2026-10-07

- `d14001c` — საწყისი იმპორტი.
- `eaac4d2` — autopass rebrand: კლიენტის აპი, voucher checker, ადმინი. PR #2.

### 2026-10-09

- `dd54748` — v4 მობილური რედიზაინი. PR #3.
- `ece763f` — `lending/` მარკეტინგის გვერდი. PR #4.

### 2026-10-10

- `dfe84dc` — ქართული ტექსტი, ჯავშნის დღე, კორპორატიული ავტოპარკი, TBC checkout. 109 ფაილი.
- დაემატა `docs/PRD.md`, `docs/ARCHITECTURE.md`, `docs/RULES.md`, `docs/DESIGN.md`, `docs/TASKS.md`, `docs/MEMORY.md`. `AGENTS.md` და `.cursor/rules/project-docs.mdc` ავალდებულებს agent-ს, წაიკითხოს MEMORY, TASKS და RULES და დავალების შემდეგ იმავე PR-ში განაახლოს TASKS და MEMORY. აპის კოდი არ შეცვლილა.
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

## 2026-10-12 (UX/deploy)
- Promo text lives in the DB; content fixes are done with idempotent data migrations, never by editing the server by hand.
- Corporate panel `saveAccount`: `current_password` is `required_with:password`.
- Merge order and deploy steps: `docs/DEPLOY_RUNBOOK.md`. Frontend PRs #9–#12 conflict with #6 on `gewash/package.json` (vitest) and `App.tsx`, so rebase each one after the previous merge.

## 2026-10-12 (merge prep)
- JWT TTL default is 30 days (43200 min) in `config/jwt.php` and `.env.example`.
- Voucher `partner_id` backfill can't be automated: there is no voucher→partner/company relation in the data (no company_id, creator or category link). Shota/admin must assign it manually (runbook §5).

## 2026-10-12 — FE-11 / MOB-10

მენეჯერის როლი ცნობილია მხოლოდ `GET /api/me`-ის შემდეგ (`users.role`: `1` მენეჯერი, `0` მომხმარებელი). ტოკენის არსებობისას სესია იწყება `pending`-ით და `AppRoutes` არ ხატავს მარშრუტებს — splash ჩანს, სანამ `/me` არ დასრულდება. მენეჯერის გვერდები `CarCheckRoute`-ის გარეთაა; მანქანის გარეშე მომხმარებელი კვლავ მიდის `/add-car`. ლოგინის შემდეგ მენეჯერი მიდის `/manager-qr-page`. `401` შლის `access_token`-ს და ხსნის `/auth`. ქსელის სხვა შეცდომა მარშრუტს არ ირჩევს (`session = error`).

## 2026-10-12 — set_password, FE-03, FE-04

`POST /api/register/set_password` ითხოვს `{ temp_code, code, password }`. `code` არის იგივე 6-ნიშნა SMS, რომელიც მომხმარებელმა შეიყვანა `/register/verify`-ზე (15 წუთი, ერთჯერადი — backend PR #7). კოდი ინახება მხოლოდ რეგისტრაციის კომპონენტის state-ში, არა `localStorage`-ში. გვერდის განახლება ამ ეტაპზე კოდს კარგავს და დადასტურება თავიდან სჭირდება.

რეგისტრაციის პასუხიდან იკითხება მხოლოდ `temp_code`. `sms_code` არ გამოიყენება.

FE-03: `customFetch` `401`-ზე, თუ მოთხოვნას ჰქონდა ნამდვილი `Authorization: Bearer`, შლის ტოკენს და გადადის `/auth`-ზე. ლოგინის `401` (ტოკენის გარეშე) სესიას არ ეხება.

FE-04: რეფერალის IP არის `{VITE_API_URL}/ref/ip` (`GET /api/ref/ip`). `backend/resources/views/referral/redirect.blade.php` კვლავ იძახებს `back.geocar.ge`-ს — ეს Blade-ია, ამ PR-ში არ შეცვლილა.

`origin/main`-ზე (PR #7 შერწყმულია) `set_password` ვალიდაცია არის `temp_code` required integer, `code` required digits:6, `password` required. Frontend-ის სხეული იგივე ველებს აგზავნის.
