<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 🗄️ NextSafar Widget Cache Manager
 * مدیریت هوشمند کش برای ویجت‌های داشبورد
 */
class NextSafar_Widget_Cache {

    /**
     * پیشوند کش — 🆕 هماهنگ با class-dashboard.php
     */
    private static $prefix = 'nextsafar_';

    /**
     * زمان‌های کش پیش‌فرض (به ثانیه)
     */
    private static $default_ttl = array(
        'recent_comments'  => 3 * MINUTE_IN_SECONDS,
        'expiring_tours'   => 10 * MINUTE_IN_SECONDS,
        'recent_bookings'  => 2 * MINUTE_IN_SECONDS,
        'upcoming_tours'   => 10 * MINUTE_IN_SECONDS,
        'admin_logins'     => 5 * MINUTE_IN_SECONDS,
        'yoast_stats'      => 15 * MINUTE_IN_SECONDS,
        'weather'          => 30 * MINUTE_IN_SECONDS,
    );

    /* =========================================================
    🎯 گرفتن کش
    ========================================================== */
    public static function get( $widget_id, $args = array() ) {
        $cache_key = self::build_key( $widget_id, $args );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            self::debug_log( "✅ [Cache HIT] {$widget_id}" );
            return $cached;
        }

        return false;
    }

    /**
     * ذخیره داده در کش
     */
    public static function set( $widget_id, $data, $args = array(), $ttl = 0 ) {
        $cache_key = self::build_key( $widget_id, $args );

        if ( 0 === $ttl ) {
            $ttl = isset( self::$default_ttl[ $widget_id ] )
                ? self::$default_ttl[ $widget_id ]
                : 5 * MINUTE_IN_SECONDS;
        }

        $result = set_transient( $cache_key, $data, $ttl );

        self::debug_log( "💾 [Cache SET] {$widget_id} (TTL: {$ttl}s)" );

        return $result;
    }

    /**
     * 🆕 پاک کردن کش یک ویجت خاص — با delete_transient (سازگار با Redis/Memcached)
     */
    public static function delete( $widget_id ) {
        $cache_key = self::build_key( $widget_id );
        delete_transient( $cache_key );

        // اگر کلید با hash هم ذخیره شده، اون رو هم پاک کن
        global $wpdb;
        $pattern = self::$prefix . $widget_id . '_%';
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s",
            '_transient_' . $pattern,
            '_transient_timeout_' . $pattern
        ) );

        self::debug_log( "🗑️ [Cache DELETE] {$widget_id}" );
    }

    /**
     * 🆕 پاک کردن همه کش‌های ویجت‌ها — با حلقه delete_transient
     */
    public static function clear_all() {
        foreach ( array_keys( self::$default_ttl ) as $widget_id ) {
            self::delete( $widget_id );
        }

        self::debug_log( "🗑️ [Cache CLEAR ALL]" );
    }

    /**
     * ساخت کلید یکتا برای کش
     */
    private static function build_key( $widget_id, $args = array() ) {
        $key = self::$prefix . $widget_id;

        if ( ! empty( $args ) ) {
            $key .= '_' . md5( wp_json_encode( $args ) );
        }

        return $key;
    }

    /**
     * 🆕 لاگ دیباگ — فقط وقتی WP_DEBUG فعال باشه
     */
    private static function debug_log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( $message );
        }
    }

    /* =========================================================
    🔄 Hook های پاکسازی خودکار کش
    ========================================================== */

    /**
     * راه‌اندازی Hook ها
     */
    public static function init_hooks() {
        // پاکسازی کش کامنت‌ها
        add_action( 'wp_insert_comment', array( __CLASS__, 'clear_comments_cache' ) );
        add_action( 'edit_comment', array( __CLASS__, 'clear_comments_cache' ) );
        add_action( 'delete_comment', array( __CLASS__, 'clear_comments_cache' ) );
        add_action( 'transition_comment_status', array( __CLASS__, 'clear_comments_cache' ) );

        // پاکسازی کش پست‌ها
        add_action( 'save_post', array( __CLASS__, 'clear_post_cache' ), 10, 3 );
        add_action( 'delete_post', array( __CLASS__, 'clear_post_related_cache' ) );
        add_action( 'transition_post_status', array( __CLASS__, 'clear_post_cache_on_status_change' ), 10, 3 );

        // پاکسازی کش رزروها
        add_action( 'nextsafar_booking_created', array( __CLASS__, 'clear_bookings_cache' ) );
        add_action( 'nextsafar_booking_updated', array( __CLASS__, 'clear_bookings_cache' ) );

        // پاکسازی کش لاگین ادمین‌ها
        add_action( 'wp_login', array( __CLASS__, 'clear_admin_logins_cache' ), 10, 2 );
    }

    /* =========================================================
    🧹 متدهای پاکسازی
    ========================================================== */

    public static function clear_comments_cache() {
        self::delete( 'recent_comments' );
    }

    public static function clear_post_cache( $post_id, $post, $update ) {
        $important_types = array( 'post', 'page', 'tour', 'visa' );

        if ( ! in_array( $post->post_type, $important_types, true ) ) {
            return;
        }

        self::delete( 'expiring_tours' );
        self::delete( 'upcoming_tours' );
        self::delete( 'recent_bookings' );
        self::delete( 'yoast_stats' );
    }

    public static function clear_post_related_cache( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }
        self::clear_post_cache( $post_id, $post, true );
    }

    public static function clear_post_cache_on_status_change( $new_status, $old_status, $post ) {
        if ( $new_status !== $old_status ) {
            self::clear_post_cache( $post->ID, $post, true );
        }
    }

    public static function clear_bookings_cache() {
        self::delete( 'recent_bookings' );
    }

    public static function clear_admin_logins_cache( $user_login, $user ) {
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
            self::delete( 'admin_logins' );
        }
    }
}