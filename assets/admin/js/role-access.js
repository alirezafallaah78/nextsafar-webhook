/**
 * 🔐 NextSafar Role Access Manager
 */
(function ($) {
  "use strict";

  const RoleAccess = {
    currentRole: null,
    isDirty: false,
    config: window.nextsafarRoleAccess || {},

    init() {
      this.bindEvents();
      // به صورت پیش‌فرض اولین نقش غیر ادمین را لود کن
      const firstRole = $(".role-item:not(:disabled)").first();
      if (firstRole.length) {
        firstRole.trigger("click");
      }
    },

    bindEvents() {
      // کلیک روی نقش
      $(document).on("click", ".role-item:not(:disabled)", (e) => {
        this.loadRole($(e.currentTarget).data("role"));
      });

      // تغییر checkbox منو
      $(document).on("change", ".menu-check-input", () => {
        this.isDirty = true;
        this.updateCounts();
      });

      // تغییر checkbox حذف
      $(document).on("change", "#can_delete", () => {
        this.isDirty = true;
      });

      // دکمه ذخیره
      $(document).on("click", "#save-role-settings", () => {
        this.saveSettings();
      });

      // دکمه بازنشانی
      $(document).on("click", "#reset-role-settings", () => {
        if (confirm(this.config.strings.confirmReset)) {
          this.resetSettings();
        }
      });

      // انتخاب/لغو انتخاب همه
      $(document).on("click", ".bulk-select-all", (e) => {
        const category = $(e.currentTarget).data("category");
        $(`.menu-category[data-category="${category}"] .menu-check-input`)
          .prop("checked", true)
          .trigger("change");
      });

      $(document).on("click", ".bulk-deselect-all", (e) => {
        const category = $(e.currentTarget).data("category");
        $(`.menu-category[data-category="${category}"] .menu-check-input`)
          .prop("checked", false)
          .trigger("change");
      });
    },

    loadRole(roleSlug) {
      if (!roleSlug) return;

      // فعال کردن نقش در sidebar
      $(".role-item").removeClass("active");
      $(`.role-item[data-role="${roleSlug}"]`).addClass("active");

      this.currentRole = roleSlug;
      const $container = $("#role-settings-container");
      const roleData = this.config.roles[roleSlug];

      // نمایش loading
      $container.html(`
                <div class="settings-loading">
                    <div class="nextsafar-spinner"></div>
                    <span>در حال بارگذاری تنظیمات...</span>
                </div>
            `);

      // درخواست AJAX
      $.ajax({
        url: this.config.ajaxUrl,
        type: "GET",
        data: {
          action: "nextsafar_get_role_access",
          role: roleSlug,
        },
        success: (response) => {
          if (response.success) {
            this.renderSettings(
              roleSlug,
              roleData,
              response.data.settings,
              response.data.menus,
            );
            this.isDirty = false;
          } else {
            $container.html(
              `<div class="alert alert-warning">${response.data.message || "خطا"}</div>`,
            );
          }
        },
        error: () => {
          $container.html(
            '<div class="alert alert-warning">خطا در ارتباط با سرور</div>',
          );
        },
      });
    },

    renderSettings(roleSlug, roleData, settings, allMenus) {
      const $container = $("#role-settings-container");
      const isSuper = roleData.is_super;
      const strings = this.config.strings;

      // گروه‌بندی منوها
      const menuCategories = this.categorizeMenus(allMenus);

      let html = `
                <div class="role-settings-panel">
                    <div class="role-settings-header">
                        <div class="role-settings-title">
                            <h2>${roleData.name}</h2>
                        </div>
                        <div class="role-settings-actions">
                            <button type="button" id="reset-role-settings" class="btn btn-secondary">
                                ↺ ${strings.reset}
                            </button>
                            <button type="button" id="save-role-settings" class="btn btn-primary">
                                ${strings.save}
                            </button>
                        </div>
                    </div>
            `;

      // هشدار برای ادمین کل
      if (isSuper) {
        html += `
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚠️</span>
                        <div>${strings.superAdminWarning}</div>
                    </div>
                `;
      }

      // بخش محدودیت حذف
      html += `
                <div class="role-settings-section">
                    <h3 class="section-title">محدودیت‌های ویژه</h3>
                    <p class="section-help">محدودیت‌هایی که روی قابلیت‌های کاربران این نقش اعمال می‌شود.</p>
                    
                    <label class="delete-toggle-wrapper">
                        <input type="checkbox" 
                               id="can_delete" 
                               ${settings.can_delete ? "checked" : ""}
                               ${isSuper ? "disabled checked" : ""}>
                        <div class="delete-toggle-content">
                            <span class="delete-toggle-label">${strings.canDeleteLabel}</span>
                            <span class="delete-toggle-help">${strings.canDeleteHelp}</span>
                        </div>
                    </label>
                </div>
            `;

      // بخش منوها (فقط برای نقش‌های غیر ادمین)
      if (!isSuper) {
        html += `
                    <div class="role-settings-section">
                        <h3 class="section-title">${strings.menusSectionTitle}</h3>
                        <p class="section-help">${strings.menusSectionHelp}</p>
                `;

        if (Object.keys(menuCategories).length === 0) {
          html += `<p style="color:var(--ns-text-muted);">${strings.noMenus}</p>`;
        } else {
          const enabledMenus = settings.menus || {};

          for (const [categoryName, categoryMenus] of Object.entries(
            menuCategories,
          )) {
            const enabledCount = this.countEnabledInCategory(
              categoryMenus,
              enabledMenus,
            );
            const totalCount = this.countTotalInCategory(categoryMenus);
            const categorySlug = this.slugify(categoryName);

            html += `
                            <div class="menu-category" data-category="${categorySlug}">
                                <div class="menu-category-header">
                                    <div class="menu-category-title">
                                        <span>${categoryName}</span>
                                    </div>
                                    <span class="menu-category-count">
                                        <span class="enabled-count">${enabledCount}</span> / ${totalCount}
                                    </span>
                                </div>
                                <div class="menu-category-body">
                                    <div class="bulk-actions">
                                        <button type="button" class="bulk-btn bulk-select-all" data-category="${categorySlug}">
                                            ✓ ${strings.selectAll}
                                        </button>
                                        <button type="button" class="bulk-btn bulk-deselect-all" data-category="${categorySlug}">
                                            ✕ ${strings.deselectAll}
                                        </button>
                                    </div>
                        `;

            for (const menu of categoryMenus) {
              const isChecked = enabledMenus[menu.slug] ? "checked" : "";
              html += `
                                <div class="menu-item-wrapper">
                                    <label class="menu-item-check">
                                        <input type="checkbox" 
                                               class="menu-check-input" 
                                               name="menus[${this.escapeAttr(menu.slug)}]" 
                                               value="1"
                                               ${isChecked}>
                                        <span class="menu-item-label">${menu.title}</span>
                                        <span class="menu-item-slug">${menu.slug}</span>
                                    </label>
                            `;

              // Submenus
              if (menu.submenus && menu.submenus.length > 0) {
                html += '<div class="submenu-list">';
                for (const sub of menu.submenus) {
                  const subChecked = enabledMenus[sub.slug] ? "checked" : "";
                  html += `
                                        <label class="menu-item-check">
                                            <input type="checkbox" 
                                                   class="menu-check-input" 
                                                   name="menus[${this.escapeAttr(sub.slug)}]" 
                                                   value="1"
                                                   ${subChecked}>
                                            <span class="menu-item-label">${sub.title}</span>
                                            <span class="menu-item-slug">${sub.slug}</span>
                                        </label>
                                    `;
                }
                html += "</div>";
              }

              html += "</div>";
            }

            html += `
                                </div>
                            </div>
                        `;
          }
        }

        html += "</div>";
      }

      html += "</div>";

      $container.html(html);
    },

    categorizeMenus(menus) {
      const categories = {};

      // ✅ گروه‌بندی بر اساس فیلد section
      const sectionNames = {
        MAIN: "اصلی",
        CONTENT: "محتوا",
        APPEARANCE: "ظاهر",
        MANAGEMENT: "مدیریت",
        PLUGINS: "پلاگین‌ها",
      };

      for (const menu of menus) {
        const section = menu.section || "PLUGINS";
        const categoryName = sectionNames[section] || "سایر";

        if (!categories[categoryName]) {
          categories[categoryName] = [];
        }
        categories[categoryName].push(menu);
      }

      // مرتب‌سازی بر اساس ترتیب دلخواه
      const order = ["اصلی", "محتوا", "ظاهر", "مدیریت", "پلاگین‌ها", "سایر"];
      const sorted = {};
      for (const key of order) {
        if (categories[key]) {
          sorted[key] = categories[key];
        }
      }

      return sorted;
    },

    getCategoryIcon(category) {
      const match = category.match(/^(\S+)/);
      return match ? match[1] : "";
    },

    countEnabledInCategory(menus, enabledMenus) {
      let count = 0;
      for (const menu of menus) {
        if (enabledMenus[menu.slug]) count++;
        if (menu.submenus) {
          for (const sub of menu.submenus) {
            if (enabledMenus[sub.slug]) count++;
          }
        }
      }
      return count;
    },

    countTotalInCategory(menus) {
      let count = menus.length;
      for (const menu of menus) {
        if (menu.submenus) {
          count += menu.submenus.length;
        }
      }
      return count;
    },

    updateCounts() {
      $(".menu-category").each((_, cat) => {
        const $cat = $(cat);
        const total = $cat.find(".menu-check-input").length;
        const enabled = $cat.find(".menu-check-input:checked").length;
        $cat.find(".enabled-count").text(enabled);
      });
    },

    saveSettings() {
      if (!this.currentRole) return;

      const $button = $("#save-role-settings");
      const originalText = $button.html();
      $button
        .prop("disabled", true)
        .html(
          `<span class="nextsafar-spinner" style="width:16px;height:16px;border-width:2px;"></span> ${this.config.strings.saving}`,
        );

      const menus = {};
      $(".menu-check-input:checked").each((_, input) => {
        menus[input.name.replace("menus[", "").replace("]", "")] = true;
      });

      const canDelete = $("#can_delete").is(":checked");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "nextsafar_save_role_access",
          nonce: this.config.nonce,
          role: this.currentRole,
          menus: menus,
          can_delete: canDelete ? "1" : "0",
        },
        success: (response) => {
          if (response.success) {
            $button.html(`✓ ${this.config.strings.saved}`);
            this.isDirty = false;

            // به‌روزرسانی تعداد منوها در sidebar
            const count = Object.keys(menus).length;
            $(
              `.role-item[data-role="${this.currentRole}"] .role-menu-count`,
            ).text(`${count} منو`);

            setTimeout(() => {
              $button.prop("disabled", false).html(originalText);
            }, 2000);
          } else {
            $button.prop("disabled", false).html(originalText);
            alert(response.data.message || this.config.strings.error);
          }
        },
        error: () => {
          $button.prop("disabled", false).html(originalText);
          alert(this.config.strings.error);
        },
      });
    },

    resetSettings() {
      if (!this.currentRole) return;

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "nextsafar_reset_role_access",
          nonce: this.config.nonce,
          role: this.currentRole,
        },
        success: (response) => {
          if (response.success) {
            this.loadRole(this.currentRole);
          } else {
            alert(response.data.message || "خطا");
          }
        },
      });
    },

    slugify(text) {
      return text
        .toLowerCase()
        .replace(/[^\w\s-]/g, "")
        .replace(/[\s_-]+/g, "-")
        .replace(/^-+|-+$/g, "");
    },

    escapeAttr(text) {
      return text.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    },
  };

  $(document).ready(() => {
    RoleAccess.init();
  });

  window.NextSafarRoleAccess = RoleAccess;
})(jQuery);
