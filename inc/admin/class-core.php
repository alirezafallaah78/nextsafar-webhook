<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * NextSafar Core Container - نسخه نهایی و پایدار
 * مدیریت یکپارچه همه Instance ها
 */
class NextSafar_Core {
    private static $instance = null;
    
    public $admin;
    public $assets;
    public $content_manager;
    public $categories;
    public $media_folders;
    public $media_modal;
    public $updates;
    public $wp_wrapper;
    public $external_menus;
    public $dashboard;
    public $widgets;
    
    private function __construct() {
        $this->init_components();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_components() {
        // Instantiate all components with class_exists check
        if (class_exists('NextSafar_Admin')) {
            $this->admin = new NextSafar_Admin();
        }
        
        if (class_exists('NextSafar_Assets')) {
            $this->assets = new NextSafar_Assets();
        }
        
        if (class_exists('NextSafar_Content_Manager')) {
            $this->content_manager = new NextSafar_Content_Manager();
        }
        
        if (class_exists('NextSafar_Categories')) {
            $this->categories = new NextSafar_Categories();
        }
        
        if (class_exists('NextSafar_Media_Folders')) {
            $this->media_folders = new NextSafar_Media_Folders();
        }
        
        if (class_exists('NextSafar_Media_Modal_Enhancer')) {
            $this->media_modal = new NextSafar_Media_Modal_Enhancer();
        }
        
        if (class_exists('NextSafar_Updates')) {
            $this->updates = new NextSafar_Updates();
        }
        
        if (class_exists('NextSafar_WP_Pages_Wrapper')) {
            $this->wp_wrapper = new NextSafar_WP_Pages_Wrapper();
        }
        
        if (class_exists('NextSafar_External_Menus')) {
            $this->external_menus = new NextSafar_External_Menus();
        }
        
        if (class_exists('NextSafar_Dashboard')) {
            $this->dashboard = new NextSafar_Dashboard();
        }
        
        if (class_exists('NextSafar_Widgets')) {
            $this->widgets = new NextSafar_Widgets();
        }
        
        // ❌ NextSafar_Role_Access کاملاً حذف شد
        
        // Initialize all components that have an init() method
        foreach (get_object_vars($this) as $component) {
            if (is_object($component) && method_exists($component, 'init')) {
                $component->init();
            }
        }
        
        // Widget cache hooks
        if (class_exists('NextSafar_Widget_Cache')) {
            NextSafar_Widget_Cache::init_hooks();
        }
        
        add_action('save_post', function() {
            delete_transient('nextsafar_widget_recent_content');
            delete_transient('nextsafar_widget_stats_data');
        }, 20);
        
        add_action('wp_insert_comment', function() {
            delete_transient('nextsafar_pending_comments_count');
        });
        
        add_action('transition_comment_status', function() {
            delete_transient('nextsafar_pending_comments_count');
        });
        
        add_action('upgrader_process_complete', function() {
            delete_transient('nextsafar_pending_updates_count');
        }, 10, 0);
    }
}