<?php
/**
 * AirportSync — Synchronize airports from Google Maps + Wikidata + Aviation APIs
 *
 * 🚀 ENHANCED: Multi-source enrichment
 * - Google Maps for basic info
 * - Wikipedia/Wikidata for ICAO, opening year, terminals, runways
 * - Aviation APIs for airlines serving the airport
 *
 * @package NextSafar\Api\Sync
 * @since 1.0.0
 */

namespace NextSafar\Api\Sync;

use NextSafar\Api\Clients\WikipediaClient;
use NextSafar\Api\Clients\AviationClient;
use NextSafar\Sync\GeoSync;
use NextSafar\Sync\Traits\PlaceEnrichTrait;

if (!defined('ABSPATH')) {
    exit;
}

class AirportSync extends BaseSync
{
    use PlaceEnrichTrait;

    protected string $post_type = 'airport';

    protected function getSearchMethod(): string
    {
        return 'searchAirports';
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
        $this->saveAirportFields($post_id, $data);
        $this->saveFeaturedImage($post_id, $data);

        return $result['action'];
    }

    // ═══════════════════════════════════════════════════════════
    // SAVE AIRPORT FIELDS
    // ═══════════════════════════════════════════════════════════

    protected function saveAirportFields(int $post_id, array $data): void
    {
        // IATA code from search result
        $iata = $data['iata_code'] ?? $this->extractIataCode($data);
        if (!empty($iata)) {
            update_post_meta($post_id, '_airport_iata', strtoupper($iata));
        }

        // ICAO code (try multiple sources)
        $icao = $this->extractIcaoCode($data);
        if (!empty($icao)) {
            update_post_meta($post_id, '_airport_icao', strtoupper($icao));
        }

        // Airport type
        $type = $this->detectAirportType($data);
        update_post_meta($post_id, '_airport_type', sanitize_text_field($type));

        // Rating
        if (!empty($data['rating'])) {
            update_post_meta($post_id, '_airport_rating', (float) $data['rating']);
        }
        if (!empty($data['reviews_count'])) {
            update_post_meta($post_id, '_airport_reviews', (int) $data['reviews_count']);
        }

        // Contact
        if (!empty($data['phone'])) {
            update_post_meta($post_id, '_airport_phone', sanitize_text_field($data['phone']));
        }
        if (!empty($data['website'])) {
            update_post_meta($post_id, '_airport_website', esc_url_raw($data['website']));
            update_post_meta($post_id, '_airline_main_airline_site', esc_url_raw($data['website']));
        }

        // Save facilities from extensions
        if (!empty($data['extensions']) && is_array($data['extensions'])) {
            $this->saveAirportFacilities($post_id, $data['extensions']);
            $this->saveAirportServices($post_id, $data['extensions']);
        }

        // Enrich address (Google Maps)
        $this->enrichAddressFromSearchApi(
            $post_id,
            $data['name'] ?? '',
            $data['city'] ?? '',
            $data['country'] ?? '',
            '_airport_'
        );

        // 🆕 WIKIPEDIA/WIKIDATA ENRICHMENT
        $this->enrichWithWikidata($post_id, $data);

        // 🆕 AVIATION API ENRICHMENT (Airlines + extra data)
        if (!empty($iata)) {
            $this->enrichWithAviationData($post_id, $iata);
        }

        GeoSync::syncToCustomTable($post_id);
    }

    // ═══════════════════════════════════════════════════════════
    // 🆕 WIKIDATA ENRICHMENT
    // ═══════════════════════════════════════════════════════════

    protected function enrichWithWikidata(int $post_id, array $data): void
    {
        $name = $data['name_en'] ?? ($data['name'] ?? '');
        
        if (empty($name)) {
            return;
        }

        // Prevent repeated attempts
        if (get_post_meta($post_id, '_airport_wikidata_attempted', true)) {
            return;
        }

        update_post_meta($post_id, '_airport_wikidata_attempted', '1');

        try {
            $wiki_client = new WikipediaClient('en');
            $info = $wiki_client->getAirportInfo($name);

            if (empty($info) || empty($info['wikidata'])) {
                error_log("⚠️ [AirportSync] No Wikidata found for: {$name}");
                return;
            }

            $wikidata = $info['wikidata'];

            // Map Wikidata → Metabox fields
            $field_mapping = [
                'iata'           => '_airport_iata',
                'icao'           => '_airport_icao',
                'opened'         => '_airport_opened',
                'elevation'      => '_airport_elevation',
                'operator'       => '_airport_operator',
                'runway_count'   => '_airport_runways_count',
                'terminal_count' => '_airport_terminals',
            ];

            foreach ($field_mapping as $wd_key => $meta_key) {
                if (!empty($wikidata[$wd_key])) {
                    // Only set if not already set by API
                    $existing = get_post_meta($post_id, $meta_key, true);
                    if (empty($existing)) {
                        update_post_meta($post_id, $meta_key, sanitize_text_field($wikidata[$wd_key]));
                    }
                }
            }

            // Save runways list
            if (!empty($wikidata['runways']) && is_array($wikidata['runways'])) {
                update_post_meta($post_id, '_airport_runways_list', $wikidata['runways']);
            }

            // Save terminals list
            if (!empty($wikidata['terminals']) && is_array($wikidata['terminals'])) {
                update_post_meta($post_id, '_airport_terminals_list', $wikidata['terminals']);
            }

            // Save airlines from Wikidata (P1124)
            if (!empty($wikidata['airlines']) && is_array($wikidata['airlines'])) {
                $this->saveAirlinesList($post_id, $wikidata['airlines']);
            }

            // Wikipedia URL
            if (!empty($info['fullurl'])) {
                update_post_meta($post_id, '_airport_wikipedia_url', esc_url($info['fullurl']));
            }

            // Wikipedia content
            if (!empty($info['excerpt'])) {
                $current = get_post($post_id);
                if ($current && empty($current->post_content)) {
                    wp_update_post([
                        'ID'           => $post_id,
                        'post_content' => wp_kses_post(substr($info['excerpt'], 0, 3000)),
                    ]);
                }
            }

            // Wikipedia thumbnail (if no featured image)
            if (!empty($info['thumbnail']) && !has_post_thumbnail($post_id)) {
                \NextSafar\Api\Services\ImageManager::setFeaturedImage($post_id, $info['thumbnail'], 'wikipedia');
            }

            // Wikidata image (fallback to Wikipedia)
            if (!empty($wikidata['image']) && !has_post_thumbnail($post_id)) {
                \NextSafar\Api\Services\ImageManager::setFeaturedImage($post_id, $wikidata['image'], 'wikidata');
            }

            error_log("✅ [AirportSync] Wikidata enriched for #{$post_id}");

        } catch (\Throwable $e) {
            error_log("⚠️ [AirportSync] Wikidata failed: " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🆕 AVIATION API ENRICHMENT
    // ═══════════════════════════════════════════════════════════

    protected function enrichWithAviationData(int $post_id, string $iata): void
    {
        try {
            $aviation_client = new AviationClient();
            $info = $aviation_client->getAirportInfo($iata);

            if (empty($info)) {
                return;
            }

            // ICAO code
            if (!empty($info['icao'])) {
                $existing = get_post_meta($post_id, '_airport_icao', true);
                if (empty($existing)) {
                    update_post_meta($post_id, '_airport_icao', strtoupper($info['icao']));
                }
            }

            // Elevation
            if (!empty($info['elevation_ft'])) {
                $existing = get_post_meta($post_id, '_airport_elevation', true);
                if (empty($existing)) {
                    update_post_meta($post_id, '_airport_elevation', (int) $info['elevation_ft']);
                }
            }

            // Runway count
            if (!empty($info['runway_count'])) {
                $existing = get_post_meta($post_id, '_airport_runways_count', true);
                if (empty($existing)) {
                    update_post_meta($post_id, '_airport_runways_count', (int) $info['runway_count']);
                }
            }

            // Airlines from OpenSky (observed)
            if (!empty($info['airlines_observed']) && is_array($info['airlines_observed'])) {
                $this->saveAirlinesFromOpenSky($post_id, $info['airlines_observed']);
            }

            // Also fetch airlines via dedicated method
            $airlines = $aviation_client->getAirlines($iata);
            if (!empty($airlines)) {
                $this->saveAirlinesList($post_id, $airlines);
            }

            error_log("✅ [AirportSync] Aviation data enriched for #{$post_id}");

        } catch (\Throwable $e) {
            error_log("⚠️ [AirportSync] Aviation failed: " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // AIRLINES HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * Save airlines as metabox format (name + link)
     */
    protected function saveAirlinesList(int $post_id, array $airline_names): void
    {
        if (empty($airline_names)) {
            return;
        }

        // Get existing airlines to prevent duplicates
        $existing = get_post_meta($post_id, '_airlines', true);
        $existing = is_array($existing) ? $existing : [];
        $existing_names = array_column($existing, 'name');

        $new_airlines = [];
        foreach ($airline_names as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            // Skip if already exists
            if (in_array($name, $existing_names, true)) {
                continue;
            }

            $new_airlines[] = [
                'name' => sanitize_text_field($name),
                'link' => $this->getAirlineWebsite($name),
            ];
        }

        if (!empty($new_airlines)) {
            $all_airlines = array_merge($existing, $new_airlines);
            update_post_meta($post_id, '_airlines', $all_airlines);
            error_log("✅ [AirportSync] Added " . count($new_airlines) . " airlines for #{$post_id}");
        }
    }

    /**
     * Save OpenSky observed airlines (IATA codes)
     */
    protected function saveAirlinesFromOpenSky(int $post_id, array $airline_codes): void
    {
        // These are just codes (e.g., "IRA", "THY")
        // We could resolve to names via Wikidata or a static mapping
        // For now, save as raw codes
        update_post_meta($post_id, '_airport_observed_airlines', $airline_codes);
    }

    /**
     * Try to get airline website by name
     */
    protected function getAirlineWebsite(string $airline_name): string
    {
        // Static mapping for major airlines (can be expanded)
        $airlines_map = [
            'Iran Air'           => 'https://www.iranair.com',
            'Mahan Air'          => 'https://www.mahan.aero',
            'Turkish Airlines'   => 'https://www.turkishairlines.com',
            'Emirates'           => 'https://www.emirates.com',
            'Qatar Airways'      => 'https://www.qatarairways.com',
            'Lufthansa'          => 'https://www.lufthansa.com',
            'British Airways'    => 'https://www.britishairways.com',
            'Air France'         => 'https://www.airfrance.com',
            'KLM'                => 'https://www.klm.com',
            'Dubai Air'          => 'https://www.flydubai.com',
            'flydubai'           => 'https://www.flydubai.com',
        ];

        foreach ($airlines_map as $name => $website) {
            if (stripos($airline_name, $name) !== false) {
                return $website;
            }
        }

        return '';
    }

    // ═══════════════════════════════════════════════════════════
    // EXISTING FACILITIES & SERVICES METHODS (UNCHANGED)
    // ═══════════════════════════════════════════════════════════

    protected function saveAirportFacilities(int $post_id, array $extensions): void
    {
        $extensions_lower = array_map('strtolower', array_filter(array_map(function ($e) {
            return is_string($e) ? $e : '';
        }, $extensions)));
        
        $extensions_string = implode(' ', $extensions_lower);

        $facility_mapping = [
            'waiting'         => 'airport_waiting_halls',
            'lounge'          => 'airport_lounges',
            'vip'             => 'airport_vip_cip',
            'transit'         => 'airport_transit_area',
            'customs'         => 'airport_customs',
            'immigration'     => 'airport_customs',
            'check-in'        => 'airport_checkin_counters',
            'checkin'         => 'airport_checkin_counters',
            'baggage'         => 'airport_baggage_claim',
            'parking'         => 'airport_parking_short',
            'taxi'            => 'airport_taxi_bus_parking',
            'public transport'=> 'airport_public_transport',
            'metro'           => 'airport_public_transport',
            'train'           => 'airport_public_transport',
            'bus'             => 'airport_public_transport',
            'wifi'            => 'airport_wifi',
            'free wifi'       => 'airport_wifi',
            'restroom'        => 'airport_restrooms',
            'prayer'          => 'airport_prayer_room',
            'mother'          => 'airport_mother_child',
            'baby'            => 'airport_mother_child',
            'play'            => 'airport_kids_play_area',
            'kids'            => 'airport_kids_play_area',
            'spa'             => 'airport_spa',
            'massage'         => 'airport_spa',
            'smoking'         => 'airport_smoking_room',
            'hotel'           => 'airport_airport_hotel',
            'security'        => 'airport_security_gates',
            'cctv'            => 'airport_cctv',
        ];

        foreach ($facility_mapping as $keyword => $meta_key) {
            if (strpos($extensions_string, $keyword) !== false) {
                update_post_meta($post_id, '_' . $meta_key, 'yes');
            }
        }

        error_log("✅ [AirportSync] Facilities saved for #{$post_id}");
    }

    protected function saveAirportServices(int $post_id, array $extensions): void
    {
        $extensions_lower = array_map('strtolower', array_filter(array_map(function ($e) {
            return is_string($e) ? $e : '';
        }, $extensions)));
        
        $extensions_string = implode(' ', $extensions_lower);

        $service_mapping = [
            'car rental'       => 'car_rental',
            'rental car'       => 'car_rental',
            'atm'              => 'atm',
            'bank'             => 'atm',
            'currency exchange'=> 'currency_exchange',
            'restaurant'       => 'restaurant',
            'cafe'             => 'cafe',
            'coffee'           => 'cafe',
            'shopping'         => 'shopping',
            'duty free'        => 'duty_free',
            'pharmacy'         => 'pharmacy',
            'medical'          => 'medical',
            'clinic'           => 'medical',
            'police'           => 'police',
            'lost and found'   => 'lost_found',
            'information'      => 'information_desk',
            'concierge'        => 'information_desk',
            'charging station' => 'charging_station',
            'wifi'             => 'wifi',
            'internet'         => 'wifi',
        ];

        $services = [];
        foreach ($service_mapping as $keyword => $service_key) {
            if (strpos($extensions_string, $keyword) !== false && !in_array($service_key, $services)) {
                $services[] = $service_key;
            }
        }

        if (!empty($services)) {
            update_post_meta($post_id, '_airport_services', $services);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ICAO/TYPE EXTRACTION
    // ═══════════════════════════════════════════════════════════

    protected function extractIcaoCode(array $data): string
    {
        // Try from title
        $title = $data['name'] ?? '';
        if (preg_match('/\b([A-Z]{4})\b/', $title, $matches)) {
            return $matches[1];
        }

        // Try from description
        $desc = $data['description'] ?? '';
        if (preg_match('/ICAO[:\s]+([A-Z]{4})/i', $desc, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function extractIataCode(array $data): string
    {
        $title = $data['name'] ?? '';
        if (preg_match('/\(([A-Z]{3})\)/', $title, $matches)) {
            return $matches[1];
        }

        $desc = $data['description'] ?? '';
        if (preg_match('/IATA[:\s]+([A-Z]{3})/i', $desc, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function detectAirportType(array $data): string
    {
        $name = strtolower($data['name'] ?? '');
        $desc = strtolower($data['description'] ?? '');
        $combined = "{$name} {$desc}";

        if (strpos($combined, 'international') !== false) {
            return 'international';
        }
        if (strpos($combined, 'domestic') !== false) {
            return 'domestic';
        }
        if (strpos($combined, 'military') !== false) {
            return 'military';
        }
        if (strpos($combined, 'cargo') !== false) {
            return 'cargo';
        }
        if (strpos($combined, 'private') !== false) {
            return 'private';
        }

        return 'civil';
    }
}