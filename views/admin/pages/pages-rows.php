<?php
/**
 * 📋 Partial: ردیف‌های جدول برگه‌ها
 * ورودی: $pages_query (WP_Query)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php if ( $pages_query->have_posts() ) : ?>
    <?php while ( $pages_query->have_posts() ) : ?>
        <?php
        $pages_query->the_post();
        $page_id     = get_the_ID();
        $page_status = get_post_status( $page_id );
        $template    = get_page_template_slug( $page_id );
        $template    = $template ? basename( $template, '.php' ) : 'پیش‌فرض';
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
                    'publish' => 'منتشر شده',
                    'draft'   => 'پیش‌نویس',
                    'pending' => 'در انتظار',
                    'private' => 'خصوصی',
                    'trash'   => 'زباله‌دان',
                );
                $status_label = isset( $status_labels[ $page_status ] ) ? $status_labels[ $page_status ] : ucfirst( $page_status );
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
                <strong>برگه‌ای یافت نشد</strong>
                <span>فیلترها را تغییر دهید یا اولین برگه خود را ایجاد کنید.</span>
            </div>
        </td>
    </tr>
<?php endif; ?>