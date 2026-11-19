<?php

namespace NextSafar;

if (!defined('ABSPATH')) exit;

/**
 * Deactivator — Manages plugin deactivation
 *
 * Note: Tables are NOT deleted here. They are removed only on uninstall,
 * so temporary deactivation preserves the user's data.
 */
class Deactivator {

    const DB_VERSION_OPTION = 'nextsafar_db_version';

    public static function deactivate() {
        self::clear_all_cron_jobs();
        flush_rewrite_rules();
        self::clear_temporary_transients();
        update_option('nextsafar_deactivated_at', current_time('mysql'));
        self::log('✅ NextSafar Core: Plugin deactivated successfully');
    }

    /** لاگ فقط در حالت دیباگ */
    private static function log(string $msg): void {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($msg);
        }
    }

    private static function clear_all_cron_jobs(): void {
        $cron_hooks = [
            // News system crons
            'nextsafar_news_cron_hook',
            'nextsafar_news_hourly_tick',
            'nextsafar_news_retry_hook',
            'nextsafar_news_batch_fetch',
            // Other crons
            'ns_enrich_locations_cron',
            'nextsafar_exchange_update_cron',
            'nextsafar_sync_cron',
        ];

        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
            self::log('🗑️ NextSafar: Cron hook cleared: ' . $hook);
        }
    }

    private static function clear_temporary_transients(): void {
        global $wpdb;

        $patterns = [
            'ns_ai_rates_cache',
            'visa_api_rates_cache',
            'nextsafar_news_overview_stats',
            'nextsafar_news_public_stats',
            'ns_ai_consecutive_fails',
            'ns_ai_fail_count',
            'ns_ai_disabled_until',
        ];

        foreach ($patterns as $pattern) {
            delete_transient($pattern);
        }

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_ns_maps_%'
                OR option_name LIKE '_transient_timeout_ns_maps_%'"
        );

        self::log('🗑️ NextSafar: Temporary transients cleared');
    }
}