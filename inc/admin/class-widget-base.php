<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class NextSafar_Widget_Base {

    protected $id;
    protected $title;
    protected $description;
    protected $width = 'half'; // 'full', 'half', 'third'
    protected $enabled = true;

    public function __construct( $id, $title, $description = '', $width = 'half' ) {
        $this->id          = $id;
        $this->title       = $title;
        $this->description = $description;
        $this->width       = $width;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_title() {
        return $this->title;
    }

    public function get_description() {
        return $this->description;
    }

    public function get_width() {
        return $this->width;
    }

    public function is_enabled() {
        $user_id  = get_current_user_id();
        $settings = get_user_meta( $user_id, 'nextsafar_dashboard_widgets', true );

        if ( empty( $settings ) ) {
            return $this->enabled;
        }

        return isset( $settings[ $this->id ]['enabled'] ) ? (bool) $settings[ $this->id ]['enabled'] : $this->enabled;
    }

    public function render() {
        $user_id  = get_current_user_id();
        $settings = get_user_meta( $user_id, 'nextsafar_dashboard_widgets', true );

        // دریافت داده‌ها
        $data = $this->get_data();
        
        ?>
        <div
            class="nextsafar-widget nextsafar-widget-<?php echo esc_attr( $this->width ); ?>"
            data-widget-id="<?php echo esc_attr( $this->id ); ?>"
        >
            <div class="nextsafar-widget-header">
                <div class="nextsafar-widget-title">
                    <h3><?php echo esc_html( $this->title ); ?></h3>
                    <?php if ( $this->description ) : ?>
                        <p><?php echo esc_html( $this->description ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="nextsafar-widget-actions">
                    <button type="button" class="nextsafar-widget-toggle" title="Toggle">
                        ▾
                    </button>
                </div>
            </div>
            <div class="nextsafar-widget-body">
                <?php
                // فراخوانی render_content
                $this->render_content( $data );
                ?>
            </div>
        </div>
        <?php
    }

    abstract protected function get_data();
    abstract protected function render_content( $data );
}