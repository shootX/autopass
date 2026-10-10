(function () {
  var base = String(window.AUTOPASS_APP_BASE || "https://wash.socialsave.cc").replace(/\/+$/, "");
  document.querySelectorAll("[data-app]").forEach(function (el) {
    var path = el.getAttribute("data-app") || "/";
    if (path.charAt(0) !== "/") path = "/" + path;
    el.href = base + path;
  });

  var stores = { ios: window.AUTOPASS_IOS_URL || "", android: window.AUTOPASS_ANDROID_URL || "" };
  document.querySelectorAll("a.store").forEach(function (el) {
    var url = stores[el.getAttribute("data-store")] || "";
    if (url) {
      el.href = url;
      return;
    }
    el.addEventListener("click", function (event) { event.preventDefault(); });
    el.removeAttribute("href");
    el.setAttribute("aria-disabled", "true");
    var small = el.querySelector("small");
    if (small) small.textContent = "მალე";
  });

  var burger = document.querySelector(".burger");
  var panel = document.getElementById("nav-panel");
  if (burger && panel) {
    var setOpen = function (open) {
      burger.setAttribute("aria-expanded", open ? "true" : "false");
      burger.setAttribute("aria-label", open ? "მენიუს დახურვა" : "მენიუ");
      panel.classList.toggle("is-open", open);
    };
    burger.addEventListener("click", function () {
      setOpen(burger.getAttribute("aria-expanded") !== "true");
    });
    panel.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        setOpen(false);
      });
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") setOpen(false);
    });
    window.addEventListener("resize", function () {
      if (window.innerWidth > 760) setOpen(false);
    });
  }

  var form = document.querySelector(".nl");
  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
    });
  }
})();
