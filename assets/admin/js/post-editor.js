/**
 * NextSafar Post Editor - Main JavaScript
 * Yoast SEO به صورت خودکار mount می‌شود - نیازی به initialize دستی نیست
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ============================================
    // 🎯 Slug Editor
    // ============================================
    const slugEditBtn = document.getElementById("editor-slug-edit-btn");
    const slugSaveBtn = document.getElementById("editor-slug-save-btn");
    const slugCancelBtn = document.getElementById("editor-slug-cancel-btn");
    const slugDisplay = document.getElementById("editor-slug-display");
    const slugInput = document.getElementById("editor-slug-input");
    const titleInput = document.getElementById("post_title");

    // تولید slug خودکار از عنوان
    function generateSlug(title) {
      return title
        .toLowerCase()
        .replace(/[^\w\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .trim();
    }

    if (titleInput && !slugInput.value) {
      titleInput.addEventListener("input", function () {
        if (
          slugDisplay.textContent === "..." ||
          slugDisplay.textContent === ""
        ) {
          const newSlug = generateSlug(this.value);
          slugDisplay.textContent = newSlug;
          slugInput.value = newSlug;
        }
      });
    }

    if (slugEditBtn) {
      slugEditBtn.addEventListener("click", function () {
        slugDisplay.style.display = "none";
        slugEditBtn.style.display = "none";
        slugInput.style.display = "inline-block";
        slugSaveBtn.style.display = "inline-block";
        slugCancelBtn.style.display = "inline-block";
        slugInput.focus();
      });
    }

    if (slugCancelBtn) {
      slugCancelBtn.addEventListener("click", function () {
        slugDisplay.style.display = "inline-block";
        slugEditBtn.style.display = "inline-block";
        slugInput.style.display = "none";
        slugSaveBtn.style.display = "none";
        slugCancelBtn.style.display = "none";
      });
    }

    if (slugSaveBtn) {
      slugSaveBtn.addEventListener("click", function () {
        const newSlug = slugInput.value.trim();
        if (newSlug) {
          slugDisplay.textContent = newSlug;
        }
        slugDisplay.style.display = "inline-block";
        slugEditBtn.style.display = "inline-block";
        slugInput.style.display = "none";
        slugSaveBtn.style.display = "none";
        slugCancelBtn.style.display = "none";
      });
    }

    // ============================================
    // 🎯 Status Toggle
    // ============================================
    const statusToggle = document.getElementById("editor-status-toggle");
    const statusOptions = document.getElementById("editor-status-options");
    const statusInput = document.getElementById("post_status_input");
    const statusText = document.getElementById("editor-status-text");

    if (statusToggle && statusOptions) {
      statusToggle.addEventListener("click", function () {
        statusOptions.classList.toggle("is-open");
      });

      statusOptions
        .querySelectorAll('input[type="radio"]')
        .forEach(function (radio) {
          radio.addEventListener("change", function () {
            statusInput.value = this.value;
            statusText.textContent =
              window.NextSafarEditor.statusLabels[this.value] || this.value;
            statusOptions.classList.remove("is-open");
          });
        });
    }

    // ============================================
    // 🎯 Visibility Toggle
    // ============================================
    const visibilityToggle = document.getElementById(
      "editor-visibility-toggle",
    );
    const visibilityOptions = document.getElementById(
      "editor-visibility-options",
    );
    const visibilityInput = document.getElementById("post_visibility_input");
    const visibilityText = document.getElementById("visibility-text");

    if (visibilityToggle && visibilityOptions) {
      visibilityToggle.addEventListener("click", function () {
        visibilityOptions.classList.toggle("is-open");
      });

      visibilityOptions
        .querySelectorAll('input[type="radio"]')
        .forEach(function (radio) {
          radio.addEventListener("change", function () {
            visibilityInput.value = this.value;
            visibilityText.textContent =
              this.value === "private" ? "خصوصی" : "عمومی";
            visibilityOptions.classList.remove("is-open");

            if (this.value === "private") {
              statusInput.value = "private";
              statusText.textContent = "خصوصی";
            }
          });
        });
    }

    // ============================================
    // 🎯 Featured Image
    // ============================================
    const fiEmpty = document.getElementById("editor-fi-empty");
    const fiChangeBtn = document.getElementById("editor-fi-change-btn");
    const fiRemoveBtn = document.getElementById("editor-fi-remove-btn");
    const fiInput = document.getElementById("featured_image_id");

    function openMediaModal() {
      if (typeof wp === "undefined" || typeof wp.media === "undefined") {
        alert("WordPress Media Library در دسترس نیست");
        return;
      }

      const frame = wp.media({
        title: "انتخاب تصویر شاخص",
        button: { text: "انتخاب به عنوان تصویر شاخص" },
        multiple: false,
        library: { type: "image" },
      });

      frame.on("select", function () {
        const attachment = frame.state().get("selection").first().toJSON();
        fiInput.value = attachment.id;
        window.location.reload();
      });

      frame.open();
    }

    if (fiEmpty) fiEmpty.addEventListener("click", openMediaModal);
    if (fiChangeBtn) fiChangeBtn.addEventListener("click", openMediaModal);
    if (fiRemoveBtn) {
      fiRemoveBtn.addEventListener("click", function () {
        if (confirm("آیا از حذف تصویر شاخص مطمئن هستید؟")) {
          fiInput.value = "0";
          window.location.reload();
        }
      });
    }

    // ============================================
    // 🎯 Tags Input
    // ============================================
    document.querySelectorAll(".editor-tags-input").forEach(function (input) {
      const taxonomy = input.dataset.taxonomy;
      const addButton = input.nextElementSibling;
      const selectedContainer = document.getElementById(
        "tags-selected-" + taxonomy,
      );

      function addTag(tagName) {
        tagName = tagName.trim();
        if (!tagName) return;

        const existing = selectedContainer.querySelectorAll(".editor-tag-chip");
        for (let chip of existing) {
          if (chip.textContent.trim().replace("×", "") === tagName) {
            return;
          }
        }

        const chip = document.createElement("span");
        chip.className = "editor-tag-chip";
        chip.innerHTML = `
                    ${tagName}
                    <input type="hidden" name="post_tags[${taxonomy}][]" value="${tagName}">
                    <button type="button" class="editor-tag-chip-remove">×</button>
                `;

        chip
          .querySelector(".editor-tag-chip-remove")
          .addEventListener("click", function () {
            chip.remove();
          });

        selectedContainer.appendChild(chip);
        input.value = "";
      }

      if (addButton) {
        addButton.addEventListener("click", function () {
          const tags = input.value.split(",");
          tags.forEach(addTag);
        });
      }

      input.addEventListener("keypress", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          const tags = input.value.split(",");
          tags.forEach(addTag);
        }
      });
    });

    document
      .querySelectorAll(".editor-tag-chip-remove")
      .forEach(function (btn) {
        btn.addEventListener("click", function () {
          this.parentElement.remove();
        });
      });

    // ============================================
    // 🎯 Category Add Form
    // ============================================
    document.querySelectorAll(".editor-cats-add-btn").forEach(function (btn) {
      const taxonomy = btn.dataset.taxonomy;
      const form = document.getElementById("add-form-" + taxonomy);
      const cancelBtn = form.querySelector(".editor-cats-cancel-btn");
      const saveBtn = form.querySelector(".editor-cats-save-btn");
      const input = form.querySelector(".editor-cats-add-input");

      btn.addEventListener("click", function () {
        form.classList.add("is-open");
        input.focus();
      });

      cancelBtn.addEventListener("click", function () {
        form.classList.remove("is-open");
        input.value = "";
      });

      saveBtn.addEventListener("click", async function () {
        const name = input.value.trim();
        if (!name) {
          alert("نام دسته‌بندی الزامی است");
          return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = "در حال افزودن...";

        const formData = new FormData();
        formData.append("action", "nextsafar_add_term_ajax");
        formData.append("nonce", window.NextSafarEditor.nonce);
        formData.append("taxonomy", taxonomy);
        formData.append("name", name);

        try {
          const response = await fetch(window.NextSafarEditor.ajaxUrl, {
            method: "POST",
            body: formData,
          });
          const result = await response.json();

          if (result.success) {
            // افزودن دسته‌بندی جدید به درخت دسته‌بندی‌ها در UI
            const tree = document.querySelector(".editor-cats-tree");
            if (tree) {
              const li = document.createElement("li");
              li.innerHTML = `
               <label>
                  <input type="checkbox" name="post_terms[${taxonomy}][]" value="${result.data.term_id}" checked>
                  <span>${name}</span>
                  <span class="cat-count">0</span>
               </label>
            `;
              tree.appendChild(li);
            }

            input.value = "";
            form.classList.remove("is-open");
          } else {
            alert(result.data?.message || "خطا در افزودن دسته‌بندی");
          }
        } catch (error) {
          console.error(error);
          alert("خطا در ارتباط با سرور");
        } finally {
          saveBtn.disabled = false;
          saveBtn.textContent = "افزودن";
        }
      });
    });

    // ============================================
    // 🎯 Word Count
    // ============================================
    const contentEditor = document.getElementById("post_content");
    const wordCountEl = document.getElementById("word-count");
    const charCountEl = document.getElementById("char-count");
    const readTimeEl = document.getElementById("read-time");

    if (contentEditor && wordCountEl) {
      function updateWordCount() {
        let text = "";

        if (typeof tinymce !== "undefined") {
          const editor = tinymce.get("post_content");
          if (editor) {
            text = editor.getContent({ format: "text" });
          }
        } else {
          text = contentEditor.value;
        }

        text = text.replace(/<[^>]*>/g, "");
        const charCount = text.length;
        charCountEl.textContent = charCount;

        const words = text
          .trim()
          .split(/\s+/)
          .filter((w) => w.length > 0);
        const wordCount = words.length;
        wordCountEl.textContent = wordCount;

        const readTime = Math.ceil(wordCount / 200);
        readTimeEl.textContent = readTime;
      }

      setInterval(updateWordCount, 1000);
      updateWordCount();
    }

    // ============================================
    // 🎯 Preview Button
    // ============================================
    const previewBtn = document.getElementById("editor-preview-btn");
    if (previewBtn) {
      previewBtn.addEventListener("click", function () {
        const form = document.getElementById("nextsafar-post-editor-form");
        const originalAction = form.action;
        const originalTarget = form.target;

        form.action = window.NextSafarEditor.ajaxUrl;
        form.target = "_blank";

        const actionInput = document.createElement("input");
        actionInput.type = "hidden";
        actionInput.name = "action";
        actionInput.value = "nextsafar_preview_post";
        form.appendChild(actionInput);

        form.submit();

        form.action = originalAction;
        form.target = originalTarget;
        actionInput.remove();
      });
    }

    // ============================================
    // 🎯 Publish/Save Buttons
    // ============================================
    const publishBtn = document.getElementById("editor-publish-btn");
    const saveDraftBtn = document.getElementById("editor-save-draft-btn");
    const form = document.getElementById("nextsafar-post-editor-form");

    if (publishBtn && form) {
      publishBtn.addEventListener("click", function () {
        statusInput.value = "publish";
        form.submit();
      });
    }

    if (saveDraftBtn && form) {
      saveDraftBtn.addEventListener("click", function () {
        statusInput.value = "draft";
        form.submit();
      });
    }

    // ============================================
    // 🎯 Trash Button
    // ============================================
    const trashBtn = document.getElementById("editor-trash-btn");
    if (trashBtn) {
      trashBtn.addEventListener("click", function () {
        if (confirm(window.NextSafarEditor.strings.confirmDelete)) {
          const postId = window.NextSafarEditor.postId;
          window.location.href = `${window.NextSafarEditor.ajaxUrl.replace("admin-ajax.php", "post.php")}?action=trash&post=${postId}&_wpnonce=${window.NextSafarEditor.trashNonce || ""}`;
        }
      });
    }

    console.log("✅ NextSafar Post Editor initialized");
  });
})(jQuery);
