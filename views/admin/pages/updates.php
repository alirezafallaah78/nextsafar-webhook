<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page_title = 'Updates';

require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';

// Get updates data
$updates = new NextSafar_Updates();
$data    = $updates->get_updates_data();
?>

<div class="nextsafar-main">

    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>

    <main class="nextsafar-content">

        <div class="nextsafar-page-header">
            <div class="nextsafar-page-actions">
                <button type="button" class="nextsafar-secondary-button" id="nextsafar-check-updates">
                ↻ <?php echo esc_html__( 'بررسی مجدد', 'nextsafar-admin' ); ?>
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="nextsafar-updates-summary">
            
            <div class="nextsafar-update-summary-card">
                <div class="nextsafar-update-summary-icon nextsafar-update-icon-core">
                    W
                </div>
                <div class="nextsafar-update-summary-info">
                    <span>WordPress Core</span>
                    <strong>
                        <?php if ( $data['core']['available'] ) : ?>
                            <span class="nextsafar-update-available">Update Available</span>
                        <?php else : ?>
                            <span class="nextsafar-update-up-to-date">Up to date</span>
                        <?php endif; ?>
                    </strong>
                    <small>Version <?php echo esc_html( $data['core']['current_version'] ); ?></small>
                </div>
            </div>

            <div class="nextsafar-update-summary-card">
                <div class="nextsafar-update-summary-icon nextsafar-update-icon-plugins">
                    ⚙
                </div>
                <div class="nextsafar-update-summary-info">
                    <span>Plugins</span>
                    <strong><?php echo number_format_i18n( $data['plugin_count'] ); ?> available</strong>
                    <small>
                        <?php echo count( get_plugins() ); ?> total installed
                    </small>
                </div>
            </div>

            <div class="nextsafar-update-summary-card">
                <div class="nextsafar-update-summary-icon nextsafar-update-icon-themes">
                    🎨
                </div>
                <div class="nextsafar-update-summary-info">
                    <span>Themes</span>
                    <strong><?php echo number_format_i18n( $data['theme_count'] ); ?> available</strong>
                    <small>
                        <?php echo count( wp_get_themes() ); ?> total installed
                    </small>
                </div>
            </div>

            <div class="nextsafar-update-summary-card">
                <div class="nextsafar-update-summary-icon nextsafar-update-icon-last">
                    🕐
                </div>
                <div class="nextsafar-update-summary-info">
                    <span>Last Checked</span>
                    <strong id="nextsafar-last-checked">
                        <?php echo esc_html( $data['last_checked'] ); ?>
                    </strong>
                    <small>Updates check</small>
                </div>
            </div>

        </div>

        <!-- WordPress Core Update -->
        <div class="nextsafar-panel nextsafar-update-section">
            
            <div class="nextsafar-panel-header">
                <div>
                    <h2>WordPress Core</h2>
                    <p>Current version: <?php echo esc_html( $data['core']['current_version'] ); ?></p>
                </div>
                <?php if ( $data['core']['available'] ) : ?>
                    <span class="nextsafar-update-badge nextsafar-update-badge-warning">
                        Update Available
                    </span>
                <?php else : ?>
                    <span class="nextsafar-update-badge nextsafar-update-badge-success">
                        Up to date
                    </span>
                <?php endif; ?>
            </div>

            <div class="nextsafar-panel-body">
                <?php if ( $data['core']['available'] ) : ?>
                    <div class="nextsafar-core-update-info">
                        <div class="nextsafar-core-update-versions">
                            <div class="nextsafar-version-box">
                                <span>Current</span>
                                <strong><?php echo esc_html( $data['core']['current_version'] ); ?></strong>
                            </div>
                            <span class="nextsafar-version-arrow">→</span>
                            <div class="nextsafar-version-box nextsafar-version-new">
                                <span>New</span>
                                <strong><?php echo esc_html( $data['core']['new_version'] ); ?></strong>
                            </div>
                        </div>
                        <div class="nextsafar-core-update-actions">
                            <button 
                                type="button" 
                                class="nextsafar-primary-button nextsafar-update-core-btn"
                                data-version="<?php echo esc_attr( $data['core']['new_version'] ); ?>"
                            >
                                Update to WordPress <?php echo esc_html( $data['core']['new_version'] ); ?>
                            </button>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="nextsafar-update-empty">
                        <div class="nextsafar-update-empty-icon">✓</div>
                        <h3>Your WordPress is up to date</h3>
                        <p>You are running the latest version of WordPress.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Plugins Update -->
        <div class="nextsafar-panel nextsafar-update-section">
            
            <div class="nextsafar-panel-header">
                <div>
                    <h2>Plugins</h2>
                    <p>Manage your plugin updates.</p>
                </div>
                <?php if ( $data['plugin_count'] > 0 ) : ?>
                    <span class="nextsafar-update-badge nextsafar-update-badge-warning">
                        <?php echo $data['plugin_count']; ?> updates
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( $data['plugin_count'] > 0 ) : ?>
                <div class="nextsafar-update-toolbar">
                    <label class="nextsafar-update-select-all">
                        <input type="checkbox" id="nextsafar-select-all-plugins">
                        <span>Select all plugins</span>
                    </label>
                    <button type="button" class="nextsafar-primary-button" id="nextsafar-update-selected-plugins" disabled>
                        Update Selected Plugins
                    </button>
                </div>

                <div class="nextsafar-updates-list" id="nextsafar-plugins-list">
                    <?php foreach ( $data['plugins'] as $plugin ) : ?>
                        <div class="nextsafar-update-item nextsafar-plugin-update" data-plugin-file="<?php echo esc_attr( $plugin['file'] ); ?>">
                            
                            <div class="nextsafar-update-item-checkbox">
                                <input type="checkbox" class="nextsafar-plugin-checkbox" value="<?php echo esc_attr( $plugin['file'] ); ?>">
                            </div>

                            <div class="nextsafar-update-item-info">
                                <div class="nextsafar-update-item-header">
                                    <strong class="nextsafar-update-item-name">
                                        <?php echo esc_html( $plugin['name'] ); ?>
                                    </strong>
                                    <div class="nextsafar-update-item-versions">
                                        <span class="nextsafar-version-current">
                                            <?php echo esc_html( $plugin['current_version'] ); ?>
                                        </span>
                                        <span class="nextsafar-version-arrow">→</span>
                                        <span class="nextsafar-version-new">
                                            <?php echo esc_html( $plugin['new_version'] ); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="nextsafar-update-item-meta">
                                    <?php if ( ! empty( $plugin['author'] ) ) : ?>
                                        <span class="nextsafar-update-author">
                                            By <?php echo wp_kses_post( $plugin['author'] ); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $plugin['compatibility'] ) ) : ?>
                                        <span class="nextsafar-update-compatibility nextsafar-update-compat-<?php echo esc_attr( $plugin['compatibility']['status'] ); ?>">
                                            <?php echo esc_html( $plugin['compatibility']['message'] ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ( ! empty( $plugin['changelog'] ) ) : ?>
                                    <details class="nextsafar-update-changelog">
                                        <summary>View changelog</summary>
                                        <div class="nextsafar-changelog-content">
                                            <?php echo wp_kses_post( $plugin['changelog'] ); ?>
                                        </div>
                                    </details>
                                <?php endif; ?>

                                <div class="nextsafar-update-progress" style="display: none;">
                                    <div class="nextsafar-progress-bar">
                                        <div class="nextsafar-progress-fill" style="width: 0%;"></div>
                                    </div>
                                    <span class="nextsafar-progress-text">Updating...</span>
                                </div>

                                <div class="nextsafar-update-status" style="display: none;"></div>
                            </div>

                            <div class="nextsafar-update-item-actions">
                                <button type="button" class="nextsafar-secondary-button nextsafar-update-single-plugin">
                                    Update
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="nextsafar-update-empty">
                    <div class="nextsafar-update-empty-icon">✓</div>
                    <h3>All plugins are up to date</h3>
                    <p>Your plugins are updated to the latest versions.</p>
                </div>
            <?php endif; ?>

        </div>

        <!-- Themes Update -->
        <div class="nextsafar-panel nextsafar-update-section">
            
            <div class="nextsafar-panel-header">
                <div>
                    <h2>Themes</h2>
                    <p>Manage your theme updates.</p>
                </div>
                <?php if ( $data['theme_count'] > 0 ) : ?>
                    <span class="nextsafar-update-badge nextsafar-update-badge-warning">
                        <?php echo $data['theme_count']; ?> updates
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( $data['theme_count'] > 0 ) : ?>
                <div class="nextsafar-update-toolbar">
                    <label class="nextsafar-update-select-all">
                        <input type="checkbox" id="nextsafar-select-all-themes">
                        <span>Select all themes</span>
                    </label>
                    <button type="button" class="nextsafar-primary-button" id="nextsafar-update-selected-themes" disabled>
                        Update Selected Themes
                    </button>
                </div>

                <div class="nextsafar-updates-list" id="nextsafar-themes-list">
                    <?php foreach ( $data['themes'] as $theme ) : ?>
                        <div class="nextsafar-update-item nextsafar-theme-update" data-theme-slug="<?php echo esc_attr( $theme['slug'] ); ?>">
                            
                            <div class="nextsafar-update-item-checkbox">
                                <input type="checkbox" class="nextsafar-theme-checkbox" value="<?php echo esc_attr( $theme['slug'] ); ?>">
                            </div>

                            <?php if ( ! empty( $theme['screenshot'] ) ) : ?>
                                <div class="nextsafar-update-item-screenshot">
                                    <img src="<?php echo esc_url( $theme['screenshot'] ); ?>" alt="<?php echo esc_attr( $theme['name'] ); ?>">
                                </div>
                            <?php endif; ?>

                            <div class="nextsafar-update-item-info">
                                <div class="nextsafar-update-item-header">
                                    <strong class="nextsafar-update-item-name">
                                        <?php echo esc_html( $theme['name'] ); ?>
                                    </strong>
                                    <div class="nextsafar-update-item-versions">
                                        <span class="nextsafar-version-current">
                                            <?php echo esc_html( $theme['current_version'] ); ?>
                                        </span>
                                        <span class="nextsafar-version-arrow">→</span>
                                        <span class="nextsafar-version-new">
                                            <?php echo esc_html( $theme['new_version'] ); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="nextsafar-update-item-meta">
                                    <?php if ( ! empty( $theme['author'] ) ) : ?>
                                        <span class="nextsafar-update-author">
                                            By <?php echo wp_kses_post( $theme['author'] ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="nextsafar-update-progress" style="display: none;">
                                    <div class="nextsafar-progress-bar">
                                        <div class="nextsafar-progress-fill" style="width: 0%;"></div>
                                    </div>
                                    <span class="nextsafar-progress-text">Updating...</span>
                                </div>

                                <div class="nextsafar-update-status" style="display: none;"></div>
                            </div>

                            <div class="nextsafar-update-item-actions">
                                <button type="button" class="nextsafar-secondary-button nextsafar-update-single-theme">
                                    Update
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="nextsafar-update-empty">
                    <div class="nextsafar-update-empty-icon">✓</div>
                    <h3>All themes are up to date</h3>
                    <p>Your themes are updated to the latest versions.</p>
                </div>
            <?php endif; ?>

        </div>

    </main>

</div>

<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';
?>