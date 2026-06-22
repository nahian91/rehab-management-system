<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Staff Tab - Main Controller & Processing Engine
 */
function arms_staff_tab() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'arms_staff';

    // Parse parameters
    $current_sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';
    $staff_id    = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    // Core Link Constants
    $list_url = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=list' );
    $add_url  = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=add' );

    /* =========================================================================
       1. DATABASE ACTION: DELETE OPERATIONS
       ========================================================================= */
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $staff_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'arms_delete_staff_' . $staff_id ) ) {
            $deleted = $wpdb->delete( $table_staff, array( 'id' => $staff_id ), array( '%d' ) );
            if ( $deleted ) {
                echo '<div class="notice notice-success is-dismissible"><p>Staff record deleted successfully from the registry.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Failed to execute database deletion query.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Security token challenge failed. Aborted.</p></div>';
        }
    }

    /* =========================================================================
       2. DATABASE ACTION: SAVE OPERATIONS (INSERT & UPDATE)
       ========================================================================= */
    if ( isset( $_POST['arms_save_staff'] ) && check_admin_referer( 'arms_staff_nonce_action', 'arms_staff_nonce' ) ) {
        
        $first_name     = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $last_name      = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
        $email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $role_category  = isset( $_POST['role_category'] ) ? sanitize_text_field( wp_unslash( $_POST['role_category'] ) ) : '';
        $license_number = ! empty( $_POST['license_number'] ) ? sanitize_text_field( wp_unslash( $_POST['license_number'] ) ) : '';
        $status         = ! empty( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
        
        $salary       = ! empty( $_POST['salary'] ) ? floatval( wp_unslash( $_POST['salary'] ) ) : 0.00;
        $joining_date = ! empty( $_POST['joining_date'] ) ? sanitize_text_field( wp_unslash( $_POST['joining_date'] ) ) : '1970-01-01';
        $profile_image = isset( $_POST['existing_profile_image'] ) ? esc_url_raw( wp_unslash( $_POST['existing_profile_image'] ) ) : '';

        // Media Library File Upload Management Tracking
        if ( ! empty( $_FILES['profile_image']['name'] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );

            $attachment_id = media_handle_upload( 'profile_image', 0 );
            if ( ! is_wp_error( $attachment_id ) ) {
                $profile_image = wp_get_attachment_url( $attachment_id );
            }
        }

        if ( ! empty( $first_name ) && ! empty( $last_name ) && ! empty( $email ) ) {
            
            $data_array = array(
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'email'          => $email,
                'phone'          => $phone,
                'role_category'  => $role_category,
                'license_number' => $license_number,
                'joining_date'   => $joining_date,
                'salary'         => $salary,
                'status'         => $status,
                'profile_image'  => $profile_image,
            );
            $format_array = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s' );

            if ( $current_sub === 'edit' && $staff_id > 0 ) {
                // UPDATE ENGINE ACTION
                $updated = $wpdb->update( $table_staff, $data_array, array( 'id' => $staff_id ), $format_array, array( '%d' ) );
                if ( $updated !== false ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Staff profile modifications committed successfully.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error updating: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            } else {
                // INSERT ENGINE ACTION
                $data_array['created_at'] = current_time( 'mysql' );
                $format_array[] = '%s';
                $inserted = $wpdb->insert( $table_staff, $data_array, $format_array );
                if ( $inserted ) {
                    echo '<div class="notice notice-success is-dismissible"><p>New staff profile registered successfully.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Database entry failure: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Required form parameters missing.</p></div>';
        }
    }

    // Load External Stylesheet Component Module
    include plugin_dir_path( __FILE__ ) . 'staff/style-staff.php';
    ?>
    <div class="arms-staff-wrapper">
        <nav class="arms-subnav-bar">
            <a href="<?php echo esc_url( $list_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'list') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-groups" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> All Staff
            </a>
            <a href="<?php echo esc_url( $add_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'add') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-plus" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Add New Staff
            </a>
            <?php if ( $current_sub === 'edit' ) : ?><a class="arms-subnav-link active">Editing Staff Profile</a><?php endif; ?>
            <?php if ( $current_sub === 'view' ) : ?><a class="arms-subnav-link active">Viewing Staff Profile</a><?php endif; ?>
        </nav>

        <?php
        /* =========================================================================
           3. ROUTER MAP LOADERS
           ========================================================================= */
        switch ( $current_sub ) {
            case 'add':
            case 'edit':
                $row_data = ( $current_sub === 'edit' && $staff_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_staff WHERE id = %d", $staff_id ) ) : null;
                include plugin_dir_path( __FILE__ ) . 'staff/add-edit-staff.php';
                break;

            case 'view':
                $staff = ( $staff_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_staff WHERE id = %d", $staff_id ) ) : null;
                if ( $staff ) {
                    include plugin_dir_path( __FILE__ ) . 'staff/view-staff.php';
                } else {
                    echo '<div class="notice notice-error"><p>Profile asset directory parsing tracking mismatch.</p></div>';
                }
                break;

            case 'list':
            default:
                $staff_entries = $wpdb->get_results( "SELECT * FROM $table_staff ORDER BY id DESC" );
                include plugin_dir_path( __FILE__ ) . 'staff/all-staff.php';
                break;
        }
        ?>
    </div>
    <?php
}