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
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );

        wp_enqueue_script(
            'nextsafar-post-generator',
            NEXTSAFAR_URL . 'assets/admin/js/pages/post-generator.js',
            array('jquery', 'nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-post-generator', 'nextsafarPG', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => $nonce,
        ));

        // ✅ مستقیم view را render کن - بدون Layout wrapper
        include NEXTSAFAR_PATH . 'views/admin/post-generator/page-main.php';
    }
}