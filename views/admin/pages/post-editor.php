<?php
/**
 * NextSafar Post Editor - Universal Editor (iframe approach)
 * این فایل صفحه استاندارد وردپرس را در iframe لود می‌کند
 * تا تمام پلاگین‌ها (Yoast, ACF, etc) به صورت 100% Native کار کنند.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================
// 🎯 تشخیص Post Type و حالت
// ============================================
$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
$post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
$action    = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'add';

if ( ! post_type_exists( $post_type ) ) {
    wp_die( 'پست تایپ مشخص شده وجود ندارد.', 'خطا', array( 'response' => 400 ) );
}

$post_type_obj = get_post_type_object( $post_type );
$labels        = $post_type_obj->labels;
$is_edit_mode  = ( 'edit' === $action && $post_id > 0 );

// ============================================
// 🎯 ساخت URL صفحه واقعی وردپرس
// ============================================
if ( $is_edit_mode ) {
    // بررسی دسترسی
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( 'شما اجازه ویرایش این پست را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
    }
    $editor_url = admin_url( "post.php?post={$post_id}&action=edit" );
    $page_title = sprintf( 'ویرایش %s', $labels->singular_name );
} else {
    // بررسی دسترسی
    if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
        wp_die( 'شما اجازه افزودن پست جدید را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
    }
    $editor_url = admin_url( "post-new.php?post_type={$post_type}" );
    $page_title = sprintf( 'افزودن %s جدید', $labels->singular_name );
}

// اضافه کردن پارامتر iframe برای تشخیص در class-admin.php
$iframe_url = add_query_arg( 'nextsafar_iframe', '1', $editor_url );

// ============================================
// 🎯 Redirect بعد از ذخیره به صفحه NextSafar
// ============================================
// این JavaScript باعث می‌شود بعد از ذخیره پست در وردپرس،
// کاربر به صفحه CPT در NextSafar برگردد (نه wp-admin استاندارد)
$redirect_to_cpt = admin_url( 'admin.php?page=nextsafar-cpt&post_type=' . $post_type );
$editor_js = <<<JS
<script>
(function() {
    // گوش دادن به پیام‌های iframe
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'nextsafar_redirect') {
            window.location.href = '{$redirect_to_cpt}';
        }
    });
    
    // تزریق اسکریپت به iframe بعد از لود
    document.addEventListener('DOMContentLoaded', function() {
        const iframe = document.getElementById('nextsafar-editor-iframe');
        if (!iframe) return;
        
        iframe.addEventListener('load', function() {
            try {
                const iframeDoc = iframe.contentWindow.document;
                const iframeWin = iframe.contentWindow;
                
                // 1. بررسی URL فعلی iframe
                const currentUrl = iframeWin.location.href;
                
                // 2. اگر بعد از save به صفحه edit هدایت شد، پیام بفرست
                if (currentUrl.indexOf('post.php') !== -1 && currentUrl.indexOf('action=edit') !== -1) {
                    // بررسی وجود پارامتر updated=1 در URL
                    if (currentUrl.indexOf('message=1') !== -1 || currentUrl.indexOf('updated=1') !== -1) {
                        // صبر کن تا نوتیفیکیشن وردپرس نمایش داده شود
                        setTimeout(function() {
                            // کاربر می‌تواند ادامه دهد یا برگردد
                            // ما او را به لیست CPT برمی‌گردانیم
                            if (confirm('پست با موفقیت ذخیره شد. آیا می‌خواهید به لیست برگردید؟')) {
                                window.location.href = '{$redirect_to_cpt}';
                            }
                        }, 1500);
                    }
                }
                
                // 3. تغییر لینک "View Posts" و "All Posts" در نوار بالا
                const viewAllLink = iframeDoc.querySelector('a[href*="edit.php"]');
                if (viewAllLink) {
                    viewAllLink.href = '{$redirect_to_cpt}';
                }
                
                // 4. مخفی کردن admin bar داخل iframe
                const style = iframeDoc.createElement('style');
                style.textContent = `
                    #wpadminbar { display: none !important; }
                    html { margin-top: 0 !important; }
                    body { padding-top: 0 !important; }
                `;
                iframeDoc.head.appendChild(style);
                
            } catch(e) {
                console.log('Iframe access error:', e);
            }
        });
    });
})();
</script>
JS;

// ============================================
// 🎨 Layout
// ============================================
require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
?>
<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <main class="nextsafar-content" style="padding: 0 !important;">
        <!-- Top Bar -->
        <div class="editor-topbar" style="padding: 12px 20px; background: #fff; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-cpt&post_type=' . $post_type ) ); ?>"
               class="nextsafar-secondary-button" style="text-decoration: none;">
                ← بازگشت به لیست <?php echo esc_html( $labels->name ); ?>
            </a>
            <div style="flex: 1;"></div>
            <span style="color: #6b7280; font-size: 13px;">
                <?php echo esc_html( $page_title ); ?>
            </span>
        </div>
        
        <!-- iframe Container -->
        <div class="nextsafar-iframe-wrapper" style="height: calc(100vh - 130px); width: 100%; overflow: hidden;">
            <iframe 
                id="nextsafar-editor-iframe"
                src="<?php echo esc_url( $iframe_url ); ?>" 
                class="nextsafar-iframe" 
                frameborder="0" 
                allowfullscreen
                style="width: 100%; height: 100%; border: 0; display: block;">
            </iframe>
        </div>
    </main>
</div>

<?php echo $editor_js; ?>

<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';
?>