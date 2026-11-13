<?php
/**
 * NewsAjaxHandler — مدیریت همه درخواست‌های AJAX سیستم اخبار
 *
 * Actions:
 * - nextsafar_news_batch_start     : شروع سینک دستی
 * - nextsafar_news_batch_process   : پردازش batch بعدی
 * - nextsafar_news_test_api        : تست Gemini API
 * - nextsafar_news_get_models      : دریافت لیست مدل‌های Gemini
 * - nextsafar_news_reset_sources   : بازنشانی منابع پیش‌فرض
 * - nextsafar_news_get_sources     : دریافت لیست منابع
 * - nextsafar_news_add_keyword     : افزودن کلمه کلیدی
 * - nextsafar_news_delete_keyword  : حذف کلمه کلیدی
 * - nextsafar_news_get_history     : دریافت تاریخچه فیلتر
 * - nextsafar_news_reset_ai        : ریست cooldown AI
 *
 * @package NextSafar\Admin\News
 * @since 1.0.0
 */

namespace NextSafar\Admin\News;

use NextSafar\News\NewsBatchSync;
use NextSafar\News\GeminiClient;
use NextSafar\News\AIRewriter;
use NextSafar\News\NewsFilter;
use NextSafar\Database\NewsTables;

if (!defined('ABSPATH')) {
    exit;
}

class NewsAjaxHandler
{
    public static function init(): void
    {
        // Manual sync
        add_action('wp_ajax_nextsafar_news_batch_start', [__CLASS__, 'batchStart']);
        add_action('wp_ajax_nextsafar_news_batch_process', [__CLASS__, 'batchProcess']);
        
        // Gemini API
        add_action('wp_ajax_nextsafar_news_test_api', [__CLASS__, 'testApi']);
        add_action('wp_ajax_nextsafar_news_get_models', [__CLASS__, 'getModels']);
        
        // Sources
        add_action('wp_ajax_nextsafar_news_reset_sources', [__CLASS__, 'resetSources']);
        add_action('wp_ajax_nextsafar_news_get_sources', [__CLASS__, 'getSources']);
        
        // Keywords
        add_action('wp_ajax_nextsafar_news_add_keyword', [__CLASS__, 'addKeyword']);
        add_action('wp_ajax_nextsafar_news_delete_keyword', [__CLASS__, 'deleteKeyword']);
        add_action('wp_ajax_nextsafar_news_get_history', [__CLASS__, 'getHistory']);
        
        // AI management
        add_action('wp_ajax_nextsafar_news_reset_ai', [__CLASS__, 'resetAi']);
    }

    /* ========================================================================
       Manual Sync
       ======================================================================== */
    
    public static function batchStart(): void
    {
        self::checkPermission();
        
        $mode = sanitize_key($_POST['mode'] ?? 'quick');
        
        if (!in_array($mode, ['quick', 'ai'], true)) {
            $mode = 'quick';
        }

        try {
            $result = NewsBatchSync::startSync($mode);
            $result['success'] 
                ? wp_send_json_success($result) 
                : wp_send_json_error($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function batchProcess(): void
    {
        self::checkPermission();

        try {
            wp_send_json_success(NewsBatchSync::processNextBatch());
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /* ========================================================================
       Gemini API
       ======================================================================== */
    
public static function testApi(): void
{
    self::checkPermission();

    $apiKey = (string) get_option('nextsafar_gemini_api_key', '');
    if (empty($apiKey)) {
        wp_send_json_error(['message' => 'کلید Gemini API تنظیم نشده است']);
        return;
    }

    $client = new GeminiClient(15);

    // تست با models.list — بدون مصرف سهمیه generateContent
    if (!$client->ping()) {
        wp_send_json_error([
            'message' => 'خطا در ارتباط با Gemini: ' . ($client->getLastError() ?? 'نامشخص'),
        ]);
        return;
    }

    wp_send_json_success([
        'message'  => '✅ اتصال برقرار است و کلید معتبر است (بدون مصرف سهمیه روزانه)',
        'response' => \NextSafar\News\GeminiQuota::statusText(),
        'tokens'   => 0,
    ]);
}

    public static function getModels(): void
    {
        self::checkPermission();
        wp_send_json_success(GeminiClient::getModelsList());
    }

    /* ========================================================================
       Sources
       ======================================================================== */
    
    public static function resetSources(): void
    {
        self::checkPermission();

        try {
            NewsTables::seedDefaultSources();
            wp_send_json_success(['message' => '✅ منابع پیش‌فرض بازنشانی شدند']);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function getSources(): void
    {
        self::checkPermission();
        wp_send_json_success(\NextSafar\News\NewsSync::getSourcesStats());
    }

    /* ========================================================================
       Keywords
       ======================================================================== */
    
    public static function addKeyword(): void
    {
        self::checkPermission();

        $keyword = sanitize_text_field(trim($_POST['keyword'] ?? ''));
        $type = sanitize_text_field($_POST['type'] ?? 'positive');
        $weight = (int) ($_POST['weight'] ?? ($type === 'negative' ? -20 : 10));
        $category = sanitize_text_field($_POST['category'] ?? 'general');

        if (empty($keyword)) {
            wp_send_json_error(['message' => 'کلمه کلیدی نمی‌تواند خالی باشد']);
            return;
        }

        if (!in_array($type, ['positive', 'negative'], true)) {
            wp_send_json_error(['message' => 'نوع نامعتبر']);
            return;
        }

        if (NewsFilter::addKeyword($keyword, $type, $weight, $category)) {
            wp_send_json_success(['message' => '✅ کلمه کلیدی اضافه شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در افزودن']);
        }
    }

    public static function deleteKeyword(): void
    {
        self::checkPermission();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => 'ID نامعتبر']);
            return;
        }

        if (NewsFilter::deleteKeyword($id)) {
            wp_send_json_success(['message' => '✅ کلمه کلیدی حذف شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف']);
        }
    }

    public static function getHistory(): void
    {
        self::checkPermission();

        $limit = min(100, max(10, (int) ($_POST['limit'] ?? 50)));
        wp_send_json_success(NewsFilter::getFilterHistory($limit));
    }

    /* ========================================================================
       AI Management
       ======================================================================== */
    
    public static function resetAi(): void
    {
        self::checkPermission();
        AIRewriter::resetAll();
        wp_send_json_success(['message' => '✅ وضعیت AI ریست شد']);
    }

    /* ========================================================================
       Permission Check
       ======================================================================== */
    
    private static function checkPermission(): void
    {
        check_ajax_referer('nextsafar_news_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }
    }
}