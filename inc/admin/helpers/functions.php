<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ============================================
 * Helper Function: ثبت آیکون CPT
 * ============================================
 */
function nextsafar_register_cpt_icon( $post_type, $icon, $type = 'svg-inline' ) {
    global $nextsafar_cpt_icons;
    if ( ! is_array( $nextsafar_cpt_icons ) ) {
        $nextsafar_cpt_icons = array();
    }
    $nextsafar_cpt_icons[ $post_type ] = array(
        'type' => $type,
        'icon' => $icon,
    );
}

/**
 * ثبت چند آیکون یکجا
 */
function nextsafar_register_cpt_icons( $icons ) {
    foreach ( $icons as $post_type => $icon ) {
        if ( is_array( $icon ) ) {
            nextsafar_register_cpt_icon(
                $post_type,
                $icon['icon'],
                isset( $icon['type'] ) ? $icon['type'] : 'svg-inline'
            );
        } else {
            $type = 'svg-inline';
            if ( strpos( $icon, 'dashicons-' ) === 0 ) {
                $type = 'dashicon';
            } elseif ( filter_var( $icon, FILTER_VALIDATE_URL ) ) {
                $type = 'svg-url';
            } elseif ( file_exists( $icon ) ) {
                $type = 'svg-file';
            }
            nextsafar_register_cpt_icon( $post_type, $icon, $type );
        }
    }
}

function nextsafar_get_registered_cpt_icons() {
    global $nextsafar_cpt_icons;
    return is_array( $nextsafar_cpt_icons ) ? $nextsafar_cpt_icons : array();
}

/**
 * ثبت آیکون برای منوهای غیر CPT
 */
function nextsafar_register_menu_icon( $slug, $icon, $type = 'svg-inline' ) {
    global $nextsafar_menu_icons;
    if ( ! is_array( $nextsafar_menu_icons ) ) {
        $nextsafar_menu_icons = array();
    }
    if ( empty( $type ) || $type === 'svg-inline' ) {
        if ( strpos( $icon, 'dashicons-' ) === 0 ) {
            $type = 'dashicon';
        } elseif ( filter_var( $icon, FILTER_VALIDATE_URL ) ) {
            $type = 'svg-url';
        } elseif ( file_exists( $icon ) ) {
            $type = 'svg-file';
        } else {
            $type = 'svg-inline';
        }
    }
    $nextsafar_menu_icons[ $slug ] = array(
        'type' => $type,
        'icon' => $icon,
    );
}

function nextsafar_register_menu_icons( $icons ) {
    foreach ( $icons as $slug => $icon ) {
        if ( is_array( $icon ) ) {
            nextsafar_register_menu_icon(
                $slug,
                $icon['icon'],
                isset( $icon['type'] ) ? $icon['type'] : 'svg-inline'
            );
        } else {
            nextsafar_register_menu_icon( $slug, $icon );
        }
    }
}

function nextsafar_get_registered_menu_icons() {
    global $nextsafar_menu_icons;
    return is_array( $nextsafar_menu_icons ) ? $nextsafar_menu_icons : array();
}

/**
 * Helper Function: ساخت URL برای Post Editor
 *
 * استفاده:
 * nextsafar_editor_url( 'post' )           → افزودن post
 * nextsafar_editor_url( 'post', 123 )      → ویرایش post با ID 123
 * nextsafar_editor_url( 'tour' )           → افزودن tour
 * nextsafar_editor_url( 'page', 456 )      → ویرایش page با ID 456
 */
function nextsafar_editor_url( $post_type, $post_id = 0 ) {
    if ( $post_id > 0 ) {
        return admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' );
    }
    $url = admin_url( 'post-new.php' );
    if ( 'post' !== $post_type ) {
        $url = add_query_arg( 'post_type', sanitize_key( $post_type ), $url );
    }
    return $url;
}

if ( ! function_exists( 'nextsafar_user_can_menu' ) ) {
    /**
     * آیا کاربر فعلی اجازه دیدن این منو را دارد؟
     * نسخه ساده: فقط برای ادمین کل کار می‌کند
     */
    function nextsafar_user_can_menu( $slug ) {
        // ادمین کل همیشه دسترسی دارد
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        
        // بقیه نقش‌ها: فعلاً دسترسی ندارند (در آینده می‌توان Role Access را اضافه کرد)
        return false;
    }
}