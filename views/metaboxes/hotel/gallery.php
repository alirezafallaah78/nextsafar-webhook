<?php
if (!defined('ABSPATH')) exit;

$gallery = get_post_meta($post->ID, '_hotel_gallery', true);
$gallery = is_array($gallery) ? $gallery : [];
$gallery_source = get_post_meta($post->ID, '_hotel_gallery_source', true);
?>
<div class="ns-section">
    <h3 class="ns-section-title">
        <svg class="ns-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        گالری تصاویر هتل
    </h3>

    <!-- Status -->
    <div class="ns-gallery-status">
        <div class="ns-gallery-status-row">
            <span class="ns-gallery-label">منبع گالری:</span>
            <?php if ($gallery_source === 'manual') : ?>
                <span class="ns-badge ns-badge-manual">🔒 دستی - قفل</span>
            <?php elseif ($gallery_source === 'api') : ?>
                <span class="ns-badge ns-badge-api">🤖 API</span>
            <?php else : ?>
                <span class="ns-badge ns-badge-empty">⚠️ خالی</span>
            <?php endif; ?>
            <span class="ns-gallery-count">(<?= count($gallery); ?> تصویر)</span>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div id="hotel-gallery-grid" class="ns-gallery-grid">
        <?php foreach ($gallery as $img_id) :
            $thumb = wp_get_attachment_image_url($img_id, 'thumbnail');
            if (!$thumb) continue;
        ?>
            <div class="ns-gallery-item" data-id="<?= $img_id; ?>">
                <img src="<?= esc_url($thumb); ?>" alt="">
                <button type="button" class="ns-gallery-remove" title="حذف">×</button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Hidden Inputs -->
    <input type="hidden" id="hotel-gallery-ids" name="hotel_gallery_ids"
           value="<?= esc_attr(implode(',', $gallery)); ?>">
    <input type="hidden" id="hotel-gallery-touched" name="hotel_gallery_touched" value="0">
    <input type="hidden" id="featured-touched" name="hotel_featured_touched" value="0">

    <!-- Actions -->
    <div class="ns-gallery-buttons">
        <button type="button" id="ns-add-gallery-images" class="nextsafar-primary-button">
            <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            افزودن تصویر از کتابخانه
        </button>
    </div>

    <p class="ns-hint">
        💡 اولین تصویر به صورت خودکار به عنوان تصویر شاخص پیشنهاد می‌شود.
    </p>
</div>