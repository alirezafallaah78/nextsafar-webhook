<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page_title = 'داشبورد';
require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
$current_user = wp_get_current_user();
$user_name    = $current_user->display_name;
$kpi_stats = NextSafar_Helpers::get_kpi_stats();
?>
<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <main class="nextsafar-content nextsafar-dashboard">
        <!-- Welcome Header -->
        <div class="nextsafar-dashboard-welcome">
            <div class="welcome-text">
                <h1>سلام، <?php echo esc_html( $user_name ); ?></h1>
                <p class="welcome-date">
                    <?php NextSafar_Icons::display( 'calendar-days', 20 ); ?>
                    <?php echo esc_html( NextSafar_Helpers::get_persian_date() ); ?>
                </p>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dashboard-grid">
            <!-- Main Column (Right) -->
            <div class="dashboard-main-col">
                <!-- KPI Cards -->
                <div class="kpi-cards-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon"><?php NextSafar_Icons::display( 'passport' ); ?></div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo number_format_i18n( $kpi_stats['visa_bookings'] ); ?></div>
                            <div class="kpi-label">ویزاهای رزرو شده</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><?php NextSafar_Icons::display( 'visa' ); ?></div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo number_format_i18n( $kpi_stats['visas'] ); ?></div>
                            <div class="kpi-label">ویزاهای فعال</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><?php NextSafar_Icons::display( 'users' ); ?></div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo number_format_i18n( $kpi_stats['subscribers'] ); ?></div>
                            <div class="kpi-label">کاربران سایت</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon"><?php NextSafar_Icons::display( 'backpack' ); ?></div>
                        <div class="kpi-content">
                            <div class="kpi-value"><?php echo number_format_i18n( $kpi_stats['tours'] ); ?></div>
                            <div class="kpi-label">تورهای فعال</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row: Search Console + Yoast -->
                <div class="charts-row">
                    <div class="widget-box widget-search-console">
                        <div class="widget-header">
                            <h3>عملکرد در نتایج جستجو</h3>
                            <div class="widget-tabs gsc-range-tabs">
                                <button class="widget-tab gsc-range-tab" data-range="24h">۲۴ ساعت</button>
                                <button class="widget-tab gsc-range-tab" data-range="7d">۷ روز</button>
                                <button class="widget-tab gsc-range-tab active" data-range="28d">۲۸ روز</button>
                                <button class="widget-tab gsc-range-tab" data-range="3m">۳ ماه</button>
                            </div>
                        </div>
                        <div class="widget-content">
                            <div id="gsc-chart-container">
                                <div class="chart-stats-row">
                                    <div class="chart-stat">
                                        <div class="stat-label">🖱️ کل کلیک‌ها</div>
                                        <div class="stat-value" id="gsc-clicks">—</div>
                                    </div>
                                    <div class="chart-stat">
                                        <div class="stat-label">👁️ کل نمایش‌ها</div>
                                        <div class="stat-value" id="gsc-impressions">—</div>
                                    </div>
                                    <div class="chart-stat">
                                        <div class="stat-label">📊 میانگین CTR</div>
                                        <div class="stat-value" id="gsc-ctr">—</div>
                                    </div>
                                    <div class="chart-stat">
                                        <div class="stat-label">میانگین رتبه</div>
                                        <div class="stat-value" id="gsc-position">—</div>
                                    </div>
                                </div>
                                <canvas id="searchConsoleChart" height="110"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Yoast Widget -->
                    <div class="widget-box widget-yoast">
                        <div class="widget-header">
                            <h3>وضعیت SEO سایت</h3>
                            <span class="widget-badge">Yoast SEO</span>
                        </div>
                        <div class="widget-content" id="yoast-widget">
                            <div class="widget-loading">
                                <div class="nextsafar-spinner"></div>
                                <span>در حال بارگذاری...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Cards Row -->
                <div class="info-cards-grid">
                    <div class="widget-box widget-comments">
                        <div class="widget-header"><h3>جدیدترین کامنت‌ها</h3></div>
                        <div class="widget-content" id="recent-comments">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                        </div>
                    </div>
                    <div class="widget-box widget-expiring-tours">
                        <div class="widget-header"><h3>تورهای منقضی (۷ روز)</h3></div>
                        <div class="widget-content" id="expiring-tours">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                        </div>
                    </div>
                    <div class="widget-box widget-recent-bookings">
                        <div class="widget-header"><h3>آخرین رزروهای ویزا</h3></div>
                        <div class="widget-content" id="recent-bookings">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                        </div>
                    </div>
                    <div class="widget-box widget-weather">
                        <div class="widget-header"><h3>آب و هوای مقاصد</h3></div>
                        <div class="widget-content" id="weather-widget">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column (Left) -->
            <div class="dashboard-side-col">
                <div class="widget-box widget-calendar">
                    <div class="widget-header"><h3>تقویم</h3></div>
                    <div class="widget-content" id="calendar-widget">
                        <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                    </div>
                </div>
                <div class="widget-box widget-upcoming">
                    <div class="widget-header"><h3>تورهای هفته آینده</h3></div>
                    <div class="widget-content" id="upcoming-tours">
                        <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                    </div>
                </div>
                <div class="widget-box widget-admin-logins">
                    <div class="widget-header"><h3>آخرین ورود ادمین‌ها</h3></div>
                    <div class="widget-content" id="admin-logins">
                        <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';
?>