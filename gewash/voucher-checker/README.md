# Voucher Checker (mini app)

Standalone mini app to validate voucher codes against backend.

## Run

```bash
cd voucher-checker
npm install
cp .env.example .env
npm run dev
```

## Environment

Copy `.env.example` to `.env` and adjust:

- `VITE_API_URL` — API base URL (no trailing slash), e.g. `https://your-host/api`
- `VITE_AUTH_LOGIN_PATH` — partner login route (default `/partner/login`). **POST** body: `{ "login": "...", "password": "..." }`. Response should include `access_token` or `token` (or nested under `data`).
- `VITE_NGROK_SKIP_BROWSER_WARNING` — set `true` when using ngrok free tier (adds `ngrok-skip-browser-warning` header).
- `VITE_VOUCHER_CHECK_PATH` — default `/partner/voucher/check`. **POST** `{ "code": "<voucher>" }` + Bearer. On success the API sends SMS to the user and returns **`temp_code`**.
- `VITE_VOUCHER_USE_PATH` — default `/partner/voucher/use`. **POST** `{ "temp_code": <from check>, "code": "<SMS digits>" }` + Bearer. On success **`success: true`** — voucher is redeemed. To resend SMS, call **`/check`** again with the same voucher `code`.
- `VITE_MOCK_VOUCHER_CHECK` — default `false` (real HTTP). Set `true` only to work on layout without a backend (no network).

## Auth

- **Login** calls `POST {VITE_API_URL}{VITE_AUTH_LOGIN_PATH}` and stores the token in `localStorage` (`voucher_checker_access_token`).
- **Voucher check** (mock off): `POST …/check` with voucher `code` → store `temp_code` from JSON.
- **Voucher use** (mock off): `POST …/use` with `temp_code` + SMS `code`, then show success if `success: true`.
