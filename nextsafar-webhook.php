<?php
/**
 * Plugin Name: NextSafar Webhook
 * Plugin URI: https://github.com/alirezafallaah78/nextsafar
 * Description: پلاگین مدیریت محتوا برای پلتفرم نکست‌سفر - نسخه یکپارچه
 * Version: 2.0.0
 * Author: Alireza Fallah
 * Author URI: https://github.com/alirezafallah-dev
 * Text Domain: nextsafar
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * License: MIT
 */

if (!defined('ABSPATH')) exit;

// ═══════════════════════════════════════════════════════════
// Plugin Constants
// ═══════════════════════════════════════════════════════════

// ✅ کد صحیح (بدون تکرار):
if (!defined('NEXTSAFAR_VERSION')) {
    define('NEXTSAFAR_VERSION', '2.0.0');
}
if (!defined('NEXTSAFAR_PATH')) {
    define('NEXTSAFAR_PATH', plugin_dir_path(__FILE__));
}
if (!defined('NEXTSAFAR_URL')) {
    define('NEXTSAFAR_URL', plugin_dir_url(__FILE__));
}

// Admin Constants (برای سازگاری با کلاس‌های admin)
if (!defined('NEXTSAFAR_ADMIN_VERSION')) {
    define('NEXTSAFAR_ADMIN_VERSION', '2.0.0');
}
if (!defined('NEXTSAFAR_ADMIN_PATH')) {
    define('NEXTSAFAR_ADMIN_PATH', NEXTSAFAR_PATH);
}
if (!defined('NEXTSAFAR_ADMIN_URL')) {
    define('NEXTSAFAR_ADMIN_URL', NEXTSAFAR_URL);
}

// ═══════════════════════════════════════════════════════════
// 1. AUTOLOADER
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/autoloader.php';

// ═══════════════════════════════════════════════════════════
// 2. ADMIN SYSTEM (از پلاگین admin)
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/admin/functions-helpers.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-helpers.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-icons.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-assets.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-admin.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-content-manager.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-media-folders.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-media-modal-enhancer.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-updates.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-wp-pages-wrapper.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-external-menus.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-categories.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-login.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-role-access.php';

// Widgets
require_once NEXTSAFAR_PATH . 'inc/admin/class-widget-base.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-widgets.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-dashboard.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-stats.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-revenue-chart.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-recent-bookings.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-visa-applications.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-yoast-seo.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-widget-cache.php';

// ═══════════════════════════════════════════════════════════
// 3. CORE HOOKS
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/activator.php';
require_once NEXTSAFAR_PATH . 'inc/deactivator.php';
require_once NEXTSAFAR_PATH . 'inc/media.php';

// ═══════════════════════════════════════════════════════════
// 4. POST TYPES
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/posttypes/hotel.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/airport.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/destination.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/restaurant.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/hospital.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/visa.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/travelguide.php';
require_once NEXTSAFAR_PATH . 'inc/posttypes/travelnews.php';

// ═══════════════════════════════════════════════════════════
// TAXONOMIES - Custom taxonomies
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/taxonomies/hotel-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/hotel-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/airport-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/airport-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/destination-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/destination-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/restaurant-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/restaurant-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/hospital-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/hospital-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/visa-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/visa-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/travelguide-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/travelguide-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/travelnews-category.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/travelnews-tag.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/tourism.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/tourism-meta.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/term-images.php';

// ═══════════════════════════════════════════════════════════
// 5. METABOXES
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/metaboxes/hotel-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/hotel-featured-meta.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/airport-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/airport-facilities.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/airport-services.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/airport-airlines.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/flag-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/destination-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/gallery-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/restaurant-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/restaurant-facilities.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/hospital-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/visa-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/travelguide-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/travelnews-metabox.php';
require_once NEXTSAFAR_PATH . 'inc/metaboxes/geo-metabox.php';

// ═══════════════════════════════════════════════════════════
// 6. API CLIENTS
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/api/clients/class-base-client.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-serpapi-client.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-searchapi-client.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-wikipedia-client.php';

// ═══════════════════════════════════════════════════════════
// 7. API SERVICES
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/api/services/class-image-manager.php';

// ═══════════════════════════════════════════════════════════
// 8. SYNC LAYER
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/sync/class-geo-schema.php';
require_once NEXTSAFAR_PATH . 'inc/sync/class-geo-sync.php';
require_once NEXTSAFAR_PATH . 'inc/sync/traits/class-place-enrich-trait.php';

// ═══════════════════════════════════════════════════════════
// 9. API SYNC
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/api/sync/class-base-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-hotel-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-destination-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-restaurant-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-hospital-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-airport-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-batch-sync.php';

// ═══════════════════════════════════════════════════════════
// 10. POST GENERATOR
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/admin/post-generator/class-post-generator-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/post-generator/class-settings-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/post-generator/class-ajax-handler.php';

// ═══════════════════════════════════════════════════════════
// 11. NEWS SYSTEM
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/database/class-news-tables.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-rate-limiter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-gemini-client.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-rss-fetcher.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-newsapi-fetcher.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-duplicate-checker.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-news-filter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-relevance-filter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-ai-rewriter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-news-sync.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-news-batch-sync.php';

// ═══════════════════════════════════════════════════════════
// 12. NEWS ADMIN
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/admin/news/class-dashboard-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-manual-sync-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-filter-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-settings-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-news-ajax-handler.php';

// ═══════════════════════════════════════════════════════════
// 14. CORE
// ═══════════════════════════════════════════════════════════

require_once NEXTSAFAR_PATH . 'inc/core.php';

// ═══════════════════════════════════════════════════════════
// NEXTSAFAR CORE CONTAINER (مثل پلاگین admin)
// ═══════════════════════════════════════════════════════════

class NextSafar_Core {
    private static $instance = null;

    public $admin;
    public $assets;
    public $content_manager;
    public $categories;
    public $media_folders;
    public $media_modal;
    public $updates;
    public $wp_wrapper;
    public $external_menus;
    public $dashboard;
    public $login;
    public $role_access;

    private function __construct() {
        $this->init_components();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_components() {
        $this->admin = new NextSafar_Admin();
        $this->assets = new NextSafar_Assets();
        $this->content_manager = new NextSafar_Content_Manager();
        $this->categories = new NextSafar_Categories();
        $this->media_folders = new NextSafar_Media_Folders();
        $this->media_modal = new NextSafar_Media_Modal_Enhancer();
        $this->updates = new NextSafar_Updates();
        $this->wp_wrapper = new NextSafar_WP_Pages_Wrapper();
        $this->external_menus = new NextSafar_External_Menus();
        $this->dashboard = new NextSafar_Dashboard();
        $this->login = new NextSafar_Login();
        $this->role_access = new NextSafar_Role_Access();

        // Init all components
        foreach (get_object_vars($this) as $component) {
            if (is_object($component) && method_exists($component, 'init')) {
                $component->init();
            }
        }

        // Widget Cache Hooks
        NextSafar_Widget_Cache::init_hooks();

        // Clear caches on save
        add_action('save_post', function() {
            delete_transient('nextsafar_widget_recent_content');
            delete_transient('nextsafar_widget_stats_data');
        }, 20);

        add_action('wp_insert_comment', function() {
            delete_transient('nextsafar_pending_comments_count');
        });

        add_action('transition_comment_status', function() {
            delete_transient('nextsafar_pending_comments_count');
        });

        add_action('upgrader_process_complete', function() {
            delete_transient('nextsafar_pending_updates_count');
        }, 10, 0);
    }
}

function nextsafar() {
    return NextSafar_Core::get_instance();
}

// ═══════════════════════════════════════════════════════════
// ACTIVATION / DEACTIVATION
// ═══════════════════════════════════════════════════════════

register_activation_hook(__FILE__, ['NextSafar\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['NextSafar\Deactivator', 'deactivate']);

// Activation: ایجاد جدول media folders
function nextsafar_activation_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'nextsafar_media_folders';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        parent_id BIGINT(20) UNSIGNED DEFAULT 0,
        order_index INT(11) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_parent (parent_id),
        KEY idx_order (order_index)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    add_option('nextsafar_admin_version', NEXTSAFAR_ADMIN_VERSION);
}
register_activation_hook(__FILE__, 'nextsafar_activation_tables');

// ═══════════════════════════════════════════════════════════
// BOOTSTRAP
// ═══════════════════════════════════════════════════════════

add_action('plugins_loaded', function() {
    // Init Admin System
    nextsafar();
    
    // Init Webhook Core
    NextSafar\Core::init();
}, 10);

// ═══════════════════════════════════════════════════════════
// MENU ICONS (از icon-menu.php)
// ═══════════════════════════════════════════════════════════

add_action('init', function() {
    if (!function_exists('nextsafar_register_cpt_icons')) return;
    
    nextsafar_register_cpt_icons([
        'hotel' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 22v-6.57"/><path d="M12 11h.01"/><path d="M12 7h.01"/><path d="M14 15.43V22"/><path d="M15 16a5 5 0 0 0-6 0"/><path d="M16 11h.01"/><path d="M16 7h.01"/><path d="M8 11h.01"/><path d="M8 7h.01"/><rect x="4" y="2" width="16" height="20" rx="2"/></svg>',
        'destination' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>',
        'travelguide' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>',
        'travelnews' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>',
        'restaurant' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>',
        'airport' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>',
        'visa' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4z"/><path d="M12 6v1"/><path d="M12 17v1"/><circle cx="12" cy="12" r="4"/><path d="m10 12 1.5 1.5L14 11"/><path d="M9 4h6"/></svg>',
        'tour' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 22h20"/><path d="M6.36 17.4 4 17l-2-4 1.1-.55a2 2 0 0 1 1.8 0l.17.1a2 2 0 0 0 1.8 0L8 12 5 6l.9-.45a2 2 0 0 1 2.09.2l4.02 3a2 2 0 0 0 2.1.2l4.19-2.06a2.41 2.41 0 0 1 1.73-.17L21 7a1.4 1.4 0 0 1 .87 1.99l-.38.76c-.23.46-.6.84-1.07 1.08L7.58 17.2a2 2 0 0 1-1.22.18Z"/></svg>',
        'hospital' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v4"/><path d="M14 21v-3a2 2 0 0 0-4 0v3"/><path d="M14 9h-4"/><path d="M18 11h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2"/><path d="M18 21V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16"/></svg>',
    ]);
}, 1);