/**
 * NextSafar State Manager - مدیریت State مشترک
 */
const NextSafarState = {
  // Storage key
  STORAGE_KEY: "nextsafar_state",

  // State داخلی
  state: {
    expandedFolders: new Set(),
    currentFolderId: 0,
    currentFolderType: "all",
    currentSearch: "",
    selectionMode: false,
    selectedMediaIds: new Set(),
    lastClickedCard: null,
    draggedMediaId: null,
  },

  /**
   * Load state از localStorage
   */
  load() {
    try {
      const saved = localStorage.getItem(this.STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);

        // تبدیل آرایه‌ها به Set
        if (parsed.expandedFolders) {
          this.state.expandedFolders = new Set(
            parsed.expandedFolders.map((id) => parseInt(id)),
          );
        }
        if (parsed.selectedMediaIds) {
          this.state.selectedMediaIds = new Set(
            parsed.selectedMediaIds.map((id) => parseInt(id)),
          );
        }

        // سایر مقادیر
        this.state.currentFolderId = parsed.currentFolderId || 0;
        this.state.currentFolderType = parsed.currentFolderType || "all";
        this.state.currentSearch = parsed.currentSearch || "";
      }
    } catch (e) {
      console.error("Failed to load state:", e);
    }
  },

  /**
   * Save state به localStorage
   */
  save() {
    try {
      const toSave = {
        expandedFolders: Array.from(this.state.expandedFolders),
        currentFolderId: this.state.currentFolderId,
        currentFolderType: this.state.currentFolderType,
        currentSearch: this.state.currentSearch,
        selectedMediaIds: Array.from(this.state.selectedMediaIds),
      };
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(toSave));
    } catch (e) {
      console.error("Failed to save state:", e);
    }
  },

  /**
   * Get state value
   */
  get(key) {
    return this.state[key];
  },

  /**
   * Set state value
   */
  set(key, value) {
    this.state[key] = value;
    this.save();
  },

  /**
   * Reset state
   */
  reset() {
    this.state = {
      expandedFolders: new Set(),
      currentFolderId: 0,
      currentFolderType: "all",
      currentSearch: "",
      selectionMode: false,
      selectedMediaIds: new Set(),
      lastClickedCard: null,
      draggedMediaId: null,
    };
    this.save();
  },
};

// Initialize state از URL
(function () {
  NextSafarState.load();

  const urlParams = new URLSearchParams(window.location.search);
  const folderParam = urlParams.get("folder");

  NextSafarState.state.currentSearch = urlParams.get("s") || "";

  if (folderParam === "-1") {
    NextSafarState.state.currentFolderId = -1;
    NextSafarState.state.currentFolderType = "uncategorized";
  } else if (folderParam) {
    NextSafarState.state.currentFolderId = parseInt(folderParam) || 0;
    NextSafarState.state.currentFolderType = "folder";
  }
})();

// Export
if (typeof window !== "undefined") {
  window.NextSafarState = NextSafarState;
}
