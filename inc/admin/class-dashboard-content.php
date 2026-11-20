<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 📝 NextSafar Dashboard Content — داشبورد محتوا و سئو
 * جایگزین ویجت‌های رزرو/تور با ویجت‌های محتوایی.
 * همه داده‌ها محلیِ خود وردپرس هستند: پست‌ها، متای Yoast، متای Geo، جداول اخبار.
 * هیچ تماسی با بک‌اند Node.js گرفته نمی‌شود.
 *
 * @package NextSafar
 */
class NextSafar_Dashboard_Content {

    const GEO_TYPES     = array( 'hotel', 'restaurant', 'destination', 'hospital', 'airport' );
    const ARTICLE_TYPES = array( 'post', 'travelguide', 'travelnews', 'visa' );
    const STALE_TYPES   = array( 'post', 'hotel', 'travelguide', 'visa', 'destination', 'restaurant', 'airport', 'hospital' );

    const CACHE_PREFIX = 'nextsafar_cd_';
    const CACHE_KEYS   = array( 'kpis', 'pipeline', 'seo', 'geo', 'news', 'scheduled', 'stale' );

    /* =====================================================================
       Hooks — پاکسازی کش هنگام تغییر محتوا
    ===================================================================== */

    public static function init_hooks() {
        add_action( 'save_post', array( __CLASS__, 'maybe_flush' ), 30 );
        add_action( 'delete_post', array( __CLASS__, 'maybe_flush' ), 30 );
        add_action( 'transition_post_status', array( __CLASS__, 'flush_on_transition' ), 30, 3 );
    }

    public static function maybe_flush( $post_id ) {
        // ❌ رد کردن auto-draft
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }
        
        if ( 'auto-draft' === $post->post_status ) {
            return;
        }
        
        // ❌ رد کردن revision
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        
        if ( in_array( $post->post_type, self::all_types(), true ) ) {
            self::flush();
        }
    }

    public static function flush_on_transition( $new, $old, $post ) {
        if ( $new !== $old && $post && in_array( $post->post_type, self::all_types(), true ) ) {
            self::flush();
        }
    }

    public static function flush() {
        static $done = false;
        if ( $done ) {
            return; // یک‌بار per-request (maybe_flush + flush_on_transition دوبل صدا می‌زدند)
        }
        $done = true;

        foreach ( self::CACHE_KEYS as $key ) {
            delete_transient( self::CACHE_PREFIX . $key );
        }
    }

        /* =====================================================================
       رجیستری ویجت‌های واقعی داشبورد + تنظیمات نمایش (Screen Options)
    ===================================================================== */

    public static function widget_registry() {
        return array(
            'kpis'              => 'کارت‌های شاخص (KPI)',
            'gsc_chart'         => 'نمودار عملکرد جستجو',
            'yoast_seo'         => 'وضعیت SEO سایت',
            'pipeline'          => 'پایپلاین محتوا',
            'seo_tasks'         => 'کارهای سئو',
            'gsc_opportunities' => 'فرصت‌های سئو',
            'coverage'          => 'پوشش مقاصد',
            'orphaned'          => 'محتوای یتیم',
            'media'             => 'سلامت رسانه',
            'comments'          => 'جدیدترین کامنت‌ها',
            'news'              => 'صف خبرها',
            'geo'               => 'کامل بودن داده هتل‌ها',
            'weather'           => 'آب و هوای مقاصد',
            'calendar'          => 'تقویم',
            'scheduled'         => 'انتشارهای زمان‌بندی‌شده',
            'stale'             => 'محتوای کهنه',
            'admin_logins'      => 'آخرین ورود ادمین‌ها',
        );
    }

    public static function widget_settings() {
        $settings = get_user_meta( get_current_user_id(), 'nextsafar_dashboard_widgets', true );
        return is_array( $settings ) ? $settings : array();
    }

    public static function is_enabled( $widget_id ) {
        $settings = self::widget_settings();
        if ( ! isset( $settings[ $widget_id ] ) ) {
            return true; // پیش‌فرض: فعال
        }
        if ( ! isset( $settings[ $widget_id ]['enabled'] ) ) {
            return true;
        }
        return (bool) $settings[ $widget_id ]['enabled'];
    }
    
    /* =====================================================================
       Helpers
    ===================================================================== */

    private static function all_types() {
        return array_merge( self::ARTICLE_TYPES, self::GEO_TYPES, array( 'page' ) );
    }

    private static function publish_types() {
        return array_merge( self::ARTICLE_TYPES, self::GEO_TYPES );
    }

    private static function labels() {
        return array(
            'post'        => 'مقاله',
            'page'        => 'برگه',
            'hotel'       => 'هتل',
            'restaurant'  => 'رستوران',
            'destination' => 'جاذبه',
            'hospital'    => 'بیمارستان',
            'airport'     => 'فرودگاه',
            'travelguide' => 'راهنما',
            'travelnews'  => 'خبر',
            'visa'        => 'ویزا',
        );
    }

    private static function label( $type ) {
        $labels = self::labels();
        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    private static function count_status( $types, $status ) {
        $sum = 0;
        foreach ( $types as $type ) {
            if ( ! post_type_exists( $type ) ) {
                continue;
            }
            $counts = wp_count_posts( $type );
            $sum   += isset( $counts->{$status} ) ? (int) $counts->{$status} : 0;
        }
        return $sum;
    }

    private static function count_query( $args ) {
        $defaults = array(
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        );
        $query = new WP_Query( array_merge( $defaults, $args ) );
        return (int) $query->found_posts;
    }

    private static function empty_or_missing( $key ) {
        return array(
            'relation' => 'OR',
            array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
            array( 'key' => $key, 'value' => '', 'compare' => '=' ),
        );
    }

    private static function geo_get( $post_id, $field ) {
        if ( class_exists( '\NextSafar\Sync\GeoSchema' ) ) {
            return \NextSafar\Sync\GeoSchema::get( $post_id, $field );
        }
        return get_post_meta( $post_id, '_geo_' . $field, true );
    }

    private static function jalali( $date ) {
        if ( class_exists( 'NextSafar_Helpers' ) ) {
            return NextSafar_Helpers::gregorian_to_jalali_str( $date );
        }
        return date_i18n( 'Y/m/d', strtotime( $date ) );
    }

    private static function cached_html( $key, $ttl, $callback ) {
        $cached = get_transient( self::CACHE_PREFIX . $key );
        if ( false !== $cached ) {
            return $cached;
        }
        $html = call_user_func( $callback );
        set_transient( self::CACHE_PREFIX . $key, $html, $ttl );
        return $html;
    }

    private static function row( $post, $extra_html = '', $time_html = '' ) {
        $edit = get_edit_post_link( $post->ID, 'raw' );
        $html  = '<div class="ns-row">';
        $html .= '<span class="ns-tag ns-tag--muted">' . esc_html( self::label( $post->post_type ) ) . '</span>';
        $html .= '<a class="ns-row-title" href="' . esc_url( $edit ) . '">' . esc_html( $post->post_title ? $post->post_title : '(بدون عنوان)' ) . '</a>';
        $html .= $extra_html . $time_html;
        $html .= '</div>';
        return $html;
    }

    private static function empty_state( $text ) {
        return '<div class="ns-empty">' . esc_html( $text ) . '</div>';
    }

    /* =====================================================================
       1) کارت‌های KPI
    ===================================================================== */

    public static function render_kpis() {
        echo self::cached_html( 'kpis', 5 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_kpis' ) );
    }

    private static function build_kpis() {
        $published = self::count_status( self::publish_types(), 'publish' );
        $hotels    = self::count_status( array( 'hotel' ), 'publish' );
        $articles  = self::count_status( self::ARTICLE_TYPES, 'publish' );

        $new_30d = self::count_query( array(
            'post_type'  => self::publish_types(),
            'date_query' => array( array(
                'after' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ),
            ) ),
        ) );

        $draft   = self::count_status( self::all_types(), 'draft' );
        $pending = self::count_status( self::all_types(), 'pending' );
        $future  = self::count_status( self::all_types(), 'future' );
        $queue   = $draft + $pending + $future;

        $seo_total   = self::count_status( array_merge( self::publish_types(), array( 'page' ) ), 'publish' );
        $seo_missing = defined( 'WPSEO_VERSION' ) ? self::count_query( array(
            'post_type'  => array_merge( self::publish_types(), array( 'page' ) ),
            'meta_query' => self::empty_or_missing( '_yoast_wpseo_metadesc' ),
        ) ) : 0;
        $seo_percent = $seo_total > 0 ? (int) round( 100 * ( $seo_total - $seo_missing ) / $seo_total ) : 100;

        $geo_total = self::count_status( self::GEO_TYPES, 'publish' );
        $geo_miss  = self::count_query( array(
            'post_type'  => self::GEO_TYPES,
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => '_geo_external_id', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_geo_lat', 'compare' => 'NOT EXISTS' ),
            ),
        ) );
        $geo_percent = $geo_total > 0 ? (int) round( 100 * ( $geo_total - $geo_miss ) / $geo_total ) : 100;

        $cpt_url  = admin_url( 'admin.php?page=nextsafar-cpt' );
        $bulk_url = admin_url( 'tools.php?page=ns-bulk-sync' );

        ob_start();
        ?>
        <div class="kpi-cards-grid">
            <div class="kpi-card">
                <div class="kpi-icon">📝</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo number_format_i18n( $published ); ?></div>
                    <div class="kpi-label">محتوای منتشرشده</div>
                    <div class="kpi-sub"><?php echo number_format_i18n( $hotels ); ?> هتل · <?php echo number_format_i18n( $articles ); ?> مقاله/راهنما · <a href="<?php echo esc_url( $cpt_url ); ?>">+<?php echo number_format_i18n( $new_30d ); ?> در ۳۰ روز</a></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">⏳</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo number_format_i18n( $queue ); ?></div>
                    <div class="kpi-label">در صف تولید</div>
                    <div class="kpi-sub"><a href="#ns-pipeline"><?php echo number_format_i18n( $draft ); ?> پیش‌نویس · <?php echo number_format_i18n( $pending ); ?> بازبینی · <?php echo number_format_i18n( $future ); ?> زمان‌بندی</a></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">🔍</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo number_format_i18n( $seo_percent ); ?>٪</div>
                    <div class="kpi-label">سلامت سئو</div>
                    <div class="kpi-sub"><a href="#ns-seo-tasks"><?php echo number_format_i18n( $seo_missing ); ?> محتوا بدون توضیحات متا</a></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon">📍</div>
                <div class="kpi-content">
                    <div class="kpi-value"><?php echo number_format_i18n( $geo_percent ); ?>٪</div>
                    <div class="kpi-label">کامل بودن داده مکان‌ها</div>
                    <div class="kpi-sub"><a href="<?php echo esc_url( current_user_can( 'manage_options' ) ? $bulk_url : '#ns-geo' ); ?>"><?php echo number_format_i18n( $geo_miss ); ?> مکان بدون مختصات/شناسه</a></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* =====================================================================
       2) پایپلاین محتوا
    ===================================================================== */

    public static function render_pipeline() {
        echo self::cached_html( 'pipeline', 5 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_pipeline' ) );
    }

    private static function build_pipeline() {
        $types   = self::all_types();
        $draft   = self::count_status( $types, 'draft' );
        $pending = self::count_status( $types, 'pending' );
        $future  = self::count_status( $types, 'future' );

        $items = new WP_Query( array(
            'post_type'      => $types,
            'post_status'    => array( 'draft', 'pending' ),
            'posts_per_page' => 6,
            'orderby'        => 'modified',
            'order'          => 'ASC',
        ) );

        ob_start();
        ?>
        <div class="ns-chips">
            <a class="ns-chip" href="<?php echo esc_url( admin_url( 'edit.php?post_status=draft' ) ); ?>"><b><?php echo number_format_i18n( $draft ); ?></b><span>پیش‌نویس</span></a>
            <a class="ns-chip ns-chip--warn" href="<?php echo esc_url( admin_url( 'edit.php?post_status=pending' ) ); ?>"><b><?php echo number_format_i18n( $pending ); ?></b><span>در انتظار بازبینی</span></a>
            <a class="ns-chip ns-chip--ok" href="#ns-scheduled"><b><?php echo number_format_i18n( $future ); ?></b><span>زمان‌بندی‌شده</span></a>
        </div>
        <?php if ( $items->have_posts() ) : ?>
            <div class="ns-list">
                <?php
                while ( $items->have_posts() ) {
                    $items->the_post();
                    $post     = get_post();
                    $age      = human_time_diff( strtotime( $post->post_modified ), current_time( 'timestamp' ) );
                    $status   = 'pending' === $post->post_status ? '<span class="ns-tag ns-tag--warn">بازبینی</span>' : '<span class="ns-tag">پیش‌نویس</span>';
                    $time     = '<span class="ns-time">' . esc_html( $age ) . ' پیش</span>';
                    echo self::row( $post, $status, $time );
                }
                wp_reset_postdata();
                ?>
            </div>
            <div class="ns-widget-foot">قدیمی‌ترین کارهای باز · <a href="<?php echo esc_url( admin_url( 'edit.php?post_status=draft' ) ); ?>">مشاهده همه</a></div>
        <?php else : ?>
            <?php echo self::empty_state( '🎉 هیچ پیش‌نویس یا مورد بازبینی باز نیست.' ); ?>
        <?php endif;
        return ob_get_clean();
    }

    /* =====================================================================
       3) کارهای سئو (Yoast)
    ===================================================================== */

    public static function render_seo_tasks() {
        echo self::cached_html( 'seo', 15 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_seo_tasks' ) );
    }

    private static function build_seo_tasks() {
        if ( ! defined( 'WPSEO_VERSION' ) ) {
            return self::empty_state( 'افزونه Yoast SEO فعال نیست؛ این ویجت به متادیتای Yoast نیاز دارد.' );
        }

        $types   = array_merge( self::publish_types(), array( 'page' ) );
        $no_desc = self::count_query( array( 'post_type' => $types, 'meta_query' => self::empty_or_missing( '_yoast_wpseo_metadesc' ) ) );
        $no_kw   = self::count_query( array( 'post_type' => $types, 'meta_query' => self::empty_or_missing( '_yoast_wpseo_focuskw' ) ) );
        $noindex = self::count_query( array(
            'post_type'  => $types,
            'meta_query' => array( array( 'key' => '_yoast_wpseo_meta-robots-noindex', 'value' => '1' ) ),
        ) );

        $items = new WP_Query( array(
            'post_type'      => $types,
            'posts_per_page' => 8,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => self::empty_or_missing( '_yoast_wpseo_metadesc' ),
        ) );

        ob_start();
        ?>
        <div class="ns-chips">
            <span class="ns-chip ns-chip--warn"><b><?php echo number_format_i18n( $no_desc ); ?></b><span>بدون توضیحات متا</span></span>
            <span class="ns-chip"><b><?php echo number_format_i18n( $no_kw ); ?></b><span>بدون کلمه کلیدی</span></span>
            <span class="ns-chip ns-chip--danger"><b><?php echo number_format_i18n( $noindex ); ?></b><span>noindex</span></span>
        </div>
        <?php if ( $items->have_posts() ) : ?>
            <div class="ns-list">
                <?php
                while ( $items->have_posts() ) {
                    $items->the_post();
                    $post  = get_post();
                    $extra = '<span class="ns-tag ns-tag--warn">متا</span>';
                    $kw    = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
                    if ( '' === $kw ) {
                        $extra .= '<span class="ns-tag ns-tag--muted">کلیدواژه</span>';
                    }
                    if ( '1' === get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
                        $extra .= '<span class="ns-tag ns-tag--danger">noindex</span>';
                    }
                    echo self::row( $post, $extra );
                }
                wp_reset_postdata();
                ?>
            </div>
            <div class="ns-widget-foot">جدیدترین محتواهای بدون توضیحات متا · برای ویرایش روی عنوان کلیک کنید</div>
        <?php else : ?>
            <?php echo self::empty_state( '✅ همه محتواها توضیحات متا دارند.' ); ?>
        <?php endif;
        return ob_get_clean();
    }

    /* =====================================================================
       4) کامل بودن داده مکان‌ها (Geo)
    ===================================================================== */

    public static function render_geo() {
        echo self::cached_html( 'geo', 15 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_geo' ) );
    }

    private static function build_geo() {
        $total = self::count_status( self::GEO_TYPES, 'publish' );
        $miss  = self::count_query( array(
            'post_type'  => self::GEO_TYPES,
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => '_geo_external_id', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_geo_lat', 'compare' => 'NOT EXISTS' ),
            ),
        ) );
        $percent = $total > 0 ? (int) round( 100 * ( $total - $miss ) / $total ) : 100;

        $hotels = get_posts( array(
            'post_type'      => 'hotel',
            'post_status'    => 'publish',
            'posts_per_page' => 40,
            'fields'         => 'ids',
            'orderby'        => 'modified',
            'order'          => 'ASC',
        ) );

        $scored = array();
        foreach ( $hotels as $pid ) {
            $missing = array();
            if ( ! has_post_thumbnail( $pid ) ) {
                $missing[] = 'عکس';
            }
            $gallery = get_post_meta( $pid, '_hotel_gallery', true );
            if ( ! is_array( $gallery ) || count( $gallery ) < 3 ) {
                $missing[] = 'گالری';
            }
            $amenities = get_post_meta( $pid, '_hotel_amenities', true );
            if ( empty( $amenities ) ) {
                $missing[] = 'امکانات';
            }
            if ( '' === (string) self::geo_get( $pid, 'lat' ) ) {
                $missing[] = 'مختصات';
            }
            if ( '' === (string) self::geo_get( $pid, 'city' ) ) {
                $missing[] = 'شهر';
            }
            if ( $missing ) {
                $scored[] = array( 'id' => $pid, 'missing' => $missing );
            }
        }
        usort( $scored, function ( $a, $b ) {
            return count( $b['missing'] ) - count( $a['missing'] );
        } );
        $scored = array_slice( $scored, 0, 8 );

        ob_start();
        ?>
        <div class="ns-progress"><i style="width:<?php echo (int) $percent; ?>%"></i></div>
        <div class="ns-time" style="margin-bottom:10px"><?php echo number_format_i18n( $percent ); ?>٪ مکان‌ها شناسه/مختصات دارند · <?php echo number_format_i18n( $miss ); ?> مورد ناقص</div>
        <?php if ( $scored ) : ?>
            <div class="ns-list">
                <?php foreach ( $scored as $item ) :
                    $post  = get_post( $item['id'] );
                    $extra = '';
                    foreach ( array_slice( $item['missing'], 0, 3 ) as $m ) {
                        $extra .= '<span class="ns-tag ns-tag--warn">' . esc_html( $m ) . '</span>';
                    }
                    echo self::row( $post, $extra );
                endforeach; ?>
            </div>
        <?php else : ?>
            <?php echo self::empty_state( '✅ داده همه هتل‌ها کامل است.' ); ?>
        <?php endif; ?>
        <div class="ns-widget-foot">
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=ns-bulk-sync' ) ); ?>">🔄 سینک انبوه پست‌های ناقص</a> ·
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-cpt&post_type=hotel' ) ); ?>">همه هتل‌ها</a>
        </div>
        <?php
        return ob_get_clean();
    }

    /* =====================================================================
       5) صف خبرها (News Generator)
    ===================================================================== */

    public static function render_news() {
        echo self::cached_html( 'news', 5 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_news' ) );
    }

    private static function build_news() {
        global $wpdb;

        $draft   = self::count_status( array( 'travelnews' ), 'draft' );
        $pending = self::count_status( array( 'travelnews' ), 'pending' );
        $publish = self::count_status( array( 'travelnews' ), 'publish' );

        $log_table = $wpdb->prefix . 'ns_news_filter_log';
        $src_table = $wpdb->prefix . 'ns_news_sources';
        $has_log   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) === $log_table;
        $has_src   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $src_table ) ) === $src_table;

        $decisions = array();
        if ( $has_log ) {
            $since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );
            $rows  = $wpdb->get_results( $wpdb->prepare(
                "SELECT decision, COUNT(*) AS c FROM {$log_table} WHERE created_at >= %s GROUP BY decision",
                $since
            ) );
            foreach ( (array) $rows as $r ) {
                $decisions[ $r->decision ] = (int) $r->c;
            }
        }

        $active = 0;
        $problems = array();
        if ( $has_src ) {
            $active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$src_table} WHERE is_active = 1" );
            $stale  = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 2 * DAY_IN_SECONDS );
            $problems = (array) $wpdb->get_results( $wpdb->prepare(
                "SELECT name, error_message, last_success FROM {$src_table}
                 WHERE is_active = 1 AND ( (error_message IS NOT NULL AND error_message <> '') OR last_success IS NULL OR last_success < %s )
                 ORDER BY last_success ASC LIMIT 4",
                $stale
            ) );
        }

        ob_start();
        ?>
        <div class="ns-chips">
            <a class="ns-chip ns-chip--warn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=travelnews&post_status=draft' ) ); ?>"><b><?php echo number_format_i18n( $draft + $pending ); ?></b><span>خبر منتظر بررسی</span></a>
            <span class="ns-chip ns-chip--ok"><b><?php echo number_format_i18n( isset( $decisions['publish'] ) ? $decisions['publish'] : 0 ); ?></b><span>انتشار خودکار ۲۴ ساعت</span></span>
            <span class="ns-chip"><b><?php echo number_format_i18n( $active ); ?></b><span>منبع فعال</span></span>
        </div>
        <?php if ( $problems ) : ?>
            <div class="ns-list">
                <?php foreach ( $problems as $src ) :
                    $why = ! empty( $src->error_message ) ? 'خطا در فچ' : ( empty( $src->last_success ) ? 'هنوز فچ موفق نداشته' : 'فچ موفق اخیر ندارد' );
                    ?>
                    <div class="ns-row">
                        <span class="ns-tag ns-tag--danger">منبع</span>
                        <span class="ns-row-title"><?php echo esc_html( $src->name ); ?></span>
                        <span class="ns-time"><?php echo esc_html( $why ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ( $has_src ) : ?>
            <?php echo self::empty_state( '✅ همه منابع سالم هستند.' ); ?>
        <?php else : ?>
            <?php echo self::empty_state( 'جدول منابع خبر هنوز ساخته نشده است.' ); ?>
        <?php endif; ?>
        <div class="ns-widget-foot">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-news' ) ); ?>">آنالیز اخبار</a> ·
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-news-manual' ) ); ?>">تولید دستی</a>
            <?php if ( $publish ) : ?> · <?php echo number_format_i18n( $publish ); ?> خبر منتشرشده<?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* =====================================================================
       6) پست‌های زمان‌بندی‌شده
    ===================================================================== */

    public static function render_scheduled() {
        echo self::cached_html( 'scheduled', 5 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_scheduled' ) );
    }

    private static function build_scheduled() {
        $items = new WP_Query( array(
            'post_type'      => self::all_types(),
            'post_status'    => 'future',
            'posts_per_page' => 8,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        ob_start();
        if ( $items->have_posts() ) :
            ?>
            <div class="ns-list">
                <?php
                while ( $items->have_posts() ) {
                    $items->the_post();
                    $post  = get_post();
                    $until = human_time_diff( current_time( 'timestamp' ), strtotime( $post->post_date ) );
                    $time  = '<span class="ns-time" title="' . esc_attr( self::jalali( $post->post_date ) ) . '">' . esc_html( $until ) . ' دیگر</span>';
                    echo self::row( $post, '', $time );
                }
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <?php echo self::empty_state( '🗓 هیچ محتوایی زمان‌بندی نشده است.' ); ?>
        <?php endif;
        return ob_get_clean();
    }

    /* =====================================================================
       7) محتوای کهنه (نیازمند به‌روزرسانی)
    ===================================================================== */

    public static function render_stale() {
        echo self::cached_html( 'stale', 30 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_stale' ) );
    }

    private static function build_stale() {
        $items = new WP_Query( array(
            'post_type'      => self::STALE_TYPES,
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'orderby'        => 'modified',
            'order'          => 'ASC',
            'date_query'     => array( array(
                'column' => 'post_modified',
                'before' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 180 * DAY_IN_SECONDS ),
            ) ),
        ) );

        ob_start();
        if ( $items->have_posts() ) :
            ?>
            <div class="ns-list">
                <?php
                while ( $items->have_posts() ) {
                    $items->the_post();
                    $post = get_post();
                    $age  = human_time_diff( strtotime( $post->post_modified ), current_time( 'timestamp' ) );
                    $time = '<span class="ns-time">' . esc_html( $age ) . ' پیش</span>';
                    echo self::row( $post, '', $time );
                }
                wp_reset_postdata();
                ?>
            </div>
            <div class="ns-widget-foot">بیش از ۶ ماه از آخرین ویرایش گذشته است</div>
        <?php else : ?>
            <?php echo self::empty_state( '✅ همه محتواها به‌روز هستند.' ); ?>
        <?php endif;
        return ob_get_clean();
    }
}

NextSafar_Dashboard_Content::init_hooks();