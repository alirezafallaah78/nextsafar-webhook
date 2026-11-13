<?php
namespace NextSafar\Admin\PostGenerator;

if (!defined('ABSPATH')) exit;

class SettingsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

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

        // ✅ اصلاح: مسیر صحیح
        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            [],
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/js/pages/news-admin.js',
            ['jquery'],
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-news-admin', 'nextsafarNews', [
            'ajaxUrl' => \NextSafar\Core::get_ajax_url(),
            'nonce' => wp_create_nonce('nextsafar_news_admin'),
        ]);

        $settings = self::getSettings();
        $geminiModels = GeminiClient::getModelsList();

        include NEXTSAFAR_PATH . 'views/news/settings.php';
    }

    protected static function saveSettings(): void
    {
        update_option('nextsafar_serpapi_key', sanitize_text_field($_POST['serpapi_key'] ?? ''));
        update_option('nextsafar_searchapi_key', sanitize_text_field($_POST['searchapi_key'] ?? ''));
        update_option('nextsafar_active_provider', sanitize_text_field($_POST['active_provider'] ?? 'searchapi'));

        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد</p></div>';
    }

    public static function getSettings(): array
    {
        return [
            'serpapi_key'     => get_option('nextsafar_serpapi_key', ''),
            'searchapi_key'   => get_option('nextsafar_searchapi_key', ''),
            'active_provider' => get_option('nextsafar_active_provider', 'searchapi'),
        ];
    }
}