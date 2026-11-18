<?php
/**
 * 📄 Partial: صفحه‌بندی نوشته‌ها
 * ورودی‌های مورد انتظار: $posts_query, $paged, $search, $status, $category
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( $posts_query->max_num_pages > 1 ) {
    echo paginate_links(
        array(
            'base'      => add_query_arg(
                array( 'paged' => '%#%', 'page' => 'nextsafar-posts' ),
                admin_url( 'admin.php' )
            ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $posts_query->max_num_pages,
            'prev_text' => '←',
            'next_text' => '→',
            'add_args'  => array_filter(
                array(
                    's'           => $search,
                    'post_status' => $status,
                    'category'    => $category,
                )
            ),
        )
    );
}