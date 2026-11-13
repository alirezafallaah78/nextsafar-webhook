/**
 * NextSafar Media Library - Fixed Version
 * ✅ Modal close works
 * ✅ Select mode works
 * ✅ All event handlers attached
 */
window.NextSafarMediaLibrary = {
  // State
  expandedFolders: new Set(),
  currentFolderId: 0,
  currentFolderType: "all",
  currentSearch: "",
  selectionMode: false,
  selectedMediaIds: new Set(),
  lastClickedCard: null,
  draggedMediaId: null,
  currentMediaDetailsId: null,

  // DOM Elements
  mediaPanel: null,
  foldersList: null,
  foldersSidebar: null,
  searchInput: null,
  hiddenFolderInput: null,
  perPageSelect: null,

  // Modal Elements
  mediaDetailsModal: null,
  mediaDetailsClose: null,
  mediaDetailsBackdrop: null,
  mediaDetailsCancel: null,
  mediaDetailsSave: null,

  // Selection Elements
  selectModeButton: null,
  bulkToolbar: null,
  selectedCount: null,
  cancelSelectionButton: null,
  bulkDeleteButton: null,

  // Folder Modal
  folderModal: null,
  createFolderBtn: null,

  init() {
    console.log("🚀 NextSafarMediaLibrary initializing...");

    // Main elements
    this.mediaPanel = document.getElementById("nextsafar-media-panel");
    this.foldersList = document.getElementById("nextsafar-folders-list");
    this.foldersSidebar = document.getElementById("nextsafar-folders-sidebar");
    this.searchInput = document.getElementById("nextsafar-search-input");
    this.hiddenFolderInput = document.getElementById(
      "nextsafar-hidden-folder-input",
    );
    this.perPageSelect = document.getElementById("nextsafar-per-page");

    // ✅ Modal Elements
    this.mediaDetailsModal = document.getElementById(
      "nextsafar-media-details-modal",
    );
    this.mediaDetailsClose = document.getElementById(
      "nextsafar-media-details-close",
    );
    this.mediaDetailsBackdrop = document.getElementById(
      "nextsafar-media-details-backdrop",
    );
    this.mediaDetailsCancel = document.getElementById(
      "nextsafar-media-details-cancel",
    );
    this.mediaDetailsSave = document.getElementById(
      "nextsafar-media-details-save",
    );

    // ✅ Selection Elements
    this.selectModeButton = document.getElementById("nextsafar-select-mode");
    this.bulkToolbar = document.getElementById("nextsafar-bulk-toolbar");
    this.selectedCount = document.getElementById("nextsafar-selected-count");
    this.cancelSelectionButton = document.getElementById(
      "nextsafar-cancel-selection",
    );
    this.bulkDeleteButton = document.getElementById("nextsafar-bulk-delete");

    // ✅ Folder Modal
    this.folderModal = document.getElementById("nextsafar-folder-modal");
    this.createFolderBtn = document.getElementById(
      "nextsafar-create-folder-btn",
    );

    if (!this.mediaPanel) {
      console.warn("Media panel not found, aborting init");
      return;
    }

    this.loadState();
    this.parseUrlParams();
    this.bindEvents();
    this.loadFoldersTree();
    this.initMediaCardHandlers();
    this.initMediaDeleteHandlers();
    this.initPaginationHandlers();
    this.initDragAndDrop();

    console.log("✅ NextSafarMediaLibrary initialized successfully");
  },

  loadState() {
    if (window.NextSafarState) {
      this.expandedFolders =
        window.NextSafarState.get("expandedFolders") || new Set();
      this.currentFolderId = window.NextSafarState.get("currentFolderId") || 0;
      this.currentFolderType =
        window.NextSafarState.get("currentFolderType") || "all";
      this.currentSearch = window.NextSafarState.get("currentSearch") || "";
    }
  },

  parseUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const folderParam = urlParams.get("folder");
    this.currentSearch = urlParams.get("s") || "";

    if (folderParam === "-1") {
      this.currentFolderId = -1;
      this.currentFolderType = "uncategorized";
    } else if (folderParam) {
      this.currentFolderId = parseInt(folderParam) || 0;
      this.currentFolderType = "folder";
    }
  },

  bindEvents() {
    // Search
    if (this.searchInput) {
      let searchTimeout;
      this.searchInput.addEventListener("input", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          this.loadMediaGrid(
            this.currentFolderId,
            this.searchInput.value.trim(),
            1,
            true,
          );
        }, 500);
      });
    }

    // Per page select
    if (this.perPageSelect) {
      this.perPageSelect.addEventListener("change", () => {
        this.loadMediaGrid(this.currentFolderId, this.currentSearch, 1, true);
      });
    }

    // Upload button
    const uploadButton = document.getElementById("nextsafar-upload-media");
    const mediaInput = document.getElementById("nextsafar-media-input");
    if (uploadButton && mediaInput) {
      uploadButton.addEventListener("click", () => mediaInput.click());
      mediaInput.addEventListener("change", async () => {
        const files = Array.from(mediaInput.files);
        if (!files.length) return;
        uploadButton.disabled = true;
        uploadButton.textContent = "Uploading...";
        for (const file of files) {
          await this.uploadMedia(file);
        }
        uploadButton.disabled = false;
        uploadButton.textContent = "+ Upload Media";
        mediaInput.value = "";
      });
    }

    // Browser back/forward
    window.addEventListener("popstate", (e) => {
      if (e.state) {
        const { folderId, search, paged } = e.state;
        this.loadMediaGrid(folderId, search || "", paged || 1, false);
      } else {
        this.parseUrlParams();
        this.loadMediaGrid(this.currentFolderId, this.currentSearch, 1, false);
      }
    });

    // ✅ Modal Close Buttons
    if (this.mediaDetailsClose) {
      this.mediaDetailsClose.addEventListener("click", () =>
        this.closeMediaDetails(),
      );
    }
    if (this.mediaDetailsBackdrop) {
      this.mediaDetailsBackdrop.addEventListener("click", () =>
        this.closeMediaDetails(),
      );
    }
    if (this.mediaDetailsCancel) {
      this.mediaDetailsCancel.addEventListener("click", () =>
        this.closeMediaDetails(),
      );
    }
    if (this.mediaDetailsSave) {
      this.mediaDetailsSave.addEventListener("click", () =>
        this.saveMediaDetails(),
      );
    }

    // ✅ Escape key to close modal
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.mediaDetailsModal) {
        if (this.mediaDetailsModal.style.display === "flex") {
          this.closeMediaDetails();
        }
      }
    });

    // ✅ Select Mode Button
    if (this.selectModeButton) {
      this.selectModeButton.addEventListener("click", () => {
        this.toggleSelectionMode(true);
      });
    }

    // ✅ Cancel Selection Button
    if (this.cancelSelectionButton) {
      this.cancelSelectionButton.addEventListener("click", () => {
        this.toggleSelectionMode(false);
      });
    }

    // ✅ Bulk Delete Button
    if (this.bulkDeleteButton) {
      this.bulkDeleteButton.addEventListener("click", () => {
        this.bulkDeleteSelected();
      });
    }

    // ✅ Create Folder Button
    if (this.createFolderBtn) {
      this.createFolderBtn.addEventListener("click", () => {
        this.openFolderModal();
      });
    }

    // ✅ Folder Modal Close
    const folderModalClose = document.getElementById(
      "nextsafar-folder-modal-close",
    );
    const folderCancelBtn = document.getElementById("nextsafar-folder-cancel");
    const folderSaveBtn = document.getElementById("nextsafar-folder-save");

    if (folderModalClose) {
      folderModalClose.addEventListener("click", () => this.closeFolderModal());
    }
    if (folderCancelBtn) {
      folderCancelBtn.addEventListener("click", () => this.closeFolderModal());
    }
    if (folderSaveBtn) {
      folderSaveBtn.addEventListener("click", () => this.saveFolder());
    }

    console.log("📌 Event listeners bound successfully");
  },

  async loadMediaGrid(folderId, search = "", paged = 1, pushHistory = true) {
    if (!this.mediaPanel) return;

    this.mediaPanel.classList.add("loading");
    this.mediaPanel.style.opacity = "0.5";
    this.mediaPanel.style.pointerEvents = "none";

    const formData = new FormData();
    formData.append("action", "nextsafar_load_media_grid");
    formData.append("folder_id", folderId);
    formData.append("search", search);
    formData.append("paged", paged);
    formData.append("per_page", this.getPerPageValue());

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (result.success) {
        this.mediaPanel.innerHTML = result.data.html;

        if (pushHistory) {
          const newUrl = this.buildMediaUrl(folderId, search, paged);
          window.history.pushState({ folderId, search, paged }, "", newUrl);
        }

        this.currentFolderId = folderId;
        this.currentSearch = search;
        if (folderId === -1) this.currentFolderType = "uncategorized";
        else if (folderId > 0) this.currentFolderType = "folder";
        else this.currentFolderType = "all";

        if (this.hiddenFolderInput) {
          this.hiddenFolderInput.value = folderId;
        }

        if (window.NextSafarState) {
          window.NextSafarState.set("currentFolderId", folderId);
          window.NextSafarState.set(
            "currentFolderType",
            this.currentFolderType,
          );
          window.NextSafarState.set("currentSearch", search);
        }

        this.updateFolderActiveState();
        this.initMediaCardHandlers();
        this.initMediaDeleteHandlers();
        this.initPaginationHandlers();
        this.loadFoldersTree();
      } else {
        alert(result.data?.message || "Failed to load media.");
      }
    } catch (error) {
      console.error(error);
      alert("Failed to load media.");
    } finally {
      this.mediaPanel.classList.remove("loading");
      this.mediaPanel.style.opacity = "1";
      this.mediaPanel.style.pointerEvents = "auto";
    }
  },

  buildMediaUrl(folderId, search, paged) {
    const params = new URLSearchParams();
    params.set("page", "nextsafar-media");
    if (folderId !== 0) params.set("folder", folderId);
    if (search) params.set("s", search);
    if (paged > 1) params.set("paged", paged);
    if (this.perPageSelect && parseInt(this.perPageSelect.value) !== 50) {
      params.set("per_page", this.perPageSelect.value);
    }
    return window.location.pathname + "?" + params.toString();
  },

  getPerPageValue() {
    return this.perPageSelect ? parseInt(this.perPageSelect.value) : 50;
  },

  async loadFoldersTree() {
    if (!this.foldersList) return;

    try {
      const formData = new FormData();
      formData.append("action", "nextsafar_get_folders");

      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (result.success) {
        if (this.currentFolderId > 0) {
          this.expandParentsOf(this.currentFolderId, result.data.folders);
        }

        this.renderFoldersTree(result.data.folders, this.foldersList, 0);
        this.updateParentSelect(result.data.folders);
        this.updateSpecialCounts(
          result.data.all_media_count,
          result.data.uncategorized_count,
        );
        this.updateFolderActiveState();
      }
    } catch (error) {
      console.error("Failed to load folders:", error);
    }
  },

  renderFoldersTree(folders, container, depth) {
    container.innerHTML = "";

    folders.forEach((folder) => {
      const folderWrapper = document.createElement("div");
      folderWrapper.className = "nextsafar-folder-wrapper";
      folderWrapper.dataset.folderId = folder.id;

      const folderItem = document.createElement("div");
      folderItem.className = "nextsafar-folder-item";
      folderItem.dataset.folderId = folder.id;

      const isActive =
        this.currentFolderType === "folder" &&
        this.currentFolderId === folder.id;
      const isExpanded = this.expandedFolders.has(folder.id);

      if (isActive) folderItem.classList.add("active");
      if (isExpanded) folderItem.classList.add("expanded");

      const hasChildren = folder.children && folder.children.length > 0;
      const toggleIcon = hasChildren ? (isExpanded ? "▾" : "▸") : "";
      const folderIcon = isExpanded ? "📂" : "📁";

      folderItem.innerHTML = `
                ${hasChildren ? `<span class="nextsafar-folder-toggle">${toggleIcon}</span>` : '<span class="nextsafar-folder-toggle"></span>'}
                <span class="nextsafar-folder-icon">${folderIcon}</span>
                <span class="nextsafar-folder-name">${folder.name}</span>
                ${folder.media_count > 0 ? `<span class="nextsafar-folder-count">${folder.media_count}</span>` : ""}
                <div class="nextsafar-folder-actions">
                    <button type="button" class="nextsafar-folder-edit" title="Edit"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen-icon lucide-square-pen"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg></button>
                    <button type="button" class="nextsafar-folder-delete" title="Delete"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-icon lucide-trash"><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                </div>
            `;

      folderWrapper.appendChild(folderItem);

      // Toggle expand/collapse
      const toggleBtn = folderItem.querySelector(".nextsafar-folder-toggle");
      if (hasChildren && toggleBtn) {
        toggleBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          e.preventDefault();
          if (this.expandedFolders.has(folder.id)) {
            this.expandedFolders.delete(folder.id);
          } else {
            this.expandedFolders.add(folder.id);
          }
          if (window.NextSafarState) {
            window.NextSafarState.set("expandedFolders", this.expandedFolders);
          }
          this.loadFoldersTree();
        });
      }

      // Click to filter media
      folderItem.addEventListener("click", (e) => {
        if (
          e.target.closest(".nextsafar-folder-toggle") ||
          e.target.closest(".nextsafar-folder-actions")
        ) {
          return;
        }
        e.stopPropagation();
        e.preventDefault();
        this.loadMediaGrid(folder.id, this.currentSearch, 1, true);
      });

      // Edit folder
      const editBtn = folderItem.querySelector(".nextsafar-folder-edit");
      if (editBtn) {
        editBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          e.preventDefault();
          this.openFolderModal(folder.id, folder.name, folder.parent_id);
        });
      }

      // Delete folder
      const deleteBtn = folderItem.querySelector(".nextsafar-folder-delete");
      if (deleteBtn) {
        deleteBtn.addEventListener("click", async (e) => {
          e.stopPropagation();
          e.preventDefault();
          if (!confirm("Are you sure? All media will become uncategorized."))
            return;

          const formData = new FormData();
          formData.append("action", "nextsafar_delete_folder");
          formData.append("nonce", window.nextsafarAdmin.folderNonce);
          formData.append("folder_id", folder.id);

          try {
            const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
              method: "POST",
              body: formData,
            });
            const result = await response.json();

            if (result.success) {
              if (this.currentFolderId === folder.id) {
                this.loadMediaGrid(0, this.currentSearch, 1, true);
              } else {
                this.loadFoldersTree();
                this.loadMediaGrid(
                  this.currentFolderId,
                  this.currentSearch,
                  1,
                  false,
                );
              }
            } else {
              alert(result.data?.message || "Failed to delete.");
            }
          } catch (error) {
            alert("Failed to delete folder.");
          }
        });
      }

      container.appendChild(folderWrapper);

      if (hasChildren && isExpanded) {
        const childrenContainer = document.createElement("div");
        childrenContainer.className = "nextsafar-folder-children";
        folderWrapper.appendChild(childrenContainer);
        this.renderFoldersTree(folder.children, childrenContainer, depth + 1);
      }
    });
  },

  expandParentsOf(folderId, folders) {
    if (!folderId || folderId <= 0) return false;

    for (let folder of folders) {
      if (folder.id === folderId) {
        let current = folder;
        while (current && current.parent_id > 0) {
          this.expandedFolders.add(current.parent_id);
          current = this.findFolderById(current.parent_id, folders);
        }
        if (window.NextSafarState) {
          window.NextSafarState.set("expandedFolders", this.expandedFolders);
        }
        return true;
      }

      if (folder.children && folder.children.length > 0) {
        if (this.expandParentsOf(folderId, folder.children)) {
          this.expandedFolders.add(folder.id);
          if (window.NextSafarState) {
            window.NextSafarState.set("expandedFolders", this.expandedFolders);
          }
          return true;
        }
      }
    }
    return false;
  },

  findFolderById(id, folders) {
    for (let folder of folders) {
      if (folder.id === id) return folder;
      if (folder.children && folder.children.length > 0) {
        const found = this.findFolderById(id, folder.children);
        if (found) return found;
      }
    }
    return null;
  },

  updateSpecialCounts(allCount, uncategorizedCount) {
    const allCountEl = document.getElementById("nextsafar-all-media-count");
    const uncategorizedCountEl = document.getElementById(
      "nextsafar-uncategorized-count",
    );

    if (allCountEl) allCountEl.textContent = allCount;
    if (uncategorizedCountEl)
      uncategorizedCountEl.textContent = uncategorizedCount;
  },

  updateFolderActiveState() {
    document.querySelectorAll(".nextsafar-folder-item.active").forEach((el) => {
      el.classList.remove("active");
    });

    let selector = "";
    if (this.currentFolderType === "all") {
      selector = '[data-folder-type="all"]';
    } else if (this.currentFolderType === "uncategorized") {
      selector = '[data-folder-type="uncategorized"]';
    } else {
      selector = `.nextsafar-folder-item[data-folder-id="${this.currentFolderId}"]`;
    }

    const activeItem = document.querySelector(selector);
    if (activeItem) activeItem.classList.add("active");
  },

  updateParentSelect(folders, depth = 0) {
    const folderParentSelect = document.getElementById(
      "nextsafar-folder-parent",
    );
    if (!folderParentSelect) return;

    if (depth === 0) {
      folderParentSelect.innerHTML =
        '<option value="0">Root (No Parent)</option>';
    }

    folders.forEach((folder) => {
      const option = document.createElement("option");
      option.value = folder.id;
      option.textContent = "—".repeat(depth) + " " + folder.name;
      folderParentSelect.appendChild(option);

      if (folder.children && folder.children.length > 0) {
        this.updateParentSelect(folder.children, depth + 1);
      }
    });
  },

  initMediaCardHandlers() {
    const cards = document.querySelectorAll(".nextsafar-media-card");
    cards.forEach((card) => {
      if (card.dataset.cardHandlerAttached) return;
      card.dataset.cardHandlerAttached = "true";

      card.addEventListener("click", (e) => {
        if (e.target.closest(".nextsafar-media-delete")) return;

        const checkbox = card.querySelector(".nextsafar-media-checkbox");
        const mediaId = card.dataset.mediaId;

        if (this.selectionMode) {
          e.preventDefault();
          if (!checkbox) return;

          if (e.shiftKey && this.lastClickedCard) {
            const allCards = Array.from(
              document.querySelectorAll(".nextsafar-media-card"),
            );
            const startIndex = allCards.indexOf(this.lastClickedCard);
            const endIndex = allCards.indexOf(card);
            const [start, end] = [
              Math.min(startIndex, endIndex),
              Math.max(startIndex, endIndex),
            ];

            for (let i = start; i <= end; i++) {
              const currentCard = allCards[i];
              const currentCheckbox = currentCard.querySelector(
                ".nextsafar-media-checkbox",
              );
              if (currentCheckbox && !currentCheckbox.checked) {
                currentCheckbox.checked = true;
                currentCard.classList.add("selected");
                this.selectedMediaIds.add(currentCard.dataset.mediaId);
              }
            }
            this.updateSelectedCount();
          } else {
            checkbox.checked = !checkbox.checked;
            if (checkbox.checked) {
              card.classList.add("selected");
              this.selectedMediaIds.add(mediaId);
            } else {
              card.classList.remove("selected");
              this.selectedMediaIds.delete(mediaId);
            }
            this.updateSelectedCount();
          }
          this.lastClickedCard = card;
        } else {
          e.preventDefault();
          this.openMediaDetails(mediaId);
        }
      });
    });
  },

  initMediaDeleteHandlers() {
    document.querySelectorAll(".nextsafar-media-delete").forEach((button) => {
      if (button.dataset.deleteHandlerAttached) return;
      button.dataset.deleteHandlerAttached = "true";

      button.addEventListener("click", async (e) => {
        e.stopPropagation();
        e.preventDefault();
        const mediaId = button.getAttribute("data-media-id");
        const mediaCard = button.closest(".nextsafar-media-card");

        if (!confirm("Are you sure you want to delete this media?")) return;

        const formData = new FormData();
        formData.append("action", "nextsafar_delete_media");
        formData.append("nonce", window.nextsafarAdmin.deleteNonce);
        formData.append("media_id", mediaId);

        try {
          button.disabled = true;
          button.textContent = "⏳";

          const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
            method: "POST",
            body: formData,
          });
          const result = await response.json();

          if (!result.success) {
            alert(result.data?.message || "Delete failed.");
            button.disabled = false;
            button.textContent = "🗑";
            return;
          }

          mediaCard.style.opacity = "0";
          mediaCard.style.transform = "scale(0.9)";
          setTimeout(() => {
            mediaCard.remove();
            this.loadFoldersTree();
          }, 200);
        } catch (error) {
          console.error(error);
          alert("Delete failed.");
          button.disabled = false;
          button.textContent = "🗑";
        }
      });
    });
  },

  initPaginationHandlers() {
    document
      .querySelectorAll(".nextsafar-pagination .page-numbers")
      .forEach((link) => {
        if (link.dataset.paginationHandlerAttached) return;
        link.dataset.paginationHandlerAttached = "true";

        link.addEventListener("click", (e) => {
          e.preventDefault();
          const href = link.getAttribute("href");
          if (!href) return;
          const url = new URL(href, window.location.origin);
          const paged = parseInt(url.searchParams.get("paged")) || 1;
          this.loadMediaGrid(
            this.currentFolderId,
            this.currentSearch,
            paged,
            true,
          );
        });
      });

    const clearSearch = document.getElementById("nextsafar-clear-search");
    if (clearSearch && !clearSearch.dataset.clearHandlerAttached) {
      clearSearch.dataset.clearHandlerAttached = "true";
      clearSearch.addEventListener("click", (e) => {
        e.preventDefault();
        if (this.searchInput) this.searchInput.value = "";
        this.loadMediaGrid(this.currentFolderId, "", 1, true);
      });
    }
  },

  // ✅ Media Details Modal
  async openMediaDetails(mediaId) {
    if (!this.mediaDetailsModal) {
      console.error("Media details modal not found!");
      return;
    }

    this.currentMediaDetailsId = mediaId;
    const imageContainer = document.getElementById(
      "nextsafar-media-details-image",
    );
    if (imageContainer)
      imageContainer.innerHTML = '<div class="loading">Loading...</div>';

    const formData = new FormData();
    formData.append("action", "nextsafar_get_media_details");
    formData.append("media_id", mediaId);

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (result.success) {
        const data = result.data;

        const titleEl = document.getElementById("media-title");
        const altEl = document.getElementById("media-alt-text");
        const captionEl = document.getElementById("media-caption");
        const descEl = document.getElementById("media-description");
        const urlEl = document.getElementById("media-file-url");

        if (titleEl) titleEl.value = data.title || "";
        if (altEl) altEl.value = data.alt_text || "";
        if (captionEl) captionEl.value = data.caption || "";
        if (descEl) descEl.value = data.description || "";
        if (urlEl) urlEl.value = data.file_url || "";

        const fileNameEl = document.getElementById("media-file-name");
        const fileTypeEl = document.getElementById("media-file-type");
        const fileSizeEl = document.getElementById("media-file-size");
        const dimensionsEl = document.getElementById("media-dimensions");
        const uploadDateEl = document.getElementById("media-upload-date");
        const folderEl = document.getElementById("media-folder");

        if (fileNameEl) fileNameEl.textContent = data.file_name || "";
        if (fileTypeEl) fileTypeEl.textContent = data.file_type || "";
        if (fileSizeEl) fileSizeEl.textContent = data.file_size || "";
        if (dimensionsEl) dimensionsEl.textContent = data.dimensions || "";
        if (uploadDateEl) uploadDateEl.textContent = data.upload_date || "";
        if (folderEl) folderEl.textContent = data.folder_name || "";

        if (imageContainer) imageContainer.innerHTML = data.preview_html || "";

        this.mediaDetailsModal.style.display = "flex";
      } else {
        alert(result.data?.message || "Failed to load media details.");
      }
    } catch (error) {
      console.error(error);
      alert("Failed to load media details.");
    }
  },

  closeMediaDetails() {
    if (this.mediaDetailsModal) {
      this.mediaDetailsModal.style.display = "none";
    }
    this.currentMediaDetailsId = null;
  },

  async saveMediaDetails() {
    if (!this.currentMediaDetailsId) return;

    const formData = new FormData();
    formData.append("action", "nextsafar_update_media_details");
    formData.append("nonce", window.nextsafarAdmin.mediaDetailsNonce);
    formData.append("media_id", this.currentMediaDetailsId);
    formData.append(
      "title",
      document.getElementById("media-title")?.value || "",
    );
    formData.append(
      "alt_text",
      document.getElementById("media-alt-text")?.value || "",
    );
    formData.append(
      "caption",
      document.getElementById("media-caption")?.value || "",
    );
    formData.append(
      "description",
      document.getElementById("media-description")?.value || "",
    );

    if (this.mediaDetailsSave) {
      this.mediaDetailsSave.disabled = true;
      this.mediaDetailsSave.textContent = "Saving...";
    }

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (result.success) {
        this.closeMediaDetails();
        this.loadMediaGrid(this.currentFolderId, this.currentSearch, 1, false);
      } else {
        alert(result.data?.message || "Failed to save.");
      }
    } catch (error) {
      console.error(error);
      alert("Failed to save.");
    } finally {
      if (this.mediaDetailsSave) {
        this.mediaDetailsSave.disabled = false;
        this.mediaDetailsSave.textContent = "Save Changes";
      }
    }
  },

  // ✅ Selection Mode
  toggleSelectionMode(enable) {
    this.selectionMode = enable;

    if (enable) {
      if (this.mediaPanel)
        this.mediaPanel.classList.add("nextsafar-selection-mode");
      if (this.bulkToolbar) this.bulkToolbar.style.display = "flex";
      if (this.selectModeButton) this.selectModeButton.style.display = "none";
    } else {
      if (this.mediaPanel)
        this.mediaPanel.classList.remove("nextsafar-selection-mode");
      if (this.bulkToolbar) this.bulkToolbar.style.display = "none";
      if (this.selectModeButton)
        this.selectModeButton.style.display = "inline-flex";

      this.selectedMediaIds.clear();
      document
        .querySelectorAll(".nextsafar-media-checkbox")
        .forEach((checkbox) => {
          checkbox.checked = false;
          const card = checkbox.closest(".nextsafar-media-card");
          if (card) card.classList.remove("selected");
        });
      this.lastClickedCard = null;
      this.updateSelectedCount();
    }
  },

  updateSelectedCount() {
    if (this.selectedCount) {
      this.selectedCount.textContent = this.selectedMediaIds.size;
    }
    if (this.bulkDeleteButton) {
      this.bulkDeleteButton.disabled = this.selectedMediaIds.size === 0;
    }
  },

  async bulkDeleteSelected() {
    if (this.selectedMediaIds.size === 0) return;

    const count = this.selectedMediaIds.size;
    if (
      !confirm(
        `Are you sure you want to delete ${count} selected media file(s)?`,
      )
    )
      return;

    if (this.bulkDeleteButton) {
      this.bulkDeleteButton.disabled = true;
      this.bulkDeleteButton.textContent = "Deleting...";
    }

    const formData = new FormData();
    formData.append("action", "nextsafar_bulk_delete_media");
    formData.append("nonce", window.nextsafarAdmin.bulkDeleteNonce);
    this.selectedMediaIds.forEach((id) => {
      formData.append("media_ids[]", id);
    });

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (!result.success) {
        alert(result.data?.message || "Bulk delete failed.");
        if (this.bulkDeleteButton) {
          this.bulkDeleteButton.disabled = false;
          this.bulkDeleteButton.textContent = "🗑 Delete Selected";
        }
        return;
      }

      result.data.deleted.forEach((id) => {
        const card = document.querySelector(
          `.nextsafar-media-card[data-media-id="${id}"]`,
        );
        if (card) {
          card.style.opacity = "0";
          card.style.transform = "scale(0.9)";
          setTimeout(() => card.remove(), 200);
        }
      });

      setTimeout(() => {
        this.toggleSelectionMode(false);
        this.loadFoldersTree();
      }, 300);
    } catch (error) {
      console.error(error);
      alert("Bulk delete failed.");
      if (this.bulkDeleteButton) {
        this.bulkDeleteButton.disabled = false;
        this.bulkDeleteButton.textContent = "🗑 Delete Selected";
      }
    }
  },

  // ✅ Folder Modal
  openFolderModal(folderId = 0, name = "", parentId = 0) {
    if (!this.folderModal) return;

    const editIdInput = document.getElementById("nextsafar-folder-edit-id");
    const parentIdInput = document.getElementById("nextsafar-folder-parent-id");
    const nameInput = document.getElementById("nextsafar-folder-name");
    const titleEl = document.getElementById("nextsafar-folder-modal-title");
    const parentSelect = document.getElementById("nextsafar-folder-parent");

    if (editIdInput) editIdInput.value = folderId;
    if (parentIdInput) parentIdInput.value = parentId;
    if (nameInput) nameInput.value = name;
    if (titleEl)
      titleEl.textContent = folderId ? "Edit Folder" : "Create Folder";

    this.folderModal.style.display = "flex";
    if (nameInput) nameInput.focus();
  },

  closeFolderModal() {
    if (this.folderModal) {
      this.folderModal.style.display = "none";
    }
    const nameInput = document.getElementById("nextsafar-folder-name");
    const editIdInput = document.getElementById("nextsafar-folder-edit-id");
    if (nameInput) nameInput.value = "";
    if (editIdInput) editIdInput.value = 0;
  },

  async saveFolder() {
    const nameInput = document.getElementById("nextsafar-folder-name");
    const editIdInput = document.getElementById("nextsafar-folder-edit-id");
    const parentSelect = document.getElementById("nextsafar-folder-parent");

    const name = nameInput?.value.trim() || "";
    const folderId = parseInt(editIdInput?.value || 0);
    const parentId = parseInt(parentSelect?.value || 0);

    if (!name) {
      alert("Folder name is required.");
      return;
    }

    const formData = new FormData();
    formData.append("nonce", window.nextsafarAdmin.folderNonce);

    if (folderId) {
      formData.append("action", "nextsafar_update_folder");
      formData.append("folder_id", folderId);
      formData.append("name", name);
    } else {
      formData.append("action", "nextsafar_create_folder");
      formData.append("name", name);
      formData.append("parent_id", parentId);
    }

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (result.success) {
        this.closeFolderModal();
        this.loadFoldersTree();
      } else {
        alert(result.data?.message || "Failed to save.");
      }
    } catch (error) {
      alert("Failed to save folder.");
    }
  },

  async uploadMedia(file) {
    const formData = new FormData();
    formData.append("action", "nextsafar_upload_media");
    formData.append("nonce", window.nextsafarAdmin.mediaNonce);
    formData.append("file", file);

    if (this.currentFolderId > 0) {
      formData.append("folder_id", this.currentFolderId);
    }

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();

      if (!result.success) {
        alert(result.data?.message || "Upload failed.");
        return;
      }

      this.loadMediaGrid(this.currentFolderId, this.currentSearch, 1, false);
    } catch (error) {
      console.error(error);
      alert("Upload failed.");
    }
  },

  initDragAndDrop() {
    if (!this.mediaPanel || !this.foldersSidebar) return;

    this.mediaPanel.addEventListener("dragstart", (e) => {
      const card = e.target.closest(".nextsafar-media-card");
      if (!card) return;

      const cardId = card.dataset.mediaId;
      const isCardSelected = this.selectedMediaIds.has(cardId);

      let idsToDrag = [];
      if (
        this.selectionMode &&
        isCardSelected &&
        this.selectedMediaIds.size > 0
      ) {
        idsToDrag = Array.from(this.selectedMediaIds);
      } else {
        idsToDrag = [cardId];
      }

      this.draggedMediaId = idsToDrag;
      e.dataTransfer.setData("text/plain", JSON.stringify(idsToDrag));
      e.dataTransfer.effectAllowed = "move";

      card.classList.add("dragging");
      card.style.opacity = "0.5";

      if (idsToDrag.length > 1) {
        idsToDrag.forEach((id) => {
          const selectedCard = document.querySelector(
            `.nextsafar-media-card[data-media-id="${id}"]`,
          );
          if (selectedCard && selectedCard !== card) {
            selectedCard.classList.add("dragging");
            selectedCard.style.opacity = "0.5";
          }
        });
      }
    });

    this.mediaPanel.addEventListener("dragend", () => {
      document
        .querySelectorAll(".nextsafar-media-card.dragging")
        .forEach((c) => {
          c.classList.remove("dragging");
          c.style.opacity = "1";
        });
      this.draggedMediaId = null;
      document
        .querySelectorAll(".nextsafar-folder-item.drag-over")
        .forEach((item) => {
          item.classList.remove("drag-over");
        });
    });

    this.foldersSidebar.addEventListener("dragover", (e) => {
      const folderItem = e.target.closest(".nextsafar-folder-item");
      if (!folderItem) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";

      document
        .querySelectorAll(".nextsafar-folder-item.drag-over")
        .forEach((item) => {
          if (item !== folderItem) item.classList.remove("drag-over");
        });
      folderItem.classList.add("drag-over");
    });

    this.foldersSidebar.addEventListener("dragleave", (e) => {
      const folderItem = e.target.closest(".nextsafar-folder-item");
      if (!folderItem) return;
      if (!folderItem.contains(e.relatedTarget)) {
        folderItem.classList.remove("drag-over");
      }
    });

    this.foldersSidebar.addEventListener("drop", async (e) => {
      const folderItem = e.target.closest(".nextsafar-folder-item");
      if (!folderItem) return;

      e.preventDefault();
      e.stopPropagation();
      folderItem.classList.remove("drag-over");

      const rawData = e.dataTransfer.getData("text/plain");
      let mediaIds = [];

      if (rawData) {
        try {
          const parsed = JSON.parse(rawData);
          mediaIds = Array.isArray(parsed) ? parsed : [parsed];
        } catch (err) {
          mediaIds = [rawData];
        }
      }

      if (!mediaIds.length && this.draggedMediaId) {
        mediaIds = Array.isArray(this.draggedMediaId)
          ? this.draggedMediaId
          : [this.draggedMediaId];
      }

      if (!mediaIds.length) return;

      let folderId = parseInt(folderItem.dataset.folderId);
      const folderType = folderItem.dataset.folderType;

      if (folderType === "all") return;
      if (folderType === "uncategorized") folderId = 0;

      const isBulk = mediaIds.length > 1;
      const folderName =
        folderItem.querySelector(".nextsafar-folder-name")?.textContent || "";

      if (isBulk) {
        if (
          !confirm(
            `Are you sure you want to move ${mediaIds.length} photos to "${folderName}"?`,
          )
        ) {
          document
            .querySelectorAll(".nextsafar-media-card.dragging")
            .forEach((c) => {
              c.classList.remove("dragging");
              c.style.opacity = "1";
            });
          this.draggedMediaId = null;
          return;
        }
      }

      folderItem.classList.add("dropping");

      const formData = new FormData();
      formData.append("action", "nextsafar_move_media");
      formData.append("nonce", window.nextsafarAdmin.folderNonce);
      mediaIds.forEach((id) => formData.append("media_ids[]", id));
      formData.append("folder_id", folderId);

      try {
        const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
          method: "POST",
          body: formData,
        });
        const result = await response.json();

        if (result.success) {
          const cardsToAnimate = [];
          mediaIds.forEach((id) => {
            const card = document.querySelector(
              `.nextsafar-media-card[data-media-id="${id}"]`,
            );
            if (card) {
              card.style.transition = "opacity 0.3s ease, transform 0.3s ease";
              card.style.opacity = "0";
              card.style.transform = "scale(0.8)";
              cardsToAnimate.push(card);
            }
          });

          setTimeout(() => {
            cardsToAnimate.forEach((card) => card.remove());
            if (isBulk && this.selectionMode) {
              this.toggleSelectionMode(false);
            }
            this.loadMediaGrid(
              this.currentFolderId,
              this.currentSearch,
              1,
              false,
            );
          }, 300);
        } else {
          alert(result.data?.message || "Failed to move.");
        }
      } catch (error) {
        console.error("Move error:", error);
        alert("Failed to move media.");
      } finally {
        folderItem.classList.remove("dropping");
        this.draggedMediaId = null;
      }
    });
  },
};

// Auto-initialize
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    window.NextSafarMediaLibrary.init();
  });
} else {
  window.NextSafarMediaLibrary.init();
}
