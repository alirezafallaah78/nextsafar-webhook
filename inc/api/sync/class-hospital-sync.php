<?php
/**
 * HospitalSync — Synchronize hospitals from Google Maps
 *
 * Features:
 * - Hospital type detection (general, specialized, clinic)
 * - Emergency service detection (24/7, trauma)
 * - Departments mapping
 * - Working hours enrichment
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

class HospitalSync extends BaseSync
{
    use PlaceEnrichTrait;

    protected string $post_type = 'hospital';

    protected function getSearchMethod(): string
    {
        return 'searchHospitals';
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
        $this->saveHospitalFields($post_id, $data);
        $this->saveFeaturedImage($post_id, $data);

        return $result['action'];
    }

    /* ========================================================================
       Save Hospital-Specific Fields
       ======================================================================== */

    protected function saveHospitalFields(int $post_id, array $data): void
    {
        // Hospital type
        if (!empty($data['type'])) {
            update_post_meta($post_id, '_hospital_type', sanitize_text_field($data['type']));
        }

        // Emergency detection
        $is_emergency = $this->detectEmergency($data);
        update_post_meta($post_id, '_hospital_emergency', $is_emergency ? 'yes' : 'no');

        // ✅ NEW: If emergency, set emergency_phone as same as phone
        if ($is_emergency && !empty($data['phone'])) {
            update_post_meta($post_id, '_hospital_emergency_phone', sanitize_text_field($data['phone']));
        }

        // Rating
        if (!empty($data['rating'])) {
            update_post_meta($post_id, '_hospital_rating', (float) $data['rating']);
        }
        if (!empty($data['reviews_count'])) {
            update_post_meta($post_id, '_hospital_reviews', (int) $data['reviews_count']);
        }

        // Contact info
        if (!empty($data['phone'])) {
            update_post_meta($post_id, '_hospital_phone', sanitize_text_field($data['phone']));
        }
        if (!empty($data['website'])) {
            update_post_meta($post_id, '_hospital_website', esc_url_raw($data['website']));
        }

        // ✅ NEW: Save facilities from extensions
        if (!empty($data['extensions']) && is_array($data['extensions'])) {
            $this->saveHospitalFacilities($post_id, $data['extensions']);
        }

        // Enrich address
        $this->enrichAddressFromSearchApi(
            $post_id,
            $data['name'] ?? '',
            $data['city'] ?? '',
            $data['country'] ?? '',
            '_hospital_'
        );

        // Re-check emergency based on working hours
        $work_time = get_post_meta($post_id, '_hospital_work_time', true);
        if (is_array($work_time) && $this->isOpen247($work_time)) {
            update_post_meta($post_id, '_hospital_emergency', 'yes');
            if (!empty($data['phone'])) {
                update_post_meta($post_id, '_hospital_emergency_phone', sanitize_text_field($data['phone']));
            }
        }
        $this->enrichWithWikidata($post_id, $data);

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
    if (get_post_meta($post_id, '_hospital_wikidata_attempted', true)) {
        return;
    }

    update_post_meta($post_id, '_hospital_wikidata_attempted', '1');

    try {
        $wiki_client = new \NextSafar\Api\Clients\WikipediaClient('fa');
        $info = $wiki_client->getHospitalInfo($name);

        // Fallback to English if Persian fails
        if (empty($info) || empty($info['wikidata'])) {
            $wiki_client_en = new \NextSafar\Api\Clients\WikipediaClient('en');
            $info = $wiki_client_en->getHospitalInfo($name);
        }

        if (empty($info) || empty($info['wikidata'])) {
            error_log("⚠️ [HospitalSync] No Wikidata for: {$name}");
            return;
        }

        $wikidata = $info['wikidata'];

        // Map to metabox fields
        if (!empty($wikidata['opened'])) {
            update_post_meta($post_id, '_hospital_opened', sanitize_text_field($wikidata['opened']));
        }

        if (!empty($wikidata['bed_count'])) {
            $existing = get_post_meta($post_id, '_hospital_number_of_bed', true);
            if (empty($existing)) {
                update_post_meta($post_id, '_hospital_number_of_bed', (int) $wikidata['bed_count']);
            }
        }

        if (!empty($wikidata['employees'])) {
            update_post_meta($post_id, '_hospital_employees', (int) $wikidata['employees']);
        }

        if (!empty($wikidata['specializations']) && is_array($wikidata['specializations'])) {
            update_post_meta($post_id, '_hospital_specializations', implode("\n", $wikidata['specializations']));
        }

        if (!empty($wikidata['affiliation'])) {
            update_post_meta($post_id, '_hospital_affiliation', sanitize_text_field($wikidata['affiliation']));
        }

        if (!empty($wikidata['founded_by'])) {
            update_post_meta($post_id, '_hospital_founded_by', sanitize_text_field($wikidata['founded_by']));
        }

        if (!empty($wikidata['hospital_type'])) {
            $existing = get_post_meta($post_id, '_hospital_type', true);
            if (empty($existing)) {
                update_post_meta($post_id, '_hospital_type', sanitize_text_field($wikidata['hospital_type']));
            }
        }

        // Wikipedia URL
        if (!empty($info['fullurl'])) {
            update_post_meta($post_id, '_hospital_wikipedia_url', esc_url($info['fullurl']));
        }

        // Content from excerpt
        if (!empty($info['excerpt'])) {
            $current = get_post($post_id);
            if ($current && empty($current->post_content)) {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => wp_kses_post(substr($info['excerpt'], 0, 3000)),
                ]);
            }
        }

        // Image from Wikipedia or Wikidata
        $image_url = $info['thumbnail'] ?? $wikidata['image'] ?? '';
        if (!empty($image_url) && !has_post_thumbnail($post_id)) {
            \NextSafar\Api\Services\ImageManager::setFeaturedImage($post_id, $image_url, 'wikipedia');
        }

        error_log("✅ [HospitalSync] Wikidata enriched for #{$post_id}");

    } catch (\Throwable $e) {
        error_log("⚠️ [HospitalSync] Wikidata failed: " . $e->getMessage());
    }
}

    /**
     * Save hospital facilities and services from extensions
     */
    protected function saveHospitalFacilities(int $post_id, array $extensions): void
    {
        $extensions_lower = array_map('strtolower', array_filter(array_map(function ($e) {
            return is_string($e) ? $e : '';
        }, $extensions)));
        
        $extensions_string = implode(' ', $extensions_lower);

        // Build facilities and services lists
        $facilities_keywords = [
            'parking', 'wheelchair', 'accessible', 'pharmacy',
            'cafeteria', 'atm', 'wifi', 'restroom', 'prayer room',
        ];
        
        $services_keywords = [
            'emergency', '24 hour', 'icu', 'surgery', 'x-ray',
            'mri', 'ct scan', 'laboratory', 'blood bank',
            'ambulance', 'dialysis', 'rehabilitation',
        ];

        $facilities = [];
        foreach ($facilities_keywords as $keyword) {
            if (strpos($extensions_string, $keyword) !== false) {
                $facilities[] = ucfirst($keyword);
            }
        }

        $services = [];
        foreach ($services_keywords as $keyword) {
            if (strpos($extensions_string, $keyword) !== false) {
                $services[] = ucfirst($keyword);
            }
        }

        if (!empty($facilities)) {
            update_post_meta($post_id, '_hospital_facilities', implode("\n", $facilities));
        }
        if (!empty($services)) {
            update_post_meta($post_id, '_hospital_services', implode("\n", $services));
        }

        // ✅ NEW: Accessibility detection
        if (strpos($extensions_string, 'wheelchair') !== false || 
            strpos($extensions_string, 'accessible') !== false) {
            update_post_meta($post_id, '_hospital_accessibility', '♿ قابل دسترسی با ویلچر');
        }

        error_log("✅ [HospitalSync] Facilities: " . count($facilities) . 
                ", Services: " . count($services) . " for #{$post_id}");
    }

    /* ========================================================================
       Emergency Detection
       ======================================================================== */

    /**
     * Detect if hospital has emergency services
     */
    protected function detectEmergency(array $data): bool
    {
        $name = strtolower($data['name'] ?? '');
        $type = strtolower($data['type'] ?? '');
        $desc = strtolower($data['description'] ?? '');
        $combined = "{$name} {$type} {$desc}";

        $emergency_keywords = [
            'emergency', '24 hour', '24/7', '24-hour',
            'trauma', 'urgent care',
            'اورژانس', 'شبانه‌روزی', '۲۴ ساعته',
        ];

        foreach ($emergency_keywords as $keyword) {
            if (strpos($combined, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if hospital is open 24/7 based on working hours
     */
    protected function isOpen247(array $work_time): bool
    {
        foreach ($work_time as $day => $slots) {
            if (!is_array($slots)) {
                continue;
            }

            foreach ($slots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $from = $slot['from'] ?? '';
                $to = $slot['to'] ?? '';

                if ($from === '24h' || $to === '24h') {
                    return true;
                }

                if (stripos($from . $to, '24') !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}