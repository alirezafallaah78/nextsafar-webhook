document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginform");
  const submitBtn = document.getElementById("wp-submit");

  if (loginForm && submitBtn) {
    loginForm.addEventListener("submit", function () {
      // ذخیره متن اصلی دکمه
      const originalText = submitBtn.value || submitBtn.innerText;

      // تغییر متن و غیرفعال کردن دکمه
      submitBtn.value = nextsafarLogin.loadingText || "در حال ورود...";
      submitBtn.disabled = true;
      submitBtn.style.opacity = "0.7";
      submitBtn.style.cursor = "not-allowed";

      // اگر به هر دلیلی فرم ارسال نشد (مثلاً اعتبارسنجی HTML5)، دکمه را برگردان
      setTimeout(() => {
        if (!loginForm.checkValidity()) {
          submitBtn.value = originalText;
          submitBtn.disabled = false;
          submitBtn.style.opacity = "1";
          submitBtn.style.cursor = "pointer";
        }
      }, 1000);
    });
  }
});
