# GeWash — პროექტის სახელმძღვანელო

მანქანის სამრეცხაოს აპლიკაცია. კლიენტი ჯავშნის რეცხვას, ყიდულობს პაკეტს, ქულებს, ბილეთს და ვაუჩერს. მენეჯერი ამოწმებს QR-ს და ცვლის ჩანაწერის სტატუსს. ადმინი მართავს ფილიალებს Blade დაფიდან.

## სტრუქტურა

- `backend/` — Laravel 13, PHP 8.4. API, ადმინის დაფა, cron ბრძანებები.
- `gewash/` — React 19 + Vite + Redux. კლიენტის და მენეჯერის ეკრანები ცხოვრობენ `gewash/src/components`-ში.
- `fix.md` — ცნობილი ხარვეზების სია. ფულის, ანგარიშის და ბიზნეს ლოგიკის პუნქტები ღიაა.

API პრეფიქსია `/api`. ადმინის მარშრუტებია `backend/routes/dashboard.php`, პრეფიქსი `/dashboard`. ვებჰუკი: `POST /callback/flitt/payment`.

ფრონტის API მისამართი: `gewash/.env` → `VITE_API_URL`.

## მონაცემთა ბაზა

PostgreSQL 16. ჩართულია.

| | |
|---|---|
| კავშირი | `pgsql` |
| ჰოსტი | `127.0.0.1:5432` |
| ბაზა | `autopass` |
| მომხმარებელი | `autopass` |
| პაროლი | `backend/.env` → `DB_PASSWORD` |

სქემა აწეულია: `php artisan migrate` გავლილია, 36 ცხრილი `public` სქემაში. ბაზა ცარიელია, სიდერი არ გაშვებულა.

`backend/.env`: `DB_CONNECTION=pgsql`, პორტი `5432`. სესია, რიგი და ქეში `database` დრაივერზეა.

## PostgreSQL-ზე გადასვლისას შეცვლილი

- `SendManagerFifteenMinutesBefore` — MySQL `STR_TO_DATE` შეიცვალა `(date + time)`-ით. `appointments.date` არის `date`, `time` არის `time`.
- `StatisticsController` გრაფიკი აჯგუფებს `date` სვეტით. MySQL `DATE()` PostgreSQL-ზე არ მუშაობს.
- `2026_09_23_201500_make_user_profile_fields_nullable` — `enum()->change()` PostgreSQL-ზე არასწორ `ALTER TYPE ... check` SQL-ს აგენერირებდა. ახლა არის `ALTER COLUMN ... DROP NOT NULL`. `users.sex` რჩება `varchar` + check (`male`, `female`).

`->after()` PostgreSQL-ზე იგნორირდება. `unsigned*` იგნორირდება. `enum` იქმნება როგორც `varchar` + check.

## როლები

`users.role`: `0` კლიენტი, `1` მენეჯერი, `2` ადმინი. კონსტანტები: `User::ROLE_USER`, `ROLE_MANAGER`, `ROLE_ADMIN`.

API ავტორიზაცია JWT (`auth:api`). პარტნიორი ცალკეა: `auth:partners`, ცხრილი `partners`. ადმინის დაფა სესიით შედის (`Auth::routes`, რეგისტრაცია გამორთულია).

## დომენი

ძირითადი მოდელები `backend/app/Models`-ში:

- ფილიალი: `CarWash`, სერვისები `CarWashService`, კავშირი `CarWashServicesList`. მენეჯერი `manager_id`.
- მანქანა: `CarBrand`, `CarModel`, კლიენტის მანქანა `UserCar`.
- ჯავშანი: `Appointment` + `AppointmentsService`. სტატუსი 1–5. `approved` არის boolean.
- პაკეტი: `Package`, `PackagePrice`, `UserPackage` (QR, ვადა, დარჩენილი რეცხვა).
- მაღაზია: `Ticket` / `UserTicket`, `Voucher` / `UserVoucher` / `VoucherCategory`.
- ქულები: `users.points`, `Transaction` (`income` / `outcome`).
- სხვა: `Review`, `Faq`, `Contact`, `Promo`, `SmsTemp`, `VerificationCode`, `UserPushToken`, `ReferralCodeTemp`.

ენები: `ka`, `en`, `ru` ფაილებში `backend/public/lang/`. ძველი კოდი `ge` იკითხება როგორც `ka`.

გადახდა Flitt-ით. დაბრუნება მიდის `https://app.geocar.ge/`.

## განრიგი

`backend/routes/console.php`:

- ყოველ წუთში: `sms:remove`, `user-packages:check`, `app:send-manager-fifteen-minutes-before`
- 14:00: პაკეტის დასრულებამდე 3 დღით ადრე
- 15:00: პაკეტის დასრულებამდე 1 დღით ადრე

## წესები ამ პროექტზე მუშაობისას

- დიზაინი არ იცვლება, სანამ პირდაპირ არ არის ნათქვამი.
- ცოცხალი ეკრანებია `gewash/src/components`. `src/pages` და მსგავსი ასლები წაშლილია.
- ფულის, SMS-ის და ავტორიზაციის ღია ხარვეზები აღწერილია `fix.md`-ში. მათ არ შეეხო, თუ დავალება ამას არ ეხება.
- ახალი მიგრაცია PostgreSQL-ისთვის დაიწეროს. `enum()->change()` არ გამოიყენო.
