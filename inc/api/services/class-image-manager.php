<?php
/**
 * ImageManager — Download and attach images from external sources
 *
 * Features:
 * - Download with browser-like headers (prevent 403)
 * - Automatic featured image assignment
 * - Gallery filling for hotels
 * - Fallback chain for missing images
 * - File size and type validation
 *
 * @package NextSafar\Api\Services
 * @since 1.0.0
 */

namespace NextSafar\Api\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ImageManager
{
    /** Max file size: 5MB */
    public const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Allowed image extensions */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Download timeout in seconds */
    public const DOWNLOAD_TIMEOUT = 30;

    /* ========================================================================
       Set Featured Image
       ======================================================================== */

    public static function setFeaturedImage(int $post_id, string $image_url, string $source = 'api'): bool
    {
        if (empty($image_url) || !self::isValidImageUrl($image_url)) {
            return false;
        }
        if (has_post_thumbnail($post_id)) {
            return true;
        }

        // ✅ اگر همین URL قبلاً دانلود شده، دوباره دانلود نکن
        $prev = get_post_meta($post_id, '_ns_featured_image_url', true);
        if ($prev === $image_url) {
            return false;
        }

        try {
            $attachment_id = self::downloadAndAttach($image_url, $post_id);
            if (is_wp_error($attachment_id)) {
                error_log("⚠️ [ImageManager] Download failed for #{$post_id}: " . $attachment_id->get_error_message());
                return false;
            }
            if ($attachment_id > 0) {
                set_post_thumbnail($post_id, $attachment_id);
                update_post_meta($post_id, '_ns_featured_image_source', sanitize_text_field($source));
                update_post_meta($post_id, '_ns_featured_image_url', esc_url_raw($image_url));
                self::log("🖼️ [ImageManager] Featured set for #{$post_id} (attachment #{$attachment_id})");
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            error_log("❌ [ImageManager] Error for #{$post_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
 * حذف تصاویر از محتوای خبر (به‌جای img درون‌متنی، از تصویر شاخص استفاده می‌شود)
 */
public static function stripImagesFromContent(string $content): string
{
    if ($content === '') {
        return $content;
    }

    // figureهایی که تصویر دارند کامل حذف شوند (همراه figcaption)
    $content = preg_replace_callback('/<figure\b[^>]*>.*?<\/figure>/is', function ($m) {
        return stripos($m[0], '<img') !== false ? '' : $m[0];
    }, $content);

    // picture همیشه تصویر است
    $content = preg_replace('/<picture\b[^>]*>.*?<\/picture>/is', '', $content);

    // همه تگ‌های img
    $content = preg_replace('/<img\b[^>]*>/is', '', $content);

    // لینک‌های خالی که فقط دور تصویر بودند + figcaption و p باقی‌مانده
    $content = preg_replace('/<a\b[^>]*>\s*<\/a>/is', '', $content);
    $content = preg_replace('/<figcaption\b[^>]*>.*?<\/figcaption>/is', '', $content);
    $content = preg_replace('/<p>\s*<\/p>/is', '', $content);

    return trim($content);
}

    /* ========================================================================
       Fill Gallery
       ======================================================================== */

    public static function fillGallery(int $post_id, array $images, string $meta_key = '_hotel_gallery', int $max = 8): int
    {
        $max        = max(1, min(20, $max));
        $total_cap  = 20; // ✅ سقف کل گالری (قبلاً بی‌نهایت رشد می‌کرد)
        $source_key = $meta_key . '_sources';

        $gallery = get_post_meta($post_id, $meta_key, true);
        $gallery = is_array($gallery) ? $gallery : [];

        // ✅ نگاشت «URL منبع → attachment_id» برای dedup واقعی
        $sources = get_post_meta($post_id, $source_key, true);
        $sources = is_array($sources) ? $sources : [];

        $remaining_slots = max(0, $total_cap - count($gallery));
        if ($remaining_slots <= 0) {
            return 0;
        }

        $added = 0;
        $seen  = [];

        foreach (array_slice($images, 0, $max) as $url) {
            if ($added >= $remaining_slots) {
                break;
            }
            if (!is_string($url) || $url === '') {
                continue;
            }
            if (isset($seen[$url])) {
                continue; // تکراری در همین batch
            }
            $seen[$url] = true;

            // ✅ قبلاً URL خارجی با URL لوکال مقایسه می‌شد و هرگز match نمی‌شد
            if (isset($sources[$url])) {
                continue;
            }
            if (!self::isValidImageUrl($url)) {
                continue;
            }

            try {
                $attachment_id = self::downloadAndAttach($url, $post_id);
                if (is_wp_error($attachment_id) || !$attachment_id) {
                    continue;
                }
                $attachment_url = wp_get_attachment_url((int) $attachment_id);
                if ($attachment_url) {
                    $gallery[]       = $attachment_url;
                    $sources[$url]   = (int) $attachment_id;
                    $added++;
                }
            } catch (\Throwable $e) {
                error_log("⚠️ [ImageManager] Gallery image failed for #{$post_id}: " . $e->getMessage());
            }
        }

        if ($added > 0) {
            update_post_meta($post_id, $meta_key, $gallery);
            update_post_meta($post_id, $source_key, $sources);
            self::log("🖼️ [ImageManager] Gallery: {$added} images for #{$post_id}");
        }

        return $added;
    }

    /* ========================================================================
       Ensure Featured Image (Fallback Chain)
       ======================================================================== */

    /**
     * Ensure post has a featured image using fallback chain:
     * 1. Preferred image
     * 2. First image in content
     * 3. OG image from source link
     *
     * @param int    $post_id     Post ID
     * @param string $preferred   Preferred image URL
     * @param string $source_link Source page URL (for OG image)
     * @return bool Success status
     */
    public static function ensureFeaturedImage(int $post_id, string $preferred = '', string $source_link = ''): bool
    {
        if (has_post_thumbnail($post_id)) {
            return true;
        }

        $candidates = [];

        if (!empty($preferred)) {
            $candidates[] = $preferred;
        }

        // Try first image in content
        $in_content = self::extractFirstImage(get_post_field('post_content', $post_id) ?: '');
        if ($in_content) {
            $candidates[] = $in_content;
        }

        // Try OG image from source
        if (!empty($source_link)) {
            $og = self::fetchOgImage($source_link);
            if ($og) {
                $candidates[] = $og;
            }
        }

        foreach (array_unique(array_filter($candidates)) as $url) {
            if (self::setFeaturedImage($post_id, $url, 'fallback')) {
                return true;
            }
        }

        error_log("⚠️ [ImageManager] No image available for #{$post_id}");
        return false;
    }

    /* ========================================================================
       Image Extraction Helpers
       ======================================================================== */

    /**
     * Extract first image URL from HTML content
     */
    public static function extractFirstImage(string $content): ?string
    {
        // Match img src
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
            return $m[1];
        }
        // Match figure data-url
        if (preg_match('/<figure[^>]+data-url=["\']([^"\']+)["\']/i', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    protected static function fetchOgImage(string $url): ?string
    {
        $key    = 'ns_og_' . md5($url);
        $cached = get_transient($key);
        if ($cached !== false) {
            return $cached ?: null;
        }

        if (!self::isSafeRemoteUrl($url)) {
            set_transient($key, '', HOUR_IN_SECONDS);
            return null;
        }

        // ✅ wp_safe_remote_get + sslverify=true
        $response = wp_safe_remote_get($url, [
            'timeout'     => 10,
            'redirection' => 3,
            'sslverify'   => true,
        ]);

        if (is_wp_error($response)) {
            set_transient($key, '', HOUR_IN_SECONDS);
            return null;
        }

        $html = wp_remote_retrieve_body($response);
        $img  = null;
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $img = $m[1];
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $img = $m[1];
        }

        set_transient($key, $img ?: '', DAY_IN_SECONDS);
        return $img;
    }

    /* ========================================================================
       Download and Attach (Core Logic)
       ======================================================================== */

    protected static function downloadAndAttach(string $url, int $post_id): int|WP_Error
    {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        if (!self::isSafeRemoteUrl($url)) {
            return new WP_Error('unsafe_url', 'URL is not a safe public image URL');
        }

        $tmp = self::downloadImage($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $size = @filesize($tmp);
        if ($size === false || $size > self::MAX_FILE_SIZE) {
            @unlink($tmp);
            return new WP_Error('image_too_large', 'Image exceeds 5MB limit');
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            @unlink($tmp);
            return new WP_Error('not_an_image', 'File is not a valid image');
        }
        if ($info[0] < 100 || $info[1] < 100) {
            @unlink($tmp);
            return new WP_Error('image_too_small', 'Image smaller than 100px');
        }

        $filename   = self::extractFilename($url) ?: ('ns_image_' . $post_id . '_' . time() . '.jpg');
        $file_array = [
            'name'     => sanitize_file_name($filename),
            'tmp_name' => $tmp,
            'size'     => $size,
        ];

                // ✅ بعضی تصاویر خبری EXIF خراب دارند؛ هشدار هسته فقط در همین ناحیه مهار شود
        $prevReport = error_reporting();
        error_reporting($prevReport & ~E_WARNING);
        $attachment_id = media_handle_sideload($file_array, $post_id);
        error_reporting($prevReport);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
        }
        return $attachment_id;
    }

    /**
     * Download image with browser-like headers
     */
    protected static function downloadImage(string $url)
    {
        if (!self::isSafeRemoteUrl($url)) {
            return new WP_Error('unsafe_url', 'Blocked unsafe URL');
        }

        // ✅.reject زودهنگام بر اساس Content-Length (بدون دانلود)
        $head = wp_safe_remote_head($url, [
            'timeout'     => 10,
            'redirection' => 3,
            'sslverify'   => true,
            'headers'     => self::browserHeaders(),
        ]);
        if (!is_wp_error($head)) {
            $len = (int) wp_remote_retrieve_header($head, 'content-length');
            if ($len > self::MAX_FILE_SIZE) {
                return new WP_Error('image_too_large', 'Image exceeds 5MB limit (Content-Length)');
            }
        }

        // ✅ استریم به فایل موقت با download_url (بدون بارگذاری کل body در RAM)
        // هدرهای مرورگر برای جلوگیری از 403 موقتاً تزریق می‌شوند.
        $filter = static function ($args) {
            $args['headers'] = array_merge(isset($args['headers']) ? (array) $args['headers'] : [], self::browserHeaders());
            return $args;
        };
        add_filter('http_request_args', $filter, 10, 1);
        $tmp = download_url($url, self::DOWNLOAD_TIMEOUT);
        remove_filter('http_request_args', $filter, 10);

        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $size = @filesize($tmp);
        if ($size === false || $size > self::MAX_FILE_SIZE) {
            @unlink($tmp);
            return new WP_Error('image_too_large', 'Image exceeds 5MB limit');
        }

        return $tmp;
    }
    
    /**
     * Extract filename from URL
     */
    protected static function extractFilename(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (empty($path)) {
            return '';
        }

        $filename = sanitize_file_name(urldecode(basename($path)));
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.jpg';
        }

        return $filename;
    }

    /* ========================================================================
       Validation Helpers
       ======================================================================== */

    /**
     * ✅ جلوگیری از SSRF — فقط URLهای عمومی http/https مجازند.
     */
    protected static function isSafeRemoteUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        if (in_array($host, ['localhost', 'metadata', 'metadata.google.internal'], true)) {
            return false;
        }
        // IP مستقیم خصوصی/رزرو ممنوع
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // دامنه‌ای که به IP خصوصی resolve می‌شود هم ممنوع
        $ips = @gethostbynamel($host);
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** هدرهای شبیه مرورگر برای جلوگیری از 403 */
    protected static function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Referer'    => 'https://www.google.com/',
            'Accept'     => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ];
    }

    /** لاگ اطلاعاتی فقط در حالت دیباگ */
    protected static function log(string $msg): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($msg);
        }
    }

    /**
     * Check if URL is a valid image URL
     */
    protected static function isValidImageUrl(string $url): bool
    {
        if (!self::isSafeRemoteUrl($url)) {
            return false;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!empty($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!empty($ext) && !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                return false;
            }
        }
        return true;
    }
}