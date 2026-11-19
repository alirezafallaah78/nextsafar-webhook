<?php
/**
 * FilterPage — مدیریت فیلترها و کلمات کلیدی
 *
 * @package NextSafarAdminNews
 * @since 1.0.0
 */

namespace NextSafar\Admin\News;

if (!defined('ABSPATH')) {
    exit;
}

class FilterPage
{
    /**
     * ✅ assetها باید در admin_enqueue_scripts لود شوند، نه داخل render()
     * render() بعد از wp_head اجرا می‌شود و استایل در <head> چاپ نمی‌شود.
     * این enqueue برای همه صفحات nextsafar-news* کار می‌کند.
     */
    public static function enqueue_assets($hook): void
    {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if (strpos($page, 'nextsafar-news') !== 0) {
            return;
        }

        // ✅ بدون dependency — اگر handle دیگری ثبت نشده باشد، استایل چاپ نمی‌شد
        wp_enqueue_style(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin.css',
            array(),
            NEXTSAFAR_VERSION
        );

        // ✅ لایه UI جامع (بعد از CSS اصلی لود می‌شود)
        wp_enqueue_style(
            'nextsafar-news-admin-ui',
            NEXTSAFAR_URL . 'assets/admin/css/pages/news-admin-ui.css',
            array('nextsafar-news-admin'),
            NEXTSAFAR_VERSION
        );

        // ✅ فقط jquery (همیشه در ادمین ثبت شده است)
        wp_enqueue_script(
            'nextsafar-news-admin',
            NEXTSAFAR_URL . 'assets/admin/js/pages/news-admin.js',
            array('jquery'),
            NEXTSAFAR_VERSION,
            true
        );

        wp_localize_script('nextsafar-news-admin', 'nextsafarNews', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('nextsafar_news_admin'),
        ));
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز');
        }

        global $wpdb;

        // ── آمار ──
        $stats = array(
            'published'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'travelnews' AND post_status = 'publish'"),
            'draft'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'travelnews' AND post_status = 'draft'"),
            'deleted'    => 0,
            'ai_checked' => 0,
        );

        $log_table = $wpdb->prefix . 'ns_news_filter_log';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $log_table)) === $log_table) {
            $stats['deleted']    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$log_table} WHERE decision = 'delete'");
            $stats['ai_checked'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$log_table} WHERE ai_checked = 1");
        }

        // ✅ کلمات کلیدی — چیزی که view واقعاً نیاز دارد
        $saved_filters = get_option('nextsafar_news_filters', array());
        $keywords      = self::load_keywords($saved_filters);
        $positive      = $keywords['positive'];
        $negative      = $keywords['negative'];

        include NEXTSAFAR_PATH . 'views/admin/news/filter.php';
    }

    /**
     * خواندن کلمات کلیدی از جدول، با fallback به option
     */
    private static function load_keywords($saved_filters): array
    {
        global $wpdb;

        $buckets = array('positive' => array(), 'negative' => array());
        $table   = $wpdb->prefix . 'ns_news_keywords';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results(
                "SELECT id, keyword, type, weight, category
                 FROM {$table}
                 WHERE is_active = 1
                 ORDER BY weight DESC, id ASC",
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $type = ($row['type'] === 'negative') ? 'negative' : 'positive';
                $buckets[$type][] = array(
                    'id'       => (int) $row['id'],
                    'keyword'  => (string) $row['keyword'],
                    'weight'   => (int) $row['weight'],
                    'category' => (string) $row['category'],
                );
            }
        }

        // fallback: اگر جدول خالی/ناموجود بود، از option بخوان
        if (empty($buckets['positive']) && empty($buckets['negative']) && is_array($saved_filters)) {
            foreach (array('positive', 'negative') as $type) {
                if (empty($saved_filters[$type]) || !is_array($saved_filters[$type])) {
                    continue;
                }
                foreach ($saved_filters[$type] as $kw) {
                    if (is_array($kw)) {
                        $text   = (string) ($kw['keyword'] ?? '');
                        $weight = (int) ($kw['weight'] ?? 15);
                        $id     = (int) ($kw['id'] ?? 0);
                    } else {
                        $text   = (string) $kw;
                        $weight = 15;
                        $id     = 0;
                    }
                    if ($text === '') {
                        continue;
                    }
                    $buckets[$type][] = array(
                        'id'       => $id,
                        'keyword'  => $text,
                        'weight'   => $weight,
                        'category' => 'general',
                    );
                }
            }
        }

        return $buckets;
    }
}

// ✅ ثبت زودهنگام (قبل از wp_head) — اولویت ۲۰ تا dependencyها ثبت شده باشند
add_action('admin_enqueue_scripts', array('NextSafar\Admin\News\FilterPage', 'enqueue_assets'), 20);