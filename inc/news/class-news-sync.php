<?php
/**
 * NewsSync — سیستم خودکار دریافت و پردازش اخبار با Cron
 *
 * ویژگی‌ها:
 * - Cron hourly برای چک کردن زمان‌بندی
 * - اجرای خودکار در ساعات مشخص شده
 * - Fallback برای cron از دست رفته
 * - محدودیت بودجه (max publish/draft/AI)
 * - لاگ کامل در debug.log
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class NewsSync
{
    public const CRON_HOOK = 'nextsafar_news_cron_hook';
    public const HOURLY_HOOK = 'nextsafar_news_hourly_tick';
    public const RETRY_HOOK = 'nextsafar_news_retry_hook';

    public const POST_TYPE = 'travelnews';

    // Option keys
    public const OPT_AUTO_ENABLED = 'nextsafar_news_auto';
    public const OPT_SCHEDULE_HOURS = 'nextsafar_news_sync_hours';
    public const OPT_TIME_WINDOW = 'nextsafar_news_time_window_hours';
    public const OPT_MAX_PUBLISH = 'nextsafar_news_max_publish_per_run';
    public const OPT_MAX_DRAFTS = 'nextsafar_news_max_draft_per_run';
    public const OPT_MAX_AI_CHECKS = 'nextsafar_news_max_ai_review_per_run';
    public const OPT_AI_ENABLED = 'nextsafar_news_ai_enabled';
    
    public const LAST_RUN_KEY = 'nextsafar_news_last_run_hour';

    private RSSFetcher $rssFetcher;
    private NewsApiFetcher $apiFetcher;
    private DuplicateChecker $duplicateChecker;
    private NewsFilter $newsFilter;
    private RelevanceFilter $relevanceFilter;
    private AIRewriter $aiRewriter;

    public function __construct()
    {
        $this->rssFetcher = new RSSFetcher();
        $this->apiFetcher = new NewsApiFetcher();
        $this->duplicateChecker = new DuplicateChecker();
        $this->newsFilter = new NewsFilter();
        $this->relevanceFilter = new RelevanceFilter();
        $this->aiRewriter = new AIRewriter();
    }

    /* ========================================================================
       Init - ثبت cron ها و hooks
       ======================================================================== */
    
    public static function init(): void
    {
        add_action(self::CRON_HOOK, [__CLASS__, 'runCron']);
        add_action(self::HOURLY_HOOK, [__CLASS__, 'runHourlyTick']);
        add_action(self::RETRY_HOOK, [__CLASS__, 'runRetry']);

        // اضافه کردن interval سفارشی (هر 15 دقیقه)
        add_filter('cron_schedules', function ($schedules) {
            $schedules['ns_15min'] = [
                'interval' => 900,
                'display'  => 'هر ۱۵ دقیقه (NextSafar)',
            ];
            return $schedules;
        });

        // schedule در init (هر page load)
        add_action('init', [__CLASS__, 'maybeScheduleCron'], 20);

        // چک کردن cron از دست رفته در admin و frontend
        add_action('admin_init', [__CLASS__, 'checkMissedCron'], 99);
        add_action('wp_loaded', [__CLASS__, 'checkMissedCron'], 99);
    }

    /* ========================================================================
       Schedule Cron
       ======================================================================== */
    
    public static function maybeScheduleCron(): void
    {
        self::scheduleCron();
    }

    public static function scheduleCron(): void
    {
        if (get_option(self::OPT_AUTO_ENABLED, '1') !== '1') {
            self::clearCron();
            return;
        }

        // Hourly tick - هر ساعت چک می‌کند آیا زمان اجرای sync است یا نه
        if (!wp_next_scheduled(self::HOURLY_HOOK)) {
            wp_schedule_event(time() + 60, 'hourly', self::HOURLY_HOOK);
            error_log('📅 [NewsSync] Hourly cron scheduled');
        }

        // Retry cron - هر 15 دقیقه اخبار در حال انتظار را بازنویسی می‌کند
        if (!wp_next_scheduled(self::RETRY_HOOK)) {
            wp_schedule_event(time() + 120, 'ns_15min', self::RETRY_HOOK);
            error_log('📅 [NewsSync] Retry cron scheduled');
        }
    }

    public static function clearCron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::HOURLY_HOOK);
        wp_clear_scheduled_hook(self::RETRY_HOOK);
        delete_transient(self::LAST_RUN_KEY);
        error_log('📅 [NewsSync] همه cron ها پاک شدند');
    }

    /* ========================================================================
       Hourly Tick - هر ساعت اجرا می‌شود
       ======================================================================== */
    
    public static function runHourlyTick(): void
    {
        if (get_option(self::OPT_AUTO_ENABLED, '1') !== '1') {
            return;
        }

        $hours = array_map('intval', (array) get_option(self::OPT_SCHEDULE_HOURS, [8, 14, 20]));
        $nowHour = (int) wp_date('H');

        if (!in_array($nowHour, $hours, true)) {
            return;
        }

        // چک کن آیا این ساعت قبلاً اجرا شده
        $lastRunHour = get_transient(self::LAST_RUN_KEY);
        $currentHourKey = wp_date('Y-m-d-H');

        if ($lastRunHour === $currentHourKey) {
            error_log("⏰ [NewsSync] ساعت {$nowHour}:00 قبلاً اجرا شده");
            return;
        }

        set_transient(self::LAST_RUN_KEY, $currentHourKey, HOUR_IN_SECONDS);

        error_log("🕐 [NewsSync] ساعت {$nowHour}:00 - اجرای sync");

        @ini_set('max_execution_time', 300);
        @ini_set('memory_limit', '256M');

        self::runCron();
    }

    /* ========================================================================
       Check Missed Cron - برای مواقع cron از دست رفته
       ======================================================================== */
    
    public static function checkMissedCron(): void
    {
        if (get_option(self::OPT_AUTO_ENABLED, '1') !== '1') {
            return;
        }

        $hours = array_map('intval', (array) get_option(self::OPT_SCHEDULE_HOURS, [8, 14, 20]));
        if (empty($hours)) {
            return;
        }

        $nowHour = (int) wp_date('H');
        if (!in_array($nowHour, $hours, true)) {
            return;
        }

        $lastRunHour = get_transient(self::LAST_RUN_KEY);
        $currentHourKey = wp_date('Y-m-d-H');

        if ($lastRunHour === $currentHourKey) {
            return;
        }

        // فقط هر 3 دقیقه چک کن
        $checkKey = 'ns_missed_check_' . $currentHourKey;
        if (get_transient($checkKey)) {
            return;
        }

        set_transient($checkKey, 1, 3 * MINUTE_IN_SECONDS);

        // اگر در wp-cron.php هست، مستقیم اجرا کن
        if (defined('DOING_CRON') && DOING_CRON) {
            error_log("🕐 [NewsSync] اجرای مستقیم cron از دست رفته");
            set_transient(self::LAST_RUN_KEY, $currentHourKey, HOUR_IN_SECONDS);
            @ini_set('max_execution_time', 300);
            @ini_set('memory_limit', '256M');
            self::runCron();
            return;
        }

        // در غیر این صورت، schedule کن
        error_log("🕐 [NewsSync] schedule کردن cron فوری");
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK);
        }
        spawn_cron();
    }

    /* ========================================================================
       Run Cron - اجرای اصلی sync
       ======================================================================== */
    
    public static function runCron(): void
    {
        error_log('🕐 [NewsSync] Cron شروع شد در ' . current_time('mysql'));

        $sync = new self();

        try {
            $result = $sync->doSync();

            error_log('✅ [NewsSync] Cron کامل شد: ' . json_encode([
                'fetched'    => $result['total_fetched'] ?? 0,
                'rss'        => $result['fetched_rss'] ?? 0,
                'api'        => $result['fetched_api'] ?? 0,
                'duplicates' => $result['duplicates'] ?? 0,
                'filtered'   => $result['filtered_out'] ?? 0,
                'created'    => $result['created'] ?? 0,
                'drafts'     => $result['drafts'] ?? 0,
                'ai_checks'  => $result['ai_checks'] ?? 0,
                'ai_used'    => $result['ai_used'] ?? 0,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            error_log('❌ [NewsSync] Cron ناموفق: ' . $e->getMessage());
        }
    }

    /* ========================================================================
       Main Sync Logic
       ======================================================================== */
    
    private function doSync(): array
    {
        $window = max(1, (int) get_option(self::OPT_TIME_WINDOW, 24));
        $maxPub = max(0, (int) get_option(self::OPT_MAX_PUBLISH, 5));
        $maxDraft = max(0, (int) get_option(self::OPT_MAX_DRAFTS, 3));
        $maxAi = max(0, (int) get_option(self::OPT_MAX_AI_CHECKS, 2));
        $aiOn = get_option(self::OPT_AI_ENABLED, '0') === '1';

        error_log("📋 [NewsSync] Config: window={$window}h, max_pub={$maxPub}, max_draft={$maxDraft}, max_ai={$maxAi}, ai_on=" . ($aiOn ? 'yes' : 'no'));

        $stats = [
            'fetched_rss'    => 0,
            'fetched_api'    => 0,
            'total_fetched'  => 0,
            'duplicates'     => 0,
            'filtered_out'   => 0,
            'created'        => 0,
            'drafts'         => 0,
            'updated'        => 0,
            'failed'         => 0,
            'ai_used'       => 0,
            'ai_checks'    => 0,
            'retry_rewrites' => 0,
            'errors'         => [],
        ];

        // Retry pending drafts with AI
        $publishedByRetry = 0;
        if ($aiOn) {
            $publishedByRetry = $this->retryPendingRewrites($maxPub);
            $stats['retry_rewrites'] = $publishedByRetry;
        }

        $publishBudget = max(0, $maxPub - $publishedByRetry);

        // تعداد drafts در حال انتظار
        $pending = get_posts([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'draft',
            'meta_key'    => '_ns_needs_rewrite',
            'meta_value'  => '1',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);
        $draftBudget = max(0, $maxDraft - count($pending));

        // ═══ Stage 1: Fetch RSS ═══
        try {
            $rssItems = (array) $this->rssFetcher->fetchAll($window);
            $stats['fetched_rss'] = count($rssItems);
        } catch (\Throwable $e) {
            error_log('❌ [NewsSync] RSS fetch failed: ' . $e->getMessage());
            $stats['errors'][] = 'RSS: ' . $e->getMessage();
            $rssItems = [];
        }

        // ═══ Stage 2: Fetch API ═══
        try {
            $apiItems = (array) $this->apiFetcher->fetchAll();
            $stats['fetched_api'] = count($apiItems);
        } catch (\Throwable $e) {
            error_log('❌ [NewsSync] API fetch failed: ' . $e->getMessage());
            $stats['errors'][] = 'API: ' . $e->getMessage();
            $apiItems = [];
        }

        // ═══ Stage 3: Merge + time window + dedup ═══
        $all = $this->applyTimeWindow(array_merge($rssItems, $apiItems), $window);
        $stats['total_fetched'] = count($all);

        if (empty($all)) {
            return $this->buildResult($stats);
        }

        try {
            $unique = $this->duplicateChecker->filterDuplicates($all);
            $stats['duplicates'] = count($all) - count($unique);
        } catch (\Throwable $e) {
            error_log('❌ [NewsSync] Duplicate check failed: ' . $e->getMessage());
            $unique = $all;
        }

        if (empty($unique)) {
            return $this->buildResult($stats);
        }

        // ═══ Stage 4: Keyword filter ═══
        try {
            $passed = $this->newsFilter->filterItems($unique);
        } catch (\Throwable $e) {
            error_log('❌ [NewsSync] Filter failed: ' . $e->getMessage());
            $passed = $unique;
        }

        $stats['filtered_out'] = count($unique) - count($passed);

        if (empty($passed)) {
            return $this->buildResult($stats);
        }

        // ═══ Stage 5: AI check for "review" items ═══
        $queue = [];
        $review = [];

        foreach ($passed as $item) {
            $decision = $item['_filter_result']['decision'] ?? 'review';
            if ($decision === 'publish') {
                $queue[] = $item;
            } else {
                $review[] = $item;
            }
        }

        // AI check for review items
        if (!empty($review) && $aiOn) {
            usort($review, function ($a, $b) {
                return strcmp($b['pub_date'] ?? '', $a['pub_date'] ?? '');
            });

            $checks = 0;
            foreach ($review as $item) {
                if ($checks >= $maxAi) {
                    break;
                }
                $checks++;

                $ok = $this->relevanceFilter->isRelevant($item);

                if ($ok === null) {
                    // AI در دسترس نیست - بر اساس امتیاز تصمیم بگیر
                    $score = $item['_filter_result']['score'] ?? 0;
                    if ($score >= 10) {
                        $item['_filter_result']['decision'] = 'publish';
                        $queue[] = $item;
                    }
                    continue;
                }

                if ($ok) {
                    $item['_filter_result']['decision'] = 'publish';
                    $item['_filter_result']['ai_checked'] = true;
                    $queue[] = $item;
                }
            }
            $stats['ai_checks'] = $checks;
        }

        if (empty($queue)) {
            return $this->buildResult($stats);
        }

        // Sort by score (highest first)
        usort($queue, function ($a, $b) {
            return ($b['_filter_result']['score'] ?? 0) <=> ($a['_filter_result']['score'] ?? 0);
        });

        // ═══ Stage 6: Save with budget ═══
        foreach ($queue as $item) {
            if ($publishBudget <= 0 && $draftBudget <= 0) {
                break;
            }

            try {
                $r = $this->saveNews($item, $publishBudget, $draftBudget, $aiOn);

                if (($r['status'] ?? '') === 'created') {
                    $stats['created']++;
                    $publishBudget--;
                } elseif (($r['status'] ?? '') === 'draft') {
                    $stats['drafts']++;
                    $draftBudget--;
                }

                if (!empty($r['ai_used'])) {
                    $stats['ai_used']++;
                }
            } catch (\Throwable $e) {
                error_log('❌ [NewsSync] Save failed: ' . $e->getMessage());
                $stats['failed']++;
            }
        }

        error_log('✅ [NewsSync] Created=' . $stats['created'] . ', Drafts=' . $stats['drafts']);

        return $this->buildResult($stats);
    }

    /* ========================================================================
       Retry Pending Rewrites
       ======================================================================== */
    
    private function retryPendingRewrites(int $maxPublish): int
    {
        $pending = get_posts([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'draft',
            'meta_key'    => '_ns_needs_rewrite',
            'meta_value'  => '1',
            'numberposts' => $maxPublish,
            'fields'      => 'ids',
        ]);

        if (empty($pending)) {
            return 0;
        }

        $published = 0;

        foreach ($pending as $postId) {
            $originalTitle = get_post_meta($postId, '_ns_original_title', true);
            $originalContent = get_post_meta($postId, '_ns_original_content', true);
            $sourceUrl = get_post_meta($postId, '_ns_source_url', true);
            $sourceName = get_post_meta($postId, '_ns_source_name', true);

            if (empty($originalContent)) {
                continue;
            }

            $item = [
                'title'       => $originalTitle,
                'content'     => $originalContent,
                'link'        => $sourceUrl,
                'source_name' => $sourceName,
            ];

            $result = $this->aiRewriter->process($item);

            if ($result['ai_used']) {
                wp_update_post([
                    'ID'           => $postId,
                    'post_title'   => $result['title'],
                    'post_content' => $result['content'],
                    'post_excerpt' => $result['excerpt'],
                    'post_status'  => 'publish',
                ]);

                delete_post_meta($postId, '_ns_needs_rewrite');
                delete_post_meta($postId, '_ns_original_content');
                delete_post_meta($postId, '_ns_original_title');

                $this->aiRewriter->logFinal($postId, $result);
                $published++;
            }
        }

        return $published;
    }

    /* ========================================================================
       Save Single News
       ======================================================================== */
    
    private function saveNews(array $item, int &$publishBudget, int &$draftBudget, bool $aiOn): array
    {
        // بررسی تکراری دوباره
        if ($this->duplicateChecker->isDuplicate($item)) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        $processed = null;
        $aiOk = false;

        if ($aiOn) {
            try {
                $processed = $this->aiRewriter->process($item);
                $aiOk = !empty($processed['ai_used']);
            } catch (\Throwable $e) {
                error_log('⚠️ [NewsSync] AI rewrite failed: ' . $e->getMessage());
            }
        }

        $title = $processed['title'] ?? ($item['title'] ?? '');
        $content = $processed['content'] ?? ($item['content'] ?? '');
        $excerpt = $processed['excerpt'] ?? ($item['excerpt'] ?? '');

        if (empty($title) || empty(trim(wp_strip_all_tags($content)))) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        // پاک کردن تصاویر از محتوا (تصویر جداگانه ذخیره می‌شود)
        $content = \NextSafar\Api\Services\ImageManager::stripImagesFromContent($content);

        $slug = sanitize_title($title);
        if ($this->slugExists($slug)) {
            $slug .= '-' . time();
        }

        // AI موفق = publish، در غیر این صورت = draft
        $postStatus = ($aiOk || !$aiOn) ? 'publish' : 'draft';

        // بررسی budget
        if ($postStatus === 'publish' && $publishBudget <= 0) {
            $postStatus = 'draft';
        } elseif ($postStatus === 'draft' && $draftBudget <= 0) {
            return ['status' => 'skipped', 'ai_used' => false];
        }

        $postId = wp_insert_post([
            'post_type'    => self::POST_TYPE,
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

        // Save meta
        update_post_meta($postId, '_ns_source_url', esc_url_raw($item['link'] ?? ''));
        update_post_meta($postId, '_ns_source_name', sanitize_text_field($item['source_name'] ?? ''));
        update_post_meta($postId, '_ns_source_group', sanitize_text_field($item['source_group'] ?? ''));
        update_post_meta($postId, '_ns_original_guid', sanitize_text_field($item['guid'] ?? ''));
        update_post_meta($postId, '_ns_fetch_type', sanitize_text_field($item['fetch_type'] ?? 'unknown'));
        update_post_meta($postId, '_ns_ai_used', $aiOk ? '1' : '0');
        update_post_meta($postId, '_ns_fetched_at', current_time('mysql'));

        // اگر draft است و AI موفق نبود، برای بازنویسی بعدی علامت بزن
        if ($postStatus === 'draft' && !$aiOk) {
            update_post_meta($postId, '_ns_needs_rewrite', '1');
            update_post_meta($postId, '_ns_original_content', wp_kses_post($item['content'] ?? ''));
            update_post_meta($postId, '_ns_original_title', sanitize_text_field($item['title'] ?? ''));
        }

        // Featured image
        $featured = $item['image'] ?? '';
        if (!empty($featured)) {
            \NextSafar\Api\Services\ImageManager::setFeaturedImage($postId, $featured, $item['fetch_type'] ?? 'api');
        }

        // علامت‌گذاری در duplicate table
        $this->duplicateChecker->markAsSaved($item, $postId);

        // لاگ AI
        if ($processed) {
            $this->aiRewriter->logFinal($postId, $processed);
        }

        return [
            'status'  => $postStatus === 'publish' ? 'created' : 'draft',
            'ai_used' => $aiOk,
        ];
    }

    /* ========================================================================
       Helpers
       ======================================================================== */
    
    private function applyTimeWindow(array $items, int $windowHours): array
    {
        $cutoff = time() - $windowHours * HOUR_IN_SECONDS;

        return array_values(array_filter($items, function ($item) use ($cutoff) {
            $ts = strtotime($item['pub_date'] ?? '');
            return !$ts || $ts >= $cutoff;
        }));
    }

    private function slugExists(string $slug): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s LIMIT 1",
            $slug,
            self::POST_TYPE
        ));
    }

    private function buildResult(array $stats): array
    {
        return [
            'total_fetched'  => $stats['total_fetched'],
            'fetched_rss'    => $stats['fetched_rss'],
            'fetched_api'    => $stats['fetched_api'],
            'duplicates'     => $stats['duplicates'],
            'filtered_out'   => $stats['filtered_out'],
            'created'        => $stats['created'],
            'drafts'         => $stats['drafts'],
            'failed'         => $stats['failed'],
            'ai_used'        => $stats['ai_used'],
            'ai_checks'      => $stats['ai_checks'],
            'retry_rewrites' => $stats['retry_rewrites'],
            'errors'         => $stats['errors'],
        ];
    }

    /* ========================================================================
       Stats for Dashboard
       ======================================================================== */
    
    public static function getOverviewStats(): array
    {
        global $wpdb;

        $stats = [
            'total_posts'           => 0,
            'today_posts'           => 0,
            'total_duplicates'      => 0,
            'ai_total_cost_usd'     => 0,
            'ai_total_tokens'       => 0,
            'ai_total_calls'        => 0,
            'total_rss_fetched'     => 0,
            'total_api_fetched'     => 0,
        ];

        // Total posts
        $stats['total_posts'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'travelnews'"
        );

        // Today's posts
        $stats['today_posts'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'travelnews' AND DATE(post_date) = CURDATE()"
        );

        // Duplicates table
        $dupesTable = $wpdb->prefix . 'ns_news_duplicates';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$dupesTable}'") === $dupesTable) {
            $stats['total_duplicates'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$dupesTable}"
            );
        }

        // AI log
        $aiTable = $wpdb->prefix . 'ns_news_ai_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$aiTable}'") === $aiTable) {
            $stats['ai_total_calls'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$aiTable}"
            );
            $stats['ai_total_tokens'] = (int) $wpdb->get_var(
                "SELECT SUM(tokens_used) FROM {$aiTable}"
            );
            $stats['ai_total_cost_usd'] = (float) $wpdb->get_var(
                "SELECT SUM(cost_usd) FROM {$aiTable}"
            );
        }

        // Sources
        $sourcesTable = $wpdb->prefix . 'ns_news_sources';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$sourcesTable}'") === $sourcesTable) {
            $rssFetched = (int) $wpdb->get_var(
                "SELECT SUM(total_fetched) FROM {$sourcesTable} WHERE type = 'rss'"
            );
            $apiFetched = (int) $wpdb->get_var(
                "SELECT SUM(total_fetched) FROM {$sourcesTable} WHERE type = 'api'"
            );
            $stats['total_rss_fetched'] = $rssFetched;
            $stats['total_api_fetched'] = $apiFetched;
        }

        return $stats;
    }

    /**
     * Get status of all sources
     */
    public static function getSourcesStats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY type, priority DESC",
            ARRAY_A
        ) ?: [];
    }
}