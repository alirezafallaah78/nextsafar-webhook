<?php if (!defined('ABSPATH')) exit; ?>

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
        <h2>✅ کلمات کلیدی مثبت (<?= count($positive); ?>)</h2>
        <div class="ns-keywords-list">
            <?php foreach ($positive as $kw): ?>
                <div class="ns-keyword-item ns-keyword-positive">
                    <span class="ns-keyword-text"><?= esc_html($kw['keyword']); ?></span>
                    <span class="ns-badge ns-badge-success">+<?= (int)$kw['weight']; ?></span>
                    <button type="button" class="button button-small delete-keyword-btn" data-id="<?= $kw['id']; ?>">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ns-card">
        <h2>❌ کلمات کلیدی منفی (<?= count($negative); ?>)</h2>
        <div class="ns-keywords-list">
            <?php foreach ($negative as $kw): ?>
                <div class="ns-keyword-item ns-keyword-negative">
                    <span class="ns-keyword-text"><?= esc_html($kw['keyword']); ?></span>
                    <span class="ns-badge ns-badge-danger"><?= (int)$kw['weight']; ?></span>
                    <button type="button" class="button button-small delete-keyword-btn" data-id="<?= $kw['id']; ?>">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>