<?php
/**
 * BatchSync — Orchestrates batch processing of sync operations
 *
 * Features:
 * - Prevents concurrent sync (with stale lock detection)
 * - AJAX-based batch processing (prevents PHP timeout)
 * - Progress tracking via WordPress options
 * - Detailed logging to database table
 * - Support for all 5 post types
 *
 * @package NextSafar\Api\Sync
 * @since 1.0.0
 */

namespace NextSafar\Api\Sync;

if (!defined('ABSPATH')) {
    exit;
}

class BatchSync
{
    public const BATCH_SIZE = 2;
    public const SYNC_STATE_KEY = 'nextsafar_sync_state';
    public const LOCK_TTL = 1800; // 30 minutes
    public const STALE_LOCK_AGE = 900; // 15 minutes

    /* ========================================================================
       Start Sync
       ======================================================================== */

    /**
     * Start a new sync operation
     *
     * @param string $sync_type   Post type (hotel, destination, restaurant, hospital, airport)
     * @param string $location    Location to search
     * @param string $source      Provider (serpapi, searchapi)
     * @param int    $total_limit Max items to sync (1-10)
     * @return array Result with success status and metadata
     */
    public static function startSync(string $sync_type, string $location, string $source, int $total_limit): array
    {
        $lock_key = 'ns_sync_lock_' . $sync_type;

        // Handle stale locks
        $lock_time = get_transient($lock_key);
        if ($lock_time !== false) {
            $age = time() - (int) $lock_time;

            if ($age > self::STALE_LOCK_AGE) {
                error_log("🔓 [BatchSync] Stale lock cleared: {$lock_key} (age {$age}s)");
                delete_transient($lock_key);
            } else {
                return [
                    'success' => false,
                    'message' => 'یک سینک از این نوع در حال اجرا است. لطفاً صبر کنید.',
                ];
            }
        }

        // Set new lock
        set_transient($lock_key, time(), self::LOCK_TTL);

        // Release lock on fatal error
        register_shutdown_function(function () use ($lock_key) {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                delete_transient($lock_key);
                error_log("🔓 [BatchSync] Lock released after fatal: {$lock_key}");
            }
        });

        // Validate sync type
        $allowed_types = ['hotel', 'destination', 'restaurant', 'hospital', 'airport'];
        if (!in_array($sync_type, $allowed_types, true)) {
            delete_transient($lock_key);
            return ['success' => false, 'message' => 'نوع محتوا معتبر نیست'];
        }

        // Get client and fetch items
        $client = self::getClient($source);
        if (!$client) {
            delete_transient($lock_key);
            return ['success' => false, 'message' => 'کلاینت معتبر نیست'];
        }

        try {
            $all_items = self::fetchAllItems($sync_type, $client, $location, min(10, max(1, $total_limit)));
        } catch (\Throwable $e) {
            delete_transient($lock_key);
            return ['success' => false, 'message' => 'خطا در دریافت لیست: ' . $e->getMessage()];
        }

        if (is_wp_error($all_items)) {
            delete_transient($lock_key);
            return ['success' => false, 'message' => $all_items->get_error_message()];
        }

        if (empty($all_items)) {
            delete_transient($lock_key);
            return ['success' => false, 'message' => 'هیچ آیتمی یافت نشد'];
        }

        // Save state
        $state = [
            'sync_type'     => $sync_type,
            'source'        => $source,
            'location'      => $location,
            'all_items'     => $all_items,
            'total'         => count($all_items),
            'processed'     => 0,
            'created'       => 0,
            'updated'       => 0,
            'failed'        => 0,
            'current_batch' => 0,
            'status'        => 'running',
            'started_at'    => current_time('mysql'),
            'lock_key'      => $lock_key,
        ];

        update_option(self::SYNC_STATE_KEY, $state, false);

        self::logSyncStart($sync_type, $source, count($all_items));

        return [
            'success'       => true,
            'total'         => count($all_items),
            'batch_size'    => self::BATCH_SIZE,
            'total_batches' => (int) ceil(count($all_items) / self::BATCH_SIZE),
        ];
    }

    /* ========================================================================
       Process Next Batch
       ======================================================================== */

    /**
     * Process next batch of items
     */
    public static function processNextBatch(): array
    {
        $state = get_option(self::SYNC_STATE_KEY);

        if (!$state || ($state['status'] ?? '') !== 'running') {
            if (!empty($state['lock_key'] ?? '')) {
                delete_transient($state['lock_key']);
            }
            return ['completed' => true, 'message' => 'سینک فعالی وجود ندارد'];
        }

        $total = $state['total'];
        $current_batch = $state['current_batch'];
        $batch_start = $current_batch * self::BATCH_SIZE;

        $items_to_process = array_slice($state['all_items'], $batch_start, self::BATCH_SIZE);

        if (empty($items_to_process)) {
            return self::finishSync($state);
        }

        $sync_instance = self::getSyncInstance($state['sync_type'], $state['source']);
        $batch_details = [];

        foreach ($items_to_process as $index => $item) {
            $item_name = $item['name'] ?? $item['title'] ?? 'Item ' . ($batch_start + $index + 1);

            $detail = [
                'index'  => $batch_start + $index + 1,
                'name'   => mb_substr($item_name, 0, 40),
                'status' => 'processing',
            ];

            try {
                $result = $sync_instance->save($item);

                if ($result === 'created') {
                    $state['created']++;
                    $detail['status'] = 'created';
                } elseif ($result === 'updated') {
                    $state['updated']++;
                    $detail['status'] = 'updated';
                } else {
                    $state['failed']++;
                    $detail['status'] = 'failed';
                }
            } catch (\Throwable $e) {
                $state['failed']++;
                $detail['status'] = 'failed';
                $detail['error'] = $e->getMessage();
                error_log("❌ [BatchSync] Error: " . $e->getMessage());
            }

            $batch_details[] = $detail;
        }

        $batch_end = $batch_start + count($items_to_process);
        $state['processed'] = $batch_end;
        $state['current_batch']++;

        update_option(self::SYNC_STATE_KEY, $state, false);

        $is_completed = $batch_end >= $total;

        if ($is_completed) {
            self::finishSync($state);
        }

        return [
            'completed'        => $is_completed,
            'processed'        => $batch_end,
            'total'            => $total,
            'progress_percent' => $total > 0 ? (int) round(($batch_end / $total) * 100) : 0,
            'created'          => $state['created'],
            'updated'          => $state['updated'],
            'failed'           => $state['failed'],
            'current_batch'    => $state['current_batch'],
            'total_batches'    => (int) ceil($total / self::BATCH_SIZE),
            'batch_details'    => $batch_details,
            'sync_type'        => $state['sync_type'],
        ];
    }

    /* ========================================================================
       Finish Sync
       ======================================================================== */

    protected static function finishSync(array $state): array
    {
        $state['status'] = 'completed';
        $state['completed_at'] = current_time('mysql');

        update_option(self::SYNC_STATE_KEY, $state, false);
        self::logSyncComplete($state);

        if (!empty($state['lock_key'])) {
            delete_transient($state['lock_key']);
        }

        return [
            'completed'        => true,
            'processed'        => $state['total'],
            'total'            => $state['total'],
            'progress_percent' => 100,
            'created'          => $state['created'],
            'updated'          => $state['updated'],
            'failed'           => $state['failed'],
            'current_batch'    => $state['current_batch'],
            'total_batches'    => (int) ceil($state['total'] / self::BATCH_SIZE),
            'sync_type'        => $state['sync_type'],
        ];
    }

    /* ========================================================================
       Factory Methods
       ======================================================================== */

    /**
     * Get API client based on source
     */
    protected static function getClient(string $source): ?object
    {
        if ($source === 'serpapi') {
            $key = (string) get_option('nextsafar_serpapi_key', '');
            return new \NextSafar\Api\Clients\SerpApiClient($key);
        }

        $key = (string) get_option('nextsafar_searchapi_key', '');
        return new \NextSafar\Api\Clients\SearchApiClient($key);
    }

    /**
     * Get sync instance for a post type
     */
    protected static function getSyncInstance(string $sync_type, string $source): object
    {
        return match ($sync_type) {
            'destination' => new DestinationSync($source),
            'restaurant'  => new RestaurantSync($source),
            'hospital'    => new HospitalSync($source),
            'airport'     => new AirportSync($source),
            default       => new HotelSync($source),
        };
    }

    /**
     * Fetch all items from API
     */
    protected static function fetchAllItems(string $sync_type, object $client, string $location, int $limit): array|\WP_Error
    {
        $options = ['limit' => $limit];

        return match ($sync_type) {
            'hotel'       => $client->searchHotels($location, $options),
            'destination' => $client->searchDestinations($location, $options),
            'restaurant'  => $client->searchRestaurants($location, $options),
            'hospital'    => $client->searchHospitals($location, $options),
            'airport'     => $client->searchAirports($location, $options),
            default       => new \WP_Error('invalid_type', 'نوع سینک معتبر نیست'),
        };
    }

    /* ========================================================================
       Logging
       ======================================================================== */

    protected static function logSyncStart(string $sync_type, string $source, int $total): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'api_sync_log';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return; // Table doesn't exist yet
        }

        $wpdb->insert($table, [
            'source'          => $source,
            'entity_type'     => $sync_type,
            'status'          => 'running',
            'records_fetched' => $total,
            'started_at'      => current_time('mysql'),
        ]);
    }

    protected static function logSyncComplete(array $state): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'api_sync_log';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }

        $wpdb->update(
            $table,
            [
                'status'          => ($state['failed'] ?? 0) > 0 ? 'partial' : 'completed',
                'records_created' => $state['created'],
                'records_updated' => $state['updated'],
                'records_failed'  => $state['failed'],
                'completed_at'    => current_time('mysql'),
            ],
            [
                'source'      => $state['source'],
                'entity_type' => $state['sync_type'],
                'started_at'  => $state['started_at'],
            ]
        );
    }
}