<?php
/**
 * NewsFilter — فیلتر اخبار با کلمات کلیدی
 *
 * سه مرحله تصمیم‌گیری:
 * - publish: مستقیماً منتشر شود (امتیاز بالا)
 * - review: نیاز به بررسی AI دارد
 * - delete: قطعاً حذف شود (کلمات منفی)
 *
 * امتیازدهی:
 * - کلمات مثبت: +10 تا +25 امتیاز
 * - کلمات منفی: -15 تا -30 امتیاز
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class NewsFilter
{
    /** @var array|null Cache کلمات کلیدی */
    private ?array $keywordsCache = null;

    /* ========================================================================
       filterItems — فیلتر کردن لیست اخبار
       ======================================================================== */
    
    /**
     * فیلتر کردن اخبار بر اساس کلمات کلیدی
     *
     * @param array $items لیست اخبار
     * @return array اخباری که حذف نشده‌اند
     */
    public function filterItems(array $items): array
    {
        $this->loadKeywords();

        if (empty($this->keywordsCache)) {
            error_log('⚠️ [NewsFilter] Keywords not available — همه به AI می‌روند');
            foreach ($items as &$item) {
                $item['_filter_result'] = [
                    'score'            => 0,
                    'decision'         => 'review',
                    'reason'           => 'کلمات کلیدی بارگذاری نشد',
                    'positive_matches' => [],
                    'negative_matches' => [],
                    'ai_checked'       => false,
                ];
            }
            return $items;
        }

        $filtered = [];

        foreach ($items as $item) {
            $result = $this->analyzeItem($item);
            $item['_filter_result'] = $result;

            $symbol = $result['decision'] === 'delete'  ? '🗑️ FILTERED'
                    : ($result['decision'] === 'publish' ? '✅ PUBLISH' : '🤖 REVIEW');

            error_log(sprintf(
                '%s: %s (score=%d)',
                $symbol,
                mb_substr($item['title'] ?? '', 0, 60),
                $result['score']
            ));

            // لاگ همه تصمیمات
            $this->logFilterDecision($item);

            if ($result['decision'] !== 'delete') {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /* ========================================================================
       analyzeItem — تحلیل یک خبر
       ======================================================================== */
    
    /**
     * تحلیل یک خبر و تعیین امتیاز و تصمیم
     */
    public function analyzeItem(array $item): array
    {
        $text = ($item['title'] ?? '') . ' ' . ($item['content'] ?? '');
        $scoreData = $this->calculateKeywordScore($text);
        $decision = $this->makeDecision($scoreData['score'], $scoreData['matches']);

        return [
            'score'            => $scoreData['score'],
            'decision'         => $decision['type'],
            'reason'           => $decision['reason'],
            'positive_matches' => $scoreData['matches']['positive'],
            'negative_matches' => $scoreData['matches']['negative'],
            'ai_checked'       => false,
        ];
    }

    /* ========================================================================
       Keyword Scoring
       ======================================================================== */
    
    private function calculateKeywordScore(string $text): array
    {
        $score = 0;
        $matches = ['positive' => [], 'negative' => []];

        if ($this->keywordsCache === null) {
            $this->loadKeywords();
        }

        if (empty($this->keywordsCache)) {
            return ['score' => 0, 'matches' => $matches];
        }

        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return ['score' => 0, 'matches' => $matches];
        }

        foreach ($this->keywordsCache as $keyword) {
            if (empty($keyword['is_active']) || empty($keyword['keyword'])) {
                continue;
            }

            $kw = $this->normalizeText($keyword['keyword']);
            if ($kw === '' || mb_strlen($kw) < 2) {
                continue;
            }

            if (mb_strpos($normalized, $kw, 0, 'UTF-8') !== false) {
                $score += (int) ($keyword['weight'] ?? 0);

                $type = (($keyword['type'] ?? 'positive') === 'negative') ? 'negative' : 'positive';

                if (!in_array($keyword['keyword'], $matches[$type], true)) {
                    $matches[$type][] = $keyword['keyword'];
                }
            }
        }

        return ['score' => $score, 'matches' => $matches];
    }

    private function normalizeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // حذف اعراب
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        
        // نرمال‌سازی عربی به فارسی
        $text = str_replace(
            ['ي', 'ئ', 'ؤ', 'ة', 'ك', 'أ', 'إ', 'ٱ'],
            ['ی', 'ی', 'و', 'ه', 'ک', 'ا', 'ا', 'ا'],
            $text
        );
        
        // حذف ZWNJ و BOM
        $text = str_replace(["\u{200C}", "\u{FEFF}"], ' ', $text);
        
        // lowercase
        $text = mb_strtolower($text, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /* ========================================================================
       Decision Making
       ======================================================================== */
    
    /**
     * تصمیم‌گیری نهایی بر اساس امتیاز و تطابقات
     */
    private function makeDecision(int $score, array $matches): array
    {
        $publishThreshold = (int) get_option('nextsafar_news_filter_publish_threshold', 20);
        $deleteThreshold = (int) get_option('nextsafar_news_filter_delete_threshold', -10);

        $positiveCount = count($matches['positive']);
        $negativeCount = count($matches['negative']);

        // 2+ کلمه منفی → حذف قطعی
        if ($negativeCount >= 2) {
            return [
                'type'   => 'delete',
                'reason' => 'کلمات منفی: ' . implode(', ', $matches['negative']),
            ];
        }

        // امتیاز خیلی پایین → حذف
        if ($score <= $deleteThreshold) {
            return [
                'type'   => 'delete',
                'reason' => "امتیاز خیلی پایین ({$score}) - حذف قطعی",
            ];
        }

        // امتیاز عالی → انتشار مستقیم
        if ($score >= $publishThreshold) {
            return [
                'type'   => 'publish',
                'reason' => 'امتیاز عالی (' . $score . '): ' . implode(', ', $matches['positive']),
            ];
        }

        // امتیاز متوسط با چند کلمه مثبت → انتشار
        if ($score >= 10 && $positiveCount >= 2) {
            return [
                'type'   => 'publish',
                'reason' => "امتیاز متوسط ({$score}) با {$positiveCount} کلمه مثبت",
            ];
        }

        // بقیه موارد → بررسی AI
        return [
            'type'   => 'review',
            'reason' => "امتیاز {$score} - نیاز به بررسی AI",
        ];
    }

    /* ========================================================================
       Filter Logging
       ======================================================================== */
    
    private function logFilterDecision(array $item): void
    {
        if (empty($item['_filter_result'])) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_filter_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $r = $item['_filter_result'];

        $wpdb->insert($table, [
            'news_title'       => sanitize_text_field(mb_substr($item['title'] ?? '', 0, 200)),
            'source_name'      => sanitize_text_field($item['source_name'] ?? ''),
            'score'            => (int) ($r['score'] ?? 0),
            'decision'         => sanitize_text_field($r['decision'] ?? 'unknown'),
            'reason'           => sanitize_text_field(mb_substr($r['reason'] ?? '', 0, 500)),
            'positive_matches' => maybe_serialize($r['positive_matches'] ?? []),
            'negative_matches' => maybe_serialize($r['negative_matches'] ?? []),
            'ai_checked'       => !empty($r['ai_checked']) ? 1 : 0,
        ]);
    }

    /* ========================================================================
       Keyword Management
       ======================================================================== */
    
    private function loadKeywords(): void
    {
        if ($this->keywordsCache !== null) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_keywords';
        $rows = [];

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $rows = $wpdb->get_results(
                "SELECT keyword, type, weight, category, is_active FROM {$table} WHERE is_active = 1",
                ARRAY_A
            ) ?: [];
        }

        if (empty($rows)) {
            error_log('⚠️ [NewsFilter] جدول خالی است - از پیش‌فرض‌ها استفاده می‌شود');
            $rows = self::getDefaultKeywordRows();
        }

        $this->keywordsCache = $rows;
    }

    /**
     * کلمات کلیدی پیش‌فرض - تنها منبع معتبر کلمات کلیدی
     */
    public static function getDefaultKeywordRows(): array
    {
        $w = [
            // ═══════════ مثبت - Core ═══════════
            'positive' => [
                'گردشگری'        => ['w' => 25, 'c' => 'core'],
                'سفر'            => ['w' => 15, 'c' => 'core'],
                'تور'            => ['w' => 20, 'c' => 'core'],
                'تور مسافرتی'    => ['w' => 25, 'c' => 'core'],
                'مسافر'          => ['w' => 15, 'c' => 'core'],
                'هتل'            => ['w' => 20, 'c' => 'core'],
                'اقامت'          => ['w' => 15, 'c' => 'core'],
                'ویلا'           => ['w' => 20, 'c' => 'core'],
                'بوم‌گردی'       => ['w' => 25, 'c' => 'eco'],
                'بومگردی'        => ['w' => 20, 'c' => 'eco'],
                'طبیعت‌گردی'     => ['w' => 20, 'c' => 'eco'],
                'گردشگری سلامت'  => ['w' => 15, 'c' => 'health'],
                'پرواز'          => ['w' => 20, 'c' => 'transport'],
                'بلیط'           => ['w' => 15, 'c' => 'transport'],
                'فرودگاه'        => ['w' => 15, 'c' => 'transport'],
                'ایرلاین'        => ['w' => 20, 'c' => 'transport'],
                'ساحل'           => ['w' => 15, 'c' => 'nature'],
                'جزیره'          => ['w' => 15, 'c' => 'nature'],
                'دریاچه'         => ['w' => 15, 'c' => 'nature'],
                'جنگل'           => ['w' => 10, 'c' => 'nature'],
                'موزه'           => ['w' => 10, 'c' => 'culture'],
                'جاذبه'          => ['w' => 20, 'c' => 'culture'],
                'میراث فرهنگی'   => ['w' => 20, 'c' => 'culture'],
                'تاریخی'         => ['w' => 10, 'c' => 'culture'],
                'رستوران'        => ['w' => 15, 'c' => 'food'],
                'غذای محلی'      => ['w' => 15, 'c' => 'food'],
                'کافه'           => ['w' => 10, 'c' => 'food'],
                // English positive
                'travel'         => ['w' => 20, 'c' => 'en'],
                'tourism'        => ['w' => 20, 'c' => 'en'],
                'hotel'          => ['w' => 20, 'c' => 'en'],
                'resort'         => ['w' => 20, 'c' => 'en'],
                'vacation'       => ['w' => 20, 'c' => 'en'],
                'holiday'        => ['w' => 15, 'c' => 'en'],
                'destination'    => ['w' => 15, 'c' => 'en'],
                'flight'         => ['w' => 15, 'c' => 'en'],
                'airline'        => ['w' => 15, 'c' => 'en'],
                'airport'        => ['w' => 15, 'c' => 'en'],
                'tour'           => ['w' => 20, 'c' => 'en'],
                'cruise'         => ['w' => 20, 'c' => 'en'],
                'backpacking'    => ['w' => 15, 'c' => 'en'],
                'adventure'      => ['w' => 15, 'c' => 'en'],
                'beach'          => ['w' => 15, 'c' => 'en'],
                'paradise'       => ['w' => 15, 'c' => 'en'],
                'unesco'         => ['w' => 20, 'c' => 'en'],
                'heritage'       => ['w' => 15, 'c' => 'en'],
                'honeymoon'      => ['w' => 20, 'c' => 'en'],
                'luxury travel'  => ['w' => 20, 'c' => 'en'],
                'budget travel'  => ['w' => 15, 'c' => 'en'],
                'travel blogger' => ['w' => 15, 'c' => 'en'],
                'itinerary'      => ['w' => 15, 'c' => 'en'],
                'sustainable travel' => ['w' => 20, 'c' => 'en'],
                'medical tourism' => ['w' => 20, 'c' => 'en'],
                'northern lights' => ['w' => 20, 'c' => 'en'],
                'santorini'      => ['w' => 15, 'c' => 'en'],
                'maldives'       => ['w' => 20, 'c' => 'en'],
                'machu picchu'   => ['w' => 25, 'c' => 'en'],
            ],
            // ═══════════ منفی ═══════════
            'negative' => [
                'جنگ'            => ['w' => -30, 'c' => 'military'],
                'حمله'           => ['w' => -30, 'c' => 'military'],
                'بمباران'        => ['w' => -30, 'c' => 'military'],
                'موشک'           => ['w' => -30, 'c' => 'military'],
                'ترور'           => ['w' => -30, 'c' => 'military'],
                'تروریسم'        => ['w' => -30, 'c' => 'military'],
                'انفجار'         => ['w' => -30, 'c' => 'military'],
                'کشته'           => ['w' => -25, 'c' => 'accident'],
                'زخمی'           => ['w' => -20, 'c' => 'accident'],
                'تصادف'          => ['w' => -20, 'c' => 'accident'],
                'سقوط'           => ['w' => -25, 'c' => 'accident'],
                'سقوط هواپیما'   => ['w' => -30, 'c' => 'accident'],
                'آتش‌سوزی'       => ['w' => -25, 'c' => 'accident'],
                'فوتبال'         => ['w' => -30, 'c' => 'sports'],
                'جام جهانی'      => ['w' => -25, 'c' => 'sports'],
                'سیل'            => ['w' => -20, 'c' => 'disaster'],
                'زلزله'          => ['w' => -25, 'c' => 'disaster'],
                'توفان'          => ['w' => -20, 'c' => 'disaster'],
                'سونامی'         => ['w' => -30, 'c' => 'disaster'],
                'انتخابات'       => ['w' => -30, 'c' => 'politics'],
                'استیضاح'        => ['w' => -25, 'c' => 'politics'],
                'دلار'           => ['w' => -25, 'c' => 'finance'],
                'تورم'           => ['w' => -25, 'c' => 'finance'],
                'بورس'           => ['w' => -20, 'c' => 'finance'],
                'سهام'           => ['w' => -30, 'c' => 'finance'],
                'کرونا'          => ['w' => -15, 'c' => 'health'],
                'پاندمی'         => ['w' => -25, 'c' => 'health'],
                'قتل'            => ['w' => -30, 'c' => 'crime'],
                'سرقت'           => ['w' => -25, 'c' => 'crime'],
                'تظاهرات'        => ['w' => -25, 'c' => 'politics'],
                'شورش'           => ['w' => -30, 'c' => 'politics'],
                'سینما'          => ['w' => -15, 'c' => 'entertainment'],
                'بازیگر'         => ['w' => -15, 'c' => 'entertainment'],
                'سلبریتی'        => ['w' => -15, 'c' => 'entertainment'],
                // English negative
                'war'            => ['w' => -30, 'c' => 'en_neg'],
                'attack'         => ['w' => -30, 'c' => 'en_neg'],
                'bombing'        => ['w' => -30, 'c' => 'en_neg'],
                'terrorist'      => ['w' => -30, 'c' => 'en_neg'],
                'killed'         => ['w' => -25, 'c' => 'en_neg'],
                'dead'           => ['w' => -20, 'c' => 'en_neg'],
                'crash'          => ['w' => -25, 'c' => 'en_neg'],
                'plane crash'    => ['w' => -30, 'c' => 'en_neg'],
                'earthquake'     => ['w' => -25, 'c' => 'en_neg'],
                'tsunami'        => ['w' => -30, 'c' => 'en_neg'],
                'protest'        => ['w' => -15, 'c' => 'en_neg'],
                'riot'           => ['w' => -25, 'c' => 'en_neg'],
                'recession'      => ['w' => -20, 'c' => 'en_neg'],
                'pandemic'       => ['w' => -25, 'c' => 'en_neg'],
                'travel ban'     => ['w' => -25, 'c' => 'en_neg'],
                'travel warning' => ['w' => -25, 'c' => 'en_neg'],
                'bed bugs'       => ['w' => -25, 'c' => 'en_neg'],
                'food poisoning' => ['w' => -25, 'c' => 'en_neg'],
            ],
        ];

        $rows = [];
        foreach ($w as $type => $list) {
            foreach ($list as $kw => $meta) {
                $rows[] = [
                    'keyword'   => $kw,
                    'type'      => $type,
                    'weight'    => $meta['w'],
                    'category'  => $meta['c'],
                    'is_active' => 1,
                ];
            }
        }

        return $rows;
    }

    /* ========================================================================
       Keyword Management (CRUD)
       ======================================================================== */
    
    public static function saveKeywords(array $positive, array $negative): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_keywords';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            \NextSafar\Database\NewsTables::createKeywordsTable();
        }

        $wpdb->query("TRUNCATE TABLE {$table}");

        $canon = [];
        foreach (self::getDefaultKeywordRows() as $r) {
            $canon[$r['type']][$r['keyword']] = $r;
        }

        foreach (array_filter($positive) as $kw) {
            $kw = sanitize_text_field(trim($kw));
            $c = $canon['positive'][$kw] ?? null;
            $wpdb->insert($table, [
                'keyword'   => $kw,
                'type'      => 'positive',
                'weight'    => $c['weight'] ?? 10,
                'category'  => $c['category'] ?? 'general',
                'is_active' => 1,
            ]);
        }

        foreach (array_filter($negative) as $kw) {
            $kw = sanitize_text_field(trim($kw));
            $c = $canon['negative'][$kw] ?? null;
            $wpdb->insert($table, [
                'keyword'   => $kw,
                'type'      => 'negative',
                'weight'    => $c['weight'] ?? -20,
                'category'  => $c['category'] ?? 'general',
                'is_active' => 1,
            ]);
        }
    }

    public static function addKeyword(string $kw, string $type, int $weight = 10, string $cat = 'general'): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_keywords';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            \NextSafar\Database\NewsTables::createKeywordsTable();
        }

        return $wpdb->insert($table, [
            'keyword'   => sanitize_text_field($kw),
            'type'      => $type,
            'weight'    => $weight,
            'category'  => $cat,
            'is_active' => 1,
        ]) !== false;
    }

    public static function deleteKeyword(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'ns_news_keywords', ['id' => $id], ['%d']) !== false;
    }

    public static function getAllKeywords(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_keywords';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY type, category, keyword",
            ARRAY_A
        ) ?: [];
    }

    public static function getFilterHistory(int $limit = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_filter_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT news_title, source_name, score, decision, reason,
                    positive_matches, negative_matches, ai_checked, created_at
             FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);

        if (empty($results)) {
            return [];
        }

        foreach ($results as &$row) {
            $row['positive_matches'] = maybe_unserialize($row['positive_matches']) ?: [];
            $row['negative_matches'] = maybe_unserialize($row['negative_matches']) ?: [];
            $row['ai_checked'] = (bool) $row['ai_checked'];
            $row['score'] = (int) $row['score'];
        }

        return $results;
    }
}