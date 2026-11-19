<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <div class="nextsafar-page-header">
        <div>
            <h1>تولید دستی اخبار</h1>
            <p>دریافت و پردازش دستی اخبار از منابع RSS و API</p>
        </div>
    </div>

    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header"><h2>تنظیمات سینک</h2></div>
        <div class="nextsafar-panel-body">
            <div class="ns-form-grid">
                <div class="nextsafar-form-group">
                    <p class="ns-hint" style="margin: 0;">
                        منابع موجود: <?php echo (int) $rssCount; ?> فید RSS و <?php echo (int) $apiCount; ?> سرویس API
                    </p>
                </div>
                <div class="nextsafar-form-group">
                    <label for="sync-mode">حالت سینک</label>
                    <select id="sync-mode" class="nextsafar-form-input">
                        <option value="quick">سریع (بدون هوش مصنوعی)</option>
                        <option value="ai" <?php echo $aiEnabled ? '' : 'disabled'; ?>>با بازنویسی هوش مصنوعی</option>
                    </select>
                </div>
            </div>

            <div class="ns-panel-footer" style="border-top: none; padding-top: 0; margin-top: 10px;">
                <span class="ns-hint">در حالت سریع، اخبار بدون بازنویسی و با سرعت بالاتر دریافت می‌شوند.</span>
                <button type="button" class="nextsafar-primary-button" id="start-news-sync">شروع همگام‌سازی</button>
            </div>
        </div>
    </div>

    <div id="terminal-container" style="display:none; margin-top: 20px;">
        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header"><h2>خروجی اجرای زنده</h2></div>
            <div class="nextsafar-panel-body">
                <div class="ns-terminal">
                    <div class="ns-terminal-header">
                        <div class="ns-terminal-dots">
                            <span class="ns-terminal-dot red"></span>
                            <span class="ns-terminal-dot yellow"></span>
                            <span class="ns-terminal-dot green"></span>
                        </div>
                        <div class="ns-terminal-title">NextSafar News Sync</div>
                    </div>
                    <div class="ns-terminal-body" id="terminal-body"></div>
                </div>

                <div class="ns-progress-container">
                    <div class="ns-progress-bar" id="progress-bar"></div>
                </div>

                <div class="ns-stats-row" id="stats-row" style="display:none; margin-top: 16px;">
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value ns-stat-total" id="stat-total">0</div>
                        <div class="ns-stat-box-label">کل پردازش</div>
                    </div>
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value ns-stat-published" id="stat-published">0</div>
                        <div class="ns-stat-box-label">منتشر شده</div>
                    </div>
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value ns-stat-failed" id="stat-failed">0</div>
                        <div class="ns-stat-box-label">ناموفق</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>