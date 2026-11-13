<?php
/**
 * HotelSync — Synchronize hotels from SerpApi/SearchApi
 *
 * Features:
 * - Hotel post creation with full metadata
 * - Featured image + gallery download
 * - Amenities mapping to metaboxes
 * - Price and rating details
 * - Place enrichment (address, working hours)
 *
 * @package NextSafar\Api\Sync
 * @since 1.0.0
 */

namespace NextSafar\Api\Sync;

use NextSafar\Api\Services\ImageManager;
use NextSafar\Sync\GeoSchema;
use NextSafar\Sync\GeoSync;
use NextSafar\Sync\Traits\PlaceEnrichTrait;

if (!defined('ABSPATH')) {
    exit;
}

class HotelSync extends BaseSync
{
    use PlaceEnrichTrait;

    protected string $post_type = 'hotel';

    protected function getSearchMethod(): string
    {
        return 'searchHotels';
    }

    /* ========================================================================
       Public Sync Method
       ======================================================================== */

    public function sync(string $location, array $options = []): array|\WP_Error
    {
        return $this->runSync($location, $options);
    }

    
    /**
     * Save nearby places from API to hotel_near_locations meta
     * 
     * Maps API nearby_places to metabox format:
     * {type, category, name, coords, distance_km, distance_unit, walking_hr}
     */
    protected function saveNearbyLocations(int $post_id, array $nearby_places): void
    {
        if (empty($nearby_places) || !is_array($nearby_places)) {
            return;
        }

        $locations = [];
        
        // Category mapping based on place type
        $category_mapping = [
            'airport'      => 'airport',
            'museum'       => 'destination',
            'park'         => 'destination',
            'tourist_attraction' => 'destination',
            'shopping_mall' => 'shopping',
            'restaurant'   => 'restaurant',
            'cafe'         => 'restaurant',
            'hospital'     => 'hospital',
            'pharmacy'     => 'hospital',
        ];

        foreach (array_slice($nearby_places, 0, 15) as $place) {
            if (empty($place['name'])) {
                continue;
            }

            // Determine category from place type
            $category = 'destination'; // default
            $types = $place['types'] ?? [];
            foreach ($types as $type) {
                if (isset($category_mapping[$type])) {
                    $category = $category_mapping[$type];
                    break;
                }
            }

            // Extract transportation info
            $transportations = $place['transportations'] ?? [];
            $distance_km = '';
            $walking_hr = '';
            $distance_unit = 'km';

            foreach ($transportations as $transport) {
                $type = strtolower($transport['type'] ?? '');
                $duration = $transport['duration'] ?? '';

                if (strpos($type, 'walking') !== false) {
                    $walking_hr = $this->extractMinutes($duration);
                } elseif (strpos($type, 'taxi') !== false || strpos($type, 'driving') !== false) {
                    $distance_km = $this->extractMinutes($duration);
                }
            }

            $locations[] = [
                'type'          => 'manual',
                'category'      => $category,
                'post_id'       => 0,
                'name'          => sanitize_text_field($place['name']),
                'coords'        => '', // Can be added later from geocoding
                'distance_km'   => $distance_km,
                'distance_unit' => $distance_unit,
                'distance_hr'   => '',
                'walking_hr'    => $walking_hr,
            ];
        }

        if (!empty($locations)) {
            update_post_meta($post_id, '_hotel_near_locations', $locations);
            error_log("✅ [HotelSync] Saved " . count($locations) . " nearby locations for #{$post_id}");
        }
    }

// تابع جدید:
protected function enrichWithPropertyDetails(int $post_id, string $property_token): void
{
    // Prevent repeated attempts
    if (get_post_meta($post_id, '_hotel_property_details_attempted', true)) {
        return;
    }

    update_post_meta($post_id, '_hotel_property_details_attempted', '1');

    try {
        $api_client = new \NextSafar\Api\Clients\SerpApiClient();
        $details = $api_client->getHotelPropertyDetails($property_token);

        if (empty($details)) {
            return;
        }

        // Check-in instructions
        if (!empty($details['checkin_instructions'])) {
            $existing = get_post_meta($post_id, '_hotel_dis_checkin', true);
            if (empty($existing)) {
                update_post_meta($post_id, '_hotel_dis_checkin', wp_kses_post($details['checkin_instructions']));
            }
        }

        // Check-out instructions
        if (!empty($details['checkout_instructions'])) {
            $existing = get_post_meta($post_id, '_hotel_dis_checkout', true);
            if (empty($existing)) {
                update_post_meta($post_id, '_hotel_dis_checkout', wp_kses_post($details['checkout_instructions']));
            }
        }

        // Optional fees
        if (!empty($details['optional_fees'])) {
            $existing = get_post_meta($post_id, '_hotel_dis_expenses', true);
            if (empty($existing)) {
                $fees_text = '';
                foreach ($details['optional_fees'] as $fee) {
                    $fees_text .= "- {$fee['name']}: {$fee['price']}\n";
                }
                update_post_meta($post_id, '_hotel_dis_expenses', wp_kses_post($fees_text));
            }
        }

        // House rules (includes pet policy, age restriction, etc.)
        if (!empty($details['house_rules'])) {
            $rules_text = implode("\n", $details['house_rules']);
            
            // Detect pet rules
            $pet_keywords = ['pet', 'animal', 'dog', 'cat'];
            foreach ($details['house_rules'] as $rule) {
                $rule_lower = strtolower($rule);
                foreach ($pet_keywords as $keyword) {
                    if (strpos($rule_lower, $keyword) !== false) {
                        update_post_meta($post_id, '_hotel_Pets_rouls', wp_kses_post($rule));
                        break 2;
                    }
                }
            }
        }

        // Property description (use as content if empty)
        if (!empty($details['property_description'])) {
            $current = get_post($post_id);
            if ($current && empty($current->post_content)) {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => wp_kses_post($details['property_description']),
                ]);
            }
        }

        // Additional images for gallery
        if (!empty($details['images'])) {
            \NextSafar\Api\Services\ImageManager::fillGallery($post_id, $details['images'], '_hotel_gallery', 20);
        }

        error_log("✅ [HotelSync] Property details enriched for #{$post_id}");

    } catch (\Throwable $e) {
        error_log("⚠️ [HotelSync] Property details failed: " . $e->getMessage());
    }
}


    /**
     * Extract minutes from duration string like "7 min", "1 hr 30 min"
     */
    protected function extractMinutes(string $duration): string
    {
        $duration = strtolower(trim($duration));
        $total = 0;

        // Match hours
        if (preg_match('/(\d+)\s*hr/i', $duration, $m)) {
            $total += (int) $m[1] * 60;
        }
        // Match minutes
        if (preg_match('/(\d+)\s*min/i', $duration, $m)) {
            $total += (int) $m[1];
        }

        return $total > 0 ? (string) $total : '';
    }

    /* ========================================================================
       Save Single Hotel
       ======================================================================== */

    public function save(array $data): string
    {
        $data['search_location'] = $data['search_location'] ?? '';

        $result = $this->savePost($data);

        if ($result['action'] === 'failed') {
            return 'failed';
        }

        $post_id = $result['post_id'];

        // Save geo data first
        $this->saveGeoMeta($post_id, $data);

        // Save hotel-specific fields
        $this->saveHotelFields($post_id, $data);

        // Save images
        $this->saveFeaturedImage($post_id, $data);

        if (!empty($data['images'])) {
            ImageManager::fillGallery($post_id, $data['images'], '_hotel_gallery', 8);
        }

        return $result['action'];
    }

    /* ========================================================================
       Save Hotel-Specific Fields
       ======================================================================== */

    protected function saveHotelFields(int $post_id, array $data): void
    {
        // Hotel-specific meta
        if (!empty($data['stars'])) {
            update_post_meta($post_id, '_hotel_stars', (int) $data['stars']);
        }

        if (!empty($data['rating'])) {
            update_post_meta($post_id, '_hotel_rating', (float) $data['rating']);
        }

        if (!empty($data['reviews_count'])) {
            update_post_meta($post_id, '_hotel_reviews', (int) $data['reviews_count']);
        }

        if (!empty($data['check_in_time'])) {
            update_post_meta($post_id, '_hotel_checkin', sanitize_text_field($data['check_in_time']));
        }

        if (!empty($data['check_out_time'])) {
            update_post_meta($post_id, '_hotel_checkout', sanitize_text_field($data['check_out_time']));
        }

        if (!empty($data['link'])) {
            update_post_meta($post_id, '_hotel_booking_link', esc_url_raw($data['link']));
        }

        if (!empty($data['country_code'])) {
            update_post_meta($post_id, '_hotel_country_code', sanitize_text_field($data['country_code']));
        }

        if (!empty($data['property_token'])) {
            update_post_meta($post_id, '_hotel_property_token', sanitize_text_field($data['property_token']));
        }

        // 🆕 Get property details if we have token
        if (!empty($data['property_token'])) {
            $this->enrichWithPropertyDetails($post_id, $data['property_token']);
        }

        // Save price data
        if (!empty($data['price'])) {
            update_post_meta($post_id, '_hotel_price_usd', (float) $data['price']);
            update_post_meta($post_id, '_hotel_currency', $data['currency'] ?? 'USD');
        }

        // Save nearby places
        if (!empty($data['nearby_places']) && is_array($data['nearby_places'])) {
            $this->saveNearbyLocations($post_id, $data['nearby_places']);
        }

        // Save amenities
        if (!empty($data['amenities']) && is_array($data['amenities'])) {
            $this->saveAmenities($post_id, $data['amenities']);
        }

        // Save review breakdown (ratings 0-10)
        if (!empty($data['reviews_breakdown']) && is_array($data['reviews_breakdown'])) {
            $this->saveReviewBreakdown($post_id, $data['reviews_breakdown']);
        }

        // Enrich with address and working hours from Google Maps
        $this->enrichAddressFromSearchApi(
            $post_id,
            $data['name'] ?? '',
            $data['city'] ?? '',
            $data['country'] ?? '',
            '_hotel_'
        );

        // Sync to custom table
        GeoSync::syncToCustomTable($post_id);
    }

    /* ========================================================================
       Save Amenities
       ======================================================================== */
    /**
     * Map and save amenities from API to metaboxes
     * 
     * ✅ Updated: Comprehensive mapping matching hotel-metabox.php keys
     */
    protected function saveAmenities(int $post_id, array $amenities): void
    {
        // Comprehensive mapping: API keyword => WordPress meta key (without leading _)
        // Keys must match exactly with HotelMetaBox::get_amenity_sections()
        $amenity_mapping = [
            // ═══ WiFi & Internet ═══
            'free wifi'              => 'hotel_wifi',
            'wifi'                   => 'hotel_wifi',
            'wireless internet'      => 'hotel_wifi',
            'internet'               => 'hotel_wifi',

            // ═══ TV & Media ═══
            'flat-screen tv'         => 'hotel_flatscreen_tv',
            'flatscreen tv'          => 'hotel_flatscreen_tv',
            'tv'                     => 'hotel_flatscreen_tv',
            'telephone'              => 'hotel_room_telephone',
            'satellite channels'     => 'hotel_Satellite_channels',
            'cable channels'         => 'hotel_Cable_channels',
            'radio'                  => 'hotel_radio',

            // ═══ Parking ═══
            'free parking'           => 'hotel_free_parking',
            'parking'                => 'hotel_valet_parking',
            'valet parking'          => 'hotel_valet_parking',
            'electric vehicle charging' => 'hotel_Electric_charging_station',
            'ev charging'            => 'hotel_Electric_charging_station',

            // ═══ Pool ═══
            'indoor pool'            => 'hotel_Indoor_pool',
            'outdoor pool'           => 'hotel_outdoor_pool',
            'pool'                   => 'hotel_outdoor_pool',
            'swimming pool'          => 'hotel_outdoor_pool',
            'heated pool'            => 'hotel_heated_pool',
            'pool with view'         => 'hotel_Pool_with_view',
            'pool bar'               => 'hotel_Pool_bar',
            'beach chairs'           => 'hotel_Beach_chairs_Loungers',
            'sun loungers'           => 'hotel_Beach_chairs_Loungers',
            'beach umbrellas'        => 'hotel_Beach_umbrellas',
            'sun umbrellas'          => 'hotel_sun_umbrellas',

            // ═══ Spa & Wellness ═══
            'spa'                    => 'hotel_spa',
            'massage'                => 'hotel_spa',
            'fitness center'         => 'hotel_fitness_center',
            'gym'                    => 'hotel_fitness_center',
            'fitness'                => 'hotel_fitness_center',
            'sauna'                  => 'hotel_sauna',
            'hot tub'                => 'hotel_Hot_tub',
            'jacuzzi'                => 'hotel_jacuzzi',
            'steam room'             => 'hotel_Steam_room',
            'turkish bath'           => 'hotel_Turkish_Steam_Bath',
            'hammam'                 => 'hotel_Turkish_Steam_Bath',
            'yoga'                   => 'hotel_yoga_classes',
            'personal trainer'       => 'hotel_personal_trainer',
            'facial treatments'      => 'hotel_Facial_treatments',
            'body treatments'        => 'hotel_body_treatments',
            'massage chair'          => 'hotel_Massage_chair',

            // ═══ Food & Drinks ═══
            'restaurant'             => 'hotel_restaurant',
            'bar'                    => 'hotel_bar',
            'minibar'                => 'hotel_minibar',
            'breakfast'              => 'hotel_free_breakfast',
            'free breakfast'         => 'hotel_free_breakfast',
            'breakfast in the room'  => 'hotel_Breakfast_in_the_room',
            'room service'           => 'hotel_room_servies',
            'snack bar'              => 'hotel_snack_bar',
            'kids meals'             => 'hotel_Kids_meals',
            'kid-friendly buffet'    => 'hotel_Kid_friendly_buffet',
            'special diet meals'     => 'hotel_special_diet_meals',
            'wine/champagne'         => 'hotel_wine_champagne',
            'fruit'                  => 'hotel_Fruit',

            // ═══ Transportation ═══
            'airport shuttle'        => 'hotel_airport_shuttle_service',
            'shuttle'                => 'hotel_shuttle_service',
            'car rental'             => 'hotel_car_rental',
            'public transport tickets' => 'hotel_transport_tickets',

            // ═══ Pets ═══
            'pet friendly'           => 'hotel_pet_friendly',
            'pets allowed'           => 'hotel_pet_friendly',

            // ═══ Climate ═══
            'air conditioning'       => 'hotel_air_conditioning',
            'ac'                     => 'hotel_air_conditioning',
            'heating'                => 'hotel_heating',

            // ═══ Accessibility ═══
            'accessible'             => 'hotel_Wheelchair_accessible',
            'wheelchair accessible'  => 'hotel_Wheelchair_accessible',
            'facilities for disabled' => 'hotel_Fac_for_disabled_guests',
            'elevator'               => 'hotel_elevator',

            // ═══ Business ═══
            'business center'        => 'hotel_business_center',
            'meeting rooms'          => 'hotel_Meeting_Banquet_facilities',
            'fax/photocopying'       => 'hotel_Fax_photocopying',

            // ═══ Cleaning ═══
            'laundry'                => 'hotel_laundry',
            'daily housekeeping'     => 'hotel_cleaning_daily_service',
            'daily cleaning'         => 'hotel_cleaning_daily_service',
            'ironing service'        => 'hotel_Ironing_service',
            'dry cleaning'           => 'hotel_Dry_cleaning',

            // ═══ Front Desk ═══
            '24-hour front desk'     => 'hotel_24_hour_reception',
            'concierge'              => 'hotel_concierge_service',
            'luggage storage'        => 'hotel_luggage_storage',
            'currency exchange'      => 'hotel_Currency_exchange',
            'early check-in'         => 'hotel_early_checkin',
            'late check-out'         => 'hotel_late_checkout',

            // ═══ Room Features ═══
            'kitchen'                => 'hotel_Refrigerator', // fallback
            'refrigerator'           => 'hotel_Refrigerator',
            'electric kettle'        => 'hotel_Electric_kettle',
            'coffee/tea maker'       => 'hotel_coffee_tea_maker',
            'wardrobe'               => 'hotel_wardrobe_closet',
            'safe'                   => 'hotel_safe_box',
            'sofa'                   => 'hotel_room_sofa',

            // ═══ Bathroom ═══
            'private bathroom'       => 'hotel_private_bathroom',
            'shower'                 => 'hotel_shower',
            'bathtub'                => 'hotel_Bathtub',
            'hairdryer'              => 'hotel_Hairdryer',
            'free toiletries'        => 'hotel_Free_toiletries',
            'towels'                 => 'hotel_Towels',
            'slippers'               => 'hotel_Slippers',
            'bathrobe'               => 'hotel_Bathrobe',

            // ═══ Activities ═══
            'tennis court'           => 'hotel_tennis_court',
            'golf course'            => 'hotel_golf_course',
            'bicycle rental'         => 'hotel_bicycle_rental',
            'cycling'                => 'hotel_Cycling',
            'kids club'              => 'hotel_kids_club',
            'playground'             => 'hotel_playground',
            'game room'              => 'hotel_game_room',
            'casino'                 => 'hotel_casino',
            'live music'             => 'hotel_live_music',
            'nightclub'              => 'hotel_nightclub',

            // ═══ Outdoor ═══
            'garden'                 => 'hotel_garden',
            'terrace'                => 'hotel_terrace',
            'sun terrace'            => 'hotel_Sun_terrace',
            'beachfront'             => 'hotel_Beachfront',
            'private beach'          => 'hotel_Private_beach_area',
            'outdoor furniture'      => 'hotel_outdoor_furniture',

            // ═══ Services ═══
            'gift shop'              => 'hotel_gift_shop',
            'atm'                    => 'hotel_atm_banking',
            'wedding services'       => 'hotel_wedding_services',
            'multilingual staff'     => 'hotel_multilingual_staff',
            'tour desk'              => 'hotel_tour_ticket_assistant',

            // ═══ Safety ═══
            '24-hour security'       => 'hotel_24_hour_security',
            'cctv'                   => 'hotel_CCTV_in_common_areas',
            'fire extinguishers'     => 'hotel_Fire_extinguishers',
            'smoke alarms'           => 'hotel_Smoke_alarms',
            'key card access'        => 'hotel_Key_card_access',

            // ═══ Views ═══
            'city view'              => 'hotel_city_view',
            'pool view'              => 'hotel_pool_view',

            // ═══ General ═══
            'family rooms'           => 'hotel_family_rooms',
            'non-smoking rooms'      => 'hotel_non_smoking_rooms',
            'soundproof rooms'       => 'hotel_soundproof_rooms',
            'shared lounge'          => 'hotel_Shared_lounge_TV_area',
        ];

        // Get all valid amenity keys from metabox
        $valid_keys = [];
        if (class_exists('\NextSafar\MetaBoxes\HotelMetaBox')) {
            $valid_keys = \NextSafar\MetaBoxes\HotelMetaBox::get_all_amenity_keys();
        }

        $set_amenities = [];
        $matched = 0;
        $unmatched = [];

        foreach ($amenities as $amenity) {
            if (!is_string($amenity)) {
                continue;
            }

            $amenity_lower = strtolower(trim($amenity));
            $found = false;

            // Sort mapping by length (longer keywords first) to match most specific
            $sorted_mapping = $amenity_mapping;
            uksort($sorted_mapping, function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            foreach ($sorted_mapping as $keyword => $meta_key) {
                if (strpos($amenity_lower, $keyword) !== false) {
                    // Verify the meta key is valid
                    if (in_array($meta_key, $valid_keys, true)) {
                        $set_amenities[$meta_key] = 'yes';
                        $found = true;
                        $matched++;
                        break;
                    }
                }
            }

            if (!$found) {
                $unmatched[] = $amenity;
            }
        }

        // Save matched amenities as 'yes'
        foreach ($set_amenities as $meta_key => $value) {
            update_post_meta($post_id, '_' . $meta_key, $value);
        }

        // Log results
        error_log("✅ [HotelSync] Amenities saved for #{$post_id}: " .
            "{$matched} matched, " . count($unmatched) . " unmatched. " .
            "Unmatched: " . implode(', ', array_slice($unmatched, 0, 5)));

        // Save unmatched amenities as JSON for reference
        if (!empty($unmatched)) {
            update_post_meta($post_id, '_hotel_unmatched_amenities', $unmatched);
        }
    }

    /**
     * Save review breakdown ratings (0-10 scale)
     * 
     * ✅ Enhanced: Uses direct ratings + smart fallbacks
     */
    protected function saveReviewBreakdown(int $post_id, array $data): void
    {
        $breakdown = $data['reviews_breakdown'] ?? [];
        $saved = [];

        // ═══════════════════════════════════════════════════════════
        // 1. Direct Category Mapping (از reviews_breakdown)
        // ═══════════════════════════════════════════════════════════
        $category_mapping = [
            'Service'       => 'hotel_services_point',
            'Property'      => 'hotel_facilities_point',
            'Cleanliness'   => 'hotel_cleanliness',
            'Comfort'       => 'hotel_comfort_point',
            'Location'      => 'hotel_location_point',
            'Staff'         => 'hotel_staff_point',
        ];

        foreach ($breakdown as $category) {
            if (!is_array($category)) {
                continue;
            }

            $name = $category['name'] ?? '';
            $total = (int) ($category['total'] ?? 0);
            $positive = (int) ($category['positive'] ?? 0);
            $neutral = (int) ($category['neutral'] ?? 0);

            if ($total === 0 || empty($name)) {
                continue;
            }

            // Find meta key (case-insensitive)
            $meta_key = null;
            foreach ($category_mapping as $api_name => $key) {
                if (strcasecmp($name, $api_name) === 0) {
                    $meta_key = $key;
                    break;
                }
            }

            if ($meta_key === null) {
                continue;
            }

            // Calculate score (0-10)
            $score = (($positive * 1.0) + ($neutral * 0.5)) / $total * 10;
            $score = round(max(0, min(10, $score)), 1);

            update_post_meta($post_id, '_' . $meta_key, $score);
            $saved[$meta_key] = $score;
        }

        // ═══════════════════════════════════════════════════════════
        // 2. Direct Ratings (از فیلدهای اصلی)
        // API این rating ها را مستقیم در scale 0-5 می‌دهد
        // ═══════════════════════════════════════════════════════════
        
        // Location rating (0-5) → convert to 0-10
        if (!empty($data['location_rating']) && !isset($saved['hotel_location_point'])) {
            $location_score = min(10, (float) $data['location_rating'] * 2);
            update_post_meta($post_id, '_hotel_location_point', round($location_score, 1));
            $saved['hotel_location_point'] = round($location_score, 1);
        }

        // ═══════════════════════════════════════════════════════════
        // 3. Smart Fallbacks (برای فیلدهای ناموجود در API)
        // ═══════════════════════════════════════════════════════════

        // Cleanliness: اگر نبود، از Property استفاده کن (معمولاً مشابه است)
        if (!isset($saved['hotel_cleanliness'])) {
            if (isset($saved['hotel_facilities_point'])) {
                // Cleanliness معمولاً کمی پایین‌تر از امکانات است
                $cleanliness_score = round(max(0, $saved['hotel_facilities_point'] - 0.5), 1);
                update_post_meta($post_id, '_hotel_cleanliness', $cleanliness_score);
                $saved['hotel_cleanliness'] = $cleanliness_score;
                error_log("ℹ️ [HotelSync] Cleanliness estimated from Property: {$cleanliness_score}");
            }
        }

        // Comfort: اگر نبود، از Service استفاده کن
        if (!isset($saved['hotel_comfort_point'])) {
            if (isset($saved['hotel_services_point'])) {
                // Comfort معمولاً نزدیک به Service است
                $comfort_score = round(max(0, $saved['hotel_services_point'] - 0.3), 1);
                update_post_meta($post_id, '_hotel_comfort_point', $comfort_score);
                $saved['hotel_comfort_point'] = $comfort_score;
                error_log("ℹ️ [HotelSync] Comfort estimated from Service: {$comfort_score}");
            }
        }

        // Staff: اگر نبود، از Service استفاده کن (معمولاً یکسان هستند)
        if (!isset($saved['hotel_staff_point'])) {
            if (isset($saved['hotel_services_point'])) {
                update_post_meta($post_id, '_hotel_staff_point', $saved['hotel_services_point']);
                $saved['hotel_staff_point'] = $saved['hotel_services_point'];
                error_log("ℹ️ [HotelSync] Staff = Service: {$saved['hotel_staff_point']}");
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 4. Detailed Logging
        // ═══════════════════════════════════════════════════════════
        error_log("📊 [HotelSync] Post #{$post_id} ratings:");
        error_log("   Raw API data: " . json_encode([
            'location_rating' => $data['location_rating'] ?? 'N/A',
            'breakdown_categories' => array_map(fn($c) => $c['name'] ?? '?', $breakdown),
        ]));
        error_log("   Saved scores: " . json_encode($saved));
        
        // Show missing fields
        $all_fields = [
            'hotel_staff_point',
            'hotel_facilities_point', 
            'hotel_cleanliness',
            'hotel_comfort_point',
            'hotel_services_point',
            'hotel_location_point',
        ];
        $missing = array_diff($all_fields, array_keys($saved));
        if (!empty($missing)) {
            error_log("⚠️ [HotelSync] Missing ratings for #{$post_id}: " . implode(', ', $missing));
        }
    }
}