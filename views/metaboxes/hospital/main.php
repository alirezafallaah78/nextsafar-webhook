<?php
if (!defined('ABSPATH')) exit;

$text_fields = [
    'hospital_emergency_phone' => 'شماره اورژانس',
    'hospital_fax'             => 'فکس',
    'hospital_mail'            => 'ایمیل',
];
?>
<div class="nextsafar-hospital-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            تماس‌های اختصاصی
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
        <p class="ns-hint">ℹ️ نام انگلیسی، شهر/کشور، آدرس، مختصات، تلفن، وب‌سایت، امتیاز و نوع در «اطلاعات مکانی و سینک».</p>
    </div>

    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M12 2v20M2 12h20"/></svg>
            ساختار بیمارستان
        </h3>
        <div class="ns-grid ns-grid-2">
            <?php $beds = get_post_meta($post->ID, '_hospital_number_of_bed', true); ?>
            <div class="ns-field">
                <label for="hospital_number_of_bed">تعداد تخت‌ها:</label>
                <input class="ns-input ns-input-number" type="number" min="0"
                       id="hospital_number_of_bed" name="hospital_number_of_bed"
                       value="<?= esc_attr($beds); ?>">
            </div>
        </div>
    </div>

    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            توضیحات و محتوا
        </h3>
        <?php
        $textareas = [
            'hospital_facilities'       => 'امکانات بیمارستان',
            'hospital_services'         => 'خدمات',
            'hospital_special_services' => 'خدمات ویژه',
            'hospital_key_doctors'      => 'پزشکان برجسته',
            'hospital_insurance'        => 'امکانات بیمه',
            'hospital_languages'        => 'زبان‌های پشتیبانی',
            'hospital_accessibility'    => 'دسترسی‌ها',
            'hospital_more_desc'        => 'توضیحات و توصیه‌ها',
        ];
        foreach ($textareas as $id => $label) :
            $val = get_post_meta($post->ID, '_' . $id, true);
        ?>
            <div class="ns-field" style="margin-bottom: 16px;">
                <label for="<?= esc_attr($id); ?>"><?= esc_html($label); ?>:</label>
                <textarea class="ns-textarea" id="<?= esc_attr($id); ?>"
                          name="<?= esc_attr($id); ?>" rows="5"><?= esc_textarea($val); ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>
</div>