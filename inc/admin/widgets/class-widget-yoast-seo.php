<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Widget_Yoast_SEO extends NextSafar_Widget_Base {

    public function __construct() {
        parent::__construct(
            'yoast_seo',
            __( 'Yoast SEO Overview', 'nextsafar-admin' ),
            __( 'SEO statistics and recommendations', 'nextsafar-admin' ),
            'half'
        );
    }

    protected function get_data() {
        // 🆕 استفاده از متد مشترک در NextSafar_Dashboard
        $dashboard = nextsafar()->dashboard;
        if ( $dashboard && method_exists( $dashboard, 'get_yoast_stats' ) ) {
            return $dashboard->get_yoast_stats();
        }

        // Fallback: اگر dashboard در دسترس نبود
        return array( 'available' => false );
    }

    protected function render_content( $data ) {
        if ( ! $data['available'] ) {
            echo '<div class="nextsafar-widget-empty">
                <div class="nextsafar-empty-icon">⚠</div>
                <h4>' . esc_html__( 'Yoast SEO not installed', 'nextsafar-admin' ) . '</h4>
                <p>' . esc_html__( 'Install Yoast SEO to see SEO statistics.', 'nextsafar-admin' ) . '</p>
            </div>';
            return;
        }

        $total_scored = $data['posts_with_good_seo'] + $data['posts_with_poor_seo'];
        $good_pct = $total_scored > 0 ? round( ( $data['posts_with_good_seo'] / $total_scored ) * 100 ) : 0;

        $readability_total = $data['readability_good'] + $data['readability_poor'];
        $readability_pct = $readability_total > 0 ? round( ( $data['readability_good'] / $readability_total ) * 100 ) : 0;
        ?>
        <div class="nextsafar-yoast-stats">
            <div class="nextsafar-yoast-header-info">
                <div class="nextsafar-yoast-version">
                    <span class="nextsafar-yoast-badge">
                        <?php printf( esc_html__( 'Yoast SEO v%s', 'nextsafar-admin' ), esc_html( $data['version'] ) ); ?>
                    </span>
                    <small><?php printf( esc_html__( 'Using: %s', 'nextsafar-admin' ), esc_html( $data['method'] ) ); ?></small>
                </div>
                <div class="nextsafar-yoast-total">
                    <strong><?php echo number_format_i18n( $data['total_posts'] ); ?></strong>
                    <span><?php esc_html_e( 'Total Posts', 'nextsafar-admin' ); ?></span>
                </div>
            </div>
            <div class="nextsafar-yoast-stat-item">
                <div class="nextsafar-yoast-stat-label">
                    <span><?php esc_html_e( 'Posts without Focus Keyword', 'nextsafar-admin' ); ?></span>
                </div>
                <strong class="nextsafar-yoast-stat-value nextsafar-yoast-warning">
                    <?php echo number_format_i18n( $data['posts_without_focus'] ); ?>
                </strong>
            </div>
            <div class="nextsafar-yoast-stat-item">
                <div class="nextsafar-yoast-stat-label">
                    <span><?php esc_html_e( 'Posts with Focus Keyword', 'nextsafar-admin' ); ?></span>
                </div>
                <strong class="nextsafar-yoast-stat-value nextsafar-yoast-info">
                    <?php echo number_format_i18n( $data['posts_with_focus'] ); ?>
                </strong>
            </div>
            <div class="nextsafar-yoast-stat-item">
                <div class="nextsafar-yoast-stat-label">
                    <span><?php esc_html_e( 'Good SEO Score (70+)', 'nextsafar-admin' ); ?></span>
                </div>
                <strong class="nextsafar-yoast-stat-value nextsafar-yoast-success">
                    <?php echo number_format_i18n( $data['posts_with_good_seo'] ); ?>
                </strong>
            </div>
            <div class="nextsafar-yoast-stat-item">
                <div class="nextsafar-yoast-stat-label">
                    <span><?php esc_html_e( 'Poor SEO Score (<70)', 'nextsafar-admin' ); ?></span>
                </div>
                <strong class="nextsafar-yoast-stat-value nextsafar-yoast-danger">
                    <?php echo number_format_i18n( $data['posts_with_poor_seo'] ); ?>
                </strong>
            </div>
            <div class="nextsafar-yoast-progress">
                <div class="nextsafar-yoast-progress-label">
                    <span><?php esc_html_e( 'Overall SEO Health', 'nextsafar-admin' ); ?></span>
                    <strong><?php echo esc_html( $good_pct ); ?>%</strong>
                </div>
                <div class="nextsafar-yoast-progress-bar">
                    <div class="nextsafar-yoast-progress-fill" style="width: <?php echo esc_attr( $good_pct ); ?>%;"></div>
                </div>
            </div>
            <div class="nextsafar-yoast-progress">
                <div class="nextsafar-yoast-progress-label">
                    <span>📖 <?php esc_html_e( 'Readability Score', 'nextsafar-admin' ); ?></span>
                    <strong><?php echo esc_html( $readability_pct ); ?>%</strong>
                </div>
                <div class="nextsafar-yoast-progress-bar nextsafar-yoast-progress-readability">
                    <div class="nextsafar-yoast-progress-fill" style="width: <?php echo esc_attr( $readability_pct ); ?>%;"></div>
                </div>
            </div>
        </div>
        <?php
    }
}