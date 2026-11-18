<?php
/**
 * WikipediaClient — Fetch information from Wikipedia and Wikidata
 * 
 * 🚀 ENHANCED: Full support for Airports and Hospitals via Wikidata
 *
 * Features:
 * - Multi-language search (Persian + English fallback)
 * - Wikipedia page details (excerpt, thumbnail, URL)
 * - Wikidata structural data (architect, height, floors)
 * - ✅ Airport: ICAO, IATA, runway count, terminals, opening year
 * - ✅ Hospital: bed count, specializations, founding year, type
 * - Batch label fetching for multiple entities
 *
 * @package NextSafar\Api\Clients
 * @since 1.0.0
 */

namespace NextSafar\Api\Clients;

if (!defined('ABSPATH')) {
    exit;
}

class WikipediaClient
{
    private string $lang;
    private int $timeout = 15;
    
    // Wikidata Property IDs
    private const WIKIDATA_PROPS = [
        // Common
        'iata'            => 'P238',   // IATA airport code
        'icao'            => 'P239',   // ICAO airport code
        'inception'       => 'P571',   // inception/opening date
        'country'         => 'P17',    // country
        'coordinate'      => 'P625',   // coordinate location
        'image'           => 'P18',    // image
        
        // Airport specific
        'runway_count'    => 'P529',   // number of runways (not standard, fallback)
        'runways'         => 'P529',   // runway
        'terminals'       => 'P526',   // terminal
        'runway_length'   => 'P204',   // length (for runways)
        'elevations'      => 'P2044',  // elevation above sea level
        'operator'        => 'P137',   // operator
        'serves_airlines' => 'P1124',  // airline hub for
        
        // Hospital specific
        'bed_count'       => 'P6803',  // number of beds
        'specialization'  => 'P1050',  // medical specialty
        'hospital_type'   => 'P31',    // instance of
        'affiliation'     => 'P1416',  // affiliation
        'founded_by'      => 'P112',   // founded by
        'employees'       => 'P1128',  // employees
        
        // Destination specific
        'architect'       => 'P84',    // architect
        'height'          => 'P2048',  // height
        'floors'          => 'P1101',  // floors above ground
        'owner'           => 'P127',   // owner
        'style'           => 'P149',   // architectural style
        'structural_system' => 'P1284', // structural system
        'former_names'    => 'P1725',  // former name
    ];

    public function __construct(string $lang = 'fa')
    {
        $this->lang = $lang;
    }

    /**
     * Get full place information from Wikipedia + Wikidata
     */
    public function getPlaceInfo(string $name): ?array
    {
        if (empty($name)) {
            return null;
        }

        $search = $this->search($name);
        if (!$search) {
            error_log("⚠️ [Wikipedia] Search failed for: {$name}");
            return null;
        }

        $details = $this->getDetails($search['title'], $search['lang'] ?? $this->lang);
        $wikidata = $this->getWikidataInfo($search['title'], $search['lang'] ?? $this->lang);

        $result = array_merge($search, $details ?? []);
        $result['wikidata'] = $wikidata;

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // 🆕 AIRPORT SPECIFIC METHOD
    // ═══════════════════════════════════════════════════════════
    
    /**
     * Get airport information (prioritizes English Wikipedia for better airport coverage)
     */
    public function getAirportInfo(string $name): ?array
    {
        if (empty($name)) {
            return null;
        }

        // Try English first (better airport coverage)
        $search = $this->search($name, 'en');
        
        if (!$search) {
            // Fallback to Persian
            $search = $this->search($name, 'fa');
        }
        
        if (!$search) {
            error_log("⚠️ [Wikipedia] Airport search failed for: {$name}");
            return null;
        }

        $details = $this->getDetails($search['title'], $search['lang']);
        $wikidata = $this->getAirportWikidataInfo($search['title'], $search['lang']);

        $result = array_merge($search, $details ?? []);
        $result['wikidata'] = $wikidata;
        $result['airport_specific'] = true;

        error_log("✅ [Wikipedia] Airport enriched: {$name}");
        return $result;
    }

    /**
     * Get Wikidata info specifically for airports
     */
    private function getAirportWikidataInfo(string $title, string $lang): array
    {
        $entity_id = $this->getWikidataEntityId($title, $lang);
        if (!$entity_id) {
            return [];
        }

        $claims = $this->getWikidataClaims($entity_id);
        if (empty($claims)) {
            return [];
        }

        $data = [
            'iata'           => $this->extractStringValue($claims[self::WIKIDATA_PROPS['iata']] ?? null),
            'icao'           => $this->extractStringValue($claims[self::WIKIDATA_PROPS['icao']] ?? null),
            'opened'         => $this->extractDateValue($claims[self::WIKIDATA_PROPS['inception']] ?? null),
            'elevation'      => $this->extractQuantityValue($claims[self::WIKIDATA_PROPS['elevations']] ?? null),
            'operator'       => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['operator']] ?? null, $lang),
            'country'        => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['country']] ?? null, $lang),
            'runways'        => $this->extractMultipleLabels($claims[self::WIKIDATA_PROPS['runways']] ?? null, $lang),
            'terminals'      => $this->extractMultipleLabels($claims[self::WIKIDATA_PROPS['terminals']] ?? null, $lang),
            'airlines'       => $this->extractMultipleLabels($claims[self::WIKIDATA_PROPS['serves_airlines']] ?? null, $lang),
            'image'          => $this->extractImageValue($claims[self::WIKIDATA_PROPS['image']] ?? null),
        ];

        // Count runways if list exists
        if (!empty($data['runways']) && is_array($data['runways'])) {
            $data['runway_count'] = count($data['runways']);
        }

        // Count terminals
        if (!empty($data['terminals']) && is_array($data['terminals'])) {
            $data['terminal_count'] = count($data['terminals']);
        }

        return array_filter($data);
    }

    // ═══════════════════════════════════════════════════════════
    // 🆕 HOSPITAL SPECIFIC METHOD
    // ═══════════════════════════════════════════════════════════

    /**
     * Get hospital information
     */
    public function getHospitalInfo(string $name): ?array
    {
        if (empty($name)) {
            return null;
        }

        // Try Persian first
        $search = $this->search($name, 'fa');
        
        if (!$search) {
            $search = $this->search($name, 'en');
        }
        
        if (!$search) {
            error_log("⚠️ [Wikipedia] Hospital search failed for: {$name}");
            return null;
        }

        $details = $this->getDetails($search['title'], $search['lang']);
        $wikidata = $this->getHospitalWikidataInfo($search['title'], $search['lang']);

        $result = array_merge($search, $details ?? []);
        $result['wikidata'] = $wikidata;
        $result['hospital_specific'] = true;

        error_log("✅ [Wikipedia] Hospital enriched: {$name}");
        return $result;
    }

    /**
     * Get Wikidata info specifically for hospitals
     */
    private function getHospitalWikidataInfo(string $title, string $lang): array
    {
        $entity_id = $this->getWikidataEntityId($title, $lang);
        if (!$entity_id) {
            return [];
        }

        $claims = $this->getWikidataClaims($entity_id);
        if (empty($claims)) {
            return [];
        }

        $data = [
            'opened'          => $this->extractDateValue($claims[self::WIKIDATA_PROPS['inception']] ?? null),
            'bed_count'       => $this->extractQuantityValue($claims[self::WIKIDATA_PROPS['bed_count']] ?? null),
            'employees'       => $this->extractQuantityValue($claims[self::WIKIDATA_PROPS['employees']] ?? null),
            'specializations' => $this->extractMultipleLabels($claims[self::WIKIDATA_PROPS['specialization']] ?? null, $lang),
            'affiliation'     => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['affiliation']] ?? null, $lang),
            'founded_by'      => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['founded_by']] ?? null, $lang),
            'country'         => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['country']] ?? null, $lang),
            'hospital_type'   => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['hospital_type']] ?? null, $lang),
            'image'           => $this->extractImageValue($claims[self::WIKIDATA_PROPS['image']] ?? null),
        ];

        return array_filter($data);
    }

    // ═══════════════════════════════════════════════════════════
    // 🆕 WIKIDATA EXTRACTION HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * Get Wikidata claims for an entity
     */
    private function getWikidataClaims(string $entity_id): array
    {
        $url = "https://www.wikidata.org/w/api.php";
        $params = [
            'action' => 'wbgetentities',
            'ids'    => $entity_id,
            'format' => 'json',
            'props'  => 'claims',
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['entities'][$entity_id]['claims'] ?? [];
    }

    /**
     * Extract string value from Wikidata claim
     */
    private function extractStringValue($claim): string
    {
        if (empty($claim) || !is_array($claim)) {
            return '';
        }

        $mainsnak = $claim[0]['mainsnak'] ?? null;
        if (!$mainsnak) {
            return '';
        }

        return $mainsnak['datavalue']['value'] ?? '';
    }

    /**
     * Extract date value from Wikidata claim
     */
    private function extractDateValue($claim): string
    {
        if (empty($claim) || !is_array($claim)) {
            return '';
        }

        $mainsnak = $claim[0]['mainsnak'] ?? null;
        if (!$mainsnak) {
            return '';
        }

        $time = $mainsnak['datavalue']['value']['time'] ?? '';
        
        if (empty($time)) {
            return '';
        }

        // Format: +YYYY-MM-DDThh:mm:ssZ
        if (preg_match('/\+(\d{4})-(\d{2})-(\d{2})/', $time, $matches)) {
            return $matches[1]; // Return year only
        }

        return $time;
    }

    /**
     * Extract quantity value (number) from Wikidata claim
     */
    private function extractQuantityValue($claim): ?int
    {
        if (empty($claim) || !is_array($claim)) {
            return null;
        }

        $mainsnak = $claim[0]['mainsnak'] ?? null;
        if (!$mainsnak) {
            return null;
        }

        $amount = $mainsnak['datavalue']['value']['amount'] ?? '';
        if ($amount === '') {
            return null;
        }

        // Remove leading +
        return (int) ltrim($amount, '+');
    }

    /**
     * Extract label from Wikidata entity reference
     */
    private function extractLabelValue($claim, string $lang): string
    {
        if (empty($claim) || !is_array($claim)) {
            return '';
        }

        $mainsnak = $claim[0]['mainsnak'] ?? null;
        if (!$mainsnak) {
            return '';
        }

        $entity_id = $mainsnak['datavalue']['value']['id'] ?? '';
        if (empty($entity_id)) {
            return '';
        }

        return $this->getWikidataLabel($entity_id, $lang);
    }

    /**
     * Extract multiple labels from Wikidata claim (for lists)
     */
    private function extractMultipleLabels($claim, string $lang): array
    {
        if (empty($claim) || !is_array($claim)) {
            return [];
        }

        $labels = [];
        foreach ($claim as $item) {
            $mainsnak = $item['mainsnak'] ?? null;
            if (!$mainsnak) {
                continue;
            }

            $entity_id = $mainsnak['datavalue']['value']['id'] ?? '';
            if (empty($entity_id)) {
                continue;
            }

            $label = $this->getWikidataLabel($entity_id, $lang);
            if (!empty($label) && !in_array($label, $labels)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Extract image filename from Wikidata
     */
    private function extractImageValue($claim): string
    {
        $filename = $this->extractStringValue($claim);
        if (empty($filename)) {
            return '';
        }

        // Convert filename to Commons URL
        return $this->getCommonsImageUrl($filename);
    }

    /**
     * Get image URL from Wikimedia Commons
     */
    private function getCommonsImageUrl(string $filename): string
    {
        $url = "https://commons.wikimedia.org/w/api.php";
        $params = [
            'action' => 'query',
            'titles' => "File:{$filename}",
            'prop'   => 'imageinfo',
            'iiprop' => 'url',
            'format' => 'json',
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $pages = $body['query']['pages'] ?? [];

        foreach ($pages as $page) {
            if (!empty($page['imageinfo'][0]['url'])) {
                return $page['imageinfo'][0]['url'];
            }
        }

        return '';
    }

    /**
     * Get label for Wikidata entity
     */
    private function getWikidataLabel(string $entity_id, string $lang): string
    {
        $url = "https://www.wikidata.org/w/api.php";
        $params = [
            'action'    => 'wbgetentities',
            'ids'       => $entity_id,
            'format'    => 'json',
            'props'     => 'labels',
            'languages' => $lang . '|en',
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $labels = $body['entities'][$entity_id]['labels'] ?? [];

        // Try requested language first, then English
        return $labels[$lang]['value'] ?? $labels['en']['value'] ?? '';
    }

    // ═══════════════════════════════════════════════════════════
    // EXISTING METHODS (UNCHANGED)
    // ═══════════════════════════════════════════════════════════

    public function search(string $name, string $lang = null)
    {
        $lang = $lang ?? $this->lang;
        
        $url = "https://{$lang}.wikipedia.org/w/api.php";
        $params = [
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $name,
            'srlimit'  => 1,
            'format'   => 'json',
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $results = $body['query']['search'] ?? [];

        if (empty($results)) {
            return null;
        }

        return [
            'title' => $results[0]['title'],
            'lang'  => $lang,
            'url'   => "https://{$lang}.wikipedia.org/wiki/" . urlencode(str_replace(' ', '_', $results[0]['title'])),
        ];
    }

    public function getDetails(string $title, string $lang = null)
    {
        $lang = $lang ?? $this->lang;
        
        $url = "https://{$lang}.wikipedia.org/w/api.php";
        $params = [
            'action'     => 'query',
            'titles'     => $title,
            'prop'       => 'extracts|pageimages|info',
            'exintro'    => 1,
            'explaintext'=> 1,
            'piprop'     => 'thumbnail',
            'pithumbsize'=> 800,
            'inprop'     => 'url',
            'format'     => 'json',
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $pages = $body['query']['pages'] ?? [];

        foreach ($pages as $page) {
            return [
                'excerpt'   => $page['extract'] ?? '',
                'thumbnail' => $page['thumbnail']['source'] ?? '',
                'fullurl'   => $page['fullurl'] ?? '',
            ];
        }

        return null;
    }

    public function getWikidataInfo(string $title, string $lang = null)
    {
        $lang = $lang ?? $this->lang;
        $entity_id = $this->getWikidataEntityId($title, $lang);
        
        if (!$entity_id) {
            return [];
        }

        $claims = $this->getWikidataClaims($entity_id);
        if (empty($claims)) {
            return [];
        }

        return [
            'opened'             => $this->extractDateValue($claims[self::WIKIDATA_PROPS['inception']] ?? null),
            'height'             => $this->extractQuantityValue($claims[self::WIKIDATA_PROPS['height']] ?? null),
            'architect'          => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['architect']] ?? null, $lang),
            'style'              => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['style']] ?? null, $lang),
            'owner'              => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['owner']] ?? null, $lang),
            'floors'             => $this->extractQuantityValue($claims[self::WIKIDATA_PROPS['floors']] ?? null),
            'former_names'       => $this->extractMultipleLabels($claims[self::WIKIDATA_PROPS['former_names']] ?? null, $lang),
            'structural_system'  => $this->extractLabelValue($claims[self::WIKIDATA_PROPS['structural_system']] ?? null, $lang),
        ];
    }

    private function getWikidataEntityId(string $title, string $lang): string
    {
        $url = "https://{$lang}.wikipedia.org/w/api.php";
        $params = [
            'action' => 'query',
            'titles' => $title,
            'prop'   => 'pageprops',
            'format' => 'json',
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $pages = $body['query']['pages'] ?? [];

        foreach ($pages as $page) {
            return $page['pageprops']['wikibase_item'] ?? '';
        }

        return '';
    }
}