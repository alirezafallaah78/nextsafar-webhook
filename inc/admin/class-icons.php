<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Icons {

    /**
     * مسیر پایه آیکون‌ها
     */
    private static $icons_path = 'assets/admin/icons/';

    /**
     * 🚀 کش داخلی برای SVGهای پردازش‌شده
     * جلوگیری از خواندن و پردازش مکرر فایل‌ها در یک Request
     */
    private static $cache = array();

    /* =========================================================
       متدهای عمومی
    ========================================================== */

    /**
     * گرفتن URL آیکون
     */
    public static function get_url( $name ) {
        return NEXTSAFAR_URL . self::$icons_path . $name . '.svg';
    }

    /**
     * گرفتن Path آیکون
     */
    public static function get_path( $name ) {
        return NEXTSAFAR_ADMIN_PATH . self::$icons_path . $name . '.svg';
    }

    /**
     * بررسی وجود آیکون SVG
     */
    public static function exists( $name ) {
        return file_exists( self::get_path( $name ) );
    }

    /**
     * ⭐ نمایش SVG inline با قابلیت تغییر رنگ (با کش)
     */
    public static function svg( $name, $size = 20, $class = '' ) {
        $path = self::get_path( $name );
        
        if ( ! file_exists( $path ) ) {
            return '<span class="ns-icon ns-icon-missing" style="width:' . $size . 'px;height:' . $size . 'px;display:inline-block;background:#eee;border-radius:4px;"></span>';
        }

        // کلید یکتا برای کش
        $cache_key = $name . '_' . $size . '_' . $class;
        
        // بررسی کش
        if ( isset( self::$cache[ $cache_key ] ) ) {
            return self::$cache[ $cache_key ];
        }

        $svg = file_get_contents( $path );
        
        if ( empty( $svg ) ) {
            return '';
        }

        $class_attr = ! empty( $class ) ? ' ' . $class : '';
        $result = self::prepare_svg( $svg, $name, $size, $class_attr );
        
        // ذخیره در کش
        self::$cache[ $cache_key ] = $result;
        
        return $result;
    }

    /**
     * Display SVG inline (echo)
     */
    public static function display( $name, $size = 20, $class = '' ) {
        echo self::svg( $name, $size, $class );
    }

private static function prepare_svg( $svg, $name, $size, $class ) {
    // حذف سریع بخش‌های غیرضروری
    $svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg );
    $svg = preg_replace( '/<!DOCTYPE[^>]*>/', '', $svg );
    $svg = preg_replace( '/<!--.*?-->/', '', $svg );
    $svg = preg_replace( '/\s+/', ' ', $svg );  // اصلاح شده
    $svg = trim( $svg );

    $classes = 'ns-icon ns-icon-' . esc_attr( $name );
    if ( ! empty( $class ) ) {
        $classes .= ' ' . $class;
    }

    $svg = preg_replace(
        '/<svg([^>]*)>/i',
        '<svg$1 class="' . $classes . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">',
        $svg,
        1
    );

    // تبدیل fill و stroke به currentColor
    $svg = preg_replace( '/fill="(?!currentColor)[^"]*"/i', 'fill="currentColor"', $svg );
    $svg = preg_replace( '/stroke="(?!currentColor)[^"]*"/i', 'stroke="currentColor"', $svg );

    if ( strpos( $svg, 'fill="currentColor"' ) === false && strpos( $svg, 'stroke="currentColor"' ) === false ) {
        $svg = str_replace( '<svg', '<svg fill="currentColor"', $svg );
    }

    return $svg;
}

private static function process_full_svg( $svg, $name, $size ) {
    $svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg );
    $svg = preg_replace( '/<!DOCTYPE[^>]*>/', '', $svg );
    
    $new_classes = 'ns-icon ns-icon-' . esc_attr( $name );

    if ( preg_match( '/class="[^"]*"/i', $svg ) ) {
        $svg = preg_replace( '/class="[^"]*"/i', 'class="' . $new_classes . '"', $svg, 1 );
    } else {
        $svg = preg_replace( '/<svg/i', '<svg class="' . $new_classes . '"', $svg, 1 );
    }

    $svg = preg_replace( '/\s+width="[^"]*"/i', ' width="' . $size . '"', $svg );
    $svg = preg_replace( '/\s+height="[^"]*"/i', ' height="' . $size . '"', $svg );

    if ( strpos( $svg, 'width=' ) === false ) {
        $svg = preg_replace( '/<svg/i', '<svg width="' . $size . '"', $svg, 1 );
    }

    if ( strpos( $svg, 'height=' ) === false ) {
        $svg = preg_replace( '/<svg/i', '<svg height="' . $size . '"', $svg, 1 );
    }

    $svg = preg_replace( '/fill="(?!none|currentColor)[^"]*"/i', 'fill="currentColor"', $svg );
    $svg = preg_replace( '/stroke="(?!none|currentColor)[^"]*"/i', 'stroke="currentColor"', $svg );

    return $svg;
}
    /**
     * آیکون با <img> (برای آیکون‌های چند رنگ)
     */
    public static function img( $name, $size = 20, $class = '', $alt = '' ) {
        $url = self::get_url( $name );
        $class_attr = ! empty( $class ) ? ' ' . $class : '';

        return sprintf(
            '<img src="%s" alt="%s" width="%d" height="%d" class="ns-icon-img ns-icon-%s%s" loading="lazy">',
            esc_url( $url ),
            esc_attr( $alt ?: $name ),
            $size,
            $size,
            esc_attr( $name ),
            esc_attr( $class_attr )
        );
    }

    public static function display_img( $name, $size = 20, $class = '', $alt = '' ) {
        echo self::img( $name, $size, $class, $alt );
    }

    /* =========================================================
       متدهای CPT Icons
    ========================================================== */

    /**
     * ⭐ گرفتن آیکون برای Custom Post Type
     */
    public static function get_cpt_icon( $post_type, $size = 20, $fallback_dashicon = 'dashicons-admin-post' ) {
        // اولویت ۱: آیکون‌های ثبت شده با تابع helper
        $registered_icons = nextsafar_get_registered_cpt_icons();
        if ( isset( $registered_icons[ $post_type ] ) ) {
            return self::render_icon_config( $registered_icons[ $post_type ], $post_type, $size );
        }

        // اولویت ۲: فیلتر قالب
        $theme_icons = apply_filters( 'nextsafar_cpt_icons', array() );
        if ( isset( $theme_icons[ $post_type ] ) ) {
            return self::render_icon_config( $theme_icons[ $post_type ], $post_type, $size );
        }

        // اولویت ۳: فایل SVG در پوشه assets/icons/
        $path = self::get_path( $post_type );
        if ( file_exists( $path ) ) {
            return self::svg( $post_type, $size );
        }

        // اولویت ۴: Fallback به dashicons
        return sprintf(
            '<span class="dashicons %s" style="width:%dpx;height:%dpx;font-size:%dpx;line-height:%dpx;"></span>',
            esc_attr( $fallback_dashicon ),
            $size,
            $size,
            $size,
            $size
        );
    }

    /**
     * Display CPT icon (echo)
     */
    public static function display_cpt_icon( $post_type, $size = 20, $fallback_dashicon = 'dashicons-admin-post' ) {
        echo self::get_cpt_icon( $post_type, $size, $fallback_dashicon );
    }

    /**
     * Render آیکون بر اساس config
     */
    private static function render_icon_config( $config, $name, $size ) {
        // SVG Inline
        if ( isset( $config['type'] ) && $config['type'] === 'svg-inline' && ! empty( $config['icon'] ) ) {
            $icon = trim( $config['icon'] );
            
            if ( strpos( $icon, '<svg' ) !== false ) {
                return self::process_full_svg( $icon, $name, $size );
            } else {
                return self::build_inline_svg( $icon, $name, $size );
            }
        }

        // SVG File
        if ( isset( $config['type'] ) && $config['type'] === 'svg-file' && ! empty( $config['icon'] ) ) {
            if ( file_exists( $config['icon'] ) ) {
                $svg = file_get_contents( $config['icon'] );
                return self::prepare_svg( $svg, $name, $size, '' );
            }
        }

        // SVG URL
        if ( isset( $config['type'] ) && $config['type'] === 'svg-url' && ! empty( $config['icon'] ) ) {
            return sprintf(
                '<img src="%s" alt="%s" width="%d" height="%d" class="ns-icon-img ns-icon-%s">',
                esc_url( $config['icon'] ),
                esc_attr( $name ),
                $size,
                $size,
                esc_attr( $name )
            );
        }

        // Dashicon
        if ( isset( $config['type'] ) && $config['type'] === 'dashicon' && ! empty( $config['icon'] ) ) {
            return sprintf(
                '<span class="dashicons %s" style="width:%dpx;height:%dpx;font-size:%dpx;line-height:%dpx;"></span>',
                esc_attr( $config['icon'] ),
                $size,
                $size,
                $size,
                $size
            );
        }

        return '';
    }

    /**
     * ساخت inline SVG از کد path
     */
    private static function build_inline_svg( $paths, $name, $size ) {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ns-icon ns-icon-%s" width="%d" height="%d">%s</svg>',
            esc_attr( $name ),
            $size,
            $size,
            $paths
        );
    }

    /**
     * ⭐ گرفتن آیکون برای منوی دلخواه
     */
    public static function get_menu_icon( $slug, $size = 20, $fallback_dashicon = 'dashicons-admin-generic' ) {
        $registered_icons = nextsafar_get_registered_menu_icons();
        if ( isset( $registered_icons[ $slug ] ) ) {
            return self::render_icon_config( $registered_icons[ $slug ], $slug, $size );
        }

        $path = self::get_path( $slug );
        if ( file_exists( $path ) ) {
            return self::svg( $slug, $size );
        }

        return sprintf(
            '<span class="dashicons %s" style="width:%dpx;height:%dpx;font-size:%dpx;line-height:%dpx;"></span>',
            esc_attr( $fallback_dashicon ),
            $size,
            $size,
            $size,
            $size
        );
    }

    public static function display_menu_icon( $slug, $size = 20, $fallback_dashicon = 'dashicons-admin-generic' ) {
        echo self::get_menu_icon( $slug, $size, $fallback_dashicon );
    }

    /**
     * پاکسازی کش (برای استفاده در مواقع خاص)
     */
    public static function clear_cache() {
        self::$cache = array();
    }
}
