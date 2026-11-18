<?php
/**
 * 📄 Partial: صفحه‌بندی عمومی لیست‌ها
 * ورودی‌ها: $posts_query, $paged, $search, $status, $page_slug
 * اختیاری: $post_type (برای CPT), $term (برای فیلتر تاکسونومی CPT)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( $posts_query->max_num_pages > 1 ) {
    $is_cpt    = ( 'nextsafar-cpt' === $page_slug );
    $base_args = array( 'paged' => '%#%', 'page' => $page_slug );
    $add_args  = array( 's' => $search, 'post_status' => $status );

    if ( $is_cpt && ! empty( $post_type ) ) {
        $base_args['post_type'] = $post_type;
        $add_args['post_type']  = $post_type;
        if ( ! empty( $term ) ) {
            $add_args['term'] = $term;
        }
    }

    echo paginate_links(
        array(
            'base'      => add_query_arg( $base_args, admin_url( 'admin.php' ) ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $posts_query->max_num_pages,
            'prev_text' => '←',
            'next_text' => '→',
            'add_args'  => array_filter( $add_args ),
        )
    );
}