<?php
if (!defined('ABSPATH')) exit;

$visa_options = \NextSafar\MetaBoxes\TravelGuideMetaBox::get_visa_options();
$safety_options = \NextSafar\MetaBoxes\TravelGuideMetaBox::get_safety_options();
$season_options = \NextSafar\MetaBoxes\TravelGuideMetaBox::get_season_options();
?>
<div class="nextsafar-travelguide-metabox">

    <!-- Basic Information -->
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            اطلاعات پایه کشور
        </h3>

        <div class="ns-grid ns-grid-2">
            <?php
            $text_fields = [
                'travelguide_country'      => 'نام کشور',
                'travelguide_city'         => 'پایتخت / شهر اصلی',
                'travelguide_language'     => 'زبان رسمی',
                'travelguide_currency'     => 'واحد پول (مثلاً: ریال یمن)',
                'travelguide_country_code' => 'کد بین‌المللی (مثلاً: +967)',
                'travelguide_emergency'    => 'شماره اضطراری',
                'location_coords'          => 'مختصات جغرافیایی (lat,lng)',
                'google_map_coords'        => 'لینک گوگل مپ',
                'travelguide_daily_budget' => 'میانگین هزینه روزانه (دلار)',
                'travelguide_neighbours'   => 'کشورهای همسایه (با کاما جدا)',
            ];

            foreach ($text_fields as $id => $label) :
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

    <!-- Travel Information -->
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
            اطلاعات سفر
        </h3>

        <div class="ns-grid ns-grid-3">
            <?php
            $visa_val = get_post_meta($post->ID, '_travelguide_visa_required', true);
            $safety_val = get_post_meta($post->ID, '_travelguide_safety_level', true);
            $season_val = get_post_meta($post->ID, '_travelguide_best_season', true);
            ?>

            <div class="ns-field">
                <label for="travelguide_visa_required">آیا ویزا لازم است؟</label>
                <select class="ns-input" id="travelguide_visa_required" name="travelguide_visa_required">
                    <?php foreach ($visa_options as $val => $lbl) : ?>
                        <option value="<?= esc_attr($val); ?>" <?= selected($visa_val, $val, false); ?>>
                            <?= esc_html($lbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ns-field">
                <label for="travelguide_safety_level">سطح امنیت</label>
                <select class="ns-input" id="travelguide_safety_level" name="travelguide_safety_level">
                    <?php foreach ($safety_options as $val => $lbl) : ?>
                        <option value="<?= esc_attr($val); ?>" <?= selected($safety_val, $val, false); ?>>
                            <?= esc_html($lbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ns-field">
                <label for="travelguide_best_season">بهترین فصل سفر</label>
                <select class="ns-input" id="travelguide_best_season" name="travelguide_best_season">
                    <?php foreach ($season_options as $val => $lbl) : ?>
                        <option value="<?= esc_attr($val); ?>" <?= selected($season_val, $val, false); ?>>
                            <?= esc_html($lbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

</div>