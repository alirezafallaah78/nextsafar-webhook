<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <!-- ═══ آمار کلی ═══ -->
    <div class="ns-dashboard-grid">
        <div class="ns-stat-card ns-stat-primary">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8V6Z"/></svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($stats['total_posts'] ?? 0); ?></div>
                <div class="ns-stat-label">کل اخبار</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-success">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($stats['today_posts'] ?? 0); ?></div>
                <div class="ns-stat-label">امروز</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-info">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($filterStats['total_published'] ?? 0); ?></div>
                <div class="ns-stat-label">منتشر شده</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-warning">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($filterStats['total_draft'] ?? 0); ?></div>
                <div class="ns-stat-label">پیش‌نویس</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-danger">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($filterStats['total_deleted'] ?? 0); ?></div>
                <div class="ns-stat-label">فیلتر شده</div>
            </div>
        </div>

        <div class="ns-stat-card ns-stat-purple">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?= number_format($stats['total_duplicates'] ?? 0); ?></div>
                <div class="ns-stat-label">تکراری حذف شده</div>
            </div>
        </div>
    </div>

    <!-- ═══ وضعیت AI ═══ -->
    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header">
            <h2>وضعیت هوش مصنوعی (Gemini)</h2>
        </div>
        <div class="nextsafar-panel-body">
            <?php if ($aiStatus['is_disabled'] ?? false): ?>
                <div class="ns-ai-disabled">
                    <strong>AI در حالت Cooldown</strong>
                    <p><?= esc_html($aiStatus['cooldown_remaining'] ?? ''); ?></p>
                    <button type="button" class="nextsafar-secondary-button" id="reset-ai-btn">
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        ریست وضعیت AI
                    </button>
                </div>
            <?php else: ?>
                <div class="ns-ai-active">
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

    <!-- ═══ وضعیت منابع ═══ -->
    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header">
            <h2>وضعیت منابع RSS و API</h2>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="nextsafar-secondary-button" id="refresh-sources-btn">
                    <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    بارگذاری مجدد
                </button>
                <button type="button" class="nextsafar-danger-button" id="reset-sources-btn">
                    <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                    بازنشانی
                </button>
            </div>
        </div>
        <div class="nextsafar-panel-body">
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

            <div id="sources-table-container">
                <?php if (!empty($sources)): ?>
                    <table class="nextsafar-table">
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
                                    <td><span class="ns-badge ns-badge-neutral"><?= strtoupper(esc_html($source['type'])); ?></span></td>
                                    <td><?= esc_html($source['group_name']); ?></td>
                                    <td><?= number_format($source['total_fetched'] ?? 0); ?></td>
                                    <td><?= number_format($source['total_duplicates'] ?? 0); ?></td>
                                    <td>
                                        <?php if ($source['is_active']): ?>
                                            <span class="ns-badge ns-badge-success">فعال</span>
                                        <?php else: ?>
                                            <span class="ns-badge ns-badge-danger">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="ns-text-muted">منبعی ثبت نشده</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>