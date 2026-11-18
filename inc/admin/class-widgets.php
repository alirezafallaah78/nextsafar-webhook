<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Widgets {

    private $widgets = array();

    public function init() {
        add_action( 'admin_init', array( $this, 'register_widgets' ) );
        add_action( 'wp_ajax_nextsafar_save_dashboard_layout', array( $this, 'ajax_save_layout' ) );
        add_action( 'wp_ajax_nextsafar_toggle_widget', array( $this, 'ajax_toggle_widget' ) );
        add_action( 'wp_ajax_nextsafar_debug_widget_settings', array( $this, 'ajax_debug_settings' ) );
    }

    public function register_widgets() {
        $this->widgets = array(
            'stats_cards'          => new NextSafar_Widget_Stats(),
            'revenue_chart'        => new NextSafar_Widget_Revenue_Chart(),
            'recent_content'       => new NextSafar_Widget_Recent_Bookings(),
            'visa_applications'    => new NextSafar_Widget_Visa_Applications(),
            'yoast_seo'            => new NextSafar_Widget_Yoast_SEO(),
        );

        $this->widgets = apply_filters( 'nextsafar_dashboard_widgets', $this->widgets );
    }

    public function get_widgets() {
        if ( empty( $this->widgets ) ) {
            $this->register_widgets();
        }
        return $this->widgets;
    }

    public function get_widget( $id ) {
        $widgets = $this->get_widgets();
        return isset( $widgets[ $id ] ) ? $widgets[ $id ] : null;
    }

    public function get_user_layout() {
        $user_id = get_current_user_id();
        $layout  = get_user_meta( $user_id, 'nextsafar_dashboard_layout', true );

        if ( empty( $layout ) || ! is_array( $layout ) ) {
            $layout = array_keys( $this->get_widgets() );
        }

        return $layout;
    }

    public function get_user_widget_settings() {
        $user_id  = get_current_user_id();
        $settings = get_user_meta( $user_id, 'nextsafar_dashboard_widgets', true );

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        return $settings;
    }

    public function is_widget_enabled( $widget_id ) {
        $settings = $this->get_user_widget_settings();

        // اگر تنظیماتی برای این ویجت وجود ندارد، به طور پیش‌فرض فعال است
        if ( ! isset( $settings[ $widget_id ] ) ) {
            return true;
        }

        // اگر enabled key وجود ندارد، فعال است
        if ( ! isset( $settings[ $widget_id ]['enabled'] ) ) {
            return true;
        }

        return (bool) $settings[ $widget_id ]['enabled'];
    }

    public function render_widgets() {
        $layout  = $this->get_user_layout();
        $widgets = $this->get_widgets();

        echo '<div class="nextsafar-widgets-grid">';

        foreach ( $layout as $widget_id ) {
            if ( ! isset( $widgets[ $widget_id ] ) ) {
                continue;
            }

            // فقط ویجت‌های فعال را نمایش بده
            if ( ! $this->is_widget_enabled( $widget_id ) ) {
                continue;
            }

            $widgets[ $widget_id ]->render();
        }

        echo '</div>';
    }

    public function render_screen_options() {
        $widgets  = $this->get_widgets();
        $settings = $this->get_user_widget_settings();

        // دریافت ویجت‌های وردپرس
        global $wp_meta_boxes;
        $screen_id = 'toplevel_page_nextsafar-admin';
        $wp_widgets = array();

        if ( isset( $wp_meta_boxes[ $screen_id ] ) ) {
            foreach ( $wp_meta_boxes[ $screen_id ] as $context => $priorities ) {
                foreach ( $priorities as $priority => $boxes ) {
                    foreach ( $boxes as $box_id => $box ) {
                        $wp_widgets[ $box_id ] = array(
                            'id'    => $box_id,
                            'title' => is_array( $box['title'] ) ? '' : $box['title'],
                        );
                    }
                }
            }
        }

        ?>
        <div class="nextsafar-screen-options-modal" id="nextsafar-screen-options-modal" style="display: none;">
            <div class="nextsafar-modal-backdrop"></div>
            <div class="nextsafar-modal-content">
                <div class="nextsafar-modal-header">
                    <h3>Screen Options</h3>
                    <button type="button" class="nextsafar-modal-close" id="nextsafar-screen-options-close">×</button>
                </div>
                <div class="nextsafar-modal-body">
                    
                    <!-- Custom Widgets -->
                    <h4 class="nextsafar-screen-options-section-title">Custom Widgets</h4>
                    <p class="nextsafar-screen-options-desc">
                        Choose which custom widgets to display on your dashboard.
                    </p>
                    <div class="nextsafar-screen-options-list">
                        <?php foreach ( $widgets as $widget ) :
                            $widget_id = $widget->get_id();
                            $is_enabled = $this->is_widget_enabled( $widget_id );
                        ?>
                            <label class="nextsafar-screen-option-item">
                                <input
                                    type="checkbox"
                                    name="widget_enabled[]"
                                    value="<?php echo esc_attr( $widget_id ); ?>"
                                    <?php checked( $is_enabled ); ?>
                                >
                                <div>
                                    <strong><?php echo esc_html( $widget->get_title() ); ?></strong>
                                    <?php if ( $widget->get_description() ) : ?>
                                        <small><?php echo esc_html( $widget->get_description() ); ?></small>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- WordPress Widgets -->
                    <?php if ( ! empty( $wp_widgets ) ) : ?>
                        <h4 class="nextsafar-screen-options-section-title" style="margin-top: 24px;">
                            WordPress & Plugin Widgets
                        </h4>
                        <p class="nextsafar-screen-options-desc">
                            Choose which WordPress and plugin widgets to display.
                        </p>
                        <div class="nextsafar-screen-options-list">
                            <?php foreach ( $wp_widgets as $wp_widget ) :
                                $widget_id = 'wp_' . $wp_widget['id'];
                                $is_enabled = $this->is_widget_enabled( $widget_id );
                            ?>
                                <label class="nextsafar-screen-option-item">
                                    <input
                                        type="checkbox"
                                        name="widget_enabled[]"
                                        value="<?php echo esc_attr( $widget_id ); ?>"
                                        <?php checked( $is_enabled ); ?>
                                    >
                                    <div>
                                        <strong><?php echo esc_html( $wp_widget['title'] ); ?></strong>
                                        <small>WordPress widget</small>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="nextsafar-modal-footer">
                    <button type="button" class="nextsafar-secondary-button" id="nextsafar-screen-options-cancel">Cancel</button>
                    <button type="button" class="nextsafar-primary-button" id="nextsafar-screen-options-save">Save Changes</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_save_layout() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }

        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $user_id = get_current_user_id();
        $layout  = isset( $_POST['layout'] ) ? array_map( 'sanitize_key', (array) $_POST['layout'] ) : array();

        update_user_meta( $user_id, 'nextsafar_dashboard_layout', $layout );

        wp_send_json_success( array( 'message' => 'Layout saved successfully.' ) );
    }

    public function ajax_toggle_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }

        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_dashboard_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        $user_id    = get_current_user_id();
        $widget_id  = isset( $_POST['widget_id'] ) ? sanitize_key( $_POST['widget_id'] ) : '';
        $enabled    = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1' ? true : false;

        if ( ! $widget_id ) {
            wp_send_json_error( array( 'message' => 'Invalid widget ID.' ), 400 );
        }

        // دریافت تنظیمات فعلی
        $settings = get_user_meta( $user_id, 'nextsafar_dashboard_widgets', true );
        
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        // به‌روزرسانی تنظیمات این ویجت
        $settings[ $widget_id ] = array( 'enabled' => $enabled );

        // حذف و سپس ذخیره مجدد (برای اطمینان از به‌روزرسانی)
        delete_user_meta( $user_id, 'nextsafar_dashboard_widgets' );
        $result = add_user_meta( $user_id, 'nextsafar_dashboard_widgets', $settings, true );

        // بررسی نهایی
        $saved = get_user_meta( $user_id, 'nextsafar_dashboard_widgets', true );

        wp_send_json_success(
            array(
                'message'     => 'Widget settings saved.',
                'widget_id'   => $widget_id,
                'enabled'     => $enabled,
                'saved'       => isset( $saved[ $widget_id ] ) ? $saved[ $widget_id ] : null,
            )
        );
    }

    /**
     * Debug endpoint
     */
    public function ajax_debug_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $user_id  = get_current_user_id();
        $settings = get_user_meta( $user_id, 'nextsafar_dashboard_widgets', true );

        wp_send_json_success(
            array(
                'user_id'       => $user_id,
                'raw_settings'  => $settings,
                'is_array'      => is_array( $settings ),
                'widgets'       => $this->get_widgets() ? array_keys( $this->get_widgets() ) : array(),
            )
        );
    }
}