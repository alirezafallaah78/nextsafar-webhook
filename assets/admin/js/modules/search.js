/**
 * NextSafar Search Module - جستجوی گلوبال
 */
(function () {
  "use strict";

  const SearchModule = {
    searchInput: null,
    searchResults: null,
    searchTimeout: null,

    init() {
      this.searchInput = document.getElementById("nextsafar-global-search");
      this.searchResults = document.getElementById("nextsafar-search-results");

      if (!this.searchInput || !this.searchResults) return;

      this.bindEvents();
    },

    bindEvents() {
      // Search input
      this.searchInput.addEventListener("input", () => {
        const query = this.searchInput.value.trim();
        clearTimeout(this.searchTimeout);

        if (query.length < 2) {
          this.hideResults();
          return;
        }

        this.searchTimeout = setTimeout(() => {
          this.performSearch(query);
        }, 300);
      });

      // Focus
      this.searchInput.addEventListener("focus", () => {
        if (this.searchInput.value.trim().length >= 2) {
          this.showResults();
        }
      });

      // Click outside
      document.addEventListener("click", (e) => {
        if (!e.target.closest(".topbar-search")) {
          this.hideResults();
        }
      });

      // Keyboard shortcut (Ctrl/Cmd + K)
      document.addEventListener("keydown", (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === "k") {
          e.preventDefault();
          this.searchInput.focus();
          this.searchInput.select();
        }
      });
    },

    async performSearch(query) {
      this.showResults();
      this.showLoading();

      try {
        const results = await NextSafarAjax.request("nextsafar_global_search", {
          nonce: window.nextsafarAdmin.dashboardNonce,
          query: query,
        });

        this.renderResults(results.results || []);
      } catch (error) {
        this.showError();
      }
    },

    renderResults(results) {
      if (!results || results.length === 0) {
        this.searchResults.innerHTML =
          '<div class="search-results-empty">نتیجه‌ای یافت نشد</div>';
        return;
      }

      const icons = {
        post: "📝",
        page: "📄",
        tour: "✈",
        visa: "🛂",
        attachment: "🖼",
        category: "▧",
        user: "👤",
      };

      let html = '<div class="search-results-header">نتایج جستجو</div>';

      results.forEach((item) => {
        const icon = icons[item.type] || "📋";
        html += `
                    <a href="${item.url}" class="search-result-item">
                        <div class="search-result-icon">${icon}</div>
                        <div class="search-result-content">
                            <strong>${NextSafarUtils.escapeHtml(item.title)}</strong>
                            <small>${NextSafarUtils.escapeHtml(item.type_label)}</small>
                        </div>
                    </a>
                `;
      });

      this.searchResults.innerHTML = html;
    },

    showLoading() {
      this.searchResults.innerHTML =
        '<div class="search-results-empty">در حال جستجو...</div>';
    },

    showError() {
      this.searchResults.innerHTML =
        '<div class="search-results-empty">خطا در جستجو</div>';
    },

    showResults() {
      this.searchResults.style.display = "block";
    },

    hideResults() {
      this.searchResults.style.display = "none";
    },
  };

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => SearchModule.init());
  } else {
    SearchModule.init();
  }
})();
