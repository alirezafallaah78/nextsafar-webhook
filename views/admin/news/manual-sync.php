<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap nextsafar-wrap">
    <h1>🎯 تولید دستی اخبار</h1>
    <p class="description">
        دریافت و پردازش دستی اخبار از منابع RSS و API. 
        می‌توانید انتخاب کنید که AI برای ترجمه و بازنویسی استفاده شود یا نه.
    </p>

    <div class="ns-card">
        <h2>تنظیمات سینک</h2>
        
        <div class="ns-info-box">
            <strong>ℹ️ منابع موجود:</strong>
            <?= $rssCount; ?> RSS feed + <?= $apiCount; ?> API
        </div>

        <div class="ns-form-row">
            <label for="sync-mode">حالت سینک:</label>
            <select id="sync-mode" class="ns-select">
                <option value="quick">⚡ سریع (بدون AI - فقط فیلتر کلمات)</option>
                <option value="ai" <?= $aiEnabled ? '' : 'disabled'; ?>>🤖 با AI (ترجمه و بازنویسی)</option>
            </select>
            <?php if (!$aiEnabled): ?>
                <p class="description" style="color:#d63638;">
                    ⚠️ برای استفاده از AI، ابتدا در <a href="<?= admin_url('admin.php?page=nextsafar-news-settings'); ?>">تنظیمات</a> آن را فعال کنید.
                </p>
            <?php endif; ?>
        </div>

        <div class="ns-form-row">
            <button type="button" id="start-news-sync" class="button button-primary button-large">
                🚀 شروع همگام‌سازی
            </button>
        </div>
    </div>

    <!-- Terminal Output -->
    <div id="terminal-container" style="display:none;">
        <h2>📟 خروجی ترمینال</h2>
        <div class="ns-terminal">
            <div class="ns-terminal-header">
                <div class="ns-terminal-dots">
                    <div class="ns-terminal-dot red"></div>
                    <div class="ns-terminal-dot yellow"></div>
                    <div class="ns-terminal-dot green"></div>
                </div>
                <div class="ns-terminal-title">NextSafar News Sync</div>
            </div>
            <div class="ns-terminal-body" id="terminal-body"></div>
        </div>

        <div class="ns-progress-container">
            <div class="ns-progress-bar" id="progress-bar"></div>
        </div>

        <div class="ns-stats-row" id="stats-row" style="display:none;">
            <div class="ns-stat-box">
                <div class="ns-stat-value" id="stat-total">0</div>
                <div class="ns-stat-label">کل</div>
            </div>
            <div class="ns-stat-box">
                <div class="ns-stat-value ns-stat-success" id="stat-published">0</div>
                <div class="ns-stat-label">منتشر شده</div>
            </div>
            <div class="ns-stat-box">
                <div class="ns-stat-value ns-stat-warning" id="stat-drafts">0</div>
                <div class="ns-stat-label">پیش‌نویس</div>
            </div>
            <div class="ns-stat-box">
                <div class="ns-stat-value ns-stat-danger" id="stat-failed">0</div>
                <div class="ns-stat-label">ناموفق</div>
            </div>
            <div class="ns-stat-box">
                <div class="ns-stat-value ns-stat-purple" id="stat-ai">0</div>
                <div class="ns-stat-label">با AI</div>
            </div>
        </div>
    </div>
</div>