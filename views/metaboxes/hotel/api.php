<?php
if (!defined('ABSPATH')) exit;

$external_id = get_post_meta($post->ID, '_hotel_external_id', true);
$data_source = get_post_meta($post->ID, '_hotel_data_source', true);
$last_sync   = get_post_meta($post->ID, '_hotel_last_sync', true);
$property_token = get_post_meta($post->ID, '_hotel_property_token', true);
?>

<div class="nextsafar-hotel-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            اطلاعات API و سینک
        </h3>

        <div class="ns-grid ns-grid-2">
            <div class="ns-field">
                <label>شناسه خارجی:</label>
                <div class="ns-input ns-readonly" dir="ltr"><?= esc_html($external_id ?: '—'); ?></div>
            </div>

            <div class="ns-field">
                <label>منبع داده:</label>
                <div>
                    <?php if ($data_source) : ?>
                        <span class="ns-badge ns-badge-<?= esc_attr($data_source); ?>"><?= esc_html(strtoupper($data_source)); ?></span>
                    <?php else : ?>
                        <span class="ns-badge ns-badge-manual">دستی</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ns-field">
                <label>Property Token:</label>
                <div class="ns-input ns-readonly ns-input-sm" dir="ltr" style="word-break: break-all; font-size: 11px; min-height: auto; padding: 8px;">
                    <?= esc_html($property_token ?: '—'); ?>
                </div>
            </div>

            <div class="ns-field">
                <label>آخرین همگام‌سازی:</label>
                <div class="ns-input ns-readonly" dir="ltr">
                    <?= $last_sync ? date_i18n('Y-m-d H:i', strtotime($last_sync)) : '—'; ?>
                </div>
            </div>
        </div>

        <p class="ns-hint" style="margin-top: 16px;">
            💡 این فیلدها به صورت خودکار توسط API پر می‌شوند. تغییر دستی آن‌ها در سینک بعدی بازنویسی خواهد شد.
        </p>

        <div style="margin-top: 16px;">
            <button type="button" class="nextsafar-primary-button" id="sync-hotel-data" style="width: 100%;">
                <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                همگام‌سازی مجدد
            </button>
        </div>
    </div>
</div>