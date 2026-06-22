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
    $table_patients = $wpdb->prefix . 'arms_patients'; // Relational mapping matching your schema

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
        .arms-form-group { display: flex; flex-direction: column; gap: 6px; position: relative; }
        .arms-form-group.fullwidth-col { grid-column: 1 / -1; }
        .arms-form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; }
        .arms-form-group input, .arms-form-group select, .arms-form-group textarea { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background-color: #fff; width: 100%; }
        .arms-form-group input:focus, .arms-form-group select:focus, .arms-form-group textarea:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1); }
        
        /* Patient Search Layout Configurations */
        .arms-searchable-group { background: #fdfdfd; padding: 16px; border: 1px dashed #cbd5e1; border-radius: 8px; margin-bottom: 8px; }
        .arms-patient-search-input { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%2394a3b8" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px !important; font-weight: 500; }

        .arms-submit-btn { background: #6366f1; color: #fff; border: none; padding: 11px 22px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; text-decoration: none; display: inline-block; }
        .arms-submit-btn:hover { background: #4f46e5; }
        
        /* Vital Badge Elements */
        .vital-badge-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin: 15px 0; }
        .vital-indicator-card { padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center; background: #f8fafc; }
        .vital-indicator-card .v-title { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .vital-indicator-card .v-value { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        .vital-critical { background: #fef2f2; border-color: #fecaca; }
        .vital-critical .v-value { color: #dc2626; }
        
        /* Repeater Control Matrix */
        .arms-repeater-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .arms-repeater-table th { background: #f1f5f9; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; border: 1px solid #e2e8f0; }
        .arms-repeater-table td { padding: 6px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .repeater-add-btn { background: #10b981; color:#fff; padding: 6px 12px; border-radius:4px; font-size:12px; border:none; cursor:pointer; font-weight:600; }
        .repeater-add-btn:hover { background: #059669; }
        .repeater-del-btn { background: #ef4444; color:#fff; border:none; padding: 6px 10px; border-radius:4px; cursor:pointer; font-size:11px; }
        
        /* Action Directory Components */
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
                        <div class="arms-form-group fullwidth-col arms-searchable-group" style="position: relative;">
                            <label for="arms_patient_search" style="display:block; margin-bottom: 6px;">Target Patient Profile *</label>
                            
                            <?php 
                            // Corrected mapping: Selected the explicit consolidated "name" schema mapping attribute
                            $patients_list = $wpdb->get_results("SELECT id, name FROM $table_patients ORDER BY name ASC");
                            
                            $selected_id = ($row_data) ? intval($row_data->patient_id) : 0;
                            $selected_display = '';
                            if ( $selected_id > 0 && ! empty( $patients_list ) ) {
                                foreach ( $patients_list as $pat ) {
                                    if ( intval($pat->id) === $selected_id ) {
                                        $selected_display = esc_html($pat->name . ' (#' . $pat->id . ')');
                                        break;
                                    }
                                }
                            }
                            ?>

                            <input type="hidden" id="patient_id" name="patient_id" value="<?php echo $selected_id; ?>" required>

                            <input type="text" id="arms_patient_search" class="arms-patient-search-input" 
                                   placeholder="Type name or Patient ID number here to filter results live..." 
                                   value="<?php echo $selected_display; ?>" autocomplete="off" style="z-index: 2;">
                            
                            <div id="arms_patient_dropdown_list" style="display: none; position: absolute; top: 100%; left: 16px; right: 16px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; max-height: 200px; overflow-y: auto; z-index: 999; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 2px;">
                                <div class="arms-patient-option-empty" style="padding: 10px 12px; color: #64748b; font-style: italic; display: none;">No matching patients discovered...</div>
                                <?php 
                                if ( ! empty( $patients_list ) ) {
                                    foreach ( $patients_list as $pat ) {
                                        $display_text = esc_html($pat->name . ' (#' . $pat->id . ')');
                                        echo '<div class="arms-patient-option" data-id="'.intval($pat->id).'" data-search="'.esc_attr(strtolower($display_text)).'" style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #0f172a; transition: background 0.1s;">'.$display_text.'</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="arms-form-grid">
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
                /* -------------------------------------------------------------
                   FLOATING PATIENT SEARCH DROPDOWN LOGIC
                   ------------------------------------------------------------- */
                var searchInput  = document.getElementById('arms_patient_search');
                var hiddenInput  = document.getElementById('patient_id');
                var dropdownList = document.getElementById('arms_patient_dropdown_list');
                
                if (searchInput && dropdownList) {
                    var options    = Array.from(dropdownList.querySelectorAll('.arms-patient-option'));
                    var emptyState = dropdownList.querySelector('.arms-patient-option-empty');

                    dropdownList.addEventListener('mouseover', function(e) {
                        if (e.target.classList.contains('arms-patient-option')) {
                            e.target.style.backgroundColor = '#6366f1';
                            e.target.style.color = '#ffffff';
                        }
                    });
                    dropdownList.addEventListener('mouseout', function(e) {
                        if (e.target.classList.contains('arms-patient-option')) {
                            e.target.style.backgroundColor = '#ffffff';
                            e.target.style.color = '#0f172a';
                        }
                    });

                    searchInput.addEventListener('focus', function() {
                        dropdownList.style.display = 'block';
                    });

                    searchInput.addEventListener('input', function() {
                        dropdownList.style.display = 'block';
                        var term = searchInput.value.toLowerCase().trim();
                        var matches = 0;

                        options.forEach(function(opt) {
                            var searchString = opt.getAttribute('data-search') || '';
                            if (searchString.includes(term)) {
                                opt.style.display = 'block';
                                matches++;
                            } else {
                                opt.style.display = 'none';
                            }
                        });

                        if (matches === 0 && emptyState) {
                            emptyState.style.display = 'block';
                        } else if (emptyState) {
                            emptyState.style.display = 'none';
                        }
                    });

                    dropdownList.addEventListener('click', function(e) {
                        if (e.target.classList.contains('arms-patient-option')) {
                            var pId = e.target.getAttribute('data-id');
                            if (pId !== null && pId !== "") {
                                hiddenInput.value = pId;
                                searchInput.value = e.target.textContent;
                                dropdownList.style.display = 'none';
                            }
                        }
                    });

                    document.addEventListener('click', function(e) {
                        if (!searchInput.contains(e.target) && !dropdownList.contains(e.target)) {
                            dropdownList.style.display = 'none';
                        }
                    });
                }

                /* -------------------------------------------------------------
                   MEDICATION REPEATER MANAGEMENT ENGINE
                   ------------------------------------------------------------- */
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
                                alert('Clinical Requirement: At least one medication logging entry row must be maintained inside the active grid.');
                            }
                        }
                    });
                }
            });
            </script>
        <?php 
        /* =========================================================================
            SUB-VIEW: LIST SHIFT LOGS DIRECTORY
           ========================================================================= */
        elseif ( $current_sub === 'list' ) : 
            // Corrected Mapping: SELECT p.name as explicitly specified in your schema setup
            $logs = $wpdb->get_results( "
                SELECT n.*, p.name 
                FROM $table_nursing n 
                LEFT JOIN $table_patients p ON n.patient_id = p.id 
                ORDER BY n.log_date DESC, n.id DESC
            " );
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color:#1e293b;">Active Care Sheets Tracking Index</h3>
                    <a href="<?php echo esc_url($add_url); ?>" class="arms-submit-btn" style="padding: 8px 16px; font-size:12px;">+ Open New Chart Entry</a>
                </div>
                
                <table class="arms-data-table">
                    <thead>
                        <tr>
                            <th>Date & Shift</th>
                            <th>Patient Identity File Reference</th>
                            <th>Allocated Unit Location</th>
                            <th>Vitals Status Summary</th>
                            <th style="text-align: right;">Clinical Document Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $log ) : 
                            $view_item_url = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=view&id=' . $log->id );
                            $edit_item_url = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=edit&id=' . $log->id );
                            $del_item_url  = wp_nonce_url( admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=list&action=delete&id=' . $log->id ), 'arms_delete_nursing_' . $log->id );
                            
                            $is_critical = ( $log->spo2_level < 93 || $log->bp_systolic > 150 || $log->bp_systolic < 90 ) ? 'vital-critical' : '';
                            ?>
                            <tr class="<?php echo esc_attr($is_critical); ?>">
                                <td>
                                    <strong><?php echo esc_html( date('d-M-Y', strtotime($log->log_date)) ); ?></strong><br>
                                    <span style="font-size:11px; color:#64748b;"><?php echo esc_html($log->shift_type); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($log->name ? $log->name : 'Unknown Record File'); ?></strong><br>
                                    <span style="font-size:11px; color:#64748b;">File Block ID: #<?php echo intval($log->patient_id); ?></span>
                                </td>
                                <td><?php echo esc_html($log->location_type); ?><br><span style="font-size:11px; color:#64748b;"><?php echo esc_html($log->bed_no); ?></span></td>
                                <td>
                                    <span style="font-size:12px;">
                                        BP: <strong><?php echo intval($log->bp_systolic); ?>/<?php echo intval($log->bp_diastolic); ?></strong> | 
                                        PR: <strong><?php echo intval($log->pulse_rate); ?> bpm</strong> | 
                                        SpO2: <strong><?php echo intval($log->spo2_level); ?>%</strong>
                                    </span>
                                </td>
                                <td>
                                    <div class="arms-action-btn-group">
                                        <a href="<?php echo esc_url($view_item_url); ?>" class="arms-action-btn btn-view">Review</a>
                                        <a href="<?php echo esc_url($edit_item_url); ?>" class="arms-action-btn btn-edit">Modify</a>
                                        <a href="<?php echo esc_url($del_item_url); ?>" class="arms-action-btn btn-delete" onclick="return confirm('Security Warning: Are you completely certain you want to purge this record entry?');">Drop</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 40px; color: #64748b;">No clinical care records registered inside the operational mapping context.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php 
        /* =========================================================================
            SUB-VIEW: INDIVIDUAL PATIENT SHEET COMPREHENSIVE VIEW
           ========================================================================= */
        elseif ( $current_sub === 'view' && $log_id > 0 ) :
            // Corrected mapping: pulling p.name directly for detailed report view
            $log = $wpdb->get_row( $wpdb->prepare( "
                SELECT n.*, p.name 
                FROM $table_nursing n 
                LEFT JOIN $table_patients p ON n.patient_id = p.id 
                WHERE n.id = %d
            ", $log_id ) );

            if ( $log ) :
                $meds = ! empty($log->medication_chart) ? json_decode($log->medication_chart, true) : array();
                ?>
                <div class="arms-card-box">
                    <div class="arms-card-header-flex" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
                        <div>
                            <h2 style="margin:0 0 4px 0; font-size:18px; color:#0f172a;">Patient Ward Level Sheet: <?php echo esc_html($log->name); ?></h2>
                            <p style="margin:0; font-size:12px; color:#64748b;">Tracking ID: #<?php echo intval($log->id); ?> &mdash; Observation Date: <?php echo esc_html($log->log_date); ?> (<?php echo esc_html($log->shift_type); ?>)</p>
                        </div>
                        <a href="<?php echo esc_url($list_url); ?>" class="arms-submit-btn" style="background:#475569;">Return to Directory</a>
                    </div>

                    <div style="margin-top: 24px;">
                        <h4 style="font-size:12px; text-transform:uppercase; color:#4f46e5; margin-bottom:12px;">Facility Allocation</h4>
                        <p style="font-size:14px; margin:0 0 24px 0;">Currently assigned to <strong><?php echo esc_html($log->location_type); ?></strong> inside <strong><?php echo esc_html($log->bed_no); ?></strong>.</p>
                    </div>

                    <h4 style="font-size:12px; text-transform:uppercase; color:#4f46e5; margin-bottom:12px;">Vital Signs Monitoring Panel</h4>
                    <div class="vital-badge-grid">
                        <div class="vital-indicator-card <?php if($log->bp_systolic > 140 || $log->bp_systolic < 90) echo 'vital-critical'; ?>">
                            <div class="v-title">Blood Pressure</div>
                            <div class="v-value"><?php echo intval($log->bp_systolic); ?>/<?php echo intval($log->bp_diastolic); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">mmHg</span></div>
                        </div>
                        <div class="vital-indicator-card <?php if($log->pulse_rate > 100 || $log->pulse_rate < 60) echo 'vital-critical'; ?>">
                            <div class="v-title">Pulse Rate</div>
                            <div class="v-value"><?php echo intval($log->pulse_rate); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">BPM</span></div>
                        </div>
                        <div class="vital-indicator-card <?php if($log->body_temp > 100.4 || $log->body_temp < 97) echo 'vital-critical'; ?>">
                            <div class="v-title">Body Temp</div>
                            <div class="v-value"><?php echo floatval($log->body_temp); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">°F</span></div>
                        </div>
                        <div class="vital-indicator-card <?php if($log->spo2_level < 94) echo 'vital-critical'; ?>">
                            <div class="v-title">Oxygen Saturation</div>
                            <div class="v-value"><?php echo intval($log->spo2_level); ?> <span style="font-size:11px; font-weight:500; color:#64748b;">%</span></div>
                        </div>
                    </div>

                    <h4 style="font-size:12px; text-transform:uppercase; color:#4f46e5; margin: 30px 0 12px 0;">Administered Medications Treatment Array</h4>
                    <table class="arms-repeater-table" style="margin-bottom:24px;">
                        <thead>
                            <tr>
                                <th>Medicine Generic / Brand Name</th>
                                <th>Dosage Metric</th>
                                <th>Route</th>
                                <th>Frequency</th>
                                <th>Target Administration Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($meds)) : foreach($meds as $m) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($m['name']); ?></strong></td>
                                    <td><?php echo esc_html($m['dose']); ?></td>
                                    <td><span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-weight:700; font-size:11px;"><?php echo esc_html($m['route']); ?></span></td>
                                    <td><?php echo esc_html($m['freq']); ?></td>
                                    <td><?php echo esc_html($m['time']); ?></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="5" style="color:#64748b; padding:12px; text-align:center;">No medications recorded on this tracking matrix shift.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:24px;">
                        <div>
                            <h4 style="font-size:12px; text-transform:uppercase; color:#4f46e5; margin-bottom:8px;">Head-to-Toe Progress Assessment Notes</h4>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; font-size:13px; line-height:1.5; white-space:pre-wrap; color:#334155;"><?php echo $log->nursing_notes ? esc_html($log->nursing_notes) : '<em>No diagnostic updates provided.</em>'; ?></div>
                        </div>
                        <div>
                            <h4 style="font-size:12px; text-transform:uppercase; color:#dc2626; margin-bottom:8px;">Critical Shift Handover Matrix Report</h4>
                            <div style="background:#fff5f5; border:1px solid #fecaca; border-radius:6px; padding:14px; font-size:13px; line-height:1.5; white-space:pre-wrap; color:#991b1b; font-weight:500;"><?php echo $log->shift_report ? esc_html($log->shift_report) : '<em>No strategic handover warning vectors mapped.</em>'; ?></div>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="notice notice-error"><p>Clinical Core Error: Targeted Daily care tracking identifier could not be validated.</p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}