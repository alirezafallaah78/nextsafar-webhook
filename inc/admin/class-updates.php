<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom Upgrader Skin for AJAX updates
 */
if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

class NextSafar_Upgrader_Skin extends WP_Upgrader_Skin {
    public $messages = array();
    public $errors   = array();

    public function feedback( $string, ...$args ) {
        if ( isset( $this->upgrader->strings[ $string ] ) ) {
            $string = $this->upgrader->strings[ $string ];
        }
        if ( strpos( $string, '%' ) !== false ) {
            if ( $args ) {
                $string = vsprintf( $string, $args );
            }
        }
        $string = trim( $string );
        $string = wp_strip_all_tags( $string );
        if ( ! empty( $string ) ) {
            $this->messages[] = $string;
        }
    }

    public function header() { return; }
    public function footer() { return; }

    public function error( $errors ) {
        if ( is_string( $errors ) && ! empty( $errors ) ) {
            $this->errors[] = $errors;
        } elseif ( is_wp_error( $errors ) ) {
            foreach ( $errors->get_error_messages() as $message ) {
                $this->errors[] = $message;
            }
        }
    }

    public function get_messages() { return $this->messages; }
    public function get_errors()   { return $this->errors; }
}

/**
 * NextSafar Updates Class
 */
class NextSafar_Updates {

    public function init() {
        // AJAX handlers
        add_action( 'wp_ajax_nextsafar_check_updates',  array( $this, 'ajax_check_updates' ) );
        add_action( 'wp_ajax_nextsafar_update_core',    array( $this, 'ajax_update_core' ) );
        // 🆕 هوک‌های جدید برای آپدیت پلاگین و تم
        add_action( 'wp_ajax_nextsafar_update_plugin',  array( $this, 'ajax_update_plugin' ) );
        add_action( 'wp_ajax_nextsafar_update_theme',   array( $this, 'ajax_update_theme' ) );
    }

    /* =========================================================
    🔄 AJAX: Check for updates
    ========================================================== */
    public function ajax_check_updates() {
        if ( ! current_user_can( 'update_core' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_updates_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        require_once ABSPATH . 'wp-admin/includes/update.php';
        
        // Force check
        wp_version_check( array(), true );
        wp_update_plugins();
        wp_update_themes();

        $data = $this->get_updates_data();
        wp_send_json_success( array( 'data' => $data, 'message' => 'Updates checked successfully.' ) );
    }

    /* =========================================================
    📊 Get all updates data
    ========================================================== */
    public function get_updates_data() {
        if ( ! function_exists( 'get_core_updates' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        // Core
        $core_updates = get_core_updates();
        $core_data    = array(
            'current_version' => get_bloginfo( 'version' ),
            'new_version'     => null,
            'available'       => false,
            'download_url'    => '',
            'locale'          => get_locale(),
        );
        if ( ! empty( $core_updates ) && ! is_wp_error( $core_updates ) ) {
            $first = $core_updates[0];
            if ( isset( $first->response ) && 'upgrade' === $first->response ) {
                $core_data['available']    = true;
                $core_data['new_version']  = $first->current;
                $core_data['download_url'] = $first->download;
            }
        }

        // Plugins 🆕 ایمن‌سازی شده
        $plugin_updates = get_plugin_updates();
        $plugins        = array();
        $plugin_count   = 0;
        if ( ! empty( $plugin_updates ) ) {
            foreach ( $plugin_updates as $plugin_file => $plugin_data ) {
                $update_info = isset( $plugin_data->update ) ? (array) $plugin_data->update : array();
                $plugins[] = array(
                    'file'            => $plugin_file,
                    'name'            => $plugin_data->Name,
                    'current_version' => $plugin_data->Version,
                    'new_version'     => isset( $update_info['new_version'] ) ? $update_info['new_version'] : 'Unknown',
                    'author'          => $plugin_data->Author,
                    'plugin_url'      => $plugin_data->PluginURI,
                    'compatibility'   => $this->get_plugin_compatibility( $update_info ),
                    'changelog'       => isset( $update_info['sections']['changelog'] ) ? $update_info['sections']['changelog'] : '',
                );
                $plugin_count++;
            }
        }

        // Themes 🆕 ایمن‌سازی شده
        $theme_updates = get_theme_updates();
        $themes        = array();
        $theme_count   = 0;
        if ( ! empty( $theme_updates ) ) {
            foreach ( $theme_updates as $theme_slug => $theme_data ) {
                $update_info = isset( $theme_data->update ) ? (array) $theme_data->update : array();
                $themes[] = array(
                    'slug'            => $theme_slug,
                    'name'            => $theme_data->Name,
                    'current_version' => $theme_data->Version,
                    'new_version'     => isset( $update_info['new_version'] ) ? $update_info['new_version'] : 'Unknown',
                    'author'          => $theme_data->Author,
                    'screenshot'      => $theme_data->get_screenshot(),
                );
                $theme_count++;
            }
        }

        // Last checked
        $transient    = get_site_transient( 'update_plugins' );
        $last_checked = 'Never';
        if ( $transient && isset( $transient->last_checked ) ) {
            $last_checked = date( 'Y-m-d H:i:s', $transient->last_checked );
        }

        return array(
            'core'          => $core_data,
            'plugins'       => $plugins,
            'plugin_count'  => $plugin_count,
            'themes'        => $themes,
            'theme_count'   => $theme_count,
            'total_updates' => $plugin_count + $theme_count + ( $core_data['available'] ? 1 : 0 ),
            'last_checked'  => $last_checked,
        );
    }

    private function get_plugin_compatibility( $update_info ) {
        $wp_version = get_bloginfo( 'version' );
        if ( empty( $update_info['tested'] ) ) {
            return array( 'status' => 'unknown', 'message' => 'Compatibility unknown' );
        }
        $tested_up_to = $update_info['tested'];
        if ( version_compare( $wp_version, $tested_up_to, '<=' ) ) {
            return array( 'status' => 'compatible', 'message' => 'Compatible with WordPress ' . $tested_up_to );
        }
        return array( 'status' => 'warning', 'message' => 'Tested up to WordPress ' . $tested_up_to );
    }

    /* =========================================================
    🚀 AJAX: Update WordPress Core
    ========================================================== */
    public function ajax_update_core() {
        if ( ! current_user_can( 'update_core' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_updates_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';

        $updates = get_core_updates();
        if ( empty( $updates ) || is_wp_error( $updates ) ) {
            wp_send_json_error( array( 'message' => 'No updates available.' ), 400 );
        }
        $update = $updates[0];
        if ( ! isset( $update->response ) || 'upgrade' !== $update->response ) {
            wp_send_json_error( array( 'message' => 'WordPress is already up to date.' ), 400 );
        }

        $skin     = new NextSafar_Upgrader_Skin();
        $upgrader = new Core_Upgrader( $skin );
        $result   = $upgrader->upgrade( $update );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message'  => $result->get_error_message(),
                'errors'   => $result->get_error_messages(),
                'messages' => $skin->get_messages(),
            ), 500 );
        }

        // 🆕 پاکسازی کش پس از آپدیت
        wp_version_check( array(), true );
        wp_update_plugins();
        wp_update_themes();

        wp_send_json_success( array(
            'message'  => 'WordPress updated successfully to version ' . $update->current,
            'version'  => $update->current,
            'messages' => $skin->get_messages(),
        ) );
    }

    /* =========================================================
    🆕 AJAX: Update Plugin (اصلاح شده برای رفع خطای Package Not Available)
    ========================================================== */
    public function ajax_update_plugin() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_updates_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $plugin_file = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
        if ( empty( $plugin_file ) ) {
            wp_send_json_error( array( 'message' => 'Plugin not specified.' ), 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // 🆕 ۱. مجبور کردن وردپرس به بررسی مجدد آپدیت‌ها (Force Refresh)
        wp_update_plugins();
        
        // 🆕 ۲. بررسی وجود لینک دانلود (Package URL) در Transient
        $update_plugins_transient = get_site_transient( 'update_plugins' );
        $package_url = '';
        
        if ( isset( $update_plugins_transient->response[ $plugin_file ] ) ) {
            $package_url = isset( $update_plugins_transient->response[ $plugin_file ]->package ) 
                ? $update_plugins_transient->response[ $plugin_file ]->package 
                : '';
        }

        // 🆕 ۳. Fallback: تلاش برای دریافت از get_plugin_updates
        if ( empty( $package_url ) ) {
            $plugin_updates = get_plugin_updates();
            if ( isset( $plugin_updates[ $plugin_file ] ) && isset( $plugin_updates[ $plugin_file ]->update->package ) ) {
                $package_url = $plugin_updates[ $plugin_file ]->update->package;
            }
        }

        // 🆕 ۴. اگر هنوز لینک دانلود پیدا نشد، خطای دقیق و راهنما نمایش بده
        if ( empty( $package_url ) ) {
            wp_send_json_error( array(
                'message' => 'لینک دانلود بسته به‌روزرسانی (Package URL) یافت نشد. اگر این پلاگین اختصاصی است، لطفاً بررسی کنید که لایسنس فعال باشد و سرور آپدیت در دسترس باشد.',
            ), 400 );
        }

        // 🆕 ۵. مقداردهی اولیه فایل‌سیستم وردپرس (رفع خطای دسترسی)
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $skin     = new NextSafar_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );
        $result   = $upgrader->upgrade( $plugin_file );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message'  => $result->get_error_message(),
                'errors'   => $result->get_error_messages(),
                'messages' => $skin->get_messages(),
            ), 500 );
        }

        if ( ! $result ) {
            $skin_errors  = $skin->get_errors();
            $fallback_msg = 'آپدیت ناموفق بود. لطفاً دسترسی‌های فایل (File Permissions) پوشه wp-content/plugins را بررسی کنید.';
            $error_msg    = ! empty( $skin_errors ) ? implode( ' | ', $skin_errors ) : $fallback_msg;

            wp_send_json_error( array(
                'message'  => $error_msg,
                'messages' => $skin->get_messages(),
            ), 500 );
        }

        // پاکسازی کش پس از آپدیت موفق
        wp_clean_plugins_cache();
        wp_update_plugins();
        
        wp_send_json_success( array(
            'message'  => 'پلاگین با موفقیت به‌روزرسانی شد.',
            'plugin'   => $plugin_file,
            'messages' => $skin->get_messages(),
        ) );
    }

    /* =========================================================
    🆕 AJAX: Update Theme
    ========================================================== */
    public function ajax_update_theme() {
        if ( ! current_user_can( 'update_themes' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_updates_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $theme_slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
        if ( empty( $theme_slug ) ) {
            wp_send_json_error( array( 'message' => 'Theme not specified.' ), 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $skin     = new NextSafar_Upgrader_Skin();
        $upgrader = new Theme_Upgrader( $skin );
        $result   = $upgrader->upgrade( $theme_slug );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message'  => $result->get_error_message(),
                'errors'   => $result->get_error_messages(),
                'messages' => $skin->get_messages(),
            ), 500 );
        }
        if ( ! $result ) {
            wp_send_json_error( array(
                'message'  => 'Update failed. Please check file permissions.',
                'messages' => $skin->get_messages(),
            ), 500 );
        }

        wp_clean_themes_cache();
        wp_update_themes();

        wp_send_json_success( array(
            'message'  => 'Theme updated successfully.',
            'theme'    => $theme_slug,
            'messages' => $skin->get_messages(),
        ) );
    }
}