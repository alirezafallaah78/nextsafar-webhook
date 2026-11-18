(function () {
  var modal, postId;

  function ensureModal() {
    if (modal) return;

    modal = document.createElement("div");
    modal.id = "ns-sync-modal";
    modal.style.cssText =
      "position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center;";

    modal.innerHTML =
      '<div style="width:92%;max-width:780px;max-height:85vh;background:#fff;border-radius:10px;display:flex;flex-direction:column;overflow:hidden;">' +
      '<div style="padding:10px;border-bottom:1px solid #dcdcde;display:flex;gap:8px;background:#f6f7f7;">' +
      '<input id="ns-sync-query" style="flex:1;padding:6px 10px;" placeholder="عبارت جستجو (نام + شهر)...">' +
      '<button class="button button-primary" id="ns-sync-search">🔍 جستجو</button>' +
      '<button class="button" id="ns-sync-close">بستن</button></div>' +
      '<div id="ns-sync-status" style="padding:8px 12px;font-size:12px;background:#f8f9fa;border-bottom:1px solid #e5e7eb;"></div>' +
      '<div id="ns-sync-list" style="overflow-y:auto;padding:10px;flex:1;"></div></div>';

    document.body.appendChild(modal);

    document.getElementById("ns-sync-close").onclick = function () {
      modal.style.display = "none";
    };

    document.getElementById("ns-sync-search").onclick = search;

    document.getElementById("ns-sync-query").addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        search();
      }
    });
  }

  function status(t, type) {
    var el = document.getElementById("ns-sync-status");
    el.textContent = t;
    el.style.color = type === "error" ? "#d63638" : type === "success" ? "#00a32a" : "#1d2327";
  }

  function esc(s) {
    var d = document.createElement("div");
    d.textContent = s || "";
    return d.innerHTML;
  }

  function openModal(pid, q) {
    ensureModal();

    postId = pid;
    modal.style.display = "flex";

    document.getElementById("ns-sync-query").value = q || "";
    document.getElementById("ns-sync-list").innerHTML = "";

    if (q) search();
  }

  function search() {
    var q = document.getElementById("ns-sync-query").value.trim();

    if (!q) return;

    status("⏳ در حال جستجو از SearchApi...", "info");

    jQuery.post(
      NS_SYNC.ajaxUrl,
      {
        action: "ns_post_sync_candidates",
        nonce: NS_SYNC.nonce,
        post_id: postId,
        query: q,
      },
      function (res) {
        if (!res.success) {
          status("❌ " + ((res.data && res.data.message) || "خطا"), "error");
          return;
        }

        render(res.data.candidates);
      }
    ).fail(function () {
      status("❌ خطای ارتباط", "error");
    });
  }

  function render(cands) {
    var list = document.getElementById("ns-sync-list");

    list.innerHTML = "";

    status(cands.length + " نتیجه پیدا شد — یکی را انتخاب کن:", "success");

    cands.forEach(function (c) {
      var row = document.createElement("div");

      row.style.cssText =
        "display:flex;gap:10px;align-items:center;padding:10px;border:1px solid #dcdcde;border-radius:8px;margin-bottom:8px;background:#fff;transition:all 0.2s;";

      row.innerHTML =
        (c.thumbnail
          ? '<img src="' +
            c.thumbnail +
            '" style="width:64px;height:64px;object-fit:cover;border-radius:6px;flex-shrink:0;">'
          : "") +
        '<div style="flex:1;">' +
        '<strong style="display:block;margin-bottom:4px;">' +
        esc(c.name) +
        "</strong>" +
        '<span style="font-size:11px;color:#666;display:block;margin-bottom:2px;">' +
        esc(c.address) +
        "</span>" +
        '<span style="font-size:11px;">' +
        "⭐ " + c.rating + " (" + c.reviews + ") • " +
        "شباهت: <strong>" + Math.round(c.similarity * 100) + "٪</strong>" +
        "</span>" +
        "</div>" +
        '<button class="button button-primary" style="flex-shrink:0;">اعمال</button>';

      row.onmouseenter = function () {
        this.style.borderColor = "#2271b1";
        this.style.background = "#f0f6fc";
      };
      row.onmouseleave = function () {
        this.style.borderColor = "#dcdcde";
        this.style.background = "#fff";
      };

      row.querySelector("button").onclick = function () {
        apply(c);
      };

      list.appendChild(row);
    });
  }

  function apply(c) {
    status("⏳ در حال اعمال داده روی پست... (ممکن است تا 60 ثانیه طول بکشد)", "info");

    // غیرفعال کردن دکمه‌ها برای جلوگیری از کلیک مجدد
    var buttons = document.querySelectorAll('#ns-sync-list button');
    buttons.forEach(function (btn) {
      btn.disabled = true;
      btn.style.opacity = '0.5';
    });

    var startTime = Date.now();
    var progressInterval = setInterval(function () {
      var elapsed = Math.round((Date.now() - startTime) / 1000);
      status("⏳ در حال اعمال... (" + elapsed + " ثانیه گذشته)", "info");
    }, 2000);

    jQuery.ajax({
      url: NS_SYNC.ajaxUrl,
      type: 'POST',
      timeout: 120000, // ✅ 2 دقیقه timeout
      data: {
        action: "ns_post_sync_apply",
        nonce: NS_SYNC.nonce,
        post_id: postId,
        data: JSON.stringify(c.data),
        force: c.similarity < 0.35 ? 1 : 0,
      },
      success: function (res) {
        clearInterval(progressInterval);

        if (!res.success) {
          status("❌ " + ((res.data && res.data.message) || "خطا"), "error");
          // فعال کردن مجدد دکمه‌ها
          buttons.forEach(function (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
          });
          return;
        }

        var applied = res.data.applied || [];
        var summary = "✅ اعمال شد (" + applied.length + " مورد): " + applied.join("، ");
        status(summary, "success");

        console.log('✅ PostSync applied:', {
          post_id: res.data.post_id,
          applied: applied,
          duration_ms: Date.now() - startTime,
        });

        setTimeout(function () {
          location.reload();
        }, 1500);
      },
      error: function (xhr, status, error) {
        clearInterval(progressInterval);

        console.error('=== PostSync Apply Error ===');
        console.error('Status:', status);
        console.error('HTTP Code:', xhr.status);
        console.error('Error:', error);
        console.error('Response:', xhr.responseText?.substring(0, 500));

        var errorMessage = "❌ خطا: ";
        if (status === 'timeout') {
          errorMessage += "Timeout - درخواست بیش از 2 دقیقه طول کشید";
        } else if (xhr.status === 500) {
          var match = xhr.responseText?.match(/<b>(?:Fatal error|Warning|Notice)<\/b>:\s*([^<]+)/i);
          errorMessage += match ? 'خطای PHP: ' + match[1] : 'خطای سرور 500';
        } else if (xhr.status === 0) {
          errorMessage += "ارتباط با سرور قطع شد";
        } else {
          errorMessage += error || "خطای ناشناخته";
        }

        status(errorMessage, "error");

        // فعال کردن مجدد دکمه‌ها
        buttons.forEach(function (btn) {
          btn.disabled = false;
          btn.style.opacity = '1';
        });
      }
    });
  }

  /* ==========================================================================
     Bulk Sync
     ========================================================================== */
  var bulkOffset = 0,
    bulkDone = 0;

  function bulkRun() {
    var log = document.getElementById("ns-bulk-log");
    var btn = document.getElementById("ns-bulk-start");

    btn.disabled = true;

    function step() {
      jQuery.post(
        NS_SYNC.ajaxUrl,
        {
          action: "ns_bulk_sync_step",
          nonce: NS_SYNC.nonce,
          offset: bulkOffset,
          batch: 5,
        },
        function (res) {
          if (!res.success) {
            log.innerHTML +=
              '<div style="color:#a00;">❌ ' +
              ((res.data && res.data.message) || "خطا") +
              "</div>";
            btn.disabled = false;
            return;
          }

          var d = res.data;

          d.results.forEach(function (r) {
            var color =
              r.status === "ok"
                ? "#0a0"
                : r.status === "low_sim"
                  ? "#c80"
                  : "#a00";

            log.innerHTML +=
              '<div style="color:' +
              color +
              "'>[" +
              r.status +
              "] " +
              esc(r.title) +
              (r.sim ? " (" + r.sim + "٪)" : "") +
              "</div>";
          });

          log.scrollTop = log.scrollHeight;

          bulkOffset = d.offset;
          bulkDone += d.processed;

          document.getElementById("ns-bulk-progress").textContent =
            "پردازش‌شده: " + bulkDone;

          if (d.processed > 0) {
            setTimeout(step, 1000);
          } else {
            log.innerHTML += '<div style="color:#0a0;">✅ پایان سینک انبوه</div>';
            btn.disabled = false;
          }
        }
      ).fail(function () {
        log.innerHTML += '<div style="color:#a00;">❌ خطای ارتباط</div>';
        btn.disabled = false;
      });
    }

    step();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var btn = document.getElementById("ns-sync-open");

    if (btn) {
      btn.onclick = function () {
        openModal(parseInt(btn.dataset.post, 10), btn.dataset.query);
      };
    }

    var bulkBtn = document.getElementById("ns-bulk-start");

    if (bulkBtn) {
      bulkBtn.onclick = bulkRun;
    }
  });
})();