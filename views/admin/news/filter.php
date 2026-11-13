<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap nextsafar-wrap">
    <h1>🎛️ مدیریت فیلترها</h1>
    <p class="description">
        مدیریت کلمات کلیدی مثبت و منفی برای فیلتر کردن اخبار. 
        اخبار با امتیاز بالاتر از <?= $publishThreshold; ?> منتشر می‌شوند.
    </p>

    <!-- ═══ افزودن کلمه کلیدی ═══ -->
    <div class="ns-card">
        <h2>➕ افزودن کلمه کلیدی جدید</h2>
        <div class="ns-add-keyword-form">
            <div class="ns-form-row">
                <input type="text" id="new-keyword" class="ns-input" placeholder="کلمه کلیدی (مثال: گردشگری)">
                <select id="new-keyword-type" class="ns-select">
                    <option value="positive">مثبت (+)</option>
                    <option value="negative">منفی (-)</option>
                </select>
                <input type="number" id="new-keyword-weight" class="ns-input ns-input-small" value="15" placeholder="وزن">
                <button type="button" id="add-keyword-btn" class="button button-primary">افزودن</button>
            </div>
        </div>
    </div>

    <!-- ═══ کلمات کلیدی مثبت ═══ -->
    <div class="ns-card">
        <h2>✅ کلمات کلیدی مثبت (<?= count($positive); ?>)</h2>
        <p class="description">این کلمات باعث انتشار خبر می‌شوند</p>
        <div class="ns-keywords-list">
            <?php foreach ($positive as $kw): ?>
                <div class="ns-keyword-item ns-keyword-positive">
                    <span class="ns-keyword-text"><?= esc_html($kw['keyword']); ?></span>
                    <span class="ns-keyword-meta">
                        <span class="ns-badge ns-badge-success">+<?= (int)$kw['weight']; ?></span>
                        <span class="ns-badge"><?= esc_html($kw['category']); ?></span>
                    </span>
                    <button type="button" class="button button-small delete-keyword-btn" data-id="<?= $kw['id']; ?>">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══ کلمات کلیدی منفی ═══ -->
    <div class="ns-card">
        <h2>❌ کلمات کلیدی منفی (<?= count($negative); ?>)</h2>
        <p class="description">این کلمات باعث حذف خبر می‌شوند</p>
        <div class="ns-keywords-list">
            <?php foreach ($negative as $kw): ?>
                <div class="ns-keyword-item ns-keyword-negative">
                    <span class="ns-keyword-text"><?= esc_html($kw['keyword']); ?></span>
                    <span class="ns-keyword-meta">
                        <span class="ns-badge ns-badge-danger"><?= (int)$kw['weight']; ?></span>
                        <span class="ns-badge"><?= esc_html($kw['category']); ?></span>
                    </span>
                    <button type="button" class="button button-small delete-keyword-btn" data-id="<?= $kw['id']; ?>">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══ تاریخچه فیلتر ═══ -->
    <div class="ns-card">
        <h2>📜 تاریخچه تصمیمات فیلتر (50 اخیر)</h2>
        <button type="button" class="button" id="refresh-history-btn">🔄 بارگذاری مجدد</button>
        
        <div id="history-container" style="margin-top:15px;">
            <?php if (!empty($history)): ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>منبع</th>
                            <th>امتیاز</th>
                            <th>تصمیم</th>
                            <th>دلیل</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= esc_html(mb_substr($h['news_title'], 0, 80)); ?></td>
                                <td><?= esc_html($h['source_name']); ?></td>
                                <td><strong><?= (int)$h['score']; ?></strong></td>
                                <td>
                                    <?php
                                    $decisionClass = [
                                        'publish' => 'ns-badge-success',
                                        'draft'   => 'ns-badge-warning',
                                        'review'  => 'ns-badge-info',
                                        'delete'  => 'ns-badge-danger',
                                    ][$h['decision']] ?? '';
                                    $decisionLabel = [
                                        'publish' => '✅ انتشار',
                                        'draft'   => '📝 پیش‌نویس',
                                        'review'  => '🤖 بررسی AI',
                                        'delete'  => '🗑️ حذف',
                                    ][$h['decision']] ?? $h['decision'];
                                    ?>
                                    <span class="ns-badge <?= $decisionClass; ?>"><?= $decisionLabel; ?></span>
                                    <?php if ($h['ai_checked']): ?>
                                        <span class="ns-badge">🤖</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= esc_html(mb_substr($h['reason'], 0, 100)); ?></small></td>
                                <td><small><?= esc_html($h['created_at']); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>هنوز تصمیمی ثبت نشده</p>
            <?php endif; ?>
        </div>
    </div>
</div>