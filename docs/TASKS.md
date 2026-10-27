# დავალებები — autopass

განახლების თარიღი: 2026-10-10. წყარო: GitHub PR #1, #3, #4, `fix.md`, `docs/gewash-frontend-integration.md`, `docs/gewash-frontend-coverage.md`, `lending/README.md`, კოდი. `TODO` / `FIXME` კომენტარი `*.ts`, `*.tsx`, `*.php`-ში არ მოიძებნა (მხოლოდ placeholder `XXXX`).

**წესი:** ერთი agent ერთ დროს ერთ დავალებას იღებს. `in progress` ერთზე მეტი არ არის. დავალების დასრულებისას სტატუსი და მფლობელი იცვლება იმავე PR-ში, და `docs/MEMORY.md` იღებს თარიღიან ჩანაწერს.

## მთვლელები

| | რაოდენობა |
|---|---|
| to do | 10 |
| in progress | 1 |
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
| T-15 | 2026-10-26: კლიენტის სრული პროფილი ადმინში (`/dashboard/clients/{id}`): KPI, სეგმენტი, ისტორიის ტაბები. ბაზა არ შეცვლილა. შემდეგი ეტაპი: სეგმენტის ფილტრი სიაში და ექსპორტში, აკლია ბოლო შესვლა, პლატფორმა, გაუქმების მიზეზი, ჯავშნის ფასი, წყარო, ადმინის შენიშვნა. | in progress | shootX |
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

## 2026-10-12

- [x] FE-06: coverage-ის ის მისამართები, რომელსაც ეკრანი უკვე აქვს, `App.tsx`-შია. უცნობი მისამართი აღარ გადადის სხვა გვერდზე — ჩანს 404. ეკრანის გარეშე მისამართები არ გამოგონილა (`missingCoverageRoutes`).
- [x] FE-07: მანქანის რედაქტირება და წაშლა ამოწმებს `res.ok`-ს და `success: false`-ს.
- [x] FE-08: ლოგინი ინახავს `expires_in`-ს და ვადაგასული ტოკენი აპის გახსნაზე იშლება. TTL backend-ის `JWT_TTL`-ია. main-ის ნაგულისხმევი ახლა 43200 წუთია (30 დღე); კლიენტი სერვერის `expires_in`-ს იყენებს.
- [x] FE-09: მარკეტინგის `/` ტოკენის მქონე მომხმარებელს მიჰყავს `/home`-ზე. სერვერმა `/home` იმავე SPA-ზე უნდა გაუშვას, როგორც `/auth`.
- [x] FE-10: `/customer-my-data#vehicles` სქროლავს `#vehicles` ანკერზე.

- [x] MOB-01: onboarding აღარ ფარავს `/auth`, `/register`, `/renew-password`, `/privacy`, `/payment-success`.
- [x] MOB-02: lending-ის ღილაკი, ბურგერი, ფუტერის ბმული და სოციალური ხატი მობილურზე მინიმუმ 44px-ია.
- [x] MOB-03: lending-ის ელფოსტის ველი 16px-ია, რომ iOS-მა არ გაადიდოს.
- [x] MOB-04: რეგისტრაციის თანხმობის რიგი იხვევა და 320px-ზე არ გადის გვერდით.
- [x] MOB-05: `/auth`-ის პაროლის ღილაკი მინიმუმ 44px-ია.
- [x] MOB-06: ტელეფონს, პაროლს და OTP-ს აქვს `autocomplete`.
- [x] MOB-09: ქვედა ნავი ითვალისწინებს `safe-area-inset-bottom`-ს.
- [x] MOB-11: სკანერის კამერის შეცდომა ჩანს („კამერაზე წვდომა საჭიროა“ + თავიდან ცდა), `useEffect` აჩერებს კამერას, სტარტი ერთია.
- [x] viewport: `maximum-scale=1` და `user-scalable=no` მოშორებულია.

- [x] PWA: manifest-ში PNG 192 და 512, maskable 192 და 512. `public/sw.js` ქეშავს გარსს და `/api`-ს არ ეხება. რეგისტრაცია მხოლოდ production build-შია.
- [x] მკვდარი დამოკიდებულება: `expo-camera` წაშლილია. EAS-ის `gewash/app.json` წაშლილია.

UX აუდიტის ფრონტის პუნქტები, ცალკე პრ:

- UX-20 ავტოგანახლება გამორთულია, სანამ მომხმარებელი არ ჩართავს. `/my-packages` ჯერ სიას აჩვენებს.
- UX-22 ენის სახელებია ქართული, English, Русский.
- UX-01 ტელეფონის placeholder ერთია (`500500555`). პაროლის placeholder თარგმანიდანაა. ღია ფონზე ლაიმის ბმული ტყის ფერია. მუქ პანელზე ჩამქრალი ტექსტი გაიხსნა.
- UX-04 შესვლის შეცდომა თავის ველის ქვეშაა.
- UX-02, UX-06 ავტორიზაციის, სპლეშის და ონბორდინგის მანქანა ჩარჩოში ეტევა.
- UX-03 პოლიტიკის სტრიქონი იხვევა.
- UX-07 გვერდითი პანელი იწელება, პროფილი ხელმისაწვდომია.
- UX-08 მარშრუტის ცვლილება სქროლს აბრუნებს ზემოთ. `#vehicles` რჩება.
- UX-13 მისალმება სახელის გარეშე ტელეფონს იყენებს. ჯავშნის ღილაკი აღარ იჭრება.
- UX-14 მანქანის დამატებას ერთი სათაური აქვს, როცა ჰედერი ჩანს.
- UX-15 სქესის placeholder არის „აირჩიეთ“. გადამრთველები ტექსტს ემთხვევა.
- UX-16 რუკის ძებნა და ფილტრი ერთ რიგშია.
- UX-17 ცარიელი სერვისის ტექსტი.
- UX-18 მაღაზიის ბურგერი 1024px-იდან იმალება. ლენდინგის მენიუ 860px-ის ზემოთ იხურება.
- UX-19 ვაუჩერის ჩიპები ითარგმნება.
- UX-21 ავატარი ინიციალებია, სახელის გარეშე ნომრის ბოლო ციფრები.
- UX-12 ლენდინგი `access_token`-ით მიდის `{APP_BASE}/home`. SPA-ში `/home` კლიენტის მთავარია, მენეჯერს `/`-ზე აბრუნებს.
- UX-23 API გასაღები ნიღბიანია, ჩვენების ღილაკით.
- UX-24 პარტნიორის საიდბარი 992px-იდან კონტენტს აღარ ფარავს, მენიუ იწელება.
- UX-25 ცარიელი ტრენდი ცარიელ მდგომარეობას აჩვენებს.
- UX-26 მოდელის სია ბრენდის არჩევამდე გამორთულია.
- UX-27 გასაღები იხვევა.
- UX-28 ექსელის ფაილს აქვს „ფაილის არჩევა“, ბრაუზერის ინგლისური ღილაკის ნაცვლად.
- UX-29 ავტოპარკის სამი ბარათი ერთ სიმაღლეზეა.
- UX-30 ახალი პაროლი ანგარიშზე არასავალდებულოა. მიმდინარე პაროლიც არასავალდებულოა: სერვერი ითხოვს მას მხოლოდ ახალ პაროლთან ერთად (`required_with:password`). ფორმის ველს `required` არ აქვს.

შენიშვნები, კოდში არ შეცვლილა:

- UX-09 „ჭკკვიანი“ SPA-ის წყაროში არ არის. პრომოს სათაური `GET /api/promo`-დან მოდის. main-ის მიგრაცია `2026_10_12_000004_fix_promo_typo` ამ სიტყვას ბაზაში ცვლის.
- UX-10 სპლეშს ხელოვნური 3–5 წამი არ აქვს. ჩანს, სანამ ენა და მომხმარებელი ჩაიტვირთება.
- UX-11 ჰედერები სხვადასხვა ეკრანზე სხვადასხვა კომპონენტია. ერთი დეფექტი არ გამოჩნდა, სტილი არ შეცვლილა.
- UX-05 ლენდინგის ილუსტრაციები `lending/img`-შია. ახალი ნახატი არ დახატულა.
- UX-12 `/home` სერვერმა ისე უნდა გახსნას, როგორც `/auth`. nginx არ შეხებულა.
- UX-26 თუ კატალოგი ცარიელია, მოდელები ბაზაში არ არის. ეს ფრონტის ფილტრი არ არის.

FE-12. შესვლის ტელეფონის ველი იღებს შეკუმშულ და გამოყოფილ ნომერს. გამოტოვებულია სივრცე, ტირე და `+995` / `995` პრეფიქსი. ველში რჩება 9-ნიშნა ადგილობრივი ნომერი. `maxLength` აღარ ჭრის ფორმატირებულ ჩასმას.

UX-10. სპლეში იხურება ენისა და როლის მონაცემების მზადყოფნისთანავე. თუ ჩატვირთვა გრძელდება, 1.5 წამზე მეტხანს აღარ რჩება. მინიმალური ხელოვნური დაყოვნება არ არის. Lazy route-ების Suspense fallback უცვლელია.

UX-11. გვერდების ჰედერები ერთ `Header` კომპონენტშია (`shell`, `bar`, `v4`, `labeled`). კლასები, ტეგები და ღილაკები იგივე დარჩა. ჩატვირთვის სკელეტი ცალკეა, რადგან იქ მენიუ არ იხსნება.

UX-05. ლენდინგის ცენტრის ილუსტრაცია `img/top-car-840` რჩება, ტელეფონის მაკეტი `img/app-home-780`-ს აჩვენებს. `picture { display: contents }` მოხსნილია, რომ სურათი ჩარჩოში დაიხატოს.

UX-09. დესკტოპის საიდბარი სქროლავს, პრომო და პროფილი აღარ იფარება. „ჭკკვიანი“ SPA-ის წყაროში არ არის: სათაური `GET /api/promo`-დან მოდის. main-ის მიგრაცია `2026_10_12_000004_fix_promo_typo` ამ სიტყვას ბაზაში ცვლის.

UX-12. დაემატა `deploy/nginx/autopass.conf.example`. `/home` და დანარჩენი SPA მისამართები `try_files`-ით მიდის SPA-ის `index.html`-ზე. სერვერი არ შეხებულა.

## 2026-10-12 — Autopass merge დასრულდა

main-ში რიგით დამერჯდა #7, #8, #5, #6, #9, #10–#17 (merge commit-ებით, force-push-ის გარეშე). სუფთა checkout-ზე: Pest 32/32 (108 assertions), vitest 72/72 (21 ფაილი), vite build გავიდა. დეპლოი არ გაკეთებულა, მას შოთა გააკეთებს.

## 2026-10-13 — Stage 1 frontend

`docs/API_CONTRACT_STAGE1.md` ამ ბრენჩზე არ არის. კონტრაქტი აღებულია `AuthController::smsJson`-იდან და პარტნიორის ვაუჩერის/პაროლის პასუხებიდან. რეგისტრაცია და პაროლის აღდგენა უკვე იყო `temp_code` (32 hex სტრინგი) + `setup_token`. ეს არ გადაკეთებულა, მხოლოდ შემოწმდა.

SMS სტატუსი (`simulated`, `accepted`, `failed`, `unknown`) ერთი კომპონენტია რეგისტრაციაზე, აღდგენაზე, ტელეფონის შეცვლაზე და ვაუჩერის დადასტურებაზე. `simulated` სატესტო რეჟიმად წერია. `accepted` არ ნიშნავს მიტანას. OTP არც localStorage-ში ინახება და არც ლოგში იწერება. ტელეფონის შეცვლა `temp_code`-ს sessionStorage-ში აღარ დებს.

`customFetch` 401 შლის ტოკენს, ვადას, როლს და `autopass.me` ქეშს. მენეჯერი მიდის `/manager-auth`-ზე, კლიენტი `/auth`-ზე. ორივე იგივე შესვლის ფორმაა. მენეჯერი კვლავ CarCheckRoute-ის გარეთ რჩება.

voucher-checker: `temp_code` მხოლოდ სტრინგია, რიცხვითი id აღარ იგზავნება. `must_change_password` სხვა ეკრანს ბლოკავს. 401 სესიას შლის. `PhoneOTPWrapper`-ის ძველი რიცხვითი `temp_code` მოხსნილია.

`npm ci` გავიდა ორივე აპში. შემდეგ იგივე დღეს ტიპები ბოლომდე გასწორდა, წესების მოშვების გარეშე. gewash: vitest 83/83 (23 ფაილი), `build:check` (`tsc -b` და vite build) 0 შეცდომა, lint 0 შეცდომა და 0 გაფრთხილება. voucher-checker: vitest 9/9 (2 ფაილი), `tsc -b && vite build` გავიდა, lint 0 შეცდომა და 0 გაფრთხილება. სერვერი არ შეხებულა.

## 2026-10-13 — სატესტო გადახდა პროდაქშენზე (flag)

დაემატა `TEST_PAYMENT_ALLOW_PRODUCTION` (ნაგულისხმევად false). ჩართვის შემდეგაც სატესტო გადახდა მხოლოდ ადმინს და `TEST_PAYMENT_PHONES`-ის ნომრებს შეუძლიათ. მარშრუტები ახლა ყოველთვის რეგისტრირდება, ხოლო შემოწმება კონტროლერში ხდება (`App\Support\TestPayments`). webhook-ები არ შეცვლილა. Pest-ის ტესტები: `tests/Feature/TestPaymentProductionTest.php`. runbook §10.
## 2026-10-14 — Stage 1 merged to `main`
- PR #19 (frontend, head `34b6a94`) merged into `feat/stage1-security-smsoffice` (`8c08f08`), then PR #18 merged into `main` (`7f73d2b`). Merge commits only, no force-push. Shota approved the merge in the GAI.GE Stuff group; no deploy.
- Checks on a fresh `main` checkout: backend Pest 69/69 (408 assertions); gewash `npm ci` ok, vitest 83/83, build ok, `build:check` 0 TS errors, lint 0/0; voucher-checker `npm ci` ok, vitest 9/9, build ok, lint 0/0.
- Open: test-payment flag for test accounts in production (separate PR, not merged yet). Deploy is done by Shota: `php artisan migrate`, scheduler running, cache driver with locks; backend and frontend ship together.

## 2026-10-14 — Stage 2 უსაფრთხოება (1–4), branch `feat/stage2-security`

ყველა პუნქტისთვის ჯერ დაიწერა ტესტი, რომელიც ვარდებოდა (`tests/Feature/Stage2SecurityTest.php`), მერე გასწორდა.
1. `/api/me/edit` ტელეფონს და ელფოსტას აღარ ცვლის (თუ შეცვლილია, აბრუნებს 422-ს). ელფოსტა იცვლება მხოლოდ `pending_email`-ის გავლით, ჰეშირებული კოდით და ვადით.
2. დაბლოკილი (ban) მომხმარებელი: API, ადმინის ვები, პარტნიორის API და კომპანიის პანელი ძველ სესიას/ტოკენს მაშინვე უარყოფს, შესვლაც უარყოფილია. partners/corporate_clients-ს დაემატა `ban` სვეტი.
3. კომპანიის `/partner/password` მუშაობს მხოლოდ მაშინ, როცა `password_must_change=true`. ჩვეულებრივ ცვლილებას სჭირდება მიმდინარე პაროლი, და `session_version` სხვა სესიებს აუქმებს.
4. ვაუჩერის ყიდვა ერთ ტრანზაქციაშია და ბალანსის ჩანაწერზე იყენებს `lockForUpdate`-ს. ნებისმიერი შეცდომისას ყველაფერი უკან ბრუნდება.

## 2026-10-14 — Stage 2 sweep (mass-assignment + balance mutations)
- [x] User/Partner/CorporateClient privileged columns removed from mass assignment (role, referral_id, ref_code, phone_verified_at; partner login secrets via $fillable; corporate credentials/api_token/session_version/must_change). Creates use forceCreate; token rotations forceFill.
- [x] Admin car wash edit validates fields; manager_id must be a manager; services stripped from update.
- [x] Ticket purchase: integer qty 1..20, DB::transaction + lockForUpdate (ticket, user), limit counts qty, tickets + ledger inside transaction, rollback tested.
- [ ] Follow-up: admin UI for partner/company ban; ReferralsController::saveHash public unvalidated writes; TbcPayment/Transaction/UserVoucher still $guarded=[] (server-side only).

## 2026-10-14 — Stage 2 follow-ups
- [x] /api/ref/save-hash + check-hash: hash must be 64 hex, code must be an existing 5-char ref_code; `throttle:referral` 10/min/IP.
- [x] Admin ban/unban partner (POST /dashboard/partners/{id}/ban, button on partner edit page) and company (POST /dashboard/corporate/{id}/ban, button in corporate list). Partner JWTs revoked via token_version, company panel sessions via session_version, company API key refused (403) while banned.
- [x] Admin edit of user phone/email: admin role only, admin_audit_logs entry per field (masked), user's JWTs + DB sessions revoked. Bans are audited too.

## 2026-10-14 — QA fix on #21
- [x] Sandbox payment confirm re-checks the owner: TestPayments::allowed() is false for banned users, and SettleTbcPayment::grantNow() re-checks allowed() on the locked owner row for test_mode payments (marks the payment Failed, credits nothing).

## 2026-10-14 — hotfix: ცარიელი გვერდები (`PublicAuthRoutes`)
- [x] `gewash/src/App.tsx`: `<PublicAuthRoutes />` `<Routes>`-ის შვილად აღარ რენდერდება. იძახება `{PublicAuthRoutes()}`, რომ დაბრუნებული ფრაგმენტი პირდაპირი შვილი იყოს. react-router მხოლოდ `Route` ან `Fragment` შვილს იღებს და სხვა კომპონენტზე ისვრის, ამიტომ ყველა გვერდი ცარიელი იყო.
- [x] ტესტი `gewash/src/App.routes.test.tsx`: აპის როუტერი ხატავს მთავარს (`/`, ნომერი და „სწრაფი ჩაწერა“) და შესვლას (`/auth`, სათაური „შესვლა“ და ტელეფონის ველი). `/login` მარშრუტი არ არსებობს — შესვლა არის `/auth`.
- [x] შემოღებულია `650defc` (2026-10-12, ავტორი Cursor Agent), PR #6 (`cursor/fe-11-manager-routing-9883`).
- [x] gewash: lint 0, vitest 85/85, `build:check` 0. მერჯი და დეპლოი არ გაკეთებულა.

## 2026-10-14 — Stage 2 items 5–6
- [x] Partner voucher check: Cache::lock per voucher+recipient, open challenge reused within SECURITY_VOUCHER_CHECK_DEDUPE_SECONDS (60) → one SMS for repeated checks; 429 while a check is still running.
- [x] Car catalog: source + external ids, CarAPI ids no longer treated as local ids, year on user_cars + GET /api/cars/years. Contract in gewash-frontend-integration.md.
- [x] Frontend: AddCar sends source/brand_id/model_id/year; implemented locally in Stage 3, publication pending (see latest entry).

## 2026-10-14 — QA fixes on #22
- [x] Model resolution checks brand: local model must belong to brand_id; offline CarAPI fallback only reuses a mapping under the same external brand.
- [x] Parallel first-time add of the same CarAPI model: unique (source, external_*_id) + lookup/insert with UniqueConstraintViolationException retry → no 500, one row.

## 2026-10-14 — Stage 3, Codex (`codex/stage3-product-fixes`)

- Implemented locally from `f751751`: AddCar catalog/source/year contract and real car id; EditCar direct route; generation-year provenance; owned-car/branch/service/hours booking validation and atomic writes; duplicate exact slots; password-reset session revocation; pending package order reuse and one benefit per paid payment; expired/exhausted replacement with retained history; consistent appointment statuses; locale keys and npm 11 clean installs.
- Verification: backend SQLite 131/131 (695 assertions), gewash 92/92, voucher-checker 9/9; both builds and lints passed; all six locale files cover 373 referenced keys; i18n regression passed; clean npm 11 installs passed. See `docs/STAGE3_QA.md` for commands and limits.
- Native PostgreSQL 16/database-cache concurrency CI prepared, NOT run. Local PostgreSQL-WASM regression tests passed, but concurrency runs were unstable; no native database concurrency certification.
- Browser visual/E2E checks and authenticated CarAPI, SMTP/SMS/payment delivery still require a working browser/provider environment. Test payment remains enabled only as previously authorized; SMS stays mocked.
- GitHub branch creation was rejected with HTTP 403 `Resource not accessible by integration`; no remote branch, PR, main merge or deployment. Export contains the local commits and test evidence. GitHub write access is required to publish.

## 2026-10-14 — Stage 3 publication
- [ ] Push branch `codex/stage3-product-fixes` and open draft PR (blocked: token lacks `workflow` scope for `.github/workflows/product-checks.yml`).
- [x] Native local PostgreSQL 17 concurrency suite: 22/22, 8 runs.
- [ ] PostgreSQL 16 CI job run.
- [ ] Browser visual/E2E check (QA, Playwright).
- [ ] Live CarAPI fields, SMTP/SMS/payment sandbox delivery.
- [ ] Migration validated on a copy of the target DB before deploy.

## 2026-10-14 — Controlled test launch readiness (Codex)

- PR #27 (`3718736`) contains all Stage 3 code. GitHub Product regression checks completed successfully, including native PostgreSQL 16/database-cache checks; the earlier WASM uncertainty is not the current native CI result.
- Additional local hardening: authentication responses/tokens are no longer logged; a translated connection-failure alert replaces a silent login catch; the service worker caches public assets only, respects private/no-store responses, and removes its old application cache without deleting unrelated caches. Document navigation and authenticated routes pass through the network. Uncached page navigation will require connectivity.
- Example deployment endpoints now point to wash.socialsave.cc and car.socialsave.cc instead of a temporary tunnel or empty values; authentication bypass remains false.
- Verification: gewash 103/103 (11 new regressions), build:check/lint/i18n checks passed; 374 referenced locale keys, 0 missing in six dictionaries. Stage 3 backend 131/131, voucher 9/9 and PostgreSQL 22/22 remain prior CI evidence; backend business code and voucher application code were not changed in this follow-up.
- Browser observed the current landing, customer login, recovery, registration, public privacy text and admin login. No credentials were submitted; private panels and full new-version workflows were not tested. Landing help redirects anonymous visitors to login; privacy is English-only. These are recorded polish tasks for the closed test.
- No merge, target server change or deployment. Target database copy, server credentials/configuration, actual CarAPI key and mail delivery are unavailable here. See docs/TEST_LAUNCH_READINESS.md for the exact controlled launch gates. Follow-up patch must be uploaded before deploying.

## 2026-10-15 — Security audit fixes (branch `security/audit-fixes`)
- [x] C1: migration `2026_10_15_000001` locks admin/manager accounts and partners whose password matches a published default (admin123/manager123/partner123/client123/legacy hardcoded) — random password, token_version+1, web sessions deleted. Users get `password_reset_required` (API + web login refused until SMS reset); partners get an expired temporary password (admin must issue a new one). Seeders already use env/random.
- [x] H1: package wash approve needs a booking today at the manager's branch, or `qr_code` (scanned), or the one-time `approve_token` from `GET /api/checkqr/{qr}` (10 min, bound to manager+package). Bare id → 403. Throttle 30/min on both.
- [x] H2: `TEST_PAYMENT_MAX_AMOUNT` (default 100) on /api/testpay; /payment/sandbox is 404 in production unless test payments are enabled.
- [x] M1: `POST /api/refresh` (rotates, blacklists old token); default `JWT_TTL` 1440 min.
- [x] M2: `throttle:otp-send` on SMS-sending endpoints: per number per hour (any IP) + daily global budget; `users:prune-unverified` (hourly).
- [x] M3: /register and /change_password answer the same for known and unknown numbers; login reveals unconfirmed/banned only after a correct password.
- [x] M5: `AuditAdminRequests` middleware logs every admin write + SMS inbox views to admin_audit_logs.
- [x] M6: composer: maatwebsite/excel 3.1.70, league/commonmark, laravel/framework, league/flysystem updated; `composer audit` clean.
- [x] M7: corporate API key stored as sha256 (+last4, last-used); shown once after issue; existing keys hashed in place by migration `2026_10_15_000002`.
- [x] M8: mock SMS inbox stores texts only for `SMS_MOCK_INBOX_PHONES` (default TEST_PAYMENT_PHONES); views audited.
- [x] L2 uniform partner voucher answers; L3 no SVG uploads; L4 `@json($voucher)`; L5 review toggle is POST+CSRF; L6 `TRUSTED_PROXIES`; L7 OTP/email codes HMAC-keyed per challenge; L8 account deletion erases personal data with a unique tombstone phone.
- [x] Frontend: manager approve, `/api/refresh`, CSP list, npm audit — done on `security/frontend-audit-fixes` (section below).
- [ ] DevOps (Shota): deploy, security headers (M4), APP_ENV=production, TEST_PAYMENT_ALLOW_PRODUCTION off, TRUSTED_PROXIES, check live accounts after migrate (C1).

## 2026-10-15 — Frontend audit fixes (`security/frontend-audit-fixes`)

- [x] `security/audit-fixes` (`16845d1`, PR #28) შერწყმულია merge commit-ით. Rebase და force-push არ ყოფილა.
- [x] H1: მენეჯერის approve აგზავნის `qr_code` და/ან `approve_token`-ს `GET /api/checkqr`-დან. მხოლოდ id აღარ იგზავნება. ტელეფონი ბარათზე აღარ არის სავალდებულო. ტესტი: `gewash/src/lib/packageApprove.test.ts`.
- [x] M1: JWT რჩება მეხსიერებაში. #28 httpOnly cookie-ს არ დებს. ძველი `localStorage` ტოკენი იშლება. `POST /api/refresh` ეშვება `expires_in`-მდე (TTL 1 დღე). წარუმატებლობა გამოდის სესიიდან. ტესტი: `accessToken.test.ts`, `sessionRefresh.test.ts`. voucher-checker-ის პარტნიორის ტოკენიც მეხსიერებაშია (`voucherApi.test.ts`).
- [x] M6: `maplibre-gl` 6.14.0, `react-router-dom` 7.18.4, gewash `vite` 7.3.7, `postcss` 8.5.30, voucher-checker `vite` 8.3.4. `npm audit --omit=dev` ორივეზე სუფთაა. `npm ci` გადის npm 11.17.0-ზეც და npm 10.9.9-ზეც (engines მხოლოდ გაფრთხილებაა).
- [x] M4: `docs/CSP.md`. nginx არ შეცვლილა. `api.mapbox.com` preconnect მოხსნილია.
- [x] `/help` სტუმარს შესვლაზე აღარ აგდებს. ტესტი: `App.routes.test.tsx`.
- [x] კონფიდენციალურობა ka/en/ru, `[cite:` აღარ არის. ტესტი: `PrivacyPage.test.tsx`.
- [x] `VITE_BYPASS_AUTH` და `VITE_MOCK_VOUCHER_CHECK` production ბილდში ვერ ირთვება. ტესტი: `productionFlags.test.ts` ორივე პაკეტში.
- [x] `/payment/sandbox` არის NotFound, სანამ `VITE_TEST_PAYMENTS=true` არ არის. ბარათის ფორმა არ დაემატა. ტესტი: `App.routes.test.tsx`.
- [x] იგივე კლასი: device token აღარ ეწერება iframe-ში; checkout URL გადის `safeCheckoutUrl`-ში; `redirect`/`next` არ მოჰყვება; Viber აღარ იყენებს `geocar.ge/redirect.html`. ტესტი: `navigationTarget.test.ts`, `App.routes.test.tsx`.
- captcha ამოცანიდან ამოღებულია. ვიჯეტი არ დაემატა.
- შერწყმა main-ში, დეპლოი და ცოცხალი სერვერი არ შეხებია.

## 2026-10-15 — Frontend follow-up for #28 `104dfe8`

- [x] `security/audit-fixes` (`104dfe8`) შერწყმულია merge commit-ით. Rebase და force-push არ ყოფილა.
- [x] `POST /api/refresh`: 409 ერთხელ მეორდება და სესია რჩება; 401 გამოდის სესიიდან. ტესტი: `sessionRefresh.test.ts`.
- [x] `approve_token` ერთჯერადია: გაგზავნის შემდეგ აღარ ინახება. 403/409/422 აბრუნებს სკანერზე. ტესტი: `packageApprove.test.ts`.
- [x] `test_points` ცალკე ჩანს ხარჯვადი ქულისგან (`points - test_points`), სადაც `/me` აბრუნებს ველს. ტესტი: `pointBalance.test.ts`.
- main-ში შერწყმა და ცოცხალი სერვერი არ შეხებია.

## 2026-10-15 — QA fixes on PR #28 (D1–D9, `tests/Feature/AuditQaFixesTest.php`)
- [x] D1 client accounts on client123/known defaults locked too (migration 000003 reruns RetireDefaultPasswords incl. role USER).
- [x] D2 approve_token consume under `Cache::lock` (parallel → 409, single deduction).
- [x] D3 array `qr_code`/`approve_token`/`amount` → 422 instead of 500.
- [x] D4 a booking allows only one bare-id approve; later washes need QR/token.
- [x] D5 daily per-user test-payment cap (`TEST_PAYMENT_DAILY_MAX`); sandbox points tracked in `users.test_points` and not spendable on vouchers/tickets.
- [x] D6 refresh: per-jti lock (409 on parallel), blacklist recheck, absolute session lifetime via `sess` claim (`SECURITY_SESSION_MAX_MINUTES`).
- [x] D7 decoy path does a bcrypt so timing matches (SMS gateway latency not equalized).
- [x] D8 change_phone to a taken number answers like a free one, no SMS; verify still enforces unique.
- [x] D9 failed-validation redirects not audited; admin testpay audited with explicit admin id.

## 2026-10-15 — QA round 2 on PR #28 (`tests/Feature/AuditQa2Test.php`)
- [x] D13 approve accepts only the one-time checkqr token (static QR/bare id → 403, booking too); per-package Cache lock (409) + row lock.
- [x] D12 testpay daily cap: sum + insert under a per-user Cache lock (409) and user row lock.
- [x] D11 deletion tombstone `deleted-<id>-<rand>` written past the phone mutator (unique, never a phone).
- [x] D10 register/change_password/change_phone send SMS via queued `SendSms` (decoy dispatches a no-op job); bcrypt only on the register decoy.
- [x] Refresh: rotating httpOnly `ap_refresh` cookie (Path=/api/refresh, Secure, SameSite=Strict), reuse → family revoked; public `/api/refresh`; bearer flow kept as deprecated compat; CORS credentials for listed origins.
- [x] Contract: `docs/auth-contract.md`.
- [ ] Ops: production needs a queue worker for the `database` queue (SMS now goes through it).
- [x] Frontend: switch gewash to the cookie flow (`credentials: 'include'`), handle 409. See the section below.

## 2026-10-15 — Frontend for #28 `f45eca0`

- [x] `security/audit-fixes` (`f45eca0`) შერწყმულია merge commit-ით. Rebase და force-push არ ყოფილა.
- [x] `ap_refresh` (HttpOnly, path `/api/refresh`). `credentials: 'include'` არის `/api/login`, `/api/register/set_password`, `/api/refresh`, `/api/logout`-ზე. ტესტი: `customFetch.test.ts`, `Authentication.integration.test.tsx`, `sessionRefresh.test.ts`.
- [x] გვერდის ჩატვირთვა იძახებს `POST /api/refresh`-ს Authorization-ის გარეშე. სანამ პასუხი არ მოვა, ჩანს splash და დაცული გვერდი შესვლაზე არ გადადის. 200 ინახავს `access_token`-ს მხოლოდ მეხსიერებაში. 401 ხსნის შესვლას. 409 ერთხელ მეორდება და სესიას არ წყვეტს. ქსელის შეცდომა სესიას არ წყვეტს. პარალელური refresh ერთია. Bearer refresh აღარ იგზავნება. ტესტი: `App.routes.test.tsx`, `sessionRefresh.test.ts`.
- [x] Approve სხეული არის მხოლოდ `{approve_token}`. 403 აჩვენებს შეცდომას და აბრუნებს სკანერზე. 409 აბრუნებს სკანერზე. ტესტი: `packageApprove.test.ts`.
- [x] `/register`, `/change_password`, `/change_phone` პასუხი `delivery: accepted|simulated` აღარ ითვლება შეცდომად. სატესტო შეტყობინება ჩანს მხოლოდ არა-production ბილდში. ტესტი: `registerPassword.test.ts`, `smsDelivery.test.ts`.
- captcha ამოცანიდან ამოღებულია.
- main-ში შერწყმა და ცოცხალი სერვერი არ შეხებია.

## 2026-10-15 — QA round 3 on PR #28
- [x] D14 two-tab refresh: parallel calls wait on the cookie lock; a rotated cookie reused within 30 s (`REFRESH_REUSE_GRACE_SECONDS`) gets a fresh pair in the same family; later reuse still revokes the family.
- [x] D15 public `POST /api/refresh/logout` (under the cookie Path) revokes the session by cookie with an expired access token and clears the cookie.
- [x] D16 parallel /register of one new number: unique violation caught, normal answer (no 500).
- [x] SendSms: tries=3, backoff [5, 20] s.
- [x] PR #29 (frontend): Sign out → `POST /api/refresh/logout` with `credentials: 'include'`.

## 2026-10-15 — Frontend for #28 `806db9a`

- [x] `security/audit-fixes` (`806db9a`) შერწყმულია merge commit-ით. Rebase და force-push არ ყოფილა.
- [x] გამოსვლა იძახებს `POST /api/refresh/logout`-ს (`credentials: 'include'`, bearer თუ მეხსიერებაში ტოკენია). შემდეგ შლის მეხსიერების ტოკენს და ხსნის `/auth`-ს. ქსელის შეცდომაზეც სრულდება. ტესტი: `sessionRefresh.test.ts`.
- refresh უცვლელია: 409 ერთხელ, ქსელის შეცდომა სესიას არ წყვეტს. ტაბების lock არ დაემატა.
- login, set_password, approve და cookie refresh უცვლელია.
- main-ში შერწყმა და ცოცხალი სერვერი არ შეხებია.

## 2026-10-16 — ვიზუალური პორტი ნავი/ოქრო (`cursor/visual-redesign-port-e825`)

- [x] `41a3eae` (blue+gold) გადმოვიდა `aac2b78`-ზე ახალ შტოზე. ძველი შტო არ შერწყმულა.
- [x] მხოლოდ ვიზუალი: SCSS/CSS, Tailwind, `redesign.scss`, `umami-admin.css`, voucher-checker სტილები, ბრენდის SVG და კომპონენტების ფერის მნიშვნელობები. პალიტრა `docs/DESIGN.md`-ში.
- [x] არ გადმოვიდა: `src/preview/fixtures.ts`, hook-ების preview მონაცემები, `App.tsx`-ის bundled lang, `phonePrefix`, ავტორიზაცია/refresh/logout, i18n გასაღებები და მარშრუტები.
- [x] შემოწმება: gewash lint, vitest 139/139, `build:check`, `i18n:check`; voucher-checker lint, vitest 12/12, `build:check`.
- [ ] შერწყმა main-ში და დეპლოი არ გაკეთებულა.

## 2026-10-16 — QA ფიქსები PR #30 (F1–F3)

- [x] F1: voucher-checker `.auth-wrapper` და `.sign-in` იღებს `box-sizing: border-box`. დესკტოპის `.auth-page::before`-საც. სპლიტი ივსება `min-height: 100dvh`-ით.
- [x] F2: შესვლის ბმული დესკტოპზე ნავია (`#12284b` თეთრზე), მობილურზე ოქრო მუქ პანელზე. `+995` დესკტოპის თეთრ ველზე არის `#14233c`.
- [x] F3: ოქროს ტექსტი ღია ფონზე არის `#7a560c` (`--ap-gold-text`, `$goldText`). `#e4b23c` რჩება შევსებასა და ნავზე. ტესტი: `paletteContrast.test.ts`.
- [ ] შერწყმა main-ში და დეპლოი არ გაკეთებულა.

## 2026-10-16 — Fix duplicate route names (`route:cache` failed on main bc7a766)
- [x] `dashboard/search_user_cars` → `admin.search_user_cars`, `dashboard/search_wash_services` → `admin.search_wash_services`, `dashboard/search_car_models` → `admin.search_car_models` (were duplicates of `admin.search_clients` / `admin.search_car_brands`).
- [x] `tests/Feature/RouteNamesTest.php`: no duplicate names, each search name resolves to its own URL, `route:cache` succeeds.

## 2026-10-25 — geoapi.ge catalog: logos, generations, modifications, photos (`feat/geoapi-catalog`)
- [x] `CARAPI_URL` default and `.env.example` point to `https://geoapi.ge/api/v1`; `CARAPI_SECRET` is the `geoapi_sk_` key, server only.
- [x] `GET /api/cars/brands` adds `logo` (`logo_url_light`, https only). Models no longer expose upstream `photo_url`.
- [x] `GET /api/cars/models/{model}/generations?brand_id=&year=`, `GET /api/cars/models/{model}/generations/{generation}/modifications?year=`, `GET /api/cars/modifications/{id}` (specs + equipment). Lists cached 6–24 h.
- [x] Public `GET /api/cars/models/{model}/photo?generation=` proxies the geoapi.ge photo with the secret key, stores it on the local disk, caches a 404 for a day. Only ids the server knows (a generation list it loaded, a model a user saved) cost an upstream request.
- [x] `user_cars`: `generation_id`, `generation_name`, `modification_id`, `modification_name`, `body_type`, `photo` (additive, nullable). Names, body type and photo come from the catalog, not from the client. `image` in `mycars` prefers the saved photo.
- [ ] Set `CARAPI_URL` / `CARAPI_SECRET` on the server, run the migration. Live geoapi.ge fields verified only with fakes here.
- [ ] Open question: should `body_type` from the generation pick the package `car_type` (prices)? Not changed.

## 2026-10-26 — Fuel type and model photo fallback (`feat/car-fuel`)
- [x] `user_cars.fuel_type` (nullable, codes in `UserCar::FUEL_TYPES`: regular, premium, super, diesel, electric, gas_petrol, cng, lpg, hydrogen). Add/edit validate it; `mycars` and `car` return it. Labels in `lang/{ka,en,ru}/cars.php`.
- [x] Web AddCar: required fuel select (petrol grades grouped), sent as `fuel_type`. Strings in `gewash/*.json` and `backend/public/lang/*.json`.
- [x] Generations without their own photo return the model photo; the photo proxy also serves a model whose generation list was just loaded.
- [ ] Web AddCar has no generation/trim steps yet (the iOS app has them).

## 2026-10-26 — Corporate clients stage 1 (`claude/project-thread-8emdwh`)

- [x] Vendors (`partners`) have their menu item, list and add page again; corporate clients are named as such in the menu, dashboard and texts.
- [x] Fleet car driver phone and status `invited` / `active` / `blocked` (migration `2026_10_26_000002`). Manual add requires the phone; Excel (4th column „ტელეფონი“) and API take it optionally.
- [x] Invite SMS on add, on phone change and on resend (60 s guard). Activation on driver login, password set or `/api/me`.
- [x] Panel and admin: change driver, resend invite, block/unblock per car. API: `PATCH /api/corporate/vehicles/{plate}` with `phone` and/or `blocked`; `GET` returns `status` and `phone`.
- [x] Company report and dashboard count only washes of activated cars booked after activation (closes the "type any plate, see its washes" leak).
- [x] Small fixes: temporary password button label + confirm, block confirm and status badges, company note field, import lists skipped rows with reasons, company password min 8, API rate limit, `mycars` returns `company`.
- [ ] After deploy, existing fleet cars are `invited` without a phone: companies have to add driver phones before their reports fill again.
- [ ] App (GeocarAPP): show the company badge from `mycars.company`, explain `company_car` errors. Not done here.
- [ ] Next stages: wash packages, discount packages, invoices (see the plan artifact).

## 2026-10-26 — Corporate clients stage 2: wash packages and QR debit (`claude/project-thread-8emdwh`)

- [x] Per-company packages (`corporate_packages`: title, washes, price) created by the admin on the company page. Regular app packages are untouched.
- [x] Company wash pool (`corporate_clients.washes_balance`) with a ledger (`corporate_wash_moves`): admin credit (a package or a manual amount), signed correction with a reason, allocation to cars, wash, deleted car. The pool never drops below the washes on cars.
- [x] Car `mode` (`washes` / `discount`) and `washes_left`, set by the company in the panel (fleet table) and over the API (`PATCH /vehicles/{plate}` with `mode`, `washes`; `GET /balance`). Admin can do the same.
- [x] Driver QR for a company car: `GET /api/mycars/{id}/company-qr` (SVG, `X-QR-Expires`, 5 minutes, reset on driver change and block). `mycars` returns `company_car` (mode, washes left).
- [x] Manager scan: `checkqr` answers `kind: corporate` with company, mode and balances; `POST /api/corporate-wash/{id}/approve` takes one wash from the car and the pool (`corporate_washes` row). Web manager screen shows it.
- [x] Company panel page „რეცხვები“: balance, packages, ledger.
- [x] iOS app: driver QR for a company car and the manager result card (GeocarAPP PR #14).
- [x] A car in `discount` mode can't be approved until stage 3 adds the discount package (done in stage 3).
- [ ] Company reports still count bookings only; walk-in corporate washes are in the ledger and, since stage 3, in the wash history, not in the report yet.

## 2026-10-26 — Corporate clients stage 3: discount package (`claude/project-thread-8emdwh`)

- [x] Company packages move to the admin Packages menu, tab „კორპორატიული პაკეტები“ (`/dashboard/packages/corporate`). Only the Autopass admin makes them; each belongs to one company, picked at creation and fixed after. The company page and the company panel only show them.
- [x] Package `type`: `washes` (stage 2) or `discount`. A discount package has a row per body type (`corporate_package_prices`: full price, percent). A company has one active discount package; saving another active one switches the old one off. (Changed in stage 4: several active packages, one per car.)
- [x] Manager scan of a discount car: `checkqr` returns `corporate.discount` (car_type, full_price, percent, to_pay); approve records the wash with the package, price, percent and what the driver pays on site. The pool and the car's washes stay as they are.
- [x] `mycars` `company_car.discount` tells the driver the price for the car's body type.
- [x] Admin company page and panel: discount table, per-car price in the fleet, wash history with this month's totals (`corporate_washes`, walk-ins included).
- [x] Body type rename and delete keep the price rows in step.
- [x] iOS app: show the discount price to the driver and the manager (GeocarAPP PR #15).
- [x] Stage 4: invoices (see below).

## 2026-10-26 — Corporate clients stage 4: invoices (`claude/project-thread-8emdwh`)

- [x] `corporate_invoices`: number `AP-000001`, package, washes, amount, VAT rate, buyer and seller details copied at issue, due date, status `issued` / `paid` / `cancelled`.
- [x] Company panel „ინვოისები“ (`/partner/invoices`): orders one of its active wash packages that has a price, sees its invoices, prints or saves one as PDF, cancels an unpaid one. At most 3 unpaid invoices at a time.
- [x] Admin: company page block „ინვოისები“ (issue for a package, mark paid, cancel) and the list of all invoices (`/dashboard/corporate/invoices`, unpaid by default). „გადახდილია“ credits the package's washes once; the ledger row links to the invoice.
- [x] Seller details and VAT come from `INVOICE_*` env values (`config/invoices.php`).
- [x] Correction from the user: a company may have several active discount packages, and the company picks the package for each discount car (`fleet_cars.corporate_package_id`, migration `2026_10_26_000006`). Fleet in the panel and admin: package picker under the mode; API: `GET /api/corporate/discounts`, `PATCH /api/corporate/vehicles/{plate}` with `discount_package_id`. Today's discount cars keep the company's active package.
- [x] Who pays the discount difference: the company, by a monthly invoice (see below).
- [ ] A real PDF file (needs a PDF library, which needs approval). Today the invoice is a print-ready page.
- [ ] Stage 5: panel redesign and company API (washes, webhook, docs).

## 2026-10-26 — Corporate clients: monthly discount invoice and discount limit (`claude/project-thread-8emdwh`)

- [x] User's answer: the discount difference (full price minus what the driver paid on site) is billed to the company at month end, after the service.
- [x] `corporate:discount-invoices` runs on the 1st at 01:00 (`routes/console.php`) and makes one `discount` invoice per company for the month that ended, with lines per package, body type and price. Each billed wash points at its invoice (`corporate_washes.corporate_invoice_id`), so a wash is never billed twice. Admin can run it by hand from the invoices list; `--month=YYYY-MM` for a past month.
- [x] A discount invoice credits nothing when paid; only the admin can cancel it, and its washes go into the next run. It doesn't count toward the company's 3 unpaid package orders.
- [x] Discount package limit (user chose per car per month): `corporate_packages.monthly_limit`, empty = unlimited. Over the limit the scan answers `discount_limit`; `discount.monthly_limit` and `discount.left` in `checkqr`, `mycars` and the approve answer. Fleet shows each car's use this month.
- [x] Wash history shows what the company owes for this month so far.
- [ ] Server: the scheduler (`schedule:run` every minute) must be running, as for the other scheduled commands.

## 2026-10-26 — Body types: no photo, fixed list of 16 in Georgian and English (`claude/project-thread-tm2e1r`)

- [x] Migration `2026_10_26_000009_reset_body_types`: drops `body_types.image`, adds `name_ka`, `name_en`, `sort`, and sets the list: sedan, hatchback, liftback, coupe, convertible, wagon, suv, pickup, minivan, van, panel_van, bus, truck, chassis_cab, flatbed_truck, tractor_unit.
- [x] Old types move onto the new keys (crossover/jeep → suv, cabriolet/roadster → convertible, estate/universal → wagon, and so on) in models, packages and discount prices. An unknown type is deleted if unused and kept (with a log warning) if used.
- [x] Dashboard: add/edit take the Georgian and English names; the key is made from the English name and never changes. Lists and selects show the name in the panel's language.
- [ ] Server: `php artisan migrate`.

## 2026-10-26 — Landing from mockup v1.3 (`claude/project-thread-fw494k`)

- [x] `lending/index.html` and new `lending/business.html` rebuilt from the owner's mockup `autopass-v1.3` (ka/en/ru, desktop, tablet ≤1199px, phone ≤700px).
- [x] Assets out of the mockup's base64: FiraGO and Montserrat woff2, logo SVGs, car WebP. Old photos, `src-photos/`, `scripts/` and old fonts removed. New icons and `og.jpg`.
- [x] No inline scripts (CSP `script-src 'self'`); settings in `config.js`, en/ru text and lists in `content/*.js`.
- [x] Mockup forms are not faked: SMS form hidden until `AUTOPASS_SMS_URL`, business form posts to `AUTOPASS_LEAD_URL` or opens an e-mail. App Store buttons open the web app until `AUTOPASS_IOS_URL`.
- [ ] Owner: real prices, branches, phone, e-mail, privacy/terms links (`config.js`, `content/home.js`).
- [x] Admin > Website (`/dashboard/site`): every text of both pages in ka/en/ru, lists (FAQ, package points, branches) with add/edit/delete, sections on/off and order, settings (prices, hours, contacts, App Store, legal links, branches from the car washes), business requests. Landing reads `GET /api/site/{page}`; the business form posts to `POST /api/site/lead`.
- [ ] Server: `php artisan migrate`, then `sh lending/build.sh` and upload `lending/dist/`.

## 2026-10-26 — Admin and company panel in the new brand (`claude/project-thread-fw494k`)

- [x] `backend/public/css/umami-admin.css`: the landing's tokens (navy #133A6B / #0B2545, mint #9CCBB8, sage), FiraGO for Georgian and Montserrat for English (woff2 in `backend/public/fonts/`), navy sidebar with a mint marker on the active item, navy pill buttons, tabs, checks, badges, pagination, login card.
- [x] New logo (`/brand/logo.svg`, `/brand/logo-white.svg`, cropped to the wordmark) and favicon; `theme-color` #0B2545 in the admin, login and company layouts.
- [x] Phone: the page no longer slides off to the left under 992px, and the menu opens under the top bar so its button can close it.
- [x] Company panel: the logout button is readable on the navy sidebar.
- [ ] Not changed: page markup and functions, e-mail templates, the client web app (gewash) still in the old green.

## 2026-10-26 — Body type from the car catalog (`claude/project-thread-fw494k`)

- [x] `BodyType::fromCatalog()` reads our key from a geoapi.ge body name (English, Russian, Georgian words; "pickup truck" → pickup, "coupe SUV" → suv, "cargo van" → panel_van). Unknown names give null.
- [x] New `user_cars.car_type`: the car's own type from the generation it was added with. `UserCar::carType()` = that, else the model's type. Packages, package purchase, company discounts, the QR answer, reports and exports go by it.
- [x] A catalog model added from geoapi.ge takes the most common type of its generations (newest on a tie) instead of always sedan.
- [x] `mycars` and the car payload carry `car_type`; the web app shows it. Admin > Body types shows cars per type, types with cars but no package, and catalog names it could not read.
- [x] `php artisan cars:sync-body-types [--dry-run] [--cars-only]` sets the type of cars and catalog models added before (one cached geoapi.ge request per catalog model).
- [ ] Server: `php artisan migrate`, then `php artisan cars:sync-body-types --dry-run`, check, and run it without `--dry-run`. Make sure every type that has cars has a package.

## 2026-10-26 — Body type from brand, model and year (`claude/project-thread-fw494k`)

- [x] `CarBody` (backend): the body types of the geoapi.ge generations built in the car's year. One type is set on its own; several (a sedan and a wagon) are offered. `GET /api/cars/body-types?source&brand_id&model_id&year` answers `{body_types, choose, car_type}`.
- [x] `mycars/add` and `edit`: without a generation the car takes the year's type, and `car_type` is needed only when the year has several (`422 body_type_required` / `body_type_invalid` with `body_types`). The newest generation of that type gives the name and photo. A changed year reads the type again.
- [x] Web app add-car form: the body type field shows only when the year has several.
- [x] Company cars (`fleet_cars.year`, `car_type`, catalog ids, `generation_id`): the panel form picks brand, model and year from the catalog (`/partner/catalog/*`, `/dashboard/corporate/catalog/*`) and shows the body type only when needed; sheets get `წელი` and `ძარა` columns; the API takes `year` and `body_type` and lists the fitting types when one is missing. The driver's app car is made from this on sign-in. `FleetCar::carType()` (company's type first) prices discounts.
- [x] Company washes per body type (`corporate_wash_pools`): a wash package has a body type and credits that type's pool; a car gets washes from its own type's pool only and keeps its type (`fleet_cars.car_type`) once it has washes. Manual credits and corrections name the type. `GET /api/corporate/balance` adds `types`. The migration splits a company's existing washes: each car's share to its type, the free rest to the type most of its cars have (also given to old wash packages).
- [ ] Server: `php artisan migrate` (000012, 000013), `php artisan route:cache`, rebuild gewash. Check each company's split in Admin > company > Washes and correct it if needed. Tell API clients that `year` is now required.

## 2026-10-27 — Body type confirmed before packages and QR (`claude/project-thread-fw494k`)

- [x] Cars (`user_cars`, `fleet_cars`) get `body_status` (`confirmed` / `choose` / `unknown`), `body_options`, `body_incomplete`, `body_source`, `body_checked_at` (migration `2026_10_27_000001`). `car_type` counts only when confirmed (`carType()` is null otherwise); no fallback to the model's type any more.
- [x] geoapi.ge decides: one type for the year → confirmed on its own; several, or one next to an unnamed variant → `choose` (the driver picks one of them, the server checks it); none or geoapi.ge down → `unknown`, with a retry. A generation, modification and photo are kept only when exactly one fits.
- [x] Until confirmed, on every path: no package priced, bought (`/packages`, `/packages/buy`, TBC checkout) or given by the admin; no QR (`/packages/{id}/qr`, `/mycars/{id}/company-qr`), no `checkqr` token and no approval; no company washes allocated. The driver's other cars work. Confirming grants nothing.
- [x] Packages bought before this change (`user_packages.body_legacy`) keep their QR until they end (user's answer "I don't know" → the safe default; can be flipped).
- [x] App API: `POST /api/mycars/{id}/body {car_type}` (only from the car's types, once), `POST /api/mycars/{id}/body/check` (asks geoapi.ge again). `mycars` and car answers carry `body_status`, `body_types`, `body_incomplete`; `company_car.body_confirmed`. Model or year can't change while a package runs (`car_has_package`).
- [x] Company cars (panel form, Excel/CSV, API): a car with several types is saved as `choose` and counted as added, not failed; the answers list it under `pending`. Fleet lists show the status; panel and admin can ask geoapi.ge again; only the Autopass admin can set a type by hand (also on a client's cars page). Washes given to a car of a type later confirmed as another go back to the company's pool of the old type (totals never change).
- [x] `php artisan cars:check-bodies [--apply] [--unknown] [--limit=N]`: checks cars from before against geoapi.ge (old `car_type` as the hint), prints counts and the cases to look at (running packages, company washes); saves only with `--apply`.
- [x] Web app: My vehicles shows the status, the pick (only the server's types) or the retry; QR page, Home and My packages send an unconfirmed car to the pick instead of a package; add-car saves without a pick.
- [ ] Server (only with the owner's go-ahead): `php artisan migrate`, `php artisan route:cache`, rebuild gewash, run `php artisan cars:check-bodies` and send the report; `--apply` only after it is read. Tell API clients about `pending` and `body_status`.
- [ ] iOS: GeocarAPP PR (same rules); check on a device.

## 2026-10-27 — Add car: generation and modification required (`claude/project-thread-r5ruzb`)

- [x] Cause of Ford Focus 2019 "body type not determined": geoapi.ge's generations had `body_type: null`. geoapi.ge now sends `body_types` (several per generation) and `body_type` + `drive_type` on modifications; `CarBody` reads `body_types` too.
- [x] User rule: a driver adds a car by brand → model → year → generation → modification; the photo comes from the generation. `POST /api/mycars/add` (and an edit that changes the model) refuses a geoapi.ge car without `year` (`year_required`), `generation_id` (`generation_required`) or `modification_id` (`modification_required`) while the catalog lists any for that year, and one not in the year's list (`generation_invalid`, `modification_invalid`). A step the catalog has nothing for is not asked; a model from our own list (catalog down) and a geoapi.ge outage at save time still save the car unconfirmed.
- [x] Body type of a picked trim (`CarBody::trimOptions`): the modification's `body_type`, else the generation's `body_type` or `body_types`. One → confirmed by the catalog; several → `choose` (the driver picks on the car card, the generation and modification stay). A saved car with a generation is checked again by its trim, not by its year.
- [x] Web app add form: generation and modification selects (required when listed), photo after the modification; the body type select is gone.
- [x] iOS: GeocarAPP PR (same steps).
- [ ] Company panel, Excel/CSV, company API and admin car forms: not changed (user chose drivers first).
- [ ] Edit form: brand and model are locked; a year change still reads the body type by year and clears the generation.
- [ ] Server: `php artisan route:cache` not needed (no new routes); rebuild gewash; clear the geoapi.ge cache (`php artisan cache:clear` or the `carapi.*` keys) so generations and modifications carry the new fields.
