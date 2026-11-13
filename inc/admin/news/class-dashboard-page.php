<?php
/**
 * DashboardPage — صفحه پیشخوان و آنالیز اخبار
 *
 * @package NextSafarAdminNews
 * @since 1.0.0
 */

namespace NextSafar\Admin\News;

use NextSafar\News\NewsSync;
use NextSafar\News\NewsFilter;
use NextSafar\News\AIRewriter;
use NextSafar\News\NewsBatchSync;

if (!defined('ABSPATH')) {
    exit;
}

class DashboardPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        // CSS و JS
        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/js/pages/news-admin.js',
            array('jquery', 'nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-news-admin', 'nextsafarNews', array(
            'ajaxUrl' => \NextSafar\Core::get_ajax_url(),
            'nonce'   => wp_create_nonce('nextsafar_news_admin'),
        ));

        // ✅ آمار کلی
        $stats = NewsSync::getOverviewStats();

        // آمار فیلتر
        $filterStats = self::getFilterStats();

        // وضعیت AI
        $aiStatus = AIRewriter::getStatus();

        // وضعیت Batch Sync
        $batchStatus = NewsBatchSync::getStatus();

        // وضعیت Cron
        $cronStatus = self::getCronStatus();

        // منابع
        $sources = NewsSync::getSourcesStats();

        // ✅ مستقیم view را render کن
        include NEXTSAFAR_PATH . 'views/admin/news/dashboard.php';
    }

    /**
     * دریافت آمار فیلتر
     */
    private static function getFilterStats(): array
    {
        global $wpdb;

        $stats = array(
            'total_published' => 0,
            'total_draft'     => 0,
            'total_deleted'   => 0,
            'ai_checks'       => 0,
        );

        $stats['total_published'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'travelnews' AND post_status = 'publish'"
        );

        $stats['total_draft'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'travelnews' AND post_status = 'draft'"
        );

        $table = $wpdb->prefix . 'ns_news_filter_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $stats['total_deleted'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE decision = 'delete'"
            );
            $stats['ai_checks'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE ai_checked = 1"
            );
        }

        return $stats;
    }

    /**
     * دریافت وضعیت Cron ها
     */
    private static function getCronStatus(): array
    {
        return array(
            'hourly' => wp_next_scheduled(NewsSync::HOURLY_HOOK)
                ? date_i18n('Y-m-d H:i', wp_next_scheduled(NewsSync::HOURLY_HOOK))
                : 'غیرفعال',
            'retry' => wp_next_scheduled(NewsSync::RETRY_HOOK)
                ? date_i18n('Y-m-d H:i', wp_next_scheduled(NewsSync::RETRY_HOOK))
                : 'غیرفعال',
            'auto_on' => get_option(NewsSync::OPT_AUTO_ENABLED, '1') === '1',
        );
    }
}