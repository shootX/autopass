# მეხსიერება

## 2026-10-12 — PWA და მკვდარი დამოკიდებულებები

ხატები გენერირდება `gewash/public/brand/app-icon.svg`-დან: `public/icons/icon-192.png`, `icon-512.png`, `icon-maskable-192.png`, `icon-maskable-512.png`. maskable სავსეა `#14482F`-ით და ნიშანი ცენტრის 80%-შია.

სერვის-ვორქერი არის `gewash/public/sw.js`. ქსელი პირველია, ქეში მხოლოდ წარუმატებლობისას. `/api` არ ინახება. `navigator.serviceWorker.register` იძახება მხოლოდ `import.meta.env.PROD`-ზე, რომ dev სერვერი არ დაიქეშოს.

`expo-camera` კოდში არ გამოიყენებოდა (სკანერი არის `html5-qrcode`). `gewash/app.json` მხოლოდ EAS `projectId`-ს ინახავდა.
