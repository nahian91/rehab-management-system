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
        <h2><?php echo $patient_id ? 'Edit Patient' : 'Add New Patient'; ?></h2>
        
        <h2 class="nav-tab-wrapper">
            <a class="nav-tab nav-tab-active" href="#tab-registration">Registration</a>
            <a class="nav-tab" href="#tab-upload">Upload</a>
            <a class="nav-tab" href="#tab-clinical">Clinical Data</a>
            <a class="nav-tab" href="#tab-followup">Follow-up History</a>
        </h2>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" style="margin-top: 20px;">
            <?php wp_nonce_field( 'arms_save_patient_nonce', 'arms_nonce' ); ?>
            <input type="hidden" name="action" value="arms_save_patient">
            <input type="hidden" name="patient_id" value="<?php echo esc_attr( $patient_id ); ?>">

            <div id="tab-registration" class="tab-content">
                <table class="form-table">
                    <tr><th>Name</th><td><input type="text" name="name" value="<?php echo esc_attr($data->name ?? ''); ?>" class="regular-text" required></td></tr>
                    <tr><th>Age</th><td><input type="number" name="age" value="<?php echo esc_attr($data->age ?? ''); ?>" required></td></tr>
                    <tr><th>Gender</th><td>
                        <select name="gender">
                            <option value="Male" <?php selected($data->gender ?? '', 'Male'); ?>>Male</option>
                            <option value="Female" <?php selected($data->gender ?? '', 'Female'); ?>>Female</option>
                        </select>
                    </td></tr>
                    <tr><th>Mobile</th><td><input type="tel" name="mobile" value="<?php echo esc_attr($data->mobile ?? ''); ?>" required></td></tr>
                    <tr><th>Emergency Contact Name</th><td><input type="text" name="emergency_contact_name" value="<?php echo esc_attr($data->emergency_contact_name ?? ''); ?>" class="regular-text"></td></tr>
                    <tr><th>Emergency Contact Phone</th><td><input type="tel" name="emergency_contact_phone" value="<?php echo esc_attr($data->emergency_contact_phone ?? ''); ?>"></td></tr>
                    <tr><th>Address</th><td><textarea name="address" class="large-text"><?php echo esc_textarea($data->address ?? ''); ?></textarea></td></tr>
                    <tr><th>Room Type</th><td>
                        <select name="room_type">
                            <option value="Cabin" <?php selected($data->room_type ?? '', 'Cabin'); ?>>Cabin</option>
                            <option value="General Ward" <?php selected($data->room_type ?? '', 'General Ward'); ?>>General Ward</option>
                            <option value="ICU" <?php selected($data->room_type ?? '', 'ICU'); ?>>ICU</option>
                        </select>
                    </td></tr>
                    <tr><th>Room No</th><td><input type="text" name="room_no" value="<?php echo esc_attr($data->room_no ?? ''); ?>" required></td></tr>
                    <tr><th>Status</th><td>
                        <select name="status">
                            <option value="Active Stay" <?php selected($data->status ?? '', 'Active Stay'); ?>>Active Stay</option>
                            <option value="Discharged" <?php selected($data->status ?? '', 'Discharged'); ?>>Discharged</option>
                        </select>
                    </td></tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary next-tab" data-next="#tab-upload">Next</button>
                </p>
            </div>

            <div id="tab-upload" class="tab-content" style="display:none;">
                <p>Upload patient files/media vault attachments.</p>
                <input type="file" name="patient_media[]" multiple>
                <p class="submit">
                    <button type="button" class="button prev-tab" data-prev="#tab-registration">Back</button>
                    <button type="button" class="button button-primary next-tab" data-next="#tab-clinical">Next</button>
                </p>
            </div>

            <div id="tab-clinical" class="tab-content" style="display:none;">
                <table class="form-table">
                    <tr><th>Initial Diagnosis</th><td><textarea name="initial_diagnosis" class="large-text"><?php echo esc_textarea($data->initial_diagnosis ?? ''); ?></textarea></td></tr>
                    <tr><th>Custom Diagnosis</th><td><input type="text" name="custom_diagnosis" value="<?php echo esc_attr($data->custom_diagnosis ?? ''); ?>" class="regular-text"></td></tr>
                </table>
                
                <h3>Patient Conditions Checklist</h3>
                <?php 
                foreach(['Stroke', 'Paralysis', 'PLID', 'SCI', 'Osteoarthritis'] as $item) {
                    $key = sanitize_title($item);
                    echo "<label><input type='checkbox' name='conditions[$key]' value='1' " . checked(1, $conditions[$key] ?? 0, false) . "> $item</label><br><br>";
                }
                ?>
                <p class="submit">
                    <button type="button" class="button prev-tab" data-prev="#tab-upload">Back</button>
                    <button type="button" class="button button-primary next-tab" data-next="#tab-followup">Next</button>
                </p>
            </div>

            <div id="tab-followup" class="tab-content" style="display:none;">
                <textarea name="followup_history" rows="10" class="large-text" placeholder="Enter patient clinical history details here..."><?php echo esc_textarea($data->followup_history ?? ''); ?></textarea>
                <p class="submit">
                    <button type="button" class="button prev-tab" data-prev="#tab-clinical">Back</button>
                    <?php submit_button('Save Patient Record', 'primary', 'submit', false); ?>
                </p>
            </div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        function switchTab(tabId) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[href="' + tabId + '"]').addClass('nav-tab-active');
            $('.tab-content').hide();
            $(tabId).show();
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
    
    // Exact schema synchronization alignment
    $data = [
        'name'                    => sanitize_text_field( $_POST['name'] ),
        'age'                     => intval( $_POST['age'] ),
        'gender'                  => sanitize_text_field( $_POST['gender'] ),
        'mobile'                  => sanitize_text_field( $_POST['mobile'] ),
        'emergency_contact_name'  => sanitize_text_field( $_POST['emergency_contact_name'] ?? '' ),
        'emergency_contact_phone' => sanitize_text_field( $_POST['emergency_contact_phone'] ?? '' ),
        'address'                 => sanitize_textarea_field( $_POST['address'] ),
        'room_type'               => sanitize_text_field( $_POST['room_type'] ),
        'room_no'                 => sanitize_text_field( $_POST['room_no'] ),
        'initial_diagnosis'       => sanitize_textarea_field( $_POST['initial_diagnosis'] ),
        'custom_diagnosis'        => sanitize_text_field( $_POST['custom_diagnosis'] ),
        'conditions'              => json_encode( array_map('sanitize_text_field', $_POST['conditions'] ?? []) ),
        'followup_history'        => sanitize_textarea_field( $_POST['followup_history'] ),
        'status'                  => sanitize_text_field( $_POST['status'] ),
    ];

    // Handle required fields that cannot be null based on schema definition
    if ( $id === 0 ) {
        $data['day_billing_ledger'] = ''; // NOT NULL fallback
        $data['media_vault_urls']   = ''; // NOT NULL fallback
    }

    if ( $id > 0 ) { 
        $wpdb->update( $table, $data, ['id' => $id] ); 
    } else { 
        $wpdb->insert( $table, $data ); 
    }

    wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=patients' ) );
    exit;
}