# CSP — SPA წყაროები

თარიღი: 2026-10-15. ეს არის სათაურის შეთავაზება. nginx და სერვერი ამ ფაილით არ იცვლება.

## gewash

| დირექტივა | წყარო |
|---|---|
| script-src | `'self'` — `/src/main.tsx` (ბილდის შემდეგ საკუთარი JS). გარე სკრიპტი არ არის. |
| worker-src | `'self' blob:` — MapLibre-ს worker. |
| style-src | `'self' 'unsafe-inline'` — `maplibre-gl.css`, React-ის `style` ატრიბუტები. |
| img-src | `'self' data: blob:` — საკუთარი SVG/PNG, QR და რუკის კანვასი. |
| font-src | `'self'` — Plus Jakarta Sans და Noto Sans Georgian (თვითჰოსტირებული). |
| connect-src | `'self'`. ნაგულისხმევი API არის `https://wash.socialsave.cc/api` (იგივე origin). თუ `VITE_API_URL` სხვა origin-ია, დაამატე მხოლოდ ის origin. რუკა: `/maps/georgia.pmtiles`, `/maps/fonts/`. |
| frame-src | `'none'` |
| frame-ancestors | `'self'` |
| object-src | `'none'` |
| base-uri | `'self'` |
| form-action | `'self'` |

`https://api.mapbox.com` აღარ გამოიყენება (preconnect მოხსნილია). Leaflet კოდში არ იძახება.

შეთავაზებული სათაური:

```
Content-Security-Policy: default-src 'self'; script-src 'self'; worker-src 'self' blob:; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'none'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'
```

თუ ცოცხალი რუკა worker-ზე WASM-ს მოითხოვს, მხოლოდ მაშინ დაემატოს `script-src`/`worker-src`-ში `'wasm-unsafe-eval'`. ჯერ ნუ ჩართავ.

სხვა სათაურები (ოპერაციული, ამ PR-ში სერვერზე არ იდება): `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(self), geolocation=(self)`, `Strict-Transport-Security` `includeSubDomains`-ით.

## voucher-checker

სკრიპტი, სტილი და ფონტი მხოლოდ `'self'`. რუკა და worker არ არის. `connect-src` არის `'self'` და API-ის origin (`VITE_API_URL`, ნაგულისხმევად იგივე ჰოსტი). `frame-src 'none'`.

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src 'none'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'
```
