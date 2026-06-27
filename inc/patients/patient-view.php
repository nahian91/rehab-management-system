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

    // Fetch patient metadata securely
    $patient_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_patients WHERE id = %d", $item_id), 
        ARRAY_A
    );

    if (!$patient_row) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Patient record not found.', 'arms-textdomain') . '</p></div>';
        return;
    }

    // Fetch comprehensive data from the latest active admission record
    $admission_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_admissions WHERE patient_id = %d ORDER BY id DESC LIMIT 1", $item_id),
        ARRAY_A
    );

    // Fetch Global Pricing Framework Settings
    $global_settings = get_option('arms_global_settings', array());
    $currency        = isset($global_settings['currency_symbol']) ? $global_settings['currency_symbol'] : '৳';
    $tax_rate        = floatval($global_settings['tax_rate'] ?? 0);

    // Fallbacks and parsing for dynamic admission data properties
    $advance_payment   = floatval($admission_row['advance_payment'] ?? 0);
    $room_key          = !empty($admission_row['room_type']) ? sanitize_key($admission_row['room_type']) : 'none';
    $room_type         = !empty($admission_row['room_type']) ? ucwords(str_replace('_', ' ', $admission_row['room_type'])) : 'Not Assigned';
    $assigned_doctor   = !empty($admission_row['assigned_doctor']) ? $admission_row['assigned_doctor'] : 'Unassigned';
    $duration_days     = isset($admission_row['duration_days']) ? intval($admission_row['duration_days']) : 0;
    
    // Therapy counters directly from the active admission instance
    $physio_sessions   = isset($admission_row['physio_sessions']) ? intval($admission_row['physio_sessions']) : 0;
    $acupuncture_count = isset($admission_row['acupuncture_sessions']) ? intval($admission_row['acupuncture_sessions']) : 0;
    $prp_count         = isset($admission_row['prp_sessions']) ? intval($admission_row['prp_sessions']) : 0;

    /*--------------------------------------------------------------
    # CALCULATE LIVE ADMISSION CHARGES DYNAMICALLY
    --------------------------------------------------------------*/
    // 1. Resolve Daily Room Rent
    $base_room_rate = 0;
    if ($room_key === 'cabin' || $room_key === 'luxury_private_cabin') {
        $base_room_rate = floatval($global_settings['room_rent_cabin'] ?? 0);
    } elseif ($room_key === 'semi_private' || $room_key === 'semi_private_shared') {
        $base_room_rate = floatval($global_settings['room_rent_semi_private'] ?? 0);
    } elseif ($room_key === 'ward' || $room_key === 'general_ward') {
        $base_room_rate = floatval($global_settings['room_rent_ward'] ?? 0);
    }
    $total_room_charge = $base_room_rate * $duration_days;

    // 2. Resolve Daily Care Charges
    $rate_doctor       = floatval($global_settings['fee_doctor'] ?? 0);
    $rate_nursing      = floatval($global_settings['fee_nursing'] ?? 0);
    $total_doctor_fee  = $rate_doctor * $duration_days;
    $total_nursing_fee = $rate_nursing * $duration_days;

    // 3. Resolve Per-Session Therapy Charges
    $rate_physio       = floatval($global_settings['fee_physio'] ?? 0);
    $rate_acupuncture  = floatval($global_settings['fee_acupuncture'] ?? 0);
    $rate_prp          = floatval($global_settings['fee_prp'] ?? 0);
    
    $total_physio_fee  = $rate_physio * $physio_sessions;
    $total_acup_fee    = $rate_acupuncture * $acupuncture_count;
    $total_prp_fee     = $rate_prp * $prp_count;

    // Subtotal Calculation
    $gross_subtotal   = $total_room_charge + $total_doctor_fee + $total_nursing_fee + $total_physio_fee + $total_acup_fee + $total_prp_fee;
    $surcharge_amount = ($gross_subtotal * $tax_rate) / 100;
    $calculated_total = $gross_subtotal + $surcharge_amount;
    $net_balance_due  = $calculated_total - $advance_payment;

    // Unpack conditions cleanly
    $raw_conditions = [];
    if (!empty($patient_row['conditions'])) {
        $decoded = json_decode($patient_row['conditions'], true);
        $raw_conditions = is_array($decoded) ? $decoded : array_map('trim', explode(',', $patient_row['conditions']));
    }

    // Build runtime layout mapped securely to your exact patient columns
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
        'media_vault_urls'        => $patient_row['media_vault_urls'],
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
        .arms-p-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1250px;
            margin: 24px auto;
            color: #1e293b;
            padding: 0 16px;
        }

        /* Top Action Bar Header */
        .arms-p-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
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

        /* Complex Grid Layout Panels */
        .arms-main-grid { display: grid; grid-template-columns: 4fr 8fr; gap: 24px; margin-bottom: 24px; }
        .arms-card-panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: 100%; box-sizing: border-box; }
        .panel-title { font-size: 14px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 20px 0; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }

        /* Sleek Info Lists layout rules */
        .arms-list-strip { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px; }
        .arms-list-strip li { display: flex; justify-content: space-between; align-items: flex-start; font-size: 13.5px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 10px; }
        .arms-list-strip li:last-child { border-bottom: none; padding-bottom: 0; }
        .arms-list-strip .strip-lbl { color: #64748b; font-weight: 500; }
        .arms-list-strip .strip-val { color: #0f172a; font-weight: 600; text-align: right; max-width: 65%; }

        /* Diagnostic tag block styles */
        .arms-badge-deck { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .arms-badge-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f8fafc; color: #64748b; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 4px; }
        .arms-badge-tag.is-active { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }

        /* Narrative blocks formatting */
        .arms-narrative-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; font-size: 13.5px; line-height: 1.6; color: #334155; }

        /* Modernized Data Table Settings Layout */
        .arms-table-responsive { width: 100%; overflow-x: auto; margin-top: 12px; }
        .arms-pricing-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        .arms-pricing-table th, .arms-pricing-table td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        .arms-pricing-table th { background: #f8fafc; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .arms-pricing-table tr:hover td { background: #fdfdfd; }
        .arms-pricing-table td.fee-val { font-weight: 700; color: #0f172a; text-align: right; }
        .arms-pricing-table .tax-highlight td { background: #faf5ff; color: #6b21a8; font-weight: 700; border-bottom: 2px dashed #d8b4fe; }
        .arms-pricing-table .total-highlight td { background: #f0fdf4; color: #166534; font-weight: 800; border-top: 2px solid #bbf7d0; font-size: 14px; }

        @media(max-width: 1024px) {
            .arms-main-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="arms-p-container">
        
        <div class="arms-p-header">
            <div class="arms-p-identity">
                <?php if ( !empty($patient['media_vault_urls']) ) : ?>
                    <img src="<?php echo esc_url($patient['media_vault_urls']); ?>" class="arms-p-img" alt="Patient File Photo">
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

        <div class="arms-main-grid">
            
            <div class="arms-card-panel">
                <h3 class="panel-title">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.418.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    Demographics & Active Admission
                </h3>
                <ul class="arms-list-strip">
                    <li><span class="strip-lbl">Primary Mobile</span><span class="strip-val"><?php echo esc_html($patient['mobile']); ?></span></li>
                    <li><span class="strip-lbl">System Intake Date</span><span class="strip-val"><?php echo esc_html(!empty($patient['admission_date']) ? date_i18n(get_option('date_format'), strtotime($patient['admission_date'])) : '—'); ?></span></li>
                    <li><span class="strip-lbl">Assigned Space</span><span class="strip-val" style="color: #003376;"><?php echo esc_html($room_type); ?></span></li>
                    <li><span class="strip-lbl">Clinical Consultant</span><span class="strip-val"><?php echo esc_html($assigned_doctor); ?></span></li>
                    <li><span class="strip-lbl">Duration Registered</span><span class="strip-val"><?php echo esc_html($duration_days); ?> Treatment Days</span></li>
                    <li><span class="strip-lbl">Emergency Line</span><span class="strip-val"><?php echo esc_html(!empty($patient['emergency_contact_phone']) ? $patient['emergency_contact_phone'] : '—'); ?></span></li>
                    <li><span class="strip-lbl">Residential Address</span><span class="strip-val"><?php echo esc_html($patient['address']); ?></span></li>
                </ul>
            </div>

            <div class="arms-card-panel">
                <h3 class="panel-title">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Medical Mapping & Admission Metrics
                </h3>
                
                <div class="arms-badge-deck">
                    <?php 
                    foreach ($conditions_map as $key => $label) {
                        $is_active = !empty($patient['conditions'][$key]) && $patient['conditions'][$key] == '1';
                        $class = $is_active ? 'is-active' : '';
                        echo '<span class="arms-badge-tag ' . $class . '">';
                        if($is_active) {
                            echo '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" style="margin-right:2px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                        }
                        echo esc_html($label) . '</span>';
                    }
                    ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; text-align: center;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight:600; display:block; margin-bottom:4px;">Physio Runs</span>
                        <strong style="font-size: 18px; color: #0f172a;"><?php echo $physio_sessions; ?></strong>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; text-align: center;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight:600; display:block; margin-bottom:4px;">Acupuncture</span>
                        <strong style="font-size: 18px; color: #0f172a;"><?php echo $acupuncture_count; ?></strong>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; text-align: center;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight:600; display:block; margin-bottom:4px;">PRP Logs</span>
                        <strong style="font-size: 18px; color: #0f172a;"><?php echo $prp_count; ?></strong>
                    </div>
                </div>

                <h4 style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.5px;">Initial Case Summary Narration</h4>
                <div class="arms-narrative-box">
                    <?php echo !empty($patient['initial_diagnosis']) ? nl2br(esc_html($patient['initial_diagnosis'])) : '<em>No descriptive case file annotations logged on system entry.</em>'; ?>
                </div>
            </div>

        </div>

        <div class="arms-card-panel">
            <h3 class="panel-title" style="color: #dc2626;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Active Admission Cost Itemization Breakdown
            </h3>
            
            <div class="arms-table-responsive">
                <table class="arms-pricing-table">
                    <thead>
                        <tr>
                            <th>Service Item Designation</th>
                            <th>Unit Rate</th>
                            <th>Quantity / Units</th>
                            <th style="text-align: right;">Total Charge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Accommodation Rent (<?php echo esc_html($room_type); ?>)</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($base_room_rate, 2); ?></td>
                            <td><?php echo $duration_days; ?> Days</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_room_charge, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Attending Physician Rounds</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_doctor, 2); ?></td>
                            <td><?php echo $duration_days; ?> Days</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_doctor_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Daily Nursing Care Services</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_nursing, 2); ?></td>
                            <td><?php echo $duration_days; ?> Days</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_nursing_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Physiotherapy Care Allocations</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_physio, 2); ?></td>
                            <td><?php echo $physio_sessions; ?> Sessions</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_physio_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Acupuncture Therapy Sessions</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_acupuncture, 2); ?></td>
                            <td><?php echo $acupuncture_count; ?> Sessions</td>
                            <td class="fee-val"><?php echo esc_html($currency) . ' ' . number_format($total_acup_fee, 2); ?></td>
                        </tr>
                        <tr>
                            <td>PRP Treatment Clinical Runs</td>
                            <td><?php echo esc_html($currency) . ' ' . number_format($rate_prp, 2); ?></td>
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
    <?php
}