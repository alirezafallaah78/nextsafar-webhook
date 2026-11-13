<?php
/**
 * GeminiClient — کلاینت رسمی Google Gemini API
 *
 * ویژگی‌ها:
 * - پشتیبانی از مدل‌های Gemini 3.5 Flash به بالا
 * - Fallback chain هوشمند (اگر یک مدل ناموفق بود، به بعدی می‌رود)
 * - Rate limiting داخلی (حداقل 8 ثانیه بین درخواست‌ها)
 * - Exponential backoff برای خطاهای 429/500/503
 * - Token counting و هزینه‌یابی
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class GeminiClient
{
    /**
     * لیست مدل‌های پشتیبانی شده (به ترتیب اولویت)
     * از جدیدترین به قدیمی‌ترین
     */
    public const VALID_MODELS = [
        'gemini-3.8-flash',
        'gemini-3.7-flash',
        'gemini-3.6-flash',
        'gemini-3.5-flash',
        'gemini-3.5-flash-lite',
        'gemini-3.5-pro',
        'gemini-3.0-flash',
        'gemini-3.0-pro',
    ];

    public const DEFAULT_MODEL = 'gemini-3.7-flash';
    public const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    public const MAX_HTTP_RETRIES = 2;
    public const RETRYABLE_CODES = [429, 500, 503];
    
    /** حداقل فاصله بین درخواست‌ها (ثانیه) - برای rate limiting */
    public const MIN_REQUEST_INTERVAL = 8.0;

    private string $api_key;
    private int $timeout;
    private ?string $lastResponse = null;
    private ?string $lastError = null;
    private static float $lastRequestTime = 0;

    public function __construct(int $timeout = 90)
    {
        $this->api_key = (string) get_option('nextsafar_gemini_api_key', '');
        $this->timeout = $timeout;
    }

    /* ========================================================================
       API Key Management
       ======================================================================== */
    
    public function hasApiKey(): bool
    {
        return !empty($this->api_key);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getActiveModel(): string
    {
        // منبع واحد: همان گزینه‌ای که صفحه تنظیمات ذخیره می‌کند
        $model = (string) get_option('nextsafar_news_ai_model_main', '');
        if ($model === '') {
            $model = (string) get_option('nextsafar_news_ai_model', self::DEFAULT_MODEL);
        }
        // به‌جای لیست هاردکد، فقط شکل نام مدل اعتبارسنجی می‌شود
        return preg_match('/^gemini-[a-z0-9.\-]+$/i', $model) ? $model : self::DEFAULT_MODEL;
    }

/* ========================================================================
   Ping — تست اتصال بدون مصرف سهمیه (models.list)
======================================================================== */

/**
 * بررسی اعتبار کلید و اتصال، بدون هیچ درخواست generateContent
 * بنابراین حتی وقتی سهمیه روزانه تمام شده، این تست موفق می‌شود.
 */
public function ping(): bool
{
    if (!$this->hasApiKey()) {
        $this->lastError = 'Gemini API key تنظیم نشده است';
        return false;
    }

    $response = wp_remote_get(self::API_URL . '?pageSize=1', [
        'timeout' => 15,
        'headers' => [
            'Accept'        => 'application/json',
            'x-goog-api-key' => $this->api_key, // کلید در هدر، نه در URL
        ],
    ]);

    if (is_wp_error($response)) {
        $this->lastError = 'خطای شبکه: ' . $response->get_error_message()
            . ' (احتمالاً مشکل VPN/اتصال به سرور گوگل)';
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 200) {
        // اگر VPN/پروکسی صفحه برگرداند، کد 200 است ولی models ندارد
        if (!isset($body['models'])) {
            $this->lastError = 'پاسخ نامعتبر از سرور — احتمال تداخل VPN یا پروکسی';
            return false;
        }
        return true;
    }

    $msg = $body['error']['message'] ?? 'نامشخص';

    if ($code === 400) {
        $this->lastError = 'کلید API نامعتبر است (HTTP 400): ' . $msg;
    } elseif ($code === 403) {
        $this->lastError = 'کلید API مجوز ندارد یا محدود شده است (HTTP 403): ' . $msg;
    } else {
        $this->lastError = "HTTP {$code}: " . $msg;
    }

    return false;
}

    /* ========================================================================
       Rate Limiting
       ======================================================================== */
    
    private function rateLimitWait(): void
    {
        $now = microtime(true);
        $elapsed = $now - self::$lastRequestTime;

        if ($elapsed < self::MIN_REQUEST_INTERVAL) {
            $waitTime = self::MIN_REQUEST_INTERVAL - $elapsed;
            if ($waitTime > 0.1) {
                usleep((int) ($waitTime * 1000000));
            }
        }

        self::$lastRequestTime = microtime(true);
    }

    /* ========================================================================
       Send Request — ارسال درخواست به Gemini
       ======================================================================== */
    
    /**
     * ارسال درخواست و دریافت پاسخ متنی
     *
     * @param string $prompt متن درخواست
     * @param array  $options تنظیمات اضافی (temperature, max_tokens, model)
     * @return string|null پاسخ متنی یا null در صورت خطا
     */
    public function sendRequest(string $prompt, array $options = []): ?string
    {
        if (!$this->hasApiKey()) {
            $this->lastError = 'Gemini API key تنظیم نشده است';
            return null;
        }

        $model = $options['model'] ?? $this->getActiveModel();
        // گیت بودجه: اگر مدل قفل است یا به سقف امن رسیده، اصلاً درخواست نفرست
        if (!GeminiQuota::canCall($model)) {
            $this->lastError = 'HTTP 429 (local quota): سهمیه امروز مدل ' . $model
                . ' تمام شده یا به سقف امن رسیده — تا ریست (≈۱۱:۳۰ تهران) صبر کنید یا مدل دیگری انتخاب کنید';
            return null;
        }
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = max(1024, (int) ($options['max_tokens'] ?? 2048));
        $responseMime = $options['response_mime'] ?? 'application/json';

        $url = self::API_URL . '/' . $model . ':generateContent?key=' . urlencode($this->api_key);

        $requestBody = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature'      => $temperature,
                'maxOutputTokens'  => $maxTokens,
                'responseMimeType' => $responseMime,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',       'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
            ],
        ];

        $body = json_encode($requestBody);
        $attempt = 0;

        while (true) {
            $this->rateLimitWait();

            $response = wp_remote_post($url, [
                'timeout' => $this->timeout,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => $body,
            ]);

            if (is_wp_error($response)) {
                $this->lastError = 'HTTP Error: ' . $response->get_error_message();
                if ($attempt < self::MAX_HTTP_RETRIES) {
                    $attempt++;
                    sleep(5 * $attempt);
                    continue;
                }
                return null;
            }

            $code = wp_remote_retrieve_response_code($response);
            $raw = wp_remote_retrieve_body($response);
            $this->lastResponse = $raw;

            // 429 روزانه: retry بی‌فایده است — مدل تا ریست قفل می‌شود
            if ((int) $code === 429) {
                $err = json_decode($raw, true);
                $msg = $err['error']['message'] ?? '';

                $isDaily = stripos($msg, 'PerDay') !== false
                    || stripos($msg, 'free_tier_requests') !== false
                    || (preg_match('/retry in ([\d.]+)s/i', $msg, $m) && (float) $m[1] > 300);

                if ($isDaily) {
                    GeminiQuota::markExhausted($model);
                    $this->lastError = 'HTTP 429 (daily quota): سهمیه روزانه مدل ' . $model . ' تمام شده است. ' . $msg;
                    error_log("⛔ [Gemini] Daily quota exhausted for {$model} — no retry");
                    return null;
                }
            }

            // Retry برای خطاهای موقت
            if (in_array((int) $code, self::RETRYABLE_CODES, true) && $attempt < self::MAX_HTTP_RETRIES) {
                $attempt++;
                $err = json_decode($raw, true);
                $waitTime = 5 * $attempt;
                $msg = $err['error']['message'] ?? '';

                if ($code === 429 && preg_match('/retry in (\d+(?:\.\d+)?)s/i', $msg, $m)) {
                    $waitTime = min((int) ceil((float) $m[1]), 30);
                }
                if ($code === 503) {
                    $waitTime = 10 * $attempt;
                }

                error_log("⏳ [Gemini] HTTP {$code} — retry {$attempt}/" . self::MAX_HTTP_RETRIES . " (wait: {$waitTime}s)");
                sleep($waitTime);
                continue;
            }

            if ($code !== 200) {
                $err = json_decode($raw, true);
                $this->lastError = "HTTP {$code}: " . ($err['error']['message'] ?? mb_substr($raw, 0, 300));
                return null;
            }

            // هر پاسخ 200 یک درخواست مصرف‌شده از سهمیه امروز است
            GeminiQuota::record($model);

            $data = json_decode($raw, true);
            if (empty($data['candidates'][0])) {
                $this->lastError = 'No candidates. Block: ' . ($data['promptFeedback']['blockReason'] ?? 'Unknown');
                return null;
            }

            $candidate = $data['candidates'][0];
            $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';

            if (empty($candidate['content']['parts'][0]['text'])) {
                $this->lastError = "Empty text. Reason: {$finishReason}";

                // اگر MAX_TOKENS، با max_tokens بیشتر retry کن
                if ($finishReason === 'MAX_TOKENS' && $attempt < self::MAX_HTTP_RETRIES) {
                    $attempt++;
                    $newMax = $maxTokens * 2;
                    error_log("⚠️ [Gemini] MAX_TOKENS — retrying with {$newMax} tokens");
                    $requestBody['generationConfig']['maxOutputTokens'] = $newMax;
                    $body = json_encode($requestBody);
                    continue;
                }
                return null;
            }

            return $candidate['content']['parts'][0]['text'];
        }
    }

    /* ========================================================================
       Send JSON Request
       ======================================================================== */
    
    /**
     * ارسال درخواست و دریافت پاسخ JSON
     */
    public function sendJsonRequest(string $prompt, array $options = []): ?array
    {
        $text = $this->sendRequest($prompt, $options);
        return $text === null ? null : $this->extractJson($text);
    }

    /* ========================================================================
       Extract JSON from Response
       ======================================================================== */
    
    public function extractJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/i', '', $text);

        $result = json_decode($text, true);
        if (is_array($result)) {
            return $result;
        }

        // تلاش برای استخراج JSON از داخل متن
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $result = json_decode($m[0], true);
            if (is_array($result)) {
                return $result;
            }
        }

        return null;
    }

    /* ========================================================================
       Token Count
       ======================================================================== */
    
    public function getLastTokenCount(): int
    {
        if (empty($this->lastResponse)) {
            return 0;
        }

        $data = json_decode($this->lastResponse, true);
        return (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);
    }

    /* ========================================================================
       Get Models List — برای نمایش در تنظیمات
       ======================================================================== */
    
    /**
     * دریافت لیست مدل‌های در دسترس از API
     *
     * @return array{models?: array, error?: string}
     */
    public static function getModelsList(): array
    {
        $apiKey = (string) get_option('nextsafar_gemini_api_key', '');

        if (empty($apiKey)) {
            return ['error' => 'Gemini API key تنظیم نشده است'];
        }

        // لیست داخلی (fallback)
        $known = [];
        foreach (self::VALID_MODELS as $m) {
            $known[] = [
                'name'         => $m,
                'display_name' => ucfirst(str_replace('-', ' ', $m)) . ' (Google)',
                'is_flash'     => stripos($m, 'flash') !== false,
                'is_pro'       => stripos($m, 'pro') !== false,
                'is_latest'    => in_array($m, ['gemini-3.7-flash', 'gemini-3.6-flash', 'gemini-3.5-flash'], true),
            ];
        }

        // دریافت لیست واقعی از API
        $response = wp_remote_get(self::API_URL . '?key=' . urlencode($apiKey), [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return ['models' => $known];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['models']) || !is_array($data['models'])) {
            return ['models' => $known];
        }

        $models = [];
        foreach ($data['models'] as $model) {
            $name = str_replace('models/', '', $model['name'] ?? '');

            if (empty($model['supportedGenerationMethods']) ||
                !in_array('generateContent', $model['supportedGenerationMethods'])) {
                continue;
            }

            if (stripos($name, 'gemini-') !== 0) {
                continue;
            }

            $models[] = [
                'name'         => $name,
                'display_name' => $model['displayName'] ?? $name,
                'is_flash'     => stripos($name, 'flash') !== false,
                'is_pro'       => stripos($name, 'pro') !== false,
                'is_latest'    => in_array($name, ['gemini-3.7-flash', 'gemini-3.6-flash', 'gemini-3.5-flash'], true),
            ];
        }

        if (empty($models)) {
            return ['models' => $known];
        }

        // مرتب‌سازی: جدیدترین اول
        usort($models, function ($a, $b) {
            return ($b['is_latest'] ? 1 : 0) - ($a['is_latest'] ? 1 : 0);
        });

        return ['models' => $models];
    }
}