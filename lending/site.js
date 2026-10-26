/* Shared by index.html and business.html: language, settings (config.js, then the admin's via the API), menu, FAQ, entrances. */
(function () {
  var root = document.documentElement;
  root.classList.add("js");
  var base = String(window.AUTOPASS_APP_BASE || "https://wash.socialsave.cc").replace(/\/+$/, "");

  if (typeof window.autopassLoggedInHome === "function") {
    var token = "";
    try { token = localStorage.getItem("access_token") || ""; } catch (err) { token = ""; }
    var next = window.autopassLoggedInHome(base, token, location.pathname);
    if (next) {
      location.replace(next);
      return;
    }
  }

  var T = window.AP_TEXT || { ka: {}, en: {}, ru: {} };
  var KA = {};
  document.querySelectorAll("[data-i18n]").forEach(function (el) {
    if (!(el.dataset.i18n in KA)) KA[el.dataset.i18n] = el.textContent;
  });
  var renderers = [];
  var AP = {
    lang: "ka",
    I: function (key) {
      var own = T[AP.lang] || {};
      if (key in own) return own[key];
      if (key in T.ka) return T.ka[key];
      return KA[key] || "";
    },
    D: function () { return T[AP.lang] || T.ka; },
    fmt: function (n) {
      return "₾" + Math.round(n).toLocaleString("ru-RU").replace(/\s/g, " ");
    },
    onLang: function (fn) { renderers.push(fn); },
    setLang: setLang
  };
  window.AP = AP;

  /* config.js first; the admin's settings from /api/site/{page} replace them when they arrive. */
  var cfg = {
    api: String(window.AUTOPASS_API || base + "/api").replace(/\/+$/, ""),
    prices: window.AUTOPASS_PRICES || { 4: 60, 8: 110, 12: 150 },
    hours: window.AUTOPASS_HOURS || "08:00–22:00",
    phone: window.AUTOPASS_PHONE || "",
    email: window.AUTOPASS_EMAIL || "",
    ios_url: window.AUTOPASS_IOS_URL || "",
    ios_qr: window.AUTOPASS_IOS_QR || "",
    privacy_url: window.AUTOPASS_PRIVACY_URL || "",
    terms_url: window.AUTOPASS_TERMS_URL || "",
    sms_url: window.AUTOPASS_SMS_URL || "",
    lead_url: window.AUTOPASS_LEAD_URL
  };
  if (cfg.lead_url === undefined) cfg.lead_url = cfg.api + "/site/lead";
  AP.cfg = cfg;

  function applyConfig() {
    AP.prices = cfg.prices;
    /* App Store when the app is published, the web app until then. */
    document.querySelectorAll("[data-store]").forEach(function (el) {
      var ios = cfg.ios_url;
      el.href = ios || base + "/register";
      var icon = el.querySelector("svg");
      if (icon) icon.style.display = ios ? "" : "none";
      var label = el.querySelector("[data-i18n^='hero.']") || el;
      var swap = { "hero.cta": "hero.web", "ft.store": "ft.web" };
      Object.keys(swap).forEach(function (store) {
        var key = label.dataset.i18n;
        if (!ios && key === store) label.dataset.i18n = swap[store];
        if (ios && key === swap[store]) label.dataset.i18n = store;
      });
    });
    document.querySelectorAll("[data-contact]").forEach(function (el) {
      var phone = el.dataset.contact === "phone";
      var value = phone ? cfg.phone : cfg.email;
      el.hidden = !value;
      if (!value) return;
      el.textContent = value;
      el.href = phone ? "tel:" + value.replace(/[^\d+]/g, "") : "mailto:" + value;
    });
    document.querySelectorAll("[data-link]").forEach(function (el) {
      var url = cfg[el.dataset.link + "_url"];
      el.hidden = !url;
      if (url) el.href = url;
    });
  }
  applyConfig();
  document.querySelectorAll("[data-app]").forEach(function (el) {
    el.href = base + (el.getAttribute("data-app") || "/");
  });
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = String(new Date().getFullYear());
  });

  /* Sections the admin switched off are hidden; the rest follow the admin's order. */
  function applySections(list) {
    var main = document.querySelector("main");
    list.forEach(function (s) {
      var el = document.querySelector('[data-section="' + s.id + '"]');
      if (!el) return;
      el.hidden = !s.visible;
      main.appendChild(el);
    });
  }

  function loadServer() {
    var page = document.body.dataset.page;
    if (!page || !window.fetch) return;
    fetch(cfg.api + "/site/" + page, { headers: { Accept: "application/json" } })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data || !data.success) return;
        ["ka", "en", "ru"].forEach(function (lang) {
          if (data.text && data.text[lang]) T[lang] = data.text[lang];
        });
        Object.keys(data.config || {}).forEach(function (k) { cfg[k] = data.config[k]; });
        AP.branches = data.branches || null;
        applyConfig();
        applySections(data.sections || []);
        setLang(AP.lang);
      })
      .catch(function () { /* the built-in text stays: the page works without the API */ });
  }

  var openCard = 0;
  function renderFaq() {
    var box = document.getElementById("cards");
    if (!box) return;
    box.innerHTML = (AP.D().faq || T.ka.faq).map(function (f, i) {
      var open = i === openCard;
      return '<div class="card' + (open ? " open" : "") + '" style="--k:' + (i % 5) + '">' +
        '<span class="tabhead" aria-hidden="true"></span>' +
        '<button type="button" class="q" aria-expanded="' + open + '">' + esc(f[1]) + "</button>" +
        '<div class="ans"><div><p>' + esc(f[2]) + "</p></div></div></div>";
    }).join("");
    box.querySelectorAll(".card .q").forEach(function (btn, i) {
      btn.onclick = function () {
        openCard = i;
        renderFaq();
      };
    });
  }
  AP.esc = esc;
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function setLang(lang) {
    AP.lang = lang;
    root.lang = lang;
    document.querySelectorAll("[data-i18n]").forEach(function (el) { el.textContent = AP.I(el.dataset.i18n); });
    document.querySelectorAll("[data-i18n-alt]").forEach(function (el) { el.alt = AP.I(el.dataset.i18nAlt); });
    document.querySelectorAll("[data-i18n-ph]").forEach(function (el) { el.placeholder = AP.I(el.dataset.i18nPh); });
    document.querySelectorAll("[data-lang]").forEach(function (b) {
      b.setAttribute("aria-pressed", String(b.dataset.lang === lang));
    });
    renderFaq();
    renderers.forEach(function (fn) { fn(); });
    try { localStorage.setItem("ap-lang", lang); } catch (err) { /* private mode: language is not remembered */ }
  }
  document.querySelectorAll("[data-lang]").forEach(function (b) {
    b.onclick = function () { setLang(b.dataset.lang); };
  });

  var menu = document.querySelector(".menu-btn");
  var sheet = document.getElementById("sheet");
  function setMenu(open) {
    sheet.classList.toggle("open", open);
    menu.setAttribute("aria-expanded", String(open));
  }
  menu.onclick = function () { setMenu(!sheet.classList.contains("open")); };
  sheet.onclick = function (e) { if (e.target.closest("a")) setMenu(false); };
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") setMenu(false); });

  AP.start = function () {
    var saved = "ka";
    try { saved = localStorage.getItem("ap-lang") || "ka"; } catch (err) { saved = "ka"; }
    setLang(["ka", "en", "ru"].indexOf(saved) >= 0 ? saved : "ka");
    if (!("IntersectionObserver" in window)) {
      document.querySelectorAll("[data-rise],.bar").forEach(function (el) { el.classList.add("in"); });
      loadServer();
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.classList.add("in");
        io.unobserve(e.target);
      });
    }, { threshold: 0.15 });
    document.querySelectorAll("[data-rise],.bar").forEach(function (el) { io.observe(el); });
    loadServer();
  };
})();
