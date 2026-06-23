<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// FIXED: Mismatched function callback name in the action hook hook string aligned
add_action( 'admin_post_fd_delete_item', 'fdaa_delete_item' );

function fdaa_delete_item() {
    $item_id = isset( $_GET['item'] ) ? intval( $_GET['item'] ) : 0;
    $nonce   = $_GET['_wpnonce'] ?? '';

    if ( ! $item_id || ! wp_verify_nonce( $nonce, 'fd_delete_item_' . $item_id ) ) {
        wp_die( 'Security check failed' );
    }

    // Safely move the custom item post to trash
    wp_trash_post( $item_id );

    // Redirect cleanly back to the main items catalog board listing matrix
    wp_safe_redirect( admin_url( 'admin.php?page=awesome_food_delivery&tab=items&sub=all' ) );
    exit;
}