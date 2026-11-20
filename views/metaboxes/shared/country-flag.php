<?php
if (!defined('ABSPATH')) exit;

$flag_url = get_post_meta($post->ID, '_country_flag', true);
$iso_code = get_post_meta($post->ID, '_country_iso', true);
$source   = get_post_meta($post->ID, '_country_flag_source', true);
?>

<div class="ns-flag-wrapper">
    <!-- Flag status -->
    <div class="ns-flag-status">
        <?php if ($source === 'manual') : ?>
            <span class="ns-badge ns-badge-manual">🔒 دستی</span>
        <?php elseif ($source === 'auto') : ?>
            <span class="ns-badge ns-badge-api">🤖 خودکار</span>
        <?php else : ?>
            <span class="ns-badge ns-badge-empty">⚠️ خالی</span>
        <?php endif; ?>
    </div>

    <!-- Flag preview -->
    <div class="ns-flag-preview" id="ns-flag-preview">
        <?php if ($flag_url) : ?>
            <img src="<?= esc_url($flag_url); ?>" alt="پرچم">
        <?php else : ?>
            <div class="ns-flag-placeholder">
                <svg class="ns-icon" viewBox="0 0 24 24" style="width:40px;height:40px;margin-bottom:8px;color:var(--ns-text-muted);">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>
                </svg>
                <br>پرچمی انتخاب نشده
            </div>
        <?php endif; ?>
    </div>

    <!-- Hidden inputs -->
    <input type="hidden" name="country_flag" id="ns-country-flag" value="<?= esc_attr($flag_url); ?>">
    <input type="hidden" name="country_iso" id="ns-country-iso" value="<?= esc_attr($iso_code); ?>">
    <input type="hidden" name="country_flag_source" id="ns-country-flag-source" value="<?= esc_attr($source); ?>">

    <!-- Buttons -->
    <div class="ns-flag-buttons">
        <button type="button" id="ns-flag-upload" class="nextsafar-primary-button">
            <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            آپلود پرچم دستی
        </button>
        <button type="button" id="ns-flag-remove" class="nextsafar-secondary-button">
            <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            حذف پرچم
        </button>
    </div>

    <p class="ns-flag-help">
        💡 تصویر پرچم یا لوگوی کشور. در صورت انتخاب دستی، در sync بعدی API بازنویسی نمی‌شود.
    </p>
</div>