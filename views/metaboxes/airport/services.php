<?php if (!defined('ABSPATH')) exit; ?>

<div class="nextsafar-airport-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            خدمات فرودگاه
        </h3>
        
        <div class="ns-amenities-accordion">
            <?php 
            $index = 0;
            foreach ($sections as $section_title => $fields) :
                $section_id = 'airport-svc-' . $index;
                $section_meta_key = 'svc_custom_' . $index;
                $custom_values = get_post_meta($post->ID, $section_meta_key, true);
                if (!is_array($custom_values) || empty($custom_values)) {
                    $custom_values = [''];
                }
                $index++;
            ?>
                <div class="ns-amenity-group">
                    <button type="button" class="ns-amenity-header"
                            data-target="<?= esc_attr($section_id); ?>"
                            data-meta-key="<?= esc_attr($section_meta_key); ?>">
                        <span>
                            <?= esc_html($section_title); ?>
                            <span class="ns-count-badge"><?= count($fields); ?></span>
                        </span>
                        <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="ns-amenity-body" id="<?= esc_attr($section_id); ?>" style="display: none;">
                        
                        <!-- Service checkboxes -->
                        <div class="ns-grid ns-grid-3">
                            <?php foreach ($fields as $key => $label) :
                                $value = get_post_meta($post->ID, '_' . $key, true);
                            ?>
                                <div class="ns-amenity-item">
                                    <div class="ns-amenity-main">
                                        <label>
                                            <input type="checkbox" name="<?= esc_attr($key); ?>"
                                                   value="yes" <?= checked($value, 'yes', false); ?>>
                                            <?= esc_html($label); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Repeatable fields -->
                        <div class="ns-repeatable-wrapper" data-name="<?= esc_attr($section_meta_key); ?>">
                            <div class="ns-repeatable-list">
                                <?php foreach ($custom_values as $val) : ?>
                                    <div class="ns-repeatable-item">
                                        <input type="text" name="<?= esc_attr($section_meta_key); ?>[]"
                                               value="<?= esc_attr($val); ?>" placeholder="مورد سفارشی...">
                                        <button type="button" class="remove-item">×</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="ns-btn-add add-item">
                                <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                افزودن مورد
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>