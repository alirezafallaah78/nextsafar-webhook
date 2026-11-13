<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 🏷️ NextSafar Categories Manager
 * مدیریت کامل دسته‌بندی‌ها (CRUD operations)
 */
class NextSafar_Categories {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_categories_page' ) );
        add_action( 'admin_init', array( $this, 'handle_categories_actions' ) );
    }

    /* =========================================================
       📋 ثبت و رندر صفحه دسته‌بندی‌ها
    ========================================================== */

    public function register_categories_page() {
        add_submenu_page(
            'nextsafar-admin',
            'Categories',
            'Categories',
            'manage_categories',
            'nextsafar-categories',
            array( $this, 'render_categories_page' )
        );
    }

    public function render_categories_page() {
        require NEXTSAFAR_PATH . 'admin/pages/categories.php';
    }

    /* =========================================================
       ⚡ پردازش اکشن‌های دسته‌بندی (Add, Update, Delete)
    ========================================================== */

    public function handle_categories_actions() {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }

        if ( ! isset( $_POST['nextsafar_action'] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['nextsafar_action'] ) );

        switch ( $action ) {
            case 'add_category':
                $this->handle_add_category();
                break;

            case 'update_category':
                $this->handle_update_category();
                break;

            case 'delete_category':
                $this->handle_delete_category();
                break;
        }
    }

    /**
     * افزودن دسته‌بندی جدید
     */
    private function handle_add_category() {
        check_admin_referer( 'nextsafar_add_category' );

        $name        = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
        $slug        = isset( $_POST['category_slug'] ) ? sanitize_title( wp_unslash( $_POST['category_slug'] ) ) : '';
        $description = isset( $_POST['category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) ) : '';

        if ( empty( $name ) ) {
            $this->redirect_with_notice( 'error_name_required' );
            return;
        }

        $result = wp_insert_term(
            $name,
            'category',
            array(
                'slug'        => $slug,
                'description' => $description,
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error_add_failed' );
            return;
        }

        $this->redirect_with_notice( 'added' );
    }

    /**
     * بروزرسانی دسته‌بندی
     */
    private function handle_update_category() {
        check_admin_referer( 'nextsafar_update_category' );

        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
        $name        = isset( $_POST['category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
        $slug        = isset( $_POST['category_slug'] ) ? sanitize_title( wp_unslash( $_POST['category_slug'] ) ) : '';
        $description = isset( $_POST['category_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_description'] ) ) : '';

        if ( ! $category_id || empty( $name ) ) {
            $this->redirect_with_notice( 'error_update_failed' );
            return;
        }

        $result = wp_update_term(
            $category_id,
            'category',
            array(
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error_update_failed' );
            return;
        }

        $this->redirect_with_notice( 'updated' );
    }

    /**
     * حذف دسته‌بندی
     */
    private function handle_delete_category() {
        check_admin_referer( 'nextsafar_delete_category' );

        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        if ( ! $category_id ) {
            $this->redirect_with_notice( 'error_delete_failed' );
            return;
        }

        $result = wp_delete_category( $category_id );

        if ( ! $result ) {
            $this->redirect_with_notice( 'error_delete_failed' );
            return;
        }

        $this->redirect_with_notice( 'deleted' );
    }

    /**
     * Redirect با notice
     */
    private function redirect_with_notice( $notice ) {
        wp_safe_redirect(
            add_query_arg(
                'ns_notice',
                $notice,
                admin_url( 'admin.php?page=nextsafar-categories' )
            )
        );
        exit;
    }

    /* =========================================================
       🔧 متدهای کمکی برای View
    ========================================================== */

    /**
     * نمایش پیام‌های Notice
     */
    public static function display_notices() {
        if ( ! isset( $_GET['ns_notice'] ) ) {
            return;
        }

        $notice = sanitize_key( $_GET['ns_notice'] );

        $messages = array(
            'added'               => array( 'success', 'دسته‌بندی با موفقیت اضافه شد.' ),
            'updated'             => array( 'success', 'دسته‌بندی با موفقیت بروزرسانی شد.' ),
            'deleted'             => array( 'success', 'دسته‌بندی با موفقیت حذف شد.' ),
            'error_name_required' => array( 'error', 'نام دسته‌بندی الزامی است.' ),
            'error_add_failed'    => array( 'error', 'افزودن دسته‌بندی با خطا مواجه شد.' ),
            'error_update_failed' => array( 'error', 'بروزرسانی دسته‌بندی با خطا مواجه شد.' ),
            'error_delete_failed' => array( 'error', 'حذف دسته‌بندی با خطا مواجه شد.' ),
        );

        if ( ! isset( $messages[ $notice ] ) ) {
            return;
        }

        list( $type, $message ) = $messages[ $notice ];
        printf(
            '<div class="notice notice-%s is-dismissible" style="margin: 20px 0;">
                <p>%s</p>
            </div>',
            esc_attr( $type ),
            esc_html( $message )
        );
    }
}