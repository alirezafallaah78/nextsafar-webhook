<?php
if (!defined('ABSPATH')) exit;

$sections = \NextSafar\MetaBoxes\HotelMetaBox::get_amenity_sections();
$special_hours_keys = ['hotel_Indoor_pool', 'hotel_outdoor_pool', 'hotel_restaurant'];

// English IDs for each section
$section_ids = [
    'حمام'              => 'sec-bath',
    'امکانات اتاق'      => 'sec-room',
    'فضای باز'          => 'sec-outdoor',
    'آشپزخانه'          => 'sec-kitchen',
    'فعالیت‌ها'         => 'sec-activities',
    'رسانه و فناوری'    => 'sec-media',
    'امکانات رفاهی'     => 'sec-facilities',
    'غذا و نوشیدنی'     => 'sec-food',
    'خدمات پذیرش'       => 'sec-reception',
    'خدمات نظافتی'      => 'sec-cleaning',
    'حیوانات خانگی'     => 'sec-pets',
    'امکانات تجاری'     => 'sec-business',
    'ایمنی و امنیت'     => 'sec-safety',
    'استخر'             => 'sec-pool',
    'پارکینگ'           => 'sec-parking',
    'حمل و نقل'         => 'sec-transport',
    'عمومی'             => 'sec-general',
    'خدمات اسپا'        => 'sec-spa',
    'انواع اتاق'        => 'sec-roomtype',
    'زبان کارکنان'      => 'sec-language',
    'دسترسی'            => 'sec-access',
];

// English display labels
$section_labels = [
    'حمام'              => 'Bathroom',
    'امکانات اتاق'      => 'Room Amenities',
    'فضای باز'          => 'Outdoor',
    'آشپزخانه'          => 'Kitchen',
    'فعالیت‌ها'         => 'Activities',
    'رسانه و فناوری'    => 'Media & Technology',
    'امکانات رفاهی'     => 'Wellness Facilities',
    'غذا و نوشیدنی'     => 'Food & Drinks',
    'خدمات پذیرش'       => 'Reception Services',
    'خدمات نظافتی'      => 'Cleaning Services',
    'حیوانات خانگی'     => 'Pets',
    'امکانات تجاری'     => 'Business Facilities',
    'ایمنی و امنیت'     => 'Safety & Security',
    'استخر'             => 'Pool',
    'پارکینگ'           => 'Parking',
    'حمل و نقل'         => 'Transport',
    'عمومی'             => 'General',
    'خدمات اسپا'        => 'Spa Services',
    'انواع اتاق'        => 'Room Types',
    'زبان کارکنان'      => 'Staff Languages',
    'دسترسی'            => 'Accessibility',
];
?>

<div class="ns-amenities-accordion">
    <?php $index = 0; ?>
    <?php foreach ($sections as $section_title => $fields) :
        $section_id = $section_ids[$section_title] ?? ('sec-' . $index);
        $index++;
    ?>
        <div class="ns-amenity-group">
            <button type="button" class="ns-amenity-header" data-target="<?= esc_attr($section_id); ?>">
                <span>
                    <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <?= esc_html($section_title); ?>
                    <span class="ns-count-badge"><?= count($fields); ?></span>
                </span>
                <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="ns-amenity-body" id="<?= esc_attr($section_id); ?>" style="display: none;">
                <div class="ns-grid ns-grid-3">
                    <?php foreach ($fields as $key => $label) :
                        $value = get_post_meta($post->ID, '_' . $key, true);
                        $extra = get_post_meta($post->ID, '_' . $key . '_extra', true);
                        $has_hours = in_array($key, $special_hours_keys);
                        $hours = get_post_meta($post->ID, 'working_hours_' . $key, true);
                    ?>
                        <div class="ns-amenity-item">
                            <div class="ns-amenity-main">
                                <label>
                                    <input type="checkbox" name="<?= esc_attr($key); ?>"
                                           value="yes" <?= checked($value, 'yes', false); ?>>
                                    <?= esc_html($label); ?>
                                </label>
                                <?php if ($has_hours) : ?>
                                    <button type="button" class="ns-icon-btn ns-icon-btn-sm set-working-hours"
                                            data-key="<?= esc_attr($key); ?>"
                                            data-hours='<?= esc_attr(json_encode($hours ?: [])); ?>'
                                            title="تنظیم ساعات کاری">
                                        <svg class="ns-icon ns-icon-xs" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <label class="ns-extra-charge">
                                <input type="checkbox" name="<?= esc_attr($key . '_extra'); ?>"
                                       value="yes" <?= checked($extra, 'yes', false); ?>>
                                <small>💰 هزینه اضافی</small>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>