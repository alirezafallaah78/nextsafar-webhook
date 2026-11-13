<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Widget_Visa_Applications extends NextSafar_Widget_Base {

    public function __construct() {
        parent::__construct(
            'visa_applications',
            'Visa Applications',
            'Recent visa applications and their status',
            'half'
        );
    }

    protected function get_data() {
        $applications = array();

        if ( post_type_exists( 'visa' ) ) {
            $posts = get_posts(
                array(
                    'post_type'      => 'visa',
                    'posts_per_page' => 10,
                    'post_status'    => 'any',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                )
            );

            foreach ( $posts as $post ) {
                $applications[] = array(
                    'id'        => $post->ID,
                    'title'     => $post->post_title,
                    'status'    => $post->post_status,
                    'date'      => get_the_date( 'M j, Y', $post->ID ),
                    'edit_link' => get_edit_post_link( $post->ID ),
                );
            }
        }

        return array( 'applications' => $applications );
    }

    protected function render_content( $data ) {
        if ( empty( $data['applications'] ) ) {
            echo '<div class="nextsafar-widget-empty">
                    <div class="nextsafar-empty-icon">📄</div>
                    <h4>No visa applications</h4>
                    <p>New applications will appear here.</p>
                  </div>';
            return;
        }

        $status_labels = array(
            'publish' => 'Approved',
            'pending' => 'Under Review',
            'draft'   => 'Draft',
        );
        ?>
        <div class="nextsafar-visa-list">
            <?php foreach ( $data['applications'] as $app ) :
                $status = $app['status'];
                $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
            ?>
                <div class="nextsafar-visa-item">
                    <div class="nextsafar-visa-info">
                        <a href="<?php echo esc_url( $app['edit_link'] ); ?>" class="nextsafar-visa-title">
                            <?php echo esc_html( $app['title'] ); ?>
                        </a>
                        <span class="nextsafar-visa-date">
                            <?php echo esc_html( $app['date'] ); ?>
                        </span>
                    </div>
                    <span class="nextsafar-status nextsafar-status-<?php echo esc_attr( $status ); ?>">
                        <?php echo esc_html( $status_label ); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}