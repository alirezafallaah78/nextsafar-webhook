<?php
/**
 * FilterPage — مدیریت فیلترها و کلمات کلیدی
 *
 * قابلیت‌ها:
 * - افزودن/حذف کلمات کلیدی مثبت و منفی
 * - مشاهده تاریخچه فیلتر (اخبار فیلتر شده)
 * - تنظیم threshold ها
 *
 * @package NextSafar\Admin\News
 * @since 1.0.0
 */

namespace NextSafar\Admin\News;

use NextSafar\News\NewsFilter;

if (!defined('ABSPATH')) {
    exit;
}

class FilterPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            ['nextsafar-components'],
            NEXTSAFAR_VERSION
        );
        wp_enqueue_script(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/js/pages/news-admin.js',
            ['jquery', 'nextsafar-utils'],
            NEXTSAFAR_VERSION,
            true
        );
        wp_localize_script('nextsafar-news-admin', 'nextsafarNews', [
            'ajaxUrl' => \NextSafar\Core::get_ajax_url(),
            'nonce'   => wp_create_nonce('nextsafar_news_admin'),
        ]);

        \NextSafar\Admin\Layout::render(function() {
            global $wpdb;
            
            // آمار فیلتر
            $stats = [
                'published' => 0,
                'draft'     => 0,
                'deleted'   => 0,
                'ai_checked' => 0,
            ];
            
            $stats['published'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'travelnews' AND post_status = 'publish'"
            );
            $stats['draft'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'travelnews' AND post_status = 'draft'"
            );
            
            $table = $wpdb->prefix . 'ns_news_filter_log';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $stats['deleted'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$table} WHERE decision = 'delete'"
                );
                $stats['ai_checked'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$table} WHERE ai_checked = 1"
                );
            }

            // فیلترهای ذخیره شده
            $saved_filters = get_option('nextsafar_news_filters', []);

            include NEXTSAFAR_PATH . 'views/admin/news/filter.php';
        }, 'فیلترهای اخبار');
    }
}