<?php
/**
 * PlaceEnrichTrait — Enrich place data using the ACTIVE provider's Google Maps
 *
 * Logic:
 * - Uses the same provider that was selected for the initial search
 * - Both SerpApi and SearchApi support Google Maps (engine=google_maps)
 * - This prevents wasting tokens from the non-selected provider
 *
 * @package NextSafar\Sync\Traits
 * @since 1.0.0
 */

namespace NextSafar\Sync\Traits;

use NextSafar\Api\Clients\SerpApiClient;
use NextSafar\Api\Clients\SearchApiClient;

if (!defined('ABSPATH')) {
    exit;
}

trait PlaceEnrichTrait
{
    /**
     * Enrich post with address, working hours, phone, website from Google Maps
     *
     * Uses the same provider as the initial search (from settings)
     *
     * @param int    $post_id  Post ID
     * @param string $name     Place name
     * @param string $city     City name
     * @param string $country  Country name
     * @param string $prefix   Meta key prefix (e.g., '_hotel_')
     * @return void
     */
    protected function enrichAddressFromSearchApi(
        int $post_id,
        string $name,
        string $city = '',
        string $country = '',
        string $prefix = '_geo_'
    ): void {
        if (empty($name)) {
            return;
        }

        // Skip if already enriched
        $already_enriched = get_post_meta($post_id, $prefix . 'enriched', true);
        if ($already_enriched === '1') {
            error_log("⏭️ [Enrich] Skipped #{$post_id}: already enriched");
            return;
        }

        // ✅ Use the SAME provider as the initial search
        $source = (string) get_option('nextsafar_active_provider', 'searchapi');
        
        if ($source === 'serpapi') {
            $api_key = get_option('nextsafar_serpapi_key', '');
            $client = new SerpApiClient($api_key);
            $provider_name = 'SerpApi';
        } else {
            $api_key = get_option('nextsafar_searchapi_key', '');
            $client = new SearchApiClient($api_key);
            $provider_name = 'SearchApi';
        }
        
        if (empty($api_key)) {
            error_log("⚠️ [Enrich] Skipped #{$post_id}: {$provider_name} key not configured");
            return;
        }

        $query = $name;
        if (!empty($city)) {
            $query .= ", {$city}";
        }
        if (!empty($country)) {
            $query .= ", {$country}";
        }

        error_log("🔍 [Enrich] Using {$provider_name} (Google Maps) for #{$post_id}: {$name}");

        $details = $client->getPlaceDetails($query);
        if (empty($details)) {
            error_log("⚠️ [Enrich] No details found for #{$post_id}: {$name}");
            return;
        }

        // Save address
        if (!empty($details['address'])) {
            update_post_meta($post_id, $prefix . 'address', sanitize_text_field($details['address']));
            update_post_meta($post_id, '_geo_address', sanitize_text_field($details['address']));
        }

        // Save phone
        if (!empty($details['phone'])) {
            update_post_meta($post_id, $prefix . 'phone', sanitize_text_field($details['phone']));
        }

        // Save website
        if (!empty($details['website'])) {
            update_post_meta($post_id, $prefix . 'website', esc_url_raw($details['website']));
        }

        // Save working hours
        if (!empty($details['hours']) && is_array($details['hours'])) {
            update_post_meta($post_id, $prefix . 'work_time', $details['hours']);
        }

        // Update coordinates if more precise
        if (!empty($details['lat']) && !empty($details['lng'])) {
            $current_lat = (float) get_post_meta($post_id, '_geo_lat', true);
            if ($current_lat === 0.0) {
                update_post_meta($post_id, '_geo_lat', (float) $details['lat']);
                update_post_meta($post_id, '_geo_lng', (float) $details['lng']);
                if ($prefix !== '_geo_') {
                    update_post_meta($post_id, $prefix . 'lat', (float) $details['lat']);
                    update_post_meta($post_id, $prefix . 'lng', (float) $details['lng']);
                }
            }
        }

        // ✅ Save Google Place ID (try multiple fields)
        $place_id = $details['place_id'] ?? '';

        // Fallback to data_id if place_id is empty
        if (empty($place_id) && !empty($details['data_id'])) {
            $place_id = $details['data_id'];
        }

        if (!empty($place_id)) {
            update_post_meta($post_id, '_geo_place_id', sanitize_text_field($place_id));
            
            // Also save to post-type specific key
            $post_type = get_post_type($post_id);
            if ($post_type) {
                update_post_meta($post_id, "_{$post_type}_place_id", sanitize_text_field($place_id));
            }
            
            error_log("✅ [Enrich] Saved Google Place ID for #{$post_id}: {$place_id}");
        }
    }

    /* ========================================================================
       Price Level Conversion
       ======================================================================== */

    protected function convertPriceLevel(string $price_level): string
    {
        $mapping = [
            '$'         => 'ارزان',
            '$$'        => 'متوسط',
            '$$$'       => 'گران',
            '$$$$'      => 'لوکس',
            'cheap'     => 'ارزان',
            'moderate'  => 'متوسط',
            'expensive' => 'گران',
        ];

        $price_level = strtolower(trim($price_level));
        return $mapping[$price_level] ?? $price_level;
    }

    /* ========================================================================
       Type Mapping Helpers
       ======================================================================== */

    protected function mapType(string $google_type, array $mapping): string
    {
        if (empty($google_type)) {
            return '';
        }

        $type_lower = strtolower($google_type);

        foreach ($mapping as $our_type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($type_lower, $keyword) !== false) {
                    return $our_type;
                }
            }
        }

        return '';
    }
}