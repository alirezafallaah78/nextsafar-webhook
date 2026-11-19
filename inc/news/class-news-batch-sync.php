<?php
/**
 * NewsBatchSync — سینک دستی دو فازی (Fetch → Process)
 *
 * - فاز دریافت در پس‌زمینه (cron) اجرا می‌شود تا AJAX بلاک نشود
 * - قفل اتمیک + heartbeat
 * - payload هر آیتم در transient جداگانه (state سبک می‌ماند)
 * - اندازه batch در حالت AI کوچک‌تر است تا timeout نشود
 *
 * @package NextSafar\News
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class NewsBatchSync
{
    public const BATCH_SIZE = 5;
    public const AI_BATCH_SIZE = 2;      // ✅ در حالت AI کوچک‌تر
    public const STATE_KEY = 'nextsafar_news_batch_state';
    public const LOCK_KEY = 'ns_news_batch_lock';
    public const LOCK_TTL = 1800;
    public const STALE_LOCK_AGE = 900;
    public const ITEM_TTL = 7200;
    public const FETCH_HOOK = 'nextsafar_news_batch_fetch';

    /* ========================================================================
       Helpers
    ======================================================================== */

    private static function makeRunId(): string
    {
        return time() . '_' . wp_rand(1000, 9999);
    }

    private static function itemKey(string $run_id, int $index): string
    {
        return 'ns_ni_' . $run_id . '_' . $index;
    }

        private static function rawKey(string $run_id, int $index): string
    {
        return 'ns_nraw_' . $run_id . '_' . $index;
    }

    /** ✅ صف منابع: هر RSS فعال + هر پروایدر API که کلید دارد */
    private static function buildSourceQueue(): array
    {
        $queue = [];

        try {
            $rss = new RSSFetcher();
            foreach ($rss->getSources() as $src) {
                $queue[] = [
                    'type' => 'rss',
                    'id'   => (int) ($src['id'] ?? 0),
                    'name' => (string) ($src['name'] ?? 'RSS'),
                ];
            }
        } catch (\Throwable $e) {
            error_log('⚠️ [NewsBatchSync] RSS sources: ' . $e->getMessage());
        }

        try {
            $api = new NewsApiFetcher();
            foreach ($api->getProviders() as $p) {
                if (!empty($p['has_key'])) {
                    $queue[] = [
                        'type' => 'api',
                        'key'  => (string) ($p['key'] ?? ''),
                        'name' => (string) ($p['name'] ?? 'API'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('⚠️ [NewsBatchSync] API providers: ' . $e->getMessage());
        }

        return $queue;
    }

    private static function fetchProgress(array $state, string $name = '', int $count = 0, $error = null): array
    {
        $cursor = (int) ($state['source_cursor'] ?? 0);
        $total  = (int) ($state['source_total'] ?? 0);

        return [
            'phase'            => 'fetching',
            'source_cursor'    => $cursor,
            'source_total'     => $total,
            'source_name'      => $name !== '' ? $name : (string) ($state['last_source'] ?? ''),
            'source_count'     => $count,
            'source_error'     => $error,
            'progress_percent' => $total > 0 ? (int) round(($cursor / $total) * 100) : 0,
            'stats'            => $state['stats'] ?? [],
        ];
    }

    /** ✅ اندازه batch بر اساس روشن بودن AI */
    private static function batchSize(array $state): int
    {
        return !empty($state['ai_on']) ? self::AI_BATCH_SIZE : self::BATCH_SIZE;
    }

    private static function resolveAuthorId(): int
    {
        $uid = get_current_user_id();
        if ($uid > 0) {
            return $uid;
        }
        static $admin = null;
        if ($admin === null) {
            $admins = get_users([
                'role'    => 'administrator',
                'number'  => 1,
                'fields'  => 'ID',
                'orderby' => 'ID',
                'order'   => 'ASC',
            ]);
            $admin = !empty($admins) ? (int) $admins[0] : 0;
        }
        return $admin;
    }

    private static function resolvePostDate(string $raw): string
    {
        $ts  = $raw ? strtotime($raw) : false;
        $now = current_time('timestamp');
        if (!$ts || $ts > $now) {
            return current_time('mysql');
        }
        return wp_date('Y-m-d H:i:s', $ts);
    }

    /* ========================================================================
       Start Sync — فقط صف‌بندی می‌کند و فوراً برمی‌گردد
    ======================================================================== */

        public static function startSync(string $mode = 'quick'): array
    {
        if (!nextsafar_acquire_lock(self::LOCK_KEY, self::LOCK_TTL)) {
            return [
                'success' => false,
                'message' => 'یک همگام‌سازی در حال اجرا است. لطفاً صبر کنید.',
            ];
        }

        register_shutdown_function(static function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                nextsafar_release_lock(self::LOCK_KEY);
            }
        });

        $queue = self::buildSourceQueue();

        $state = [
            'run_id'         => self::makeRunId(),
            'mode'           => $mode,
            'ai_on'          => false,
            'phase'          => 'fetching',
            'sources'        => $queue,
            'source_cursor'  => 0,
            'source_total'   => count($queue),
            'source_results' => [],
            'last_source'    => '',
            'total'          => 0,
            'processed'      => 0,
            'published'      => 0,
            'drafts'         => 0,
            'failed'         => 0,
            'skipped'        => 0,
            'ai_used'        => 0,
            'current_batch'  => 0,
            'status'         => 'running',
            'started_at'     => current_time('mysql'),
            'stats'          => ['rss' => 0, 'api' => 0, 'total_fetched' => 0, 'duplicates' => 0, 'filtered' => 0],
        ];
        update_option(self::STATE_KEY, $state, false);

        if (empty($queue)) {
            $state['status']       = 'completed';
            $state['phase']        = 'completed';
            $state['completed_at'] = current_time('mysql');
            update_option(self::STATE_KEY, $state, false);
            nextsafar_release_lock(self::LOCK_KEY);

            return [
                'success' => true,
                'phase'   => 'completed',
                'total'   => 0,
                'message' => 'هیچ منبع فعالی یافت نشد (RSS فعال یا کلید API).',
            ];
        }

        // ✅ fallback پس‌زمینه اگر تب بسته شد
        if (!wp_next_scheduled(self::FETCH_HOOK)) {
            wp_schedule_single_event(time() + 3, self::FETCH_HOOK);
        }

        return [
            'success'      => true,
            'phase'        => 'fetching',
            'source_total' => count($queue),
            'total'        => 0,
            'message'      => 'در حال دریافت از ' . count($queue) . ' منبع…',
        ];
    }

    /* ========================================================================
       Fetch Phase — در پس‌زمینه اجرا می‌شود
    ======================================================================== */

    /** ✅ هر فراخوانی = فقط یک منبع */
    public static function runFetchStep(): array
    {
        $state = get_option(self::STATE_KEY);
        if (!$state || ($state['status'] ?? '') !== 'running') {
            nextsafar_release_lock(self::LOCK_KEY);
            return ['phase' => 'idle', 'message' => 'سینک فعالی وجود ندارد'];
        }

        if (($state['phase'] ?? 'processing') !== 'fetching') {
            return self::fetchProgress($state);
        }

        if (!nextsafar_acquire_lock('ns_news_fetch_step', 120)) {
            return self::fetchProgress($state); // یک step دیگر در جریان است
        }

        nextsafar_refresh_lock(self::LOCK_KEY, self::LOCK_TTL);
        @set_time_limit(120);

        $cursor  = (int) ($state['source_cursor'] ?? 0);
        $sources = (array) ($state['sources'] ?? []);
        $total   = count($sources);
        $runId   = (string) ($state['run_id'] ?? '');
        $window  = max(1, (int) get_option('nextsafar_news_time_window_hours', 24));

        if ($cursor >= $total) {
            return self::finalizeFetch($state);
        }

        $src   = $sources[$cursor];
        $name  = (string) ($src['name'] ?? '—');
        $items = [];
        $error = null;

        try {
            if (($src['type'] ?? '') === 'rss') {
                $fetcher = new RSSFetcher();
                $r       = $fetcher->fetchSourceById((int) ($src['id'] ?? 0), $window);
                $items   = (array) ($r['items'] ?? []);
                $error   = $r['error'] ?? null;
                $name    = (string) ($r['name'] ?? $name);
            } else {
                $fetcher = new NewsApiFetcher();
                $r       = $fetcher->fetchProvider((string) ($src['key'] ?? ''));
                $items   = (array) ($r['items'] ?? []);
                $name    = (string) ($r['name'] ?? $name);
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            error_log('❌ [NewsBatchSync] Source failed (' . $name . '): ' . $error);
        }

        // ✅ نتیجه خام هر منبع جداگانه ذخیره می‌شود (state سبک می‌ماند)
        set_transient(self::rawKey($runId, $cursor), $items, self::ITEM_TTL);

        $state['source_cursor']    = $cursor + 1;
        $state['last_source']      = $name;
        $state['source_results'][] = [
            'name'  => $name,
            'count' => count($items),
            'error' => $error ? mb_substr((string) $error, 0, 120) : null,
        ];

        $bucket = (($src['type'] ?? '') === 'rss') ? 'rss' : 'api';
        $state['stats'][$bucket] = (int) ($state['stats'][$bucket] ?? 0) + count($items);
        $state['stats']['total_fetched'] = (int) ($state['stats']['total_fetched'] ?? 0) + count($items);

        update_option(self::STATE_KEY, $state, false);
        nextsafar_release_lock('ns_news_fetch_step');

        if ($state['source_cursor'] >= $total) {
            return self::finalizeFetch($state);
        }

        return self::fetchProgress($state, $name, count($items), $error);
    }

    /** ✅ بعد از آخرین منبع: ادغام + فیلتر + ساخت صف پردازش */
    private static function finalizeFetch(array $state): array
    {
        $runId  = (string) ($state['run_id'] ?? '');
        $window = max(1, (int) get_option('nextsafar_news_time_window_hours', 24));
        $mode   = $state['mode'] ?? 'quick';
        $aiOn   = ($mode === 'ai') && get_option('nextsafar_news_ai_enabled', '0') === '1';

        $all = [];
        $n   = (int) ($state['source_total'] ?? 0);
        for ($i = 0; $i < $n; $i++) {
            $chunk = get_transient(self::rawKey($runId, $i));
            if (is_array($chunk) && !empty($chunk)) {
                $all = array_merge($all, $chunk);
            }
            delete_transient(self::rawKey($runId, $i));
        }

        $cutoff = time() - $window * HOUR_IN_SECONDS;
        $all    = array_values(array_filter($all, static function ($item) use ($cutoff) {
            $ts = strtotime($item['pub_date'] ?? '');
            return !$ts || $ts >= $cutoff;
        }));

        $stats                 = $state['stats'] ?? [];
        $stats['total_fetched'] = count($all);
        $stats['duplicates']    = 0;
        $stats['filtered']      = 0;

        $passed = [];
        if (!empty($all)) {
            $unique = $all;
            try {
                $dc     = new DuplicateChecker();
                $unique = $dc->filterDuplicates($all);
            } catch (\Throwable $e) {
                error_log('⚠️ [NewsBatchSync] Dedup failed: ' . $e->getMessage());
            }
            $stats['duplicates'] = count($all) - count($unique);

            try {
                $filter = new NewsFilter();
                $passed = $filter->filterItems($unique);
            } catch (\Throwable $e) {
                error_log('⚠️ [NewsBatchSync] Filter failed: ' . $e->getMessage());
                $passed = $unique;
            }
            $stats['filtered'] = count($unique) - count($passed);
        }

        usort($passed, static function ($a, $b) {
            return ($b['_filter_result']['score'] ?? 0) <=> ($a['_filter_result']['score'] ?? 0);
        });
        $passed = array_values($passed);

        foreach ($passed as $i => $item) {
            set_transient(self::itemKey($runId, $i), $item, self::ITEM_TTL);
        }

        $state['ai_on']         = $aiOn;
        $state['stats']         = $stats;
        $state['total']         = count($passed);
        $state['processed']     = 0;
        $state['current_batch'] = 0;
        $state['phase']         = 'processing';

        if (empty($passed)) {
            $state['status']       = 'completed';
            $state['phase']        = 'completed';
            $state['completed_at'] = current_time('mysql');
        }

        update_option(self::STATE_KEY, $state, false);
        nextsafar_release_lock('ns_news_fetch_step');

        if (empty($passed)) {
            nextsafar_release_lock(self::LOCK_KEY);
            return [
                'phase'   => 'completed',
                'total'   => 0,
                'stats'   => $stats,
                'message' => 'هیچ خبر جدیدی برای پردازش یافت نشد.',
            ];
        }

        $bs = self::batchSize($state);
        return [
            'phase'          => 'processing',
            'total'          => count($passed),
            'batch_size'     => $bs,
            'total_batches'  => (int) ceil(count($passed) / $bs),
            'stats'          => $stats,
            'ai_on'          => $aiOn,
            'source_results' => $state['source_results'] ?? [],
        ];
    }

    /** ✅ fallback پس‌زمینه: یک منبع در هر اجرای cron */
    public static function cronFetchStep(): void
    {
        $r = self::runFetchStep();
        if (($r['phase'] ?? '') === 'fetching') {
            wp_schedule_single_event(time() + 5, self::FETCH_HOOK);
        }
    }

    public static function getFetchStatus(): array
    {
        $state = get_option(self::STATE_KEY);
        if (!$state) {
            return ['phase' => 'idle'];
        }

        $phase = $state['phase'] ?? 'processing';
        if (($state['status'] ?? '') === 'completed') {
            $phase = 'completed';
        } elseif (($state['status'] ?? '') === 'failed') {
            $phase = 'failed';
        }

        $total = (int) ($state['total'] ?? 0);
        $bs    = self::batchSize($state);

        return [
            'phase'          => $phase,
            'total'          => $total,
            'stats'          => $state['stats'] ?? [],
            'ai_on'          => !empty($state['ai_on']),
            'batch_size'     => $bs,
            'total_batches'  => $total > 0 ? (int) ceil($total / $bs) : 0,
            'source_cursor'  => (int) ($state['source_cursor'] ?? 0),
            'source_total'   => (int) ($state['source_total'] ?? 0),
            'source_results' => $state['source_results'] ?? [],
            'message'        => $state['error'] ?? '',
        ];
    }

    /* ========================================================================
       Process Next Batch
    ======================================================================== */

    public static function processNextBatch(): array
    {
        $state = get_option(self::STATE_KEY);
        if (!$state || ($state['status'] ?? '') !== 'running') {
            nextsafar_release_lock(self::LOCK_KEY);
            return ['completed' => true, 'message' => 'سینک فعالی وجود ندارد'];
        }

        // ✅ هنوز در فاز دریافت است
        if (($state['phase'] ?? 'processing') === 'fetching') {
            return ['completed' => false, 'phase' => 'fetching', 'progress_percent' => 0];
        }

        nextsafar_refresh_lock(self::LOCK_KEY, self::LOCK_TTL);

        $total        = (int) $state['total'];
        $bs           = self::batchSize($state);
        $currentBatch = (int) $state['current_batch'];
        $batchStart   = $currentBatch * $bs;

        if ($batchStart >= $total) {
            return self::finishSync($state);
        }

        $batchEnd = min($batchStart + $bs, $total);
        $runId    = $state['run_id'] ?? '';

        $aiRewriter       = new AIRewriter();
        $aiOn             = !empty($state['ai_on']);
        $duplicateChecker = new DuplicateChecker();
        $batchDetails     = [];

        for ($i = $batchStart; $i < $batchEnd; $i++) {
            $item = $runId ? get_transient(self::itemKey($runId, $i)) : false;
            if (false === $item && isset($state['all_items'][$i])) {
                $item = $state['all_items'][$i];
            }

            if (!is_array($item) || empty($item)) {
                $state['failed']++;
                $batchDetails[] = [
                    'index'  => $i + 1,
                    'name'   => '—',
                    'status' => 'failed',
                    'source' => '',
                ];
                continue;
            }

            $detail = [
                'index'  => $i + 1,
                'name'   => mb_substr($item['title'] ?? ('News ' . ($i + 1)), 0, 45),
                'status' => 'processing',
                'source' => $item['source_name'] ?? '',
            ];

            try {
                $result = self::saveNewsItem($item, $aiOn, $aiRewriter, $duplicateChecker);
                $detail['status'] = $result['status'];

                switch ($result['status']) {
                    case 'published':
                        $state['published']++;
                        if (!empty($result['ai_used'])) {
                            $state['ai_used']++;
                        }
                        break;
                    case 'draft':
                        $state['drafts']++;
                        break;
                    case 'skipped':
                        $state['skipped']++;
                        break;
                    case 'failed':
                        $state['failed']++;
                        break;
                }
            } catch (\Throwable $e) {
                $state['failed']++;
                $detail['status'] = 'failed';
                error_log('❌ [NewsBatchSync] Save failed: ' . $e->getMessage());
            }

            if ($runId) {
                delete_transient(self::itemKey($runId, $i));
            }

            $batchDetails[] = $detail;
        }

        $state['processed']     = $batchEnd;
        $state['current_batch'] = $currentBatch + 1;
        unset($state['all_items']);
        update_option(self::STATE_KEY, $state, false);

        $isCompleted = $batchEnd >= $total;
        if ($isCompleted) {
            self::finishSync($state);
        }

        return [
            'completed'        => $isCompleted,
            'phase'            => 'processing',
            'processed'        => $batchEnd,
            'total'            => $total,
            'progress_percent' => $total > 0 ? (int) round(($batchEnd / $total) * 100) : 0,
            'published'        => $state['published'],
            'drafts'           => $state['drafts'],
            'failed'           => $state['failed'],
            'skipped'          => $state['skipped'],
            'ai_used'          => $state['ai_used'],
            'current_batch'    => $state['current_batch'],
            'total_batches'    => (int) ceil($total / $bs),
            'batch_details'    => $batchDetails,
        ];
    }

    /* ========================================================================
       Save Single News Item
    ======================================================================== */

    private static function saveNewsItem(array $item, bool $aiOn, AIRewriter $aiRewriter, DuplicateChecker $duplicateChecker): array
    {
        if ($duplicateChecker->isDuplicate($item)) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        $processed = null;
        $aiOk      = false;

        if ($aiOn) {
            try {
                $processed = $aiRewriter->process($item);
                $aiOk      = !empty($processed['ai_used']);
            } catch (\Throwable $e) {
                error_log('⚠️ [NewsBatchSync] AI rewrite failed: ' . $e->getMessage());
            }
        }

        $title   = $processed['title'] ?? ($item['title'] ?? '');
        $content = $processed['content'] ?? ($item['content'] ?? '');
        $excerpt = $processed['excerpt'] ?? ($item['excerpt'] ?? '');

        if (empty($title) || empty(trim(wp_strip_all_tags($content)))) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        $content = \NextSafar\Api\Services\ImageManager::stripImagesFromContent($content);

        $slug = sanitize_title($title);
        if (self::slugExists($slug)) {
            $slug .= '-' . time();
        }

        $postStatus = ($aiOk || !$aiOn) ? 'publish' : 'draft';

        $postId = wp_insert_post([
            'post_type'    => 'travelnews',
            'post_title'   => wp_strip_all_tags($title),
            'post_content' => wp_kses_post($content),
            'post_excerpt' => sanitize_textarea_field($excerpt),
            'post_status'  => $postStatus,
            'post_author'  => self::resolveAuthorId(),
            'post_date'    => self::resolvePostDate($item['pub_date'] ?? ''),
            'post_name'    => $slug,
        ]);

        if (is_wp_error($postId)) {
            return ['status' => 'failed', 'ai_used' => false];
        }

        update_post_meta($postId, '_ns_source_url', esc_url_raw($item['link'] ?? ''));
        update_post_meta($postId, '_ns_source_name', sanitize_text_field($item['source_name'] ?? ''));
        update_post_meta($postId, '_ns_source_group', sanitize_text_field($item['source_group'] ?? ''));
        update_post_meta($postId, '_ns_original_guid', sanitize_text_field($item['guid'] ?? ''));
        update_post_meta($postId, '_ns_fetch_type', sanitize_text_field($item['fetch_type'] ?? 'unknown'));
        update_post_meta($postId, '_ns_ai_used', $aiOk ? '1' : '0');
        update_post_meta($postId, '_ns_fetched_at', current_time('mysql'));

        if ($postStatus === 'draft') {
            update_post_meta($postId, '_ns_needs_rewrite', '1');
            update_post_meta($postId, '_ns_original_content', wp_kses_post($item['content'] ?? ''));
            update_post_meta($postId, '_ns_original_title', sanitize_text_field($item['title'] ?? ''));
        }

        $featured = $item['image'] ?? '';
        if (!empty($featured)) {
            \NextSafar\Api\Services\ImageManager::setFeaturedImage($postId, $featured, $item['fetch_type'] ?? 'api');
        }

        $duplicateChecker->markAsSaved($item, $postId);

        if ($processed) {
            $aiRewriter->logFinal($postId, $processed);
        }

        return [
            'status'  => $postStatus === 'publish' ? 'published' : 'draft',
            'ai_used' => $aiOk,
        ];
    }

    /* ========================================================================
       Finish / Status / Cancel
    ======================================================================== */

    private static function finishSync(array $state): array
    {
        $bs = self::batchSize($state);

        $state['status']       = 'completed';
        $state['phase']        = 'completed';
        $state['completed_at'] = current_time('mysql');
        unset($state['all_items']);
        update_option(self::STATE_KEY, $state, false);

        nextsafar_release_lock(self::LOCK_KEY);

        return [
            'completed'        => true,
            'phase'            => 'completed',
            'processed'        => $state['total'],
            'total'            => $state['total'],
            'progress_percent' => 100,
            'published'        => $state['published'],
            'drafts'           => $state['drafts'],
            'failed'           => $state['failed'],
            'skipped'          => $state['skipped'],
            'ai_used'          => $state['ai_used'],
            'current_batch'    => $state['current_batch'],
            'total_batches'    => (int) ceil(max(1, (int) $state['total']) / $bs),
        ];
    }

    public static function getStatus(): array
    {
        $state = get_option(self::STATE_KEY);
        if (!$state) {
            return ['status' => 'idle'];
        }

        $total = (int) ($state['total'] ?? 0);

        return [
            'status'           => $state['status'],
            'phase'            => $state['phase'] ?? 'processing',
            'mode'             => $state['mode'] ?? 'quick',
            'total'            => $total,
            'processed'        => (int) ($state['processed'] ?? 0),
            'published'        => (int) ($state['published'] ?? 0),
            'drafts'           => (int) ($state['drafts'] ?? 0),
            'failed'           => (int) ($state['failed'] ?? 0),
            'skipped'          => (int) ($state['skipped'] ?? 0),
            'ai_used'          => (int) ($state['ai_used'] ?? 0),
            'progress_percent' => $total > 0 ? (int) round(((int) $state['processed'] / $total) * 100) : 0,
            'started_at'       => $state['started_at'] ?? '',
        ];
    }

    public static function cancelSync(): bool
    {
        $state = get_option(self::STATE_KEY);
        if ($state) {
            $runId = (string) ($state['run_id'] ?? '');
            $n     = (int) ($state['source_total'] ?? 0);
            for ($i = 0; $i < $n; $i++) {
                delete_transient(self::rawKey($runId, $i));
            }
            $state['status'] = 'cancelled';
            update_option(self::STATE_KEY, $state, false);
        }
        wp_clear_scheduled_hook(self::FETCH_HOOK);
        nextsafar_release_lock('ns_news_fetch_step');
        nextsafar_release_lock(self::LOCK_KEY);
        return true;
    }

    public static function ajaxFetchStep(): void
    {
        self::ajaxGuard();
        wp_send_json_success(self::runFetchStep());
    }

    public static function ajaxFetchStatus(): void
    {
        self::ajaxGuard();
        wp_send_json_success(self::getFetchStatus());
    }

    private static function ajaxGuard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'nextsafar_news_admin')) {
            wp_send_json_error(['message' => 'نشست منقضی شده است؛ صفحه را رفرش کنید.']);
        }
    }
    
    private static function slugExists(string $slug): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'travelnews' LIMIT 1",
            $slug
        ));
    }
}

// ✅ ثبت cron فاز دریافت + AJAXهای مربوطه
add_action('nextsafar_news_batch_fetch', ['NextSafar\News\NewsBatchSync', 'cronFetchStep']);
add_action('wp_ajax_nextsafar_news_fetch_step', ['NextSafar\News\NewsBatchSync', 'ajaxFetchStep']);
add_action('wp_ajax_nextsafar_news_fetch_status', ['NextSafar\News\NewsBatchSync', 'ajaxFetchStatus']);