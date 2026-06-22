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
    $table_patients = $wpdb->prefix . 'arms_patients';

    $current_sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';
    $log_id      = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    $list_url = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=list' );
    $add_url  = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=add' );

    /* =========================================================================
       ACTION ROUTER: DELETE CLINICAL LOG ENTRY
       ========================================================================= */
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $log_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'arms_delete_nursing_' . $log_id ) ) {
            $wpdb->delete( $table_nursing, array( 'id' => $log_id ), array( '%d' ) );
        }
    }

    /* =========================================================================
       POST ENGINE: HANDLING INSERTIONS / UPDATES
       ========================================================================= */
    if ( isset( $_POST['arms_save_nursing'] ) && check_admin_referer( 'arms_nursing_nonce_action', 'arms_nursing_nonce' ) ) {
        
        $patient_id    = isset( $_POST['patient_id'] ) ? intval( $_POST['patient_id'] ) : 0;
        $shift_type    = isset( $_POST['shift_type'] ) ? sanitize_text_field( wp_unslash( $_POST['shift_type'] ) ) : 'Morning';
        $location_type = isset( $_POST['location_type'] ) ? sanitize_text_field( wp_unslash( $_POST['location_type'] ) ) : 'General Ward';
        $bed_no        = isset( $_POST['bed_no'] ) ? sanitize_text_field( wp_unslash( $_POST['bed_no'] ) ) : '';
        $log_date      = ! empty( $_POST['log_date'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date'] ) ) : date('Y-m-d');

        // Parse 2-column Vitals tracking fields
        $vitals_bp     = isset( $_POST['vitals_bp'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['vitals_bp'])) : array();
        $vitals_pulse  = isset( $_POST['vitals_pulse'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['vitals_pulse'])) : array();
        $vitals_temp   = isset( $_POST['vitals_temp'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['vitals_temp'])) : array();
        $vitals_spo2   = isset( $_POST['vitals_spo2'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['vitals_spo2'])) : array();
        $service_times = isset( $_POST['service_time'] ) ? array_map('sanitize_text_field', wp_unslash($_POST['service_time'])) : array();

        $medication_chart_array = array();
        for ( $i = 0; $i < count( $vitals_bp ); $i++ ) {
            if ( ! empty( $vitals_bp[$i] ) || ! empty( $vitals_pulse[$i] ) ) {
                $medication_chart_array[] = array(
                    'bp'           => $vitals_bp[$i],
                    'pulse'        => isset($vitals_pulse[$i]) ? $vitals_pulse[$i] : '',
                    'temperature'  => isset($vitals_temp[$i]) ? $vitals_temp[$i] : '',
                    'oxygen_sat'   => isset($vitals_spo2[$i]) ? $vitals_spo2[$i] : '',
                    'service_time' => isset($service_times[$i]) ? $service_times[$i] : '',
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
                'medication_chart' => $medication_chart_json,
                'nursing_notes'    => '',
                'shift_report'     => '',
            );
            $format_array = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $wpdb->update( $table_nursing, $data_array, array( 'id' => $log_id ), $format_array, array( '%d' ) );
            } else {
                $data_array['created_at'] = current_time( 'mysql' );
                $format_array[] = '%s';
                $wpdb->insert( $table_nursing, $data_array, $format_array );
            }
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
        .arms-form-group input, .arms-form-group select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background-color: #fff; width: 100%; }
        .arms-form-group input:focus, .arms-form-group select:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1); }
        
        .arms-searchable-group { background: #fdfdfd; padding: 16px; border: 1px dashed #cbd5e1; border-radius: 8px; margin-bottom: 8px; }
        .arms-patient-search-input { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%2394a3b8" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px !important; font-weight: 500; }

        .arms-submit-btn { background: #6366f1; color: #fff; border: none; padding: 11px 22px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; text-decoration: none; display: inline-block; margin-top: 20px; }
        .arms-submit-btn:hover { background: #4f46e5; }
        
        .arms-repeater-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .arms-repeater-table th { background: #f1f5f9; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; border: 1px solid #e2e8f0; }
        .arms-repeater-table td { padding: 12px; border: 1px solid #e2e8f0; vertical-align: top; background: #fff; }
        .repeater-add-btn { background: #10b981; color:#fff; padding: 6px 12px; border-radius:4px; font-size:12px; border:none; cursor:pointer; font-weight:600; }
        .repeater-add-btn:hover { background: #059669; }
        .repeater-del-btn { background: #ef4444; color:#fff; border:none; padding: 8px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight: 600; width: 100%; margin-top: 10px; }
        
        .vitals-sub-flex { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .vitals-input-wrap { display: flex; flex-direction: column; gap: 4px; }
        .vitals-input-wrap label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }

        /* DataTables Custom Clean Overrides */
        .arms-data-table-container { margin-top: 15px; }
        table.dataTable.arms-data-table { width: 100% !important; border-collapse: collapse !important; border-bottom: 1px solid #cbd5e1 !important; margin-bottom: 15px !important; }
        table.dataTable.arms-data-table thead th { background: #f8fafc !important; padding: 12px 16px !important; font-size: 11px !important; font-weight: 600 !important; text-transform: uppercase !important; color: #64748b !important; border-bottom: 2px solid #e2e8f0 !important; }
        table.dataTable.arms-data-table tbody td { padding: 14px 16px !important; font-size: 13px !important; border-bottom: 1px solid #f1f5f9 !important; color: #334155 !important; vertical-align: top !important; background: transparent !important; }
        .dataTables_wrapper .dataTables_filter input { padding: 6px 12px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; background-color: #fff !important; margin-left: 8px !important; }
        .dataTables_wrapper .dataTables_length select { padding: 4px 8px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; }
        
        .arms-action-btn-group { display: flex; gap: 4px; justify-content: flex-end; }
        .arms-action-btn { padding: 5px 10px; font-size: 12px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; }
        .btn-edit { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-edit:hover { background:#dbeafe; } .btn-delete:hover { background:#fee2e2; }

        .arms-list-vitals-container { margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; font-size: 11px; }
        .arms-list-vitals-row { display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding: 4px 0; gap: 10px; }
        .arms-list-vitals-row:last-child { border-bottom: none; }
        .arms-list-vitals-metrics { color: #475569; }
        .arms-list-vitals-time { color: #6366f1; font-weight: 600; text-align: right; white-space: nowrap; }
    </style>

    <div class="arms-nurse-wrapper">
        <nav class="arms-subnav-bar">
            <a href="<?php echo esc_url( $list_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'list') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-clipboard" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> All Services
            </a>
            <a href="<?php echo esc_url( $add_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'add') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-welcome-write-blog" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Add Service
            </a>
        </nav>

        <?php 
        /* =========================================================================
           SUB-VIEW: ADD / EDIT LOG FILES
           ========================================================================= */
        if ( $current_sub === 'add' || $current_sub === 'edit' ) :
            $form_heading = "Initiate Shift Level ICU/Ward Care Entry";
            $row_data = null;
            $existing_vitals = array();

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_nursing WHERE id = %d", $log_id ) );
                $form_heading = "Edit Nursing Entry Configuration Matrix Log #ID: " . esc_html($log_id);
                if ( $row_data && ! empty($row_data->medication_chart) ) {
                    $existing_vitals = json_decode($row_data->medication_chart, true);
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
                            <input type="text" id="arms_patient_search" class="arms-patient-search-input" placeholder="Type name or Patient ID number here to filter results live..." value="<?php echo $selected_display; ?>" autocomplete="off" style="z-index: 2;">
                            
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

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #4f46e5; display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px;">
                        <span>Active Patient Vitals Tracking Grid</span>
                        <button type="button" class="repeater-add-btn" id="armsAddVitalsRow">+ Add Vitals Check Entry</button>
                    </h4>
                    
                    <table class="arms-repeater-table" id="armsVitalsRepeaterGrid">
                        <thead>
                            <tr>
                                <th>Patient Vitals Metrics</th>
                                <th style="width: 350px;">Service Day & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $existing_vitals ) ) : foreach ( $existing_vitals as $vital ) : ?>
                                <tr>
                                    <td>
                                        <div class="vitals-sub-flex">
                                            <div class="vitals-input-wrap">
                                                <label>BP (Blood Pressure)</label>
                                                <input type="text" name="vitals_bp[]" value="<?php echo esc_attr(isset($vital['bp']) ? $vital['bp'] : ''); ?>" placeholder="e.g. 120/80">
                                            </div>
                                            <div class="vitals-input-wrap">
                                                <label>Pulse (bpm)</label>
                                                <input type="text" name="vitals_pulse[]" value="<?php echo esc_attr(isset($vital['pulse']) ? $vital['pulse'] : ''); ?>" placeholder="e.g. 72">
                                            </div>
                                            <div class="vitals-input-wrap">
                                                <label>Temperature (°F)</label>
                                                <input type="text" name="vitals_temp[]" value="<?php echo esc_attr(isset($vital['temperature']) ? $vital['temperature'] : ''); ?>" placeholder="e.g. 98.6">
                                            </div>
                                            <div class="vitals-input-wrap">
                                                <label>Oxygen Saturation (%)</label>
                                                <input type="text" name="vitals_spo2[]" value="<?php echo esc_attr(isset($vital['oxygen_sat']) ? $vital['oxygen_sat'] : ''); ?>" placeholder="e.g. 98">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="datetime-local" name="service_time[]" value="<?php echo esc_attr(isset($vital['service_time']) ? $vital['service_time'] : ''); ?>">
                                        <button type="button" class="repeater-del-btn armsRemoveRow">Drop Entry Row</button>
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr>
                                    <td>
                                        <div class="vitals-sub-flex">
                                            <div class="vitals-input-wrap">
                                                <label>BP (Blood Pressure)</label>
                                                <input type="text" name="vitals_bp[]" placeholder="e.g. 120/80">
                                            </div>
                                            <div class="vitals-input-wrap">
                                                <label>Pulse (bpm)</label>
                                                <input type="text" name="vitals_pulse[]" placeholder="e.g. 72">
                                            </div>
                                            <div class="vitals-input-wrap">
                                                <label>Temperature (°F)</label>
                                                <input type="text" name="vitals_temp[]" placeholder="e.g. 98.6">
                                            </div>
                                            <div class="vitals-input-wrap">
                                                <label>Oxygen Saturation (%)</label>
                                                <input type="text" name="vitals_spo2[]" placeholder="e.g. 98">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="datetime-local" name="service_time[]">
                                        <button type="button" class="repeater-del-btn armsRemoveRow">Drop Entry Row</button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <button type="submit" name="arms_save_nursing" class="arms-submit-btn">
                        <span class="dashicons dashicons-database-add" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Commit Shift Record to Active Framework
                    </button>
                </form>
            </div>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var $searchInput = $('#arms_patient_search');
                var $dropdownList = $('#arms_patient_dropdown_list');
                var $hiddenInput = $('#patient_id');
                var $emptyMessage = $('.arms-patient-option-empty');

                $searchInput.on('focus input', function() {
                    var query = $(this).val().toLowerCase().trim();
                    if (query === '') { $hiddenInput.val('0'); }
                    $dropdownList.show();
                    var matchCount = 0;
                    $('.arms-patient-option').each(function() {
                        var searchMeta = $(this).data('search');
                        if (searchMeta.indexOf(query) > -1) {
                            $(this).show();
                            matchCount++;
                        } else {
                            $(this).hide();
                        }
                    });
                    if (matchCount === 0) { $emptyMessage.show(); } else { $emptyMessage.hide(); }
                });

                $(document).on('click', '.arms-patient-option', function(e) {
                    var selectedId = $(this).data('id');
                    var displayedText = $(this).text();
                    $hiddenInput.val(selectedId);
                    $searchInput.val(displayedText);
                    $dropdownList.hide();
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.arms-searchable-group').length) { $dropdownList.hide(); }
                });

                $('#armsAddVitalsRow').on('click', function(e) {
                    e.preventDefault();
                    var newRowHtml = '<tr>' +
                        '<td>' +
                            '<div class="vitals-sub-flex">' +
                                '<div class="vitals-input-wrap"><label>BP (Blood Pressure)</label><input type="text" name="vitals_bp[]" placeholder="e.g. 120/80"></div>' +
                                '<div class="vitals-input-wrap"><label>Pulse (bpm)</label><input type="text" name="vitals_pulse[]" placeholder="e.g. 72"></div>' +
                                '<div class="vitals-input-wrap"><label>Temperature (°F)</label><input type="text" name="vitals_temp[]" placeholder="e.g. 98.6"></div>' +
                                '<div class="vitals-input-wrap"><label>Oxygen Saturation (%)</label><input type="text" name="vitals_spo2[]" placeholder="e.g. 98"></div>' +
                            '</div>' +
                        '</td>' +
                        '<td>' +
                            '<input type="datetime-local" name="service_time[]">' +
                            '<button type="button" class="repeater-del-btn armsRemoveRow">Drop Entry Row</button>' +
                        '</td>' +
                    '</tr>';
                    $('#armsVitalsRepeaterGrid tbody').append(newRowHtml);
                });

                $(document).on('click', '.armsRemoveRow', function(e) {
                    e.preventDefault();
                    var totalRows = $('#armsVitalsRepeaterGrid tbody tr').length;
                    if (totalRows > 1) {
                        $(this).closest('tr').remove();
                    } else {
                        var $lastRow = $(this).closest('tr');
                        $lastRow.find('input[type="text"], input[type="datetime-local"]').val('');
                    }
                });
            });
            </script>

        <?php 
        /* =========================================================================
           SUB-VIEW: SHIFT CARE LOGS DIRECTORY
           ========================================================================= */
        else : 
            $sql = "SELECT n.*, p.name as patient_name 
                    FROM $table_nursing n 
                    LEFT JOIN $table_patients p ON n.patient_id = p.id 
                    ORDER BY n.log_date DESC, n.id DESC LIMIT 300";
            $logs = $wpdb->get_results($sql);
            ?>
            <div class="arms-card-box">

                <div class="arms-data-table-container">
                    <table class="arms-data-table" id="armsNursingDataTable">
                        <thead>
                            <tr>
                                <th style="width:70px;">ID Log</th>
                                <th style="width:110px;">Date</th>
                                <th>Patient File & Service by Name</th>
                                <th style="width:120px;">Shift Range</th>
                                <th style="width:150px;">Allocated Facility</th>
                                <th style="width:180px; text-align:right;">Control Matrix Links</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $logs ) ) : foreach ( $logs as $l ) : 
                                $edit_entry_url = admin_url( 'admin.php?page=rehab_management_system&tab=nursing&sub=edit&id=' . $l->id );
                                $del_nonce_url  = wp_nonce_url( admin_url( 'admin.php?page=rehab_management_system&tab=nursing&action=delete&id=' . $l->id ), 'arms_delete_nursing_' . $l->id );
                                ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html($l->id); ?></strong></td>
                                    <td><?php echo esc_html(date('Y-m-d', strtotime($l->log_date))); ?></td>
                                    <td>
                                        <span class="dashicons dashicons-id-alt" style="font-size:15px; vertical-align:middle; color:#64748b;"></span>
                                        <strong><?php echo $l->patient_name ? esc_html($l->patient_name) : '<span style="color:#ef4444; font-style:italic;">Unassigned/Orphan Record</span>'; ?></strong>
                                        <div style="font-size:11px; color:#64748b; margin-top:2px;">Mapped Patient Reference ID: #<?php echo intval($l->patient_id); ?></div>
                                        
                                        <?php 
                                        if ( ! empty( $l->medication_chart ) ) {
                                            $vitals_list = json_decode( $l->medication_chart, true );
                                            if ( ! empty( $vitals_list ) && is_array( $vitals_list ) ) {
                                                echo '<div class="arms-list-vitals-container">';
                                                foreach ( $vitals_list as $index => $v_row ) {
                                                    $metrics = array();
                                                    if(!empty($v_row['bp']))   $metrics[] = "BP: " . esc_html($v_row['bp']);
                                                    if(!empty($v_row['pulse']))$metrics[] = "P: " . esc_html($v_row['pulse']);
                                                    if(!empty($v_row['temperature'])) $metrics[] = "T: " . esc_html($v_row['temperature']);
                                                    if(!empty($v_row['oxygen_sat']))  $metrics[] = "O₂: " . esc_html($v_row['oxygen_sat']);
                                                    
                                                    $time_display = !empty($v_row['service_time']) ? esc_html(date('Y-m-d H:i', strtotime($v_row['service_time']))) : 'N/A';
                                                    
                                                    echo '<div class="arms-list-vitals-row">';
                                                    echo '<span class="arms-list-vitals-metrics"><strong>Entry #' . ($index + 1) . ':</strong> ' . implode(' | ', $metrics) . '</span>';
                                                    echo '<span class="arms-list-vitals-time">' . $time_display . '</span>';
                                                    echo '</div>';
                                                }
                                                echo '</div>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><span class="dashicons dashicons-clock" style="font-size:14px; vertical-align:middle; color:#a4b5cf;"></span> <?php echo esc_html($l->shift_type); ?></td>
                                    <td>
                                        <span style="display:block; font-weight:500; font-size:12px; color:#334155;"><?php echo esc_html($l->location_type); ?></span>
                                        <span style="font-size:11px; color:#64748b;"><?php echo $l->bed_no ? esc_html($l->bed_no) : 'No Bed Specified'; ?></span>
                                    </td>
                                    <td>
                                        <div class="arms-action-btn-group">
                                            <a href="<?php echo $edit_entry_url; ?>" class="arms-action-btn btn-edit">Edit Log</a>
                                            <a href="<?php echo $del_nonce_url; ?>" class="arms-action-btn btn-delete" onclick="return confirm('Purge this record?');">Drop Log</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                if ($.fn.DataTable) {
                    $('#armsNursingDataTable').DataTable({
                        "order": [[0, "desc"]],
                        "pageLength": 25,
                        "language": {
                            "search": "Filter Records:",
                            "emptyTable": "No care tracking entries discovered inside the operational database environment."
                        },
                        "columnDefs": [
                            { "orderable": false, "targets": [2, 5] }
                        ]
                    });
                }
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}