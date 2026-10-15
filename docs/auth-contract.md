# Auth, refresh and package-approve contract (API ↔ gewash)

Updated 2026-10-15 (PR #28, QA round 2). Backend: `backend/app/Http/Controllers/Api/AuthController.php`,
`PackagesController::managerApprove`, `AppointmentsController::checkQr`, `app/Services/Security/RefreshTokens.php`.

## 1. Tokens

| Token | Where it lives | Lifetime | Sent as |
|---|---|---|---|
| Access token (JWT) | JS memory only (never localStorage) | `JWT_TTL` minutes (1440) | `Authorization: Bearer <access_token>` |
| Refresh token (opaque, 64 hex) | httpOnly cookie `ap_refresh`, JS can't read it | `min(JWT_REFRESH_TTL, remaining session)` | browser sends it automatically to `/api/refresh` only |

Refresh cookie attributes: `ap_refresh=<token>; Path=/api/refresh; HttpOnly; Secure; SameSite=Strict; Expires=…`.
No Domain attribute (host-only on the API host). `REFRESH_COOKIE_SECURE=false` exists only for plain-http local dev.

`SameSite=Strict` means the SPA and the API must be **same-site** (same registrable domain, e.g.
`app.example.ge` + `api.example.ge`, or same host). A cross-site SPA would never get the cookie back.

The absolute session lifetime is `SECURITY_SESSION_MAX_MINUTES` (default 43200 = 30 days) from the
login, regardless of how often it is refreshed.

## 2. Endpoints

All requests from the SPA that may set or send the cookie must use `credentials: 'include'`
(fetch) / `withCredentials: true` (axios): `/api/login`, `/api/register/set_password`,
`/api/refresh`, `/api/logout`. CORS: only origins in `CORS_ALLOWED_ORIGINS` (comma list, `*` is
ignored) get `Access-Control-Allow-Origin`; `Access-Control-Allow-Credentials: true`.

### POST /api/login
Body `{phone, password}` → 200
`{success, access_token, token_type: "bearer", expires_in, user}` + `Set-Cookie: ap_refresh=…`.
Errors unchanged (401 bad credentials, 403 `password_reset_required: true` / not confirmed / blocked).

### POST /api/register → /register/verify → /register/set_password
`/register` and `/change_password` (and `/change_phone`) always answer
`200 {success: true, temp_code, delivery: "accepted"|"simulated", message}`; the SMS is sent by a
queued job, so `delivery` no longer reports gateway failures (a failed send just makes the code
invalid; the user retries after the resend window). `/register/set_password` returns the same body as
login and sets the refresh cookie.

### POST /api/refresh  (public route, no Authorization needed)
- **Cookie flow (preferred):** send with `credentials: 'include'`, no body.
  200 `{success, access_token, token_type, expires_in}` + a **new** `Set-Cookie: ap_refresh` (rotated).
  - 401 `{success:false}` + cookie cleared: unknown/expired cookie, session past its max, password
    changed / logged out everywhere / banned / reset required, or **reuse of an already rotated
    cookie** (the whole session family is revoked — the newer cookie stops working too).
  - 409: a parallel refresh with the same cookie is in progress. Retry once after a short delay;
    don't log the user out on 409 or on a network error.
- **Legacy bearer flow (compat, deprecated):** no cookie, `Authorization: Bearer <still-valid access token>`.
  Rotates the access token as before (old one blacklisted; parallel → 409; past session max → 401) and
  also sets the refresh cookie, moving the client onto the cookie flow. Remove once the SPA uses the cookie.

### POST /api/logout
`Authorization: Bearer …`, `credentials: 'include'` → 200; blacklists the access token, revokes the
refresh family and clears the cookie.

### Suggested SPA flow
1. Login → keep `access_token` in memory.
2. On page load / new tab: `POST /api/refresh` with credentials → if 200, the user is still signed in
   (this fixes the "reload logs out" note U1); if 401, show login.
3. Shortly before `expires_in` or on a 401 from the API: `POST /api/refresh` once, retry the request.

## 3. Package approve (manager)

1. `GET /api/checkqr/{qr}` (manager) → `{success, approve_token, package: {...}}`. The token is
   one-time, bound to this manager and package, valid 10 minutes.
2. `POST /api/package/{id}/approve` body `{approve_token}` (`qr_code` may still be sent but is ignored).
   - 200 `{success, package}`: one wash deducted.
   - 403: missing/used/foreign token (`Scan the package QR code`), wrong branch, not a manager.
   - 409: another approval of this package (or this token) is running — rescan, don't auto-retry.
   - 422: validation (non-string fields), package not active/expired, no washes left.
   A bare package id or the static QR never approves, booking or not. A booking today at this branch
   only decides the branch check and is linked to the first wash approved for it.
