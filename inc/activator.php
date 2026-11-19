<?php

namespace NextSafar;

if (!defined('ABSPATH')) exit;

class Activator {

    public static function activate() {
        Core::register_post_types();
        Core::register_taxonomies();

        self::create_custom_roles();

        // ✅ ساخت جداول اخبار فقط هنگام فعال‌سازی (نه در هر ریکوئست)
        \NextSafar\Database\NewsTables::createAll();
        \NextSafar\Database\NewsTables::seedDefaultSources();
        update_option(
            \NextSafar\Database\NewsTables::DB_VERSION_OPTION,
            \NextSafar\Database\NewsTables::DB_VERSION
        );

        flush_rewrite_rules();

        update_option('nextsafar_version', NEXTSAFAR_VERSION);
        update_option('nextsafar_activated_at', current_time('mysql'));

        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('✅ NextSafar Webhook: Plugin activated successfully');
        }
    }

    private static function create_custom_roles(): void {
        remove_role('nextsafar_editor');
        remove_role('nextsafar_author');

        add_role('nextsafar_editor', 'NextSafar Editor', [
            'read'                 => true,
            'edit_posts'           => true,
            'edit_published_posts' => true,
            'publish_posts'        => true,
            'upload_files'         => true,
            'edit_pages'           => true,
            'edit_others_posts'    => true,
            'edit_published_pages' => true,
            'publish_pages'        => true,
            'edit_others_pages'    => true,
        ]);

        add_role('nextsafar_author', 'NextSafar Author', [
            'read'                 => true,
            'edit_posts'           => true,
            'edit_published_posts' => true,
            'upload_files'         => true,
        ]);
    }
}