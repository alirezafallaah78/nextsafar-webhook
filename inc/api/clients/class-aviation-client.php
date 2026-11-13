<?php
/**
 * AviationClient — Fetch aviation data from free APIs
 *
 * Features:
 * - ICAO code lookup via airport name/IATA
 * - Airlines serving an airport
 * - Runway information
 * - Airport details (elevation, operator)
 *
 * APIs Used:
 * - OpenSky Network (free, no key needed for basic)
 * - AviationStack (optional, requires key)
 * - OurAirports (free CSV data)
 *
 * @package NextSafar\Api\Clients
 * @since 1.0.0
 */

namespace NextSafar\Api\Clients;

if (!defined('ABSPATH')) {
    exit;
}

class AviationClient
{
    private int $timeout = 20;
    private string $aviation_stack_key = '';

    public function __construct(string $aviation_stack_key = '')
    {
        $this->aviation_stack_key = $aviation_stack_key;
    }

    /**
     * Get full airport info from multiple sources
     */
    public function getAirportInfo(string $identifier): ?array
    {
        if (empty($identifier)) {
            return null;
        }

        $info = [];

        // 1. Try OpenSky Network (free)
        $opensky = $this->getFromOpenSky($identifier);
        if ($opensky) {
            $info = array_merge($info, $opensky);
        }

        // 2. Try OurAirports database (cached locally)
        $ourairports = $this->getFromOurAirports($identifier);
        if ($ourairports) {
            $info = array_merge($info, $ourairports);
        }

        // 3. If AviationStack key available, use it
        if (!empty($this->aviation_stack_key)) {
            $aviation_stack = $this->getFromAviationStack($identifier);
            if ($aviation_stack) {
                $info = array_merge($info, $aviation_stack);
            }
        }

        return !empty($info) ? $info : null;
    }

    /**
     * Get airlines serving an airport
     */
    public function getAirlines(string $iata_code): array
    {
        if (empty($iata_code) || strlen($iata_code) !== 3) {
            return [];
        }

        $cache_key = 'nextsafar_airlines_' . strtoupper($iata_code);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        $airlines = [];

        // Method 1: OpenSky Network
        $opensky_airlines = $this->getAirlinesFromOpenSky($iata_code);
        $airlines = array_merge($airlines, $opensky_airlines);

        // Method 2: AviationStack (if key available)
        if (!empty($this->aviation_stack_key)) {
            $aviation_airlines = $this->getAirlinesFromAviationStack($iata_code);
            $airlines = array_merge($airlines, $aviation_airlines);
        }

        // Remove duplicates
        $airlines = array_unique($airlines);
        sort($airlines);

        // Cache for 7 days
        set_transient($cache_key, $airlines, WEEK_IN_SECONDS);

        error_log("✅ [Aviation] Found " . count($airlines) . " airlines for {$iata_code}");
        return $airlines;
    }

    // ═══════════════════════════════════════════════════════════
    // OPENSKY NETWORK
    // ═══════════════════════════════════════════════════════════

    private function getFromOpenSky(string $identifier): array
    {
        // Normalize to ICAO if needed
        $icao = $this->toIcao($identifier);
        if (empty($icao)) {
            return [];
        }

        $url = "https://opensky-network.org/api/flights/arrival";
        $params = [
            'airport' => $icao,
            'begin'   => time() - 86400 * 7,  // Last 7 days
            'end'     => time(),
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body)) {
            return [];
        }

        // Extract unique airlines from flights
        $airlines = [];
        foreach ($body as $flight) {
            $callsign = $flight['callsign'] ?? '';
            if (!empty($callsign)) {
                // Extract airline code from callsign (e.g., "IRA123" → "IRA")
                if (preg_match('/^([A-Z]{2,3})/', trim($callsign), $matches)) {
                    $airlines[] = $matches[1];
                }
            }
        }

        return [
            'airlines_observed' => array_unique($airlines),
            'flight_count'      => count($body),
            'source'            => 'opensky',
        ];
    }

    private function getAirlinesFromOpenSky(string $iata_code): array
    {
        $icao = $this->iataToIcao($iata_code);
        if (empty($icao)) {
            return [];
        }

        $info = $this->getFromOpenSky($icao);
        return $info['airlines_observed'] ?? [];
    }

    // ═══════════════════════════════════════════════════════════
    // OURAIRPORTS DATABASE
    // ═══════════════════════════════════════════════════════════

    /**
     * Get info from OurAirports (cached CSV data)
     * We download and cache locally to avoid repeated requests
     */
    private function getFromOurAirports(string $identifier): array
    {
        $cache_key = 'nextsafar_ourairports_data';
        $data = get_transient($cache_key);

        if ($data === false) {
            $data = $this->downloadOurAirportsData();
            if (!empty($data)) {
                // Cache for 30 days
                set_transient($cache_key, $data, MONTH_IN_SECONDS);
            }
        }

        if (empty($data)) {
            return [];
        }

        // Search by IATA or ICAO
        $identifier = strtoupper($identifier);
        $found = null;

        foreach ($data as $airport) {
            if (strtoupper($airport['iata_code'] ?? '') === $identifier ||
                strtoupper($airport['ident'] ?? '') === $identifier) {
                $found = $airport;
                break;
            }
        }

        if (!$found) {
            return [];
        }

        return [
            'icao'             => $found['ident'] ?? '',
            'iata'             => $found['iata_code'] ?? '',
            'name'             => $found['name'] ?? '',
            'type'             => $found['type'] ?? '',
            'elevation_ft'     => (int) ($found['elevation_ft'] ?? 0),
            'latitude'         => (float) ($found['latitude_deg'] ?? 0),
            'longitude'        => (float) ($found['longitude_deg'] ?? 0),
            'municipality'     => $found['municipality'] ?? '',
            'country'          => $found['iso_country'] ?? '',
            'region'           => $found['iso_region'] ?? '',
            'wikipedia_link'   => $found['wikipedia_link'] ?? '',
            'home_link'        => $found['home_link'] ?? '',
            'runway_count'     => $this->countRunwaysFromOurAirports($found['ident'] ?? ''),
            'source'           => 'ourairports',
        ];
    }

    /**
     * Download OurAirports data CSVs
     */
    private function downloadOurAirportsData(): array
    {
        $url = 'https://davidmegginson.github.io/ourairports-data/airports.csv';
        
        $response = wp_remote_get($url, [
            'timeout' => 60,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            error_log("⚠️ [Aviation] OurAirports download failed: " . $response->get_error_message());
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return [];
        }

        return $this->parseCsv($body);
    }

    /**
     * Parse CSV string into array
     */
    private function parseCsv(string $csv): array
    {
        $lines = explode("\n", $csv);
        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        return $data;
    }

    /**
     * Count runways for an airport from OurAirports
     */
    private function countRunwaysFromOurAirports(string $icao): int
    {
        $cache_key = 'nextsafar_ourairports_runways';
        $runways = get_transient($cache_key);

        if ($runways === false) {
            $url = 'https://davidmegginson.github.io/ourairports-data/runways.csv';
            $response = wp_remote_get($url, [
                'timeout' => 60,
                'sslverify' => false,
            ]);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $runways = $this->parseCsv($body);
                set_transient($cache_key, $runways, MONTH_IN_SECONDS);
            } else {
                return 0;
            }
        }

        if (empty($runways)) {
            return 0;
        }

        $count = 0;
        foreach ($runways as $runway) {
            if (strtoupper($runway['airport_ident'] ?? '') === $icao) {
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════
    // AVIATIONSTACK (OPTIONAL - REQUIRES API KEY)
    // ═══════════════════════════════════════════════════════════

    private function getFromAviationStack(string $identifier): array
    {
        if (empty($this->aviation_stack_key)) {
            return [];
        }

        $params = [
            'access_key' => $this->aviation_stack_key,
        ];

        // Determine if identifier is IATA (3 letters) or ICAO (4 letters)
        if (strlen($identifier) === 3) {
            $params['iata_code'] = $identifier;
        } else {
            $params['icao_code'] = $identifier;
        }

        $url = 'http://api.aviationstack.com/v1/flights';
        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $data = $body['data'] ?? [];

        if (empty($data)) {
            return [];
        }

        // Extract airport info from first flight
        $flight = $data[0];
        $arrival = $flight['arrival'] ?? [];

        return [
            'iata'         => $arrival['iata'] ?? '',
            'icao'         => $arrival['icao'] ?? '',
            'name'         => $arrival['airport'] ?? '',
            'timezone'     => $arrival['timezone'] ?? '',
            'terminal'     => $arrival['terminal'] ?? '',
            'source'       => 'aviationstack',
        ];
    }

    private function getAirlinesFromAviationStack(string $iata_code): array
    {
        if (empty($this->aviation_stack_key)) {
            return [];
        }

        $url = 'http://api.aviationstack.com/v1/flights';
        $params = [
            'access_key' => $this->aviation_stack_key,
            'arrival_iata' => $iata_code,
            'limit' => 100,
        ];

        $response = wp_remote_get($url . '?' . http_build_query($params), [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $flights = $body['data'] ?? [];

        $airlines = [];
        foreach ($flights as $flight) {
            $airline = $flight['airline']['name'] ?? '';
            if (!empty($airline) && !in_array($airline, $airlines)) {
                $airlines[] = $airline;
            }
        }

        return $airlines;
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * Convert any identifier to ICAO
     */
    private function toIcao(string $identifier): string
    {
        if (strlen($identifier) === 4 && ctype_alpha($identifier)) {
            return strtoupper($identifier);
        }

        if (strlen($identifier) === 3) {
            return $this->iataToIcao($identifier);
        }

        return '';
    }

    /**
     * Convert IATA to ICAO using OurAirports data
     */
    private function iataToIcao(string $iata): string
    {
        $info = $this->getFromOurAirports($iata);
        return $info['icao'] ?? '';
    }
}