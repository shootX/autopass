# წესები — autopass

იმუშავე `docs/PRD.md`, `docs/ARCHITECTURE.md` და `docs/DESIGN.md`-ის მიხედვით. მოთხოვნის გარეთ ფუნქცია არ ემატება.

მუშაობის წინ წაიკითხე `docs/MEMORY.md`, `docs/TASKS.md` და ეს ფაილი. ერთი agent ერთ დროს ერთ დავალებას იღებს (`docs/TASKS.md`). დავალების შემდეგ იმავე PR-ში განაახლე `TASKS.md` და `MEMORY.md` თარიღით (`YYYY-MM-DD`).

## კოდი

- ახალი ბიბლიოთეკა არ ემატება დამტკიცების გარეშე. დაშვებულია ის, რაც უკვე წერია `gewash/package.json`, `gewash/voucher-checker/package.json`, `backend/package.json` და `backend/composer.json`-ში.
- ყოველ მოთხოვნას აქვს შეცდომის დამუშავება და გასაგები ტექსტი. API უკვე აბრუნებს `{ success: false, error }` ან `{ ok: false, message }` და HTTP სტატუსს (`401`, `403`, `404`, `422`). ახალი პასუხი ამ ფორმას მიჰყვეს. ცარიელი `catch`, რომელიც შეცდომას ყლაპავს და მომხმარებელს არაფერს აჩვენებს, არ ჩაითვალოს მზად.
- ფაილი დაახლოებით 300 ხაზამდე. ახალი და შეცვლილი ფაილი ამ ზღვარს მიჰყვეს. რეპოში უკვე არის გრძელი ფაილები (`gewash/src/styles/v4.scss` და სხვა). მათი დაშლა ცალკე დავალების გარეშე არ იწყება.
- TypeScript-ში `any` არ გამოიყენება. არსებული `any` (`fetchUserData`, რამდენიმე hook და გვერდი) ახალ კოდში არ მეორდება.
- დიზაინი არ იცვლება, სანამ დავალება პირდაპირ ამას არ ამბობს. ფერი და ზომა `docs/DESIGN.md`-დან მოდის.
- ცოცხალი ეკრანებია `gewash/src/components`. პარალელური `src/pages` არ იქმნება.
- `fix.md`-ის ფულის, SMS-ის და ავტორიზაციის ღია პუნქტებს ნუ შეეხები, თუ დავალება სწორედ ეს არის.
- ახალი მიგრაცია PostgreSQL-ზე იწერება. `enum()->change()` არ გამოიყენება.
- საიდუმლო (`.env`, გასაღები, პაროლი) რეპოში არ ხვდება.

## gewash — lint და ტიპები

`gewash/package.json`:

- `npm run lint` — ESLint. კონფიგი `gewash/eslint.config.js`: `@eslint/js` recommended, `typescript-eslint` recommended, `eslint-plugin-react-hooks` (`recommended-latest`), `eslint-plugin-react-refresh` (Vite). იგნორებს `dist`-ს. ფაილები `**/*.{ts,tsx}`, `ecmaVersion: 2020`, `globals.browser`.
- `npm run build` — მხოლოდ `vite build`. ტიპებს არ ამოწმებს.
- `npm run build:check` — `tsc -b && vite build`. სანამ ეს ბრძანება წითელია, ტიპის შეცდომა დახურულად არ ჩაითვალოს.
- `tsconfig.app.json`: `strict`, `noUnusedLocals`, `noUnusedParameters`, `noFallthroughCasesInSwitch`, `noEmit`, `jsx: react-jsx`, `verbatimModuleSyntax`. გამოუყენებელი იმპორტი ვარდება.
- ენა: `npm run i18n:check`, `npm run i18n:generate`, `npm run i18n:extract-unique`. მომხმარებლის ტექსტი `ka` / `en` / `ru` ფაილებშია, არა მხოლოდ კომპონენტში ჩაწერილი სტრიქონი. ნაგულისხმევი ენა `ka`.

Frontend ტესტის სკრიპტი `package.json`-ში არ არის. ახალი test runner ცალკე დამტკიცების გარეშე არ ემატება.

## voucher-checker

- `npm run lint` — იგივე ESLint ნაკრები (`eslint.config.js`, flat config).
- `npm run build` — `tsc -b && vite build`. აქ ტიპის შემოწმება build-ის ნაწილია.

## backend — ტესტი და ფორმატი

- `composer test` — `php artisan config:clear`, შემდეგ `php artisan test`.
- PHPUnit `^12`. `backend/phpunit.xml`: suite `tests/Unit` და `tests/Feature`, წყარო `app/`. ტესტის ბაზა SQLite `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`. ახლანდელი ტესტები `tests/Unit/ExampleTest.php` და `tests/Feature/ExampleTest.php`-ია.
- PHP ფორმატი: Laravel Pint (`composer.json` require-dev). `pint.json` არ არის, მოქმედებს Pint-ის ნაგულისხმევი Laravel preset.
- `backend/.editorconfig`: UTF-8, LF, 4 space. YAML — 2 space. Markdown-ში trailing whitespace არ იჭრება.

## შეცდომის ტექსტი

მომხმარებლისკენ მიმართული ახალი ტექსტი ქართულადაც უნდა არსებობდეს (`ka`), ინგლისურთან და რუსულთან ერთად, თუ იგივე ეკრანი სამივე ენას უკვე იყენებს. API-ის ზოგი ტექსტი კვლავ რუსული ან ინგლისურია (`fix.md`). ახალი ტექსტი ამ ნარჩენს არ აგრძელებს.
