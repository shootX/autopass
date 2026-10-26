/* index.html: price picker, branches, decorative QR, SMS form, hero car drift. */
(function () {
  var AP = window.AP;
  if (!AP) return;
  var HOURS = window.AUTOPASS_HOURS || "08:00–22:00";
  var PINS = [[205, 250], [140, 190], [180, 120], [285, 330], [250, 60], [90, 90]];
  var plan = "8";
  var active = 0;
  var $ = function (id) { return document.getElementById(id); };

  function ringSegs(n) {
    var c = 2 * Math.PI * 34, gap = n > 8 ? 2.2 : 3.2, seg = c / n - gap;
    return '<circle cx="50" cy="50" r="34" fill="none" stroke="#9CCBB8" stroke-width="18" stroke-dasharray="' +
      seg + " " + gap + '" transform="rotate(-90 50 50)" class="seg-ring"/>';
  }
  function list(items) {
    return items.map(function (x) { return "<li>" + AP.esc(x) + "</li>"; }).join("");
  }
  function renderPlan() {
    var d = AP.D().plan, ring = $("planRing");
    document.querySelectorAll("#planCtl button").forEach(function (b) {
      b.setAttribute("aria-checked", String(b.dataset.plan === plan));
    });
    if (plan === "u") {
      $("pCur").textContent = "";
      $("pNum").textContent = d.u_fig;
      $("pPer").textContent = d.u_per;
      $("pEach").textContent = d.u_each;
      $("pList").innerHTML = list(d.lu);
      ring.innerHTML = '<circle cx="50" cy="50" r="34" fill="none" stroke="#9CCBB8" stroke-width="18"/>' +
        '<text x="50" y="62" text-anchor="middle" font-size="36" font-weight="700" fill="#133A6B">∞</text>';
      return;
    }
    var n = +plan, p = AP.prices[n];
    $("pCur").textContent = "₾";
    $("pNum").textContent = p;
    $("pPer").textContent = AP.I("price.per");
    $("pEach").textContent = d.each + "₾" + (p / n).toFixed(2).replace(/\.00$/, "");
    $("pList").innerHTML = list(d.l.map(function (x) { return x.replace("{n}", n); }));
    ring.innerHTML = ringSegs(n) + '<text x="50" y="60" text-anchor="middle" font-size="28" font-weight="700" fill="#133A6B">' + n + "</text>";
  }

  function renderBranches() {
    var branches = AP.D().branches;
    if (active >= branches.length) active = 0;
    $("rows").innerHTML = branches.map(function (b, i) {
      return '<button type="button" class="row" data-i="' + i + '" aria-expanded="false">' +
        '<span class="top"><span class="nm">' + AP.esc(b[0]) + '</span><span class="hr tab">' + AP.esc(HOURS) + "</span></span>" +
        '<span class="more"><div><p>' + AP.esc(b[1]) + '</p><p><a class="link" href="' + mapUrl(b) + '" target="_blank" rel="noopener">' +
        AP.esc(AP.I("br.dir")) + "</a></p></div></span></button>";
    }).join("");
    $("pins").innerHTML = branches.map(function (b, i) {
      var p = PINS[i % PINS.length];
      return '<g class="pin" data-i="' + i + '"><circle cx="' + p[0] + '" cy="' + p[1] + '" r="11" fill="none" stroke="#9CCBB8" stroke-width="7"/>' +
        '<path d="M' + (p[0] + 7.8) + " " + (p[1] - 7.8) + "A11 11 0 1 1 " + (p[0] - 7.8) + " " + (p[1] + 7.8) +
        '" fill="none" stroke="#133A6B" stroke-width="7" stroke-linecap="round"/></g>';
    }).join("");
    setActive(active);
  }
  function mapUrl(b) {
    return "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(b[1] + ", " + b[0]);
  }
  function setActive(i) {
    active = i;
    document.querySelectorAll(".row").forEach(function (r) {
      var on = +r.dataset.i === i;
      r.classList.toggle("on", on);
      r.setAttribute("aria-expanded", String(on));
    });
    document.querySelectorAll(".pin").forEach(function (p) { p.classList.toggle("on", +p.dataset.i === i); });
    $("mapName").textContent = AP.D().branches[i][0];
    $("mapHr").textContent = HOURS;
  }

  /* Decorative QR pattern: not a scannable code. */
  (function () {
    var s = "", seed = 7;
    var r = function () { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; };
    var finder = function (x, y) {
      return '<path d="M' + x + " " + y + "h7v7h-7zM" + (x + 1) + " " + (y + 1) + 'v5h5v-5z" fill-rule="evenodd"/>' +
        '<rect x="' + (x + 2) + '" y="' + (y + 2) + '" width="3" height="3"/>';
    };
    for (var y = 0; y < 25; y++) {
      for (var x = 0; x < 25; x++) {
        var f = (x < 8 && y < 8) || (x > 16 && y < 8) || (x < 8 && y > 16);
        if (!f && r() > 0.52) s += '<rect x="' + x + '" y="' + y + '" width="1" height="1"/>';
      }
    }
    $("qrcells").innerHTML = s + finder(0, 0) + finder(18, 0) + finder(0, 18);
  })();

  /* The QR card shows the real App Store code once config.js has one. */
  var qr = window.AUTOPASS_IOS_QR || "";
  if (qr && window.AUTOPASS_IOS_URL) {
    document.querySelector("#ctaQr .qrbox").innerHTML = '<img src="' + AP.esc(qr) + '" alt="" width="200" height="200">';
    $("ctaQr").hidden = false;
  }

  /* SMS with the app link: only when the server endpoint is configured. */
  var smsUrl = window.AUTOPASS_SMS_URL || "";
  var form = $("smsForm");
  if (smsUrl) {
    form.hidden = false;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var why = form.querySelector(".why");
      var phone = form.querySelector("input").value.trim();
      fetch(smsUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ phone: phone })
      }).then(function (res) {
        why.textContent = AP.I(res.ok ? "cta.ok" : "cta.err");
      }).catch(function () {
        why.textContent = AP.I("cta.err");
      });
    });
  } else {
    document.querySelector(".cta-alt").hidden = false;
  }

  $("planCtl").onclick = function (e) {
    var b = e.target.closest("button");
    if (!b) return;
    plan = b.dataset.plan;
    renderPlan();
  };
  var rows = $("rows");
  rows.addEventListener("pointerover", function (e) {
    var r = e.target.closest(".row");
    if (r && matchMedia("(hover:hover)").matches) setActive(+r.dataset.i);
  });
  rows.addEventListener("click", function (e) {
    var r = e.target.closest(".row");
    if (r) setActive(+r.dataset.i);
  });
  rows.addEventListener("focusin", function (e) {
    var r = e.target.closest(".row");
    if (r) setActive(+r.dataset.i);
  });

  AP.onLang(function () {
    document.title = "Autopass · " + AP.I("hero.h1");
    renderPlan();
    renderBranches();
  });
  AP.start();

  /* One moving picture: the car drifts across the ring as you scroll. */
  var car = document.querySelector("[data-drift]");
  if (!matchMedia("(prefers-reduced-motion: reduce)").matches) {
    addEventListener("scroll", function () {
      var y = Math.min(scrollY, 900);
      car.style.transform = "translate(" + (y * 0.12) + "px, calc(-50% + " + (y * -0.04) + "px))";
    }, { passive: true });
  }
})();
