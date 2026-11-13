<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Media_Folders {
    
    private $table_name;
    private static $request_cache = array();
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nextsafar_media_folders';
    }
    
    public function init() {
        add_action( 'wp_ajax_nextsafar_create_folder', array( $this, 'ajax_create_folder' ) );
        add_action( 'wp_ajax_nextsafar_update_folder', array( $this, 'ajax_update_folder' ) );
        add_action( 'wp_ajax_nextsafar_delete_folder', array( $this, 'ajax_delete_folder' ) );
        add_action( 'wp_ajax_nextsafar_get_folders', array( $this, 'ajax_get_folders' ) );
        add_action( 'wp_ajax_nextsafar_move_media', array( $this, 'ajax_move_media' ) );
        add_action( 'wp_ajax_nextsafar_load_media_grid', array( $this, 'ajax_load_media_grid' ) );
        add_action( 'wp_ajax_nextsafar_get_media_details', array( $this, 'ajax_get_media_details' ) );
        add_action( 'wp_ajax_nextsafar_update_media_details', array( $this, 'ajax_update_media_details' ) );
        add_action( 'wp_ajax_nextsafar_upload_media', array( $this, 'ajax_upload_media' ) );
        add_action( 'wp_ajax_nextsafar_delete_media', array( $this, 'ajax_delete_media' ) );
        add_action( 'wp_ajax_nextsafar_bulk_delete_media', array( $this, 'ajax_bulk_delete_media' ) );
    }
    
    /* =========================================================
    📦 کش Transient برای Count ها
    ========================================================== */
    
    private function get_cache_key( $type ) {
        return 'nextsafar_count_' . $type;
    }
    
    private function invalidate_count_cache() {
        delete_transient( $this->get_cache_key( 'all_media' ) );
        delete_transient( $this->get_cache_key( 'uncategorized' ) );
        delete_transient( $this->get_cache_key( 'folders_tree' ) );
        self::$request_cache = array();
    }
    
    /* =========================================================
    🚀 متدهای بهینه‌شده
    ========================================================== */
    
    private function get_all_folders_flat() {
        if ( isset( self::$request_cache['all_folders'] ) ) {
            return self::$request_cache['all_folders'];
        }
        
        global $wpdb;
        $folders = $wpdb->get_results(
            "SELECT id, name, parent_id, order_index 
             FROM {$this->table_name} 
             ORDER BY order_index ASC, name ASC"
        );
        
        if ( ! is_array( $folders ) ) {
            $folders = array();
        }
        
        $indexed = array();
        foreach ( $folders as $folder ) {
            $folder->children = array();
            $indexed[ $folder->id ] = $folder;
        }
        
        self::$request_cache['all_folders'] = $indexed;
        return $indexed;
    }
    
    public function get_folders_tree( $parent_id = 0, $depth = 0 ) {
        $all_folders = $this->get_all_folders_flat();
        return $this->build_tree_from_flat( $all_folders, $parent_id, $depth );
    }
    
    private function build_tree_from_flat( $indexed_folders, $parent_id, $depth ) {
        $tree = array();
        foreach ( $indexed_folders as $folder ) {
            if ( (int) $folder->parent_id === (int) $parent_id ) {
                $folder->depth = $depth;
                $folder->children = $this->build_tree_from_flat( $indexed_folders, $folder->id, $depth + 1 );
                $tree[] = $folder;
            }
        }
        return $tree;
    }
    
    public function get_folder( $folder_id ) {
        $all_folders = $this->get_all_folders_flat();
        return isset( $all_folders[ $folder_id ] ) ? $all_folders[ $folder_id ] : null;
    }
    
    private function get_all_children( $parent_id ) {
        $all_folders = $this->get_all_folders_flat();
        $children = array();
        $this->collect_children_recursive( $all_folders, $parent_id, $children );
        return $children;
    }
    
    private function collect_children_recursive( $indexed_folders, $parent_id, &$children ) {
        foreach ( $indexed_folders as $folder ) {
            if ( (int) $folder->parent_id === (int) $parent_id ) {
                $children[] = $folder->id;
                $this->collect_children_recursive( $indexed_folders, $folder->id, $children );
            }
        }
    }
    
    /**
     * ✅ بهینه‌شده: گرفتن پوشه‌ها با count در یک کوئری
     */
    public function get_folders_with_count( $parent_id = 0, $depth = 0 ) {
        global $wpdb;
        
        $folders = $wpdb->get_results(
            "SELECT 
                f.id, f.name, f.parent_id, f.order_index,
                COALESCE(pm.media_count, 0) as media_count
             FROM {$this->table_name} f
             LEFT JOIN (
                SELECT meta_value, COUNT(*) as media_count
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_nextsafar_folder_id'
                GROUP BY meta_value
             ) pm ON pm.meta_value = f.id
             ORDER BY f.order_index ASC, f.name ASC"
        );
        
        if ( empty( $folders ) ) {
            return array();
        }
        
        $indexed = array();
        foreach ( $folders as $folder ) {
            $folder->children = array();
            $folder->media_count = (int) $folder->media_count;
            $indexed[ $folder->id ] = $folder;
        }
        
        return $this->build_tree_with_count( $indexed, $parent_id, $depth );
    }
    
    /**
     * ✅ بهینه‌شده: ساخت درخت با یک پاس — O(n)
     */
    private function build_tree_with_count( $indexed_folders, $parent_id = 0 ) {
        $tree = array();
        
        // ۱. مقداردهی اولیه
        foreach ( $indexed_folders as $id => $folder ) {
            $indexed_folders[ $id ]->children = array();
        }
        
        // ۲. اتصال فرزندان به والدین در یک پاس
        foreach ( $indexed_folders as $id => $folder ) {
            $pid = (int) $folder->parent_id;
            if ( $pid === 0 || ! isset( $indexed_folders[ $pid ] ) ) {
                $tree[] = $folder;
            } else {
                $indexed_folders[ $pid ]->children[] = $folder;
            }
        }
        
        // ۳. محاسبه عمق و مرتب‌سازی
        $this->set_depths( $tree, 0 );
        return $tree;
    }

    private function set_depths( &$tree, $depth ) {
        foreach ( $tree as &$folder ) {
            $folder->depth = $depth;
            $folder->has_children = ! empty( $folder->children );
            if ( $folder->has_children ) {
                $this->set_depths( $folder->children, $depth + 1 );
            }
        }
    }
    
    /**
     * ✅ بهینه‌شده: با کش Transient
     */
    private function get_all_media_count() {
        $cache_key = $this->get_cache_key( 'all_media' );
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return (int) $cached;
        }
        
        $count = wp_count_attachments();
        $total = 0;
        
        if ( is_object( $count ) ) {
            foreach ( $count as $num ) {
                $total += (int) $num;
            }
        }
        
        set_transient( $cache_key, $total, 5 * MINUTE_IN_SECONDS );
        return $total;
    }
    
    /**
     * ✅ بهینه‌شده: با کش Transient
     */
    private function get_uncategorized_count() {
        $cache_key = $this->get_cache_key( 'uncategorized' );
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return (int) $cached;
        }
        
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) 
             FROM {$wpdb->posts} p
             WHERE p.post_type = 'attachment'
             AND p.post_status = 'inherit'
             AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                AND pm.meta_key = '_nextsafar_folder_id'
                AND pm.meta_value != '0'
                AND pm.meta_value != ''
             )"
        );
        
        $count = (int) $count;
        set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );
        return $count;
    }
    
    /* =========================================================
    ✏️ متدهای Write با invalidate خودکار کش
    ========================================================== */
    
    public function create_folder( $name, $parent_id = 0 ) {
        global $wpdb;
        
        $all_folders = $this->get_all_folders_flat();
        $max_order = -1;
        
        foreach ( $all_folders as $folder ) {
            if ( (int) $folder->parent_id === (int) $parent_id ) {
                if ( (int) $folder->order_index > $max_order ) {
                    $max_order = (int) $folder->order_index;
                }
            }
        }
        
        $order_index = $max_order + 1;
        
        $wpdb->insert(
            $this->table_name,
            array(
                'name'        => sanitize_text_field( $name ),
                'parent_id'   => absint( $parent_id ),
                'order_index' => $order_index,
            ),
            array( '%s', '%d', '%d' )
        );
        
        $new_id = $wpdb->insert_id;
        $this->invalidate_count_cache();
        return $new_id;
    }
    
    public function update_folder( $folder_id, $name ) {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            array( 'name' => sanitize_text_field( $name ) ),
            array( 'id' => absint( $folder_id ) ),
            array( '%s' ),
            array( '%d' )
        );
        
        $this->invalidate_count_cache();
        return true;
    }
    
    public function delete_folder( $folder_id ) {
        global $wpdb;
        
        $children = $this->get_all_children( $folder_id );
        $children[] = $folder_id;
        
        foreach ( $children as $child_id ) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_nextsafar_folder_id' 
                     AND meta_value = %d",
                    $child_id
                )
            );
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $children ), '%d' ) );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
                $children
            )
        );
        
        $this->invalidate_count_cache();
        return true;
    }
    
    /* =========================================================
    📥 AJAX Handlers (همان کد قبلی)
    ========================================================== */
    
    public function ajax_load_media_grid() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        $folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $paged = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? min( 300, max( 50, absint( $_POST['per_page'] ) ) ) : 50;
        $is_uncategorized = ( -1 === $folder_id );
        
        $query_args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
        );
        
        if ( ! empty( $search ) ) {
            $query_args['s'] = $search;
        }
        
        if ( $is_uncategorized ) {
            $query_args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_nextsafar_folder_id', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_nextsafar_folder_id', 'value' => 0, 'compare' => '=' ),
            );
        } elseif ( $folder_id > 0 ) {
            $query_args['meta_query'] = array(
                array( 'key' => '_nextsafar_folder_id', 'value' => $folder_id ),
            );
        }
        
        $media_query = new WP_Query( $query_args );
        
        ob_start();
        ?>
        <div class="nextsafar-media-grid">
        <?php if ( $media_query->have_posts() ) : ?>
            <?php while ( $media_query->have_posts() ) : ?>
                <?php
                $media_query->the_post();
                $attachment_id = get_the_ID();
                $mime_type = get_post_mime_type( $attachment_id );
                ?>
                <div class="nextsafar-media-card" data-media-id="<?php echo esc_attr( $attachment_id ); ?>">
                    <div class="nextsafar-media-select-checkbox">
                        <input type="checkbox" class="nextsafar-media-checkbox" data-media-id="<?php echo esc_attr( $attachment_id ); ?>">
                    </div>
                    <div class="nextsafar-media-preview">
                        <?php if ( wp_attachment_is_image( $attachment_id ) ) : ?>
                            <?php echo wp_get_attachment_image( $attachment_id, 'medium' ); ?>
                        <?php else : ?>
                            <div class="nextsafar-media-file-icon">▣</div>
                        <?php endif; ?>
                    </div>
                    <div class="nextsafar-media-info">
                        <strong><?php echo esc_html( get_the_title() ); ?></strong>
                        <span><?php echo esc_html( $mime_type ); ?></span>
                    </div>
                    <div class="nextsafar-media-actions">
                        <button type="button" class="nextsafar-media-delete" data-media-id="<?php echo esc_attr( $attachment_id ); ?>" title="Delete">🗑</button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else : ?>
            <div class="nextsafar-media-empty">
                <strong><?php echo ! empty( $search ) ? 'No results found' : 'No media files'; ?></strong>
                <span><?php echo ! empty( $search ) ? 'No media files match your search: "' . esc_html( $search ) . '"' : 'Upload your first image or file.'; ?></span>
            </div>
        <?php endif; ?>
        </div>
        <?php wp_reset_postdata(); ?>
        
        <?php if ( $media_query->max_num_pages > 1 ) : ?>
            <div class="nextsafar-pagination">
                <?php
                $pagination_args = array(
                    'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=nextsafar-media' ) ),
                    'current'   => $paged,
                    'total'     => $media_query->max_num_pages,
                    'prev_text' => '←',
                    'next_text' => '→',
                );
                $add_args = array();
                if ( ! empty( $search ) ) $add_args['s'] = $search;
                if ( $folder_id ) $add_args['folder'] = $folder_id;
                if ( $per_page !== 50 ) $add_args['per_page'] = $per_page;
                if ( ! empty( $add_args ) ) $pagination_args['add_args'] = $add_args;
                echo paginate_links( $pagination_args );
                ?>
            </div>
        <?php endif; ?>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success( array(
            'html'        => $html,
            'total_pages' => $media_query->max_num_pages,
            'found_posts' => $media_query->found_posts,
            'per_page'    => $per_page,
        ) );
    }
    
    public function ajax_move_media() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_folder_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $media_ids = isset( $_POST['media_ids'] ) ? array_map( 'absint', (array) $_POST['media_ids'] ) : array();
        $folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
        
        if ( empty( $media_ids ) ) {
            wp_send_json_error( array( 'message' => 'No media selected.' ), 400 );
        }
        
        if ( $folder_id > 0 ) {
            $folder = $this->get_folder( $folder_id );
            if ( ! $folder ) {
                wp_send_json_error( array( 'message' => 'Folder not found.' ), 404 );
            }
        }
        
        $moved = array();
        $failed = array();
        
        foreach ( $media_ids as $media_id ) {
            if ( $folder_id > 0 ) {
                $result = update_post_meta( $media_id, '_nextsafar_folder_id', $folder_id );
            } else {
                $result = delete_post_meta( $media_id, '_nextsafar_folder_id' );
            }
            
            if ( $result ) {
                $moved[] = $media_id;
            } else {
                $failed[] = $media_id;
            }
        }
        
        if ( ! empty( $moved ) ) {
            $this->invalidate_count_cache();
        }
        
        if ( empty( $moved ) ) {
            wp_send_json_error( array( 'message' => 'Failed to move any media.' ), 500 );
        }
        
        wp_send_json_success( array(
            'moved'   => $moved,
            'failed'  => $failed,
            'message' => count( $moved ) . ' media file(s) moved successfully.',
        ) );
    }
    
    public function ajax_create_folder() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_folder_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;
        
        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Folder name is required.' ), 400 );
        }
        
        $folder_id = $this->create_folder( $name, $parent_id );
        
        if ( ! $folder_id ) {
            wp_send_json_error( array( 'message' => 'Failed to create folder.' ), 500 );
        }
        
        $folder = $this->get_folder( $folder_id );
        wp_send_json_success( array( 'folder' => $folder, 'message' => 'Folder created successfully.' ) );
        // در انتهای هر متد
        $this->invalidate_folders_cache();
    }
    
    public function ajax_update_folder() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_folder_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        
        if ( ! $folder_id || empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ), 400 );
        }
        
        $this->update_folder( $folder_id, $name );
        wp_send_json_success( array( 'message' => 'Folder updated successfully.' ) );
        // در انتهای هر متد
        $this->invalidate_folders_cache();
    }
    
    public function ajax_delete_folder() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_folder_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
        
        if ( ! $folder_id ) {
            wp_send_json_error( array( 'message' => 'Invalid folder ID.' ), 400 );
        }
        
        $this->delete_folder( $folder_id );
        wp_send_json_success( array( 'message' => 'Folder deleted successfully.' ) );
        // در انتهای هر متد
        $this->invalidate_folders_cache();
    }
    
    public function ajax_get_folders() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        $tree = $this->get_folders_with_count();
        $all_media_count = $this->get_all_media_count();
        $uncategorized_count = $this->get_uncategorized_count();
        
        wp_send_json_success( array(
            'folders'             => $tree,
            'all_media_count'     => $all_media_count,
            'uncategorized_count' => $uncategorized_count,
        ) );
    }
    
    public function get_folder_path($folder_id, $separator = ' / ') {
        $folder_id = absint($folder_id);

        if (!$folder_id) {
            return '';
        }

        $all_folders = $this->get_all_folders_flat();

        $path = [];
        $visited = [];
        $current_id = $folder_id;

        while ($current_id && count($path) < 20 && empty($visited[$current_id])) {
            $visited[$current_id] = true;

            if (!isset($all_folders[$current_id])) {
                break;
            }

            $folder = $all_folders[$current_id];

            array_unshift($path, $folder->name);

            $current_id = absint($folder->parent_id);
        }

        $path = array_map('sanitize_text_field', $path);

        return implode($separator, $path);
    }

    public function ajax_get_media_details() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        $media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
        
        if ( ! $media_id ) {
            wp_send_json_error( array( 'message' => 'Invalid media ID.' ), 400 );
        }
        
        $attachment = get_post( $media_id );
        
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            wp_send_json_error( array( 'message' => 'Media not found.' ), 404 );
        }
        
        $alt_text = get_post_meta( $media_id, '_wp_attachment_image_alt', true );
        $file_url = wp_get_attachment_url( $media_id );
        $file_path = get_attached_file( $media_id );
        $metadata = wp_get_attachment_metadata( $media_id );
        $folder_id = get_post_meta( $media_id, '_nextsafar_folder_id', true );
        $folder_name = 'Uncategorized';
        $folder_path = '';

        if ($folder_id > 0) {
            $folder_path = $this->get_folder_path($folder_id);

            if (!empty($folder_path)) {
                $folder_name = $folder_path;
            } else {
                $folder = $this->get_folder($folder_id);

                if ($folder) {
                    $folder_name = $folder->name;
                }
            }
        }
        $file_size = file_exists( $file_path ) ? size_format( filesize( $file_path ) ) : 'Unknown';
        $dimensions = '';
        
        if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
            $dimensions = $metadata['width'] . ' × ' . $metadata['height'] . ' pixels';
        }
        
        $data = array(
            'id'           => $media_id,
            'title'        => $attachment->post_title,
            'alt_text'     => $alt_text,
            'caption'      => $attachment->post_excerpt,
            'description'  => $attachment->post_content,
            'file_url'     => $file_url,
            'file_name'    => basename($file_path),
            'file_type'    => get_post_mime_type($media_id),
            'file_size'    => $file_size,
            'dimensions'   => $dimensions,
            'upload_date'  => get_the_date('F j, Y', $media_id) . ' at ' . get_the_time('g:i a', $media_id),
            'folder_id'    => $folder_id ? $folder_id : 0,
            'folder_name'  => $folder_name,
            'folder_path'  => $folder_path,
            'preview_html' => wp_get_attachment_image($media_id, 'large'),
        );
        wp_send_json_success( $data );
    }
    
    public function ajax_update_media_details() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_media_details_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $alt_text = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';
        $caption = isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        
        if ( ! $media_id ) {
            wp_send_json_error( array( 'message' => 'Invalid media ID.' ), 400 );
        }
        
        $post_data = array(
            'ID'           => $media_id,
            'post_title'   => $title,
            'post_excerpt' => $caption,
            'post_content' => $description,
        );
        
        $result = wp_update_post( $post_data, true );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => 'Failed to update media.' ), 500 );
        }
        
        update_post_meta( $media_id, '_wp_attachment_image_alt', $alt_text );
        wp_send_json_success( array( 'message' => 'Media updated successfully.' ) );
    }
    
    public function ajax_upload_media() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_upload_media' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ), 400 );
        }
        
        if ( UPLOAD_ERR_OK !== (int) $_FILES['file']['error'] ) {
            wp_send_json_error( array( 'message' => 'Upload error: ' . $_FILES['file']['error'] ), 500 );
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        $attachment_id = media_handle_upload( 'file', 0 );
        
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 500 );
        }
        
        if ( isset( $_POST['folder_id'] ) && absint( $_POST['folder_id'] ) > 0 ) {
            update_post_meta( $attachment_id, '_nextsafar_folder_id', absint( $_POST['folder_id'] ) );
        }
        
        wp_send_json_success( array(
            'id'  => $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ),
        ) );
    }
    
    public function ajax_delete_media() {
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_delete_media' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
        
        if ( ! $media_id ) {
            wp_send_json_error( array( 'message' => 'Invalid media ID.' ), 400 );
        }
        
        $result = wp_delete_attachment( $media_id, true );
        
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Failed to delete.' ), 500 );
        }
        
        $this->invalidate_count_cache();
        wp_send_json_success( array( 'message' => 'Deleted successfully.' ) );
    }
    
    public function ajax_bulk_delete_media() {
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_bulk_delete_media' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        
        $media_ids = isset( $_POST['media_ids'] ) ? array_map( 'absint', (array) $_POST['media_ids'] ) : array();
        
        if ( empty( $media_ids ) ) {
            wp_send_json_error( array( 'message' => 'No media selected.' ), 400 );
        }
        
        $deleted = array();
        $failed = array();
        
        foreach ( $media_ids as $media_id ) {
            $result = wp_delete_attachment( $media_id, true );
            if ( $result ) {
                $deleted[] = $media_id;
            } else {
                $failed[] = $media_id;
            }
        }
        
        if ( empty( $deleted ) ) {
            wp_send_json_error( array( 'message' => 'Failed to delete any media.' ), 500 );
        }
        
        $this->invalidate_count_cache();
        wp_send_json_success( array(
            'deleted' => $deleted,
            'failed'  => $failed,
            'message' => count( $deleted ) . ' file(s) deleted.',
        ) );
    }

    /**
     * ✅ کش Transient برای پوشه‌ها — بین ریکوئست‌ها
     */
    private function get_folders_cache_key() {
        return 'nextsafar_folders_tree';
    }

    public function invalidate_folders_cache() {
        delete_transient( $this->get_folders_cache_key() );
        self::$folders_cache = null;
        self::$request_cache = array();
    }

    private function get_folders_with_cache( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( $this->get_folders_cache_key() );
            if ( false !== $cached ) {
                return $cached;
            }
        }
        
        $result = $this->get_folders_raw(); // کوئری اصلی
        
        set_transient( $this->get_folders_cache_key(), $result, HOUR_IN_SECONDS );
        
        return $result;
    }
}