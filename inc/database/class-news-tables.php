<?php
/**
 * NewsTables — مدیریت جداول دیتابیس سیستم اخبار (نسخه‌بندی‌شده)
 *
 * @package NextSafar\Database
 */

namespace NextSafar\Database;

if (!defined('ABSPATH')) {
    exit;
}

class NewsTables
{
    public const DB_VERSION        = '1.1.0';
    public const DB_VERSION_OPTION = 'nextsafar_news_db_version';

    /**
     * ✅ فقط وقتی نسخه اسکیما تغییر کرده باشد اجرا می‌شود.
     * قبلاً createAll() روی هر admin_init صدا زده می‌شد (۱۲+ کوئری اضافه در هر صفحه).
     */
    public static function maybeUpgrade(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        self::createAll();
        self::seedDefaultSources();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public static function createAll(): void
    {
        self::createSourcesTable();
        self::createDuplicatesTable();
        self::createAiLogTable();
        self::createKeywordsTable();
        self::createFilterLogTable();
    }

    private static function log(string $msg): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($msg);
        }
    }

    public static function createSourcesTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table} (
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
            PRIMARY KEY  (id),
            KEY type (type),
            KEY is_active (is_active)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function createDuplicatesTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_duplicates';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table} (
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
            PRIMARY KEY  (id),
            KEY url_hash (url_hash),
            KEY guid_hash (guid_hash),
            KEY title_hash (title_hash),
            KEY fuzzy_hash (fuzzy_hash)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function createAiLogTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_ai_log';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) NOT NULL DEFAULT 0,
            provider VARCHAR(50) NOT NULL DEFAULT 'gemini',
            model VARCHAR(100) NOT NULL DEFAULT '',
            tokens_used INT NOT NULL DEFAULT 0,
            cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function createKeywordsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_keywords';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword VARCHAR(191) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'positive',
            weight INT NOT NULL DEFAULT 10,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY is_active (is_active),
            KEY category (category)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function createFilterLogTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_filter_log';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table} (
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
            PRIMARY KEY  (id),
            KEY decision (decision),
            KEY created_at (created_at),
            KEY score (score)
        ) {$wpdb->get_charset_collate()};");
    }

    public static function seedDefaultSources(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_sources';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            self::createSourcesTable();
        }

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") > 0) {
            return;
        }

        $sources = [
            ['name' => 'GNews.io',              'url' => 'https://gnews.io/api/v4/search',                  'type' => 'api', 'group_name' => 'api',      'priority' => 5],
            ['name' => 'NewsData.io',           'url' => 'https://newsdata.io/api/1/latest',                'type' => 'api', 'group_name' => 'api',      'priority' => 5],
            ['name' => 'Currents API',          'url' => 'https://api.currentsapi.services/v1/search',      'type' => 'api', 'group_name' => 'api',      'priority' => 5],
            ['name' => 'Skift',                 'url' => 'https://skift.com/feed/',                         'type' => 'rss', 'group_name' => 'industry', 'priority' => 10],
            ['name' => 'Travel and Tour World', 'url' => 'https://www.travelandtourworld.com/feed/',        'type' => 'rss', 'group_name' => 'industry', 'priority' => 8],
            ['name' => 'Travel Daily News',     'url' => 'https://www.traveldailynews.com/feed/',           'type' => 'rss', 'group_name' => 'industry', 'priority' => 7],
        ];

        foreach ($sources as $source) {
            $wpdb->insert($table, $source + ['is_active' => 1]);
        }

        self::log('✅ [NewsTables] ' . count($sources) . ' منبع پیش‌فرض seed شد');
    }

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
        delete_option(self::DB_VERSION_OPTION);
    }
}