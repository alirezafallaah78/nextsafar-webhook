/**
NextSafar Media Modal Enhancer - Ultra Fast Version
✅ ثبت دیتا در wp.media Store (حذف Fetch های Sync)
✅ انتخاب مستقیم با کلیک در حالت Featured/Gallery
✅ بهینه‌سازی Bulk Selection برای ۱۰۰+ عکس
*/
(function ($) {
  "use strict";
  if (typeof wp === "undefined" || typeof wp.media === "undefined") return;

  // ============================================
  // 📂 State Management
  // ============================================
  let foldersData = { folders: [], all_media_count: 0, uncategorized_count: 0 };
  const foldersDataEl = document.getElementById("nextsafar-folders-data");
  if (foldersDataEl) {
    try {
      foldersData = JSON.parse(foldersDataEl.textContent);
    } catch (e) {
      console.error("Failed to parse folders data:", e);
    }
  }

  let currentFolderId = 0;
  let currentFolderType = "all";
  let currentPage = 1;
  let isLoading = false;
  let hasMore = true;
  let currentSearch = "";
  let currentMimeFilter = "";
  let isFeaturedImageMode = false;
  let currentState = "library";
  let selectMode = false;
  const localSelectedIds = new Set();
  let lastClickedCard = null;

  const responseCache = new Map();
  const CACHE_MAX_SIZE = 50;
  const CACHE_TTL = 5 * 60 * 1000;

  const strings =
    typeof nextsafarMediaModal !== "undefined" && nextsafarMediaModal.strings
      ? nextsafarMediaModal.strings
      : {
          folders: "پوشه‌ها",
          allMedia: "همه رسانه‌ها",
          uncategorized: "بدون پوشه",
          searchFolders: "جستجو در پوشه‌ها...",
          mediaSearch: "جستجو در رسانه‌ها...",
          loading: "در حال بارگذاری...",
          noResults: "رسانه‌ای یافت نشد",
          noResultsDesc: "فیلتر دیگری را امتحان کنید.",
          loadMore: "بارگذاری بیشتر",
          selectMode: "انتخاب گروهی",
          cancelSelect: "انصراف",
          applySelection: "تایید انتخاب",
          selectedCount: "انتخاب شده",
          imageDetails: "جزئیات تصویر",
          close: "بستن",
          save: "ذخیره تغییرات",
          saving: "در حال ذخیره...",
          saved: "ذخیره شد",
          selectImage: "انتخاب این تصویر",
        };

  // ============================================
  // Cache
  // ============================================
  function getCacheKey(folderId, search, page, mime) {
    return `${folderId}_${search}_${page}_${mime}`;
  }
  function getFromCache(key) {
    const cached = responseCache.get(key);
    if (!cached) return null;
    if (Date.now() - cached.time > CACHE_TTL) {
      responseCache.delete(key);
      return null;
    }
    return cached.data;
  }
  function setCache(key, data) {
    if (responseCache.size >= CACHE_MAX_SIZE) {
      const firstKey = responseCache.keys().next().value;
      responseCache.delete(firstKey);
    }
    responseCache.set(key, { data, time: Date.now() });
  }
  function escapeHtml(text) {
    if (!text && text !== 0) return "";
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
  }

  // ============================================
  // 🎨 HTML Generators
  // ============================================
  function renderFolderTree(folders, depth = 0) {
    let html = "";
    folders.forEach(function (folder) {
      const hasChildren = folder.children && folder.children.length > 0;
      const isActive =
        currentFolderType === "folder" &&
        currentFolderId === parseInt(folder.id);
      const indent = depth * 12;

      html += `<div class="nextsafar-folder-wrapper" data-folder-id="${folder.id}" style="padding-right: ${indent}px;">`;
      html += `<div class="nextsafar-folder-item${isActive ? " active" : ""}" data-folder-id="${folder.id}" data-folder-name="${escapeHtml(folder.name)}">`;
      html += hasChildren
        ? '<span class="nextsafar-folder-toggle">▸</span>'
        : '<span class="nextsafar-folder-toggle"></span>';
      html += '<span class="nextsafar-folder-icon">📁</span>';
      html += `<span class="nextsafar-folder-name">${escapeHtml(folder.name)}</span>`;
      if (folder.media_count > 0)
        html += `<span class="nextsafar-folder-count">${folder.media_count}</span>`;
      html += "</div>";

      if (hasChildren) {
        html += '<div class="nextsafar-folder-children" style="display:none;">';
        html += renderFolderTree(folder.children, depth + 1);
        html += "</div>";
      }
      html += "</div>";
    });
    return html;
  }

  function buildSidebarHTML() {
    let html = '<aside class="nextsafar-folders-sidebar">';
    html +=
      '<div class="nextsafar-folders-header"><h3>' +
      strings.folders +
      "</h3></div>";
    html +=
      '<div style="padding: 10px 12px; border-bottom: 1px solid var(--ns-border); background: #fff;">';
    html += `<input type="text" placeholder="${strings.searchFolders}" class="nextsafar-search-input nextsafar-folder-search">`;
    html += "</div><div class='nextsafar-folders-tree'>";

    html += `<div class="nextsafar-folder-item${currentFolderType === "all" ? " active" : ""}" data-folder-id="0" data-folder-type="all">`;
    html +=
      '<span class="nextsafar-folder-toggle"></span><span class="nextsafar-folder-icon">▣</span>';
    html += `<span class="nextsafar-folder-name">${strings.allMedia}</span>`;
    html += `<span class="nextsafar-folder-count">${foldersData.all_media_count || "—"}</span></div>`;

    html += `<div class="nextsafar-folder-item${currentFolderType === "uncategorized" ? " active" : ""}" data-folder-id="-1" data-folder-type="uncategorized">`;
    html +=
      '<span class="nextsafar-folder-toggle"></span><span class="nextsafar-folder-icon">▤</span>';
    html += `<span class="nextsafar-folder-name">${strings.uncategorized}</span>`;
    html += `<span class="nextsafar-folder-count">${foldersData.uncategorized_count || "—"}</span></div>`;

    html += '<div class="nextsafar-folders-divider"></div>';
    if (foldersData.folders && foldersData.folders.length > 0) {
      html += renderFolderTree(foldersData.folders, 0);
    }
    html += "</div></aside>";
    return html;
  }

  // ============================================
  // 🚀 Fetch Media
  // ============================================
  async function fetchMedia(folderId, search, page, mime) {
    const cacheKey = getCacheKey(folderId, search, page, mime);
    const cached = getFromCache(cacheKey);
    if (cached) return cached;

    const formData = new FormData();
    formData.append("action", "nextsafar_modal_media");
    formData.append("nonce", nextsafarMediaModal.modalNonce);
    formData.append("folder_id", folderId);
    formData.append("search", search || "");
    formData.append("paged", page || 1);
    formData.append("per_page", 40);
    if (mime) formData.append("mime_type", mime);

    try {
      const response = await fetch(nextsafarMediaModal.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();
      if (result.success) {
        setCache(cacheKey, result.data);
        return result.data;
      }
      return null;
    } catch (e) {
      console.error("Fetch media error:", e);
      return null;
    }
  }

  // ============================================
  // 🎯 Inject into Modal
  // ============================================
  let injectionDebounceTimer = null;
  function scheduleInjection(modal, delay = 50) {
    if (injectionDebounceTimer) clearTimeout(injectionDebounceTimer);
    injectionDebounceTimer = setTimeout(function () {
      injectIntoModal(modal);
    }, delay);
  }

  function injectIntoModal(modal) {
    const $modal = $(modal);
    const $content = $modal.find(".media-frame-content");
    if ($content.length === 0) return;

    isFeaturedImageMode = false;
    currentState = "library";
    try {
      if (wp.media.frame && wp.media.frame.state()) {
        const state = wp.media.frame.state();
        currentState = (state.id || "").toLowerCase();
        const multiple = state.get("multiple");
        if (!multiple || multiple === false) isFeaturedImageMode = true;
      }
    } catch (e) {}

    const hasSidebar = $modal.find(".nextsafar-folders-sidebar").length > 0;
    const shouldShowSidebar =
      currentState.indexOf("library") !== -1 ||
      currentState === "" ||
      currentState === "featured-image" ||
      currentState === "insert" ||
      currentState.indexOf("gallery") !== -1;

    if (!shouldShowSidebar) {
      if (hasSidebar) {
        $modal.find(".nextsafar-folders-sidebar").remove();
        const $wrapper = $modal.find(".nextsafar-modal-wrapper");
        if ($wrapper.length > 0) {
          const $children = $wrapper.children().detach();
          $content.empty().append($children);
          $content.css("display", "");
        }
      }
      return;
    }

    if (hasSidebar) return;

    const $attachmentsBrowser = $content.find(".attachments-browser");
    if ($attachmentsBrowser.length === 0) {
      setTimeout(function () {
        injectIntoModal(modal);
      }, 100);
      return;
    }

    $content.css({ display: "flex", background: "var(--ns-bg)" });
    $attachmentsBrowser.css("display", "none");
    $content.prepend(buildSidebarHTML());

    const selectActionsHtml = isFeaturedImageMode
      ? ""
      : `
        <div class="nextsafar-modal-select-actions">
            <button type="button" class="nextsafar-modal-select-btn" id="nextsafar-modal-select-toggle">${strings.selectMode}</button>
            <div class="nextsafar-modal-selection-info" style="display:none;">
                <span class="nextsafar-modal-selected-count">0 ${strings.selectedCount}</span>
                <button type="button" class="nextsafar-modal-apply-btn" id="nextsafar-modal-apply">${strings.applySelection}</button>
                <button type="button" class="nextsafar-modal-cancel-btn" id="nextsafar-modal-cancel-select" title="${strings.cancelSelect}">×</button>
            </div>
        </div>`;

    const $wrapper = $(`
        <div class="nextsafar-media-main nextsafar-modal-wrapper">
            <div class="nextsafar-media-toolbar">
                <div class="nextsafar-search">
                    <span class="nextsafar-search-icon">🔍</span>
                    <input type="text" class="nextsafar-search-input nextsafar-modal-search-input" placeholder="${strings.mediaSearch}">
                </div>
                <div class="nextsafar-mime-filters">
                    <button type="button" class="nextsafar-mime-btn active" data-mime="">All</button>
                    <button type="button" class="nextsafar-mime-btn" data-mime="image">Images</button>
                    <button type="button" class="nextsafar-mime-btn" data-mime="video">Videos</button>
                    <button type="button" class="nextsafar-mime-btn" data-mime="audio">Audio</button>
                </div>
                ${selectActionsHtml}
                <div class="nextsafar-filter-container"></div>
            </div>
            <div class="nextsafar-modal-grid-scroll">
                <div class="nextsafar-media-grid"></div>
                <div class="nextsafar-load-more" style="display:none;">
                    <button type="button" class="nextsafar-load-more-btn">${strings.loadMore}</button>
                </div>
                <div class="nextsafar-modal-sentinel" style="height:1px;"></div>
            </div>
        </div>`);

    $content.append($wrapper);
    bindEvents($content, $modal);
    resetAndLoad($wrapper, 0, "", "");
  }

  // ============================================
  // 🔄 Load Media & 🚀 Register to WP Store
  // ============================================
  async function resetAndLoad($wrapper, folderId, search, mime) {
    if (folderId === 0) {
      currentFolderId = 0;
      currentFolderType = "all";
    } else if (folderId === -1) {
      currentFolderId = -1;
      currentFolderType = "uncategorized";
    } else {
      currentFolderId = folderId;
      currentFolderType = "folder";
    }

    currentSearch = search;
    currentMimeFilter = mime;
    currentPage = 1;
    hasMore = true;
    lastClickedCard = null;

    const $grid = $wrapper.find(".nextsafar-media-grid");
    $grid.html(
      `<div class="nextsafar-media-empty"><div class="nextsafar-spinner"></div><strong>${strings.loading}</strong></div>`,
    );
    await loadMediaPage($wrapper, true);
    updateFolderActiveState($wrapper);
  }

  async function loadMediaPage($wrapper, isReset) {
    if (isLoading || !hasMore) return;
    isLoading = true;
    const $grid = $wrapper.find(".nextsafar-media-grid");
    const $loadMore = $wrapper.find(".nextsafar-load-more");

    if (!isReset) {
      $loadMore
        .show()
        .find("button")
        .prop("disabled", true)
        .text(strings.loading);
    }

    const result = await fetchMedia(
      currentFolderId,
      currentSearch,
      currentPage,
      currentMimeFilter,
    );

    if (!result) {
      if (isReset)
        $grid.html(
          `<div class="nextsafar-media-empty"><strong>Error</strong><span>Failed to load media.</span></div>`,
        );
      isLoading = false;
      return;
    }

    if (isReset) $grid.empty();

    if (result.items.length === 0) {
      if (isReset)
        $grid.html(
          `<div class="nextsafar-media-empty"><strong>${strings.noResults}</strong><span>${strings.noResultsDesc}</span></div>`,
        );
      hasMore = false;
      $loadMore.hide();
    } else {
      let html = "";
      result.items.forEach(function (item) {
        const isSelected = localSelectedIds.has(item.id);
        const isImage = item.mime && item.mime.indexOf("image") === 0;
        const previewSrc = item.thumbnail || item.url;

        // 🚀 ثبت دیتا در wp.media Store (حیاتی برای سرعت انتخاب)
        if (typeof wp !== "undefined" && wp.media && wp.media.attachment) {
          try {
            const att = wp.media.attachment(item.id);
            if (!att.get("url")) {
              att.set({
                id: item.id,
                url: item.url,
                title: item.title,
                filename: item.filename,
                mime: item.mime,
                type: item.type,
                dateFormatted: item.date,
                width: item.width,
                height: item.height,
                sizes: {
                  full: {
                    url: item.full,
                    width: item.width,
                    height: item.height,
                  },
                  medium: {
                    url: item.thumbnail,
                    width: item.width,
                    height: item.height,
                  },
                  thumbnail: { url: item.thumbnail },
                },
              });
            }
          } catch (e) {}
        }

        html += `<div class="nextsafar-media-card${isSelected ? " selected" : ""}" data-media-id="${item.id}" data-attachment-id="${item.id}">`;
        html += `<div class="nextsafar-media-select-checkbox"><input type="checkbox" class="nextsafar-media-checkbox" data-media-id="${item.id}" ${isSelected ? "checked" : ""}></div>`;
        html += `<div class="nextsafar-media-preview">`;
        if (isImage && previewSrc) {
          html += `<img src="${escapeHtml(previewSrc)}" alt="${escapeHtml(item.title)}" loading="lazy">`;
        } else {
          html += `<div class="nextsafar-media-file-icon">▣</div>`;
        }
        html += `</div>`;
        html += `<div class="nextsafar-media-info"><strong>${escapeHtml(item.title || item.filename)}</strong><span>${escapeHtml(item.mime)}</span></div>`;
        html += `</div>`;
      });
      $grid.append(html);

      if (currentPage >= result.total_pages) {
        hasMore = false;
        $loadMore.hide();
      } else {
        hasMore = true;
        currentPage++;
        $loadMore
          .show()
          .find("button")
          .prop("disabled", false)
          .text(strings.loadMore);
      }
    }
    isLoading = false;
  }

  // ============================================
  // ♾️ Infinite Scroll
  // ============================================
  let scrollObserver = null;
  function initInfiniteScroll($wrapper) {
    if (scrollObserver) scrollObserver.disconnect();
    const sentinel = $wrapper.find(".nextsafar-modal-sentinel")[0];
    if (!sentinel) return;
    scrollObserver = new IntersectionObserver(
      function (entries) {
        if (entries[0].isIntersecting && hasMore && !isLoading)
          loadMediaPage($wrapper, false);
      },
      { rootMargin: "400px 0px" },
    );
    scrollObserver.observe(sentinel);
  }

  // ============================================
  // 🎯 Selection Management
  // ============================================
  function enterSelectMode($wrapper) {
    selectMode = true;
    localSelectedIds.clear();
    lastClickedCard = null;
    $wrapper.addClass("nextsafar-selection-mode");
    $wrapper.find("#nextsafar-modal-select-toggle").hide();
    $wrapper.find(".nextsafar-modal-selection-info").show();
    updateSelectionUI($wrapper);
  }

  function exitSelectMode($wrapper) {
    selectMode = false;
    localSelectedIds.clear();
    lastClickedCard = null;
    $wrapper.removeClass("nextsafar-selection-mode");
    $wrapper.find("#nextsafar-modal-select-toggle").show();
    $wrapper.find(".nextsafar-modal-selection-info").hide();
    $wrapper.find(".nextsafar-media-card").removeClass("selected");
    $wrapper.find(".nextsafar-media-checkbox").prop("checked", false);
    updateSelectionUI($wrapper);
  }

  function updateSelectionUI($wrapper) {
    const count = localSelectedIds.size;
    $wrapper
      .find(".nextsafar-modal-selected-count")
      .text(`${count} ${strings.selectedCount}`);
    $wrapper.find("#nextsafar-modal-apply").prop("disabled", count === 0);
  }

  function toggleCardSelection(cardElement, $wrapper) {
    const id = parseInt(cardElement.getAttribute("data-attachment-id"));
    const $card = $(cardElement);
    const $checkbox = $card.find(".nextsafar-media-checkbox");

    if (localSelectedIds.has(id)) {
      localSelectedIds.delete(id);
      $card.removeClass("selected");
      $checkbox.prop("checked", false);
    } else {
      localSelectedIds.add(id);
      $card.addClass("selected");
      $checkbox.prop("checked", true);
    }
    lastClickedCard = cardElement;
    updateSelectionUI($wrapper);
  }

  function selectRange(fromCard, toCard, $wrapper) {
    const allCards = Array.from(
      $wrapper[0].querySelectorAll(".nextsafar-media-card"),
    );
    const fromIndex = allCards.indexOf(fromCard);
    const toIndex = allCards.indexOf(toCard);
    if (fromIndex === -1 || toIndex === -1) return;

    const start = Math.min(fromIndex, toIndex);
    const end = Math.max(fromIndex, toIndex);
    for (let i = start; i <= end; i++) {
      const card = allCards[i];
      const id = parseInt(card.getAttribute("data-attachment-id"));
      if (!localSelectedIds.has(id)) {
        localSelectedIds.add(id);
        card.classList.add("selected");
        const checkbox = card.querySelector(".nextsafar-media-checkbox");
        if (checkbox) checkbox.checked = true;
      }
    }
    updateSelectionUI($wrapper);
  }

  function updateFolderActiveState($wrapper) {
    $wrapper.find(".nextsafar-folder-item.active").removeClass("active");
    let selector = "";
    if (currentFolderType === "all")
      selector = '.nextsafar-folder-item[data-folder-type="all"]';
    else if (currentFolderType === "uncategorized")
      selector = '.nextsafar-folder-item[data-folder-type="uncategorized"]';
    else
      selector = `.nextsafar-folder-item[data-folder-id="${currentFolderId}"]`;

    const activeItem = $wrapper.find(selector);
    if (activeItem.length) activeItem.addClass("active");
  }

  // ============================================
  // 🖼️ Image Details Modal
  // ============================================
  async function openDetailsModal(attachmentId) {
    const existingModal = document.querySelector(
      ".nextsafar-image-details-modal",
    );
    if (existingModal) existingModal.remove();

    const modal = document.createElement("div");
    modal.className = "nextsafar-image-details-modal";
    const selectBtnHtml =
      isFeaturedImageMode || currentState.indexOf("gallery") !== -1
        ? `<button type="button" class="nextsafar-image-details-select-btn" data-attachment-id="${attachmentId}">${strings.selectImage}</button>`
        : "";

    modal.innerHTML = `
        <div class="nextsafar-image-details-backdrop"></div>
        <div class="nextsafar-image-details-content">
            <div class="nextsafar-image-details-header">
                <h3>${strings.imageDetails}</h3>
                <button type="button" class="nextsafar-image-details-close" title="${strings.close}">×</button>
            </div>
            <div class="nextsafar-image-details-body">
                <div class="nextsafar-image-details-preview"><div class="nextsafar-spinner"></div></div>
                <div class="nextsafar-image-details-info"><div class="nextsafar-spinner"></div></div>
            </div>
            <div class="nextsafar-image-details-footer">
                <button type="button" class="nextsafar-image-details-cancel-btn">${strings.close}</button>
                <button type="button" class="nextsafar-image-details-save-btn" data-attachment-id="${attachmentId}">${strings.save}</button>
                ${selectBtnHtml}
            </div>
        </div>`;
    document.body.appendChild(modal);

    const closeBtn = modal.querySelector(".nextsafar-image-details-close");
    const cancelBtn = modal.querySelector(
      ".nextsafar-image-details-cancel-btn",
    );
    const backdrop = modal.querySelector(".nextsafar-image-details-backdrop");
    const selectBtn = modal.querySelector(
      ".nextsafar-image-details-select-btn",
    );
    const saveBtn = modal.querySelector(".nextsafar-image-details-save-btn");

    const closeModal = () => {
      modal.classList.add("closing");
      setTimeout(() => modal.remove(), 200);
    };
    closeBtn.addEventListener("click", closeModal);
    cancelBtn.addEventListener("click", closeModal);
    backdrop.addEventListener("click", closeModal);

    const escHandler = (e) => {
      if (e.key === "Escape") {
        closeModal();
        document.removeEventListener("keydown", escHandler);
      }
    };
    document.addEventListener("keydown", escHandler);

    if (selectBtn) {
      selectBtn.addEventListener("click", function () {
        const id = parseInt(this.getAttribute("data-attachment-id"));

        // 🚀 اگر در حالت گالری هستیم، عکس را به Selection گالری اضافه می‌کنیم
        if (currentState.indexOf("gallery") !== -1) {
          const attachment = wp.media.attachment(id);
          if (attachment) {
            const selection = wp.media.frame.state().get("selection");
            if (selection) {
              const exists = selection.findWhere({ id: id });
              if (!exists) {
                selection.add(attachment);
              }
            }
          }
          // علامت زدن عکس به عنوان انتخاب‌شده در گرید مودال
          const $card = $(`.nextsafar-media-card[data-attachment-id="${id}"]`);
          $card.addClass("selected");
          const checkbox = $card.find(".nextsafar-media-checkbox");
          if (checkbox) checkbox.prop("checked", true);
          localSelectedIds.add(id);
        } else {
          // در حالت Featured Image
          applySingleSelection(id);
        }

        closeModal();
      });
    }
    saveBtn.addEventListener("click", async function () {
      await saveDetails(
        parseInt(this.getAttribute("data-attachment-id")),
        modal,
      );
    });
    await loadDetailsContent(attachmentId, modal);
  }

  async function loadDetailsContent(attachmentId, modal) {
    const formData = new FormData();
    formData.append("action", "nextsafar_get_media_details");
    formData.append("media_id", attachmentId);
    try {
      const response = await fetch(nextsafarMediaModal.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();
      if (!result.success) {
        modal.querySelector(".nextsafar-image-details-preview").innerHTML =
          '<div class="nextsafar-image-details-error">Error loading details</div>';
        return;
      }
      const data = result.data;
      const previewContainer = modal.querySelector(
        ".nextsafar-image-details-preview",
      );
      if (data.preview_html) previewContainer.innerHTML = data.preview_html;
      else if (data.url || data.file_url)
        previewContainer.innerHTML = `<img src="${escapeHtml(data.file_url || data.url)}" alt="${escapeHtml(data.title)}">`;
      else
        previewContainer.innerHTML =
          '<div class="nextsafar-image-details-file-icon">▣</div>';

      const infoContainer = modal.querySelector(
        ".nextsafar-image-details-info",
      );
      infoContainer.innerHTML = `
            <div class="nextsafar-form-group"><label>${strings.title || "Title"}</label><input type="text" class="nextsafar-form-input" id="details-title" value="${escapeHtml(data.title || "")}"></div>
            <div class="nextsafar-form-group"><label>${strings.altText || "Alt Text"}</label><input type="text" class="nextsafar-form-input" id="details-alt-text" value="${escapeHtml(data.alt_text || "")}"></div>
            <div class="nextsafar-form-group"><label>${strings.caption || "Caption"}</label><textarea class="nextsafar-form-textarea" id="details-caption" rows="2">${escapeHtml(data.caption || "")}</textarea></div>
            <div class="nextsafar-form-group"><label>${strings.description || "Description"}</label><textarea class="nextsafar-form-textarea" id="details-description" rows="3">${escapeHtml(data.description || "")}</textarea></div>
            <div class="nextsafar-media-details-meta">
                <div class="meta-row"><strong>File:</strong><span>${escapeHtml(data.file_name)}</span></div>
                <div class="meta-row"><strong>Type:</strong><span>${escapeHtml(data.file_type)}</span></div>
                <div class="meta-row"><strong>Size:</strong><span>${escapeHtml(data.file_size)}</span></div>
                <div class="meta-row"><strong>Dimensions:</strong><span>${escapeHtml(data.dimensions)}</span></div>
                <div class="meta-row"><strong>Uploaded:</strong><span>${escapeHtml(data.upload_date)}</span></div>
                <div class="meta-row"><strong>Folder:</strong><span>${escapeHtml(data.folder_name)}</span></div>
                <div class="meta-row"><strong>URL:</strong><input type="text" class="nextsafar-form-input" readonly value="${escapeHtml(data.file_url)}" onclick="this.select();"></div>
            </div>`;
    } catch (error) {
      console.error("Load details error:", error);
    }
  }

  async function saveDetails(attachmentId, modal) {
    const saveBtn = modal.querySelector(".nextsafar-image-details-save-btn");
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = strings.saving;

    const formData = new FormData();
    formData.append("action", "nextsafar_update_media_details");
    formData.append("nonce", nextsafarAdmin.mediaDetailsNonce);
    formData.append("media_id", attachmentId);
    formData.append("title", modal.querySelector("#details-title").value);
    formData.append("alt_text", modal.querySelector("#details-alt-text").value);
    formData.append("caption", modal.querySelector("#details-caption").value);
    formData.append(
      "description",
      modal.querySelector("#details-description").value,
    );

    try {
      const response = await fetch(nextsafarMediaModal.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();
      if (result.success) {
        saveBtn.innerHTML = `✓ ${strings.saved}`;
        const $card = $(
          `.nextsafar-media-card[data-attachment-id="${attachmentId}"]`,
        );
        if ($card.length > 0)
          $card
            .find(".nextsafar-media-info strong")
            .text(modal.querySelector("#details-title").value);
        setTimeout(() => {
          saveBtn.innerHTML = originalText;
          saveBtn.disabled = false;
        }, 1500);
      } else {
        alert(result.data?.message || "Failed to save.");
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
      }
    } catch (error) {
      alert("Failed to save.");
      saveBtn.innerHTML = originalText;
      saveBtn.disabled = false;
    }
  }

  // ============================================
  // 🎯 Selection → wp.media (🚀 Ultra Fast)
  // ============================================
  function applySingleSelection(attachmentId) {
    if (!wp.media.frame) return;
    const state = wp.media.frame.state();
    if (!state) return;
    const selection = state.get("selection");
    if (!selection) return;

    const attachment = wp.media.attachment(attachmentId);
    if (!attachment) return;

    // 🚀 حذف fetch sync - دیتا قبلاً در store ثبت شده است
    selection.reset([attachment]);
  }

  function applyBulkSelection() {
    if (!wp.media.frame) return;
    if (localSelectedIds.size === 0) return;

    const state = wp.media.frame.state();
    if (!state) return;
    const selection = state.get("selection");
    if (!selection) return;

    const attachmentsToSelect = [];
    localSelectedIds.forEach(function (id) {
      const attachment = wp.media.attachment(id);
      if (attachment) attachmentsToSelect.push(attachment);
    });

    if (attachmentsToSelect.length > 0) {
      // 🚀 استفاده از reset برای سرعت بالا و جلوگیری از تریگرهای مکرر
      selection.reset(attachmentsToSelect);
    }
  }

  // ============================================
  // 🖱️ Card Click Handler (🚀 باز کردن جزئیات با کلیک روی عکس)
  // ============================================
  function handleCardClick(cardElement, event, $wrapper) {
    // اگر روی چک‌باکس کلیک شد، کاری نکنیم (چک‌باکس هندلر جداگانه دارد)
    if ($(event.target).is('input[type="checkbox"]')) return;

    event.preventDefault();
    event.stopPropagation();
    const id = parseInt(cardElement.getAttribute("data-attachment-id"));

    // اگر در حالت انتخاب گروهی (Select Mode) هستیم
    if (selectMode) {
      const isShift = event.shiftKey;
      const isCtrl = event.ctrlKey || event.metaKey;
      if (isShift && lastClickedCard) {
        selectRange(lastClickedCard, cardElement, $wrapper);
        return;
      }
      if (isCtrl) {
        toggleCardSelection(cardElement, $wrapper);
        return;
      }
      localSelectedIds.clear();
      $wrapper.find(".nextsafar-media-card").removeClass("selected");
      $wrapper.find(".nextsafar-media-checkbox").prop("checked", false);
      toggleCardSelection(cardElement, $wrapper);
      return;
    }

    // 🚀 تغییر اصلی: در تمام حالت‌ها (گالری، فیچرد ایمیج و ...) با کلیک روی عکس، جزئیات باز می‌شود
    openDetailsModal(id);
  }

  // ============================================
  // 🎨 Event Bindings
  // ============================================
  function bindEvents($content, $modal) {
    const $wrapper = $content.find(".nextsafar-modal-wrapper");
    const $gridWrapper = $wrapper.find(".nextsafar-modal-grid-scroll");

    $gridWrapper.on("click", ".nextsafar-media-card", function (e) {
      handleCardClick(this, e, $wrapper);
    });
    $gridWrapper.on("click", ".nextsafar-media-checkbox", function (e) {
      e.stopPropagation();
      if (selectMode) {
        const card = this.closest(".nextsafar-media-card");
        if (card) toggleCardSelection(card, $wrapper);
      }
    });

    $wrapper.on("click", "#nextsafar-modal-select-toggle", function (e) {
      e.preventDefault();
      enterSelectMode($wrapper);
    });
    $wrapper.on("click", "#nextsafar-modal-cancel-select", function (e) {
      e.preventDefault();
      exitSelectMode($wrapper);
    });
    $wrapper.on("click", "#nextsafar-modal-apply", function (e) {
      e.preventDefault();
      applyBulkSelection();
      exitSelectMode($wrapper);
    });

    $content.on("click", ".nextsafar-folder-item", function (e) {
      e.stopPropagation();
      const $folder = $(this);
      if ($(e.target).hasClass("nextsafar-folder-toggle")) {
        const $wrapperEl = $folder.parent();
        const $children = $wrapperEl
          .find("> .nextsafar-folder-children")
          .first();
        if ($children.length > 0) {
          $children.slideToggle(150);
          $folder.toggleClass("expanded");
        }
        return;
      }
      const folderId = parseInt($folder.data("folder-id"));
      const folderType = $folder.data("folder-type");
      let targetId = folderId;
      if (folderType === "all") targetId = 0;
      else if (folderType === "uncategorized") targetId = -1;
      resetAndLoad($wrapper, targetId, currentSearch, currentMimeFilter);
    });

    let folderSearchTimeout;
    $content.on("input", ".nextsafar-folder-search", function () {
      clearTimeout(folderSearchTimeout);
      const query = $(this).val().toLowerCase();
      folderSearchTimeout = setTimeout(function () {
        $content.find(".nextsafar-folder-wrapper").each(function () {
          const $w = $(this);
          const name = $w
            .find(".nextsafar-folder-name")
            .first()
            .text()
            .toLowerCase();
          if (query === "" || name.indexOf(query) !== -1) {
            $w.show();
            $w.parents(".nextsafar-folder-wrapper").show();
            $w.parents(".nextsafar-folder-children").show();
          } else {
            $w.hide();
          }
        });
      }, 150);
    });

    let mediaSearchTimeout;
    $content.on("input", ".nextsafar-modal-search-input", function () {
      clearTimeout(mediaSearchTimeout);
      const query = $(this).val().trim();
      mediaSearchTimeout = setTimeout(function () {
        resetAndLoad($wrapper, currentFolderId, query, currentMimeFilter);
      }, 300);
    });

    $content.on("click", ".nextsafar-mime-btn", function () {
      const $btn = $(this);
      $content.find(".nextsafar-mime-btn").removeClass("active");
      $btn.addClass("active");
      resetAndLoad($wrapper, currentFolderId, currentSearch, $btn.data("mime"));
    });

    $content.on("click", ".nextsafar-load-more-btn", function () {
      loadMediaPage($wrapper, false);
    });
    initInfiniteScroll($wrapper);
  }

  // ============================================
  // 👀 MutationObserver & Frame Listeners
  // ============================================
  const observer = new MutationObserver(function () {
    const modals = document.querySelectorAll(".media-modal.wp-core-ui");
    modals.forEach(function (modal) {
      scheduleInjection(modal, 80);
    });
  });
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: false,
  });

  function attachFrameListeners(frame) {
    if (!frame || frame._nextsafarBound) return;
    frame._nextsafarBound = true;

    frame.on("open", function () {
      selectMode = false;
      localSelectedIds.clear();
      lastClickedCard = null;
      currentFolderId = 0;
      currentFolderType = "all";
      setTimeout(function () {
        const modal = document.querySelector(".media-modal.wp-core-ui");
        if (modal) scheduleInjection(modal, 50);
      }, 100);
    });

    frame.on("content:render", function () {
      setTimeout(function () {
        const modal = document.querySelector(".media-modal.wp-core-ui");
        if (modal) {
          let stateId = "";
          try {
            stateId = frame.state().id || "";
          } catch (e) {}
          if (
            stateId.indexOf("library") !== -1 ||
            stateId === "insert" ||
            stateId === "featured-image" ||
            stateId.indexOf("gallery") !== -1
          ) {
            scheduleInjection(modal, 100);
          } else {
            const $modal = $(modal);
            $modal.find(".nextsafar-folders-sidebar").remove();
            const $wrapper = $modal.find(".nextsafar-modal-wrapper");
            if ($wrapper.length > 0) {
              const $content = $modal.find(".media-frame-content");
              const $children = $wrapper.children().detach();
              $content.empty().append($children);
              $content.css("display", "");
            }
          }
        }
      }, 150);
    });

    frame.states.on("activate", function () {
      setTimeout(function () {
        const modal = document.querySelector(".media-modal.wp-core-ui");
        if (modal) scheduleInjection(modal, 100);
      }, 150);
    });
  }

  setInterval(function () {
    if (wp.media && wp.media.frame && !wp.media.frame._nextsafarBound)
      attachFrameListeners(wp.media.frame);
  }, 500);
  const checkInterval = setInterval(function () {
    if (wp.media && wp.media.frame) {
      clearInterval(checkInterval);
      attachFrameListeners(wp.media.frame);
    }
  }, 100);

  console.log("✅ NextSafar Media Modal - Ultra Fast Version Loaded");
})(jQuery);
