<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

$page_title = 'نوشته‌ها';

// 🚀 دریافت داده‌ها از Controller
$posts_query = $data['query'];
$search      = $data['search'];
$status      = $data['status'];
$category    = $data['category'];
$paged       = $data['paged'];

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
        ?>

        <div class="nextsafar-panel nextsafar-posts-panel">

            <!-- Unified Toolbar: Search + Filters + New Post Button -->
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="nextsafar-posts-toolbar nextsafar-unified-toolbar">
                <input type="hidden" name="page" value="nextsafar-posts">

                <!-- Left: Search + Filters -->
                <div class="nextsafar-toolbar-filters">
                    <div class="nextsafar-search">
                        <span class="nextsafar-search-icon">
                            <?php NextSafar_Icons::display( 'search', 16 ); ?>
                        </span>
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" 
                               placeholder="<?php echo esc_attr__( 'جستجو در نوشته‌ها...', 'nextsafar-admin' ); ?>">
                    </div>

                    <select name="post_status" class="nextsafar-filter">
                        <option value=""><?php echo esc_html__( 'همه وضعیت‌ها', 'nextsafar-admin' ); ?></option>
                        <option value="publish" <?php selected( $status, 'publish' ); ?>><?php echo esc_html__( 'منتشر شده', 'nextsafar-admin' ); ?></option>
                        <option value="draft" <?php selected( $status, 'draft' ); ?>><?php echo esc_html__( 'پیش‌نویس', 'nextsafar-admin' ); ?></option>
                        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php echo esc_html__( 'در انتظار', 'nextsafar-admin' ); ?></option>
                        <option value="private" <?php selected( $status, 'private' ); ?>><?php echo esc_html__( 'خصوصی', 'nextsafar-admin' ); ?></option>
                        <option value="trash" <?php selected( $status, 'trash' ); ?>><?php echo esc_html__( 'زباله‌دان', 'nextsafar-admin' ); ?></option>
                    </select>

                    <select name="category" class="nextsafar-filter">
                        <option value=""><?php echo esc_html__( 'همه دسته‌ها', 'nextsafar-admin' ); ?></option>
                        <?php
                        $categories = get_categories( array( 'hide_empty' => false ) );
                        foreach ( $categories as $cat ) :
                        ?>
                            <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $category, $cat->term_id ); ?>>
                                <?php echo esc_html( $cat->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="nextsafar-secondary-button nextsafar-filter-btn">
                        <?php echo esc_html__( 'فیلتر', 'nextsafar-admin' ); ?>
                    </button>

                    <?php if ( ! empty( $search ) || ! empty( $status ) || ! empty( $category ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-posts' ) ); ?>" 
                           class="nextsafar-clear-filters" title="<?php echo esc_attr__( 'پاک کردن فیلترها', 'nextsafar-admin' ); ?>">
                            ✕
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Right: New Post Button -->
                <a href="<?php echo esc_url( nextsafar_editor_url( 'post' ) ); ?>" class="nextsafar-primary-button">
                    + <?php echo esc_html__( 'نوشته جدید', 'nextsafar-admin' ); ?>
                </a>
            </form>

            <!-- Bulk Actions -->
            <form method="post" action="" id="nextsafar-bulk-form">
                <?php wp_nonce_field( 'nextsafar_bulk_posts' ); ?>
                <div class="nextsafar-bulk-toolbar">
                    <select name="nextsafar_bulk_action" class="nextsafar-filter">
                        <option value=""><?php echo esc_html__( 'عملیات گروهی', 'nextsafar-admin' ); ?></option>
                        <option value="trash"><?php echo esc_html__( 'انتقال به زباله‌دان', 'nextsafar-admin' ); ?></option>
                        <option value="restore"><?php echo esc_html__( 'بازیابی', 'nextsafar-admin' ); ?></option>
                        <option value="delete"><?php echo esc_html__( 'حذف دائمی', 'nextsafar-admin' ); ?></option>
                    </select>
                    <button type="submit" class="nextsafar-primary-button"><?php echo esc_html__( 'اعمال', 'nextsafar-admin' ); ?></button>
                </div>

                <!-- Table -->
                <div class="nextsafar-table-wrapper">
                    <table class="nextsafar-table">
                        <thead>
                            <tr>
                                <th class="nextsafar-checkbox-column">
                                    <input type="checkbox" id="nextsafar-select-all">
                                </th>
                                <th><?php echo esc_html__( 'عنوان', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'نویسنده', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'دسته', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'وضعیت', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'تاریخ', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'عملیات', 'nextsafar-admin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( $posts_query->have_posts() ) : ?>
                                <?php while ( $posts_query->have_posts() ) : ?>
                                    <?php
                                    $posts_query->the_post();
                                    $post_id           = get_the_ID();
                                    $post_categories   = get_the_category();
                                    $post_status       = get_post_status( $post_id );
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $post_id ); ?>" class="nextsafar-post-checkbox">
                                        </td>
                                        <td>
                                            <div class="nextsafar-post-title">
                                                <?php if ( has_post_thumbnail() ) : ?>
                                                    <div class="nextsafar-post-thumbnail">
                                                        <?php echo get_the_post_thumbnail( $post_id, array( 48, 36 ) ); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="<?php echo esc_url( nextsafar_editor_url( 'post', $post_id ) ); ?>" class="nextsafar-post-name">
                                                        <?php the_title(); ?>
                                                    </a>
                                                    <span class="nextsafar-post-id">#<?php echo esc_html( $post_id ); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html( get_the_author() ); ?></td>
                                        <td>
                                            <?php if ( ! empty( $post_categories ) ) : ?>
                                                <div class="nextsafar-category-list">
                                                    <?php foreach ( $post_categories as $cat ) : ?>
                                                        <span><?php echo esc_html( $cat->name ); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="nextsafar-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_labels = array(
                                                'publish' => 'منتشر شده',
                                                'draft'   => 'پیش‌نویس',
                                                'pending' => 'در انتظار',
                                                'private' => 'خصوصی',
                                                'trash'   => 'زباله‌دان',
                                            );
                                            $status_label = $status_labels[ $post_status ] ?? ucfirst( $post_status );
                                            ?>
                                            <span class="nextsafar-status nextsafar-status-<?php echo esc_attr( $post_status ); ?>">
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
                                                <a href="<?php echo esc_url( nextsafar_editor_url( 'post', $post_id ) ); ?>" title="<?php echo esc_attr__( 'ویرایش', 'nextsafar-admin' ); ?>"><?php NextSafar_Icons::display( 'square-pen', 18 ); ?></a>
                                                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'مشاهده', 'nextsafar-admin' ); ?>"><?php NextSafar_Icons::display( 'eye', 18 ); ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7" class="nextsafar-table-empty">
                                        <div>
                                            <strong><?php echo esc_html__( 'نوشته‌ای یافت نشد', 'nextsafar-admin' ); ?></strong>
                                            <span><?php echo esc_html__( 'اولین نوشته خود را ایجاد کنید.', 'nextsafar-admin' ); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php wp_reset_postdata(); ?>

            <!-- Pagination -->
            <?php if ( $posts_query->max_num_pages > 1 ) : ?>
                <div class="nextsafar-pagination">
                    <?php
                    echo paginate_links(
                        array(
                            'base'     => add_query_arg(
                                array( 'paged' => '%#%', 'page' => 'nextsafar-posts' ),
                                admin_url( 'admin.php' )
                            ),
                            'format'   => '',
                            'current'  => $paged,
                            'total'    => $posts_query->max_num_pages,
                            'prev_text'=> '←',
                            'next_text'=> '→',
                            'add_args' => array_filter(
                                array(
                                    's'           => $search,
                                    'post_status' => $status,
                                    'category'    => $category,
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
?>