/**
 * 📋 NextSafar Posts List — فیلتر ایجکسی + عملیات گروهی
 * هدر یک‌خطی: جستجو | وضعیت | دسته | عملیات گروهی | اعمال | نوشته جدید
 */
(function () {
  "use strict";

  var cfg = window.nextsafarPosts || {};
  var tbody = document.getElementById("ns-posts-tbody");
  var pagWrap = document.getElementById("ns-posts-pagination");
  var tableWrap = document.getElementById("ns-posts-table-wrap");
  var loading = document.getElementById("ns-posts-loading");
  var filtersForm = document.getElementById("ns-posts-filters");
  var search = document.getElementById("ns-posts-search");
  var statusSel = document.getElementById("ns-filter-status");
  var catSel = document.getElementById("ns-filter-category");
  var bulkSel = document.getElementById("ns-bulk-action");
  var applyBtn = document.getElementById("ns-bulk-apply");
  var bulkForm = document.getElementById("nextsafar-bulk-form");
  var selectAll = document.getElementById("nextsafar-select-all");
  var returnField = document.getElementById("ns-return-query");

  if (!tbody || !cfg.ajaxUrl || !cfg.nonce) return;

  var state = {
    s: search ? search.value : "",
    status: statusSel ? statusSel.value : "",
    category: catSel ? catSel.value : "",
    paged: parseInt(new URLSearchParams(window.location.search).get("paged") || "1", 10) || 1,
  };

  var controller = null;
  var debounceTimer = null;

  /* ---------- Helpers ---------- */
  function toast(msg, isErr) {
    var el = document.getElementById("ns-posts-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "ns-posts-toast";
      el.className = "ns-posts-toast";
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.toggle("is-err", !!isErr);
    el.classList.add("is-on");
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.classList.remove("is-on"); }, 2400);
  }

  function filterQuery() {
    var p = new URLSearchParams();
    if (state.s) p.set("s", state.s);
    if (state.status) p.set("post_status", state.status);
    if (state.category) p.set("category", state.category);
    if (state.paged > 1) p.set("paged", String(state.paged));
    return p.toString();
  }

  function syncUrlAndReturn() {
    var q = filterQuery();
    if (returnField) returnField.value = q;
    var url = window.location.pathname + "?page=nextsafar-posts" + (q ? "&" + q : "");
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, "", url);
    }
  }

  function setLoading(on) {
    if (loading) loading.classList.toggle("is-on", on);
    if (tableWrap) tableWrap.setAttribute("aria-busy", on ? "true" : "false");
  }

  function resetSelectAll() {
    if (selectAll) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
    }
  }

  function syncSelectAll() {
    if (!selectAll) return;
    var boxes = tbody.querySelectorAll(".nextsafar-post-checkbox");
    var checked = tbody.querySelectorAll(".nextsafar-post-checkbox:checked").length;
    selectAll.checked = boxes.length > 0 && checked === boxes.length;
    selectAll.indeterminate = checked > 0 && checked < boxes.length;
  }

  /* ---------- AJAX listing ---------- */
  function fetchList() {
    if (controller) controller.abort();
    controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    setLoading(true);
    syncUrlAndReturn();

    var fd = new FormData();
    fd.append("action", "nextsafar_filter_posts");
    fd.append("nonce", cfg.nonce);
    fd.append("s", state.s);
    fd.append("post_status", state.status);
    fd.append("category", state.category);
    fd.append("paged", String(state.paged));

    fetch(cfg.ajaxUrl, {
      method: "POST",
      body: fd,
      signal: controller ? controller.signal : undefined,
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success && res.data) {
          tbody.innerHTML = res.data.rows || "";
          if (pagWrap) pagWrap.innerHTML = res.data.pagination || "";
          resetSelectAll();
        } else {
          toast((res && res.data && res.data.message) || "خطا در بارگذاری لیست", true);
        }
        setLoading(false);
      })
      .catch(function (err) {
        if (err && err.name === "AbortError") return;
        setLoading(false);
        toast("خطا در اتصال", true);
      });
  }

  function applyFilters(resetPage) {
    state.s = search ? search.value.trim() : "";
    state.status = statusSel ? statusSel.value : "";
    state.category = catSel ? catSel.value : "";
    if (resetPage) state.paged = 1;
    fetchList();
  }

  /* ---------- Filter events (بدون دکمه فیلتر) ---------- */
  if (filtersForm) {
    filtersForm.addEventListener("submit", function (e) {
      e.preventDefault();
      clearTimeout(debounceTimer);
      applyFilters(true);
    });
  }
  if (search) {
    search.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () { applyFilters(true); }, 350);
    });
    search.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        clearTimeout(debounceTimer);
        applyFilters(true);
      }
    });
  }
  if (statusSel) statusSel.addEventListener("change", function () { applyFilters(true); });
  if (catSel) catSel.addEventListener("change", function () { applyFilters(true); });

  /* ---------- Pagination (ایجکسی) ---------- */
  if (pagWrap) {
    pagWrap.addEventListener("click", function (e) {
      var a = e.target.closest("a");
      if (!a) return;
      e.preventDefault();
      try {
        var u = new URL(a.href, window.location.origin);
        state.paged = parseInt(u.searchParams.get("paged") || "1", 10) || 1;
        state.s = u.searchParams.get("s") || "";
        state.status = u.searchParams.get("post_status") || "";
        state.category = u.searchParams.get("category") || "";
        if (search) search.value = state.s;
        if (statusSel) statusSel.value = state.status;
        if (catSel) catSel.value = state.category;
        fetchList();
        if (tableWrap && tableWrap.scrollIntoView) {
          tableWrap.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      } catch (err) {
        window.location.href = a.href;
      }
    });
  }

  /* ---------- Bulk: اعمال فقط با انتخاب عملیات فعال می‌شود ---------- */
  function syncApply() {
    if (applyBtn && bulkSel) applyBtn.disabled = bulkSel.value === "";
  }
  if (bulkSel) bulkSel.addEventListener("change", syncApply);
  syncApply();

  if (bulkForm) {
    bulkForm.addEventListener("submit", function (e) {
      if (!bulkSel || bulkSel.value === "") {
        e.preventDefault();
        return;
      }
      var checked = tbody.querySelectorAll(".nextsafar-post-checkbox:checked");
      if (checked.length === 0) {
        e.preventDefault();
        toast("هیچ موردی انتخاب نشده است", true);
        return;
      }
      if (bulkSel.value === "delete") {
        if (!window.confirm("موارد انتخاب‌شده برای همیشه حذف شوند؟ این عملیات قابل بازگشت نیست.")) {
          e.preventDefault();
          return;
        }
      }
      syncUrlAndReturn();
    });
  }

  /* ---------- Checkbox delegation (بعد از AJAX هم کار می‌کند) ---------- */
  if (tableWrap) {
    tableWrap.addEventListener("change", function (e) {
      var t = e.target;
      if (t && t.id === "nextsafar-select-all") {
        tbody.querySelectorAll(".nextsafar-post-checkbox").forEach(function (cb) {
          cb.checked = t.checked;
        });
        if (t.indeterminate) t.indeterminate = false;
      } else if (t && t.classList.contains("nextsafar-post-checkbox")) {
        syncSelectAll();
      }
    });
  }

  syncUrlAndReturn();
})();