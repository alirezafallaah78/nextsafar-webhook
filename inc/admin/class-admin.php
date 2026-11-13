<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Admin {

public function init() {
    add_filter( 'show_admin_bar', '__return_false' );
    add_action( 'load-index.php', array( $this, 'redirect_default_dashboard' ) );
    add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 1 );
    add_action( 'admin_menu', array( $this, 'register_media_page' ) );
    add_action( 'admin_menu', array( $this, 'register_updates_page' ) );
    
    add_action( 'admin_head', array( $this, 'inject_styles' ), 9999 );
    add_action( 'admin_footer', array( $this, 'inject_sidebar_and_topbar_js' ), 999 );
    add_filter( 'admin_title', array( $this, 'fix_admin_title' ), 10, 2 );
    add_action( 'wp_ajax_nextsafar_global_search', array( $this, 'ajax_global_search' ) );
    add_action( 'wp_ajax_nextsafar_add_term_ajax', array( $this, 'ajax_add_term' ) );
    add_action( 'admin_init', array( $this, 'yoast_ajax_proxy' ), 1 );
    add_action( 'current_screen', array( $this, 'enable_screen_options' ) );
    add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
    
    // 🆕 AJAX جدید برای Screen Options
    add_action( 'wp_ajax_nextsafar_get_screen_options', array( $this, 'ajax_get_screen_options' ) );
    add_action( 'wp_ajax_nextsafar_save_dashboard_widgets', array( $this, 'ajax_save_dashboard_widgets' ) );
add_action( 'current_screen', array( $this, 'set_page_title_for_hidden_pages' ) );

}


// این متد جدید را به کلاس NextSafar_Admin اضافه کنید:
public function set_page_title_for_hidden_pages() {
    global $title, $plugin_page;
    
    // اگر عنوان خالی است و در یکی از صفحات مخفی پلاگین هستیم
    if ( empty( $title ) && ! empty( $plugin_page ) && strpos( $plugin_page, 'nextsafar' ) !== false ) {
        $titles = array(
            'nextsafar-admin' => 'داشبورد',
            'nextsafar-post-editor' => 'ویرایشگر',
            'nextsafar-cpt' => 'محتوا',
            'nextsafar-cpt-taxonomy' => 'دسته‌بندی',
            'nextsafar-media' => 'رسانه',
            'nextsafar-categories' => 'دسته‌بندی‌ها',
            'nextsafar-posts' => 'نوشته‌ها',
            'nextsafar-pages' => 'برگه‌ها',
            'nextsafar-updates' => 'بروزرسانی‌ها',
            'nextsafar-wp-wrapper' => 'صفحه وردپرس',
        );
        
        $title = isset( $titles[ $plugin_page ] ) ? $titles[ $plugin_page ] : 'پنل مدیریت';
    }
}
    private function is_iframe() {
        return isset( $_GET['nextsafar_iframe'] );
    }

    /**
     * 🎯 اصلاح شده: تشخیص صفحات NextSafar
     */
    private function is_nextsafar_page() {
        // اولویت ۱: بررسی $_GET['page'] (همیشه در دسترس)
        if ( isset( $_GET['page'] ) ) {
            $page = sanitize_key( $_GET['page'] );
            if ( strpos( $page, 'nextsafar' ) !== false ) {
                return true;
            }
        }
        
        // اولویت ۲: بررسی screen
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'nextsafar' ) !== false ) {
            return true;
        }
        
        return false;
    }

    /**
     * 🎯 تشخیص صفحات Post Editor (post.php, post-new.php)
     */
    private function is_post_editor_page() {
        global $pagenow;
        return in_array( $pagenow, array( 'post.php', 'post-new.php' ), true );
    }

    /**
     * 🎯 رفع خطای Deprecated: strip_tags(): Passing null
     */
    public function fix_admin_title( $admin_title, $title ) {
        // اگر صفحه NextSafar نیست و iframe هم نیست، عنوان پیش‌فرض وردپرس را برگردان
        if ( ! $this->is_nextsafar_page() && ! isset( $_GET['nextsafar_iframe'] ) ) {
            return $admin_title ?: '';  // ✅ جلوگیری از null
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $site_name = get_bloginfo( 'name' ) ?: 'سایت';  // ✅ fallback برای نام سایت
        
        // نقشه عنوان‌های ثابت
        $titles = array(
            'nextsafar-admin' => 'داشبورد',
            'nextsafar-post-editor' => 'ویرایشگر',
            'nextsafar-cpt' => 'محتوا',
            'nextsafar-cpt-taxonomy' => 'دسته‌بندی',
            'nextsafar-media' => 'رسانه',
            'nextsafar-categories' => 'دسته‌بندی‌ها',
            'nextsafar-posts' => 'نوشته‌ها',
            'nextsafar-pages' => 'برگه‌ها',
            'nextsafar-updates' => 'بروزرسانی‌ها',
            'nextsafar-wp-wrapper' => 'صفحه وردپرس',
        );

        // عنوان پیش‌فرض
        $page_title = $titles[ $page ] ?? 'پنل مدیریت';

        // 🆕 صفحه CPT با post_type
        if ( 'nextsafar-cpt' === $page && isset( $_GET['post_type'] ) ) {
            $post_type_key = sanitize_key( $_GET['post_type'] );
            $pt_obj = get_post_type_object( $post_type_key );
            if ( $pt_obj && ! empty( $pt_obj->labels->name ) ) {
                $page_title = $pt_obj->labels->name;
            }
        }
        // 🆕 صفحه Taxonomy با taxonomy
        elseif ( 'nextsafar-cpt-taxonomy' === $page && isset( $_GET['taxonomy'] ) ) {
            $taxonomy_key = sanitize_key( $_GET['taxonomy'] );
            $tax_obj = get_taxonomy( $taxonomy_key );
            if ( $tax_obj && ! empty( $tax_obj->labels->name ) ) {
                $page_title = $tax_obj->labels->name;
            }
        }
        // 🆕 صفحه Post Editor با post_type
        elseif ( 'nextsafar-post-editor' === $page && isset( $_GET['post_type'] ) ) {
            $post_type_key = sanitize_key( $_GET['post_type'] );
            $pt_obj = get_post_type_object( $post_type_key );
            if ( $pt_obj && ! empty( $pt_obj->labels->singular_name ) ) {
                $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'add';
                $is_edit = ( 'edit' === $action && isset( $_GET['post'] ) );
                $page_title = $is_edit
                    ? 'ویرایش ' . $pt_obj->labels->singular_name
                    : 'افزودن ' . $pt_obj->labels->singular_name;
            }
        }

        // 🆕 برای صفحات Taxonomy وردپرس (edit-tags.php, term.php)
        global $pagenow;
        if ( in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) ) {
            $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
            if ( ! empty( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {
                $tax_obj = get_taxonomy( $taxonomy );
                if ( $tax_obj && ! empty( $tax_obj->labels->name ) ) {
                    $page_title = $tax_obj->labels->name;
                    if ( 'term.php' === $pagenow && ! empty( $tax_obj->labels->singular_name ) ) {
                        $page_title = 'ویرایش ' . $tax_obj->labels->singular_name;
                    }
                }
            }
        }

        // ✅ اطمینان از اینکه page_title هرگز null یا خالی نیست
        if ( empty( $page_title ) ) {
            $page_title = 'پنل مدیریت';
        }

        // ✅ ساخت عنوان نهایی با بررسی null
        return trim( $page_title ) . ' — ' . $site_name;
    }

    public function register_admin_menu() {
        add_menu_page(
            'NextSafar',
            'NextSafar',
            'manage_options',
            'nextsafar-admin',
            array( $this, 'render_admin_page' ),
            'dashicons-admin-site',
            2
        );

        add_submenu_page(
            null,
            'CPT Listing',
            'CPT Listing',
            'manage_options',
            'nextsafar-cpt',
            array( $this, 'render_cpt_page' )
        );

        add_submenu_page(
            null,
            'CPT Taxonomy',
            'CPT Taxonomy',
            'manage_options',
            'nextsafar-cpt-taxonomy',
            array( $this, 'render_cpt_taxonomy_page' )
        );

        add_submenu_page(
            null,
            'Post Editor',
            'Post Editor',
            'manage_options',
            'nextsafar-post-editor',
            array( $this, 'render_post_editor_page' )
        );
    }

    public function render_cpt_page() {
        $content_manager = nextsafar()->content_manager;
        if ( ! $content_manager || ! method_exists( $content_manager, 'render_content_page_dynamic' ) ) {
            wp_die( 'Content manager not available.', 'خطا', array( 'response' => 500 ) );
        }
        $content_manager->render_content_page_dynamic();
    }

    public function render_post_editor_page() {
        $view_file = NEXTSAFAR_PATH . 'views/admin/pages/post-editor.php';
        if ( file_exists( $view_file ) ) {
            require $view_file;
        } else {
            wp_die( 'Post editor view file not found.', 'خطا', array( 'response' => 500 ) );
        }
    }

    public function render_cpt_taxonomy_page() {
        $view_file = NEXTSAFAR_PATH . 'views/admin/pages/cpt-taxonomy.php';
        if ( file_exists( $view_file ) ) {
            require $view_file;
        } else {
            wp_die( 'View file not found: cpt-taxonomy.php' );
        }
    }

    public function register_media_page() {
        add_submenu_page(
            'nextsafar-admin',
            'Media',
            'Media',
            'manage_options',
            'nextsafar-media',
            array( $this, 'render_media_page' )
        );
    }

    public function register_updates_page() {
        add_submenu_page(
            'nextsafar-admin',
            'Updates',
            'Updates',
            'update_core',
            'nextsafar-updates',
            array( $this, 'render_updates_page' )
        );
    }

    /**
     * ✅ همه کاربران (نویسنده، ویرایشگر، ادمین) به داشبورد بروند
     */
    public function redirect_default_dashboard() {
        if ( ! isset( $_GET['page'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=nextsafar-admin' ) );
            exit;
        }
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) { // نویسنده/ویرایشگر/مشارکت‌کننده هم edit_posts دارند
            wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
        }
        require NEXTSAFAR_PATH . 'views/admin/dashboard.php';
    }

    public function render_media_page() {
        require NEXTSAFAR_PATH . 'views/admin/pages/media.php';
    }

    public function render_updates_page() {
        require NEXTSAFAR_PATH . 'views/admin/pages/updates.php';
    }

    /**
     * 🎯 فقط CSS - بدون HTML
     */
    public function inject_styles() {
        if ( $this->is_iframe() ) {
            $this->print_iframe_styles();
            return;
        }

        if ( $this->is_nextsafar_page() ) {
            $this->print_nextsafar_page_styles();
            return;
        }

        // برای تمام صفحات دیگر وردپرس (شامل post.php و post-new.php)
        $this->print_wp_page_styles();
    }

    /**
     * 🎯 Inject سایدبار و تاپبار با JavaScript در admin_footer
     * این روش مطمئن‌تر از admin_head است چون HTML در body قرار می‌گیرد
     */
    public function inject_sidebar_and_topbar_js() {
        if ( $this->is_iframe() ) return;
        if ( $this->is_nextsafar_page() ) return; // صفحات NextSafar خودشان سایدبار/تاپبار دارند

        // Capture sidebar HTML
        ob_start();
        require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
        $sidebar_html = ob_get_clean();

        // Capture topbar HTML
        ob_start();
        require NEXTSAFAR_PATH . 'inc/admin/layout/header.php';
        $topbar_html = ob_get_clean();

        ?>
        <script id="nextsafar-inject-ui">
        (function() {
            // Inject Sidebar
            var sidebarDiv = document.createElement('div');
            sidebarDiv.id = 'nextsafar-injected-sidebar';
            sidebarDiv.innerHTML = <?php echo wp_json_encode( $sidebar_html ); ?>;
            document.body.insertBefore(sidebarDiv, document.body.firstChild);

            // Inject Topbar
            var topbarDiv = document.createElement('div');
            topbarDiv.id = 'nextsafar-injected-topbar';
            topbarDiv.innerHTML = <?php echo wp_json_encode( $topbar_html ); ?>;
            document.body.insertBefore(topbarDiv, document.body.firstChild);
            
            // Initialize submenu flyout after injection
            if (typeof initNextsafarSubmenu === 'function') {
                initNextsafarSubmenu();
            }
        })();
        </script>
        <?php
    }

    private function print_iframe_styles() {
        ?>
        <style>
            /* 🚫 مخفی کردن کامل سایدبار، ادمین بار و فوتر وردپرس */
            #wpadminbar,
            #adminmenumain,
            #adminmenuback,
            #adminmenuwrap,
            #adminmenu,
            #wpfooter,
            #screen-meta-links,
            #screen-meta,
            #contextual-help-link,
            #contextual-help-wrap {
                display: none !important;
                visibility: hidden !important;
            }

            /* 🚫 مخفی کردن در همه body class های ممکن */
            body.wp-admin #wpadminbar,
            body.wp-admin #adminmenumain,
            body.wp-admin #adminmenuback,
            body.wp-admin #adminmenuwrap,
            body.wp-admin #wpfooter,
            body.nav-menus-php #adminmenumain,
            body.nav-menus-php #wpadminbar,
            body.nav-menus-php #wpfooter,
            body.themes-php #adminmenumain,
            body.themes-php #wpadminbar,
            body.themes-php #wpfooter,
            body.users-php #adminmenumain,
            body.users-php #wpadminbar,
            body.plugins-php #adminmenumain,
            body.plugins-php #wpadminbar {
                display: none !important;
                visibility: hidden !important;
            }

            /* 🎯 تنظیمات پایه iframe */
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #f5f7fb !important;
            }

            html {
                margin-top: 0 !important;
            }

            /* 📐 اصلاح margin و padding محتوای اصلی */
            #wpcontent {
                margin: 0 !important;
                margin-left: 0 !important;
                padding: 20px !important;
            }

            #wpbody,
            #wpbody-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            #wpwrap {
                height: auto !important;
            }

            .wrap {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }

            /* 📝 هدرهای صفحه */
            h1.wp-heading-inline,
            .wrap h1 {
                margin-top: 0 !important;
                color: #111827 !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            }

            /* 📋 جدول‌ها */
            .wp-list-table {
                border-radius: 8px !important;
                overflow: hidden !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
            }

            /* 🎨 دکمه‌ها و لینک‌ها */
            .button-primary {
                background: #2563eb !important;
                border-color: #2563eb !important;
            }

            .button-primary:hover {
                background: #1d4ed8 !important;
                border-color: #1d4ed8 !important;
            }

            a {
                color: #2563eb !important;
            }

            a:hover {
                color: #1d4ed8 !important;
            }

            /* 🎯 اصلاح ویژه برای صفحه فهرست‌ها (nav-menus.php) */
            body.nav-menus-php #wpcontent {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            body.nav-menus-php #post-body.columns-2 {
                margin-right: 0 !important;
            }

            body.nav-menus-php #nav-menu-header {
                padding: 10px 15px !important;
            }

            body.nav-menus-php .menu-edit {
                margin: 0 !important;
            }

            body.nav-menus-php #menu-management {
                margin-top: 0 !important;
            }

            /* 🎯 اصلاح ویژه برای صفحه پوسته‌ها (themes.php) */
            body.themes-php .wrap {
                padding: 20px !important;
            }

            body.themes-php .theme-browser .theme {
                margin-right: 16px !important;
            }

            /* 📱 Responsive */
            @media screen and (max-width: 782px) {
                #wpcontent {
                    padding: 10px !important;
                }
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
            #wpbody { margin: 0 !important; padding: 0 !important; }
            #wpbody-content { margin: 0 !important; padding: 0 !important; }
            #wpfooter { display: none !important; }
        </style>
        <?php
    }

private function print_wp_page_styles() {
    ?>
    <style>
        html { margin-top: 0 !important; }
        #wpadminbar { display: none !important; }
        #adminmenumain, #adminmenuback, #adminmenuwrap { display: none !important; }

        body:not(.iframe) #wpcontent {
            margin-left: 0 !important;
            margin-right: 260px !important;
            padding: 0 !important;
        }
        body:not(.iframe) #wpbody {
            margin: 0 !important;
            padding: 0 !important;
            padding-top: 82px !important;
        }
        #wpbody-content { margin: 0 !important; padding: 0 !important; padding-bottom: 40px !important; }
        #wpfooter { display: none !important; }

        body:not(.iframe) .wrap {
            margin: 10px 30px !important;
            padding: 0 !important;
            background: #f5f7fb;
            min-height: calc(100vh - 140px);
        }

        h1.wp-heading-inline,
        .wrap h1 {
            color: #111827 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            font-weight: 500 !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* ✅ مخفی کردن Screen Options اصلی وردپرس */
        #screen-meta-links,
        #screen-meta,
        #contextual-help-link-wrap,
        #contextual-help-wrap,
        #contextual-help-link {
            display: none !important;
            visibility: hidden !important;
        }

        .wp-list-table { border-radius: 8px !important; overflow: hidden !important; box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important; border: 1px solid #e5e7eb !important; }
        .button-primary { background: #43abff !important; border-color: #43abff !important; border-radius: 8px !important; }
        .button-primary:hover { background: #3384c6 !important; border-color: #3384c6 !important; }
        a { color: #43abff; }
        a:hover { color: #3384c6 !important; }
        #collapse-button { display: none !important; }

        #nextsafar-injected-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 99999;
        }

        #nextsafar-injected-topbar {
            position: absolute !important;
            top: 10px !important;
            left: 30px !important;
            right: 290px !important;
            height: var(--ns-topbar-height, 62px) !important;
            z-index: 999 !important;
        }

        #nextsafar-injected-topbar .nextsafar-topbar {
            margin-inline: 0 !important;
        }

        .wp-core-ui .notice.is-dismissible {
            padding-right: 270px !important;
        }

        body.edit-tags-php #wpbody,
        body.term-php #wpbody,
        body.post-new-php #wpbody,
        body.post-php #wpbody,
        body.edit-comments-php #wpbody {
            padding-top: 82px !important;
        }

        /* ✅ دکمه Screen Options سفارشی در topbar */
        .topbar-screen-options {
            position: relative;
        }

        .screen-options-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--ns-border, #e5e7eb);
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .screen-options-btn:hover {
            background: var(--ns-primary-light-2, rgba(67, 171, 255, 0.15));
            border-color: var(--ns-primary, #43abff);
        }

        .screen-options-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: 320px;
            background: #fff;
            border: 1px solid var(--ns-border, #e5e7eb);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.2s ease;
        }

        .screen-options-dropdown.is-open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .screen-options-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--ns-border, #e5e7eb);
            background: #f9fafb;
            border-radius: 12px 12px 0 0;
        }

        .screen-options-content {
            padding: 16px 18px;
            max-height: 400px;
            overflow-y: auto;
        }

        .screen-options-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            color: #6b7280;
        }

        @media (max-width: 900px) {
            body:not(.iframe) #wpcontent { margin-right: 0 !important; }
            #nextsafar-injected-topbar { right: 0; left: 0; top: 0; }
            #nextsafar-injected-sidebar { transform: translateX(100%); transition: transform 0.25s ease; }
            #nextsafar-injected-sidebar.open { transform: translateX(0); }
        }
    </style>
    <?php
}

    /**
     * 🆕 AJAX: ذخیره Screen Option
     */
    public function ajax_save_screen_option() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $option = isset( $_POST['option'] ) ? sanitize_key( $_POST['option'] ) : '';
        $value  = isset( $_POST['value'] ) ? absint( $_POST['value'] ) : 0;

        if ( 'nextsafar_items_per_page' === $option ) {
            if ( $value < 5 ) $value = 5;
            if ( $value > 200 ) $value = 200;
            update_user_meta( get_current_user_id(), $option, $value );
        }

        wp_send_json_success( array( 'message' => 'Saved' ) );
    }
    
    public function ajax_global_search() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        // 🚀 بهبود Rate Limiting با Transient
        $user_id = get_current_user_id();
        $rate_limit_key = 'nextsafar_search_rate_' . $user_id;
        $request_count = (int) get_transient( $rate_limit_key );
        
        if ( $request_count >= 120 ) { // افزایش از ۶۰ به ۱۲۰
            wp_send_json_error( array( 'message' => 'Too many requests. Please wait a moment.' ), 429 );
        }
        
        set_transient( $rate_limit_key, $request_count + 1, MINUTE_IN_SECONDS );
        
        $query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        
        if ( strlen( $query ) < 2 ) {
            wp_send_json_success( array( 'results' => array() ) );
        }
        
        // 🚀 بهبود کش: ۱۵ دقیقه به جای ۵ دقیقه
        $cache_key = 'nextsafar_search_' . md5( $query . $user_id );
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            wp_send_json_success( array( 'results' => $cached, 'cached' => true ) );
        }
        
        $results = array();
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
        
        // 🚀 روش اول: تلاش برای استفاده از Full-text Search
        $fulltext_results = $this->search_with_fulltext( $query, $max_per_type, $type_labels );
        
        if ( ! empty( $fulltext_results ) ) {
            $results = $fulltext_results;
        } else {
            // 🚀 روش دوم: Fallback به LIKE با بهینه‌سازی
            $results = $this->search_with_like( $query, $max_per_type, $type_labels );
        }
        
        // محدود کردن به ۱۵ نتیجه
        $results = array_slice( $results, 0, 15 );
        
        // 🚀 ذخیره در کش برای ۱۵ دقیقه
        set_transient( $cache_key, $results, 15 * MINUTE_IN_SECONDS );
        
        wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * 🚀 جستجو با Full-text Search (سریع‌تر)
     */
    private function search_with_fulltext( $query, $max_per_type, $type_labels ) {
        global $wpdb;

        // بررسی اینکه آیا Full-text Index وجود دارد
        $has_fulltext = $wpdb->get_var(
            "SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = 'post_title_fulltext'"
        );

        if ( ! $has_fulltext ) {
            return array(); // Full-text Index وجود ندارد
        }

        // ساخت لیست post_type ها
        $post_types = array( 'post', 'page' );
        if ( post_type_exists( 'tour' ) ) $post_types[] = 'tour';
        if ( post_type_exists( 'visa' ) ) $post_types[] = 'visa';

        // ✅ استفاده از placeholders صحیح
        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        
        $search_query = $wpdb->esc_like( $query );
        $limit = $max_per_type * count( $post_types );

        // ✅ ساخت آرایه پارامترها
        $params = array_merge( $post_types, array( $search_query, $search_query, $limit ) );

        // ✅ کوئری ایمن با placeholders صحیح
        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title, post_type,
                MATCH(post_title) AGAINST(%s IN BOOLEAN MODE) as relevance
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
                AND post_status IN ('publish', 'draft', 'pending')
                AND MATCH(post_title) AGAINST(%s IN BOOLEAN MODE)
            ORDER BY relevance DESC
            LIMIT %d",
            ...$params
        ) );

        $results = array();

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $post ) {
                if ( count( $results ) >= $max_per_type * 2 ) break;
                
                $results[] = array(
                    'type'       => $post->post_type,
                    'type_label' => $type_labels[ $post->post_type ] ?? ucfirst( $post->post_type ),
                    'title'      => $post->post_title,
                    'url'        => get_edit_post_link( $post->ID, 'raw' ),
                );
            }
        }

        return $results;
    }

    /**
     * 🚀 جستجو با LIKE (Fallback)
     */
    private function search_with_like( $query, $max_per_type, $type_labels ) {
        global $wpdb;

        $results = array();
        $like_query = '%' . $wpdb->esc_like( $query ) . '%';

        // ساخت لیست post_type ها
        $post_types = array( 'post', 'page' );
        if ( post_type_exists( 'tour' ) ) $post_types[] = 'tour';
        if ( post_type_exists( 'visa' ) ) $post_types[] = 'visa';

        // ✅ استفاده از placeholders صحیح
        $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $limit_posts = $max_per_type * count( $post_types );

        // ✅ ساخت آرایه پارامترها
        $params = array_merge( $post_types, array( $like_query, $limit_posts ) );

        // 🚀 کوئری ۱: Posts با placeholders صحیح
        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title, post_type
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
                AND post_status IN ('publish', 'draft', 'pending')
                AND post_title LIKE %s
            ORDER BY post_modified DESC
            LIMIT %d",
            ...$params
        ) );

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $post ) {
                if ( count( $results ) >= $max_per_type * 2 ) break;
                
                $results[] = array(
                    'type'       => $post->post_type,
                    'type_label' => $type_labels[ $post->post_type ] ?? ucfirst( $post->post_type ),
                    'title'      => $post->post_title,
                    'url'        => get_edit_post_link( $post->ID, 'raw' ),
                );
            }
        }

        // 🚀 کوئری ۲: Attachments
        $attachments = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
                AND post_status = 'inherit'
                AND post_title LIKE %s
            ORDER BY post_modified DESC
            LIMIT %d",
            $like_query,
            $max_per_type
        ) );

        if ( ! empty( $attachments ) ) {
            foreach ( $attachments as $attachment ) {
                $results[] = array(
                    'type'       => 'attachment',
                    'type_label' => 'رسانه',
                    'title'      => $attachment->post_title,
                    'url'        => admin_url( 'admin.php?page=nextsafar-media' ),
                );
            }
        }

        // 🚀 کوئری ۳: Categories
        $categories = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.term_id, t.name
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'category'
                AND t.name LIKE %s
            LIMIT %d",
            $like_query,
            $max_per_type
        ) );

        if ( ! empty( $categories ) ) {
            foreach ( $categories as $cat ) {
                $results[] = array(
                    'type'       => 'category',
                    'type_label' => 'دسته‌بندی',
                    'title'      => $cat->name,
                    'url'        => admin_url( 'admin.php?page=nextsafar-categories' ),
                );
            }
        }

        // 🚀 کوئری ۴: Users (فقط برای ادمین‌ها)
        if ( current_user_can( 'list_users' ) ) {
            $users = $wpdb->get_results( $wpdb->prepare(
                "SELECT ID, display_name, user_email
                FROM {$wpdb->users}
                WHERE user_login LIKE %s
                    OR user_email LIKE %s
                    OR display_name LIKE %s
                LIMIT %d",
                $like_query,
                $like_query,
                $like_query,
                $max_per_type
            ) );

            if ( ! empty( $users ) ) {
                foreach ( $users as $user ) {
                    $results[] = array(
                        'type'       => 'user',
                        'type_label' => 'کاربر',
                        'title'      => $user->display_name . ' (' . $user->user_email . ')',
                        'url'        => admin_url( 'user-edit.php?user_id=' . $user->ID ),
                    );
                }
            }
        }

        return $results;
    }

    // درون متد init() این خط را اضافه کنید:


    /**
     * 🚀 ایجاد Full-text Index (فقط یک بار اجرا شود)
     * این متد را می‌توانید در activation hook یا یک بار دستی اجرا کنید
     */
    public static function create_fulltext_index() {
        global $wpdb;
        
        $table = $wpdb->posts;
        $index_name = 'post_title_fulltext';
        
        // بررسی اینکه آیا Index قبلاً وجود دارد
        $exists = $wpdb->get_var( "
            SHOW INDEX FROM {$table} 
            WHERE Key_name = '{$index_name}'
        " );
        
        if ( ! $exists ) {
            $wpdb->query( "
                ALTER TABLE {$table} 
                ADD FULLTEXT INDEX {$index_name} (post_title)
            " );
        }
    }

    public function ajax_add_term() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }

        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_post_editor' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

        if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
            wp_send_json_error( array( 'message' => 'Invalid taxonomy.' ), 400 );
        }

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Name is required.' ), 400 );
        }

        $result = wp_insert_term( $name, $taxonomy );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        wp_send_json_success( array(
            'term_id' => $result['term_id'],
            'message' => 'دسته‌بندی با موفقیت اضافه شد.'
        ) );
    }

    public function yoast_ajax_proxy() {
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
        
        if ( strpos( $action, 'wpseo' ) !== 0 && strpos( $action, 'yoast' ) !== 0 ) {
            return;
        }

        global $pagenow, $current_screen;
        
        $post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : 0;
        $post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( $_REQUEST['post_type'] ) : 'post';

        $pagenow = ( $post_id > 0 ) ? 'post.php' : 'post-new.php';
        
        $current_screen = WP_Screen::get( $post_type );
        set_current_screen( $post_type );

        if ( $post_id > 0 ) {
            $post_obj = get_post( $post_id );
            if ( $post_obj ) {
                $GLOBALS['post'] = $post_obj;
                setup_postdata( $post_obj );
            }
        }
    }

    /**
     * 🎯 فعال‌سازی Screen Options فقط برای صفحات لیست
     */
    public function enable_screen_options() {
        $screen = get_current_screen();
        if ( ! $screen ) return;
        
        // فقط برای صفحات NextSafar
        if ( strpos( $screen->id, 'nextsafar' ) === false ) return;
        
        // 🎯 فقط برای صفحات لیست per_page option اضافه کن
        $list_pages = array(
            'nextsafar-admin_page_nextsafar-posts',
            'nextsafar-admin_page_nextsafar-pages',
            'nextsafar-admin_page_nextsafar-media',
            'nextsafar-admin_page_nextsafar-categories',
        );
        
        $is_list_page = in_array( $screen->id, $list_pages, true )
            || strpos( $screen->id, 'nextsafar-cpt' ) !== false;
        
        // ❌ برای داشبورد و صفحات غیر لیست، هیچ screen option اضافه نکن
        if ( ! $is_list_page ) {
            return;
        }
        
        // ✅ فقط برای صفحات لیست
        add_screen_option( 'per_page', array(
            'label'   => __( 'تعداد در هر صفحه', 'nextsafar-admin' ),
            'default' => 20,
            'option'  => 'nextsafar_items_per_page',
        ) );
        
        wp_enqueue_script( 'common' );
        wp_enqueue_script( 'wp-ajax-response' );
    }

    /**
     * 💾 ذخیره تنظیمات Screen Options
     */
    public function save_screen_option( $status, $option, $value ) {
        if ( 'nextsafar_items_per_page' === $option ) {
            $value = absint( $value );
            // محدود کردن بین ۵ تا ۲۰۰
            if ( $value < 5 ) $value = 5;
            if ( $value > 200 ) $value = 200;
            return $value;
        }
        return $status;
    }

/**
 * 🆕 AJAX: گرفتن محتوای Screen Options
 */
public function ajax_get_screen_options() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }

    // 🎯 تلاش برای گرفتن screen object
    $screen = get_current_screen();
    
    // 🆕 اگر screen موجود نیست، از $_POST استفاده کن
    if ( ! $screen && isset( $_POST['screen_id'] ) ) {
        $screen_id = sanitize_text_field( wp_unslash( $_POST['screen_id'] ) );
        $screen = WP_Screen::get( $screen_id );
        if ( $screen ) {
            set_current_screen( $screen_id );
        }
    }

    // 🆕 اگر هنوز screen نداریم، از page parameter استفاده کن
    if ( ! $screen && isset( $_POST['page'] ) ) {
        $page = sanitize_key( $_POST['page'] );
        // ساخت screen id از page
        $screen_id = 'toplevel_page_' . $page;
        if ( strpos( $page, 'nextsafar-admin_page_' ) === false && strpos( $page, 'toplevel_page_' ) === false ) {
            $screen_id = 'nextsafar-admin_page_' . $page;
        }
        $screen = WP_Screen::get( $screen_id );
    }

    $html = '';

    if ( ! $screen ) {
        // 🆕 Fallback: نمایش تنظیمات عمومی
        $html = $this->get_screen_options_fallback();
        wp_send_json_success( array( 'html' => $html ) );
        return;
    }

    $list_pages = array(
        'nextsafar-admin_page_nextsafar-posts',
        'nextsafar-admin_page_nextsafar-pages',
        'nextsafar-admin_page_nextsafar-media',
        'nextsafar-admin_page_nextsafar-categories',
    );

    $is_list_page = in_array( $screen->id, $list_pages, true )
        || strpos( $screen->id, 'nextsafar-cpt' ) !== false;

    $is_dashboard = 'toplevel_page_nextsafar-admin' === $screen->id;

    if ( $is_list_page ) {
        $html = $this->get_list_page_options_html();
    } elseif ( $is_dashboard ) {
        $html = $this->get_dashboard_options_html();
    } else {
        $html = $this->get_default_options_html();
    }

    wp_send_json_success( array( 'html' => $html ) );
}

/**
 * 🆕 Helper: HTML تنظیمات صفحه لیست
 */
private function get_list_page_options_html() {
    $current_per_page = get_user_meta( get_current_user_id(), 'nextsafar_items_per_page', true );
    $current_per_page = absint( $current_per_page );
    if ( $current_per_page < 5 || $current_per_page > 200 ) {
        $current_per_page = 20;
    }

    $nonce = wp_create_nonce( 'screen-options-nonce' );

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
                <input type="number" 
                       step="1" 
                       min="5" 
                       max="200" 
                       id="nextsafar_items_per_page" 
                       name="per_page"
                       value="<?php echo esc_attr( $current_per_page ); ?>"
                       style="width:80px; padding:6px 10px; border:1px solid var(--ns-border, #e5e7eb); border-radius:6px; font-size:13px;">
                <span style="color:var(--ns-text-muted, #6b7280); font-size:13px;">آیتم</span>
            </div>
            <small style="display:block; margin-top:8px; color:var(--ns-text-muted, #6b7280); font-size:11px;">
                💡 مقادیر بین ۵ تا ۲۰۰ مجاز است
            </small>
            <input type="hidden" name="wp_screen_options[option]" value="nextsafar_items_per_page">
            <input type="hidden" name="wp_screen_options[value]" value="<?php echo esc_attr( $current_per_page ); ?>">
            <input type="hidden" name="screenoptionnonce" value="<?php echo esc_attr( $nonce ); ?>">
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

/**
 * 🆕 Helper: HTML تنظیمات داشبورد
 */
private function get_dashboard_options_html() {
    $core = nextsafar();
    $widgets = array();
    
    if ( isset( $core->widgets ) && method_exists( $core->widgets, 'get_widgets' ) ) {
        $widgets = $core->widgets->get_widgets();
    }

    $settings = array();
    if ( isset( $core->widgets ) && method_exists( $core->widgets, 'get_user_widget_settings' ) ) {
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
            <?php if ( empty( $widgets ) ) : ?>
                <p style="color:var(--ns-text-muted, #6b7280); font-size:13px;">
                    ویجتی برای تنظیم وجود ندارد.
                </p>
            <?php else : ?>
                <div style="display:flex; flex-direction:column; gap:4px;">
                    <?php foreach ( $widgets as $widget_id => $widget ) : 
                        $enabled = ! isset( $settings[ $widget_id ] ) 
                            || ( isset( $settings[ $widget_id ]['enabled'] ) && $settings[ $widget_id ]['enabled'] );
                    ?>
                        <label style="display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; cursor:pointer; transition:background 0.2s; margin:0;">
                            <input type="checkbox" 
                                   name="widgets[]" 
                                   value="<?php echo esc_attr( $widget_id ); ?>"
                                   <?php checked( $enabled ); ?>
                                   style="accent-color:var(--ns-primary, #43abff); width:16px; height:16px;">
                            <span style="font-size:13px; color:var(--ns-text, #111827);">
                                <?php echo esc_html( $widget->get_title() ); ?>
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

/**
 * 🆕 Helper: HTML تنظیمات پیش‌فرض
 */
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

/**
 * 🆕 Helper: Fallback وقتی screen موجود نیست
 */
private function get_screen_options_fallback() {
    // بررسی $_GET['page']
    $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
    
    if ( strpos( $page, 'nextsafar' ) !== false ) {
        // صفحه NextSafar است
        if ( $page === 'nextsafar-admin' ) {
            return $this->get_dashboard_options_html();
        } elseif ( strpos( $page, 'nextsafar-posts' ) !== false || 
                   strpos( $page, 'nextsafar-pages' ) !== false ||
                   strpos( $page, 'nextsafar-media' ) !== false ||
                   strpos( $page, 'nextsafar-cpt' ) !== false ) {
            return $this->get_list_page_options_html();
        }
    }
    
    return $this->get_default_options_html();
}

/**
 * 🆕 AJAX: ذخیره تنظیمات ویجت‌های داشبورد
 */
public function ajax_save_dashboard_widgets() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }

    $user_id = get_current_user_id();
    $enabled_widgets = isset( $_POST['widgets'] ) ? array_map( 'sanitize_key', (array) $_POST['widgets'] ) : array();

    // گرفتن لیست همه ویجت‌ها
    $core = nextsafar();
    $all_widgets = array();
    if ( isset( $core->widgets ) && method_exists( $core->widgets, 'get_widgets' ) ) {
        $all_widgets = $core->widgets->get_widgets();
    }

    // ساخت تنظیمات جدید
    $settings = array();
    foreach ( $all_widgets as $widget_id => $widget ) {
        $settings[ $widget_id ] = array(
            'enabled' => in_array( $widget_id, $enabled_widgets, true ),
        );
    }

    update_user_meta( $user_id, 'nextsafar_dashboard_widgets', $settings );

    wp_send_json_success( array( 'message' => 'Widget settings saved' ) );
}
}