# nginx-ის მაგალითი

`autopass.conf.example` მხოლოდ ნიმუშია. ის სერვერზე არ იდება და nginx-ს არ გადატვირთავს.

`root` უნდა იყოს SPA-ის ბილდი (`gewash/dist`), არა `lending/`. `location /` იყენებს `try_files $uri $uri/ /index.html;`-ს, ამიტომ `/home`, `/auth`, `/register`, `/renew-password` და დანარჩენი კლიენტის მისამართები ხსნის SPA-ის `index.html`-ს და არა მარკეტინგის გვერდს.
