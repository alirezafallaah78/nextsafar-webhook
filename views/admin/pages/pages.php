<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'edit_pages' ) ) {
    wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

$page_title = 'برگه‌ها';
require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';

?>
<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <main class="nextsafar-content">

        <?php
        global $content_manager;
        if ( isset( $content_manager ) && method_exists( $content_manager, 'display_bulk_notice' ) ) {
            $content_manager->display_bulk_notice();
        }

        $search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $status  = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';
        $paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

        $query_args = array(
            'post_type'      => 'page',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        );

        if ( ! empty( $search ) ) {
            $query_args['s'] = $search;
        }

        $allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'trash' );
        if ( ! empty( $status ) && in_array( $status, $allowed_statuses, true ) ) {
            $query_args['post_status'] = $status;
        } else {
            $query_args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
        }

        $pages_query = new WP_Query( $query_args );
        ?>
        <div class="nextsafar-panel nextsafar-posts-panel">

            <!-- 🎯 Unified Toolbar: Search + Filters + New Page Button -->
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="nextsafar-posts-toolbar nextsafar-unified-toolbar">
                <input type="hidden" name="page" value="nextsafar-pages">

                <!-- Left: Search + Filters -->
                <div class="nextsafar-toolbar-filters">
                    <div class="nextsafar-search">
                        <span class="nextsafar-search-icon">
                            <?php NextSafar_Icons::display( 'search', 16 ); ?>
                        </span>
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" 
                               placeholder="<?php echo esc_attr__( 'جستجو در برگه‌ها...', 'nextsafar-admin' ); ?>">
                    </div>

                    <select name="post_status" class="nextsafar-filter">
                        <option value=""><?php echo esc_html__( 'همه وضعیت‌ها', 'nextsafar-admin' ); ?></option>
                        <option value="publish" <?php selected( $status, 'publish' ); ?>><?php echo esc_html__( 'منتشر شده', 'nextsafar-admin' ); ?></option>
                        <option value="draft" <?php selected( $status, 'draft' ); ?>><?php echo esc_html__( 'پیش‌نویس', 'nextsafar-admin' ); ?></option>
                        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php echo esc_html__( 'در انتظار', 'nextsafar-admin' ); ?></option>
                        <option value="private" <?php selected( $status, 'private' ); ?>><?php echo esc_html__( 'خصوصی', 'nextsafar-admin' ); ?></option>
                        <option value="trash" <?php selected( $status, 'trash' ); ?>><?php echo esc_html__( 'زباله‌دان', 'nextsafar-admin' ); ?></option>
                    </select>

                    <button type="submit" class="nextsafar-secondary-button nextsafar-filter-btn">
                        <?php echo esc_html__( 'فیلتر', 'nextsafar-admin' ); ?>
                    </button>

                    <?php if ( ! empty( $search ) || ! empty( $status ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-pages' ) ); ?>" 
                           class="nextsafar-clear-filters" title="<?php echo esc_attr__( 'پاک کردن فیلترها', 'nextsafar-admin' ); ?>">
                            ✕
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Right: New Page Button -->
                <a href="<?php echo esc_url( nextsafar_editor_url( 'page' ) ); ?>" class="nextsafar-primary-button">
                    + <?php echo esc_html__( 'برگه جدید', 'nextsafar-admin' ); ?>
                </a>
            </form>

            <!-- Bulk Actions -->
            <form method="post" action="" id="nextsafar-bulk-form">
                <?php wp_nonce_field( 'nextsafar_bulk_pages' ); ?>
                <div class="nextsafar-bulk-toolbar">
                    <select name="nextsafar_bulk_action" class="nextsafar-filter">
                        <option value=""><?php echo esc_html__( 'عملیات گروهی', 'nextsafar-admin' ); ?></option>
                        <option value="trash"><?php echo esc_html__( 'انتقال به زباله‌دان', 'nextsafar-admin' ); ?></option>
                        <option value="restore"><?php echo esc_html__( 'بازیابی', 'nextsafar-admin' ); ?></option>
                        <option value="delete"><?php echo esc_html__( 'حذف دائمی', 'nextsafar-admin' ); ?></option>
                    </select>
                    <button type="submit" class="nextsafar-primary-button"><?php echo esc_html__( 'اعمال', 'nextsafar-admin' ); ?></button>
                </div>

                <div class="nextsafar-table-wrapper">
                    <table class="nextsafar-table">
                        <thead>
                            <tr>
                                <th class="nextsafar-checkbox-column">
                                    <input type="checkbox" id="nextsafar-select-all">
                                </th>
                                <th><?php echo esc_html__( 'عنوان', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'نویسنده', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'قالب', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'وضعیت', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'تاریخ', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'عملیات', 'nextsafar-admin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( $pages_query->have_posts() ) : ?>
                                <?php while ( $pages_query->have_posts() ) : ?>
                                    <?php
                                    $pages_query->the_post();
                                    $page_id      = get_the_ID();
                                    $page_status  = get_post_status( $page_id );
                                    $template     = get_page_template_slug( $page_id );
                                    $template     = $template ? basename( $template, '.php' ) : 'پیش‌فرض';
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="page_ids[]" value="<?php echo esc_attr( $page_id ); ?>" class="nextsafar-post-checkbox">
                                        </td>
                                        <td>
                                            <div class="nextsafar-post-title">
                                                <div>
                                                    <a href="<?php echo esc_url( nextsafar_editor_url( 'page', $page_id ) ); ?>" class="nextsafar-post-name">
                                                        <?php the_title(); ?>
                                                    </a>
                                                    <span class="nextsafar-post-id">#<?php echo esc_html( $page_id ); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html( get_the_author() ); ?></td>
                                        <td><span class="nextsafar-muted"><?php echo esc_html( $template ); ?></span></td>
                                        <td>
                                            <?php
                                            $status_labels = array(
                                                'publish' => __( 'منتشر شده', 'nextsafar-admin' ),
                                                'draft'   => __( 'پیش‌نویس', 'nextsafar-admin' ),
                                                'pending' => __( 'در انتظار', 'nextsafar-admin' ),
                                                'private' => __( 'خصوصی', 'nextsafar-admin' ),
                                                'trash'   => __( 'زباله‌دان', 'nextsafar-admin' ),
                                            );
                                            $status_label = $status_labels[ $page_status ] ?? ucfirst( $page_status );
                                            ?>
                                            <span class="nextsafar-status nextsafar-status-<?php echo esc_attr( $page_status ); ?>">
                                                <?php echo esc_html( $status_label ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="nextsafar-date">
                                                <strong><?php echo esc_html( NextSafar_Helpers::gregorian_to_jalali_str( get_the_date( 'Y-m-d' ) ) ); ?></strong>
                                                <span><?php echo esc_html( get_the_time( 'H:i' ) ); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="nextsafar-actions">
                                                <a href="<?php echo esc_url( nextsafar_editor_url( 'page', $page_id ) ); ?>" title="<?php echo esc_attr__( 'ویرایش', 'nextsafar-admin' ); ?>"><?php NextSafar_Icons::display( 'square-pen', 18 ); ?></a>
                                                <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'مشاهده', 'nextsafar-admin' ); ?>"><?php NextSafar_Icons::display( 'eye', 18 ); ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7" class="nextsafar-table-empty">
                                        <div>
                                            <strong><?php echo esc_html__( 'برگه‌ای یافت نشد', 'nextsafar-admin' ); ?></strong>
                                            <span><?php echo esc_html__( 'اولین برگه خود را ایجاد کنید.', 'nextsafar-admin' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php wp_reset_postdata(); ?>

            <?php if ( $pages_query->max_num_pages > 1 ) : ?>
            <div class="nextsafar-pagination">
                <?php
                echo paginate_links(
                    array(
                        'base'      => add_query_arg( array( 'paged' => '%#%', 'page' => 'nextsafar-pages' ), admin_url( 'admin.php' ) ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $pages_query->max_num_pages,
                        'prev_text' => '←',
                        'next_text' => '→',
                        'add_args'  => array_filter(
                            array(
                                's'           => $search,
                                'post_status' => $status,
                            )
                        ),
                    )
                );
                ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';