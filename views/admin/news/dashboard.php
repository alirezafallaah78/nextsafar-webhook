<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap nextsafar-wrap">
    <h1>📊 آنالیز و پرووایدرها</h1>
    <p class="description">آمار کلی سیستم اخبار، وضعیت منابع و عملکرد AI</p>

    <!-- ═══ آمار کلی ═══ -->
    <div class="ns-dashboard-grid">
        <div class="ns-stat-card ns-stat-primary">
            <div class="ns-stat-icon">📰</div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($stats['total_posts'] ?? 0); ?></div>
                <div class="ns-stat-label">کل اخبار</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-success">
            <div class="ns-stat-icon">📅</div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($stats['today_posts'] ?? 0); ?></div>
                <div class="ns-stat-label">امروز</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-info">
            <div class="ns-stat-icon">✅</div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($filterStats['total_published'] ?? 0); ?></div>
                <div class="ns-stat-label">منتشر شده</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-warning">
            <div class="ns-stat-icon">📝</div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($filterStats['total_draft'] ?? 0); ?></div>
                <div class="ns-stat-label">پیش‌نویس</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-danger">
            <div class="ns-stat-icon">🗑️</div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($filterStats['total_deleted'] ?? 0); ?></div>
                <div class="ns-stat-label">فیلتر شده</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-purple">
            <div class="ns-stat-icon">🔁</div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($stats['total_duplicates'] ?? 0); ?></div>
                <div class="ns-stat-label">تکراری حذف شده</div>
            </div>
        </div>
    </div>

    <!-- ═══ وضعیت AI ═══ -->
    <div class="ns-card">
        <h2>🤖 وضعیت هوش مصنوعی (Gemini)</h2>
        <div class="ns-ai-status">
            <?php if ($aiStatus['is_disabled'] ?? false): ?>
                <div class="ns-ai-disabled">
                    <strong>⚠️ AI در حالت Cooldown</strong>
                    <p><?= esc_html($aiStatus['cooldown_remaining'] ?? ''); ?></p>
                    <button type="button" class="button" id="reset-ai-btn">🔄 ریست وضعیت AI</button>
                </div>
            <?php else: ?>
                <div class="ns-ai-active">
                    <strong>✅ AI فعال</strong>
                    <p>
                        تعداد خطاهای متوالی: 
                        <strong><?= $aiStatus['fail_count'] ?? 0; ?></strong> 
                        از <?= $aiStatus['max_fails'] ?? 5; ?>
                    </p>
                    <p>
                        کل فراخوانی‌های AI: 
                        <strong><?= number_format($stats['ai_total_calls'] ?? 0); ?></strong>
                    </p>
                    <p>
                        کل توکن مصرفی: 
                        <strong><?= number_format($stats['ai_total_tokens'] ?? 0); ?></strong>
                    </p>
                    <p>
                        هزینه کل: 
                        <strong>$<?= number_format($stats['ai_total_cost_usd'] ?? 0, 4); ?></strong>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ وضعیت Cron ═══ -->
    <div class="ns-card">
        <h2>⏰ وضعیت Cron Jobs</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Cron</th>
                    <th>وضعیت</th>
                    <th>زمان اجرای بعدی</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Hourly Tick</strong></td>
                    <td>
                        <?php if ($cronStatus['auto_on'] ?? false): ?>
                            <span class="ns-badge ns-badge-success">✅ فعال</span>
                        <?php else: ?>
                            <span class="ns-badge ns-badge-danger">❌ غیرفعال</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc_html($cronStatus['hourly'] ?? 'نامشخص'); ?></td>
                </tr>
                <tr>
                    <td><strong>Retry Hook</strong></td>
                    <td>
                        <?php if ($cronStatus['auto_on'] ?? false): ?>
                            <span class="ns-badge ns-badge-success">✅ فعال</span>
                        <?php else: ?>
                            <span class="ns-badge ns-badge-danger">❌ غیرفعال</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc_html($cronStatus['retry'] ?? 'نامشخص'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ═══ وضعیت Batch Sync ═══ -->
    <?php if (($batchStatus['status'] ?? 'idle') === 'running'): ?>
    <div class="ns-card ns-card-warning">
        <h2>🔄 سینک در حال اجرا</h2>
        <p>
            پیشرفت: <strong><?= $batchStatus['progress_percent'] ?? 0; ?>%</strong> 
            (<?= $batchStatus['processed'] ?? 0; ?> از <?= $batchStatus['total'] ?? 0; ?>)
        </p>
        <p>
            منتشر شده: <strong><?= $batchStatus['published'] ?? 0; ?></strong> | 
            پیش‌نویس: <strong><?= $batchStatus['drafts'] ?? 0; ?></strong> | 
            ناموفق: <strong><?= $batchStatus['failed'] ?? 0; ?></strong>
        </p>
    </div>
    <?php endif; ?>

    <!-- ═══ وضعیت منابع ═══ -->
    <div class="ns-card">
        <h2>🌐 وضعیت منابع RSS و API</h2>
        <div class="ns-stats-row" style="margin-bottom: 20px;">
            <div class="ns-mini-stat">
                <div class="ns-mini-value"><?= number_format($stats['total_rss_fetched'] ?? 0); ?></div>
                <div class="ns-mini-label">کل اخبار RSS</div>
            </div>
            <div class="ns-mini-stat">
                <div class="ns-mini-value"><?= number_format($stats['total_api_fetched'] ?? 0); ?></div>
                <div class="ns-mini-label">کل اخبار API</div>
            </div>
            <div class="ns-mini-stat">
                <div class="ns-mini-value"><?= count($sources); ?></div>
                <div class="ns-mini-label">تعداد منابع</div>
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <button type="button" class="button" id="refresh-sources-btn">🔄 بارگذاری مجدد</button>
            <button type="button" class="button" id="reset-sources-btn">♻️ بازنشانی پیش‌فرض</button>
        </div>

        <div id="sources-table-container">
            <?php if (!empty($sources)): ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>نوع</th>
                            <th>گروه</th>
                            <th>دریافتی</th>
                            <th>تکراری</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr>
                                <td><strong><?= esc_html($source['name']); ?></strong></td>
                                <td><span class="ns-badge"><?= strtoupper(esc_html($source['type'])); ?></span></td>
                                <td><?= esc_html($source['group_name']); ?></td>
                                <td><?= number_format($source['total_fetched'] ?? 0); ?></td>
                                <td><?= number_format($source['total_duplicates'] ?? 0); ?></td>
                                <td>
                                    <?php if ($source['is_active']): ?>
                                        <span class="ns-badge ns-badge-success">✅ فعال</span>
                                    <?php else: ?>
                                        <span class="ns-badge ns-badge-danger">❌ غیرفعال</span>
                                    <?php endif; ?>
                                    <?php if (!empty($source['error_message'])): ?>
                                        <br><small style="color:#dc3232;"><?= esc_html(mb_substr($source['error_message'], 0, 100)); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>منبعی ثبت نشده</p>
            <?php endif; ?>
        </div>
    </div>
</div>