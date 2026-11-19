<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap nextsafar-wrap">
    <div class="nextsafar-page-header">
        <div>
            <h1>آنالیز و پرووایدرها</h1>
            <p>آمار کلی سیستم اخبار، وضعیت منابع و عملکرد هوش مصنوعی</p>
        </div>
    </div>

    <!-- ═══ آمار کلی ═══ -->
    <div class="ns-dashboard-grid">

        <div class="ns-stat-card">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/>
                    <path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>
                </svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($stats['total_posts'] ?? 0); ?></div>
                <div class="ns-stat-label">کل اخبار</div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 2v4"/><path d="M16 2v4"/>
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <path d="M3 10h18"/>
                </svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($stats['today_posts'] ?? 0); ?></div>
                <div class="ns-stat-label">امروز</div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21.801 10A10 10 0 1 1 17 3.335"/>
                    <path d="m9 11 3 3L22 4"/>
                </svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($filterStats['total_published'] ?? 0); ?></div>
                <div class="ns-stat-label">منتشر شده</div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                    <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                    <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                </svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($filterStats['total_draft'] ?? 0); ?></div>
                <div class="ns-stat-label">پیش‌نویس</div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 6h18"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                </svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($filterStats['total_deleted'] ?? 0); ?></div>
                <div class="ns-stat-label">فیلتر شده</div>
            </div>
        </div>

        <div class="ns-stat-card">
            <div class="ns-stat-icon">
                <svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m17 2 4 4-4 4"/>
                    <path d="M3 11v-1a4 4 0 0 1 4-4h14"/>
                    <path d="m7 22-4-4 4-4"/>
                    <path d="M21 13v1a4 4 0 0 1-4 4H3"/>
                </svg>
            </div>
            <div class="ns-stat-content">
                <div class="ns-stat-value"><?php echo number_format($stats['total_duplicates'] ?? 0); ?></div>
                <div class="ns-stat-label">تکراری حذف شده</div>
            </div>
        </div>
    </div>

    <!-- ═══ وضعیت هوش مصنوعی ═══ -->
    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header">
            <h2>وضعیت هوش مصنوعی (Gemini)</h2>
        </div>
        <div class="nextsafar-panel-body">
            <?php if ($aiStatus['is_disabled'] ?? false) : ?>
                <div class="ns-ai-disabled">
                    <strong>هوش مصنوعی در حالت انتظار (Cooldown) قرار دارد</strong>
                    <p><?php echo esc_html($aiStatus['cooldown_remaining'] ?? ''); ?></p>
                    <button type="button" class="nextsafar-secondary-button" id="reset-ai-btn">ریست وضعیت</button>
                </div>
            <?php else : ?>
                <div class="ns-stats-row">
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value"><?php echo (int) ($aiStatus['fail_count'] ?? 0); ?></div>
                        <div class="ns-stat-box-label">خطاهای متوالی</div>
                    </div>
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value"><?php echo (int) ($aiStatus['max_fails'] ?? 5); ?></div>
                        <div class="ns-stat-box-label">حداکثر مجاز</div>
                    </div>
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value"><?php echo number_format($stats['ai_total_calls'] ?? 0); ?></div>
                        <div class="ns-stat-box-label">کل فراخوانی‌ها</div>
                    </div>
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value"><?php echo number_format($stats['ai_total_tokens'] ?? 0); ?></div>
                        <div class="ns-stat-box-label">توکن مصرفی</div>
                    </div>
                    <div class="ns-stat-box">
                        <div class="ns-stat-box-value">$<?php echo number_format($stats['ai_total_cost_usd'] ?? 0, 4); ?></div>
                        <div class="ns-stat-box-label">هزینه کل</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ وضعیت منابع ═══ -->
    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header">
            <h2>وضعیت منابع RSS و API</h2>
        </div>
        <div class="nextsafar-panel-body">
            <div class="ns-stats-row" style="margin-bottom: 20px;">
                <div class="ns-stat-box">
                    <div class="ns-stat-box-value"><?php echo number_format($stats['total_rss_fetched'] ?? 0); ?></div>
                    <div class="ns-stat-box-label">کل اخبار RSS</div>
                </div>
                <div class="ns-stat-box">
                    <div class="ns-stat-box-value"><?php echo number_format($stats['total_api_fetched'] ?? 0); ?></div>
                    <div class="ns-stat-box-label">کل اخبار API</div>
                </div>
                <div class="ns-stat-box">
                    <div class="ns-stat-box-value"><?php echo count($sources); ?></div>
                    <div class="ns-stat-box-label">تعداد منابع</div>
                </div>
            </div>

            <div style="margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="nextsafar-secondary-button" id="refresh-sources-btn">بارگذاری مجدد</button>
                <button type="button" class="nextsafar-secondary-button" id="reset-sources-btn">بازنشانی پیش‌فرض</button>
            </div>

            <div id="sources-table-container">
                <?php if (!empty($sources)) : ?>
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
                            <?php foreach ($sources as $source) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($source['name']); ?></strong></td>
                                    <td><span class="nextsafar-status"><?php echo esc_html(strtoupper($source['type'])); ?></span></td>
                                    <td><?php echo esc_html($source['group_name']); ?></td>
                                    <td><?php echo number_format($source['total_fetched'] ?? 0); ?></td>
                                    <td><?php echo number_format($source['total_duplicates'] ?? 0); ?></td>
                                    <td>
                                        <?php if ($source['is_active']) : ?>
                                            <span class="nextsafar-status nextsafar-status-publish">فعال</span>
                                        <?php else : ?>
                                            <span class="nextsafar-status nextsafar-status-trash">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="description">منبعی ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>