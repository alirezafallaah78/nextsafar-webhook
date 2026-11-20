<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 📊 NextSafar Dashboard Insights — فاز ۲
 * سلامت رسانه، محتوای یتیم، پوشش مقاصد، فرصت‌های Search Console.
 * همه داده‌ها محلی هستند به‌جز GSC که از همان Search Console خود سایت می‌خواند.
 *
 * @package NextSafar
 */
class NextSafar_Dashboard_Insights {

    const GEO_TYPES     = array( 'hotel', 'restaurant', 'destination', 'hospital', 'airport' );
    const ARTICLE_TYPES = array( 'post', 'travelguide', 'travelnews', 'visa' );
    const PREFIX        = 'nextsafar_ins_';

    public static function init_hooks() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_nextsafar_get_gsc_opportunities', array( __CLASS__, 'ajax_gsc' ) );

        add_action( 'save_post', array( __CLASS__, 'flush_local' ), 40 );
        add_action( 'delete_post', array( __CLASS__, 'flush_local' ), 40 );
        add_action( 'add_attachment', array( __CLASS__, 'flush_local' ), 40 );
        add_action( 'edit_attachment', array( __CLASS__, 'flush_local' ), 40 );
        add_action( 'delete_attachment', array( __CLASS__, 'flush_local' ), 40 );
    }

    public static function enqueue() {
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( 'nextsafar-admin' !== $page ) {
            return;
        }
        wp_enqueue_script(
            'nextsafar-dashboard-insights',
            NEXTSAFAR_URL . 'assets/admin/js/dashboard-insights.js',
            array(),
            NEXTSAFAR_VERSION,
            true
        );
    }

    public static function flush_local( $post_id = null ) {
        // ❌ رد کردن auto-draft
        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post && 'auto-draft' === $post->post_status ) {
                return;
            }
            if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
                return;
            }
        }
        
        foreach ( array( 'media', 'orphaned', 'coverage' ) as $key ) {
            delete_transient( self::PREFIX . $key );
        }
    }

    /* ================= Helpers ================= */

    private static function types() {
        return array_merge( self::ARTICLE_TYPES, self::GEO_TYPES );
    }

    private static function label( $type ) {
        $map = array(
            'post' => 'مقاله', 'hotel' => 'هتل', 'restaurant' => 'رستوران',
            'destination' => 'جاذبه', 'hospital' => 'بیمارستان', 'airport' => 'فرودگاه',
            'travelguide' => 'راهنما', 'travelnews' => 'خبر', 'visa' => 'ویزا',
        );
        return isset( $map[ $type ] ) ? $map[ $type ] : $type;
    }

    private static function count_query( $args ) {
        $q = new WP_Query( array_merge( array(
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ), $args ) );
        return (int) $q->found_posts;
    }

    private static function empty_or_missing( $key ) {
        return array(
            'relation' => 'OR',
            array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
            array( 'key' => $key, 'value' => '', 'compare' => '=' ),
        );
    }

    private static function cached( $key, $ttl, $cb ) {
        $hit = get_transient( self::PREFIX . $key );
        if ( false !== $hit ) {
            return $hit;
        }
        $html = call_user_func( $cb );
        set_transient( self::PREFIX . $key, $html, $ttl );
        return $html;
    }

    private static function empty_state( $text ) {
        return '<div class="ns-empty">' . esc_html( $text ) . '</div>';
    }

    private static function edit_link( $id ) {
        return esc_url( get_edit_post_link( $id, 'raw' ) );
    }

    /* ================= 1) سلامت رسانه ================= */

    public static function render_media() {
        echo self::cached( 'media', 30 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_media' ) );
    }

    private static function build_media() {
        $no_alt = self::count_query( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'meta_query'     => self::empty_or_missing( '_wp_attachment_image_alt' ),
        ) );

        $no_thumb = self::count_query( array(
            'post_type'  => self::types(),
            'meta_query' => self::empty_or_missing( '_thumbnail_id' ),
        ) );

        $sample = get_posts( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 80,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $heavy = array();
        foreach ( $sample as $aid ) {
            $file = get_attached_file( $aid );
            if ( $file && file_exists( $file ) ) {
                $size = (int) filesize( $file );
                if ( $size > 500 * 1024 ) {
                    $heavy[] = array( 'id' => $aid, 'size' => $size );
                }
            }
        }
        usort( $heavy, function ( $a, $b ) { return $b['size'] - $a['size']; } );
        $heavy_total = count( $heavy );
        $heavy = array_slice( $heavy, 0, 5 );

        ob_start();
        ?>
        <div class="ns-chips">
            <span class="ns-chip ns-chip--warn"><b><?php echo number_format_i18n( $no_alt ); ?></b><span>تصویر بدون alt</span></span>
            <span class="ns-chip ns-chip--danger"><b><?php echo number_format_i18n( $heavy_total ); ?></b><span>سنگین (+۵۰۰KB)</span></span>
            <span class="ns-chip"><b><?php echo number_format_i18n( $no_thumb ); ?></b><span>محتوا بدون عکس شاخص</span></span>
        </div>
        <?php if ( $heavy ) : ?>
            <div class="ns-list">
                <?php foreach ( $heavy as $h ) :
                    $title = get_the_title( $h['id'] );
                    $mb    = round( $h['size'] / 1048576, 1 );
                    ?>
                    <div class="ns-row">
                        <span class="ns-tag ns-tag--danger">سنگین</span>
                        <a class="ns-row-title" href="<?php echo self::edit_link( $h['id'] ); ?>"><?php echo esc_html( $title ? $title : '(بدون نام)' ); ?></a>
                        <span class="ns-time"><?php echo number_format_i18n( $mb ); ?> MB</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ns-widget-foot">نمونه: ۸۰ تصویر اخیر · <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-media' ) ); ?>">مدیریت رسانه</a></div>
        <?php else : ?>
            <?php echo self::empty_state( '✅ تصویر سنگینی در نمونه اخیر پیدا نشد.' ); ?>
        <?php endif;
        return ob_get_clean();
    }

    /* ================= 2) محتوای یتیم (Yoast) ================= */

    public static function render_orphaned() {
        echo self::cached( 'orphaned', 30 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_orphaned' ) );
    }

    private static function build_orphaned() {
        global $wpdb;

        if ( ! defined( 'WPSEO_VERSION' ) ) {
            return self::empty_state( 'Yoast SEO فعال نیست.' );
        }

        $table = $wpdb->prefix . 'yoast_indexable';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return self::empty_state( 'جدول Yoast Indexable ساخته نشده — در Yoast بخش Tools → Optimize SEO data را اجرا کنید.' );
        }
        $col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'incoming_link_count' ) );
        if ( ! $col ) {
            return self::empty_state( 'ستون لینک‌های ورودی Yoast موجود نیست — Optimize SEO data را اجرا کنید.' );
        }

        $types        = self::types();
        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

        $where = "i.object_type = 'post' AND i.incoming_link_count = 0 AND i.permalink IS NOT NULL
                  AND p.post_status = 'publish' AND p.post_type IN ({$placeholders})";

        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} i INNER JOIN {$wpdb->posts} p ON p.ID = i.object_id WHERE {$where}",
            ...$types
        );
        $total = (int) $wpdb->get_var( $count_sql );

        $list_sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type FROM {$table} i
             INNER JOIN {$wpdb->posts} p ON p.ID = i.object_id
             WHERE {$where} ORDER BY p.post_modified DESC LIMIT 8",
            ...$types
        );
        $rows = (array) $wpdb->get_results( $list_sql );

        ob_start();
        ?>
        <div class="ns-chips">
            <span class="ns-chip ns-chip--warn"><b><?php echo number_format_i18n( $total ); ?></b><span>محتوای یتیم (بدون لینک داخلی)</span></span>
        </div>
        <?php if ( $rows ) : ?>
            <div class="ns-list">
                <?php foreach ( $rows as $r ) : ?>
                    <div class="ns-row">
                        <span class="ns-tag ns-tag--muted"><?php echo esc_html( self::label( $r->post_type ) ); ?></span>
                        <a class="ns-row-title" href="<?php echo self::edit_link( $r->ID ); ?>"><?php echo esc_html( $r->post_title ? $r->post_title : '(بدون عنوان)' ); ?></a>
                        <span class="ns-tag ns-tag--warn">۰ لینک ورودی</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ns-widget-foot">از مقالات مرتبط به این صفحات لینک بدهید تا از یتیمی خارج شوند.</div>
        <?php else : ?>
            <?php echo self::empty_state( '✅ هیچ محتوای یتیمی پیدا نشد.' ); ?>
        <?php endif;
        return ob_get_clean();
    }

    /* ================= 3) پوشش مقاصد ================= */

    public static function render_coverage() {
        echo self::cached( 'coverage', 30 * MINUTE_IN_SECONDS, array( __CLASS__, 'build_coverage' ) );
    }

    private static function build_coverage() {
        global $wpdb;

        $types        = self::GEO_TYPES;
        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT pm.meta_value AS city, p.post_type, COUNT(*) AS c
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
               AND pm.meta_key = '_geo_city' AND pm.meta_value <> ''
             GROUP BY pm.meta_value, p.post_type",
            ...$types
        );
        $rows = (array) $wpdb->get_results( $sql );

        $cities = array();
        foreach ( $rows as $r ) {
            $city = trim( $r->city );
            if ( '' === $city ) {
                continue;
            }
            if ( ! isset( $cities[ $city ] ) ) {
                $cities[ $city ] = array( 'hotel' => 0, 'destination' => 0, 'restaurant' => 0, 'total' => 0 );
            }
            $c = (int) $r->c;
            $cities[ $city ]['total'] += $c;
            if ( isset( $cities[ $city ][ $r->post_type ] ) ) {
                $cities[ $city ][ $r->post_type ] += $c;
            }
        }
        uasort( $cities, function ( $a, $b ) { return $b['total'] - $a['total']; } );
        $top = array_slice( $cities, 0, 8, true );

        ob_start();
        ?>
        <div class="ns-chips">
            <span class="ns-chip"><b><?php echo number_format_i18n( count( $cities ) ); ?></b><span>شهر دارای محتوا</span></span>
        </div>
        <?php if ( $top ) : ?>
            <div class="ns-list">
                <?php foreach ( $top as $city => $c ) :
                    $gap = ( $c['hotel'] > 0 && 0 === $c['destination'] );
                    ?>
                    <div class="ns-row">
                        <span class="ns-row-title"><?php echo esc_html( $city ); ?></span>
                        <span class="ns-tag ns-tag--muted">هتل <?php echo number_format_i18n( $c['hotel'] ); ?></span>
                        <span class="ns-tag ns-tag--muted">جاذبه <?php echo number_format_i18n( $c['destination'] ); ?></span>
                        <span class="ns-tag ns-tag--muted">رستوران <?php echo number_format_i18n( $c['restaurant'] ); ?></span>
                        <?php if ( $gap ) : ?><span class="ns-tag ns-tag--warn">فرصت: جاذبه ندارد</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ns-widget-foot">شهری که هتل دارد ولی جاذبه ندارد = فرصت تولید محتوا برای سئو.</div>
        <?php else : ?>
            <?php echo self::empty_state( 'هنوز شهری در متای مکان‌ها ثبت نشده است.' ); ?>
        <?php endif;
        return ob_get_clean();
    }

    /* ================= 4) فرصت‌های GSC (AJAX) ================= */

    public static function ajax_gsc() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'striking';
        if ( ! in_array( $tab, array( 'striking', 'lowctr', 'top' ), true ) ) {
            $tab = 'striking';
        }

        $key    = self::PREFIX . 'gsc_' . $tab;
        $cached = get_transient( $key );
        if ( false !== $cached ) {
            wp_send_json_success( array( 'html' => $cached ) );
        }

        $html = self::build_gsc( $tab );
        set_transient( $key, $html, 30 * MINUTE_IN_SECONDS );
        wp_send_json_success( array( 'html' => $html ) );
    }

    private static function gsc_rows() {
        if ( ! function_exists( 'nextsafar' ) ) {
            return new WP_Error( 'no_core', 'هسته پلاگین NextSafar در دسترس نیست.' );
        }
        $dashboard = nextsafar()->dashboard;
        if ( ! $dashboard || ! method_exists( $dashboard, 'query_search_console' ) ) {
            return new WP_Error( 'no_method', 'فایل class-dashboard.php اصلاح‌شده را جایگزین کنید.' );
        }
        $data = $dashboard->query_search_console( array(
            'startDate'  => date( 'Y-m-d', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ),
            'endDate'    => date( 'Y-m-d', current_time( 'timestamp' ) - 3 * DAY_IN_SECONDS ),
            'dimensions' => array( 'page' ),
            'rowLimit'   => 100,
        ) );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( empty( $data['rows'] ) || ! is_array( $data['rows'] ) ) {
            return new WP_Error( 'no_rows', 'داده‌ای از GSC دریافت نشد.' );
        }
        return $data['rows'];
    }

    private static function post_by_url( $url ) {
        static $cache = array();
        if ( isset( $cache[ $url ] ) ) {
            return $cache[ $url ];
        }
        $path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
        $slug = rawurldecode( basename( $path ) );
        $post = null;
        if ( $slug ) {
            $q = new WP_Query( array(
                'name'           => $slug,
                'post_type'      => self::types(),
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ) );
            if ( $q->have_posts() ) {
                $post = $q->posts[0];
            }
            wp_reset_postdata();
        }
        $cache[ $url ] = $post;
        return $post;
    }

    private static function build_gsc( $tab ) {
        $rows = self::gsc_rows();
        if ( is_wp_error( $rows ) ) {
            return '<div class="ns-empty">🔌 ' . esc_html( $rows->get_error_message() ) . '</div>';
        }

        $items = array();
        foreach ( $rows as $r ) {
            $page = isset( $r['keys'][0] ) ? $r['keys'][0] : '';
            $imp  = (int) $r['impressions'];
            $clk  = (int) $r['clicks'];
            $ctr  = (float) $r['ctr'];
            $pos  = (float) $r['position'];

            if ( 'striking' === $tab && ( $pos < 5 || $pos > 15 ) ) {
                continue;
            }
            if ( 'lowctr' === $tab && ( $imp < 100 || $ctr >= 0.02 ) ) {
                continue;
            }
            $items[] = array( 'page' => $page, 'imp' => $imp, 'clk' => $clk, 'ctr' => $ctr, 'pos' => $pos );
        }

        if ( 'top' === $tab ) {
            usort( $items, function ( $a, $b ) { return $b['clk'] - $a['clk']; } );
        } else {
            usort( $items, function ( $a, $b ) { return $b['imp'] - $a['imp']; } );
        }
        $items = array_slice( $items, 0, 8 );

        if ( ! $items ) {
            return self::empty_state( 'در این بازه موردی مطابق این فیلتر پیدا نشد.' );
        }

        ob_start();
        ?>
        <div class="ns-list">
            <?php foreach ( $items as $it ) :
                $post  = self::post_by_url( $it['page'] );
                $title = $post ? $post->post_title : (string) parse_url( $it['page'], PHP_URL_PATH );
                $href  = $post ? self::edit_link( $post->ID ) : esc_url( $it['page'] );
                ?>
                <div class="ns-row">
                    <?php if ( $post ) : ?><span class="ns-tag ns-tag--muted"><?php echo esc_html( self::label( $post->post_type ) ); ?></span><?php endif; ?>
                    <a class="ns-row-title" href="<?php echo $href; ?>" <?php echo $post ? '' : 'target="_blank" rel="noopener"'; ?>><?php echo esc_html( $title ? $title : $it['page'] ); ?></a>
                    <span class="ns-time">👁 <?php echo number_format_i18n( $it['imp'] ); ?></span>
                    <span class="ns-time">🖱 <?php echo number_format_i18n( $it['clk'] ); ?></span>
                    <?php if ( 'lowctr' === $tab ) : ?>
                        <span class="ns-tag ns-tag--warn">CTR <?php echo number_format_i18n( round( $it['ctr'] * 100, 1 ) ); ?>٪</span>
                    <?php else : ?>
                        <span class="ns-tag ns-tag--muted">رتبه <?php echo number_format_i18n( round( $it['pos'], 1 ) ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ns-widget-foot">
            <?php
            if ( 'striking' === $tab ) {
                echo 'رتبه ۵ تا ۱۵ با نمایش بالا — با بهبود محتوا/لینک داخلی به صفحه اول نزدیک‌اند.';
            } elseif ( 'lowctr' === $tab ) {
                echo 'نمایش زیاد ولی CTR زیر ۲٪ — عنوان و توضیحات متا را بازنویسی کنید.';
            } else {
                echo 'پربازدیدترین صفحات از نظر کلیک در ۲۸ روز اخیر.';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

NextSafar_Dashboard_Insights::init_hooks();