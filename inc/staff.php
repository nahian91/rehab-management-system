<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Staff Tab - Clinical HR Registry & Profile Desk with View, Edit, and Delete Actions
 * Database Mapping: arms_staff
 */
function arms_staff_tab() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'arms_staff';

    // Sub-tab switching architecture logic
    $current_sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';
    $staff_id    = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    // Core Navigation URLs
    $list_url = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=list' );
    $add_url  = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=add' );

    /* =========================================================================
       ACTION ROUTER: SYSTEM DATA DELETION
       ========================================================================= */
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $staff_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'arms_delete_staff_' . $staff_id ) ) {
            $deleted = $wpdb->delete( $table_staff, array( 'id' => $staff_id ), array( '%d' ) );
            if ( $deleted ) {
                echo '<div class="notice notice-success is-dismissible"><p>Staff record deleted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Failed to delete staff record from the registry.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Security check failed. Action aborted.</p></div>';
        }
    }

    /* =========================================================================
       POST ENGINE: HANDLE STAFF REGISTRATION / PROFILES UPDATE
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

        // File upload tracking logic 
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
                // Update Path
                $updated = $wpdb->update( $table_staff, $data_array, array( 'id' => $staff_id ), $format_array, array( '%d' ) );
                if ( $updated !== false ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Staff profile modified successfully.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error updating registry data.' . esc_html($wpdb->last_error) . '</p></div>';
                }
            } else {
                // Insert Path
                $data_array['created_at'] = current_time( 'mysql' );
                $format_array[] = '%s';
                $inserted = $wpdb->insert( $table_staff, $data_array, $format_array );
                if ( $inserted ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Staff member profile registered successfully.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Database insertion failed.' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Required input values missing (First Name, Last Name, Email).</p></div>';
        }
    }
    ?>

    <style>
        .arms-staff-wrapper {
            padding: 24px; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a; max-width: 1300px; margin: 20px auto; box-sizing: border-box;
        }
        .arms-staff-wrapper * { box-sizing: border-box; }
        .arms-subnav-bar { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; margin-bottom: 24px; }
        .arms-subnav-link {
            padding: 10px 20px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px;
            border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease;
        }
        .arms-subnav-link:hover { color: #4f46e5; }
        .arms-subnav-link.active { color: #4f46e5; border-bottom-color: #4f46e5; }
        .arms-card-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .arms-card-header-flex { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
        .arms-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .arms-form-group { display: flex; flex-direction: column; gap: 6px; }
        .arms-form-group label { font-size: 12px; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.02em; }
        .arms-form-group input, .arms-form-group select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background-color: #fff; width: 100%; }
        .arms-form-group input:focus, .arms-form-group select:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1); }
        .arms-search-input-field { max-width: 260px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        .arms-search-input-field:focus { border-color: #4f46e5; outline: none; }
        .arms-submit-btn { background: #4f46e5; color: #fff; border: none; padding: 12px 24px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; text-decoration: none; display: inline-block; }
        .arms-submit-btn:hover { background: #4338ca; }
        .arms-table-container { overflow-x: auto; }
        .arms-data-table { width: 100%; border-collapse: collapse; text-align: left; }
        .arms-data-table th { background: #f8fafc; padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        .arms-data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .arms-data-table tr:hover td { background: #f8fafc; }
        .arms-staff-profile-meta { display: flex; align-items: center; gap: 12px; }
        .arms-staff-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #e2e8f0; border: 1px solid #cbd5e1; }
        .arms-avatar-fallback { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-weight: bold; }
        .arms-role-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .badge-doctor { background: #e0f2fe; color: #0369a1; }
        .badge-physio { background: #fae8ff; color: #a21caf; }
        .badge-nurse { background: #dcfce7; color: #15803d; }
        .badge-accountant { background: #fef9c3; color: #a16207; }
        .badge-support { background: #f1f5f9; color: #475569; }
        .arms-status-dot { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
        .arms-status-dot::before { content: ''; width: 8px; height: 8px; border-radius: 50%; }
        .status-active::before { background: #10b981; }
        .status-inactive::before { background: #ef4444; }
        
        /* Action Utility Layout Overlays */
        .arms-action-btn-group { display: flex; gap: 6px; align-items: center; }
        .arms-action-btn { 
            padding: 5px 10px; font-size: 12px; border-radius: 4px; text-decoration: none; font-weight: 500;
            display: inline-flex; align-items: center; justify-content: center; transition: all 0.1s ease;
        }
        .btn-view { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn-view:hover { background: #e2e8f0; }
        .btn-edit { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-edit:hover { background: #dbeafe; }
        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-delete:hover { background: #fee2e2; }

        /* Profile Layout Sheets for View Mode */
        .profile-view-grid { display: flex; gap: 30px; flex-wrap: wrap; margin-top: 15px; }
        .profile-view-sidebar { flex: 1; min-width: 240px; max-width: 320px; text-align: center; border-right: 1px solid #e2e8f0; padding-right: 30px; }
        .profile-large-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #4f46e5; margin-bottom: 15px; background: #f1f5f9; }
        .profile-large-fallback { width: 150px; height: 150px; border-radius: 50%; background: #4f46e5; color: #fff; font-size: 48px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; font-weight: bold; }
        .profile-view-details { flex: 2; min-width: 300px; }
        .profile-detail-row { display: flex; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; justify-content: space-between; font-size: 14px; }
        .profile-detail-label { font-weight: 600; color: #64748b; }
        .profile-detail-val { color: #0f172a; font-weight: 500; }
    </style>

    <div class="arms-staff-wrapper">
        
        <nav class="arms-subnav-bar">
            <a href="<?php echo esc_url( $list_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'list') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-groups" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> All Staff Directory
            </a>
            <a href="<?php echo esc_url( $add_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'add') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-plus" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Add New Staff Profile
            </a>
            <?php if ( $current_sub === 'edit' ) : ?><a class="arms-subnav-link active">Editing Staff Profile</a><?php endif; ?>
            <?php if ( $current_sub === 'view' ) : ?><a class="arms-subnav-link active">Viewing Staff Profile</a><?php endif; ?>
        </nav>

        <?php 
        /* =========================================================================
           SUB-VIEW: ADD PROFILE OR EDIT PROFILE FORMS
           ========================================================================= */
        if ( $current_sub === 'add' || $current_sub === 'edit' ) : 
            $form_title = "Human Resources Registry Configuration";
            $btn_text = "Commit Profile Entry";
            $row_data = null;

            if ( $current_sub === 'edit' && $staff_id > 0 ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_staff WHERE id = %d", $staff_id ) );
                $form_title = "Modify Existing Profile Data Matrix: " . esc_html($row_data->first_name . ' ' . $row_data->last_name);
                $btn_text = "Save Updated Profile Changes";
            }
            ?>
            <div class="arms-card-box">
                <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 700;"><?php echo esc_html($form_title); ?></h3>
                
                <form method="post" action="" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'arms_staff_nonce_action', 'arms_staff_nonce' ); ?>
                    <?php if ( $row_data && ! empty( $row_data->profile_image ) ) : ?>
                        <input type="hidden" name="existing_profile_image" value="<?php echo esc_url($row_data->profile_image); ?>">
                    <?php endif; ?>
                    
                    <div class="arms-form-grid">
                        <div class="arms-form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required placeholder="e.g. John" value="<?php echo $row_data ? esc_attr($row_data->first_name) : ''; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required placeholder="e.g. Doe" value="<?php echo $row_data ? esc_attr($row_data->last_name) : ''; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="role_category">Role Assignment Track *</label>
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
                            <label for="email">Institutional Email *</label>
                            <input type="email" id="email" name="email" required placeholder="john.doe@clinic.com" value="<?php echo $row_data ? esc_attr($row_data->email) : ''; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="phone">Primary Contact Phone *</label>
                            <input type="text" id="phone" name="phone" required placeholder="+8801..." value="<?php echo $row_data ? esc_attr($row_data->phone) : ''; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="license_number">BMDC / Professional Registry License Code</label>
                            <input type="text" id="license_number" name="license_number" placeholder="Leave blank if Support/Accounting" value="<?php echo $row_data ? esc_attr($row_data->license_number) : ''; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="joining_date">Official Joining Date</label>
                            <input type="date" id="joining_date" name="joining_date" value="<?php echo $row_data ? esc_attr($row_data->joining_date) : date('Y-m-d'); ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="salary">Base Monthly Remuneration (Gross)</label>
                            <input type="number" step="0.01" id="salary" name="salary" placeholder="0.00" value="<?php echo $row_data ? esc_attr($row_data->salary) : ''; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="status">Initial Operations Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo ($row_data && $row_data->status === 'active') ? 'selected' : ''; ?>>Active Duty</option>
                                <option value="inactive" <?php echo ($row_data && $row_data->status === 'inactive') ? 'selected' : ''; ?>>On Leave / Suspended</option>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label for="profile_image">Profile Picture (JPG/PNG)</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" style="padding: 6px;">
                        </div>
                    </div>

                    <button type="submit" name="arms_save_staff" class="arms-submit-btn">
                        <span class="dashicons dashicons-id" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> <?php echo esc_html($btn_text); ?>
                    </button>
                    <a href="<?php echo esc_url($list_url); ?>" class="arms-action-btn btn-view" style="padding: 11px 18px; margin-left: 10px; font-size:13px; font-weight:600; border-radius:6px;">Cancel</a>
                </form>
            </div>

        <?php 
        /* =========================================================================
           SUB-VIEW: PATIENT-STYLE PROFILE SINGLE DETAIL ROW
           ========================================================================= */
        elseif ( $current_sub === 'view' && $staff_id > 0 ) : 
            $staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_staff WHERE id = %d", $staff_id ) );
            if ( ! $staff ) {
                echo '<div class="notice notice-error"><p>Profile entry target error.</p></div>';
                return;
            }
            $initial_char = ! empty( $staff->first_name ) ? strtoupper( substr( $staff->first_name, 0, 1 ) ) : '?';
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3 style="margin:0; font-size:18px; font-weight:700;">Clinical Staff Record Profile Sheet</h3>
                    <a href="<?php echo esc_url($list_url); ?>" class="arms-submit-btn" style="padding: 8px 16px;">
                        <span class="dashicons dashicons-arrow-left-alt" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Back to Roster
                    </a>
                </div>

                <div class="profile-view-grid">
                    <div class="profile-view-sidebar">
                        <?php if ( ! empty( $staff->profile_image ) ) : ?>
                            <img src="<?php echo esc_url( $staff->profile_image ); ?>" class="profile-large-avatar" alt="Profile pic">
                        <?php else : ?>
                            <div class="profile-large-fallback"><?php echo esc_html( $initial_char ); ?></div>
                        <?php endif; ?>
                        <h4 style="margin:5px 0 0 0; font-size:18px;"><?php echo esc_html($staff->first_name . ' ' . $staff->last_name); ?></h4>
                        <p style="margin:4px 0 15px 0; color:#64748b; font-weight:500; text-transform:capitalize;"><?php echo esc_html(str_replace('_', ' ', $staff->role_category)); ?></p>
                        
                        <span class="arms-status-dot <?php echo ($staff->status === 'active') ? 'status-active' : 'status-inactive'; ?>">
                            Status: <?php echo ($staff->status === 'active') ? 'Active Duty' : 'Inactive'; ?>
                        </span>
                    </div>

                    <div class="profile-view-details">
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">System Record Reference ID</span>
                            <span class="profile-detail-val">#<?php echo esc_html($staff->id); ?></span>
                        </div>
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">Institutional Contact Email</span>
                            <span class="profile-detail-val"><?php echo esc_html($staff->email); ?></span>
                        </div>
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">Primary Secure Contact</span>
                            <span class="profile-detail-val"><?php echo esc_html($staff->phone); ?></span>
                        </div>
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">Medical Board Registration / Code</span>
                            <span class="profile-detail-val"><code><?php echo ! empty($staff->license_number) ? esc_html($staff->license_number) : 'Not Applicable'; ?></code></span>
                        </div>
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">Official Registry Joining Date</span>
                            <span class="profile-detail-val"><?php echo esc_html(date('F j, Y', strtotime($staff->joining_date))); ?></span>
                        </div>
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">Base Monthly Salary Baseline</span>
                            <span class="profile-detail-val">৳<?php echo number_format($staff->salary, 2); ?></span>
                        </div>
                        <div class="profile-detail-row">
                            <span class="profile-detail-label">System Profile Creation Timestamp</span>
                            <span class="profile-detail-val"><?php echo esc_html(date('g:ia - M j, Y', strtotime($staff->created_at))); ?></span>
                        </div>
                        
                        <div style="margin-top:25px; display:flex; gap:10px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=rehab_management_system&tab=staff&sub=edit&id='.$staff->id)); ?>" class="arms-action-btn btn-edit" style="padding:10px 20px; font-weight:600; border-radius:6px;">Modify Data Profile</a>
                        </div>
                    </div>
                </div>
            </div>

        <?php 
        /* =========================================================================
           SUB-VIEW: DEFAULT ROSTER DIRECTORY LISTINGS DATA TABLE
           ========================================================================= */
        else : ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Active System Operators Matrix</h3>
                    <input type="text" id="armsStaffTableSearch" class="arms-search-input-field" placeholder="Search staff registry entries...">
                </div>
                
                <div class="arms-table-container">
                    <table class="arms-data-table" id="armsStaffSystemDirectoryTable">
                        <thead>
                            <tr>
                                <th>Staff Reference Name</th>
                                <th>Assigned Track</th>
                                <th>Email Address</th>
                                <th>Primary Contact</th>
                                <th>Registry Code</th>
                                <th>Joining Date</th>
                                <th>Status Matrix</th>
                                <th style="text-align: right; padding-right: 24px;">Controls Panel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $staff_entries = $wpdb->get_results( "SELECT * FROM $table_staff ORDER BY id DESC" );
                            
                            if ( ! empty( $staff_entries ) ) :
                                foreach ( $staff_entries as $staff ) :
                                    $badge_class = 'badge-support';
                                    if ( $staff->role_category === 'doctor' ) $badge_class = 'badge-doctor';
                                    elseif ( $staff->role_category === 'physiotherapist' ) $badge_class = 'badge-physio';
                                    elseif ( $staff->role_category === 'nurse' ) $badge_class = 'badge-nurse';
                                    elseif ( $staff->role_category === 'accountant' ) $badge_class = 'badge-accountant';

                                    $initial_char = ! empty( $staff->first_name ) ? strtoupper( substr( $staff->first_name, 0, 1 ) ) : '?';
                                    
                                    // Security Context URL Parsing Rules
                                    $view_profile_url = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=view&id=' . $staff->id );
                                    $edit_profile_url = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=edit&id=' . $staff->id );
                                    $delete_profile_url = wp_nonce_url( admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=list&action=delete&id=' . $staff->id ), 'arms_delete_staff_' . $staff->id );
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="arms-staff-profile-meta">
                                                <?php if ( ! empty( $staff->profile_image ) ) : ?>
                                                    <img src="<?php echo esc_url( $staff->profile_image ); ?>" class="arms-staff-avatar" alt="Avatar">
                                                <?php else : ?>
                                                    <div class="arms-avatar-fallback"><?php echo esc_html( $initial_char ); ?></div>
                                                <?php endif; ?>
                                                <strong><?php echo esc_html( $staff->first_name . ' ' . $staff->last_name ); ?></strong>
                                            </div>
                                        </td>
                                        <td><span class="arms-role-badge <?php echo $badge_class; ?>"><?php echo esc_html( str_replace('_', ' ', $staff->role_category) ); ?></span></td>
                                        <td><?php echo esc_html( $staff->email ); ?></td>
                                        <td><?php echo esc_html( $staff->phone ); ?></td>
                                        <td><code><?php echo ! empty($staff->license_number) ? esc_html($staff->license_number) : 'N/A'; ?></code></td>
                                        <td><?php echo esc_html( date('M j, Y', strtotime($staff->joining_date)) ); ?></td>
                                        <td>
                                            <span class="arms-status-dot <?php echo ($staff->status === 'active') ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo ($staff->status === 'active') ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="arms-action-btn-group" style="justify-content: flex-end;">
                                                <a href="<?php echo esc_url( $view_profile_url ); ?>" class="arms-action-btn btn-view" title="View Patient Style Card">
                                                    <span class="dashicons dashicons-visibility" style="font-size:14px; margin-right:2px;"></span> View
                                                </a>
                                                <a href="<?php echo esc_url( $edit_profile_url ); ?>" class="arms-action-btn btn-edit" title="Edit Staff Parameter Matrix">
                                                    <span class="dashicons dashicons-edit" style="font-size:14px; margin-right:2px;"></span> Edit
                                                </a>
                                                <a href="<?php echo esc_url( $delete_profile_url ); ?>" class="arms-action-btn btn-delete" title="Purge Record From System" onclick="return confirm('Warning: Are you completely sure you want to delete this staff record entry?');">
                                                    <span class="dashicons dashicons-trash" style="font-size:14px; margin-right:2px;"></span> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr class="no-records-row">
                                        <td colspan="8" style="text-align: center; color: #64748b; padding: 30px;">No registered staff member profiles found inside the active database tracking grid.</td>
                                    </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var searchInput = document.getElementById('armsStaffTableSearch');
                var tableBody = document.querySelector('#armsStaffSystemDirectoryTable tbody');
                
                if (searchInput && tableBody) {
                    searchInput.addEventListener('keyup', function() {
                        var filterValue = searchInput.value.toLowerCase();
                        var rows = tableBody.querySelectorAll('tr:not(.no-records-row)');
                        var visibleRowsCount = 0;

                        rows.forEach(function(row) {
                            var textContent = row.textContent.toLowerCase();
                            if (textContent.indexOf(filterValue) > -1) {
                                row.style.display = '';
                                visibleRowsCount++;
                            } else {
                                row.style.display = 'none';
                            }
                        });

                        var dynamicFallback = document.getElementById('arms-search-fallback-row');
                        if (visibleRowsCount === 0 && rows.length > 0) {
                            if (!dynamicFallback) {
                                dynamicFallback = document.createElement('tr');
                                dynamicFallback.id = 'arms-search-fallback-row';
                                dynamicFallback.innerHTML = '<td colspan="8" style="text-align: center; color: #64748b; padding: 20px;">No matching records found matching the query criteria.</td>';
                                tableBody.appendChild(dynamicFallback);
                            }
                        } else if (dynamicFallback) {
                            dynamicFallback.remove();
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}