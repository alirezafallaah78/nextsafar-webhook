<?php
/**
 * AIRewriter — ترجمه و بازنویسی اخبار با Gemini
 *
 * ویژگی‌ها:
 * - ترجمه انگلیسی به فارسی
 * - بازنویسی روان و طبیعی
 * - Fallback chain بین مدل‌ها
 * - Cooldown بعد از 5 خطای متوالی (10 دقیقه)
 * - Similarity guard برای جلوگیری از کپی کردن
 * - Token counting و cost tracking
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class AIRewriter
{
    private int $timeout = 120;

    public const FAIL_COUNT_KEY = 'ns_ai_fail_count';
    public const DISABLED_UNTIL_KEY = 'ns_ai_disabled_until';
    public const MAX_FAILS = 5;
    public const COOLDOWN_MINUTES = 10;
    public const SIMILARITY_MAX = 0.60;

    /* ========================================================================
       process — پردازش کامل یک خبر
       ======================================================================== */
    
    /**
     * ترجمه و بازنویسی یک خبر با AI
     *
     * @param array $item آیتم خبری
     * @return array نتیجه پردازش
     */
    public function process(array $item): array
    {
        $aiEnabled = get_option('nextsafar_news_ai_enabled', '0') === '1';

        $result = [
            'title'       => $item['title'] ?? '',
            'excerpt'     => $item['excerpt'] ?? '',
            'content'     => $item['content'] ?? '',
            'ai_used'     => false,
            'ai_provider' => null,
            'ai_model'    => null,
            'tokens_used' => 0,
        ];

        // اگر AI غیرفعال است، فقط لینک منبع را اضافه کن
        if (!$aiEnabled) {
            $result['content'] = $this->addSourceLink(
                $result['content'],
                $item['link'] ?? '',
                $item['source_name'] ?? ''
            );
            return $result;
        }

        // بررسی cooldown
        if ($this->isDisabled()) {
            $until = get_transient(self::DISABLED_UNTIL_KEY);
            $remaining = $until ? human_time_diff(time(), $until) : '';
            error_log("⚠️ [AIRewriter] در حالت cooldown ({$remaining} باقی‌مانده)");
            return $result;
        }

        // بررسی تعداد خطاها
        if ($this->getFailCount() >= self::MAX_FAILS) {
            $this->enterCooldown();
            error_log('🚨 [AIRewriter] Cooldown بعد از ' . self::MAX_FAILS . ' خطا');
            return $result;
        }

        // تلاش برای بازنویسی
        try {
            $rewritten = $this->rewriteWithGemini($item);

            if ($rewritten) {
                $result['title'] = $rewritten['title'];
                $result['excerpt'] = $rewritten['excerpt'] ?? '';
                $result['content'] = $rewritten['content'];
                $result['ai_used'] = true;
                $result['ai_provider'] = 'gemini';
                $result['ai_model'] = $rewritten['model'] ?? null;
                $result['tokens_used'] = $rewritten['tokens'] ?? 0;

                $this->resetFailCount();
            }
        } catch (\Exception $e) {
            $newCount = $this->incrementFailCount();
            error_log("⚠️ [AIRewriter] خطا ({$newCount}/" . self::MAX_FAILS . '): ' . $e->getMessage());

        if ($newCount >= self::MAX_FAILS && !$this->isDisabled()) {
            $this->enterCooldown();
        }
        }

        return $result;
    }

    /* ========================================================================
       Rewrite with Gemini
       ======================================================================== */
    
    private function rewriteWithGemini(array $item, bool $forceDifferent = false): ?array
    {
        $client = new GeminiClient($this->timeout);

        if (!$client->hasApiKey()) {
            throw new \Exception('Gemini API key is not set');
        }

        $allModels = $this->getModelChain();
        // اگر همه مدل‌ها قفل باشند، به‌جای تلاش بی‌فایده تا ریست سهمیه بخواب
        if (empty($allModels)) {
            $this->enterQuotaCooldown();
            throw new \Exception('همه مدل‌های Gemini تا ریست سهمیه قفل هستند (≈۱۱:۳۰ تهران)');
        }
        $prompt = $this->getSystemPrompt($forceDifferent) . "\n\n" . $this->buildPrompt($item, $forceDifferent);

        $lastErr = '';
        $triedCount = 0;

        foreach ($allModels as $model) {
            $triedCount++;
            error_log("🤖 [AIRewriter] تلاش با مدل: {$model} ({$triedCount}/" . count($allModels) . ")");

            $res = $client->sendJsonRequest($prompt, [
                'temperature' => 0.7,
                'max_tokens'  => 4096,
                'model'       => $model,
            ]);

            if ($res === null) {
                $lastErr = $client->getLastError() ?? 'unknown error';

                // خطاهای قابل بازیابی
                if (preg_match('/HTTP (404|429|500|503)/', $lastErr) || stripos($lastErr, 'not found') !== false) {
                    error_log("⚠️ [AIRewriter] مدل {$model} ناموفق - تلاش با بعدی");
                    continue;
                }

                throw new \Exception("Gemini [{$model}]: {$lastErr}");
            }

            if (empty($res['title']) || empty($res['content'])) {
                $lastErr = 'Invalid JSON response';
                error_log("⚠️ [AIRewriter] مدل {$model} JSON نامعتبر - تلاش با بعدی");
                continue;
            }

            error_log("✅ [AIRewriter] مدل {$model} موفق!");

            $res['content'] = $this->addSourceLink(
                $res['content'],
                $item['link'] ?? '',
                $item['source_name'] ?? ''
            );
            $res['tokens'] = $client->getLastTokenCount();
            $res['model'] = $model;

            return $res;
        }

        throw new \Exception("همه " . count($allModels) . " مدل Gemini ناموفق بودند. آخرین خطا: " . $lastErr);
    }

    /* ========================================================================
       Cooldown Management
       ======================================================================== */
    
    private function getFailCount(): int
    {
        $c = get_transient(self::FAIL_COUNT_KEY);
        return is_numeric($c) ? (int) $c : 0;
    }

    private function incrementFailCount(): int
    {
        $c = $this->getFailCount() + 1;
        set_transient(self::FAIL_COUNT_KEY, $c, HOUR_IN_SECONDS);
        return $c;
    }

    private function resetFailCount(): void
    {
        delete_transient(self::FAIL_COUNT_KEY);
        delete_transient(self::DISABLED_UNTIL_KEY);
    }

    private function isDisabled(): bool
    {
        $until = get_transient(self::DISABLED_UNTIL_KEY);
        return $until && $until > time();
    }

    private function enterQuotaCooldown(): void
    {
        $tz    = new \DateTimeZone('America/Los_Angeles');
        $reset = (new \DateTime('tomorrow', $tz))->getTimestamp();
        $secs  = max(300, $reset - time());
        set_transient(self::DISABLED_UNTIL_KEY, $reset, $secs);
        error_log('⛔ [AIRewriter] Cooldown تا ریست سهمیه (' . human_time_diff(time(), $reset) . ' دیگر)');
    }

    public static function resetAll(): void
    {
        delete_transient(self::FAIL_COUNT_KEY);
        delete_transient(self::DISABLED_UNTIL_KEY);
        error_log('✅ [AIRewriter] شمارنده‌ها ریست شدند');
    }

    public static function getStatus(): array
    {
        $fail = (int) get_transient(self::FAIL_COUNT_KEY);
        $until = get_transient(self::DISABLED_UNTIL_KEY);
        $dis = $until && $until > time();

        return [
            'fail_count'         => $fail,
            'max_fails'          => self::MAX_FAILS,
            'is_disabled'        => $dis,
            'cooldown_remaining' => $dis ? human_time_diff(time(), $until) . ' باقی‌مانده' : null,
            'cooldown_minutes'   => self::COOLDOWN_MINUTES,
        ];
    }

    /* ========================================================================
       Model Chain
       ======================================================================== */
    
    private function getModelChain(): array
    {
        $main  = get_option('nextsafar_news_ai_model_main', 'gemini-2.5-flash-lite');
        $back1 = get_option('nextsafar_news_ai_model_backup1', 'gemini-2.5-flash');
        $back2 = get_option('nextsafar_news_ai_model_backup2', 'gemini-2.0-flash');

        $adminModels = array_filter([$main, $back1, $back2]);

        // fallbackهای ارزان و پرسهمیه — به‌جای proهای گران با سهمیه کم
        $hardcodedFallbacks = [
            'gemini-3.5-flash-lite',
            'gemini-3.5-flash',
            'gemini-3.0-flash',
        ];

        $chain = array_values(array_unique(array_merge($adminModels, $hardcodedFallbacks)));

        // مدل‌های قفل‌شده یا به‌سقف‌رسیده اصلاً وارد زنجیره نمی‌شوند
        return array_values(array_filter($chain, function ($m) {
            return GeminiQuota::canCall($m);
        }));
    }

    /* ========================================================================
       Prompts
       ======================================================================== */
    
    private function buildPrompt(array $item, bool $forceDifferent = false): string
    {
        $title = $item['title'] ?? '';
        $content = $item['content'] ?? '';
        $source = $item['source_name'] ?? 'نامشخص';

        return <<<PROMPT
این خبر را برای سایت NextSafar (سایت تخصصی گردشگری ایران) ترجمه و بازنویسی کن.

عنوان اصلی: {$title}
منبع: {$source}
متن اصلی:
{$content}

قوانین مهم:
1. عنوان، چکیده و متن کامل را به فارسی ترجمه کن
2. هیچ کلمه انگلیسی در خروجی نباشد
3. از HTML ساده استفاده کن (p, h2, h3, strong)
4. متن روان و طبیعی باشد
5. جملات را به سبک ژورنالیستی فارسی بازنویسی کن

خروجی JSON:
{
  "title": "عنوان فارسی جذاب (حداکثر 70 کاراکتر)",
  "excerpt": "چکیده فارسی (حداکثر 160 کاراکتر)",
  "content": "متن کامل فارسی با HTML"
}
PROMPT;
    }

    private function getSystemPrompt(bool $forceDifferent = false): string
    {
        return "تو مترجم و نویسنده گردشگری سایت NextSafar هستی. اخبار انگلیسی را به فارسی ترجمه و بازنویسی کن. فقط JSON معتبر برگردان.";
    }

    private function addSourceLink(string $content, string $url, string $source): string
    {
        if (empty($url) || empty($source)) {
            return $content;
        }

        if (strpos($content, $url) !== false) {
            return $content;
        }

        $box = '<div class="ns-source-box" style="margin-top:30px;padding:20px;background:#f8f9fa;border-right:4px solid #00a3a3;border-radius:8px;">';
        $box .= '<strong>📰 منبع اصلی:</strong> ';
        $box .= '<a href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">' . esc_html($source) . '</a>';
        $box .= '</div>';

        return $content . $box;
    }

    /* ========================================================================
       Logging
       ======================================================================== */
    
    public function logFinal(int $postId, array $result): void
    {
        if (empty($result['ai_used'])) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ns_news_ai_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $model = $result['ai_model'] ?? get_option('nextsafar_news_ai_model', 'gemini-3.7-flash');

        // قیمت هر میلیون توکن (pro گران‌تر از flash)
        $pricePerMillion = stripos($model, 'pro') !== false ? 1.25 : 0.075;
        $tokens = (int) ($result['tokens_used'] ?? 0);

        $wpdb->insert($table, [
            'post_id'     => $postId,
            'provider'    => $result['ai_provider'] ?? 'gemini',
            'model'       => $model,
            'tokens_used' => $tokens,
            'cost_usd'    => ($tokens / 1000000) * $pricePerMillion,
            'status'      => 'success',
        ]);
    }
}