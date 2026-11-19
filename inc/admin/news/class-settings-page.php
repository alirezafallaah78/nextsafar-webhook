<?php
/**
 * NewsSettingsPage — تنظیمات سیستم اخبار + تست اتصال
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

        $message     = '';
        $messageType = '';

        if (
            isset($_POST['save_news_settings'], $_POST['_wpnonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'nextsafar_news_settings')
        ) {
            $result      = self::saveSettings(wp_unslash($_POST));
            $message     = $result['message'];
            $messageType = $result['type'];
        }

        if (
            isset($_POST['save_api_keys'], $_POST['_wpnonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'nextsafar_news_settings')
        ) {
            $result      = self::saveApiKeys(wp_unslash($_POST));
            $message     = $result['message'];
            $messageType = $result['type'];
        }

        // ✅ assetها توسط FilterPage::enqueue_assets زودهنگام (قبل از wp_head) لود می‌شوند.
        // اینجا دیگر enqueue نمی‌کنیم چون render() بعد از wp_head اجرا می‌شود.

        $settings     = self::getSettings();
        $geminiModels = (class_exists(GeminiClient::class) && method_exists(GeminiClient::class, 'getModelsList'))
            ? GeminiClient::getModelsList()
            : array();

        include NEXTSAFAR_PATH . 'views/admin/news/settings.php';
    }

    public static function getSettings(): array
    {
        return array(
            'news_auto'             => get_option(NewsSync::OPT_AUTO_ENABLED, '0'),
            'sync_hours'            => get_option(NewsSync::OPT_SCHEDULE_HOURS, array(8, 12, 18)),
            'time_window_hours'     => get_option(NewsSync::OPT_TIME_WINDOW, 24),
            'max_publish_per_run'   => get_option(NewsSync::OPT_MAX_PUBLISH, 5),
            'max_draft_per_run'     => get_option(NewsSync::OPT_MAX_DRAFTS, 3),
            'max_ai_review_per_run' => get_option(NewsSync::OPT_MAX_AI_CHECKS, 2),

            'ai_enabled'            => get_option(NewsSync::OPT_AI_ENABLED, '0'),
            'gemini_api_key'        => get_option('nextsafar_gemini_api_key', ''),
            'ai_model_main'         => get_option('nextsafar_news_ai_model_main', 'gemini-3.7-flash'),
            'ai_model_backup1'      => get_option('nextsafar_news_ai_model_backup1', 'gemini-3.6-flash'),
            'ai_model_backup2'      => get_option('nextsafar_news_ai_model_backup2', 'gemini-3.5-flash'),

            'gnews_api_key'         => get_option('nextsafar_gnews_api_key', ''),
            'newsdata_api_key'      => get_option('nextsafar_newsdata_api_key', ''),
            'currents_api_key'      => get_option('nextsafar_currents_api_key', ''),

            'publish_threshold'     => get_option('nextsafar_news_filter_publish_threshold', 20),
            'delete_threshold'      => get_option('nextsafar_news_filter_delete_threshold', -10),
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
        update_option(NewsSync::OPT_TIME_WINDOW, max(1, absint($post['time_window_hours'] ?? 24)));

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

    /* ========================================================================
       ✅ تست اتصال
    ======================================================================== */

    public static function ajaxTestConnection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'nextsafar_news_admin')) {
            wp_send_json_error(array('message' => 'خطای امنیتی (nonce)'));
        }

        $service = isset($_POST['service']) ? sanitize_key($_POST['service']) : '';
        if (!in_array($service, array('gemini', 'gnews', 'newsdata', 'currents', 'rss'), true)) {
            wp_send_json_error(array('message' => 'سرویس نامعتبر'));
        }

        if (!nextsafar_acquire_lock('news_test_' . $service, 10)) {
            wp_send_json_error(array('message' => 'چند ثانیه صبر کنید و دوباره امتحان کنید.'));
        }

        @set_time_limit(30);

        try {
            $result = self::runTest($service);
        } catch (\Throwable $e) {
            $result = array('ok' => false, 'message' => 'خطا: ' . $e->getMessage());
        } finally {
            nextsafar_release_lock('news_test_' . $service);
        }

        if (!empty($result['ok'])) {
            wp_send_json_success(array('message' => $result['message']));
        }
        wp_send_json_error(array('message' => $result['message']));
    }

    private static function runTest(string $service): array
    {
        switch ($service) {
            case 'gemini':
                $key = trim((string) get_option('nextsafar_gemini_api_key', ''));
                if ($key === '') {
                    return array('ok' => false, 'message' => 'Gemini: کلید API ذخیره نشده است.');
                }
                // ✅ کلید در هدر (نه query string) تا در لاگ‌ها ثبت نشود
                $r = self::httpJson('https://generativelanguage.googleapis.com/v1beta/models', 'Gemini', array(
                    'x-goog-api-key' => $key,
                ));
                if (!empty($r['ok'])) {
                    $n = (isset($r['data']['models']) && is_array($r['data']['models'])) ? count($r['data']['models']) : 0;
                    return array('ok' => true, 'message' => 'Gemini: اتصال موفق ✅' . ($n ? " ({$n} مدل در دسترس)" : ''));
                }
                return $r;

            case 'gnews':
                $key = trim((string) get_option('nextsafar_gnews_api_key', ''));
                if ($key === '') {
                    return array('ok' => false, 'message' => 'GNews: کلید API ذخیره نشده است.');
                }
                $url = add_query_arg(array('q' => 'travel', 'lang' => 'en', 'max' => 1, 'apikey' => $key), 'https://gnews.io/api/v4/search');
                $r   = self::httpJson($url, 'GNews');
                if (!empty($r['ok'])) {
                    if (!empty($r['data']['errors'])) {
                        $err = is_array($r['data']['errors']) ? implode(' | ', $r['data']['errors']) : (string) $r['data']['errors'];
                        return array('ok' => false, 'message' => 'GNews: ' . $err);
                    }
                    $n = (int) ($r['data']['totalArticles'] ?? 0);
                    return array('ok' => true, 'message' => "GNews: اتصال موفق ✅ ({$n} نتیجه)");
                }
                return $r;

            case 'newsdata':
                $key = trim((string) get_option('nextsafar_newsdata_api_key', ''));
                if ($key === '') {
                    return array('ok' => false, 'message' => 'NewsData: کلید API ذخیره نشده است.');
                }
                $url = add_query_arg(array('apikey' => $key, 'q' => 'travel'), 'https://newsdata.io/api/1/latest');
                $r   = self::httpJson($url, 'NewsData');
                if (!empty($r['ok']) && (($r['data']['status'] ?? '') === 'success')) {
                    $n = (int) ($r['data']['totalResults'] ?? 0);
                    return array('ok' => true, 'message' => "NewsData: اتصال موفق ✅ ({$n} نتیجه)");
                }
                if (!empty($r['ok'])) {
                    return array('ok' => false, 'message' => 'NewsData: ' . (string) ($r['data']['message'] ?? 'پاسخ نامعتبر'));
                }
                return $r;

            case 'currents':
                $key = trim((string) get_option('nextsafar_currents_api_key', ''));
                if ($key === '') {
                    return array('ok' => false, 'message' => 'Currents: کلید API ذخیره نشده است.');
                }
                $url = add_query_arg(array('apiKey' => $key, 'keywords' => 'travel', 'page_size' => 1), 'https://api.currentsapi.services/v1/search');
                $r   = self::httpJson($url, 'Currents');
                if (!empty($r['ok']) && (($r['data']['status'] ?? '') === 'ok')) {
                    return array('ok' => true, 'message' => 'Currents: اتصال موفق ✅');
                }
                if (!empty($r['ok'])) {
                    return array('ok' => false, 'message' => 'Currents: ' . (string) ($r['data']['message'] ?? 'پاسخ نامعتبر'));
                }
                return $r;

            case 'rss':
                return self::testRss();
        }

        return array('ok' => false, 'message' => 'سرویس نامعتبر');
    }

    private static function httpJson(string $url, string $label, array $headers = array()): array
    {
        $res = wp_safe_remote_get($url, array(
            'timeout'             => 12,
            'redirection'         => 2,
            'headers'             => $headers,
            'limit_response_size' => 262144,
        ));

        if (is_wp_error($res)) {
            return array('ok' => false, 'message' => $label . ': اتصال ناموفق — ' . $res->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        if (200 === $code && is_array($body)) {
            return array('ok' => true, 'message' => $label . ': اتصال موفق ✅', 'data' => $body);
        }

        $err = '';
        if (is_array($body)) {
            $err = $body['error']['message'] ?? ($body['message'] ?? '');
            if (is_array($err)) {
                $err = implode(' | ', $err);
            }
            if ($err === '' && !empty($body['errors'])) {
                $err = is_array($body['errors']) ? implode(' | ', $body['errors']) : (string) $body['errors'];
            }
        }

        return array('ok' => false, 'message' => $label . ': HTTP ' . $code . ($err !== '' ? ' — ' . $err : ''));
    }

    private static function testRss(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return array('ok' => false, 'message' => 'RSS: جدول منابع ساخته نشده است.');
        }

        $row = $wpdb->get_row(
            "SELECT name, url FROM {$table} WHERE type = 'rss' AND is_active = 1 ORDER BY priority DESC LIMIT 1",
            ARRAY_A
        );

        if (!$row || empty($row['url'])) {
            return array('ok' => false, 'message' => 'RSS: منبع فعالی یافت نشد.');
        }

        $res = wp_safe_remote_get($row['url'], array(
            'timeout'             => 12,
            'redirection'         => 3,
            'limit_response_size' => 131072,
        ));

        if (is_wp_error($res)) {
            return array('ok' => false, 'message' => 'RSS (' . $row['name'] . '): ' . $res->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if (200 === $code) {
            return array('ok' => true, 'message' => 'RSS (' . $row['name'] . '): اتصال موفق ✅');
        }

        return array('ok' => false, 'message' => 'RSS (' . $row['name'] . '): HTTP ' . $code);
    }
}

// ✅ ثبت AJAX
add_action('wp_ajax_nextsafar_news_test_connection', array('NextSafar\Admin\News\NewsSettingsPage', 'ajaxTestConnection'));