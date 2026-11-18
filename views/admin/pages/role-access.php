<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$role_access = nextsafar()->role_access;
$roles = $role_access->get_editable_roles();
$all_menus = $role_access->get_all_menus();

require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
?>

<div class="nextsafar-main">
    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    
    <main class="nextsafar-content nextsafar-role-access">
        <div class="role-access-header">
            <div>
                <h1 style="margin:0 0 8px 0; font-size:24px; font-weight:600;">مدیریت دسترسی‌ها</h1>
                <p style="margin:0; color:var(--ns-text-muted, #6b7280); font-size:14px;">
                    مدیریت دسترسی منوها و قابلیت‌ها برای هر نقش کاربری
                </p>
            </div>
        </div>

        <div class="role-access-layout">
            <!-- Sidebar: لیست نقش‌ها -->
            <aside class="role-access-sidebar">
                <div class="role-list">
                    <?php foreach ( $roles as $role_slug => $role_data ) : 
                        $settings = $role_access->get_role_settings( $role_slug );
                        $is_super = $role_data['is_super'];
                    ?>
                        <button type="button" 
                                class="role-item <?php echo $role_slug === 'editor' ? 'active' : ''; ?>" 
                                data-role="<?php echo esc_attr( $role_slug ); ?>"
                                <?php echo $is_super ? 'disabled' : ''; ?>>
                            <div class="role-item-header">
                                <span class="role-name"><?php echo esc_html( $role_data['name'] ); ?></span>
                                <?php if ( $is_super ) : ?>
                                    <span class="role-badge super">ادمین کل</span>
                                <?php endif; ?>
                            </div>
                            <div class="role-item-meta">
                                <span class="role-user-count">
                                    <?php echo esc_html( $role_data['user_count'] ); ?> کاربر
                                </span>
                                <?php if ( ! $is_super && ! empty( $settings['menus'] ) ) : 
                                    $enabled_count = count( array_filter( $settings['menus'] ) );
                                ?>
                                    <span class="role-menu-count">
                                        <?php echo esc_html( $enabled_count ); ?> منو
                                    </span>
                                <?php endif; ?>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- Main: تنظیمات نقش انتخاب شده -->
            <div class="role-access-main">
                <div id="role-settings-container">
                    <div class="role-settings-placeholder">
                        <div class="placeholder-icon">👈</div>
                        <h3>یک نقش را از لیست انتخاب کنید</h3>
                        <p>تنظیمات دسترسی و قابلیت‌ها برای آن نقش را می‌توانید در اینجا مدیریت کنید.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
//  ارسال داده‌ها به JS
window.nextsafarRoleAccess = {
    ajaxUrl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
    nonce: '<?php echo wp_create_nonce( 'nextsafar_role_access' ); ?>',
    roles: <?php echo wp_json_encode( $roles ); ?>,
    allMenus: <?php echo wp_json_encode( $all_menus ); ?>,
    strings: {
        saving: 'در حال ذخیره...',
        saved: 'ذخیره شد ✓',
        error: 'خطا در ذخیره',
        confirmReset: 'آیا از بازنشانی تنظیمات این نقش به حالت پیش‌فرض مطمئن هستید؟',
        superAdminWarning: 'ادمین کل همیشه دسترسی کامل به تمام منوها دارد.',
        noMenus: 'هیچ منویی برای نمایش وجود ندارد.',
        canDeleteLabel: 'اجازه حذف محتوا',
        canDeleteHelp: 'اگر فعال باشد، کاربران این نقش می‌توانند پست‌ها، صفحات و سایر محتواها را حذف کنند.',
        menusSectionTitle: 'دسترسی به منوها',
        menusSectionHelp: 'منوهایی که تیک می‌زنید برای کاربران این نقش در دسترس خواهد بود.',
        selectAll: 'انتخاب همه',
        deselectAll: 'لغو انتخاب همه',
        reset: 'بازنشانی به پیش‌فرض',
        save: 'ذخیره تنظیمات',
    }
};
</script>

<style>
/* =========================================================
   Role Access Page Styles
========================================================= */
.nextsafar-role-access {
    padding: 24px;
}

.role-access-header {
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.role-access-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 24px;
    align-items: start;
}

/* Sidebar */
.role-access-sidebar {
    background: var(--ns-surface, #fff);
    border: 1px solid var(--ns-border, #e5e7eb);
    border-radius: 12px;
    overflow: hidden;
    position: sticky;
    top: 90px;
}

.role-list {
    display: flex;
    flex-direction: column;
}

.role-item {
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--ns-border, #e5e7eb);
    padding: 16px 18px;
    text-align: right;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    font-family: inherit;
}

.role-item:last-child {
    border-bottom: none;
}

.role-item:hover:not(:disabled) {
    background: var(--ns-primary-light-3, rgba(67, 171, 255, 0.08));
}

.role-item.active {
    background: var(--ns-primary-light-2, rgba(67, 171, 255, 0.15));
    border-right: 3px solid var(--ns-primary, #43abff);
}

.role-item:disabled {
    cursor: not-allowed;
    opacity: 0.9;
}

.role-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.role-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--ns-text, #111827);
}

.role-badge.super {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.role-item-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: var(--ns-text-muted, #6b7280);
}

.role-user-count,
.role-menu-count {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Main content */
.role-access-main {
    background: var(--ns-surface, #fff);
    border: 1px solid var(--ns-border, #e5e7eb);
    border-radius: 12px;
    min-height: 600px;
    overflow: hidden;
}

.role-settings-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 40px;
    text-align: center;
    color: var(--ns-text-muted, #6b7280);
}

.placeholder-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.6;
}

.role-settings-placeholder h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--ns-text, #111827);
}

.role-settings-placeholder p {
    margin: 0;
    font-size: 14px;
}

/* Settings panel */
.role-settings-panel {
    padding: 24px;
}

.role-settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--ns-border, #e5e7eb);
}

.role-settings-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-settings-title h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--ns-text, #111827);
}

.role-settings-actions {
    display: flex;
    gap: 8px;
}

/* Sections */
.role-settings-section {
    margin-bottom: 32px;
}

.role-settings-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--ns-text, #111827);
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-help {
    font-size: 13px;
    color: var(--ns-text-muted, #6b7280);
    margin: 0 0 16px 0;
}

/* Delete toggle */
.delete-toggle-wrapper {
    background: var(--ns-bg, #f9fafb);
    border: 1px solid var(--ns-border, #e5e7eb);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.delete-toggle-wrapper:hover {
    border-color: var(--ns-primary, #43abff);
    background: var(--ns-primary-light-3, rgba(67, 171, 255, 0.05));
}

.delete-toggle-wrapper input[type="checkbox"] {
    margin-top: 2px;
    width: 18px;
    height: 18px;
    accent-color: var(--ns-primary, #43abff);
    cursor: pointer;
}

.delete-toggle-content {
    flex: 1;
}

.delete-toggle-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--ns-text, #111827);
    margin-bottom: 4px;
}

.delete-toggle-help {
    display: block;
    font-size: 12px;
    color: var(--ns-text-muted, #6b7280);
    line-height: 1.5;
}

/* Menu categories */
.menu-category {
    border: 1px solid var(--ns-border, #e5e7eb);
    border-radius: 10px;
    margin-bottom: 12px;
    overflow: hidden;
}

.menu-category-header {
    background: var(--ns-bg, #f9fafb);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}

.menu-category-header:hover {
    background: var(--ns-primary-light-3, rgba(67, 171, 255, 0.05));
}

.menu-category-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--ns-text, #111827);
    display: flex;
    align-items: center;
    gap: 10px;
}

.menu-category-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--ns-primary-light-3, rgba(67, 171, 255, 0.1));
    border-radius: 6px;
    color: var(--ns-primary, #43abff);
}

.menu-category-count {
    font-size: 12px;
    color: var(--ns-text-muted, #6b7280);
    background: var(--ns-surface, #fff);
    padding: 2px 8px;
    border-radius: 10px;
}

.menu-category-body {
    padding: 12px 16px;
    border-top: 1px solid var(--ns-border, #e5e7eb);
}

.menu-item-check {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
    margin-bottom: 4px;
}

.menu-item-check:last-child {
    margin-bottom: 0;
}

.menu-item-check:hover {
    background: var(--ns-primary-light-3, rgba(67, 171, 255, 0.05));
}

.menu-item-check input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--ns-primary, #43abff);
    cursor: pointer;
}

.menu-item-label {
    font-size: 13px;
    color: var(--ns-text, #111827);
    flex: 1;
}

.menu-item-slug {
    font-size: 11px;
    color: var(--ns-text-muted, #6b7280);
    font-family: monospace;
    direction: ltr;
}

.submenu-list {
    margin-right: 26px;
    margin-top: 4px;
    padding-right: 12px;
    border-right: 2px solid var(--ns-border, #e5e7eb);
}

.submenu-list .menu-item-check {
    padding: 6px 8px;
}

.submenu-list .menu-item-label {
    font-size: 12px;
}

/* Bulk actions */
.bulk-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

.bulk-btn {
    background: transparent;
    border: 1px solid var(--ns-border, #e5e7eb);
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12px;
    color: var(--ns-text-muted, #6b7280);
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}

.bulk-btn:hover {
    border-color: var(--ns-primary, #43abff);
    color: var(--ns-primary, #43abff);
    background: var(--ns-primary-light-3, rgba(67, 171, 255, 0.05));
}

/* Buttons */
.btn {
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: var(--ns-primary, #43abff);
    color: #fff;
    border-color: var(--ns-primary, #43abff);
}

.btn-primary:hover {
    background: var(--ns-primary-dark, #3384c6);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(67, 171, 255, 0.3);
}

.btn-secondary {
    background: transparent;
    color: var(--ns-text, #111827);
    border-color: var(--ns-border, #e5e7eb);
}

.btn-secondary:hover {
    background: var(--ns-bg, #f9fafb);
    border-color: var(--ns-text-muted, #6b7280);
}

.btn-danger {
    background: transparent;
    color: var(--ns-danger, #dc2626);
    border-color: var(--ns-danger, #dc2626);
}

.btn-danger:hover {
    background: var(--ns-danger, #dc2626);
    color: #fff;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Alert boxes */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.alert-warning {
    background: #fffbeb;
    border: 1px solid #fcd34d;
    color: #92400e;
}

.alert-success {
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #166534;
}

.alert-icon {
    font-size: 18px;
    flex-shrink: 0;
}

/* Loading */
.settings-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 40px;
    gap: 12px;
    color: var(--ns-text-muted, #6b7280);
}

.nextsafar-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--ns-border, #e5e7eb);
    border-top-color: var(--ns-primary, #43abff);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 900px) {
    .role-access-layout {
        grid-template-columns: 1fr;
    }
    
    .role-access-sidebar {
        position: static;
    }
}
</style>

<?php require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php'; ?>