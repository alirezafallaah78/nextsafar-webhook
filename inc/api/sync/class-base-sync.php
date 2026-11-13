<?php
/**
 * BaseSync — Abstract base class for all Sync classes
 *
 * Provides unified interface for:
 * - API client initialization
 * - Common save_post logic
 * - External ID duplicate detection
 * - Featured image management
 * - Debug info collection
 *
 * @package NextSafar\Api\Sync
 * @since 1.0.0
 */

namespace NextSafar\Api\Sync;

use NextSafar\Api\Clients\BaseClient;
use NextSafar\Api\Clients\SearchApiClient;
use NextSafar\Api\Clients\SerpApiClient;
use NextSafar\Api\Services\ImageManager;
use NextSafar\Sync\GeoSync;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseSync
{
    /** API client instance */
    protected BaseClient $client;

    /** Post type for this sync */
    protected string $post_type;

    /** Source identifier */
    protected string $source;

    /** Debug information collected during sync */
    protected array $debug_info = [];

    /* ========================================================================
       Constructor
       ======================================================================== */

    /**
     * @param string|null $source Provider name (serpapi, searchapi) or null for active source
     */
    public function __construct(?string $source = null)
    {
        // ✅ Force source to be serpapi or searchapi, never empty
        if ($source !== null && in_array($source, ['serpapi', 'searchapi'], true)) {
            $this->source = $source;
        } else {
            $this->source = (string) get_option('nextsafar_active_provider', 'searchapi');
            
            // ✅ Final fallback to searchapi if option is empty or invalid
            if (!in_array($this->source, ['serpapi', 'searchapi'], true)) {
                $this->source = 'searchapi';
            }
        }
        
        $this->debug_info['source'] = $this->source;

        $key = $this->getApiKey();
        $this->client = $this->createClient($key);
        $this->debug_info['has_api_key'] = !empty($key);
        
        error_log("🔧 [BaseSync] Initialized with source: {$this->source}, has_key: " . ($this->debug_info['has_api_key'] ? 'yes' : 'no'));
    }

    /* ========================================================================
       Abstract Methods (Must be implemented by child classes)
       ======================================================================== */

    /**
     * Run sync for a location
     */
    abstract public function sync(string $location, array $options = []): array|WP_Error;

    /**
     * Save a single item to WordPress
     * @return string 'created'|'updated'|'failed'
     */
    abstract public function save(array $data): string;

    /* ========================================================================
       Common Sync Logic
       ======================================================================== */

    /**
     * Common sync implementation for child classes
     *
     * @param string $location Location to search
     * @param array  $options  Search options (limit, check_in, etc.)
     * @return array|WP_Error Results or error
     */
    protected function runSync(string $location, array $options): array|WP_Error
    {
        error_log("🚀 [Sync] Starting {$this->post_type} sync for: {$location}");

        $search_method = $this->getSearchMethod();

        if (!method_exists($this->client, $search_method)) {
            return new WP_Error('invalid_method', "Method {$search_method} does not exist");
        }

        $items = $this->client->{$search_method}($location, $options);

        if (is_wp_error($items)) {
            return $items;
        }

        $this->debug_info['api_response_count'] = count($items);

        $results = [
            'total'   => count($items),
            'created' => 0,
            'updated' => 0,
            'failed'  => 0,
            'debug'   => $this->debug_info,
            'errors'  => [],
        ];

        foreach ($items as $item_data) {
            try {
                $result = $this->save($item_data);

                if ($result === 'created') {
                    $results['created']++;
                } elseif ($result === 'updated') {
                    $results['updated']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = ($item_data['name'] ?? 'Unknown') . ': ' . $e->getMessage();
                error_log("❌ [Sync] Failed: {$e->getMessage()}");
            }
        }

        error_log("✅ [Sync] Completed: " . json_encode([
            'total'   => $results['total'],
            'created' => $results['created'],
            'updated' => $results['updated'],
            'failed'  => $results['failed'],
        ]));

        return $results;
    }

    /* ========================================================================
       Common Save Helpers
       ======================================================================== */

    /**
     * Save or update WordPress post
     *
     * @param array $data Item data from API
     * @return array ['post_id' => int|null, 'action' => string]
     */
    protected function savePost(array $data): array
    {
        $existing = GeoSync::findByExternalId($data['external_id'] ?? '');

        if ($existing) {
            return ['post_id' => $existing, 'action' => 'updated'];
        }

        $post_id = wp_insert_post([
            'post_type'    => $this->post_type,
            'post_title'   => sanitize_text_field($data['name'] ?? ''),
            'post_status'  => 'publish',
            'post_content' => wp_kses_post($data['description'] ?? ''),
        ]);

        if (is_wp_error($post_id)) {
            error_log("❌ [Sync] wp_insert_post failed: " . $post_id->get_error_message());
            return ['post_id' => null, 'action' => 'failed'];
        }

        return ['post_id' => $post_id, 'action' => 'created'];
    }

    /**
     * Save featured image
     */
    protected function saveFeaturedImage(int $post_id, array $data): void
    {
        if (!empty($data['images'][0]) && !has_post_thumbnail($post_id)) {
            ImageManager::setFeaturedImage($post_id, $data['images'][0], 'api');
        }
    }

    /**
     * Save geo data
     */
    protected function saveGeoMeta(int $post_id, array $data): void
    {
        GeoSync::apply($post_id, $data, $this->source);
    }

    /* ========================================================================
       Protected Helpers
       ======================================================================== */

    /**
     * Get search method name in API client (e.g., 'searchHotels')
     */
    abstract protected function getSearchMethod(): string;

    /**
     * Get API key based on source
     */
    protected function getApiKey(): string
    {
        if ($this->source === 'serpapi') {
            return (string) get_option('nextsafar_serpapi_key', '');
        }
        return (string) get_option('nextsafar_searchapi_key', '');
    }

    /**
     * Create API client based on source
     */
    protected function createClient(string $api_key): BaseClient
    {
        if ($this->source === 'serpapi') {
            return new SerpApiClient($api_key);
        }
        return new SearchApiClient($api_key);
    }

    /**
     * Get debug info collected during sync
     */
    public function getDebugInfo(): array
    {
        return $this->debug_info;
    }
}