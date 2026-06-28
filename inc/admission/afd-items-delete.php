<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * =========================================================================
 * INPATIENT ADMISSION RECORD DELETION HANDLER
 * =========================================================================
 * Listens for administrative delete action signals sent via admin-post.php
 */
// 1. Hook for logged-in administrators
add_action( 'admin_post_arms_delete_admission', 'arms_handle_admission_record_deletion' );

function arms_handle_admission_record_deletion() {
    global $wpdb;

    // 2. Extract values safely from the incoming query string parameters
    $admission_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    $nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : '';

    // 3. Match nonce verification tokens against the precise admission record ID row
    if ( ! $admission_id || ! wp_verify_nonce( $nonce, 'arms_delete_admission_' . $admission_id ) ) {
        wp_die( esc_html__( 'Security check failed: Your security token has expired or is invalid.', 'arms' ) );
    }

    // 4. Permission guard check (Only allow users with administrative capabilities to delete logs)
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Access Denied: You do not possess adequate clearance to delete records.', 'arms' ) );
    }

    // 5. Execute deletion directly on the database single-table architecture
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $deleted = $wpdb->delete(
        $table_admissions,
        array( 'id' => $admission_id ),
        array( '%d' )
    );

    // 6. Redirect back to the main management screen with a contextual notification state code
    $redirect_url = admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=all' );

    if ( $deleted !== false ) {
        // Success code flag to trigger an admin notice confirmation message on page load
        $redirect_url = add_query_arg( 'arms_msg', 'deleted_success', $redirect_url );
    } else {
        $redirect_url = add_query_arg( 'arms_msg', 'deleted_failed', $redirect_url );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}