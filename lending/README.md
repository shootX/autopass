# autopass — მარკეტინგის გვერდი

სტატიკური საიტი. დამტკიცებული მაკეტის განლაგებაა: დესკტოპი 760px-ზე ზემოთ, მობილური 760px-ზე და ქვემოთ.

## ატვირთვა (FTP)

ბილდი კომპილაციას არ შვება. პროდაქშენის ფაილები უკვე ამ საქაღალდეშია.

```sh
sh build.sh
```

ატვირთე `lending/dist/`-ის **შიგთავსი** საიტის root-ად (`index.html` დომენის ფესვზე უნდა გაიხსნას).

`dist/`-ში შედის: `index.html`, `styles.css`, `config.js`, `site.js`, `site.webmanifest`, `fonts/`, `img/`, `icons/`.

არ ატვირთო `src-photos/` და `scripts/`.

`sh build.sh`-ის გარეშეც იმავე ფაილების ატვირთვა პირდაპირ `lending/`-იდან მუშაობს. `dist/` იმისთვისაა, რომ ზედმეტი ფაილი არ ავიდეს.

## აპის მისამართი

შეცვალე მხოლოდ `config.js`:

```js
window.AUTOPASS_APP_BASE = "https://wash.socialsave.cc";
```

აქედან ივსება შესვლა (`/auth`), რეგისტრაცია (`/register`), „აირჩიე პაკეტი“ და App Store / Google Play ბეჯები (აპი ჯერ არ არის, ამიტომ ბეჯებიც `/register`-ზე მიდის). `index.html`-ის `href` იგივე ნაგულისხმევია, რომ JavaScript-ის გარეშეც გაიხსნას; გვერდის ჩატვირთვისას `site.js` ბმულებს `config.js`-იდან ანახლებს.

## სურათები და ფონტები

ფოტოები AVIF და WebP-ია, PNG fallback-ით. ეკრანის ქვემოთ მყოფი სურათები `loading="lazy"`-ით იტვირთება. ფონტები (Plus Jakarta Sans, Noto Sans Georgian, OFL) `fonts/`-შია, woff2.

თუ cut-out ფოტოს შეცვლი (`src-photos/`), თავიდან დააგენერირე:

```sh
python3 scripts/optimize-images.py
sh build.sh
```

სჭირდება Pillow (WebP და AVIF), fontTools, brotli, numpy.

ზემოდან გადაღებული მანქანა placeholder-ია: Porsche-ს ფოტო, ფერი ფაილშია ჩაწერილი (ადრე CSS `filter` იყო). საქარე მინის ზოლი და უკანა სპოილერის ფედი კვლავ CSS-ით იფარება. ლიცენზიები და რეფერენსები: `references.md`.
