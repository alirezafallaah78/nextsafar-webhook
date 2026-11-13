<?php
namespace NextSafar\Admin\PostGenerator;

use NextSafar\Api\Sync\BatchSync;

if (!defined('ABSPATH')) exit;

class AjaxHandler
{
    public static function init(): void
    {
        add_action('wp_ajax_nextsafar_pg_start', [__CLASS__, 'startSync']);
        add_action('wp_ajax_nextsafar_pg_batch', [__CLASS__, 'processBatch']);
        add_action('wp_ajax_nextsafar_pg_test_api', [__CLASS__, 'testApi']);
        add_action('wp_ajax_nextsafar_pg_status', [__CLASS__, 'getStatus']);
    }

    public static function startSync(): void
    {
        check_ajax_referer('nextsafar_post_generator', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        }

        $sync_type = sanitize_text_field($_POST['sync_type'] ?? '');
        $location  = sanitize_text_field($_POST['location'] ?? '');
        $limit     = (int) ($_POST['limit'] ?? 10);
        
        // ✅ خواندن منبع از تنظیمات (نه از POST)
        $source = (string) get_option('nextsafar_active_provider', 'searchapi');

        $allowed_types = ['hotel', 'destination', 'restaurant', 'hospital', 'airport'];
        if (!in_array($sync_type, $allowed_types, true)) {
            wp_send_json_error(['message' => 'نوع محتوا معتبر نیست']);
        }

        if (empty($location)) {
            wp_send_json_error(['message' => 'مقصد جستجو خالی است']);
        }

        $key = $source === 'serpapi'
            ? get_option('nextsafar_serpapi_key', '')
            : get_option('nextsafar_searchapi_key', '');

        if (empty($key)) {
            wp_send_json_error([
                'message' => "کلید {$source} تنظیم نشده. لطفاً به صفحه تنظیمات بروید."
            ]);
        }

        try {
            $result = \NextSafar\Api\Sync\BatchSync::startSync($sync_type, $location, $source, $limit);
            $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function processBatch(): void
    {
        check_ajax_referer('nextsafar_post_generator', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز']);

        try {
            wp_send_json_success(BatchSync::processNextBatch());
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function testApi(): void
    {
        check_ajax_referer('nextsafar_post_generator', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز']);

        $results = [];

        foreach (['serpapi' => 'nextsafar_serpapi_key', 'searchapi' => 'nextsafar_searchapi_key'] as $provider => $option) {
            $key = get_option($option, '');
            if (empty($key)) {
                $results[$provider] = ['status' => 'warning', 'message' => 'کلید تنظیم نشده'];
                continue;
            }

            try {
                $client = $provider === 'serpapi'
                    ? new \NextSafar\Api\Clients\SerpApiClient($key)
                    : new \NextSafar\Api\Clients\SearchApiClient($key);

                $test = $client->searchHotels('Dubai', ['limit' => 1]);
                $results[$provider] = is_wp_error($test)
                    ? ['status' => 'error', 'message' => $test->get_error_message()]
                    : ['status' => 'success', 'message' => 'متصل'];
            } catch (\Throwable $e) {
                $results[$provider] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        wp_send_json_success($results);
    }

    public static function getStatus(): void
    {
        check_ajax_referer('nextsafar_post_generator', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز']);

        $state = get_option(BatchSync::SYNC_STATE_KEY, []);
        if (empty($state)) wp_send_json_success(['status' => 'idle']);

        wp_send_json_success([
            'status'           => $state['status'] ?? 'idle',
            'sync_type'        => $state['sync_type'] ?? '',
            'total'            => $state['total'] ?? 0,
            'processed'        => $state['processed'] ?? 0,
            'created'          => $state['created'] ?? 0,
            'updated'          => $state['updated'] ?? 0,
            'failed'           => $state['failed'] ?? 0,
            'progress_percent' => ($state['total'] ?? 0) > 0 ? round(($state['processed'] / $state['total']) * 100) : 0,
        ]);
    }
}