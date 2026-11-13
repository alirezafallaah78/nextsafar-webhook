/**
NextSafar Updates - AJAX-based updates (Fixed Version)
*/
document.addEventListener("DOMContentLoaded", function () {
  // Check Again button
  const checkAgainBtn = document.getElementById("nextsafar-check-updates");
  if (checkAgainBtn) {
    checkAgainBtn.addEventListener("click", async function () {
      this.disabled = true;
      this.textContent = "Checking...";
      try {
        const formData = new FormData();
        formData.append("action", "nextsafar_check_updates");
        formData.append("nonce", nextsafarUpdates.nonce);
        const response = await fetch(nextsafarUpdates.ajaxUrl, {
          method: "POST",
          body: formData,
        });
        const result = await response.json();
        if (result.success) {
          alert("Updates checked successfully. Refreshing page...");
          window.location.reload();
        } else {
          alert(result.data?.message || "Failed to check updates.");
        }
      } catch (error) {
        console.error(error);
        alert("Failed to check updates.");
      } finally {
        this.disabled = false;
        this.textContent = "↻ Check Again";
      }
    });
  }

  // ============================================================
  // Plugins: Select All & Update
  // ============================================================
  const selectAllPlugins = document.getElementById(
    "nextsafar-select-all-plugins",
  );
  const pluginCheckboxes = document.querySelectorAll(
    ".nextsafar-plugin-checkbox",
  );
  const updateSelectedPluginsBtn = document.getElementById(
    "nextsafar-update-selected-plugins",
  );

  if (selectAllPlugins) {
    selectAllPlugins.addEventListener("change", function () {
      pluginCheckboxes.forEach((cb) => (cb.checked = selectAllPlugins.checked));
      updatePluginsButtonState();
    });
  }
  pluginCheckboxes.forEach((cb) => {
    cb.addEventListener("change", function () {
      if (selectAllPlugins) {
        const checkedCount = document.querySelectorAll(
          ".nextsafar-plugin-checkbox:checked",
        ).length;
        selectAllPlugins.checked =
          checkedCount === pluginCheckboxes.length &&
          pluginCheckboxes.length > 0;
        selectAllPlugins.indeterminate =
          checkedCount > 0 && checkedCount < pluginCheckboxes.length;
      }
      updatePluginsButtonState();
    });
  });

  function updatePluginsButtonState() {
    if (!updateSelectedPluginsBtn) return;
    const count = document.querySelectorAll(
      ".nextsafar-plugin-checkbox:checked",
    ).length;
    updateSelectedPluginsBtn.disabled = count === 0;
    updateSelectedPluginsBtn.textContent =
      count > 0
        ? `Update ${count} Plugin${count > 1 ? "s" : ""}`
        : "Update Selected Plugins";
  }

  document
    .querySelectorAll(".nextsafar-update-single-plugin")
    .forEach((btn) => {
      btn.addEventListener("click", function () {
        const item = this.closest(".nextsafar-plugin-update");
        updatePlugin(item.dataset.pluginFile, item);
      });
    });

  if (updateSelectedPluginsBtn) {
    updateSelectedPluginsBtn.addEventListener("click", async function () {
      const selected = Array.from(
        document.querySelectorAll(".nextsafar-plugin-checkbox:checked"),
      ).map((cb) => cb.value);
      if (selected.length === 0) return;
      if (
        !confirm(
          `Are you sure you want to update ${selected.length} plugin(s)?`,
        )
      )
        return;

      for (const pluginFile of selected) {
        const item = document.querySelector(
          `.nextsafar-plugin-update[data-plugin-file="${CSS.escape(pluginFile)}"]`,
        );
        if (item) await updatePlugin(pluginFile, item);
      }
    });
  }

  // 🆕 تابع آپدیت پلاگین (اصلاح شده)
  async function updatePlugin(pluginFile, item) {
    const progressBar = item.querySelector(".nextsafar-update-progress");
    const progressFill = item.querySelector(".nextsafar-progress-fill");
    const progressText = item.querySelector(".nextsafar-progress-text");
    const statusDiv = item.querySelector(".nextsafar-update-status");
    const actionBtn = item.querySelector(".nextsafar-update-single-plugin");

    progressBar.style.display = "block";
    progressFill.style.width = "30%";
    progressText.textContent = "Preparing update...";
    statusDiv.style.display = "none";
    if (actionBtn) {
      actionBtn.disabled = true;
      actionBtn.textContent = "Updating...";
    }

    try {
      const formData = new FormData();
      formData.append("action", "nextsafar_update_plugin"); // 🆕 هوک جدید
      formData.append("nonce", nextsafarUpdates.nonce); // 🆕 نام صحیح nonce
      formData.append("plugin", pluginFile);

      progressFill.style.width = "60%";
      progressText.textContent = "Downloading...";

      const response = await fetch(nextsafarUpdates.ajaxUrl, {
        method: "POST",
        body: formData,
      });

      progressFill.style.width = "90%";
      progressText.textContent = "Installing...";

      const result = await response.json();
      progressFill.style.width = "100%";

      if (result.success) {
        progressText.textContent = "Updated!";
        statusDiv.style.display = "block";
        statusDiv.className =
          "nextsafar-update-status nextsafar-update-status-success";
        statusDiv.innerHTML =
          "✓ " + (result.data?.message || "Plugin updated successfully");
        if (actionBtn) actionBtn.style.display = "none";
      } else {
        throw new Error(result.data?.message || "Update failed");
      }
    } catch (error) {
      console.error("Update error:", error);
      progressBar.style.display = "none";
      statusDiv.style.display = "block";
      statusDiv.className =
        "nextsafar-update-status nextsafar-update-status-error";
      statusDiv.innerHTML = "✗ " + error.message;
      if (actionBtn) {
        actionBtn.disabled = false;
        actionBtn.textContent = "Retry";
      }
    }
  }

  // ============================================================
  // Themes: Select All & Update
  // ============================================================
  const selectAllThemes = document.getElementById(
    "nextsafar-select-all-themes",
  );
  const themeCheckboxes = document.querySelectorAll(
    ".nextsafar-theme-checkbox",
  );
  const updateSelectedThemesBtn = document.getElementById(
    "nextsafar-update-selected-themes",
  );

  if (selectAllThemes) {
    selectAllThemes.addEventListener("change", function () {
      themeCheckboxes.forEach((cb) => (cb.checked = selectAllThemes.checked));
      updateThemesButtonState();
    });
  }
  themeCheckboxes.forEach((cb) => {
    cb.addEventListener("change", function () {
      if (selectAllThemes) {
        const checkedCount = document.querySelectorAll(
          ".nextsafar-theme-checkbox:checked",
        ).length;
        selectAllThemes.checked =
          checkedCount === themeCheckboxes.length && themeCheckboxes.length > 0;
        selectAllThemes.indeterminate =
          checkedCount > 0 && checkedCount < themeCheckboxes.length;
      }
      updateThemesButtonState();
    });
  });

  function updateThemesButtonState() {
    if (!updateSelectedThemesBtn) return;
    const count = document.querySelectorAll(
      ".nextsafar-theme-checkbox:checked",
    ).length;
    updateSelectedThemesBtn.disabled = count === 0;
    updateSelectedThemesBtn.textContent =
      count > 0
        ? `Update ${count} Theme${count > 1 ? "s" : ""}`
        : "Update Selected Themes";
  }

  document.querySelectorAll(".nextsafar-update-single-theme").forEach((btn) => {
    btn.addEventListener("click", function () {
      const item = this.closest(".nextsafar-theme-update");
      updateTheme(item.dataset.themeSlug, item);
    });
  });

  if (updateSelectedThemesBtn) {
    updateSelectedThemesBtn.addEventListener("click", async function () {
      const selected = Array.from(
        document.querySelectorAll(".nextsafar-theme-checkbox:checked"),
      ).map((cb) => cb.value);
      if (selected.length === 0) return;
      if (
        !confirm(`Are you sure you want to update ${selected.length} theme(s)?`)
      )
        return;

      for (const themeSlug of selected) {
        const item = document.querySelector(
          `.nextsafar-theme-update[data-theme-slug="${CSS.escape(themeSlug)}"]`,
        );
        if (item) await updateTheme(themeSlug, item);
      }
    });
  }

  // 🆕 تابع آپدیت تم (اصلاح شده)
  async function updateTheme(themeSlug, item) {
    const progressBar = item.querySelector(".nextsafar-update-progress");
    const progressFill = item.querySelector(".nextsafar-progress-fill");
    const progressText = item.querySelector(".nextsafar-progress-text");
    const statusDiv = item.querySelector(".nextsafar-update-status");
    const actionBtn = item.querySelector(".nextsafar-update-single-theme");

    progressBar.style.display = "block";
    progressFill.style.width = "30%";
    progressText.textContent = "Preparing update...";
    statusDiv.style.display = "none";
    if (actionBtn) {
      actionBtn.disabled = true;
      actionBtn.textContent = "Updating...";
    }

    try {
      const formData = new FormData();
      formData.append("action", "nextsafar_update_theme"); // 🆕 هوک جدید
      formData.append("nonce", nextsafarUpdates.nonce); // 🆕 نام صحیح nonce
      formData.append("slug", themeSlug);

      progressFill.style.width = "60%";
      progressText.textContent = "Downloading...";

      const response = await fetch(nextsafarUpdates.ajaxUrl, {
        method: "POST",
        body: formData,
      });

      progressFill.style.width = "90%";
      progressText.textContent = "Installing...";

      const result = await response.json();
      progressFill.style.width = "100%";

      if (result.success) {
        progressText.textContent = "Updated!";
        statusDiv.style.display = "block";
        statusDiv.className =
          "nextsafar-update-status nextsafar-update-status-success";
        statusDiv.innerHTML =
          "✓ " + (result.data?.message || "Theme updated successfully");
        if (actionBtn) actionBtn.style.display = "none";
      } else {
        throw new Error(result.data?.message || "Update failed");
      }
    } catch (error) {
      console.error("Update error:", error);
      progressBar.style.display = "none";
      statusDiv.style.display = "block";
      statusDiv.className =
        "nextsafar-update-status nextsafar-update-status-error";
      statusDiv.innerHTML = "✗ " + error.message;
      if (actionBtn) {
        actionBtn.disabled = false;
        actionBtn.textContent = "Retry";
      }
    }
  }

  // ============================================================
  // Core Update
  // ============================================================
  const updateCoreBtn = document.querySelector(".nextsafar-update-core-btn");
  if (updateCoreBtn) {
    updateCoreBtn.addEventListener("click", async function () {
      const newVersion = this.dataset.version;
      if (
        !confirm(
          `Are you sure you want to update WordPress to version ${newVersion}?\n\nMake sure you have a backup!`,
        )
      )
        return;

      this.disabled = true;
      const originalText = this.textContent;
      this.textContent = "Updating WordPress...";

      const actionsDiv = this.closest(".nextsafar-core-update-actions");
      const progressDiv = document.createElement("div");
      progressDiv.className = "nextsafar-update-progress";
      progressDiv.style.marginTop = "16px";
      progressDiv.innerHTML = `
                <div class="nextsafar-progress-bar"><div class="nextsafar-progress-fill" style="width: 10%;"></div></div>
                <span class="nextsafar-progress-text">Downloading WordPress ${newVersion}...</span>
            `;
      actionsDiv.appendChild(progressDiv);

      const progressFill = progressDiv.querySelector(
        ".nextsafar-progress-fill",
      );
      const progressText = progressDiv.querySelector(
        ".nextsafar-progress-text",
      );

      try {
        progressFill.style.width = "30%";
        progressText.textContent = "Preparing update...";
        await new Promise((r) => setTimeout(r, 1000));

        progressFill.style.width = "50%";
        progressText.textContent = "Downloading files...";

        const formData = new FormData();
        formData.append("action", "nextsafar_update_core");
        formData.append("nonce", nextsafarUpdates.nonce);

        const response = await fetch(nextsafarUpdates.ajaxUrl, {
          method: "POST",
          body: formData,
        });

        progressFill.style.width = "80%";
        progressText.textContent = "Installing update...";

        const result = await response.json();
        if (result.success) {
          progressFill.style.width = "100%";
          progressText.textContent = "Update complete! Refreshing page...";
          await new Promise((r) => setTimeout(r, 1500));
          window.location.reload();
        } else {
          throw new Error(result.data?.message || "Update failed");
        }
      } catch (error) {
        console.error("Core update error:", error);
        progressDiv.innerHTML = `<div class="nextsafar-update-status nextsafar-update-status-error">✗ ${error.message}</div>`;
        this.disabled = false;
        this.textContent = originalText;
      }
    });
  }
});
