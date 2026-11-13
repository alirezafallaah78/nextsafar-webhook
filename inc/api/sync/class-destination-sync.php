<?php
/**
 * DestinationSync — Synchronize tourist destinations
 *
 * Features:
 * - Destination post creation
 * - Wikipedia integration (Persian + English)
 * - Place enrichment from Google Maps
 * - Entry fee mapping from price level
 *
 * @package NextSafar\Api\Sync
 * @since 1.0.0
 */

namespace NextSafar\Api\Sync;

use NextSafar\Api\Clients\WikipediaClient;
use NextSafar\Sync\GeoSync;
use NextSafar\Sync\Traits\PlaceEnrichTrait;

if (!defined('ABSPATH')) {
    exit;
}

class DestinationSync extends BaseSync
{
    use PlaceEnrichTrait;

    protected string $post_type = 'destination';

    protected function getSearchMethod(): string
    {
        return 'searchDestinations';
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

        // Save geo data
        $this->saveGeoMeta($post_id, $data);

        // Save destination-specific fields
        $this->saveDestinationFields($post_id, $data);

        // Featured image
        $this->saveFeaturedImage($post_id, $data);

        return $result['action'];
    }

    /* ========================================================================
       Save Destination-Specific Fields
       ======================================================================== */

    protected function saveDestinationFields(int $post_id, array $data): void
    {
        // Type mapping
        $type_mapping = [
            'museum'        => ['museum', 'gallery', 'exhibition'],
            'park'          => ['park', 'garden', 'green'],
            'monument'      => ['monument', 'statue', 'memorial'],
            'historical'    => ['historical', 'historic', 'heritage'],
            'religious'     => ['mosque', 'church', 'temple', 'shrine'],
            'beach'         => ['beach', 'coast', 'sea'],
            'mountain'      => ['mountain', 'peak', 'hill'],
            'market'        => ['market', 'bazaar', 'souq'],
        ];

        $mapped_type = $this->mapType($data['type'] ?? '', $type_mapping);
        if ($mapped_type) {
            update_post_meta($post_id, '_destination_type', sanitize_text_field($mapped_type));
        }

        // Entry fee from price level
        if (!empty($data['price_level'])) {
            $fee = $this->convertPriceLevel($data['price_level']);
            if ($fee) {
                update_post_meta($post_id, '_destination_entry_fee', sanitize_text_field($fee));
            }
        }

        // Rating and reviews
        if (!empty($data['rating'])) {
            update_post_meta($post_id, '_destination_rating', (float) $data['rating']);
        }
        if (!empty($data['reviews_count'])) {
            update_post_meta($post_id, '_destination_reviews', (int) $data['reviews_count']);
        }

        // ✅ NEW: Save facilities from extensions (Google Maps)
        if (!empty($data['extensions']) && is_array($data['extensions'])) {
            $facilities = implode("\n", array_filter(array_map(function ($ext) {
                return is_string($ext) ? trim($ext) : '';
            }, $data['extensions'])));
            
            if (!empty($facilities)) {
                update_post_meta($post_id, '_destination_facilities', wp_kses_post($facilities));
            }
        }

        // Enrich address
        $this->enrichAddressFromSearchApi(
            $post_id,
            $data['name'] ?? '',
            $data['city'] ?? '',
            $data['country'] ?? '',
            '_destination_'
        );

        // ✅ NEW: Save working hours from enrichment
        $work_time = get_post_meta($post_id, '_destination_work_time', true);
        if (!empty($work_time) && is_array($work_time)) {
            // Already saved by enrichment
            error_log("✅ [DestinationSync] Working hours saved for #{$post_id}");
        }

        // Wikipedia integration
        $this->enrichWithWikipedia($post_id, $data['name_en'] ?? ($data['name'] ?? ''));

        GeoSync::syncToCustomTable($post_id);
    }

    /* ========================================================================
       Wikipedia Integration
       ======================================================================== */

    /**
     * Enrich post with Wikipedia data (Persian first, then English)
     */
    protected function enrichWithWikipedia(int $post_id, string $name): void
    {
        if (empty($name) || get_post_meta($post_id, '_wikipedia_attempted', true)) {
            return;
        }

        update_post_meta($post_id, '_wikipedia_attempted', '1');

        try {
            $wiki_fa = new \NextSafar\Api\Clients\WikipediaClient('fa');
            $info = $wiki_fa->getPlaceInfo($name);

            if (empty($info) || empty($info['url'])) {
                $wiki_en = new \NextSafar\Api\Clients\WikipediaClient('en');
                $info = $wiki_en->getPlaceInfo($name);
            }

            if (empty($info)) {
                return;
            }

            // Save Wikipedia URL
            if (!empty($info['url'])) {
                update_post_meta($post_id, '_destination_wikipedia_url', esc_url($info['url']));
            }

            // ✅ NEW: Save Wikidata structural data
            if (!empty($info['wikidata']) && is_array($info['wikidata'])) {
                $wikidata = $info['wikidata'];
                
                $field_mapping = [
                    'opened'            => 'destination_Opened',
                    'height'            => 'destination_Height',
                    'architect'         => 'destination_Architect',
                    'style'             => 'destination_Architectural_styles',
                    'owner'             => 'destination_Owner',
                    'floors'            => 'destination_Floor_count',
                    'former_names'      => 'destination_Former_names',
                    'structural_system' => 'destination_Structural_system',
                ];

                foreach ($field_mapping as $wd_key => $meta_key) {
                    if (!empty($wikidata[$wd_key])) {
                        update_post_meta($post_id, '_' . $meta_key, sanitize_text_field($wikidata[$wd_key]));
                    }
                }
            }

            // Use excerpt as content
            $current_post = get_post($post_id);
            if ($current_post && empty($current_post->post_content) && !empty($info['excerpt'])) {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => wp_kses_post(substr($info['excerpt'], 0, 2000)),
                ]);
            }

            // Wikipedia thumbnail
            if (!empty($info['thumbnail']) && !has_post_thumbnail($post_id)) {
                \NextSafar\Api\Services\ImageManager::setFeaturedImage($post_id, $info['thumbnail'], 'wikipedia');
            }

            error_log("✅ [Wikipedia] Enriched #{$post_id}: {$name}");
        } catch (\Throwable $e) {
            error_log("⚠️ [Wikipedia] Failed: " . $e->getMessage());
        }
    }
}