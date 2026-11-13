<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================
// 🎯 تشخیص Taxonomy از URL
// ============================================
$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
    wp_die( 'دسته‌بندی مشخص نشده یا وجود ندارد.', 'خطا', array( 'response' => 400 ) );
}

$tax_obj = get_taxonomy( $taxonomy );
if ( ! $tax_obj ) {
    wp_die( 'دسته‌بندی یافت نشد.', 'خطا', array( 'response' => 404 ) );
}

if ( ! current_user_can( $tax_obj->cap->manage_terms ) ) {
    wp_die( 'شما اجازه دسترسی به این بخش را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

$labels          = $tax_obj->labels;
$page_title      = $labels->name;
$is_hierarchical = $tax_obj->hierarchical;

// ============================================
// 📥 پردازش اکشن‌ها (Add / Update / Delete)
// ============================================
$notice    = '';
$edit_term = false;

if ( isset( $_GET['edit'] ) ) {
    $edit_id = absint( $_GET['edit'] );
    if ( $edit_id ) {
        $edit_term = get_term( $edit_id, $taxonomy );
    }
}

if ( isset( $_POST['nextsafar_tax_action'] ) && check_admin_referer( 'nextsafar_tax_action_' . $taxonomy ) ) {
    $action = sanitize_key( $_POST['nextsafar_tax_action'] );
    switch ( $action ) {
        case 'add':
            $name   = sanitize_text_field( wp_unslash( $_POST['term_name'] ?? '' ) );
            $slug   = sanitize_title( wp_unslash( $_POST['term_slug'] ?? '' ) );
            $desc   = sanitize_textarea_field( wp_unslash( $_POST['term_description'] ?? '' ) );
            $parent = $is_hierarchical ? absint( $_POST['term_parent'] ?? 0 ) : 0;
            
            if ( ! empty( $name ) ) {
                $args = array( 'description' => $desc, 'slug' => $slug );
                if ( $parent > 0 ) {
                    $args['parent'] = $parent;
                }
                $result = wp_insert_term( $name, $taxonomy, $args );
                if ( is_wp_error( $result ) ) {
                    $notice = array( 'error', $result->get_error_message() );
                } else {
                    $new_term_id = is_array( $result ) ? $result['term_id'] : 0;
                    if ( $new_term_id && ! empty( $_POST['term_meta'] ) && is_array( $_POST['term_meta'] ) ) {
                        NextSafar_Helpers::save_term_meta( $new_term_id, $_POST['term_meta'] );
                    }
                    do_action( "created_{$taxonomy}", $new_term_id, 0 );
                    $notice = array( 'success', $labels->added ?? 'دسته‌بندی با موفقیت اضافه شد.' );
                }
            } else {
                $notice = array( 'error', 'نام دسته‌بندی الزامی است.' );
            }
            break;
            
        case 'update':
            $term_id = absint( $_POST['term_id'] ?? 0 );
            $name    = sanitize_text_field( wp_unslash( $_POST['term_name'] ?? '' ) );
            $slug    = sanitize_title( wp_unslash( $_POST['term_slug'] ?? '' ) );
            $desc    = sanitize_textarea_field( wp_unslash( $_POST['term_description'] ?? '' ) );
            $parent  = $is_hierarchical ? absint( $_POST['term_parent'] ?? 0 ) : 0;
            
            if ( $term_id && ! empty( $name ) ) {
                $args = array( 'name' => $name, 'description' => $desc, 'slug' => $slug );
                if ( $parent >= 0 ) {
                    $args['parent'] = $parent;
                }
                $result = wp_update_term( $term_id, $taxonomy, $args );
                if ( is_wp_error( $result ) ) {
                    $notice = array( 'error', $result->get_error_message() );
                } else {
                    if ( ! empty( $_POST['term_meta'] ) && is_array( $_POST['term_meta'] ) ) {
                        NextSafar_Helpers::save_term_meta( $term_id, $_POST['term_meta'] );
                    }
                    do_action( "edited_{$taxonomy}", $term_id, 0 );
                    $notice = array( 'success', 'دسته‌بندی با موفقیت بروزرسانی شد.' );
                }
            }
            break;
            
        case 'delete':
            $term_id = absint( $_POST['term_id'] ?? 0 );
            if ( $term_id ) {
                $result = wp_delete_term( $term_id, $taxonomy );
                if ( is_wp_error( $result ) ) {
                    $notice = array( 'error', $result->get_error_message() );
                } elseif ( $result ) {
                    NextSafar_Helpers::delete_term_meta( $term_id );
                    $notice = array( 'success', 'دسته‌بندی با موفقیت حذف شد.' );
                }
            }
            break;
    }
}

// ============================================
// 📊 گرفتن لیست دسته‌بندی‌ها
// ============================================
$terms = get_terms( array(
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
) );
if ( is_wp_error( $terms ) ) {
    $terms = array();
}

$parent_terms = array();
if ( $is_hierarchical ) {
    $parent_terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );
}

// ✅ استفاده از Helpers برای ACF و Term Meta
$add_fields_data  = NextSafar_Helpers::capture_taxonomy_fields( $taxonomy, null );
$edit_fields_data = $edit_term && ! is_wp_error( $edit_term )
    ? NextSafar_Helpers::capture_taxonomy_fields( $taxonomy, $edit_term )
    : $add_fields_data;

// ============================================
// 🎨 Layout
// ============================================
require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
?>
<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <main class="nextsafar-content">
        <div class="nextsafar-page-header">
            <div class="nextsafar-page-actions"></div>
        </div>
        
        <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo $notice[0] === 'error' ? 'error' : 'success'; ?> is-dismissible" style="margin: 0 0 20px;">
            <p><?php echo esc_html( $notice[1] ); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="nextsafar-categories-grid">
            <!-- فرم افزودن/ویرایش -->
            <div class="nextsafar-panel">
                <div class="nextsafar-panel-header">
                    <div>
                        <?php if ( $edit_term && ! is_wp_error( $edit_term ) ) : ?>
                            <h2><?php echo esc_html( $labels->edit_item ); ?></h2>
                            <p><?php echo esc_html( $labels->update_item ); ?></p>
                        <?php else : ?>
                            <h2><?php echo esc_html( $labels->add_new_item ); ?></h2>
                            <p><?php echo esc_html( $labels->new_item_name ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nextsafar-panel-body">
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'nextsafar_tax_action_' . $taxonomy ); ?>
                        <input type="hidden" name="nextsafar_tax_action"
                               value="<?php echo $edit_term ? 'update' : 'add'; ?>">
                        <?php if ( $edit_term && ! is_wp_error( $edit_term ) ) : ?>
                            <input type="hidden" name="term_id" value="<?php echo esc_attr( $edit_term->term_id ); ?>">
                        <?php endif; ?>
                        
                        <div class="nextsafar-form-group">
                            <label for="term-name"><?php echo esc_html( $labels->name_label ?? 'نام' ); ?></label>
                            <input type="text" id="term-name" name="term_name" class="nextsafar-form-input"
                                   value="<?php echo $edit_term && ! is_wp_error( $edit_term ) ? esc_attr( $edit_term->name ) : ''; ?>"
                                   required>
                        </div>
                        <div class="nextsafar-form-group">
                            <label for="term-slug"><?php echo esc_html( $labels->slug_label ?? 'نامک' ); ?></label>
                            <input type="text" id="term-slug" name="term_slug" class="nextsafar-form-input"
                                   value="<?php echo $edit_term && ! is_wp_error( $edit_term ) ? esc_attr( $edit_term->slug ) : ''; ?>">
                            <small>خالی بگذارید تا به صورت خودکار ساخته شود.</small>
                        </div>
                        <?php if ( $is_hierarchical && ! empty( $parent_terms ) ) : ?>
                        <div class="nextsafar-form-group">
                            <label for="term-parent"><?php echo esc_html( $labels->parent_item ?? 'والد' ); ?></label>
                            <select id="term-parent" name="term_parent" class="nextsafar-form-input">
                                <option value="0">— بدون والد —</option>
                                <?php foreach ( $parent_terms as $parent ) :
                                    if ( $edit_term && $parent->term_id === $edit_term->term_id ) continue;
                                ?>
                                <option value="<?php echo esc_attr( $parent->term_id ); ?>"
                                    <?php selected( $edit_term && ! is_wp_error( $edit_term ) ? $edit_term->parent : 0, $parent->term_id ); ?>>
                                    <?php echo esc_html( $parent->name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="nextsafar-form-group">
                            <label for="term-description"><?php echo esc_html( $labels->desc_label ?? 'توضیحات' ); ?></label>
                            <textarea id="term-description" name="term_description"
                                      class="nextsafar-form-textarea" rows="4"><?php
                                echo $edit_term && ! is_wp_error( $edit_term ) ? esc_textarea( $edit_term->description ) : '';
                            ?></textarea>
                        </div>
                        
                        <?php
                        $fields_data = ( $edit_term && ! is_wp_error( $edit_term ) ) ? $edit_fields_data : $add_fields_data;
                        if ( ! empty( $fields_data['html'] ) ) :
                        ?>
                        <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--ns-border);">
                            <?php echo $fields_data['html']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display:flex; gap:10px; align-items:center; margin-top: 20px;">
                            <button type="submit" class="nextsafar-primary-button">
                                <?php echo $edit_term ? esc_html( $labels->update_item ?? 'بروزرسانی' ) : esc_html( $labels->add_new_item ?? 'افزودن' ); ?>
                            </button>
                            <?php if ( $edit_term ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-cpt-taxonomy&taxonomy=' . $taxonomy ) ); ?>"
                               class="nextsafar-secondary-button">انصراف</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول لیست -->
            <div class="nextsafar-panel">
                <div class="nextsafar-panel-header">
                    <div>
                        <h2><?php echo esc_html( $labels->name ); ?></h2>
                        <p><?php printf( '%d دسته‌بندی', count( $terms ) ); ?></p>
                    </div>
                </div>
                <div class="nextsafar-table-wrapper">
                    <table class="nextsafar-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html( $labels->name_label ?? 'نام' ); ?></th>
                                <th><?php echo esc_html( $labels->slug_label ?? 'نامک' ); ?></th>
                                <?php if ( $is_hierarchical ) : ?><th>والد</th><?php endif; ?>
                                <th>تعداد</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $terms ) ) : ?>
                                <?php foreach ( $terms as $term ) :
                                    $parent_name = '—';
                                    if ( $is_hierarchical && $term->parent > 0 ) {
                                        $parent_term = get_term( $term->parent, $taxonomy );
                                        if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                                            $parent_name = $parent_term->name;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $term->name ); ?></strong>
                                        <?php if ( ! empty( $term->description ) ) : ?>
                                        <div style="font-size: 12px; color: var(--ns-text-secondary); margin-top: 4px;">
                                            <?php echo esc_html( wp_trim_words( $term->description, 10 ) ); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="nextsafar-muted"><?php echo esc_html( $term->slug ); ?></span></td>
                                    <?php if ( $is_hierarchical ) : ?>
                                    <td><span class="nextsafar-muted"><?php echo esc_html( $parent_name ); ?></span></td>
                                    <?php endif; ?>
                                    <td><?php echo esc_html( $term->count ); ?></td>
                                    <td>
                                        <div class="nextsafar-actions">
                                            <a href="<?php echo esc_url(
                                                admin_url( 'admin.php?page=nextsafar-cpt-taxonomy&taxonomy=' . $taxonomy . '&edit=' . $term->term_id )
                                            ); ?>" title="ویرایش"><?php NextSafar_Icons::display( 'square-pen', 16 ); ?></a>
                                            <a href="<?php echo esc_url( get_term_link( $term ) ); ?>"
                                               target="_blank" rel="noopener noreferrer" title="مشاهده"><?php NextSafar_Icons::display( 'eye', 16 ); ?></a>
                                            <form method="post" style="display:inline;"
                                                  onsubmit="return confirm('آیا از حذف این دسته‌بندی مطمئن هستید؟');">
                                                <?php wp_nonce_field( 'nextsafar_tax_action_' . $taxonomy ); ?>
                                                <input type="hidden" name="nextsafar_tax_action" value="delete">
                                                <input type="hidden" name="term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                                <button type="submit" title="حذف" class="nextsafar-delete-button">🗑</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="<?php echo $is_hierarchical ? '5' : '4'; ?>" class="nextsafar-table-empty">
                                        <div>
                                            <strong><?php echo esc_html( $labels->not_found ?? 'دسته‌بندی یافت نشد' ); ?></strong>
                                            <span>اولین دسته‌بندی خود را ایجاد کنید.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';
?>

<!-- Image Picker JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.term-image-upload-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const fieldName   = this.dataset.field;
            const hiddenInput = document.getElementById('term-meta-' + fieldName);
            
            if (typeof wp !== 'undefined' && wp.media) {
                const frame = wp.media({
                    title: 'انتخاب تصویر',
                    button: { text: 'انتخاب' },
                    multiple: false
                });
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    hiddenInput.value = attachment.id;
                    const container = btn.parentElement;
                    let img = container.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        img.style.cssText = 'max-width:150px; max-height:150px; border-radius:8px; margin-bottom:10px; display:block;';
                        container.insertBefore(img, btn);
                    }
                    img.src = attachment.url;
                    btn.textContent = 'تغییر تصویر';
                });
                frame.open();
            } else {
                alert('WordPress Media Library در دسترس نیست');
            }
        });
    });
    
    document.querySelectorAll('.term-image-remove-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const fieldName   = this.dataset.field;
            const hiddenInput = document.getElementById('term-meta-' + fieldName);
            hiddenInput.value = '';
            const container = btn.parentElement;
            const img = container.querySelector('img');
            if (img) img.remove();
            const uploadBtn = container.querySelector('.term-image-upload-btn');
            if (uploadBtn) uploadBtn.textContent = 'انتخاب تصویر';
            btn.remove();
        });
    });
});
</script>