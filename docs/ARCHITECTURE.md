# არქიტექტურა — autopass

## ნაკადი

```
კლიენტი / მენეჯერი
  → gewash (React SPA, Vite)
  → VITE_API_URL + /api
  → Laravel (JWT guard api)
  → PostgreSQL (DB_CONNECTION=pgsql)

ადმინი
  → ბრაუზერი, სესია
  → /dashboard (AdminMiddleware)
  → იგივე Laravel და ბაზა

პარტნიორი (ვაუჩერი)
  → gewash/voucher-checker
  → POST /api/partner/login, /voucher/check, /voucher/use
  → JWT guard partners

კორპორატიული კლიენტი
  → /partner (სესია, CorporatePanel)
  → ან /api/corporate/vehicles (Bearer / X-Api-Key = corporate_clients.api_token)

სტუმარი
  → lending/ (სტატიკური HTML/CSS/JS)
  → ბმულები config.js → აპის /auth და /register

გადახდა
  → Flitt webhook /callback/flitt/payment
  → TBC webhook /callback/tbc/payment და დაბრუნება /payment/return
  → redirect {APP_URL}/payment-success
  → gewash კითხულობს sessionStorage გასაღებს ap_payment_pending
```

API პრეფიქსი Laravel-ის ნაგულისხმევია: `routes/api.php` იხსნება `/api`-ზე. დაფის მარშრუტები `routes/dashboard.php`-შია და `web` middleware-ზეა (`bootstrap/app.php`). ჯანმრთელობის მისამართია `/up`.

ტოკენი კლიენტის აპში ინახება `localStorage.access_token`. ენის JSON ინახება `sessionStorage`-ში (`gewash_lang:{ka|en|ru}`). თარგმანის მოთხოვნას აქვს 8 წამიანი timeout (`App.tsx`). `VITE_BYPASS_AUTH=true` ტოკენის გარეშე დებს preview მომხმარებელს და `main.tsx`-ში რთავს `installPreviewFetch`-ს. პროდაქშენზე ეს ფლაგი არ უნდა იყოს (`PR #3`).

## სტეკი

ფაქტი `composer.json`, `package.json` და `.env.example`-დან.

| ფენა | ტექნოლოგია |
|---|---|
| Backend | PHP `^8.2`, Laravel `^13`, Sanctum `^4`, `tymon/jwt-auth` `^2.2`, Laravel UI `^4.6` |
| ბაზა | კონფიგი: PostgreSQL 16-ის პორტი `5432`, ბაზა და მომხმარებელი `autopass` (`backend/.env.example`). სესია, რიგი და ქეში: `database`. PHPUnit ტესტი: SQLite `:memory:` |
| გადახდა | `flittpayments/php-sdk`, TBC HTTP (`TBC_BASE_URL` ნაგულისხმევად `https://test-api.tbcbank.ge`) |
| სხვა PHP | Hashids, Maatwebsite Excel, SimpleSoftwareIO QR, OneSignal HTTP |
| კლიენტის აპი | React `^19.2`, Vite `^7`, TypeScript `~5.8`, Redux Toolkit, React Router `^7` |
| UI | Tailwind `^3.4` (`tailwind.config.js`) და `@tailwindcss/vite` `^4`, SCSS, Radix Dialog/Slot/Switch, CVA, lucide-react, framer-motion |
| რუკა | Leaflet, react-leaflet, MapLibre, PMTiles, react-map-gl. ცოცხალი რუკა მხოლოდ `VITE_LIVE_MAP=true` |
| voucher-checker | React `^19.2`, Vite `^8`, TypeScript `~5.9`. სხვა UI ბიბლიოთეკა არ აქვს |
| ადმინის asset | Laravel Vite, Bootstrap `^5.2`, Tailwind `^4`, Sass |
| Landing | სტატიკური ფაილები. ფონტი woff2. სურათი AVIF/WebP/PNG |
| ხარისხი | gewash: ESLint 9 + typescript-eslint. Backend: PHPUnit `^12`, Laravel Pint. ავტომატური frontend test runner `package.json`-ში არ არის |

`memory.md` ადგილობრივ გარემოს PHP 8.4-ად და აწეულ, ცარიელ PostgreSQL სქემად (36 ცხრილი, seeder გაშვებული არ არის) აღწერს. ეს რეპოს snapshot-ის ჩანაწერია, არა `composer.json`-ის ქვედა ზღვარი.

## მონაცემები

ძირითადი მოდელები `backend/app/Models`-ში:

- ფილიალი: `CarWash`, სერვისი `CarWashService`, კავშირი `CarWashServicesList`. მენეჯერი `manager_id`.
- მანქანა: `CarBrand`, `CarModel`, `BodyType`, კლიენტის მანქანა `UserCar`.
- ჯავშანი: `Appointment` + `AppointmentsService`. ველი `approved` ინახავს სტატუსს. API იღებს მთელ რიცხვს 1–5. ადმინის სია: `0` მოლოდინი, `1` დადასტურებული, `2` გაუქმებული, `3` დასრულებული (`WashReportQuery::statusLabel`). API-ში `1` არის დადასტურების push, `2` უარყოფა და წაშლა, `3` გადატანის push.
- პაკეტი: `Package`, `PackagePrice`, `UserPackage` (QR, ვადა, დარჩენილი რეცხვა).
- მაღაზია: `Ticket` / `UserTicket`, `Voucher` / `UserVoucher` / `VoucherCategory`.
- ქულები: `users.points`, `Transaction`.
- გადახდა: `TbcPayment`. Flitt-ის ლოგიკა `FlittCheckout` და `FlittWebHookController`.
- კორპორატიული: `CorporateClient`, `FleetCar`.
- სხვა: `Review`, `Faq`, `Contact`, `Promo`, `SmsTemp`, `VerificationCode`, `UserPushToken`, `ReferralCodeTemp`, `Partner`.

PostgreSQL-ზე `enum()->change()` არ გამოიყენება. `->after()` და `unsigned*` იგნორირდება. `users.sex` არის `varchar` + check (`male`, `female`). ჯავშნის `date` არის `date`, `time` არის `time`. დროის ზონა ბრაუზერით არ გადაითვლება (`docs/gewash-frontend-integration.md`).

## საქაღალდეები

```
backend/                 Laravel
  app/Http/Controllers/  Api, Admin, Auth, WebHook
  app/Services/Payments  FlittCheckout, TbcCheckout, SettleTbcPayment
  app/Models app/Jobs app/Console/Commands
  routes/                api.php, web.php, dashboard.php, console.php
  resources/views/       admin/, partner/, payments/
  database/migrations
  public/lang            ka.json, en.json, ru.json
gewash/                  კლიენტის და მენეჯერის SPA
  src/components/        ცოცხალი ეკრანები (pages, Layouts, Calendars, ui, v4)
  src/hooks src/store src/lib src/styles src/assets/brand
  voucher-checker/       პარტნიორის მინი-აპი
lending/                 მარკეტინგის გვერდი (index.html, styles.css, site.js, config.js)
docs/                    ეს ფაილები და ძველი coverage/integration ჩანაწერები
memory.md                მოკლე სახელმძღვანელო (სტეკი, როლები, PostgreSQL)
fix.md                   ღია და დახურული ხარვეზები
```

`gewash/src/pages` და ძველი ასლები წაშლილია. ახალი ეკრანი იწერება `gewash/src/components`-ში.

Redux (`gewash/src/store/index.ts`): `orders`, `user`, `car`, `appointments`, `plateSlice`, `lang`.

ფრონტის მოთხოვნა გადის `customFetch`-ში (`gewash/src/utils/customFetch.ts`): ngrok ჰოსტზე ამატებს `ngrok-skip-browser-warning`-ს, სხვა შემთხვევაში ჩვეულებრივი `fetch`-ია.

## გარემო

- `gewash/.env`: `VITE_API_URL`, `VITE_NO_API_URL`, `VITE_BYPASS_AUTH`, `VITE_MAPBOX_TOKEN`, `VITE_LIVE_MAP`.
- `backend/.env`: `DB_*`, `JWT_SECRET`, `FLITT_PAY_NUMBER`, `FLITT_PAYMENT_KEY`, `TBC_*`, `CARAPI_URL`, `CARAPI_SECRET`.
- `gewash/voucher-checker/.env`: `VITE_API_URL`, `VITE_AUTH_LOGIN_PATH` (ნაგულისხმევი `/partner/login`), `VITE_VOUCHER_CHECK_PATH`, `VITE_VOUCHER_USE_PATH`, `VITE_MOCK_VOUCHER_CHECK`.
