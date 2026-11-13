/**
 * NextSafar Topbar Module
 * مدیریت User Menu و تعاملات تاپبار
 */
window.NextSafarTopbar = {
  userTrigger: null,
  userMenu: null,
  isUserMenuOpen: false,

  init() {
    this.userTrigger = document.getElementById("nextsafar-user-trigger");
    this.userMenu = document.getElementById("nextsafar-user-menu");

    if (!this.userTrigger || !this.userMenu) return;

    this.bindEvents();
  },

  bindEvents() {
    // Toggle user menu
    this.userTrigger.addEventListener("click", (e) => {
      e.stopPropagation();
      this.toggleUserMenu();

      // Close notifications if open
      if (window.NextSafarNotifications) {
        window.NextSafarNotifications.close();
      }
    });

    // Close when clicking outside
    document.addEventListener("click", (e) => {
      if (this.isUserMenuOpen && !this.userMenu.contains(e.target)) {
        this.closeUserMenu();
      }
    });

    // Close on Escape
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isUserMenuOpen) {
        this.closeUserMenu();
      }
    });
  },

  toggleUserMenu() {
    if (this.isUserMenuOpen) {
      this.closeUserMenu();
    } else {
      this.openUserMenu();
    }
  },

  openUserMenu() {
    this.userMenu.classList.add("is-open");
    this.isUserMenuOpen = true;
  },

  closeUserMenu() {
    this.userMenu.classList.remove("is-open");
    this.isUserMenuOpen = false;
  },
};

// Auto-initialize
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    window.NextSafarTopbar.init();
  });
} else {
  window.NextSafarTopbar.init();
}

/**
 * 🎯 NextSafar Screen Options Manager
 */
(function ($) {
  "use strict";

  const ScreenOptions = {
    isOpen: false,
    isLoading: false,

    init() {
      this.bindEvents();
    },

    bindEvents() {
      // کلیک روی دکمه
      $(document).on("click", "#nextsafar-screen-options-btn", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.toggle();
      });

      // کلیک خارج از dropdown
      $(document).on("click", (e) => {
        if (!$(e.target).closest(".topbar-screen-options").length) {
          this.close();
        }
      });

      // Escape key
      $(document).on("keydown", (e) => {
        if (e.key === "Escape" && this.isOpen) {
          this.close();
        }
      });

      // Submit فرم
      $(document).on("submit", "#nextsafar-screen-options-form", (e) => {
        e.preventDefault();
        this.submitForm(e.target);
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
      const $dropdown = $("#nextsafar-screen-options-dropdown");

      // بستن dropdown notification
      $("#nextsafar-notification-dropdown").removeClass("open");
      $("#nextsafar-user-dropdown").removeClass("open");

      $dropdown.addClass("open");
      this.isOpen = true;

      // لود محتوا اگر قبلاً لود نشده
      if (
        !this.isLoading &&
        $dropdown.find(".screen-options-form-loaded").length === 0
      ) {
        this.loadContent();
      }
    },

    close() {
      $("#nextsafar-screen-options-dropdown").removeClass("open");
      this.isOpen = false;
    },

    loadContent() {
      if (this.isLoading) return;

      this.isLoading = true;
      const $content = $("#nextsafar-screen-options-content");

      // بررسی وجود nextsafarAdmin
      if (typeof nextsafarAdmin === "undefined" || !nextsafarAdmin.ajaxUrl) {
        $content.html(
          '<p style="color:red; text-align:center;">خطا: تنظیمات لود نشد</p>',
        );
        this.isLoading = false;
        return;
      }

      // 🎯 استخراج screen_id از body class یا $_GET
      let screenId = "";

      // روش ۱: از wp.screen (اگر موجود باشد)
      if (typeof wp !== "undefined" && wp.screen && wp.screen.id) {
        screenId = wp.screen.id;
      }

      // روش ۲: از body class
      if (!screenId) {
        const bodyClasses = document.body.className.split(" ");
        for (let cls of bodyClasses) {
          if (cls.includes("page_") || cls.includes("toplevel_page_")) {
            screenId = cls;
            break;
          }
        }
      }

      // روش ۳: از URL parameter
      if (!screenId) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get("page");
        if (page) {
          if (page === "nextsafar-admin") {
            screenId = "toplevel_page_nextsafar-admin";
          } else {
            screenId = "nextsafar-admin_page_" + page;
          }
        }
      }

      // 🎯 استخراج page از URL
      const urlParams = new URLSearchParams(window.location.search);
      const page = urlParams.get("page") || "";

      $.ajax({
        url: nextsafarAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "nextsafar_get_screen_options",
          nonce: nextsafarAdmin.dashboardNonce,
          screen_id: screenId, // 🆕 ارسال screen_id
          page: page, // 🆕 ارسال page
        },
        success: (response) => {
          if (response.success) {
            $content.html(
              response.data.html +
                '<div class="screen-options-form-loaded"></div>',
            );
          } else {
            $content.html(
              '<p style="color:red; text-align:center;">خطا: ' +
                (response.data.message || "نامشخص") +
                "</p>",
            );
          }
        },
        error: () => {
          $content.html(
            '<p style="color:red; text-align:center;">خطا در ارتباط با سرور</p>',
          );
        },
        complete: () => {
          this.isLoading = false;
        },
      });
    },

    submitForm(form) {
      const $form = $(form);
      const $button = $form.find('button[type="submit"]');
      const originalText = $button.text();

      $button.prop("disabled", true).text("در حال ذخیره...");

      // تشخیص نوع صفحه
      const isDashboard = $form.find('input[name="widgets[]"]').length > 0;
      const isListPage = $form.find('input[name="per_page"]').length > 0;

      if (isDashboard) {
        // ذخیره تنظیمات ویجت‌های داشبورد
        const enabledWidgets = $form
          .find('input[name="widgets[]"]:checked')
          .map(function () {
            return this.value;
          })
          .get();

        $.ajax({
          url: nextsafarAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "nextsafar_save_dashboard_widgets",
            nonce: nextsafarAdmin.dashboardNonce,
            widgets: enabledWidgets,
          },
          success: (response) => {
            if (response.success) {
              this.showSuccess($button, "ذخیره شد!");
              setTimeout(() => {
                window.location.reload();
              }, 800);
            } else {
              this.showError($button, originalText);
            }
          },
          error: () => {
            this.showError($button, originalText);
          },
        });
      } else if (isListPage) {
        // ذخیره تنظیمات صفحه‌بندی
        const perPage = parseInt(
          $form.find('input[name="per_page"]').val(),
          10,
        );

        if (perPage < 5 || perPage > 200) {
          alert("تعداد باید بین ۵ تا ۲۰۰ باشد");
          $button.prop("disabled", false).text(originalText);
          return;
        }

        // شبیه‌سازی submit فرم وردپرس
        const formData = new FormData();
        formData.append("wp_screen_options[value]", perPage);
        formData.append(
          "wp_screen_options[option]",
          "nextsafar_items_per_page",
        );
        formData.append(
          "screenoptionnonce",
          $form.find('input[name="screenoptionnonce"]').val() || "",
        );
        formData.append("screen-options-apply", "1");

        $.ajax({
          url: window.location.href,
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          success: () => {
            this.showSuccess($button, "ذخیره شد!");
            setTimeout(() => {
              window.location.reload();
            }, 800);
          },
          error: () => {
            this.showError($button, originalText);
          },
        });
      }
    },

    showSuccess($button, text) {
      $button.css("background", "#16a34a").text("✓ " + text);
    },

    showError($button, originalText) {
      $button.css("background", "#dc2626").text("خطا!");
      setTimeout(() => {
        $button
          .prop("disabled", false)
          .css("background", "")
          .text(originalText);
      }, 2000);
    },
  };

  // Initialize
  $(document).ready(() => {
    ScreenOptions.init();
  });

  // Export to global
  window.NextSafarScreenOptions = ScreenOptions;
})(jQuery);
