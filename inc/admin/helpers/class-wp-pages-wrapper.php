<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_WP_Pages_Wrapper {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_wrapper_pages' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'force_admin_styles_in_iframe' ), 1 );
    }

    /**
     * Force WordPress admin styles in iframe
     */
    public function force_admin_styles_in_iframe() {
        // فقط در iframe
        if ( ! isset( $_GET['nextsafar_iframe'] ) ) {
            return;
        }

        // ✅ افزودن body class برای iframe
        add_filter( 'admin_body_class', function( $classes ) {
            return $classes . ' nextsafar-iframe-page';
        } );

        // اطمینان از لود شدن استایل‌های ضروری وردپرس
        wp_enqueue_style( 'wp-admin' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'buttons' );
        wp_enqueue_style( 'forms' );
        wp_enqueue_style( 'common' );
        // ❌ حذف admin-menu چون با CSS مخفی‌سازی تداخل دارد
        // wp_enqueue_style( 'admin-menu' );
        wp_enqueue_style( 'list-tables' );
        wp_enqueue_style( 'edit' );

        // Script های ضروری
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'common' );
        wp_enqueue_script( 'wp-a11y' );
        wp_enqueue_script( 'wp-util' );
    }

    public function register_wrapper_pages() {
        // یک wrapper generic برای همه صفحات وردپرس
        add_submenu_page(
            'nextsafar-admin',
            'WordPress Page',
            'WordPress Page',
            'read',
            'nextsafar-wp-wrapper',
            array( $this, 'render_generic_wrapper' )
        );
    }

    public function render_generic_wrapper() {
        // Debug برای ادمین (اختیاری - می‌توانید حذف کنید)
        if ( current_user_can( 'manage_options' ) && isset( $_GET['debug'] ) ) {
            $target = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
            $is_simple_slug = preg_match( '/^[a-z0-9_\-]+$/', $target );
            
            echo '<div style="position:fixed;top:10px;left:10px;background:#111;color:#fff;padding:15px;z-index:99999;font-family:monospace;font-size:11px;border-radius:8px;border:2px solid #43abff;max-width:500px;">';
            echo '<strong style="color:#43abff;">🔍 Debug:</strong><br>';
            echo 'Target: <span style="color:#ffeb3b;">' . esc_html( $target ) . '</span><br>';
            echo 'Is Simple Slug: <span style="color:' . ($is_simple_slug ? 'lime' : 'red') . ';">';
            echo $is_simple_slug ? 'YES ✓' : 'NO';
            echo '</span><br>';
            
            if ( $is_simple_slug ) {
                echo 'URL will be: <span style="color:#00bcd4;">admin.php?page=' . esc_html( $target ) . '</span>';
            } else {
                echo 'URL will be: <span style="color:#00bcd4;">' . esc_html( $target ) . '</span>';
            }
            echo '</div>';
        }

        if ( ! isset( $_GET['target'] ) ) {
            wp_die( 'No target page specified.', 'Error', array( 'response' => 400 ) );
        }

        $target = sanitize_text_field( wp_unslash( $_GET['target'] ) );
        
        // Validate URL
        if ( ! $this->is_allowed_target( $target ) ) {
            wp_die( 'Access denied to this page.', 'Error', array( 'response' => 403 ) );
        }

        // Parse query parameters اضافی
        $extra_params = array();
        if ( isset( $_GET['extra'] ) ) {
            parse_str( sanitize_text_field( wp_unslash( $_GET['extra'] ) ), $extra_params );
        }

        // ⭐ ساخت URL نهایی - تشخیص slug ساده
        if ( preg_match( '/^[a-z0-9_\-]+$/', $target ) ) {
            // slug ساده (مثل tour-manager) - تبدیل به admin.php?page=xxx
            $url = admin_url( 'admin.php' );
            $url = add_query_arg( 'page', $target, $url );
        } else {
            // فایل PHP (مثل edit-comments.php) یا URL کامل
            $url = admin_url( $target );
        }

        if ( ! empty( $extra_params ) ) {
            $url = add_query_arg( $extra_params, $url );
        }

        $this->render_iframe_wrapper( $url, 'WordPress Page' );
    }

    /**
     * بررسی مجاز بودن target page - نسخه کامل و ایمن
     * پشتیبانی از: slug ساده، admin.php، edit.php، core files
     */
    private function is_allowed_target( $target ) {
        // ۰. بررسی‌های اولیه امنیتی
        if ( empty( $target ) || ! is_string( $target ) ) {
            return false;
        }

        // بررسی طول غیرمعمول
        if ( strlen( $target ) > 500 ) {
            return false;
        }

        // جلوگیری از کاراکترهای خطرناک و پروتکل‌های مخرب
        $dangerous_patterns = array(
            '..',
            "\0",
            "\n",
            "\r",
            "\t",
            '<script',
            'javascript:',
            'data:',
            'vbscript:',
            'file://',
            'php://',
            'phar://',
            'expect://',
            'input://',
            'zip://',
            'glob://',
        );

        $target_lower = strtolower( $target );
        foreach ( $dangerous_patterns as $pattern ) {
            if ( strpos( $target_lower, $pattern ) !== false ) {
                return false;
            }
        }

        // بررسی کاراکترهای مجاز (نسخه بهبود یافته)
        // اجازه: حروف، اعداد، _ - . = ? & % + @ / : # ~
        if ( ! preg_match( '/^[a-zA-Z0-9_\-.=?&%+@\/:#~]+$/', $target ) ) {
            return false;
        }

        // ⭐ ۱. بررسی slug های ساده (مثل tour-manager, visa_exchange_rates)
        if ( preg_match( '/^[a-z0-9_-]+$/', $target ) ) {
            return $this->user_can_access_page( $target );
        }

        // ⭐ ۲. بررسی admin.php?page=xxx یا admin.php با query string
        if ( strpos( $target, 'admin.php' ) === 0 ) {
            $query = parse_url( $target, PHP_URL_QUERY );
            if ( ! empty( $query ) ) {
                parse_str( $query, $params );
                if ( isset( $params['page'] ) ) {
                    return $this->user_can_access_page( sanitize_key( $params['page'] ) );
                }
            }
            return current_user_can( 'manage_options' );
        }

        // ⭐ ۳. بررسی edit.php با post_type (برای CPT ها)
        if ( strpos( $target, 'edit.php' ) === 0 ) {
            $query = parse_url( $target, PHP_URL_QUERY );
            if ( ! empty( $query ) ) {
                parse_str( $query, $params );
                if ( isset( $params['post_type'] ) ) {
                    $post_type_obj = get_post_type_object( sanitize_key( $params['post_type'] ) );
                    if ( $post_type_obj ) {
                        return current_user_can( $post_type_obj->cap->edit_posts );
                    }
                    return false;
                }
            }
            return current_user_can( 'edit_posts' );
        }

        // ⭐ ۴. بررسی post-new.php
        if ( strpos( $target, 'post-new.php' ) === 0 ) {
            $query = parse_url( $target, PHP_URL_QUERY );
            if ( ! empty( $query ) ) {
                parse_str( $query, $params );
                if ( isset( $params['post_type'] ) ) {
                    $post_type_obj = get_post_type_object( sanitize_key( $params['post_type'] ) );
                    if ( $post_type_obj ) {
                        return current_user_can( $post_type_obj->cap->create_posts );
                    }
                }
            }
            return current_user_can( 'edit_posts' );
        }

        // ⭐ ۵. بررسی post.php با دسترسی دقیق به پست
        if ( strpos( $target, 'post.php' ) === 0 ) {
            $query = parse_url( $target, PHP_URL_QUERY );
            if ( ! empty( $query ) ) {
                parse_str( $query, $params );
                if ( isset( $params['post'] ) ) {
                    $post_id = absint( $params['post'] );
                    // بررسی دسترسی به همین پست خاص
                    return current_user_can( 'edit_post', $post_id );
                }
            }
            return current_user_can( 'edit_posts' );
        }

        // ⭐ ۶. بررسی edit-tags.php و term.php
        if ( strpos( $target, 'edit-tags.php' ) === 0 || strpos( $target, 'term.php' ) === 0 ) {
            return current_user_can( 'manage_categories' );
        }

        // ⭐ ۷. بررسی comment.php
        if ( strpos( $target, 'comment.php' ) === 0 ) {
            return current_user_can( 'moderate_comments' );
        }

        // ⭐ ۸. بررسی صفحات کاربران
        if ( strpos( $target, 'user-edit.php' ) === 0 || strpos( $target, 'user-new.php' ) === 0 ) {
            return current_user_can( 'edit_users' );
        }

        if ( strpos( $target, 'profile.php' ) === 0 ) {
            return is_user_logged_in(); // همه کاربران لاگین شده
        }

        // ⭐ ۹. بررسی ویرایشگرها (خطرناک - فقط ادمین)
        if ( strpos( $target, 'plugin-editor.php' ) === 0 || strpos( $target, 'theme-editor.php' ) === 0 ) {
            return current_user_can( 'manage_options' );
        }

        // ⭐ ۱۰. بررسی صفحات تنظیمات (فقط ادمین)
        if ( strpos( $target, 'options-' ) === 0 ) {
            return current_user_can( 'manage_options' );
        }

        // ⭐ ۱۱. Whitelist فایل‌های core وردپرس
        $core_pages = array(
            'edit-comments.php',
            'users.php',
            'plugins.php', 'plugin-install.php',
            'themes.php', 'customize.php', 'nav-menus.php', 'widgets.php',
            'tools.php', 'import.php', 'export.php', 'site-health.php',
            'export-personal-data.php', 'erase-personal-data.php',
            'upload.php', 'media-new.php', 'media.php',
        );

        // استخراج نام فایل (بدون query string)
        $target_file = strtok( $target, '?' );
        
        if ( in_array( $target_file, $core_pages, true ) ) {
            return true;
        }

        // ⭐ ۱۲. بررسی فایل‌های موجود در wp-admin (با محافظت کامل)
        // فقط اجازه دسترسی به فایل‌های مستقیم داخل wp-admin
        if ( ! preg_match( '/^[a-z0-9_-]+\.php$/i', $target_file ) ) {
            return false;
        }

        $file_path = ABSPATH . 'wp-admin/' . $target_file;
        $real_path = realpath( $file_path );
        
        if ( $real_path === false ) {
            return false;
        }

        // اطمینان از اینکه فایل واقعاً داخل wp-admin است (جلوگیری از path traversal)
        $wp_admin_path = realpath( ABSPATH . 'wp-admin' );
        if ( strpos( $real_path, $wp_admin_path ) !== 0 ) {
            return false;
        }

        return true;
    }

    /**
     * بررسی دسترسی کاربر به یک page slug
     */
    private function user_can_access_page( $page ) {
        global $submenu;
        
        // ۱. بررسی در همه submenu ها
        foreach ( $submenu as $parent => $items ) {
            foreach ( $items as $item ) {
                if ( isset( $item[2] ) && $item[2] === $page ) {
                    $capability = $item[1];
                    return current_user_can( $capability );
                }
            }
        }
        
        // ۲. بررسی در top-level menu
        global $menu;
        foreach ( $menu as $menu_item ) {
            if ( isset( $menu_item[2] ) && $menu_item[2] === $page ) {
                $capability = $menu_item[1];
                return current_user_can( $capability );
            }
        }
        
        // ۳. اگر page در منوها ثبت نشده، بررسی وجود hook
        $hook_name = get_plugin_page_hookname( $page, '' );
        if ( has_action( $hook_name ) !== false ) {
            // Page دارای hook است، اجازه بده وردپرس خودش بررسی کند
            return true;
        }
        
        // ۴. بررسی با parent های مختلف
        foreach ( $submenu as $parent => $items ) {
            $hook_name = get_plugin_page_hookname( $page, $parent );
            if ( has_action( $hook_name ) !== false ) {
                return true;
            }
        }
        
        // ۵. اگر هیچ‌کدام کار نکرد، اجازه بده و بگذار وردپرس خودش بررسی کند
        // (وردپرس در صورت عدم دسترسی ارور می‌دهد)
        return true;
    }

    /**
     * Render iframe wrapper
     */
    private function render_iframe_wrapper( $url, $title ) {
        // ????? ???? query parameter ???? ????? iframe
        $iframe_url = add_query_arg( 'nextsafar_iframe', '1', $url );

        require NEXTSAFAR_PATH . 'inc/inc/admin/layout/sidebar.php';  // ? ????
        ?>
        <div class="nextsafar-main">
            <?php require NEXTSAFAR_PATH . 'inc/inc/admin/layout/header.php'; ?>  // ? ????

            <div class="nextsafar-iframe-wrapper">
                <iframe
                    src="<?php echo esc_url( $iframe_url ); ?>"
                    class="nextsafar-iframe"
                    frameborder="0"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
        <?php
        require NEXTSAFAR_PATH . 'inc/inc/admin/layout/footer.php';  // ? ????
    }

    /**
     * Helper: ساخت wrapper URL برای هر target
     */
    public static function build_wrapper_url( $target_page, $extra_params = array() ) {
        $base_url = admin_url( 'admin.php?page=nextsafar-wp-wrapper' );
        $base_url = add_query_arg( 'target', $target_page, $base_url );

        if ( ! empty( $extra_params ) ) {
            $base_url = add_query_arg( 'extra', http_build_query( $extra_params ), $base_url );
        }

        return $base_url;
    }
}
