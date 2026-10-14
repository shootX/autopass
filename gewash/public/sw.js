const CACHE = "autopass-public-assets-v2";
const PUBLIC_FILES = new Set(["/manifest.webmanifest", "/favicon.svg", "/favicon.ico"]);

function publicAsset(url) {
  return PUBLIC_FILES.has(url.pathname) || url.pathname.startsWith("/assets/") || url.pathname.startsWith("/icons/");
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(["/manifest.webmanifest"])),
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key.startsWith("autopass-") && key !== CACHE).map((key) => caches.delete(key))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith("/api")) return;
  if (request.mode === "navigate" || !publicAsset(url)) return;
  if (request.cache === "no-store" || request.headers.has("Authorization")) return;

  event.respondWith(
    fetch(request)
      .then((response) => {
        const cacheControl = response.headers.get("Cache-Control") || "";
        if (response.ok && response.type === "basic" && !response.redirected && !/\b(no-store|private)\b/i.test(cacheControl)) {
          const copy = response.clone();
          event.waitUntil(caches.open(CACHE).then((cache) => cache.put(request, copy)));
        }
        return response;
      })
      .catch(() => caches.open(CACHE).then((cache) => cache.match(request)).then((cached) => cached || Response.error())),
  );
});
