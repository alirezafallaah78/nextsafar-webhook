(function () {
    "use strict";

    function cfg() {
        return window.nextsafarAdmin || {};
    }

    function load(tab, box) {
        if (!box) return;

        // جلوگیری از درخواست همزمانِ تکراری برای یک تب
        if (box.dataset.nsGscLoading === tab) return;

        var c = cfg();
        if (!c.ajaxUrl || !c.dashboardNonce) {
            box.innerHTML = '<div class="ns-empty">در حال بارگذاری...</div>';
            return;
        }

        box.dataset.nsGscLoading = tab;
        box.innerHTML = '<div class="widget-loading"><div class="nextsafar-spinner"></div><span>در حال دریافت از Search Console...</span></div>';

        var fd = new FormData();
        fd.append("action", "nextsafar_get_gsc_opportunities");
        fd.append("nonce", c.dashboardNonce);
        fd.append("tab", tab);

        fetch(c.ajaxUrl, { method: "POST", body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                box.dataset.nsGscLoading = "";
                if (data.success && data.data && data.data.html) {
                    box.innerHTML = data.data.html;
                    box.dataset.nsGscLoaded = tab;
                } else {
                    box.innerHTML = '<div class="ns-empty">خطا در بارگذاری</div>';
                }
            })
            .catch(function () {
                box.dataset.nsGscLoading = "";
                box.innerHTML = '<div class="ns-empty">خطا در اتصال</div>';
            });
    }

    function init() {
        var wrap = document.getElementById("ns-gsc-opportunities");
        if (!wrap) return;

        // ✅ گارد یک‌بار اجرا — حتی اگر اسکریپت دو بار لود شود
        if (wrap.dataset.nsGscInit === "1") return;
        wrap.dataset.nsGscInit = "1";

        var box = document.getElementById("ns-gsc-results");
        var tabs = wrap.querySelectorAll(".ns-gsc-tab");

        tabs.forEach(function (btn) {
            btn.addEventListener("click", function () {
                var tab = btn.getAttribute("data-tab");
                // همان تبِ قبلاً لودشده → درخواست مجدد نزن
                if (box && box.dataset.nsGscLoaded === tab) return;
                tabs.forEach(function (b) { b.classList.remove("active"); });
                btn.classList.add("active");
                load(tab, box);
            });
        });

        load("striking", box);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();