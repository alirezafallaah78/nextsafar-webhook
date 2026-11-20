<?php
if (!defined('ABSPATH')) exit;

$text_fields = [
    'airport_opened'   => 'تاریخ تاسیس',
    'airport_whatsapp' => 'شماره واتساپ',
];
?>
<div class="nextsafar-airport-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
            اطلاعات تکمیلی فرودگاه
        </h3>
        <div class="ns-grid ns-grid-2">
            <?php foreach ($text_fields as $id => $label) :
                $value = get_post_meta($post->ID, '_' . $id, true);
            ?>
                <div class="ns-field">
                    <label for="<?= esc_attr($id); ?>"><?= esc_html($label); ?>:</label>
                    <input class="ns-input" type="text" id="<?= esc_attr($id); ?>"
                           name="<?= esc_attr($id); ?>" value="<?= esc_attr($value); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ns-hint">ℹ️ نام انگلیسی، IATA، شهر/کشور، آدرس، مختصات، تماس، وب‌سایت، ترمینال‌ها و نوع در «اطلاعات مکانی و سینک».</p>
    </div>

    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            ساختار فرودگاه
        </h3>
        <div class="ns-grid ns-grid-4">
            <?php $runways = get_post_meta($post->ID, '_airport_runways_count', true); ?>
            <div class="ns-field">
                <label for="airport_runways_count">تعداد باندهای پرواز:</label>
                <input class="ns-input ns-input-number" type="number" min="0"
                       id="airport_runways_count" name="airport_runways_count"
                       value="<?= esc_attr($runways); ?>">
            </div>
        </div>
    </div>
</div>