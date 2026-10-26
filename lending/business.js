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

  /* POST to the server (admin > Website > Requests); without an endpoint, or when it fails, an e-mail to the contact address. */
  var form = $("leadForm");
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var note = form.querySelector(".note");
    var company = form.elements.company.value.trim();
    var phone = form.elements.phone.value.trim();
    var url = AP.cfg.lead_url;
    if (!url) {
      mail(company, phone, note);
      return;
    }
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ company: company, phone: phone, lang: AP.lang })
    }).then(function (res) {
      if (res.ok) {
        note.textContent = AP.I("form.ok");
        form.reset();
      } else if (res.status === 422) {
        note.textContent = AP.I("form.bad");
      } else {
        mail(company, phone, note);
      }
    }).catch(function () {
      mail(company, phone, note);
    });
  });

  function mail(company, phone, note) {
    var email = AP.cfg.email;
    if (!email) {
      note.textContent = AP.I("form.err");
      return;
    }
    var body = AP.I("form.co") + ": " + company + "\n" + AP.I("form.ph") + ": " + phone;
    location.href = "mailto:" + email + "?subject=" + encodeURIComponent(AP.I("form.h")) + "&body=" + encodeURIComponent(body);
    note.textContent = AP.I("form.mail");
  }

  AP.onLang(function () {
    document.title = AP.I("doc");
    renderBiz();
  });
  AP.start();
})();
