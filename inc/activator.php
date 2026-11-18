<?php

namespace NextSafar;

if (!defined('ABSPATH')) exit;

/**
 * Activator — Manages plugin activation
 *
 * This class is responsible for:
 * - Registering post types and taxonomies
 * - Creating custom user roles
 * - Flushing rewrite rules
 */
class Activator {

    /**
     * Main activation method
     */
    public static function activate() {
        /* Register post types and taxonomies */
        Core::register_post_types();
        Core::register_taxonomies();

        /* Create custom user roles */
        self::create_custom_roles();

        /* Flush rewrite rules */
        flush_rewrite_rules();

        /* Record plugin version */
        update_option('nextsafar_version', NEXTSAFAR_VERSION);
        update_option('nextsafar_activated_at', current_time('mysql'));

        error_log('✅ NextSafar Webhook: Plugin activated successfully');
    }

    /**
     * Create custom user roles
     */
    private static function create_custom_roles(): void {
        remove_role('nextsafar_editor');
        remove_role('nextsafar_author');

        add_role('nextsafar_editor', 'NextSafar Editor', [
            'read' => true,
            'edit_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            'edit_pages' => true,  // ✅ فقط یکبار
            'edit_others_posts' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'edit_others_pages' => true,
        ]);

        add_role('nextsafar_author', 'NextSafar Author', [
            'read' => true,
            'edit_posts' => true,
            'edit_published_posts' => true,
            'upload_files' => true,
        ]);
    }
}