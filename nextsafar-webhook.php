<?php
/**
 * Plugin Name: NextSafar Webhook
 * Plugin URI: https://github.com/alirezafallaah78/nextsafar
 * Description: پلاگین مدیریت محتوا برای پلتفرم نکست‌سفر. شامل پست‌تایپ‌های سفارشی، متاباکس‌ها، طبقه‌بندی‌ها و سیستم تولید پست هوشمند.
 * Version: 1.0.0
 * Author: Alireza Fallah
 * Author URI: https://github.com/alirezafallah-dev
 * Text Domain: nextsafar
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

if (!defined('ABSPATH')) exit;

// ═══════════════════════════════════════════════════════════
// Plugin Constants
// ═══════════════════════════════════════════════════════════
define('NEXTSAFAR_VERSION', '1.0.0');
define('NEXTSAFAR_PATH', plugin_dir_path(__FILE__));
define('NEXTSAFAR_URL', plugin_dir_url(__FILE__));

// ═══════════════════════════════════════════════════════════
// 1. AUTOLOADER - Smart class loading
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/autoloader.php';

// ═══════════════════════════════════════════════════════════
// 2. CORE HOOKS - Activation and deactivation
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/activator.php';
require_once NEXTSAFAR_PATH . 'inc/deactivator.php';
require_once NEXTSAFAR_PATH . 'inc/media.php';

// ═══════════════════════════════════════════════════════════
// 3. POST TYPES - Custom content types
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
// 4. TAXONOMIES - Categories & Tags for all post types
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
require_once NEXTSAFAR_PATH . 'inc/taxonomies/tourism-meta.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/tourism.php';
require_once NEXTSAFAR_PATH . 'inc/taxonomies/term-images.php';

// ═══════════════════════════════════════════════════════════
// 5. METABOXES - Custom fields in editor
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
// 6. ADMIN - Backend interface (basic)
// ═══════════════════════════════════════════════════════════
include_once NEXTSAFAR_PATH . 'inc/admin/icon-menu.php';

// ═══════════════════════════════════════════════════════════
// 7. API CLIENTS - External service integrations
//    ⚠️ Order matters: BaseClient must load first
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-api-cache.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-base-client.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-serpapi-client.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-searchapi-client.php';
require_once NEXTSAFAR_PATH . 'inc/api/clients/class-wikipedia-client.php';

// ═══════════════════════════════════════════════════════════
// 8. API SERVICES - Shared services
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/api/services/class-image-manager.php';

// ═══════════════════════════════════════════════════════════
// 9. SYNC LAYER - Data synchronization
//    ⚠️ Order matters: Schema → Sync → Traits
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/sync/class-geo-schema.php';
require_once NEXTSAFAR_PATH . 'inc/sync/class-geo-sync.php';
require_once NEXTSAFAR_PATH . 'inc/sync/traits/class-place-enrich-trait.php';

// ═══════════════════════════════════════════════════════════
// 10. API SYNC - Post generation sync classes
//    ⚠️ Order matters: BaseSync must load before child classes
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-base-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-hotel-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-destination-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-restaurant-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-hospital-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-airport-sync.php';
require_once NEXTSAFAR_PATH . 'inc/api/sync/class-batch-sync.php';

// ═══════════════════════════════════════════════════════════
// 11. ADMIN - Post Generator pages
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/admin/post-generator/class-post-generator-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/post-generator/class-settings-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/post-generator/class-ajax-handler.php';

// ═══════════════════════════════════════════════════════════
// 12. NEWS SYSTEM - News fetching, filtering, AI translation
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/database/class-news-tables.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-rate-limiter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-gemini-quota.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-gemini-client.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-rss-fetcher.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-newsapi-fetcher.php';

// ═══════════════════════════════════════════════════════════
// 13. NEWS SYSTEM - Business Logic
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/news/class-duplicate-checker.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-news-filter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-relevance-filter.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-ai-rewriter.php';

// ═══════════════════════════════════════════════════════════
// 14. NEWS SYSTEM - Sync Logic
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/news/class-news-sync.php';
require_once NEXTSAFAR_PATH . 'inc/news/class-news-batch-sync.php';

// ═══════════════════════════════════════════════════════════
// 15. NEWS SYSTEM - Admin Pages & AJAX
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-dashboard-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-manual-sync-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-filter-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-settings-page.php';
require_once NEXTSAFAR_PATH . 'inc/admin/news/class-news-ajax-handler.php';

// ═══════════════════════════════════════════════════════════
// 16. ADMIN POST SYNC - Manual sync from API based on post title
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/admin/class-post-sync.php';

// ═══════════════════════════════════════════════════════════
// 17. CORE - Main plugin core (load last)
// ═══════════════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/core.php';

// ACTIVATION / DEACTIVATION HOOKS
// ═══════════════════════════════════════════════════════════
register_activation_hook(__FILE__, ['NextSafar\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['NextSafar\Deactivator', 'deactivate']);

// ═══════════════════════════════════════════════════
// 🎨 NextSafar Layout & Admin System
// ═══════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/admin/helpers/functions.php';
require_once NEXTSAFAR_PATH . 'inc/admin/helpers/class-helpers.php';
require_once NEXTSAFAR_PATH . 'inc/admin/helpers/class-icons.php';
require_once NEXTSAFAR_PATH . 'inc/admin/helpers/class-external-menus.php';
require_once NEXTSAFAR_PATH . 'inc/admin/helpers/class-wp-pages-wrapper.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-core.php';
// Define global nextsafar() function
if (!function_exists('nextsafar')) {
    function nextsafar() {
        return NextSafar_Core::get_instance();
    }
}
require_once NEXTSAFAR_PATH . 'inc/admin/class-assets.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-layout.php';

// ═══════════════════════════════════════════════════
// 🎨 NextSafar Admin System
// ═══════════════════════════════════════════════════
require_once NEXTSAFAR_PATH . 'inc/admin/class-widget-base.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-widget-cache.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-widgets.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-stats.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-revenue-chart.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-recent-bookings.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-visa-applications.php';
require_once NEXTSAFAR_PATH . 'inc/admin/widgets/class-widget-yoast-seo.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-dashboard.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-content-manager.php';
// require_once NEXTSAFAR_PATH . 'inc/admin/class-role-access.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-categories.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-media-folders.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-media-modal-enhancer.php';
require_once NEXTSAFAR_PATH . 'inc/admin/class-updates.php';

// 🆕 Assets System - با namespace صحیح
require_once NEXTSAFAR_PATH . 'inc/admin/class-assets.php';

require_once NEXTSAFAR_PATH . 'inc/admin/class-admin.php';

// Initialize admin system
// Initialize admin system via NextSafar_Core
// ✅ باید قبل از admin_menu اجرا بشه تا همه pages ثبت بشن
add_action('admin_menu', function() {
    nextsafar(); // این همه components رو init می‌کنه
}, 0); // priority 0 = قبل از همه
// BOOTSTRAP - Plugin initialization
// ═══════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════
// 🎨 Initialize Admin System
// ═══════════════════════════════════════════════════
add_action('admin_menu', function() {
    nextsafar(); // Initialize all components before menu registration
}, 0);