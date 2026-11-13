<?php
/**
 * ApiCache — Simple caching layer for API responses
 *
 * Prevents repeated API calls for the same query within a time window.
 * Uses WordPress transients for storage.
 *
 * @package NextSafar\Api\Clients
 * @since 1.0.0
 */

namespace NextSafar\Api\Clients;

if (!defined('ABSPATH')) {
    exit;
}

class ApiCache
{
    /** Default cache duration in seconds */
    public const DEFAULT_TTL = 3600; // 1 hour

    /** Cache key prefix */
    private const PREFIX = 'ns_api_cache_';

    /* ========================================================================
       Get — Retrieve cached response
       ======================================================================== */
    
    /**
     * Get cached response for a query
     *
     * @param string $provider Provider name (serpapi, searchapi)
     * @param string $engine   Engine name (google_hotels, google_maps)
     * @param array  $params   Query parameters
     * @return array|false Cached data or false if not found
     */
    public static function get(string $provider, string $engine, array $params)
    {
        $key = self::buildKey($provider, $engine, $params);
        $cached = get_transient($key);

        if ($cached !== false) {
            return $cached;
        }

        return false;
    }

    /* ========================================================================
       Set — Store response in cache
       ======================================================================== */
    
    /**
     * Cache an API response
     *
     * @param string $provider Provider name
     * @param string $engine   Engine name
     * @param array  $params   Query parameters
     * @param mixed  $data     Response data to cache
     * @param int    $ttl      Time to live in seconds
     * @return bool Success status
     */
    public static function set(string $provider, string $engine, array $params, $data, int $ttl = self::DEFAULT_TTL): bool
    {
        $key = self::buildKey($provider, $engine, $params);
        return set_transient($key, $data, $ttl);
    }

    /* ========================================================================
       Delete — Remove from cache
       ======================================================================== */
    
    /**
     * Delete a specific cached response
     *
     * @param string $provider Provider name
     * @param string $engine   Engine name
     * @param array  $params   Query parameters
     * @return bool Success status
     */
    public static function delete(string $provider, string $engine, array $params): bool
    {
        $key = self::buildKey($provider, $engine, $params);
        return delete_transient($key);
    }

    /* ========================================================================
       Clear All — Remove all API cache
       ======================================================================== */
    
    /**
     * Clear all API cache entries
     *
     * @return int Number of entries cleared
     */
    public static function clearAll(): int
    {
        global $wpdb;

        $count = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . self::PREFIX . '%'
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . self::PREFIX . '%'
        ));

        error_log("🗑️ [ApiCache] Cleared {$count} cache entries");

        return (int) $count;
    }

    /* ========================================================================
       Build Key — Generate unique cache key
       ======================================================================== */
    
    /**
     * Build a unique cache key from provider, engine, and params
     */
    private static function buildKey(string $provider, string $engine, array $params): string
    {
        // Remove api_key from params before hashing
        $cleanParams = $params;
        unset($cleanParams['api_key'], $cleanParams['apikey']);

        // Sort params for consistent hashing
        ksort($cleanParams);

        $hash = md5(json_encode([
            'provider' => $provider,
            'engine'   => $engine,
            'params'   => $cleanParams,
        ]));

        return self::PREFIX . $hash;
    }

    /* ========================================================================
       Stats — Get cache statistics
       ======================================================================== */
    
    /**
     * Get cache statistics
     *
     * @return array{total: int, total_size_kb: float}
     */
    public static function getStats(): array
    {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(LENGTH(option_value)) / 1024 as total_size_kb
             FROM {$wpdb->options}
             WHERE option_name LIKE %s",
            '_transient_' . self::PREFIX . '%'
        ), ARRAY_A);

        return [
            'total'         => (int) ($stats['total'] ?? 0),
            'total_size_kb' => round((float) ($stats['total_size_kb'] ?? 0), 2),
        ];
    }
}