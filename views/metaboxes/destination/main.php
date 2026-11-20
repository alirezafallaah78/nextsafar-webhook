<?php
if (!defined('ABSPATH')) exit;

$text_fields = [
    'destination_entry_fee'   => 'هزینه ورود',
    'destination_duration'    => 'مدت زمان پیشنهادی بازدید',
    'destination_best_season' => 'بهترین فصل بازدید',
];
?>
<div class="nextsafar-destination-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            اطلاعات بازدید
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
        <p class="ns-hint">ℹ️ نام انگلیسی، شهر/کشور، آدرس، مختصات، تماس، وب‌سایت، ویکی‌پدیا، امتیاز و نوع مکان در «اطلاعات مکانی و سینک».</p>
    </div>

    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
            اطلاعات ساختاری و تاریخی
        </h3>
        <div class="ns-grid ns-grid-2">
            <?php
            $structural_fields = [
                'destination_Opened'               => 'تاسیس / سال ساخت',
                'destination_Height'               => 'ارتفاع',
                'destination_Architect'            => 'معمار',
                'destination_Architectural_styles' => 'سبک‌های معماری',
                'destination_Owner'                => 'مالک',
                'destination_Floor_count'          => 'تعداد طبقات',
                'destination_Former_names'         => 'نام‌های پیشین',
                'destination_Structural_system'    => 'سیستم سازه‌ای',
            ];
            foreach ($structural_fields as $id => $label) :
                $value = get_post_meta($post->ID, '_' . $id, true);
            ?>
                <div class="ns-field">
                    <label for="<?= esc_attr($id); ?>"><?= esc_html($label); ?>:</label>
                    <input class="ns-input" type="text" id="<?= esc_attr($id); ?>"
                           name="<?= esc_attr($id); ?>" value="<?= esc_attr($value); ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            توضیحات و محتوا
        </h3>
        <?php
        $textareas = [
            'destination_facilities'    => 'لیست امکانات',
            'destination_accessibility' => 'دسترسی‌ها',
            'destination_more_desc'     => 'توضیحات و توصیه‌ها',
        ];
        foreach ($textareas as $id => $label) :
            $val = get_post_meta($post->ID, '_' . $id, true);
        ?>
            <div class="ns-field" style="margin-bottom: 16px;">
                <label for="<?= esc_attr($id); ?>"><?= esc_html($label); ?>:</label>
                <textarea class="ns-textarea" id="<?= esc_attr($id); ?>"
                          name="<?= esc_attr($id); ?>" rows="6"><?= esc_textarea($val); ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>
</div>