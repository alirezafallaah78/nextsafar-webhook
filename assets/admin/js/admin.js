/**
 * NextSafar Admin - Bootstrap File (Clean Version)
 * این فایل فقط کارهای مشترک و غیر ماژولار را انجام می‌دهد
 * ماژول‌های Search, Notifications, Topbar, Sidebar, Media به صورت خودکار initialize می‌شوند
 */
(function ($) {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    /* ============================================================
       📋 POSTS PAGE - Select All Checkbox
    ============================================================ */
    const selectAllPosts = document.getElementById("nextsafar-select-all");
    const postCheckboxes = document.querySelectorAll(
      ".nextsafar-post-checkbox",
    );

    if (selectAllPosts && postCheckboxes.length > 0) {
      selectAllPosts.addEventListener("change", function () {
        postCheckboxes.forEach(function (checkbox) {
          checkbox.checked = selectAllPosts.checked;
        });
      });

      postCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener("change", function () {
          const checkedCount = document.querySelectorAll(
            ".nextsafar-post-checkbox:checked",
          ).length;
          selectAllPosts.checked =
            checkedCount === postCheckboxes.length && postCheckboxes.length > 0;
          selectAllPosts.indeterminate =
            checkedCount > 0 && checkedCount < postCheckboxes.length;
        });
      });
    }

    /* ============================================================
       📱 MOBILE MENU (Fallback اگر ماژول لود نشد)
    ============================================================ */
    const mobileMenu = document.querySelector(".nextsafar-mobile-menu");
    const mainSidebar = document.querySelector(".nextsafar-sidebar");

    if (mobileMenu && mainSidebar && !window.NextSafarSidebar) {
      mobileMenu.addEventListener("click", function () {
        mainSidebar.classList.toggle("open");
      });
    }

    /* ============================================================
       🔍 DEBUG: Module Availability Check
    ============================================================ */
    if (window.location.search.indexOf("debug=1") !== -1) {
      console.log("📦 Module Status:", {
        NextSafarUtils: typeof window.NextSafarUtils !== "undefined",
        NextSafarAjax: typeof window.NextSafarAjax !== "undefined",
        NextSafarState: typeof window.NextSafarState !== "undefined",
        NextSafarSearch: typeof window.NextSafarSearch !== "undefined",
        NextSafarNotifications:
          typeof window.NextSafarNotifications !== "undefined",
        NextSafarTopbar: typeof window.NextSafarTopbar !== "undefined",
        NextSafarSidebar: typeof window.NextSafarSidebar !== "undefined",
        NextSafarMediaLibrary:
          typeof window.NextSafarMediaLibrary !== "undefined",
      });
    }
  });
})(jQuery);
/**
 * 🎯 Screen Options Dropdown Handler
 */
(function ($) {
  "use strict";

  const ScreenOptions = {
    isOpen: false,

    init() {
      this.bindEvents();
    },

    bindEvents() {
      $(document).on("click", "#nextsafar-screen-options-btn", (e) => {
        e.stopPropagation();
        this.toggle();
      });

      $(document).on("click", (e) => {
        if (!$(e.target).closest(".topbar-screen-options").length) {
          this.close();
        }
      });

      $(document).on("submit", "#nextsafar-screen-options-form", (e) => {
        e.preventDefault();
        this.save();
      });
    },

    toggle() {
      if (this.isOpen) {
        this.close();
      } else {
        this.open();
      }
    },

    open() {
      this.isOpen = true;
      $("#nextsafar-screen-options-dropdown").addClass("is-open");
      this.load();
    },

    close() {
      this.isOpen = false;
      $("#nextsafar-screen-options-dropdown").removeClass("is-open");
    },

    load() {
      const $content = $("#nextsafar-screen-options-content");
      $content.html(
        '<div class="screen-options-loading"><div class="nextsafar-spinner"></div><span>در حال بارگذاری...</span></div>',
      );

      // دریافت اطلاعات صفحه فعلی
      const page =
        new URLSearchParams(window.location.search).get("page") || "";
      const screenId = this.detectScreenId();

      $.ajax({
        url: ajaxurl || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: {
          action: "nextsafar_get_screen_options",
          nonce: window.nextsafarDashboardNonce || "",
          screen_id: screenId,
          page: page,
        },
        success: (response) => {
          if (response.success && response.data.html) {
            $content.html(response.data.html);
          } else {
            $content.html(
              '<p style="color:#6b7280; text-align:center;">تنظیماتی برای این صفحه وجود ندارد.</p>',
            );
          }
        },
        error: () => {
          $content.html(
            '<p style="color:#dc2626; text-align:center;">خطا در بارگذاری تنظیمات.</p>',
          );
        },
      });
    },

    save() {
      const $form = $("#nextsafar-screen-options-form");
      const formData = $form.serialize();

      $.ajax({
        url: ajaxurl || "/wp-admin/admin-ajax.php",
        type: "POST",
        data:
          formData +
          "&action=screen-options-save&screenoptionnonce=" +
          ($form.find('[name="screenoptionnonce"]').val() || ""),
        success: (response) => {
          // ذخیره در user meta
          const perPage = $form.find('[name="per_page"]').val();
          if (perPage) {
            $.post(ajaxurl || "/wp-admin/admin-ajax.php", {
              action: "nextsafar_save_screen_option",
              nonce: window.nextsafarDashboardNonce || "",
              option: "nextsafar_items_per_page",
              value: perPage,
            });
          }

          // نمایش پیام موفقیت
          $form
            .find(".submit button")
            .text("✓ ذخیره شد")
            .prop("disabled", true);
          setTimeout(() => {
            location.reload();
          }, 1000);
        },
        error: () => {
          alert("خطا در ذخیره تنظیمات.");
        },
      });
    },

    detectScreenId() {
      const page = new URLSearchParams(window.location.search).get("page");
      const path = window.location.pathname;
      const file = path.split("/").pop();

      if (page) {
        return "nextsafar-admin_page_" + page;
      }

      // صفحات native وردپرس
      const screenMap = {
        "edit.php": "edit-post",
        "post-new.php": "post",
        "post.php": "post",
        "edit-comments.php": "edit-comments",
        "edit-tags.php": "edit-tags",
        "upload.php": "upload",
        "themes.php": "themes",
        "plugins.php": "plugins",
        "users.php": "users",
        "tools.php": "tools",
        "options-general.php": "options-general",
      };

      return screenMap[file] || "dashboard";
    },
  };

  $(document).ready(() => {
    ScreenOptions.init();
  });
})(jQuery);
