<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Widget_Recent_Bookings extends NextSafar_Widget_Base {

    public function __construct() {
        parent::__construct(
            'recent_content',
            'Recent Content',
            'Latest tours, visas and articles',
            'half'
        );
    }

    protected function get_data() {
        // 🚀 کش ۵ دقیقه‌ای
        $cache_key = 'nextsafar_widget_recent_content';
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // 🚀 یک کوئری واحد به جای سه کوئری جداگانه
        $post_types = array( 'post' );
        if ( post_type_exists( 'tour' ) ) $post_types[] = 'tour';
        if ( post_type_exists( 'visa' ) ) $post_types[] = 'visa';

        $posts = get_posts( array(
            'post_type'      => $post_types,
            'posts_per_page' => 10,
            'post_status'    => array( 'publish', 'any' ),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true, // 🚀 صرفه‌جویی در COUNT(*)
        ) );

        $type_labels = array(
            'tour' => 'Tour',
            'visa' => 'Visa',
            'post' => 'Article',
        );

        $type_icons = array(
            'tour' => '✈',
            'visa' => '📄',
            'post' => '📝',
        );

        $items = array();
        foreach ( $posts as $post ) {
            $items[] = array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'type'       => $post->post_type,
                'type_label' => $type_labels[ $post->post_type ] ?? ucfirst( $post->post_type ),
                'type_icon'  => $type_icons[ $post->post_type ] ?? '📄',
                'status'     => $post->post_status,
                'date'       => get_the_date( 'M j, Y', $post->ID ),
                'edit_link'  => get_edit_post_link( $post->ID ),
            );
        }

        $data = array( 'items' => $items );
        set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

        return $data;
    }

    protected function render_content( $data ) {
        if ( empty( $data['items'] ) ) {
            echo '<div class="nextsafar-widget-empty">
                <div class="nextsafar-empty-icon">📋</div>
                <h4>No content yet</h4>
                <p>Create your first tour, visa or article.</p>
            </div>';
            return;
        }
        ?>
        <div class="nextsafar-content-list">
            <?php foreach ( $data['items'] as $item ) : ?>
            <div class="nextsafar-content-item">
                <div class="nextsafar-content-info">
                    <div class="nextsafar-content-header">
                        <span class="nextsafar-content-icon"><?php echo esc_html( $item['type_icon'] ); ?></span>
                        <a href="<?php echo esc_url( $item['edit_link'] ); ?>" class="nextsafar-content-title">
                            <?php echo esc_html( $item['title'] ); ?>
                        </a>
                    </div>
                    <div class="nextsafar-content-meta">
                        <span class="nextsafar-content-type"><?php echo esc_html( $item['type_label'] ); ?></span>
                        <span class="nextsafar-content-date"><?php echo esc_html( $item['date'] ); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}