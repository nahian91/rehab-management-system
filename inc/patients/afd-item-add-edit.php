<?php
// 1. FORM RENDERER
function arms_add_edit_patient_form( $patient_id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'arms_patients';
    
    // Fetch existing data if ID is provided
    $data = ( $patient_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $patient_id ) ) : null;
    $conditions = !empty($data->conditions) ? json_decode($data->conditions, true) : [];

    ?>
    <style>
        /* Form Design & Layout Alignment */
        .arms-form-container {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin-top: 20px;
            max-width: 900px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .arms-form-container h2.nav-tab-wrapper {
            margin: -24px -24px 24px -24px;
            padding: 10px 15px 0 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .arms-form-container .nav-tab {
            margin-bottom: -1px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 500;
        }
        
        /* 2-Column Responsive Form Layout */
        .arms-two-column-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px 30px;
            padding: 10px 0;
        }
        .arms-form-group {
            flex: 1 1 calc(50% - 15px);
            min-width: 280px;
            display: flex;
            flex-direction: column;
        }
        .arms-form-group.full-width {
            flex: 1 1 100%;
        }
        .arms-form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 13px;
        }
        
        /* Strict Uniform Width Rule for Inputs/Selects */
        .arms-form-container input[type="text"], 
        .arms-form-container input[type="number"], 
        .arms-form-container input[type="tel"],
        .arms-form-container input[type="file"],
        .arms-form-container select, 
        .arms-form-container textarea {
            box-sizing: border-box;
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 12px;
            box-shadow: none;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out;
        }
        .arms-form-container input:focus, 
        .arms-form-container select:focus, 
        .arms-form-container textarea:focus {
            border-color: #003376;
            box-shadow: 0 0 0 1px #003376;
        }
        .arms-id-display-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 12px;
        }
        .arms-auto-id-badge {
            display: inline-block;
            background: #e2e8f0;
            color: #475569;
            padding: 2px 8px;
            font-weight: 600;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .arms-condition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin: 15px 0 25px 0;
            max-width: 500px;
        }
        .arms-condition-item {
            background: #f1f5f9;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        .arms-condition-item:hover {
            background: #e2e8f0;
        }
        .arms-condition-item input {
            margin: 0 10px 0 0 !important;
            width: auto !important;
        }
        .arms-upload-row {
            margin-bottom: 18px;
        }
        .arms-upload-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1e293b;
        }
        .arms-action-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .arms-action-footer .submit-right {
            margin-left: auto;
        }
    </style>

    <div class="wrap">
        <hr class="wp-header-end">
        
        <div class="arms-form-container">
            <h2 class="nav-tab-wrapper">
                <a class="nav-tab nav-tab-active" href="#tab-registration">Registration</a>
                <a class="nav-tab" href="#tab-upload">Upload Documents</a>
                <a class="nav-tab" href="#tab-clinical">Clinical Data</a>
                <a class="nav-tab" href="#tab-followup">Follow-up History</a>
            </h2>

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'arms_save_patient_nonce', 'arms_nonce' ); ?>
                <input type="hidden" name="action" value="arms_save_patient">
                <input type="hidden" name="patient_id" value="<?php echo esc_attr( $patient_id ); ?>">

                <!-- Tab 1: Registration (2-Column Grid Layout) -->
                <div id="tab-registration" class="tab-content">
                    <div class="arms-two-column-grid">
                        
                        <div class="arms-form-group">
                            <label>Patient ID</label>
                            <div class="arms-id-display-box">
                                <span class="arms-auto-id-badge">
                                    <?php echo $patient_id ? 'ARMS-' . str_pad($patient_id, 5, '0', STR_PAD_LEFT) : 'Auto-Generated on Save'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="arms-form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo esc_attr($data->name ?? ''); ?>" required>
                        </div>

                        <div class="arms-form-group">
                            <label for="age">Age</label>
                            <input type="number" id="age" name="age" value="<?php echo esc_attr($data->age ?? ''); ?>" required>
                        </div>

                        <div class="arms-form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="Male" <?php selected($data->gender ?? '', 'Male'); ?>>Male</option>
                                <option value="Female" <?php selected($data->gender ?? '', 'Female'); ?>>Female</option>
                            </select>
                        </div>

                        <div class="arms-form-group">
                            <label for="mobile">Mobile Number</label>
                            <input type="tel" id="mobile" name="mobile" value="<?php echo esc_attr($data->mobile ?? ''); ?>" required>
                        </div>

                        <div class="arms-form-group">
                            <label for="emergency_contact_name">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo esc_attr($data->emergency_contact_name ?? ''); ?>">
                        </div>

                        <div class="arms-form-group">
                            <label for="emergency_contact_phone">Emergency Contact Phone</label>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo esc_attr($data->emergency_contact_phone ?? ''); ?>">
                        </div>

                        <div class="arms-form-group">
                            <label for="patient_photo">Patient Photo</label>
                            <input type="file" id="patient_photo" name="patient_photo" accept="image/*">
                        </div>

                        <div class="arms-form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo esc_textarea($data->address ?? ''); ?></textarea>
                        </div>

                        <div class="arms-form-group full-width">
                            <label for="initial_diagnosis">Diagnosis</label>
                            <textarea id="initial_diagnosis" name="initial_diagnosis" rows="3"><?php echo esc_textarea($data->initial_diagnosis ?? ''); ?></textarea>
                        </div>

                    </div>

                    <div class="arms-action-footer">
                        <div class="submit-right">
                            <button type="button" class="button button-primary next-tab" data-next="#tab-upload">Next Step</button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Upload -->
                <div id="tab-upload" class="tab-content" style="display:none; padding: 10px 0;">
                    <div class="arms-upload-row">
                        <label for="upload_mri">MRI Attachments</label>
                        <input type="file" id="upload_mri" name="patient_mri[]" multiple>
                    </div>
                    <div class="arms-upload-row">
                        <label for="upload_xray">X-Ray Attachments</label>
                        <input type="file" id="upload_xray" name="patient_xray[]" multiple>
                    </div>
                    <div class="arms-upload-row">
                        <label for="upload_ct">CT Scan Attachments</label>
                        <input type="file" id="upload_ct" name="patient_ct[]" multiple>
                    </div>
                    <div class="arms-upload-row">
                        <label for="upload_lab">Laboratory Reports</label>
                        <input type="file" id="upload_lab" name="patient_lab[]" multiple>
                    </div>
                    
                    <div class="arms-action-footer">
                        <button type="button" class="button prev-tab" data-prev="#tab-registration">Back</button>
                        <button type="button" class="button button-primary next-tab" data-next="#tab-clinical">Next Step</button>
                    </div>
                </div>

                <!-- Tab 3: Clinical Data -->
                <div id="tab-clinical" class="tab-content" style="display:none;">
                    <p class="description" style="margin-bottom:15px; font-size:14px;">Select conditions matching the patient's medical summary status:</p>
                    <div class="arms-condition-grid">
                        <?php 
                        foreach(['Stroke', 'Paralysis', 'PLID', 'SCI', 'Osteoarthritis'] as $item) {
                            $key = sanitize_title($item);
                            echo "<label class='arms-condition-item'>";
                            echo "<input type='checkbox' name='conditions[$key]' value='1' " . checked(1, $conditions[$key] ?? 0, false) . ">";
                            echo "<span>$item</span>";
                            echo "</label>";
                        }
                        ?>
                    </div>
                    
                    <div class="arms-action-footer">
                        <button type="button" class="button prev-tab" data-prev="#tab-upload">Back</button>
                        <button type="button" class="button button-primary next-tab" data-next="#tab-followup">Next Step</button>
                    </div>
                </div>

                <!-- Tab 4: Follow-up History -->
                <div id="tab-followup" class="tab-content" style="display:none;">
                    <p class="description" style="margin-bottom:12px; font-size:13px;">Complete Visit History Logs & Clinical Trackings:</p>
                    <textarea name="followup_history" rows="12" placeholder="Enter continuous visit summary tracking details here..."><?php echo esc_textarea($data->followup_history ?? ''); ?></textarea>
                    
                    <div class="arms-action-footer">
                        <button type="button" class="button prev-tab" data-prev="#tab-clinical">Back</button>
                        <div class="submit-right">
                            <?php submit_button('Save Patient Record', 'primary', 'submit', false); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        function switchTab(tabId) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[href="' + tabId + '"]').addClass('nav-tab-active');
            $('.tab-content').hide();
            $(tabId).show();
            $('html, body').animate({
                scrollTop: $(".arms-form-container").offset().top - 40
            }, 150);
        }

        $('.nav-tab').click(function(e) {
            e.preventDefault();
            switchTab($(this).attr('href'));
        });

        $('.next-tab').click(function() {
            switchTab($(this).data('next'));
        });

        $('.prev-tab').click(function() {
            switchTab($(this).data('prev'));
        });
    });
    </script>
    <?php
}

// 2. DATA HANDLER (Processing Engine)
add_action( 'admin_post_arms_save_patient', 'arms_handle_patient_save' );
function arms_handle_patient_save() {
    if ( ! isset( $_POST['arms_nonce'] ) || ! wp_verify_nonce( $_POST['arms_nonce'], 'arms_save_patient_nonce' ) ) {
        wp_die('Unauthorized Submission');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'arms_patients';
    $id = isset($_POST['patient_id']) ? intval( $_POST['patient_id'] ) : 0;
    
    // Check for an uploaded single profile patient photo
    $uploaded_photo_url = '';
    if ( ! empty( $_FILES['patient_photo']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload( $_FILES['patient_photo'], array( 'test_form' => false ) );
        if ( isset( $upload['url'] ) ) {
            $uploaded_photo_url = $upload['url'];
        }
    }

    // Build alignment map for database columns
    $data = [
        'name'                    => sanitize_text_field( $_POST['name'] ),
        'age'                     => intval( $_POST['age'] ),
        'gender'                  => sanitize_text_field( $_POST['gender'] ),
        'mobile'                  => sanitize_text_field( $_POST['mobile'] ),
        'emergency_contact_name'  => sanitize_text_field( $_POST['emergency_contact_name'] ?? '' ),
        'emergency_contact_phone' => sanitize_text_field( $_POST['emergency_contact_phone'] ?? '' ),
        'address'                 => sanitize_textarea_field( $_POST['address'] ),
        'initial_diagnosis'       => sanitize_textarea_field( $_POST['initial_diagnosis'] ),
        'conditions'              => json_encode( array_map('sanitize_text_field', $_POST['conditions'] ?? []) ),
        'followup_history'        => sanitize_textarea_field( $_POST['followup_history'] ),
    ];

    // If a profile photo was uploaded, route it into media_vault_urls
    if ( ! empty( $uploaded_photo_url ) ) {
        $data['media_vault_urls'] = esc_url_raw( $uploaded_photo_url );
    }

    // Default entries required by NOT NULL structure rules or unsubmitted settings
    if ( $id === 0 ) {
        if ( ! isset( $data['media_vault_urls'] ) ) {
            $data['media_vault_urls'] = ''; 
        }
        $data['day_billing_ledger']   = ''; 
        $data['custom_diagnosis']     = ''; 
        $data['room_type']            = 'Cabin';
        $data['room_no']              = '0';
        $data['status']               = 'Active Stay';
    }

    if ( $id > 0 ) { 
        $wpdb->update( $table, $data, ['id' => $id] ); 
    } else { 
        $wpdb->insert( $table, $data ); 
    }

    wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=patients' ) );
    exit;
}