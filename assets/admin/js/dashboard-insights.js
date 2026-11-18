(function () {
  "use strict";

  function cfg() {
    return window.nextsafarAdmin || {};
  }

  function load(tab) {
    var box = document.getElementById("ns-gsc-results");
    if (!box) return;
    var c = cfg();
    if (!c.ajaxUrl || !c.dashboardNonce) {
      box.innerHTML = '<div class="ns-empty">در حال بارگذاری...</div>';
      return;
    }
    box.innerHTML =
      '<div class="widget-loading"><div class="nextsafar-spinner"></div><span>در حال دریافت از Search Console...</span></div>';

    var fd = new FormData();
    fd.append("action", "nextsafar_get_gsc_opportunities");
    fd.append("nonce", c.dashboardNonce);
    fd.append("tab", tab);

    fetch(c.ajaxUrl, { method: "POST", body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success && data.data && data.data.html) {
          box.innerHTML = data.data.html;
        } else {
          box.innerHTML = '<div class="ns-empty">خطا در بارگذاری</div>';
        }
      })
      .catch(function () {
        box.innerHTML = '<div class="ns-empty">خطا در اتصال</div>';
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var wrap = document.getElementById("ns-gsc-opportunities");
    if (!wrap) return;

    var tabs = wrap.querySelectorAll(".ns-gsc-tab");
    tabs.forEach(function (btn) {
      btn.addEventListener("click", function () {
        tabs.forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        load(btn.getAttribute("data-tab"));
      });
    });

    load("striking");
  });
})();