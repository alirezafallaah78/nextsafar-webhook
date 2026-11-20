<?php
if (!defined('ABSPATH')) exit;

$week_days = [
    'شنبه'      => 'Saturday',
    'یکشنبه'    => 'Sunday',
    'دوشنبه'    => 'Monday',
    'سه‌شنبه'   => 'Tuesday',
    'چهارشنبه'  => 'Wednesday',
    'پنج‌شنبه'  => 'Thursday',
    'جمعه'      => 'Friday',
];

$work_time = get_post_meta($post->ID, '_destination_work_time', true);
if (!is_array($work_time)) $work_time = [];
?>
<div class="nextsafar-hours-metabox">
    <div class="ns-section">
        <h3 class="ns-section-title">
            <svg class="ns-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            ساعات کاری
        </h3>

        <p class="ns-hint" style="margin-bottom: 16px;">
            ⏰ برای هر روز می‌توانید چند بازه زمانی تعریف کنید
        </p>

        <div class="ns-working-hours">
            <?php foreach ($week_days as $day_fa => $day_en) :
                $slots = $work_time[$day_fa] ?? [];
                
                $option = 'custom';
                if (empty($slots)) {
                    $option = 'custom';
                } elseif (count($slots) === 1) {
                    if (($slots[0]['from'] ?? '') === 'off') $option = 'off';
                    elseif (($slots[0]['from'] ?? '') === '24h') $option = '24h';
                }
            ?>
                <div class="ns-wh-day" data-day="<?= esc_attr($day_fa); ?>">
                    <span class="ns-wh-day-name"><?= esc_html($day_fa); ?></span>
                    
                    <label class="ns-wh-option">
                        <input type="radio" name="destination_work_time_option[<?= esc_attr($day_fa); ?>]"
                               value="custom" <?= checked($option, 'custom'); ?> class="ns-hours-option">
                        فعال
                    </label>
                    
                    <label class="ns-wh-option">
                        <input type="radio" name="destination_work_time_option[<?= esc_attr($day_fa); ?>]"
                               value="off" <?= checked($option, 'off'); ?> class="ns-hours-option">
                        تعطیل
                    </label>
                    
                    <label class="ns-wh-option">
                        <input type="radio" name="destination_work_time_option[<?= esc_attr($day_fa); ?>]"
                               value="24h" <?= checked($option, '24h'); ?> class="ns-hours-option">
                        ۲۴ ساعته
                    </label>

                    <div class="ns-wh-slots" style="<?= $option !== 'custom' ? 'display:none;' : ''; ?> width: 100%; margin-top: 10px;">
                        <?php if (empty($slots)) : ?>
                            <div class="ns-wh-slot">
                                <input type="time" name="destination_work_time[<?= esc_attr($day_fa); ?>][from][]" value="">
                                <span>تا</span>
                                <input type="time" name="destination_work_time[<?= esc_attr($day_fa); ?>][to][]" value="">
                                <span class="ns-remove-slot" onclick="this.parentElement.remove()">×</span>
                            </div>
                        <?php else : ?>
                            <?php foreach ($slots as $slot) : ?>
                                <div class="ns-wh-slot">
                                    <input type="time" name="destination_work_time[<?= esc_attr($day_fa); ?>][from][]"
                                           value="<?= esc_attr($slot['from'] ?? ''); ?>">
                                    <span>تا</span>
                                    <input type="time" name="destination_work_time[<?= esc_attr($day_fa); ?>][to][]"
                                           value="<?= esc_attr($slot['to'] ?? ''); ?>">
                                    <span class="ns-remove-slot" onclick="this.parentElement.remove()">×</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <button type="button" class="ns-btn-add ns-btn-add-sm" onclick="addTimeSlot(this)">
                            <svg class="ns-icon ns-icon-xs" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            افزودن بازه
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function addTimeSlot(button) {
    const slotsContainer = button.parentElement;
    const day = slotsContainer.closest('.ns-wh-day').dataset.day;
    
    const newSlot = document.createElement('div');
    newSlot.className = 'ns-wh-slot';
    newSlot.innerHTML = `
        <input type="time" name="destination_work_time[${day}][from][]">
        <span>تا</span>
        <input type="time" name="destination_work_time[${day}][to][]">
        <span class="ns-remove-slot" onclick="this.parentElement.remove()">×</span>
    `;
    
    slotsContainer.insertBefore(newSlot, button);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ns-hours-option').forEach(radio => {
        radio.addEventListener('change', function() {
            const day = this.closest('.ns-wh-day');
            const slots = day.querySelector('.ns-wh-slots');
            
            if (this.value === 'custom' && this.checked) {
                slots.style.display = '';
            } else if (this.checked) {
                slots.style.display = 'none';
            }
        });
    });
});
</script>