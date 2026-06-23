<?php
if(!defined('ABSPATH')) exit;

add_action('admin_post_fd_delete_item','fd_delete_item');
function fd_delete_item(){
    $item_id = intval($_GET['item'] ?? 0);
    $nonce   = $_GET['_wpnonce'] ?? '';

    if(!$item_id || !wp_verify_nonce($nonce,'fd_delete_item_'.$item_id)){
        wp_die('Security check failed');
    }

    wp_trash_post($item_id);

    wp_redirect(admin_url('admin.php?page=awesome_food_delivery&tab=items&sub=all'));
    exit;
}
