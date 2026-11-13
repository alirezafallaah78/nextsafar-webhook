<?php
/**
 * 🔐 NextSafar Role-Based Access Control (RBAC)
 * مدیریت دسترسی منوها و قابلیت‌ها بر اساس نقش کاربری
 *
 * @package NextSafar_Admin
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NextSafar_Role_Access {

    const OPTION_KEY         = 'nextsafar_role_access';
    const EXCLUDED_ROLES     = array( 'subscriber' );
    const SUPER_ADMIN_ROLES  = array( 'administrator' );

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_access_page' ), 99 );
        add_action( 'admin_menu', array( $this, 'restrict_menus' ), 9999 );
        add_filter( 'user_has_cap', array( $this, 'restrict_capabilities' ), 10, 4 );
        add_filter( 'pre_delete_post', array( $this, 'prevent_deletion' ), 10, 2 );
        add_filter( 'pre_trash_post', array( $this, 'prevent_deletion' ), 10, 2 );

        add_action( 'wp_ajax_nextsafar_save_role_access', array( $this, 'ajax_save_role_access' ) );
        add_action( 'wp_ajax_nextsafar_get_role_access', array( $this, 'ajax_get_role_access' ) );
        add_action( 'wp_ajax_nextsafar_reset_role_access', array( $this, 'ajax_reset_role_access' ) );
        add_filter( 'login_redirect', array( $this, 'login_redirect_to_panel' ), 10, 3 );

        add_action( 'admin_init', array( $this, 'restrict_page_access' ), 1 );
        add_action( 'admin_init', array( $this, 'maybe_set_defaults' ) );
    }

    /* =========================================================
    📋 صفحه مدیریت دسترسی‌ها
    ========================================================= */

    public function register_access_page() {
        add_submenu_page(
            'nextsafar-admin',
            'مدیریت دسترسی‌ها',
            '🔐 دسترسی‌ها',
            'manage_options',
            'nextsafar-role-access',
            array( $this, 'render_access_page' )
        );
    }

    public function render_access_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
        }
        require NEXTSAFAR_ADMIN_PATH . 'admin/pages/role-access.php';
    }

    /* =========================================================
    🎯 جمع‌آوری منوها (نسخه کامل و پایدار)
    ========================================================= */

    /**
     * گرفتن تمام نقش‌های قابل ویرایش
     */
    public function get_editable_roles() {
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        $roles = array();
        foreach ( $wp_roles->roles as $slug => $role ) {
            if ( in_array( $slug, self::EXCLUDED_ROLES, true ) ) {
                continue;
            }

            $user_query = new WP_User_Query( array( 'role' => $slug, 'count_total' => true ) );

            $roles[ $slug ] = array(
                'name'       => translate_user_role( $role['name'] ),
                'is_super'   => in_array( $slug, self::SUPER_ADMIN_ROLES, true ),
                'user_count' => $user_query->get_total(),
            );
        }
        return $roles;
    }

    /**
     * گرفتن تنظیمات یک نقش
     */
    public function get_role_settings( $role_slug ) {
        $all_settings = get_option( self::OPTION_KEY, array() );
        return isset( $all_settings[ $role_slug ] ) ? $all_settings[ $role_slug ] : $this->get_default_role_settings( $role_slug );
    }

    /**
     * تنظیمات پیش‌فرض یک نقش
     */
    public function get_default_role_settings( $role_slug ) {
        return array(
            'menus'      => array(),
            'can_delete' => in_array( $role_slug, array( 'administrator', 'editor' ), true ),
            'is_super'   => in_array( $role_slug, self::SUPER_ADMIN_ROLES, true ),
        );
    }

    public function save_role_settings( $role_slug, $settings ) {
        $all_settings = get_option( self::OPTION_KEY, array() );
        $all_settings[ $role_slug ] = $settings;
        return update_option( self::OPTION_KEY, $all_settings );
    }

    /**
     * ✅ حذف: دیگر تنظیمات پیش‌فرض ذخیره نمی‌کنیم
     * فقط وقتی ادمین صریحاً ذخیره کرد، محدودیت اعمال می‌شود
     */
    public function maybe_set_defaults() {
        // دیگر هیچ کاری انجام نمی‌دهیم
        // محدودیت فقط وقتی اعمال می‌شود که ادمین صریحاً ذخیره کند
    }

    /**
     * 🎯 اطمینان از لود شدن منوهای وردپرس
     * این متد را قبل از دسترسی به $menu و $submenu صدا بزنید
     */
    private function ensure_menus_loaded() {
        global $menu, $submenu;

        // اگر منوها از قبل لود شده‌اند، برگرد
        if ( ! empty( $menu ) ) {
            return;
        }

        // در AJAX، گاهی منوها لود نشده‌اند. شبیه‌سازی می‌کنیم.
        if ( wp_doing_ajax() || is_admin() ) {
            // فراخوانی مجدد admin_menu hook با priority پایین برای اطمینان از لود منوها
            if ( ! did_action( 'admin_menu' ) ) {
                do_action( 'admin_menu' );
            }
        }

        // اگر هنوز لود نشدند، fallback استفاده کن
        if ( empty( $menu ) ) {
            require_once ABSPATH . 'wp-admin/includes/menu.php';
        }
    }

    /**
     * 🎯 گرفتن لیست منوها - مرتب‌شده مطابق سایدبار
     * ترتیب: اصلی → محتوا (شامل پست تایپ‌ها) → ظاهر → مدیریت → پلاگین‌ها
     */
    public function get_all_menus() {
        $all_menus  = array();
        $seen_slugs = array();

        // ──────────────────────────────────────────
        // ۱. بخش اصلی (داشبورد + دسترسی‌ها)
        // ──────────────────────────────────────────
        foreach ( $this->get_main_section_menus() as $menu ) {
            if ( ! isset( $seen_slugs[ $menu['slug'] ] ) ) {
                $all_menus[] = $menu;
                $seen_slugs[ $menu['slug'] ] = true;
            }
        }

        // ──────────────────────────────────────────
        // ۲. بخش محتوا - منوهای ثابت
        // ──────────────────────────────────────────
        foreach ( $this->get_content_fixed_menus() as $menu ) {
            if ( ! isset( $seen_slugs[ $menu['slug'] ] ) ) {
                $all_menus[] = $menu;
                $seen_slugs[ $menu['slug'] ] = true;
            }
        }

        // ──────────────────────────────────────────
        // ۳. بخش محتوا - پست تایپ‌های سفارشی ⭐
        // ──────────────────────────────────────────
        foreach ( $this->get_panel_cpt_menus( $seen_slugs ) as $menu ) {
            $all_menus[] = $menu;
        }

        // ──────────────────────────────────────────
        // ۴. بخش ظاهر
        // ──────────────────────────────────────────
        foreach ( $this->get_appearance_menus() as $menu ) {
            if ( ! isset( $seen_slugs[ $menu['slug'] ] ) ) {
                $all_menus[] = $menu;
                $seen_slugs[ $menu['slug'] ] = true;
            }
        }

        // ──────────────────────────────────────────
        // ۵. بخش مدیریت
        // ──────────────────────────────────────────
        foreach ( $this->get_management_menus() as $menu ) {
            if ( ! isset( $seen_slugs[ $menu['slug'] ] ) ) {
                $all_menus[] = $menu;
                $seen_slugs[ $menu['slug'] ] = true;
            }
        }

        // ──────────────────────────────────────────
        // ۶. پلاگین‌ها (آخر لیست) ⭐
        // ──────────────────────────────────────────
        foreach ( $this->get_panel_plugin_menus( $seen_slugs ) as $menu ) {
            $all_menus[] = $menu;
        }

        return $all_menus;
    }

    /**
     * 📌 بخش اصلی
     */
    private function get_main_section_menus() {
        return array(
            array(
                'slug'       => 'admin.php?page=nextsafar-admin',
                'title'      => 'داشبورد',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-dashboard',
                'type'       => 'panel',
                'section'    => 'MAIN',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-role-access',
                'title'      => 'مدیریت دسترسی‌ها',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-lock',
                'type'       => 'panel',
                'section'    => 'MAIN',
                'submenus'   => array(),
            ),
        );
    }

    /**
     * 📌 بخش محتوا - منوهای ثابت
     */
    private function get_content_fixed_menus() {
        return array(
            array(
                'slug'       => 'admin.php?page=nextsafar-posts',
                'title'      => 'نوشته‌ها',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-admin-post',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-pages',
                'title'      => 'برگه‌ها',
                'capability' => 'edit_pages',
                'icon'       => 'dashicons-admin-page',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-media',
                'title'      => 'رسانه‌ها',
                'capability' => 'upload_files',
                'icon'       => 'dashicons-admin-media',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'edit-comments.php',
                'title'      => 'دیدگاه‌ها',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-admin-comments',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-categories',
                'title'      => 'دسته‌بندی‌ها',
                'capability' => 'manage_categories',
                'icon'       => 'dashicons-tag',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
        );
    }

    /**
     * 📌 بخش ظاهر
     */
    private function get_appearance_menus() {
        return array(
            array(
                'slug'       => 'themes.php',
                'title'      => 'پوسته‌ها',
                'capability' => 'switch_themes',
                'icon'       => 'dashicons-admin-appearance',
                'type'       => 'panel',
                'section'    => 'APPEARANCE',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'nav-menus.php',
                'title'      => 'فهرست‌ها',
                'capability' => 'edit_theme_options',
                'icon'       => 'dashicons-menu',
                'type'       => 'panel',
                'section'    => 'APPEARANCE',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'widgets.php',
                'title'      => 'ابزارک‌ها',
                'capability' => 'edit_theme_options',
                'icon'       => 'dashicons-admin-appearance',
                'type'       => 'panel',
                'section'    => 'APPEARANCE',
                'submenus'   => array(),
            ),
        );
    }

    /**
     * 📌 بخش مدیریت
     */
    private function get_management_menus() {
        return array(
            array(
                'slug'       => 'users.php',
                'title'      => 'کاربران',
                'capability' => 'list_users',
                'icon'       => 'dashicons-admin-users',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'plugins.php',
                'title'      => 'افزونه‌ها',
                'capability' => 'activate_plugins',
                'icon'       => 'dashicons-admin-plugins',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-updates',
                'title'      => 'بروزرسانی‌ها',
                'capability' => 'update_core',
                'icon'       => 'dashicons-update',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'tools.php',
                'title'      => 'ابزارها',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-admin-tools',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'options-general.php',
                'title'      => 'تنظیمات',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-admin-settings',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
        );
    }

    /**
     * 📌 منوهای ثابت پنل - دقیقاً مطابق سایدبار
     */
    private function get_panel_fixed_menus() {
        return array(
            // ─── MAIN ───
            array(
                'slug'       => 'admin.php?page=nextsafar-admin',
                'title'      => 'داشبورد',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-dashboard',
                'type'       => 'panel',
                'section'    => 'MAIN',
                'submenus'   => array(),
            ),

            // ─── CONTENT ───
            array(
                'slug'       => 'admin.php?page=nextsafar-posts',
                'title'      => 'نوشته‌ها',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-admin-post',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-pages',
                'title'      => 'برگه‌ها',
                'capability' => 'edit_pages',
                'icon'       => 'dashicons-admin-page',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-media',
                'title'      => 'رسانه‌ها',
                'capability' => 'upload_files',
                'icon'       => 'dashicons-admin-media',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'edit-comments.php',
                'title'      => 'دیدگاه‌ها',
                'capability' => 'edit_posts',
                'icon'       => 'dashicons-admin-comments',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),

            // ─── APPEARANCE ───
            array(
                'slug'       => 'themes.php',
                'title'      => 'پوسته‌ها',
                'capability' => 'switch_themes',
                'icon'       => 'dashicons-admin-appearance',
                'type'       => 'panel',
                'section'    => 'APPEARANCE',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'nav-menus.php',
                'title'      => 'فهرست‌ها',
                'capability' => 'edit_theme_options',
                'icon'       => 'dashicons-menu',
                'type'       => 'panel',
                'section'    => 'APPEARANCE',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'widgets.php',
                'title'      => 'ابزارک‌ها',
                'capability' => 'edit_theme_options',
                'icon'       => 'dashicons-admin-appearance',
                'type'       => 'panel',
                'section'    => 'APPEARANCE',
                'submenus'   => array(),
            ),

            // ─── MANAGEMENT ───
            array(
                'slug'       => 'users.php',
                'title'      => 'کاربران',
                'capability' => 'list_users',
                'icon'       => 'dashicons-admin-users',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'plugins.php',
                'title'      => 'افزونه‌ها',
                'capability' => 'activate_plugins',
                'icon'       => 'dashicons-admin-plugins',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-updates',
                'title'      => 'بروزرسانی‌ها',
                'capability' => 'update_core',
                'icon'       => 'dashicons-update',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'tools.php',
                'title'      => 'ابزارها',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-admin-tools',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'options-general.php',
                'title'      => 'تنظیمات',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-admin-settings',
                'type'       => 'panel',
                'section'    => 'MANAGEMENT',
                'submenus'   => array(),
            ),

            // ─── NEXTSAFAR SPECIAL ───
            array(
                'slug'       => 'admin.php?page=nextsafar-categories',
                'title'      => 'دسته‌بندی‌ها',
                'capability' => 'manage_categories',
                'icon'       => 'dashicons-tag',
                'type'       => 'panel',
                'section'    => 'CONTENT',
                'submenus'   => array(),
            ),
            array(
                'slug'       => 'admin.php?page=nextsafar-role-access',
                'title'      => 'مدیریت دسترسی‌ها',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-lock',
                'type'       => 'panel',
                'section'    => 'MAIN',
                'submenus'   => array(),
            ),
        );
    }

    /**
     * 📌 پست تایپ‌های سفارشی - داخل بخش محتوا
     * روش ۱: از پلاگین پنل | روش ۲: مستقیم از وردپرس
     */
    private function get_panel_cpt_menus( &$seen_slugs ) {
        $cpt_menus = array();

        // ═══════════════════════════════════════════
        // روش ۱: از طریق پلاگین پنل (اگر موجود باشد)
        // ═══════════════════════════════════════════
        $core = nextsafar();
        if ( $core->external_menus && method_exists( $core->external_menus, 'get_cpt_menus' ) ) {
            $sidebar_cpts = $core->external_menus->get_cpt_menus();

            if ( ! empty( $sidebar_cpts ) && is_array( $sidebar_cpts ) ) {
                foreach ( $sidebar_cpts as $cpt ) {
                    // ✅ مستقیم از کلید post_type استفاده کن
                    $post_type = isset( $cpt['post_type'] ) ? $cpt['post_type'] : '';
                    if ( empty( $post_type ) ) continue;

                    $menu_slug = 'edit.php?post_type=' . $post_type;
                    if ( isset( $seen_slugs[ $menu_slug ] ) ) continue;

                    // عنوان
                    $title = isset( $cpt['title'] ) ? $cpt['title'] : '';
                    if ( empty( $title ) ) {
                        $pt_obj = get_post_type_object( $post_type );
                        $title = $pt_obj ? $pt_obj->labels->name : ucfirst( $post_type );
                    }

                    // آیکون
                    $icon = isset( $cpt['icon'] ) ? $cpt['icon'] : '';
                    if ( empty( $icon ) ) {
                        $pt_obj = get_post_type_object( $post_type );
                        $icon = $pt_obj ? ( $pt_obj->menu_icon ?: 'dashicons-admin-post' ) : 'dashicons-admin-post';
                    }

                    // قابلیت
                    $capability = 'edit_posts';
                    $pt_obj = get_post_type_object( $post_type );
                    if ( $pt_obj && isset( $pt_obj->cap ) && isset( $pt_obj->cap->edit_posts ) ) {
                        $capability = $pt_obj->cap->edit_posts;
                    }

                    $cpt_menus[] = array(
                        'slug'       => $menu_slug,
                        'title'      => $title,
                        'capability' => $capability,
                        'icon'       => $icon,
                        'type'       => 'cpt',
                        'section'    => 'CONTENT',
                        'submenus'   => array(),
                    );
                    $seen_slugs[ $menu_slug ] = true;
                }
            }
        }

        // ═══════════════════════════════════════════
        // روش ۲: مستقیم از وردپرس (اگر روش ۱ خالی بود)
        // ═══════════════════════════════════════════
        if ( empty( $cpt_menus ) ) {
            $post_types = get_post_types( array(
                'public'   => true,
                '_builtin' => false,
                'show_ui'  => true,
            ), 'objects' );

            foreach ( $post_types as $pt ) {
                // رد کردن پست تایپ‌هایی که در منو نمایش داده نمی‌شوند
                if ( ! $pt->show_in_menu ) continue;

                $menu_slug = 'edit.php?post_type=' . $pt->name;
                if ( isset( $seen_slugs[ $menu_slug ] ) ) continue;

                $capability = 'edit_posts';
                if ( isset( $pt->cap ) && isset( $pt->cap->edit_posts ) ) {
                    $capability = $pt->cap->edit_posts;
                }

                $cpt_menus[] = array(
                    'slug'       => $menu_slug,
                    'title'      => $pt->labels->name,
                    'capability' => $capability,
                    'icon'       => $pt->menu_icon ?: 'dashicons-admin-post',
                    'type'       => 'cpt',
                    'section'    => 'CONTENT',
                    'submenus'   => array(),
                );
                $seen_slugs[ $menu_slug ] = true;
            }
        }

        return $cpt_menus;
    }

    /**
     * 📌 پلاگین‌های خارجی - آخر لیست
     */
    private function get_panel_plugin_menus( &$seen_slugs ) {
        $plugin_menus = array();

        $core = nextsafar();
        if ( ! $core->external_menus || ! method_exists( $core->external_menus, 'get_plugin_menus' ) ) {
            return $plugin_menus;
        }

        $sidebar_plugins = $core->external_menus->get_plugin_menus();

        foreach ( $sidebar_plugins as $plugin ) {
            $slug = '';

            if ( ! empty( $plugin['page_slug'] ) ) {
                $slug = $plugin['page_slug'];
            } elseif ( ! empty( $plugin['raw_slug'] ) ) {
                $slug = $plugin['raw_slug'];
            }

            if ( empty( $slug ) || isset( $seen_slugs[ $slug ] ) ) {
                continue;
            }

            $plugin_menus[] = array(
                'slug'       => $slug,
                'title'      => $plugin['title'],
                'capability' => isset( $plugin['capability'] ) ? $plugin['capability'] : 'manage_options',
                'icon'       => isset( $plugin['icon'] ) ? $plugin['icon'] : 'dashicons-admin-generic',
                'type'       => 'plugin',
                'section'    => 'PLUGINS', // ⭐ آخر لیست
                'submenus'   => array(),
            );
            $seen_slugs[ $slug ] = true;
        }

        return $plugin_menus;
    }

    /**
     * CPT های سفارشی که در منو نیستند
     */
    private function get_missing_cpt_menus( &$seen_slugs ) {
        $cpt_menus    = array();
        $post_types   = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );

        foreach ( $post_types as $cpt ) {
            $cpt_slug = 'edit.php?post_type=' . $cpt->name;
            if ( isset( $seen_slugs[ $cpt_slug ] ) ) {
                continue;
            }

            $labels     = $cpt->labels;
            $icon       = $cpt->menu_icon ?: 'dashicons-admin-post';
            $capability = isset( $cpt->cap->edit_posts ) ? $cpt->cap->edit_posts : 'edit_posts';

            $submenus = array();

            // افزودن submenu
            $add_slug = 'post-new.php?post_type=' . $cpt->name;
            if ( ! isset( $seen_slugs[ $add_slug ] ) ) {
                $submenus[] = array(
                    'slug'       => $add_slug,
                    'title'      => $labels->add_new_item ?: 'افزودن ' . $labels->singular_name,
                    'capability' => $capability,
                );
            }

            // افزودن taxonomy های مرتبط
            $taxonomies = get_object_taxonomies( $cpt->name, 'objects' );
            foreach ( $taxonomies as $tax ) {
                if ( ! $tax->show_in_menu ) continue;
                $tax_slug = 'edit-tags.php?taxonomy=' . $tax->name;
                if ( 'post' !== $cpt->name ) {
                    $tax_slug .= '&post_type=' . $cpt->name;
                }
                if ( ! isset( $seen_slugs[ $tax_slug ] ) ) {
                    $submenus[] = array(
                        'slug'       => $tax_slug,
                        'title'      => $tax->labels->name,
                        'capability' => 'manage_categories',
                    );
                }
            }

            $cpt_menus[] = array(
                'slug'       => $cpt_slug,
                'title'      => $labels->name,
                'capability' => $capability,
                'icon'       => $icon,
                'type'       => 'cpt',
                'submenus'   => $submenus,
            );
            $seen_slugs[ $cpt_slug ] = true;
        }
        return $cpt_menus;
    }

    /**
     * Taxonomy های سفارشی
     */
    private function get_taxonomy_menus( &$seen_slugs ) {
        $tax_menus    = array();
        $taxonomies   = get_taxonomies( array( 'public' => true ), 'objects' );

        foreach ( $taxonomies as $tax ) {
            if ( in_array( $tax->name, array( 'category', 'post_tag', 'nav_menu', 'link_category', 'post_format' ), true ) ) {
                continue;
            }
            if ( ! $tax->show_in_menu ) {
                continue;
            }

            $post_type = ! empty( $tax->object_type ) ? $tax->object_type[0] : 'post';
            $tax_slug  = 'edit-tags.php?taxonomy=' . $tax->name;
            if ( 'post' !== $post_type ) {
                $tax_slug .= '&post_type=' . $post_type;
            }

            if ( isset( $seen_slugs[ $tax_slug ] ) ) {
                continue;
            }

            $tax_menus[] = array(
                'slug'       => $tax_slug,
                'title'      => $tax->labels->name,
                'capability' => 'manage_categories',
                'icon'       => 'dashicons-tag',
                'type'       => 'taxonomy',
                'submenus'   => array(),
            );
            $seen_slugs[ $tax_slug ] = true;
        }
        return $tax_menus;
    }

    /**
     * 🆕 Fallback کامل: تمام منوهای وردپرس + پلاگین‌ها
     */
    private function get_menus_fallback() {
        $menus = array();
        $seen  = array();

        // ─── 🏠 داشبورد ───
        $menus[] = array(
            'slug' => 'index.php', 'title' => 'داشبورد', 'capability' => 'read',
            'icon' => 'dashicons-dashboard', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'index.php', 'title' => 'خانه', 'capability' => 'read' ),
                array( 'slug' => 'update-core.php', 'title' => 'بروزرسانی‌ها', 'capability' => 'update_core' ),
            ),
        );

        // ─── 📝 نوشته‌ها ───
        $menus[] = array(
            'slug' => 'edit.php', 'title' => 'نوشته‌ها', 'capability' => 'edit_posts',
            'icon' => 'dashicons-admin-post', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'edit.php', 'title' => 'همه نوشته‌ها', 'capability' => 'edit_posts' ),
                array( 'slug' => 'post-new.php', 'title' => 'افزودن نوشته', 'capability' => 'edit_posts' ),
                array( 'slug' => 'edit-tags.php?taxonomy=category', 'title' => 'دسته‌بندی‌ها', 'capability' => 'manage_categories' ),
                array( 'slug' => 'edit-tags.php?taxonomy=post_tag', 'title' => 'برچسب‌ها', 'capability' => 'manage_categories' ),
            ),
        );

        // ─── 🖼️ رسانه ───
        $menus[] = array(
            'slug' => 'upload.php', 'title' => 'رسانه', 'capability' => 'upload_files',
            'icon' => 'dashicons-admin-media', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'upload.php', 'title' => 'کتابخانه', 'capability' => 'upload_files' ),
                array( 'slug' => 'media-new.php', 'title' => 'افزودن رسانه', 'capability' => 'upload_files' ),
            ),
        );

        // ─── 📄 برگه‌ها ───
        $menus[] = array(
            'slug' => 'edit.php?post_type=page', 'title' => 'برگه‌ها', 'capability' => 'edit_pages',
            'icon' => 'dashicons-admin-page', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'edit.php?post_type=page', 'title' => 'همه برگه‌ها', 'capability' => 'edit_pages' ),
                array( 'slug' => 'post-new.php?post_type=page', 'title' => 'افزودن برگه', 'capability' => 'edit_pages' ),
            ),
        );

        // ─── 💬 دیدگاه‌ها ───
        $menus[] = array(
            'slug' => 'edit-comments.php', 'title' => 'دیدگاه‌ها', 'capability' => 'edit_posts',
            'icon' => 'dashicons-admin-comments', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'edit-comments.php', 'title' => 'همه دیدگاه‌ها', 'capability' => 'edit_posts' ),
            ),
        );

        // ─── 🎨 ظاهر ───
        $menus[] = array(
            'slug' => 'themes.php', 'title' => 'پوسته‌ها', 'capability' => 'switch_themes',
            'icon' => 'dashicons-admin-appearance', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'themes.php', 'title' => 'پوسته‌ها', 'capability' => 'switch_themes' ),
                array( 'slug' => 'customize.php', 'title' => 'سفارشی‌سازی', 'capability' => 'customize' ),
                array( 'slug' => 'nav-menus.php', 'title' => 'فهرست‌ها', 'capability' => 'edit_theme_options' ),
                array( 'slug' => 'widgets.php', 'title' => 'ابزارک‌ها', 'capability' => 'edit_theme_options' ),
                array( 'slug' => 'theme-editor.php', 'title' => 'ویرایشگر پوسته', 'capability' => 'edit_themes' ),
            ),
        );

        // ─── 🔌 افزونه‌ها ───
        $menus[] = array(
            'slug' => 'plugins.php', 'title' => 'افزونه‌ها', 'capability' => 'activate_plugins',
            'icon' => 'dashicons-admin-plugins', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'plugins.php', 'title' => 'افزونه‌های نصب شده', 'capability' => 'activate_plugins' ),
                array( 'slug' => 'plugin-install.php', 'title' => 'افزودن افزونه', 'capability' => 'install_plugins' ),
                array( 'slug' => 'plugin-editor.php', 'title' => 'ویرایشگر افزونه', 'capability' => 'edit_plugins' ),
            ),
        );

        // ─── 👥 کاربران ───
        $menus[] = array(
            'slug' => 'users.php', 'title' => 'کاربران', 'capability' => 'list_users',
            'icon' => 'dashicons-admin-users', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'users.php', 'title' => 'همه کاربران', 'capability' => 'list_users' ),
                array( 'slug' => 'user-new.php', 'title' => 'افزودن کاربر', 'capability' => 'create_users' ),
                array( 'slug' => 'profile.php', 'title' => 'شناسنامه شما', 'capability' => 'read' ),
            ),
        );

        // ─── 🛠️ ابزارها ───
        $menus[] = array(
            'slug' => 'tools.php', 'title' => 'ابزارها', 'capability' => 'manage_options',
            'icon' => 'dashicons-admin-tools', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'tools.php', 'title' => 'ابزارهای موجود', 'capability' => 'manage_options' ),
                array( 'slug' => 'import.php', 'title' => 'درون‌ریزی', 'capability' => 'import' ),
                array( 'slug' => 'export.php', 'title' => 'برون‌بری', 'capability' => 'export' ),
                array( 'slug' => 'site-health.php', 'title' => 'سلامت سایت', 'capability' => 'manage_options' ),
                array( 'slug' => 'export-personal-data.php', 'title' => 'صادر کردن داده‌های شخصی', 'capability' => 'export_others_personal_data' ),
                array( 'slug' => 'erase-personal-data.php', 'title' => 'پاک کردن داده‌های شخصی', 'capability' => 'erase_others_personal_data' ),
            ),
        );

        // ─── ⚙️ تنظیمات ───
        $menus[] = array(
            'slug' => 'options-general.php', 'title' => 'تنظیمات', 'capability' => 'manage_options',
            'icon' => 'dashicons-admin-settings', 'type' => 'core',
            'submenus' => array(
                array( 'slug' => 'options-general.php', 'title' => 'عمومی', 'capability' => 'manage_options' ),
                array( 'slug' => 'options-writing.php', 'title' => 'نوشتن', 'capability' => 'manage_options' ),
                array( 'slug' => 'options-reading.php', 'title' => 'خواندن', 'capability' => 'manage_options' ),
                array( 'slug' => 'options-discussion.php', 'title' => 'گفت‌وگو', 'capability' => 'manage_options' ),
                array( 'slug' => 'options-media.php', 'title' => 'رسانه', 'capability' => 'manage_options' ),
                array( 'slug' => 'options-permalink.php', 'title' => 'پیوندهای یکتا', 'capability' => 'manage_options' ),
                array( 'slug' => 'options-privacy.php', 'title' => 'حریم خصوصی', 'capability' => 'manage_privacy_options' ),
            ),
        );

        // ─── 📦 پلاگین‌های رایج ───
        // Yoast SEO
        if ( defined( 'WPSEO_VERSION' ) ) {
            $menus[] = array(
                'slug' => 'wpseo_dashboard', 'title' => 'Yoast SEO', 'capability' => 'wpseo_manage_options',
                'icon' => 'dashicons-chart-line', 'type' => 'plugin',
                'submenus' => array(
                    array( 'slug' => 'wpseo_dashboard', 'title' => 'عمومی', 'capability' => 'wpseo_manage_options' ),
                    array( 'slug' => 'wpseo_page_settings', 'title' => 'تنظیمات', 'capability' => 'wpseo_manage_options' ),
                    array( 'slug' => 'wpseo_integrations', 'title' => 'یکپارچه‌سازی‌ها', 'capability' => 'wpseo_manage_options' ),
                    array( 'slug' => 'wpseo_tools', 'title' => 'ابزارها', 'capability' => 'wpseo_manage_options' ),
                ),
            );
        }

        // WooCommerce
        if ( class_exists( 'WooCommerce' ) ) {
            $menus[] = array(
                'slug' => 'woocommerce', 'title' => 'ووکامرس', 'capability' => 'manage_woocommerce',
                'icon' => 'dashicons-cart', 'type' => 'plugin',
                'submenus' => array(
                    array( 'slug' => 'woocommerce', 'title' => 'داشبورد', 'capability' => 'manage_woocommerce' ),
                    array( 'slug' => 'edit.php?post_type=product', 'title' => 'محصولات', 'capability' => 'edit_products' ),
                    array( 'slug' => 'edit.php?post_type=shop_order', 'title' => 'سفارش‌ها', 'capability' => 'edit_shop_orders' ),
                    array( 'slug' => 'edit.php?post_type=shop_coupon', 'title' => 'کوپن‌ها', 'capability' => 'edit_shop_coupons' ),
                ),
            );
        }

        // ACF
        if ( class_exists( 'ACF' ) ) {
            $menus[] = array(
                'slug' => 'edit.php?post_type=acf-field-group', 'title' => 'فیلدهای سفارشی (ACF)',
                'capability' => 'manage_options', 'icon' => 'dashicons-editor-table', 'type' => 'plugin',
                'submenus' => array(
                    array( 'slug' => 'edit.php?post_type=acf-field-group', 'title' => 'گروه‌های فیلد', 'capability' => 'manage_options' ),
                    array( 'slug' => 'post-new.php?post_type=acf-field-group', 'title' => 'افزودن گروه فیلد', 'capability' => 'manage_options' ),
                ),
            );
        }

        // Elementor
        if ( did_action( 'elementor/loaded' ) ) {
            $menus[] = array(
                'slug' => 'elementor', 'title' => 'المنتور', 'capability' => 'manage_options',
                'icon' => 'dashicons-welcome-widgets-menus', 'type' => 'plugin',
                'submenus' => array(
                    array( 'slug' => 'elementor', 'title' => 'تنظیمات', 'capability' => 'manage_options' ),
                    array( 'slug' => 'elementor-custom-fonts', 'title' => 'فونت‌های سفارشی', 'capability' => 'manage_options' ),
                    array( 'slug' => 'elementor-custom-icons', 'title' => 'آیکون‌های سفارشی', 'capability' => 'manage_options' ),
                ),
            );
        }

        // ─── 🚀 NextSafar ───
        $menus[] = array(
            'slug' => 'admin.php?page=nextsafar-admin', 'title' => '🚀 داشبورد NextSafar',
            'capability' => 'edit_posts', 'icon' => 'dashicons-admin-site', 'type' => 'nextsafar',
            'submenus' => array(
                array( 'slug' => 'admin.php?page=nextsafar-admin', 'title' => 'داشبورد', 'capability' => 'edit_posts' ),
                array( 'slug' => 'admin.php?page=nextsafar-media', 'title' => 'رسانه‌های NextSafar', 'capability' => 'upload_files' ),
                array( 'slug' => 'admin.php?page=nextsafar-updates', 'title' => 'بروزرسانی‌ها', 'capability' => 'update_core' ),
                array( 'slug' => 'admin.php?page=nextsafar-categories', 'title' => 'دسته‌بندی‌ها', 'capability' => 'manage_categories' ),
                array( 'slug' => 'admin.php?page=nextsafar-role-access', 'title' => '🔐 مدیریت دسترسی‌ها', 'capability' => 'manage_options' ),
            ),
        );

        // ─── 📦 NextSafar Core CPTs ───
        $nextsafar_cpts = array(
            'hotel'       => array( 'هتل‌ها',       'dashicons-building' ),
            'destination' => array( 'مقاصد',       'dashicons-location-alt' ),
            'flight'      => array( 'پروازها',      'dashicons-paperclip' ),
            'restaurant'  => array( 'رستوران‌ها',    'dashicons-food' ),
            'tour'        => array( 'تورها',         'dashicons-palmtree' ),
            'visa'        => array( 'ویزاها',        'dashicons-visibility' ),
        );

        foreach ( $nextsafar_cpts as $cpt_slug => $cpt_info ) {
            if ( ! post_type_exists( $cpt_slug ) ) {
                continue;
            }
            $edit_slug = 'edit.php?post_type=' . $cpt_slug;
            $add_slug  = 'post-new.php?post_type=' . $cpt_slug;

            $menus[] = array(
                'slug' => $edit_slug, 'title' => $cpt_info[0], 'capability' => 'edit_posts',
                'icon' => $cpt_info[1], 'type' => 'cpt',
                'submenus' => array(
                    array( 'slug' => $edit_slug, 'title' => 'همه ' . $cpt_info[0], 'capability' => 'edit_posts' ),
                    array( 'slug' => $add_slug, 'title' => 'افزودن ' . $cpt_info[0], 'capability' => 'edit_posts' ),
                ),
            );
        }

        // ─── 🏷️ NextSafar Core Taxonomies ───
        $nextsafar_taxes = array(
            'hotel-city'          => array( 'شهر هتل‌ها',       'hotel' ),
            'hotel-star'          => array( 'ستاره هتل‌ها',    'hotel' ),
            'destination-country' => array( 'کشور مقاصد',     'destination' ),
        );
        foreach ( $nextsafar_taxes as $tax_slug => $tax_info ) {
            if ( ! taxonomy_exists( $tax_slug ) ) {
                continue;
            }
            $tax_edit_slug = 'edit-tags.php?taxonomy=' . $tax_slug . '&post_type=' . $tax_info[1];
            $menus[] = array(
                'slug' => $tax_edit_slug, 'title' => '' . $tax_info[0],
                'capability' => 'manage_categories', 'icon' => 'dashicons-tag',
                'type' => 'taxonomy', 'submenus' => array(),
            );
        }

        return $menus;
    }

    /* =========================================================
    🔒 بررسی دسترسی به منو
    ========================================================= */
    /**
     * 🎯 بررسی دسترسی کاربر به یک منو
     * منطق:
     * - ادمین کل → دسترسی کامل
     * - مشترک → بدون دسترسی
     * - بقیه: اگر هیچ منویی تیک نخورده → دسترسی کامل
     *         اگر حداقل یک منو تیک خورده → فقط تیک خورده‌ها
     */
    public function user_can_access_menu( $slug, $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return false;

        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        // ادمین کل: همیشه دسترسی کامل
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return true;
        }

        // مشترک: بدون دسترسی
        if ( in_array( 'subscriber', $user->roles, true ) ) {
            return false;
        }

        // صفحات همیشه مجاز
        $always_allowed = array(
            'index.php',
            'profile.php',
            'admin.php?page=nextsafar-admin',
        );
        if ( in_array( $slug, $always_allowed, true ) ) {
            return true;
        }

        // بررسی تنظیمات هر نقش
        foreach ( $user->roles as $role ) {
            $settings = $this->get_role_settings( $role );

            // ادمین کل
            if ( ! empty( $settings['is_super'] ) ) {
                return true;
            }

            $menus = isset( $settings['menus'] ) ? $settings['menus'] : array();

            // ✅ کلید اصلی: بررسی آیا هیچ منویی تیک خورده؟
            $any_enabled = false;
            foreach ( $menus as $enabled ) {
                if ( $enabled ) {
                    $any_enabled = true;
                    break;
                }
            }

            // اگر هیچ منویی تیک نخورده = دسترسی کامل (هنوز تنظیم نشده)
            if ( ! $any_enabled ) {
                return true;
            }

            // بررسی slug دقیق
            if ( ! empty( $menus[ $slug ] ) ) {
                return true;
            }

            // بررسی با base slug
            $base_slug = strtok( $slug, '?' );
            foreach ( $menus as $menu_slug => $enabled ) {
                if ( $enabled ) {
                    if ( $menu_slug === $slug ) return true;
                    $menu_base = strtok( $menu_slug, '?' );
                    if ( $menu_base === $base_slug ) return true;
                }
            }
        }

        return false;
    }

    /**
     * 🎯 محدودسازی دسترسی به صفحات
     */
    public function restrict_page_access() {
        if ( wp_doing_ajax() || wp_doing_cron() ) return;

        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        if ( in_array( 'administrator', $user->roles, true ) ) return;
        if ( in_array( 'subscriber', $user->roles, true ) ) {
            wp_safe_redirect( home_url() );
            exit;
        }

        // ✅ آیا حداقل یک نقش منوی فعال دارد؟
        $has_restrictions = false;
        foreach ( $user->roles as $role ) {
            if ( $this->role_has_any_enabled_menu( $role ) ) {
                $has_restrictions = true;
                break;
            }
        }

        // اگر هیچ منویی تیک نخورده = هیچ محدودیتی اعمال نکن
        if ( ! $has_restrictions ) {
            return;
        }

        global $pagenow;
        $current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

        $always_allowed_pages = array( 'nextsafar-admin' );
        $always_allowed_files = array( 'index.php', 'profile.php' );

        if ( ! empty( $current_page ) && in_array( $current_page, $always_allowed_pages, true ) ) {
            return;
        }
        if ( empty( $current_page ) && in_array( $pagenow, $always_allowed_files, true ) ) {
            return;
        }

        $current_slug = $pagenow;
        if ( ! empty( $current_page ) ) {
            $current_slug = 'admin.php?page=' . $current_page;
        } elseif ( ! empty( $_GET['post_type'] ) ) {
            $current_slug = $pagenow . '?post_type=' . sanitize_key( $_GET['post_type'] );
        } elseif ( ! empty( $_GET['taxonomy'] ) ) {
            $current_slug = $pagenow . '?taxonomy=' . sanitize_key( $_GET['taxonomy'] );
        }

        if ( ! $this->user_can_access_menu( $current_slug, $user_id ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=nextsafar-admin' ) );
            exit;
        }
    }

    /**
     * ✅ بررسی اینکه آیا نقش تنظیمات ذخیره شده دارد یا نه
     */
    private function role_has_saved_settings( $role_slug ) {
        $all_settings = get_option( self::OPTION_KEY, array() );
        return isset( $all_settings[ $role_slug ] );
    }

    /**
     * ✅ آیا نقش حداقل یک منوی فعال (تیک‌خورده) دارد؟
     */
    public function role_has_any_enabled_menu( $role_slug ) {
        if ( ! $this->role_has_saved_settings( $role_slug ) ) {
            return false;
        }

        $settings = $this->get_role_settings( $role_slug );
        $menus    = isset( $settings['menus'] ) ? (array) $settings['menus'] : array();

        foreach ( $menus as $enabled ) {
            if ( $enabled ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 🎯 ریدایرکت بعد از لاگین به پنل NextSafar
     */
    public function login_redirect_to_panel( $redirect_to, $request, $user ) {
        if ( ! ( $user instanceof WP_User ) ) {
            return $redirect_to;
        }

        // مشترک → صفحه اصلی سایت
        if ( in_array( 'subscriber', $user->roles, true ) ) {
            return home_url();
        }

        // اگر redirect_to مشخص و غیرپیش‌فرضی عمداً پاس شده، محترمانه رد نشویم
        $defaults = array( '', admin_url(), admin_url( 'index.php' ), user_admin_url() );
        if ( ! empty( $redirect_to )
            && ! in_array( $redirect_to, $defaults, true )
            && strpos( $redirect_to, 'wp-login.php' ) === false ) {
            return $redirect_to;
        }

        // ادمین، ویرایشگر، نویسنده، مشارکت‌کننده → پنل اختصاصی
        return admin_url( 'admin.php?page=nextsafar-admin' );
    }

    /**
     * حذف منوهای غیرمجاز
     * ✅ اگر نقش تنظیمات ذخیره شده نداشته باشد، هیچ منویی حذف نمی‌شود
     */
    public function restrict_menus() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        // ادمین کل: هیچ محدودیتی
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return;
        }

        // مشترک: ریدایرکت به سایت
        if ( in_array( 'subscriber', $user->roles, true ) ) {
            add_action( 'admin_init', function() {
                if ( ! wp_doing_ajax() && ! wp_doing_cron() ) {
                    wp_safe_redirect( home_url() );
                    exit;
                }
            }, 1 );
            return;
        }

        // ✅ بررسی: آیا حداقل یک نقش تنظیمات ذخیره شده دارد؟
        $has_any_saved_settings = false;
        foreach ( $user->roles as $role ) {
            if ( $this->role_has_saved_settings( $role ) ) {
                $has_any_saved_settings = true;
                break;
            }
        }

        // ✅ اگر هیچ تنظیمی ذخیره نشده، هیچ محدودیتی اعمال نکن
        if ( ! $has_any_saved_settings ) {
            return;
        }

        // لیست منوهای همیشه مجاز
        $always_visible = array(
            'index.php',
            'admin.php?page=nextsafar-admin',
            'profile.php',
        );

        global $menu, $submenu;

        // حذف منوهای اصلی غیرمجاز
        if ( ! empty( $menu ) && is_array( $menu ) ) {
            foreach ( $menu as $index => $menu_item ) {
                if ( ! isset( $menu_item[2] ) ) continue;
                $slug = $menu_item[2];

                // رد کردن separator
                if ( strpos( $slug, 'separator' ) !== false ) continue;

                // منوهای همیشه مجاز
                if ( in_array( $slug, $always_visible, true ) ) continue;

                if ( ! $this->user_can_access_menu( $slug, $user_id ) ) {
                    remove_menu_page( $slug );
                }
            }
        }

        // حذف زیرمنوهای غیرمجاز
        if ( ! empty( $submenu ) && is_array( $submenu ) ) {
            foreach ( $submenu as $parent_slug => $sub_items ) {
                foreach ( $sub_items as $index => $sub_item ) {
                    if ( ! isset( $sub_item[2] ) ) continue;
                    $sub_slug = $sub_item[2];

                    if ( in_array( $sub_slug, $always_visible, true ) ) continue;

                    if ( ! $this->user_can_access_menu( $sub_slug, $user_id ) ) {
                        remove_submenu_page( $parent_slug, $sub_slug );
                    }
                }
            }
        }
    }

    public function restrict_capabilities( $allcaps, $caps, $args, $user ) {
        if ( ! $user || ! isset( $user->ID ) ) return $allcaps;
        if ( in_array( 'administrator', $user->roles, true ) ) return $allcaps;

        $delete_caps = array(
            'delete_posts', 'delete_published_posts', 'delete_private_posts',
            'delete_others_posts', 'delete_pages', 'delete_published_pages',
            'delete_others_pages', 'delete_users',
        );

        $requested_cap = isset( $args[0] ) ? $args[0] : '';
        if ( in_array( $requested_cap, $delete_caps, true ) ) {
            $can_delete = false;
            foreach ( $user->roles as $role ) {
                $settings = $this->get_role_settings( $role );
                if ( ! empty( $settings['can_delete'] ) ) {
                    $can_delete = true;
                    break;
                }
            }
            if ( ! $can_delete ) {
                $allcaps[ $requested_cap ] = false;
            }
        }
        return $allcaps;
    }

    public function prevent_deletion( $check, $post_id ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return $check;

        $user = get_userdata( $user_id );
        if ( ! $user ) return $check;

        if ( in_array( 'administrator', $user->roles, true ) ) return $check;

        $can_delete = false;
        foreach ( $user->roles as $role ) {
            $settings = $this->get_role_settings( $role );
            if ( ! empty( $settings['can_delete'] ) ) {
                $can_delete = true;
                break;
            }
        }

        if ( ! $can_delete ) {
            wp_die(
                'شما اجازه حذف این مورد را ندارید. لطفاً با مدیر سایت تماس بگیرید.',
                'خطای دسترسی',
                array( 'response' => 403, 'back_link' => true )
            );
        }
        return $check;
    }

    /* =========================================================
    🔌 AJAX Handlers
    ========================================================= */

    public function ajax_save_role_access() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_role_access' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $role_slug  = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
        $menus      = isset( $_POST['menus'] ) ? (array) $_POST['menus'] : array();
        $can_delete = isset( $_POST['can_delete'] ) && $_POST['can_delete'] === '1';

        if ( empty( $role_slug ) ) {
            wp_send_json_error( array( 'message' => 'Invalid role' ) );
        }

        $clean_menus = array();
        foreach ( $menus as $menu_slug => $enabled ) {
            $menu_slug = sanitize_text_field( $menu_slug );
            $clean_menus[ $menu_slug ] = (bool) $enabled;
        }

        $settings = array(
            'menus'      => $clean_menus,
            'can_delete' => $can_delete,
            'is_super'   => in_array( $role_slug, self::SUPER_ADMIN_ROLES, true ),
        );

        $this->save_role_settings( $role_slug, $settings );

        wp_send_json_success( array(
            'message' => 'تنظیمات با موفقیت ذخیره شد',
            'role'    => $role_slug,
        ) );
    }

    public function ajax_get_role_access() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $role_slug = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
        if ( empty( $role_slug ) ) {
            wp_send_json_error( array( 'message' => 'Invalid role' ) );
        }

        $this->ensure_menus_loaded();

        $settings = $this->get_role_settings( $role_slug );
        $all_menus = $this->get_all_menus();

        if ( empty( $all_menus ) ) {
            $all_menus = $this->get_menus_fallback();
        }

        wp_send_json_success( array(
            'settings' => $settings,
            'menus'    => $all_menus,
        ) );
    }

    public function ajax_reset_role_access() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nextsafar_role_access' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $role_slug = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
        if ( empty( $role_slug ) ) {
            wp_send_json_error( array( 'message' => 'Invalid role' ) );
        }

        $default_settings = $this->get_default_role_settings( $role_slug );
        $this->save_role_settings( $role_slug, $default_settings );

        wp_send_json_success( array(
            'message'  => 'تنظیمات به حالت پیش‌فرض بازگردانده شد',
            'settings' => $default_settings,
        ) );
    }
}