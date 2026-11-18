<?php
/**
 * NewsSettingsPage — تنظیمات سیستم اخبار
 *
 * @package NextSafarAdminNews
 * @since 1.0.0
 */

namespace NextSafar\Admin\News;

use NextSafar\News\NewsSync;
use NextSafar\News\GeminiClient;

if (!defined('ABSPATH')) {
    exit;
}

class NewsSettingsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        // پردازش فرم ذخیره
        $message = '';
        $messageType = '';

        if (isset($_POST['save_news_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'nextsafar_news_settings')) {
            $result = self::saveSettings($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
        }

        if (isset($_POST['save_api_keys']) && wp_verify_nonce($_POST['_wpnonce'], 'nextsafar_news_settings')) {
            $result = self::saveApiKeys($_POST);
            $message = $result['message'];
            $messageType = $result['type'];
        }

        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            array(),
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/js/pages/news-admin.js',
            array('jquery'),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-news-admin', 'nextsafarNews', array(
            'ajaxUrl' => \NextSafar\Core::get_ajax_url(),
            'nonce'   => wp_create_nonce('nextsafar_news_admin'),
        ));

        // بارگذاری تنظیمات فعلی
        $settings = self::getSettings();

        // لیست مدل‌های Gemini
        $geminiModels = GeminiClient::getModelsList();

        // ✅ مستقیم view را render کن
        include NEXTSAFAR_PATH . 'views/admin/news/settings.php';
    }

    public static function getSettings(): array
    {
        return array(
            // General
            'news_auto'           => get_option(NewsSync::OPT_AUTO_ENABLED, '0'),
            'sync_hours'          => get_option(NewsSync::OPT_SCHEDULE_HOURS, array(8, 12, 18)),
            'time_window_hours'    => get_option(NewsSync::OPT_TIME_WINDOW, 24),
            'max_publish_per_run' => get_option(NewsSync::OPT_MAX_PUBLISH, 5),
            'max_draft_per_run'   => get_option(NewsSync::OPT_MAX_DRAFTS, 3),
            'max_ai_review_per_run' => get_option(NewsSync::OPT_MAX_AI_CHECKS, 2),

            // AI
            'ai_enabled'          => get_option(NewsSync::OPT_AI_ENABLED, '0'),
            'gemini_api_key'      => get_option('nextsafar_gemini_api_key', ''),
            'ai_model_main'       => get_option('nextsafar_news_ai_model_main', 'gemini-3.7-flash'),
            'ai_model_backup1'    => get_option('nextsafar_news_ai_model_backup1', 'gemini-3.6-flash'),
            'ai_model_backup2'    => get_option('nextsafar_news_ai_model_backup2', 'gemini-3.5-flash'),

            // API Keys
            'gnews_api_key'       => get_option('nextsafar_gnews_api_key', ''),
            'newsdata_api_key'    => get_option('nextsafar_newsdata_api_key', ''),
            'currents_api_key'    => get_option('nextsafar_currents_api_key', ''),

            // Filter
            'publish_threshold'   => get_option('nextsafar_news_filter_publish_threshold', 20),
            'delete_threshold'    => get_option('nextsafar_news_filter_delete_threshold', -10),
        );
    }

    private static function saveSettings(array $post): array
    {
        update_option(NewsSync::OPT_AUTO_ENABLED, isset($post['news_auto']) ? '1' : '0');

        $hours = array();
        if (isset($post['sync_hours']) && is_array($post['sync_hours'])) {
            foreach ($post['sync_hours'] as $h) {
                $h = (int) $h;
                if ($h >= 0 && $h <= 23) {
                    $hours[] = $h;
                }
            }
        }

        update_option(NewsSync::OPT_SCHEDULE_HOURS, $hours);
        update_option(NewsSync::OPT_MAX_PUBLISH, absint($post['max_publish_per_run'] ?? 5));
        update_option(NewsSync::OPT_MAX_DRAFTS, absint($post['max_draft_per_run'] ?? 3));
        update_option(NewsSync::OPT_MAX_AI_CHECKS, absint($post['max_ai_review_per_run'] ?? 2));
        update_option(NewsSync::OPT_TIME_WINDOW, absint($post['time_window_hours'] ?? 24));

        update_option(NewsSync::OPT_AI_ENABLED, isset($post['ai_enabled']) ? '1' : '0');
        update_option('nextsafar_gemini_api_key', sanitize_text_field($post['gemini_api_key'] ?? ''));
        update_option('nextsafar_news_ai_model_main', sanitize_text_field($post['ai_model_main'] ?? 'gemini-3.7-flash'));
        update_option('nextsafar_news_ai_model_backup1', sanitize_text_field($post['ai_model_backup1'] ?? 'gemini-3.6-flash'));
        update_option('nextsafar_news_ai_model_backup2', sanitize_text_field($post['ai_model_backup2'] ?? 'gemini-3.5-flash'));

        update_option('nextsafar_news_filter_publish_threshold', (int) ($post['publish_threshold'] ?? 20));
        update_option('nextsafar_news_filter_delete_threshold', (int) ($post['delete_threshold'] ?? -10));

        NewsSync::clearCron();
        NewsSync::scheduleCron();

        return array(
            'success' => true,
            'type'    => 'success',
            'message' => '✅ تنظیمات با موفقیت ذخیره شد',
        );
    }

    private static function saveApiKeys(array $post): array
    {
        update_option('nextsafar_gnews_api_key', sanitize_text_field($post['gnews_api_key'] ?? ''));
        update_option('nextsafar_newsdata_api_key', sanitize_text_field($post['newsdata_api_key'] ?? ''));
        update_option('nextsafar_currents_api_key', sanitize_text_field($post['currents_api_key'] ?? ''));

        return array(
            'success' => true,
            'type'    => 'success',
            'message' => '✅ کلیدهای API با موفقیت ذخیره شد',
        );
    }
}