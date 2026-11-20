<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo $messageType === 'success' ? 'success' : 'error'; ?> is-dismissible" style="margin: 0 0 20px;">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('nextsafar_pg_settings'); ?>

        <!-- انتخاب منبع -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>منبع جستجو</h2>
            </div>
            <div class="nextsafar-panel-body">
                <?php $active_provider = get_option('nextsafar_active_provider', 'searchapi'); ?>
                <div class="ns-form-grid">
                    <div class="nextsafar-form-group">
                        <label for="active_provider">منبع فعال</label>
                        <select id="active_provider" name="active_provider" class="nextsafar-form-input">
                            <option value="searchapi" <?php selected($active_provider, 'searchapi'); ?>>SearchApi.io</option>
                            <option value="serpapi" <?php selected($active_provider, 'serpapi'); ?>>SerpApi.com</option>
                        </select>
                        <p class="ns-hint">منبعی که برای جستجوی هتل، رستوران، مقصد، بیمارستان و فرودگاه استفاده می‌شود.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- کلیدها -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header">
                <h2>کلیدهای API</h2>
            </div>
            <div class="nextsafar-panel-body">
                <div class="ns-form-grid">
                    <div class="nextsafar-form-group">
                        <label for="searchapi_key">SearchApi.io API Key</label>
                        <input type="password" id="searchapi_key" name="searchapi_key"
                               value="<?php echo esc_attr(get_option('nextsafar_searchapi_key', '')); ?>"
                               class="nextsafar-form-input" dir="ltr" autocomplete="off">
                        <p class="ns-hint">کلید API برای جستجوی هتل، رستوران و سایر مکان‌ها از SearchApi.io</p>
                    </div>
                    <div class="nextsafar-form-group">
                        <label for="serpapi_key">SerpApi.com API Key</label>
                        <input type="password" id="serpapi_key" name="serpapi_key"
                               value="<?php echo esc_attr(get_option('nextsafar_serpapi_key', '')); ?>"
                               class="nextsafar-form-input" dir="ltr" autocomplete="off">
                        <p class="ns-hint">کلید API جایگزین از SerpApi.com</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="nextsafar-panel">
            <div class="nextsafar-panel-body">
                <div class="ns-api-key-row">
                    <div class="nextsafar-form-group">
                        <label>📖 راهنما</label>
                        <p class="ns-hint" style="margin: 0;">
                            برای دریافت کلید API از 
                            <a href="https://www.searchapi.io/" target="_blank" rel="noopener">SearchApi.io</a> 
                            یا 
                            <a href="https://serpapi.com/" target="_blank" rel="noopener">SerpApi.com</a> 
                            ثبت‌نام کنید. هر دو سرویس جستجوی مکان‌های توریستی را ارائه می‌دهند.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
            <button type="submit" name="save_pg_settings" class="nextsafar-primary-button">
                <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>