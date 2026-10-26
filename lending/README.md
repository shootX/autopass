# autopass — მარკეტინგის გვერდი

სტატიკური საიტი, მაკეტი `autopass-v1.3` (2026-10-26): მთავარი გვერდი `index.html` და ბიზნესის გვერდი `business.html`. ენები: ქართული, English, Русский (არჩევანი ინახება ბრაუზერში). განლაგება: დესკტოპი, ტაბლეტი 1199px-მდე, ტელეფონი 700px-მდე (`responsive.css`).

## ატვირთვა (FTP)

ბილდი კომპილაციას არ შვება.

```sh
sh build.sh
```

ატვირთე `lending/dist/`-ის **შიგთავსი** საიტის root-ად. `dist/`-ში შედის: `index.html`, `business.html`, `styles.css`, `responsive.css`, `config.js`, `logged-in-redirect.js`, `site.js`, `home.js`, `business.js`, `site.webmanifest`, `content/`, `fonts/`, `img/`, `icons/`.

## პარამეტრები — `config.js`

| ცვლადი | რას აკეთებს | ცარიელი რომ არის |
|---|---|---|
| `AUTOPASS_APP_BASE` | ვებ-აპის მისამართი | — |
| `AUTOPASS_IOS_URL` | App Store-ის ბმული | ღილაკები ხსნის ვებ-აპს (`/register`), წარწერა „გახსენით Autopass“ |
| `AUTOPASS_IOS_QR` | ნამდვილი QR კოდის სურათი App Store-ის ბმულისთვის | QR ბარათი დამალულია |
| `AUTOPASS_PRICES` | სედანის პაკეტის ფასი თვეში (4, 8, 12 რეცხვა); ბიზნესის კალკულატორიც ამას იყენებს | — |
| `AUTOPASS_HOURS` | ფილიალების სამუშაო საათი | — |
| `AUTOPASS_PHONE`, `AUTOPASS_EMAIL` | ფუტერის კონტაქტი | ხაზი დამალულია |
| `AUTOPASS_PRIVACY_URL`, `AUTOPASS_TERMS_URL` | ფუტერის ბმულები | ბმული დამალულია |
| `AUTOPASS_API` | API, საიდანაც გვერდი ადმინის ტექსტს და პარამეტრებს კითხულობს | `AUTOPASS_APP_BASE` + `/api` |
| `AUTOPASS_SMS_URL` | POST `{phone}`, აპის ბმული SMS-ით | ფორმა დამალულია, ჩანს სათაური და ღილაკი |
| `AUTOPASS_LEAD_URL` | ბიზნესის მოთხოვნის მისამართი; ნაგულისხმევად `AUTOPASS_API` + `/site/lead` | `""`: ფორმა ხსნის ელფოსტას `AUTOPASS_EMAIL`-ზე |

## ტექსტი და ადმინი

საიტს მართავს ადმინი: **Dashboard → საიტი** (`/dashboard/site`). იქ არის ორივე გვერდის ყველა ტექსტი სამ ენაზე, სიები (FAQ, პაკეტის პუნქტები, ფილიალები) დამატებით, შეცვლით და წაშლით, სექციების ჩართვა/გამორთვა და რიგი, პარამეტრები (ფასები, საათები, კონტაქტი, ბმულები, ფილიალები ბაზიდან) და ბიზნესის მოთხოვნები.

გვერდი ჩატვირთვისას კითხულობს `GET /api/site/home` ან `/api/site/business`-ს და ტექსტს ანაცვლებს. API თუ არ პასუხობს, რჩება ჩაშენებული ტექსტი: ქართული HTML-ში, ინგლისური, რუსული და სიები `content/home.js` და `content/business.js`-ში.

ადმინის ნაგულისხმევი ტექსტი არის `backend/resources/site/*.json`. ჩაშენებული ტექსტის შეცვლის შემდეგ ის თავიდან აეწყობა: `node lending/tools/site-defaults.js lending gewash backend/resources/site` (სჭირდება `gewash/node_modules`).

სერვერზე ერთხელ: `php artisan migrate` (`site_contents`, `site_leads`).

## წესები

Inline `<script>` და `onclick`/`onsubmit` არ გამოიყენება (`docs/CSP.md`, `script-src 'self'`). შესული მომხმარებელი `/`-დან `/home`-ზე გადადის (`logged-in-redirect.js`).

ფონტები: FiraGO და Montserrat (OFL), `fonts/`. ლიცენზია: `fonts/COPYRIGHT.txt`, `fonts/OFL.txt`.
