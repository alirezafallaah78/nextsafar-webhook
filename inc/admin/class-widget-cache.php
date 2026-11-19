<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 🗄️ NextSafar Widget Cache Manager
 * بدون DELETE خام SQL روی wp_options (سازگار با Redis/Memcached و بدون full-scan).
 */
class NextSafar_Widget_Cache {

    private static $prefix = 'nextsafar_';

    private static $default_ttl = array(
        'recent_comments' => 3 * MINUTE_IN_SECONDS,
        'expiring_tours'  => 10 * MINUTE_IN_SECONDS,
        'recent_bookings' => 2 * MINUTE_IN_SECONDS,
        'upcoming_tours'  => 10 * MINUTE_IN_SECONDS,
        'admin_logins'    => 5 * MINUTE_IN_SECONDS,
        'yoast_stats'     => 15 * MINUTE_IN_SECONDS,
        'weather'         => 30 * MINUTE_IN_SECONDS,
    );

    /**
     * کلیدهای variant که class-dashboard.php واقعاً استفاده می‌کند.
     * قبلاً با LIKE '_transient_nextsafar_X_%' پاک می‌شدند (full-scan + bypass object cache).
     */
    private static $variants = array(
        'admin_logins' => array( 'admin_logins_html' ),
        'yoast_stats'  => array( 'yoast_stats_data' ),
    );

    public static function get( $widget_id, $args = array() ) {
        $cached = get_transient( self::build_key( $widget_id, $args ) );
        if ( false !== $cached ) {
            self::debug_log( "✅ [Cache HIT] {$widget_id}" );
            return $cached;
        }
        return false;
    }

    public static function set( $widget_id, $data, $args = array(), $ttl = 0 ) {
        if ( 0 === $ttl ) {
            $ttl = isset( self::$default_ttl[ $widget_id ] )
                ? self::$default_ttl[ $widget_id ]
                : 5 * MINUTE_IN_SECONDS;
        }
        $result = set_transient( self::build_key( $widget_id, $args ), $data, $ttl );
        self::debug_log( "💾 [Cache SET] {$widget_id} (TTL: {$ttl}s)" );
        return $result;
    }

    /**
     * ✅ فقط delete_transient — بدون SQL خام. object cache هم invalidate می‌شود.
     */
    public static function delete( $widget_id ) {
        delete_transient( self::$prefix . $widget_id );

        if ( isset( self::$variants[ $widget_id ] ) ) {
            foreach ( self::$variants[ $widget_id ] as $variant ) {
                delete_transient( self::$prefix . $variant );
            }
        }

        self::debug_log( "🗑️ [Cache DELETE] {$widget_id}" );
    }

    public static function clear_all() {
        foreach ( array_keys( self::$default_ttl ) as $widget_id ) {
            self::delete( $widget_id );
        }
        self::debug_log( '🗑️ [Cache CLEAR ALL]' );
    }

    private static function build_key( $widget_id, $args = array() ) {
        $key = self::$prefix . $widget_id;
        if ( ! empty( $args ) ) {
            $key .= '_' . md5( wp_json_encode( $args ) );
        }
        return $key;
    }

    private static function debug_log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( $message );
        }
    }

    public static function init_hooks() {
        add_action( 'wp_insert_comment', array( __CLASS__, 'clear_comments_cache' ) );
        add_action( 'edit_comment', array( __CLASS__, 'clear_comments_cache' ) );
        add_action( 'delete_comment', array( __CLASS__, 'clear_comments_cache' ) );
        add_action( 'transition_comment_status', array( __CLASS__, 'clear_comments_cache' ) );

        add_action( 'save_post', array( __CLASS__, 'clear_post_cache' ), 10, 3 );
        add_action( 'delete_post', array( __CLASS__, 'clear_post_related_cache' ) );
        add_action( 'transition_post_status', array( __CLASS__, 'clear_post_cache_on_status_change' ), 10, 3 );

        add_action( 'nextsafar_booking_created', array( __CLASS__, 'clear_bookings_cache' ) );
        add_action( 'nextsafar_booking_updated', array( __CLASS__, 'clear_bookings_cache' ) );

        add_action( 'wp_login', array( __CLASS__, 'clear_admin_logins_cache' ), 10, 2 );
    }

    public static function clear_comments_cache() {
        self::delete( 'recent_comments' );
    }

    /**
     * ✅ یک‌بار per-request per-post (جلوگیری از flush دوبل save_post + transition_post_status)
     */
    public static function clear_post_cache( $post_id, $post, $update ) {
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        if ( ! in_array( $post->post_type, array( 'post', 'page', 'tour', 'visa' ), true ) ) {
            return;
        }

        static $done = array();
        $pid = (int) $post->ID;
        if ( isset( $done[ $pid ] ) ) {
            return;
        }
        $done[ $pid ] = true;

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
            self::delete( 'admin_logins' ); // حالا variant ـhtml را هم پاک می‌کند
        }
    }
}