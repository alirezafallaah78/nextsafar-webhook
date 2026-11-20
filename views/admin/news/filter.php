<?php
if (!defined('ABSPATH')) exit;
$positive = (isset($positive) && is_array($positive)) ? $positive : array();
$negative = (isset($negative) && is_array($negative)) ? $negative : array();
?>

<div class="wrap nextsafar-wrap">
    <!-- ═══ افزودن کلمه ═══ -->
    <div class="nextsafar-panel">
        <div class="nextsafar-panel-header"><h2>افزودن کلمه کلیدی جدید</h2></div>
        <div class="nextsafar-panel-body">
            <div class="ns-form-grid cols-3">
                <div class="nextsafar-form-group span-2">
                    <label for="new-keyword">کلمه کلیدی</label>
                    <input type="text" id="new-keyword" class="nextsafar-form-input" placeholder="مثال: سفر، گردشگری، هتل">
                </div>
                <div class="nextsafar-form-group">
                    <label for="new-keyword-type">نوع کلمه</label>
                    <select id="new-keyword-type" class="nextsafar-form-input">
                        <option value="positive">مثبت</option>
                        <option value="negative">منفی</option>
                    </select>
                </div>
                <div class="nextsafar-form-group">
                    <label for="new-keyword-weight">وزن امتیاز</label>
                    <input type="number" id="new-keyword-weight" class="nextsafar-form-input" value="15" min="1" max="100">
                </div>
                <div class="nextsafar-form-group span-2" style="display:flex; align-items:flex-end; justify-content:flex-end;">
                    <button type="button" id="add-keyword-btn" class="nextsafar-primary-button">افزودن کلمه</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ لیست‌ها ═══ -->
    <div class="ns-filter-layout" style="margin-top: 20px;">

        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <h2>کلمات مثبت <span class="ns-count-badge"><?php echo count($positive); ?></span></h2>
                <input type="text" class="ns-keyword-search" placeholder="جستجو در لیست..." data-target="positive-list">
            </div>
            <div class="nextsafar-panel-body">
                <?php if (empty($positive)) : ?>
                    <div class="ns-list-empty">هیچ کلمه مثبتی ثبت نشده است.</div>
                <?php else : ?>
                    <div class="ns-chips" id="positive-list">
                        <?php foreach ($positive as $kw) : ?>
                            <span class="ns-chip ns-chip-positive" data-keyword="<?php echo esc_attr($kw['keyword']); ?>">
                                <span class="ns-chip-text"><?php echo esc_html($kw['keyword']); ?></span>
                                <span class="ns-chip-weight">+<?php echo (int) $kw['weight']; ?></span>
                                <button type="button" class="ns-chip-delete delete-keyword-btn" data-id="<?php echo (int) $kw['id']; ?>" title="حذف کلمه">×</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="nextsafar-panel">
            <div class="nextsafar-panel-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <h2>کلمات منفی <span class="ns-count-badge"><?php echo count($negative); ?></span></h2>
                <input type="text" class="ns-keyword-search" placeholder="جستجو در لیست..." data-target="negative-list">
            </div>
            <div class="nextsafar-panel-body">
                <?php if (empty($negative)) : ?>
                    <div class="ns-list-empty">هیچ کلمه منفی ثبت نشده است.</div>
                <?php else : ?>
                    <div class="ns-chips" id="negative-list">
                        <?php foreach ($negative as $kw) : ?>
                            <span class="ns-chip ns-chip-negative" data-keyword="<?php echo esc_attr($kw['keyword']); ?>">
                                <span class="ns-chip-text"><?php echo esc_html($kw['keyword']); ?></span>
                                <span class="ns-chip-weight">-<?php echo (int) $kw['weight']; ?></span>
                                <button type="button" class="ns-chip-delete delete-keyword-btn" data-id="<?php echo (int) $kw['id']; ?>" title="حذف کلمه">×</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    "use strict";

    document.querySelectorAll(".ns-keyword-search").forEach(function (input) {
        input.addEventListener("input", function () {
            var q = this.value.trim().toLowerCase();
            var list = document.getElementById(this.getAttribute("data-target"));
            if (!list) return;
            list.querySelectorAll(".ns-chip").forEach(function (chip) {
                var text = (chip.getAttribute("data-keyword") || "").toLowerCase();
                chip.style.display = (q === "" || text.indexOf(q) !== -1) ? "" : "none";
            });
        });
    });

    var newKw = document.getElementById("new-keyword");
    if (newKw) {
        newKw.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                var btn = document.getElementById("add-keyword-btn");
                if (btn) btn.click();
            }
        });
    }
})();
</script>