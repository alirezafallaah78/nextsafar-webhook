<?php
if ( defined( 'NEXTSAFAR_TOPBAR_RENDERED' ) ) {
    return; // از رندر دوباره هدر در یک ریکوئست جلوگیری می‌کند
}
define( 'NEXTSAFAR_TOPBAR_RENDERED', true );
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// تشخیص نام بخش فعلی
$current_page    = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
$current_target  = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
$current_post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
$current_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
$current_file    = basename( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

$section_names = array(
    'nextsafar-admin'         => 'داشبورد',
    'nextsafar-posts'         => 'نوشته‌ها',
    'nextsafar-pages'         => 'برگه‌ها',
    'nextsafar-media'         => 'رسانه‌ها',
    'nextsafar-categories'    => 'دسته‌بندی‌ها',
    'nextsafar-updates'       => 'بروزرسانی‌ها',
    'nextsafar-cpt'           => '', // نام از post_type گرفته می‌شود
    'nextsafar-cpt-taxonomy'  => '', // نام از taxonomy گرفته می‌شود
);

// نقشه فایل‌های وردپرس (بدون edit-tags.php چون داینامیک تشخیص داده می‌شود)
$wp_file_names = array(
    'edit-comments.php'     => 'دیدگاه‌ها',
    'comment.php'           => 'ویرایش دیدگاه',
    'users.php'             => 'کاربران',
    'user-new.php'          => 'افزودن کاربر',
    'user-edit.php'         => 'ویرایش کاربر',
    'profile.php'           => 'شناسنامه',
    'plugins.php'           => 'افزونه‌ها',
    'plugin-install.php'    => 'افزودن افزونه',
    'themes.php'            => 'پوسته‌ها',
    'nav-menus.php'         => 'فهرست‌ها',
    'widgets.php'           => 'ابزارک‌ها',
    'tools.php'             => 'ابزارها',
    'import.php'            => 'درون‌ریزی',
    'export.php'            => 'برون‌بری',
    'site-health.php'       => 'سلامت سایت',
    'options-general.php'   => 'تنظیمات عمومی',
    'options-writing.php'   => 'تنظیمات نوشتن',
    'options-reading.php'   => 'تنظیمات خواندن',
    'options-discussion.php'=> 'تنظیمات گفت‌وگو',
    'options-media.php'     => 'تنظیمات رسانه',
    'options-permalink.php' => 'پیوندهای یکتا',
    'options-privacy.php'   => 'حریم خصوصی',
    'post-new.php'          => 'افزودن نوشته',
    'post.php'              => 'ویرایش نوشته',
    'media-new.php'         => 'افزودن رسانه',
    'edit.php'              => 'نوشته‌ها',
);

// 🎯 تابع کمکی برای تشخیص نوع taxonomy
$get_taxonomy_type_label = function( $tax_obj, $post_type_obj ) {
    if ( ! $tax_obj ) {
        return 'دسته‌بندی‌ها';
    }
    
    // اگر taxonomy hierarchical است، یعنی دسته‌بندی
    $type_label = $tax_obj->hierarchical ? 'دسته‌بندی' : 'برچسب';
    
    // اضافه کردن نام post type اگر وجود دارد
    if ( $post_type_obj ) {
        return $type_label . ' ' . $post_type_obj->labels->singular_name;
    }
    
    // اگر post type نبود، از نام خود taxonomy استفاده کن
    return $tax_obj->labels->name;
};

// تشخیص نام بخش
$section_name = 'داشبورد';

if ( ! empty( $current_page ) && isset( $section_names[ $current_page ] ) ) {
    // 🆕 اگر صفحه CPT است، نام را از post_type بگیر
    if ( 'nextsafar-cpt' === $current_page && ! empty( $current_post_type ) ) {
        $post_type_obj = get_post_type_object( $current_post_type );
        if ( $post_type_obj ) {
            $section_name = $post_type_obj->labels->name;
        } else {
            $section_name = ucfirst( $current_post_type );
        }
    }
    // 🆕 اگر صفحه Taxonomy است، نام را از taxonomy بگیر
    elseif ( 'nextsafar-cpt-taxonomy' === $current_page && ! empty( $current_taxonomy ) ) {
        $tax_obj = get_taxonomy( $current_taxonomy );
        if ( $tax_obj ) {
            $section_name = $tax_obj->labels->name;
        } else {
            $section_name = ucfirst( $current_taxonomy );
        }
    }
    else {
        $section_name = $section_names[ $current_page ];
    }
} 
elseif ( ! empty( $current_target ) && isset( $wp_file_names[ $current_target ] ) ) {
    $section_name = $wp_file_names[ $current_target ];
    
    // برای CPT ها
    if ( $current_target === 'edit.php' && ! empty( $current_post_type ) ) {
        $post_type_obj = get_post_type_object( $current_post_type );
        if ( $post_type_obj ) {
            $section_name = $post_type_obj->labels->name;
        }
    } elseif ( $current_target === 'post-new.php' && ! empty( $current_post_type ) ) {
        $post_type_obj = get_post_type_object( $current_post_type );
        if ( $post_type_obj ) {
            $section_name = 'افزودن ' . $post_type_obj->labels->singular_name;
        }
    }
    // 🆕 برای Taxonomy (edit-tags.php و term.php) در target
    elseif ( in_array( $current_target, array( 'edit-tags.php', 'term.php' ), true ) && ! empty( $current_taxonomy ) ) {
        $tax_obj = get_taxonomy( $current_taxonomy );
        $post_type_obj = ! empty( $current_post_type ) ? get_post_type_object( $current_post_type ) : null;
        $section_name = $get_taxonomy_type_label( $tax_obj, $post_type_obj );
    }
} 
elseif ( isset( $wp_file_names[ $current_file ] ) ) {
    $section_name = $wp_file_names[ $current_file ];
    
    if ( $current_file === 'edit.php' && ! empty( $current_post_type ) ) {
        $post_type_obj = get_post_type_object( $current_post_type );
        if ( $post_type_obj ) {
            $section_name = $post_type_obj->labels->name;
        }
    } elseif ( $current_file === 'post-new.php' && ! empty( $current_post_type ) ) {
        $post_type_obj = get_post_type_object( $current_post_type );
        if ( $post_type_obj ) {
            $section_name = 'افزودن ' . $post_type_obj->labels->singular_name;
        }
    }
}
// 🆕 تشخیص ویژه برای edit-tags.php و term.php (صفحات native وردپرس)
elseif ( in_array( $current_file, array( 'edit-tags.php', 'term.php' ), true ) ) {
    $tax_obj = ! empty( $current_taxonomy ) ? get_taxonomy( $current_taxonomy ) : null;
    $post_type_obj = ! empty( $current_post_type ) ? get_post_type_object( $current_post_type ) : null;
    
    $section_name = $get_taxonomy_type_label( $tax_obj, $post_type_obj );
}

// اطلاعات کاربر
$current_user   = wp_get_current_user();
$user_name      = $current_user->display_name;
$user_email     = $current_user->user_email;
$user_role      = '';
if ( ! empty( $current_user->roles ) ) {
    $role_names = array(
        'administrator' => 'مدیر کل',
        'editor'        => 'ویرایشگر',
        'author'        => 'نویسنده',
        'contributor'   => 'مشارکت‌کننده',
        'subscriber'    => 'مشترک',
    );
    $role = $current_user->roles[0];
    $user_role = isset( $role_names[ $role ] ) ? $role_names[ $role ] : ucfirst( $role );
}

// آواتار کاربر
$avatar_url = get_avatar_url( $current_user->ID, array( 'size' => 80 ) );

// 🆕 محاسبه pending_count در همین scope
$pending_comments = 0;
$comment_count = wp_count_comments();
if ( $comment_count && isset( $comment_count->moderated ) ) {
    $pending_comments = (int) $comment_count->moderated;
}

// 🚀 کش شمارنده کامنت‌ها — ۲ دقیقه
$pending_count_cached = get_transient( 'nextsafar_pending_comments_count' );
if ( false !== $pending_count_cached ) {
    $pending_comments = (int) $pending_count_cached;
} else {
    set_transient( 'nextsafar_pending_comments_count', $pending_comments, 2 * MINUTE_IN_SECONDS );
}
$pending_count = $pending_comments;
// شمارنده آپدیت‌ها
$update_data = wp_get_update_data();

// 🚀 کش شمارنده آپدیت‌ها — ۵ دقیقه
$pending_updates = get_transient( 'nextsafar_pending_updates_count' );
if ( false === $pending_updates ) {
    $pending_updates = isset( $update_data['counts']['total'] ) ? (int) $update_data['counts']['total'] : 0;
    set_transient( 'nextsafar_pending_updates_count', $pending_updates, 5 * MINUTE_IN_SECONDS );
}
$pending_updates = (int) $pending_updates;

// ✅ حالا هر دو متغیر تعریف شده‌اند
$total_notifications = $pending_comments + $pending_updates;

?>
<header class="nextsafar-topbar">

    <div class="topbar-section-title">
        <h1 class="section-name"><?php echo esc_html( $section_name ); ?></h1>
    </div>

    <div class="topbar-actions">

        <!-- Search -->
        <div class="topbar-search">
            <span class="search-icon">
                <?php NextSafar_Icons::display( 'search', 16 ); ?>
            </span>
            <input type="text" id="nextsafar-global-search" class="search-input" placeholder="جستجو در سایت..." autocomplete="off">
            <div class="search-results" id="nextsafar-search-results" style="display: none;"></div>
        </div>

        <!-- Notification -->
        <div class="topbar-notification">
            <button type="button" class="notification-btn" id="nextsafar-notification-btn">
                <span class="topbar-icon">
                    <?php NextSafar_Icons::display( 'bell', 20 ); ?>
                </span>
                <?php if ( $total_notifications > 0 ) : ?>
                    <span class="notification-badge"><?php echo esc_html( $total_notifications ); ?></span>
                <?php endif; ?>
            </button>

            <div class="notification-dropdown" id="nextsafar-notification-dropdown">
                <div class="notification-header">
                    <strong>اعلان‌ها</strong>
                    <span class="notification-count"><?php echo esc_html( $total_notifications ); ?> جدید</span>
                </div>

                <div class="notification-list">
                    <?php if ( $pending_comments > 0 ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'edit-comments.php' ) ); ?>" class="notification-item">
                            <div class="notification-icon-wrap nextsafar-notif-comments">
                                <?php NextSafar_Icons::display( 'comments', 18 ); ?>
                            </div>
                            <div class="notification-content">
                                <strong><?php echo esc_html( $pending_comments ); ?> دیدگاه منتظر تأیید</strong>
                                <span>برای بررسی کلیک کنید</span>
                            </div>
                            <span class="notification-arrow">
                                <?php NextSafar_Icons::display( 'chevron-left', 14 ); ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ( $pending_updates > 0 ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-updates' ) ); ?>" class="notification-item">
                            <div class="notification-icon-wrap nextsafar-notif-updates">
                                <?php NextSafar_Icons::display( 'update', 18 ); ?>
                            </div>
                            <div class="notification-content">
                                <strong><?php echo esc_html( $pending_updates ); ?> بروزرسانی موجود</strong>
                                <span>پلاگین‌ها و هسته وردپرس</span>
                            </div>
                            <span class="notification-arrow">
                                <?php NextSafar_Icons::display( 'chevron-left', 14 ); ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ( $total_notifications === 0 ) : ?>
                        <div class="notification-empty">
                            <div class="notification-empty-icon">
                                <?php NextSafar_Icons::display( 'check', 22 ); ?>
                            </div>
                            <p>اعلان جدیدی وجود ندارد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<!-- 🆕 Screen Options Button -->
<?php
$show_screen_options = true;

// برای iframe ها نمایش نده
if ( isset( $_GET['nextsafar_iframe'] ) ) {
    $show_screen_options = false;
}
?>

<?php if ( $show_screen_options ) : ?>
<div class="topbar-screen-options">
    <button type="button" class="screen-options-btn" id="nextsafar-screen-options-btn" title="تنظیمات صفحه">
        <span class="topbar-icon">
            <?php NextSafar_Icons::display( 'settings', 18 ); ?>
        </span>
    </button>
    <div class="screen-options-dropdown" id="nextsafar-screen-options-dropdown">
        <div class="screen-options-header">
            <strong>⚙️ تنظیمات صفحه</strong>
        </div>
        <div class="screen-options-content" id="nextsafar-screen-options-content">
            <div class="screen-options-loading">
                <div class="nextsafar-spinner"></div>
                <span>در حال بارگذاری...</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>       

        <!-- User Menu -->
        <div class="topbar-user-menu" id="nextsafar-user-menu">
            <button type="button" class="user-menu-trigger" id="nextsafar-user-trigger">
                <div class="user-avatar">
                    <?php if ( $avatar_url ) : ?>
                        <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user_name ); ?>">
                    <?php else : ?>
                        <?php NextSafar_Icons::display( 'user', 20 ); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info-text">
                    <strong><?php echo esc_html( $user_name ); ?></strong>
                    <small><?php echo esc_html( $user_role ); ?></small>
                </div>
                <span class="user-arrow">
                    <?php NextSafar_Icons::display( 'chevron-down', 12 ); ?>
                </span>
            </button>

            <div class="user-dropdown" id="nextsafar-user-dropdown">
                <div class="user-dropdown-header">
                    <div class="user-avatar-large">
                        <?php if ( $avatar_url ) : ?>
                            <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user_name ); ?>">
                        <?php else : ?>
                            <?php NextSafar_Icons::display( 'user', 24 ); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <strong><?php echo esc_html( $user_name ); ?></strong>
                        <span><?php echo esc_html( $user_email ); ?></span>
                    </div>
                </div>

                <div class="user-dropdown-menu">
                    <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>" class="user-dropdown-item">
                        <span class="dropdown-icon">
                            <?php NextSafar_Icons::display( 'profile', 14 ); ?>
                        </span>
                        <span>شناسنامه من</span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" class="user-dropdown-item">
                        <span class="dropdown-icon">
                            <?php NextSafar_Icons::display( 'settings', 14 ); ?>
                        </span>
                        <span>تنظیمات</span>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="user-dropdown-item" target="_blank">
                        <span class="dropdown-icon">
                            <?php NextSafar_Icons::display( 'view-site', 14 ); ?>
                        </span>
                        <span>مشاهده سایت</span>
                    </a>
                </div>

                <div class="user-dropdown-divider"></div>

                <div class="user-dropdown-menu">
                    <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="user-dropdown-item user-dropdown-logout">
                        <span class="dropdown-icon">
                            <?php NextSafar_Icons::display( 'logout', 14 ); ?>
                        </span>
                        <span>خروج از حساب</span>
                    </a>
                </div>
            </div>
        </div>

    </div>

</header>