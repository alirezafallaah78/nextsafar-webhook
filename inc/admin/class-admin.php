<?php
if (!defined('ABSPATH')) {
    exit;
}

class NextSafar_Admin {

    public function init() {
        add_filter('show_admin_bar', '__return_false');
        add_action('load-index.php', array($this, 'redirect_default_dashboard'));
        add_action('admin_menu', array($this, 'register_admin_menu'), 1);
        add_action('admin_menu', array($this, 'register_media_page'));
        add_action('admin_menu', array($this, 'register_updates_page'));

        add_action('admin_head', array($this, 'inject_styles'), 9999);
        add_action('admin_footer', array($this, 'inject_sidebar_and_topbar_js'), 999);
        add_filter('admin_title', array($this, 'fix_admin_title'), 10, 2);
        add_action('wp_ajax_nextsafar_global_search', array($this, 'ajax_global_search'));
        add_action('wp_ajax_nextsafar_add_term_ajax', array($this, 'ajax_add_term'));
        add_action('admin_init', array($this, 'yoast_ajax_proxy'), 1);
        add_action('current_screen', array($this, 'enable_screen_options'));
        add_filter('set-screen-option', array($this, 'save_screen_option'), 10, 3);

        // 🆕 AJAX برای Screen Options
        add_action('wp_ajax_nextsafar_get_screen_options', array($this, 'ajax_get_screen_options'));
        add_action('wp_ajax_nextsafar_save_dashboard_widgets', array($this, 'ajax_save_dashboard_widgets'));
        add_action('current_screen', array($this, 'set_page_title_for_hidden_pages'));
    }

    /* =========================================================
     *  Page Title & Detection Helpers
     * ========================================================= */

    public function set_page_title_for_hidden_pages() {
        global $title, $plugin_page;

        if (empty($title) && !empty($plugin_page) && strpos($plugin_page, 'nextsafar') !== false) {
            $titles = array(
                'nextsafar-admin'         => 'داشبورد',
                'nextsafar-post-editor'   => 'ویرایشگر',
                'nextsafar-cpt'           => 'محتوا',
                'nextsafar-cpt-taxonomy'  => 'دسته‌بندی',
                'nextsafar-media'         => 'رسانه',
                'nextsafar-categories'    => 'دسته‌بندی‌ها',
                'nextsafar-posts'         => 'نوشته‌ها',
                'nextsafar-pages'         => 'برگه‌ها',
                'nextsafar-updates'       => 'بروزرسانی‌ها',
                'nextsafar-wp-wrapper'    => 'صفحه وردپرس',
            );
            $title = isset($titles[$plugin_page]) ? $titles[$plugin_page] : 'پنل مدیریت';
        }
    }

    private function is_iframe() {
        return isset($_GET['nextsafar_iframe']);
    }

/**
 * 🎯 تشخیص صفحات NextSafar
 * نسخه بهبود یافته
 */
private function is_nextsafar_page() {
    // اولویت ۱: بررسی $_GET['page']
    if (isset($_GET['page'])) {
        $page = sanitize_key($_GET['page']);
        if (strpos($page, 'nextsafar') !== false) {
            return true;
        }
    }
    
    // اولویت ۲: بررسی post_type برای CPT pages
    if (isset($_GET['post_type'])) {
        $post_type = sanitize_key($_GET['post_type']);
        $nextsafar_post_types = array(
            'hotel', 'airport', 'destination', 'restaurant',
            'hospital', 'visa', 'travelguide', 'travelnews',
            'tour', 'booking'
        );
        if (in_array($post_type, $nextsafar_post_types, true)) {
            return true;
        }
    }
    
    // اولویت ۳: بررسی screen
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'nextsafar') !== false) {
        return true;
    }
    
    return false;
}

/**
 * 🎯 تشخیص دقیق صفحات Post Editor (post.php, post-new.php)
 * نسخه بهبود یافته - بدون وابستگی به $pagenow
 */
private function is_post_editor_page() {
    // روش ۱: بررسی مستقیم از URL
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $script_name = basename(parse_url($request_uri, PHP_URL_PATH));
    
    if (in_array($script_name, array('post.php', 'post-new.php'), true)) {
        return true;
    }
    
    // روش ۲: بررسی از $_SERVER['PHP_SELF']
    $php_self = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
    if (in_array($php_self, array('post.php', 'post-new.php'), true)) {
        return true;
    }
    
    // روش ۳: fallback به global $pagenow
    global $pagenow;
    if (isset($pagenow) && in_array($pagenow, array('post.php', 'post-new.php'), true)) {
        return true;
    }
    
    return false;
}

    /* =========================================================
     *  Admin Menu Registration
     * ========================================================= */

    public function register_admin_menu() {
        add_menu_page(
            'NextSafar',
            'NextSafar',
            'edit_posts',
            'nextsafar-admin',
            array($this, 'render_admin_page'),
            'dashicons-admin-site',
            2
        );

        add_submenu_page(
            null,
            'CPT Listing',
            'CPT Listing',
            'edit_posts',
            'nextsafar-cpt',
            array($this, 'render_cpt_page')
        );

        add_submenu_page(
            null,
            'CPT Taxonomy',
            'CPT Taxonomy',
            'manage_categories',
            'nextsafar-cpt-taxonomy',
            array($this, 'render_cpt_taxonomy_page')
        );

        add_submenu_page(
            null,
            'Post Editor',
            'Post Editor',
            'edit_posts',
            'nextsafar-post-editor',
            array($this, 'render_post_editor_page')
        );
    }

    public function register_media_page() {
        add_submenu_page(
            'nextsafar-admin',
            'Media',
            'Media',
            'upload_files',
            'nextsafar-media',
            array($this, 'render_media_page')
        );
    }

    public function register_updates_page() {
        add_submenu_page(
            'nextsafar-admin',
            'Updates',
            'Updates',
            'update_core',
            'nextsafar-updates',
            array($this, 'render_updates_page')
        );
    }

    /* =========================================================
     *  ✅ Page Render Methods (با مسیرهای صحیح)
     * ========================================================= */

    public function redirect_default_dashboard() {
        if (!isset($_GET['page'])) {
            wp_safe_redirect(admin_url('admin.php?page=nextsafar-admin'));
            exit;
        }
    }

    /**
     * داشبورد اصلی
     */
    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die('شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array('response' => 403));
        }
        $this->safe_require('views/admin/dashboard.php');
    }

    /**
     * صفحه CPT (Content Manager)
     */
    public function render_cpt_page() {
        $content_manager = nextsafar()->content_manager;
        if (!$content_manager || !method_exists($content_manager, 'render_content_page_dynamic')) {
            wp_die('Content manager not available.', 'خطا', array('response' => 500));
        }
        $content_manager->render_content_page_dynamic();
    }

    /**
     * صفحه Taxonomy
     */
    public function render_cpt_taxonomy_page() {
        $this->safe_require('views/admin/pages/cpt-taxonomy.php');
    }

    /**
     * صفحه ویرایشگر پست
     */
    public function render_post_editor_page() {
        $this->safe_require('views/admin/pages/post-editor.php');
    }

    /**
     * صفحه Media
     */
    public function render_media_page() {
        if (!current_user_can('upload_files')) {
            wp_die('شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array('response' => 403));
        }
        $this->safe_require('views/admin/pages/media.php');
    }

    /**
     * صفحه Updates
     */
    public function render_updates_page() {
        if (!current_user_can('update_core')) {
            wp_die('شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array('response' => 403));
        }
        $this->safe_require('views/admin/pages/updates.php');
    }

    /**
     * Helper امن برای require فایل‌ها
     * مسیرها از ریشه پلاگین: nextsafar-webhook/{relative_path}
     */
    private function safe_require($relative_path) {
        // چند مسیر احتمالی برای پیدا کردن فایل
        $paths = array(
            NEXTSAFAR_PATH . $relative_path,
            NEXTSAFAR_ADMIN_PATH . $relative_path,
            NEXTSAFAR_PATH . 'inc/admin/' . $relative_path,
        );

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require $path;
                return;
            }
        }

        wp_die(
            'فایل view پیدا نشد: ' . esc_html($relative_path) .
            '<br><small>مسیرهای بررسی شده: ' . esc_html(implode(' | ', $paths)) . '</small>',
            'خطای سیستم',
            array('response' => 500)
        );
    }

    /* =========================================================
     *  Admin Title
     * ========================================================= */

    public function fix_admin_title($admin_title, $title) {
        if (!$this->is_nextsafar_page() && !isset($_GET['nextsafar_iframe'])) {
            return $admin_title ?: '';
        }

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        $site_name = get_bloginfo('name') ?: 'سایت';

        $titles = array(
            'nextsafar-admin'        => 'داشبورد',
            'nextsafar-post-editor'  => 'ویرایشگر',
            'nextsafar-cpt'          => 'محتوا',
            'nextsafar-cpt-taxonomy' => 'دسته‌بندی',
            'nextsafar-media'        => 'رسانه',
            'nextsafar-categories'   => 'دسته‌بندی‌ها',
            'nextsafar-posts'        => 'نوشته‌ها',
            'nextsafar-pages'        => 'برگه‌ها',
            'nextsafar-updates'      => 'بروزرسانی‌ها',
            'nextsafar-wp-wrapper'   => 'صفحه وردپرس',
        );

        $page_title = $titles[$page] ?? 'پنل مدیریت';

        // CPT
        if ('nextsafar-cpt' === $page && isset($_GET['post_type'])) {
            $pt_obj = get_post_type_object(sanitize_key($_GET['post_type']));
            if ($pt_obj && !empty($pt_obj->labels->name)) {
                $page_title = $pt_obj->labels->name;
            }
        }
        // Taxonomy
        elseif ('nextsafar-cpt-taxonomy' === $page && isset($_GET['taxonomy'])) {
            $tax_obj = get_taxonomy(sanitize_key($_GET['taxonomy']));
            if ($tax_obj && !empty($tax_obj->labels->name)) {
                $page_title = $tax_obj->labels->name;
            }
        }
        // Post Editor
        elseif ('nextsafar-post-editor' === $page && isset($_GET['post_type'])) {
            $pt_obj = get_post_type_object(sanitize_key($_GET['post_type']));
            if ($pt_obj && !empty($pt_obj->labels->singular_name)) {
                $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'add';
                $is_edit = ('edit' === $action && isset($_GET['post']));
                $page_title = $is_edit
                    ? 'ویرایش ' . $pt_obj->labels->singular_name
                    : 'افزودن ' . $pt_obj->labels->singular_name;
            }
        }

        // Taxonomy صفحات native
        global $pagenow;
        if (in_array($pagenow, array('edit-tags.php', 'term.php'), true)) {
            $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';
            if (!empty($taxonomy) && taxonomy_exists($taxonomy)) {
                $tax_obj = get_taxonomy($taxonomy);
                if ($tax_obj && !empty($tax_obj->labels->name)) {
                    $page_title = $tax_obj->labels->name;
                    if ('term.php' === $pagenow && !empty($tax_obj->labels->singular_name)) {
                        $page_title = 'ویرایش ' . $tax_obj->labels->singular_name;
                    }
                }
            }
        }

        if (empty($page_title)) {
            $page_title = 'پنل مدیریت';
        }

        return trim($page_title) . ' — ' . $site_name;
    }

    /* =========================================================
     *  Styles & JS Injection
     * ========================================================= */

public function inject_styles() {
    // ✅ در iframe
    if ($this->is_iframe()) {
        $this->print_iframe_styles();
        return;
    }
    
    // ✅ در صفحات NextSafar (خودشان استایل دارند) - اما NOT post editor
    if ($this->is_nextsafar_page() && !$this->is_post_editor_page()) {
        $this->print_nextsafar_page_styles();
        return;
    }
    
    // ✅ در بقیه صفحات وردپرس (مثل edit-comments.php و post.php)
    $this->print_wp_page_styles();
}

public function inject_sidebar_and_topbar_js() {
    // در iframe اجرا نشود
    if ($this->is_iframe()) return;
    
    // فقط در صفحات ادمین اجرا شود
    if (!is_admin()) return;
    
    // ✅ صفحات NextSafar خودشان سایدبار دارند
    // اما post.php و post-new.php صفحات native وردپرس هستند و نیاز به inject دارند
    if ($this->is_nextsafar_page() && !$this->is_post_editor_page()) {
        return;
    }
    
    // مسیر فایل‌های layout
    $sidebar_path = NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
    $topbar_path  = NEXTSAFAR_PATH . 'inc/admin/layout/header.php';
    
    if (!file_exists($sidebar_path) || !file_exists($topbar_path)) {
        error_log('[NextSafar] Layout files not found');
        return;
    }
    
    // Capture sidebar HTML
    ob_start();
    try {
        require $sidebar_path;
        $sidebar_html = ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        $sidebar_html = '';
        error_log('[NextSafar] Sidebar error: ' . $e->getMessage());
    }
    
    // Capture topbar HTML
    ob_start();
    try {
        require $topbar_path;
        $topbar_html = ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        $topbar_html = '';
        error_log('[NextSafar] Topbar error: ' . $e->getMessage());
    }
    
    if (empty($sidebar_html) && empty($topbar_html)) {
        return;
    }
    
    ?>
    <script id="nextsafar-inject-ui">
    (function() {
        'use strict';
        
        // جلوگیری از inject مجدد
        if (document.getElementById('nextsafar-injected-sidebar')) {
            return;
        }
        
        <?php if (!empty($sidebar_html)): ?>
        var sidebarDiv = document.createElement('div');
        sidebarDiv.id = 'nextsafar-injected-sidebar';
        sidebarDiv.innerHTML = <?php echo wp_json_encode($sidebar_html); ?>;
        document.body.insertBefore(sidebarDiv, document.body.firstChild);
        <?php endif; ?>
        
        <?php if (!empty($topbar_html)): ?>
        var topbarDiv = document.createElement('div');
        topbarDiv.id = 'nextsafar-injected-topbar';
        topbarDiv.innerHTML = <?php echo wp_json_encode($topbar_html); ?>;
        document.body.insertBefore(topbarDiv, document.body.firstChild);
        <?php endif; ?>
        
        // فعال‌سازی submenu ها
        if (typeof initNextsafarSubmenu === 'function') {
            initNextsafarSubmenu();
        }
        
        // فعال‌سازی hover
        var navItems = document.querySelectorAll('#nextsafar-injected-sidebar .nextsafar-nav-parent');
        navItems.forEach(function(item) {
            item.addEventListener('mouseenter', function() {
                var submenu = this.querySelector('.nextsafar-submenu');
                if (submenu) {
                    submenu.style.maxHeight = '500px';
                    submenu.classList.add('open');
                }
            });
            item.addEventListener('mouseleave', function() {
                var submenu = this.querySelector('.nextsafar-submenu');
                if (submenu && !this.classList.contains('has-active-child')) {
                    submenu.style.maxHeight = '0';
                    submenu.classList.remove('open');
                }
            });
        });
    })();
    </script>
    <?php
}

    private function print_iframe_styles() {
        ?>
        <style>
            #wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap, #adminmenu,
            #wpfooter, #screen-meta-links, #screen-meta, #contextual-help-link,
            #contextual-help-wrap {
                display: none !important;
                visibility: hidden !important;
            }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #f5f7fb !important;
            }
            html { margin-top: 0 !important; }
            #wpcontent {
                margin: 0 !important;
                margin-left: 0 !important;
                padding: 20px !important;
            }
            #wpbody, #wpbody-content { margin: 0 !important; padding: 0 !important; }
            #wpwrap { height: auto !important; }
            .wrap { margin: 0 !important; padding: 0 !important; max-width: 100% !important; }
            h1.wp-heading-inline, .wrap h1 {
                margin-top: 0 !important;
                color: #111827 !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            }
            .wp-list-table {
                border-radius: 8px !important;
                overflow: hidden !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
            }
            .button-primary { background: #2563eb !important; border-color: #2563eb !important; }
            .button-primary:hover { background: #1d4ed8 !important; border-color: #1d4ed8 !important; }
            a { color: #2563eb !important; }
            a:hover { color: #1d4ed8 !important; }
            body.nav-menus-php #wpcontent { margin-left: 0 !important; margin-right: 0 !important; }
            body.nav-menus-php #post-body.columns-2 { margin-right: 0 !important; }
            body.nav-menus-php #nav-menu-header { padding: 10px 15px !important; }
            body.nav-menus-php .menu-edit { margin: 0 !important; }
            body.nav-menus-php #menu-management { margin-top: 0 !important; }
            body.themes-php .wrap { padding: 20px !important; }
            body.themes-php .theme-browser .theme { margin-right: 16px !important; }
            @media screen and (max-width: 782px) {
                #wpcontent { padding: 10px !important; }
            }
        </style>
        <?php
    }

    private function print_nextsafar_page_styles() {
        ?>
        <style>
            html { margin-top: 0 !important; }
            #wpadminbar { display: none !important; }
            #adminmenumain, #adminmenuback, #adminmenuwrap { display: none !important; }
            #wpcontent { margin: 0 !important; padding: 0 !important; }
            #wpbody, #wpbody-content { margin: 0 !important; padding: 0 !important; }
            #wpfooter { display: none !important; }
        </style>
        <?php
    }

private function print_wp_page_styles() {
    $is_post_editor = $this->is_post_editor_page();
    ?>
    <style id="nextsafar-wp-page-styles">
    /* ═══ Reset Base ═══ */
    html { margin-top: 0 !important; }
    #wpadminbar { display: none !important; }
    #adminmenumain, #adminmenuback, #adminmenuwrap { display: none !important; }
    #wpfooter { display: none !important; }
    #screen-meta-links, #screen-meta, #contextual-help-link-wrap,
    #contextual-help-wrap, #contextual-help-link, #collapse-button {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* ═══ Main Layout ═══ */
    body:not(.iframe) #wpcontent {
        margin-left: 0 !important;
        margin-right: var(--ns-sidebar-width, 256px) !important;
        padding: 0 !important;
    }
    
    body:not(.iframe) #wpbody-content {
        margin: 0 !important;
        padding: 0 !important;
        padding-bottom: 40px !important;
    }
    
    <?php if ($is_post_editor): ?>
    /* ═══ Post Editor Special Layout ═══ */
    body.post-php #wpbody,
    body.post-new-php #wpbody {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    body.post-php #wpbody-content,
    body.post-new-php #wpbody-content {
        padding-top: calc(var(--ns-topbar-height, 60px) + 16px) !important;
        padding-right: 0 !important;
        padding-left: 0 !important;
    }
    
    /* Block Editor (Gutenberg) - Full Screen */
    body.block-editor-page #wpbody,
    body.block-editor-page #wpbody-content {
        padding-top: 0 !important;
        margin: 0 !important;
    }
    
    body.block-editor-page .interface-interface-skeleton {
        top: 0 !important;
    }
    
    /* Classic Editor adjustments */
    body.post-php #poststuff,
    body.post-new-php #poststuff {
        margin: 0 !important;
        padding: 20px !important;
    }
    
    body.post-php #post-body.columns-2,
    body.post-new-php #post-body.columns-2 {
        margin-right: 0 !important;
    }
    
    body.post-php #postbox-container-1,
    body.post-new-php #postbox-container-1 {
        margin-right: 0 !important;
    }
    <?php else: ?>
    /* ═══ Regular WP Pages ═══ */
    body:not(.iframe) #wpbody {
        margin: 0 !important;
        padding: 0 !important;
        padding-top: calc(var(--ns-topbar-height, 60px) + 16px) !important;
    }
    <?php endif; ?>
    
    /* ═══ Wrap & Content ═══ */
    body:not(.iframe) .wrap {
        margin: 10px 30px !important;
        padding: 0 !important;
        background: var(--ns-bg-sec, #f8fafc);
        min-height: calc(100vh - 140px);
        border-radius: var(--ns-radius-lg, 16px);
    }
    
    h1.wp-heading-inline, .wrap h1 {
        color: var(--ns-text-strong, #0f172a) !important;
        font-family: var(--ns-font-family, -apple-system, sans-serif) !important;
        font-weight: 700 !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    
    /* ═══ Tables & Forms ═══ */
    .wp-list-table {
        border-radius: var(--ns-radius, 12px) !important;
        overflow: hidden !important;
        box-shadow: var(--ns-shadow-sm) !important;
        border: 1px solid var(--ns-border, #e5e7eb) !important;
    }
    
    .button-primary {
        background: var(--ns-primary, #43abff) !important;
        border-color: var(--ns-primary, #43abff) !important;
        border-radius: var(--ns-radius-sm, 8px) !important;
        box-shadow: 0 2px 8px rgba(67, 171, 255, 0.25) !important;
        transition: all 0.2s ease !important;
    }
    
    .button-primary:hover {
        background: var(--ns-primary-dark, #3384c6) !important;
        border-color: var(--ns-primary-dark, #3384c6) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(67, 171, 255, 0.35) !important;
    }
    
    a { color: var(--ns-primary, #43abff); }
    a:hover { color: var(--ns-primary-dark, #3384c6) !important; }
    
    /* ═══ Notices ═══ */
    .wp-core-ui .notice.is-dismissible {
        padding-right: calc(var(--ns-sidebar-width, 256px) + 20px) !important;
    }
    
    /* ═══ Injected UI Positioning ═══ */
    #nextsafar-injected-sidebar {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        z-index: 99999 !important;
    }
    
    #nextsafar-injected-topbar {
        position: fixed !important;
        top: 10px !important;
        left: 30px !important;
        right: calc(var(--ns-sidebar-width, 256px) + 20px) !important;
        height: var(--ns-topbar-height, 60px) !important;
        z-index: 999 !important;
    }
    
    #nextsafar-injected-topbar .nextsafar-topbar {
        margin-inline: 0 !important;
    }
    
    /* ═══ Special Pages ═══ */
    body.edit-tags-php #wpbody,
    body.term-php #wpbody,
    body.edit-comments-php #wpbody {
        padding-top: calc(var(--ns-topbar-height, 60px) + 16px) !important;
    }
    
    /* ═══ Responsive ═══ */
    @media (max-width: 900px) {
        body:not(.iframe) #wpcontent {
            margin-right: 0 !important;
        }
        
        #nextsafar-injected-topbar {
            right: 10px !important;
            left: 10px !important;
            top: 10px !important;
        }
        
        #nextsafar-injected-sidebar {
            transform: translateX(100%);
            transition: transform 0.25s ease;
        }
        
        #nextsafar-injected-sidebar.open {
            transform: translateX(0);
        }
        
        body.post-php #wpbody-content,
        body.post-new-php #wpbody-content {
            padding-top: calc(var(--ns-topbar-height, 60px) + 10px) !important;
        }
    }
    
    @media (max-width: 600px) {
        #nextsafar-injected-topbar {
            right: 5px !important;
            left: 5px !important;
        }
    }
    </style>
    <?php
}

    /* =========================================================
     *  AJAX Handlers
     * ========================================================= */

    public function ajax_global_search() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nextsafar_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'), 403);
        }

        $user_id = get_current_user_id();
        $rate_limit_key = 'nextsafar_search_rate_' . $user_id;
        $request_count = (int) get_transient($rate_limit_key);

        if ($request_count >= 120) {
            wp_send_json_error(array('message' => 'Too many requests. Please wait a moment.'), 429);
        }

        set_transient($rate_limit_key, $request_count + 1, MINUTE_IN_SECONDS);

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';

        if (strlen($query) < 2) {
            wp_send_json_success(array('results' => array()));
        }

        $cache_key = 'nextsafar_search_' . md5($query . $user_id);
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            wp_send_json_success(array('results' => $cached, 'cached' => true));
        }

        $max_per_type = 3;
        $type_labels = array(
            'post'       => 'مقاله',
            'page'       => 'برگه',
            'tour'       => 'تور',
            'visa'       => 'ویزا',
            'attachment' => 'رسانه',
            'category'   => 'دسته‌بندی',
            'user'       => 'کاربر',
        );

        $fulltext_results = $this->search_with_fulltext($query, $max_per_type, $type_labels);
        $results = !empty($fulltext_results)
            ? $fulltext_results
            : $this->search_with_like($query, $max_per_type, $type_labels);

        $results = array_slice($results, 0, 15);
        set_transient($cache_key, $results, 15 * MINUTE_IN_SECONDS);

        wp_send_json_success(array('results' => $results));
    }

    private function search_with_fulltext($query, $max_per_type, $type_labels) {
        global $wpdb;

        $has_fulltext = $wpdb->get_var("SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = 'post_title_fulltext'");
        if (!$has_fulltext) {
            return array();
        }

        $post_types = array('post', 'page');
        if (post_type_exists('tour')) $post_types[] = 'tour';
        if (post_type_exists('visa')) $post_types[] = 'visa';

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $search_query = $wpdb->esc_like($query);
        $limit = $max_per_type * count($post_types);
        $params = array_merge($post_types, array($search_query, $search_query, $limit));

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type,
                MATCH(post_title) AGAINST(%s IN BOOLEAN MODE) as relevance
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
                AND post_status IN ('publish', 'draft', 'pending')
                AND MATCH(post_title) AGAINST(%s IN BOOLEAN MODE)
            ORDER BY relevance DESC
            LIMIT %d",
            ...$params
        ));

        $results = array();
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if (count($results) >= $max_per_type * 2) break;
                $results[] = array(
                    'type'       => $post->post_type,
                    'type_label' => $type_labels[$post->post_type] ?? ucfirst($post->post_type),
                    'title'      => $post->post_title,
                    'url'        => get_edit_post_link($post->ID, 'raw'),
                );
            }
        }
        return $results;
    }

    private function search_with_like($query, $max_per_type, $type_labels) {
        global $wpdb;

        $results = array();
        $like_query = '%' . $wpdb->esc_like($query) . '%';

        $post_types = array('post', 'page');
        if (post_type_exists('tour')) $post_types[] = 'tour';
        if (post_type_exists('visa')) $post_types[] = 'visa';

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $limit_posts = $max_per_type * count($post_types);
        $params = array_merge($post_types, array($like_query, $limit_posts));

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
                AND post_status IN ('publish', 'draft', 'pending')
                AND post_title LIKE %s
            ORDER BY post_modified DESC
            LIMIT %d",
            ...$params
        ));

        if (!empty($posts)) {
            foreach ($posts as $post) {
                if (count($results) >= $max_per_type * 2) break;
                $results[] = array(
                    'type'       => $post->post_type,
                    'type_label' => $type_labels[$post->post_type] ?? ucfirst($post->post_type),
                    'title'      => $post->post_title,
                    'url'        => get_edit_post_link($post->ID, 'raw'),
                );
            }
        }

        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_title LIKE %s
            ORDER BY post_modified DESC
            LIMIT %d",
            $like_query,
            $max_per_type
        ));

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $results[] = array(
                    'type'       => 'attachment',
                    'type_label' => 'رسانه',
                    'title'      => $attachment->post_title,
                    'url'        => admin_url('admin.php?page=nextsafar-media'),
                );
            }
        }

        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT t.term_id, t.name
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'category' AND t.name LIKE %s
            LIMIT %d",
            $like_query,
            $max_per_type
        ));

        if (!empty($categories)) {
            foreach ($categories as $cat) {
                $results[] = array(
                    'type'       => 'category',
                    'type_label' => 'دسته‌بندی',
                    'title'      => $cat->name,
                    'url'        => admin_url('admin.php?page=nextsafar-categories'),
                );
            }
        }

        if (current_user_can('list_users')) {
            $users = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, display_name, user_email
                FROM {$wpdb->users}
                WHERE user_login LIKE %s OR user_email LIKE %s OR display_name LIKE %s
                LIMIT %d",
                $like_query, $like_query, $like_query, $max_per_type
            ));

            if (!empty($users)) {
                foreach ($users as $user) {
                    $results[] = array(
                        'type'       => 'user',
                        'type_label' => 'کاربر',
                        'title'      => $user->display_name . ' (' . $user->user_email . ')',
                        'url'        => admin_url('user-edit.php?user_id=' . $user->ID),
                    );
                }
            }
        }

        return $results;
    }

    public static function create_fulltext_index() {
        global $wpdb;
        $table = $wpdb->posts;
        $index_name = 'post_title_fulltext';
        $exists = $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = '{$index_name}'");
        if (!$exists) {
            $wpdb->query("ALTER TABLE {$table} ADD FULLTEXT INDEX {$index_name} (post_title)");
        }
    }

    public function ajax_add_term() {
        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nextsafar_post_editor')) {
            wp_send_json_error(array('message' => 'Security check failed.'), 403);
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            wp_send_json_error(array('message' => 'Invalid taxonomy.'), 400);
        }
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Name is required.'), 400);
        }

        $result = wp_insert_term($name, $taxonomy);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        wp_send_json_success(array(
            'term_id' => $result['term_id'],
            'message' => 'دسته‌بندی با موفقیت اضافه شد.',
        ));
    }

    public function yoast_ajax_proxy() {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        if (strpos($action, 'wpseo') !== 0 && strpos($action, 'yoast') !== 0) {
            return;
        }

        global $pagenow, $current_screen;
        $post_id = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
        $post_type = isset($_REQUEST['post_type']) ? sanitize_key($_REQUEST['post_type']) : 'post';

        $pagenow = ($post_id > 0) ? 'post.php' : 'post-new.php';
        $current_screen = WP_Screen::get($post_type);
        set_current_screen($post_type);

        if ($post_id > 0) {
            $post_obj = get_post($post_id);
            if ($post_obj) {
                $GLOBALS['post'] = $post_obj;
                setup_postdata($post_obj);
            }
        }
    }

    /* =========================================================
     *  Screen Options
     * ========================================================= */

    public function enable_screen_options() {
        $screen = get_current_screen();
        if (!$screen) return;
        if (strpos($screen->id, 'nextsafar') === false) return;

        $list_pages = array(
            'nextsafar-admin_page_nextsafar-posts',
            'nextsafar-admin_page_nextsafar-pages',
            'nextsafar-admin_page_nextsafar-media',
            'nextsafar-admin_page_nextsafar-categories',
        );

        $is_list_page = in_array($screen->id, $list_pages, true)
            || strpos($screen->id, 'nextsafar-cpt') !== false;

        if (!$is_list_page) {
            return;
        }

        add_screen_option('per_page', array(
            'label'   => __('تعداد در هر صفحه', 'nextsafar-admin'),
            'default' => 20,
            'option'  => 'nextsafar_items_per_page',
        ));

        wp_enqueue_script('common');
        wp_enqueue_script('wp-ajax-response');
    }

    public function save_screen_option($status, $option, $value) {
        if ('nextsafar_items_per_page' === $option) {
            $value = absint($value);
            if ($value < 5) $value = 5;
            if ($value > 200) $value = 200;
            return $value;
        }
        return $status;
    }

    public function ajax_save_screen_option() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nextsafar_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $option = isset($_POST['option']) ? sanitize_key($_POST['option']) : '';
        $value = isset($_POST['value']) ? absint($_POST['value']) : 0;

        if ('nextsafar_items_per_page' === $option) {
            if ($value < 5) $value = 5;
            if ($value > 200) $value = 200;
            update_user_meta(get_current_user_id(), $option, $value);
        }

        wp_send_json_success(array('message' => 'Saved'));
    }

    public function ajax_get_screen_options() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nextsafar_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $screen = get_current_screen();

        if (!$screen && isset($_POST['screen_id'])) {
            $screen_id = sanitize_text_field(wp_unslash($_POST['screen_id']));
            $screen = WP_Screen::get($screen_id);
            if ($screen) {
                set_current_screen($screen_id);
            }
        }

        if (!$screen && isset($_POST['page'])) {
            $page = sanitize_key($_POST['page']);
            $screen_id = 'toplevel_page_' . $page;
            if (strpos($page, 'nextsafar-admin_page_') === false && strpos($page, 'toplevel_page_') === false) {
                $screen_id = 'nextsafar-admin_page_' . $page;
            }
            $screen = WP_Screen::get($screen_id);
        }

        if (!$screen) {
            $html = $this->get_screen_options_fallback();
            wp_send_json_success(array('html' => $html));
            return;
        }

        $list_pages = array(
            'nextsafar-admin_page_nextsafar-posts',
            'nextsafar-admin_page_nextsafar-pages',
            'nextsafar-admin_page_nextsafar-media',
            'nextsafar-admin_page_nextsafar-categories',
        );

        $is_list_page = in_array($screen->id, $list_pages, true)
            || strpos($screen->id, 'nextsafar-cpt') !== false;
        $is_dashboard = 'toplevel_page_nextsafar-admin' === $screen->id;

        if ($is_list_page) {
            $html = $this->get_list_page_options_html();
        } elseif ($is_dashboard) {
            $html = $this->get_dashboard_options_html();
        } else {
            $html = $this->get_default_options_html();
        }

        wp_send_json_success(array('html' => $html));
    }

    private function get_list_page_options_html() {
        $current_per_page = absint(get_user_meta(get_current_user_id(), 'nextsafar_items_per_page', true));
        if ($current_per_page < 5 || $current_per_page > 200) {
            $current_per_page = 20;
        }
        $nonce = wp_create_nonce('screen-options-nonce');

        ob_start();
        ?>
        <form id="nextsafar-screen-options-form" class="nextsafar-screen-options-form">
            <fieldset>
                <legend style="font-weight:600; margin-bottom:12px; color:var(--ns-text, #111827);">
                    📊 تنظیمات صفحه‌بندی
                </legend>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <label for="nextsafar_items_per_page" style="font-size:13px; color:var(--ns-text-muted, #6b7280);">
                        تعداد در هر صفحه:
                    </label>
                    <input type="number" step="1" min="5" max="200"
                           id="nextsafar_items_per_page" name="per_page"
                           value="<?php echo esc_attr($current_per_page); ?>"
                           style="width:80px; padding:6px 10px; border:1px solid var(--ns-border, #e5e7eb); border-radius:6px; font-size:13px;">
                    <span style="color:var(--ns-text-muted, #6b7280); font-size:13px;">آیتم</span>
                </div>
                <small style="display:block; margin-top:8px; color:var(--ns-text-muted, #6b7280); font-size:11px;">
                    💡 مقادیر بین ۵ تا ۲۰۰ مجاز است
                </small>
                <input type="hidden" name="wp_screen_options[option]" value="nextsafar_items_per_page">
                <input type="hidden" name="wp_screen_options[value]" value="<?php echo esc_attr($current_per_page); ?>">
                <input type="hidden" name="screenoptionnonce" value="<?php echo esc_attr($nonce); ?>">
            </fieldset>
            <p class="submit" style="margin-top:16px; margin-bottom:0;">
                <button type="submit" class="button button-primary" style="width:100%;">
                    ذخیره تنظیمات
                </button>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    private function get_dashboard_options_html() {
        $core = nextsafar();
        $widgets = array();
        $settings = array();

        if (isset($core->widgets) && method_exists($core->widgets, 'get_widgets')) {
            $widgets = $core->widgets->get_widgets();
        }
        if (isset($core->widgets) && method_exists($core->widgets, 'get_user_widget_settings')) {
            $settings = $core->widgets->get_user_widget_settings();
        }

        ob_start();
        ?>
        <form id="nextsafar-screen-options-form" class="nextsafar-screen-options-form">
            <fieldset>
                <legend style="font-weight:600; margin-bottom:12px; color:var(--ns-text, #111827);">
                    🎯 ویجت‌های داشبورد
                </legend>
                <p style="font-size:12px; color:var(--ns-text-muted, #6b7280); margin-bottom:12px;">
                    ویجت‌هایی که می‌خواهید در داشبورد نمایش داده شوند:
                </p>
                <?php if (empty($widgets)) : ?>
                    <p style="color:var(--ns-text-muted, #6b7280); font-size:13px;">
                        ویجتی برای تنظیم وجود ندارد.
                    </p>
                <?php else : ?>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <?php foreach ($widgets as $widget_id => $widget) :
                            $enabled = !isset($settings[$widget_id])
                                || (isset($settings[$widget_id]['enabled']) && $settings[$widget_id]['enabled']);
                        ?>
                            <label style="display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; cursor:pointer; transition:background 0.2s; margin:0;">
                                <input type="checkbox" name="widgets[]"
                                       value="<?php echo esc_attr($widget_id); ?>"
                                       <?php checked($enabled); ?>
                                       style="accent-color:var(--ns-primary, #43abff); width:16px; height:16px;">
                                <span style="font-size:13px; color:var(--ns-text, #111827);">
                                    <?php echo esc_html($widget->get_title()); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>
            <p class="submit" style="margin-top:16px; margin-bottom:0;">
                <button type="submit" class="button button-primary" style="width:100%;">
                    ذخیره تنظیمات
                </button>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    private function get_default_options_html() {
        ob_start();
        ?>
        <div style="text-align:center; padding:20px; color:var(--ns-text-muted, #6b7280);">
            <div style="font-size:32px; margin-bottom:8px;">⚙️</div>
            <p style="font-size:13px; margin:0;">
                تنظیمات صفحه‌ای برای این صفحه وجود ندارد.
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_screen_options_fallback() {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        if (strpos($page, 'nextsafar') !== false) {
            if ($page === 'nextsafar-admin') {
                return $this->get_dashboard_options_html();
            } elseif (
                strpos($page, 'nextsafar-posts') !== false ||
                strpos($page, 'nextsafar-pages') !== false ||
                strpos($page, 'nextsafar-media') !== false ||
                strpos($page, 'nextsafar-cpt') !== false
            ) {
                return $this->get_list_page_options_html();
            }
        }

        return $this->get_default_options_html();
    }

    public function ajax_save_dashboard_widgets() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nextsafar_dashboard_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $user_id = get_current_user_id();
        $enabled_widgets = isset($_POST['widgets']) ? array_map('sanitize_key', (array) $_POST['widgets']) : array();

        $core = nextsafar();
        $all_widgets = array();
        if (isset($core->widgets) && method_exists($core->widgets, 'get_widgets')) {
            $all_widgets = $core->widgets->get_widgets();
        }

        $settings = array();
        foreach ($all_widgets as $widget_id => $widget) {
            $settings[$widget_id] = array(
                'enabled' => in_array($widget_id, $enabled_widgets, true),
            );
        }

        update_user_meta($user_id, 'nextsafar_dashboard_widgets', $settings);
        wp_send_json_success(array('message' => 'Widget settings saved'));
    }
}