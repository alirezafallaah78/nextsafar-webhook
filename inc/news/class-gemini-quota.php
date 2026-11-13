<?php
/**
 * GeminiQuota — بودجه‌بندی پایدار سهمیه روزانه Gemini
 *
 * چرا transient و نه static؟ چون هر درخواست AJAX/cron یک پروسه PHP جدید است
 * و شمارنده‌های static بین اجراها از بین می‌روند.
 *
 * ریست سهمیه گوگل: نیمه‌شب اقیانوس آرام (≈ ۱۱:۳۰ صبح تهران)
 * کلیدها بر اساس «تاریخ Pacific» ساخته می‌شوند، پس با ریست گوگل
 * به‌صورت خودکار روز جدید شروع می‌شود.
 *
 * @package NextSafarNews
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class GeminiQuota
{
    /** سقف امن مصرف روزانه هر مدل (Flash رایگان ۲۰ است؛ ۲ تا حاشیه امن) */
    const DEFAULT_BUDGET = 18;

    /** مدل‌های Flash-Lite سهمیه رایگان ۵۰۰تایی دارند */
    const LITE_BUDGET = 450;

    private static function day(): string
    {
        return (new \DateTime('now', new \DateTimeZone('America/Los_Angeles')))->format('Y-m-d');
    }

    private static function secondsUntilReset(): int
    {
        $tz    = new \DateTimeZone('America/Los_Angeles');
        $reset = (new \DateTime('tomorrow', $tz))->getTimestamp();
        return max(60, $reset - time());
    }

    public static function budget(string $model): int
    {
        if (stripos($model, 'lite') !== false) {
            return (int) apply_filters('nextsafar_gemini_daily_budget_lite', self::LITE_BUDGET, $model);
        }
        return (int) apply_filters('nextsafar_gemini_daily_budget', self::DEFAULT_BUDGET, $model);
    }

    private static function usedKey(string $model): string
    {
        return 'ns_gq_used_' . $model . '_' . self::day();
    }

    private static function deadKey(string $model): string
    {
        return 'ns_gq_dead_' . $model . '_' . self::day();
    }

    public static function used(string $model): int
    {
        return (int) get_transient(self::usedKey($model));
    }

    public static function isExhausted(string $model): bool
    {
        return (bool) get_transient(self::deadKey($model));
    }

    public static function canCall(string $model): bool
    {
        return !self::isExhausted($model) && self::used($model) < self::budget($model);
    }

    /** هر پاسخ 200 (حتی blocked یا MAX_TOKENS) یک درخواست مصرف‌شده حساب می‌شود */
    public static function record(string $model): void
    {
        set_transient(self::usedKey($model), self::used($model) + 1, 2 * DAY_IN_SECONDS);
    }

    /** قفل مدل تا ریست رسمی گوگل — عمداً با دکمه «ریست AI» پاک نمی‌شود */
    public static function markExhausted(string $model): void
    {
        set_transient(self::deadKey($model), 1, self::secondsUntilReset());
        error_log("⛔ [GeminiQuota] {$model} تا ریست سهمیه قفل شد");
    }

    public static function statusText(?string $model = null): string
    {
        $model = $model ?: (string) get_option('nextsafar_news_ai_model_main', 'gemini-3.7-flash');
        $txt   = 'مصرف امروز ' . $model . ': ' . self::used($model) . ' از ' . self::budget($model);

        if (self::isExhausted($model)) {
            $txt .= ' — ⛔ سهمیه تمام شده (ریست تا ' . human_time_diff(time(), time() + self::secondsUntilReset()) . ' دیگر)';
        }

        return $txt;
    }
}