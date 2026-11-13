<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_External_Menus {

    private $menus = array();
    private $cpt_menus = array();
    private $plugin_menus = array();

    public function init() {
        add_action( 'admin_menu', array( $this, 'collect_menus' ), 9999 );
    }

    public function collect_menus() {
        global $menu, $submenu;

        if ( empty( $menu ) ) {
            return;
        }

        foreach ( $menu as $menu_item ) {
            if ( ! isset( $menu_item[0] ) || empty( $menu_item[0] ) ) {
                continue;
            }

            if ( strpos( $menu_item[0], 'separator' ) !== false ) {
                continue;
            }

            $slug = isset( $menu_item[2] ) ? $menu_item[2] : '';

            if ( $this->should_exclude( $slug ) ) {
                continue;
            }

            $menu_data = $this->build_menu_data( $menu_item, $slug, $submenu );

            if ( ! $menu_data ) {
                continue;
            }

            if ( $menu_data['type'] === 'cpt' ) {
                $this->cpt_menus[ $menu_data['post_type'] ] = $menu_data;
            } elseif ( $menu_data['type'] === 'plugin' ) {
                $this->plugin_menus[] = $menu_data;
            }
        }
    }

    private function should_exclude( $slug ) {
        // ۱. صفحات core وردپرس که خودمان در MANAGEMENT مدیریت می‌کنیم
        $exclude = array(
            'index.php',
            'themes.php',
            'plugins.php',
            'tools.php',
            'options-general.php',
            'edit.php',
            'upload.php',
            'edit-comments.php',
            'edit.php?post_type=page',
            'users.php',
            'user-new.php',
            'profile.php',
            'user-edit.php',
        );

        if ( in_array( $slug, $exclude, true ) ) {
            return true;
        }

        // ۲. ✅ فقط صفحات داخلی پنل NextSafar را exclude کن (نه پلاگین nextsafar-core)
        $internal_nextsafar_pages = array(
            'nextsafar-admin',
            'nextsafar-cpt',
            'nextsafar-cpt-taxonomy',
            'nextsafar-post-editor',
            'nextsafar-media',
            'nextsafar-updates',
            'nextsafar-wp-wrapper',
            'nextsafar-posts',
            'nextsafar-pages',
            'nextsafar-categories',
        );

        if ( in_array( $slug, $internal_nextsafar_pages, true ) ) {
            return true;
        }

        // ۳. فیلتر منوهای تکراری کاربران از پلاگین‌های ثالث
        $user_related_plugins = array(
            'user-role-editor',
            'profile-builder',
            'members',
            'capability-manager',
        );

        foreach ( $user_related_plugins as $plugin_slug ) {
            if ( strpos( strtolower( $slug ), $plugin_slug ) !== false ) {
                return true;
            }
        }

        return false;
    }

private function build_menu_data( $menu_item, $slug, $submenu ) {
    $title = wp_strip_all_tags( $menu_item[0] );
    $capability = isset( $menu_item[1] ) ? $menu_item[1] : 'manage_options';

    if ( ! current_user_can( $capability ) ) {
        return null;
    }

    $type = $this->detect_menu_type( $slug );
    $icon = $this->get_icon( $menu_item, $slug );
    $url = $this->build_wrapper_url( $slug );
    $page_slug = $this->extract_page_slug( $slug );
    $submenus = $this->get_submenus( $slug, $submenu, $capability );

    $data = array(
        'title'     => $title,
        'url'       => $url,
        'icon'      => $icon,
        'page_slug' => $page_slug,
        'submenus'  => $submenus,
        'type'      => $type,
        'raw_slug'  => $slug,
    );

    if ( $type === 'cpt' ) {
        $data['post_type'] = $this->extract_post_type( $slug );
    }
    
    // 🆕 اضافه کردن taxonomy
    if ( $type === 'taxonomy' ) {
        $data['taxonomy'] = $this->extract_taxonomy( $slug );
    }

    return $data;
}
/**
 * استخراج taxonomy از slug
 */
private function extract_taxonomy( $slug ) {
    // روش ۱: regex ساده
    if ( preg_match( '/taxonomy=([^&]+)/', $slug, $matches ) ) {
        return sanitize_key( $matches[1] );
    }
    
    // روش ۲: parse_str
    $query = parse_url( $slug, PHP_URL_QUERY );
    if ( ! empty( $query ) ) {
        parse_str( $query, $params );
        if ( isset( $params['taxonomy'] ) ) {
            return sanitize_key( $params['taxonomy'] );
        }
    }
    
    return '';
}
/**
 * تشخیص نوع منو (CPT یا Plugin)
 */
private function detect_menu_type( $slug ) {
    // بررسی CPT با روش‌های مختلف
    
    // ۱. بررسی مستقیم با strpos (برای حالت‌های ساده)
    if ( strpos( $slug, 'edit.php?post_type=' ) === 0 ) {
        return 'cpt';
    }
    
    if ( strpos( $slug, 'post-new.php?post_type=' ) === 0 ) {
        return 'cpt';
    }
    
    // ۲. بررسی با strpos معمولی (برای حالت‌های پیچیده‌تر)
    if ( strpos( $slug, 'post_type=' ) !== false ) {
        // بررسی اینکه آیا post_type واقعاً در slug وجود دارد
        if ( preg_match( '/[?&]post_type=([^&]+)/', $slug, $matches ) ) {
            $post_type = $matches[1];
            // بررسی اینکه آیا این post_type واقعاً وجود دارد
            if ( post_type_exists( $post_type ) ) {
                return 'cpt';
            }
        }
    }
    
    // ۳. بررسی taxonomy
    if ( strpos( $slug, 'edit-tags.php?taxonomy=' ) === 0 ) {
        return 'taxonomy';
    }
    
    if ( strpos( $slug, 'term.php?taxonomy=' ) === 0 ) {
        return 'taxonomy';
    }
    
    // ۴. بررسی با regex (برای حالت‌های خیلی پیچیده)
    if ( preg_match( '/edit\.php.*post_type=([^&]+)/', $slug ) ) {
        return 'cpt';
    }
    
    if ( preg_match( '/post-new\.php.*post_type=([^&]+)/', $slug ) ) {
        return 'cpt';
    }
    
    return 'plugin';
}

/**
 * استخراج post_type از slug
 */
private function extract_post_type( $slug ) {
    // روش ۱: regex ساده
    if ( preg_match( '/post_type=([^&]+)/', $slug, $matches ) ) {
        return sanitize_key( $matches[1] );
    }
    
    // روش ۲: parse_str (برای URL های پیچیده)
    $query = parse_url( $slug, PHP_URL_QUERY );
    if ( ! empty( $query ) ) {
        parse_str( $query, $params );
        if ( isset( $params['post_type'] ) ) {
            return sanitize_key( $params['post_type'] );
        }
    }
    
    return '';
}

    private function get_icon( $menu_item, $slug ) {
        
        // ⭐ اولویت ۱: بررسی آیکون‌های ثبت شده با nextsafar_register_menu_icon()
        $registered_menu_icons = nextsafar_get_registered_menu_icons();
        if ( isset( $registered_menu_icons[ $slug ] ) ) {
            return 'custom:' . $slug;
        }
        
        // آیکون‌های خاص پلاگین‌ها
        $plugin_icons = array(
            'wpseo_dashboard'  => array( 'svg' => 'seo', 'dashicon' => 'dashicons-chart-line' ),
            'yoast-seo'        => array( 'svg' => 'seo', 'dashicon' => 'dashicons-chart-line' ),
            'elementor'        => array( 'svg' => 'elementor', 'dashicon' => 'dashicons-welcome-widgets-menus' ),
            'woocommerce'      => array( 'svg' => 'cart', 'dashicon' => 'dashicons-cart' ),
            'contact-form-7'   => array( 'svg' => 'email', 'dashicon' => 'dashicons-email' ),
        );

        if ( isset( $plugin_icons[ $slug ] ) ) {
            $icon_config = $plugin_icons[ $slug ];
            if ( class_exists( 'NextSafar_Icons' ) && NextSafar_Icons::exists( $icon_config['svg'] ) ) {
                return 'svg:' . $icon_config['svg'];
            }
            return $icon_config['dashicon'];
        }

        // برای CPT ها
        if ( $this->detect_menu_type( $slug ) === 'cpt' ) {
            $post_type = $this->extract_post_type( $slug );
            return 'cpt:' . $post_type;
        }

        // آیکون وردپرس پیش‌فرض
        if ( ! empty( $menu_item[6] ) && strpos( $menu_item[6], 'dashicons-' ) === 0 ) {
            return $menu_item[6];
        }

        return 'dashicons-admin-generic';
    }

    private function build_wrapper_url( $slug ) {
        // 🆕 CPT Listing → صفحه اختصاصی NextSafar
        if ( strpos( $slug, 'edit.php?post_type=' ) === 0 ) {
            $post_type = $this->extract_post_type( $slug );
            if ( $post_type && post_type_exists( $post_type ) ) {
                return admin_url( 'admin.php?page=nextsafar-cpt&post_type=' . $post_type );
            }
        }

        // 🆕 صفحه افزودن پست → مستقیم به post-new.php استاندارد وردپرس
        if ( preg_match( '/post-new\.php/', $slug ) ) {
            $post_type = $this->extract_post_type( $slug );
            $url = admin_url( 'post-new.php' );
            if ( ! empty( $post_type ) && 'post' !== $post_type ) {
                $url = add_query_arg( 'post_type', $post_type, $url );
            }
            return $url;
        }

        // 🆕 صفحه ویرایش پست → مستقیم به post.php استاندارد وردپرس
        if ( preg_match( '/post\.php\?post=([0-9]+)/', $slug, $matches ) ) {
            $post_id = (int) $matches[1];
            return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        }

// 🆕 Taxonomy → مستقیم به edit-tags.php و term.php استاندارد وردپرس
if ( strpos( $slug, 'edit-tags.php?taxonomy=' ) === 0 ) {
    if ( preg_match( '/edit-tags\.php.*taxonomy=([^&]+)/', $slug, $matches ) ) {
        $taxonomy_name = $matches[1];
        if ( taxonomy_exists( $taxonomy_name ) ) {
            return admin_url( 'edit-tags.php?taxonomy=' . $taxonomy_name );
        }
    }
}

// 🆕 صفحه ویرایش ترم → مستقیم به term.php استاندارد وردپرس
if ( preg_match( '/term\.php\?.*taxonomy=([^&]+)/', $slug, $matches ) ) {
    $taxonomy_name = $matches[1];
    if ( taxonomy_exists( $taxonomy_name ) ) {
        return admin_url( $slug ); // URL اصلی وردپرس
    }
}

        // admin.php?page=xxx
        if ( strpos( $slug, 'admin.php?page=' ) === 0 ) {
            parse_str( parse_url( $slug, PHP_URL_QUERY ), $params );
            $page = isset( $params['page'] ) ? $params['page'] : '';
            return NextSafar_WP_Pages_Wrapper::build_wrapper_url( 'admin.php', array( 'page' => $page ) );
        }

        // admin.php با query های دیگر
        if ( strpos( $slug, 'admin.php' ) === 0 ) {
            parse_str( parse_url( $slug, PHP_URL_QUERY ), $params );
            return NextSafar_WP_Pages_Wrapper::build_wrapper_url( 'admin.php', $params );
        }

        // فایل PHP
        if ( preg_match( '/^([^?]+\.php)(\?(.*))?$/', $slug, $matches ) ) {
            $file = $matches[1];
            $params = array();
            if ( isset( $matches[3] ) ) {
                parse_str( $matches[3], $params );
            }
            return NextSafar_WP_Pages_Wrapper::build_wrapper_url( $file, $params );
        }

        // fallback
        return NextSafar_WP_Pages_Wrapper::build_wrapper_url( $slug );
    }

    private function extract_page_slug( $slug ) {
        if ( strpos( $slug, 'page=' ) !== false ) {
            parse_str( $slug, $parsed );
            return isset( $parsed['page'] ) ? $parsed['page'] : '';
        }
        if ( strpos( $slug, '.php' ) === false && strpos( $slug, '?' ) === false ) {
            return $slug;
        }
        return '';
    }

    private function get_submenus( $parent_slug, $submenu, $capability ) {
        $result = array();

        if ( ! isset( $submenu[ $parent_slug ] ) ) {
            return $result;
        }

        foreach ( $submenu[ $parent_slug ] as $sub_item ) {
            if ( ! isset( $sub_item[0] ) || empty( $sub_item[0] ) ) {
                continue;
            }

            $sub_capability = isset( $sub_item[1] ) ? $sub_item[1] : $capability;
            if ( ! current_user_can( $sub_capability ) ) {
                continue;
            }

            $sub_slug = isset( $sub_item[2] ) ? $sub_item[2] : '';

            $result[] = array(
                'title'     => wp_strip_all_tags( $sub_item[0] ),
                'url'       => $this->build_wrapper_url( $sub_slug ),
                'page_slug' => $this->extract_page_slug( $sub_slug ),
            );
        }

        return $result;
    }

    public function get_cpt_menus() {
        return $this->cpt_menus;
    }

    public function get_plugin_menus() {
        return $this->plugin_menus;
    }

    public function get_menus() {
        return array_merge( $this->cpt_menus, $this->plugin_menus );
    }
}