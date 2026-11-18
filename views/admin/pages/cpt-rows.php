<?php
/**
 * 📋 Partial: ردیف‌های جدول CPT
 * ورودی‌ها: $cpt_query (WP_Query), $post_type, $labels
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php if ( $cpt_query->have_posts() ) : ?>
    <?php while ( $cpt_query->have_posts() ) : ?>
        <?php
        $cpt_query->the_post();
        $item_id     = get_the_ID();
        $item_status = get_post_status( $item_id );
        ?>
        <tr>
            <td>
                <input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $item_id ); ?>" class="nextsafar-post-checkbox">
            </td>
            <td>
                <div class="nextsafar-post-title">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="nextsafar-post-thumbnail">
                            <?php echo get_the_post_thumbnail( $item_id, array( 48, 36 ) ); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <a href="<?php echo esc_url( nextsafar_editor_url( $post_type, $item_id ) ); ?>" class="nextsafar-post-name">
                            <?php the_title(); ?>
                        </a>
                        <span class="nextsafar-post-id">#<?php echo esc_html( $item_id ); ?></span>
                    </div>
                </div>
            </td>
            <td><?php echo esc_html( get_the_author() ); ?></td>
            <td>
                <?php
                $status_labels = array(
                    'publish' => 'منتشر شده',
                    'draft'   => 'پیش‌نویس',
                    'pending' => 'در انتظار',
                    'private' => 'خصوصی',
                    'trash'   => 'زباله‌دان',
                );
                $status_label = isset( $status_labels[ $item_status ] ) ? $status_labels[ $item_status ] : ucfirst( $item_status );
                ?>
                <span class="nextsafar-status nextsafar-status-<?php echo esc_attr( $item_status ); ?>">
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
                    <a href="<?php echo esc_url( nextsafar_editor_url( $post_type, $item_id ) ); ?>" title="<?php echo esc_attr__( 'ویرایش', 'nextsafar-admin' ); ?>"><?php NextSafar_Icons::display( 'square-pen', 18 ); ?></a>
                    <a href="<?php echo esc_url( get_permalink( $item_id ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'مشاهده', 'nextsafar-admin' ); ?>"><?php NextSafar_Icons::display( 'eye', 18 ); ?></a>
                </div>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else : ?>
    <tr>
        <td colspan="6" class="nextsafar-table-empty">
            <div>
                <strong><?php echo esc_html( $labels ? $labels->not_found : 'موردی یافت نشد' ); ?></strong>
                <span><?php echo esc_html( $labels ? sprintf( 'اولین %s خود را ایجاد کنید.', $labels->singular_name ) : 'فیلترها را تغییر دهید.' ); ?></span>
            </div>
        </td>
    </tr>
<?php endif; ?>