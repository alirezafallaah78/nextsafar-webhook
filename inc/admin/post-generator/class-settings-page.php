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

        if (isset($_POST['save_api_keys']) && wp_verify_nonce($_POST['_wpnonce'], 'nextsafar_settings')) {
            self::saveSettings();
        }

        wp_enqueue_style(
            'nextsafar-pg-settings',
            NEXTSAFAR_URL . 'assets/admin/css/pages/pg-settings.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-pg-settings',
            NEXTSAFAR_URL . 'assets/admin/js/pages/pg-settings.js',
            array('jquery', 'nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );

        // ✅ مستقیم view را render کن - بدون Layout wrapper
        $settings = self::getSettings();
        include NEXTSAFAR_PATH . 'views/admin/post-generator/page-settings.php';
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
        return array(
            'serpapi_key'     => get_option('nextsafar_serpapi_key', ''),
            'searchapi_key'   => get_option('nextsafar_searchapi_key', ''),
            'active_provider' => get_option('nextsafar_active_provider', 'searchapi'),
        );
    }
}