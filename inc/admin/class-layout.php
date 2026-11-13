<?php
/**
 * NextSafar Layout Wrapper
 * پیچیدن محتوای صفحات در Layout مدرن
 * 
 * @package NextSafar\Admin
 * @since 2.0.0
 */

namespace NextSafar\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Layout {
    
    /**
     * Wrap page content in the modern layout
     * 
     * @param callable $content_callback Callback that outputs the page content
     * @param string $page_title Title of the current page
     * @return void
     */
    public static function render(callable $content_callback, string $page_title = ''): void {
        // Start output buffering to capture content
        ob_start();
        call_user_func($content_callback);
        $content = ob_get_clean();
        
        // Render layout structure
        self::render_header();
        self::render_sidebar();
        ?>
        
        <div class="nextsafar-main">
            <?php self::render_topbar($page_title); ?>
            
            <main class="nextsafar-content">
                <?php echo $content; ?>
            </main>
        </div>
        
        <?php
        self::render_footer();
    }
    
    /**
     * Render header section
     */
    private static function render_header(): void {
        // Header is rendered by sidebar (it contains the opening HTML tags)
        // We don't render it separately to avoid duplication
    }
    
    /**
     * Render sidebar
     */
    private static function render_sidebar(): void {
        require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';
    }
    
    /**
     * Render topbar
     * 
     * @param string $page_title Current page title
     */
    private static function render_topbar(string $page_title = ''): void {
        // Set page title for topbar
        if (!empty($page_title)) {
            global $nextsafar_current_page_title;
            $nextsafar_current_page_title = $page_title;
        }
        
        require NEXTSAFAR_PATH . 'inc/admin/layout/header.php';
    }
    
    /**
     * Render footer
     */
    private static function render_footer(): void {
        require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';
    }
    
    /**
     * Check if current page should use the new layout
     * 
     * @return bool
     */
    public static function should_use_new_layout(): bool {
        // For now, return true for all NextSafar admin pages
        // Later we can make this more specific
        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        
        $nextsafar_pages = [
            'nextsafar-admin',
            'nextsafar-news',
            'nextsafar-news-manual',
            'nextsafar-news-filter',
            'nextsafar-news-settings',
            'nextsafar-post-generator',
            'nextsafar-pg-settings',
        ];
        
        return in_array($current_page, $nextsafar_pages, true);
    }
}