# მეხსიერება

## 2026-10-12 — FE-06, FE-07, FE-08, FE-09, FE-10

FE-06: `docs/gewash-frontend-coverage.md` აღწერს `gewash/src/quiet/`-ს, რომელიც რეპოში არ არის. დაემატა მხოლოდ ის alias-ები, რომელთა კომპონენტი უკვე არსებობს: `/home`, `/qr`, `/reviews`, `/referrals`, `/calendar`, `/customer-calendar` (აღარ მიდის `/shop`-ზე), `/profile/cars`, `/profile/cars/new`, `/profile/cars/:id/edit`, მენეჯერის `/manager/calendar|bookings|scanner|statistics|profile|contacts|help|settings|reschedule/:id`. `/profile` კვლავ ProfilePage-ია (ჰაბი), არა CustomerMyData — გადაფარვა არსებულ ეკრანს წაშლიდა. Catch-all აღარ არის redirect; არის `NotFound`.

არ გაკეთდა, რადგან ეკრანი ან API არ არის: `/reset-password`, `/verify-email`, `/terms`, `/bookings*`, `/packages*`, `/my-points/history`, ვაუჩერის/ბილეთის დეტალი, `/shop/:id/checkout`, `/orders*`, `/reviews/new`, `/messages/:id`, `/notifications`, `/help/:slug`, `/support`, settings-ის ქვეგვერდები, `/session-expired`, `/403`, `/500`, `/maintenance`, `/offline`.

FE-07: `editMyCar` / `removeMyCar` `success: false`-ზე შეცდომას აჩვენებს და აღარ გადადის შენახულად. BE-10 (წარმატების ცრუ `success: true`, როცა სტრიქონი არ იცვლება) backend-ია და აქ არ შეცვლილა.

FE-08: `expires_in` ინახება `access_token_expires_at`-ში. `dropExpiredAccessToken` იძახება `main.tsx`-იდან რენდერამდე. ტოკენი კვლავ `localStorage`-შია, რომ განახლებამ სესია არ მოკლას. ~7776000 წამი (90 დღე) არის `backend/config/jwt.php` `JWT_TTL` (ნაგულისხმევი 129600 წუთი). ამის შემცირება backend-ის საქმეა და აქ არ გამოგონილა.

FE-09 / UX-12-ის ნაწილი: `lending/` `/`-ზე, თუ `access_token` არის, ხდება `location.replace` `{APP_BASE}/home`-ზე. ეს მუშაობს მხოლოდ მაშინ, თუ ვებ-სერვერი `/home`-ს gewash-ის `index.html`-ზე უშვებს ისე, როგორც `/auth`-ს. nginx root = marketing ამ რეპოში არ იცვლება. სერვერს არ შევხებივარ.

FE-10: `CustomerMyData` hash `#vehicles`-ზე იძახებს `scrollIntoView`. `#vehicles` არის `MyVehicles`-ის wrapper-ზე.
