<?php
/**
 * RestaurantSync — Synchronize restaurants from Google Maps
 *
 * Features:
 * - Restaurant type mapping (traditional, fast food, etc.)
 * - Facilities/amenities mapping (60+ keywords)
 * - Menu link extraction
 * - Price level conversion
 * - Working hours
 *
 * @package NextSafar\Api\Sync
 * @since 1.0.0
 */

namespace NextSafar\Api\Sync;

use NextSafar\Sync\GeoSync;
use NextSafar\Sync\Traits\PlaceEnrichTrait;

if (!defined('ABSPATH')) {
    exit;
}

class RestaurantSync extends BaseSync
{
    use PlaceEnrichTrait;

    protected string $post_type = 'restaurant';

    protected function getSearchMethod(): string
    {
        return 'searchRestaurants';
    }

    public function sync(string $location, array $options = []): array|\WP_Error
    {
        return $this->runSync($location, $options);
    }

    public function save(array $data): string
    {
        $result = $this->savePost($data);

        if ($result['action'] === 'failed') {
            return 'failed';
        }

        $post_id = $result['post_id'];

        $this->saveGeoMeta($post_id, $data);
        $this->saveRestaurantFields($post_id, $data);
        $this->saveFeaturedImage($post_id, $data);

        return $result['action'];
    }

    /* ========================================================================
       Save Restaurant-Specific Fields
       ======================================================================== */

    protected function saveRestaurantFields(int $post_id, array $data): void
    {
        // Type mapping
        $type_mapping = [
            'traditional'   => ['traditional', 'local cuisine', 'authentic', 'persian'],
            'fast_food'     => ['fast food', 'burger', 'pizza', 'sandwich'],
            'cafe'          => ['cafe', 'coffee', 'tea house'],
            'seafood'       => ['seafood', 'fish', 'shrimp'],
            'kebab'         => ['kebab', 'kabab', 'grill'],
            'international' => ['international', 'french', 'italian', 'chinese'],
            'vegetarian'    => ['vegetarian', 'vegan', 'plant-based'],
        ];

        $mapped_type = $this->mapType($data['type'] ?? '', $type_mapping);
        if ($mapped_type) {
            update_post_meta($post_id, '_restaurant_type', sanitize_text_field($mapped_type));
        }

        // ✅ NEW: Cuisine type (separate field)
        if (!empty($data['type'])) {
            update_post_meta($post_id, '_restaurant_cuisine_type', sanitize_text_field($data['type']));
        }

        // Price level
        if (!empty($data['price_level'])) {
            $price_text = $this->convertPriceLevel($data['price_level']);
            if ($price_text) {
                update_post_meta($post_id, '_restaurant_average_price', sanitize_text_field($price_text));
                update_post_meta($post_id, '_restaurant_entry_fee', sanitize_text_field($price_text));
            }
        }

        // Rating
        if (!empty($data['rating'])) {
            update_post_meta($post_id, '_restaurant_rating', (float) $data['rating']);
        }
        if (!empty($data['reviews_count'])) {
            update_post_meta($post_id, '_restaurant_reviews', (int) $data['reviews_count']);
        }

        // Contact info
        if (!empty($data['website'])) {
            update_post_meta($post_id, '_restaurant_website', esc_url_raw($data['website']));
        }
        if (!empty($data['phone'])) {
            update_post_meta($post_id, '_restaurant_phone', sanitize_text_field($data['phone']));
        }

        // ✅ NEW: Save facilities as checkbox meta
        if (!empty($data['extensions']) && is_array($data['extensions'])) {
            $this->saveRestaurantFacilities($post_id, $data['extensions']);
        }

        // Enrich address (will fill working hours, phone, website if missing)
        $this->enrichAddressFromSearchApi(
            $post_id,
            $data['name'] ?? '',
            $data['city'] ?? '',
            $data['country'] ?? '',
            '_restaurant_'
        );

        GeoSync::syncToCustomTable($post_id);
    }

    /**
     * Map and save restaurant facilities from extensions to checkboxes
     */
    protected function saveRestaurantFacilities(int $post_id, array $extensions): void
    {
        // Mapping: Google Maps keyword → WordPress meta key
        $facility_mapping = [
            // Services
            'takeout'           => 'restaurant_takeout',
            'delivery'          => 'restaurant_delivery',
            'dine-in'           => 'restaurant_dine_in',
            'curbside pickup'   => 'restaurant_curbside_pickup',
            'drive-through'     => 'restaurant_drive_through',
            
            // Seating
            'outdoor seating'   => 'restaurant_outdoor_seating',
            'rooftop'           => 'restaurant_rooftop_seating',
            'private dining'    => 'restaurant_private_dining',
            'bar onsite'        => 'restaurant_bar_onsite',
            'table service'     => 'restaurant_table_service',
            'counter service'   => 'restaurant_counter_service',
            
            // Payment
            'reservations'      => 'restaurant_reservations',
            'accepts credit cards' => 'restaurant_accepts_credit_cards',
            'accepts debit cards'  => 'restaurant_accepts_debit_cards',
            'nfc mobile payments'  => 'restaurant_nfc_payments',
            
            // Amenities
            'wi-fi'             => 'restaurant_wifi',
            'free wifi'         => 'restaurant_wifi',
            'restroom'          => 'restaurant_restroom',
            'air conditioning'  => 'restaurant_air_conditioning',
            'heating'           => 'restaurant_heating',
            'pets allowed'      => 'restaurant_pet_friendly',
            'high chairs'       => 'restaurant_high_chairs',
            
            // Accessibility
            'wheelchair accessible' => 'restaurant_wheelchair_accessible',
            'wheelchair entrance'   => 'restaurant_wheelchair_entrance',
            'wheelchair parking'    => 'restaurant_wheelchair_parking',
            'wheelchair seating'    => 'restaurant_wheelchair_seating',
            
            // Parking
            'parking'           => 'restaurant_parking',
            'free parking'      => 'restaurant_free_parking',
            'paid parking'      => 'restaurant_paid_parking',
            'street parking'    => 'restaurant_street_parking',
            'valet parking'     => 'restaurant_valet_parking',
            
            // Food & Drink
            'serves alcohol'    => 'restaurant_serves_alcohol',
            'serves beer'       => 'restaurant_serves_beer',
            'serves wine'       => 'restaurant_serves_wine',
            'serves cocktails'  => 'restaurant_serves_cocktails',
            'serves coffee'     => 'restaurant_serves_coffee',
            'serves breakfast'  => 'restaurant_serves_breakfast',
            'serves lunch'      => 'restaurant_serves_lunch',
            'serves dinner'     => 'restaurant_serves_dinner',
            'serves dessert'    => 'restaurant_serves_dessert',
            'serves brunch'     => 'restaurant_serves_brunch',
            'late-night food'   => 'restaurant_late_night_food',
            'halal'             => 'restaurant_halal',
            'vegan options'     => 'restaurant_vegan',
            'vegetarian options'=> 'restaurant_vegetarian',
            'organic'           => 'restaurant_organic',
            'kids menu'         => 'restaurant_kids_menu',
            
            // Atmosphere
            'casual'            => 'restaurant_casual',
            'cozy'              => 'restaurant_cozy',
            'upscale'           => 'restaurant_upscale',
            'trendy'            => 'restaurant_trendy',
            'romantic'          => 'restaurant_romantic',
            'historic'          => 'restaurant_historic',
            'quiet'             => 'restaurant_quiet',
            'family-friendly'   => 'restaurant_family_friendly',
            'good for groups'   => 'restaurant_good_for_groups',
            'good for solo'     => 'restaurant_good_for_solo',
            'popular with tourists' => 'restaurant_popular_with_tourists',
        ];

        // Combine all extensions into one string for matching
        $extensions_lower = array_map('strtolower', array_filter(array_map(function ($e) {
            return is_string($e) ? $e : '';
        }, $extensions)));
        
        $extensions_string = implode(' ', $extensions_lower);
        
        $matched = [];
        foreach ($facility_mapping as $keyword => $meta_key) {
            if (strpos($extensions_string, $keyword) !== false) {
                update_post_meta($post_id, '_' . $meta_key, 'yes');
                $matched[] = $keyword;
            }
        }

        if (!empty($matched)) {
            error_log("✅ [RestaurantSync] Matched " . count($matched) . " facilities for #{$post_id}");
        }
    }

    /* ========================================================================
       Save Facilities
       ======================================================================== */

    /**
     * Map and save restaurant facilities from extensions
     */
    protected function saveFacilities(int $post_id, array $extensions): void
    {
        $facility_mapping = [
            'restaurant_wifi'        => ['wifi', 'free wifi', 'wireless', 'internet'],
            'restaurant_parking'     => ['parking', 'free parking', 'valet', 'garage'],
            'restaurant_reservation' => ['reservations', 'reservation', 'booking'],
            'restaurant_delivery'    => ['delivery', 'takeout', 'takeaway'],
            'restaurant_outdoor'     => ['outdoor seating', 'outdoor', 'patio', 'terrace'],
            'restaurant_bar'         => ['bar', 'alcohol', 'drinks', 'cocktails'],
            'restaurant_live_music'  => ['live music', 'music', 'entertainment'],
            'restaurant_wheelchair'  => ['wheelchair', 'accessible', 'disability'],
        ];

        $extensions_lower = array_map('strtolower', array_filter(array_map(function ($e) {
            return is_string($e) ? $e : '';
        }, $extensions)));

        $extensions_string = implode(' ', $extensions_lower);

        foreach ($facility_mapping as $meta_key => $keywords) {
            $has_facility = false;
            foreach ($keywords as $keyword) {
                if (strpos($extensions_string, $keyword) !== false) {
                    $has_facility = true;
                    break;
                }
            }
            update_post_meta($post_id, '_' . $meta_key, $has_facility ? 'yes' : 'no');
        }
    }
}