<?php
/**
 * Advanced Rehabilitation Management System (ARMS) - Unified Master File
 * Complete implementation covering Demographics, Clinical Data, Day Ledger Matrix, 
 * Media Vault Archive, and the Real-time Ward/Cabin Occupancy Engine.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent external file compilation requests
}

/**
 * UI Renderer Entrypoint Controller Layout Hook
 */
function arms_add_edit_patient_form() {
    // Determine runtime state variables
    $is_edit = isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['patient_id'] );
    $patient_id = $is_edit ? intval( $_GET['patient_id'] ) : 0;
    
    // Default model hydration initialization arrays
    $patient = array(
        'id'                => 0,
        'name'              => '',
        'age'               => '',
        'gender'            => 'Male',
        'mobile'            => '',
        'emergency'         => '',
        'address'           => '',
        'room_type'         => 'Cabin',
        'room_no'           => '',
        'admission_date'    => date('Y-m-d'),
        'initial_diagnosis' => '',
        'custom_diagnosis'  => '',
        'conditions'        => array(),
        'ledger'            => array( array('date' => date('Y-m-d'), 'room_rent' => 0, 'doctor' => 0, 'nursing' => 0, 'physio' => 0, 'acupuncture' => 0, 'prp' => 0) ),
        'media'             => array()
    );

    // Pull database profile details if editing an existing entry
    if ( $is_edit ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'arms_patients';
        $db_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $patient_id ) );
        
        if ( $db_record ) {
            $patient['id']                = intval( $db_record->id );
            $patient['name']              = esc_attr( $db_record->name );
            $patient['age']               = intval( $db_record->age );
            $patient['gender']            = esc_attr( $db_record->gender );
            $patient['mobile']            = esc_attr( $db_record->mobile );
            $patient['emergency']         = esc_attr( $db_record->emergency );
            $patient['address']           = esc_textarea( $db_record->address );
            $patient['room_type']         = esc_attr( $db_record->room_type );
            $patient['room_no']           = esc_attr( $db_record->room_no );
            $patient['admission_date']    = esc_attr( $db_record->admission_date );
            $patient['initial_diagnosis'] = esc_textarea( $db_record->initial_diagnosis );
            $patient['custom_diagnosis']  = esc_textarea( $db_record->custom_diagnosis );
            
            // Unpack serialization wrappers
            if ( ! empty( $db_record->conditions ) ) {
                $patient['conditions'] = maybe_unserialize( $db_record->conditions );
                if ( ! is_array( $patient['conditions'] ) ) {
                    $patient['conditions'] = json_decode( $db_record->conditions, true ) ?: array();
                }
            }
            if ( ! empty( $db_record->day_billing_ledger ) ) {
                $patient['ledger'] = json_decode( $db_record->day_billing_ledger, true ) ?: $patient['ledger'];
            }
            if ( ! empty( $db_record->media_vault_urls ) ) {
                $patient['media'] = json_decode( $db_record->media_vault_urls, true ) ?: array();
            }
        }
    }
    ?>
    <div class="arms-system-container" style="max-width:1300px; margin:20px auto; padding:15px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
        
        <div class="arms-m-header" style="background:#1e293b; color:#fff; padding:24px; border-radius:12px; margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
            <div>
                <h1 style="margin:0; font-size:24px; font-weight:700; color:#fff; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-hospital-user" style="color:#38bdf8;"></i> 
                    <?php echo $is_edit ? 'Modifying Patient File Workspace' : 'New Intake Registry Deployment'; ?>
                </h1>
                <p style="margin:5px 0 0 0; font-size:13px; color:#94a3b8;">Unified Clinical Operations Platform & Matrix Billing Router</p>
            </div>
            <?php if ( $is_edit ) : ?>
                <div style="background:#334155; padding:8px 16px; border-radius:8px; border:1px solid #475569; text-align:right;">
                    <span style="display:block; font-size:10px; color:#cbd5e1; text-transform:uppercase;">System Reference Token</span>
                    <strong style="font-size:14px; color:#38bdf8;">#ARMS-2026-<?php echo $patient['id']; ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <div class="arms-wizard-navigation-bar" style="display:flex; gap:8px; margin-bottom:25px; background:#f1f5f9; padding:6px; border-radius:10px; border:1px solid #e2e8f0; overflow-x:auto;">
            <button type="button" id="btn-sec-demo" class="arms-wiz-btn active" onclick="armsSwitchPanel('sec-demo')"><i class="fa-solid fa-id-card"></i> 1. Demographics</button>
            <button type="button" id="btn-sec-clinic" class="arms-wiz-btn" onclick="armsSwitchPanel('sec-clinic')"><i class="fa-solid fa-stethoscope"></i> 2. Clinical Data</button>
            <button type="button" id="btn-sec-billing" class="arms-wiz-btn" onclick="armsSwitchPanel('sec-billing')"><i class="fa-solid fa-file-invoice-dollar"></i> 3. Room & Services Ledger</button>
            <button type="button" id="btn-sec-vault" class="arms-wiz-btn" onclick="armsSwitchPanel('sec-vault')"><i class="fa-solid fa-vault"></i> 4. Media Archive Vault</button>
            <button type="button" id="btn-sec-live-allocations" class="arms-wiz-btn" onclick="armsSwitchPanel('sec-live-allocations')"><i class="fa-solid fa-bed"></i> 5. Live Occupancy Matrix</button>
        </div>

        <form method="post" action="" enctype="multipart/form-data" id="arms-unified-master-form">
            <?php wp_nonce_field( 'arms_save_unified_patient_action', 'arms_unified_nonce' ); ?>
            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>" />
            <input type="hidden" name="action_type" value="<?php echo $is_edit ? 'update' : 'create'; ?>" />

            <div class="arms-wizard-content-viewport" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:30px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                
                <div id="sec-demo" class="arms-section-panel active">
                    <div style="display:grid; grid-template-columns:220px 1fr; gap:40px;">
                        
                        <div style="text-align:center; border-right:1px solid #f1f5f9; padding-right:30px;">
                            <div style="width:160px; height:160px; margin:0 auto 15px auto; border-radius:50%; overflow:hidden; border:4px solid #f1f5f9; background:#f8fafc; display:flex; align-items:center; justify-content:center; box-shadow:inset 0 2px 4px rgba(0,0,0,0.06);">
                                <?php 
                                $avatar_src = ! empty( $patient['media']['patient_profile_pic'] ) ? $patient['media']['patient_profile_pic'] : 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y&s=160';
                                ?>
                                <img id="patient-avatar-view" src="<?php echo esc_url($avatar_src); ?>" style="width:100%; height:100%; object-fit:cover;" alt="Avatar Target Viewport" />
                            </div>
                            <label class="arms-m-btn b-secondary" style="display:inline-block; cursor:pointer; font-size:12px; padding:6px 12px;">
                                <i class="fa-solid fa-camera"></i> Change Photo
                                <input type="file" name="patient_profile_pic" id="patient_profile_pic" accept="image/*" style="display:none;" />
                            </label>
                        </div>

                        <div>
                            <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:20px;">
                                <div>
                                    <label class="arms-m-label">Patient Full Name <span style="color:#ef4444;">*</span></label>
                                    <input type="text" name="patient_name" class="arms-input-field" value="<?php echo $patient['name']; ?>" required placeholder="John Doe" />
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                                    <div>
                                        <label class="arms-m-label">Age <span style="color:#ef4444;">*</span></label>
                                        <input type="number" name="patient_age" class="arms-input-field" value="<?php echo $patient['age']; ?>" required min="1" max="120" placeholder="45" />
                                    </div>
                                    <div>
                                        <label class="arms-m-label">Gender <span style="color:#ef4444;">*</span></label>
                                        <select name="patient_gender" class="arms-input-field">
                                            <option value="Male" <?php selected($patient['gender'], 'Male'); ?>>Male</option>
                                            <option value="Female" <?php selected($patient['gender'], 'Female'); ?>>Female</option>
                                            <option value="Other" <?php selected($patient['gender'], 'Other'); ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="arms-m-label">Primary Mobile Line <span style="color:#ef4444;">*</span></label>
                                    <input type="tel" name="patient_mobile" class="arms-input-field" value="<?php echo $patient['mobile']; ?>" required placeholder="+1 (555) 000-0000" />
                                </div>
                                <div>
                                    <label class="arms-m-label">Emergency Callback Reference Contacts</label>
                                    <input type="text" name="patient_emergency" class="arms-input-field" value="<?php echo $patient['emergency']; ?>" placeholder="Spouse / Parent contact phone details" />
                                </div>
                                <div style="grid-column:span 2;">
                                    <label class="arms-m-label">Permanent Residential Address Coordinates</label>
                                    <textarea name="patient_address" class="arms-input-field" style="height:65px; resize:vertical;" placeholder="Street, Building, Suite, City, Postal Code Structure"><?php echo $patient['address']; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="arms-wiz-actions">
                        <div></div>
                        <button type="button" class="arms-m-btn b-primary" onclick="armsSwitchPanel('sec-clinic')">
                            Proceed to Clinical Data <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div id="sec-clinic" class="arms-section-panel">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:35px;">
                        
                        <div>
                            <div class="sub-divider-title">📋 Clinical Diagnosis Mapping Matrix</div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:20px; border-radius:10px; display:flex; flex-direction:column; gap:12px;">
                                <?php 
                                $conditions_directory = array(
                                    'stroke'         => 'Brain Stroke Rehabilitation Profile',
                                    'paralysis'      => 'Paralytic Structural Motor Dysfunction',
                                    'plid'           => 'PLID (Prolapsed Lumbar Intervertebral Disc Analysis)',
                                    'sci'            => 'Spinal Cord Injury Pathological Tracking',
                                    'osteoarthritis' => 'Degenerative Joint Osteoarthritis Profile'
                                );
                                foreach ( $conditions_directory as $slug => $label ) : 
                                    $is_checked = in_array( $slug, $patient['conditions'] ) ? 'checked' : '';
                                ?>
                                    <label class="matrix-check-card" style="display:flex; align-items:center; gap:12px; background:#fff; padding:12px 15px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer; transition:all 0.2s;">
                                        <input type="checkbox" name="medical_conditions[]" value="<?php echo $slug; ?>" <?php echo $is_checked; ?> style="width:18px; height:18px; accent-color:#0284c7;" />
                                        <span style="font-size:14px; font-weight:500; color:#334155;"><?php echo $label; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <div class="sub-divider-title">🏨 Space Allocation & Logistics Tracking</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                                <div>
                                    <label class="arms-m-label">Accommodation Variant</label>
                                    <select name="room_type" id="room_type" class="arms-input-field">
                                        <option value="Cabin" <?php selected($patient['room_type'], 'Cabin'); ?>>Exclusive VIP Cabin Package</option>
                                        <option value="Ward" <?php selected($patient['room_type'], 'Ward'); ?>>General Medical Ward Bay Block</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="arms-m-label">Assigned Room/Bed ID <span style="color:#ef4444;">*</span></label>
                                    <input type="text" name="room_no" id="room_no" class="arms-input-field" value="<?php echo $patient['room_no']; ?>" required placeholder="e.g., C-302 or W-12" />
                                </div>
                            </div>
                            <div style="margin-bottom:20px;">
                                <label class="arms-m-label">Onboarding Operational Date Field</label>
                                <input type="date" name="admission_date" class="arms-input-field" value="<?php echo $patient['admission_date']; ?>" required />
                            </div>
                            <div>
                                <label class="arms-m-label">Intake Symptom Log Narrative & Direct Observations</label>
                                <textarea name="patient_initial_diagnosis" class="arms-input-field" style="height:90px; resize:vertical;" placeholder="Input active structural analysis notes baseline metrics here..."><?php echo $patient['initial_diagnosis']; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:25px;">
                        <label class="arms-m-label">Advanced Neuromuscular Complications / Secondary Pathology Overrides</label>
                        <textarea name="patient_custom_diagnosis" class="arms-input-field" style="height:70px; resize:vertical;" placeholder="Track supplementary treatment plan exceptions or multi-disciplinary care pathways here..."><?php echo $patient['custom_diagnosis']; ?></textarea>
                    </div>

                    <div class="arms-wiz-actions">
                        <button type="button" class="arms-m-btn b-secondary" onclick="armsSwitchPanel('sec-demo')">
                            <i class="fa-solid fa-arrow-left"></i> Back to Demographics
                        </button>
                        <button type="button" class="arms-m-btn b-primary" onclick="armsSwitchPanel('sec-billing')">
                            Proceed to Space Allocations & Ledger <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div id="sec-billing" class="arms-section-panel">
                    <div class="sub-divider-title">📊 Granular Daily Stay Accounts Matrix & Modality Cost Optimization Map</div>
                    
                    <div class="repeater-header-wrapper">
                        <div>Log Stay Date</div>
                        <div>Room Rent</div>
                        <div>Consultation</div>
                        <div>Nursing Care</div>
                        <div>Physiotherapy</div>
                        <div>Acupuncture</div>
                        <div>PRP Therapy</div>
                        <div style="text-align:center;">Action</div>
                    </div>

                    <div id="arms-repeater-root">
                        <?php foreach ( $patient['ledger'] as $index => $row ) : ?>
                            <div class="repeater-data-item" data-index="<?php echo $index; ?>">
                                <div>
                                    <input type="date" name="day_billing_ledger[<?php echo $index; ?>][date]" class="r-calc-trigger" value="<?php echo esc_attr($row['date']); ?>" required />
                                </div>
                                <div class="r-cell-input-wrap">
                                    <span class="r-currency">$</span>
                                    <input type="number" name="day_billing_ledger[<?php echo $index; ?>][room_rent]" class="r-calc-trigger field-room-rent" min="0" step="any" value="<?php echo floatval($row['room_rent']); ?>" />
                                </div>
                                <div class="r-cell-input-wrap">
                                    <span class="r-currency">$</span>
                                    <input type="number" name="day_billing_ledger[<?php echo $index; ?>][doctor]" class="r-calc-trigger field-doctor" min="0" step="any" value="<?php echo floatval($row['doctor']); ?>" />
                                </div>
                                <div class="r-cell-input-wrap">
                                    <span class="r-currency">$</span>
                                    <input type="number" name="day_billing_ledger[<?php echo $index; ?>][nursing]" class="r-calc-trigger field-nursing" min="0" step="any" value="<?php echo floatval($row['nursing']); ?>" />
                                </div>
                                <div class="r-cell-input-wrap">
                                    <span class="r-currency">$</span>
                                    <input type="number" name="day_billing_ledger[<?php echo $index; ?>][physio]" class="r-calc-trigger field-physio" min="0" step="any" value="<?php echo floatval($row['physio']); ?>" />
                                </div>
                                <div class="r-cell-input-wrap">
                                    <span class="r-currency">$</span>
                                    <input type="number" name="day_billing_ledger[<?php echo $index; ?>][acupuncture]" class="r-calc-trigger field-acupuncture" min="0" step="any" value="<?php echo floatval($row['acupuncture']); ?>" />
                                </div>
                                <div class="r-cell-input-wrap">
                                    <span class="r-currency">$</span>
                                    <input type="number" name="day_billing_ledger[<?php echo $index; ?>][prp]" class="r-calc-trigger field-prp" min="0" step="any" value="<?php echo floatval($row['prp']); ?>" />
                                </div>
                                <div style="text-align:center;">
                                    <button type="button" class="btn-remove-row" onclick="armsDeleteRepeaterRow(this)">&times;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
                        <button type="button" class="arms-m-btn b-secondary" id="arms-add-row-trigger" style="background:#fff; border:1px dashed #cbd5e1; color:#0f172a;">
                            <i class="fa-solid fa-plus"></i> Add New Day Entry
                        </button>
                    </div>

                    <div class="summary-box-wrapper" style="margin-top:30px;">
                        <div class="summary-card">
                            <span class="s-lbl">Total Days Logged</span>
                            <span class="s-val" id="sum-days-count">1</span>
                        </div>
                        <div class="summary-card">
                            <span class="s-lbl">Total Room Accommodations</span>
                            <span class="s-val" id="sum-room-rent">$0.00</span>
                        </div>
                        <div class="summary-card">
                            <span class="s-lbl">Total Clinical Consultations</span>
                            <span class="s-val" id="sum-clinical-visits">$0.00</span>
                        </div>
                        <div class="summary-card highlight-total">
                            <span class="s-lbl">Gross Account Matrix Balance</span>
                            <span class="s-val" id="sum-gross-total">$0.00</span>
                        </div>
                    </div>

                    <div class="arms-wiz-actions">
                        <button type="button" class="arms-m-btn b-secondary" onclick="armsSwitchPanel('sec-clinic')">
                            <i class="fa-solid fa-arrow-left"></i> Back to Clinical Data
                        </button>
                        <button type="button" class="arms-m-btn b-primary" onclick="armsSwitchPanel('sec-vault')">
                            Proceed to Document Vault & History <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div id="sec-vault" class="arms-section-panel">
                    <div class="vault-upload-grid">
                        <div class="vault-card">
                            <div class="icon" style="font-size:24px;">💿</div>
                            <div class="arms-m-label">MRI Scans Vault</div>
                            <?php if ( ! empty($patient['media']['doc_mri']) ) : ?>
                                <div style="font-size:11px; margin-bottom:5px;"><a href="<?php echo esc_url($patient['media']['doc_mri']); ?>" target="_blank" style="color:#0284c7; font-weight:600;"><i class="fa-solid fa-external-link"></i> View Loaded Scan</a></div>
                            <?php endif; ?>
                            <input type="file" name="doc_mri" id="doc_mri" accept=".jpg,.jpeg,.png,.pdf" data-title="MRI Scan" />
                        </div>
                        <div class="vault-card">
                            <div class="icon" style="font-size:24px;">🩻</div>
                            <div class="arms-m-label">X-Ray Records</div>
                            <?php if ( ! empty($patient['media']['doc_xray']) ) : ?>
                                <div style="font-size:11px; margin-bottom:5px;"><a href="<?php echo esc_url($patient['media']['doc_xray']); ?>" target="_blank" style="color:#0284c7; font-weight:600;"><i class="fa-solid fa-external-link"></i> View Loaded Record</a></div>
                            <?php endif; ?>
                            <input type="file" name="doc_xray" id="doc_xray" accept=".jpg,.jpeg,.png,.pdf" data-title="X-Ray Report" />
                        </div>
                        <div class="vault-card">
                            <div class="icon" style="font-size:24px;">🧪</div>
                            <div class="arms-m-label">Lab Pathology</div>
                            <?php if ( ! empty($patient['media']['doc_lab']) ) : ?>
                                <div style="font-size:11px; margin-bottom:5px;"><a href="<?php echo esc_url($patient['media']['doc_lab']); ?>" target="_blank" style="color:#0284c7; font-weight:600;"><i class="fa-solid fa-external-link"></i> View Loaded Pathology</a></div>
                            <?php endif; ?>
                            <input type="file" name="doc_lab" id="doc_lab" accept=".jpg,.jpeg,.png,.pdf" data-title="Lab Report" />
                        </div>
                        <div class="vault-card" style="border-style: solid; background: #f0fdf4; border-color: #bbf7d0;">
                            <div class="icon" style="font-size:24px;">🖼️</div>
                            <div class="arms-m-label" style="color:#166534;">Posture Media</div>
                            <?php if ( ! empty($patient['media']['doc_patient_images']) && is_array($patient['media']['doc_patient_images']) ) : ?>
                                <div style="font-size:11px; margin-bottom:5px; color:#166534; font-weight:600;"><i class="fa-solid fa-images"></i> <?php echo count($patient['media']['doc_patient_images']); ?> Images Retained</div>
                            <?php endif; ?>
                            <input type="file" name="doc_patient_images[]" id="doc_patient_images" accept="image/*" data-title="Posture View" multiple />
                        </div>
                    </div>

                    <div id="arms-m-preview-wrapper" style="display: none; margin-top:25px;">
                        <div class="arms-pre-header" style="background:#0f172a; color:#fff; padding:12px 20px; border-radius:8px 8px 0 0; font-weight:600; font-size:14px;"><i class="fa-solid fa-paperclip"></i> Attached Upload Cache Processing Checklist</div>
                        <div class="arms-pre-container" id="arms-m-preview-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:15px; background:#f8fafc; border:1px solid #e2e8f0; border-top:none; padding:20px; border-radius:0 0 8px 8px;"></div>
                    </div>

                    <?php if ( $is_edit ) : ?>
                        <div class="sub-divider-title" style="margin-top:35px;">💰 Historic Settlement Records Audit Trail</div>
                        <div class="arms-display-card" style="padding:0; overflow:hidden; margin-top:10px; border:1px solid #e2e8f0; border-radius:8px;">
                            <table class="arms-display-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
                                <style>
                                    .arms-display-table th { background:#f8fafc; padding:12px 16px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0; }
                                    .arms-display-table td { padding:12px 16px; border-bottom:1px solid #f1f5f9; color:#334155; }
                                    .tag-success { background:#dcfce7; color:#166534; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:500; }
                                    .tag-warning { background:#fef9c3; color:#854d0e; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:500; }
                                </style>
                                <thead>
                                    <tr><th>Invoice ID</th><th>Statement Date</th><th>Description Category</th><th>Gross Amount</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td><strong>#INV-9842</strong></td><td>June 12, 2026</td><td>Base Accommodation Cabin Rent (7 Days Stay Package)</td><td>$2,100.00</td><td><span class="tag-success">Fully Paid</span></td></tr>
                                    <tr><td><strong>#INV-9940</strong></td><td>June 21, 2026</td><td>Advanced Regenerative Orthopedics (PRP Treatment Line)</td><td>$1,650.00</td><td><span class="tag-warning">Balance Due</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="sub-divider-title" style="margin-top:30px;">💪 Ongoing Therapy Modality Progression tracking</div>
                        <div class="arms-display-card" style="padding:0; overflow:hidden; margin-top:10px; border:1px solid #e2e8f0; border-radius:8px;">
                            <table class="arms-display-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
                                <thead>
                                    <tr><th>Date Scheduled</th><th>Modality Category</th><th>Practitioner Notes & Milestones</th><th>Physiological Outcome Tracking</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>June 14, 2026</td><td><strong>Acupuncture</strong></td><td>Neuromuscular point stimulation across lower vertebrae structures.</td><td><span class="tag-success">Pain Scale Reduced (8 to 5)</span></td></tr>
                                    <tr><td>June 18, 2026</td><td><strong>PRP Therapy</strong></td><td>Platelet-rich plasma injection executed targeted precisely to local damage matrix.</td><td><span class="tag-success">Inflammation Stabilized</span></td></tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="arms-wiz-actions">
                        <button type="button" class="arms-m-btn b-secondary" onclick="armsSwitchPanel('sec-billing')">
                            <i class="fa-solid fa-arrow-left"></i> Back to Space Allocations
                        </button>
                        <button type="button" class="arms-m-btn b-primary" onclick="armsSwitchPanel('sec-live-allocations')">
                            Proceed to Live Occupancy Matrix <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div id="sec-live-allocations" class="arms-section-panel">
                    <div class="sub-divider-title">🛏️ Live Ward & Cabin Dynamic Occupancy Matrix</div>
                    <p class="section-context-desc" style="color: #64748b; margin-bottom: 20px; font-size: 14px;">
                        Below is an automated visual verification map tracking operational spaces across your scheduled roadmap. Green blocks indicate structural availability; Red blocks denote reserved clinical stays based on real-time room ledger evaluations.
                    </p>

                    <div class="arms-display-card" style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div class="matrix-filter-header" style="display:flex; gap:15px; margin-bottom:20px; align-items:center; flex-wrap: wrap;">
                            <div>
                                <label class="arms-m-label" style="display:block; margin-bottom:5px;">Target Accommodation View Filter</label>
                                <select id="matrix-room-filter" class="arms-input-field" style="padding:8px 12px; min-width: 180px;">
                                    <option value="all">Show All Rooms</option>
                                    <option value="Cabin">Cabins Only</option>
                                    <option value="Ward">Wards Only</option>
                                </select>
                            </div>
                            <div style="margin-top: 22px;">
                                <button type="button" class="arms-m-btn b-secondary" onclick="armsRefreshLiveMatrixMap()">
                                    <i class="fa-solid fa-sync"></i> Synchronize Operational Data Map
                                </button>
                            </div>
                        </div>

                        <div id="live-inventory-matrix-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
                            </div>

                        <div class="matrix-legend" style="display:flex; gap:20px; margin-top:25px; padding-top:15px; border-top:1px solid #f1f5f9; font-size:13px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="display:inline-block; width:16px; height:16px; background:#ef4444; border-radius:4px; border:1px solid #dc2626;"></span>
                                <strong style="color: #334155;">Occupied / Reserved Stay</strong>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="display:inline-block; width:16px; height:16px; background:#22c55e; border-radius:4px; border:1px solid #16a34a;"></span>
                                <strong style="color: #334155;">Empty / Available for Onboarding</strong>
                            </div>
                        </div>
                    </div>

                    <div class="arms-wiz-actions">
                        <button type="button" class="arms-m-btn b-secondary" onclick="armsSwitchPanel('sec-vault')">
                            <i class="fa-solid fa-arrow-left"></i> Back to Media Vault
                        </button>
                        <button type="submit" name="arms_save_unified_patient" class="arms-m-btn b-success">
                            <i class="fa-solid fa-floppy-disk"></i> <?php echo $is_edit ? 'Commit Master File Updates' : 'Complete Record Onboarding'; ?>
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <style>
        .arms-section-panel { display: none; }
        .arms-section-panel.active { display: block; }
        
        .arms-wiz-btn { 
            flex: 1; padding: 12px 18px; border: 1px solid transparent; background: transparent; 
            color: #64748b; font-weight: 600; font-size: 14px; border-radius: 8px; cursor: pointer; 
            transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .arms-wiz-btn:hover { background: #e2e8f0; color: #1e293b; }
        .arms-wiz-btn.active { background: #ffffff; color: #0284c7; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-color: #e2e8f0; }
        
        .arms-m-label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.3px; }
        .arms-input-field { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; color: #1e293b; background: #fff; box-sizing: border-box; transition: border-color 0.15s; }
        .arms-input-field:focus { border-color: #0284c7; outline: none; box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
        
        .arms-m-btn { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 10px 20px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: background 0.15s; font-size: 14px; text-decoration: none; }
        .arms-m-btn.b-primary { background: #0284c7; color: #fff; } .arms-m-btn.b-primary:hover { background: #0369a1; }
        .arms-m-btn.b-secondary { background: #f1f5f9; color: #475569; border-color: #e2e8f0; } .arms-m-btn.b-secondary:hover { background: #e2e8f0; }
        .arms-m-btn.b-success { background: #22c55e; color: #fff; } .arms-m-btn.b-success:hover { background: #16a34a; }
        
        .arms-wiz-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 35px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
        .sub-divider-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        
        /* Repeater Structural Interface Elements */
        .repeater-header-wrapper { display: grid; grid-template-columns: 140px repeat(6, 1fr) 70px; gap: 10px; background: #0f172a; color: #fff; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 12px; text-transform: uppercase; margin-bottom: 10px; text-align: center; }
        .repeater-data-item { display: grid; grid-template-columns: 140px repeat(6, 1fr) 70px; gap: 10px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 8px; align-items: center; }
        .repeater-data-item input { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; text-align: center; box-sizing: border-box; }
        .r-cell-input-wrap { position: relative; display: flex; align-items: center; }
        .r-cell-input-wrap input { padding-left: 20px; }
        .r-currency { position: absolute; left: 8px; font-size: 12px; color: #94a3b8; font-weight: 600; }
        .btn-remove-row { background: #ef4444; color: #fff; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; line-height: 1; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto; }
        .btn-remove-row:hover { background: #dc2626; }
        
        /* Account Calculations Elements */
        .summary-box-wrapper { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; text-align: right; }
        .summary-card .s-lbl { display: block; font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
        .summary-card .s-val { font-size: 18px; font-weight: 700; color: #1e293b; }
        .summary-card.highlight-total { background: #f0fdf4; border-color: #bbf7d0; }
        .summary-card.highlight-total .s-val { color: #166534; font-size: 22px; }
        
        /* Media Vault Section Grid Blocks */
        .vault-upload-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .vault-card { background: #f8fafc; border: 2px dashed #cbd5e1; padding: 20px; border-radius: 10px; text-align: center; transition: border-color 0.15s; }
        .vault-card:hover { border-color: #94a3b8; }
        .vault-card .arms-m-label { margin-top: 10px; margin-bottom: 12px; font-size: 12px; }
        .vault-card input[type="file"] { width: 100%; font-size: 11px; color: #64748b; }
        
        /* Live Inventory Cell Items Styling Overrides */
        .matrix-cell { padding: 15px; border-radius: 8px; text-align: center; color: #ffffff; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.04); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .matrix-cell:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .matrix-cell .m-date { display: block; font-size: 11px; opacity: 0.9; margin-bottom: 4px; font-weight: 400; }
        .matrix-cell .m-room { display: block; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .matrix-cell .m-status { display: block; font-size: 10px; margin-top: 6px; background: rgba(0, 0, 0, 0.15); padding: 2px 6px; border-radius: 4px; font-weight: 500; }
        .matrix-cell.occupied { background: #ef4444; border: 1px solid #dc2626; }
        .matrix-cell.vacant { background: #22c55e; border: 1px solid #16a34a; }

        /* Media Cache Output Components */
        .arms-pre-item { background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; display: flex; align-items: center; gap: 10px; position: relative; }
        .arms-pre-thumbnail { width: 45px; height: 45px; border-radius: 4px; object-fit: cover; border: 1px solid #e2e8f0; }
        .arms-pre-fallback { width: 45px; height: 45px; background: #e2e8f0; border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; }
        .arms-pre-fallback span { font-size: 7px; color: #64748b; line-height: 1; margin-top: 2px; }
        .arms-pre-meta { flex: 1; min-width: 0; padding-right: 20px; }
        .arms-pre-name { font-size: 12px; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .arms-pre-type { font-size: 10px; color: #0284c7; text-transform: uppercase; font-weight: bold; margin-top: 2px; }
        .arms-pre-remove { position: absolute; right: 8px; top: 5px; background: transparent; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 0; line-height: 1; }
        .arms-pre-remove:hover { color: #ef4444; }
    </style>

    <script>
    function armsSwitchPanel(panelId) {
        document.querySelectorAll('.arms-section-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.arms-wiz-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById(panelId).classList.add('active');
        
        if (panelId === 'sec-demo') document.getElementById('btn-sec-demo').classList.add('active');
        if (panelId === 'sec-clinic') document.getElementById('btn-sec-clinic').classList.add('active');
        if (panelId === 'sec-billing') document.getElementById('btn-sec-billing').classList.add('active');
        if (panelId === 'sec-vault') document.getElementById('btn-sec-vault').classList.add('active');
        if (panelId === 'sec-live-allocations') document.getElementById('btn-sec-live-allocations').classList.add('active');
        
        document.querySelector('.arms-m-header').scrollIntoView({ behavior: 'smooth' });
        
        // Auto trigger dynamic occupancy visual grid processing loop if loading 5th panel window
        if (panelId === 'sec-live-allocations') {
            armsRefreshLiveMatrixMap();
        }
    }

    function armsDeleteRepeaterRow(buttonInstance) {
        const rootContainer = document.getElementById('arms-repeater-root');
        if (rootContainer.children.length > 1) {
            buttonInstance.closest('.repeater-data-item').remove();
            armsReindexRepeaterAttributes();
            armsRecalculateFinancialSummary();
        } else {
            alert('A valid operational stay record requires at least 1 day entry log initialization.');
        }
    }

    function armsReindexRepeaterAttributes() {
        document.querySelectorAll('#arms-repeater-root .repeater-data-item').forEach((row, rowIndex) => {
            row.setAttribute('data-index', rowIndex);
            row.querySelectorAll('input').forEach(inputField => {
                const nameAttr = inputField.getAttribute('name');
                if (nameAttr) {
                    const cleanName = nameAttr.replace(/day_billing_ledger\[\d+\]/, `day_billing_ledger[${rowIndex}]`);
                    inputField.setAttribute('name', cleanName);
                }
            });
        });
    }

    function armsRecalculateFinancialSummary() {
        let rows = document.querySelectorAll('#arms-repeater-root .repeater-data-item');
        let totalDays = rows.length;
        
        let sumRent = 0;
        let sumConsultation = 0;
        let sumGross = 0;

        rows.forEach(row => {
            const getVal = (selector) => {
                const el = row.querySelector(selector);
                return el ? (parseFloat(el.value) || 0) : 0;
            };

            let roomRent    = getVal('.field-room-rent');
            let doctor      = getVal('.field-doctor');
            let nursing     = getVal('.field-nursing');
            let physio      = getVal('.field-physio');
            let acupuncture = getVal('.field-acupuncture');
            let prp         = getVal('.field-prp');

            sumRent         += roomRent;
            sumConsultation += doctor;
            sumGross        += (roomRent + doctor + nursing + physio + acupuncture + prp);
        });

        document.getElementById('sum-days-count').textContent = totalDays;
        document.getElementById('sum-room-rent').textContent  = '$' + sumRent.toFixed(2);
        document.getElementById('sum-clinical-visits').textContent = '$' + sumConsultation.toFixed(2);
        document.getElementById('sum-gross-total').textContent     = '$' + sumGross.toFixed(2);
    }

    function armsRefreshLiveMatrixMap() {
        const matrixContainer = document.getElementById('live-inventory-matrix-grid');
        if (!matrixContainer) return;

        matrixContainer.innerHTML = '';

        // Extract raw layout parameters from real-time dynamic configurations
        const configRoomTypeSelect = document.getElementById('room_type');
        const configRoomNoInput = document.getElementById('room_no');
        const selectedFilter = document.getElementById('matrix-room-filter').value;

        const currentRoomType = configRoomTypeSelect ? configRoomTypeSelect.value : 'Cabin';
        const currentRoomNo = (configRoomNoInput && configRoomNoInput.value.trim() !== '') ? configRoomNoInput.value.trim() : 'UNASSIGNED';

        // Filter evaluation pipeline checks
        if (selectedFilter !== 'all' && selectedFilter !== currentRoomType) {
            matrixContainer.innerHTML = `<div style="grid-column: 1/-1; padding: 25px; text-align: center; color: #94a3b8; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">No records match your selected accommodation filter criteria.</div>`;
            return;
        }

        const recordedDayRows = document.querySelectorAll('#arms-repeater-root .repeater-data-item');
        
        if (recordedDayRows.length === 0) {
            matrixContainer.innerHTML = `<div style="grid-column: 1/-1; padding: 25px; text-align: center; color: #94a3b8; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">No explicit ledger stay tracks populated to calculate mapping indices.</div>`;
            return;
        }

        // Loop structured day indices to render cell nodes
        recordedDayRows.forEach(row => {
            const dateInput = row.querySelector('input[type="date"]');
            const roomRentInput = row.querySelector('.field-room-rent');

            if (dateInput && dateInput.value) {
                const targetDateString = dateInput.value;
                const evaluatedRentAmount = roomRentInput ? parseFloat(roomRentInput.value) || 0 : 0;
                
                // Rule: If room rent is actively mapped (> 0), block status evaluates to Occupied (Red). Otherwise Vacant (Green).
                const isOccupied = evaluatedRentAmount > 0;
                const executionClass = isOccupied ? 'occupied' : 'vacant';
                const labelTextText = isOccupied ? 'RESERVED' : 'AVAILABLE';

                const structuralNodeString = `
                    <div class="matrix-cell ${executionClass}">
                        <span class="m-date">${targetDateString}</span>
                        <span class="m-room">${currentRoomType} ${currentRoomNo}</span>
                        <span class="m-status">${labelTextText}</span>
                    </div>
                `;
                matrixContainer.insertAdjacentHTML('beforeend', structuralNodeString);
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const rootContainer = document.getElementById('arms-repeater-root');
        const addBtnTrigger = document.getElementById('arms-add-row-trigger');

        // Dynamic Calculation Event Listener Delegation
        rootContainer.addEventListener('input', function(e) {
            if (e.target.classList.contains('r-calc-trigger')) {
                armsRecalculateFinancialSummary();
            }
        });

        // Event listener mapping for filters configuration change triggers
        const matrixFilterEl = document.getElementById('matrix-room-filter');
        if (matrixFilterEl) {
            matrixFilterEl.addEventListener('change', armsRefreshLiveMatrixMap);
        }

        // Dynamic row generation management click controller
        if (addBtnTrigger) {
            addBtnTrigger.addEventListener('click', function() {
                const nextIndex = rootContainer.children.length;
                
                let fallbackDate = new Date().toISOString().split('T')[0];
                const lastDateInput = rootContainer.querySelector(`.repeater-data-item:last-child input[type="date"]`);
                if (lastDateInput && lastDateInput.value) {
                    let tempDate = new Date(lastDateInput.value);
                    tempDate.setDate(tempDate.getDate() + 1);
                    if (!isNaN(tempDate.getTime())) {
                        fallbackDate = tempDate.toISOString().split('T')[0];
                    }
                }

                const nodeTemplate = `
                    <div class="repeater-data-item" data-index="${nextIndex}">
                        <div>
                            <input type="date" name="day_billing_ledger[${nextIndex}][date]" class="r-calc-trigger" value="${fallbackDate}" required />
                        </div>
                        <div class="r-cell-input-wrap">
                            <span class="r-currency">$</span>
                            <input type="number" name="day_billing_ledger[${nextIndex}][room_rent]" class="r-calc-trigger field-room-rent" min="0" step="any" value="0" />
                        </div>
                        <div class="r-cell-input-wrap">
                            <span class="r-currency">$</span>
                            <input type="number" name="day_billing_ledger[${nextIndex}][doctor]" class="r-calc-trigger field-doctor" min="0" step="any" value="0" />
                        </div>
                        <div class="r-cell-input-wrap">
                            <span class="r-currency">$</span>
                            <input type="number" name="day_billing_ledger[${nextIndex}][nursing]" class="r-calc-trigger field-nursing" min="0" step="any" value="0" />
                        </div>
                        <div class="r-cell-input-wrap">
                            <span class="r-currency">$</span>
                            <input type="number" name="day_billing_ledger[${nextIndex}][physio]" class="r-calc-trigger field-physio" min="0" step="any" value="0" />
                        </div>
                        <div class="r-cell-input-wrap">
                            <span class="r-currency">$</span>
                            <input type="number" name="day_billing_ledger[${nextIndex}][acupuncture]" class="r-calc-trigger field-acupuncture" min="0" step="any" value="0" />
                        </div>
                        <div class="r-cell-input-wrap">
                            <span class="r-currency">$</span>
                            <input type="number" name="day_billing_ledger[${nextIndex}][prp]" class="r-calc-trigger field-prp" min="0" step="any" value="0" />
                        </div>
                        <div>
                            <button type="button" class="btn-remove-row" onclick="armsDeleteRepeaterRow(this)">&times;</button>
                        </div>
                    </div>`;
                
                rootContainer.insertAdjacentHTML('beforeend', nodeTemplate);
                armsRecalculateFinancialSummary();
            });
        }

        // Profile local upload reader execution logic
        const profileInput = document.getElementById('patient_profile_pic');
        const avatarPreview = document.getElementById('patient-avatar-view');
        if (profileInput && avatarPreview) {
            profileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) { avatarPreview.src = e.target.result; }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // Asynchronous multi-vault upload pipeline cache handling processes
        const fileInputs = document.querySelectorAll('.vault-card input[type="file"]');
        const preWrapper = document.getElementById('arms-m-preview-wrapper');
        const preGrid    = document.getElementById('arms-m-preview-grid');
        let liveFilesCache = { doc_mri: [], doc_xray: [], doc_lab: [], doc_patient_images: [] };

        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                const cacheKey = input.id === 'doc_patient_images' ? 'doc_patient_images' : input.id;
                if (input.files && input.files.length > 0) {
                    if (input.id === 'doc_patient_images') {
                        liveFilesCache[cacheKey] = liveFilesCache[cacheKey].concat(Array.from(input.files));
                    } else {
                        liveFilesCache[cacheKey] = Array.from(input.files);
                    }
                }
                syncInputsAndRenderPreviews();
            });
        });

        function syncInputsAndRenderPreviews() {
            preGrid.innerHTML = '';
            let totalFiles = 0;

            fileInputs.forEach(input => {
                const cacheKey = input.id === 'doc_patient_images' ? 'doc_patient_images' : input.id;
                const activeArr = liveFilesCache[cacheKey];
                const displayTitle = input.getAttribute('data-title') || 'Document';

                const transferObj = new DataTransfer();
                activeArr.forEach(f => transferObj.items.add(f));
                input.files = transferObj.files;

                if (activeArr.length > 0) {
                    totalFiles += activeArr.length;
                    activeArr.forEach((file, idx) => {
                        const item = document.createElement('div');
                        item.className = 'arms-pre-item';

                        if (file.type.match('image.*')) {
                            const img = document.createElement('img');
                            img.className = 'arms-pre-thumbnail';
                            img.src = URL.createObjectURL(file);
                            img.onload = function() { URL.revokeObjectURL(this.src); }
                            item.appendChild(img);
                        } else {
                            const fallback = document.createElement('div');
                            fallback.className = 'arms-pre-fallback';
                            fallback.innerHTML = file.name.toLowerCase().endsWith('.pdf') ? '📕 <span>PDF DOC</span>' : '📄 <span>FILE</span>';
                            item.appendChild(fallback);
                        }

                        const meta = document.createElement('div');
                        meta.className = 'arms-pre-meta';
                        
                        const nameLbl = document.createElement('div');
                        nameLbl.className = 'arms-pre-name';
                        nameLbl.textContent = file.name;
                        nameLbl.title = file.name;

                        const typeLbl = document.createElement('div');
                        typeLbl.className = 'arms-pre-type';
                        typeLbl.textContent = displayTitle;

                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.className = 'arms-pre-remove';
                        delBtn.innerHTML = '&times;';
                        delBtn.addEventListener('click', function() {
                            liveFilesCache[cacheKey].splice(idx, 1);
                            syncInputsAndRenderPreviews();
                        });

                        meta.appendChild(nameLbl);
                        meta.appendChild(typeLbl);
                        meta.appendChild(delBtn);
                        item.appendChild(meta);
                        preGrid.appendChild(item);
                    });
                }
            });
            preWrapper.style.display = totalFiles > 0 ? 'block' : 'none';
        }

        // Initialize execution on runtime loop deployment
        armsRecalculateFinancialSummary();
    });
    </script>
    <?php
}

/**
 * Backend Processing Controller - Intercepts Submissions, Sanitizes Datasets,
 * Manages Multi-Stream Asset Upload Routing Pipelines, and Stabilizes Database Commits.
 */
function arms_process_patient_admission_form_submission() {
    if ( ! isset( $_POST['arms_save_unified_patient'] ) || ! isset( $_POST['arms_unified_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['arms_unified_nonce'], 'arms_save_unified_patient_action' ) ) {
        wp_die( esc_html__( 'Security validation failed. Intended payload verification rejected.', 'arms' ) );
    }

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Privilege escalation alert. Access to process clinical files denied.', 'arms' ) );
    }

    global $wpdb;
    $patient_table = $wpdb->prefix . 'arms_patients';

    $patient_id  = isset( $_POST['patient_id'] ) ? intval( $_POST['patient_id'] ) : 0;
    $action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : 'create';
    
    // Build array layout parameters block
    $sanitized_data = array(
        'name'              => isset( $_POST['patient_name'] ) ? sanitize_text_field( $_POST['patient_name'] ) : '',
        'age'               => isset( $_POST['patient_age'] ) ? intval( $_POST['patient_age'] ) : 0,
        'gender'            => isset( $_POST['patient_gender'] ) ? sanitize_text_field( $_POST['patient_gender'] ) : 'Male',
        'mobile'            => isset( $_POST['patient_mobile'] ) ? sanitize_text_field( $_POST['patient_mobile'] ) : '',
        'emergency'         => isset( $_POST['patient_emergency'] ) ? sanitize_text_field( $_POST['patient_emergency'] ) : '',
        'address'           => isset( $_POST['patient_address'] ) ? sanitize_textarea_field( $_POST['patient_address'] ) : '',
        'room_type'         => isset( $_POST['room_type'] ) ? sanitize_text_field( $_POST['room_type'] ) : 'Cabin',
        'room_no'           => isset( $_POST['room_no'] ) ? sanitize_text_field( $_POST['room_no'] ) : '',
        'admission_date'    => isset( $_POST['admission_date'] ) ? sanitize_text_field( $_POST['admission_date'] ) : date('Y-m-d'),
        'initial_diagnosis' => isset( $_POST['patient_initial_diagnosis'] ) ? sanitize_textarea_field( $_POST['patient_initial_diagnosis'] ) : '',
        'custom_diagnosis'  => isset( $_POST['patient_custom_diagnosis'] ) ? sanitize_textarea_field( $_POST['patient_custom_diagnosis'] ) : '',
        'status'            => 'Active Stay'
    );

    // Filter checkbox values
    $conditions_saved = array();
    if ( isset( $_POST['medical_conditions'] ) && is_array( $_POST['medical_conditions'] ) ) {
        $allowed_conditions = array( 'stroke', 'paralysis', 'plid', 'sci', 'osteoarthritis' );
        foreach ( $_POST['medical_conditions'] as $condition ) {
            $clean_cond = sanitize_key( $condition );
            if ( in_array( $clean_cond, $allowed_conditions, true ) ) {
                $conditions_saved[] = $clean_cond;
            }
        }
    }
    $sanitized_data['conditions'] = wp_json_encode( $conditions_saved );

    // Parse repeater structures
    $sanitized_ledger = array();
    if ( isset( $_POST['day_billing_ledger'] ) && is_array( $_POST['day_billing_ledger'] ) ) {
        foreach ( $_POST['day_billing_ledger'] as $row ) {
            if ( ! isset( $row['date'] ) ) continue;
            $sanitized_ledger[] = array(
                'date'        => sanitize_text_field( $row['date'] ),
                'room_rent'   => isset( $row['room_rent'] ) ? floatval( $row['room_rent'] ) : 0.00,
                'doctor'      => isset( $row['doctor'] ) ? floatval( $row['doctor'] ) : 0.00,
                'nursing'     => isset( $row['nursing'] ) ? floatval( $row['nursing'] ) : 0.00,
                'physio'      => isset( $row['physio'] ) ? floatval( $row['physio'] ) : 0.00,
                'acupuncture' => isset( $row['acupuncture'] ) ? floatval( $row['acupuncture'] ) : 0.00,
                'prp'         => isset( $row['prp'] ) ? floatval( $row['prp'] ) : 0.00,
            );
        }
    }
    $sanitized_data['day_billing_ledger'] = wp_json_encode( $sanitized_ledger );

    // Sync original file properties for update contexts to prevent record loss
    $existing_vault_meta = array();
    if ( $action_type === 'update' && $patient_id > 0 ) {
        $old_record = $wpdb->get_row( $wpdb->prepare("SELECT media_vault_urls FROM $patient_table WHERE id = %d", $patient_id) );
        if ( $old_record && ! empty($old_record->media_vault_urls) ) {
            $existing_vault_meta = json_decode($old_record->media_vault_urls, true) ?: array();
        }
    }

    // Core WP Media Handlers Initialization Dependencies
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );

    $vault_keys = array( 'patient_profile_pic', 'doc_mri', 'doc_xray', 'doc_lab' );
    foreach ( $vault_keys as $key ) {
        if ( isset( $_FILES[$key] ) && ! empty( $_FILES[$key]['name'] ) ) {
            $attachment_id = media_handle_upload( $key, 0 );
            if ( ! is_wp_error( $attachment_id ) ) {
                $existing_vault_meta[$key] = wp_get_attachment_url( $attachment_id );
            }
        }
    }

    // Capture Multiple Image Upload Arrays for Posture Targets
    if ( isset( $_FILES['doc_patient_images'] ) && is_array( $_FILES['doc_patient_images']['name'] ) ) {
        $files = $_FILES['doc_patient_images'];
        $uploaded_images_urls = isset($existing_vault_meta['doc_patient_images']) && is_array($existing_vault_meta['doc_patient_images']) ? $existing_vault_meta['doc_patient_images'] : array();

        foreach ( $files['name'] as $key => $value ) {
            if ( $files['name'][$key] ) {
                $_FILES['arms_temp_upload'] = array(
                    'name'     => $files['name'][$key],
                    'type'     => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error'    => $files['error'][$key],
                    'size'     => $files['size'][$key]
                );

                $attachment_id = media_handle_upload( 'arms_temp_upload', 0 );
                if ( ! is_wp_error( $attachment_id ) ) {
                    $uploaded_images_urls[] = wp_get_attachment_url( $attachment_id );
                }
            }
        }
        if ( ! empty( $uploaded_images_urls ) ) {
            $existing_vault_meta['doc_patient_images'] = $uploaded_images_urls;
        }
    }

    $sanitized_data['media_vault_urls'] = wp_json_encode( $existing_vault_meta );

    // Execution Context Split Strategy Routing
    if ( $action_type === 'update' && $patient_id > 0 ) {
        $wpdb->update(
            $patient_table,
            $sanitized_data,
            array( 'id' => $patient_id ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        $redirect_url = add_query_arg( array( 'page' => 'arms-patients', 'action' => 'edit', 'patient_id' => $patient_id, 'updated' => 'true' ), admin_url( 'admin.php' ) );
    } else {
        $wpdb->insert(
            $patient_table,
            $sanitized_data,
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        $new_id = $wpdb->insert_id;
        $redirect_url = add_query_arg( array( 'page' => 'arms-patients', 'action' => 'edit', 'patient_id' => $new_id, 'created' => 'true' ), admin_url( 'admin.php' ) );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_init', 'arms_process_patient_admission_form_submission' );