<?php
/**
 * BatchSync — Orchestrates batch processing of sync operations
 *
 * Features:
 * - Atomic lock (prevents concurrent sync)
 * - AJAX-based batch processing (prevents PHP timeout)
 * - Lightweight progress state (payloads stored per-item)
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
    public const STALE_LOCK_AGE = 900; // 15 minutes (برای سازگاری نگه داشته شده)
    public const ITEM_TTL = 7200; // 2 hours — عمر payload هر آیتم

    /* ========================================================================
       Start Sync
    ======================================================================== */

    public static function startSync(string $sync_type, string $location, string $source, int $total_limit): array
    {
        $lock_key = 'ns_sync_lock_' . $sync_type;

        // ✅ قفل اتمیک — قفل منقضی‌شده خودکار آزاد می‌شود
        if (!nextsafar_acquire_lock($lock_key, self::LOCK_TTL)) {
            return [
                'success' => false,
                'message' => 'یک سینک از این نوع در حال اجرا است. لطفاً صبر کنید.',
            ];
        }

        // Release lock on fatal error
        register_shutdown_function(function () use ($lock_key) {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                nextsafar_release_lock($lock_key);
                error_log("🔓 [BatchSync] Lock released after fatal: {$lock_key}");
            }
        });

        $allowed_types = ['hotel', 'destination', 'restaurant', 'hospital', 'airport'];
        if (!in_array($sync_type, $allowed_types, true)) {
            nextsafar_release_lock($lock_key);
            return ['success' => false, 'message' => 'نوع محتوا معتبر نیست'];
        }

        $client = self::getClient($source);
        if (!$client) {
            nextsafar_release_lock($lock_key);
            return ['success' => false, 'message' => 'کلید API تنظیم نشده است'];
        }

        try {
            $all_items = self::fetchAllItems($sync_type, $client, $location, min(10, max(1, $total_limit)));
        } catch (\Throwable $e) {
            nextsafar_release_lock($lock_key);
            return ['success' => false, 'message' => 'خطا در دریافت لیست: ' . $e->getMessage()];
        }

        if (is_wp_error($all_items)) {
            nextsafar_release_lock($lock_key);
            return ['success' => false, 'message' => $all_items->get_error_message()];
        }

        if (empty($all_items)) {
            nextsafar_release_lock($lock_key);
            return ['success' => false, 'message' => 'هیچ آیتمی یافت نشد'];
        }

        // ✅ payload سنگین هر آیتم جداگانه ذخیره می‌شود (نه داخل state)
        $all_items = array_values($all_items);
        $run_id    = self::makeRunId();
        foreach ($all_items as $i => $item) {
            set_transient(self::itemKey($run_id, $i), $item, self::ITEM_TTL);
        }

        $state = [
            'run_id'        => $run_id,
            'sync_type'     => $sync_type,
            'source'        => $source,
            'location'      => $location,
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

    public static function processNextBatch(): array
    {
        $state = get_option(self::SYNC_STATE_KEY);
        if (!$state || ($state['status'] ?? '') !== 'running') {
            if (!empty($state['lock_key'])) {
                nextsafar_release_lock($state['lock_key']);
            }
            return ['completed' => true, 'message' => 'سینک فعالی وجود ندارد'];
        }

        // ✅ تمدید قفل در هر مرحله
        if (!empty($state['lock_key'])) {
            nextsafar_refresh_lock($state['lock_key'], self::LOCK_TTL);
        }

        $total         = (int) $state['total'];
        $current_batch = (int) $state['current_batch'];
        $batch_start   = $current_batch * self::BATCH_SIZE;

        if ($batch_start >= $total) {
            return self::finishSync($state);
        }

        $batch_end     = min($batch_start + self::BATCH_SIZE, $total);
        $run_id        = $state['run_id'] ?? '';
        $sync_instance = self::getSyncInstance($state['sync_type'], $state['source']);
        $batch_details = [];

        // ✅ فقط آیتم‌های همین batch خوانده می‌شوند
        for ($i = $batch_start; $i < $batch_end; $i++) {
            $item = $run_id ? get_transient(self::itemKey($run_id, $i)) : false;
            if (false === $item && isset($state['all_items'][$i])) {
                $item = $state['all_items'][$i]; // سازگاری با state قدیمی
            }

            if (!is_array($item) || empty($item)) {
                $state['failed']++;
                $batch_details[] = [
                    'index'  => $i + 1,
                    'name'   => '—',
                    'status' => 'failed',
                    'error'  => 'آیتم منقضی شده است',
                ];
                continue;
            }

            $item_name = $item['name'] ?? $item['title'] ?? ('Item ' . ($i + 1));
            $detail = [
                'index'  => $i + 1,
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
                $detail['error']  = $e->getMessage();
                error_log('❌ [BatchSync] Error: ' . $e->getMessage());
            }

            if ($run_id) {
                delete_transient(self::itemKey($run_id, $i)); // ✅ آیتم مصرف‌شده پاک شود
            }

            $batch_details[] = $detail;
        }

        $state['processed']     = $batch_end;
        $state['current_batch'] = $current_batch + 1;
        unset($state['all_items']);
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
        $state['status']       = 'completed';
        $state['completed_at'] = current_time('mysql');
        unset($state['all_items']);
        update_option(self::SYNC_STATE_KEY, $state, false);

        self::logSyncComplete($state);

        if (!empty($state['lock_key'])) {
            nextsafar_release_lock($state['lock_key']);
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

    protected static function getClient(string $source): ?object
    {
        if ($source === 'serpapi') {
            $key = (string) get_option('nextsafar_serpapi_key', '');
            return $key !== '' ? new \NextSafar\Api\Clients\SerpApiClient($key) : null;
        }
        $key = (string) get_option('nextsafar_searchapi_key', '');
        return $key !== '' ? new \NextSafar\Api\Clients\SearchApiClient($key) : null;
    }

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
       Helpers
    ======================================================================== */

    protected static function makeRunId(): string
    {
        return time() . '_' . wp_rand(1000, 9999);
    }

    protected static function itemKey(string $run_id, int $index): string
    {
        return 'ns_si_' . $run_id . '_' . $index;
    }

    /* ========================================================================
       Logging
    ======================================================================== */

    protected static function logSyncStart(string $sync_type, string $source, int $total): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'api_sync_log';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
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