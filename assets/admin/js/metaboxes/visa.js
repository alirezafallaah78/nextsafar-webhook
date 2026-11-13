(function ($) {
  "use strict";

  $(document).ready(function () {
    /* ========================================================================
       Visa Prices
       ✅ ردیف جدید از قالب PHP ساخته می‌شود (tmpl-ns-visa-price-row)
       ✅ همه فیلدها SELECT هستند به‌جز قیمت — دقیقاً مثل ردیف‌های ذخیره‌شده
       ======================================================================== */
    const $wrapper = $("#visa-prices-wrapper");

    // ✅ ایندکس یکتا: بزرگ‌ترین ایندکس موجود + 1
    //    (قبلاً از تعداد ردیف‌ها استفاده می‌شد و بعد از حذف ردیف میانی،
    //     ایندکس تکراری می‌شد و یک ردیف روی ردیف دیگر overwrite می‌شد)
    function nextPriceIndex() {
      let max = -1;

      $wrapper.find("select[name], input[name]").each(function () {
        const m = /^visa_prices\[(\d+)\]/.exec(this.name || "");
        if (m) {
          max = Math.max(max, parseInt(m[1], 10));
        }
      });

      return max + 1;
    }

    $("#add-visa-price").on("click", function (e) {
      e.preventDefault();

      const tpl = document.getElementById("tmpl-ns-visa-price-row");
      if (!tpl || !$wrapper.length) {
        return;
      }

      const html = tpl.innerHTML.replace(/__INDEX__/g, String(nextPriceIndex()));
      const $row = $(html);

      $wrapper.append($row);
      $row.find("select").first().trigger("focus");
    });

    // Remove price.
    $(document).on("click", ".ns-remove-visa-price", function (e) {
      e.preventDefault();

      $(this)
        .closest(".ns-visa-price-group")
        .fadeOut(200, function () {
          $(this).remove();
        });
    });

    /* ========================================================================
       Visa Banner Image
       ======================================================================== */
    let bannerFrame = null;

    $(document).on("click", ".ns-visa-banner-upload", function (e) {
      e.preventDefault();

      const $input = $("#ns-visa-banner-input");
      const $preview = $("#ns-visa-banner-preview");
      const $removeBtn = $(".ns-visa-banner-remove");

      if (!bannerFrame) {
        bannerFrame = wp.media({
          title: "انتخاب عکس بنر ویزا",
          button: { text: "انتخاب بنر" },
          multiple: false,
        });
      }

      // ✅ هر بار handler جدید بسته می‌شود تا همیشه روی فیلد فعلی بنویسد
      bannerFrame.off("select");
      bannerFrame.on("select", function () {
        const att = bannerFrame.state().get("selection").first().toJSON();

        $input.val(att.url);
        $preview.empty().append(
          $("<img>", {
            src: att.url,
            css: { maxWidth: "100%", height: "auto", borderRadius: "6px" },
          }),
        );
        $removeBtn.show();
      });

      bannerFrame.open();
    });

    $(document).on("click", ".ns-visa-banner-remove", function (e) {
      e.preventDefault();

      $("#ns-visa-banner-input").val("");
      $("#ns-visa-banner-preview").html(
        '<div class="ns-visa-banner-placeholder">🏞️ بنری انتخاب نشده</div>',
      );

      $(this).hide();
    });

    /* ========================================================================
       Visa Category Image Upload (Banner and Image)
       ======================================================================== */
    let visaMediaFrame = null;

    $(document).on("click", ".ns-visa-upload", function (e) {
      e.preventDefault();

      const $button = $(this);
      const target = $button.data("target");
      const $wrap = $button.closest(".ns-visa-image-field");
      const $input = $wrap.find(`input[name="${target}"]`);
      const $preview = $wrap.find(
        `.ns-visa-image-preview[data-target="${target}"]`,
      );
      const $removeBtn = $wrap.find(`.ns-visa-remove[data-target="${target}"]`);

      if (!visaMediaFrame) {
        visaMediaFrame = wp.media({
          title: "انتخاب تصویر",
          button: { text: "انتخاب تصویر" },
          multiple: false,
        });
      }

      // ✅ رفع باگ: قبلاً select فقط یک‌بار bind می‌شد و آپلود فیلد دوم
      //    باز هم داخل فیلد اول ذخیره می‌شد
      visaMediaFrame.off("select");
      visaMediaFrame.on("select", function () {
        const att = visaMediaFrame.state().get("selection").first().toJSON();

        $input.val(att.id);
        $preview.empty().append(
          $("<img>", { src: att.url, css: { maxWidth: "100px", height: "auto" } }),
        );
        $removeBtn.show();
      });

      visaMediaFrame.open();
    });

    $(document).on("click", ".ns-visa-remove", function (e) {
      e.preventDefault();

      const target = $(this).data("target");
      const $wrap = $(this).closest(".ns-visa-image-field");
      const $input = $wrap.find(`input[name="${target}"]`);
      const $preview = $wrap.find(
        `.ns-visa-image-preview[data-target="${target}"]`,
      );

      $input.val("");
      $preview.empty();
      $(this).hide();
    });
  });
})(jQuery);