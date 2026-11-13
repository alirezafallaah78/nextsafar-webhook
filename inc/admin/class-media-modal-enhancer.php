<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * NextSafar Media Modal Enhancer - Production Ready
 */
class NextSafar_Media_Modal_Enhancer {

    private static $folders_cache = null;

    public function init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'ajax_query_attachments_args', array( $this, 'filter_attachments_by_folder' ), 10, 1 );
        add_action( 'admin_footer', array( $this, 'print_folders_data' ) );
        add_action( 'wp_ajax_nextsafar_modal_media', array( $this, 'ajax_modal_media' ) );
    }

    public function enqueue_assets( $hook ) {
        $allowed_hooks = array( 'post.php', 'post-new.php', 'edit-tags.php', 'term.php' );
        $is_allowed = in_array( $hook, $allowed_hooks, true );
        
        if ( ! $is_allowed ) {
            $screen = get_current_screen();
            if ( $screen && in_array( $screen->base, array( 'post', 'term', 'edit-tags' ), true ) ) {
                $is_allowed = true;
            }
        }

        if ( ! $is_allowed ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'nextsafar-media',
            NEXTSAFAR_URL . 'assets/css/media.css',
            array(),
            NEXTSAFAR_ADMIN_VERSION
        );

        // 🚀 مطمئن شویم admin.js لود شده (برای دسترسی به nextsafarAdmin.mediaDetailsNonce)
        if ( ! wp_script_is( 'nextsafar-admin', 'enqueued' ) ) {
            wp_enqueue_script(
                'nextsafar-admin',
                NEXTSAFAR_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                NEXTSAFAR_ADMIN_VERSION,
                true
            );
            
            wp_localize_script(
                'nextsafar-admin',
                'nextsafarAdmin',
                array(
                    'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
                    'mediaNonce'        => wp_create_nonce( 'nextsafar_upload_media' ),
                    'deleteNonce'       => wp_create_nonce( 'nextsafar_delete_media' ),
                    'bulkDeleteNonce'   => wp_create_nonce( 'nextsafar_bulk_delete_media' ),
                    'folderNonce'       => wp_create_nonce( 'nextsafar_folder_nonce' ),
                    'mediaDetailsNonce' => wp_create_nonce( 'nextsafar_media_details_nonce' ),
                    'dashboardNonce'    => wp_create_nonce( 'nextsafar_dashboard_nonce' ),
                )
            );
        }

        wp_enqueue_script(
            'nextsafar-media-modal-enhancer',
            NEXTSAFAR_URL . 'assets/js/media-modal-enhancer.js',
            array( 'jquery', 'media-editor', 'media-views', 'wp-util', 'nextsafar-admin' ),
            NEXTSAFAR_ADMIN_VERSION,
            true
        );

        wp_localize_script(
            'nextsafar-media-modal-enhancer',
            'nextsafarMediaModal',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'folderNonce'   => wp_create_nonce( 'nextsafar_folder_nonce' ),
                'modalNonce'    => wp_create_nonce( 'nextsafar_modal_media' ),
                'strings'       => array(
                    'folders'       => 'پوشه‌ها',
                    'allMedia'      => 'همه رسانه‌ها',
                    'uncategorized' => 'بدون پوشه',
                    'search'        => 'جستجو در پوشه‌ها...',
                    'mediaSearch'   => 'جستجو در رسانه‌ها...',
                    'loading'       => 'در حال بارگذاری...',
                    'noResults'     => 'رسانه‌ای یافت نشد',
                    'loadMore'      => 'بارگذاری بیشتر',
                    'selectMode'    => 'انتخاب',
                    'cancelSelect'  => 'انصراف',
                    'applySelection'=> 'تایید انتخاب',
                    'selectedCount' => 'انتخاب شده',
                    'imageDetails'  => 'جزئیات تصویر',
                    'close'         => 'بستن',
                    'save'          => 'ذخیره تغییرات',
                    'saving'        => 'در حال ذخیره...',
                    'saved'         => 'ذخیره شد',
                    'selectImage'   => 'انتخاب این تصویر',
                    'altText'       => 'متن جایگزین (Alt)',
                    'title'         => 'عنوان',
                    'caption'       => 'توضیح کوتاه',
                    'description'   => 'توضیحات',
                    'fileName'      => 'نام فایل',
                    'fileType'      => 'نوع فایل',
                    'fileSize'      => 'حجم فایل',
                    'dimensions'    => 'ابعاد',
                    'uploadDate'    => 'تاریخ آپلود',
                    'folder'        => 'پوشه',
                    'url'           => 'آدرس',
                ),
            )
        );
    }

    public function ajax_modal_media() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }

        check_ajax_referer( 'nextsafar_modal_media', 'nonce' );

        $folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
        $search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $paged     = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
        $per_page  = isset( $_POST['per_page'] ) ? min( 100, max( 20, absint( $_POST['per_page'] ) ) ) : 40;
        $mime_type = isset( $_POST['mime_type'] ) ? sanitize_text_field( wp_unslash( $_POST['mime_type'] ) ) : '';

        $query_args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $search ) ) {
            $query_args['s'] = $search;
        }

        if ( ! empty( $mime_type ) ) {
            if ( $mime_type === 'image' ) {
                $query_args['post_mime_type'] = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' );
            } elseif ( $mime_type === 'video' ) {
                $query_args['post_mime_type'] = array( 'video/mp4', 'video/webm', 'video/ogg' );
            } elseif ( $mime_type === 'audio' ) {
                $query_args['post_mime_type'] = array( 'audio/mpeg', 'audio/ogg', 'audio/wav' );
            } else {
                $query_args['post_mime_type'] = $mime_type;
            }
        }

        if ( -1 === $folder_id ) {
            $query_args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_nextsafar_folder_id', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_nextsafar_folder_id', 'value' => '0', 'compare' => '=' ),
                array( 'key' => '_nextsafar_folder_id', 'value' => '', 'compare' => '=' ),
            );
        } elseif ( $folder_id > 0 ) {
            $query_args['meta_query'] = array(
                array( 'key' => '_nextsafar_folder_id', 'value' => $folder_id ),
            );
        }

        $query = new WP_Query( $query_args );
        $attachments = array();

        foreach ( $query->posts as $post ) {
            $attachment = wp_prepare_attachment_for_js( $post );
            if ( $attachment ) {
                $attachments[] = array(
                    'id'        => $attachment['id'],
                    'title'     => $attachment['title'],
                    'filename'  => $attachment['filename'],
                    'mime'      => $attachment['mime'],
                    'type'      => $attachment['type'],
                    'url'       => $attachment['url'],
                    'thumbnail' => isset( $attachment['sizes']['medium'] ) ? $attachment['sizes']['medium']['url'] : 
                                  ( isset( $attachment['sizes']['thumbnail'] ) ? $attachment['sizes']['thumbnail']['url'] : $attachment['url'] ),
                    'full'      => isset( $attachment['sizes']['full'] ) ? $attachment['sizes']['full']['url'] : $attachment['url'],
                    'width'     => isset( $attachment['width'] ) ? $attachment['width'] : 0,
                    'height'    => isset( $attachment['height'] ) ? $attachment['height'] : 0,
                    'date'      => $attachment['dateFormatted'],
                );
            }
        }

        wp_send_json_success( array(
            'items'       => $attachments,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current'     => $paged,
        ) );
    }

    public function print_folders_data() {
        $screen = get_current_screen();
        if ( ! $screen ) return;

        $allowed_bases = array( 'post', 'edit-tags', 'term' );
        if ( ! in_array( $screen->base, $allowed_bases, true ) ) {
            return;
        }

        $data = $this->get_folders_data_cached();

        echo '<script id="nextsafar-folders-data" type="application/json">';
        echo wp_json_encode( $data );
        echo '</script>';
    }

    private function get_folders_data_cached() {
        if ( null !== self::$folders_cache ) {
            return self::$folders_cache;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'nextsafar_media_folders';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
        if ( ! $table_exists ) {
            self::$folders_cache = array(
                'folders'             => array(),
                'all_media_count'     => 0,
                'uncategorized_count' => 0,
            );
            return self::$folders_cache;
        }

        $folders = $wpdb->get_results(
            "SELECT id, name, parent_id, order_index FROM {$table_name} ORDER BY order_index ASC, name ASC"
        );

        $counts = $wpdb->get_results(
            "SELECT meta_value as folder_id, COUNT(*) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_nextsafar_folder_id' AND meta_value != '' AND meta_value != '0'
             GROUP BY meta_value"
        );

        $count_map = array();
        foreach ( $counts as $c ) {
            $count_map[ (int) $c->folder_id ] = (int) $c->count;
        }

        foreach ( $folders as $folder ) {
            $folder->media_count = isset( $count_map[ (int) $folder->id ] ) ? $count_map[ (int) $folder->id ] : 0;
        }

        $tree = $this->build_tree( $folders );

        $total_count = wp_count_attachments();
        $total       = 0;
        if ( is_object( $total_count ) ) {
            foreach ( $total_count as $num ) {
                $total += (int) $num;
            }
        }

        $folders_count = array_sum( $count_map );
        $uncategorized = max( 0, $total - $folders_count );

        self::$folders_cache = array(
            'folders'             => $tree,
            'all_media_count'     => $total,
            'uncategorized_count' => $uncategorized,
        );

        return self::$folders_cache;
    }

    private function build_tree( $folders, $parent_id = 0 ) {
        $tree = array();
        foreach ( $folders as $folder ) {
            if ( (int) $folder->parent_id === (int) $parent_id ) {
                $folder->children = $this->build_tree( $folders, $folder->id );
                $tree[]           = $folder;
            }
        }
        return $tree;
    }

    public function filter_attachments_by_folder( $args ) {
        if ( ! isset( $_REQUEST['query']['nextsafar_folder_id'] ) ) {
            return $args;
        }

        $folder_id = intval( $_REQUEST['query']['nextsafar_folder_id'] );

        if ( 0 === $folder_id ) return $args;

        if ( -1 === $folder_id ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_nextsafar_folder_id', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_nextsafar_folder_id', 'value' => '0', 'compare' => '=' ),
                array( 'key' => '_nextsafar_folder_id', 'value' => '', 'compare' => '=' ),
            );
            return $args;
        }

        if ( $folder_id > 0 ) {
            $existing_meta = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
            $folder_query = array( 'key' => '_nextsafar_folder_id', 'value' => $folder_id );
            
            if ( empty( $existing_meta ) ) {
                $args['meta_query'] = array( $folder_query );
            } else {
                $existing_meta[] = $folder_query;
                $args['meta_query'] = $existing_meta;
            }
        }

        return $args;
    }
}