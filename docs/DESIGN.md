# დიზაინი — autopass

წყარო: `gewash/src/assets/brand/palette.json`, `tokens.css`, `gewash/src/index.css`, `gewash/tailwind.config.js`, `gewash/src/styles/_variables.scss`, `autopass.scss`, `v4.scss`, `lending/styles.css`, `backend/public/css/umami-admin.css`. ნიშანი აღწერილია `gewash/src/assets/brand/README.md`-ში (brand kit v1.0, concept #8).

პალიტრა არის forest / lime. ძველი Geocar-ის navy/gold და PR #1-ის `#12284b` / `#e4b23c` `main`-ზე აღარ არის. SCSS-ის ძველი სახელები (`$color-blue-*`, `$color-yellow-*`, `$font-roboto`) შენარჩუნებულია, მნიშვნელობები ახალი ბრენდისაა (`_variables.scss`).

## ფერები

| Token | HEX | გამოყენება |
|---|---|---|
| forest | `#14482F` | ძირითადი მწვანე, ლოგო, header, `--c-primary` |
| lime | `#B5DD3A` | აქცენტი, ძირითადი CTA, აქტიური tab |
| lime-deep | `#8DB523` | lime-ის hover / დაჭერა |
| pine | `#0B2A1B` | ყველაზე მუქი მწვანე, dark ფონი ტოკენებში |
| moss | `#2D7F50` | შუა მწვანე, მეორე მოქმედება |
| mint | `#DCEFD0` | ჩიპი, არჩეული რიგი |
| mist | `#EEF6E8` | სექციის ფონი |
| ink | `#0E1712` | ტექსტი, wordmark, v4-ის მუქი პანელი |
| foam | `#F7F9F4` | აპის ფონი |
| white | `#FFFFFF` | ბარათი, v4 ეკრანის ფონი |
| gray-100 | `#EDF0EC` | გამყოფი, input-ის შევსება |
| gray-200 | `#DCE1DB` | ჩარჩო |
| gray-400 | `#A2ABA4` | გამორთული, placeholder |
| gray-600 | `#5E6A62` | მეორე ტექსტი |
| gray-800 | `#2B342E` | ძლიერი ტექსტი |
| success | `#1E9E5A` | დადასტურება |
| warning | `#F0A020` | ვადა, ყურადღება |
| error | `#D64541` | შეცდომა |
| info | `#2E86D6` | ინფო, რეცხვის მიმდინარეობა |

სემანტიკა `index.css`-ში: `--c-bg` foam, `--c-surface` თეთრი, `--c-surface-2` mist, `--c-border` gray-100, `--c-text` ink, `--c-text-muted` gray-600, `--c-on-primary` ink, `--c-success-soft` `#E3F4E9`, `--c-success-text` `#137443`.

Tailwind: `autopass.*` იგივე HEX-ებია. `primary` = forest, `primary.light` = moss, `primary.dark` = pine. `accent` = lime, `accent.dark` = lime-deep.

`tokens.css`-ის `@media (prefers-color-scheme: dark)` ცვლის `--ap-bg` / `--ap-surface` / `--ap-text`-ს. ცოცხალი აპის `index.css` და `v4.scss` ეკრანს მაინც ღიად ტოვებს (თეთრი და foam). `SettingsPage` მხოლოდ ენას, გამოსვლას და ანგარიშის წაშლას აჩვენებს. თემის გადამრთველი ამ ეკრანზე არ არის.

ადმინი (`umami-admin.css`): `--primary` `#14482F`, ტექსტი `#0E1712`, muted `#5E6A62`, ჩარჩო `#DCE1DB`, ფონი `#F7F9F4` / თეთრი, აქცენტი `--ap-lime`. გვერდითა სვეტი `--side-width: 256px`, ზედა ზოლი `--top-height: 52px`.

სანომრე ნიშნის ლურჯი ზოლი (`.ap-plate b`) არის `#1F4FB0`. ეს პალიტრის token არ არის.

## ფონტი

- **Plus Jakarta Sans**, წონა 200–800. აპში TTF (`gewash/public/fonts`, `index.css`). Landing-ზე woff2, ლათინური `unicode-range`.
- **Noto Sans Georgian**, წონა 100–900. იგივე წყაროები. Landing-ზე მხოლოდ ქართული `unicode-range`.
- `font-display: swap`. სხეული: `"Plus Jakarta Sans", "Noto Sans Georgian", sans-serif`. Landing ამატებს `system-ui`.
- Wordmark SVG-შია: Plus Jakarta Sans ExtraBold (800), tracking −1.5% (`brand/README.md`).
- v4-ის ქართული სათაური და ნავის ლეიბლი იყენებს Noto Sans Georgian-ს.

## ზომები, რომლებიც კოდშია

ცალკე type scale ფაილი არ არის. ეს არის რეალურად გამოყენებული ზომები.

**აპი, v4 (`v4.scss`)**

| ელემენტი | ზომა |
|---|---|
| kicker | 12.5px / 600, gray-600 |
| ნავის ლეიბლი | 13px / 800 |
| სექციის სათაური | 16px / 800 |
| ეკრანის `h1` | 17px / 800, ცენტრი |
| `.ap-btn` (v4) | 16.5px / 800, სიმაღლე 56px |

**აპი, shell (`autopass.scss`)** — v4 ზოგ წესს გადაფარავს, რადგან `global.scss` ჯერ `autopass.scss`-ს იტვირთავს, მერე `v4.scss`-ს.

| ელემენტი | ზომა |
|---|---|
| `.ap-btn` (საბაზო) | 17px / 700, სიმაღლე 60px, radius 18px |
| ნომერი `.ap-plate` | 12px / 800, tracking `0.04em` |
| ავატარი | 48px წრე, 16px / 800 |

**Landing (`styles.css`)**

სხეული 16px / line-height 1.6. `h1` 50px, `h2` 38px, experience `h2` 40px, ნაბიჯის `h3` 20px. ღილაკი 15px / სიმაღლე 46px, `.btn-lg` 16px / 58px. Eyebrow 13px / 700, tracking `0.06em`.

## დაშორება და radius

**აპი**

- v4 ეკრანი: padding `18px 22px 24px`.
- ხატის ღილაკი `.v4-ib`: 44×44, radius 14, ფონი `#F3F5F2`.
- lime ხატი `.v4-limeico`: 40×40, radius 12.
- მუქი პანელი `.v4-dk`: radius 24. გრადიენტი `#1D3326` → ink.
- ქვედა ნავი: კონტეინერი სიმაღლე 66px, radius 22, padding `0 16px calc(16px + safe-area)`. აქტიური tab lime-ია, ლეიბლით. არააქტიური ფერი `#8D978F`.
- კონტენტის ქვედა სივრცე `.ap-main`: 112px (v4), საბაზო 96px.
- დესკტოპი `@media (min-width: 1024px)`: sidebar 268px, ფონი ink, გვერდის ფონი `#F2F5EF`. შუალედური წესი იწყება `768px`-ზე (`autopass.scss`).
- ჩრდილი: lime ღილაკი `0 10px 22px -12px rgba(141, 181, 35, 0.9)`. ნავი `0 14px 30px -14px rgba(14, 23, 18, 0.6)`. მაღაზია `$shop-ui-shadow`: `0 8px 24px -12px rgba(11, 42, 27, 0.18)`.

**Landing**

- კონტეინერი 1200px, გვერდითი ველი `max(24px, (100vw - 1200px) / 2)` არ არის ყველა ბლოკზე. `.container` არის `max-width: calc(100% - 48px)`.
- სექცია: padding `120px 0`.
- მობილური ზღვარი `760px` (`lending/README.md` და `@media (max-width: 760px)`).
- ღილაკის radius 14, დიდის 16. Hero ბლოკი radius 36. Pill radius 99. აქცენტის ზოლი 44×5, radius 4.

**ნიშანი**

2×2 მომრგვალებული კვადრატი, 100-ერთეულოვან ბადეზე კვადრატი 45, დაშორება 10, radius 8. წყლის წვეთი მარჯვენა ქვედა კვადრატში. მინიმუმ: ნიშანი 24px, ჰორიზონტალური ლოგო 96px სიგანე. სიცარიელე ≥ ნახევარი კვადრატი.

## კომპონენტები

შაბლონი `gewash/components.json`: shadcn `new-york`, CSS ცვლადები, ხატები lucide. UI პრიმიტივები `gewash/src/components/ui`-ში:

- `button.tsx` — CVA. ზომები: default სიმაღლე 36px (`h-9`), `sm` 32px, `lg` 40px, `icon` 36px. ვარიანტები: default, destructive, outline, secondary, ghost, link. ეს shadcn-ის კლასებია. კლიენტის ძირითადი CTA კოდში არის `.ap-btn` (lime, ink ტექსტი), არა ეს `Button`.
- `input.tsx`, `switch.tsx` (Radix), `sheet.tsx` (Radix Dialog), `calendar.tsx`, `Sidebar.tsx`.
- დროპდაუნები: მანქანა, ბრენდი, მოდელი, სქესი, რეცხვის ტიპი, ენა, ფილიალის ფილტრი, დრო.

v4 ბლოკები `gewash/src/components/v4`: `SplashScreen`, `Onboarding`, `PaymentSuccess`, `WashRing`, `Mark`.

სხვა ხილული ნაწილი: `.ap-plate` (სანომრე), `.ap-sq` (დარჩენილი რეცხვის უჯრები, აქტიური lime-deep v4-ში), `.ap-pin` (რუკის პინი 34px, არჩეული 48px და lime).

ხატები: lucide-react (ნავი, sidebar) და `gewash/src/assets/icons` SVG. `vite-plugin-svgr` ჩართულია.
