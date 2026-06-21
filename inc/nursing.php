<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Nursing Tab - ICU & Ward Level Daily Care Tracking System
 * Database Mapping: arms_nursing_logs
 */
function arms_nursing_tab() {
    global $wpdb;
    $table_nursing  = $wpdb->prefix . 'arms_nursing_logs';
    $table_patients = $wpdb->prefix . 'arms_patients'; // External relational mapping source

    // Sub-tab switching engine
    $current_sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';
    $log_id      = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    // Internal Routing Mapping Base
    $list_url = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=list' );
    $add_url  = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=add' );

    /* =========================================================================
       ACTION ROUTER: DELETE CLINICAL LOG ENTRY
       ========================================================================= */
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $log_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'arms_delete_nursing_' . $log_id ) ) {
            $deleted = $wpdb->delete( $table_nursing, array( 'id' => $log_id ), array( '%d' ) );
            if ( $deleted ) {
                echo '<div class="notice notice-success is-dismissible"><p>Clinical care log purged successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Database processing error while dropping the log record.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Security check failed. Action aborted.</p></div>';
        }
    }

    /* =========================================================================
       POST ENGINE: ENGINE HANDLING INSERTIONS / UPDATES
       ========================================================================= */
    if ( isset( $_POST['arms_save_nursing'] ) && check_admin_referer( 'arms_nursing_nonce_action', 'arms_nursing_nonce' ) ) {
        
        $patient_id    = isset( $_POST['patient_id'] ) ? intval( $_POST['patient_id'] ) : 0;
        $shift_type    = isset( $_POST['shift_type'] ) ? sanitize_text_field( wp_unslash( $_POST['shift_type'] ) ) : 'Morning';
        $location_type = isset( $_POST['location_type'] ) ? sanitize_text_field( wp_unslash( $_POST['location_type'] ) ) : 'General Ward';
        $bed_no        = isset( $_POST['bed_no'] ) ? sanitize_text_field( wp_unslash( $_POST['bed_no'] ) ) : '';
        $bp_systolic   = isset( $_POST['bp_systolic'] ) ? intval( $_POST['bp_systolic'] ) : 120;
        $bp_diastolic  = isset( $_POST['bp_diastolic'] ) ? intval( $_POST['bp_diastolic'] ) : 80;
        $pulse_rate    = isset( $_POST['pulse_rate'] ) ? intval( $_POST['pulse_rate'] ) : 75;
        $body_temp     = isset( $_POST['body_temp'] ) ? floatval( $_POST['body_temp'] ) : 98.6;
        $spo2_level    = isset( $_POST['spo2_level'] ) ? intval( $_POST['spo2_level'] ) : 98;
        $nursing_notes = isset( $_POST['nursing_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nursing_notes'] ) ) : '';
        $shift_report  = isset( $_POST['shift_report'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shift_report'] ) ) : '';
        $log_date      = ! empty( $_POST['log_date'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date'] ) ) : date('Y-m-d');

        // Parse medication chart repeater grid arrays 
        $med_names  = isset( $_POST['med_name'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['med_name'])) : array();
        $med_doses  = isset( $_POST['med_dose'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['med_dose'])) : array();
        $med_routes = isset( $_POST['med_route'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['med_route'])) : array();
        $med_freqs  = isset( $_POST['med_freq'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['med_freq'])) : array();
        $med_times  = isset( $_POST['med_time'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['med_time'])) : array();

        $medication_chart_array = array();
        for ( $i = 0; $i < count( $med_names ); $i++ ) {
            if ( ! empty( $med_names[$i] ) ) {
                $medication_chart_array[] = array(
                    'name'  => $med_names[$i],
                    'dose'  => isset($med_doses[$i]) ? $med_doses[$i] : '',
                    'route' => isset($med_routes[$i]) ? $med_routes[$i] : 'PO',
                    'freq'  => isset($med_freqs[$i]) ? $med_freqs[$i] : 'OD',
                    'time'  => isset($med_times[$i]) ? $med_times[$i] : '',
                );
            }
        }
        $medication_chart_json = wp_json_encode( $medication_chart_array );

        if ( $patient_id > 0 ) {
            $data_array = array(
                'patient_id'       => $patient_id,
                'log_date'         => $log_date,
                'shift_type'       => $shift_type,
                'location_type'    => $location_type,
                'bed_no'           => $bed_no,
                'bp_systolic'      => $bp_systolic,
                'bp_diastolic'     => $bp_diastolic,
                'pulse_rate'       => $pulse_rate,
                'body_temp'        => $body_temp,
                'spo2_level'       => $spo2_level,
                'medication_chart' => $medication_chart_json,
                'nursing_notes'    => $nursing_notes,
                'shift_report'     => $shift_report,
            );
            $format_array = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s' );

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $updated = $wpdb->update( $table_nursing, $data_array, array( 'id' => $log_id ), $format_array, array( '%d' ) );
                if ( $updated !== false ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Patient daily chart log data saved.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Failed to update record details: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            } else {
                $data_array['created_at'] = current_time( 'mysql' );
                $format_array[] = '%s';
                $inserted = $wpdb->insert( $table_nursing, $data_array, $format_array );
                if ( $inserted ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Ward Level Clinical chart tracking row logged securely.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Database insertion failed: ' . esc_html($wpdb->last_error) . '</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Validation error: Please associate this record with a valid patient file block.</p></div>';
        }
    }
    ?>

    <style>
        .arms-nurse-wrapper { padding: 24px; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; max-width: 1300px; margin: 20px auto; box-sizing: border-box; }
        .arms-nurse-wrapper * { box-sizing: border-box; }
        .arms-subnav-bar { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; margin-bottom: 24px; }
        .arms-subnav-link { padding: 10px 20px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease; }
        .arms-subnav-link:hover { color: #6366f1; }
        .arms-subnav-link.active { color: #6366f1; border-bottom-color: #6366f1; }
        .arms-card-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); margin-bottom: 24px; }
        .arms-card-header-flex { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
        .arms-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .arms-form-group { display: flex; flex-direction: column; gap: 6px; }
        .arms-form-group.fullwidth-col { grid-column: 1 / -1; }
        .arms-form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; }
        .arms-form-group input, .arms-form-group select, .arms-form-group textarea { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background-color: #fff; width: 100%; }
        .arms-form-group input:focus, .arms-form-group select:focus, .arms-form-group textarea:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1); }
        .arms-submit-btn { background: #6366f1; color: #fff; border: none; padding: 11px 22px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; text-decoration: none; display: inline-block; }
        .arms-submit-btn:hover { background: #4f46e5; }
        
        /* Clinical Parameter Layouts styling */
        .vital-badge-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin: 15px 0; }
        .vital-indicator-card { padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center; background: #f8fafc; }
        .vital-indicator-card .v-title { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .vital-indicator-card .v-value { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        .vital-critical { background: #fef2f2; border-color: #fecaca; }
        .vital-critical .v-value { color: #dc2626; }
        
        /* Repeater Control System */
        .arms-repeater-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .arms-repeater-table th { background: #f1f5f9; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; border: 1px solid #e2e8f0; }
        .arms-repeater-table td { padding: 6px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .repeater-add-btn { background: #10b981; color:#fff; padding: 6px 12px; border-radius:4px; font-size:12px; border:none; cursor:pointer; font-weight:600; }
        .repeater-add-btn:hover { background: #059669; }
        .repeater-del-btn { background: #ef4444; color:#fff; border:none; padding: 6px 10px; border-radius:4px; cursor:pointer; font-size:11px; }
        
        /* Action buttons configurations */
        .arms-data-table { width: 100%; border-collapse: collapse; text-align: left; }
        .arms-data-table th { background: #f8fafc; padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        .arms-data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .arms-action-btn-group { display: flex; gap: 4px; justify-content: flex-end; }
        .arms-action-btn { padding: 5px 10px; font-size: 12px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; }
        .btn-view { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn-edit { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-view:hover { background:#e2e8f0; } .btn-edit:hover { background:#dbeafe; } .btn-delete:hover { background:#fee2e2; }
    </style>

    <div class="arms-nurse-wrapper">
        <nav class="arms-subnav-bar">
            <a href="<?php echo esc_url( $list_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'list') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-clipboard" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Shift Care Logs Directory
            </a>
            <a href="<?php echo esc_url( $add_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'add') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-welcome-write-blog" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Chart Daily Vital & Meds
            </a>
            <?php if ( $current_sub === 'edit' ) : ?><a class="arms-subnav-link active">Modify Record Chart</a><?php endif; ?>
            <?php if ( $current_sub === 'view' ) : ?><a class="arms-subnav-link active">Patient Ward Level Sheet</a><?php endif; ?>
        </nav>

        <?php 
        /* =========================================================================
           SUB-VIEW: ADD / EDIT LOG FILES (WITH REPEATERS)
           ========================================================================= */
        if ( $current_sub === 'add' || $current_sub === 'edit' ) :
            $form_heading = "Initiate Shift Level ICU/Ward Care Entry";
            $row_data = null;
            $existing_meds = array();

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_nursing WHERE id = %d", $log_id ) );
                $form_heading = "Edit Nursing Entry Configuration Matrix Log #ID: " . esc_html($log_id);
                if ( $row_data && ! empty($row_data->medication_chart) ) {
                    $existing_meds = json_decode($row_data->medication_chart, true);
                }
            }
            ?>
            <div class="arms-card-box">
                <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 700; color:#1e293b;"><?php echo esc_html($form_heading); ?></h3>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'arms_nursing_nonce_action', 'arms_nursing_nonce' ); ?>
                    
                    <div class="arms-form-grid">
                        <div class="arms-form-group">
                            <label for="patient_id">Select Target Patient Profile *</label>
                            <select id="patient_id" name="patient_id" required>
                                <option value="">-- Choose Patient Folder Reference --</option>
                                <?php 
                                $patients_list = $wpdb->get_results("SELECT id, first_name, last_name FROM $table_patients ORDER BY first_name ASC");
                                if ( ! empty( $patients_list ) ) {
                                    foreach ( $patients_list as $pat ) {
                                        $selected_flag = ($row_data && intval($row_data->patient_id) === intval($pat->id)) ? 'selected' : '';
                                        echo '<option value="'.intval($pat->id).'" '.$selected_flag.'>'.esc_html($pat->first_name . ' ' . $pat->last_name . ' (#'.$pat->id.')').'</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label for="log_date">Date of Observation</label>
                            <input type="date" id="log_date" name="log_date" value="<?php echo $row_data ? esc_attr($row_data->log_date) : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="arms-form-group">
                            <label for="shift_type">Assigned Monitoring Shift</label>
                            <select id="shift_type" name="shift_type">
                                <option value="Morning" <?php echo ($row_data && $row_data->shift_type === 'Morning') ? 'selected' : ''; ?>>Morning Shift (06:00 - 14:00)</option>
                                <option value="Evening" <?php echo ($row_data && $row_data->shift_type === 'Evening') ? 'selected' : ''; ?>>Evening Shift (14:00 - 22:00)</option>
                                <option value="Night" <?php echo ($row_data && $row_data->shift_type === 'Night') ? 'selected' : ''; ?>>Night Duty (22:00 - 06:00)</option>
                            </select>
                        </div>
                    </div>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 20px;">
                        Patient Facility Location assignment
                    </h4>
                    <div class="arms-form-grid">
                        <div class="arms-form-group">
                            <label for="location_type">Ward / Location Type</label>
                            <select id="location_type" name="location_type">
                                <option value="General Ward" <?php echo ($row_data && $row_data->location_type === 'General Ward') ? 'selected' : ''; ?>>General Ward</option>
                                <option value="Semi-Private Ward" <?php echo ($row_data && $row_data->location_type === 'Semi-Private Ward') ? 'selected' : ''; ?>>Semi-Private Ward</option>
                                <option value="ICU Block" <?php echo ($row_data && $row_data->location_type === 'ICU Block') ? 'selected' : ''; ?>>Intensive Care Unit (ICU)</option>
                                <option value="CCU Block" <?php echo ($row_data && $row_data->location_type === 'CCU Block') ? 'selected' : ''; ?>>Coronary Care Unit (CCU)</option>
                                <option value="Private Cabin" <?php echo ($row_data && $row_data->location_type === 'Private Cabin') ? 'selected' : ''; ?>>Private Cabin Room</option>
                                <option value="Deluxe Cabin" <?php echo ($row_data && $row_data->location_type === 'Deluxe Cabin') ? 'selected' : ''; ?>>Deluxe Cabin Room</option>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label for="bed_no">Bed / Cabin Number Reference</label>
                            <select id="bed_no" name="bed_no">
                                <option value="">-- Select Allocated Bed --</option>
                                <?php 
                                // Generates a structured operational array list mapping beds/cabins dynamically
                                for($b = 1; $b <= 50; $b++) {
                                    $bed_id = "Bed-" . str_pad($b, 2, "0", STR_PAD_LEFT);
                                    $selected_bed = ($row_data && $row_data->bed_no === $bed_id) ? 'selected' : '';
                                    echo '<option value="'.esc_attr($bed_id).'" '.$selected_bed.'>Allocated '.esc_html($bed_id).'</option>';
                                }
                                for($c = 101; $c <= 120; $c++) {
                                    $cabin_id = "Cabin-" . $c;
                                    $selected_cabin = ($row_data && $row_data->bed_no === $cabin_id) ? 'selected' : '';
                                    echo '<option value="'.esc_attr($cabin_id).'" '.$selected_cabin.'>Private '.esc_html($cabin_id).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px;">
                        Vital Signs Metrics Panel
                    </h4>
                    <div class="arms-form-grid">
                        <div class="arms-form-group">
                            <label for="bp_systolic">Systolic BP (mmHg)</label>
                            <input type="number" id="bp_systolic" name="bp_systolic" value="<?php echo $row_data ? intval($row_data->bp_systolic) : '120'; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="bp_diastolic">Diastolic BP (mmHg)</label>
                            <input type="number" id="bp_diastolic" name="bp_diastolic" value="<?php echo $row_data ? intval($row_data->bp_diastolic) : '80'; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="pulse_rate">Pulse Rate (BPM)</label>
                            <input type="number" id="pulse_rate" name="pulse_rate" value="<?php echo $row_data ? intval($row_data->pulse_rate) : '72'; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="body_temp">Body Temperature (°F)</label>
                            <input type="number" step="0.1" id="body_temp" name="body_temp" value="<?php echo $row_data ? floatval($row_data->body_temp) : '98.6'; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="spo2_level">Oxygen Saturation SpO2 (%)</label>
                            <input type="number" id="spo2_level" name="spo2_level" value="<?php echo $row_data ? intval($row_data->spo2_level) : '98'; ?>">
                        </div>
                    </div>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px;">
                        <span>Active Medication Chart Repeater</span>
                        <button type="button" class="repeater-add-btn" id="armsAddMedicationRow">+ Add Medication Entry</button>
                    </h4>
                    
                    <table class="arms-repeater-table" id="armsMedicationRepeaterGrid">
                        <thead>
                            <tr>
                                <th>Medicine Generic / Brand Name</th>
                                <th style="width:150px;">Dosage Metric</th>
                                <th style="width:160px;">Route Configuration</th>
                                <th style="width:160px;">Frequency Interval</th>
                                <th style="width:150px;">Administration Target Time</th>
                                <th style="width:50px; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $existing_meds ) ) : foreach ( $existing_meds as $med ) : ?>
                                <tr>
                                    <td><input type="text" name="med_name[]" value="<?php echo esc_attr($med['name']); ?>" placeholder="e.g. Inj. Ceftriaxone"></td>
                                    <td><input type="text" name="med_dose[]" value="<?php echo esc_attr($med['dose']); ?>" placeholder="e.g. 1gm or 500mg"></td>
                                    <td>
                                        <select name="med_route[]">
                                            <option value="IV" <?php selected($med['route'], 'IV'); ?>>Intravenous (IV)</option>
                                            <option value="IM" <?php selected($med['route'], 'IM'); ?>>Intramuscular (IM)</option>
                                            <option value="PO" <?php selected($med['route'], 'PO'); ?>>Oral (Per Os)</option>
                                            <option value="SC" <?php selected($med['route'], 'SC'); ?>>Subcutaneous</option>
                                            <option value="Neb" <?php selected($med['route'], 'Neb'); ?>>Nebulizer</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="med_freq[]">
                                            <option value="OD" <?php selected($med['freq'], 'OD'); ?>>Once Daily (OD)</option>
                                            <option value="BD" <?php selected($med['freq'], 'BD'); ?>>Twice Daily (BD)</option>
                                            <option value="TDS" <?php selected($med['freq'], 'TDS'); ?>>Three Times (TDS)</option>
                                            <option value="QDS" <?php selected($med['freq'], 'QDS'); ?>>Four Times (QDS)</option>
                                            <option value="PRN" <?php selected($med['freq'], 'PRN'); ?>>As Needed (PRN)</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="med_time[]" value="<?php echo esc_attr($med['time']); ?>" placeholder="e.g. 08:00 AM, 10:00 PM"></td>
                                    <td style="text-align:center;"><button type="button" class="repeater-del-btn armsRemoveRow">Drop</button></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr>
                                    <td><input type="text" name="med_name[]" placeholder="e.g. Tab. Paracetamol"></td>
                                    <td><input type="text" name="med_dose[]" placeholder="e.g. 500 mg"></td>
                                    <td>
                                        <select name="med_route[]">
                                            <option value="PO">Oral (Per Os)</option>
                                            <option value="IV">Intravenous (IV)</option>
                                            <option value="IM">Intramuscular (IM)</option>
                                            <option value="SC">Subcutaneous</option>
                                            <option value="Neb">Nebulizer</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="med_freq[]">
                                            <option value="TDS">Three Times (TDS)</option>
                                            <option value="BD">Twice Daily (BD)</option>
                                            <option value="OD">Once Daily (OD)</option>
                                            <option value="QDS">Four Times (QDS)</option>
                                            <option value="PRN">As Needed (PRN)</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="med_time[]" placeholder="e.g. 02:00 PM"></td>
                                    <td style="text-align:center;"><button type="button" class="repeater-del-btn armsRemoveRow">Drop</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px;">
                        Clinical Nursing Diagnostics & Reporting
                    </h4>
                    <div class="arms-form-grid">
                        <div class="arms-form-group fullwidth-col">
                            <label for="nursing_notes">Daily Active Nursing Progress Notes / Head-to-Toe Assessment</label>
                            <textarea id="nursing_notes" name="nursing_notes" rows="4" placeholder="Log physical updates, fluid outputs, patient mental states or ventilation updates..."><?php echo $row_data ? esc_textarea($row_data->nursing_notes) : ''; ?></textarea>
                        </div>
                        <div class="arms-form-group fullwidth-col">
                            <label for="shift_report">Critical Shift Handover Report</label>
                            <textarea id="shift_report" name="shift_report" rows="3" placeholder="Provide direct warning flags or instructions to incoming staff members for the next shift..."><?php echo $row_data ? esc_textarea($row_data->shift_report) : ''; ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="arms_save_nursing" class="arms-submit-btn">
                        <span class="dashicons dashicons-database-add" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Commit Shift Record to Active Framework
                    </button>
                    <a href="<?php echo esc_url($list_url); ?>" class="arms-action-btn btn-view" style="padding:11px 18px; margin-left:10px; font-size:13px; font-weight:600; border-radius:6px;">Exit Form</a>
                </form>
            </div>

            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var repeaterBody = document.querySelector('#armsMedicationRepeaterGrid tbody');
                var addRowBtn     = document.getElementById('armsAddMedicationRow');

                if (addRowBtn && repeaterBody) {
                    addRowBtn.addEventListener('click', function() {
                        var templateRow = document.createElement('tr');
                        templateRow.innerHTML = `
                            <td><input type="text" name="med_name[]" placeholder="Medicine Generic / Brand Name"></td>
                            <td><input type="text" name="med_dose[]" placeholder="Dosage, e.g. 1 tab"></td>
                            <td>
                                <select name="med_route[]">
                                    <option value="PO">Oral (Per Os)</option>
                                    <option value="IV">Intravenous (IV)</option>
                                    <option value="IM">Intramuscular (IM)</option>
                                    <option value="SC">Subcutaneous</option>
                                    <option value="Neb">Nebulizer</option>
                                </select>
                            </td>
                            <td>
                                <select name="med_freq[]">
                                    <option value="OD">Once Daily (OD)</option>
                                    <option value="BD">Twice Daily (BD)</option>
                                    <option value="TDS">Three Times (TDS)</option>
                                    <option value="QDS">Four Times (QDS)</option>
                                    <option value="PRN">As Needed (PRN)</option>
                                </select>
                            </td>
                            <td><input type="text" name="med_time[]" placeholder="Administration Time"></td>
                            <td style="text-align:center;"><button type="button" class="repeater-del-btn armsRemoveRow">Drop</button></td>
                        `;
                        repeaterBody.appendChild(templateRow);
                    });

                    repeaterBody.addEventListener('click', function(e) {
                        if (e.target && e.target.classList.contains('armsRemoveRow')) {
                            var rowCount = repeaterBody.querySelectorAll('tr').length;
                            if(rowCount > 1) {
                                e.target.closest('tr').remove();
                            } else {
                                alert("At least one target row template must look active inside the chart field.");
                            }
                        }
                    });
                }
            });
            </script>

        <?php 
        /* =========================================================================
           SUB-VIEW: PATIENT CLINICAL FILE CARD OVERLAY VIEW
           ========================================================================= */
        elseif ( $current_sub === 'view' && $log_id > 0 ) :
            $log = $wpdb->get_row( $wpdb->prepare( "SELECT n.*, p.first_name, p.last_name FROM $table_nursing n LEFT JOIN $table_patients p ON n.patient_id = p.id WHERE n.id = %d", $log_id ) );
            if ( ! $log ) {
                echo '<div class="notice notice-error"><p>Target clinical sheet could not be mapped inside storage blocks.</p></div>';
                return;
            }

            // Flag critical vitals thresholds
            $is_pulse_high = ($log->pulse_rate > 100 || $log->pulse_rate < 60) ? 'vital-critical' : '';
            $is_spo2_low   = ($log->spo2_level < 94) ? 'vital-critical' : '';
            $is_temp_high  = ($log->body_temp > 100.4 || $log->body_temp < 96.5) ? 'vital-critical' : '';
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex" style="border-bottom: 2px solid #f1f5f9; padding-bottom:14px;">
                    <div>
                        <h2 style="margin:0; font-size:20px; font-weight:800; color:#1e293b;">Daily Clinical Monitoring Log</h2>
                        <p style="margin:4px 0 0 0; color:#64748b; font-weight:500;">
                            Patient Profile Target: <strong style="color:#0f172a;"><?php echo esc_html($log->first_name . ' ' . $log->last_name); ?> (#<?php echo intval($log->patient_id); ?>)</strong>
                        </p>
                    </div>
                    <a href="<?php echo esc_url($list_url); ?>" class="arms-submit-btn">← Back to Monitoring Desk</a>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin: 20px 0; font-size:13px; background:#f8fafc; padding:12px 18px; border-radius:6px; border:1px solid #e2e8f0;">
                    <span><strong>Observation Date:</strong> <?php echo esc_html(date('M j, Y', strtotime($log->log_date))); ?></span>
                    <span><strong>Active Duty Shift:</strong> <?php echo esc_html($log->shift_type); ?></span>
                    <span><strong>Location Unit:</strong> <span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; font-weight:600; border-radius:4px;"><?php echo esc_html($log->location_type . ' - ' . $log->bed_no); ?></span></span>
                    <span><strong>Chart ID Reference:</strong> #NURS-<?php echo esc_html($log->id); ?></span>
                </div>

                <h3 style="font-size:13px; text-transform:uppercase; color:#475569; margin: 24px 0 10px 0;">Patient Vital Signs Dashboard</h3>
                <div class="vital-badge-grid">
                    <div class="vital-indicator-card">
                        <div class="v-title">Blood Pressure</div>
                        <div class="v-value"><?php echo intval($log->bp_systolic) . '/' . intval($log->bp_diastolic); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">mmHg</span></div>
                    </div>
                    <div class="vital-indicator-card <?php echo $is_pulse_high; ?>">
                        <div class="v-title">Pulse Rate</div>
                        <div class="v-value"><?php echo intval($log->pulse_rate); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">BPM</span></div>
                    </div>
                    <div class="vital-indicator-card <?php echo $is_temp_high; ?>">
                        <div class="v-title">Body Temp</div>
                        <div class="v-value"><?php echo floatval($log->body_temp); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">°F</span></div>
                    </div>
                    <div class="vital-indicator-card <?php echo $is_spo2_low; ?>">
                        <div class="v-title">Oxygen Level</div>
                        <div class="v-value"><?php echo intval($log->spo2_level); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">% SpO2</span></div>
                    </div>
                </div>

                <h3 style="font-size:13px; text-transform:uppercase; color:#475569; margin: 30px 0 10px 0;">Administered Shift Medication Chart</h3>
                <table class="arms-data-table" style="border: 1px solid #e2e8f0;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px;">Therapeutic / Generic Agent</th>
                            <th style="padding:10px;">Dosage Given</th>
                            <th style="padding:10px;">Delivery Route</th>
                            <th style="padding:10px;">Frequency Protocol</th>
                            <th style="padding:10px;">Logged Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ( ! empty( $log->medication_chart ) ) {
                            $meds = json_decode( $log->medication_chart, true );
                            if ( ! empty( $meds ) ) {
                                foreach ( $meds as $m ) {
                                    echo '<tr>';
                                    echo '<td style="font-weight:600; padding:10px;">'.esc_html($m['name']).'</td>';
                                    echo '<td style="padding:10px;">'.esc_html($m['dose']).'</td>';
                                    echo '<td style="padding:10px;"><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px;">'.esc_html($m['route']).'</code></td>';
                                    echo '<td style="padding:10px;">'.esc_html($m['freq']).'</td>';
                                    echo '<td style="padding:10px; color:#475569;">'.esc_html($m['time']).'</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align:center; padding:12px; color:#64748b;">No medications parsed onto chart sheet rows.</td></tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" style="text-align:center; padding:12px; color:#64748b;">No medication records associated with this clinical shift.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>

                <div style="margin-top:30px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div style="background:#fdfdfd; border:1px dashed #cbd5e1; padding:16px; border-radius:8px;">
                        <h4 style="margin:0 0 8px 0; font-size:12px; text-transform:uppercase; color:#475569;">Nursing Progress Logs</h4>
                        <p style="margin:0; font-size:13px; line-height:1.6; white-space:pre-line; color:#334155;"><?php echo !empty($log->nursing_notes) ? esc_html($log->nursing_notes) : 'No therapeutic notation compiled.'; ?></p>
                    </div>
                    <div style="background:#fffbfa; border:1px dashed #fecaca; padding:16px; border-radius:8px;">
                        <h4 style="margin:0 0 8px 0; font-size:12px; text-transform:uppercase; color:#dc2626;">Shift Handover Warnings</h4>
                        <p style="margin:0; font-size:13px; line-height:1.6; white-space:pre-line; color:#991b1b;"><?php echo !empty($log->shift_report) ? esc_html($log->shift_report) : 'No critical handover instructions noted.'; ?></p>
                    </div>
                </div>
            </div>

        <?php 
        /* =========================================================================
           SUB-VIEW: DEFAULT MASTER WARD LIST OVERVIEW
           ========================================================================= */
        else : ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Ward Monitoring Desk & Clinical Archives</h3>
                    <input type="text" id="armsNursingTableSearch" class="arms-form-group" style="max-width:280px; padding:8px 12px; border-radius:6px;" placeholder="Filter entries by patient name or location...">
                </div>

                <div style="overflow-x: auto;">
                    <table class="arms-data-table" id="armsMasterNursingTable">
                        <thead>
                            <tr>
                                <th>Chart Ref</th>
                                <th>Patient Name</th>
                                <th>Assigned Unit / Location</th>
                                <th>Observation Date</th>
                                <th>Assigned Shift</th>
                                <th>Vitals Overview (BP | Pulse | SpO2)</th>
                                <th>Meds Count</th>
                                <th style="text-align: right; padding-right:20px;">Controls Grid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $master_logs = $wpdb->get_results("SELECT n.*, p.first_name, p.last_name FROM $table_nursing n LEFT JOIN $table_patients p ON n.patient_id = p.id ORDER BY n.log_date DESC, n.id DESC");
                            
                            if ( ! empty( $master_logs ) ) :
                                foreach ( $master_logs as $log ) :
                                    $med_count = 0;
                                    if ( ! empty( $log->medication_chart ) ) {
                                        $decoded_arr = json_decode($log->medication_chart, true);
                                        $med_count = is_array($decoded_arr) ? count($decoded_arr) : 0;
                                    }

                                    $view_log_url   = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=view&id=' . $log->id );
                                    $edit_log_url   = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=edit&id=' . $log->id );
                                    $delete_log_url = wp_nonce_url( admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=list&action=delete&id=' . $log->id ), 'arms_delete_nursing_' . $log->id );
                                    ?>
                                    <tr>
                                        <td><code>#NURS-<?php echo esc_html($log->id); ?></code></td>
                                        <td><strong><?php echo esc_html($log->first_name . ' ' . $log->last_name); ?></strong></td>
                                        <td>
                                            <span style="font-weight: 600; font-size:12px; color:#1e40af; background:#dbeafe; padding:2px 8px; border-radius:4px;">
                                                <?php echo esc_html($log->location_type); ?> [<?php echo !empty($log->bed_no) ? esc_html($log->bed_no) : 'N/A'; ?>]
                                            </span>
                                        </td>
                                        <td><?php echo esc_html(date('M j, Y', strtotime($log->log_date))); ?></td>
                                        <td><span style="font-weight:600; color:#475569;"><?php echo esc_html($log->shift_type); ?></span></td>
                                        <td>
                                            <span style="display:inline-block; font-size:12px; background:#f1f5f9; padding:4px 8px; border-radius:4px;">
                                                <strong>BP:</strong> <?php echo intval($log->bp_systolic).'/'.intval($log->bp_diastolic); ?> | 
                                                <strong>PR:</strong> <?php echo intval($log->pulse_rate); ?> | 
                                                <strong>SpO2:</strong> <?php echo intval($log->spo2_level); ?>%
                                            </span>
                                        </td>
                                        <td><span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-weight:700; font-size:11px;"><?php echo intval($med_count); ?> Agents</span></td>
                                        <td>
                                            <div class="arms-action-btn-group">
                                                <a href="<?php echo esc_url($view_log_url); ?>" class="arms-action-btn btn-view">
                                                    <span class="dashicons dashicons-visibility" style="font-size:14px; margin-right:2px;"></span> View
                                                </a>
                                                <a href="<?php echo esc_url($edit_log_url); ?>" class="arms-action-btn btn-edit">
                                                    <span class="dashicons dashicons-edit" style="font-size:14px; margin-right:2px;"></span> Edit
                                                </a>
                                                <a href="<?php echo esc_url($delete_log_url); ?>" class="arms-action-btn btn-delete" onclick="return confirm('Purge this record from the database structure?');">
                                                    <span class="dashicons dashicons-trash" style="font-size:14px; margin-right:2px;"></span> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr class="no-records-row">
                                        <td colspan="8" style="text-align:center; padding:40px; color:#64748b;">No ward tracking log registries found inside the engine system context.</td>
                                    </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var searchBox = document.getElementById('armsNursingTableSearch');
                var tableRows = document.querySelectorAll('#armsMasterNursingTable tbody tr:not(.no-records-row)');

                if (searchBox) {
                    searchBox.addEventListener('keyup', function() {
                        var term = searchBox.value.toLowerCase();
                        tableRows.forEach(function(row) {
                            var text = row.textContent.toLowerCase();
                            row.style.display = (text.indexOf(term) > -1) ? '' : 'none';
                        });
                    });
                }
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}