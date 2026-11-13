<?php
/**
 * NewsBatchSync — سیستم سینک دستی با Batch Processing
 *
 * برای استفاده از صفحه "تولید دستی" با Terminal UI
 *
 * ویژگی‌ها:
 * - Lock system برای جلوگیری از اجرای همزمان
 * - Batch processing (5 خبر در هر batch)
 * - Progress tracking
 * - Stale lock detection
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class NewsBatchSync
{
    public const BATCH_SIZE = 5;
    public const STATE_KEY = 'nextsafar_news_batch_state';
    public const LOCK_KEY = 'ns_news_batch_lock';
    public const LOCK_TTL = 1800; // 30 دقیقه
    public const STALE_LOCK_AGE = 900; // 15 دقیقه

    /* ========================================================================
       Start Sync
       ======================================================================== */
    
    /**
     * شروع یک سینک جدید
     *
     * @param string $mode حالت: 'quick' (بدون AI) یا 'ai' (با AI)
     * @return array نتیجه
     */
    public static function startSync(string $mode = 'quick'): array
    {
        // بررسی lock
        $lockTime = get_transient(self::LOCK_KEY);
        if ($lockTime !== false) {
            $age = time() - (int) $lockTime;
            if ($age > self::STALE_LOCK_AGE) {
                error_log("🔓 [BatchSync] Stale lock cleared (age {$age}s)");
                delete_transient(self::LOCK_KEY);
            } else {
                return [
                    'success' => false,
                    'message' => 'یک همگام‌سازی در حال اجرا است. لطفاً صبر کنید.',
                ];
            }
        }

        set_transient(self::LOCK_KEY, time(), self::LOCK_TTL);

        // Release lock on fatal error
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                delete_transient(self::LOCK_KEY);
                error_log('🔓 [BatchSync] Lock released after fatal error');
            }
        });

        try {
            $rssFetcher = new RSSFetcher();
            $apiFetcher = new NewsApiFetcher();
            $duplicateChecker = new DuplicateChecker();
            $newsFilter = new NewsFilter();

            $window = max(1, (int) get_option('nextsafar_news_fetch_window_hours', 12));
            $aiOn = ($mode === 'ai') && get_option('nextsafar_news_ai_enabled', '0') === '1';

            // ═══ Stage 1: Fetch RSS ═══
            $rssItems = [];
            try {
                $rssItems = (array) $rssFetcher->fetchAll($window);
            } catch (\Throwable $e) {
                error_log('⚠️ [BatchSync] RSS fetch failed: ' . $e->getMessage());
            }

            // ═══ Stage 2: Fetch API ═══
            $apiItems = [];
            try {
                $apiItems = (array) $apiFetcher->fetchAll();
            } catch (\Throwable $e) {
                error_log('⚠️ [BatchSync] API fetch failed: ' . $e->getMessage());
            }

            // ═══ Stage 3: Merge + time window ═══
            $all = array_merge($rssItems, $apiItems);
            $cutoff = time() - $window * HOUR_IN_SECONDS;

            $all = array_values(array_filter($all, function ($item) use ($cutoff) {
                $ts = strtotime($item['pub_date'] ?? '');
                return !$ts || $ts >= $cutoff;
            }));

            $stats = [
                'rss'           => count($rssItems),
                'api'           => count($apiItems),
                'total_fetched' => count($all),
                'duplicates'    => 0,
                'filtered'      => 0,
            ];

            if (empty($all)) {
                delete_transient(self::LOCK_KEY);
                return [
                    'success' => true,
                    'total'   => 0,
                    'message' => 'هیچ خبر جدیدی یافت نشد.',
                    'stats'   => $stats,
                ];
            }

            // ═══ Stage 4: Dedup ═══
            $unique = $all;
            try {
                $unique = $duplicateChecker->filterDuplicates($all);
            } catch (\Throwable $e) {
                error_log('⚠️ [BatchSync] Duplicate check failed: ' . $e->getMessage());
            }
            $stats['duplicates'] = count($all) - count($unique);

            // ═══ Stage 5: Keyword filter ═══
            $passed = [];
            try {
                $passed = $newsFilter->filterItems($unique);
            } catch (\Throwable $e) {
                error_log('⚠️ [BatchSync] Filter failed: ' . $e->getMessage());
                $passed = $unique;
            }
            $stats['filtered'] = count($unique) - count($passed);

            if (empty($passed)) {
                delete_transient(self::LOCK_KEY);
                return [
                    'success' => true,
                    'total'   => 0,
                    'message' => 'همه اخبار فیلتر شدند.',
                    'stats'   => $stats,
                ];
            }

            // Sort by score
            usort($passed, function ($a, $b) {
                return ($b['_filter_result']['score'] ?? 0) <=> ($a['_filter_result']['score'] ?? 0);
            });

            // Save state
            $state = [
                'mode'          => $mode,
                'ai_on'         => $aiOn,
                'all_items'     => array_values($passed),
                'total'         => count($passed),
                'processed'     => 0,
                'published'     => 0,
                'drafts'        => 0,
                'failed'        => 0,
                'skipped'       => 0,
                'ai_used'       => 0,
                'current_batch' => 0,
                'status'        => 'running',
                'started_at'    => current_time('mysql'),
                'stats'         => $stats,
            ];

            update_option(self::STATE_KEY, $state, false);

            return [
                'success'       => true,
                'total'         => count($passed),
                'batch_size'    => self::BATCH_SIZE,
                'total_batches' => (int) ceil(count($passed) / self::BATCH_SIZE),
                'stats'         => $stats,
                'mode'          => $mode,
                'ai_on'         => $aiOn,
            ];
        } catch (\Throwable $e) {
            delete_transient(self::LOCK_KEY);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ========================================================================
       Process Next Batch
       ======================================================================== */
    
    /**
     * پردازش batch بعدی
     */
    public static function processNextBatch(): array
    {
        $state = get_option(self::STATE_KEY);

        if (!$state || ($state['status'] ?? '') !== 'running') {
            delete_transient(self::LOCK_KEY);
            return ['completed' => true, 'message' => 'سینک فعالی وجود ندارد'];
        }

        $total = $state['total'];
        $currentBatch = $state['current_batch'];
        $batchStart = $currentBatch * self::BATCH_SIZE;
        $batchEnd = min($batchStart + self::BATCH_SIZE, $total);

        $items = array_slice($state['all_items'], $batchStart, self::BATCH_SIZE);

        if (empty($items)) {
            return self::finishSync($state);
        }

        $aiRewriter = new AIRewriter();
        $aiOn = $state['ai_on'];
        $duplicateChecker = new DuplicateChecker();

        $batchDetails = [];

        foreach ($items as $index => $item) {
            $title = mb_substr($item['title'] ?? 'News ' . ($batchStart + $index + 1), 0, 45);

            $detail = [
                'index'  => $batchStart + $index + 1,
                'name'   => $title,
                'status' => 'processing',
                'source' => $item['source_name'] ?? '',
            ];

            try {
                $result = self::saveNewsItem($item, $aiOn, $aiRewriter, $duplicateChecker);
                $detail['status'] = $result['status'];

                switch ($result['status']) {
                    case 'published':
                        $state['published']++;
                        if ($result['ai_used']) {
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
                error_log('❌ [BatchSync] Save failed: ' . $e->getMessage());
            }

            $batchDetails[] = $detail;
        }

        $state['processed'] = $batchEnd;
        $state['current_batch']++;

        update_option(self::STATE_KEY, $state, false);

        $isCompleted = $batchEnd >= $total;

        if ($isCompleted) {
            self::finishSync($state);
        }

        return [
            'completed'        => $isCompleted,
            'processed'        => $batchEnd,
            'total'            => $total,
            'progress_percent' => $total > 0 ? (int) round(($batchEnd / $total) * 100) : 0,
            'published'        => $state['published'],
            'drafts'           => $state['drafts'],
            'failed'           => $state['failed'],
            'skipped'          => $state['skipped'],
            'ai_used'          => $state['ai_used'],
            'current_batch'    => $state['current_batch'],
            'total_batches'    => (int) ceil($total / self::BATCH_SIZE),
            'batch_details'    => $batchDetails,
        ];
    }

    /* ========================================================================
       Save Single News Item
       ======================================================================== */
    
    private static function saveNewsItem(array $item, bool $aiOn, AIRewriter $aiRewriter, DuplicateChecker $duplicateChecker): array
    {
        // بررسی تکراری
        if ($duplicateChecker->isDuplicate($item)) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        $processed = null;
        $aiOk = false;

        if ($aiOn) {
            try {
                $processed = $aiRewriter->process($item);
                $aiOk = !empty($processed['ai_used']);
            } catch (\Throwable $e) {
                error_log('⚠️ [BatchSync] AI rewrite failed: ' . $e->getMessage());
            }
        }

        $title = $processed['title'] ?? ($item['title'] ?? '');
        $content = $processed['content'] ?? ($item['content'] ?? '');
        $excerpt = $processed['excerpt'] ?? ($item['excerpt'] ?? '');

        if (empty($title) || empty(trim(wp_strip_all_tags($content)))) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        // پاک کردن تصاویر از محتوا
        $content = \NextSafar\Api\Services\ImageManager::stripImagesFromContent($content);

        $slug = sanitize_title($title);
        if (self::slugExists($slug)) {
            $slug .= '-' . time();
        }

        // AI موفق = publish، در غیر این صورت = draft
        $postStatus = ($aiOk || !$aiOn) ? 'publish' : 'draft';

        $postId = wp_insert_post([
            'post_type'    => 'travelnews',
            'post_title'   => wp_strip_all_tags($title),
            'post_content' => wp_kses_post($content),
            'post_excerpt' => sanitize_textarea_field($excerpt),
            'post_status'  => $postStatus,
            'post_author'  => 1,
            'post_date'    => $item['pub_date'] ?? current_time('mysql'),
            'post_name'    => $slug,
        ]);

        if (is_wp_error($postId)) {
            return ['status' => 'failed', 'ai_used' => false];
        }

        // Meta
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

        // Featured image
        $featured = $item['image'] ?? '';
        if (!empty($featured)) {
            \NextSafar\Api\Services\ImageManager::setFeaturedImage($postId, $featured, $item['fetch_type'] ?? 'api');
        }

        // Mark as saved
        $duplicateChecker->markAsSaved($item, $postId);

        // AI log
        if ($processed) {
            $aiRewriter->logFinal($postId, $processed);
        }

        return [
            'status'  => $postStatus === 'publish' ? 'published' : 'draft',
            'ai_used' => $aiOk,
        ];
    }

    /* ========================================================================
       Finish Sync
       ======================================================================== */
    
    private static function finishSync(array $state): array
    {
        $state['status'] = 'completed';
        $state['completed_at'] = current_time('mysql');

        update_option(self::STATE_KEY, $state, false);
        delete_transient(self::LOCK_KEY);

        return [
            'completed'        => true,
            'processed'        => $state['total'],
            'total'            => $state['total'],
            'progress_percent' => 100,
            'published'        => $state['published'],
            'drafts'           => $state['drafts'],
            'failed'           => $state['failed'],
            'skipped'          => $state['skipped'],
            'ai_used'          => $state['ai_used'],
            'current_batch'    => $state['current_batch'],
            'total_batches'    => (int) ceil($state['total'] / self::BATCH_SIZE),
        ];
    }

    /* ========================================================================
       Status & Helpers
       ======================================================================== */
    
    public static function getStatus(): array
    {
        $state = get_option(self::STATE_KEY);
        if (!$state) {
            return ['status' => 'idle'];
        }

        return [
            'status'           => $state['status'],
            'mode'             => $state['mode'] ?? 'quick',
            'total'            => $state['total'],
            'processed'        => $state['processed'],
            'published'        => $state['published'],
            'drafts'           => $state['drafts'],
            'failed'           => $state['failed'],
            'skipped'          => $state['skipped'],
            'ai_used'          => $state['ai_used'],
            'progress_percent' => $state['total'] > 0
                ? (int) round(($state['processed'] / $state['total']) * 100)
                : 0,
            'started_at'       => $state['started_at'] ?? '',
        ];
    }

    public static function cancelSync(): bool
    {
        $state = get_option(self::STATE_KEY);
        if ($state) {
            $state['status'] = 'cancelled';
            update_option(self::STATE_KEY, $state, false);
            delete_transient(self::LOCK_KEY);
        }
        return true;
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