<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ✅ بررسی دسترسی
if ( ! current_user_can( 'manage_categories' ) ) {
    wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.', 'خطای دسترسی', array( 'response' => 403 ) );
}

$page_title = 'Categories';
$edit_category = false;

if ( isset( $_GET['edit'] ) ) {

    $edit_id = absint( $_GET['edit'] );

    if ( $edit_id ) {

        $edit_category = get_category( $edit_id );
    }
}

require NEXTSAFAR_PATH . 'inc/admin/layout/sidebar.php';

?>

<div class="nextsafar-main">

    <?php require NEXTSAFAR_PATH . 'inc/admin/layout/header.php'; ?>
    <?php if ( class_exists( 'NextSafar_Categories' ) ) : ?>
    <?php NextSafar_Categories::display_notices(); ?>
    <?php endif; ?>

    <main class="nextsafar-content">

        <div class="nextsafar-page-header">
            <div class="nextsafar-page-actions"></div>
        </div>

        <div class="nextsafar-categories-grid">

            <div class="nextsafar-panel">

                <div class="nextsafar-panel-header">

                    <div>
                        <?php if ( $edit_category ) : ?>

                            <h2>Edit Category</h2>
                            <p>Update your category information.</p>

                        <?php else : ?>

                            <h2>Add New Category</h2>
                            <p>Create a new category for your posts.</p>

                        <?php endif; ?>
                    </div>

                </div>

                <form method="post">

                    <?php
                    if ( $edit_category ) {
                        wp_nonce_field( 'nextsafar_update_category' );
                    } else {
                        wp_nonce_field( 'nextsafar_add_category' );
                    }
                    ?>

                    <input
                        type="hidden"
                        name="nextsafar_action"
                        value="<?php echo $edit_category ? 'update_category' : 'add_category'; ?>"
                    >

                    <?php if ( $edit_category ) : ?>

                        <input
                            type="hidden"
                            name="category_id"
                            value="<?php echo esc_attr( $edit_category->term_id ); ?>"
                        >

                    <?php endif; ?>


                    <div class="nextsafar-form-group">

                        <label for="nextsafar-category-name">
                            Name
                        </label>

                        <input
                            type="text"
                            id="nextsafar-category-name"
                            name="category_name"
                            class="nextsafar-form-input"
                            value="<?php echo $edit_category ? esc_attr( $edit_category->name ) : ''; ?>"
                            required
                        >

                    </div>


                    <div class="nextsafar-form-group">

                        <label for="nextsafar-category-slug">
                            Slug
                        </label>

                        <input
                            type="text"
                            id="nextsafar-category-slug"
                            name="category_slug"
                            class="nextsafar-form-input"
                            value="<?php echo $edit_category ? esc_attr( $edit_category->slug ) : ''; ?>"
                        >

                        <small>
                            Leave empty to generate automatically.
                        </small>

                    </div>


                    <div class="nextsafar-form-group">

                        <label for="nextsafar-category-description">
                            Description
                        </label>

                        <textarea
                            id="nextsafar-category-description"
                            name="category_description"
                            class="nextsafar-form-textarea"
                            rows="4"
                        ><?php echo $edit_category ? esc_textarea( $edit_category->description ) : ''; ?></textarea>

                    </div>


                    <div style="display:flex; gap:10px; align-items:center;">

                        <button
                            type="submit"
                            class="nextsafar-primary-button"
                        >
                            <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                        </button>


                        <?php if ( $edit_category ) : ?>

                            <a
                                href="<?php echo esc_url( admin_url( 'admin.php?page=nextsafar-categories' ) ); ?>"
                                class="nextsafar-secondary-button"
                            >
                                Cancel
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </div>


            <div class="nextsafar-panel">

                <div class="nextsafar-panel-header">

                    <div>
                        <h2>Categories</h2>
                        <p>Your website categories.</p>
                    </div>

                </div>

                <div class="nextsafar-table-wrapper">

                    <table class="nextsafar-table">

                        <thead>

                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Posts</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php

                            $categories = get_categories(
                                array(
                                    'hide_empty' => false,
                                    'orderby'    => 'name',
                                    'order'      => 'ASC',
                                )
                            );

                            ?>

                            <?php if ( ! empty( $categories ) ) : ?>

                                <?php foreach ( $categories as $category ) : ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?php echo esc_html( $category->name ); ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <span class="nextsafar-muted">
                                                <?php echo esc_html( $category->slug ); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php echo esc_html( $category->count ); ?>
                                        </td>

                                        <td>

                                            <div class="nextsafar-actions">

                                                <a
                                                    href="<?php echo esc_url(
                                                        admin_url(
                                                            'admin.php?page=nextsafar-categories&edit=' . $category->term_id
                                                        )
                                                    ); ?>"
                                                    title="Edit"
                                                >
                                                    ✎
                                                </a>

                                                <a
                                                    href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>"
                                                    target="_blank"
                                                    title="View"
                                                >
                                                    <?php NextSafar_Icons::display( 'eye', 20 ); ?>
                                                </a>

                                                <form
                                                    method="post"
                                                    style="display:inline;"
                                                    onsubmit="return confirm('Delete this category?');"
                                                >

                                                    <?php wp_nonce_field( 'nextsafar_delete_category' ); ?>

                                                    <input
                                                        type="hidden"
                                                        name="nextsafar_action"
                                                        value="delete_category"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="category_id"
                                                        value="<?php echo esc_attr( $category->term_id ); ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        title="Delete"
                                                        class="nextsafar-delete-button"
                                                    >
                                                        🗑
                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else : ?>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="nextsafar-table-empty"
                                    >
                                        No categories found.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

<?php
require NEXTSAFAR_PATH . 'inc/admin/layout/footer.php';
?>