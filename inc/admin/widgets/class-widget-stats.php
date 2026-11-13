<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Widget_Stats extends NextSafar_Widget_Base {

    public function __construct() {
        parent::__construct(
            'stats_cards',
            'Statistics Overview',
            'Key metrics for your travel business',
            'full'
        );
    }

    protected function get_data() {
        // 🚀 کش ۵ دقیقه‌ای
        $cache_key = 'nextsafar_widget_stats_data';
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $data = array(
            'tours'  => 0,
            'visas'  => 0,
            'posts'  => 0,
            'media'  => 0,
            'users'  => 0,
        );

        // 🚀 Tours
        if ( post_type_exists( 'tour' ) ) {
            $counts = wp_count_posts( 'tour' );
            $data['tours'] = isset( $counts->publish ) ? (int) $counts->publish : 0;
        }

        // 🚀 Visas
        if ( post_type_exists( 'visa' ) ) {
            $counts = wp_count_posts( 'visa' );
            foreach ( (array) $counts as $status => $count ) {
                if ( 'trash' !== $status ) {
                    $data['visas'] += (int) $count;
                }
            }
        }

        // 🚀 Posts
        $counts = wp_count_posts( 'post' );
        $data['posts'] = isset( $counts->publish ) ? (int) $counts->publish : 0;

        // 🚀 Media — یک کوئری سریع به جای حلقه
        global $wpdb;
        $data['media'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
        );

        // 🚀 Users
        $user_counts = count_users();
        $data['users'] = $user_counts['total_users'];

        set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );
        return $data;
    }

    protected function render_content( $data ) {
        ?>
        <div class="nextsafar-stats-cards">
            <div class="nextsafar-stat-card">
                <div class="nextsafar-stat-icon nextsafar-stat-icon-blue">
                    <?php NextSafar_Icons::display( 'plane', 16, 'ns-icon-transparent' ); ?>
                </div>
                <div class="nextsafar-stat-content">
                    <span>Total Tours</span>
                    <strong><?php echo number_format_i18n( $data['tours'] ); ?></strong>
                    <small>Active tours</small>
                </div>
            </div>

            <div class="nextsafar-stat-card">
                <div class="nextsafar-stat-icon nextsafar-stat-icon-orange">
                    📄
                </div>
                <div class="nextsafar-stat-content">
                    <span>Visa Applications</span>
                    <strong><?php echo number_format_i18n( $data['visas'] ); ?></strong>
                    <small>Total applications</small>
                </div>
            </div>

            <div class="nextsafar-stat-card">
                <div class="nextsafar-stat-icon nextsafar-stat-icon-green">
                    📝
                </div>
                <div class="nextsafar-stat-content">
                    <span>Articles</span>
                    <strong><?php echo number_format_i18n( $data['posts'] ); ?></strong>
                    <small>Published posts</small>
                </div>
            </div>

            <div class="nextsafar-stat-card">
                <div class="nextsafar-stat-icon nextsafar-stat-icon-purple">
                    🖼
                </div>
                <div class="nextsafar-stat-content">
                    <span>Media Files</span>
                    <strong><?php echo number_format_i18n( $data['media'] ); ?></strong>
                    <small>Images & files</small>
                </div>
            </div>

            <div class="nextsafar-stat-card">
                <div class="nextsafar-stat-icon nextsafar-stat-icon-teal">
                    👥
                </div>
                <div class="nextsafar-stat-content">
                    <span>Total Users</span>
                    <strong><?php echo number_format_i18n( $data['users'] ); ?></strong>
                    <small>Registered</small>
                </div>
            </div>
        </div>
        <?php
    }
}