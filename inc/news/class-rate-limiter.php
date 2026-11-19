<?php
/**
 * RateLimiter — کنترل نرخ درخواست‌ها بدون بلاک‌کردن درخواست وب
 *
 * ✅ state پایدار (transient) به‌جای static per-request
 * ✅ در وب هرگز نمی‌خوابد؛ فقط در cron مکث کوتاه و کراندار
 *
 * @package NextSafar\News
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter
{
    private const MAX_BURST = [
        'rss'        => 10,
        'gnews'      => 5,
        'newsdata'   => 5,
        'currents'   => 5,
        'mediastack' => 5,
        'searchapi'  => 3,
        'serpapi'    => 3,
        'gemini'     => 5,
    ];

    private const STORE_PREFIX = 'ns_rl_';
    private const STORE_TTL    = 600;

    /**
     * سازگاری عقب‌رو — دیگر در وب نمی‌خوابد.
     */
    public static function waitIfNeeded(string $service, float $seconds = 1.0): void
    {
        $delay = self::secondsUntilAllowed($service, $seconds);

        if ($delay > 0 && defined('DOING_CRON') && DOING_CRON) {
            $sleep = min($delay, 3.0); // ✅ کراندار
            if ($sleep > 0.05) {
                usleep((int) ($sleep * 1000000));
            }
        }

        self::markRequest($service);
    }

    /**
     * ✅ چند ثانیه تا مجازشدن درخواست بعدی باقی مانده؟ (۰ = مجاز)
     */
    public static function secondsUntilAllowed(string $service, float $minInterval = 1.0): float
    {
        $state = get_transient(self::STORE_PREFIX . $service);
        if (!is_array($state)) {
            return 0.0;
        }

        $last  = (float) ($state['last'] ?? 0);
        $count = (int) ($state['count'] ?? 0);
        if ($last <= 0) {
            return 0.0;
        }

        $required = $minInterval;
        $maxBurst = self::MAX_BURST[$service] ?? 5;
        if ($count >= $maxBurst) {
            $required = max($required, 3.0);
        }

        $elapsed = microtime(true) - $last;
        return $elapsed >= $required ? 0.0 : round($required - $elapsed, 2);
    }

    /**
     * ✅ ثبت زمان درخواست (پایدار بین درخواست‌ها)
     */
    public static function markRequest(string $service): void
    {
        $state    = get_transient(self::STORE_PREFIX . $service);
        $count    = is_array($state) ? (int) ($state['count'] ?? 0) : 0;
        $maxBurst = self::MAX_BURST[$service] ?? 5;
        $count    = ($count >= $maxBurst) ? 0 : $count + 1;

        set_transient(
            self::STORE_PREFIX . $service,
            array('last' => microtime(true), 'count' => $count),
            self::STORE_TTL
        );
    }

    public static function reset(string $service): void
    {
        delete_transient(self::STORE_PREFIX . $service);
    }

    public static function resetAll(): void
    {
        foreach (array_keys(self::MAX_BURST) as $service) {
            self::reset($service);
        }
    }

    public static function getStatus(): array
    {
        $out = array();
        foreach (array_keys(self::MAX_BURST) as $service) {
            $state = get_transient(self::STORE_PREFIX . $service);
            $out[$service] = is_array($state) ? $state : array('last' => 0, 'count' => 0);
        }
        return $out;
    }
}