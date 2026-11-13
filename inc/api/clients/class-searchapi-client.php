<?php
/**
 * SearchApiClient — Google Hotels & Places search via SearchApi.io
 *
 * Backup provider for SerpApi. Same endpoints, slightly different response format.
 *
 * @package NextSafar\Api\Clients
 * @since 1.0.0
 */

namespace NextSafar\Api\Clients;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class SearchApiClient extends BaseClient
{
    protected string $base_url = 'https://www.searchapi.io/api/v1/search';

    protected function getProviderName(): string
    {
        return 'SearchApi';
    }

    /* ========================================================================
       Search Hotels
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

        unset($params['limit']);

        $data = $this->request($this->base_url, $params);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->normalizeHotelsResponse($data, $options['limit'] ?? 10);
    }

    /* ========================================================================
       Search Places
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
       Get Place Details (for enrichment)
       ======================================================================== */

    /**
     * Get detailed place information from Google Maps
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

    /**
     * Extract place details from Google Maps response
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

        // Extract working hours
        $hours = [];
        if (!empty($place['open_hours'])) {
            foreach ($place['open_hours'] as $day => $time) {
                $hours[strtolower($day)] = is_array($time) ? implode(', ', $time) : $time;
            }
        }

        return [
            'place_id' => $place['place_id'] ?? '',
            'data_id'  => $place['data_id'] ?? '',
            'name'     => $place['title'] ?? '',
            'address'  => $place['address'] ?? '',
            'phone'    => $place['phone'] ?? '',
            'website'  => $this->extractWebsite($place['website'] ?? ''),
            'rating'   => (float) ($place['rating'] ?? 0),
            'reviews'  => (int) ($place['reviews'] ?? 0),
            'lat'      => (float) ($place['gps_coordinates']['latitude'] ?? 0),
            'lng'      => (float) ($place['gps_coordinates']['longitude'] ?? 0),
            'type'     => $place['type'] ?? '',
            'hours'    => $hours,
        ];
    }

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

        // SearchApi may return in different keys
        $properties = $data['properties']
            ?? $data['places']
            ?? $data['places_results']
            ?? $data['local_results']
            ?? [];

        if (empty($properties)) {
            error_log("⚠️ [SearchApi] No properties found");
            return $hotels;
        }

        $properties = array_slice($properties, 0, $limit);

        // Track external IDs to prevent duplicates
        $seen_ids = [];

        foreach ($properties as $prop) {
            if (empty($prop['name'])) {
                continue;
            }

            // Generate guaranteed unique external_id
            $external_id = $this->generateUniqueId($prop, $seen_ids);
            $seen_ids[$external_id] = true;

            $country_code = strtoupper(trim($prop['country'] ?? ''));
            $country_name = $this->countryCodeToName($country_code);
            $city = $this->extractMainCity($prop['city'] ?? '');

            // Build address
            $address = $prop['address'] ?? $prop['full_address'] ?? '';
            if (empty($address)) {
                $address = implode(', ', array_filter([$city, $country_name]));
            }

            $hotels[] = [
                'external_id'      => $external_id,
                'name'             => $prop['name'] ?? '',
                'name_en'          => $prop['name'] ?? '',
                'description'      => $prop['description'] ?? '',
                'address'          => $address,
                'city'             => $city,
                'country'          => $country_name,
                'country_code'     => $country_code,
                'lat'              => (float) ($prop['gps_coordinates']['latitude'] ?? 0),
                'lng'              => (float) ($prop['gps_coordinates']['longitude'] ?? 0),
                'stars'            => (int) ($prop['extracted_hotel_class'] ?? ($prop['hotel_class'] ?? 0)),
                'rating'           => (float) ($prop['rating'] ?? 0),
                'reviews_count'    => (int) ($prop['reviews'] ?? 0),
                'price'            => (float) ($prop['price_per_night']['extracted_price'] ?? 0),
                'currency'         => 'USD',
                'amenities'        => $prop['amenities'] ?? [],
                'images'           => $this->extractImages($prop['images'] ?? []),
                'link'             => $prop['link'] ?? '',
                'property_token'   => $prop['property_token'] ?? '',
                'check_in_time'    => $this->normalizeTime($prop['check_in_time'] ?? ''),
                'check_out_time'   => $this->normalizeTime($prop['check_out_time'] ?? ''),
                'reviews_breakdown' => $prop['reviews_breakdown'] ?? [],
                'location_rating'   => (float) ($prop['location_rating'] ?? 0),
                'source'            => 'searchapi',
            ];
        }

        error_log("✅ [SearchApi] Normalized " . count($hotels) . " hotels");
        return $hotels;
    }

    /* ========================================================================
       Normalize Places Response
       ======================================================================== */

    protected function normalizePlacesResponse(array $data, string $type, int $limit): array
    {
        $places = [];
        $results = $data['local_results'] ?? ($data['places_results'] ?? []);

        if (empty($results) || !is_array($results)) {
            return $places;
        }

        $results = array_slice($results, 0, $limit);

        foreach ($results as $place) {
            if (empty($place['title'])) {
                continue;
            }

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
                'opening_hours'     => $place['open_hours'] ?? [],
                'open_state'        => $place['open_state'] ?? '',
                'extensions'        => $this->extractExtensions($place['extensions'] ?? []),
                'iata_code'         => $type === 'airport' ? $this->extractIataCode($place) : '',
                'thumbnail'         => $place['thumbnail'] ?? '',
                'images'            => $this->extractImages($place['images'] ?? []),
                'source'            => 'searchapi',
            ];
        }

        error_log("✅ [SearchApi] Normalized " . count($places) . " {$type}s");
        return $places;
    }

    /* ========================================================================
       Helper Methods
       ======================================================================== */

    /**
     * Generate guaranteed unique external ID
     */
    protected function generateUniqueId(array $prop, array $seen_ids): string
    {
        // Priority: property_token → data_id → place_id → random
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

        // Fallback: real unique ID
        return 'hotel_' . uniqid('', true) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Convert country code to full name
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
            'PT' => 'Portugal', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
            'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czech Republic',
        ];

        return $countries[$code] ?? $code;
    }

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

    protected function extractExtensions(array $extensions): array
    {
        $result = [];
        foreach ($extensions as $ext) {
            if (!empty($ext['items'])) {
                foreach ($ext['items'] as $item) {
                    if (!empty($item['value'])) {
                        $result[] = $item['value'];
                    }
                }
            } elseif (is_string($ext)) {
                $result[] = $ext;
            }
        }
        return $result;
    }

    protected function extractIataCode(array $place): string
    {
        $title = $place['title'] ?? '';
        if (preg_match('/\(([A-Z]{3})\)/', $title, $matches)) {
            return $matches[1];
        }

        $desc = $place['description'] ?? '';
        if (preg_match('/IATA[:\s]+([A-Z]{3})/i', $desc, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function extractImages(array $images): array
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

    /**
     * Normalize time format to HH:MM
     */
    protected function normalizeTime(string $time_str): string
    {
        if (empty($time_str)) {
            return '';
        }

        $timestamp = strtotime($time_str);
        if ($timestamp !== false) {
            return date('H:i', $timestamp);
        }

        return $time_str;
    }

        /* ========================================================================
       Search Places by Name (for PostSync)
       ======================================================================== */
    
    /**
     * Search for places by exact name (for manual post sync)
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