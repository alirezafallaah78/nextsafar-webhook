<div class="wrap nextsafar-wrap">
    <h1>تولید پست هوشمند</h1>
    <p class="description">تولید خودکار پست برای هتل، رستوران، مقصد، بیمارستان و فرودگاه</p>

    <div class="nextsafar-card">
        <h2>تنظیمات جستجو</h2>
        <form id="nextsafar-pg-form">
            <table class="form-table">
                <tr>
                    <th><label for="sync_type">نوع محتوا:</label></th>
                    <td>
                        <select id="sync_type" name="sync_type">
                            <option value="hotel">هتل</option>
                            <option value="restaurant">رستوران</option>
                            <option value="destination">مقصد گردشگری</option>
                            <option value="hospital">بیمارستان</option>
                            <option value="airport">فرودگاه</option>
                        </select>
                    </td>
                </tr>
                <!-- ✅ نمایش منبع فعال فعلی -->
                <?php 
                $active_provider = get_option('nextsafar_active_provider', 'searchapi');
                $provider_labels = [
                    'searchapi' => 'SearchApi.io',
                    'serpapi'   => 'SerpApi.com',
                ];
                $provider_icons = [
                    'searchapi' => '🔍',
                    'serpapi'   => '🌐',
                ];
                ?>
                <tr>
                    <th>منبع فعال:</th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                            <span style="font-size: 20px;"><?php echo $provider_icons[$active_provider] ?? '🔌'; ?></span>
                            <strong><?php echo esc_html($provider_labels[$active_provider] ?? $active_provider); ?></strong>
                            <a href="<?php echo admin_url('admin.php?page=nextsafar-pg-settings'); ?>" 
                            style="margin-right: auto; color: #2271b1; text-decoration: none;">
                                تغییر منبع →
                            </a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="location">مقصد:</label></th>
                    <td>
                        <input type="text" id="location" name="location" class="regular-text" placeholder="Istanbul, Turkey" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="limit">تعداد:</label></th>
                    <td>
                        <select id="limit" name="limit">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" id="start-sync-btn" class="button button-primary button-large">شروع تولید</button>
                <button type="button" id="test-api-btn" class="button">تست API</button>
            </p>
        </form>
    </div>

    <div id="terminal-container" style="display:none;">
        <h2>خروجی</h2>
        <div class="ns-terminal">
            <div class="ns-terminal-header">
                <div class="ns-terminal-dots">
                    <div class="ns-terminal-dot red"></div>
                    <div class="ns-terminal-dot yellow"></div>
                    <div class="ns-terminal-dot green"></div>
                </div>
                <div class="ns-terminal-title">NextSafar Post Generator</div>
            </div>
            <div class="ns-terminal-body" id="terminal-body"></div>
        </div>
        <div class="ns-progress-container">
            <div class="ns-progress-bar" id="progress-bar"></div>
        </div>
        <div class="ns-stats-row" id="stats-row" style="display:none;">
            <div class="ns-stat-box"><div class="ns-stat-box-value ns-stat-total" id="stat-total">0</div><div class="ns-stat-box-label">کل</div></div>
            <div class="ns-stat-box"><div class="ns-stat-box-value ns-stat-created" id="stat-created">0</div><div class="ns-stat-box-label">ایجاد</div></div>
            <div class="ns-stat-box"><div class="ns-stat-box-value ns-stat-updated" id="stat-updated">0</div><div class="ns-stat-box-label">بروزرسانی</div></div>
            <div class="ns-stat-box"><div class="ns-stat-box-value ns-stat-failed" id="stat-failed">0</div><div class="ns-stat-box-label">ناموفق</div></div>
        </div>
    </div>

    <div id="api-test-results" style="display:none;"></div>
</div>