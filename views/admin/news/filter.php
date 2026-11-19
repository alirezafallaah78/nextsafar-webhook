<?php
if (!defined('ABSPATH')) exit;

// ✅ گارد — حتی اگر داده لود نشده باشد، صفحه Fatal نمی‌دهد
$positive = (isset($positive) && is_array($positive)) ? $positive : array();
$negative = (isset($negative) && is_array($negative)) ? $negative : array();
?>
<div class="wrap nextsafar-wrap">
    <h1>🎛️ مدیریت فیلترها</h1>
    <p class="description">
        مدیریت کلمات کلیدی مثبت و منفی برای فیلتر کردن اخبار.
    </p>

    <div class="ns-card">
        <h2>➕ افزودن کلمه کلیدی جدید</h2>
        <div class="ns-add-keyword-form">
            <div class="ns-form-row">
                <input type="text" id="new-keyword" class="ns-input" placeholder="کلمه کلیدی">
                <select id="new-keyword-type" class="ns-select">
                    <option value="positive">مثبت (+)</option>
                    <option value="negative">منفی (-)</option>
                </select>
                <input type="number" id="new-keyword-weight" class="ns-input ns-input-small" value="15">
                <button type="button" id="add-keyword-btn" class="button button-primary">افزودن</button>
            </div>
        </div>
    </div>

    <div class="ns-card">
        <h2>✅ کلمات کلیدی مثبت (<?php echo count($positive); ?>)</h2>
        <div class="ns-keywords-list">
            <?php if (empty($positive)) : ?>
                <p class="description">هیچ کلمه کلیدی مثبتی ثبت نشده است.</p>
            <?php else : ?>
                <?php foreach ($positive as $kw) : ?>
                    <div class="ns-keyword-item ns-keyword-positive">
                        <span class="ns-keyword-text"><?php echo esc_html($kw['keyword']); ?></span>
                        <span class="ns-badge ns-badge-success">+<?php echo (int) $kw['weight']; ?></span>
                        <button type="button" class="button button-small delete-keyword-btn" data-id="<?php echo (int) $kw['id']; ?>">✕</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="ns-card">
        <h2>❌ کلمات کلیدی منفی (<?php echo count($negative); ?>)</h2>
        <div class="ns-keywords-list">
            <?php if (empty($negative)) : ?>
                <p class="description">هیچ کلمه کلیدی منفی ثبت نشده است.</p>
            <?php else : ?>
                <?php foreach ($negative as $kw) : ?>
                    <div class="ns-keyword-item ns-keyword-negative">
                        <span class="ns-keyword-text"><?php echo esc_html($kw['keyword']); ?></span>
                        <span class="ns-badge ns-badge-danger"><?php echo (int) $kw['weight']; ?></span>
                        <button type="button" class="button button-small delete-keyword-btn" data-id="<?php echo (int) $kw['id']; ?>">✕</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>