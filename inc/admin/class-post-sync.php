<?php
/**
 * PostSync — Sync existing posts with API data based on post title
 *
 * Features:
 * - Adds "🔄 سینک از API" metabox in sidebar for all 5 post types
 * - Clicking button opens a modal showing API candidates
 * - User selects the matching place from results
 * - Data is enriched (not replaced) in the existing post
 * - Bulk sync for legacy posts (Tools menu)
 * - Similarity check to prevent wrong matches
 * - Hotel enrichment with stars, amenities, check-in/out times
 *
 * @package NextSafar\Admin
 * @since 1.0.0
 */

namespace NextSafar\Admin;

use NextSafar\Api\Clients\SearchApiClient;
use NextSafar\Api\Services\ImageManager;
use NextSafar\Sync\GeoSync;
use NextSafar\Sync\GeoSchema;

if (!defined('ABSPATH')) {
    exit;
}

class PostSync
{
    /** Post types that support API sync */
    public const TYPES = ['hotel', 'restaurant', 'destination', 'hospital', 'airport'];

    /* ========================================================================
       Init — Register hooks
       ======================================================================== */
    
    public static function init(): void
    {
        add_action('add_meta_boxes', [__CLASS__, 'registerBox']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_menu', [__CLASS__, 'addBulkPage']);
        
        // AJAX actions
        add_action('wp_ajax_ns_post_sync_candidates', [__CLASS__, 'ajaxCandidates']);
        add_action('wp_ajax_ns_post_sync_apply', [__CLASS__, 'ajaxApply']);
        add_action('wp_ajax_ns_bulk_sync_step', [__CLASS__, 'ajaxBulkStep']);
    }

    /* ========================================================================
       Sidebar Metabox — "🔄 سینک از API"
       ======================================================================== */
    
    public static function registerBox(): void
    {
        foreach (self::TYPES as $type) {
            add_meta_box(
                'ns_post_sync_box',
                '🔄 سینک از API',
                [__CLASS__, 'renderBox'],
                $type,
                'side',
                'low'
            );
        }
    }

    public static function renderBox($post): void
    {
        $pid = (int) $post->ID;
        $query = esc_attr(self::buildQuery($post));
        
        // ✅ دریافت اطلاعات API فعلی
        $external_id = \NextSafar\Sync\GeoSchema::get($pid, 'external_id');
        $source = \NextSafar\Sync\GeoSchema::get($pid, 'source');
        $last_sync = \NextSafar\Sync\GeoSchema::get($pid, 'last_sync');
        $place_id = \NextSafar\Sync\GeoSchema::get($pid, 'place_id');
        
        $activeSource = self::getActiveSource();
        $activeSourceLabel = $activeSource === 'serpapi' ? 'SerpApi' : 'SearchApi';
        $activeSourceColor = $activeSource === 'serpapi' ? '#d1ecf1' : '#d4edda';
        
        // ═══════════════════════════════════════════════
        // بخش 1: دکمه سینک (بالا)
        // ═══════════════════════════════════════════════
        echo '<div style="margin-bottom:12px;">';
        echo '<button type="button" class="button button-primary" id="ns-sync-open" 
                data-post="' . $pid . '" 
                data-query="' . $query . '" 
                style="width:100%;">
                🔄 دریافت اطلاعات از API
            </button>';
        echo '<p style="margin:6px 0 0;font-size:11px;color:#666;">
                منبع فعال: 
                <span style="display:inline-block;padding:1px 6px;border-radius:3px;background:' . $activeSourceColor . ';font-size:10px;">
                    <strong>' . $activeSourceLabel . '</strong>
                </span>
            </p>';
        echo '</div>';
        
        // ═══════════════════════════════════════════════
        // بخش 2: اطلاعات API فعلی
        // ═══════════════════════════════════════════════
        $hasData = !empty($external_id) || !empty($source) || !empty($last_sync);
        
        if ($hasData) {
            echo '<div style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:6px;padding:10px;font-size:12px;">';
            echo '<div style="font-weight:600;margin-bottom:8px;color:#1d2327;">📊 اطلاعات فعلی</div>';
            
            // Source
            if (!empty($source)) {
                $sourceColor = [
                    'serpapi' => '#d1ecf1',
                    'searchapi' => '#d4edda',
                    'wikipedia' => '#fff3cd',
                    'manual' => '#f8d7da',
                ][$source] ?? '#f0f0f0';
                
                echo '<div style="margin-bottom:6px;">
                        <span style="color:#666;">منبع:</span> 
                        <span style="display:inline-block;padding:1px 6px;border-radius:3px;background:' . $sourceColor . ';font-size:11px;">
                            <strong>' . esc_html($source) . '</strong>
                        </span>
                    </div>';
            }
            
            // External ID
            if (!empty($external_id)) {
                echo '<div style="margin-bottom:6px;">
                        <span style="color:#666;">شناسه:</span> 
                        <code style="font-size:10px;word-break:break-all;">' . esc_html(mb_substr($external_id, 0, 30)) . (mb_strlen($external_id) > 30 ? '...' : '') . '</code>
                    </div>';
            }
            
            // Place ID
            if (!empty($place_id) && $place_id !== $external_id) {
                echo '<div style="margin-bottom:6px;">
                        <span style="color:#666;">Place ID:</span> 
                        <code style="font-size:10px;word-break:break-all;">' . esc_html(mb_substr($place_id, 0, 30)) . (mb_strlen($place_id) > 30 ? '...' : '') . '</code>
                    </div>';
            }
            
            // Last Sync
            if (!empty($last_sync)) {
                $syncTime = strtotime($last_sync);
                $timeAgo = human_time_diff($syncTime, current_time('timestamp'));
                echo '<div style="margin-bottom:0;">
                        <span style="color:#666;">آخرین سینک:</span> 
                        <span style="font-size:11px;">' . esc_html($timeAgo) . ' پیش</span>
                        <div style="color:#999;font-size:10px;">' . esc_html($last_sync) . '</div>
                    </div>';
            }
            
            echo '</div>';
        } else {
            echo '<div style="background:#fff3cd;border:1px solid #ffeaa7;border-radius:6px;padding:10px;font-size:12px;color:#856404;">';
            echo '⚠️ <strong>بدون داده API</strong><br>';
            echo '<span style="font-size:11px;">این پست به صورت دستی ساخته شده. برای دریافت اطلاعات از Google Maps، دکمه بالا را بزنید.</span>';
            echo '</div>';
        }
        
        // ═══════════════════════════════════════════════
        // بخش 3: راهنما
        // ═══════════════════════════════════════════════
        echo '<p style="margin-top:10px;font-size:11px;color:#666;line-height:1.5;">
                💡 با کلیک روی دکمه بالا، نام پست در Google Maps جستجو می‌شود 
                و می‌توانید نتیجه مناسب را انتخاب کنید.
            </p>';
    }

    /* ========================================================================
       Bulk Sync Page (Tools menu)
       ======================================================================== */
    
    public static function addBulkPage(): void
    {
        add_submenu_page(
            'tools.php',
            'همگام‌سازی انبوه پست‌ها',
            '🔄 سینک انبوه پست‌ها',
            'manage_options',
            'ns-bulk-sync',
            [__CLASS__, 'renderBulkPage']
        );
    }

    public static function renderBulkPage(): void
    {
        $count = self::countUnsynced();
        
        echo '<div class="wrap">';
        echo '<h1>🔄 همگام‌سازی انبوه پست‌های قدیمی</h1>';
        echo '<p>پست‌هایی که هنوز مختصات/شناسه خارجی ندارند: <strong>' . $count . '</strong></p>';
        echo '<p class="description">
                  در هر گام ۵ پست پردازش می‌شود. 
                  فقط پست‌هایی با شباهت نام کافی اعمال می‌شوند.
              </p>';
        echo '<button class="button button-primary" id="ns-bulk-start">شروع سینک انبوه</button>';
        echo '<span id="ns-bulk-progress" style="margin-inline-start:10px;font-weight:600;"></span>';
        echo '<div id="ns-bulk-log" style="margin-top:14px;background:#fff;border:1px solid #dcdcde;
              padding:10px;max-height:420px;overflow-y:auto;font-size:12px;line-height:1.9;"></div>';
        echo '</div>';
    }

    /* ========================================================================
       Assets — Load JS
       ======================================================================== */
    
    public static function assets($hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        
        $isPostScreen = $screen && in_array($screen->post_type ?? '', self::TYPES, true);
        $isBulkScreen = ($hook === 'tools_page_ns-bulk-sync');
        
        if (!$isPostScreen && !$isBulkScreen) {
            return;
        }

        wp_enqueue_script(
            'ns-post-sync',
            NEXTSAFAR_URL . 'assets/admin/js/pages/post-sync.js',
            ['jquery'],
            NEXTSAFAR_VERSION,
            true
        );

        // ✅ استفاده از URL با پورت صحیح (برای Local by Flywheel)
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $ajax_url = $protocol . '://' . $host . '/wp-admin/admin-ajax.php';

        wp_localize_script('ns-post-sync', 'NS_SYNC', [
            'nonce'   => wp_create_nonce('ns_post_sync'),
            'ajaxUrl' => $ajax_url,
        ]);
    }

    /* ========================================================================
       AJAX: Get Candidates from SearchApi
       ======================================================================== */
    
    public static function ajaxCandidates(): void
    {
        check_ajax_referer('ns_post_sync', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);
        $query = sanitize_text_field($_POST['query'] ?? '');
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error(['message' => 'پست پیدا نشد']);
        }

        if ($query === '') {
            $query = self::buildQuery($post);
        }

        $client = self::getClient();
        if (!$client) {
            $source = self::getActiveSource();
            wp_send_json_error(['message' => "کلید {$source} تنظیم نشده (منو: تنظیمات API)"]);
        }

        // ابتدا به فارسی جستجو کن
        $places = $client->searchPlacesByName($query, 6, 'fa');

        if (is_wp_error($places)) {
            wp_send_json_error(['message' => $places->get_error_message()]);
        }

        // اگر نتیجه نداشت، انگلیسی
        if (empty($places)) {
            $places = $client->searchPlacesByName($query, 6, 'en');

            if (is_wp_error($places)) {
                wp_send_json_error(['message' => $places->get_error_message()]);
            }
        }

        if (empty($places)) {
            wp_send_json_error([
                'message' => 'نتیجه‌ای پیدا نشد؛ عبارت جستجو را تغییر دهید',
                'query'   => $query,
            ]);
        }

        $out = [];
        foreach ($places as $p) {
            $out[] = [
                'name'       => $p['name'] ?? '',
                'address'    => $p['address'] ?? '',
                'rating'     => $p['rating'] ?? 0,
                'reviews'    => $p['reviews_count'] ?? 0,
                'lat'        => $p['lat'] ?? 0,
                'lng'        => $p['lng'] ?? 0,
                'thumbnail'  => $p['thumbnail'] ?? ($p['images'][0] ?? ''),
                'type'       => $p['type'] ?? '',
                'similarity' => round(self::similarity($post->post_title, $p['name'] ?? ''), 2),
                'data'       => $p,
            ];
        }

        wp_send_json_success(['candidates' => $out, 'query' => $query]);
    }

    /* ========================================================================
       AJAX: Apply Selected Candidate
       ======================================================================== */
    
    public static function ajaxApply(): void
    {
        check_ajax_referer('ns_post_sync', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        // ✅ افزایش timeout برای عملیات سنگین
        @set_time_limit(120);
        @ini_set('max_execution_time', 120);

        $post_id = (int) ($_POST['post_id'] ?? 0);

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        $data = json_decode(wp_unslash($_POST['data'] ?? ''), true);
        $force = !empty($_POST['force']);
        $post = get_post($post_id);

        if (!$post || !is_array($data)) {
            wp_send_json_error(['message' => 'داده نامعتبر']);
        }

        $sim = self::similarity($post->post_title, $data['name'] ?? '');

        if ($sim < 0.35 && !$force) {
            wp_send_json_error([
                'message' => 'شباهت نام کم است (' . round($sim * 100) . '٪). اگر مطمئنی، با force اعمال کن.',
            ]);
        }

        $result = self::applyToPost($post, $data);

        wp_send_json_success($result);
    }

    /* ========================================================================
       AJAX: Bulk Sync Step
       ======================================================================== */
    
    public static function ajaxBulkStep(): void
    {
        check_ajax_referer('ns_post_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        $batch = min(5, max(1, (int) ($_POST['batch'] ?? 5)));
        $offset = max(0, (int) ($_POST['offset'] ?? 0));
        $ids = self::getUnsyncedIds($batch, $offset);
        $client = self::getClient();

        $results = [];

        foreach ($ids as $pid) {
            $post = get_post($pid);
            if (!$post) {
                continue;
            }

            if (!$client) {
                $results[] = [
                    'id'     => $pid, 
                    'title'  => $post->post_title, 
                    'status' => 'no_key',
                    'source' => self::getActiveSource(),
                ];
                continue;
            }

            $query = self::buildQuery($post);
            $places = $client->searchPlacesByName($query, 3, 'fa');

            if (is_wp_error($places) || empty($places)) {
                $places = $client->searchPlacesByName($query, 3, 'en');
            }

            if (is_wp_error($places) || empty($places)) {
                $results[] = ['id' => $pid, 'title' => $post->post_title, 'status' => 'no_result'];
                continue;
            }

            $best = $places[0];
            $sim = round(self::similarity($post->post_title, $best['name'] ?? ''), 2);

            if ($sim < 0.35) {
                $results[] = [
                    'id'     => $pid,
                    'title'  => $post->post_title,
                    'status' => 'low_sim',
                    'sim'    => (int) ($sim * 100),
                ];
                continue;
            }

            self::applyToPost($post, $best);

            $results[] = [
                'id'     => $pid,
                'title'  => $post->post_title,
                'status' => 'ok',
                'sim'    => (int) ($sim * 100),
            ];
        }

        wp_send_json_success([
            'results'   => $results,
            'processed' => count($ids),
            'offset'    => $offset + count($ids),
        ]);
    }

    /* ========================================================================
       Apply Data to Post — Core Logic
       ======================================================================== */
    
    public static function applyToPost($post, array $d): array
    {
        $pid = (int) $post->ID;
        $applied = [];

        // Hotel enrichment: try hotels engine for better data
        if ($post->post_type === 'hotel') {
            $d = self::enrichHotelFromHotelsEngine($d, $d['city'] ?? '');
        }

        // Stars from type string like "4-star hotel"
        if (empty($d['stars']) && !empty($d['type']) && preg_match('/(\d+)\s*[-–]?\s*star/i', $d['type'], $m)) {
            $d['stars'] = (int) $m[1];
        }

        // name_en only if it is an English name
        $candName = $d['name_en'] ?? ($d['name'] ?? '');
        $nameEn = preg_match('/^[\x20-\x7E]+$/', $candName) ? $candName : '';

        GeoSync::apply($pid, [
            'external_id'    => $d['place_id'] ?? '',
            'place_id'       => $d['place_id'] ?? '',
            'name_en'        => $nameEn,
            'address'        => $d['address'] ?? '',
            'city'           => $d['city'] ?? '',
            'country'        => $d['country'] ?? '',
            'phone'          => $d['phone'] ?? '',
            'website'        => $d['website'] ?? '',
            'rating'         => !empty($d['rating']) ? (string) $d['rating'] : '',
            'reviews_count'  => !empty($d['reviews_count']) ? (string) $d['reviews_count'] : '',
            'price_level'    => $d['price_level'] ?? '',
            'stars'          => !empty($d['stars']) ? (string) $d['stars'] : '',
            'check_in_time'  => $d['check_in_time'] ?? '',
            'check_out_time' => $d['check_out_time'] ?? '',
            'lat'            => !empty($d['lat']) ? (string) $d['lat'] : '',
            'lng'            => !empty($d['lng']) ? (string) $d['lng'] : '',
        ], self::getActiveSource()); // ✅ استفاده از provider فعال

        $applied[] = 'geo+contact+stars+times';

        // 2) Working hours
        $hours = $d['opening_hours'] ?? ($d['hours'] ?? null);
        if (!empty($hours) && is_array($hours)) {
            update_post_meta($pid, '_geo_hours', wp_json_encode($hours));

            if ($post->post_type === 'destination') {
                update_post_meta($pid, '_destination_work_time', $hours);
            }
            if ($post->post_type === 'restaurant') {
                update_post_meta($pid, '_restaurant_work_time', $hours);
            }
            if ($post->post_type === 'hospital') {
                update_post_meta($pid, '_hospital_work_time', $hours);
            }

            $applied[] = 'hours';
        }

        // 3) Menu link (restaurant)
        if ($post->post_type === 'restaurant' && !empty($d['menu_link'])) {
            update_post_meta($pid, '_restaurant_menu_link', esc_url_raw($d['menu_link']));
            $applied[] = 'menu';
        }

        // 4) Hotel amenities
        if ($post->post_type === 'hotel' && !empty($d['amenities']) && is_array($d['amenities'])) {
            update_post_meta($pid, '_hotel_amenities', array_map('sanitize_text_field', $d['amenities']));
            $applied[] = 'amenities(' . count($d['amenities']) . ')';
        }

        // 5) Hotel nearby places
        if ($post->post_type === 'hotel' && !empty($d['nearby_places']) && is_array($d['nearby_places'])) {
            $clean = [];

            foreach ($d['nearby_places'] as $np) {
                if (empty($np['name'])) {
                    continue;
                }

                $coords = '';
                if (isset($np['gps_coordinates']['latitude'])) {
                    $coords = $np['gps_coordinates']['latitude'] . ',' . $np['gps_coordinates']['longitude'];
                }

                $clean[] = [
                    'name'          => sanitize_text_field($np['name']),
                    'type'          => 'manual',
                    'category'      => sanitize_text_field($np['type'] ?? ''),
                    'coords'        => $coords,
                    'distance_km'   => floatval($np['distance'] ?? 0),
                    'distance_unit' => 'km',
                ];
            }

            if ($clean) {
                update_post_meta($pid, '_hotel_near_locations', $clean);
                $applied[] = 'nearby(' . count($clean) . ')';
            }
        }

        // 6) Images: featured + gallery
        $images = $d['images'] ?? [];
        if (!empty($images)) {
            // Featured image
            if (!has_post_thumbnail($pid)) {
                try {
                    ImageManager::setFeaturedImage($pid, $images[0], 'api');
                    $applied[] = 'featured_image';
                } catch (\Throwable $e) {
                    error_log('⚠️ [PostSync] Featured image failed: ' . $e->getMessage());
                }
            }

            // Gallery - ✅ FIXED: correct parameters (meta_key, max)
            try {
                $galleryKey = '_' . $post->post_type . '_gallery';
                $n = ImageManager::fillGallery($pid, $images, $galleryKey, 6);
                if ($n) {
                    $applied[] = 'gallery(' . $n . ')';
                }
            } catch (\Throwable $e) {
                error_log('⚠️ [PostSync] Gallery failed: ' . $e->getMessage());
            }
        }

        // 7) Content only if empty
        $desc = trim(wp_strip_all_tags($d['description'] ?? ''));
        if ($desc !== '' && trim($post->post_content) === '') {
            wp_update_post([
                'ID'           => $pid,
                'post_content' => sanitize_textarea_field($desc),
            ]);
            $applied[] = 'content';
        }

        return ['applied' => $applied, 'post_id' => $pid];
    }

    /* ========================================================================
       Hotel Enrichment — Use hotels engine for stars, amenities, etc.
       ======================================================================== */
    
    private static function enrichHotelFromHotelsEngine(array $d, string $city): array
    {
        $client = self::getClient();
        if (!$client) {
            return $d;
        }

        $q = trim(($d['name'] ?? '') . ($city !== '' ? ', ' . $city : ''));
        $hotels = $client->searchHotels($q, ['limit' => 5]);

        if (is_wp_error($hotels) || empty($hotels)) {
            return $d;
        }

        $best = null;
        $bestSim = 0.0;

        foreach ($hotels as $h) {
            $s = self::similarity($d['name'] ?? '', $h['name'] ?? '');
            if ($s > $bestSim) {
                $bestSim = $s;
                $best = $h;
            }
        }

        if (!$best || $bestSim < 0.5) {
            return $d;
        }

        foreach (['stars', 'check_in_time', 'check_out_time', 'price', 'price_formatted', 
                  'amenities', 'nearby_places', 'description'] as $k) {
            if (!empty($best[$k]) && empty($d[$k])) {
                $d[$k] = $best[$k];
            }
        }

        if (!empty($best['images']) && count($best['images']) > count($d['images'] ?? [])) {
            $d['images'] = $best['images'];
        }

        return $d;
    }

    /* ========================================================================
       Helpers
       ======================================================================== */
    
    /**
     * Get API client based on active provider setting
     * Uses the same provider as Post Generator
     */
    private static function getClient(): ?object
    {
        $source = (string) get_option('nextsafar_active_provider', 'searchapi');

        if ($source === 'serpapi') {
            $key = (string) get_option('nextsafar_serpapi_key', '');
            return $key ? new \NextSafar\Api\Clients\SerpApiClient($key) : null;
        }

        $key = (string) get_option('nextsafar_searchapi_key', '');
        return $key ? new \NextSafar\Api\Clients\SearchApiClient($key) : null;
    }

    /**
     * Get active provider name
     */
    private static function getActiveSource(): string
    {
        return (string) get_option('nextsafar_active_provider', 'searchapi');
    }

    private static function buildQuery($post): string
    {
        $city = GeoSchema::get((int) $post->ID, 'city');
        $country = GeoSchema::get((int) $post->ID, 'country');
        $parts = array_filter([$post->post_title, $city, $country]);
        return implode(', ', $parts);
    }

    /**
     * Calculate similarity between two strings (0 to 1)
     */
    private static function similarity(string $a, string $b): float
    {
        $norm = function (string $s): string {
            $s = mb_strtolower($s);
            $s = str_replace(
                ['ي', 'ك', 'ة', 'ؤ', 'إ', 'أ', 'آ'],
                ['ی', 'ک', 'ه', 'و', 'ا', 'ا', 'ا'],
                $s
            );
            $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
            return trim($s);
        };

        $wa = array_filter(explode(' ', $norm($a)));
        $wb = array_filter(explode(' ', $norm($b)));

        if (!$wa || !$wb) {
            return 0.0;
        }

        $inter = count(array_intersect($wa, $wb));
        return $inter / min(count($wa), count($wb));
    }

    private static function unsyncedMetaQuery(): array
    {
        return [
            'relation' => 'AND',
            ['key' => '_geo_external_id', 'compare' => 'NOT EXISTS'],
            ['key' => '_geo_lat', 'compare' => 'NOT EXISTS'],
        ];
    }

    private static function getUnsyncedIds(int $limit, int $offset): array
    {
        $q = new \WP_Query([
            'post_type'      => self::TYPES,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'fields'         => 'ids',
            'meta_query'     => self::unsyncedMetaQuery(),
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        return $q->posts;
    }

    public static function countUnsynced(): int
    {
        $q = new \WP_Query([
            'post_type'      => self::TYPES,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => self::unsyncedMetaQuery(),
        ]);

        return (int) $q->found_posts;
    }
}