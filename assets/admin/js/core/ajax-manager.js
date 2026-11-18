/**
 * NextSafar AJAX Manager - مدیریت متمرکز درخواست‌های AJAX
 */
const NextSafarAjax = {
  /**
   * ارسال درخواست AJAX
   */
  async request(action, data = {}, options = {}) {
    const formData = new FormData();
    formData.append("action", action);

    // اضافه کردن nonce
    if (data.nonce) {
      formData.append("nonce", data.nonce);
      delete data.nonce;
    }

    // اضافه کردن سایر داده‌ها
    Object.keys(data).forEach((key) => {
      formData.append(key, data[key]);
    });

    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        body: formData,
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Request failed");
      }

      return result.data;
    } catch (error) {
      console.error(`AJAX Error [${action}]:`, error);
      throw error;
    }
  },

  /**
   * ارسال درخواست با JSON
   */
  async requestJSON(action, data = {}, options = {}) {
    try {
      const response = await fetch(window.nextsafarAdmin.ajaxUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action,
          ...data,
        }),
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (!result.success) {
        throw new Error(result.data?.message || "Request failed");
      }

      return result.data;
    } catch (error) {
      console.error(`AJAX JSON Error [${action}]:`, error);
      throw error;
    }
  },
};

// Export
if (typeof window !== "undefined") {
  window.NextSafarAjax = NextSafarAjax;
}
