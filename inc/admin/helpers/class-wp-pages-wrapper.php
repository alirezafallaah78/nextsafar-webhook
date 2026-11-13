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
        // ÙÙ‚Ø· Ø¯Ø± iframe
        if ( ! isset( $_GET['nextsafar_iframe'] ) ) {
            return;
        }

        // âœ… Ø§ÙØ²ÙˆØ¯Ù† body class Ø¨Ø±Ø§ÛŒ iframe
        add_filter( 'admin_body_class', function( $classes ) {
            return $classes . ' nextsafar-iframe-page';
        } );

        // Ø§Ø·Ù…ÛŒÙ†Ø§Ù† Ø§Ø² Ù„ÙˆØ¯ Ø´Ø¯Ù† Ø§Ø³ØªØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ Ø¶Ø±ÙˆØ±ÛŒ ÙˆØ±Ø¯Ù¾Ø±Ø³
        wp_enqueue_style( 'wp-admin' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'buttons' );
        wp_enqueue_style( 'forms' );
        wp_enqueue_style( 'common' );
        // âŒ Ø­Ø°Ù admin-menu Ú†ÙˆÙ† Ø¨Ø§ CSS Ù…Ø®ÙÛŒâ€ŒØ³Ø§Ø²ÛŒ ØªØ¯Ø§Ø®Ù„ Ø¯Ø§Ø±Ø¯
        // wp_enqueue_style( 'admin-menu' );
        wp_enqueue_style( 'list-tables' );
        wp_enqueue_style( 'edit' );

        // Script Ù‡Ø§ÛŒ Ø¶Ø±ÙˆØ±ÛŒ
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'common' );
        wp_enqueue_script( 'wp-a11y' );
        wp_enqueue_script( 'wp-util' );
    }

    public function register_wrapper_pages() {
        // ÛŒÚ© wrapper generic Ø¨Ø±Ø§ÛŒ Ù‡Ù…Ù‡ ØµÙØ­Ø§Øª ÙˆØ±Ø¯Ù¾Ø±Ø³
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
        // Debug Ø¨Ø±Ø§ÛŒ Ø§Ø¯Ù…ÛŒÙ† (Ø§Ø®ØªÛŒØ§Ø±ÛŒ - Ù…ÛŒâ€ŒØªÙˆØ§Ù†ÛŒØ¯ Ø­Ø°Ù Ú©Ù†ÛŒØ¯)
        if ( current_user_can( 'manage_options' ) && isset( $_GET['debug'] ) ) {
            $target = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
            $is_simple_slug = preg_match( '/^[a-z0-9_\-]+$/', $target );
            
            echo '<div style="position:fixed;top:10px;left:10px;background:#111;color:#fff;padding:15px;z-index:99999;font-family:monospace;font-size:11px;border-radius:8px;border:2px solid #43abff;max-width:500px;">';
            echo '<strong style="color:#43abff;">ðŸ” Debug:</strong><br>';
            echo 'Target: <span style="color:#ffeb3b;">' . esc_html( $target ) . '</span><br>';
            echo 'Is Simple Slug: <span style="color:' . ($is_simple_slug ? 'lime' : 'red') . ';">';
            echo $is_simple_slug ? 'YES âœ“' : 'NO';
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

        // Parse query parameters Ø§Ø¶Ø§ÙÛŒ
        $extra_params = array();
        if ( isset( $_GET['extra'] ) ) {
            parse_str( sanitize_text_field( wp_unslash( $_GET['extra'] ) ), $extra_params );
        }

        // â­ Ø³Ø§Ø®Øª URL Ù†Ù‡Ø§ÛŒÛŒ - ØªØ´Ø®ÛŒØµ slug Ø³Ø§Ø¯Ù‡
        if ( preg_match( '/^[a-z0-9_\-]+$/', $target ) ) {
            // slug Ø³Ø§Ø¯Ù‡ (Ù…Ø«Ù„ tour-manager) - ØªØ¨Ø¯ÛŒÙ„ Ø¨Ù‡ admin.php?page=xxx
            $url = admin_url( 'admin.php' );
            $url = add_query_arg( 'page', $target, $url );
        } else {
            // ÙØ§ÛŒÙ„ PHP (Ù…Ø«Ù„ edit-comments.php) ÛŒØ§ URL Ú©Ø§Ù…Ù„
            $url = admin_url( $target );
        }

        if ( ! empty( $extra_params ) ) {
            $url = add_query_arg( $extra_params, $url );
        }

        $this->render_iframe_wrapper( $url, 'WordPress Page' );
    }

    /**
     * Ø¨Ø±Ø±Ø³ÛŒ Ù…Ø¬Ø§Ø² Ø¨ÙˆØ¯Ù† target page - Ù†Ø³Ø®Ù‡ Ú©Ø§Ù…Ù„ Ùˆ Ø§ÛŒÙ…Ù†
     * Ù¾Ø´ØªÛŒØ¨Ø§Ù†ÛŒ Ø§Ø²: slug Ø³Ø§Ø¯Ù‡ØŒ admin.phpØŒ edit.phpØŒ core files
     */
    private function is_allowed_target( $target ) {
        // Û°. Ø¨Ø±Ø±Ø³ÛŒâ€ŒÙ‡Ø§ÛŒ Ø§ÙˆÙ„ÛŒÙ‡ Ø§Ù…Ù†ÛŒØªÛŒ
        if ( empty( $target ) || ! is_string( $target ) ) {
            return false;
        }

        // Ø¨Ø±Ø±Ø³ÛŒ Ø·ÙˆÙ„ ØºÛŒØ±Ù…Ø¹Ù…ÙˆÙ„
        if ( strlen( $target ) > 500 ) {
            return false;
        }

        // Ø¬Ù„ÙˆÚ¯ÛŒØ±ÛŒ Ø§Ø² Ú©Ø§Ø±Ø§Ú©ØªØ±Ù‡Ø§ÛŒ Ø®Ø·Ø±Ù†Ø§Ú© Ùˆ Ù¾Ø±ÙˆØªÚ©Ù„â€ŒÙ‡Ø§ÛŒ Ù…Ø®Ø±Ø¨
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

        // Ø¨Ø±Ø±Ø³ÛŒ Ú©Ø§Ø±Ø§Ú©ØªØ±Ù‡Ø§ÛŒ Ù…Ø¬Ø§Ø² (Ù†Ø³Ø®Ù‡ Ø¨Ù‡Ø¨ÙˆØ¯ ÛŒØ§ÙØªÙ‡)
        // Ø§Ø¬Ø§Ø²Ù‡: Ø­Ø±ÙˆÙØŒ Ø§Ø¹Ø¯Ø§Ø¯ØŒ _ - . = ? & % + @ / : # ~
        if ( ! preg_match( '/^[a-zA-Z0-9_\-.=?&%+@\/:#~]+$/', $target ) ) {
            return false;
        }

        // â­ Û±. Ø¨Ø±Ø±Ø³ÛŒ slug Ù‡Ø§ÛŒ Ø³Ø§Ø¯Ù‡ (Ù…Ø«Ù„ tour-manager, visa_exchange_rates)
        if ( preg_match( '/^[a-z0-9_-]+$/', $target ) ) {
            return $this->user_can_access_page( $target );
        }

        // â­ Û². Ø¨Ø±Ø±Ø³ÛŒ admin.php?page=xxx ÛŒØ§ admin.php Ø¨Ø§ query string
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

        // â­ Û³. Ø¨Ø±Ø±Ø³ÛŒ edit.php Ø¨Ø§ post_type (Ø¨Ø±Ø§ÛŒ CPT Ù‡Ø§)
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

        // â­ Û´. Ø¨Ø±Ø±Ø³ÛŒ post-new.php
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

        // â­ Ûµ. Ø¨Ø±Ø±Ø³ÛŒ post.php Ø¨Ø§ Ø¯Ø³ØªØ±Ø³ÛŒ Ø¯Ù‚ÛŒÙ‚ Ø¨Ù‡ Ù¾Ø³Øª
        if ( strpos( $target, 'post.php' ) === 0 ) {
            $query = parse_url( $target, PHP_URL_QUERY );
            if ( ! empty( $query ) ) {
                parse_str( $query, $params );
                if ( isset( $params['post'] ) ) {
                    $post_id = absint( $params['post'] );
                    // Ø¨Ø±Ø±Ø³ÛŒ Ø¯Ø³ØªØ±Ø³ÛŒ Ø¨Ù‡ Ù‡Ù…ÛŒÙ† Ù¾Ø³Øª Ø®Ø§Øµ
                    return current_user_can( 'edit_post', $post_id );
                }
            }
            return current_user_can( 'edit_posts' );
        }

        // â­ Û¶. Ø¨Ø±Ø±Ø³ÛŒ edit-tags.php Ùˆ term.php
        if ( strpos( $target, 'edit-tags.php' ) === 0 || strpos( $target, 'term.php' ) === 0 ) {
            return current_user_can( 'manage_categories' );
        }

        // â­ Û·. Ø¨Ø±Ø±Ø³ÛŒ comment.php
        if ( strpos( $target, 'comment.php' ) === 0 ) {
            return current_user_can( 'moderate_comments' );
        }

        // â­ Û¸. Ø¨Ø±Ø±Ø³ÛŒ ØµÙØ­Ø§Øª Ú©Ø§Ø±Ø¨Ø±Ø§Ù†
        if ( strpos( $target, 'user-edit.php' ) === 0 || strpos( $target, 'user-new.php' ) === 0 ) {
            return current_user_can( 'edit_users' );
        }

        if ( strpos( $target, 'profile.php' ) === 0 ) {
            return is_user_logged_in(); // Ù‡Ù…Ù‡ Ú©Ø§Ø±Ø¨Ø±Ø§Ù† Ù„Ø§Ú¯ÛŒÙ† Ø´Ø¯Ù‡
        }

        // â­ Û¹. Ø¨Ø±Ø±Ø³ÛŒ ÙˆÛŒØ±Ø§ÛŒØ´Ú¯Ø±Ù‡Ø§ (Ø®Ø·Ø±Ù†Ø§Ú© - ÙÙ‚Ø· Ø§Ø¯Ù…ÛŒÙ†)
        if ( strpos( $target, 'plugin-editor.php' ) === 0 || strpos( $target, 'theme-editor.php' ) === 0 ) {
            return current_user_can( 'manage_options' );
        }

        // â­ Û±Û°. Ø¨Ø±Ø±Ø³ÛŒ ØµÙØ­Ø§Øª ØªÙ†Ø¸ÛŒÙ…Ø§Øª (ÙÙ‚Ø· Ø§Ø¯Ù…ÛŒÙ†)
        if ( strpos( $target, 'options-' ) === 0 ) {
            return current_user_can( 'manage_options' );
        }

        // â­ Û±Û±. Whitelist ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ core ÙˆØ±Ø¯Ù¾Ø±Ø³
        $core_pages = array(
            'edit-comments.php',
            'users.php',
            'plugins.php', 'plugin-install.php',
            'themes.php', 'customize.php', 'nav-menus.php', 'widgets.php',
            'tools.php', 'import.php', 'export.php', 'site-health.php',
            'export-personal-data.php', 'erase-personal-data.php',
            'upload.php', 'media-new.php', 'media.php',
        );

        // Ø§Ø³ØªØ®Ø±Ø§Ø¬ Ù†Ø§Ù… ÙØ§ÛŒÙ„ (Ø¨Ø¯ÙˆÙ† query string)
        $target_file = strtok( $target, '?' );
        
        if ( in_array( $target_file, $core_pages, true ) ) {
            return true;
        }

        // â­ Û±Û². Ø¨Ø±Ø±Ø³ÛŒ ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ Ù…ÙˆØ¬ÙˆØ¯ Ø¯Ø± wp-admin (Ø¨Ø§ Ù…Ø­Ø§ÙØ¸Øª Ú©Ø§Ù…Ù„)
        // ÙÙ‚Ø· Ø§Ø¬Ø§Ø²Ù‡ Ø¯Ø³ØªØ±Ø³ÛŒ Ø¨Ù‡ ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ÛŒ Ù…Ø³ØªÙ‚ÛŒÙ… Ø¯Ø§Ø®Ù„ wp-admin
        if ( ! preg_match( '/^[a-z0-9_-]+\.php$/i', $target_file ) ) {
            return false;
        }

        $file_path = ABSPATH . 'wp-admin/' . $target_file;
        $real_path = realpath( $file_path );
        
        if ( $real_path === false ) {
            return false;
        }

        // Ø§Ø·Ù…ÛŒÙ†Ø§Ù† Ø§Ø² Ø§ÛŒÙ†Ú©Ù‡ ÙØ§ÛŒÙ„ ÙˆØ§Ù‚Ø¹Ø§Ù‹ Ø¯Ø§Ø®Ù„ wp-admin Ø§Ø³Øª (Ø¬Ù„ÙˆÚ¯ÛŒØ±ÛŒ Ø§Ø² path traversal)
        $wp_admin_path = realpath( ABSPATH . 'wp-admin' );
        if ( strpos( $real_path, $wp_admin_path ) !== 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Ø¨Ø±Ø±Ø³ÛŒ Ø¯Ø³ØªØ±Ø³ÛŒ Ú©Ø§Ø±Ø¨Ø± Ø¨Ù‡ ÛŒÚ© page slug
     */
    private function user_can_access_page( $page ) {
        global $submenu;
        
        // Û±. Ø¨Ø±Ø±Ø³ÛŒ Ø¯Ø± Ù‡Ù…Ù‡ submenu Ù‡Ø§
        foreach ( $submenu as $parent => $items ) {
            foreach ( $items as $item ) {
                if ( isset( $item[2] ) && $item[2] === $page ) {
                    $capability = $item[1];
                    return current_user_can( $capability );
                }
            }
        }
        
        // Û². Ø¨Ø±Ø±Ø³ÛŒ Ø¯Ø± top-level menu
        global $menu;
        foreach ( $menu as $menu_item ) {
            if ( isset( $menu_item[2] ) && $menu_item[2] === $page ) {
                $capability = $menu_item[1];
                return current_user_can( $capability );
            }
        }
        
        // Û³. Ø§Ú¯Ø± page Ø¯Ø± Ù…Ù†ÙˆÙ‡Ø§ Ø«Ø¨Øª Ù†Ø´Ø¯Ù‡ØŒ Ø¨Ø±Ø±Ø³ÛŒ ÙˆØ¬ÙˆØ¯ hook
        $hook_name = get_plugin_page_hookname( $page, '' );
        if ( has_action( $hook_name ) !== false ) {
            // Page Ø¯Ø§Ø±Ø§ÛŒ hook Ø§Ø³ØªØŒ Ø§Ø¬Ø§Ø²Ù‡ Ø¨Ø¯Ù‡ ÙˆØ±Ø¯Ù¾Ø±Ø³ Ø®ÙˆØ¯Ø´ Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†Ø¯
            return true;
        }
        
        // Û´. Ø¨Ø±Ø±Ø³ÛŒ Ø¨Ø§ parent Ù‡Ø§ÛŒ Ù…Ø®ØªÙ„Ù
        foreach ( $submenu as $parent => $items ) {
            $hook_name = get_plugin_page_hookname( $page, $parent );
            if ( has_action( $hook_name ) !== false ) {
                return true;
            }
        }
        
        // Ûµ. Ø§Ú¯Ø± Ù‡ÛŒÚ†â€ŒÚ©Ø¯Ø§Ù… Ú©Ø§Ø± Ù†Ú©Ø±Ø¯ØŒ Ø§Ø¬Ø§Ø²Ù‡ Ø¨Ø¯Ù‡ Ùˆ Ø¨Ú¯Ø°Ø§Ø± ÙˆØ±Ø¯Ù¾Ø±Ø³ Ø®ÙˆØ¯Ø´ Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†Ø¯
        // (ÙˆØ±Ø¯Ù¾Ø±Ø³ Ø¯Ø± ØµÙˆØ±Øª Ø¹Ø¯Ù… Ø¯Ø³ØªØ±Ø³ÛŒ Ø§Ø±ÙˆØ± Ù…ÛŒâ€ŒØ¯Ù‡Ø¯)
        return true;
    }

    /**
     * Render iframe wrapper
     */
    private function render_iframe_wrapper( $url, $title ) {
        // اضافه کردن query parameter برای تشخیص iframe
        $iframe_url = add_query_arg( 'nextsafar_iframe', '1', $url );

        require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';  // ✅ صحیح
        ?>
        <div class="nextsafar-main">
            <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>  // ✅ صحیح

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
        require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';  // ✅ صحیح
    }

    /**
     * Helper: Ø³Ø§Ø®Øª wrapper URL Ø¨Ø±Ø§ÛŒ Ù‡Ø± target
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
