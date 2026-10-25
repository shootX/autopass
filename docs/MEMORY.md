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

## 2026-10-12 — FE-06, FE-07, FE-08, FE-09, FE-10

FE-06: `docs/gewash-frontend-coverage.md` აღწერს `gewash/src/quiet/`-ს, რომელიც რეპოში არ არის. დაემატა მხოლოდ ის alias-ები, რომელთა კომპონენტი უკვე არსებობს: `/home`, `/qr`, `/reviews`, `/referrals`, `/calendar`, `/customer-calendar` (აღარ მიდის `/shop`-ზე), `/profile/cars`, `/profile/cars/new`, `/profile/cars/:id/edit`, მენეჯერის `/manager/calendar|bookings|scanner|statistics|profile|contacts|help|settings|reschedule/:id`. `/profile` კვლავ ProfilePage-ია (ჰაბი), არა CustomerMyData — გადაფარვა არსებულ ეკრანს წაშლიდა. Catch-all აღარ არის redirect; არის `NotFound`.

არ გაკეთდა, რადგან ეკრანი ან API არ არის: `/reset-password`, `/verify-email`, `/terms`, `/bookings*`, `/packages*`, `/my-points/history`, ვაუჩერის/ბილეთის დეტალი, `/shop/:id/checkout`, `/orders*`, `/reviews/new`, `/messages/:id`, `/notifications`, `/help/:slug`, `/support`, settings-ის ქვეგვერდები, `/session-expired`, `/403`, `/500`, `/maintenance`, `/offline`.

FE-07: `editMyCar` / `removeMyCar` `success: false`-ზე შეცდომას აჩვენებს და აღარ გადადის შენახულად. BE-10 (წარმატების ცრუ `success: true`, როცა სტრიქონი არ იცვლება) backend-ია და აქ არ შეცვლილა.

FE-08: `expires_in` ინახება `access_token_expires_at`-ში. `dropExpiredAccessToken` იძახება `main.tsx`-იდან რენდერამდე. ტოკენი კვლავ `localStorage`-შია, რომ განახლებამ სესია არ მოკლას. main-ის `JWT_TTL` ნაგულისხმევი ახლა 43200 წუთია (30 დღე). კლიენტი ამ რიცხვს არ იგონებს — იყენებს პასუხის `expires_in`-ს.

FE-09 / UX-12-ის ნაწილი: `lending/` `/`-ზე, თუ `access_token` არის, ხდება `location.replace` `{APP_BASE}/home`-ზე. ეს მუშაობს მხოლოდ მაშინ, თუ ვებ-სერვერი `/home`-ს gewash-ის `index.html`-ზე უშვებს ისე, როგორც `/auth`-ს. nginx root = marketing ამ რეპოში არ იცვლება. სერვერს არ შევხებივარ.

FE-10: `CustomerMyData` hash `#vehicles`-ზე იძახებს `scrollIntoView`. `#vehicles` არის `MyVehicles`-ის wrapper-ზე.

## 2026-10-12 — MOB-01, MOB-02, MOB-03, MOB-04, MOB-05, MOB-06, MOB-09, MOB-11

MOB-10 არ შეხებია (PR #6). MOB-07 და MOB-08 აუდიტში არ არის.

Onboarding ჩანს მხოლოდ გამოუსვლელ მომხმარებელზე, რომელიც პირდაპირ ავტორიზაციის მისამართზე არ მოსულა. `/privacy` და `/payment-success` იგივე სიაშია, რომ თანხმობის ბმულმა და გადახდის დაბრუნებამ კარუსელი არ გახსნას.

სკანერი: `html5-qrcode`-ის `start` ერთ ეფექტშია. დახურვისას `releaseScanner` იძახებს `stop` და `clear`. კამერის უარზე ჩანს „კამერაზე წვდომა საჭიროა“ და ღილაკი „თავიდან ცდა“.

ვიუპორტი აღარ კეტავს მასშტაბს. lending-ის `.nl input` არის 16px. ქვედა ნავის სიმაღლე არის `88px + env(safe-area-inset-bottom)`. v4 ნავი საკუთარ padding-ს ინარჩუნებს.

## 2026-10-12 — PWA და მკვდარი დამოკიდებულებები

ხატები გენერირდება `gewash/public/brand/app-icon.svg`-დან: `public/icons/icon-192.png`, `icon-512.png`, `icon-maskable-192.png`, `icon-maskable-512.png`. maskable სავსეა `#14482F`-ით და ნიშანი ცენტრის 80%-შია.

სერვის-ვორქერი არის `gewash/public/sw.js`. ქსელი პირველია, ქეში მხოლოდ წარუმატებლობისას. `/api` არ ინახება. `navigator.serviceWorker.register` იძახება მხოლოდ `import.meta.env.PROD`-ზე, რომ dev სერვერი არ დაიქეშოს.

`expo-camera` კოდში არ გამოიყენებოდა (სკანერი არის `html5-qrcode`). `gewash/app.json` მხოლოდ EAS `projectId`-ს ინახავდა.

## 2026-10-12

UX პრ (`cursor/ux-audit-fixes-fc25`) main-ისგან დამოუკიდებელია. ტესტები main-ის vitest ^5.0.3 და jsdom ^29.1.1 ვერსიებზეა.

ავტოგანახლების საწყისი მნიშვნელობაა `AUTO_RENEWAL_DEFAULT = false`. სია პირველია: ფორმა იხსნება მხოლოდ ყიდვის ან რედაქტირების დროს.

`ჭკკვიანი` წყაროში არ მოიძებნა. პრომო ტექსტი API-დანაა. სპლეშს ტაიმერი არ აქვს. ჰედერები განზრახ არ გაერთიანდა.

ლენდინგის გადამისამართება `lending/logged-in-redirect.js`-შია. მუშაობს მხოლოდ მაშინ, თუ სერვერი `/home`-ს SPA-ზე აბრუნებს.

პარტნიორის API გასაღები გვერდზე ნიღბიანად ჩანს. სრული მნიშვნელობა მაინც HTML ატრიბუტშია, რადგან მფლობელმა უნდა ნახოს. მიმდინარე პაროლი ანგარიშის შენახვაზე არასავალდებულოა. სერვერი ითხოვს მას მხოლოდ მაშინ, როცა ახალი პაროლი იწერება.

## 2026-10-12

FE-12 ცხოვრობს `gewash/src/lib/loginPhone.ts`-ში. 9 ციფრი, რომელიც თავად იწყება `995`-ით, ქვეყნის კოდად არ იჭრება. პრეფიქსი იჭრება მხოლოდ მაშინ, როცა ციფრები 9-ზე მეტია.

## 2026-10-12

UX-10. `splashShouldBlock` აბრუნებს false-ს, როცა ენა და როლი მზადაა, ან როცა გასულია 1500 მს. App.tsx ტაიმერი მხოლოდ ამ ზღვარს რთავს; მონაცემების ადრე მოსვლისას სპლეში მაშინვე ქრება.

UX-11. `Header` იღებს `left` / `right` კვანძებს, რომ გვერდის უნიკალური ღილაკები შენარჩუნდეს. `bar` და `labeled` რენდერდება `<header>`-ად, `v4` — `div.v4-hd`-ად, `shell` — არსებული ბურგერის ჰედერი (CustomerCalendar).

## 2026-10-12

UX-05. ფუნქციების სექცია იყენებს `lending/img/top-car-840`-ს, აპის მაკეტი `lending/img/app-home-780`-ს. სურათები უკვე ამ ფაილებზე იყო მიბმული. გლობალური `picture{display:contents}` ზოგ ბრაუზერში ჩანაცვლებულ სურათს არ ხატავდა, ამიტომ ჩარჩო ცარიელი ჩანდა. ახლა picture ბლოკია, მაკეტის სურათი ჩარჩოს ავსებს (`object-fit: cover`, ზემოდან).

UX-09. 1024px-იდან `.ap-side` არის `overflow-y: auto`. `.promo` და `.usr` არის `flex: none`, რომ გრძელი პრომო პროფილს არ დაადოს. ტექსტური შეცდომა SPA-ში არ დევს; main-ზე ის `promos` ცხრილშია და მიგრაცია `2026_10_12_000004_fix_promo_typo` ასწორებს.

## 2026-10-12

UX-12. მაგალითში `root` არის SPA-ის ბილდი, არა `lending/`. `try_files $uri $uri/ /index.html` აბრუნებს `/home`-ს და დანარჩენ კლიენტის მისამართებს SPA-ის ინდექსზე. ფაილი ნიმუშია და არც ერთ სერვერზე არ არის ჩართული.

## 2026-10-12 — Autopass main მდგომარეობა

ყველა აუდიტის PR (#5–#17) main-შია. დეპლოიმდე: სერვერზე დააყენე CORS_ALLOWED_ORIGINS, ყველა არსებულ ვაუჩერს მიანიჭე partner_id (runbook §5), #7 და #9 ერთად უნდა გავიდეს. JWT_TTL=43200 (30 დღე).

## 2026-10-13 — Stage 1 frontend

`docs/API_CONTRACT_STAGE1.md` რეპოში არ დევს. ცოცხალი კონტრაქტია backend-ის `smsJson`: `simulated`/`accepted` არის 200 და `temp_code` 32 hex; `failed` არის 422 უკოდოდ; `unknown` არის 503 და `temp_code` რჩება. `set_password` იღებს მხოლოდ `{ setup_token, password }`. პარტნიორის login აბრუნებს `must_change_password`. სანამ პაროლი არ შეიცვლება, სხვა მარშრუტი 403-ს აბრუნებს ამ დროშით.

გაზიარებული ტექსტი არის `gewash/src/lib/smsDelivery.ts`. კომპონენტი `SmsStatusNotice` ენას Redux-იდან იღებს. voucher-checker იმავე მოდულს იყენებს ინგლისურად, რადგან მისი ეკრანი ინგლისურია. `accepted` ტექსტი პირდაპირ ამბობს, რომ მიღება მიტანა არ არის.

როლი სესიაში ინახება `sessionStorage` გასაღებით `autopass.session_role`, მხოლოდ `/me`-ის შემდეგ. 401 ამას შლის ტოკენთან და `autopass.me`-სთან ერთად, სანამ მისამართს აირჩევს. სრული `/me` პასუხი არ ინახება.

voucher-checker-ში `useVoucher`/`usePath` სახელები შეიცვალა (`confirmVoucherUse`, `voucherUsePath`), რომ eslint ჰუკებად არ ჩაეთვალა. იგივე დღეს დარჩენილი მოდელების შეუსაბამობაც გასწორდა რეალური ტიპებით: პაკეტს აქვს `id` და `car_id`, ჯავშანს `carId`, მანქანა ინახება `{ model: { name, type, brand } }` ფორმით. `buttonVariants` ცალკე ფაილშია, რომ react-refresh კომპონენტის ფაილში კონსტანტას არ ხედავდეს. `any`, `eslint-disable` და `@ts-ignore` არ დამატებულა. gewash `build:check` და lint, და voucher-checker-ის `tsc` და lint, ნულზეა.

## 2026-10-13 — Test payment flag

`App\Support\TestPayments::available()`: production-ზე false, თუ `security.test_payment_allow_production` (env `TEST_PAYMENT_ALLOW_PRODUCTION`) არ არის true. `allowed()` ამოწმებს ადმინს და `TEST_PAYMENT_PHONES`-ს. sandbox-ის დადასტურებისას ხელახლა მოწმდება, რომ გადახდის მფლობელი ჯერ კიდევ დაშვებულია. ტესტებში env=production რთავს CSRF-ს, ამიტომ ტესტის ფაილში `PreventRequestForgery` გამორთულია.
### 2026-10-14

- Stage 1 (security + SMSOffice + frontend contracts) is on `main` via PR #19 → #18 → `main`. Tests: backend 69/69, gewash 83/83, voucher-checker 9/9; gewash `build:check` and lint clean.
- Backend Pest tests read `backend/.env`; on a fresh checkout copy `.env.example` to `.env` locally before `php artisan test` (otherwise 67 tests end as warnings).
- Nothing deployed. Test payment in production stays off until the separate flag PR is reviewed and merged.

## 2026-10-14 — Stage 2 (1–4)

`users.pending_email`: `/email/set` ახალ მისამართს `pending_email`-ში ინახავს, ხოლო `/email/verify` ამ მისამართს `email`-ში გადაიტანს. ban-ს ამოწმებს EnsureApiSession, EnsurePartnerReady, CorporatePanel და AdminMiddleware; ადმინის ban ასევე ზრდის `token_version`-ს. ვებ-შესვლა ban-ს ამოწმებს `LoginController::credentials()`-ში. პარტნიორის/კომპანიის ban-ის ადმინ UI ჯერ არ არის, მხოლოდ სვეტია. ადმინის რედაქტირებიდან (clients/partners edit) ტელეფონისა და ელფოსტის შეცვლა შენარჩუნებულია, როგორც ადმინის ფუნქცია.

## 2026-10-14 — Car ids and voucher SMS dedupe
- car_models.id is always local. CarAPI ids live in external_model_id / external_brand_id with source='carapi'. Never look up a client model_id unless it comes with a source.
- Voucher check SMS: dedupe key voucher-check:{user_voucher_id}:{user_id}; within the window the open SmsTemp public_id is returned (deduplicated: true).

## 2026-10-14 — Mass assignment policy
- Privileged columns (role, ban, points, token/session versions, credentials, api_token, phone_verified_at, referral ids) are NOT in $fillable. Set them with forceFill/forceCreate in trusted code only. DatabaseSeeder runs Model::unguard().
- All points mutations (vouchers, tickets, packages, TBC settle) run in DB::transaction with lockForUpdate on the user row.

## 2026-10-14 — ცარიელი გვერდები

`Routes`-ის შვილი მხოლოდ `Route` ან `React.Fragment` შეიძლება იყოს. `<PublicAuthRoutes />` კომპონენტია, ამიტომ react-router 7 `invariant`-ს ისვრის და აპი ცარიელი რჩება. `{PublicAuthRoutes()}` აბრუნებს ფრაგმენტს, რომელსაც როუტერი შლის შვილებად. შესვლის მისამართი არის `/auth`, არა `/login`.

შემომტანი: `650defce35952d263f2615385a15da0c3b4fa574` (2026-10-12, Cursor Agent `<cursoragent@cursor.com>`, co-author Shota Chkhvirkia). PR [#6](https://github.com/shootX/autopass/pull/6) `cursor/fe-11-manager-routing-9883`, merge `e96ea3c`.

შემოწმება ამ hotfix-ზე: gewash lint 0, vitest 85/85 (24 ფაილი), `build:check` 0. დეპლოი არ ყოფილა.

## 2026-10-14 — Revocation + audit helpers
- App\Support\SessionRevoker::{user,partner,corporate} is the one way to kill live credentials (version bump + DB sessions for users).
- App\Support\AdminAudit::record() writes admin_audit_logs; old/new values are masked (email first char + domain, phone last 2 digits).

## 2026-10-14 — Stage 3, Codex (`codex/stage3-product-fixes`)

- Implemented locally from `f751751`: AddCar catalog/source/year contract and real car id; EditCar direct route; generation-year provenance; owned-car/branch/service/hours booking validation and atomic writes; duplicate exact slots; password-reset session revocation; pending package order reuse and one benefit per paid payment; expired/exhausted replacement with retained history; consistent appointment statuses; locale keys and npm 11 clean installs.
- Verification: backend SQLite 131/131 (695 assertions), gewash 92/92, voucher-checker 9/9; both builds and lints passed; all six locale files cover 373 referenced keys; i18n regression passed; clean npm 11 installs passed. See `docs/STAGE3_QA.md` for commands and limits.
- Native PostgreSQL 16/database-cache concurrency CI prepared, NOT run. Local PostgreSQL-WASM regression tests passed, but concurrency runs were unstable; no native database concurrency certification.
- Browser visual/E2E checks and authenticated CarAPI, SMTP/SMS/payment delivery still require a working browser/provider environment. Test payment remains enabled only as previously authorized; SMS stays mocked.
- GitHub branch creation was rejected with HTTP 403 `Resource not accessible by integration`; no remote branch, PR, main merge or deployment. Export contains the local commits and test evidence. GitHub write access is required to publish.

## 2026-10-14 — Stage 3 applied and re-verified
- Patch applied with `git am` on `f751751` (main had not moved) on branch `codex/stage3-product-fixes`. Commit SHAs differ from the local export (`0efc820`) only because of committer metadata; content is identical to the patch.
- Re-verified on the box with Node 24.19.0 / npm 11.9.0: backend SQLite 131/131 (695 assertions); gewash npm ci, test 92/92, build:check, lint, i18n:check (373 keys, 0 missing), i18n:test all passed; voucher-checker npm ci, test 9/9, build, lint passed.
- Native PostgreSQL 17 (local, isolated `autopass_test` DB, box-only) with `CACHE_STORE=database vendor/bin/pest -c phpunit.pgsql.xml`: 22/22 (82 assertions), 8 consecutive runs, both race tests passed. PostgreSQL 16 CI job still not run; production DB not touched.
- Still open: browser visual/E2E, live CarAPI, SMTP/SMS/payment sandbox delivery, migration on a copy of the target DB. No merge, no deploy.

## 2026-10-14 — Controlled test launch readiness (Codex)

- PR #27 (`3718736`) contains all Stage 3 code. GitHub Product regression checks completed successfully, including native PostgreSQL 16/database-cache checks; the earlier WASM uncertainty is not the current native CI result.
- Additional local hardening: authentication responses/tokens are no longer logged; a translated connection-failure alert replaces a silent login catch; the service worker caches public assets only, respects private/no-store responses, and removes its old application cache without deleting unrelated caches. Document navigation and authenticated routes pass through the network. Uncached page navigation will require connectivity.
- Example deployment endpoints now point to wash.socialsave.cc and car.socialsave.cc instead of a temporary tunnel or empty values; authentication bypass remains false.
- Verification: gewash 103/103 (11 new regressions), build:check/lint/i18n checks passed; 374 referenced locale keys, 0 missing in six dictionaries. Stage 3 backend 131/131, voucher 9/9 and PostgreSQL 22/22 remain prior CI evidence; backend business code and voucher application code were not changed in this follow-up.
- Browser observed the current landing, customer login, recovery, registration, public privacy text and admin login. No credentials were submitted; private panels and full new-version workflows were not tested. Landing help redirects anonymous visitors to login; privacy is English-only. These are recorded polish tasks for the closed test.
- No merge, target server change or deployment. Target database copy, server credentials/configuration, actual CarAPI key and mail delivery are unavailable here. See docs/TEST_LAUNCH_READINESS.md for the exact controlled launch gates. Follow-up patch must be uploaded before deploying.

## 2026-10-15 — Audit fixes: rules to keep
- Never ship known passwords: RetireDefaultPasswords::KNOWN lists the published ones; staff with `password_reset_required` can only get back in via the SMS reset.
- Package wash approval without a booking needs the QR (`qr_code`) or a checkqr `approve_token` (cache `package-approve:{sha256}`), never a bare id.
- Corporate API keys: only `api_token_hash` is stored; assigning `api_token` on the model hashes it, the plain key exists only in memory and in a one-time flash (`corporate_api_token`).
- OTP/email codes: `SmsChallengeService::codeHash()` (HMAC with APP_KEY + challenge id). Rotating APP_KEY invalidates open codes only.
- Every admin POST/PUT/DELETE is audited by middleware; add explicit `AdminAudit::record` only when field-level old/new values matter.

## 2026-10-15 — Frontend audit fixes

`security/audit-fixes` (`16845d1`, PR #28) შევიდა `security/frontend-audit-fixes`-ში merge commit-ით.

JWT httpOnly cookie არ არის: `access_token` რჩება მეხსიერებაში და გვერდის განახლება სესიას წყვეტს. `POST /api/refresh` შავ სიაში აგდებს ძველ ტოკენს და უნდა გამოიძახოს `expires_in`-მდე (წამები, ნაგულისხმევი 86400). წარუმატებელი refresh არის logout. `localStorage` გასაღებები `access_token` და `access_token_expires_at` იშლება. voucher-checker-ის `voucher_checker_access_token` იგივე წესით მეხსიერებაშია.

პაკეტის approve ცარიელი ტანით 403-ია. სხეული არის `qr_code` (დასკანერებული) და/ან `approve_token` (`GET /api/checkqr`). checkqr აღარ აბრუნებს ტელეფონს.

`VITE_BYPASS_AUTH` და `VITE_MOCK_VOUCHER_CHECK` production ბილდს აგდებს და runtime-ზეც გამორთულია. `VITE_TEST_PAYMENTS=true` რთავს მხოლოდ შეტყობინებას `/payment/sandbox`-ზე. ბარათი რჩება სერვერის გვერდზე. CSP არის `docs/CSP.md`. nginx არ შეცვლილა.

რეგისტრაციის captcha ამოცანიდან ამოღებულია. ვიჯეტი არ დაემატა.

`npm audit --omit=dev` სუფთაა gewash-სა და voucher-checker-ში (`maplibre-gl` 6.14.0, `react-router-dom` 7.18.4, vite 7.3.7 / 8.3.4). `npm ci` გადის npm 10.9.9-ზეც და 11.17.0-ზეც. main-ში შერწყმა და დეპლოი არ ყოფილა.

## 2026-10-15 — QA fixes on PR #28
- Spendable points for partner goods = `points - test_points` (`User::realPoints()`); `test_points` is clamped to `points` on save.
- One-time grants (approve_token, refresh) are consumed under `Cache::lock`; a busy lock answers 409, never a second consume.
- Refreshed JWTs carry `sess` (first login time); `security.session_max_minutes` bounds the whole chain.
- `AdminAudit::record(..., adminId:)` — pass it explicitly from API (JWT) routes; the default guard is web.

## 2026-10-15 — Frontend for 104dfe8

`security/audit-fixes` (`104dfe8`) შევიდა `security/frontend-audit-fixes`-ში merge commit-ით.

`POST /api/refresh` 409-ზე ერთხელ მეორდება იმავე ტოკენით და სესიას არ წყვეტს. მეორე 409-იც ტოვებს სესიას. 401 წყვეტს. `approve_token` იგზავნება ერთხელ და იშლება; უარყოფა (403, 409, 422) აბრუნებს კამერაზე ახალი სკანისთვის.

`GET /me`-ის `test_points` არის სანდბოქსის ქულა. ხარჯვადი რიცხვი არის `points - test_points`. ველი რომ არ მოვიდეს, ჩანს მთელი `points`. main-ში შერწყმა და დეპლოი არ ყოფილა.

## 2026-10-15 — QA round 2 on PR #28
- Package approve = checkqr token only. Tests use `approveTokenFor($manager, $pkgId)` (tests/Pest.php).
- Auth SMS goes through `SendSms` (encrypted queued job). Tests that read codes from MockSmsGateway use `Queue::fakeExcept([SendSms::class])`; plain `Queue::fake()` swallows the SMS.
- Refresh tokens: table `refresh_tokens` (sha256 only), family = JWT claim `sid`, session start = `sess`. JSON test requests need `->withCredentials()` to send cookies.
- Laravel's CORS with one allowed origin always returns that origin (not the caller's); assert "not echoed", not "missing".
- Query builder has no `whereKey` (it becomes `where key = …`); use `where('id', …)` on `DB::table`.

## 2026-10-15 — Frontend for f45eca0

`security/audit-fixes` (`f45eca0`) შევიდა `security/frontend-audit-fixes`-ში merge commit-ით. კონტრაქტი არის `docs/auth-contract.md`.

სესია გვერდის განახლებასა და ახალ ტაბზე ბრუნდება `POST /api/refresh`-ით (`credentials: 'include'`, Authorization-ის გარეშე). `ap_refresh` არის HttpOnly და JS-ს არ ეჭირება. `access_token` რჩება მხოლოდ მეხსიერებაში. სანამ refresh არ დასრულდება, დაცული მარშრუტი შესვლაზე არ გადადის. 401 ხსნის შესვლას. 409 ერთხელ მეორდება. ქსელის შეცდომა და მეორე 409 სესიას არ წყვეტს. პარალელური გამოძახება ერთია. ძველი bearer refresh აღარ იგზავნება.

`POST /api/login`, `/api/register/set_password`, `/api/refresh` და `/api/logout` აგზავნის cookie-ს. გამოსვლა ჯერ `/api/logout`-ს იძახებს.

პაკეტის approve არის მხოლოდ `{approve_token}`. 403 აჩვენებს შეცდომას და სკანერს ხსნის. 409 მხოლოდ სკანერს ხსნის.

`/register`, `/change_password` და `/change_phone` წარმატებაა `delivery` `accepted` ან `simulated`. სატესტო ტექსტი production ბილდში არ ჩანს. captcha ამოცანიდან ამოღებულია. main-ში შერწყმა და დეპლოი არ ყოფილა.

## 2026-10-15 — QA round 3 on PR #28
- Refresh reuse inside the grace window is a tab race, not theft: a fresh token in the same family; the first use alone sets `used_at`.
- Logout that must work without a valid JWT lives under the cookie Path: `/api/refresh/logout`.
- `User::forceCreate` in /register can lose a race: catch `UniqueConstraintViolationException` and continue with the existing row.

## 2026-10-15 — Frontend for 806db9a

`security/audit-fixes` (`806db9a`) შევიდა `security/frontend-audit-fixes`-ში merge commit-ით.

გამოსვლა არის `POST /api/refresh/logout` (`credentials: 'include'`). Bearer ემატება მხოლოდ მაშინ, როცა `access_token` მეხსიერებაშია. პასუხის შემდეგ ტოკენი იშლება და იხსნება `/auth`. მოთხოვნის წარუმატებლობაც იგივე ლოკალურ გამოსვლას აკეთებს. `/api/logout` აღარ იძახება.

`POST /api/refresh` უცვლელია: 409 ერთხელ მეორდება, ქსელის შეცდომა `expireSession`-ს არ იძახებს. 30 წამიანი ტაბების grace კლიენტში ცალკე არ იმუშავა. login, `set_password` და approve უცვლელია. main-ში შერწყმა და დეპლოი არ ყოფილა.

## 2026-10-16 — ნავი და ოქრო

`41a3eae` ვიზუალი დაჯდა `aac2b78`-ზე შტო `cursor/visual-redesign-port-e825`-ში. ძველი `cursor/visual-redesign-a88c` არ შერწყმულა.

ტოკენის სახელები (`--ap-forest`, `--ap-lime`) დარჩა. მნიშვნელობებია ნავი `#12284b` და ოქრო `#e4b23c`, ტილო `#f6f3ec`, ტექსტი `#14233c`. `gewash/src/styles/redesign.scss` დესკტოპის შესვლას ორ სვეტად ხატავს. ადმინის სვეტი ნავია, აქტიური პუნქტი ოქრო. voucher-checker-ის შესვლაც იგივე სპლიტია. ფონტი კვლავ თვითჰოსტირებული Plus Jakarta Sans და Noto Sans Georgian-ია.

არ გადმოვიდა preview fixtures, hook-ების სატესტო მონაცემები და ავტორიზაციის ლოგიკა. i18n გასაღებები და მარშრუტები არ შეცვლილა. დეპლოი არ ყოფილა.

## 2026-10-16 — QA კონტრასტი და voucher სპლიტი

ოქროს ტექსტი ღია ფონზე არის `#7a560c` (`--ap-gold-text`, `$goldText`). `#e4b23c` რჩება შევსებას, ჩარჩოს და ტექსტს ნავზე (`#12284b`). voucher-checker-ის გამოსვლა, აქტიური ნაბიჯი და კონტური ღილაკი იგივე ტექსტის ტოკენს იყენებს. კონტრასტის ტესტია `gewash/src/lib/paletteContrast.test.ts`.

დესკტოპის შესვლაზე „დაგავიწყდათ პაროლი?“ ნავია, `+995` კი `#14233c`. მობილურზე ბმული ოქროა მუქ პანელზე, პრეფიქსი `#97a29a` მუქ ველზე. voucher-checker-ის `.auth-wrapper` არის `border-box`, სპლიტი `100dvh`.

`#5d6e86` ნავის სექციის სათაურზე და cream ჩიპზე კვლავ 4.5-ზე ქვემოთაა. ეს ოქრო არ არის და ამ ფიქსში არ შეცვლილა. დეპლოი არ ყოფილა.

## 2026-10-16 — Route names must be unique
- A duplicated `->name()` breaks `php artisan route:cache` (deploy) and silently makes `route('name')` point at the last route registered. `RouteNamesTest` guards it; give every new route its own name.

## 2026-10-25 — geoapi.ge is the car catalog

CarAPI client (`CarCatalog`) speaks the geoapi.ge v1 shape: Bearer `geoapi_sk_`, `data` + `meta.total`, ids as strings (cast to int), up to 1000 rows a page. Free plan is 1000 requests a month and 15 a minute, so every list is cached and photos are kept on the `local` disk under `carapi-photos/`.

Photo `photo_url` and `/models/{id}/photo` need the secret key. The app never gets the upstream link, only `/api/cars/models/{id}/photo?generation=`. The proxy never sends the key to another host. `UserCar::imageUrl()` = saved photo path, else the model/brand/body-type image. `body_type` is stored but does not change package pricing.
