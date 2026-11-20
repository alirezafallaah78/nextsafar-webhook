<?php
if (!defined('ABSPATH')) exit;

$settings     = isset($settings) && is_array($settings) ? $settings : array();
$message      = isset($message) ? (string) $message : '';
$messageType  = isset($messageType) ? (string) $messageType : '';
$geminiModels = isset($geminiModels) ? $geminiModels : array();

$s = function ($key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

// نرمال‌سازی لیست مدل‌ها به آرایه‌ای از شناسه‌ها
$model_ids = array();
if (is_array($geminiModels)) {
    foreach ($geminiModels as $k => $v) {
        if (is_array($v)) {
            $id = $v['id'] ?? ($v['name'] ?? '');
        } elseif (is_string($v)) {
            $id = (is_string($k) && !is_numeric($k)) ? $k : $v;
        } else {
            $id = '';
        }
        if (is_string($id) && $id !== '') {
            $model_ids[] = $id;
        }
    }
}
$model_ids = array_values(array_unique($model_ids));
?>
<div class="wrap nextsafar-wrap">
    <?php if ($message !== '') : ?>
        <div class="notice notice-<?php echo $messageType === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('nextsafar_news_settings'); ?>

        <!-- ═══ دریافت خودکار ═══ -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>دریافت خودکار</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-toggle-row">
                    <div class="ns-toggle-info">
                        <div class="ns-toggle-label">فعال‌سازی دریافت خودکار اخبار</div>
                        <div class="ns-toggle-desc">در ساعات مشخص شده، سیستم به صورت خودکار اخبار جدید را دریافت می‌کند</div>
                    </div>
                    <label class="ns-switch">
                        <input type="checkbox" name="news_auto" value="1" <?php checked($s('news_auto', '0'), '1'); ?>>
                        <span class="ns-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ═══ زمان‌بندی دریافت ═══ -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>زمان‌بندی دریافت</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-hours-grid">
                    <?php for ($i = 0; $i < 24; $i++) :
                        $checked = in_array($i, (array) $s('sync_hours', array()), true); ?>
                        <label class="ns-hour-cell <?php echo $checked ? 'checked' : ''; ?>">
                            <input type="checkbox" name="sync_hours[]" value="<?php echo (int) $i; ?>" <?php checked($checked, true); ?>>
                            <span><?php echo str_pad((string) $i, 2, '0', STR_PAD_LEFT); ?>:00</span>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- ═══ بودجه و محدودیت ═══ -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>بودجه و محدودیت هر اجرا</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-form-row">
                    <label for="time_window_hours">بازه زمانی خبر (ساعت):</label>
                    <input type="number" min="1" max="168" id="time_window_hours" name="time_window_hours"
                           class="ns-input ns-input-small" value="<?php echo esc_attr($s('time_window_hours', 24)); ?>">
                </div>
                <div class="ns-form-row">
                    <label for="max_publish_per_run">حداکثر انتشار در هر اجرا:</label>
                    <input type="number" min="0" max="50" id="max_publish_per_run" name="max_publish_per_run"
                           class="ns-input ns-input-small" value="<?php echo esc_attr($s('max_publish_per_run', 5)); ?>">
                </div>
                <div class="ns-form-row">
                    <label for="max_draft_per_run">حداکثر پیش‌نویس در هر اجرا:</label>
                    <input type="number" min="0" max="50" id="max_draft_per_run" name="max_draft_per_run"
                           class="ns-input ns-input-small" value="<?php echo esc_attr($s('max_draft_per_run', 3)); ?>">
                </div>
                <div class="ns-form-row">
                    <label for="max_ai_review_per_run">حداکثر بررسی AI در هر اجرا:</label>
                    <input type="number" min="0" max="50" id="max_ai_review_per_run" name="max_ai_review_per_run"
                           class="ns-input ns-input-small" value="<?php echo esc_attr($s('max_ai_review_per_run', 2)); ?>">
                </div>
            </div>
        </div>

        <!-- ═══ هوش مصنوعی Gemini ═══ -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>هوش مصنوعی Gemini</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-toggle-row">
                    <div class="ns-toggle-info">
                        <div class="ns-toggle-label">فعال‌سازی بازنویسی با AI</div>
                        <div class="ns-toggle-desc">اگر خاموش باشد، خبرها بدون بازنویسی منتشر می‌شوند</div>
                    </div>
                    <label class="ns-switch">
                        <input type="checkbox" name="ai_enabled" value="1" <?php checked($s('ai_enabled', '0'), '1'); ?>>
                        <span class="ns-slider"></span>
                    </label>
                </div>

                <div class="ns-form-row" style="margin-top: 16px;">
                    <label for="gemini_api_key">کلید Gemini API:</label>
                    <input type="password" id="gemini_api_key" name="gemini_api_key"
                           value="<?php echo esc_attr($s('gemini_api_key')); ?>"
                           class="ns-input" dir="ltr" autocomplete="off">
                </div>

                <?php if (!empty($model_ids)) : ?>
                    <datalist id="ns-gemini-models">
                        <?php foreach ($model_ids as $mid) : ?>
                            <option value="<?php echo esc_attr($mid); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                <?php endif; ?>

                <div class="ns-form-row">
                    <label for="ai_model_main">مدل اصلی:</label>
                    <input type="text" id="ai_model_main" name="ai_model_main" list="ns-gemini-models"
                           value="<?php echo esc_attr($s('ai_model_main', 'gemini-3.7-flash')); ?>"
                           class="ns-input" dir="ltr">
                </div>
                <div class="ns-form-row">
                    <label for="ai_model_backup1">مدل پشتیبان ۱:</label>
                    <input type="text" id="ai_model_backup1" name="ai_model_backup1" list="ns-gemini-models"
                           value="<?php echo esc_attr($s('ai_model_backup1', 'gemini-3.6-flash')); ?>"
                           class="ns-input" dir="ltr">
                </div>
                <div class="ns-form-row">
                    <label for="ai_model_backup2">مدل پشتیبان ۲:</label>
                    <input type="text" id="ai_model_backup2" name="ai_model_backup2" list="ns-gemini-models"
                           value="<?php echo esc_attr($s('ai_model_backup2', 'gemini-3.5-flash')); ?>"
                           class="ns-input" dir="ltr">
                </div>

                <div class="ns-test-row">
                    <button type="button" class="nextsafar-secondary-button ns-test-btn" data-service="gemini">
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        تست اتصال Gemini
                    </button>
                    <span class="ns-test-result" data-service="gemini"></span>
                </div>
                <p class="ns-hint" style="margin-top: 10px;">تست با کلید <strong>ذخیره‌شده</strong> انجام می‌شود؛ اول ذخیره کنید، بعد تست.</p>
            </div>
        </div>

        <!-- ═══ آستانه‌های فیلتر ═══ -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>آستانه‌های فیلتر امتیاز</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-form-row">
                    <label for="publish_threshold">آستانه انتشار:</label>
                    <input type="number" id="publish_threshold" name="publish_threshold"
                           class="ns-input ns-input-small" value="<?php echo esc_attr($s('publish_threshold', 20)); ?>">
                </div>
                <div class="ns-form-row">
                    <label for="delete_threshold">آستانه حذف:</label>
                    <input type="number" id="delete_threshold" name="delete_threshold"
                           class="ns-input ns-input-small" value="<?php echo esc_attr($s('delete_threshold', -10)); ?>">
                </div>
            </div>
        </div>

        <div class="ns-submit-row">
            <button type="submit" name="save_news_settings" class="nextsafar-primary-button">
                <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                ذخیره تنظیمات
            </button>
        </div>
    </form>

    <form method="post" action="">
        <?php wp_nonce_field('nextsafar_news_settings'); ?>

        <!-- ═══ کلیدهای API منابع خبری ═══ -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>کلیدهای API منابع خبری</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-form-row">
                    <label for="gnews_api_key">GNews API Key:</label>
                    <input type="password" id="gnews_api_key" name="gnews_api_key"
                           value="<?php echo esc_attr($s('gnews_api_key')); ?>" class="ns-input" dir="ltr" autocomplete="off">
                </div>
                <div class="ns-test-row">
                    <button type="button" class="nextsafar-secondary-button ns-test-btn" data-service="gnews">
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        تست GNews
                    </button>
                    <span class="ns-test-result" data-service="gnews"></span>
                </div>

                <div class="ns-form-row" style="margin-top: 16px;">
                    <label for="newsdata_api_key">NewsData API Key:</label>
                    <input type="password" id="newsdata_api_key" name="newsdata_api_key"
                           value="<?php echo esc_attr($s('newsdata_api_key')); ?>" class="ns-input" dir="ltr" autocomplete="off">
                </div>
                <div class="ns-test-row">
                    <button type="button" class="nextsafar-secondary-button ns-test-btn" data-service="newsdata">
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        تست NewsData
                    </button>
                    <span class="ns-test-result" data-service="newsdata"></span>
                </div>

                <div class="ns-form-row" style="margin-top: 16px;">
                    <label for="currents_api_key">Currents API Key:</label>
                    <input type="password" id="currents_api_key" name="currents_api_key"
                           value="<?php echo esc_attr($s('currents_api_key')); ?>" class="ns-input" dir="ltr" autocomplete="off">
                </div>
                <div class="ns-test-row">
                    <button type="button" class="nextsafar-secondary-button ns-test-btn" data-service="currents">
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        تست Currents
                    </button>
                    <span class="ns-test-result" data-service="currents"></span>
                </div>

                <div class="ns-submit-row">
                    <button type="submit" name="save_api_keys" class="nextsafar-primary-button">
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        ذخیره کلیدها
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- ═══ تست منابع RSS ═══ -->
    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header">
            <h2>تست منابع RSS</h2>
        </div>
        <div class="nextsafar-panel-body">
            <div class="ns-test-row">
                <button type="button" class="nextsafar-secondary-button ns-test-btn" data-service="rss">
                    <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>
                    تست اولین منبع RSS فعال
                </button>
                <span class="ns-test-result" data-service="rss"></span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    "use strict";
    var cfg = window.nextsafarNews || {};
    if (!cfg.ajaxUrl || !cfg.nonce) return;

    document.querySelectorAll(".ns-test-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var service = btn.getAttribute("data-service");
            var out = document.querySelector('.ns-test-result[data-service="' + service + '"]');
            btn.disabled = true;
            if (out) { out.textContent = "در حال تست..."; out.className = "ns-test-result"; }

            var fd = new FormData();
            fd.append("action", "nextsafar_news_test_connection");
            fd.append("nonce", cfg.nonce);
            fd.append("service", service);

            fetch(cfg.ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var msg = (d && d.data && d.data.message) ? d.data.message : "پاسخ نامعتبر";
                    if (out) {
                        out.textContent = msg;
                        out.className = "ns-test-result " + (d && d.success ? "is-ok" : "is-err");
                    }
                })
                .catch(function () {
                    if (out) { out.textContent = "خطا در اتصال"; out.className = "ns-test-result is-err"; }
                })
                .finally(function () { btn.disabled = false; });
        });
    });
})();
</script>