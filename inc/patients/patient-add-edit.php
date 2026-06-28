<?php
// 1. FORM RENDERER
function arms_add_edit_patient_form( $patient_id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'arms_patients';
    
    // Fetch existing data if ID is provided
    $data = ( $patient_id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $patient_id ) ) : null;
    $conditions = !empty($data->conditions) ? json_decode($data->conditions, true) : [];

    ?>
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
                    <p class="description">Select conditions matching the patient's medical summary status:</p>
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
                    <p class="description">Complete Visit History Logs & Clinical Trackings:</p>
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