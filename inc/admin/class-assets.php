<?php
if (!defined('ABSPATH')) {
    exit;
}

class NextSafar_Assets {

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
public function enqueue_assets($hook) {
    // 🌐 IFRAME HANDLER
    if (isset($_GET['nextsafar_iframe'])) {
        wp_enqueue_style(
            'nextsafar-wp-pages',
            NEXTSAFAR_URL . 'assets/admin/css/core/wp-pages.css',
            array('nextsafar-variables'),
            NEXTSAFAR_VERSION
        );
        return;
    }

    // 🎯 تشخیص: آیا این صفحه استاندارد وردپرس هست؟
    $is_wp_standard_page = in_array($hook, array(
        'post.php', 'post-new.php',
        'edit-tags.php', 'term.php',
        'edit-comments.php', 'comment.php',
        'edit.php',
        'upload.php', 'media-new.php',
        'themes.php', 'customize.php', 'nav-menus.php', 'widgets.php',
        'plugins.php', 'plugin-install.php', 'plugin-editor.php',
        'users.php', 'user-new.php', 'user-edit.php', 'profile.php',
        'tools.php', 'import.php', 'export.php', 'site-health.php',
        'options-general.php', 'options-writing.php', 'options-reading.php',
        'options-discussion.php', 'options-media.php', 'options-permalink.php',
        'options-privacy.php',
    ), true);

    // ✅ تشخیص صفحات news - بهبود یافته
    $is_news_page = (
        strpos($hook, 'nextsafar-news') !== false ||
        strpos($hook, 'nextsafar_news') !== false ||
        (isset($_GET['page']) && strpos(sanitize_key($_GET['page']), 'nextsafar-news') !== false)
    );

    // ✅ تشخیص صفحات post-generator - بهبود یافته
    $is_post_generator_page = (
        strpos($hook, 'nextsafar-post-generator') !== false ||
        strpos($hook, 'nextsafar-pg-settings') !== false ||
        strpos($hook, 'nextsafar_post_generator') !== false ||
        (isset($_GET['page']) && (
            sanitize_key($_GET['page']) === 'nextsafar-post-generator' ||
            sanitize_key($_GET['page']) === 'nextsafar-pg-settings'
        ))
    );

    $is_admin_php_page = (
        strpos($hook, 'admin.php') !== false ||
        strpos($hook, 'admin_page_') !== false
    );

    $needs_injected_sidebar = ($is_wp_standard_page || $is_admin_php_page || $is_news_page || $is_post_generator_page) && !isset($_GET['nextsafar_iframe']);

    // 🎨 CSS FILES - MODULAR
    $is_role_access_page = (
        'nextsafar-admin_page_nextsafar-role-access' === $hook ||
        strpos($hook, 'nextsafar-role-access') !== false
    );

    if ($is_role_access_page) {
        wp_enqueue_script(
            'nextsafar-role-access',
            NEXTSAFAR_URL . 'assets/admin/js/role-access.js',
            array('jquery', 'nextsafar-admin'),
            NEXTSAFAR_VERSION,
            true
        );
    }

    // ✅ 1. Fonts - همیشه لود بشه
    wp_enqueue_style(
        'nextsafar-fonts',
        NEXTSAFAR_URL . 'assets/admin/css/core/fonts.css',
        array(),
        NEXTSAFAR_VERSION
    );

    // ✅ 2. Variables
    wp_enqueue_style(
        'nextsafar-variables',
        NEXTSAFAR_URL . 'assets/admin/css/core/variables.css',
        array('nextsafar-fonts'),
        NEXTSAFAR_VERSION
    );

    wp_enqueue_style(
        'nextsafar-metabox-base',
        NEXTSAFAR_URL . 'assets/admin/css/core/metabox-base.css',
        ['nextsafar-variables'],
        NEXTSAFAR_VERSION
    );

    // ✅ 3. Base
    wp_enqueue_style(
        'nextsafar-base',
        NEXTSAFAR_URL . 'assets/admin/css/core/base.css',
        array('nextsafar-variables'),
        NEXTSAFAR_VERSION
    );

    // ✅ 4. Layout
    wp_enqueue_style(
        'nextsafar-layout',
        NEXTSAFAR_URL . 'assets/admin/css/core/layout.css',
        array('nextsafar-base'),
        NEXTSAFAR_VERSION
    );

    // ✅ 5. Components
    wp_enqueue_style(
        'nextsafar-components',
        NEXTSAFAR_URL . 'assets/admin/css/core/components.css',
        array('nextsafar-layout'),
        NEXTSAFAR_VERSION
    );

    // 🎯 تشخیص نوع صفحه
    $is_media_page = (
        'nextsafar_page_nextsafar-media' === $hook ||
        'toplevel_page_nextsafar-media' === $hook ||
        strpos($hook, 'nextsafar-media') !== false
    );

    $is_post_editor = in_array($hook, array('post.php', 'post-new.php'), true);
    $is_taxonomy_page = in_array($hook, array('edit-tags.php', 'term.php'), true);
    $is_comments_page = in_array($hook, array('edit-comments.php', 'comment.php'), true);
    $is_wrapper_page = (
        'nextsafar_page_nextsafar-wp-wrapper' === $hook ||
        'toplevel_page_nextsafar-wp-wrapper' === $hook ||
        strpos($hook, 'nextsafar-wp-wrapper') !== false
    );

    $is_post_editor_page = (
        'nextsafar_page_nextsafar-post-editor' === $hook ||
        'toplevel_page_nextsafar-post-editor' === $hook ||
        'nextsafar-admin_page_nextsafar-post-editor' === $hook ||
        strpos($hook, 'nextsafar-post-editor') !== false
    );

    // 📄 PAGE-SPECIFIC CSS
    if ($is_media_page || $is_post_editor || $is_taxonomy_page) {
        wp_enqueue_style(
            'nextsafar-media',
            NEXTSAFAR_URL . 'assets/admin/css/core/media.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );
    }

    // ✅ لود CSS wp-pages برای تمام صفحات استاندارد وردپرس + news + post-generator
    if ($is_comments_page || $is_wrapper_page || $is_wp_standard_page || $is_news_page || $is_post_generator_page) {
        wp_enqueue_style(
            'nextsafar-wp-pages',
            NEXTSAFAR_URL . 'assets/admin/css/core/wp-pages.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );
    }

    // ✅ لود CSS اختصاصی news-admin
    if ($is_news_page) {
        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            array('nextsafar-components', 'nextsafar-wp-pages'),
            NEXTSAFAR_VERSION
        );
    }

    // ✅ لود CSS اختصاصی terminal
    if ($is_post_generator_page) {
        wp_enqueue_style(
            'nextsafar-terminal',
            NEXTSAFAR_URL . 'assets/admin/css/pages/terminal.css',
            array('nextsafar-components', 'nextsafar-wp-pages'),
            NEXTSAFAR_VERSION
        );
    }

    if ($is_taxonomy_page || $is_post_editor) {
        wp_enqueue_style(
            'nextsafar-wp-editor',
            NEXTSAFAR_URL . 'assets/admin/css/core/post-editor.css',
            array('nextsafar-components', 'nextsafar-fonts'),
            NEXTSAFAR_VERSION
        );
    }

    if ($is_post_editor_page) {
        global $pagenow, $current_screen, $post;

        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

        $pagenow = ($post_id > 0) ? 'post.php' : 'post-new.php';

        if (!is_object($current_screen) || strpos($current_screen->id, 'nextsafar') !== false) {
            $current_screen = WP_Screen::get($post_type);
            set_current_screen($post_type);
        }

        if ($post_id > 0) {
            $post_obj = get_post($post_id);
            if ($post_obj) {
                $GLOBALS['post'] = $post_obj;
                setup_postdata($post_obj);
            }
        } else {
            $GLOBALS['post'] = get_default_post_to_edit($post_type, true);
        }

        wp_enqueue_media();
        wp_enqueue_editor();
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');
        wp_enqueue_script('post');
        wp_enqueue_script('word-count');
        wp_enqueue_script('autosave');
        wp_enqueue_script('tags-box');
        wp_enqueue_script('suggest');
        wp_enqueue_script('dashboard');

        if (function_exists('acf_get_setting')) {
            wp_enqueue_script('acf-input');
            wp_enqueue_style('acf-global');
            wp_enqueue_style('acf-input');
        }

        if (defined('RANK_MATH_VERSION') && wp_script_is('rank-math', 'registered')) {
            wp_enqueue_script('rank-math');
        }

        wp_enqueue_style(
            'nextsafar-post-editor',
            NEXTSAFAR_URL . 'assets/admin/css/core/post-editor.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );
    }

    if ('toplevel_page_nextsafar-admin' === $hook) {
        wp_enqueue_style(
            'nextsafar-dashboard',
            NEXTSAFAR_URL . 'assets/admin/css/core/dashboard.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );
    }

    $is_updates_page = (
        'nextsafar-admin_page_nextsafar-updates' === $hook ||
        'toplevel_page_nextsafar-updates' === $hook ||
        strpos($hook, 'nextsafar-updates') !== false
    );

    if ($is_updates_page) {
        wp_enqueue_style(
            'nextsafar-updates',
            NEXTSAFAR_URL . 'assets/admin/css/core/updates.css',
            array('nextsafar-components'),
            NEXTSAFAR_VERSION
        );
    }

    // ⚡ JAVASCRIPT CORE FILES
    wp_enqueue_script(
        'nextsafar-utils',
        NEXTSAFAR_URL . 'assets/admin/js/core/utils.js',
        array(),
        NEXTSAFAR_VERSION,
        true
    );

    wp_enqueue_script(
        'nextsafar-ajax',
        NEXTSAFAR_URL . 'assets/admin/js/core/ajax-manager.js',
        array('nextsafar-utils'),
        NEXTSAFAR_VERSION,
        true
    );

    wp_enqueue_script(
        'nextsafar-state',
        NEXTSAFAR_URL . 'assets/admin/js/core/state-manager.js',
        array('nextsafar-utils'),
        NEXTSAFAR_VERSION,
        true
    );

    // 🧩 JAVASCRIPT MODULES
    wp_enqueue_script(
        'nextsafar-search',
        NEXTSAFAR_URL . 'assets/admin/js/modules/search.js',
        array('nextsafar-utils', 'nextsafar-ajax'),
        NEXTSAFAR_VERSION,
        true
    );

    wp_enqueue_script(
        'nextsafar-notifications',
        NEXTSAFAR_URL . 'assets/admin/js/modules/notifications.js',
        array('nextsafar-utils'),
        NEXTSAFAR_VERSION,
        true
    );

    wp_enqueue_script(
        'nextsafar-topbar',
        NEXTSAFAR_URL . 'assets/admin/js/modules/topbar.js',
        array('nextsafar-utils'),
        NEXTSAFAR_VERSION,
        true
    );

    wp_enqueue_script(
        'nextsafar-sidebar',
        NEXTSAFAR_URL . 'assets/admin/js/modules/sidebar.js',
        array('nextsafar-utils'),
        NEXTSAFAR_VERSION,
        true
    );

    // 📱 PAGE-SPECIFIC JAVASCRIPT
    if ($is_media_page) {
        wp_enqueue_script(
            'nextsafar-media-library',
            NEXTSAFAR_URL . 'assets/admin/js/pages/media-library.js',
            array('nextsafar-utils', 'nextsafar-ajax', 'nextsafar-state', 'jquery'),
            NEXTSAFAR_VERSION,
            true
        );
    }

    if ($is_post_editor_page) {
        wp_enqueue_script(
            'nextsafar-post-editor',
            NEXTSAFAR_URL . 'assets/admin/js/post-editor.js',
            array('jquery', 'nextsafar-admin'),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-post-editor', 'nextsafarEditor', array(
            'postId'        => $post_id,
            'postType'      => $post_type,
            'isEdit'        => isset($_GET['action']) && $_GET['action'] === 'edit' ? 1 : 0,
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'adminUrl'      => admin_url(),
            'nonce'         => wp_create_nonce('nextsafar_post_editor'),
            'trashNonce'    => $post_id > 0 ? wp_create_nonce('trash-post_' . $post_id) : '',
            'strings'       => array(
                'saving'        => 'در حال ذخیره...',
                'saved'         => 'ذخیره شد',
                'publishing'    => 'در حال انتشار...',
                'published'     => 'با موفقیت منتشر شد',
                'error'         => 'خطا در ذخیره‌سازی',
                'confirmDelete' => 'آیا از حذف این پست مطمئن هستید؟',
            ),
        ));
    }

    // ✅ لود JavaScript برای news pages
    if ($is_news_page) {
        wp_enqueue_script(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/js/pages/news-admin.js',
            array('jquery', 'nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
    }

    // ✅ لود JavaScript برای post-generator pages
    if ($is_post_generator_page) {
        wp_enqueue_script(
            'nextsafar-post-generator',
            NEXTSAFAR_URL . 'assets/admin/js/pages/post-generator.js',
            array('jquery', 'nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
    }

    if ('toplevel_page_nextsafar-admin' === $hook) {

        // ✅ Chart.js لوکال — اگر فایل هنوز اضافه نشده، موقتاً CDN (fallback)
        $chart_local = NEXTSAFAR_PATH . 'assets/admin/js/vendor/chart.umd.min.js';
        $chart_url   = file_exists($chart_local)
            ? NEXTSAFAR_URL . 'assets/admin/js/vendor/chart.umd.min.js'
            : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';

        wp_enqueue_script(
            'chartjs',
            $chart_url,
            array(),
            '4.4.1',
            true
        );

        // آدرس Chart.js را به JS بده تا fallback داخلی هم لوکال باشد
        wp_localize_script('chartjs', 'nextsafarChart', array('url' => $chart_url));

        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');

        wp_enqueue_script(
            'nextsafar-dashboard',
            NEXTSAFAR_URL . 'assets/admin/js/dashboard.js',
            array('jquery', 'nextsafar-admin', 'chartjs'),
            NEXTSAFAR_VERSION,
            true
        );
    }

    if ($is_updates_page) {
        wp_enqueue_script(
            'nextsafar-updates',
            NEXTSAFAR_URL . 'assets/admin/js/updates.js',
            array('nextsafar-admin'),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-updates', 'nextsafarUpdates', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('nextsafar_updates_nonce'),
            'strings' => array(
                'updating' => 'در حال بروزرسانی...',
                'updated'  => 'با موفقیت بروزرسانی شد',
                'error'    => 'بروزرسانی ناموفق بود',
            ),
        ));
    }

    // 🚀 ADMIN JS - BOOTSTRAP
    wp_enqueue_script(
        'nextsafar-admin',
        NEXTSAFAR_URL . 'assets/admin/js/admin.js',
        array(
            'jquery',
            'nextsafar-utils',
            'nextsafar-ajax',
            'nextsafar-state',
            'nextsafar-search',
            'nextsafar-notifications',
            'nextsafar-topbar',
            'nextsafar-sidebar',
        ),
        NEXTSAFAR_VERSION,
        true
    );

    wp_enqueue_script(
        'nextsafar-submenu',
        NEXTSAFAR_URL . 'assets/admin/js/submenu.js',
        array('jquery'),
        NEXTSAFAR_VERSION,
        true
    );

    // 🌐 GLOBAL JS DATA
    wp_localize_script('nextsafar-admin', 'nextsafarAdmin', array(
        'ajaxUrl'            => admin_url('admin-ajax.php'),
        'mediaNonce'         => wp_create_nonce('nextsafar_upload_media'),
        'deleteNonce'        => wp_create_nonce('nextsafar_delete_media'),
        'bulkDeleteNonce'    => wp_create_nonce('nextsafar_bulk_delete_media'),
        'folderNonce'        => wp_create_nonce('nextsafar_folder_nonce'),
        'mediaDetailsNonce'  => wp_create_nonce('nextsafar_media_details_nonce'),
        'dashboardNonce'     => wp_create_nonce('nextsafar_dashboard_nonce'),
        'editorNonce'        => wp_create_nonce('nextsafar_post_editor'),
    ));

    // 🌐 اسکریپت‌های استاندارد وردپرس
    if ($needs_injected_sidebar || strpos($hook, 'nextsafar') !== false) {
        wp_enqueue_script('common');
        wp_enqueue_script('wp-ajax-response');
    }
}
}