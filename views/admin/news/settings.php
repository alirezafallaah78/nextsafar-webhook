<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <h1>⚙️ تنظیمات سیستم اخبار</h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-<?= $messageType === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?= esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('nextsafar_news_settings'); ?>

        <div class="ns-card">
            <h2>🤖 دریافت خودکار</h2>
            <div class="ns-toggle-row">
                <div class="ns-toggle-info">
                    <div class="ns-toggle-label">فعال‌سازی دریافت خودکار اخبار</div>
                    <div class="ns-toggle-desc">در ساعات مشخص شده، سیستم به صورت خودکار اخبار جدید را دریافت می‌کند</div>
                </div>
                <label class="ns-switch">
                    <input type="checkbox" name="news_auto" value="1" <?= checked($settings['news_auto'], '1', false); ?>>
                    <span class="ns-slider"></span>
                </label>
            </div>
        </div>

        <div class="ns-card">
            <h2>🕐 زمان‌بندی دریافت</h2>
            <div class="ns-hours-grid">
                <?php for ($i = 0; $i < 24; $i++): 
                    $checked = in_array($i, (array)$settings['sync_hours'], true);
                ?>
                    <label class="ns-hour-cell <?= $checked ? 'checked' : ''; ?>">
                        <input type="checkbox" name="sync_hours[]" value="<?= $i; ?>" <?= checked($checked, true, false); ?>>
                        <span><?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="ns-card">
            <h2>🤖 هوش مصنوعی Gemini</h2>
            <div class="ns-form-row">
                <label for="gemini_api_key">کلید Gemini API:</label>
                <input type="password" id="gemini_api_key" name="gemini_api_key" 
                       value="<?= esc_attr($settings['gemini_api_key']); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
            </div>
        </div>

        <div class="ns-submit-row">
            <button type="submit" name="save_news_settings" class="button button-primary button-large">
                💾 ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>