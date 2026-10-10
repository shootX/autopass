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
