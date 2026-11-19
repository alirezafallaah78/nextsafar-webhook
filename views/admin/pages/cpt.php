<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
    wp_die( 'پست تایپ مشخص نشده یا وجود ندارد.', 'خطا', array( 'response' => 400 ) );
}
$post_type_obj = get_post_type_object( $post_type );
if ( ! $post_type_obj ) {
    wp_die( 'پست تایپ یافت نشد.', 'خطا', array( 'response' => 404 ) );
}
if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
    wp_die( 'شما اجازه دسترسی به این بخش را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

$labels     = $post_type_obj->labels;
$page_title = $labels->name;

// 🚀 دریافت داده‌ها از Controller
$posts_query = $data['query'];
$cpt_query   = $posts_query;
$search      = $data['search'];
$status      = $data['status'];
$term        = isset( $data['term'] ) ? $data['term'] : 0;
$taxonomy    = isset( $data['taxonomy'] ) ? $data['taxonomy'] : '';
$paged       = $data['paged'];
$page_slug   = 'nextsafar-cpt';

require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
?>
<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <main class="nextsafar-content">
        <style>
            .ns-posts-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
            .ns-posts-toolbar>form{display:contents}
            .ns-posts-toolbar .nextsafar-search{flex:1 1 220px;min-width:180px;position:relative}
            .ns-posts-toolbar .nextsafar-search input{width:100%}
            .ns-posts-toolbar select.nxs-filter{flex:0 1 auto;min-width:128px;max-width:190px}
            .ns-posts-toolbar .ns-new-post{margin-inline-start:auto;white-space:nowrap}
            .ns-bulk-apply:disabled{opacity:.45;cursor:not-allowed;pointer-events:none;box-shadow:none}
            .ns-posts-table-wrap{position:relative;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .ns-posts-table-wrap::-webkit-scrollbar{height:8px}
            .ns-posts-table-wrap::-webkit-scrollbar-track{background:#f1f5f9;border-radius:99px}
            .ns-posts-table-wrap::-webkit-scrollbar-thumb{background:rgba(37,99,235,.3);border-radius:99px}
            .ns-posts-table-wrap::-webkit-scrollbar-thumb:hover{background:rgba(37,99,235,.55)}
            .ns-posts-table-wrap{scrollbar-width:thin;scrollbar-color:rgba(37,99,235,.35) #f1f5f9}
            .ns-posts-loading{position:absolute;inset:0;z-index:6;display:none;align-items:flex-start;justify-content:center;padding-top:60px;background:rgba(255,255,255,.65);backdrop-filter:blur(1px)}
            .ns-posts-loading.is-on{display:flex}
            .ns-posts-loading .ns-posts-loading-pill{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--ns-border,#e5e7eb);border-radius:99px;padding:8px 16px;font-size:12px;color:var(--ns-text-muted,#6b7280);box-shadow:0 6px 20px rgba(15,23,42,.08)}
            .ns-posts-toast{position:fixed;bottom:22px;left:22px;z-index:99999;background:#111827;color:#fff;font-size:12px;font-weight:600;padding:10px 16px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);opacity:0;transform:translateY(8px);transition:all .22s ease;pointer-events:none}
            .ns-posts-toast.is-on{opacity:1;transform:none}
            .ns-posts-toast.is-err{background:#b91c1c}
            #ns-posts-pagination:empty{display:none}
            @media(max-width:900px){
                .ns-posts-toolbar select.nxs-filter{flex:1 1 calc(33.33% - 10px);max-width:none}
                .ns-posts-toolbar .ns-new-post{margin-inline-start:0}
            }
            @media(max-width:640px){
                .ns-posts-toolbar .nextsafar-search{flex:1 1 100%}
                .ns-posts-toolbar select.nxs-filter{flex:1 1 calc(50% - 10px);min-width:0}
                .ns-posts-toolbar .ns-bulk-apply{flex:1 1 auto}
                .ns-posts-toolbar .ns-new-post{flex:1 1 100%;text-align:center;justify-content:center}
                .ns-posts-table-wrap table{min-width:760px}
            }
            @media(prefers-reduced-motion:reduce){.ns-posts-toast,.ns-posts-loading{transition:none!important}}
        </style>

        <?php
        $ns_cm = function_exists( 'nextsafar' ) ? nextsafar()->content_manager : ( isset( $GLOBALS['content_manager'] ) ? $GLOBALS['content_manager'] : null );
        if ( $ns_cm && method_exists( $ns_cm, 'display_bulk_notice' ) ) {
            $ns_cm->display_bulk_notice();
        }
        ?>

        <div class="nextsafar-panel nextsafar-posts-panel">

            <div class="nextsafar-posts-toolbar ns-posts-toolbar">
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="ns-posts-filters">
                    <input type="hidden" name="page" value="nextsafar-cpt">
                    <input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>">

                    <div class="nextsafar-search">
                        <span class="nextsafar-search-icon">
                            <?php NextSafar_Icons::display( 'search', 16 ); ?>
                        </span>
                        <input type="search" name="s" id="ns-posts-search" autocomplete="off"
                               value="<?php echo esc_attr( $search ); ?>"
                               placeholder="<?php echo esc_attr( sprintf( 'جستجو در %s...', $labels->name ) ); ?>">
                    </div>

                    <select name="post_status" id="ns-filter-status" class="nextsafar-filter nxs-filter">
                        <option value=""><?php echo esc_html__( 'همه وضعیت‌ها', 'nextsafar-admin' ); ?></option>
                        <option value="publish" <?php selected( $status, 'publish' ); ?>><?php echo esc_html__( 'منتشر شده', 'nextsafar-admin' ); ?></option>
                        <option value="draft" <?php selected( $status, 'draft' ); ?>><?php echo esc_html__( 'پیش‌نویس', 'nextsafar-admin' ); ?></option>
                        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php echo esc_html__( 'در انتظار', 'nextsafar-admin' ); ?></option>
                        <option value="private" <?php selected( $status, 'private' ); ?>><?php echo esc_html__( 'خصوصی', 'nextsafar-admin' ); ?></option>
                        <option value="trash" <?php selected( $status, 'trash' ); ?>><?php echo esc_html__( 'زباله‌دان', 'nextsafar-admin' ); ?></option>
                    </select>

                    <?php if ( $taxonomy && taxonomy_exists( $taxonomy ) ) : ?>
                        <select name="term" id="ns-filter-category" class="nextsafar-filter nxs-filter">
                            <option value=""><?php echo esc_html__( 'همه دسته‌ها', 'nextsafar-admin' ); ?></option>
                            <?php
                            $ns_terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
                            if ( ! is_wp_error( $ns_terms ) ) :
                                foreach ( $ns_terms as $ns_t ) :
                            ?>
                                <option value="<?php echo esc_attr( $ns_t->term_id ); ?>" <?php selected( $term, $ns_t->term_id ); ?>>
                                    <?php echo esc_html( $ns_t->name ); ?>
                                </option>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </select>
                    <?php endif; ?>
                </form>

                <select name="nextsafar_bulk_action" id="ns-bulk-action" class="nextsafar-filter nxs-filter" form="nextsafar-bulk-form">
                    <option value=""><?php echo esc_html__( 'عملیات گروهی', 'nextsafar-admin' ); ?></option>
                    <option value="trash"><?php echo esc_html__( 'انتقال به زباله‌دان', 'nextsafar-admin' ); ?></option>
                    <option value="restore"><?php echo esc_html__( 'بازیابی', 'nextsafar-admin' ); ?></option>
                    <option value="delete"><?php echo esc_html__( 'حذف دائمی', 'nextsafar-admin' ); ?></option>
                </select>

                <button type="submit" id="ns-bulk-apply" class="nextsafar-primary-button ns-bulk-apply" form="nextsafar-bulk-form" disabled>
                    <?php echo esc_html__( 'اعمال', 'nextsafar-admin' ); ?>
                </button>

                <?php if ( current_user_can( $post_type_obj->cap->create_posts ) ) : ?>
                    <a href="<?php echo esc_url( nextsafar_editor_url( $post_type ) ); ?>" class="nextsafar-primary-button ns-new-post">
                        + <?php echo esc_html( $labels->add_new_item ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- 📦 فرم عملیات گروهی + جدول -->
            <form method="post" action="" id="nextsafar-bulk-form">
                <?php wp_nonce_field( 'nextsafar_bulk_cpt_' . $post_type ); ?>
                <input type="hidden" name="nextsafar_post_type" value="<?php echo esc_attr( $post_type ); ?>">
                <input type="hidden" name="ns_return" id="ns-return-query" value="">

                <div class="nextsafar-table-wrapper ns-posts-table-wrap" id="ns-posts-table-wrap">
                    <div class="ns-posts-loading" id="ns-posts-loading">
                        <span class="ns-posts-loading-pill"><span class="nextsafar-spinner"></span> در حال بارگذاری…</span>
                    </div>
                    <table class="nextsafar-table">
                        <thead>
                            <tr>
                                <th class="nextsafar-checkbox-column">
                                    <input type="checkbox" id="nextsafar-select-all">
                                </th>
                                <th><?php echo esc_html__( 'عنوان', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'نویسنده', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'وضعیت', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'تاریخ', 'nextsafar-admin' ); ?></th>
                                <th><?php echo esc_html__( 'عملیات', 'nextsafar-admin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ns-posts-tbody">
                            <?php require NEXTSAFAR_PATH . 'views/admin/pages/cpt-rows.php'; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php wp_reset_postdata(); ?>

            <div class="nextsafar-pagination" id="ns-posts-pagination">
                <?php require NEXTSAFAR_PATH . 'views/admin/pages/content-pagination.php'; ?>
            </div>
        </div>
    </main>
</div>
<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';