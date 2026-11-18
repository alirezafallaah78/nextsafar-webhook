<?php
/**
 * SerpApiClient — Google Hotels & Places search via SerpApi.com
 *
 * Supports 6 post types:
 * - Hotels (google_hotels engine)
 * - Restaurants, Hospitals, Destinations, Airports (google_maps engine)
 *
 * @package NextSafar\Api\Clients
 * @since 1.0.0
 */

namespace NextSafar\Api\Clients;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class SerpApiClient extends BaseClient
{
    protected string $base_url = 'https://serpapi.com/search';

    protected function getProviderName(): string
    {
        return 'SerpApi';
    }

    /* ========================================================================
       Search Hotels (google_hotels engine)
       ======================================================================== */

    public function searchHotels(string $location, array $options = []): array|WP_Error
    {
        $params = array_merge([
            'engine'         => 'google_hotels',
            'q'              => $location,
            'hl'             => 'en',
            'gl'             => 'us',
            'currency'       => 'USD',
            'adults'         => $options['adults'] ?? 2,
            'check_in_date'  => $options['check_in'] ?? date('Y-m-d', strtotime('+7 days')),
            'check_out_date' => $options['check_out'] ?? date('Y-m-d', strtotime('+10 days')),
        ], $options);

        // Remove our custom keys before sending to API
        unset($params['limit']);

        $data = $this->request($this->base_url, $params);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->normalizeHotelsResponse($data, $options['limit'] ?? 10);
    }

    /* ========================================================================
       Search Places (google_maps engine)
       ======================================================================== */

    public function searchRestaurants(string $location, array $options = []): array|WP_Error
    {
        return $this->searchPlaces("restaurants in {$location}", 'restaurant', $options);
    }

    public function searchHospitals(string $location, array $options = []): array|WP_Error
    {
        return $this->searchPlaces("hospitals in {$location}", 'hospital', $options);
    }

    public function searchDestinations(string $location, array $options = []): array|WP_Error
    {
        return $this->searchPlaces("tourist attractions in {$location}", 'destination', $options);
    }

    public function searchAirports(string $location, array $options = []): array|WP_Error
    {
        return $this->searchPlaces("airports in {$location}", 'airport', $options);
    }

        /* ========================================================================
       Get Place Details (for enrichment via Google Maps)
       ======================================================================== */

    /**
     * Get detailed place information from Google Maps via SerpApi
     *
     * @param string $query Search query
     * @return array|null Place details or null on failure
     */
    public function getPlaceDetails(string $query): ?array
    {
        if (empty($query) || !$this->hasApiKey()) {
            return null;
        }

        $params = [
            'engine' => 'google_maps',
            'q'      => $query,
            'hl'     => 'en',
            'gl'     => 'us',
        ];

        $data = $this->request($this->base_url, $params);
        if (is_wp_error($data)) {
            return null;
        }

        return $this->extractPlaceDetails($data);
    }

    // در کلاس SerpApiClient، این متد را اضافه کنید:

/**
 * Get detailed hotel property information
 * Includes: check-in/out policies, optional fees, house rules, etc.
 */
public function getHotelPropertyDetails(string $property_token): ?array
{
    if (empty($property_token) || !$this->hasApiKey()) {
        return null;
    }

    $params = [
        'engine'         => 'google_hotels_property_details',
        'property_token' => $property_token,
        'hl'             => 'en',
        'gl'             => 'us',
    ];

    $data = $this->request($this->base_url, $params);
    
    if (is_wp_error($data)) {
        return null;
    }

    return $this->extractPropertyDetails($data);
}

/**
 * Extract property details from response
 */
protected function extractPropertyDetails(array $data): array
{
    $details = [
        'checkin_instructions'  => '',
        'checkout_instructions' => '',
        'optional_fees'         => [],
        'house_rules'           => [],
        'policies'              => [],
        'accessibility'         => [],
        'property_description'  => '',
        'images'                => [],
    ];

    // Extract check-in/out instructions
    if (!empty($data['check_in_instructions'])) {
        $details['checkin_instructions'] = $data['check_in_instructions'];
    }
    if (!empty($data['check_out_instructions'])) {
        $details['checkout_instructions'] = $data['check_out_instructions'];
    }

    // Extract optional fees
    if (!empty($data['optional_fees']) && is_array($data['optional_fees'])) {
        foreach ($data['optional_fees'] as $fee) {
            $details['optional_fees'][] = [
                'name'  => $fee['name'] ?? '',
                'price' => $fee['price'] ?? '',
            ];
        }
    }

    // Extract house rules
    if (!empty($data['house_rules']) && is_array($data['house_rules'])) {
        $details['house_rules'] = $data['house_rules'];
    }

    // Extract policies
    if (!empty($data['policies']) && is_array($data['policies'])) {
        foreach ($data['policies'] as $policy) {
            $details['policies'][] = [
                'title' => $policy['title'] ?? '',
                'text'  => $policy['description'] ?? '',
            ];
        }
    }

    // Property description
    if (!empty($data['description'])) {
        $details['property_description'] = $data['description'];
    }

    // Images
    if (!empty($data['images']) && is_array($data['images'])) {
        $details['images'] = $this->extractHotelImages(['images' => $data['images']]);
    }

    return $details;
}

    /**
     * Extract place details from Google Maps response
     * ✅ Enhanced: Extract place_id from multiple possible fields
     */
    protected function extractPlaceDetails(array $data): ?array
    {
        $results = $data['local_results'] ?? ($data['places_results'] ?? []);

        if (empty($results) || !is_array($results)) {
            return null;
        }

        $place = $results[0] ?? null;
        if (empty($place)) {
            return null;
        }

        // ✅ Extract place_id from multiple possible fields (SerpApi uses different keys)
        $place_id = $place['place_id'] 
                ?? $place['place_id_search'] 
                ?? $place['google_id'] 
                ?? $place['lsig']
                ?? '';

        // Extract working hours
        $hours = [];
        $hours_data = $place['opening_hours'] ?? $place['open_hours'] ?? [];
        if (!empty($hours_data)) {
            foreach ($hours_data as $day => $time) {
                $hours[strtolower($day)] = is_array($time) ? implode(', ', $time) : $time;
            }
        }

        $details = [
            'place_id'  => $place_id,
            'data_id'   => $place['data_id'] ?? '',
            'name'      => $place['title'] ?? '',
            'address'   => $place['address'] ?? '',
            'phone'     => $place['phone'] ?? '',
            'website'   => $this->extractWebsiteUrl($place['website'] ?? ''),
            'rating'    => (float) ($place['rating'] ?? 0),
            'reviews'   => (int) ($place['reviews'] ?? 0),
            'lat'       => (float) ($place['gps_coordinates']['latitude'] ?? 0),
            'lng'       => (float) ($place['gps_coordinates']['longitude'] ?? 0),
            'type'      => $place['type'] ?? '',
            'hours'     => $hours,
        ];

        error_log("🔍 [SerpApi] Place details extracted: place_id=" . ($details['place_id'] ?: 'EMPTY') . 
                ", data_id=" . ($details['data_id'] ?: 'EMPTY'));

        return $details;
    }

    /**
     * Extract website URL (handles string/array/object formats)
     */
    protected function extractWebsiteUrl($website): string
    {
        if (is_string($website)) {
            return $website;
        }
        if (is_array($website)) {
            return $website[0] ?? ($website['url'] ?? '');
        }
        return '';
    }

    /**
     * Common method for places search (restaurants, hospitals, destinations, airports)
     */
    protected function searchPlaces(string $query, string $type, array $options): array|WP_Error
    {
        $params = array_merge([
            'engine' => 'google_maps',
            'q'      => $query,
            'hl'     => 'en',
            'gl'     => 'us',
        ], $options);

        unset($params['limit']);

        $data = $this->request($this->base_url, $params);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->normalizePlacesResponse($data, $type, $options['limit'] ?? 10);
    }

    /* ========================================================================
       Normalize Hotels Response
       ======================================================================== */

    protected function normalizeHotelsResponse(array $data, int $limit): array
    {
        $hotels = [];
        $properties = $data['properties'] ?? [];
        $properties = array_slice($properties, 0, $limit);

        foreach ($properties as $prop) {
            if (empty($prop['name'])) {
                continue;
            }

            $hotels[] = [
                'external_id'      => $prop['property_token'] ?? uniqid('hotel_'),
                'name'             => $prop['name'] ?? '',
                'name_en'          => $prop['name'] ?? '',
                'description'      => $prop['description'] ?? '',
                'address'          => $prop['address'] ?? '',
                'city'             => $prop['city'] ?? '',
                'country'          => $prop['country'] ?? '',
                'country_code'     => $prop['country_code'] ?? '',
                'lat'              => (float) ($prop['gps_coordinates']['latitude'] ?? 0),
                'lng'              => (float) ($prop['gps_coordinates']['longitude'] ?? 0),
                'stars'            => (int) ($prop['hotel_class'] ?? 0),
                'rating'           => (float) ($prop['overall_rating'] ?? 0),
                'reviews_count'    => (int) ($prop['reviews'] ?? 0),
                'price'            => (float) ($prop['extracted_price'] ?? 0),
                'currency'         => 'USD',
                'amenities'        => $prop['amenities'] ?? [],
                'images'           => $this->extractHotelImages($prop),
                'link'             => $prop['link'] ?? '',
                'property_token'   => $prop['property_token'] ?? '',
                'check_in_time'    => $prop['check_in_time'] ?? '',
                'check_out_time'   => $prop['check_out_time'] ?? '',
                'reviews_breakdown'                  => $prop['reviews_breakdown'] ?? [],
                'location_rating'                    => (float) ($prop['location_rating'] ?? 0),
                'proximity_to_things_to_do_rating'   => (float) ($prop['proximity_to_things_to_do_rating'] ?? 0),
                'proximity_to_transit_rating'        => (float) ($prop['proximity_to_transit_rating'] ?? 0),
                'airport_access_rating'              => (float) ($prop['airport_access_rating'] ?? 0),
                'source'                             => 'serpapi',
            ];
        }

        error_log("✅ [SerpApi] Normalized " . count($hotels) . " hotels");
        return $hotels;
    }

    /**
     * Extract hotel images from response
     */
    protected function extractHotelImages(array $prop): array
    {
        $images = [];

        // SerpApi returns images in different formats
        if (!empty($prop['images']) && is_array($prop['images'])) {
            foreach ($prop['images'] as $img) {
                if (is_string($img)) {
                    $images[] = $img;
                } elseif (is_array($img)) {
                    $images[] = $img['original_image'] ?? $img['link'] ?? $img['thumbnail'] ?? '';
                }
            }
        }

        // Fallback to thumbnail
        if (empty($images) && !empty($prop['thumbnail'])) {
            $images[] = $prop['thumbnail'];
        }

        return array_values(array_filter($images));
    }

    /* ========================================================================
       Normalize Places Response (Restaurants, Hospitals, Destinations, Airports)
       ======================================================================== */

    protected function normalizePlacesResponse(array $data, string $type, int $limit): array
    {
        $places = [];

        // SerpApi may return in different keys
        $results = $data['local_results'] ?? ($data['places_results'] ?? []);

        if (empty($results) || !is_array($results)) {
            return $places;
        }

        $results = array_slice($results, 0, $limit);

        foreach ($results as $place) {
            if (empty($place['title'])) {
                continue;
            }

            // Parse address into city/country
            $address = $place['address'] ?? '';
            $city = '';
            $country = '';

            if (!empty($address)) {
                $parts = explode(',', $address);
                if (count($parts) >= 2) {
                    $city = $this->extractMainCity(trim($parts[count($parts) - 2] ?? ''));
                    $country = trim($parts[count($parts) - 1] ?? '');
                }
            }

            $places[] = [
                'external_id'       => $place['place_id'] ?? ($place['data_id'] ?? uniqid($type . '_')),
                'place_id'          => $place['place_id'] ?? '',
                'data_id'           => $place['data_id'] ?? '',
                'name'              => $place['title'] ?? '',
                'name_en'           => $place['title'] ?? '',
                'description'       => $place['description'] ?? '',
                'type'              => $place['type'] ?? ($place['types'][0] ?? ''),
                'types'             => $place['types'] ?? [],
                'address'           => $address,
                'city'              => $city,
                'country'           => $country,
                'lat'               => (float) ($place['gps_coordinates']['latitude'] ?? 0),
                'lng'               => (float) ($place['gps_coordinates']['longitude'] ?? 0),
                'phone'             => $place['phone'] ?? '',
                'website'           => $this->extractWebsite($place['website'] ?? ''),
                'rating'            => (float) ($place['rating'] ?? 0),
                'reviews_count'     => (int) ($place['reviews'] ?? 0),
                'price_level'       => $place['price'] ?? '',
                'opening_hours'     => $place['opening_hours'] ?? [],
                'open_state'        => $place['open_state'] ?? '',
                'extensions'        => $this->extractExtensions($place['extensions'] ?? []),
                'iata_code'         => $type === 'airport' ? $this->extractIataCode($place) : '',
                'thumbnail'         => $place['thumbnail'] ?? '',
                'images'            => $this->extractPlaceImages($place['images'] ?? []),
                'source'            => 'serpapi',
            ];
        }

        error_log("✅ [SerpApi] Normalized " . count($places) . " {$type}s");
        return $places;
    }

    /* ========================================================================
       Helper Methods
       ======================================================================== */

    /**
     * Extract main city name (remove district like Istanbul/Fatih)
     */
    protected function extractMainCity(string $city_string): string
    {
        $city_string = trim($city_string);
        if (empty($city_string)) {
            return '';
        }

        if (strpos($city_string, '/') !== false) {
            $parts = explode('/', $city_string);
            return trim($parts[0]);
        }

        if (strpos($city_string, '-') !== false) {
            $parts = explode('-', $city_string);
            return trim($parts[0]);
        }

        return $city_string;
    }

    /**
     * Extract website URL (handles string/array/object formats)
     */
    protected function extractWebsite($website): string
    {
        if (is_string($website)) {
            return $website;
        }
        if (is_array($website)) {
            return $website[0] ?? ($website['url'] ?? '');
        }
        return '';
    }

    /**
     * Extract extensions (facilities/amenities for places)
     */
    protected function extractExtensions(array $extensions): array
    {
        $result = [];
        foreach ($extensions as $ext) {
            if (is_string($ext)) {
                $result[] = $ext;
            } elseif (is_array($ext) && !empty($ext['name'])) {
                $result[] = $ext['name'];
            }
        }
        return $result;
    }

    /**
     * Extract IATA code for airports (e.g., "IKA" from "Imam Khomeini International Airport (IKA)")
     */
    protected function extractIataCode(array $place): string
    {
        $title = $place['title'] ?? '';

        // Try to find IATA code in parentheses (3 uppercase letters)
        if (preg_match('/\(([A-Z]{3})\)/', $title, $matches)) {
            return $matches[1];
        }

        // Try in description
        $desc = $place['description'] ?? '';
        if (preg_match('/IATA[:\s]+([A-Z]{3})/i', $desc, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extract place images from response
     */
    protected function extractPlaceImages(array $images): array
    {
        $result = [];
        foreach ($images as $img) {
            if (is_string($img)) {
                $result[] = $img;
            } elseif (is_array($img)) {
                if (!empty($img['original'])) {
                    $result[] = $img['original'];
                } elseif (!empty($img['thumbnail'])) {
                    $result[] = $img['thumbnail'];
                }
            }
        }
        return $result;
    }

        /* ========================================================================
       Search Places by Name (for PostSync - manual sync)
       ======================================================================== */
    
    /**
     * Search for places by exact name using Google Maps engine
     *
     * @param string $name  Place name
     * @param int    $limit Max results
     * @param string $lang  Language (fa, en)
     * @return array|WP_Error
     */
    public function searchPlacesByName(string $name, int $limit = 6, string $lang = 'fa'): array|WP_Error
    {
        $params = [
            'engine' => 'google_maps',
            'q'      => $name,
            'hl'     => $lang,
            'gl'     => $lang === 'fa' ? 'ir' : 'us',
        ];

        $data = $this->request($this->base_url, $params);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->normalizePlacesResponse($data, 'place', $limit);
    }

}