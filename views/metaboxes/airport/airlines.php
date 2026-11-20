<?php if (!defined('ABSPATH')) exit; ?>

<div class="nextsafar-airport-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
            خطوط هوایی (ایرلاین‌ها)
        </h3>
        
        <!-- Main airline website -->
        <?php $main_site = get_post_meta($post->ID, '_airline_main_airline_site', true); ?>
        <div class="ns-field" style="margin-bottom: 20px;">
            <label for="airline-main-site">آدرس سایت مرجع ایرلاین‌ها:</label>
            <input class="ns-input" type="url" id="airline-main-site"
                   name="airline_main_airline_site" value="<?= esc_attr($main_site); ?>"
                   placeholder="https://..." dir="ltr">
        </div>

        <!-- Airlines list -->
        <?php $airlines = get_post_meta($post->ID, '_airlines', true); ?>
        <?php if (!is_array($airlines)) $airlines = []; ?>

        <label class="ns-label">لیست خطوط هوایی فعال در این فرودگاه:</label>

        <div class="ns-airlines-header">
            <span>نام ایرلاین</span>
            <span>لینک سایت</span>
            <span></span>
        </div>

        <div id="airlines-wrapper">
            <?php foreach ($airlines as $index => $airline) : ?>
                <div class="ns-airline-item" data-index="<?= $index; ?>">
                    <input type="text" name="airlines[<?= $index; ?>][name]"
                           value="<?= esc_attr($airline['name']); ?>"
                           placeholder="نام ایرلاین (مثلاً: ماهان)">
                    <input type="url" name="airlines[<?= $index; ?>][link]"
                           value="<?= esc_url($airline['link']); ?>"
                           placeholder="لینک سایت (اختیاری)" dir="ltr">
                    <button type="button" class="remove-airline" title="حذف">×</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="add-airline" class="ns-btn-add">
            <svg class="ns-icon ns-icon-sm" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            افزودن ایرلاین جدید
        </button>
    </div>
</div>