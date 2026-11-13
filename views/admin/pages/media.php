<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ✅ بررسی دسترسی
if ( ! current_user_can( 'upload_files' ) ) {
    wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

$page_title = 'رسانه‌ها';

$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$folder_id      = isset( $_GET['folder'] ) ? absint( $_GET['folder'] ) : 0;
$is_uncategorized = isset( $_GET['folder'] ) && '-1' === $_GET['folder'];
$per_page       = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 50;

require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
?>

<div class="nextsafar-main">

    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>

    <main class="nextsafar-content">

        <!-- 🎯 Unified Media Toolbar: Search + Actions + Per Page -->
        <div class="nextsafar-panel nextsafar-media-unified-toolbar">
            <div class="nextsafar-media-toolbar-inner">
                <!-- Left: Search -->
                <div class="nextsafar-toolbar-filters">
                    <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="nextsafar-media-search-form" id="nextsafar-media-search-form">
                        <input type="hidden" name="page" value="nextsafar-media">
                        <input type="hidden" name="folder" value="<?php echo esc_attr( $folder_id ); ?>" id="nextsafar-hidden-folder-input">
                        
                        <div class="nextsafar-search">
                            <span class="nextsafar-search-icon"><?php NextSafar_Icons::display( 'search', 16 ); ?></span>
                            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" 
                                   placeholder="<?php echo esc_attr__( 'جستجو در رسانه‌ها...', 'nextsafar-admin' ); ?>" 
                                   class="nextsafar-search-input" id="nextsafar-search-input">
                        </div>

                        <button type="submit" class="nextsafar-secondary-button nextsafar-filter-btn">
                            <?php echo esc_html__( 'جستجو', 'nextsafar-admin' ); ?>
                        </button>

                        <?php if ( ! empty( $search ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-media' . ( $folder_id ? '&folder=' . $folder_id : '' ) ) ); ?>" 
                               class="nextsafar-clear-filters" title="<?php echo esc_attr__( 'پاک کردن جستجو', 'nextsafar-admin' ); ?>">
                                ✕
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Right: Actions -->
                <div class="nextsafar-toolbar-actions">
                    <label for="nextsafar-per-page" class="nextsafar-per-page-label">
                        <?php echo esc_html__( 'نمایش:', 'nextsafar-admin' ); ?>
                    </label>
                    <select id="nextsafar-per-page" class="nextsafar-filter nextsafar-per-page-select">
                        <?php
                        $options = array( 50, 100, 150, 200, 250, 300 );
                        foreach ( $options as $option ) :
                        ?>
                            <option value="<?php echo $option; ?>" <?php selected( $per_page, $option ); ?>>
                                <?php echo $option; ?> <?php echo esc_html__( 'تصویر', 'nextsafar-admin' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="button" class="nextsafar-secondary-button" id="nextsafar-select-mode">
                        <?php echo esc_html__( 'انتخاب', 'nextsafar-admin' ); ?>
                    </button>
                    <button type="button" class="nextsafar-primary-button" id="nextsafar-upload-media">
                        + <?php echo esc_html__( 'آپلود رسانه', 'nextsafar-admin' ); ?>
                    </button>
                    <input type="file" id="nextsafar-media-input" multiple hidden>
                </div>
            </div>
        </div>

        <!-- Media Layout -->
        <div class="nextsafar-media-layout">

            <!-- Folders Sidebar -->
            <aside class="nextsafar-folders-sidebar" id="nextsafar-folders-sidebar">

                <div class="nextsafar-folders-header">
                    <h3><?php echo esc_html__( 'پوشه‌ها', 'nextsafar-admin' ); ?></h3>
                    <button type="button" class="nextsafar-icon-button" id="nextsafar-create-folder-btn" title="<?php echo esc_attr__( 'ایجاد پوشه', 'nextsafar-admin' ); ?>">+</button>
                </div>

                <div class="nextsafar-folders-tree" id="nextsafar-folders-tree">

                    <div class="nextsafar-folder-item <?php echo 0 === $folder_id && ! $is_uncategorized ? 'active' : ''; ?>" data-folder-id="0" data-folder-type="all">
                        <span class="nextsafar-folder-toggle"></span>
                        <span class="nextsafar-folder-icon">▣</span>
                        <span class="nextsafar-folder-name"><?php echo esc_html__( 'همه رسانه‌ها', 'nextsafar-admin' ); ?></span>
                        <span class="nextsafar-folder-count" id="nextsafar-all-media-count">—</span>
                    </div>

                    <div class="nextsafar-folder-item <?php echo $is_uncategorized ? 'active' : ''; ?>" data-folder-id="-1" data-folder-type="uncategorized">
                        <span class="nextsafar-folder-toggle"></span>
                        <span class="nextsafar-folder-icon">▤</span>
                        <span class="nextsafar-folder-name"><?php echo esc_html__( 'بدون پوشه', 'nextsafar-admin' ); ?></span>
                        <span class="nextsafar-folder-count" id="nextsafar-uncategorized-count">—</span>
                    </div>

                    <div class="nextsafar-folders-divider"></div>

                    <div id="nextsafar-folders-list"></div>

                </div>

            </aside>

            <!-- Media Main -->
            <div class="nextsafar-media-main">

                <!-- Bulk Actions Toolbar -->
                <div class="nextsafar-bulk-media-toolbar" id="nextsafar-bulk-toolbar" style="display: none;">
                    <div class="nextsafar-bulk-media-info">
                        <span id="nextsafar-selected-count">0</span>
                        <span><?php echo esc_html__( 'انتخاب شده', 'nextsafar-admin' ); ?></span>
                    </div>
                    <div class="nextsafar-bulk-media-actions">
                        <button type="button" class="nextsafar-danger-button" id="nextsafar-bulk-delete">🗑 <?php echo esc_html__( 'حذف انتخاب شده‌ها', 'nextsafar-admin' ); ?></button>
                        <button type="button" class="nextsafar-secondary-button" id="nextsafar-cancel-selection"><?php echo esc_html__( 'انصراف', 'nextsafar-admin' ); ?></button>
                    </div>
                </div>

                <!-- Media Panel -->
                <div class="nextsafar-panel" id="nextsafar-media-panel">
                    <?php
                    $query_args = array(
                        'post_type'      => 'attachment',
                        'post_status'    => 'inherit',
                        'posts_per_page' => $per_page,
                        'paged'          => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
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
                    ?>

                    <div class="nextsafar-media-grid">

                        <?php if ( $media_query->have_posts() ) : ?>
                            <?php while ( $media_query->have_posts() ) : ?>
                                <?php
                                $media_query->the_post();
                                $attachment_id = get_the_ID();
                                $mime_type     = get_post_mime_type( $attachment_id );
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
                                        <button type="button" class="nextsafar-media-delete" data-media-id="<?php echo esc_attr( $attachment_id ); ?>" title="<?php echo esc_attr__( 'حذف', 'nextsafar-admin' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-icon lucide-trash"><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <div class="nextsafar-media-empty">
                                <strong><?php echo ! empty( $search ) ? esc_html__( 'نتیجه‌ای یافت نشد', 'nextsafar-admin' ) : esc_html__( 'رسانه‌ای موجود نیست', 'nextsafar-admin' ); ?></strong>
                                <span>
                                    <?php
                                    if ( ! empty( $search ) ) {
                                        echo esc_html( sprintf( __( 'رسانه‌ای با عبارت "%s" یافت نشد.', 'nextsafar-admin' ), $search ) );
                                    } else {
                                        echo esc_html__( 'اولین تصویر یا فایل خود را آپلود کنید.', 'nextsafar-admin' );
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php wp_reset_postdata(); ?>

                    <?php if ( $media_query->max_num_pages > 1 ) : ?>
                        <div class="nextsafar-pagination">
                            <?php
                            $pagination_args = array(
                                'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=nextsafar-media' ) ),
                                'current'   => max( 1, absint( $_GET['paged'] ?? 1 ) ),
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

                </div>

            </div>

        </div>

    </main>

</div>

<!-- Folder Modal -->
<div class="nextsafar-modal" id="nextsafar-folder-modal" style="display: none;">
    <div class="nextsafar-modal-backdrop"></div>
    <div class="nextsafar-modal-content">
        <div class="nextsafar-modal-header">
            <h3 id="nextsafar-folder-modal-title"><?php echo esc_html__( 'ایجاد پوشه', 'nextsafar-admin' ); ?></h3>
            <button type="button" class="nextsafar-modal-close" id="nextsafar-folder-modal-close">×</button>
        </div>
        <div class="nextsafar-modal-body">
            <input type="hidden" id="nextsafar-folder-edit-id" value="0">
            <input type="hidden" id="nextsafar-folder-parent-id" value="0">
            <div class="nextsafar-form-group">
                <label for="nextsafar-folder-name"><?php echo esc_html__( 'نام پوشه', 'nextsafar-admin' ); ?></label>
                <input type="text" id="nextsafar-folder-name" class="nextsafar-form-input" placeholder="<?php echo esc_attr__( 'نام پوشه را وارد کنید...', 'nextsafar-admin' ); ?>" required>
            </div>
            <div class="nextsafar-form-group">
                <label for="nextsafar-folder-parent"><?php echo esc_html__( 'پوشه والد', 'nextsafar-admin' ); ?></label>
                <select id="nextsafar-folder-parent" class="nextsafar-form-input">
                    <option value="0"><?php echo esc_html__( 'ریشه (بدون والد)', 'nextsafar-admin' ); ?></option>
                </select>
            </div>
        </div>
        <div class="nextsafar-modal-footer">
            <button type="button" class="nextsafar-secondary-button" id="nextsafar-folder-cancel"><?php echo esc_html__( 'انصراف', 'nextsafar-admin' ); ?></button>
            <button type="button" class="nextsafar-primary-button" id="nextsafar-folder-save"><?php echo esc_html__( 'ذخیره پوشه', 'nextsafar-admin' ); ?></button>
        </div>
    </div>
</div>

<!-- Media Details Modal -->
<div class="nextsafar-modal nextsafar-media-details-modal" id="nextsafar-media-details-modal" style="display: none;">
    <div class="nextsafar-modal-backdrop" id="nextsafar-media-details-backdrop"></div>
    <div class="nextsafar-modal-content nextsafar-media-details-content">
        
        <div class="nextsafar-media-details-header">
            <h3><?php echo esc_html__( 'جزئیات رسانه', 'nextsafar-admin' ); ?></h3>
            <button type="button" class="nextsafar-modal-close" id="nextsafar-media-details-close">×</button>
        </div>

        <div class="nextsafar-media-details-body">
            
            <div class="nextsafar-media-details-preview">
                <div id="nextsafar-media-details-image"></div>
            </div>

            <div class="nextsafar-media-details-info">
                
                <div class="nextsafar-media-details-form">
                    
                    <div class="nextsafar-form-group">
                        <label for="media-title"><?php echo esc_html__( 'عنوان', 'nextsafar-admin' ); ?></label>
                        <input type="text" id="media-title" class="nextsafar-form-input">
                    </div>

                    <div class="nextsafar-form-group">
                        <label for="media-alt-text"><?php echo esc_html__( 'متن جایگزین (Alt)', 'nextsafar-admin' ); ?></label>
                        <input type="text" id="media-alt-text" class="nextsafar-form-input">
                        <small><?php echo esc_html__( 'توضیح تصویر برای موتورهای جستجو و خواننده‌های صفحه.', 'nextsafar-admin' ); ?></small>
                    </div>

                    <div class="nextsafar-form-group">
                        <label for="media-caption"><?php echo esc_html__( 'توضیح کوتاه', 'nextsafar-admin' ); ?></label>
                        <textarea id="media-caption" class="nextsafar-form-textarea" rows="2"></textarea>
                    </div>

                    <div class="nextsafar-form-group">
                        <label for="media-description"><?php echo esc_html__( 'توضیحات', 'nextsafar-admin' ); ?></label>
                        <textarea id="media-description" class="nextsafar-form-textarea" rows="3"></textarea>
                    </div>

                    <div class="nextsafar-media-details-meta">
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'نام فایل:', 'nextsafar-admin' ); ?></strong>
                            <span id="media-file-name"></span>
                        </div>
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'نوع فایل:', 'nextsafar-admin' ); ?></strong>
                            <span id="media-file-type"></span>
                        </div>
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'حجم فایل:', 'nextsafar-admin' ); ?></strong>
                            <span id="media-file-size"></span>
                        </div>
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'ابعاد:', 'nextsafar-admin' ); ?></strong>
                            <span id="media-dimensions"></span>
                        </div>
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'تاریخ آپلود:', 'nextsafar-admin' ); ?></strong>
                            <span id="media-upload-date"></span>
                        </div>
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'مسیر پوشه:', 'nextsafar-admin' ); ?></strong>
                            <span id="media-folder"></span>
                        </div>
                        <div class="meta-row">
                            <strong><?php echo esc_html__( 'آدرس:', 'nextsafar-admin' ); ?></strong>
                            <input type="text" id="media-file-url" class="nextsafar-form-input" readonly onclick="this.select();">
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <div class="nextsafar-media-details-footer">
            <button type="button" class="nextsafar-secondary-button" id="nextsafar-media-details-cancel"><?php echo esc_html__( 'انصراف', 'nextsafar-admin' ); ?></button>
            <button type="button" class="nextsafar-primary-button" id="nextsafar-media-details-save"><?php echo esc_html__( 'ذخیره تغییرات', 'nextsafar-admin' ); ?></button>
        </div>

    </div>
</div>
<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';