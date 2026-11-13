<?php
/**
 * RateLimiter — کنترل نرخ درخواست‌های API
 *
 * از ارسال بیش از حد درخواست به API ها جلوگیری می‌کند تا از بلاک شدن
 * و مصرف بی‌رویه quota جلوگیری شود.
 *
 * هر service (rss, gnews, newsdata, ...) محدودیت خود را دارد.
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter
{
    /** @var array<string, float> زمان آخرین درخواست هر سرویس */
    private static array $lastRequest = [];

    /** @var array<string, int> شمارنده درخواست‌های متوالی */
    private static array $requestCount = [];

    /**
     * حداکثر درخواست‌های پشت سر هم قبل از اجبار به مکث
     */
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

    /* ========================================================================
       waitIfNeeded — اگر لازم است صبر کن
       ======================================================================== */
    
    /**
     * صبر کن اگر زمان کافی از آخرین درخواست نگذشته
     *
     * @param string $service نام سرویس (rss, gnews, newsdata, ...)
     * @param float  $seconds حداقل فاصله بین درخواست‌ها (ثانیه)
     */
    public static function waitIfNeeded(string $service, float $seconds = 1.0): void
    {
        $now = microtime(true);
        $last = self::$lastRequest[$service] ?? 0;
        $count = self::$requestCount[$service] ?? 0;
        $elapsed = $now - $last;

        // افزایش شمارنده
        self::$requestCount[$service] = $count + 1;

        // اگر به محدودیت burst رسیدیم، مکث اجباری
        $maxBurst = self::MAX_BURST[$service] ?? 5;
        if (self::$requestCount[$service] >= $maxBurst) {
            $seconds = max($seconds, 3.0); // حداقل 3 ثانیه مکث
            self::$requestCount[$service] = 0;
        }

        // اگر زمان کافی نگذشته، صبر کن
        if ($elapsed < $seconds) {
            $sleepMicroseconds = ($seconds - $elapsed) * 1000000;
            usleep((int) $sleepMicroseconds);
        }

        self::$lastRequest[$service] = microtime(true);
    }

    /* ========================================================================
       Reset Methods
       ======================================================================== */
    
    /**
     * ریست وضعیت یک سرویس خاص
     */
    public static function reset(string $service): void
    {
        unset(self::$lastRequest[$service]);
        unset(self::$requestCount[$service]);
    }

    /**
     * ریست همه سرویس‌ها
     */
    public static function resetAll(): void
    {
        self::$lastRequest = [];
        self::$requestCount = [];
    }

    /* ========================================================================
       Debug
       ======================================================================== */
    
    /**
     * دریافت وضعیت فعلی (برای دیباگ)
     *
     * @return array{last_request: array, request_count: array}
     */
    public static function getStatus(): array
    {
        return [
            'last_request'  => self::$lastRequest,
            'request_count' => self::$requestCount,
        ];
    }
}