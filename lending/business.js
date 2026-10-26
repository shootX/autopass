/* business.html: fleet calculator and the request form. */
(function () {
  var AP = window.AP;
  if (!AP) return;
  var DISCOUNT = 0.15; /* the same number as calc.disc */
  var $ = function (id) { return document.getElementById(id); };

  function renderBiz() {
    var cars = +$("cars").value;
    var washes = +document.querySelector("#bizCtl [aria-checked=true]").dataset.w;
    var full = cars * AP.prices[washes];
    $("carsOut").textContent = cars;
    $("bizSum").textContent = AP.fmt(full * (1 - DISCOUNT));
    $("bizWas").textContent = AP.fmt(full);
  }
  $("bizCtl").onclick = function (e) {
    var b = e.target.closest("button");
    if (!b) return;
    document.querySelectorAll("#bizCtl button").forEach(function (x) { x.setAttribute("aria-checked", String(x === b)); });
    renderBiz();
  };
  $("cars").oninput = renderBiz;

  /* POST to the server when config.js names an endpoint, otherwise an e-mail to the contact address. */
  var form = $("leadForm");
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var note = form.querySelector(".note");
    var company = form.elements.company.value.trim();
    var phone = form.elements.phone.value.trim();
    var url = window.AUTOPASS_LEAD_URL || "";
    if (url) {
      fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ company: company, phone: phone, lang: AP.lang })
      }).then(function (res) {
        note.textContent = AP.I(res.ok ? "form.ok" : "form.err");
        if (res.ok) form.reset();
      }).catch(function () {
        note.textContent = AP.I("form.err");
      });
      return;
    }
    var email = window.AUTOPASS_EMAIL || "";
    if (!email) {
      note.textContent = AP.I("form.err");
      return;
    }
    var body = AP.I("form.co") + ": " + company + "\n" + AP.I("form.ph") + ": " + phone;
    location.href = "mailto:" + email + "?subject=" + encodeURIComponent(AP.I("form.h")) + "&body=" + encodeURIComponent(body);
    note.textContent = AP.I("form.mail");
  });

  AP.onLang(function () {
    document.title = AP.I("doc");
    renderBiz();
  });
  AP.start();
})();
