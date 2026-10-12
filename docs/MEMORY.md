# მეხსიერება

## 2026-10-12 — set_password, FE-03, FE-04

`POST /api/register/set_password` ითხოვს `{ temp_code, code, password }`. `code` არის იგივე 6-ნიშნა SMS, რომელიც მომხმარებელმა შეიყვანა `/register/verify`-ზე (15 წუთი, ერთჯერადი — backend PR #7). კოდი ინახება მხოლოდ რეგისტრაციის კომპონენტის state-ში, არა `localStorage`-ში. გვერდის განახლება ამ ეტაპზე კოდს კარგავს და დადასტურება თავიდან სჭირდება.

რეგისტრაციის პასუხიდან იკითხება მხოლოდ `temp_code`. `sms_code` არ გამოიყენება.

FE-03: `customFetch` `401`-ზე, თუ მოთხოვნას ჰქონდა ნამდვილი `Authorization: Bearer`, შლის ტოკენს და გადადის `/auth`-ზე. ლოგინის `401` (ტოკენის გარეშე) სესიას არ ეხება.

FE-04: რეფერალის IP არის `{VITE_API_URL}/ref/ip` (`GET /api/ref/ip`). `backend/resources/views/referral/redirect.blade.php` კვლავ იძახებს `back.geocar.ge`-ს — ეს Blade-ია, ამ PR-ში არ შეცვლილა.

ეს PR უნდა შერწყმულიყოს backend PR #7-თან ერთად. #7-ის გარეშე `set_password` ძველ კონტრაქტს ელოდება და `code` ზედმეტი ველი იქნება; #7-ის გარეშე frontend პაროლს ვერ დააყენებს.
