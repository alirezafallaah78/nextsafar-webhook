<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Content_Manager {
    private $managed_post_types = array( 'post', 'page' );
    private $post_type_config = array();

    public function __construct() {
        $this->post_type_config = array(
            'post' => array(
                'slug'         => 'nextsafar-posts',
                'capability'   => 'edit_posts',
                'menu_title'   => 'Posts',
                'page_title'   => 'Posts',
                'view_file'    => 'posts.php',
                'nonce_action' => 'nextsafar_bulk_posts',
            ),
            'page' => array(
                'slug'         => 'nextsafar-pages',
                'capability'   => 'edit_pages',
                'menu_title'   => 'Pages',
                'page_title'   => 'Pages',
                'view_file'    => 'pages.php',
                'nonce_action' => 'nextsafar_bulk_pages',
            ),
        );
    }

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_content_pages' ), 2 );
        add_action( 'admin_init', array( $this, 'handle_all_bulk_actions' ) );
        add_action( 'admin_init', array( $this, 'handle_cpt_bulk_actions' ), 20 ); // 🆕 Handler برای CPT ها
    }

    /* =========================================================
       🚀 متدهای جدید Repository (برای جداسازی کوئری‌ها از View)
    ========================================================== */
    
    /**
     * گرفتن داده‌های لیست پست‌ها بر اساس فیلترها
     */
    public function get_list_data( $post_type, $args = array() ) {
        // 🎯 دریافت per_page از Screen Options
        $user_per_page = get_user_meta( 
            get_current_user_id(), 
            'nextsafar_items_per_page', 
            true 
        );
        $user_per_page = absint( $user_per_page );
        if ( $user_per_page < 5 || $user_per_page > 200 ) {
            $user_per_page = 20;
        }

        $defaults = array(
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status'   => isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '',
            'category' => isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0,
            'paged'    => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
            'per_page' => $user_per_page, // 🆕 استفاده از تنظیمات کاربر
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $query_args = array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['paged'],
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // فیلتر جستجو
        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = $args['search'];
        }

        // فیلتر وضعیت
        $allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'trash' );
        if ( ! empty( $args['status'] ) && in_array( $args['status'], $allowed_statuses, true ) ) {
            $query_args['post_status'] = $args['status'];
        } else {
            $query_args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
        }

        // فیلتر دسته‌بندی (فقط برای post)
        if ( $post_type === 'post' && ! empty( $args['category'] ) ) {
            $query_args['cat'] = $args['category'];
        }
        
        // فیلتر مرتب‌سازی (برای Pages)
        if ( $post_type === 'page' ) {
            $query_args['orderby'] = 'menu_order title';
            $query_args['order'] = 'ASC';
        }

        $wp_query = new WP_Query( $query_args );
        
        return array(
            'query'       => $wp_query,
            'search'      => $args['search'],
            'status'      => $args['status'],
            'category'    => $args['category'],
            'paged'       => $args['paged'],
            'post_type'   => $post_type,
        );
    }

    /* =========================================================
       📋 ثبت و رندر صفحات (بدون تغییر)
    ========================================================== */
    public function register_content_pages() {
        foreach ( $this->post_type_config as $post_type => $config ) {
            add_submenu_page(
                'nextsafar-admin',
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $config['slug'],
                array( $this, 'render_content_page_' . $post_type )
            );
        }
    }

    public function render_content_page_post() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
        }

        $this->render_content_page( 'post' );
    }

    public function render_content_page_page() {
        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
        }
    
        $this->render_content_page( 'page' );
    }

    private function render_content_page( $post_type ) {
        if ( ! isset( $this->post_type_config[ $post_type ] ) ) {
            wp_die( 'Invalid content type.' );
        }
        $config = $this->post_type_config[ $post_type ];
        $view_file = NEXTSAFAR_PATH . 'views/admin/pages/' . $config['view_file'];
        
        // 🚀 تزریق داده‌ها به View
        $data = $this->get_list_data( $post_type );
        
        if ( file_exists( $view_file ) ) {
            require $view_file;
        } else {
            wp_die( 'View file not found: ' . $config['view_file'] );
        }
    }

    /**
     * 🆕 رندر صفحه CPT داینامیک (بدون تغییر)
     */
    public function render_content_page_dynamic() {
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
        if ( empty( $post_type ) ) {
            wp_die( 'Post type not specified.', 'خطا', array( 'response' => 400 ) );
        }
        if ( ! post_type_exists( $post_type ) ) {
            wp_die( 'Post type does not exist: ' . esc_html( $post_type ), 'خطا', array( 'response' => 404 ) );
        }
        $post_type_obj = get_post_type_object( $post_type );
        if ( ! $post_type_obj ) {
            wp_die( 'Cannot load post type object.', 'خطا', array( 'response' => 500 ) );
        }
        $capability = $post_type_obj->cap->edit_posts;
        if ( ! current_user_can( $capability ) ) {
            wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
        }

        // 🚀 تزریق داده‌ها به View
        $data = $this->get_list_data( $post_type );
        
        $view_file = NEXTSAFAR_PATH . 'views/admin/pages/cpt.php';
        if ( ! file_exists( $view_file ) ) {
            wp_die( 'CPT view file not found.', 'خطای فایل', array( 'response' => 500 ) );
        }
        require $view_file;
    }

    /* =========================================================
       ⚡ پردازش Bulk Actions (بدون تغییر)
    ========================================================== */
    public function handle_all_bulk_actions() {
        if ( ! is_admin() ) return;
        if ( ! isset( $_POST['nextsafar_bulk_action'] ) ) return;

        $detected_type = $this->detect_content_type_from_request();
        if ( ! $detected_type ) return;

        $config = $this->post_type_config[ $detected_type ];
        $capability = $detected_type === 'page' ? 'edit_pages' : 'edit_posts';
        $id_key = $detected_type === 'page' ? 'page_ids' : 'post_ids';

        if ( ! current_user_can( $capability ) ) return;
        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $config['nonce_action'] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['nextsafar_bulk_action'] ) );
        $ids = isset( $_POST[ $id_key ] ) ? array_map( 'absint', (array) $_POST[ $id_key ] ) : array();

        if ( empty( $ids ) ) return;

        $processed = 0;
        foreach ( $ids as $id ) {
            if ( ! current_user_can( 'edit_post', $id ) ) continue;
            $result = $this->execute_bulk_action( $action, $id, $detected_type );
            if ( $result ) $processed++;
        }

        $redirect_url = admin_url( 'admin.php?page=' . $config['slug'] );
        if ( $processed > 0 ) {
            $redirect_url = add_query_arg( 'bulk_processed', $processed, $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    private function detect_content_type_from_request() {
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        foreach ( $this->post_type_config as $post_type => $config ) {
            if ( wp_verify_nonce( $nonce, $config['nonce_action'] ) ) {
                return $post_type;
            }
        }
        if ( isset( $_POST['page_ids'] ) ) return 'page';
        if ( isset( $_POST['post_ids'] ) ) return 'post';
        return null;
    }

    private function execute_bulk_action( $action, $post_id, $post_type ) {
        switch ( $action ) {
            case 'trash': return wp_trash_post( $post_id );
            case 'restore': return wp_untrash_post( $post_id );
            case 'delete': return wp_delete_post( $post_id, true );
            default: return apply_filters( 'nextsafar_custom_bulk_action', false, $action, $post_id, $post_type );
        }
    }

    public function display_bulk_notice() {
        // پیام موفقیت
        if ( isset( $_GET['bulk_processed'] ) ) {
            $count = absint( $_GET['bulk_processed'] );
            if ( $count > 0 ) {
                printf(
                    '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p>%s</p></div>',
                    sprintf(
                        _n( '%d مورد با موفقیت پردازش شد.', '%d مورد با موفقیت پردازش شدند.', $count, 'nextsafar-admin' ),
                        $count
                    )
                );
            }
        }
        
        // 🆕 پیام خطا
        if ( isset( $_GET['bulk_errors'] ) ) {
            $count = absint( $_GET['bulk_errors'] );
            if ( $count > 0 ) {
                printf(
                    '<div class="notice notice-error is-dismissible" style="margin: 20px 0;"><p>%s</p></div>',
                    sprintf(
                        _n( '%d مورد با خطا مواجه شد.', '%d مورد با خطا مواجه شدند.', $count, 'nextsafar-admin' ),
                        $count
                    )
                );
            }
        }
    }

    /**
     * 🆕 پردازش Bulk Actions برای Custom Post Types
     * این متد به صورت داینامیک همه CPT ها را پردازش می‌کند
     */
    public function handle_cpt_bulk_actions() {
        if ( ! is_admin() ) return;
        
        // بررسی وجود bulk action
        if ( ! isset( $_POST['nextsafar_bulk_action'] ) ) return;
        
        // بررسی hidden input مخصوص CPT
        if ( ! isset( $_POST['nextsafar_post_type'] ) ) return;
        
        $post_type = sanitize_key( wp_unslash( $_POST['nextsafar_post_type'] ) );
        
        // بررسی وجود post type
        if ( ! post_type_exists( $post_type ) ) return;
        
        // بررسی اینکه این یک CPT است (نه post یا page)
        if ( in_array( $post_type, array( 'post', 'page' ), true ) ) return;
        
        // بررسی nonce
        $nonce_action = 'nextsafar_bulk_cpt_' . $post_type;
        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $nonce_action ) ) {
            return;
        }
        
        // گرفتن post type object برای بررسی capability
        $post_type_obj = get_post_type_object( $post_type );
        if ( ! $post_type_obj ) return;
        
        // بررسی دسترسی
        if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
            wp_die( 'شما اجازه انجام این عملیات را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
        }
        
        // گرفتن action و IDs
        $action = sanitize_key( wp_unslash( $_POST['nextsafar_bulk_action'] ) );
        $ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();
        
        if ( empty( $ids ) ) return;
        
        // پردازش هر ID
        $processed = 0;
        $errors = 0;
        
        foreach ( $ids as $id ) {
            // بررسی post type صحیح
            if ( get_post_type( $id ) !== $post_type ) {
                $errors++;
                continue;
            }
            
            // بررسی دسترسی به این پست خاص
            if ( ! current_user_can( 'edit_post', $id ) ) {
                $errors++;
                continue;
            }
            
            // اجرای action
            $result = $this->execute_bulk_action( $action, $id, $post_type );
            
            if ( $result ) {
                $processed++;
            } else {
                $errors++;
            }
        }
        
        // Redirect با پیام
        $redirect_url = admin_url( 'admin.php?page=nextsafar-cpt&post_type=' . $post_type );
        
        if ( $processed > 0 ) {
            $redirect_url = add_query_arg( 'bulk_processed', $processed, $redirect_url );
        }
        
        if ( $errors > 0 ) {
            $redirect_url = add_query_arg( 'bulk_errors', $errors, $redirect_url );
        }
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
}