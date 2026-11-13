<?php
/**
 * NextSafar Layout Wrapper - Full Version with Sidebar
 *
 * @package NextSafarAdmin
 * @since 2.0.0
 */

namespace NextSafar\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Layout {

    public static function render(callable $content_callback, string $page_title = ''): void {
        ?>
        <div class="ns-admin-layout">
            <?php self::render_sidebar(); ?>
            
            <div class="ns-main-content">
                <?php self::render_topbar($page_title); ?>
                
                <main class="ns-content-body">
                    <?php call_user_func($content_callback); ?>
                </main>
                
                <?php self::render_footer(); ?>
            </div>
        </div>
        
        <?php self::render_styles(); ?>
        <?php self::render_scripts(); ?>
        <?php
    }

    private static function render_sidebar(): void {
        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        ?>
        <aside class="ns-sidebar" id="ns-sidebar">
            <div class="ns-sidebar-header">
                <div class="ns-sidebar-logo">
                    <div class="ns-logo-icon">🌍</div>
                    <div class="ns-logo-text">
                        <strong>نکست‌سفر</strong>
                        <small>پنل مدیریت</small>
                    </div>
                </div>
                <button class="ns-sidebar-toggle" id="ns-sidebar-toggle" title="جمع کردن منو">
                    ◀
                </button>
            </div>
            
            <nav class="ns-sidebar-nav">
                <!-- داشبورد -->
                <div class="ns-nav-section">
                    <div class="ns-nav-title">اصلی</div>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-admin')); ?>" 
                       class="ns-nav-item <?php echo $current_page === 'nextsafar-admin' ? 'active' : ''; ?>">
                        <span class="ns-nav-icon">📊</span>
                        <span class="ns-nav-text">داشبورد</span>
                    </a>
                </div>

                <!-- تولید محتوا -->
                <div class="ns-nav-section">
                    <div class="ns-nav-title">تولید محتوا</div>
                    
                    <!-- تولید پست -->
                    <?php 
                    $pg_active = in_array($current_page, ['nextsafar-post-generator', 'nextsafar-pg-settings']);
                    ?>
                    <div class="ns-nav-item-wrapper <?php echo $pg_active ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-post-generator')); ?>" 
                           class="ns-nav-item ns-nav-parent <?php echo $pg_active ? 'active' : ''; ?>">
                            <span class="ns-nav-icon">⚡</span>
                            <span class="ns-nav-text">تولید پست</span>
                            <span class="ns-nav-arrow">▾</span>
                        </a>
                        <div class="ns-submenu <?php echo $pg_active ? 'open' : ''; ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-post-generator')); ?>" 
                               class="ns-submenu-item <?php echo $current_page === 'nextsafar-post-generator' ? 'active' : ''; ?>">
                                تولید پست
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-pg-settings')); ?>" 
                               class="ns-submenu-item <?php echo $current_page === 'nextsafar-pg-settings' ? 'active' : ''; ?>">
                                تنظیمات API
                            </a>
                        </div>
                    </div>

                    <!-- اخبار -->
                    <?php 
                    $news_active = in_array($current_page, ['nextsafar-news', 'nextsafar-news-manual', 'nextsafar-news-filter', 'nextsafar-news-settings']);
                    ?>
                    <div class="ns-nav-item-wrapper <?php echo $news_active ? 'has-active-child' : ''; ?>">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-news')); ?>" 
                           class="ns-nav-item ns-nav-parent <?php echo $news_active ? 'active' : ''; ?>">
                            <span class="ns-nav-icon">📰</span>
                            <span class="ns-nav-text">اخبار</span>
                            <span class="ns-nav-arrow">▾</span>
                        </a>
                        <div class="ns-submenu <?php echo $news_active ? 'open' : ''; ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-news')); ?>" 
                               class="ns-submenu-item <?php echo $current_page === 'nextsafar-news' ? 'active' : ''; ?>">
                                آنالیز و پرووایدرها
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-news-manual')); ?>" 
                               class="ns-submenu-item <?php echo $current_page === 'nextsafar-news-manual' ? 'active' : ''; ?>">
                                تولید دستی
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-news-filter')); ?>" 
                               class="ns-submenu-item <?php echo $current_page === 'nextsafar-news-filter' ? 'active' : ''; ?>">
                                فیلترها
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-news-settings')); ?>" 
                               class="ns-submenu-item <?php echo $current_page === 'nextsafar-news-settings' ? 'active' : ''; ?>">
                                تنظیمات
                            </a>
                        </div>
                    </div>

                    <!-- دسترسی‌ها -->
                    <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nextsafar-role-access')); ?>" 
                       class="ns-nav-item <?php echo $current_page === 'nextsafar-role-access' ? 'active' : ''; ?>">
                        <span class="ns-nav-icon">🔐</span>
                        <span class="ns-nav-text">دسترسی‌ها</span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- محتوا -->
                <div class="ns-nav-section">
                    <div class="ns-nav-title">محتوا</div>
                    
                    <?php 
                    $cpt_types = ['hotel', 'airport', 'destination', 'restaurant', 'hospital', 'visa', 'travelguide', 'travelnews'];
                    $cpt_labels = [
                        'hotel' => ['هتل‌ها', '🏨'],
                        'airport' => ['فرودگاه‌ها', '✈️'],
                        'destination' => ['مقاصد', '📍'],
                        'restaurant' => ['رستوران‌ها', '🍽️'],
                        'hospital' => ['بیمارستان‌ها', '🏥'],
                        'visa' => ['ویزاها', '🛂'],
                        'travelguide' => ['راهنمای سفر', '🧭'],
                        'travelnews' => ['اخبار سفر', '📰'],
                    ];
                    
                    foreach ($cpt_types as $cpt):
                        if (!post_type_exists($cpt)) continue;
                        $label = $cpt_labels[$cpt] ?? [ucfirst($cpt), '📄'];
                        $active = (isset($_GET['post_type']) && $_GET['post_type'] === $cpt);
                    ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $cpt)); ?>" 
                       class="ns-nav-item <?php echo $active ? 'active' : ''; ?>">
                        <span class="ns-nav-icon"><?php echo $label[1]; ?></span>
                        <span class="ns-nav-text"><?php echo esc_html($label[0]); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- تنظیمات وردپرس -->
                <div class="ns-nav-section">
                    <div class="ns-nav-title">وردپرس</div>
                    
                    <?php
                    $wp_menus = [
                        ['nav-menus.php', 'فهرست‌ها', '📋'],
                        ['widgets.php', 'ابزارک‌ها', '🧩'],
                        ['themes.php', 'پوسته‌ها', '🎨'],
                        ['plugins.php', 'افزونه‌ها', '🔌'],
                        ['users.php', 'کاربران', '👥'],
                        ['options-general.php', 'تنظیمات', '⚙️'],
                    ];
                    
                    foreach ($wp_menus as $menu):
                        $wrapper_url = WP_Pages_Wrapper::wrapper_url($menu[0]);
                    ?>
                    <a href="<?php echo esc_url($wrapper_url); ?>" class="ns-nav-item">
                        <span class="ns-nav-icon"><?php echo $menu[2]; ?></span>
                        <span class="ns-nav-text"><?php echo esc_html($menu[1]); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </nav>
            
            <div class="ns-sidebar-footer">
                <small>NextSafar v<?php echo NEXTSAFAR_VERSION; ?></small>
            </div>
        </aside>
        <?php
    }

    private static function render_topbar(string $page_title = ''): void {
        $current_user = wp_get_current_user();
        ?>
        <header class="ns-topbar">
            <div class="ns-topbar-left">
                <button class="ns-topbar-menu-btn" id="ns-topbar-menu-btn">☰</button>
                <h1 class="ns-page-title"><?php echo esc_html($page_title ?: 'نکست‌سفر'); ?></h1>
            </div>
            
            <div class="ns-topbar-right">
                <div class="ns-user-menu" id="ns-user-menu">
                    <button class="ns-user-btn">
                        <span class="ns-user-avatar">
                            <?php echo get_avatar($current_user->ID, 32); ?>
                        </span>
                        <span class="ns-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                        <span class="ns-user-arrow">▾</span>
                    </button>
                    
                    <div class="ns-user-dropdown" id="ns-user-dropdown">
                        <a href="<?php echo esc_url(admin_url('profile.php')); ?>">👤 شناسنامه</a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">🔗 مشاهده سایت</a>
                        <div class="ns-user-divider"></div>
                        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">🚪 خروج</a>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }

    private static function render_footer(): void {
        ?>
        <footer class="ns-footer">
            <p>© <?php echo date('Y'); ?> NextSafar - پنل مدیریت سفر بعدی ایرانیان</p>
        </footer>
        <?php
    }

    private static function render_styles(): void {
        ?>
        <style>
        /* Layout Base */
        .ns-admin-layout {
            display: flex;
            min-height: 100vh;
            background: #f0f0f1;
        }
        
        /* Sidebar */
        .ns-sidebar {
            width: 260px;
            background: #1d2327;
            color: white;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
            z-index: 100;
        }
        
        .ns-sidebar.collapsed {
            width: 70px;
        }
        
        .ns-sidebar.collapsed .ns-nav-text,
        .ns-sidebar.collapsed .ns-logo-text,
        .ns-sidebar.collapsed .ns-nav-title,
        .ns-sidebar.collapsed .ns-nav-arrow {
            display: none;
        }
        
        .ns-sidebar.collapsed .ns-nav-item {
            justify-content: center;
            padding: 12px;
        }
        
        .ns-sidebar.collapsed .ns-submenu {
            display: none !important;
        }
        
        .ns-sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #2c3338;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .ns-sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ns-logo-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #2271b1, #135e96);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ns-logo-text {
            line-height: 1.2;
        }
        
        .ns-logo-text strong {
            display: block;
            font-size: 15px;
        }
        
        .ns-logo-text small {
            font-size: 11px;
            opacity: 0.7;
        }
        
        .ns-sidebar-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 14px;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 4px;
            opacity: 0.7;
            transition: all 0.2s;
        }
        
        .ns-sidebar-toggle:hover {
            background: #2c3338;
            opacity: 1;
        }
        
        /* Navigation */
        .ns-sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }
        
        .ns-nav-section {
            margin-bottom: 20px;
        }
        
        .ns-nav-title {
            padding: 8px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
            font-weight: 600;
        }
        
        .ns-nav-item {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            color: #c3c4c7;
            text-decoration: none;
            transition: all 0.2s;
            gap: 10px;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        
        .ns-nav-item:hover {
            background: #2c3338;
            color: white;
        }
        
        .ns-nav-item.active {
            background: #2271b1;
            color: white;
            border-left-color: #72aee6;
        }
        
        .ns-nav-icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .ns-nav-text {
            flex: 1;
            font-size: 13px;
        }
        
        .ns-nav-arrow {
            font-size: 10px;
            transition: transform 0.2s;
        }
        
        .ns-nav-item-wrapper.has-active-child .ns-nav-arrow {
            transform: rotate(180deg);
        }
        
        /* Submenu - با hover باز می‌شود */
        .ns-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: #14181b;
        }
        
        .ns-submenu.open,
        .ns-nav-item-wrapper:hover > .ns-submenu {
            max-height: 500px;
        }
        
        .ns-submenu-item {
            display: block;
            padding: 8px 20px 8px 55px;
            color: #a7aaad;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .ns-submenu-item:hover {
            background: #2c3338;
            color: white;
            border-left-color: #2271b1;
        }
        
        .ns-submenu-item.active {
            color: #72aee6;
            background: #2c3338;
            border-left-color: #72aee6;
        }
        
        .ns-sidebar-footer {
            padding: 10px 20px;
            border-top: 1px solid #2c3338;
            text-align: center;
            color: #646970;
            font-size: 11px;
        }
        
        /* Main Content */
        .ns-main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        /* Topbar */
        .ns-topbar {
            background: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 90;
        }
        
        .ns-topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .ns-topbar-menu-btn {
            background: none;
            border: 1px solid #c3c4c7;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: none;
        }
        
        .ns-page-title {
            margin: 0;
            font-size: 20px;
            color: #1d2327;
            font-weight: 600;
        }
        
        .ns-user-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: 1px solid #c3c4c7;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .ns-user-btn:hover {
            background: #f6f7f7;
        }
        
        .ns-user-avatar img {
            border-radius: 50%;
            width: 32px;
            height: 32px;
        }
        
        .ns-user-name {
            font-size: 13px;
            color: #1d2327;
        }
        
        .ns-user-menu {
            position: relative;
        }
        
        .ns-user-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            min-width: 180px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            display: none;
            margin-top: 5px;
            z-index: 100;
        }
        
        .ns-user-dropdown.open {
            display: block;
        }
        
        .ns-user-dropdown a {
            display: block;
            padding: 10px 15px;
            color: #1d2327;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.2s;
        }
        
        .ns-user-dropdown a:hover {
            background: #f0f0f1;
        }
        
        .ns-user-divider {
            height: 1px;
            background: #c3c4c7;
            margin: 5px 0;
        }
        
        /* Content Body */
        .ns-content-body {
            flex: 1;
            padding: 20px;
            background: #f0f0f1;
        }
        
        .nextsafar-wrap {
            max-width: 100%;
        }
        
        /* Footer */
        .ns-footer {
            background: white;
            padding: 15px 20px;
            text-align: center;
            color: #646970;
            font-size: 12px;
            border-top: 1px solid #c3c4c7;
        }
        
        /* Responsive */
        @media (max-width: 782px) {
            .ns-topbar-menu-btn {
                display: block;
            }
            
            .ns-sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                bottom: 0;
                z-index: 9999;
                box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            }
            
            .ns-sidebar.mobile-open {
                left: 0;
            }
            
            .ns-user-name {
                display: none;
            }
        }
        </style>
        <?php
    }

    private static function render_scripts(): void {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Sidebar toggle
            $('#ns-sidebar-toggle').on('click', function() {
                $('#ns-sidebar').toggleClass('collapsed');
                $(this).text($('#ns-sidebar').hasClass('collapsed') ? '▶' : '◀');
                localStorage.setItem('ns-sidebar-collapsed', $('#ns-sidebar').hasClass('collapsed') ? '1' : '0');
            });
            
            // Load saved state
            if (localStorage.getItem('ns-sidebar-collapsed') === '1') {
                $('#ns-sidebar').addClass('collapsed');
                $('#ns-sidebar-toggle').text('▶');
            }
            
            // Mobile menu toggle
            $('#ns-topbar-menu-btn').on('click', function() {
                $('#ns-sidebar').toggleClass('mobile-open');
            });
            
            // Close mobile menu on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#ns-sidebar, #ns-topbar-menu-btn').length) {
                    $('#ns-sidebar').removeClass('mobile-open');
                }
            });
            
            // ✅ Hover functionality برای زیرمنوها
            $('.ns-nav-item-wrapper').on('mouseenter', function() {
                const submenu = $(this).find('.ns-submenu');
                if (submenu.length) {
                    submenu.addClass('open');
                }
            });
            
            $('.ns-nav-item-wrapper').on('mouseleave', function() {
                const submenu = $(this).find('.ns-submenu');
                // اگر active child باشد، باز نگه می‌داریم
                if (!$(this).hasClass('has-active-child')) {
                    submenu.removeClass('open');
                }
            });
            
            // Click روی parent برای toggle (برای موبایل)
            $('.ns-nav-parent').on('click', function(e) {
                if ($(window).width() <= 782) {
                    e.preventDefault();
                    $(this).closest('.ns-nav-item-wrapper').toggleClass('has-active-child');
                    $(this).closest('.ns-nav-item-wrapper').find('.ns-submenu').toggleClass('open');
                }
            });
            
            // User menu toggle
            $('#ns-user-menu').on('mouseenter', function() {
                $('#ns-user-dropdown').addClass('open');
            });
            
            $('#ns-user-menu').on('mouseleave', function() {
                $('#ns-user-dropdown').removeClass('open');
            });
        });
        </script>
        <?php
    }

    public static function should_use_new_layout(): bool {
        return true;
    }
}