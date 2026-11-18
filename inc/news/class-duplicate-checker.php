<?php
/**
 * DuplicateChecker — تشخیص اخبار تکراری در 4 سطح
 *
 * سطوح بررسی:
 * 1. URL Hash - تطبیق دقیق URL
 * 2. GUID Hash - تطبیق شناسه منحصر به فرد RSS
 * 3. Title Hash - تطبیق عنوان
 * 4. Fuzzy Similarity - تطبیق تقریبی با Jaccard (آستانه 75%)
 *
 * همچنین در wp_postmeta نیز چک می‌کند.
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class DuplicateChecker
{
    /** آستانه تشابه برای Jaccard */
    public const SIMILARITY_THRESHOLD = 0.75;

    /* ========================================================================
       filterDuplicates — فیلتر کردن تکراری‌ها از یک batch
       ======================================================================== */
    
    /**
     * حذف تکراری‌ها از لیست اخبار
     *
     * @param array $newsItems لیست اخبار
     * @return array لیست اخبار منحصر به فرد
     */
    public function filterDuplicates(array $newsItems): array
    {
        $this->ensureTableExists();

        $unique = [];
        $seenInBatch = [];

        foreach ($newsItems as $item) {
            // Level 1: بررسی URL و Title در همین batch
            $urlHash = md5($this->normalizeUrl($item['link'] ?? ''));
            $titleHash = md5($this->normalizeTitle($item['title'] ?? ''));

            if (isset($seenInBatch[$urlHash]) || isset($seenInBatch[$titleHash])) {
                error_log("🔍 [Duplicate] Batch duplicate skipped: " . mb_substr($item['title'] ?? '', 0, 50));
                continue;
            }

            // Level 2: بررسی تشابه fuzzy در همین batch
            if ($this->isSimilarInBatch($item, array_values($seenInBatch))) {
                error_log("🔍 [Duplicate] Fuzzy duplicate skipped: " . mb_substr($item['title'] ?? '', 0, 50));
                continue;
            }

            // Level 3: بررسی در دیتابیس
            if ($this->isDuplicate($item)) {
                continue;
            }

            $seenInBatch[$urlHash] = $item;
            $seenInBatch[$titleHash] = $item;
            $unique[] = $item;
        }

        return $unique;
    }

    /* ========================================================================
       Batch Similarity Check
       ======================================================================== */
    
    private function isSimilarInBatch(array $item, array $seenItems): bool
    {
        $itemNormalized = $this->normalizeTitle($item['title'] ?? '');

        foreach ($seenItems as $seenItem) {
            $seenNormalized = $this->normalizeTitle($seenItem['title'] ?? '');
            if ($this->calculateSimilarity($itemNormalized, $seenNormalized) >= self::SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /* ========================================================================
       Jaccard Similarity
       ======================================================================== */
    
    /**
     * محاسبه تشابه Jaccard بین دو متن
     */
    private function calculateSimilarity(string $text1, string $text2): float
    {
        if ($text1 === $text2) {
            return 1.0;
        }
        if (empty($text1) || empty($text2)) {
            return 0.0;
        }

        $words1 = array_unique(array_filter(explode(' ', $text1)));
        $words2 = array_unique(array_filter(explode(' ', $text2)));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /* ========================================================================
       isDuplicate — بررسی تکراری بودن در دیتابیس
       ======================================================================== */
    
    /**
     * چک می‌کند آیا این خبر قبلاً پردازش شده است
     *
     * @param array $item آیتم خبری
     * @return bool تکراری است یا خیر
     */
    public function isDuplicate(array $item): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ns_news_duplicates';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return false;
        }

        // Level 1: URL
        if (!empty($item['link'])) {
            $urlHash = md5($this->normalizeUrl($item['link']));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE url_hash = %s LIMIT 1",
                $urlHash
            ));
            if ($exists) {
                return true;
            }
        }

        // Level 2: GUID
        if (!empty($item['guid']) && $item['guid'] !== ($item['link'] ?? '')) {
            $guidHash = md5($item['guid']);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE guid_hash = %s LIMIT 1",
                $guidHash
            ));
            if ($exists) {
                return true;
            }
        }

        // Level 3: Title
        $titleHash = md5($this->normalizeTitle($item['title'] ?? ''));
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE title_hash = %s LIMIT 1",
            $titleHash
        ));
        if ($exists) {
            return true;
        }

        // Level 4: wp_postmeta
        if (!empty($item['link'])) {
            $postExists = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ns_source_url' AND meta_value = %s LIMIT 1",
                $item['link']
            ));
            if ($postExists) {
                return true;
            }
        }

        // Level 5: پست‌های مشابه اخیر (24 ساعت گذشته)
        if ($this->findSimilarPost($item['title'] ?? '')) {
            return true;
        }

        return false;
    }

    /* ========================================================================
       Find Similar Recent Post
       ======================================================================== */
    
    private function findSimilarPost(string $title): bool
    {
        global $wpdb;

        $normalized = $this->normalizeTitle($title);
        $words = array_filter(explode(' ', $normalized));

        if (count($words) < 3) {
            return false;
        }

        $recentPosts = $wpdb->get_col(
            "SELECT post_title FROM {$wpdb->posts}
             WHERE post_type = 'travelnews' 
             AND post_status IN ('publish','draft')
             AND post_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             LIMIT 200"
        );

        foreach ($recentPosts as $postTitle) {
            if ($this->calculateSimilarity($normalized, $this->normalizeTitle($postTitle)) >= self::SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /* ========================================================================
       Mark as Filtered (rejected by filter)
       ======================================================================== */
    
    public function markAsFiltered(array $item): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_duplicates';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $urlHash = md5($this->normalizeUrl($item['link'] ?? ''));
        $titleHash = md5($this->normalizeTitle($item['title'] ?? ''));
        $guidHash = !empty($item['guid']) ? md5($item['guid']) : null;

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table}
             (url_hash, title_hash, guid_hash, post_id, source_name, fetched_at)
             VALUES (%s, %s, %s, %d, %s, NOW())",
            $urlHash, $titleHash, $guidHash, 0, $item['source_name'] ?? 'filtered'
        ));
    }

    /* ========================================================================
       Mark as Saved (published or drafted)
       ======================================================================== */
    
    public function markAsSaved(array $item, int $postId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_duplicates';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $urlHash = md5($this->normalizeUrl($item['link'] ?? ''));
        $titleHash = md5($this->normalizeTitle($item['title'] ?? ''));
        $guidHash = !empty($item['guid']) ? md5($item['guid']) : null;

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table}
             (url_hash, title_hash, guid_hash, post_id, source_name, fetched_at)
             VALUES (%s, %s, %s, %d, %s, NOW())",
            $urlHash, $titleHash, $guidHash, $postId, $item['source_name'] ?? 'unknown'
        ));
    }

    /* ========================================================================
       Normalization Methods
       ======================================================================== */
    
    /**
     * نرمال‌سازی URL (حذف utm و tracking params)
     */
    private function normalizeUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $parsed = parse_url($url);
        $base = ($parsed['scheme'] ?? 'https') . '://' . strtolower($parsed['host'] ?? '') . ($parsed['path'] ?? '');

        $query = $parsed['query'] ?? '';
        if (!empty($query)) {
            parse_str($query, $params);
            
            // حذف tracking parameters
            unset(
                $params['utm_source'], $params['utm_medium'], $params['utm_campaign'],
                $params['utm_content'], $params['utm_term'], $params['ref'],
                $params['fbclid'], $params['gclid']
            );

            if (!empty($params)) {
                $base .= '?' . http_build_query($params);
            }
        }

        return $base;
    }

    /**
     * نرمال‌سازی عنوان برای مقایسه
     */
    private function normalizeTitle(string $title): string
    {
        if (empty($title)) {
            return '';
        }

        $title = mb_strtolower($title, 'UTF-8');

        // حذف اعراب عربی
        $title = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $title);

        // نرمال‌سازی حروف عربی به فارسی
        $title = str_replace(
            ['ي', 'ك', 'ۀ', 'ة', 'ـ'],
            ['ی', 'ک', 'ه', 'ه', ''],
            $title
        );

        // حذف کاراکترهای خاص
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);

        // حذف stop words
        $stopWords = [
            'و', 'در', 'به', 'از', 'که', 'این', 'آن', 'است', 'بود', 'شد',
            'های', 'ای', 'را', 'با', 'برای',
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'of', 'in', 'on', 'at',
        ];

        $words = array_filter(preg_split('/\s+/', $title), function ($w) use ($stopWords) {
            $w = trim($w);
            return !in_array($w, $stopWords) && mb_strlen($w) > 1;
        });

        return implode(' ', $words);
    }

    /* ========================================================================
       Cleanup
       ======================================================================== */
    
    /**
     * حذف هش‌های قدیمی (بیش از 3 ماه)
     */
    public function cleanupOldHashes(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_duplicates';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return 0;
        }

        return (int) $wpdb->query(
            "DELETE FROM {$table} WHERE fetched_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)"
        );
    }

    /* ========================================================================
       Table Check
       ======================================================================== */
    
    private function ensureTableExists(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_duplicates';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            \NextSafar\Database\NewsTables::createDuplicatesTable();
        }
    }
}