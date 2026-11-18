<?php
/**
 * RSSFetcher — دریافت اخبار از RSS feeds
 *
 * ویژگی‌ها:
 * - خواندن لیست فیدها از جدول ns_news_sources
 * - فیلتر زمانی (فقط اخبار X ساعت اخیر)
 * - استخراج تصویر و ویدیو از محتوا
 * - استخراج دسته‌بندی‌ها
 * - Rate limiting با RateLimiter
 * - SimplePie برای parsing
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

use SimplePie;

if (!defined('ABSPATH')) {
    exit;
}

class RSSFetcher
{
    private int $timeout = 20;
    private string $userAgent;
    private int $cutoffTs = 0;

    public function __construct()
    {
        $this->userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 NextSafar/1.0';
    }

    /* ========================================================================
       fetchAll — دریافت از همه فیدهای فعال
       ======================================================================== */
    
    /**
     * دریافت از همه RSS feeds فعال
     *
     * @param int $maxAgeHours حداکثر سن اخبار (ساعت)
     * @return array لیست اخبار
     */
    public function fetchAll(int $maxAgeHours = 12): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ns_news_sources';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            error_log('⚠️ [RSSFetcher] ns_news_sources table does not exist');
            return [];
        }

        $this->cutoffTs = time() - max(1, $maxAgeHours) * HOUR_IN_SECONDS;

        $feeds = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE type = 'rss' AND is_active = 1 ORDER BY priority DESC"
        );

        if (empty($feeds)) {
            return [];
        }

        $allNews = [];
        $errorsCount = 0;
        $maxErrors = 5;

        foreach ($feeds as $feed) {
            if ($errorsCount >= $maxErrors) {
                error_log('⚠️ [RSSFetcher] Too many errors, stopping');
                break;
            }

            try {
                RateLimiter::waitIfNeeded('rss', 1.0);

                $items = $this->fetchFeed($feed->url, $feed->name);
                $allNews = array_merge($allNews, $items);

                // به‌روزرسانی آمار
                $wpdb->update($table, [
                    'last_fetch'    => current_time('mysql'),
                    'last_success'  => current_time('mysql'),
                    'total_fetched' => (int) $feed->total_fetched + count($items),
                    'error_message' => null,
                ], ['id' => $feed->id]);
            } catch (\Throwable $e) {
                $errorsCount++;

                $wpdb->update($table, [
                    'last_fetch'    => current_time('mysql'),
                    'error_message' => mb_substr($e->getMessage(), 0, 500),
                ], ['id' => $feed->id]);

                error_log("❌ [RSSFetcher] {$feed->name}: " . $e->getMessage());
            }
        }

        error_log("✅ [RSSFetcher] " . count($allNews) . " items from " . count($feeds) . " feeds");

        return $allNews;
    }

    /* ========================================================================
       fetchFeed — دریافت یک فید خاص
       ======================================================================== */
    
    private function fetchFeed(string $url, string $sourceName): array
    {
        $items = [];

        $response = wp_remote_get($url, [
            'timeout'    => $this->timeout,
            'user-agent' => $this->userAgent,
            'headers'    => [
                'Accept'          => 'application/rss+xml, application/xml, text/xml, */*',
                'Accept-Language' => 'en-US,en;q=0.9,fa;q=0.8',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('HTTP Error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new \Exception("HTTP {$code}");
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new \Exception('Empty response');
        }

        // ── بارگذاری SimplePie — سازگار با وردپرس قدیمی و 6.4+ ──
        if (!class_exists('SimplePie')) {
            $simplepie = ABSPATH . WPINC . '/class-simplepie.php';
            if (file_exists($simplepie)) {
                include_once $simplepie;
            }
            // در WP 6.4+ کلاس اصلی namespaced است؛ نام جهانی را alias کن
            if (!class_exists('SimplePie') && class_exists('SimplePie\SimplePie')) {
                class_alias('SimplePie\SimplePie', 'SimplePie');
            }
        }

        // Parse با SimplePie
        $feed = new SimplePie();
        $feed->set_raw_data($body);
        $feed->enable_cache(false);
        $feed->init();

        if ($feed->error()) {
            throw new \Exception('Parse error: ' . $feed->error());
        }

        foreach ($feed->get_items() as $item) {
            $pubDate = $item->get_date('Y-m-d H:i:s');

            // فیلتر زمانی
            if (!$this->inWindow($pubDate)) {
                continue;
            }

            $content = $item->get_content();

            $items[] = [
                'title'        => $this->cleanText($item->get_title()),
                'content'      => $this->cleanHtml($content),
                'excerpt'      => $this->extractExcerpt($item->get_description(), $content),
                'link'         => $item->get_link(),
                'pub_date'     => $pubDate,
                'source_name'  => $sourceName,
                'source_group' => 'rss',
                'fetch_type'   => 'rss',
                'guid'         => $item->get_id() ?: $item->get_link(),
                'image'        => $this->extractFirstImage($item),
                'video'        => $this->extractVideo($content),
                'categories'   => $this->extractCategories($item),
            ];
        }

        return $items;
    }

    /* ========================================================================
       Helper Methods
       ======================================================================== */
    
    private function inWindow(?string $pubDate): bool
    {
        $ts = $pubDate ? strtotime($pubDate) : time();
        return $ts >= $this->cutoffTs;
    }

    private function extractFirstImage($item): ?string
    {
        // تلاش برای گرفتن از enclosure
        $enclosure = $item->get_enclosure();
        if ($enclosure && $enclosure->get_type() && strpos($enclosure->get_type(), 'image') !== false) {
            return $enclosure->get_link();
        }

        if ($enclosure && $enclosure->get_thumbnail()) {
            return $enclosure->get_thumbnail();
        }

        // استخراج از محتوا
        $content = $item->get_content();
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractVideo(string $content): string
    {
        if (preg_match('~https?://(?:www\.)?(?:youtube\.com/watch\?v=[\w-]+|youtu\.be/[\w-]+|aparat\.com/v/[\w-]+)~i', $content, $m)) {
            return $m[0];
        }
        return '';
    }

    private function extractCategories($item): array
    {
        $cats = [];
        $categories = $item->get_categories();
        if (is_array($categories)) {
            foreach ($categories as $cat) {
                $label = $cat->get_label();
                if (!empty($label)) {
                    $cats[] = $label;
                }
            }
        }
        return $cats;
    }

    private function extractExcerpt(string $description, string $fullContent): string
    {
        $text = strip_tags(!empty($description) ? $description : $fullContent);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text) > 250) {
            $text = mb_substr($text, 0, 250);
            $sp = mb_strrpos($text, ' ');
            if ($sp !== false) {
                $text = mb_substr($text, 0, $sp);
            }
            $text .= '...';
        }

        return $text;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    private function cleanHtml(string $html): string
    {
        return wp_kses($html, wp_kses_allowed_html('post'));
    }
}