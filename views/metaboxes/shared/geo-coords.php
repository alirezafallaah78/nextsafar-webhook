<?php if (!defined('ABSPATH')) exit; ?>

<div class="ns-section">
    <h3 class="ns-section-title">
        <svg class="ns-icon" viewBox="0 0 24 24"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
        موقعیت جغرافیایی
    </h3>

    <div class="ns-field">
        <label for="ns_geo_coords">مختصات (lat,lng)</label>
        <div style="display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap;">
            <input type="text" id="ns_geo_coords" name="ns_geo_coords"
                   value="<?= esc_attr($value); ?>"
                   placeholder="41.040124,28.984285"
                   class="ns-input" dir="ltr" style="flex:1;">
            <button type="button" class="nextsafar-secondary-button" id="ns-geo-open-map">
                <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                انتخاب از نقشه
            </button>
        </div>
        <p class="ns-hint">
            یک فیلد ترکیبی: عرض و طول را با کاما جدا کن یا از نقشه انتخاب کن.
            هنگام ذخیره به <code dir="ltr">_geo_lat</code> و <code dir="ltr">_geo_lng</code> تبدیل می‌شود.
        </p>
        <?php wp_nonce_field('ns_geo_coords_' . $post->ID, 'ns_geo_coords_nonce'); ?>
    </div>
</div>

<!-- Map modal (OSM — no API key required) -->
<div id="ns-geo-modal" style="display:none;">
    <div class="ns-geo-modal-box">
        <div class="ns-geo-modal-head">
            <input type="text" id="ns-geo-search" placeholder="جستجوی نام مکان..." class="ns-input">
            <button type="button" class="nextsafar-secondary-button" id="ns-geo-do-search">جستجو</button>
            <button type="button" class="nextsafar-primary-button" id="ns-geo-use">استفاده از این مختصات</button>
            <button type="button" class="nextsafar-secondary-button" id="ns-geo-close">بستن</button>
        </div>
        <div id="ns-geo-map"></div>
        <div class="ns-geo-modal-foot">
            مختصات فعلی: <code id="ns-geo-current">—</code>
            <span class="ns-hint">روی نقشه کلیک کن یا مارکر را بکش</span>
        </div>
    </div>
</div>