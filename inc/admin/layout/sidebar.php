<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
   متغیرهای Global - وضعیت فعلی صفحه
========================================================== */
$current_page   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
$current_target = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
$request_uri    = $_SERVER['REQUEST_URI'];
$current_file   = basename( parse_url( $request_uri, PHP_URL_PATH ) );

$current_extra_params = array();
if ( isset( $_GET['extra'] ) ) {
    parse_str( sanitize_text_field( wp_unslash( $_GET['extra'] ) ), $current_extra_params );
}

$core         = nextsafar();
$cpt_menus    = $core->external_menus ? $core->external_menus->get_cpt_menus() : array();
$plugin_menus = $core->external_menus ? $core->external_menus->get_plugin_menus() : array();

/* =========================================================
   🔐 دسترسی نقش‌محور: کدام منوها برای این کاربر مجاز است؟
========================================================== */
$ns_can_access    = current_user_can( 'manage_options' );
$ns_can_posts     = nextsafar_user_can_menu( 'admin.php?page=nextsafar-posts' );
$ns_can_pages     = nextsafar_user_can_menu( 'admin.php?page=nextsafar-pages' );
$ns_can_media     = nextsafar_user_can_menu( 'admin.php?page=nextsafar-media' );
$ns_can_comments  = nextsafar_user_can_menu( 'edit-comments.php' );
$ns_can_themes    = nextsafar_user_can_menu( 'themes.php' );
$ns_can_menus     = nextsafar_user_can_menu( 'nav-menus.php' );
$ns_can_widgets   = nextsafar_user_can_menu( 'widgets.php' );
$ns_can_users     = nextsafar_user_can_menu( 'users.php' );
$ns_can_plugins   = nextsafar_user_can_menu( 'plugins.php' );
$ns_can_updates   = nextsafar_user_can_menu( 'admin.php?page=nextsafar-updates' );
$ns_can_tools     = nextsafar_user_can_menu( 'tools.php' );
$ns_can_settings  = nextsafar_user_can_menu( 'options-general.php' );

// فیلتر CPT ها بر اساس دسترسی
$ns_cpt_menus = array();
foreach ( $cpt_menus as $cpt ) {
    if ( isset( $cpt['post_type'] ) && nextsafar_user_can_menu( 'edit.php?post_type=' . $cpt['post_type'] ) ) {
        $ns_cpt_menus[] = $cpt;
    }
}

// فیلتر منوی پلاگین‌ها بر اساس دسترسی
$ns_plugin_menus = array();
foreach ( $plugin_menus as $plugin ) {
    $slug = ! empty( $plugin['page_slug'] ) ? $plugin['page_slug'] : ( isset( $plugin['raw_slug'] ) ? $plugin['raw_slug'] : '' );
    if ( '' !== $slug && nextsafar_user_can_menu( $slug ) ) {
        $ns_plugin_menus[] = $plugin;
    }
}

$ns_section_content    = $ns_can_posts || $ns_can_pages || $ns_can_media || $ns_can_comments || ! empty( $ns_cpt_menus );
$ns_section_appearance = $ns_can_themes || $ns_can_menus || $ns_can_widgets;
$ns_section_management = $ns_can_users || $ns_can_plugins || $ns_can_updates || $ns_can_tools || $ns_can_settings;

/* =========================================================
   Helper Wrapper (بدون تغییر)
========================================================== */
class H {
    public static function icon( $name ) { NextSafar_Helpers::sidebar_icon( $name ); }
    public static function is( $files ) { return NextSafar_Helpers::is_current( $files ); }
    public static function is_param( $file, $key, $val = null ) { return NextSafar_Helpers::is_current_with_param( $file, $key, $val ); }
    public static function no_extra( $keys = array() ) { return NextSafar_Helpers::has_no_extra_params( $keys ); }
    public static function page( $slugs ) { return NextSafar_Helpers::is_page( $slugs ); }
    public static function url( $target, $extra = array() ) { return NextSafar_Helpers::wrapper_url( $target, $extra ); }
}

function nextsafar_render_submenu( $submenus ) {
    if ( empty( $submenus ) ) { return; }
    echo '<div class="nextsafar-submenu">';
    foreach ( $submenus as $sub ) {
        $is_active = false;
        $current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( $current_page === 'nextsafar-cpt' ) {
            $post_type_param = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
            $url_post_type  = '';
            if ( strpos( $sub['url'], 'post_type=' ) !== false ) {
                parse_str( parse_url( $sub['url'], PHP_URL_QUERY ), $sub_params );
                $url_post_type = $sub_params['post_type'] ?? '';
            }
            if ( strpos( $sub['url'], 'page=nextsafar-cpt' ) !== false && $post_type_param === $url_post_type ) { $is_active = true; }
        }
        if ( $current_page === 'nextsafar-cpt-taxonomy' ) {
            $current_tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
            if ( strpos( $sub['url'], 'page=nextsafar-cpt-taxonomy' ) !== false && strpos( $sub['url'], 'taxonomy=' . $current_tax ) !== false ) { $is_active = true; }
        }
        if ( ! $is_active && ! empty( $sub['page_slug'] ) && H::page( $sub['page_slug'] ) ) { $is_active = true; }
        $url = $sub['url'];
        if ( ! $is_active && strpos( $url, 'target=' ) !== false ) {
            parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
            if ( isset( $params['target'] ) ) {
                $is_active = H::is( $params['target'] );
                if ( $is_active && isset( $params['extra'] ) ) {
                    parse_str( $params['extra'], $extra_params );
                    foreach ( $extra_params as $key => $value ) {
                        if ( ! H::is_param( $params['target'], $key, $value ) ) { $is_active = false; break; }
                    }
                }
            }
        }
        echo '<a href="' . esc_url( $sub['url'] ) . '" class="nextsafar-submenu-item ' . ( $is_active ? 'active' : '' ) . '">';
        echo '<span>' . esc_html( $sub['title'] ) . '</span></a>';
    }
    echo '</div>';
}
?>
<aside class="nextsafar-sidebar">
    <div class="nextsafar-logo">
        <div class="nextsafar-logo-mark">
            <img src="<?php echo esc_url( NEXTSAFAR_URL . 'assets/admin/img/favicon.png' ); ?>" alt="سفربعدی ایرانیان">
        </div>
        <div class="nextsafar-logo-text"><strong>سفر بعدی ایرانیان</strong></div>
    </div>
    <nav class="nextsafar-navigation">
        <!-- MAIN : داشبورد همیشه مجاز -->
        <div class="nextsafar-nav-section">
            <span class="nextsafar-nav-title">MAIN</span>
            <?php
            $is_dashboard_active = (
                ( H::page( 'nextsafar-admin' ) && empty( $current_target ) )
                || ( $current_file === 'index.php' && empty( $current_page ) )
            );
            ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-admin' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_dashboard_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'dashboard' ); ?></span>
                <span>داشبورد</span>
            </a>
        </div>
        <?php if ( $ns_can_access ) : $is_access_active = H::page( 'nextsafar-role-access' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-role-access' ) ); ?>"
           class="nextsafar-nav-item <?php echo $is_access_active ? 'active' : ''; ?>">
            <span class="nextsafar-nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ns-icon" width="20" height="20"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </span>
            <span>دسترسی‌ها</span>
        </a>
        <?php endif; ?>

        <!-- CONTENT -->
        <?php if ( $ns_section_content ) : ?>
        <div class="nextsafar-nav-section">
            <span class="nextsafar-nav-title">CONTENT</span>

            <?php if ( $ns_can_posts ) : ?>
            <?php
                $is_blog_all_active = H::page( 'nextsafar-posts' )
                    || ( H::is( 'edit.php' ) && ! isset( $_GET['post_type'] ) && ! isset( $current_extra_params['post_type'] ) );
                $is_blog_categories_active = H::is_param( 'edit-tags.php', 'taxonomy', 'category' );
                $is_blog_tags_active       = H::is_param( 'edit-tags.php', 'taxonomy', 'post_tag' );
                $is_blog_add_new_active    = ( $current_page === 'nextsafar-post-editor' && isset( $_GET['post_type'] ) && sanitize_key( $_GET['post_type'] ) === 'post' );
                $is_blog_has_active_child  = $is_blog_all_active || $is_blog_add_new_active || $is_blog_categories_active || $is_blog_tags_active;
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $is_blog_has_active_child ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-posts' ) ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $is_blog_all_active ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon"><?php H::icon( 'post' ); ?></span>
                    <span>مقالات</span><span class="nextsafar-nav-arrow">▸</span>
                </a>
                <div class="nextsafar-submenu">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-posts' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_blog_all_active ? 'active' : ''; ?>"><span>همه نوشته‌ها</span></a>
                    <?php if ( current_user_can( 'edit_posts' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_blog_add_new_active ? 'active' : ''; ?>"><span>افزودن نوشته</span></a>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'manage_categories' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=category' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_blog_categories_active ? 'active' : ''; ?>"><span>دسته‌بندی‌ها</span></a>
                    <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=post_tag' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_blog_tags_active ? 'active' : ''; ?>"><span>برچسب‌ها</span></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CUSTOM POST TYPES (فقط مجازها) -->
            <?php foreach ( $ns_cpt_menus as $cpt ) :
                $cpt_post_type    = $cpt['post_type'];
                $cpt_active       = false;
                $is_cpt_main_page = false;

                if ( $current_page === 'nextsafar-cpt' && isset( $_GET['post_type'] ) && sanitize_key( $_GET['post_type'] ) === $cpt_post_type ) {
                    $cpt_active = true; $is_cpt_main_page = true;
                }
                if ( $current_page === 'nextsafar-cpt-taxonomy' && isset( $_GET['taxonomy'] ) ) {
                    $current_tax = sanitize_key( $_GET['taxonomy'] );
                    $tax_obj     = get_taxonomy( $current_tax );
                    if ( $tax_obj && in_array( $cpt_post_type, $tax_obj->object_type, true ) ) { $cpt_active = true; }
                }
                if ( ! $cpt_active && $current_page === 'nextsafar-wp-wrapper' ) {
                    if ( H::is_param( 'edit.php', 'post_type', $cpt_post_type ) ) {
                        $cpt_active = true;
                        if ( H::no_extra( array( 'action', 'taxonomy', 'post', 'tag_ID' ) ) ) { $is_cpt_main_page = true; }
                    }
                    if ( H::is_param( 'post-new.php', 'post_type', $cpt_post_type ) ) $cpt_active = true;
                    if ( H::is_param( 'post.php', 'post_type', $cpt_post_type ) ) $cpt_active = true;
                    if ( H::is_param( 'edit-tags.php', 'post_type', $cpt_post_type ) ) $cpt_active = true;
                    if ( H::is_param( 'term.php', 'post_type', $cpt_post_type ) ) $cpt_active = true;
                }
                if ( ! $cpt_active ) {
                    if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $cpt_post_type ) {
                        $cpt_active = true;
                        $cpt_files  = array( 'edit.php', 'post-new.php', 'post.php', 'edit-tags.php', 'term.php' );
                        if ( in_array( $current_file, $cpt_files, true ) ) {
                            if ( $current_file === 'edit.php' && ! isset( $_GET['action'] ) && ! isset( $_GET['taxonomy'] ) && ! isset( $_GET['post'] ) ) {
                                $is_cpt_main_page = true;
                            }
                        }
                    }
                }
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $cpt_active ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( $cpt['url'] ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $is_cpt_main_page ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon">
                        <?php
                        $icon = $cpt['icon'];
                        if ( strpos( $icon, 'cpt:' ) === 0 ) {
                            if ( class_exists( 'NextSafar_Icons' ) ) NextSafar_Icons::display_cpt_icon( substr( $icon, 4 ), 20 );
                        } elseif ( strpos( $icon, 'svg:' ) === 0 ) {
                            if ( class_exists( 'NextSafar_Icons' ) ) NextSafar_Icons::display( substr( $icon, 4 ), 20 );
                        } else {
                            echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
                        }
                        ?>
                    </span>
                    <span><?php echo esc_html( $cpt['title'] ); ?></span>
                    <?php if ( ! empty( $cpt['submenus'] ) ) : ?><span class="nextsafar-nav-arrow">▸</span><?php endif; ?>
                </a>
                <?php if ( ! empty( $cpt['submenus'] ) ) : ?><?php nextsafar_render_submenu( $cpt['submenus'] ); ?><?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if ( $ns_can_pages ) : ?>
            <?php
                $is_pages_all_active = H::page( 'nextsafar-pages' ) || H::is_param( 'edit.php', 'post_type', 'page' );
                $is_pages_add_new_active = ( $current_page === 'nextsafar-post-editor' && isset( $_GET['post_type'] ) && sanitize_key( $_GET['post_type'] ) === 'page' );
                $is_pages_has_active_child = $is_pages_all_active || $is_pages_add_new_active;
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $is_pages_has_active_child ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-pages' ) ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $is_pages_all_active ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon"><?php H::icon( 'page' ); ?></span>
                    <span>برگه‌ها</span><span class="nextsafar-nav-arrow">▸</span>
                </a>
                <div class="nextsafar-submenu">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-pages' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_pages_all_active ? 'active' : ''; ?>"><span>همه برگه‌ها</span></a>
                    <?php if ( current_user_can( 'edit_pages' ) ) : ?>
                    <a href="<?php echo esc_url( nextsafar_editor_url( 'page' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_pages_add_new_active ? 'active' : ''; ?>"><span>افزودن برگه</span></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $ns_can_media ) : ?>
            <?php
                $is_media_library_active = H::page( 'nextsafar-media' ) || H::is( 'upload.php' ) || H::is( 'media.php' );
                $is_media_add_new_active = H::is( 'media-new.php' );
                $is_media_has_active_child = $is_media_library_active || $is_media_add_new_active;
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $is_media_has_active_child ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-media' ) ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $is_media_library_active ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon"><?php H::icon( 'media' ); ?></span>
                    <span>رسانه‌ها</span><span class="nextsafar-nav-arrow">▸</span>
                </a>
                <div class="nextsafar-submenu">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-media' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_media_library_active ? 'active' : ''; ?>"><span>کتابخانه</span></a>
                    <?php if ( current_user_can( 'upload_files' ) ) : ?>
                    <a href="<?php echo esc_url( H::url( 'media-new.php' ) ); ?>"
                       class="nextsafar-submenu-item <?php echo $is_media_add_new_active ? 'active' : ''; ?>"><span>افزودن رسانه</span></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $ns_can_comments ) : ?>
            <?php
                $pending_count = get_transient( 'nextsafar_pending_comments_count' );
                if ( false === $pending_count ) {
                    $comment_count = wp_count_comments();
                    $pending_count = isset( $comment_count->moderated ) ? $comment_count->moderated : 0;
                    set_transient( 'nextsafar_pending_comments_count', $pending_count, 2 * MINUTE_IN_SECONDS );
                }
                $pending_count = (int) $pending_count;
                $is_comments_active = H::is( array( 'edit-comments.php', 'comment.php' ) );
            ?>
            <a href="<?php echo esc_url( admin_url( 'edit-comments.php' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_comments_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'comments' ); ?></span>
                <span>دیدگاه‌ها</span>
                <?php if ( $pending_count > 0 ) : ?><span class="nextsafar-nav-badge"><?php echo esc_html( $pending_count ); ?></span><?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- APPEARANCE -->
        <?php if ( $ns_section_appearance ) : ?>
        <div class="nextsafar-nav-section">
            <span class="nextsafar-nav-title">APPEARANCE</span>
            <?php if ( $ns_can_themes ) : $is_themes_active = H::is( array( 'themes.php', 'theme-editor.php', 'customize.php' ) ); ?>
            <a href="<?php echo esc_url( H::url( 'themes.php' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_themes_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'themes' ); ?></span><span>پوسته‌ها</span>
            </a>
            <?php endif; ?>
            <?php if ( $ns_can_menus ) : $is_menus_active = H::is( array( 'nav-menus.php', 'customize.php' ) ); ?>
            <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_menus_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'menu' ); ?></span><span>فهرست‌ها</span>
            </a>
            <?php endif; ?>
            <?php if ( $ns_can_widgets ) : $is_widgets_active = H::is( 'widgets.php' ); ?>
            <a href="<?php echo esc_url( H::url( 'widgets.php' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_widgets_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'widgets' ); ?></span><span>ابزارک‌ها</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- MANAGEMENT -->
        <?php if ( $ns_section_management ) : ?>
        <div class="nextsafar-nav-section">
            <span class="nextsafar-nav-title">MANAGEMENT</span>
            <?php if ( $ns_can_users ) : $is_users_active = H::is( array( 'users.php', 'user-new.php', 'user-edit.php', 'profile.php' ) ); ?>
            <a href="<?php echo esc_url( H::url( 'users.php' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_users_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'users' ); ?></span><span>کاربران</span>
            </a>
            <?php endif; ?>
            <?php if ( $ns_can_plugins ) : $is_plugins_active = H::is( array( 'plugins.php', 'plugin-install.php', 'plugin-editor.php' ) ); ?>
            <a href="<?php echo esc_url( H::url( 'plugins.php' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_plugins_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'plugins' ); ?></span><span>افزونه‌ها</span>
            </a>
            <?php endif; ?>
            <?php if ( $ns_can_updates ) : $is_updates_active = H::page( 'nextsafar-updates' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-updates' ) ); ?>"
               class="nextsafar-nav-item <?php echo $is_updates_active ? 'active' : ''; ?>">
                <span class="nextsafar-nav-icon"><?php H::icon( 'update' ); ?></span><span>بروزرسانی‌ها</span>
            </a>
            <?php endif; ?>
            <?php if ( $ns_can_tools ) :
                $tools_files = array( 'tools.php', 'import.php', 'export.php', 'site-health.php', 'export-personal-data.php', 'erase-personal-data.php' );
                $is_tools_active = H::is( $tools_files );
                $is_tools_main_active = H::is( 'tools.php' );
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $is_tools_active ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( H::url( 'tools.php' ) ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $is_tools_main_active ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon"><?php H::icon( 'tools' ); ?></span>
                    <span>ابزارها</span><span class="nextsafar-nav-arrow">▸</span>
                </a>
                <div class="nextsafar-submenu">
                    <?php
                    $tools_items = array(
                        'tools.php'                => 'ابزارهای موجود',
                        'import.php'               => 'درون‌ریزی',
                        'export.php'               => 'برون‌بری',
                        'site-health.php'          => 'سلامت سایت',
                        'export-personal-data.php' => 'صادر کردن داده‌های شخصی',
                        'erase-personal-data.php'  => 'پاک کردن داده‌های شخصی',
                    );
                    foreach ( $tools_items as $file => $label ) : ?>
                        <a href="<?php echo esc_url( H::url( $file ) ); ?>"
                           class="nextsafar-submenu-item <?php echo H::is( $file ) ? 'active' : ''; ?>"><span><?php echo esc_html( $label ); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ( $ns_can_settings ) :
                $settings_files = array( 'options-general.php', 'options-writing.php', 'options-reading.php', 'options-discussion.php', 'options-media.php', 'options-permalink.php', 'options-privacy.php' );
                $is_settings_active      = H::is( $settings_files );
                $is_settings_main_active = H::is( 'options-general.php' );
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $is_settings_active ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( H::url( 'options-general.php' ) ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $is_settings_main_active ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon"><?php H::icon( 'settings' ); ?></span>
                    <span>تنظیمات</span><span class="nextsafar-nav-arrow">▸</span>
                </a>
                <div class="nextsafar-submenu">
                    <?php
                    $settings_items = array(
                        'options-general.php'    => 'عمومی',
                        'options-writing.php'    => 'نوشتن',
                        'options-reading.php'    => 'خواندن',
                        'options-discussion.php' => 'گفت‌وگو',
                        'options-media.php'      => 'رسانه',
                        'options-permalink.php'  => 'پیوندهای یکتا',
                        'options-privacy.php'    => 'حریم خصوصی',
                    );
                    foreach ( $settings_items as $file => $label ) : ?>
                        <a href="<?php echo esc_url( H::url( $file ) ); ?>"
                           class="nextsafar-submenu-item <?php echo H::is( $file ) ? 'active' : ''; ?>"><span><?php echo esc_html( $label ); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- EXTERNAL PLUGINS (فقط مجازها) -->
        <?php if ( ! empty( $ns_plugin_menus ) ) : ?>
        <div class="nextsafar-nav-section">
            <span class="nextsafar-nav-title">PLUGINS</span>
            <?php foreach ( $ns_plugin_menus as $plugin ) :
                $plugin_active      = false;
                $plugin_main_active = false;
                if ( ! empty( $plugin['page_slug'] ) && H::page( $plugin['page_slug'] ) ) {
                    $plugin_active = true; $plugin_main_active = true;
                }
                if ( ! $plugin_active && isset( $plugin['raw_slug'] ) ) {
                    if ( strpos( $plugin['raw_slug'], 'page=' ) !== false ) {
                        parse_str( parse_url( $plugin['raw_slug'], PHP_URL_QUERY ), $plugin_params );
                        if ( isset( $plugin_params['page'] ) ) {
                            $plugin_page = $plugin_params['page'];
                            if ( $current_page === 'nextsafar-wp-wrapper'
                                && isset( $current_extra_params['page'] )
                                && $current_extra_params['page'] === $plugin_page ) {
                                $plugin_active = true;
                                if ( count( $current_extra_params ) === 1 ) { $plugin_main_active = true; }
                            }
                            if ( isset( $_GET['page'] ) && $_GET['page'] === $plugin_page && $current_page !== 'nextsafar-wp-wrapper' ) {
                                $plugin_active = true; $plugin_main_active = true;
                            }
                        }
                    } elseif ( preg_match( '/^([a-z\-]+\.php)/', $plugin['raw_slug'], $matches ) ) {
                        if ( H::is( $matches[1] ) ) { $plugin_active = true; $plugin_main_active = true; }
                    }
                }
            ?>
            <div class="nextsafar-nav-item-wrapper <?php echo $plugin_active ? 'has-active-child' : ''; ?>">
                <a href="<?php echo esc_url( $plugin['url'] ); ?>"
                   class="nextsafar-nav-item nextsafar-nav-parent <?php echo $plugin_main_active ? 'active' : ''; ?>">
                    <span class="nextsafar-nav-icon">
                        <?php
                        $icon = $plugin['icon'];
                        if ( strpos( $icon, 'custom:' ) === 0 ) {
                            if ( class_exists( 'NextSafar_Icons' ) ) NextSafar_Icons::display_menu_icon( substr( $icon, 7 ), 20 );
                        } elseif ( strpos( $icon, 'cpt:' ) === 0 ) {
                            if ( class_exists( 'NextSafar_Icons' ) ) NextSafar_Icons::display_cpt_icon( substr( $icon, 4 ), 20 );
                        } elseif ( strpos( $icon, 'svg:' ) === 0 ) {
                            if ( class_exists( 'NextSafar_Icons' ) ) NextSafar_Icons::display( substr( $icon, 4 ), 20 );
                        } else {
                            echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
                        }
                        ?>
                    </span>
                    <span><?php echo esc_html( $plugin['title'] ); ?></span>
                    <?php if ( ! empty( $plugin['submenus'] ) ) : ?><span class="nextsafar-nav-arrow">▸</span><?php endif; ?>
                </a>
                <?php if ( ! empty( $plugin['submenus'] ) ) : ?><?php nextsafar_render_submenu( $plugin['submenus'] ); ?><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </nav>
    <div class="nextsafar-sidebar-bottom">
        <div class="nextsafar-sidebar-divider"></div>
        <div class="nextsafar-sidebar-version">NextSafar Admin v<?php echo NEXTSAFAR_VERSION; ?></div>
    </div>
</aside>