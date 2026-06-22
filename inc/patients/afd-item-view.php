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
    $table_name = $wpdb->prefix . 'arms_patients';

    // Fetch the actual patient database row securely
    $patient_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $item_id), 
        ARRAY_A
    );

    if (!$patient_row) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Patient record not found in the management system database.', 'arms-textdomain') . '</p></div>';
        return;
    }

    // Unpack, map, and process conditions safely (handles JSON arrays or strings)
    $raw_conditions = [];
    if (!empty($patient_row['conditions'])) {
        $decoded = json_decode($patient_row['conditions'], true);
        if (is_array($decoded)) {
            $raw_conditions = $decoded;
        } else {
            // Fallback: splitting string fields clean if stored comma-separated
            $raw_conditions = array_map('trim', explode(',', $patient_row['conditions']));
        }
    }

    // Build the clean runtime layout data dictionary mapped to your database columns
    $patient = array(
        'id'                => intval($patient_row['id']),
        'status'            => !empty($patient_row['status']) ? $patient_row['status'] : 'Active Stay',
        'name'              => $patient_row['name'],
        'age'               => intval($patient_row['age']),
        'gender'            => $patient_row['gender'],
        'mobile'            => $patient_row['mobile'],
        'emergency'         => $patient_row['emergency'],
        'address'           => $patient_row['address'],
        'room_type'         => $patient_row['room_type'],
        'room_no'           => $patient_row['room_no'],
        'admission_date'    => $patient_row['admission_date'],
        'initial_diagnosis' => $patient_row['initial_diagnosis'],
        'custom_diagnosis'  => $patient_row['custom_diagnosis'],
        'conditions'        => $raw_conditions
    );

    $conditions_map = [
        'stroke'          => 'Stroke',
        'paralysis'       => 'Paralysis',
        'plid'            => 'PLID',
        'sci'             => 'SCI',
        'osteoarthritis'  => 'Osteoarthritis'
    ];

    // Determine status design elements
    $status_clean = esc_attr($patient['status']);
    $status_class = 'badge-stay-status status-discharged';
    $status_icon  = '⚪';
    
    if ($status_clean === 'Active Stay') {
        $status_class = 'badge-stay-status status-active';
        $status_icon  = '🟢';
    }
    ?>
    <style>
        .arms-profile-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            color: #0f172a;
        }
        
        /* Profile Header Card */
        .arms-p-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .p-identity-block { display: flex; align-items: center; gap: 20px; }
        .p-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: #f1f5f9; border: 3px solid #e2e8f0; }
        .p-title-meta h2 { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; }
        .p-title-meta p { font-size: 13px; color: #64748b; margin: 0; display: flex; gap: 12px; }
        .p-action-row { display: flex; gap: 10px; align-items: center; }
        
        /* Grid Architecture */
        .arms-p-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 24px; }
        .col-4 { grid-column: span 4; }
        .col-8 { grid-column: span 8; }
        .col-12 { grid-column: span 12; }
        
        @media(max-width: 900px) {
            .col-4, .col-8 { grid-column: span 12; }
        }

        /* Reusable Dashboard Blocks */
        .arms-v-block {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }
        .block-h3 { font-size: 15px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 16px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        
        /* Info Matrix list styling */
        .info-strip-list { display: flex; flex-direction: column; gap: 14px; margin: 0; padding: 0; list-style: none; }
        .info-strip-list li { display: flex; justify-content: space-between; font-size: 13px; line-height: 1.4; }
        .info-strip-list .lbl { color: #64748b; font-weight: 500; }
        .info-strip-list .val { color: #0f172a; font-weight: 600; text-align: right; }

        /* Diagnosis Badges layout */
        .badge-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .dx-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .dx-tag.active { background: #e0e7ff; color: #4338ca; border-color: #c7d2fe; }

        /* Clinical Text Boxes */
        .clinical-narrative { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; font-size: 13px; line-height: 1.6; color: #334155; margin-bottom: 16px; }
        
        /* Generic Theme Buttons */
        .arms-p-btn { padding: 9px 16px; font-size: 13px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: none; }
        .btn-p-sec { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .btn-p-sec:hover { background: #f8fafc; color: #0f172a; }
        .btn-p-prime { background: #4f46e5; color: #ffffff; }
        .btn-p-prime:hover { background: #4338ca; }
        
        .badge-stay-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .badge-stay-status.status-active { background: #dcfce7; color: #15803d; }
        .badge-stay-status.status-discharged { background: #f1f5f9; color: #475569; }

        /* Print Override Ruleset */
        @media print {
            body * { visibility: hidden; }
            .arms-profile-wrapper, .arms-profile-wrapper * { visibility: visible; }
            .arms-profile-wrapper { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
            .p-action-row, .wp-admin-bar, #adminmenuback, #adminmenuwrap { display: none !important; }
        }
    </style>

    <div class="arms-profile-wrapper">
        
        <!-- Header Ribbon Component -->
        <div class="arms-p-card">
            <div class="p-identity-block">
                <div class="p-title-meta">
                    <h2><?php echo esc_html($patient['name']); ?></h2>
                    <p>
                        <span><strong>Age:</strong> <?php echo esc_html($patient['age']); ?> Yrs</span>
                        <span>•</span>
                        <span><strong>Gender:</strong> <?php echo esc_html($patient['gender']); ?></span>
                        <span>•</span>
                        <span><strong>Case File:</strong> #RMS-<?php echo esc_html($patient['id']); ?></span>
                    </p>
                </div>
            </div>
            <div class="p-action-row">
                <span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_icon . ' ' . $patient['status']); ?></span>
                <button type="button" class="arms-p-btn btn-p-sec" onclick="window.print();">
                    <i class="fa-solid fa-print"></i> <?php echo esc_html__('Print Summary', 'arms-textdomain'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rehab_management_system&tab=patients&id=' . $patient['id'] . '&action=edit')); ?>" class="arms-p-btn btn-p-prime">
                    <i class="fa-solid fa-user-pen"></i> <?php echo esc_html__('Edit Profile', 'arms-textdomain'); ?>
                </a>
            </div>
        </div>

        <!-- Master Split Columns Grid Matrix Layout -->
        <div class="arms-p-grid">
            
            <!-- Left Grid: Patient Administrative Profile Data -->
            <div class="col-4">
                <div class="arms-v-block" style="margin-bottom: 24px;">
                    <h3 class="block-h3"><i class="fa-solid fa-id-card"></i> <?php echo esc_html__('Demographics', 'arms-textdomain'); ?></h3>
                    <ul class="info-strip-list">
                        <li><span class="lbl"><?php echo esc_html__('Mobile Number', 'arms-textdomain'); ?></span><span class="val"><?php echo esc_html($patient['mobile']); ?></span></li>
                        <li><span class="lbl"><?php echo esc_html__('Emergency Line', 'arms-textdomain'); ?></span><span class="val"><?php echo esc_html($patient['emergency']); ?></span></li>
                        <li><span class="lbl"><?php echo esc_html__('Residential Location', 'arms-textdomain'); ?></span><span class="val" style="max-width:65%;"><?php echo esc_html($patient['address']); ?></span></li>
                    </ul>
                </div>

                <div class="arms-v-block">
                    <h3 class="block-h3"><i class="fa-solid fa-bed"></i> <?php echo esc_html__('Bed Assignment', 'arms-textdomain'); ?></h3>
                    <ul class="info-strip-list">
                        <li><span class="lbl"><?php echo esc_html__('Classification Style', 'arms-textdomain'); ?></span><span class="val"><?php echo esc_html($patient['room_type']); ?></span></li>
                        <li><span class="lbl"><?php echo esc_html__('Assigned Space ID', 'arms-textdomain'); ?></span><span class="val"><?php echo esc_html($patient['room_no']); ?></span></li>
                        <li><span class="lbl"><?php echo esc_html__('Admission Date', 'arms-textdomain'); ?></span><span class="val"><?php echo esc_html(!empty($patient['admission_date']) ? date_i18n(get_option('date_format'), strtotime($patient['admission_date'])) : 'N/A'); ?></span></li>
                    </ul>
                </div>
            </div>

            <!-- Right Grid: Clinical Status Records Logs -->
            <div class="col-8">
                <div class="arms-v-block" style="height: 100%; box-sizing: border-box;">
                    <h3 class="block-h3"><i class="fa-solid fa-notes-medical"></i> <?php echo esc_html__('Medical & Diagnostic Profile', 'arms-textdomain'); ?></h3>
                    
                    <div class="badge-grid">
                        <?php 
                        foreach ($conditions_map as $key => $label) {
                            $is_active = in_array($key, $patient['conditions']);
                            echo '<span class="dx-tag ' . ($is_active ? 'active' : '') . '">';
                            echo $is_active ? '✓ ' : '';
                            echo esc_html($label) . '</span>';
                        }
                        ?>
                    </div>

                    <h4 style="font-size: 13px; font-weight: 700; color: #475569; margin: 20px 0 8px 0; text-transform: uppercase;"><?php echo esc_html__('Initial Admission Summary', 'arms-textdomain'); ?></h4>
                    <div class="clinical-narrative">
                        <?php echo !empty($patient['initial_diagnosis']) ? nl2br(esc_html($patient['initial_diagnosis'])) : esc_html__('No initial admission summary recorded.', 'arms-textdomain'); ?>
                    </div>

                    <h4 style="font-size: 13px; font-weight: 700; color: #475569; margin: 20px 0 8px 0; text-transform: uppercase;"><?php echo esc_html__('Spinal & Musculoskeletal Structural Log', 'arms-textdomain'); ?></h4>
                    <div class="clinical-narrative" style="border-left: 3px solid #4f46e5; background: #f0fdf4;">
                        <?php echo !empty($patient['custom_diagnosis']) ? nl2br(esc_html($patient['custom_diagnosis'])) : esc_html__('No detailed structural diagnosis log recorded.', 'arms-textdomain'); ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php
}