<?php
/**
 * NextSafar Assets Manager
 * مدیریت متمرکز enqueue کردن CSS و JavaScript ها
 * 
 * @package NextSafar\Admin
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NextSafar_Assets {
    
    /**
     * Initialize the assets system
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue all admin assets
     */
    public static function enqueue_admin_assets($hook) {
        // فقط برای صفحات NextSafar
        if (strpos($hook, 'nextsafar') === false) {
            return;
        }
        
        // 🎨 1. Design System CSS
        self::enqueue_design_system_css();
        
        // ⚡ 2. Core JavaScript
        self::enqueue_core_js();
        
        // 🧩 3. Module JavaScript
        self::enqueue_module_js();
        
        // 🚀 4. Admin Bootstrap JS
        self::enqueue_admin_bootstrap_js();
        
        // 📄 5. Page-Specific Assets
        self::enqueue_page_specific_assets($hook);
    }
    
    /**
     * Enqueue Design System CSS
     */
    private static function enqueue_design_system_css() {
        // 1. Fonts
        wp_enqueue_style(
            'nextsafar-fonts',
            NEXTSAFAR_URL . 'assets/admin/css/core/fonts.css',
            array(),
            NEXTSAFAR_VERSION
        );
        
        // 2. Variables
        wp_enqueue_style(
            'nextsafar-variables',
            NEXTSAFAR_URL . 'assets/admin/css/core/variables.css',
            array('nextsafar-fonts'),
            NEXTSAFAR_VERSION
        );
        
        // 3. Base
        wp_enqueue_style(
            'nextsafar-base',
            NEXTSAFAR_URL . 'assets/admin/css/core/base.css',
            array('nextsafar-variables'),
            NEXTSAFAR_VERSION
        );
        
        // 4. Layout
        wp_enqueue_style(
            'nextsafar-layout',
            NEXTSAFAR_URL . 'assets/admin/css/core/layout.css',
            array('nextsafar-base'),
            NEXTSAFAR_VERSION
        );
        
        // 5. Components
        wp_enqueue_style(
            'nextsafar-components',
            NEXTSAFAR_URL . 'assets/admin/css/core/components.css',
            array('nextsafar-layout'),
            NEXTSAFAR_VERSION
        );
    }
    
    /**
     * Enqueue Core JavaScript
     */
    private static function enqueue_core_js() {
        wp_enqueue_script(
            'nextsafar-utils',
            NEXTSAFAR_URL . 'assets/admin/js/core/utils.js',
            array(),
            NEXTSAFAR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nextsafar-ajax',
            NEXTSAFAR_URL . 'assets/admin/js/core/ajax-manager.js',
            array('nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nextsafar-state',
            NEXTSAFAR_URL . 'assets/admin/js/core/state-manager.js',
            array('nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
    }
    
    /**
     * Enqueue Module JavaScript
     */
    private static function enqueue_module_js() {
        wp_enqueue_script(
            'nextsafar-search',
            NEXTSAFAR_URL . 'assets/admin/js/modules/search.js',
            array('nextsafar-utils', 'nextsafar-ajax'),
            NEXTSAFAR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nextsafar-notifications',
            NEXTSAFAR_URL . 'assets/admin/js/modules/notifications.js',
            array('nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nextsafar-topbar',
            NEXTSAFAR_URL . 'assets/admin/js/modules/topbar.js',
            array('nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
        
        wp_enqueue_script(
            'nextsafar-sidebar',
            NEXTSAFAR_URL . 'assets/admin/js/modules/sidebar.js',
            array('nextsafar-utils'),
            NEXTSAFAR_VERSION,
            true
        );
    }
    
    /**
     * Enqueue Admin Bootstrap JavaScript
     */
    private static function enqueue_admin_bootstrap_js() {
        wp_enqueue_script(
            'nextsafar-admin',
            NEXTSAFAR_URL . 'assets/admin/js/admin.js',
            array(
                'jquery',
                'nextsafar-utils',
                'nextsafar-ajax',
                'nextsafar-state',
                'nextsafar-search',
                'nextsafar-notifications',
                'nextsafar-topbar',
                'nextsafar-sidebar'
            ),
            NEXTSAFAR_VERSION,
            true
        );
        
        wp_localize_script('nextsafar-admin', 'NextSafarAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('nextsafar_admin'),
            'homeUrl' => home_url(),
            'adminUrl' => admin_url(),
        ));
    }
    
    /**
     * Enqueue page-specific assets
     */
    private static function enqueue_page_specific_assets($hook) {
        // Dashboard Page
        if ($hook === 'toplevel_page_nextsafar-admin') {
            wp_enqueue_style(
                'nextsafar-dashboard',
                NEXTSAFAR_URL . 'assets/admin/css/pages/dashboard.css',
                array('nextsafar-components'),
                NEXTSAFAR_VERSION
            );
            
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.0', true);
        }
    }
}