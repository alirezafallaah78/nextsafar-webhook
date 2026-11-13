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

        <!-- ═══ دریافت خودکار ═══ -->
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

        <!-- ═══ زمان‌بندی ═══ -->
        <div class="ns-card">
            <h2>🕐 زمان‌بندی دریافت (۲۴ ساعته)</h2>
            <p class="description">ساعت‌هایی که دریافت اخبار به صورت خودکار انجام شود را انتخاب کنید</p>
            
            <div class="ns-hours-actions" style="margin-bottom:15px;">
                <button type="button" id="ns-select-all-hours" class="button">✅ انتخاب همه</button>
                <button type="button" id="ns-clear-all-hours" class="button">❌ پاک کردن همه</button>
                <span id="ns-selected-count" style="margin-right:15px; color:#666;">
                    <?= count($settings['sync_hours']); ?> ساعت انتخاب شده
                </span>
            </div>

            <div class="ns-hours-grid">
                <?php for ($i = 0; $i < 24; $i++): 
                    $checked = in_array($i, (array)$settings['sync_hours'], true);
                ?>
                    <label class="ns-hour-cell <?= $checked ? 'checked' : ''; ?>" data-hour="<?= $i; ?>">
                        <input type="checkbox" name="sync_hours[]" value="<?= $i; ?>" <?= checked($checked, true, false); ?>>
                        <span><?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <!-- ═══ هوش مصنوعی Gemini ═══ -->
        <div class="ns-card">
            <h2>🤖 هوش مصنوعی Gemini</h2>
            
            <div class="ns-toggle-row">
                <div class="ns-toggle-info">
                    <div class="ns-toggle-label">فعال‌سازی ترجمه و بازنویسی با AI</div>
                    <div class="ns-toggle-desc">اخبار غیرفارسی به صورت خودکار ترجمه و بازنویسی می‌شوند</div>
                </div>
                <label class="ns-switch">
                    <input type="checkbox" name="ai_enabled" value="1" <?= checked($settings['ai_enabled'], '1', false); ?>>
                    <span class="ns-slider"></span>
                </label>
            </div>

            <div class="ns-form-row">
                <label for="gemini_api_key">کلید Gemini API:</label>
                <input type="password" id="gemini_api_key" name="gemini_api_key" 
                       value="<?= esc_attr($settings['gemini_api_key']); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
                <p class="description">
                    از <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a> دریافت کنید
                </p>
                <button type="button" class="button" id="test-gemini-btn">🧪 تست اتصال</button>
                <div id="gemini-test-result" style="margin-top:10px;"></div>
            </div>

            <div class="ns-form-row">
                <label>مدل اصلی:</label>
                <select name="ai_model_main" class="ns-select">
                    <?php 
                    $models = $geminiModels['models'] ?? [];
                    if (empty($models)) {
                        echo '<option value="' . esc_attr($settings['ai_model_main']) . '">' . esc_html($settings['ai_model_main']) . '</option>';
                    } else {
                        foreach ($models as $m) {
                            printf(
                                '<option value="%s" %s>%s %s</option>',
                                esc_attr($m['name']),
                                selected($settings['ai_model_main'], $m['name'], false),
                                esc_html($m['display_name']),
                                $m['is_latest'] ? '⭐' : ''
                            );
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="ns-form-row">
                <label>مدل پشتیبان 1:</label>
                <select name="ai_model_backup1" class="ns-select">
                    <?php foreach ($models as $m): ?>
                        <option value="<?= esc_attr($m['name']); ?>" 
                                <?= selected($settings['ai_model_backup1'], $m['name'], false); ?>>
                            <?= esc_html($m['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ns-form-row">
                <label>مدل پشتیبان 2:</label>
                <select name="ai_model_backup2" class="ns-select">
                    <?php foreach ($models as $m): ?>
                        <option value="<?= esc_attr($m['name']); ?>" 
                                <?= selected($settings['ai_model_backup2'], $m['name'], false); ?>>
                            <?= esc_html($m['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ═══ محدودیت‌های هر اجرا ═══ -->
        <div class="ns-card">
            <h2>⚡ محدودیت‌های هر اجرا</h2>
            <div class="ns-numbers-grid">
                <div class="ns-number-card">
                    <div class="ns-number-icon">✅</div>
                    <div class="ns-number-title">انتشار مستقیم</div>
                    <input type="number" name="max_publish_per_run" 
                           value="<?= esc_attr($settings['max_publish_per_run']); ?>" 
                           min="1" max="50" class="ns-number-input">
                </div>
                <div class="ns-number-card">
                    <div class="ns-number-icon">📝</div>
                    <div class="ns-number-title">پیش‌نویس</div>
                    <input type="number" name="max_draft_per_run" 
                           value="<?= esc_attr($settings['max_draft_per_run']); ?>" 
                           min="0" max="50" class="ns-number-input">
                </div>
                <div class="ns-number-card">
                    <div class="ns-number-icon">🤖</div>
                    <div class="ns-number-title">بررسی AI</div>
                    <input type="number" name="max_ai_review_per_run" 
                           value="<?= esc_attr($settings['max_ai_review_per_run']); ?>" 
                           min="0" max="20" class="ns-number-input">
                </div>
                <div class="ns-number-card">
                    <div class="ns-number-icon">⏱️</div>
                    <div class="ns-number-title">بازه زمانی (ساعت)</div>
                    <input type="number" name="time_window_hours" 
                           value="<?= esc_attr($settings['time_window_hours']); ?>" 
                           min="1" max="168" class="ns-number-input">
                </div>
            </div>
        </div>

        <!-- ═══ آستانه‌های فیلتر ═══ -->
        <div class="ns-card">
            <h2>🎯 آستانه‌های فیلتر</h2>
            <div class="ns-numbers-grid">
                <div class="ns-number-card">
                    <div class="ns-number-title">آستانه انتشار (حداقل امتیاز)</div>
                    <input type="number" name="publish_threshold" 
                           value="<?= esc_attr($settings['publish_threshold']); ?>" 
                           class="ns-number-input">
                </div>
                <div class="ns-number-card">
                    <div class="ns-number-title">آستانه حذف (حداکثر امتیاز)</div>
                    <input type="number" name="delete_threshold" 
                           value="<?= esc_attr($settings['delete_threshold']); ?>" 
                           class="ns-number-input">
                </div>
            </div>
        </div>

        <!-- ═══ کلیدهای API منابع ═══ -->
        <div class="ns-card">
            <h2>🔑 کلیدهای API منابع</h2>
            <p class="description">
                این کلیدها برای دریافت اخبار از منابع API استفاده می‌شوند (اختیاری - RSS بدون کلید کار می‌کند)
            </p>

            <div class="ns-form-row">
                <label for="gnews_api_key">GNews.io:</label>
                <input type="password" id="gnews_api_key" name="gnews_api_key" 
                       value="<?= esc_attr($settings['gnews_api_key']); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
                <p class="description">از <a href="https://gnews.io/" target="_blank">gnews.io</a> دریافت کنید</p>
            </div>

            <div class="ns-form-row">
                <label for="newsdata_api_key">NewsData.io:</label>
                <input type="password" id="newsdata_api_key" name="newsdata_api_key" 
                       value="<?= esc_attr($settings['newsdata_api_key']); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
                <p class="description">از <a href="https://newsdata.io/" target="_blank">newsdata.io</a> دریافت کنید</p>
            </div>

            <div class="ns-form-row">
                <label for="currents_api_key">Currents API:</label>
                <input type="password" id="currents_api_key" name="currents_api_key" 
                       value="<?= esc_attr($settings['currents_api_key']); ?>" 
                       class="ns-input" dir="ltr" autocomplete="off">
                <p class="description">از <a href="https://currentsapi.services/" target="_blank">currentsapi.services</a> دریافت کنید</p>
            </div>
        </div>

        <!-- ═══ دکمه ذخیره ═══ -->
        <div class="ns-submit-row">
            <button type="submit" name="save_news_settings" class="button button-primary button-large">
                💾 ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>