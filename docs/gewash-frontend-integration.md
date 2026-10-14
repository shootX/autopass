# GEWASH frontend integration gaps

Live რეჟიმი ნაგულისხმევია. `VITE_DEMO` არ ირთვება და ყალბი წარმატება არ იგზავნება.

| ოპერაცია | Endpoint | UI | მდგომარეობა |
|---|---|---|---|
| შესვლა/რეგისტრაცია/OTP | /login, /register, /register/verify | P01–P03, P05 | ready |
| პაროლი token-ით | არ არის | /reset-password | missing. ცოცხალი გზაა /change_password OTP |
| პირობების ტექსტი | არ არის | /terms | missing. /privacy არსებობს |
| ჩემი ჯავშნები | GET /myappointments | /bookings | ready |
| ჯავშნის წაშლა | DELETE /appointments/:id/remove | დეტალი | ready, თუ სერვერი დაუშვებს |
| მომხმარებლის გადატანა | POST /appointments/:id/edit | /bookings/:id/reschedule | missing უფლება: კონტროლერი მხოლოდ მენეჯერს უშვებს |
| პაკეტის ყიდვა | POST /packages/buy | checkout | ready, თუ FLITT აბრუნებს url-ს. url-ის გარეშე პაკეტი არ აქტიურდება UI-დან |
| ქულებით ყიდვა | POST /vouchers/buy, /tickets/buy | /shop/:id/checkout | ready |
| შეკვეთის ჩანაწერი | არ არის | /orders/:id | missing |
| გათამაშების შედეგი | არ არის | /my-area/giveaways/:id | missing |
| შეტყობინებები/დიალოგი | არ არის | /messages | missing |
| მხარდაჭერის ბილეთი | არ არის | /support | missing. მხოლოდ mailto |
| მენეჯერის შეკვეთა სხვის კლიენტზე | არ არის | /manager/booking | missing. /appointments/add იწერს ავტორიზებულ მომხმარებელს |
| ანგარიშის წაშლა | POST /account/delete | პარამეტრები | ready |
| სესიის 401 | — | /session-expired | გვერდი არის, გლობალური redirect არ დაემატა |
| maintenance | — | /maintenance | სერვერის დროშა არ არის |

დროის ზონა ჯავშნებზე რჩება სერვერის `date` + `time` ველებზე. ბრაუზერის timezone-ით დღე არ გადაითვლება.
## Car catalog contract (2026-10-14, stage 2 item 6)

Ids from the catalog live in two id spaces. Every catalog item now says which one.

- `GET /api/cars/brands` → `brands[]: { id, name, source }`. `source` is `"carapi"` (id = CarAPI brand id) or `"local"` (id = our `car_brands.id`, used when CarAPI is down/unconfigured).
- `GET /api/cars/brands/{brand_id}/models` → `brand: { id, name, source }`, `models[]: { id, name, source }`. Same meaning.
- `GET /api/cars/years` (new, auth) → `years: number[]`, newest first, from current year + 1 down to 1950.
- `POST /api/mycars/add` body: `{ plate, source, model_id, brand_id?, year? }`
  - `source` is **required** whenever `model_id` is sent; copy it from the picked model.
  - `brand_id` is required when `source = "carapi"` (the picked brand's `id`).
  - `year` optional integer 1950..current+1.
  - `model_id` without `source` → 422. Unknown model → 422 (was 400).
  - Legacy `{ brand_name, model_name }` still works.
- `POST /api/mycars/{id}/edit`: same fields (all optional); `year: null` clears it.
- `GET /api/mycars` → each car adds `year` (number|null); `model` adds `source` and `external_model_id` (CarAPI id or null). `model.id` is always our local id.

Backend: `car_brands.source/external_brand_id`, `car_models.source/external_model_id` (unique per source), `user_cars.year`. Additive migration; existing rows become `source=local`, no ids change. CarAPI picks are upserted to a local row by (source, external id) after checking them against CarAPI.

Frontend change (gewash AddCar.tsx): send `source: model.source, brand_id: brand.id, model_id: model.id` plus `year` from a picker fed by `/api/cars/years`. Deploy backend and frontend together: the current frontend sends `model_id` without `source` and will get a 422 (before this, it silently attached the wrong model when a CarAPI id matched a local id).
