<?php
/**
 * GeoSchema — Manages geographic metadata for posts
 *
 * @package NextSafar\Sync
 * @since 1.0.0
 */

namespace NextSafar\Sync;

if (!defined('ABSPATH')) {
    exit;
}

class GeoSchema
{
    /**
     * Post types that support geo data
     * ✅ Added for geo-metabox.php compatibility
     */
    public const GEO_POST_TYPES = [
        'hotel',
        'airport',
        'destination',
        'restaurant',
        'hospital',
        'tour',
        'visa',
        'travelguide',
        'travelnews',
    ];

    /**
     * Available geo fields with their WordPress meta keys
     */
    public const FIELDS = [
        'lat'            => ['_geo_lat', '_geo_lat'],
        'lng'            => ['_geo_lng', '_geo_lng'],
        'city'           => ['_geo_city', '_geo_city'],
        'country'        => ['_geo_country', '_geo_country'],
        'country_code'   => ['_geo_country_code', '_geo_country_code'],
        'address'        => ['_geo_address', '_geo_address'],
        'place_id'       => ['_geo_place_id', '_geo_place_id'],
        'external_id'    => ['_geo_external_id', '_geo_external_id'],
        'property_token' => ['_geo_property_token', '_geo_property_token'],
        'name_en'        => ['_geo_name_en', '_geo_name_en'],
        'source'         => ['_geo_source', '_geo_source'],
        'stars'          => ['_geo_stars', '_geo_stars'],
        'rating'         => ['_geo_rating', '_geo_rating'],
        'reviews'        => ['_geo_reviews', '_geo_reviews'],
        'checkin'        => ['_geo_checkin', '_geo_checkin'],
        'checkout'       => ['_geo_checkout', '_geo_checkout'],
        'wikipedia'      => ['_geo_wikipedia', '_geo_wikipedia'],
    ];

    /* ========================================================================
       Get Value
       ======================================================================== */

    public static function get(int $post_id, string $field, string $prefix = '_geo_'): mixed
    {
        $prefixed_key = $prefix . $field;
        $value = get_post_meta($post_id, $prefixed_key, true);

        if ($value !== '' && $value !== false) {
            return $value;
        }

        if (isset(self::FIELDS[$field])) {
            foreach (self::FIELDS[$field] as $key) {
                $value = get_post_meta($post_id, $key, true);
                if ($value !== '' && $value !== false) {
                    return $value;
                }
            }
        }

        return '';
    }

    /* ========================================================================
       Set Value
       ======================================================================== */

    public static function set(int $post_id, string $field, mixed $value, string $prefix = '_geo_'): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $prefixed_key = $prefix . $field;
        $result = update_post_meta($post_id, $prefixed_key, $value);

        if (isset(self::FIELDS[$field])) {
            $standard_key = self::FIELDS[$field][0];
            if ($standard_key !== $prefixed_key) {
                update_post_meta($post_id, $standard_key, $value);
            }
        }

        return (bool) $result;
    }

    /* ========================================================================
       Get All Geo Data
       ======================================================================== */

    public static function getAll(int $post_id, string $prefix = '_geo_'): array
    {
        $data = [];

        foreach (array_keys(self::FIELDS) as $field) {
            $value = self::get($post_id, $field, $prefix);
            if ($value !== '' && $value !== false) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /* ========================================================================
       Coordinates Helper
       ======================================================================== */

    public static function getCoordinates(int $post_id): array
    {
        return [
            'lat' => (float) self::get($post_id, 'lat'),
            'lng' => (float) self::get($post_id, 'lng'),
        ];
    }

    public static function hasCoordinates(int $post_id): bool
    {
        $lat = (float) self::get($post_id, 'lat');
        $lng = (float) self::get($post_id, 'lng');

        return $lat !== 0.0 && $lng !== 0.0;
    }

    /* ========================================================================
       Distance Calculation (Haversine)
       ======================================================================== */

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        if ($lat1 === 0.0 || $lng1 === 0.0 || $lat2 === 0.0 || $lng2 === 0.0) {
            return null;
        }

        $earth_radius = 6371000.0;

        $d_lat = deg2rad($lat2 - $lat1);
        $d_lng = deg2rad($lng2 - $lng1);

        $a = sin($d_lat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($d_lng / 2) ** 2;

        return 2 * $earth_radius * asin(sqrt($a));
    }

    /* ========================================================================
       Post Type Helper
       ======================================================================== */

    /**
     * Check if post type supports geo data
     */
    public static function supportsGeo(string $post_type): bool
    {
        return in_array($post_type, self::GEO_POST_TYPES, true);
    }

        /* ========================================================================
       Lat/Lng Helper Methods (Required by geo-metabox.php)
       ======================================================================== */

    /**
     * Get latitude and longitude as array [$lat, $lng]
     * 
     * @param int $post_id Post ID
     * @return array [$lat, $lng]
     */
    public static function get_latlng(int $post_id): array
    {
        $lat = (float) self::get($post_id, 'lat');
        $lng = (float) self::get($post_id, 'lng');

        return [$lat, $lng];
    }

    /**
     * Set latitude and longitude
     * 
     * @param int        $post_id Post ID
     * @param float|string $lat    Latitude
     * @param float|string $lng    Longitude
     * @return bool Success status
     */
    public static function set_latlng(int $post_id, $lat, $lng): bool
    {
        $lat = (float) $lat;
        $lng = (float) $lng;

        $result1 = self::set($post_id, 'lat', $lat);
        $result2 = self::set($post_id, 'lng', $lng);

        return $result1 && $result2;
    }
}