<div class="wrap nextsafar-wrap">
    <h1>تنظیمات API Keys</h1>

    <form method="post">
        <?php wp_nonce_field('nextsafar_settings'); ?>
        <?php $settings = \NextSafar\Admin\PostGenerator\SettingsPage::getSettings(); ?>

        <div class="nextsafar-card">
            <h2>کلیدهای API</h2>
            <table class="form-table">
                <tr>
                    <th><label for="serpapi_key">SerpApi Key:</label></th>
                    <td>
                        <input type="password" id="serpapi_key" name="serpapi_key" value="<?php echo esc_attr($settings['serpapi_key']); ?>" class="regular-text" autocomplete="off">
                        <p class="description">دریافت از <a href="https://serpapi.com/" target="_blank">serpapi.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="searchapi_key">SearchApi Key:</label></th>
                    <td>
                        <input type="password" id="searchapi_key" name="searchapi_key" value="<?php echo esc_attr($settings['searchapi_key']); ?>" class="regular-text" autocomplete="off">
                        <p class="description">دریافت از <a href="https://www.searchapi.io/" target="_blank">searchapi.io</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="active_provider">منبع پیش‌فرض:</label></th>
                    <td>
                        <select id="active_provider" name="active_provider">
                            <option value="searchapi" <?php selected($settings['active_provider'], 'searchapi'); ?>>SearchApi.io</option>
                            <option value="serpapi" <?php selected($settings['active_provider'], 'serpapi'); ?>>SerpApi.com</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="save_api_keys" class="button button-primary">ذخیره تنظیمات</button>
            </p>
        </div>
    </form>
</div>