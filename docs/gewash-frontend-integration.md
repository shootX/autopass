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
- `GET /api/cars/years?source=carapi&brand_id={id}&model_id={id}` (auth) → `{ success, years: number[], source: "catalog" | "manual" }`. Catalog years come from `/api/v1/models/{id}/generations`; missing/unavailable coverage uses a clearly marked manual range 1950..current+1. The year-response `source` describes provenance, whereas model/brand `source` describes the id space.
- `POST /api/mycars/add` body: `{ plate, source, model_id, brand_id?, year? }`
  - `source` is **required** whenever `model_id` is sent; copy it from the picked model.
  - `brand_id` is required when `source = "carapi"` (the picked brand's `id`). With `source = "local"` it is optional, but if sent the model must belong to that brand (else 422). If CarAPI is down, a CarAPI pick is accepted only if it was mapped before under the same brand.
  - `year` optional integer 1950..current+1 for legacy API callers; the new AddCar form requires it. When the remote catalog publishes model years, a submitted year outside that list is rejected.
  - `model_id` without `source` → 422. Unknown model → 422 (was 400).
  - Legacy `{ brand_name, model_name }` still works.
- `POST /api/mycars/{id}/edit`: same fields (all optional); `year: null` clears it.
- `GET /api/mycars/{id}` → `{ success: true, car: { id, plate, year, model, brand, image } }`, owned car only, 404 otherwise. Direct EditCar navigation reads this object.
- `POST /api/mycars/add` success returns the same `car` object; frontend stores its real id. Duplicate plate errors include `error_code: "plate_exists"`.
- `GET /api/mycars` → each car adds `year` (number|null); `model` adds `source` and `external_model_id` (CarAPI id or null). `model.id` is always our local id.

Backend: `car_brands.source/external_brand_id`, `car_models.source/external_model_id` (unique per source), `user_cars.year`. Additive migration; existing rows become `source=local`, no ids change. CarAPI picks are upserted to a local row by (source, external id) after checking them against CarAPI.

Frontend (Stage 3): AddCar sends `source: model.source, brand_id: brand.id, model_id: model.id, year` and normalized `plate`. Changing brand/model clears dependent fields and stale results cannot overwrite the current selection. EditCar works from a direct URL/refresh and validates a changed year against an existing remote model when coverage is available. Backend and frontend form one release; publication/deployment is still pending.

## Appointment statuses — 2026-10-14

Shared codes: 0 pending, 1 confirmed, 2 cancelled, 3 completed, 4 expired, 5 rescheduled. A moved date/time produces 5. Cancellation retains history; terminal bookings cannot be reactivated/rescheduled. Client upcoming lists exclude 2/3/4. Manager, reports and statistics use the same meanings. New booking validation errors follow `Accept-Language: ka|en|ru`.
