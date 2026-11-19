<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <div class="nextsafar-page-header">
        <div>
            <h1>تنظیمات تولید پست</h1>
            <p>مدیریت APIها و تنظیمات هوش مصنوعی</p>
        </div>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo $messageType === 'success' ? 'success' : 'error'; ?> is-dismissible" style="margin: 0 0 20px;">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('nextsafar_pg_settings'); ?>

        <!-- انتخاب منبع -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header"><h2>منبع جستجو</h2></div>
            <div class="nextsafar-panel-body">
                <?php $active_provider = get_option('nextsafar_active_provider', 'searchapi'); ?>
                <div class="ns-form-grid">
                    <div class="nextsafar-form-group">
                        <label for="active_provider">منبع فعال</label>
                        <select id="active_provider" name="active_provider" class="nextsafar-form-input">
                            <option value="searchapi" <?php selected($active_provider, 'searchapi'); ?>>SearchApi.io</option>
                            <option value="serpapi" <?php selected($active_provider, 'serpapi'); ?>>SerpApi.com</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- کلیدها -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header"><h2>کلیدهای API</h2></div>
            <div class="nextsafar-panel-body">
                <div class="ns-form-grid">
                    <div class="nextsafar-form-group">
                        <label for="searchapi_key">SearchApi.io API Key</label>
                        <input type="password" id="searchapi_key" name="searchapi_key"
                               value="<?php echo esc_attr(get_option('nextsafar_searchapi_key', '')); ?>"
                               class="nextsafar-form-input" dir="ltr" autocomplete="off">
                    </div>
                    <div class="nextsafar-form-group">
                        <label for="serpapi_key">SerpApi.com API Key</label>
                        <input type="password" id="serpapi_key" name="serpapi_key"
                               value="<?php echo esc_attr(get_option('nextsafar_serpapi_key', '')); ?>"
                               class="nextsafar-form-input" dir="ltr" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>

        <!-- هوش مصنوعی -->
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header"><h2>هوش مصنوعی Gemini</h2></div>
            <div class="nextsafar-panel-body">
                <div class="ns-form-grid">
                    <div class="nextsafar-form-group span-2">
                        <label for="pg_gemini_key">Gemini API Key</label>
                        <input type="password" id="pg_gemini_key" name="pg_gemini_key"
                               value="<?php echo esc_attr(get_option('nextsafar_pg_gemini_key', '')); ?>"
                               class="nextsafar-form-input" dir="ltr" autocomplete="off">
                        <p class="ns-hint">برای بازنویسی و تولید محتوای پست‌ها استفاده می‌شود.</p>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
            <button type="submit" name="save_pg_settings" class="nextsafar-primary-button">ذخیره تنظیمات</button>
        </div>
    </form>
</div>