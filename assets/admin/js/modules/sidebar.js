/**
 * NextSafar Sidebar Module
 * مدیریت سایدبار موبایل و responsive
 */
window.NextSafarSidebar = {
  mobileMenu: null,
  mainSidebar: null,
  isOpen: false,

  init() {
    this.mobileMenu = document.querySelector(".nextsafar-mobile-menu");
    this.mainSidebar = document.querySelector(".nextsafar-sidebar");

    if (!this.mainSidebar) return;

    this.bindEvents();
  },

  bindEvents() {
    // Mobile menu toggle
    if (this.mobileMenu) {
      this.mobileMenu.addEventListener("click", () => {
        this.toggle();
      });
    }

    // Close sidebar on window resize (if mobile)
    window.addEventListener("resize", () => {
      if (window.innerWidth > 900 && this.isOpen) {
        this.close();
      }
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener("click", (e) => {
      if (
        this.isOpen &&
        window.innerWidth <= 900 &&
        !this.mainSidebar.contains(e.target) &&
        !this.mobileMenu.contains(e.target)
      ) {
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
    this.mainSidebar.classList.add("open");
    this.isOpen = true;
  },

  close() {
    this.mainSidebar.classList.remove("open");
    this.isOpen = false;
  },
};

// Auto-initialize
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    window.NextSafarSidebar.init();
  });
} else {
  window.NextSafarSidebar.init();
}
