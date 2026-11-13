<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Icons {

    /**
     * Ù…Ø³ÛŒØ± Ù¾Ø§ÛŒÙ‡ Ø¢ÛŒÚ©ÙˆÙ†â€ŒÙ‡Ø§
     */
    private static $icons_path = 'assets/admin/icons/';

    /**
     * ðŸš€ Ú©Ø´ Ø¯Ø§Ø®Ù„ÛŒ Ø¨Ø±Ø§ÛŒ SVGÙ‡Ø§ÛŒ Ù¾Ø±Ø¯Ø§Ø²Ø´â€ŒØ´Ø¯Ù‡
     * Ø¬Ù„ÙˆÚ¯ÛŒØ±ÛŒ Ø§Ø² Ø®ÙˆØ§Ù†Ø¯Ù† Ùˆ Ù¾Ø±Ø¯Ø§Ø²Ø´ Ù…Ú©Ø±Ø± ÙØ§ÛŒÙ„â€ŒÙ‡Ø§ Ø¯Ø± ÛŒÚ© Request
     */
    private static $cache = array();

    /* =========================================================
       Ù…ØªØ¯Ù‡Ø§ÛŒ Ø¹Ù…ÙˆÙ…ÛŒ
    ========================================================== */

    /**
     * Ú¯Ø±ÙØªÙ† URL Ø¢ÛŒÚ©ÙˆÙ†
     */
    public static function get_url( $name ) {
        return NEXTSAFAR_URL . 'assets/admin/' . self::$icons_path . $name . '.svg';
    }

    /**
     * Ú¯Ø±ÙØªÙ† Path Ø¢ÛŒÚ©ÙˆÙ†
     */
    public static function get_path( $name ) {
        return NEXTSAFAR_PATH . self::$icons_path . $name . '.svg';
    }

    /**
     * Ø¨Ø±Ø±Ø³ÛŒ ÙˆØ¬ÙˆØ¯ Ø¢ÛŒÚ©ÙˆÙ† SVG
     */
    public static function exists( $name ) {
        return file_exists( self::get_path( $name ) );
    }

    /**
     * â­ Ù†Ù…Ø§ÛŒØ´ SVG inline Ø¨Ø§ Ù‚Ø§Ø¨Ù„ÛŒØª ØªØºÛŒÛŒØ± Ø±Ù†Ú¯ (Ø¨Ø§ Ú©Ø´)
     */
    public static function svg( $name, $size = 20, $class = '' ) {
        $path = self::get_path( $name );
        
        if ( ! file_exists( $path ) ) {
            return '<span class="ns-icon ns-icon-missing" style="width:' . $size . 'px;height:' . $size . 'px;display:inline-block;background:#eee;border-radius:4px;"></span>';
        }

        // Ú©Ù„ÛŒØ¯ ÛŒÚ©ØªØ§ Ø¨Ø±Ø§ÛŒ Ú©Ø´
        $cache_key = $name . '_' . $size . '_' . $class;
        
        // Ø¨Ø±Ø±Ø³ÛŒ Ú©Ø´
        if ( isset( self::$cache[ $cache_key ] ) ) {
            return self::$cache[ $cache_key ];
        }

        $svg = file_get_contents( $path );
        
        if ( empty( $svg ) ) {
            return '';
        }

        $class_attr = ! empty( $class ) ? ' ' . $class : '';
        $result = self::prepare_svg( $svg, $name, $size, $class_attr );
        
        // Ø°Ø®ÛŒØ±Ù‡ Ø¯Ø± Ú©Ø´
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
    // Ø­Ø°Ù Ø³Ø±ÛŒØ¹ Ø¨Ø®Ø´â€ŒÙ‡Ø§ÛŒ ØºÛŒØ±Ø¶Ø±ÙˆØ±ÛŒ
    $svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg );
    $svg = preg_replace( '/<!DOCTYPE[^>]*>/', '', $svg );
    $svg = preg_replace( '/<!--.*?-->/', '', $svg );
    $svg = preg_replace( '/\s+/', ' ', $svg );  // Ø§ØµÙ„Ø§Ø­ Ø´Ø¯Ù‡
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

    // ØªØ¨Ø¯ÛŒÙ„ fill Ùˆ stroke Ø¨Ù‡ currentColor
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
     * Ø¢ÛŒÚ©ÙˆÙ† Ø¨Ø§ <img> (Ø¨Ø±Ø§ÛŒ Ø¢ÛŒÚ©ÙˆÙ†â€ŒÙ‡Ø§ÛŒ Ú†Ù†Ø¯ Ø±Ù†Ú¯)
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
       Ù…ØªØ¯Ù‡Ø§ÛŒ CPT Icons
    ========================================================== */

    /**
     * â­ Ú¯Ø±ÙØªÙ† Ø¢ÛŒÚ©ÙˆÙ† Ø¨Ø±Ø§ÛŒ Custom Post Type
     */
    public static function get_cpt_icon( $post_type, $size = 20, $fallback_dashicon = 'dashicons-admin-post' ) {
        // Ø§ÙˆÙ„ÙˆÛŒØª Û±: Ø¢ÛŒÚ©ÙˆÙ†â€ŒÙ‡Ø§ÛŒ Ø«Ø¨Øª Ø´Ø¯Ù‡ Ø¨Ø§ ØªØ§Ø¨Ø¹ helper
        $registered_icons = nextsafar_get_registered_cpt_icons();
        if ( isset( $registered_icons[ $post_type ] ) ) {
            return self::render_icon_config( $registered_icons[ $post_type ], $post_type, $size );
        }

        // Ø§ÙˆÙ„ÙˆÛŒØª Û²: ÙÛŒÙ„ØªØ± Ù‚Ø§Ù„Ø¨
        $theme_icons = apply_filters( 'nextsafar_cpt_icons', array() );
        if ( isset( $theme_icons[ $post_type ] ) ) {
            return self::render_icon_config( $theme_icons[ $post_type ], $post_type, $size );
        }

        // Ø§ÙˆÙ„ÙˆÛŒØª Û³: ÙØ§ÛŒÙ„ SVG Ø¯Ø± Ù¾ÙˆØ´Ù‡ assets/icons/
        $path = self::get_path( $post_type );
        if ( file_exists( $path ) ) {
            return self::svg( $post_type, $size );
        }

        // Ø§ÙˆÙ„ÙˆÛŒØª Û´: Fallback Ø¨Ù‡ dashicons
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
     * Render Ø¢ÛŒÚ©ÙˆÙ† Ø¨Ø± Ø§Ø³Ø§Ø³ config
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
     * Ø³Ø§Ø®Øª inline SVG Ø§Ø² Ú©Ø¯ path
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
     * â­ Ú¯Ø±ÙØªÙ† Ø¢ÛŒÚ©ÙˆÙ† Ø¨Ø±Ø§ÛŒ Ù…Ù†ÙˆÛŒ Ø¯Ù„Ø®ÙˆØ§Ù‡
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
     * Ù¾Ø§Ú©Ø³Ø§Ø²ÛŒ Ú©Ø´ (Ø¨Ø±Ø§ÛŒ Ø§Ø³ØªÙØ§Ø¯Ù‡ Ø¯Ø± Ù…ÙˆØ§Ù‚Ø¹ Ø®Ø§Øµ)
     */
    public static function clear_cache() {
        self::$cache = array();
    }
}

