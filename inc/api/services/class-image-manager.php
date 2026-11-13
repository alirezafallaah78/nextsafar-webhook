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

    /**
     * Download image and set as featured
     *
     * @param int    $post_id   Post ID
     * @param string $image_url Image URL
     * @param string $source    Source identifier for logging
     * @return bool Success status
     */
    public static function setFeaturedImage(int $post_id, string $image_url, string $source = 'api'): bool
    {
        if (empty($image_url) || !self::isValidImageUrl($image_url)) {
            return false;
        }

        // Don't override existing featured image
        if (has_post_thumbnail($post_id)) {
            return true;
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
                error_log("🖼️ [ImageManager] Featured set for #{$post_id} (attachment #{$attachment_id})");
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

    /**
     * Fill image gallery from array of URLs
     *
     * @param int    $post_id Post ID
     * @param array  $images  Array of image URLs
     * @param string $meta_key Meta key to store gallery (default: _hotel_gallery)
     * @param int    $max     Max images to download
     * @return int Number of images added
     */
    public static function fillGallery(int $post_id, array $images, string $meta_key = '_hotel_gallery', int $max = 8): int
    {
        $max = max(1, min(20, $max));
        $added = 0;

        $gallery = get_post_meta($post_id, $meta_key, true);
        $gallery = is_array($gallery) ? $gallery : [];

        foreach (array_slice($images, 0, $max) as $url) {
            if (!is_string($url) || $url === '' || in_array($url, $gallery, true)) {
                continue;
            }

            try {
                $attachment_id = self::downloadAndAttach($url, $post_id);

                if (is_wp_error($attachment_id) || !$attachment_id) {
                    continue;
                }

                $attachment_url = wp_get_attachment_url((int) $attachment_id);
                if ($attachment_url) {
                    $gallery[] = $attachment_url;
                    $added++;
                }
            } catch (\Throwable $e) {
                error_log("⚠️ [ImageManager] Gallery image failed for #{$post_id}: " . $e->getMessage());
            }
        }

        if (!empty($gallery)) {
            update_post_meta($post_id, $meta_key, $gallery);
            error_log("🖼️ [ImageManager] Gallery: {$added} images for #{$post_id}");
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

    /**
     * Fetch OG image from URL (with caching)
     */
    protected static function fetchOgImage(string $url): ?string
    {
        $key = 'ns_og_' . md5($url);
        $cached = get_transient($key);

        if ($cached !== false) {
            return $cached ?: null;
        }

        $response = wp_remote_get($url, [
            'timeout'     => 10,
            'redirection' => 3,
            'sslverify'   => false,
        ]);

        if (is_wp_error($response)) {
            set_transient($key, '', HOUR_IN_SECONDS);
            return null;
        }

        $html = wp_remote_retrieve_body($response);
        $img = null;

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

    /**
     * Download image and attach to post
     *
     * @param string $url     Image URL
     * @param int    $post_id Post ID
     * @return int|WP_Error Attachment ID or error
     */
    protected static function downloadAndAttach(string $url, int $post_id): int|WP_Error
    {
        // Ensure WordPress media functions are loaded
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = self::downloadImage($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Check file size
        $size = @filesize($tmp);
        if ($size === false || $size > self::MAX_FILE_SIZE) {
            @unlink($tmp);
            return new WP_Error('image_too_large', 'Image exceeds 5MB limit');
        }

        // Validate image dimensions
        $info = @getimagesize($tmp);
        if ($info === false) {
            @unlink($tmp);
            return new WP_Error('not_an_image', 'File is not a valid image');
        }

        if ($info[0] < 100 || $info[1] < 100) {
            @unlink($tmp);
            return new WP_Error('image_too_small', 'Image smaller than 100px');
        }

        // Prepare for sideload
        $filename = self::extractFilename($url) ?: ('ns_image_' . $post_id . '_' . time() . '.jpg');
        $file_array = [
            'name'     => sanitize_file_name($filename),
            'tmp_name' => $tmp,
            'size'     => $size,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);
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
        $response = wp_remote_get($url, [
            'timeout'     => self::DOWNLOAD_TIMEOUT,
            'redirection' => 5,
            'sslverify'   => false,
            'headers'     => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Referer'    => 'https://www.google.com/',
                'Accept'     => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ],
        ]);

        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if (!empty($body)) {
                $tmp = wp_tempnam($url);
                if ($tmp) {
                    file_put_contents($tmp, $body);
                    return $tmp;
                }
            }
        }

        // Fallback to download_url
        return download_url($url, self::DOWNLOAD_TIMEOUT);
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
     * Check if URL is a valid image URL
     */
    protected static function isValidImageUrl(string $url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
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