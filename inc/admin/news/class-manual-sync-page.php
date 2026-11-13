<?php
/**
 * ManualSyncPage — صفحه تولید دستی اخبار با Terminal UI
 *
 * شبیه صفحه Post Generator اما مخصوص اخبار
 *
 * @package NextSafar\Admin\News
 * @since 1.0.0
 */

namespace NextSafar\Admin\News;

use NextSafar\News\NewsSync;

if (!defined('ABSPATH')) {
    exit;
}

class ManualSyncPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            [],
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
            'nonce' => wp_create_nonce('nextsafar_news_admin'),
        ]);

        \NextSafar\Admin\Layout::render(function() {
            // ✅ اصلاح: تعریف متغیرهای مورد نیاز view
            $sources = \NextSafar\News\NewsSync::getSourcesStats();
            
            $rssCount = 0;
            $apiCount = 0;
            foreach ($sources as $source => $data) {
                $type = $data['type'] ?? '';
                if ($type === 'rss') {
                    $rssCount++;
                } else {
                    $apiCount++;
                }
            }
            
            $aiEnabled = get_option(\NextSafar\News\NewsSync::OPT_AI_ENABLED, '0') === '1';

            $source_status = [];
            foreach ($sources as $source => $data) {
                $source_status[$source] = [
                    'enabled' => get_option("nextsafar_news_source_{$source}_enabled", '1') === '1',
                    'total' => $data['total'] ?? 0,
                ];
            }

            include NEXTSAFAR_PATH . 'views/admin/news/manual-sync.php';
        }, 'تولید دستی اخبار');
    }
}