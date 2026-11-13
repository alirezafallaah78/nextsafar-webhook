<?php
/**
 * 🔐 NextSafar Custom Login Page Handler
 * 
 * سفارشی‌سازی صفحه ورود وردپرس با برند NextSafar
 * 
 * @package NextSafar_Admin
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Login {

    /**
     * سازنده - ثبت هوک‌ها
     */
    public function __construct() {
        // فراخوانی استایل و اسکریپت
        add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
        
        // تغییر لوگو و لینک‌های آن
        add_action( 'login_head', array( $this, 'render_custom_logo' ) );
        add_filter( 'login_headerurl', array( $this, 'custom_logo_url' ) );
        add_filter( 'login_headertext', array( $this, 'custom_logo_title' ) );
        
        // افزودن کلاس اختصاصی به بادی
        add_filter( 'login_body_class', array( $this, 'add_body_class' ) );
        
        // تغییر متن فوتر (حذف Proudly powered by WordPress)
        add_action( 'login_footer', array( $this, 'custom_login_footer' ) );
    }

    /**
     * فراخوانی فایل‌های CSS و JS
     */
    public function enqueue_login_assets() {
        $base_url = defined( 'NEXTSAFAR_URL' ) ? NEXTSAFAR_URL : plugin_dir_url( __FILE__ );
        
        wp_enqueue_style(
            'nextsafar-login-css',
            $base_url . 'assets/css/login.css',
            array(),
            '1.0.2' // نسخه را تغییر دهید تا کش مرورگر پاک شود
        );

        wp_enqueue_script(
            'nextsafar-login-js',
            $base_url . 'assets/js/login.js',
            array(),
            '1.0.1',
            true
        );

        // انتقال متغیرها به جاوااسکریپت
        wp_localize_script( 'nextsafar-login-js', 'nextsafarLogin', array(
            'loadingText' => 'در حال ورود...',
            'buttonText'  => 'ورود به پنل'
        ));
    }

    /**
     * تغییر لوگوی صفحه ورود
     */
    public function render_custom_logo() {
        $logo_url = defined( 'NEXTSAFAR_URL' )
            ? NEXTSAFAR_URL . 'assets/img/favicon.png'
            : includes_url( 'images/w-logo-blue.png' );
        ?>
        <style type="text/css">
            #login h1 a, .login h1 a {
                background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
                background-size: contain !important;
                background-repeat: no-repeat !important;
                background-position: center center !important;
                width: 100% !important;
                height: 70px !important;
                margin-bottom: 20px !important;
            }

            /* مخفی کردن سوییچ زبان وردپرس در صورت وجود */
            .language-switcher { display: none !important; }
        </style>
        <?php
    }

    /**
     * تغییر لینک لوگو به صفحه اصلی سایت
     */
    public function custom_logo_url() {
        return home_url();
    }

    /**
     * تغییر عنوان (Title) لوگو
     */
    public function custom_logo_title() {
        return get_bloginfo( 'name' ) . ' - پنل مدیریت اختصاصی';
    }

    /**
     * افزودن کلاس به بادی برای استایل‌دهی بهتر
     */
    public function add_body_class( $classes ) {
        $classes[] = 'nextsafar-login-page';
        return $classes;
    }

    /**
     * تغییر فوتر صفحه لاگین
     */
    public function custom_login_footer() {
        ?>
        <style>
            /* مخفی کردن لینک پیش‌فرض وردپرس */
            #backtobalog { display: none !important; }

            /* استایل فوتر اختصاصی NextSafar */
            .nextsafar-custom-footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #64748b;
            }

            .nextsafar-custom-footer a {
                color: #2563eb;
                text-decoration: none;
                font-weight: 600;
            }

            .nextsafar-custom-footer a:hover {
                text-decoration: underline;
            }
        </style>

        <div class="nextsafar-custom-footer">
            © <?php echo date('Y'); ?> <a href="<?php echo home_url(); ?>" target="_blank"><?php echo get_bloginfo('name'); ?></a> | طراحی و توسعه اختصاصی
        </div>
        <?php
    }
}