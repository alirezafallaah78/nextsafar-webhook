<?php
/**
 * GeoSync — Synchronize geographic data from API responses to WordPress
 *
 * Handles:
 * - Saving geo data to post meta
 * - Syncing to custom geo table (if exists)
 * - Normalizing data from different providers
 *
 * @package NextSafar\Sync
 * @since 1.0.0
 */

namespace NextSafar\Sync;

if (!defined('ABSPATH')) {
    exit;
}

class GeoSync
{
    /* ========================================================================
       Apply — Save API data to post meta
       ======================================================================== */

    /**
     * Save geographic data from API response to post meta
     *
     * @param int    $post_id Post ID
     * @param array  $data    API response data
     * @param string $source  Source identifier (serpapi, searchapi)
     * @return void
     */
    public static function apply(int $post_id, array $data, string $source = 'searchapi'): void
    {
        // ✅ Validate and normalize source
        if (!in_array($source, ['serpapi', 'searchapi', 'wikipedia', 'manual'], true)) {
            $source = 'manual';
            error_log("⚠️ [GeoSync] Invalid source normalized to 'manual' for #{$post_id}");
        }

        $fields = [
            'lat'          => (float) ($data['lat'] ?? 0),
            'lng'          => (float) ($data['lng'] ?? 0),
            'city'         => sanitize_text_field($data['city'] ?? ''),
            'country'      => sanitize_text_field($data['country'] ?? ''),
            'country_code' => sanitize_text_field($data['country_code'] ?? ''),
            'address'      => sanitize_text_field($data['address'] ?? ''),
            'place_id'     => sanitize_text_field($data['place_id'] ?? ''),
            'external_id'  => sanitize_text_field($data['external_id'] ?? ''),
            'name_en'      => sanitize_text_field($data['name_en'] ?? ($data['name'] ?? '')),
            'source'       => $source,  // ✅ Always save source
        ];

        // Hotel-specific fields
        if (isset($data['stars'])) {
            $fields['stars'] = (int) $data['stars'];
        }
        if (isset($data['rating'])) {
            $fields['rating'] = (float) $data['rating'];
        }
        if (isset($data['reviews_count'])) {
            $fields['reviews'] = (int) $data['reviews_count'];
        }
        if (isset($data['property_token'])) {
            $fields['property_token'] = sanitize_text_field($data['property_token']);
        }
        if (isset($data['check_in_time'])) {
            $fields['checkin'] = sanitize_text_field($data['check_in_time']);
        }
        if (isset($data['check_out_time'])) {
            $fields['checkout'] = sanitize_text_field($data['check_out_time']);
        }

        $post_type = get_post_type($post_id);
        $prefix = $post_type ? "_{$post_type}_" : '_geo_';

        // ✅ Save to post meta (force all fields)
        foreach ($fields as $field => $value) {
            if ($field === 'source' || ($value !== '' && $value !== 0 && $value !== 0.0)) {
                GeoSchema::set($post_id, $field, $value, $prefix);
            }
        }

        // ✅ Also save standard geo keys (for legacy support) - ALWAYS save source
        $standard_fields = ['lat', 'lng', 'city', 'country', 'address', 'place_id', 'external_id', 'name_en', 'source'];
        foreach ($standard_fields as $field) {
            if (isset($fields[$field])) {
                $value = $fields[$field];
                if ($field === 'source' || ($value !== '' && $value !== 0 && $value !== 0.0)) {
                    update_post_meta($post_id, "_geo_{$field}", $value);
                }
            }
        }

        // ✅ Also save property_token from initial search as place_id if enrichment didn't set one
        if (!empty($data['property_token']) && empty(get_post_meta($post_id, '_geo_place_id', true))) {
            update_post_meta($post_id, '_geo_place_id', sanitize_text_field($data['property_token']));
        }

        error_log("✅ [GeoSync] Applied data to #{$post_id} with source: {$source}");

        self::syncToCustomTable($post_id);
    }

    /* ========================================================================
       Sync to Custom Table
       ======================================================================== */

    /**
     * Sync geo data to custom nextsafar_geo table (if exists)
     */
    public static function syncToCustomTable(int $post_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'nextsafar_geo';

        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }

        $post_type = get_post_type($post_id);
        if (!$post_type) {
            return;
        }

        $data = [
            'post_id'     => $post_id,
            'post_type'   => $post_type,
            'lat'         => (float) GeoSchema::get($post_id, 'lat'),
            'lng'         => (float) GeoSchema::get($post_id, 'lng'),
            'place_id'    => GeoSchema::get($post_id, 'place_id'),
            'city'        => GeoSchema::get($post_id, 'city'),
            'country'     => GeoSchema::get($post_id, 'country'),
            'address'     => GeoSchema::get($post_id, 'address'),
            'phone'       => get_post_meta($post_id, "_{$post_type}_phone", true) ?: '',
            'website'     => get_post_meta($post_id, "_{$post_type}_website", true) ?: '',
            'external_id' => GeoSchema::get($post_id, 'external_id'),
            'source'      => GeoSchema::get($post_id, 'source') ?: 'manual',
            'last_sync'   => current_time('mysql'),
        ];

        // Build spatial point if coordinates valid
        if ($data['lat'] !== 0.0 && $data['lng'] !== 0.0) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (post_id, post_type, lat, lng, place_id, city, country, address, phone, website, external_id, source, last_sync, location_point)
                 VALUES (%d, %s, %f, %f, %s, %s, %s, %s, %s, %s, %s, %s, %s, POINT(%f, %f))
                 ON DUPLICATE KEY UPDATE
                    post_type = VALUES(post_type),
                    lat = VALUES(lat),
                    lng = VALUES(lng),
                    place_id = VALUES(place_id),
                    city = VALUES(city),
                    country = VALUES(country),
                    address = VALUES(address),
                    phone = VALUES(phone),
                    website = VALUES(website),
                    external_id = VALUES(external_id),
                    source = VALUES(source),
                    last_sync = VALUES(last_sync),
                    location_point = POINT(%f, %f)",
                $data['post_id'], $data['post_type'], $data['lat'], $data['lng'],
                $data['place_id'], $data['city'], $data['country'], $data['address'],
                $data['phone'], $data['website'], $data['external_id'], $data['source'], $data['last_sync'],
                $data['lng'], $data['lat'], // POINT uses (lng, lat) order
                $data['lng'], $data['lat']
            ));
        } else {
            // No coordinates, skip spatial column
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (post_id, post_type, lat, lng, place_id, city, country, address, phone, website, external_id, source, last_sync)
                 VALUES (%d, %s, %f, %f, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    post_type = VALUES(post_type),
                    lat = VALUES(lat),
                    lng = VALUES(lng),
                    place_id = VALUES(place_id),
                    city = VALUES(city),
                    country = VALUES(country),
                    address = VALUES(address),
                    phone = VALUES(phone),
                    website = VALUES(website),
                    external_id = VALUES(external_id),
                    source = VALUES(source),
                    last_sync = VALUES(last_sync)",
                $data['post_id'], $data['post_type'], $data['lat'], $data['lng'],
                $data['place_id'], $data['city'], $data['country'], $data['address'],
                $data['phone'], $data['website'], $data['external_id'], $data['source'], $data['last_sync']
            ));
        }
    }

    /* ========================================================================
       Find by External ID
       ======================================================================== */

    /**
     * Find post by external ID across all post types
     */
    public static function findByExternalId(string $external_id): ?int
    {
        global $wpdb;

        if (empty($external_id)) {
            return null;
        }

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_geo_external_id' AND meta_value = %s
             LIMIT 1",
            $external_id
        ));

        return $post_id ? (int) $post_id : null;
    }

    /* ========================================================================
       Find Nearby Posts
       ======================================================================== */

    /**
     * Find nearby posts within radius
     *
     * @param float $lat        Center latitude
     * @param float $lng        Center longitude
     * @param float $radius_km  Radius in kilometers
     * @param array $post_types Post types to search
     * @param int   $limit      Max results
     * @return array Nearby posts
     */
    public static function findNearby(
        float $lat,
        float $lng,
        float $radius_km = 5,
        array $post_types = [],
        int $limit = 20
    ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'nextsafar_geo';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return [];
        }

        // Build WHERE clause for post types
        $where_types = '';
        if (!empty($post_types)) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $where_types = $wpdb->prepare("AND post_type IN ($placeholders)", ...$post_types);
        }

        // Bounding box for fast pre-filter
        $lat_delta = $radius_km / 111.0;
        $lng_delta = $radius_km / (111.0 * max(0.1, cos(deg2rad($lat))));

        $sql = $wpdb->prepare(
            "SELECT post_id, post_type, lat, lng, city,
                    (6371 * ACOS(
                        COS(RADIANS(%f)) * COS(RADIANS(lat)) *
                        COS(RADIANS(lng) - RADIANS(%f)) +
                        SIN(RADIANS(%f)) * SIN(RADIANS(lat))
                    )) AS distance_km
             FROM {$table}
             WHERE lat BETWEEN %f AND %f
             AND lng BETWEEN %f AND %f
             {$where_types}
             HAVING distance_km <= %f
             ORDER BY distance_km ASC
             LIMIT %d",
            $lat, $lng, $lat,
            $lat - $lat_delta, $lat + $lat_delta,
            $lng - $lng_delta, $lng + $lng_delta,
            $radius_km,
            $limit
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
}