# ამოცანები

## 2026-10-12

- [x] set_password: `/register/verify`-ზე შეყვანილი SMS კოდი რჩება კომპონენტის state-ში და იგზავნება `code`-ად `POST /api/register/set_password`-ზე (`temp_code`-თან ერთად). `sms_code` რეგისტრაციის პასუხიდან აღარ იკითხება.
- [x] FE-03: ავტორიზებული მოთხოვნის `401` შლის `access_token`-ს და ხსნის `/auth`.
- [x] FE-04: რეგისტრაციის რეფერალის IP აღარ იძახებს `https://back.geocar.ge/api/ref/ip`. იყენებს `{VITE_API_URL}/ref/ip`.
