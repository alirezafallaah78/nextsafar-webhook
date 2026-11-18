<?php
/**
 * BaseClient — Abstract base class for all API clients
 *
 * Features:
 * - API key management
 * - HTTP request with caching layer
 * - Error handling and logging
 * - Shared helper methods for SerpApi/SearchApi
 *
 * @package NextSafar\Api\Clients
 * @since 1.0.0
 */

namespace NextSafar\Api\Clients;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseClient
{
    protected string $api_key;
    protected int $timeout = 30;
    protected string $base_url = '';
    protected ?array $last_response = null;
    protected ?string $last_error = null;

    public function __construct(string $api_key = '')
    {
        $this->api_key = trim($api_key);
    }

    /* ========================================================================
       API Key Management
       ======================================================================== */

    public function getApiKey(): string
    {
        return $this->api_key;
    }

    public function setApiKey(string $api_key): void
    {
        $this->api_key = trim($api_key);
    }

    public function hasApiKey(): bool
    {
        return !empty($this->api_key);
    }

    public function getLastResponse(): ?array
    {
        return $this->last_response;
    }

    public function getLastError(): ?string
    {
        return $this->last_error;
    }

    /* ========================================================================
       HTTP Request (with Cache Integration)
       ======================================================================== */

    protected function request(string $url, array $params = []): array|WP_Error
    {
        if (!$this->hasApiKey()) {
            $this->last_error = 'کلید API تنظیم نشده است';
            return new WP_Error('no_api_key', $this->last_error);
        }

        $provider = $this->getProviderName();
        $engine = $params['engine'] ?? 'unknown';
        $log_query = $params['q'] ?? $engine;

        // 🆕 ============================================================
        // 📦 STEP 1: Check Cache First
        // ============================================================
        $cached = ApiCache::get($provider, $engine, $params);
        if ($cached !== false) {
            error_log("💾 [{$provider}] Cache HIT: {$log_query}");
            $this->last_response = $cached;
            $this->last_error = null;
            return $cached;
        }

        error_log("🔍 [{$provider}] Cache MISS - Request: {$log_query}");

        // 🆕 ============================================================
        // 🌐 STEP 2: Make Actual API Request
        // ============================================================
        $params['api_key'] = $this->api_key;
        $full_url = add_query_arg($params, $url);

        error_log("🔍 [{$provider}] API Key length: " . strlen($this->api_key));

        $response = wp_remote_get($full_url, [
            'timeout' => $this->timeout,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'NextSafar/1.0 WordPress',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            error_log("❌ [{$provider}] HTTP Error: {$this->last_error}");
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log("🔍 [{$provider}] Status: {$status_code}");

        $this->last_response = json_decode($body, true);

        // ❌ HTTP Error - Don't cache
        if ($status_code !== 200) {
            $error_message = "HTTP Error: {$status_code}";
            
            if ($this->last_response && isset($this->last_response['error'])) {
                $error_message .= " - " . $this->last_response['error'];
            } elseif ($this->last_response && isset($this->last_response['message'])) {
                $error_message .= " - " . $this->last_response['message'];
            }
            
            $this->last_error = $error_message;
            error_log("❌ [{$provider}] {$this->last_error}");
            
            return new WP_Error('http_error', $this->last_error, [
                'code' => $status_code,
                'body' => $body
            ]);
        }

        // ❌ Invalid JSON - Don't cache
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = 'Invalid JSON response';
            return new WP_Error('json_error', $this->last_error);
        }

        // ❌ API Error - Don't cache
        if (isset($this->last_response['error'])) {
            $this->last_error = $this->last_response['error'];
            error_log("❌ [{$provider}] API Error: {$this->last_error}");
            return new WP_Error('api_error', $this->last_error);
        }

        // 🆕 ============================================================
        // 💾 STEP 3: Cache Successful Response
        // ============================================================
        $ttl = $this->determineCacheTTL($params);
        
        // Add metadata to cached data for debugging
        $cache_data = $this->last_response;
        $cache_data['_cached_at'] = current_time('mysql');
        $cache_data['_cache_query'] = $log_query;
        
        ApiCache::set($provider, $engine, $params, $cache_data, $ttl);

        return $this->last_response;
    }

    /* ========================================================================
       Cache TTL Determination
       ======================================================================== */

    /**
     * Determine cache TTL based on request type
     * 
     * Different data types have different freshness requirements:
     * - Hotels (prices): 6 hours (prices change frequently)
     * - Places (basic info): 3 days (stable data)
     * - Reviews: 1 day
     * 
     * @param array $params Request parameters
     * @return int TTL in seconds
     */
    private function determineCacheTTL(array $params): int
    {
        $engine = $params['engine'] ?? '';
        $q = strtolower($params['q'] ?? '');

        // Hotel searches with check-in/out dates = price sensitive (short TTL)
        if ($engine === 'google_hotels' && (
            isset($params['check_in_date']) || 
            isset($params['check_out_date']) ||
            strpos($q, 'hotel') !== false
        )) {
            return 6 * HOUR_IN_SECONDS; // 6 hours
        }

        // Hotel reviews - change daily
        if ($engine === 'google_hotels_reviews') {
            return DAY_IN_SECONDS; // 24 hours
        }

        // Hotel property details - very stable
        if ($engine === 'google_hotels_property_details') {
            return 7 * DAY_IN_SECONDS; // 7 days
        }

        // Google Maps places - relatively stable
        if ($engine === 'google_maps') {
            return 3 * DAY_IN_SECONDS; // 3 days
        }

        // Default: 24 hours
        return DAY_IN_SECONDS;
    }

    /* ========================================================================
       Helper Methods (Shared between SerpApi and SearchApi)
       ======================================================================== */

    /**
     * Generate guaranteed unique external ID
     */
    protected function generateUniqueId(array $prop, array $seen_ids): string
    {
        $candidates = [
            $prop['property_token'] ?? '',
            $prop['data_id'] ?? '',
            $prop['place_id'] ?? '',
        ];

        foreach ($candidates as $id) {
            if (!empty($id) && !isset($seen_ids[$id])) {
                return $id;
            }
        }

        return 'item_' . uniqid('', true) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Extract images from API response
     */
    protected function extractImages(array $images): array
    {
        $result = [];
        foreach ($images as $img) {
            if (is_string($img)) {
                $result[] = $img;
            } elseif (is_array($img)) {
                if (!empty($img['original'])) {
                    $result[] = $img['original'];
                } elseif (!empty($img['original_image'])) {
                    $result[] = $img['original_image'];
                } elseif (!empty($img['link'])) {
                    $result[] = $img['link'];
                } elseif (!empty($img['thumbnail'])) {
                    $result[] = $img['thumbnail'];
                }
            }
        }
        return array_values(array_filter($result));
    }

    /**
     * Extract main city name (remove district)
     */
    protected function extractMainCity(string $city_string): string
    {
        $city_string = trim($city_string);
        if (empty($city_string)) {
            return '';
        }

        if (strpos($city_string, '/') !== false) {
            return trim(explode('/', $city_string)[0]);
        }

        if (strpos($city_string, '-') !== false) {
            return trim(explode('-', $city_string)[0]);
        }

        return $city_string;
    }

    /**
     * Normalize time to HH:MM format
     */
    protected function normalizeTime(string $time_str): string
    {
        if (empty($time_str)) {
            return '';
        }

        // Remove special characters like "2:00 PM" -> "2:00 PM"
        $time_str = preg_replace('/[^\d:APMapm\s]/u', '', $time_str);
        
        $timestamp = strtotime($time_str);
        if ($timestamp !== false) {
            return date('H:i', $timestamp);
        }

        return $time_str;
    }

    /**
     * Convert country code to name
     */
    protected function countryCodeToName(string $code): string
    {
        $code = strtoupper(trim($code));
        if (empty($code)) {
            return '';
        }

        $countries = [
            'TR' => 'Turkey', 'IR' => 'Iran', 'AE' => 'United Arab Emirates',
            'US' => 'United States', 'GB' => 'United Kingdom', 'FR' => 'France',
            'DE' => 'Germany', 'IT' => 'Italy', 'ES' => 'Spain', 'GR' => 'Greece',
            'TH' => 'Thailand', 'MY' => 'Malaysia', 'ID' => 'Indonesia',
            'IN' => 'India', 'CN' => 'China', 'JP' => 'Japan', 'KR' => 'South Korea',
            'EG' => 'Egypt', 'MA' => 'Morocco', 'TN' => 'Tunisia', 'JO' => 'Jordan',
            'LB' => 'Lebanon', 'IQ' => 'Iraq', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar',
            'KW' => 'Kuwait', 'BH' => 'Bahrain', 'OM' => 'Oman', 'SY' => 'Syria',
            'AM' => 'Armenia', 'AZ' => 'Azerbaijan', 'GE' => 'Georgia',
            'RU' => 'Russia', 'UA' => 'Ukraine', 'NL' => 'Netherlands',
            'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria',
        ];

        return $countries[$code] ?? $code;
    }

    /* ========================================================================
       Abstract Methods
       ======================================================================== */

    /**
     * Get place details from Google Maps
     * Must be implemented by child classes (SerpApiClient, SearchApiClient)
     *
     * @param string $query Search query (e.g., "Hotel Name, City, Country")
     * @return array|null Place details or null on failure
     */
    abstract public function getPlaceDetails(string $query): ?array;

    abstract protected function getProviderName(): string;
    abstract public function searchHotels(string $location, array $options = []): array|WP_Error;
    abstract public function searchRestaurants(string $location, array $options = []): array|WP_Error;
    abstract public function searchHospitals(string $location, array $options = []): array|WP_Error;
    abstract public function searchDestinations(string $location, array $options = []): array|WP_Error;
    abstract public function searchAirports(string $location, array $options = []): array|WP_Error;
}