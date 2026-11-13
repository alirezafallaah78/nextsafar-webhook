<?php
/**
 * NewsTables — مدیریت جداول دیتابیس سیستم اخبار
 *
 * جداول:
 * - ns_news_sources     : منابع RSS و API
 * - ns_news_duplicates  : تشخیص اخبار تکراری
 * - ns_news_ai_log      : لاگ استفاده از هوش مصنوعی
 * - ns_news_keywords    : کلمات کلیدی فیلتر
 * - ns_news_filter_log  : لاگ تصمیمات فیلتر
 *
 * @package NextSafar\Database
 * @since 1.0.0
 */

namespace NextSafar\Database;

if (!defined('ABSPATH')) {
    exit;
}

class NewsTables
{
    /**
     * ساخت همه جداول در یک فراخوانی
     */
    public static function createAll(): void
    {
        self::createSourcesTable();
        self::createDuplicatesTable();
        self::createAiLogTable();
        self::createKeywordsTable();
        self::createFilterLogTable();
        
        error_log('✅ [NewsTables] همه جداول اخبار ساخته شدند');
    }

    /* ========================================================================
       1. Sources Table (منابع RSS و API)
       ======================================================================== */
    
    public static function createSourcesTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            url TEXT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'rss',
            group_name VARCHAR(50) NOT NULL DEFAULT 'general',
            priority INT NOT NULL DEFAULT 5,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_fetch DATETIME NULL DEFAULT NULL,
            last_success DATETIME NULL DEFAULT NULL,
            total_fetched BIGINT(20) NOT NULL DEFAULT 0,
            total_duplicates BIGINT(20) NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) {$wpdb->get_charset_collate()};");
    }

    /* ========================================================================
       2. Duplicates Table (تشخیص تکراری)
       ======================================================================== */
    
    public static function createDuplicatesTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_duplicates';
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url_hash CHAR(32) NOT NULL DEFAULT '',
            guid TEXT NULL,
            guid_hash CHAR(32) NOT NULL DEFAULT '',
            title_hash CHAR(32) NOT NULL DEFAULT '',
            fuzzy_hash VARCHAR(191) NOT NULL DEFAULT '',
            source_name VARCHAR(191) NOT NULL DEFAULT '',
            post_id BIGINT(20) NOT NULL DEFAULT 0,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url_hash (url_hash),
            KEY guid_hash (guid_hash),
            KEY title_hash (title_hash),
            KEY fuzzy_hash (fuzzy_hash)
        ) {$wpdb->get_charset_collate()};");
    }

    /* ========================================================================
       3. AI Log Table (لاگ Gemini)
       ======================================================================== */
    
    public static function createAiLogTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_ai_log';
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) NOT NULL DEFAULT 0,
            provider VARCHAR(50) NOT NULL DEFAULT 'gemini',
            model VARCHAR(100) NOT NULL DEFAULT '',
            tokens_used INT NOT NULL DEFAULT 0,
            cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) {$wpdb->get_charset_collate()};");
    }

    /* ========================================================================
       4. Keywords Table (کلمات کلیدی)
       ======================================================================== */
    
    public static function createKeywordsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_keywords';
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword VARCHAR(191) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'positive',
            weight INT NOT NULL DEFAULT 10,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active),
            KEY category (category)
        ) {$wpdb->get_charset_collate()};");
    }

    /* ========================================================================
       5. Filter Log Table (لاگ تصمیمات فیلتر)
       ======================================================================== */
    
    public static function createFilterLogTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_filter_log';
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            news_title VARCHAR(500) NOT NULL,
            source_name VARCHAR(100) NULL,
            score INT DEFAULT 0,
            decision ENUM('publish', 'draft', 'review', 'delete') DEFAULT 'review',
            reason TEXT NULL,
            positive_matches TEXT NULL,
            negative_matches TEXT NULL,
            ai_checked TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY decision (decision),
            KEY created_at (created_at),
            KEY score (score)
        ) {$wpdb->get_charset_collate()};");
    }

    /* ========================================================================
       Seed: منابع پیش‌فرض
       ======================================================================== */
    
    public static function seedDefaultSources(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            self::createSourcesTable();
        }

        // فقط اگر خالی است، seed کن
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") > 0) {
            return;
        }

        $sources = [
            // API sources
            [
                'name'       => 'GNews.io',
                'url'        => 'https://gnews.io/api/v4/search',
                'type'       => 'api',
                'group_name' => 'api',
                'priority'   => 5,
            ],
            [
                'name'       => 'NewsData.io',
                'url'        => 'https://newsdata.io/api/1/latest',
                'type'       => 'api',
                'group_name' => 'api',
                'priority'   => 5,
            ],
            [
                'name'       => 'Currents API',
                'url'        => 'https://api.currentsapi.services/v1/search',
                'type'       => 'api',
                'group_name' => 'api',
                'priority'   => 5,
            ],
            // RSS feeds (منابع معتبر و تست شده)
            [
                'name'       => 'Skift',
                'url'        => 'https://skift.com/feed/',
                'type'       => 'rss',
                'group_name' => 'industry',
                'priority'   => 10,
            ],
            [
                'name'       => 'Travel and Tour World',
                'url'        => 'https://www.travelandtourworld.com/feed/',
                'type'       => 'rss',
                'group_name' => 'industry',
                'priority'   => 8,
            ],
            [
                'name'       => 'Travel Daily News',
                'url'        => 'https://www.traveldailynews.com/feed/',
                'type'       => 'rss',
                'group_name' => 'industry',
                'priority'   => 7,
            ],
        ];

        foreach ($sources as $source) {
            $wpdb->insert($table, $source + ['is_active' => 1]);
        }

        error_log('✅ [NewsTables] ' . count($sources) . ' منبع پیش‌فرض seed شد');
    }

    /* ========================================================================
       حذف همه جداول (برای uninstall)
       ======================================================================== */
    
    public static function dropAll(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'ns_news_sources',
            $wpdb->prefix . 'ns_news_duplicates',
            $wpdb->prefix . 'ns_news_ai_log',
            $wpdb->prefix . 'ns_news_keywords',
            $wpdb->prefix . 'ns_news_filter_log',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        error_log('🗑️ [NewsTables] همه جداول اخبار حذف شدند');
    }
}