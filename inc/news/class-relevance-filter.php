<?php
/**
 * RelevanceFilter — بررسی مرتبط بودن خبر با AI
 *
 * یک سوال ساده YES/NO از Gemini می‌پرسد:
 * "آیا این خبر مربوط به گردشگری، سفر، هتل، پرواز، تور، مقصد یا رستوران است؟"
 *
 * با temperature پایین (0.1) برای دقت بالا.
 *
 * @package NextSafar\News
 * @since 1.0.0
 */

namespace NextSafar\News;

if (!defined('ABSPATH')) {
    exit;
}

class RelevanceFilter
{
    /**
     * بررسی مرتبط بودن یک خبر با AI
     *
     * @param array $item آیتم خبری
     * @return bool|null true=مرتبط، false=نامرتبط، null=خطا
     */
    public function isRelevant(array $item): ?bool
    {
        if (get_option('nextsafar_news_relevance_filter') !== '1') {
            return true; // اگر غیرفعال است، همه مرتبط فرض می‌شوند
        }

        $client = new GeminiClient(60);
        if (!$client->hasApiKey()) {
            error_log('⚠️ [RelevanceFilter] Gemini key not set');
            return null;
        }

        $title = $item['title'] ?? '';
        $excerpt = mb_substr($item['excerpt'] ?? ($item['content'] ?? ''), 0, 500);

        $prompt = "You are a news classifier for a TRAVEL and TOURISM website.\n"
                . "Is this news related to: tourism, travel, hotels, flights, tours, destinations, restaurants?\n\n"
                . "Title: {$title}\n"
                . "Excerpt: {$excerpt}\n\n"
                . "Answer ONLY: YES or NO";

        // استفاده از زنجیره مدل‌ها برای قابلیت اطمینان
        $models = $this->getModelChain();

        foreach ($models as $model) {
            $answer = $client->sendRequest($prompt, [
                'temperature'   => 0.1,
                'max_tokens'    => 200,
                'response_mime' => 'text/plain',
                'model'         => $model,
            ]);

            if ($answer !== null) {
                $result = stripos(trim($answer), 'YES') !== false;
                error_log("🤖 [RelevanceFilter] {$title}: " . ($result ? 'YES ✅' : 'NO ❌') . " (model: {$model})");
                return $result;
            }
        }

        error_log('⚠️ [RelevanceFilter] همه مدل‌ها ناموفق بودند');
        return null;
    }

    /**
     * زنجیره مدل‌های Gemini برای fallback
     */
    private function getModelChain(): array
    {
        $main = get_option('nextsafar_news_ai_model_main', 'gemini-3.7-flash');
        $back1 = get_option('nextsafar_news_ai_model_backup1', 'gemini-3.6-flash');
        $back2 = get_option('nextsafar_news_ai_model_backup2', 'gemini-3.5-flash');

        $adminModels = array_filter([$main, $back1, $back2]);
        
        $hardcodedFallbacks = [
            'gemini-3.5-pro',
            'gemini-3.0-flash',
            'gemini-3.0-pro',
        ];

        return array_unique(array_merge($adminModels, $hardcodedFallbacks));
    }
}