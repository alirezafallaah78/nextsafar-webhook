<?php
/**
 * 📋 Partial: ردیف‌های جدول نوشته‌ها
 * ورودی مورد انتظار: $posts_query (WP_Query)
 * هم در رندر اولیه صفحه و هم در پاسخ AJAX استفاده می‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php if ( $posts_query->have_posts() ) : ?>
    <?php while ( $posts_query->have_posts() ) : ?>
        <?php
        $posts_query->the_post();
        $post_id         = get_the_ID();
        $post_categories = get_the_category();
        $post_status     = get_post_status( $post_id );
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
                <span><?php echo esc_html__( 'فیلترها را تغییر دهید یا اولین نوشته خود را ایجاد کنید.', 'nextsafar-admin' ); ?></span>
            </div>
        </td>
    </tr>
<?php endif; ?>