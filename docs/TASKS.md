# დავალებები — autopass

განახლების თარიღი: 2026-10-10. წყარო: GitHub PR #1, #3, #4, `fix.md`, `docs/gewash-frontend-integration.md`, `docs/gewash-frontend-coverage.md`, `lending/README.md`, კოდი. `TODO` / `FIXME` კომენტარი `*.ts`, `*.tsx`, `*.php`-ში არ მოიძებნა (მხოლოდ placeholder `XXXX`).

**წესი:** ერთი agent ერთ დროს ერთ დავალებას იღებს. `in progress` ერთზე მეტი არ არის. დავალების დასრულებისას სტატუსი და მფლობელი იცვლება იმავე PR-ში, და `docs/MEMORY.md` იღებს თარიღიან ჩანაწერს.

## მთვლელები

| | რაოდენობა |
|---|---|
| to do | 10 |
| in progress | 0 |
| done | 4 |

## სია

| ID | დავალება | სტატუსი | მფლობელი |
|---|---|---|---|
| T-01 | PR [#1](https://github.com/shootX/autopass/pull/1) კვლავ **draft** და ღიაა (`cursor/visual-redesign-a88c`, „ვიზუალური რედიზაინი: ნავი და ოქრო“, `#12284b` / `#e4b23c`). `main`-ზე ეს მიმართულება ჩანაცვლებულია forest/lime-ით (#2) და v4-ით (#3). გადაწყვეტილება: დაიხუროს ან თავიდან გაივლოს, რომ ძველი პალიტრა `main`-ში არ დაბრუნდეს. | to do | shootX |
| T-02 | `gewash`-ის `npm run build:check` (`tsc -b`). PR #1 (2026-10-06) წერს, რომ ბრძანება ვარდება ძველ ტიპის შეცდომებზე (გამოუყენებელი იმპორტი, `ReactNativeWebView` და სხვა), `npm run build` კი გადის. ამ გარემოში `tsc` თავიდან არ გაშვებულა: `gewash/node_modules` არ არის. კოდში ახლაც ჩანს: `gewash/src/vite-env.d.ts` მხოლოდ Vite-ს და SVGR-ს ამატებს. `Window.ReactNativeWebView` გამოცხადებული არ არის, მაგრამ გამოიყენება `Authentication.tsx`, `BranchInfoPanel.tsx`, `BranchMap.tsx`, `CustomerCalendar.tsx`-ში. `any` დარჩა `fetchUserData.ts`, `useActivatePackage.ts`, `useCreateAppointment.ts`, `useLoadAppointmentsFromBackend.ts`, `useFetchCars.ts`, `fetchFilteredBranches.ts`, `useManagerActions.ts`, `ReferralsInfo.tsx`, `Registration.tsx`, `EditCar.tsx`, `EmailChanging.tsx`, `SettingsPage.tsx`, `ManagerCarwashStatistics.tsx`, `BranchInfoPanel.tsx`, `CustomerCalendar.tsx`. | to do | — |
| T-03 | ფული. `fix.md`-ის „ფული — ღიაა“ ნაწილი 2026-10-10-ის შემდეგ ნაწილობრივ მოძველდა. კოდში უკვეა: Flitt callback ამოწმებს ხელმოწერას (`FlittCheckout::valid`), იგივე `TbcPayment` მეორედ არ ირიცხება (`SettleTbcPayment::grantNow`), პაკეტი იძებნება `car_type` და `count_washes`-ით, `merchant_payment_id` არის `P` + 16 სიმბოლო. `public/flitt.log` აღარ იწერება. კვლავ ღიაა: `PaymentsController::testpay` იყენებს `order_id => time()`. `removePackage` მფლობელს არ ამოწმებს. `managerApprove` მხოლოდ როლს ამოწმებს. `buyVoucher` ვაუჩერს ქმნის ქულის ჩამოჭრამდე. Flitt secret-ის ნაგულისხმევი `config/services.php`-ში არის `test`. `CheckUserPackages` ვადაგასულ `rectoken` პაკეტზე ტოვებს ცარიელ კომენტარს „Попытка продлить“ და შემდეგ QR-ს თავიდან წერს. `PushService` კვლავ წერს `push.log`-ს. | to do | — |
| T-04 | ანგარიში, `fix.md` „ანგარიში — ღიაა“: საერთო დროებითი პაროლი, `/register/verify` ტოკენს პაროლამდე გასცემს, SMS კოდი არ იწვება, აღდგენის კოდი პასუხში ბრუნდება, ტელეფონის კოდი მომხმარებელზე არ არის მიბმული, ბანი ლოგინზე არ მოქმედებს, წაშლა ნომერს `-del`-ს უმატებს და ტოკენს ტოვებს, ზედმეტი ველები `/me`-ზე, `car_id` და `$request->all()`, პარტნიორის ვაუჩერის შემოწმება, JWT `localStorage`-ში, `VITE_BYPASS_AUTH`, rate limit არ არის, CORS `*`. | to do | — |
| T-05 | ბიზნეს ლოგიკა, `fix.md`: ერთ დროს ერთი ჯავშანი, ხანგრძლივობა და სამუშაო საათი არ ითვლება. სტატისტიკა `approved = true`-ს ითვლის, სტატუსი კი 1–5-ია. რეფერალის კოდი ტელეფონის MD5-ის 5 სიმბოლოა და ბონუსი არ ირიცხება. რეფერალის ლინკი Telegramზე მიდის. გამოხმაურება რეცხვის გარეშეც იწერება. პაკეტის QR ყოველ გახსნაზე თავიდან გენერირდება. ლოკალურ რეჟიმში SMS კოდი პასუხშია. ადმინის ბანი, წაშლა და გამოსვლა GET-ია. | to do | — |
| T-06 | სტატუსი `3` ორ მნიშვნელობას ნიშნავს. `AppointmentsController::setStatus` სტატუს `3`-ზე აგზავნის გადატანის push-ს. ადმინის სია და `WashReportQuery::statusLabel` `3`-ს „დასრულებული“-ს ეძახის. API ვალიდაცია არის `between:1,5`, ადმინის select არის `0`–`3`. | to do | — |
| T-07 | ინტეგრაციის ხარვეზები, `docs/gewash-frontend-integration.md`: პაროლის token endpoint არ არის (`/reset-password`). პირობების ტექსტი არ არის (`/terms`). კლიენტის გადატანა `POST /appointments/:id/edit`-ს მენეჯერი სჭირდება. შეკვეთის ცალკე ჩანაწერი, გათამაშების შედეგი, შეტყობინებების სია, მხარდაჭერის ბილეთი (მხოლოდ mailto) და სხვისი კლიენტის ჯავშანი არ არის. 401-ზე გლობალური redirect არ დაემატა. maintenance დროშა სერვერზე არ არის. `docs/gewash-frontend-coverage.md` აღწერს `gewash/src/quiet/`-ს და მარშრუტებს (`/bookings`, `/packages`, თემის გვერდები), რომლებიც `App.tsx`-ში არ არის. | to do | — |
| T-08 | ქვედა ნავის კალენდარი მიდის `/customer-calendar`-ზე, `App.tsx` კი ამ მისამართს `/shop`-ზე აბრუნებს. | to do | — |
| T-09 | Push-ის ნაგულისხმევი URL კვლავ `https://app.geocar.ge/messages`-ია (`User::sendPush`). ჯავშნის push-იც `https://app.geocar.ge/booking`-ზე მიდის (`AppointmentsController`). | to do | — |
| T-10 | Landing-ის ფუტერის `#` ბმულებს რეალური გვერდი არ აქვთ (PR #4 და `lending/README.md`): პაკეტები, ჯავშანი, QR, ვაუჩერი, კორპორატიული, ჩვენ შესახებ, ფილიალები, პარტნიორობა, კარიერა, FAQ, კონტაქტი, წესები, კონფიდენციალურობა, ჩემი პაკეტი, ჯავშნები, Facebook, Instagram, YouTube. სიახლეების ფორმა მხოლოდ ვიზუალია. ზედა ხედის მანქანა placeholder-ია. სტორის ბეჯი `/register`-ზე მიდის, რადგან აპი სტორში არ არის. | to do | shootX |
| T-11 | PR [#3](https://github.com/shootX/autopass/pull/3) შერწყმულია: v4 მობილური რედიზაინი (`dd54748`, 2026-10-09). | done | shootX |
| T-12 | PR [#4](https://github.com/shootX/autopass/pull/4) შერწყმულია: მარკეტინგის გვერდი `lending/` (`ece763f`, 2026-10-09). ღია ნაშთი არის T-10. | done | shootX |
| T-13 | PR #2 შერწყმულია: forest/lime rebrand (`eaac4d2`, 2026-10-07). | done | shootX |
| T-14 | `dfe84dc` (2026-10-10, ავტორი `root`): ქართული ტექსტი, ჯავშანი არჩეულ დღეს, კორპორატიული ავტოპარკი და TBC checkout. | done | root |
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

## 2026-10-12 — FE-11 / MOB-10

- [x] FE-11 / MOB-10: მენეჯერი (`role = 1`) აღარ გადის მანქანის შემოწმებას და ლოგინის შემდეგ ხვდება `/manager-qr-page`-ზე. `GET /me` სრულდება მარშრუტის არჩევამდე; სანამ პასუხი არ მოვა, ჩანს splash. `401` აბრუნებს `/auth`-ზე.

## 2026-10-12 — set_password, FE-03, FE-04

- [x] set_password: `/register/verify`-ზე შეყვანილი SMS კოდი რჩება კომპონენტის state-ში და იგზავნება `code`-ად `POST /api/register/set_password`-ზე (`temp_code`-თან ერთად). `sms_code` რეგისტრაციის პასუხიდან აღარ იკითხება.
- [x] FE-03: ავტორიზებული მოთხოვნის `401` შლის `access_token`-ს და ხსნის `/auth`.
- [x] FE-04: რეგისტრაციის რეფერალის IP აღარ იძახებს `https://back.geocar.ge/api/ref/ip`. იყენებს `{VITE_API_URL}/ref/ip`.
- [x] main-ის კონტრაქტი ემთხვევა: `set_password` ითხოვს `temp_code`, `code` (digits:6) და `password`.
