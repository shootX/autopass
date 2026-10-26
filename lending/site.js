/* Shared by index.html and business.html: language, links from config.js, menu, FAQ, entrances. */
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
    prices: window.AUTOPASS_PRICES || { 4: 60, 8: 110, 12: 150 },
    onLang: function (fn) { renderers.push(fn); },
    setLang: setLang
  };
  window.AP = AP;

  /* App Store when the app is published, the web app until then. */
  var ios = window.AUTOPASS_IOS_URL || "";
  document.querySelectorAll("[data-store]").forEach(function (el) {
    el.href = ios || base + "/register";
    if (ios) return;
    var icon = el.querySelector("svg");
    if (icon) icon.remove();
    var label = el.querySelector("[data-i18n='hero.cta']") || el;
    if (label.dataset.i18n === "hero.cta") label.dataset.i18n = "hero.web";
    if (label.dataset.i18n === "ft.store") label.dataset.i18n = "ft.web";
  });
  document.querySelectorAll("[data-app]").forEach(function (el) {
    el.href = base + (el.getAttribute("data-app") || "/");
  });

  var phone = window.AUTOPASS_PHONE || "";
  var email = window.AUTOPASS_EMAIL || "";
  document.querySelectorAll("[data-contact]").forEach(function (el) {
    var value = el.dataset.contact === "phone" ? phone : email;
    if (!value) {
      el.hidden = true;
      return;
    }
    el.textContent = value;
    el.href = (el.dataset.contact === "phone" ? "tel:" + value.replace(/[^\d+]/g, "") : "mailto:" + value);
  });
  var legal = { privacy: window.AUTOPASS_PRIVACY_URL || "", terms: window.AUTOPASS_TERMS_URL || "" };
  document.querySelectorAll("[data-link]").forEach(function (el) {
    var url = legal[el.dataset.link];
    if (url) el.href = url;
    else el.hidden = true;
  });
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = String(new Date().getFullYear());
  });

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
  };
})();
