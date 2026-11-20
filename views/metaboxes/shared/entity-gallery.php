<?php
if (!defined('ABSPATH')) exit;

$gallery = get_post_meta($post->ID, '_entity_gallery', true);
$gallery = is_array($gallery) ? $gallery : [];
$source  = get_post_meta($post->ID, '_entity_gallery_source', true);
$featured_source = get_post_meta($post->ID, '_featured_image_source', true);
?>

<div class="ns-gallery-wrapper">
    
    <!-- Lock status -->
    <div class="ns-gallery-status">
        <div class="ns-gallery-status-row">
            <span class="ns-gallery-label">📸 گالری:</span>
            <?php if ($source === 'manual') : ?>
                <span class="ns-badge ns-badge-manual">🔒 دستی - قفل</span>
            <?php elseif ($source === 'api') : ?>
                <span class="ns-badge ns-badge-api">🤖 از API</span>
            <?php else : ?>
                <span class="ns-badge ns-badge-empty">⚠️ خالی</span>
            <?php endif; ?>
            <span class="ns-gallery-count">(<?= count($gallery); ?> تصویر)</span>
        </div>
        <div class="ns-gallery-status-row">
            <span class="ns-gallery-label">🖼️ تصویر شاخص:</span>
            <?php if ($featured_source === 'manual') : ?>
                <span class="ns-badge ns-badge-manual">🔒 دستی</span>
            <?php elseif (has_post_thumbnail($post->ID)) : ?>
                <span class="ns-badge ns-badge-api">🤖 API</span>
            <?php else : ?>
                <span class="ns-badge ns-badge-empty">⚠️ خالی</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gallery grid -->
    <div class="ns-gallery-grid" id="ns-gallery-grid">
        <?php foreach ($gallery as $img_id) :
            $thumb = wp_get_attachment_image_url($img_id, 'thumbnail');
            $full  = wp_get_attachment_image_url($img_id, 'large');
            $alt   = get_post_meta($img_id, '_wp_attachment_image_alt', true);
            if (!$thumb) continue;
        ?>
            <div class="ns-gallery-item" data-id="<?= esc_attr($img_id); ?>">
                <img src="<?= esc_url($thumb); ?>"
                     data-full="<?= esc_url($full); ?>"
                     alt="<?= esc_attr($alt); ?>"
                     loading="lazy">
                <div class="ns-gallery-actions">
                    <button type="button" class="ns-gallery-view" title="مشاهده">
                        <svg class="ns-icon ns-icon-xs" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <button type="button" class="ns-gallery-remove" title="حذف">
                        <svg class="ns-icon ns-icon-xs" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="ns-gallery-drag-handle" title="جابجایی">
                    <svg class="ns-icon ns-icon-xs" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Hidden inputs -->
    <input type="hidden" id="ns-gallery-ids" name="nextsafar_gallery_ids"
           value="<?= esc_attr(implode(',', $gallery)); ?>">
    <input type="hidden" id="ns-gallery-touched" name="nextsafar_gallery_touched" value="0">
    <input type="hidden" id="ns-featured-touched" name="nextsafar_featured_touched" value="0">

    <!-- Buttons -->
    <div class="ns-gallery-buttons">
        <button type="button" id="ns-gallery-add" class="nextsafar-primary-button">
            <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            افزودن تصاویر
        </button>
        <button type="button" id="ns-gallery-clear" class="nextsafar-secondary-button">
            <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            پاک کردن همه
        </button>
    </div>

    <!-- Help -->
    <div class="ns-gallery-help">
        💡 <strong>راهنما:</strong>
        تصاویر را با درگ مرتب کن. اولین تصویر به صورت خودکار به عنوان تصویر شاخص پیشنهاد می‌شود.
        هر تغییری که دستی انجام بدی، گالری را قفل می‌کند و API در sync بعدی آن را بازنویسی نمی‌کند.
    </div>
</div>

<!-- Image view modal -->
<div id="ns-gallery-modal" class="ns-gallery-modal" style="display:none;">
    <div class="ns-gallery-modal-overlay"></div>
    <div class="ns-gallery-modal-content">
        <button type="button" class="ns-gallery-modal-close">×</button>
        <img src="" alt="" id="ns-gallery-modal-img">
    </div>
</div>