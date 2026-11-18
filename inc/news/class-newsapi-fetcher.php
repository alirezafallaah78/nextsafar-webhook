<?php
/**
 * NewsApiFetcher — دریافت اخبار از API های خبری
 *
 * پشتیبانی از 3 API:
 * - GNews.io (max 10 مقاله در پلن رایگان)
 * - NewsData.io (max 10 مقاله در پلن رایگان)
 * - Currents API (unlimited با زبان انگلیسی)
 *
 * ویژگی‌ها:
 * - Fallback خودکار بین زبان‌ها (فارسی → انگلیسی)
 * - به‌روزرسانی آمار منابع در دیتابیس
 * - Rate limiting داخلی
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class NewsApiFetcher
{
    private int $timeout = 30;

    /* ========================================================================
       fetchAll — دریافت از همه API ها
       ======================================================================== */
    
    /**
     * دریافت از GNews + NewsData + Currents
     *
     * @return array لیست اخبار
     */
    public function fetchAll(): array
    {
        $allNews = [];

        // GNews
        $gnewsItems = $this->fetchGNews();
        $allNews = array_merge($allNews, $gnewsItems);

        // NewsData
        $newsdataItems = $this->fetchNewsData();
        $allNews = array_merge($allNews, $newsdataItems);

        // Currents
        $currentsItems = $this->fetchCurrents();
        $allNews = array_merge($allNews, $currentsItems);

        return $allNews;
    }

    /* ========================================================================
       Helper: Update Source Stats
       ======================================================================== */
    
    private function updateSourceStats(string $sourceName, int $fetchedCount, ?string $error = null): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, total_fetched FROM {$table} WHERE name = %s",
            $sourceName
        ));

        if ($existing) {
            $updateData = [
                'last_fetch'    => current_time('mysql'),
                'total_fetched' => (int) $existing->total_fetched + $fetchedCount,
            ];

            if ($error) {
                $updateData['error_message'] = mb_substr($error, 0, 500);
            } else {
                $updateData['last_success'] = current_time('mysql');
                $updateData['error_message'] = null;
            }

            $wpdb->update($table, $updateData, ['id' => $existing->id]);
        }
    }

    /* ========================================================================
       GNews.io
       ======================================================================== */
    
    private function fetchGNews(): array
    {
        $apiKey = (string) get_option('nextsafar_gnews_api_key', '');

        if (empty($apiKey)) {
            $this->updateSourceStats('GNews.io', 0, 'API key not set');
            return [];
        }

        RateLimiter::waitIfNeeded('gnews', 2.0);

        // ابتدا فارسی، سپس انگلیسی
        $items = $this->fetchGNewsWithLang($apiKey, 'fa', 'ir');
        if (empty($items)) {
            $items = $this->fetchGNewsWithLang($apiKey, 'en', '');
        }

        if (empty($items)) {
            $this->updateSourceStats('GNews.io', 0, 'No articles returned');
        } else {
            $this->updateSourceStats('GNews.io', count($items));
        }

        return $items;
    }

    private function fetchGNewsWithLang(string $apiKey, string $lang, string $country): array
    {
        $params = [
            'q'      => 'tourism OR travel OR hotel OR tour OR flight OR destination',
            'lang'   => $lang,
            'max'    => 10, // حداکثر پلن رایگان
            'apikey' => $apiKey,
        ];

        if (!empty($country)) {
            $params['country'] = $country;
        }

        $url = 'https://gnews.io/api/v4/search?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout'   => $this->timeout,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['articles'])) {
            return [];
        }

        $items = [];
        foreach ($data['articles'] as $article) {
            $items[] = [
                'source_name'  => 'GNews.io',
                'source_id'    => 0,
                'source_group' => 'api',
                'title'        => $article['title'] ?? '',
                'link'         => $article['url'] ?? '',
                'guid'         => $article['url'] ?? '',
                'excerpt'      => $article['description'] ?? '',
                'content'      => trim(($article['title'] ?? '') . "\n" . ($article['description'] ?? '') . "\n" . ($article['content'] ?? '')),
                'image'        => $article['image'] ?? '',
                'pub_date'     => !empty($article['publishedAt'])
                    ? date('Y-m-d H:i:s', strtotime($article['publishedAt']))
                    : current_time('mysql'),
                'author'       => $article['source']['name'] ?? 'GNews',
                'categories'   => [],
                'fetch_type'   => 'api',
            ];
        }

        error_log("🌐 [GNews.io] ({$lang}): " . count($items) . " items");
        return $items;
    }

    /* ========================================================================
       NewsData.io
       ======================================================================== */
    
    private function fetchNewsData(): array
    {
        $apiKey = (string) get_option('nextsafar_newsdata_api_key', '');

        if (empty($apiKey)) {
            $this->updateSourceStats('NewsData.io', 0, 'API key not set');
            return [];
        }

        RateLimiter::waitIfNeeded('newsdata', 2.0);

        $items = $this->fetchNewsDataWithLang($apiKey, 'fa');
        if (empty($items)) {
            $items = $this->fetchNewsDataWithLang($apiKey, 'en');
        }

        if (empty($items)) {
            $this->updateSourceStats('NewsData.io', 0, 'No results returned');
        } else {
            $this->updateSourceStats('NewsData.io', count($items));
        }

        return $items;
    }

    private function fetchNewsDataWithLang(string $apiKey, string $lang): array
    {
        $params = [
            'q'        => 'tourism OR travel OR hotel OR tour',
            'language' => $lang,
            'size'     => 10, // حداکثر پلن رایگان
            'apikey'   => $apiKey,
        ];

        $url = 'https://newsdata.io/api/1/latest?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout'   => $this->timeout,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['results'])) {
            return [];
        }

        $items = [];
        foreach ($data['results'] as $article) {
            $items[] = [
                'source_name'  => 'NewsData.io',
                'source_id'    => 0,
                'source_group' => 'api',
                'title'        => $article['title'] ?? '',
                'link'         => $article['link'] ?? '',
                'guid'         => $article['link'] ?? '',
                'excerpt'      => $article['description'] ?? '',
                'content'      => trim(($article['title'] ?? '') . "\n" . ($article['description'] ?? '')),
                'image'        => $article['image_url'] ?? '',
                'pub_date'     => !empty($article['pubDate'])
                    ? date('Y-m-d H:i:s', strtotime($article['pubDate']))
                    : current_time('mysql'),
                'author'       => $article['source_id'] ?? 'NewsData',
                'categories'   => $article['category'] ?? [],
                'fetch_type'   => 'api',
            ];
        }

        error_log("🌐 [NewsData.io] ({$lang}): " . count($items) . " items");
        return $items;
    }

    /* ========================================================================
       Currents API
       ======================================================================== */
    
    private function fetchCurrents(): array
    {
        $apiKey = (string) get_option('nextsafar_currents_api_key', '');

        if (empty($apiKey)) {
            $this->updateSourceStats('Currents API', 0, 'API key not set');
            return [];
        }

        RateLimiter::waitIfNeeded('currents', 2.0);

        $items = $this->fetchCurrentsLatest($apiKey);
        if (empty($items)) {
            $items = $this->fetchCurrentsSearch($apiKey);
        }

        if (empty($items)) {
            $this->updateSourceStats('Currents API', 0, 'No news returned');
        } else {
            $this->updateSourceStats('Currents API', count($items));
        }

        return $items;
    }

    private function fetchCurrentsLatest(string $apiKey): array
    {
        $params = [
            'apiKey'   => $apiKey,
            'language' => 'en', // پلن رایگان فقط انگلیسی
        ];

        $url = 'https://api.currentsapi.services/v1/latest-news?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout'   => $this->timeout,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['news'])) {
            return [];
        }

        $filtered = $this->filterCurrentsTravelNews($data['news']);
        return $this->parseCurrentsResponse($filtered);
    }

    private function fetchCurrentsSearch(string $apiKey): array
    {
        $params = [
            'apiKey'   => $apiKey,
            'language' => 'en',
            'keywords' => 'travel tourism hotel tour vacation flight',
        ];

        $url = 'https://api.currentsapi.services/v1/search?' . http_build_query($params);

        $response = wp_remote_get($url, [
            'timeout'   => $this->timeout,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['news'])) {
            return [];
        }

        return $this->parseCurrentsResponse($data['news']);
    }

    private function filterCurrentsTravelNews(array $news): array
    {
        $travelKeywords = [
            'travel', 'tourism', 'tourist', 'hotel', 'resort', 'flight',
            'airline', 'airport', 'destination', 'vacation', 'holiday',
            'beach', 'cruise', 'tour', 'adventure', 'backpack', 'hostel',
            'restaurant', 'museum', 'attraction', 'landmark', 'heritage',
        ];

        $filtered = [];
        foreach ($news as $article) {
            $text = strtolower(($article['title'] ?? '') . ' ' . ($article['description'] ?? ''));
            foreach ($travelKeywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $filtered[] = $article;
                    break;
                }
            }
        }

        return $filtered;
    }

    private function parseCurrentsResponse(array $news): array
    {
        $items = [];
        foreach ($news as $article) {
            $items[] = [
                'source_name'  => 'Currents API',
                'source_id'    => 0,
                'source_group' => 'api',
                'title'        => $article['title'] ?? '',
                'link'         => $article['url'] ?? '',
                'guid'         => $article['url'] ?? '',
                'excerpt'      => $article['description'] ?? '',
                'content'      => trim(($article['title'] ?? '') . "\n" . ($article['description'] ?? '')),
                'image'        => $article['image'] ?? '',
                'pub_date'     => !empty($article['published'])
                    ? date('Y-m-d H:i:s', strtotime($article['published']))
                    : current_time('mysql'),
                'author'       => $article['author'] ?? 'Currents',
                'categories'   => [],
                'fetch_type'   => 'api',
            ];
        }

        error_log("🌐 [Currents API] " . count($items) . " items");
        return $items;
    }
}