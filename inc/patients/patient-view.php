<?php
if(!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
# View Item - Clinical Case File & Patient Profile Dashboard
--------------------------------------------------------------*/
function arms_view_patient_profile($item_id) {
    $item_id = intval($item_id);
    if ($item_id <= 0) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Invalid Patient Case File requested.', 'arms-textdomain') . '</p></div>';
        return;
    }

    global $wpdb;
    $table_patients   = $wpdb->prefix . 'arms_patients';
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_physio     = $wpdb->prefix . 'arms_physio_logs'; 
    $table_nursing    = $wpdb->prefix . 'arms_nursing_logs';

    // Fetch patient metadata securely
    $patient_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_patients WHERE id = %d", $item_id), 
        ARRAY_A
    );

    if (!$patient_row) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Patient record not found.', 'arms-textdomain') . '</p></div>';
        return;
    }

    // Fetch data from the latest admission record
    $admission_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_admissions WHERE patient_id = %d ORDER BY id DESC LIMIT 1", $item_id),
        ARRAY_A
    );

    /*--------------------------------------------------------------
    # FETCH PHYSIOTHERAPY DATA FROM ARMS_PHYSIO_LOGS
    --------------------------------------------------------------*/
    $physio_log = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_physio WHERE patient_id = %d ORDER BY id DESC LIMIT 1", $item_id),
        ARRAY_A
    );

    /*--------------------------------------------------------------
    # FETCH ALL NURSING LOG DATA FOR THIS PATIENT
    --------------------------------------------------------------*/
    $nursing_logs = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_nursing WHERE patient_id = %d ORDER BY log_date DESC, id DESC", $item_id),
        ARRAY_A
    );

    // Fetch Global Pricing Framework Settings
    $global_settings = get_option('arms_global_settings', array());
    $currency        = isset($global_settings['currency_symbol']) ? $global_settings['currency_symbol'] : '৳';
    $tax_rate        = floatval($global_settings['tax_rate'] ?? 0);

    // Dynamic extraction or fallback mapping for Admission Table Elements
    $room_key          = !empty($admission_row['room_type']) ? sanitize_key($admission_row['room_type']) : 'none';
    $room_type         = !empty($admission_row['room_type']) ? ucwords(str_replace('_', ' ', $admission_row['room_type'])) : 'Not Assigned';
    $assigned_doctor   = !empty($admission_row['assigned_doctor']) ? $admission_row['assigned_doctor'] : 'Unassigned';
    $duration_days     = isset($admission_row['duration_days']) ? intval($admission_row['duration_days']) : 0;
    
    // Other modules
    $acupuncture_count = isset($admission_row['acupuncture_sessions']) ? intval($admission_row['acupuncture_sessions']) : 0;
    $prp_count         = isset($admission_row['prp_sessions']) ? intval($admission_row['prp_sessions']) : 0;

    /*--------------------------------------------------------------
    # COMPUTE LIVE METRICS FROM DYNAMIC PHYSIO SCHEMA & PARSE REPEATER LOGS
    --------------------------------------------------------------*/
    $parsed_repeater_sessions = array();
    $clean_progress_notes     = '';

    if (!empty($physio_log)) {
        $physio_sessions    = intval($physio_log['sessions_completed']);
        $sessions_remaining = intval($physio_log['sessions_remaining']);
        $advance_payment    = floatval($physio_log['advance_bill']);
        $rate_physio        = floatval($physio_log['per_session_bill']);

        // Parse custom progress notes with delimiter: REPEATER_DATA:[...]|||Text Notes
        $raw_notes = $physio_log['progress_notes'];
        if (strpos($raw_notes, '|||') !== false) {
            $parts = explode('|||', $raw_notes, 2);
            $repeater_part = trim($parts[0]);
            $clean_progress_notes = trim($parts[1]);

            if (strpos($repeater_part, 'REPEATER_DATA:') !== false) {
                $json_str = str_replace('REPEATER_DATA:', '', $repeater_part);
                $decoded_sessions = json_decode($json_str, true);
                if (is_array($decoded_sessions)) {
                    $parsed_repeater_sessions = $decoded_sessions;
                }
            }
        } else {
            $clean_progress_notes = $raw_notes;
        }
    } else {
        $physio_sessions    = 0;
        $sessions_remaining = 0;
        $advance_payment    = isset($admission_row['advance_payment']) ? floatval($admission_row['advance_payment']) : 0;
        $rate_physio        = isset($global_settings['fee_physio']) ? floatval($global_settings['fee_physio']) : 0;
    }

    /*--------------------------------------------------------------
    # CALCULATE LIVE ADMISSION CHARGES DYNAMICALLY
    --------------------------------------------------------------*/
    $base_room_rate = 0;
    if ($room_key === 'cabin' || $room_key === 'luxury_private_cabin') {
        $base_room_rate = floatval($global_settings['room_rent_cabin'] ?? 0);
    } elseif ($room_key === 'semi_private' || $room_key === 'semi_private_shared') {
        $base_room_rate = floatval($global_settings['room_rent_semi_private'] ?? 0);
    } elseif ($room_key === 'ward' || $room_key === 'general_ward') {
        $base_room_rate = floatval($global_settings['room_rent_ward'] ?? 0);
    }
    $total_room_charge = $base_room_rate * $duration_days;

    $rate_doctor       = floatval($global_settings['fee_doctor'] ?? 0);
    $rate_nursing      = floatval($global_settings['fee_nursing'] ?? 0);
    $total_doctor_fee  = $rate_doctor * $duration_days;
    $total_nursing_fee = $rate_nursing * $duration_days;

    $rate_acupuncture  = floatval($global_settings['fee_acupuncture'] ?? 0);
    $rate_prp          = floatval($global_settings['fee_prp'] ?? 0);
    
    $total_physio_fee  = $rate_physio * $physio_sessions;
    $total_acup_fee    = $rate_acupuncture * $acupuncture_count;
    $total_prp_fee     = $rate_prp * $prp_count;

    $gross_subtotal   = $total_room_charge + $total_doctor_fee + $total_nursing_fee + $total_physio_fee + $total_acup_fee + $total_prp_fee;
    $surcharge_amount = ($gross_subtotal * $tax_rate) / 100;
    $calculated_total = $gross_subtotal + $surcharge_amount;
    $net_balance_due  = $calculated_total - $advance_payment;

    $raw_conditions = [];
    if (!empty($patient_row['conditions'])) {
        $decoded = json_decode($patient_row['conditions'], true);
        $raw_conditions = is_array($decoded) ? $decoded : array_map('trim', explode(',', $patient_row['conditions']));
    }

    $vault = [ 'profile_photo' => '', 'mri' => [], 'xray' => [], 'ct' => [], 'lab' => [] ];
    if ( ! empty( $patient_row['media_vault_urls'] ) ) {
        $decoded_vault = json_decode( $patient_row['media_vault_urls'], true );
        if ( is_array( $decoded_vault ) ) {
            $vault = array_merge( $vault, $decoded_vault );
        } else if ( is_string( $patient_row['media_vault_urls'] ) ) {
            $vault['profile_photo'] = $patient_row['media_vault_urls'];
        }
    }

    $patient = array(
        'id'                      => intval($patient_row['id']),
        'name'                    => $patient_row['name'],
        'age'                     => intval($patient_row['age']),
        'gender'                  => $patient_row['gender'],
        'mobile'                  => $patient_row['mobile'],
        'emergency_contact_name'  => $patient_row['emergency_contact_name'],
        'emergency_contact_phone' => $patient_row['emergency_contact_phone'],
        'address'                 => $patient_row['address'],
        'admission_date'          => !empty($admission_row['admission_date']) ? $admission_row['admission_date'] : $patient_row['admission_date'],
        'initial_diagnosis'       => $patient_row['initial_diagnosis'],
        'profile_photo'           => $vault['profile_photo'],
        'attachments'             => [
            'MRI Scans'  => $vault['mri'],
            'X-Ray Film' => $vault['xray'],
            'CT Scans'   => $vault['ct'],
            'Lab Reports'=> $vault['lab']
        ],
        'conditions'              => $raw_conditions
    );

    $conditions_map = [
        'stroke'          => 'Stroke',
        'paralysis'       => 'Paralysis',
        'plid'            => 'PLID',
        'sci'             => 'SCI',
        'osteoarthritis'  => 'Osteoarthritis'
    ];
    ?>
    <style>
        .arms-p-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1350px; margin: 24px auto; color: #1e293b; padding: 0 16px; }
        .arms-p-header { display: flex; justify-content: space-between; align-items: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .arms-p-identity { display: flex; align-items: center; gap: 18px; }
        .arms-p-img { width: 68px; height: 68px; border-radius: 50%; object-fit: cover; border: 3px solid #f1f5f9; box-shadow: 0 0 0 1px #cbd5e1; }
        .arms-p-placeholder { width: 68px; height: 68px; border-radius: 50%; background: #f8fafc; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
        .arms-p-meta h2 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
        .arms-p-meta-chips { display: flex; gap: 12px; font-size: 13px; color: #64748b; align-items: center; }
        .arms-p-chip { background: #f1f5f9; color: #334155; padding: 2px 8px; border-radius: 6px; font-weight: 600; font-size: 11px; }
        .arms-btn-group { display: flex; gap: 10px; }
        .arms-btn-ui { padding: 10px 18px; font-size: 13px; font-weight: 600; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent; text-decoration: none; transition: all 0.2s ease; }
        .btn-ui-primary { background: #003376; color: #ffffff; }
        .btn-ui-primary:hover { background: #0f172a; }
        
        /* Interactive Nav Tabs Styling */
        .arms-tabs-nav { display: flex; gap: 8px; background: #f1f5f9; padding: 6px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .arms-tab-btn { background: transparent; border: none; padding: 10px 20px; font-size: 14px; font-weight: 600; color: #64748b; border-radius: 8px; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 8px; }
        .arms-tab-btn:hover { color: #0f172a; background: rgba(255,255,255,0.5); }
        .arms-tab-btn.is-active { background: #ffffff; color: #003376; box-shadow: 0 2px 4px rgba(0,0,0,0.04), 0 1px 1px rgba(0,0,0,0.02); }
        .arms-tab-content { display: none; }
        .arms-tab-content.is-active { display: block; }

        .arms-main-grid { display: grid; grid-template-columns: 4fr 8fr; gap: 24px; margin-bottom: 24px; }
        .arms-card-panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: 100%; box-sizing: border-box; margin-bottom: 24px; }
        .arms-card-panel:last-child { margin-bottom: 0; }
        .panel-title { font-size: 14px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 20px 0; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }
        .arms-list-strip { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px; }
        .arms-list-strip li { display: flex; justify-content: space-between; align-items: flex-start; font-size: 13.5px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 10px; }
        .arms-list-strip li:last-child { border-bottom: none; padding-bottom: 0; }
        .arms-list-strip .strip-lbl { color: #64748b; font-weight: 500; }
        .arms-list-strip .strip-val { color: #0f172a; font-weight: 600; text-align: right; max-width: 65%; }
        .arms-badge-deck { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .arms-badge-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f8fafc; color: #64748b; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 4px; }
        .arms-badge-tag.is-active { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
        .arms-narrative-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; font-size: 13.5px; line-height: 1.6; color: #334155; margin-bottom: 20px; }
        .arms-doc-section { border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 15px; }
        .arms-doc-cat-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #475569; margin: 12px 0 6px 0; }
        .arms-doc-links-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .arms-table-responsive { width: 100%; overflow-x: auto; margin-top: 12px; }
        .arms-pricing-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        .arms-pricing-table th, .arms-pricing-table td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        .arms-pricing-table th { background: #f8fafc; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .arms-pricing-table tr:hover td { background: #fdfdfd; }
        .arms-pricing-table td.fee-val { font-weight: 700; color: #0f172a; text-align: right; }
        .arms-pricing-table .tax-highlight td { background: #faf5ff; color: #6b21a8; font-weight: 700; border-bottom: 2px dashed #d8b4fe; }
        .arms-pricing-table .total-highlight td { background: #f0fdf4; color: #166534; font-weight: 800; border-top: 2px solid #bbf7d0; font-size: 14px; }
        
        .arms-session-item-row { display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 6px; font-size: 12.5px; }
        .arms-session-item-date { font-weight: 700; color: #003376; }
        .arms-session-item-meta { color: #64748b; font-weight: 500; }

        .arms-nurse-row-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        .arms-nurse-row-card:last-child { margin-bottom: 0; }
        .arms-nurse-meta-stripe { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px; align-items: center; }
        .arms-nurse-vitals-container { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 14px; }
        .arms-vital-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; text-align: center; }
        .arms-vital-box .v-lbl { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .arms-vital-box .v-val { font-size: 14px; font-weight: 700; color: #0f172a; }

        @media(max-width: 1024px) { 
            .arms-main-grid { grid-template-columns: 1fr; } 
            .arms-nurse-vitals-container { grid-template-columns: repeat(2, 1fr); }
        }
    </style>

    <div class="arms-p-container">
        
        <!-- HEADER BLOCK -->
        <div class="arms-p-header">
            <div class="arms-p-identity">
                <?php if ( ! empty($patient['profile_photo']) ) : ?>
                    <img src="<?php echo esc_url($patient['profile_photo']); ?>" class="arms-p-img" alt="Patient File Photo">
                <?php else : ?>
                    <div class="arms-p-placeholder">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                <?php endif; ?>
                
                <div class="arms-p-meta">
                    <h2><?php echo esc_html($patient['name']); ?></h2>
                    <div class="arms-p-meta-chips">
                        <span><strong>Age:</strong> <?php echo esc_html($patient['age']); ?> Years</span>
                        <span>•</span>
                        <span><strong>Gender:</strong> <?php echo esc_html($patient['gender']); ?></span>
                        <span class="arms-p-chip">FILE: #<?php echo esc_html(str_pad($patient['id'], 5, '0', STR_PAD_LEFT)); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="arms-btn-group">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rehab_management_system&tab=patients&id=' . $patient['id'] . '&action=edit')); ?>" class="arms-btn-ui btn-ui-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Modify Record
                </a>
            </div>
        </div>

        <!-- TABS NAV BAR ELEMENTS -->
        <div class="arms-tabs-nav">
            <button class="arms-tab-btn is-active" onclick="armsSwitchTab(event, 'tab-general')">👤 General Profile</button>
            <button class="arms-tab-btn" onclick="armsSwitchTab(event, 'tab-admission')">🏨 Admission & Costs</button>
            <button class="arms-tab-btn" onclick="armsSwitchTab(event, 'tab-physio')">⚡ Physiotherapy Log</button>
            <button class="arms-tab-btn" onclick="armsSwitchTab(event, 'tab-nursing')">🩺 Nursing Vitals</button>
        </div>

        <!-- TAB 1: GENERAL PROFILE -->
        <div id="tab-general" class="arms-tab-content is-active">
            <div class="arms-main-grid">
                <div class="arms-card-panel">
                    <h3 class="panel-title">Patient Demographics</h3>
                    <ul class="arms-list-strip">
                        <li><span class="strip-lbl">Primary Mobile</span><span class="strip-val"><?php echo esc_html($patient['mobile']); ?></span></li>
                        <li><span class="strip-lbl">Residential Address</span><span class="strip-val"><?php echo esc_html($patient['address']); ?></span></li>
                        <li><span class="strip-lbl">Emergency Contact</span><span class="strip-val"><?php echo esc_html($patient['emergency_contact_name']); ?></span></li>
                        <li><span class="strip-lbl">Emergency Line</span><span class="strip-val"><?php echo esc_html(!empty($patient['emergency_contact_phone']) ? $patient['emergency_contact_phone'] : '—'); ?></span></li>
                    </ul>
                </div>
                <div class="arms-card-panel">
                    <h3 class="panel-title">Initial Case File Clinical Attachments</h3>
                    <h4 style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.5px;">Initial Case Summary Narration</h4>
                    <div class="arms-narrative-box">
                        <?php echo !empty($patient['initial_diagnosis']) ? nl2br(esc_html($patient['initial_diagnosis'])) : '<em>No descriptive case file annotations logged on system entry.</em>'; ?>
                    </div>
                    
                    <div class="arms-doc-section">
                        <h4 style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin: 0 0 10px 0; letter-spacing: 0.5px; display:flex; align-items:center; gap:5px;">📋 Clinical Vault Uploads</h4>
                        <?php 
                        $has_files = false;
                        foreach ( $patient['attachments'] as $label => $files_array ) {
                            if ( ! empty( $files_array ) && is_array( $files_array ) ) {
                                $has_files = true;
                                echo '<div class="arms-doc-cat-title" style="margin-top: 16px; margin-bottom: 8px;">' . esc_html( $label ) . '</div>';
                                echo '<div class="arms-doc-links-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px;">';
                                foreach ( $files_array as $index => $file_url ) {
                                    $file_num = $index + 1;
                                    $extension = strtolower(pathinfo(parse_url($file_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                                    $is_image  = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                                    echo '<div class="arms-vault-preview-card" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #f8fafc; text-align: center; position: relative;">';
                                        if ( $is_image ) {
                                            echo '<img src="' . esc_url( $file_url ) . '" alt="' . esc_attr($label) . ' #' . $file_num . '" style="width: 100%; height: 90px; object-fit: cover; display: block; background: #000;">';
                                        } else {
                                            echo '<div style="height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; background: #f1f5f9;">';
                                                echo '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
                                                echo '<span style="font-size: 10px; font-weight: 700; margin-top: 4px; text-transform: uppercase;">' . esc_html($extension ? $extension : 'FILE') . '</span>';
                                            echo '</div>';
                                        }
                                        echo '<div style="font-size: 10px; font-weight: 600; padding: 4px 2px; border-top: 1px solid #e2e8f0; background: #ffffff; color: #475569;">#' . $file_num . '</div>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            }
                        }
                        if ( ! $has_files ) {
                            echo '<p class="description" style="font-style: italic; color:#94a3b8;">No medical diagnostic sheets attached to profile history.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ADMISSION & COSTS -->
<div id="tab-admission" class="arms-tab-content">
    <div class="arms-main-grid" style="grid-template-columns: 1fr;">
        <div class="arms-card-panel">
            <h3 class="panel-title">Active Admission Details</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
                <div style="background:#f8fafc; padding:14px; border:1px solid #e2e8f0; border-radius:10px;">
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600;">Intake Date</span>
                    <strong style="display:block; font-size:14px; color:#0f172a; margin-top:4px;"><?php echo esc_html(!empty($patient['admission_date']) ? date_i18n(get_option('date_format'), strtotime($patient['admission_date'])) : '—'); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:14px; border:1px solid #e2e8f0; border-radius:10px;">
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600;">Assigned Space</span>
                    <strong style="display:block; font-size:14px; color:#003376; margin-top:4px;"><?php echo esc_html($room_type); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:14px; border:1px solid #e2e8f0; border-radius:10px;">
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600;">Consultant Doctor</span>
                    <strong style="display:block; font-size:14px; color:#0f172a; margin-top:4px;"><?php echo esc_html($assigned_doctor); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:14px; border:1px solid #e2e8f0; border-radius:10px;">
                    <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600;">Treatment Duration</span>
                    <strong style="display:block; font-size:14px; color:#0f172a; margin-top:4px;"><?php echo esc_html($duration_days); ?> Days</strong>
                </div>
            </div>

            <h3 class="panel-title" style="color: #dc2626; border-top: 1px solid #f1f5f9; padding-top: 16px;">Active Admission Cost Itemization Breakdown</h3>
            <div class="arms-table-responsive">
                <table class="arms-pricing-table">
                    <thead>
                        <tr>
                            <th>Service Item Designation</th>
                            <th>Daily / Unit Rate</th>
                            <th>Quantity / Units</th>
                            <th style="text-align: right;">Total Charge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Accommodation Rent (<?php echo esc_html($room_type); ?>)</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($base_room_rate, 2); ?> <span style="font-size:10px; color:#64748b; font-weight:normal;">/ Day</span></td>
                            <td><?php echo $duration_days; ?> Days</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_room_charge, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Attending Physician Rounds</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_doctor, 2); ?> <span style="font-size:10px; color:#64748b; font-weight:normal;">/ Day</span></td>
                            <td><?php echo $duration_days; ?> Days</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_doctor_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Daily Nursing Care Services</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_nursing, 2); ?> <span style="font-size:10px; color:#64748b; font-weight:normal;">/ Day</span></td>
                            <td><?php echo $duration_days; ?> Days</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_nursing_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Physiotherapy Care Allocations</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_physio, 2); ?> <span style="font-size:10px; color:#64748b; font-weight:normal;">/ Run</span></td>
                            <td><?php echo $physio_sessions; ?> Sessions completed</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_physio_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Acupuncture Therapy Sessions</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_acupuncture, 2); ?> <span style="font-size:10px; color:#64748b; font-weight:normal;">/ Run</span></td>
                            <td><?php echo $acupuncture_count; ?> Sessions</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_acup_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>PRP Treatment Clinical Runs</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_prp, 2); ?> <span style="font-size:10px; color:#64748b; font-weight:normal;">/ Run</span></td>
                            <td><?php echo $prp_count; ?> Runs</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_prp_fee, 2); ?></td>
                        </tr>
                        <tr style="border-top: 2px solid #e2e8f0;">
                            <td colspan="3" style="text-align: right; font-weight: 600; color: #475569;">Gross Subtotal Amount:</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($gross_subtotal, 2); ?></td>
                        </tr>
                        <tr class="tax-highlight">
                            <td colspan="3" style="text-align: right; font-weight: 600;">Unified Billing Surcharge (<?php echo number_format($tax_rate, 2); ?>%):</td>
                            <td style="text-align: right;"><?php echo esc_html($currency) . ' ' . number_format($surcharge_amount, 2); ?></td>
                        </tr>
                        <tr class="total-highlight">
                            <td colspan="3" style="text-align: right;">Total Gross Account Accumulation:</td>
                            <td style="text-align: right;"><?php echo esc_html($currency) . ' ' . number_format($calculated_total, 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight: 600; color: #16a34a;">Less: Advance Monies Deposited:</td>
                            <td class="fee-val" style="color: #16a34a;">- <?php echo esc_html($currency) . ' ' . number_format($advance_payment, 2); ?></td>
                        </tr>
                        <tr style="background: #fff5f5; font-size: 14px; font-weight: 700; color: #991b1b;">
                            <td colspan="3" style="text-align: right; border-top: 1px solid #fecaca;">Adjusted Net Balance Due:</td>
                            <td style="text-align: right; border-top: 1px solid #fecaca;"><?php echo esc_html($currency) . ' ' . number_format($net_balance_due, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

        <!-- TAB 3: PHYSIOTHERAPY LOG -->
        <div id="tab-physio" class="arms-tab-content">
            <div class="arms-main-grid" style="grid-template-columns: 1fr;">
                <div class="arms-card-panel">
                    <h3 class="panel-title" style="color: #003376;">Physiotherapy Rehabilitation Clinical Summary Ledger</h3>
                    
                    <div class="arms-badge-deck">
                        <?php 
                        foreach ($conditions_map as $key => $label) {
                            $is_active = is_array($patient['conditions']) && in_array($key, $patient['conditions']);
                            $class = $is_active ? 'is-active' : '';
                            echo '<span class="arms-badge-tag ' . $class . '">' . esc_html($label) . '</span>';
                        }
                        ?>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">
                        <div style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 10px; padding: 10px; text-align: center;">
                            <span style="font-size: 10px; text-transform: uppercase; color: #0369a1; font-weight:700; display:block; margin-bottom:4px;">Physio Done</span>
                            <strong style="font-size: 16px; color: #0369a1;"><?php echo $physio_sessions; ?></strong>
                        </div>
                        <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; padding: 10px; text-align: center;">
                            <span style="font-size: 10px; text-transform: uppercase; color: #b45309; font-weight:700; display:block; margin-bottom:4px;">Remaining</span>
                            <strong style="font-size: 16px; color: #b45309;"><?php echo $sessions_remaining; ?></strong>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; text-align: center;">
                            <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight:600; display:block; margin-bottom:4px;">Acupunct.</span>
                            <strong style="font-size: 16px; color: #0f172a;"><?php echo $acupuncture_count; ?></strong>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; text-align: center;">
                            <span style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight:600; display:block; margin-bottom:4px;">PRP Runs</span>
                            <strong style="font-size: 16px; color: #0f172a;"><?php echo $prp_count; ?></strong>
                        </div>
                    </div>

                    <?php if(!empty($physio_log)): ?>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                            <div>
                                <span class="strip-lbl" style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.5px;">Initial Evaluation Baseline</span>
                                <div class="arms-narrative-box" style="margin-top:6px; min-height: 70px; background: #fafafa;"><?php echo nl2br(esc_html($physio_log['initial_assessment'])); ?></div>
                            </div>
                            <div>
                                <span class="strip-lbl" style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.5px;">Target Milestones & Goals</span>
                                <div class="arms-narrative-box" style="margin-top:6px; min-height: 70px; background: #fafafa;"><?php echo nl2br(esc_html($physio_log['rehab_goals'])); ?></div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 10px;">
                            <div>
                                <span class="strip-lbl" style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.5px;">Daily Operational Plan</span>
                                <div class="arms-narrative-box" style="margin-top:6px; min-height: 70px; background: #fafafa;"><?php echo nl2br(esc_html($physio_log['daily_plan'])); ?></div>
                            </div>
                            <div>
                                <span class="strip-lbl" style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.5px;">Progress Logs & Session Trackers</span>
                                <div class="arms-narrative-box" style="margin-top:6px; min-height: 70px; background: #fafafa;">
                                    <?php if (!empty($parsed_repeater_sessions)): ?>
                                        <div style="margin-bottom: 12px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                                            <h5 style="margin: 0 0 6px 0; font-size: 11px; text-transform: uppercase; color: #003376;">Individual Attended Runs:</h5>
                                            <?php foreach ($parsed_repeater_sessions as $session): ?>
                                                <div class="arms-session-item-row">
                                                    <div>
                                                        <span class="arms-session-item-date"><?php echo esc_html($session['date']); ?></span> — 
                                                        <span style="color:#0f172a; font-weight:600;"><?php echo esc_html($session['name']); ?></span>
                                                    </div>
                                                    <div class="arms-session-item-meta">
                                                        Qty: <strong><?php echo intval($session['count']); ?></strong> | <?php echo esc_html($currency) . ' ' . number_format($session['fee'], 2); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size:13px; color:#334155; line-height:1.5;">
                                        <?php echo !empty($clean_progress_notes) ? nl2br(esc_html($clean_progress_notes)) : '<em>No session adjustments noted.</em>'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="font-style:italic; font-size:13px; color:#94a3b8; margin:0; padding:10px 0;">No active core Physiotherapy Rehabilitation records found inside `arms_physio_logs` for this patient profile.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 4: NURSING LOG -->
        <div id="tab-nursing" class="arms-tab-content">
            <div class="arms-main-grid" style="grid-template-columns: 1fr;">
                <div class="arms-card-panel">
                    <h3 class="panel-title" style="color: #0d9488;">Clinical Nursing Observation Timeline & Vitals Ledger (<?php echo count($nursing_logs); ?> Entries)</h3>
                    
                    <?php if (!empty($nursing_logs)): ?>
                        <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach ($nursing_logs as $log): 
                                $systolic  = intval($log['bp_systolic']);
                                $diastolic = intval($log['bp_diastolic']);
                                $pulse     = intval($log['pulse_rate']);
                                $spo2      = intval($log['spo2_level']);
                                $temp      = esc_html($log['body_temp']);
                                $med_notes = $log['medication_chart'];

                                if (strpos($log['medication_chart'], '[{') !== false) {
                                    $decoded_vitals = json_decode($log['medication_chart'], true);
                                    if (is_array($decoded_vitals) && !empty($decoded_vitals[0])) {
                                        $v = $decoded_vitals[0];
                                        if (($systolic === 0 || $diastolic === 0) && !empty($v['bp'])) {
                                            $bp_parts = explode('/', $v['bp']);
                                            $systolic  = isset($bp_parts[0]) ? intval($bp_parts[0]) : $systolic;
                                            $diastolic = isset($bp_parts[1]) ? intval($bp_parts[1]) : $diastolic;
                                        }
                                        if ($pulse === 0 && !empty($v['pulse']))          $pulse = intval($v['pulse']);
                                        if ($spo2 === 0 && !empty($v['oxygen_sat']))      $spo2  = intval($v['oxygen_sat']);
                                        if (($temp == '0.00' || $temp == 0) && !empty($v['temperature'])) $temp = esc_html($v['temperature']);
                                        
                                        if (isset($v['service_time'])) {
                                            $med_notes = "Vitals logged electronically via system at " . date_i18n('g:i A', strtotime($v['service_time']));
                                        }
                                    }
                                }
                                ?>
                                <div class="arms-nurse-row-card">
                                    <div class="arms-nurse-meta-stripe">
                                        <span style="font-size: 14px; font-weight: 700; color: #0f172a;">
                                            📅 <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($log['log_date']))); ?>
                                        </span>
                                        <span class="arms-p-chip" style="background:#ccfbf1; color:#115e59; font-weight:700;">🌅 <?php echo esc_html($log['shift_type']); ?> Shift</span>
                                        <span class="arms-p-chip" style="background:#e0f2fe; color:#0369a1;">🏥 Allocation: <?php echo esc_html($log['location_type']); ?></span>
                                        <?php if (!empty($log['bed_no'])): ?>
                                            <span class="arms-p-chip" style="background:#f1f5f9; color:#334155;">🛏️ Bed: <?php echo esc_html($log['bed_no']); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="arms-nurse-vitals-container">
                                        <div class="arms-vital-box" style="border-left: 4px solid #ef4444;">
                                            <span class="v-lbl">Blood Pressure</span>
                                            <span class="v-val"><?php echo $systolic; ?>/<?php echo $diastolic; ?> <span style="font-size:10px; font-weight:normal; color:#64748b;">mmHg</span></span>
                                        </div>
                                        <div class="arms-vital-box" style="border-left: 4px solid #f97316;">
                                            <span class="v-lbl">Pulse Rate</span>
                                            <span class="v-val"><?php echo $pulse; ?> <span style="font-size:10px; font-weight:normal; color:#64748b;">bpm</span></span>
                                        </div>
                                        <div class="arms-vital-box" style="border-left: 4px solid #06b6d4;">
                                            <span class="v-lbl">Blood Oxygen</span>
                                            <span class="v-val"><?php echo $spo2; ?>%</span>
                                        </div>
                                        <div class="arms-vital-box" style="border-left: 4px solid #eab308;">
                                            <span class="v-lbl">Body Temp</span>
                                            <span class="v-val"><?php echo $temp; ?>°F</span>
                                        </div>
                                        <div class="arms-vital-box" style="border-left: 4px solid #a855f7; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <span class="v-lbl" style="margin-bottom:0;">Log Checked</span>
                                            <span style="font-size:10px; font-weight:700; color:#a855f7;"><?php echo esc_html(date_i18n('g:i A', strtotime($log['created_at']))); ?></span>
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 10px;">
                                        <div>
                                            <h5 style="margin:0 0 4px 0; font-size:11px; text-transform:uppercase; color:#475569;">📋 Medication Chart & Administration</h5>
                                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; font-size:12.5px; min-height:45px; color:#475569; line-height:1.4;">
                                                <?php echo !empty($med_notes) ? nl2br(esc_html($med_notes)) : '<em>No pharmaceutical charting specified.</em>'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <h5 style="margin:0 0 4px 0; font-size:11px; text-transform:uppercase; color:#475569;">📝 Shift Report & Nursing Handover Notes</h5>
                                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; font-size:12.5px; min-height:45px; color:#475569; line-height:1.4;">
                                                <?php echo !empty($log['shift_report']) ? nl2br(esc_html($log['shift_report'])) : '<em>No narrative shift summary provided.</em>'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if(!empty($log['nursing_notes'])): ?>
                                        <div style="margin-top: 8px; font-size:12px; color:#64748b; background:#f1f5f9; padding:6px 10px; border-radius:6px;">
                                            <strong>Internal Notes:</strong> <?php echo esc_html($log['nursing_notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-style:italic; font-size:13px; color:#94a3b8; margin:0; padding:10px 0;">No diagnostic vital sheets or shift round logs uploaded into `arms_nursing_logs` for this profile.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- UI TAB ROUTING CONTROLLER RUNNER -->
    <script>
    function armsSwitchTab(event, tabId) {
        event.preventDefault();
        
        // Hide all tabs container elements
        var contents = document.getElementsByClassName('arms-tab-content');
        for (var i = 0; i < contents.length; i++) {
            contents[i].classList.remove('is-active');
        }
        
        // Un-highlight active navigation tabs
        var buttons = document.getElementsByClassName('arms-tab-btn');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('is-active');
        }
        
        // Show current targeted content frame
        document.getElementById(tabId).classList.add('is-active');
        event.currentTarget.classList.add('is-active');
    }
    </script>
    <?php
}