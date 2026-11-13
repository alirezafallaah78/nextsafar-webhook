<?php
namespace NextSafar\Admin\PostGenerator;

if (!defined('ABSPATH')) exit;

class PostGeneratorPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        $nonce = wp_create_nonce('nextsafar_post_generator');

        wp_enqueue_style(
            'nextsafar-terminal',
            NEXTSAFAR_URL . 'assets/admin/css/pages/terminal.css',
            ['nextsafar-components'],
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-post-generator',
            NEXTSAFAR_URL . 'assets/admin/js/pages/post-generator.js',
            ['jquery', 'nextsafar-utils'],
            NEXTSAFAR_VERSION,
            true
        );

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $ajax_url = $protocol . '://' . $host . '/wp-admin/admin-ajax.php';

        wp_localize_script('nextsafar-post-generator', 'nextsafarPG', [
            'ajaxUrl' => $ajax_url,
            'nonce'   => $nonce,
        ]);

        \NextSafar\Admin\Layout::render(function() {
            include NEXTSAFAR_PATH . 'views/admin/post-generator/page-main.php';
        }, 'تولید پست هوشمند');
    }
}