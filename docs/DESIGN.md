# დიზაინი — autopass

წყარო: `gewash/src/assets/brand/palette.json`, `tokens.css`, `gewash/src/index.css`, `gewash/tailwind.config.js`, `gewash/src/styles/_variables.scss`, `autopass.scss`, `v4.scss`, `lending/styles.css`, `backend/public/css/umami-admin.css`. ნიშანი აღწერილია `gewash/src/assets/brand/README.md`-ში (brand kit v1.0, concept #8).

პალიტრა 2026-10-16-დან არის ნავი / ოქრო (`#12284b` / `#e4b23c`), პორტი `41a3eae`-დან. CSS/SCSS ტოკენის სახელები (`forest`, `lime`, `$color-blue-*`, `$color-yellow-*`) იგივეა, მნიშვნელობები ახალია (`_variables.scss`, `palette.json`).

## ფერები

| Token | HEX | გამოყენება |
|---|---|---|
| forest (ნავი) | `#12284b` | ძირითადი ნავი, ლოგო, header, `--c-primary` |
| lime (ოქრო) | `#e4b23c` | აქცენტი, ძირითადი CTA, აქტიური tab |
| lime-deep | `#c8922a` | ოქროს hover / დაჭერა |
| gold-text | `#7a560c` | ოქროს ტექსტი ღია ფონზე (თეთრი, `#f6f3ec`, `#efeae2`, `#e4ddd0`). `#e4b23c` რჩება შევსებასა და მუქ ფონზე |
| pine | `#0c1b33` | ყველაზე მუქი ნავი |
| moss | `#1c3d6e` | მეორე ნავი |
| mint | `#fff6df` | ოქროს ჩიპი, არჩეული რიგი |
| mist | `#e7eef8` | სექციის ფონი |
| ink | `#14233c` | ტექსტი, wordmark, v4-ის მუქი პანელი |
| foam | `#f6f3ec` | აპის ფონი (canvas) |
| white | `#FFFFFF` | ბარათი, v4 ეკრანის ფონი |
| gray-100 | `#efeae2` | გამყოფი, input-ის შევსება |
| gray-200 | `#e4ddd0` | ჩარჩო |
| gray-400 | `#a8b3c4` | გამორთული, placeholder |
| gray-600 | `#5d6e86` | მეორე ტექსტი |
| gray-800 | `#1a2e4a` | ძლიერი ტექსტი |
| success | `#1E9E5A` | დადასტურება |
| warning | `#F0A020` | ვადა, ყურადღება |
| error | `#D64541` | შეცდომა |
| info | `#2E86D6` | ინფო, რეცხვის მიმდინარეობა |

სემანტიკა `index.css`-ში: `--c-bg` foam, `--c-surface` თეთრი, `--c-surface-2` mist, `--c-border` gray-100, `--c-text` ink, `--c-text-muted` gray-600, `--c-on-primary` ink, `--c-success-soft` `#E3F4E9`, `--c-success-text` `#137443`.

Tailwind: `autopass.*` იგივე HEX-ებია. `primary` = ნავი `#12284b`, `primary.light` = `#1c3d6e`, `primary.dark` = `#0c1b33`. `accent` = ოქრო `#e4b23c`, `accent.dark` = `#c8922a`. დამატებითი სკალა: `navy`, `gold`, `canvas`, `ink`.

`tokens.css`-ის `@media (prefers-color-scheme: dark)` ცვლის `--ap-bg` / `--ap-surface` / `--ap-text`-ს. ცოცხალი აპის `index.css` და `v4.scss` ეკრანს მაინც ღიად ტოვებს (თეთრი და foam). `SettingsPage` მხოლოდ ენას, გამოსვლას და ანგარიშის წაშლას აჩვენებს. თემის გადამრთველი ამ ეკრანზე არ არის.

ადმინი (`umami-admin.css`): `--primary` `#12284b`, ტექსტი `#14233c`, muted `#5d6e86`, ჩარჩო `#e4ddd0`, ფონი `#f6f3ec` / თეთრი, აქცენტი `#e4b23c`. გვერდითა სვეტი ნავია, აქტიური პუნქტი ოქროა, ღილაკი ნავი თეთრი ტექსტით. `--side-width: 256px`, ზედა ზოლი `--top-height: 52px`.

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
- ქვედა ნავი: კონტეინერი სიმაღლე 66px, radius 22, padding `0 16px calc(16px + safe-area)`. აქტიური tab ოქროა, ლეიბლით. არააქტიური ფერი `#8ca3b9`.
- კონტენტის ქვედა სივრცე `.ap-main`: 112px (v4), საბაზო 96px.
- დესკტოპი `@media (min-width: 1024px)`: sidebar 268px, ფონი ink, გვერდის ფონი `#F2F5EF`. შუალედური წესი იწყება `768px`-ზე (`autopass.scss`).
- ჩრდილი: ოქროს ღილაკი `0 10px 22px -12px rgba(200, 146, 42, 0.9)`. ნავი `0 14px 30px -14px rgba(20, 35, 60, 0.6)`. მაღაზია `$shop-ui-shadow`: `0 8px 24px -12px rgba(12, 27, 51, 0.18)`.

**Landing**

- კონტეინერი 1200px, გვერდითი ველი `max(24px, (100vw - 1200px) / 2)` არ არის ყველა ბლოკზე. `.container` არის `max-width: calc(100% - 48px)`.
- სექცია: padding `120px 0`.
- მობილური ზღვარი `760px` (`lending/README.md` და `@media (max-width: 760px)`).
- ღილაკის radius 14, დიდის 16. Hero ბლოკი radius 36. Pill radius 99. აქცენტის ზოლი 44×5, radius 4.

**ნიშანი**

2×2 მომრგვალებული კვადრატი, 100-ერთეულოვან ბადეზე კვადრატი 45, დაშორება 10, radius 8. წყლის წვეთი მარჯვენა ქვედა კვადრატში. მინიმუმ: ნიშანი 24px, ჰორიზონტალური ლოგო 96px სიგანე. სიცარიელე ≥ ნახევარი კვადრატი.

## კომპონენტები

შაბლონი `gewash/components.json`: shadcn `new-york`, CSS ცვლადები, ხატები lucide. UI პრიმიტივები `gewash/src/components/ui`-ში:

- `button.tsx` — CVA. ზომები: default სიმაღლე 36px (`h-9`), `sm` 32px, `lg` 40px, `icon` 36px. ვარიანტები: default, destructive, outline, secondary, ghost, link. ეს shadcn-ის კლასებია. კლიენტის ძირითადი CTA კოდში არის `.ap-btn` (ოქრო, ink ტექსტი), არა ეს `Button`. დესკტოპის შესვლაზე ღილაკი ნავია, თეთრი ტექსტით.
- `input.tsx`, `switch.tsx` (Radix), `sheet.tsx` (Radix Dialog), `calendar.tsx`, `Sidebar.tsx`.
- დროპდაუნები: მანქანა, ბრენდი, მოდელი, სქესი, რეცხვის ტიპი, ენა, ფილიალის ფილტრი, დრო.

v4 ბლოკები `gewash/src/components/v4`: `SplashScreen`, `Onboarding`, `PaymentSuccess`, `WashRing`, `Mark`.

სხვა ხილული ნაწილი: `.ap-plate` (სანომრე), `.ap-sq` (დარჩენილი რეცხვის უჯრები, აქტიური ოქრო v4-ში), `.ap-pin` (რუკის პინი 34px, არჩეული 48px და ოქრო).

ხატები: lucide-react (ნავი, sidebar) და `gewash/src/assets/icons` SVG. `vite-plugin-svgr` ჩართულია.

## 2026-10-16 — ნავი და ოქრო

პორტი `41a3eae` (`cursor/visual-redesign-a88c`). ძირითადი `#12284b`, აქცენტი `#e4b23c`, ტილო `#f6f3ec`, ტექსტი `#14233c`. ტოკენის სახელები არ გადარქმეულა. დესკტოპის შესვლა ორ სვეტადაა: მარცხნივ ნავის გრადიენტი და ოქროს ნათება, მარჯვნივ თეთრი ფორმა.

ოქრო ტექსტად ღია ფონზე არის `#7a560c` (`--ap-gold-text`, `$goldText`). `#e4b23c` რჩება შევსებას, ჩარჩოს და ტექსტს მუქ ნავზე.
