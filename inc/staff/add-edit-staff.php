<?php

if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();

global $wpdb;
$table_name = $wpdb->prefix . 'arms_staff'; // 
$message = '';
$error   = '';

$staff_tab_url = add_query_arg( array( 'tab' => 'all_staff' ), $list_url );

if ( isset( $_POST['arms_save_staff'] ) ) {
    
    // Nonce Security Validation
    if ( ! isset( $_POST['arms_staff_nonce'] ) || ! wp_verify_nonce( $_POST['arms_staff_nonce'], 'arms_staff_nonce_action' ) ) {
        wp_die( 'Security validation failed.' );
    }

    // Sanitize and secure incoming string payloads
    $first_name     = sanitize_text_field( $_POST['first_name'] );
    $last_name      = sanitize_text_field( $_POST['last_name'] );
    $role_category  = sanitize_text_field( $_POST['role_category'] );
    $email          = sanitize_email( $_POST['email'] );
    $phone          = sanitize_text_field( $_POST['phone'] );
    $license_number = sanitize_text_field( $_POST['license_number'] );
    $joining_date   = sanitize_text_field( $_POST['joining_date'] );
    $salary         = ! empty( $_POST['salary'] ) ? floatval( $_POST['salary'] ) : 0.00;
    $status         = sanitize_text_field( $_POST['status'] );
    $password       = $_POST['password']; // Intentional: Raw pass string to preserve special characters

    // Handle Profile Image Upload
    $profile_image_url = isset( $_POST['existing_profile_image'] ) ? esc_url_raw( $_POST['existing_profile_image'] ) : '';
    if ( ! empty( $_FILES['profile_image']['name'] ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $uploaded_file = wp_handle_upload( $_FILES['profile_image'], array( 'test_form' => false ) );
        if ( isset( $uploaded_file['url'] ) ) {
            $profile_image_url = $uploaded_file['url'];
        }
    }

    if ( ! $row_data ) {
        // --- ACTION: CREATE NEW USER & WP ACCOUNT ---
        
        if ( email_exists( $email ) ) {
            $error = "Registration failed. This email is already registered in WordPress.";
        } elseif ( username_exists( $email ) ) {
            $error = "Registration failed. A user with this username/email already exists.";
        } else {
            // Step A: Register the user into WordPress Core so they can login via wp-admin
            $wp_user_id = wp_insert_user( array(
                'user_login' => $email, // Mapping Email as Username
                'user_email' => $email,
                'user_pass'  => $password,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => 'subscriber' // Adjust user capabilities/role hierarchy as required
            ) );

            if ( is_wp_error( $wp_user_id ) ) {
                $error = "WordPress Account Creation Error: " . $wp_user_id->get_error_message();
            } else {
                // Step B: Inject full data profile payload into your custom data matrix table
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'wp_user_id'     => $wp_user_id, // Maps custom entry to WordPress standard profiles
                        'first_name'     => $first_name,
                        'last_name'      => $last_name,
                        'role_category'  => $role_category,
                        'email'          => $email,
                        'phone'          => $phone,
                        'license_number' => $license_number,
                        'joining_date'   => $joining_date,
                        'salary'         => $salary,
                        'status'         => $status,
                        'profile_image'  => $profile_image_url,
                    )
                );

                if ( $inserted ) {
                    // Safe redirection targeting the specified tab layout frame
                    $redirect_target = add_query_arg( 'message', 'created', $staff_tab_url );
                    if ( ! headers_sent() ) {
                        wp_redirect( $redirect_target );
                        exit;
                    } else {
                        echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_target ) . '";</script>';
                        echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_target ) . '">';
                        exit;
                    }
                } else {
                    $error = "Custom Registry Record generation failed.";
                }
            }
        }
    } else {
        // --- ACTION: UPDATE EXISTING REGISTRY PROFILE ---
        // Safety Fix: Guard against legacy database rows that don't have wp_user_id properties yet
        $wp_user_id = isset( $row_data->wp_user_id ) ? intval( $row_data->wp_user_id ) : 0;

        // If the property wasn't linked yet, see if a user account exists under this email address
        if ( ! $wp_user_id && ! empty( $email ) ) {
            $existing_wp_user = get_user_by( 'email', $email );
            if ( $existing_wp_user ) {
                $wp_user_id = $existing_wp_user->ID;
            }
        }

        // Configuration mapping arrays
        $user_data = array(
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        );

        if ( $wp_user_id > 0 ) {
            $user_data['ID'] = $wp_user_id;
        } else {
            // If completely missing, generate an entry on the fly to prevent downstream failure drops
            $user_data['user_login'] = $email;
            $user_data['role']       = 'subscriber';
        }

        // Conditional password overwrite checks
        if ( ! empty( $password ) ) {
            $user_data['user_pass'] = $password;
        }

        // Direct user insertion/modification wrapper operations
        if ( isset( $user_data['ID'] ) ) {
            $user_update_status = wp_update_user( $user_data );
        } else {
            $user_update_status = wp_insert_user( $user_data );
            if ( ! is_wp_error( $user_update_status ) ) {
                $wp_user_id = $user_update_status;
            }
        }

        if ( is_wp_error( $user_update_status ) ) {
            $error = "Failed updating core login details: " . $user_update_status->get_error_message();
        } else {
            // Synchronize operational metrics changes on custom schema table
            $updated = $wpdb->update(
                $table_name,
                array(
                    'wp_user_id'     => $wp_user_id, // Ensure table remains paired up
                    'first_name'     => $first_name,
                    'last_name'      => $last_name,
                    'role_category'  => $role_category,
                    'email'          => $email,
                    'phone'          => $phone,
                    'license_number' => $license_number,
                    'joining_date'   => $joining_date,
                    'salary'         => $salary,
                    'status'         => $status,
                    'profile_image'  => $profile_image_url,
                ),
                array( 'id' => $row_data->id )
            );

            // Safe redirection targeting the specified tab layout frame
            $redirect_target = add_query_arg( 'message', 'updated', $staff_tab_url );
            if ( ! headers_sent() ) {
                wp_redirect( $redirect_target );
                exit;
            } else {
                echo '<script type="text/javascript">window.location.href="' . esc_url( $redirect_target ) . '";</script>';
                echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_target ) . '">';
                exit;
            }
        }
    }
}

// ==========================================
// 2. FRONTEND FORM VIEW RENDERER
// ==========================================
$btn_text   = "Commit Profile Entry";

if ( $row_data ) {
    $form_title = "Modify Existing Profile Data Matrix: " . esc_html($row_data->first_name . ' ' . $row_data->last_name);
    $btn_text   = "Save Updated Profile Changes";
}
?>
<div class="arms-card-box">

    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error" style="padding: 10px; background: #fff2f2; border-left: 4px solid #dc3232; margin-bottom: 20px;">
            <p style="margin:0; color: #dc3232; font-weight: 600;"><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field( 'arms_staff_nonce_action', 'arms_staff_nonce' ); ?>
        <?php if ( $row_data && ! empty( $row_data->profile_image ) ) : ?>
            <input type="hidden" name="existing_profile_image" value="<?php echo esc_url($row_data->profile_image); ?>">
        <?php endif; ?>
        
        <div class="arms-form-grid">
            <div class="arms-form-group">
                <label for="first_name">First Name *</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo $row_data ? esc_attr($row_data->first_name) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="last_name">Last Name *</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo $row_data ? esc_attr($row_data->last_name) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="role_category">Role Assignment *</label>
                <select id="role_category" name="role_category" required>
                    <?php 
                    $roles = array('doctor'=>'Doctor', 'physiotherapist'=>'Physiotherapist', 'nurse'=>'Nurse', 'accountant'=>'Accountant', 'support_staff'=>'Support Staff');
                    foreach($roles as $key => $label) {
                        $sel = ($row_data && $row_data->role_category === $key) ? 'selected' : '';
                        echo '<option value="'.esc_attr($key).'" '.$sel.'>'.esc_html($label).'</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="arms-form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo $row_data ? esc_attr($row_data->email) : ''; ?>">
            </div>

            <div class="arms-form-group">
                <label for="password">Password Login<?php echo $row_data ? '(Leave completely blank to retain existing)' : '*'; ?></label>
                <input type="password" id="password" name="password" <?php echo $row_data ? '' : 'required'; ?> placeholder="<?php echo $row_data ? '••••••••' : 'Assign access credentials'; ?>" autocomplete="new-password">
            </div>

            <div class="arms-form-group">
                <label for="phone">Phone *</label>
                <input type="text" id="phone" name="phone" required value="<?php echo $row_data ? esc_attr($row_data->phone) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="license_number">BMDC / Professional License Code</label>
                <input type="text" id="license_number" name="license_number" value="<?php echo $row_data ? esc_attr($row_data->license_number) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="joining_date">Joining Date</label>
                <input type="date" id="joining_date" name="joining_date" value="<?php echo $row_data ? esc_attr($row_data->joining_date) : date('Y-m-d'); ?>">
            </div>
            <div class="arms-form-group">
                <label for="salary">Monthly Salary</label>
                <input type="number" step="0.01" id="salary" name="salary" value="<?php echo $row_data ? esc_attr($row_data->salary) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active" <?php echo ($row_data && $row_data->status === 'active') ? 'selected' : ''; ?>>Active Duty</option>
                    <option value="inactive" <?php echo ($row_data && $row_data->status === 'inactive') ? 'selected' : ''; ?>>On Leave / Suspended</option>
                </select>
            </div>
            <div class="arms-form-group">
                <label for="profile_image">Profile Picture (JPG/PNG)</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*">
            </div>
        </div>

        <button type="submit" name="arms_save_staff" class="arms-submit-btn">
            <span class="dashicons dashicons-id"></span> <?php echo esc_html($btn_text); ?>
        </button>
        <a href="<?php echo esc_url($staff_tab_url); ?>" class="arms-action-btn btn-view">Cancel</a>
    </form>
</div>
<?php
// Flush output buffer out cleanly at end of script execution scope 
ob_end_flush();
?>