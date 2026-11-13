<?php
/**
 * NextSafar Core Class
 * Main system coordinator and orchestrator
 *
 * @version 1.0.0 - Webhook release
 * @package NextSafar
 */

namespace NextSafar;

if (!defined('ABSPATH')) exit;

class Core {

    /**
     * Initialize the core plugin functionality
     * Registers all hooks, post types, taxonomies, and services
     *
     * @return void
     */
    public static function init(): void {
        // Register Post Types and Taxonomies
        add_action('init', [__CLASS__, 'register_post_types']);
        add_action('init', [__CLASS__, 'register_taxonomies']);
        add_action('add_meta_boxes', [__CLASS__, 'register_metaboxes']);

        add_action('after_setup_theme', [__CLASS__, 'register_thumbnail_support'], 20);

        add_action('rest_api_init', [__CLASS__, 'register_featured_image_field']);
        
        // News System Menus
        add_action('admin_menu', [__CLASS__, 'register_news_menus'], 20);

        // Initialize News AJAX handlers
        \NextSafar\Admin\News\NewsAjaxHandler::init();
        
        // Initialize featured meta for multiple post types
        MetaBoxes\HotelFeaturedMeta::init();

        \NextSafar\Admin\WP_Pages_Wrapper::get_instance()->init();
        \NextSafar\Admin\Role_Access_Page::init();

        // ═══════════════════════════════════════════════════
        // MetaBox Hooks Registration
        // ═══════════════════════════════════════════════════
        MetaBoxes\GeoMetaBox::register_hooks();
        MetaBoxes\HotelMetaBox::register_hooks();
        MetaBoxes\GalleryMetaBox::register_hooks();
        MetaBoxes\AirportMetaBox::register_hooks();
        MetaBoxes\AirportFacilities::register_hooks();
        MetaBoxes\AirportServices::register_hooks();
        MetaBoxes\AirportAirlines::register_hooks();
        MetaBoxes\FlagMetaBox::register_hooks();
        MetaBoxes\DestinationMetaBox::register_hooks();
        MetaBoxes\RestaurantMetaBox::register_hooks();
        MetaBoxes\RestaurantFacilities::register_hooks();
        MetaBoxes\HospitalMetaBox::register_hooks();
        MetaBoxes\VisaMetaBox::register_hooks();
        MetaBoxes\TravelGuideMetaBox::register_hooks();
        MetaBoxes\TravelNewsMetaBox::register_hooks();

        // ═══════════════════════════════════════════════════
        // Tourism System — Shared taxonomy across all post types
        // ═══════════════════════════════════════════════════
        Taxonomies\TourismMeta::init();

        // Initialize Post Sync (sidebar metabox for manual API sync)
        \NextSafar\Admin\PostSync::init();

        // Initialize Assets System
        \NextSafar_Assets::init();

        // ═══════════════════════════════════════════════════
        // Admin Panel Initialization
        // ═══════════════════════════════════════════════════
        if (is_admin()) {
            Taxonomies\TermImages::init();
            
            // Initialize Post Generator AJAX handlers
            Admin\PostGenerator\AjaxHandler::init();
        }

        // ═══════════════════════════════════════════════════
        // Admin Menu Registration
        // ═══════════════════════════════════════════════════
        add_action('admin_menu', [__CLASS__, 'register_admin_menus']);
        
        // ساخت/به‌روزرسانی جداول فقط یک‌بار per schema version — نه در هر بارگذاری ادمین
        add_action('admin_init', function () {
            $schema_version = '1.0.0'; // با هر تغییر در schema این را بالا ببر
            if (get_option('nextsafar_news_db_version') === $schema_version) {
                return;
            }
            \NextSafar\Database\NewsTables::createAll();
            \NextSafar\Database\NewsTables::seedDefaultSources();
            update_option('nextsafar_news_db_version', $schema_version);
        }, 5);
    }

    /**
     * Get correct AJAX URL (with port for Local by Flywheel)
     */
    public static function get_ajax_url(): string {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/wp-admin/admin-ajax.php';
    }

    /**
     * Register admin menus for Post Generator
     */
    public static function register_admin_menus(): void {
        add_menu_page(
            'تولید پست هوشمند',
            'تولید پست',
            'manage_options',
            'nextsafar-post-generator',
            [\NextSafar\Admin\PostGenerator\PostGeneratorPage::class, 'render'],  // ✅
            'dashicons-upload',
            30
        );

        add_submenu_page(
            'nextsafar-post-generator',
            'تولید پست',
            'تولید پست',
            'manage_options',
            'nextsafar-post-generator',
            [\NextSafar\Admin\PostGenerator\PostGeneratorPage::class, 'render']  // ✅
        );

        add_submenu_page(
            'nextsafar-post-generator',
            'تنظیمات API',
            'تنظیمات',
            'manage_options',
            'nextsafar-pg-settings',
            [\NextSafar\Admin\PostGenerator\SettingsPage::class, 'render']  // ✅
        );
    }

    /**
     * ✅ فعال‌سازی تصویر شاخص برای همه پست‌تایپ‌های پلاگین
     *
     * وردپرس برای نمایش متاباکس/پنل «تصویر شاخص» دو شرط دارد:
     *   ۱. پست‌تایپ در supports مقدار thumbnail داشته باشد (از قبل دارد)
     *   ۲. قالب فعال add_theme_support('post-thumbnails') را اعلام کرده باشد
     * چون قالب سایت هدلس است و این support را ندارد، اینجا از طرف پلاگین اعلام می‌شود.
     */
    public static function register_thumbnail_support(): void {
        $plugin_types = [
            'hotel', 'airport', 'destination', 'restaurant',
            'hospital', 'visa', 'travelguide', 'travelnews',
        ];

        $existing = get_theme_support('post-thumbnails');

        // قالب به‌صورت سراسری فعال کرده → همه پست‌تایپ‌ها از قبل پوشش داده شده‌اند
        if ($existing === true) {
            return;
        }

        // قالب فقط برای چند پست‌تایپ خاص فعال کرده → لیست‌ها را ادغام کن (چیزی حذف نشود)
        if (is_array($existing) && isset($existing[0]) && is_array($existing[0])) {
            $plugin_types = array_values(array_unique(array_merge($existing[0], $plugin_types)));
        }

        add_theme_support('post-thumbnails', $plugin_types);
    }

    /**
     * ✅ فیلد REST تصویر شاخص برای همه پست‌تایپ‌ها
     * featured_media فقط ID برمی‌گرداند؛ این فیلد URL و مشخصات کامل را می‌دهد.
     */
    public static function register_featured_image_field(): void {
        $types = [
            'hotel', 'airport', 'destination', 'restaurant',
            'hospital', 'visa', 'travelguide', 'travelnews',
        ];

        register_rest_field($types, 'featured_image', [
            'get_callback' => function ($post) {
                $id = get_post_thumbnail_id((int) $post['id']);
                if (!$id) {
                    return null;
                }

                $src = wp_get_attachment_image_src($id, 'full');
                if (!$src) {
                    return null;
                }

                return [
                    'id'     => (int) $id,
                    'url'    => $src[0],
                    'width'  => (int) $src[1],
                    'height' => (int) $src[2],
                    'alt'    => get_post_meta($id, '_wp_attachment_image_alt', true),
                ];
            },
            'update_callback' => null,
            'schema' => [
                'description' => 'تصویر شاخص (URL کامل برای فرانت‌اند)',
                'type'        => 'object',
                'context'     => ['view', 'edit'],
            ],
        ]);
    }

    /**
     * Register all custom post types
     *
     * @return void
     */
    public static function register_post_types(): void {
        PostTypes\Hotel::register();
        PostTypes\Airport::register();
        PostTypes\Destination::register();
        PostTypes\Restaurant::register();
        PostTypes\Hospital::register();
        PostTypes\Visa::register();
        PostTypes\TravelGuide::register();
        PostTypes\TravelNews::register();
    }

    /**
     * Register all custom taxonomies
     *
     * @return void
     */
    public static function register_taxonomies(): void {
        // ═══ Per-post-type Category & Tag ═══
        
        // Hotel
        Taxonomies\HotelCategory::register();
        Taxonomies\HotelTag::register();
        
        // Airport
        Taxonomies\AirportCategory::register();
        Taxonomies\AirportTag::register();
        
        // Destination
        Taxonomies\DestinationCategory::register();
        Taxonomies\DestinationTag::register();
        
        // Restaurant
        Taxonomies\RestaurantCategory::register();
        Taxonomies\RestaurantTag::register();
        
        // Hospital
        Taxonomies\HospitalCategory::register();
        Taxonomies\HospitalTag::register();
        
        // Visa
        Taxonomies\VisaCategory::register();
        Taxonomies\VisaTag::register();
        
        // TravelGuide (راهنمای سفر)
        Taxonomies\TravelGuideCategory::register();
        Taxonomies\TravelGuideTag::register();
        
        // TravelNews (اخبار گردشگری)
        Taxonomies\TravelNewsCategory::register();
        Taxonomies\TravelNewsTag::register();

        // ═══ ✅ Shared Taxonomy — شهرهای توریستی ═══
        // This taxonomy appears on ALL post types (hotel, restaurant, etc.)
        // Supports hierarchical structure: Country → City
        Taxonomies\Tourism::register();
    }

    /**
     * Register all metaboxes for custom post types
     *
     * @return void
     */
    public static function register_metaboxes(): void {
        MetaBoxes\HotelMetaBox::register();
        MetaBoxes\GalleryMetaBox::register();
        MetaBoxes\AirportMetaBox::register();
        MetaBoxes\AirportFacilities::register();
        MetaBoxes\AirportServices::register();
        MetaBoxes\AirportAirlines::register();
        MetaBoxes\FlagMetaBox::register();
        MetaBoxes\DestinationMetaBox::register();
        MetaBoxes\RestaurantMetaBox::register();
        MetaBoxes\RestaurantFacilities::register();
        MetaBoxes\HospitalMetaBox::register();
        MetaBoxes\VisaMetaBox::register();
        MetaBoxes\TravelGuideMetaBox::register();
        MetaBoxes\TravelNewsMetaBox::register();
    }

    /**
     * Register News menu and submenus
     */
    public static function register_news_menus(): void
    {
        // منوی اصلی
        add_menu_page(
            'اخبار',
            'اخبار',
            'manage_options',
            'nextsafar-news',
            [\NextSafar\Admin\News\DashboardPage::class, 'render'],
            'dashicons-rss',
            31
        );

        // زیرمنوی 1: پیشخوان (همان اسلاگ اصلی)
        add_submenu_page(
            'nextsafar-news',
            'آنالیز و پرووایدرها',
            '📊 آنالیز و پرووایدرها',
            'manage_options',
            'nextsafar-news',
            [\NextSafar\Admin\News\DashboardPage::class, 'render']
        );

        // زیرمنوی 2: تولید دستی
        add_submenu_page(
            'nextsafar-news',
            'تولید دستی',
            '🎯 تولید دستی',
            'manage_options',
            'nextsafar-news-manual',
            [\NextSafar\Admin\News\ManualSyncPage::class, 'render']
        );

        // زیرمنوی 3: فیلترها
        add_submenu_page(
            'nextsafar-news',
            'فیلترها',
            '🎛️ فیلترها',
            'manage_options',
            'nextsafar-news-filter',
            [\NextSafar\Admin\News\FilterPage::class, 'render']
        );

        // زیرمنوی 4: تنظیمات
        add_submenu_page(
            'nextsafar-news',
            'تنظیمات',
            '⚙️ تنظیمات',
            'manage_options',
            'nextsafar-news-settings',
            [\NextSafar\Admin\News\NewsSettingsPage::class, 'render']
        );
    }
}