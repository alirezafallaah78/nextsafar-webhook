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
        add_action( 'admin_init', array( $this, 'handle_cpt_bulk_actions' ), 20 );

        // 🆕 فیلتر ایجکسی همه لیست‌ها (نوشته، برگه، CPT)
        add_action( 'wp_ajax_nextsafar_filter_posts', array( $this, 'ajax_filter_posts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_assets' ) );
    }

    /* =========================================================
       🚀 متدهای جدید Repository (برای جداسازی کوئری‌ها از View)
    ========================================================== */
    
    public function get_list_data( $post_type, $args = array() ) {
        $user_per_page = absint( get_user_meta( get_current_user_id(), 'nextsafar_items_per_page', true ) );
        if ( $user_per_page < 5 || $user_per_page > 200 ) {
            $user_per_page = 20;
        }

        // 🆕 تشخیص تاکسونومی اصلی (دسته‌بندی) برای CPT
        $taxonomy = '';
        if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
            $tax_objects = get_object_taxonomies( $post_type, 'objects' );
            foreach ( $tax_objects as $tax_obj ) {
                if ( ! empty( $tax_obj->hierarchical ) ) {
                    $taxonomy = $tax_obj->name;
                    break;
                }
            }
            if ( '' === $taxonomy ) {
                foreach ( $tax_objects as $tax_obj ) {
                    if ( 'post_format' !== $tax_obj->name ) {
                        $taxonomy = $tax_obj->name;
                        break;
                    }
                }
            }
        }

        $defaults = array(
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status'   => isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '',
            'category' => isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0,
            'term'     => isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0,
            'taxonomy' => $taxonomy,
            'paged'    => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
            'per_page' => $user_per_page,
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

        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = $args['search'];
        }

        $allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'trash' );
        if ( ! empty( $args['status'] ) && in_array( $args['status'], $allowed_statuses, true ) ) {
            $query_args['post_status'] = $args['status'];
        } else {
            $query_args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
        }

        if ( 'post' === $post_type && ! empty( $args['category'] ) ) {
            $query_args['cat'] = $args['category'];
        }

        // 🆕 فیلتر تاکسونومی برای CPT
        if ( ! in_array( $post_type, array( 'post', 'page' ), true ) && ! empty( $args['term'] ) && ! empty( $args['taxonomy'] ) && taxonomy_exists( $args['taxonomy'] ) ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => $args['taxonomy'],
                    'field'    => 'term_id',
                    'terms'    => array( absint( $args['term'] ) ),
                ),
            );
        }

        if ( 'page' === $post_type ) {
            $query_args['orderby'] = 'menu_order title';
            $query_args['order']   = 'ASC';
        }

        $wp_query = new WP_Query( $query_args );

        return array(
            'query'     => $wp_query,
            'search'    => $args['search'],
            'status'    => $args['status'],
            'category'  => $args['category'],
            'term'      => isset( $args['term'] ) ? absint( $args['term'] ) : 0,
            'taxonomy'  => isset( $args['taxonomy'] ) ? $args['taxonomy'] : '',
            'paged'     => $args['paged'],
            'post_type' => $post_type,
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
        $view_file = NEXTSAFAR_ADMIN_PATH . 'views/admin/pages/' . $config['view_file'];
        
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
        
        $view_file = NEXTSAFAR_ADMIN_PATH . 'views/admin/pages/cpt.php';
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

        // 🆕 حفظ فیلترهای فعلی بعد از عملیات گروهی
        if ( ! empty( $_POST['ns_return'] ) ) {
            parse_str( sanitize_text_field( wp_unslash( $_POST['ns_return'] ) ), $ns_return );
            foreach ( array( 's', 'post_status', 'category', 'paged' ) as $ns_key ) {
                if ( isset( $ns_return[ $ns_key ] ) && ! is_array( $ns_return[ $ns_key ] ) && '' !== $ns_return[ $ns_key ] ) {
                    $redirect_url = add_query_arg( $ns_key, sanitize_text_field( $ns_return[ $ns_key ] ), $redirect_url );
                }
            }
        }

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
        
        $redirect_url = admin_url( 'admin.php?page=nextsafar-cpt&post_type=' . $post_type );

        // 🆕 حفظ فیلترهای فعلی بعد از عملیات گروهی
        if ( ! empty( $_POST['ns_return'] ) ) {
            parse_str( sanitize_text_field( wp_unslash( $_POST['ns_return'] ) ), $ns_return );
            foreach ( array( 's', 'post_status', 'term', 'paged' ) as $ns_key ) {
                if ( isset( $ns_return[ $ns_key ] ) && ! is_array( $ns_return[ $ns_key ] ) && '' !== $ns_return[ $ns_key ] ) {
                    $redirect_url = add_query_arg( $ns_key, sanitize_text_field( $ns_return[ $ns_key ] ), $redirect_url );
                }
            }
        }

        if ( $processed > 0 ) {
            $redirect_url = add_query_arg( 'bulk_processed', $processed, $redirect_url );
        }

        if ( $errors > 0 ) {
            $redirect_url = add_query_arg( 'bulk_errors', $errors, $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

        /* =========================================================
       🆕 فیلتر ایجکسی صفحه نوشته‌ها (بدون دکمه فیلتر)
    ========================================================== */

    public function enqueue_list_assets() {
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( ! in_array( $page, array( 'nextsafar-posts', 'nextsafar-pages', 'nextsafar-cpt' ), true ) ) {
            return;
        }

        $post_type  = 'post';
        $page_slug  = 'nextsafar-posts';
        $term_param = 'category';

        if ( 'nextsafar-pages' === $page ) {
            $post_type  = 'page';
            $page_slug  = 'nextsafar-pages';
            $term_param = '';
        } elseif ( 'nextsafar-cpt' === $page ) {
            $post_type  = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
            $page_slug  = 'nextsafar-cpt';
            $term_param = 'term';
            if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
                return;
            }
        }

        $ajax_url = admin_url( 'admin-ajax.php' );
        if ( class_exists( '\NextSafar\Core' ) && method_exists( '\NextSafar\Core', 'get_ajax_url' ) ) {
            $ajax_url = \NextSafar\Core::get_ajax_url();
        }

        wp_enqueue_script(
            'nextsafar-content-list',
            NEXTSAFAR_URL . 'assets/admin/js/pages/content-list.js',
            array(),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script( 'nextsafar-content-list', 'nextsafarList', array(
            'ajaxUrl'   => $ajax_url,
            'nonce'     => wp_create_nonce( 'nextsafar_posts_filter' ),
            'postType'  => $post_type,
            'pageSlug'  => $page_slug,
            'termParam' => $term_param,
        ) );
    }

    public function ajax_filter_posts() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_posts_filter' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
        }

        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
        if ( ! post_type_exists( $post_type ) ) {
            wp_send_json_error( array( 'message' => 'Invalid post type' ) );
        }

        if ( 'page' === $post_type ) {
            $cap = 'edit_pages';
        } elseif ( 'post' === $post_type ) {
            $cap = 'edit_posts';
        } else {
            $pto = get_post_type_object( $post_type );
            $cap = $pto ? $pto->cap->edit_posts : 'edit_posts';
        }
        if ( ! current_user_can( $cap ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $args = array(
            'search'   => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
            'status'   => isset( $_POST['post_status'] ) ? sanitize_key( $_POST['post_status'] ) : '',
            'category' => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
            'term'     => isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0,
            'paged'    => isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1,
        );

        $data        = $this->get_list_data( $post_type, $args );
        $posts_query = $data['query'];
        $paged       = $data['paged'];
        $search      = $data['search'];
        $status      = $data['status'];
        $category    = $data['category'];
        $term        = $data['term'];
        $taxonomy    = $data['taxonomy'];

        if ( 'post' === $post_type ) {
            ob_start();
            require NEXTSAFAR_PATH . 'views/admin/pages/posts-rows.php';
            $rows = ob_get_clean();
            wp_reset_postdata();

            ob_start();
            require NEXTSAFAR_PATH . 'views/admin/pages/posts-pagination.php';
            $pagination = ob_get_clean();
        } elseif ( 'page' === $post_type ) {
            $pages_query = $posts_query;
            ob_start();
            require NEXTSAFAR_PATH . 'views/admin/pages/pages-rows.php';
            $rows = ob_get_clean();
            wp_reset_postdata();

            $page_slug = 'nextsafar-pages';
            ob_start();
            require NEXTSAFAR_PATH . 'views/admin/pages/content-pagination.php';
            $pagination = ob_get_clean();
        } else {
            $cpt_query     = $posts_query;
            $post_type_obj = get_post_type_object( $post_type );
            $labels        = $post_type_obj ? $post_type_obj->labels : null;
            ob_start();
            require NEXTSAFAR_PATH . 'views/admin/pages/cpt-rows.php';
            $rows = ob_get_clean();
            wp_reset_postdata();

            $page_slug = 'nextsafar-cpt';
            ob_start();
            require NEXTSAFAR_PATH . 'views/admin/pages/content-pagination.php';
            $pagination = ob_get_clean();
        }

        wp_send_json_success( array(
            'rows'       => $rows,
            'pagination' => $pagination,
            'found'      => (int) $posts_query->found_posts,
            'paged'      => (int) $paged,
        ) );
    }
}