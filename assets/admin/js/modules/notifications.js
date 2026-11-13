/**
 * NextSafar Notifications Module
 * مدیریت dropdown نوتیفیکیشن‌ها
 */
window.NextSafarNotifications = {
  notifBtn: null,
  notifDropdown: null,
  isOpen: false,

  init() {
    this.notifBtn = document.getElementById("nextsafar-notification-btn");
    this.notifDropdown = document.getElementById(
      "nextsafar-notification-dropdown",
    );

    if (!this.notifBtn || !this.notifDropdown) return;

    this.bindEvents();
  },

  bindEvents() {
    // Toggle dropdown
    this.notifBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      this.toggle();

      // Close user menu if open
      if (window.NextSafarTopbar) {
        window.NextSafarTopbar.closeUserMenu();
      }
    });

    // Close when clicking outside
    document.addEventListener("click", (e) => {
      if (
        this.isOpen &&
        !this.notifDropdown.contains(e.target) &&
        !this.notifBtn.contains(e.target)
      ) {
        this.close();
      }
    });

    // Close on Escape
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isOpen) {
        this.close();
      }
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
    this.notifDropdown.classList.add("is-open");
    this.isOpen = true;
  },

  close() {
    this.notifDropdown.classList.remove("is-open");
    this.isOpen = false;
  },
};

// Auto-initialize
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    window.NextSafarNotifications.init();
  });
} else {
  window.NextSafarNotifications.init();
}
