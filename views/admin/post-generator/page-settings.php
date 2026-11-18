<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <h1>⚙️ تنظیمات تولید پست</h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-<?= $messageType === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?= esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('nextsafar_pg_settings'); ?>

        <div class="ns-card">
            <h2>🔌 انتخاب منبع API</h2>
            <div class="ns-form-row">
                <label>منبع فعال:</label>
                <?php 
                $active_provider = get_option('nextsafar_active_provider', 'searchapi');
                ?>
                <select name="active_provider" class="ns-select">
                    <option value="searchapi" <?= selected($active_provider, 'searchapi'); ?>>🔍 SearchApi.io</option>
                    <option value="serpapi" <?= selected($active_provider, 'serpapi'); ?>>🌐 SerpApi.com</option>
                </select>
            </div>
        </div>

        <div class="ns-card">
            <h2>🔑 کلیدهای API</h2>
            <div class="ns-form-row">
                <label for="searchapi_key">SearchApi.io API Key:</label>
                <input type="password" id="searchapi_key" name="searchapi_key" 
                       value="<?= esc_attr(get_option('nextsafar_searchapi_key', '')); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
            </div>

            <div class="ns-form-row">
                <label for="serpapi_key">SerpApi.com API Key:</label>
                <input type="password" id="serpapi_key" name="serpapi_key" 
                       value="<?= esc_attr(get_option('nextsafar_serpapi_key', '')); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
            </div>
        </div>

        <div class="ns-card">
            <h2>🤖 هوش مصنوعی Gemini</h2>
            <div class="ns-form-row">
                <label for="pg_gemini_key">Gemini API Key:</label>
                <input type="password" id="pg_gemini_key" name="pg_gemini_key" 
                       value="<?= esc_attr(get_option('nextsafar_pg_gemini_key', '')); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
            </div>
        </div>

        <div class="ns-submit-row">
            <button type="submit" name="save_pg_settings" class="button button-primary button-large">
                💾 ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>