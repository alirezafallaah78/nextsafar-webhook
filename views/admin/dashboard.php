<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page_title = 'داشبورد';
require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
$current_user = wp_get_current_user();
$user_name    = $current_user->display_name;

$ns_cd = function ( $method ) {
    if ( class_exists( 'NextSafar_Dashboard_Content' ) ) {
        call_user_func( array( 'NextSafar_Dashboard_Content', $method ) );
    } else {
        echo '<div class="ns-empty">کلاس Dashboard Content لود نشده — خط require را به فایل اصلی پلاگین اضافه کنید.</div>';
    }
};
$ns_ins = function ( $method ) {
    if ( class_exists( 'NextSafar_Dashboard_Insights' ) ) {
        call_user_func( array( 'NextSafar_Dashboard_Insights', $method ) );
    } else {
        echo '<div class="ns-empty">کلاس Dashboard Insights لود نشده — خط require فاز ۲ را اضافه کنید.</div>';
    }
};
$ns_on = function ( $id ) {
    return class_exists( 'NextSafar_Dashboard_Content' )
        ? NextSafar_Dashboard_Content::is_enabled( $id )
        : true;
};
?>
<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <main class="nextsafar-content nextsafar-dashboard">
        <style>
            /* ═══════════ کامپوننت‌های محتوایی ═══════════ */
            .ns-content-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
            .kpi-card .kpi-icon{font-size:22px;line-height:1}
            .kpi-sub{font-size:10.5px;color:var(--ns-text-muted,#6b7280);margin-top:3px}
            .kpi-sub a{color:inherit;text-decoration:none}
            .kpi-sub a:hover{color:var(--ns-primary,#2563eb);text-decoration:underline}
            .ns-chips{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
            .ns-chip{flex:1;min-width:90px;background:#f8fafc;border:1px solid var(--ns-border,#e5e7eb);border-radius:10px;padding:10px 12px;text-align:center;text-decoration:none;display:block}
            .ns-chip b{display:block;font-size:20px;color:var(--ns-text,#111827)}
            .ns-chip span{font-size:11px;color:var(--ns-text-muted,#6b7280)}
            .ns-chip--warn b{color:#d97706}.ns-chip--danger b{color:#dc2626}.ns-chip--ok b{color:#059669}
            a.ns-chip:hover{border-color:var(--ns-primary,#2563eb)}
            .ns-list{display:flex;flex-direction:column}
            .ns-row{display:flex;align-items:center;gap:8px;padding:9px 2px;border-bottom:1px dashed var(--ns-border,#eef0f3);font-size:12.5px}
            .ns-row:last-child{border-bottom:0}
            .ns-row-title{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ns-text,#111827);text-decoration:none;font-weight:500}
            a.ns-row-title:hover{color:var(--ns-primary,#2563eb)}
            .ns-tag{font-size:10px;padding:2px 7px;border-radius:20px;background:#eef2ff;color:#4338ca;white-space:nowrap}
            .ns-tag--muted{background:#f3f4f6;color:#6b7280}
            .ns-tag--warn{background:#fef3c7;color:#92400e}
            .ns-tag--danger{background:#fee2e2;color:#b91c1c}
            .ns-tag--ok{background:#d1fae5;color:#065f46}
            .ns-time{font-size:10.5px;color:var(--ns-text-muted,#9ca3af);white-space:nowrap}
            .ns-progress{height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;margin:6px 0 8px}
            .ns-progress i{display:block;height:100%;background:linear-gradient(90deg,#34d399,#059669);border-radius:99px}
            .ns-widget-foot{margin-top:10px;padding-top:10px;border-top:1px solid var(--ns-border,#eef0f3);font-size:11.5px;color:var(--ns-text-muted,#6b7280)}
            .ns-widget-foot a{color:var(--ns-primary,#2563eb);text-decoration:none}
            .ns-empty{padding:18px 8px;text-align:center;color:var(--ns-text-muted,#6b7280);font-size:12.5px}
            .ns-gsc-tabs{display:flex;gap:8px;margin-bottom:12px}
            .ns-gsc-tab{flex:1;border:1px solid var(--ns-border,#e5e7eb);background:#f8fafc;border-radius:8px;padding:7px 10px;font-size:12px;cursor:pointer;color:var(--ns-text-muted,#6b7280);font-family:inherit;transition:all .15s ease}
            .ns-gsc-tab.active{background:var(--ns-primary,#2563eb);color:#fff;border-color:var(--ns-primary,#2563eb)}

            /* ═══════════ پایهٔ ریسپانسیو ═══════════ */
            .nextsafar-dashboard{overflow-x:clip;-webkit-tap-highlight-color:transparent;padding-bottom:calc(24px + env(safe-area-inset-bottom,0px))}
            .nextsafar-dashboard .dashboard-grid>*,
            .nextsafar-dashboard .charts-row>*,
            .nextsafar-dashboard .info-cards-grid>*,
            .nextsafar-dashboard .kpi-cards-grid>*,
            .nextsafar-dashboard .ns-content-row>*{min-width:0;max-width:100%}
            .nextsafar-dashboard .widget-box{min-width:0}
            .nextsafar-dashboard .widget-header{flex-wrap:wrap;row-gap:8px}
            .nextsafar-dashboard .widget-header h3{min-width:0}
            .nextsafar-dashboard [id^="ns-"]{scroll-margin-top:86px}
            .nextsafar-dashboard a:focus-visible,.nextsafar-dashboard button:focus-visible{outline:2px solid var(--ns-primary,#2563eb);outline-offset:2px;border-radius:8px}
            .nextsafar-dashboard .gsc-chart-wrapper{position:relative;height:280px}
            .nextsafar-dashboard #gsc-chart-container{min-height:120px}

            /* اسکرول‌بارهای سفارشی */
            body:has(.nextsafar-dashboard){scrollbar-width:thin;scrollbar-color:rgba(37,99,235,.35) transparent}
            body:has(.nextsafar-dashboard)::-webkit-scrollbar{width:10px;height:10px}
            body:has(.nextsafar-dashboard)::-webkit-scrollbar-track{background:#f1f5f9}
            body:has(.nextsafar-dashboard)::-webkit-scrollbar-thumb{background:linear-gradient(180deg,rgba(37,99,235,.4),rgba(124,58,237,.4));border-radius:99px;border:2px solid #f1f5f9}
            body:has(.nextsafar-dashboard)::-webkit-scrollbar-thumb:hover{background:linear-gradient(180deg,rgba(37,99,235,.65),rgba(124,58,237,.65))}
            .ns-gsc-tabs,.gsc-range-tabs,.ns-mob-sidebar{scrollbar-width:thin;scrollbar-color:rgba(37,99,235,.35) transparent}
            .ns-gsc-tabs::-webkit-scrollbar,.gsc-range-tabs::-webkit-scrollbar,.ns-mob-sidebar::-webkit-scrollbar{height:6px;width:7px}
            .ns-gsc-tabs::-webkit-scrollbar-track,.gsc-range-tabs::-webkit-scrollbar-track,.ns-mob-sidebar::-webkit-scrollbar-track{background:transparent}
            .ns-gsc-tabs::-webkit-scrollbar-thumb,.gsc-range-tabs::-webkit-scrollbar-thumb,.ns-mob-sidebar::-webkit-scrollbar-thumb{background:rgba(37,99,235,.3);border-radius:99px}
            .ns-gsc-tabs::-webkit-scrollbar-thumb:hover,.gsc-range-tabs::-webkit-scrollbar-thumb:hover,.ns-mob-sidebar::-webkit-scrollbar-thumb:hover{background:rgba(37,99,235,.55)}

            /* ═══════════ ≤1200: ستون کناری زیر.main ═══════════ */
            @media(max-width:1200px){
                .nextsafar-dashboard .dashboard-grid{display:grid!important;grid-template-columns:1fr!important;gap:20px}
                .nextsafar-dashboard .dashboard-main-col,.nextsafar-dashboard .dashboard-side-col{width:auto!important;max-width:100%!important;flex:auto!important}
                .nextsafar-dashboard .dashboard-side-col{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:20px;align-items:start}
            }
            /* ═══════════ ≤1100: نمودارها و ردیف‌ها تک‌ستونه ═══════════ */
            @media(max-width:1100px){
                .nextsafar-dashboard .charts-row{display:grid!important;grid-template-columns:1fr!important;gap:20px}
                .nextsafar-dashboard .ns-content-row{grid-template-columns:1fr}
                .nextsafar-dashboard .info-cards-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px}
            }
            /* ═══════════ ≤900: KPI دوستونه + سایدبار موبایل ═══════════ */
            @media(max-width:900px){
                .nextsafar-dashboard .kpi-cards-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:12px}
                .nextsafar-main{margin-right:0!important;margin-left:0!important}
                .ns-mob-sidebar{position:fixed!important;top:0!important;bottom:0!important;right:0!important;left:auto!important;width:min(280px,86vw)!important;height:100dvh!important;z-index:100000!important;transform:translateX(105%);transition:transform .28s cubic-bezier(.22,.8,.36,1);overflow-y:auto!important;overscroll-behavior:contain;box-shadow:none;margin:0!important}
                .ns-mob-sidebar.ns-open{transform:translateX(0);box-shadow:-18px 0 50px rgba(15,23,42,.28)}
                .ns-mob-scrim{position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.45);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .25s ease}
                .ns-mob-scrim.ns-open{opacity:1;pointer-events:auto}
                .ns-mob-burger{display:inline-flex;flex-direction:column;justify-content:center;align-items:center;gap:4px;width:40px;height:40px;flex:0 0 auto;border:1px solid var(--ns-border,#e5e7eb);background:#fff;border-radius:12px;cursor:pointer;padding:0;box-shadow:0 2px 8px rgba(15,23,42,.08);transition:all .18s ease}
                .ns-mob-burger:hover{border-color:var(--ns-primary,#2563eb)}
                .ns-mob-burger span{display:block;width:17px;height:2px;border-radius:2px;background:var(--ns-text,#111827);transition:all .22s ease}
                .ns-mob-burger.ns-open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
                .ns-mob-burger.ns-open span:nth-child(2){opacity:0}
                .ns-mob-burger.ns-open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
                .ns-mob-burger--fixed{position:fixed;top:calc(11px + env(safe-area-inset-top,0px));right:calc(12px + env(safe-area-inset-right,0px));z-index:100001}
                html.ns-mob-lock{overflow:hidden!important}
            }
            @media(min-width:901px){
                .ns-mob-burger,.ns-mob-scrim{display:none!important}
            }
            /* ═══════════ ≤640: موبایل ═══════════ */
            @media(max-width:640px){
                .nextsafar-dashboard .info-cards-grid{grid-template-columns:1fr!important}
                .nextsafar-dashboard .dashboard-side-col{grid-template-columns:1fr!important}
                .nextsafar-dashboard .widget-content{padding:14px!important}
                .nextsafar-dashboard .widget-header{padding:12px 14px!important}
                .nextsafar-dashboard .widget-header h3{font-size:13.5px}
                .nextsafar-dashboard .gsc-chart-wrapper{height:220px}
                .nextsafar-dashboard .gsc-stats-container,.nextsafar-dashboard .chart-stats-row{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px}
                .nextsafar-dashboard .gsc-stat-value,.nextsafar-dashboard .stat-value{font-size:17px}
                .nextsafar-dashboard .gsc-stat-card,.nextsafar-dashboard .chart-stat{padding:10px}
                .nextsafar-dashboard .yoast-stats-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px}
                .nextsafar-dashboard .weather-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px}
                .nextsafar-dashboard .weather-item{padding:9px 10px}
                .nextsafar-dashboard .weather-temp{font-size:15px}
                .nextsafar-dashboard .widget-search-console .widget-header{flex-direction:column;align-items:stretch}
                .nextsafar-dashboard .gsc-range-tabs{display:flex;overflow-x:auto;max-width:100%;padding-bottom:4px;-webkit-overflow-scrolling:touch}
                .nextsafar-dashboard .gsc-range-tabs .widget-tab{flex:0 0 auto;white-space:nowrap}
                .ns-gsc-tabs{overflow-x:auto;padding-bottom:4px;-webkit-overflow-scrolling:touch}
                .ns-gsc-tab{flex:0 0 auto;white-space:nowrap;padding:8px 12px}
                .ns-row{flex-wrap:wrap;row-gap:5px;padding:10px 2px}
                .ns-row-title{flex:1 1 100%;order:-1;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-height:1.6}
                .ns-chip{min-width:82px;padding:9px 10px}
                .ns-chip b{font-size:18px}
                .kpi-card{padding:13px!important}
                .kpi-card .kpi-icon{font-size:19px}
                .kpi-value{font-size:21px!important}
                .kpi-label{font-size:11.5px!important}
                .kpi-sub{font-size:9.5px}
                .nextsafar-dashboard .widget-list-item{padding:10px 8px}
                .nextsafar-dashboard .widget-list-meta{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%}
                .nextsafar-dashboard .widget-list-content{min-width:0}
            }
            /* ═══════════ ≤380: موبایل کوچک ═══════════ */
            @media(max-width:380px){
                .nextsafar-dashboard .kpi-cards-grid{gap:9px}
                .kpi-card{padding:11px!important}
                .kpi-value{font-size:19px!important}
                .ns-chip{min-width:74px;padding:8px}
                .ns-chip b{font-size:16.5px}
                .ns-chip span{font-size:10px}
                .ns-cal-title{min-width:0!important;font-size:12.5px!important}
                .ns-cal-navbtn{width:27px!important;height:27px!important}
                .ns-cal-mode{padding:5px 9px!important;font-size:10.5px!important}
                .ns-cal-day{font-size:11px!important;border-radius:8px!important}
                .ns-cal-today-btn{padding:6px 10px;font-size:10.5px}
                .ns-cal-today-num{font-size:11px}
                .ns-gsc-tab{font-size:11px;padding:7px 10px}
                .nextsafar-dashboard .weather-grid{grid-template-columns:1fr!important}
            }
            @media(prefers-reduced-motion:reduce){
                .nextsafar-dashboard *,.ns-mob-sidebar,.ns-mob-scrim,.ns-mob-burger{animation:none!important;transition:none!important}
            }
        </style>

        <div class="dashboard-grid">
            <div class="dashboard-main-col">
                <?php if ( $ns_on( 'kpis' ) ) : ?>
                    <?php $ns_cd( 'render_kpis' ); ?>
                <?php endif; ?>

                <div class="charts-row">
                    <?php if ( $ns_on( 'gsc_chart' ) ) : ?>
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
                                    <div class="chart-stat"><div class="stat-label">🖱️ کل کلیک‌ها</div><div class="stat-value" id="gsc-clicks">—</div></div>
                                    <div class="chart-stat"><div class="stat-label">👁️ کل نمایش‌ها</div><div class="stat-value" id="gsc-impressions">—</div></div>
                                    <div class="chart-stat"><div class="stat-label">📊 میانگین CTR</div><div class="stat-value" id="gsc-ctr">—</div></div>
                                    <div class="chart-stat"><div class="stat-label">میانگین رتبه</div><div class="stat-value" id="gsc-position">—</div></div>
                                </div>
                                <canvas id="searchConsoleChart" height="110"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( $ns_on( 'yoast_seo' ) ) : ?>
                    <div class="widget-box widget-yoast">
                        <div class="widget-header">
                            <h3>وضعیت SEO سایت</h3>
                            <span class="widget-badge">Yoast SEO</span>
                        </div>
                        <div class="widget-content" id="yoast-widget">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div><span>در حال بارگذاری...</span></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="ns-content-row">
                    <?php if ( $ns_on( 'pipeline' ) ) : ?>
                    <div class="widget-box" id="ns-pipeline">
                        <div class="widget-header"><h3>پایپلاین محتوا</h3><span class="widget-badge">تولید</span></div>
                        <div class="widget-content"><?php $ns_cd( 'render_pipeline' ); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ( $ns_on( 'seo_tasks' ) ) : ?>
                    <div class="widget-box" id="ns-seo-tasks">
                        <div class="widget-header"><h3>کارهای سئو</h3><span class="widget-badge">Yoast</span></div>
                        <div class="widget-content"><?php $ns_cd( 'render_seo_tasks' ); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="ns-content-row">
                    <?php if ( $ns_on( 'gsc_opportunities' ) ) : ?>
                    <div class="widget-box" id="ns-gsc-opportunities">
                        <div class="widget-header"><h3>فرصت‌های سئو</h3><span class="widget-badge">Search Console</span></div>
                        <div class="widget-content">
                            <div class="ns-gsc-tabs">
                                <button type="button" class="ns-gsc-tab active" data-tab="striking">نزدیک صفحه اول</button>
                                <button type="button" class="ns-gsc-tab" data-tab="lowctr">CTR پایین</button>
                                <button type="button" class="ns-gsc-tab" data-tab="top">محتوای برتر</button>
                            </div>
                            <div id="ns-gsc-results">
                                <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( $ns_on( 'coverage' ) ) : ?>
                    <div class="widget-box" id="ns-coverage">
                        <div class="widget-header"><h3>پوشش مقاصد</h3><span class="widget-badge">محتوا</span></div>
                        <div class="widget-content"><?php $ns_ins( 'render_coverage' ); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="ns-content-row">
                    <?php if ( $ns_on( 'orphaned' ) ) : ?>
                    <div class="widget-box" id="ns-orphaned">
                        <div class="widget-header"><h3>محتوای یتیم</h3><span class="widget-badge">لینک داخلی</span></div>
                        <div class="widget-content"><?php $ns_ins( 'render_orphaned' ); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ( $ns_on( 'media' ) ) : ?>
                    <div class="widget-box" id="ns-media">
                        <div class="widget-header"><h3>سلامت رسانه</h3><span class="widget-badge">سئو / سرعت</span></div>
                        <div class="widget-content"><?php $ns_ins( 'render_media' ); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-cards-grid">
                    <?php if ( $ns_on( 'comments' ) ) : ?>
                    <div class="widget-box widget-comments">
                        <div class="widget-header"><h3>جدیدترین کامنت‌ها</h3></div>
                        <div class="widget-content" id="recent-comments">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( $ns_on( 'news' ) ) : ?>
                    <div class="widget-box" id="ns-news">
                        <div class="widget-header"><h3>صف خبرها</h3><span class="widget-badge">News</span></div>
                        <div class="widget-content"><?php $ns_cd( 'render_news' ); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ( $ns_on( 'geo' ) ) : ?>
                    <div class="widget-box" id="ns-geo">
                        <div class="widget-header"><h3>کامل بودن داده هتل‌ها</h3><span class="widget-badge">Geo</span></div>
                        <div class="widget-content"><?php $ns_cd( 'render_geo' ); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ( $ns_on( 'weather' ) ) : ?>
                    <div class="widget-box widget-weather">
                        <div class="widget-header"><h3>آب و هوای مقاصد</h3></div>
                        <div class="widget-content" id="weather-widget">
                            <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-side-col">
                <?php if ( $ns_on( 'calendar' ) ) : ?>
                <div class="widget-box widget-calendar">
                    <div class="widget-content" id="calendar-widget">
                        <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ( $ns_on( 'scheduled' ) ) : ?>
                <div class="widget-box" id="ns-scheduled">
                    <div class="widget-header"><h3>انتشارهای زمان‌بندی‌شده</h3></div>
                    <div class="widget-content"><?php $ns_cd( 'render_scheduled' ); ?></div>
                </div>
                <?php endif; ?>
                <?php if ( $ns_on( 'stale' ) ) : ?>
                <div class="widget-box" id="ns-stale">
                    <div class="widget-header"><h3>محتوای کهنه</h3></div>
                    <div class="widget-content"><?php $ns_cd( 'render_stale' ); ?></div>
                </div>
                <?php endif; ?>
                <?php if ( $ns_on( 'admin_logins' ) ) : ?>
                <div class="widget-box widget-admin-logins">
                    <div class="widget-header"><h3>آخرین ورود ادمین‌ها</h3></div>
                    <div class="widget-content" id="admin-logins">
                        <div class="widget-loading"><div class="nextsafar-spinner"></div></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
(function () {
    "use strict";
    var mq = window.matchMedia("(max-width: 900px)");
    var sidebar = null, burger = null, scrim = null, taken = false;

    function findSidebar() {
        return document.querySelector("#nextsafar-injected-sidebar") ||
            document.querySelector(".nextsafar-sidebar") ||
            document.querySelector("#nextsafar-sidebar") ||
            document.querySelector("#wpbody-content aside") ||
            document.querySelector("body aside");
    }
    function findTopbar() {
        return document.querySelector("#nextsafar-injected-topbar") ||
            document.querySelector(".nextsafar-topbar") ||
            document.querySelector(".nextsafar-main header") ||
            document.querySelector("header[class*='topbar']");
    }
    function alreadyHandled(el) {
        var cs = window.getComputedStyle(el);
        if (cs.display === "none" || cs.visibility === "hidden") return true;
        var t = cs.transform;
        if (t && t !== "none") {
            var m = t.match(/matrix\(([^)]+)\)/);
            if (m) {
                var parts = m[1].split(",").map(parseFloat);
                if (Math.abs(parts[4]) > el.offsetWidth * 0.5) return true;
            }
        }
        var r = el.getBoundingClientRect();
        if (r.left >= window.innerWidth - 2 || r.right <= 2) return true;
        return false;
    }
    function setOpen(open) {
        if (!taken) return;
        sidebar.classList.toggle("ns-open", open);
        scrim.classList.toggle("ns-open", open);
        burger.classList.toggle("ns-open", open);
        burger.setAttribute("aria-expanded", open ? "true" : "false");
        document.documentElement.classList.toggle("ns-mob-lock", open);
    }
    function teardown() {
        setOpen(false);
        if (sidebar) sidebar.classList.remove("ns-mob-sidebar", "ns-open");
        if (burger) burger.remove();
        if (scrim) scrim.remove();
        burger = null; scrim = null; taken = false;
        document.documentElement.classList.remove("ns-mob-lock");
    }
    function setup() {
        if (!mq.matches) { teardown(); return; }
        sidebar = findSidebar();
        if (!sidebar || taken) return;
        if (alreadyHandled(sidebar)) return; /* پلاگین خودش سایدبار موبایل را مدیریت می‌کند */

        taken = true;
        sidebar.classList.add("ns-mob-sidebar");

        scrim = document.createElement("div");
        scrim.className = "ns-mob-scrim";
        scrim.addEventListener("click", function () { setOpen(false); });
        document.body.appendChild(scrim);

        burger = document.createElement("button");
        burger.type = "button";
        burger.className = "ns-mob-burger";
        burger.setAttribute("aria-label", "باز و بسته کردن منو");
        burger.setAttribute("aria-expanded", "false");
        burger.innerHTML = "<span></span><span></span><span></span>";
        burger.addEventListener("click", function () {
            setOpen(!sidebar.classList.contains("ns-open"));
        });
        var topbar = findTopbar();
        if (topbar) {
            topbar.insertBefore(burger, topbar.firstChild);
        } else {
            burger.classList.add("ns-mob-burger--fixed");
            document.body.appendChild(burger);
        }

        sidebar.addEventListener("click", function (e) {
            if (e.target.closest("a")) setOpen(false);
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") setOpen(false);
        });
    }
    function boot() {
        try { setup(); } catch (e) {}
        var onChange = function () { try { setup(); } catch (e) {} };
        if (mq.addEventListener) mq.addEventListener("change", onChange);
        else if (mq.addListener) mq.addListener(onChange);
        window.addEventListener("resize", function () {
            if (taken && !mq.matches) teardown();
        });
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
    else boot();
})();
</script>

<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';