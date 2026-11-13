<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 🛠️ NextSafar Helper Functions
 * تمام توابع کمکی در یک کلاس متمرکز
 */
class NextSafar_Helpers {
    
    /* =========================================================
       📅 توابع تاریخ شمسی
    ========================================================== */
    
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public static function gregorian_to_jalali( $gy, $gm, $gd ) {
        $g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
        $gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
        $days  = 355666 + ( 365 * $gy ) + (int)( ( $gy2 + 3 ) / 4 ) 
               - (int)( ( $gy2 + 99 ) / 100 ) + (int)( ( $gy2 + 399 ) / 400 ) 
               + $gd + $g_d_m[ $gm - 1 ];
        $jy    = -1595 + ( 33 * (int)( $days / 12053 ) );
        $days %= 12053;
        $jy   += 4 * (int)( $days / 1461 );
        $days %= 1461;
        
        if ( $days > 365 ) {
            $jy   += (int)( ( $days - 1 ) / 365 );
            $days  = ( $days - 1 ) % 365;
        }
        
        if ( $days < 186 ) {
            $jm = 1 + (int)( $days / 31 );
            $jd = 1 + ( $days % 31 );
        } else {
            $jm = 7 + (int)( ( $days - 186 ) / 30 );
            $jd = 1 + ( ( $days - 186 ) % 30 );
        }
        
        return array( $jy, $jm, $jd );
    }
    
    /**
     * تبدیل تاریخ میلادی به رشته شمسی
     */
    public static function gregorian_to_jalali_str( $date_str ) {
        $dt = new DateTime( $date_str );
        $gy = (int) $dt->format( 'Y' );
        $gm = (int) $dt->format( 'n' );
        $gd = (int) $dt->format( 'j' );
        
        list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
        
        $months = array( 
            '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 
            'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' 
        );
        
        return $jd . ' ' . $months[ $jm ] . ' ' . $jy;
    }
    
    /**
     * گرفتن تاریخ امروز به شمسی
     */
    public static function get_persian_date() {
        $jalali    = self::gregorian_to_jalali( date( 'Y' ), date( 'n' ), date( 'j' ) );
        $months    = array( 
            '', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 
            'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' 
        );
        $week_days = array( 
            'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 
            'پنج‌شنبه', 'جمعه', 'شنبه' 
        );
        $day_name  = $week_days[ date( 'w' ) ];
        
        return $day_name . '، ' . $jalali[2] . ' ' . $months[ $jalali[1] ] . ' ' . $jalali[0];
    }
    
    /* =========================================================
       📊 آمار KPI داشبورد
    ========================================================== */
    
    /**
     * گرفتن آمار KPI برای داشبورد
     */
    public static function get_kpi_stats() {
        global $wpdb;
        
        $bookings_count = 0;
        if ( post_type_exists( 'booking' ) ) {
            $booking_counts = wp_count_posts( 'booking' );
            $bookings_count = isset( $booking_counts->publish ) ? (int) $booking_counts->publish : 0;
        }
        
        // تعداد رزروها از جدول
        $visa_bookings_count = 0;
        $bookings_table = $wpdb->prefix . 'visa_bookings';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$bookings_table}'" ) === $bookings_table ) {
            $visa_bookings_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table}" );
        }
        
        $visa_count = 0;
        if ( post_type_exists( 'visa' ) ) {
            $visa_counts = wp_count_posts( 'visa' );
            $visa_count  = isset( $visa_counts->publish ) ? (int) $visa_counts->publish : 0;
            $visa_count += isset( $visa_counts->pending ) ? (int) $visa_counts->pending : 0;
        }
        
        $subscribers = count_users();
        $subscribers_count = isset( $subscribers['avail_roles']['subscriber'] ) 
            ? (int) $subscribers['avail_roles']['subscriber'] : 0;
        
        $tours_count = 0;
        if ( post_type_exists( 'tour' ) ) {
            $tour_counts = wp_count_posts( 'tour' );
            $tours_count = isset( $tour_counts->publish ) ? (int) $tour_counts->publish : 0;
        }
        
        return array(
            'bookings'      => $bookings_count,
            'visa_bookings' => $visa_bookings_count,
            'visas'         => $visa_count,
            'subscribers'   => $subscribers_count,
            'tours'         => $tours_count,
        );
    }
    
    /* =========================================================
       🧭 توابع Sidebar Navigation
    ========================================================== */
    
    /**
     * نمایش آیکون سایدبار
     */
    public static function sidebar_icon( $name ) {
        if ( class_exists( 'NextSafar_Icons' ) ) {
            NextSafar_Icons::display( $name, 20 );
        }
    }
    
    /**
     * بررسی تطابق target/file برای active state
     */
    public static function is_current( $files_or_targets ) {
        $current_page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $current_target = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
        $current_file   = basename( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
        
        if ( ! is_array( $files_or_targets ) ) {
            $files_or_targets = array( $files_or_targets );
        }
        
        foreach ( $files_or_targets as $item ) {
            if ( $current_page === 'nextsafar-wp-wrapper' && $current_target === $item ) {
                return true;
            }
            if ( $current_file === $item ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * بررسی با پارامتر اضافی
     */
    public static function is_current_with_param( $file, $param_key, $param_value = null ) {
        $current_page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $current_target = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
        $current_file   = basename( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
        $current_extra_params = array();
        
        if ( isset( $_GET['extra'] ) ) {
            parse_str( sanitize_text_field( wp_unslash( $_GET['extra'] ) ), $current_extra_params );
        }
        
        // wrapper mode
        if ( $current_page === 'nextsafar-wp-wrapper' && $current_target === $file ) {
            if ( $param_value === null ) {
                return isset( $current_extra_params[ $param_key ] );
            }
            return isset( $current_extra_params[ $param_key ] ) 
                && $current_extra_params[ $param_key ] === $param_value;
        }
        
        // مستقیم
        if ( $current_file === $file ) {
            if ( $param_value === null ) {
                return isset( $_GET[ $param_key ] );
            }
            return isset( $_GET[ $param_key ] ) && $_GET[ $param_key ] === $param_value;
        }
        
        return false;
    }
    
    /**
     * بررسی نبود پارامتر اضافی
     */
    public static function has_no_extra_params( $keys_to_check = array() ) {
        $current_extra_params = array();
        if ( isset( $_GET['extra'] ) ) {
            parse_str( sanitize_text_field( wp_unslash( $_GET['extra'] ) ), $current_extra_params );
        }
        
        foreach ( $keys_to_check as $key ) {
            if ( isset( $current_extra_params[ $key ] ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * بررسی page slug
     */
    public static function is_page( $page_slugs = array() ) {
        $current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( ! is_array( $page_slugs ) ) {
            $page_slugs = array( $page_slugs );
        }
        return in_array( $current_page, $page_slugs, true );
    }
    
    /**
     * ساخت URL wrapper
     */
    public static function wrapper_url( $target, $extra = array() ) {
        if ( class_exists( 'NextSafar_WP_Pages_Wrapper' ) ) {
            return NextSafar_WP_Pages_Wrapper::build_wrapper_url( $target, $extra );
        }
        return admin_url( $target );
    }
    
    /* =========================================================
       🏷️ توابع Term Meta و ACF
    ========================================================== */
    
    /**
     * گرفتن فیلدهای ACF برای taxonomy
     */
    public static function get_acf_taxonomy_fields( $taxonomy, $term = null ) {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return array();
        }
        
        $groups = acf_get_field_groups( array(
            'taxonomy' => $taxonomy,
        ) );
        
        if ( empty( $groups ) ) {
            return array();
        }
        
        $fields = array();
        foreach ( $groups as $group ) {
            $group_fields = acf_get_fields( $group );
            if ( ! empty( $group_fields ) ) {
                $fields = array_merge( $fields, $group_fields );
            }
        }
        
        return $fields;
    }
    
    /**
     * رندر فیلدهای ACF
     */
    public static function render_acf_fields( $fields, $term = null ) {
        if ( empty( $fields ) ) {
            return '';
        }
        
        $output = '<div class="nextsafar-term-meta-fields">';
        $output .= '<div class="nextsafar-meta-fields-header">';
        $output .= '<h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: var(--ns-text);">';
        $output .= '📦 فیلدهای سفارشی';
        $output .= '</h3>';
        $output .= '</div>';
        
        foreach ( $fields as $field ) {
            $value = '';
            if ( $term && function_exists( 'get_term_meta' ) ) {
                $value = get_term_meta( $term->term_id, $field['name'], true );
            }
            
            $output .= '<div class="nextsafar-form-group">';
            $output .= '<label for="term-meta-' . esc_attr( $field['name'] ) . '">';
            $output .= esc_html( $field['label'] );
            if ( ! empty( $field['required'] ) ) {
                $output .= ' <span style="color: var(--ns-danger);">*</span>';
            }
            $output .= '</label>';
            
            // رندر بر اساس نوع فیلد
            $output .= self::render_acf_field_input( $field, $value );
            
            // توضیحات
            if ( ! empty( $field['instructions'] ) ) {
                $output .= '<small style="display:block; margin-top:6px; color:var(--ns-text-muted); font-size:11px;">';
                $output .= esc_html( $field['instructions'] );
                $output .= '</small>';
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    /**
     * رندر input برای یک فیلد ACF
     */
    private static function render_acf_field_input( $field, $value ) {
        $output = '';
        
        switch ( $field['type'] ) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                $output .= sprintf(
                    '<input type="%s" id="term-meta-%s" name="term_meta[%s]" class="nextsafar-form-input" value="%s" placeholder="%s">',
                    esc_attr( $field['type'] ),
                    esc_attr( $field['name'] ),
                    esc_attr( $field['name'] ),
                    esc_attr( $value ),
                    esc_attr( $field['placeholder'] ?? '' )
                );
                break;
                
            case 'textarea':
                $output .= sprintf(
                    '<textarea id="term-meta-%s" name="term_meta[%s]" class="nextsafar-form-textarea" rows="4" placeholder="%s">%s</textarea>',
                    esc_attr( $field['name'] ),
                    esc_attr( $field['name'] ),
                    esc_attr( $field['placeholder'] ?? '' ),
                    esc_textarea( $value )
                );
                break;
                
            case 'select':
                $output .= sprintf(
                    '<select id="term-meta-%s" name="term_meta[%s]" class="nextsafar-form-input">',
                    esc_attr( $field['name'] ),
                    esc_attr( $field['name'] )
                );
                if ( ! empty( $field['choices'] ) ) {
                    foreach ( $field['choices'] as $opt_value => $opt_label ) {
                        $output .= sprintf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr( $opt_value ),
                            selected( $value, $opt_value, false ),
                            esc_html( $opt_label )
                        );
                    }
                }
                $output .= '</select>';
                break;
                
            case 'checkbox':
                $output .= sprintf(
                    '<label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="term_meta[%s]" value="1" %s> %s</label>',
                    esc_attr( $field['name'] ),
                    checked( $value, '1', false ),
                    esc_html( $field['label'] ?? '' )
                );
                break;
                
            case 'image':
            case 'file':
                $output .= '<div class="nextsafar-term-image-field">';
                if ( $value ) {
                    $img_url = wp_get_attachment_url( $value );
                    if ( $img_url ) {
                        $output .= '<img src="' . esc_url( $img_url ) . '" style="max-width:150px; max-height:150px; border-radius:8px; margin-bottom:10px;">';
                    }
                }
                $output .= sprintf(
                    '<input type="hidden" name="term_meta[%s]" value="%s" id="term-meta-%s">',
                    esc_attr( $field['name'] ),
                    esc_attr( $value ),
                    esc_attr( $field['name'] )
                );
                $output .= '<button type="button" class="nextsafar-secondary-button term-image-upload-btn" data-field="' . esc_attr( $field['name'] ) . '">';
                $output .= $value ? 'تغییر تصویر' : 'انتخاب تصویر';
                $output .= '</button>';
                if ( $value ) {
                    $output .= ' <button type="button" class="nextsafar-danger-button term-image-remove-btn" data-field="' . esc_attr( $field['name'] ) . '" style="min-height:32px; padding:0 12px;">حذف</button>';
                }
                $output .= '</div>';
                break;
                
            case 'color':
                $output .= sprintf(
                    '<input type="color" id="term-meta-%s" name="term_meta[%s]" value="%s" style="width:60px; height:40px; padding:2px; border:1px solid var(--ns-border); border-radius:6px; cursor:pointer;">',
                    esc_attr( $field['name'] ),
                    esc_attr( $field['name'] ),
                    esc_attr( $value ?: '#000000' )
                );
                break;
                
            default:
                $output .= sprintf(
                    '<input type="text" id="term-meta-%s" name="term_meta[%s]" class="nextsafar-form-input" value="%s">',
                    esc_attr( $field['name'] ),
                    esc_attr( $field['name'] ),
                    esc_attr( $value )
                );
        }
        
        return $output;
    }
    
    /**
     * ذخیره term meta
     */
    public static function save_term_meta( $term_id, $meta_data ) {
        if ( ! is_array( $meta_data ) ) {
            return;
        }
        
        foreach ( $meta_data as $key => $value ) {
            $key = sanitize_key( $key );
            
            if ( is_array( $value ) ) {
                $value = array_map( 'sanitize_text_field', $value );
                update_term_meta( $term_id, $key, $value );
            } else {
                if ( is_numeric( $value ) && strpos( $value, '.' ) === false ) {
                    update_term_meta( $term_id, $key, sanitize_text_field( $value ) );
                } else {
                    update_term_meta( $term_id, $key, wp_kses_post( $value ) );
                }
            }
        }
    }
    
    /**
     * حذف term meta
     */
    public static function delete_term_meta( $term_id ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->termmeta,
            array( 'term_id' => $term_id ),
            array( '%d' )
        );
    }
    
    /**
     * Capture metabox HTML برای taxonomy
     */
    public static function capture_taxonomy_fields( $taxonomy, $term = null ) {
        global $wp_filter;
        $output = '';
        $has_fields = false;
        
        if ( $term ) {
            $hooks = array(
                "{$taxonomy}_edit_form_fields",
                "{$taxonomy}_edit_form",
            );
        } else {
            $hooks = array(
                "{$taxonomy}_add_form_fields",
                "{$taxonomy}_add_form",
            );
        }
        
        $hooks[] = $term ? 'edit_term_metadata' : 'add_term_metadata';
        
        foreach ( $hooks as $hook ) {
            if ( isset( $wp_filter[ $hook ] ) && ! empty( $wp_filter[ $hook ]->callbacks ) ) {
                $has_fields = true;
                ob_start();
                if ( $term ) {
                    do_action( $hook, $term, $taxonomy );
                } else {
                    do_action( $hook, $taxonomy );
                }
                $output .= ob_get_clean();
            }
        }
        
        // بررسی ACF
        if ( function_exists( 'acf_get_fields' ) || class_exists( 'ACF' ) ) {
            $acf_fields = self::get_acf_taxonomy_fields( $taxonomy, $term );
            if ( ! empty( $acf_fields ) ) {
                $has_fields = true;
                $output .= self::render_acf_fields( $acf_fields, $term );
            }
        }
        
        return array(
            'html'       => $output,
            'has_fields' => $has_fields,
        );
    }
}

/* =========================================================
   🔧 Global Function Wrappers (با محافظت)
========================================================== */

if ( ! function_exists( 'nextsafar_gregorian_to_jalali' ) ) {
    function nextsafar_gregorian_to_jalali( $gy, $gm, $gd ) {
        return NextSafar_Helpers::gregorian_to_jalali( $gy, $gm, $gd );
    }
}

if ( ! function_exists( 'nextsafar_get_persian_date' ) ) {
    function nextsafar_get_persian_date() {
        return NextSafar_Helpers::get_persian_date();
    }
}

if ( ! function_exists( 'nextsafar_get_kpi_stats' ) ) {
    function nextsafar_get_kpi_stats() {
        return NextSafar_Helpers::get_kpi_stats();
    }
}

if ( ! function_exists( 'nextsafar_sidebar_icon' ) ) {
    function nextsafar_sidebar_icon( $name ) {
        return NextSafar_Helpers::sidebar_icon( $name );
    }
}

if ( ! function_exists( 'nextsafar_is_current' ) ) {
    function nextsafar_is_current( $files_or_targets ) {
        return NextSafar_Helpers::is_current( $files_or_targets );
    }
}

if ( ! function_exists( 'nextsafar_is_current_with_param' ) ) {
    function nextsafar_is_current_with_param( $file, $param_key, $param_value = null ) {
        return NextSafar_Helpers::is_current_with_param( $file, $param_key, $param_value );
    }
}

if ( ! function_exists( 'nextsafar_has_no_extra_params' ) ) {
    function nextsafar_has_no_extra_params( $keys_to_check = array() ) {
        return NextSafar_Helpers::has_no_extra_params( $keys_to_check );
    }
}

if ( ! function_exists( 'nextsafar_is_page' ) ) {
    function nextsafar_is_page( $page_slugs = array() ) {
        return NextSafar_Helpers::is_page( $page_slugs );
    }
}

if ( ! function_exists( 'nextsafar_wrapper_url' ) ) {
    function nextsafar_wrapper_url( $target, $extra = array() ) {
        return NextSafar_Helpers::wrapper_url( $target, $extra );
    }
}

if ( ! function_exists( 'nextsafar_save_term_meta' ) ) {
    function nextsafar_save_term_meta( $term_id, $meta_data ) {
        return NextSafar_Helpers::save_term_meta( $term_id, $meta_data );
    }
}

if ( ! function_exists( 'nextsafar_delete_term_meta' ) ) {
    function nextsafar_delete_term_meta( $term_id ) {
        return NextSafar_Helpers::delete_term_meta( $term_id );
    }
}

if ( ! function_exists( 'nextsafar_capture_taxonomy_fields' ) ) {
    function nextsafar_capture_taxonomy_fields( $taxonomy, $term = null ) {
        return NextSafar_Helpers::capture_taxonomy_fields( $taxonomy, $term );
    }
}