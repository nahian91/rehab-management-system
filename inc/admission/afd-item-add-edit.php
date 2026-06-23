<?php
if (!defined('ABSPATH')) exit;

/**
 * Render the Advanced Admission, Bed Allocation, Daily Charges, 
 * Discharge Protocols, and Dynamic Auto-Billing Treatment History Matrix.
 * * @param int $patient_id Optional. Pass a valid ID to switch to editing/auditing mode.
 */
function arms_add_edit_admission_form( $patient_id = 0 ) {
    global $wpdb;
    $patient_id = intval($patient_id);
    $is_edit    = ($patient_id > 0);

    // Default structural schema initialization map
    $admission_data = array(
        'status'            => 'Active Stay',
        'room_type'         => 'Cabin',
        'room_no'           => '',
        'ward_bed_no'       => '',
        'admission_date'    => date('Y-m-d'),
        'discharge_date'    => '',
        'discharge_summary' => '',
        'payment_status'    => 'Unpaid',
        'final_bill_amount' => 0,
        'repeater_charges'  => array(
            array(
                'room_rent'          => 0,
                'nursing_charge'     => 0,
                'physio_charge'      => 0,
                'doctor_charge'      => 0,
                'acupuncture_charge' => 0,
                'prp_charge'         => 0
            )
        )
    );

    // DB Hydration simulation block safely handling post/meta frameworks
    if ( $is_edit ) {
        $meta_fields = array(
            'status', 'room_type', 'room_no', 'ward_bed_no', 
            'admission_date', 'discharge_date', 'discharge_summary', 
            'payment_status', 'final_bill_amount', 'repeater_charges'
        );
        foreach ( $meta_fields as $field ) {
            $saved_val = get_post_meta( $patient_id, '_arms_' . $field, true );
            if ( $saved_val !== '' ) {
                $admission_data[$field] = $saved_val;
            }
        }
    }

    // Dynamic Fetching of Patients from WP User Registry Database
    $patients_query = get_users( array( 'role' => 'subscriber', 'number' => -1 ) );
    if ( empty( $patients_query ) ) {
        $patients_query = get_posts( array( 'post_type' => 'patient', 'posts_per_page' => -1 ) );
    }
    ?>

    <style>
        .arms-adm-wrapper { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); max-width: 1200px; margin: 20px auto; }
        .arms-adm-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 24px; }
        .arms-adm-header h2 { margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #0f172a; }
        .arms-adm-header p { margin: 0; font-size: 13px; color: #64748b; }
        .arms-status-badge { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .arms-nav-bar { display: flex; gap: 8px; border-bottom: 2px solid #f1f5f9; margin-bottom: 24px; padding-bottom: 1px; }
        .arms-nav-btn { background: none; border: none; padding: 12px 16px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s ease; }
        .arms-nav-btn:hover { color: #0f172a; }
        .arms-nav-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
        .arms-pane { display: none; }
        .arms-pane.active { display: block; animation: armsFadeIn 0.2s ease-in-out; }
        .arms-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; margin-bottom: 24px; }
        .col-12 { grid-column: span 12; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }
        .section-subtitle { grid-column: span 12; font-size: 14px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; border-left: 3px solid #2563eb; padding-left: 10px; margin: 16px 0 8px 0; }
        .arms-fgroup { display: flex; flex-direction: column; gap: 6px; }
        .arms-label { font-size: 13px; font-weight: 600; color: #334155; }
        .arms-input, .arms-select, .arms-textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; color: #334155; background: #fff; box-sizing: border-box; }
        .arms-input:focus, .arms-select:focus, .arms-textarea:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .input-addon-wrap { position: relative; display: flex; align-items: center; }
        .input-addon-wrap .addon { position: absolute; left: 12px; color: #94a3b8; font-size: 14px; }
        .input-addon-wrap .arms-input { padding-left: 28px; }
        
        /* Repeater Specific Engine Layout CSS Styles */
        .arms-repeater-container { grid-column: span 12; display: flex; flex-direction: column; gap: 12px; }
        .arms-repeater-row { display: grid; grid-template-columns: repeat(6, 1fr) 45px; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; align-items: flex-end; position: relative; }
        .arms-repeater-row .arms-fgroup { grid-column: auto; }
        .arms-btn-remove { background: #ef4444; color: #fff; border: none; padding: 9px; border-radius: 6px; cursor: pointer; text-align: center; font-size: 14px; line-height: 1; transition: background 0.2s; height: 38px; display: flex; align-items: center; justify-content: center; }
        .arms-btn-remove:hover { background: #dc2626; }
        .arms-btn-add-row { background: #0f172a; color: #fff; border: none; padding: 10px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; width: max-content; transition: background 0.2s; margin-top: 8px; }
        .arms-btn-add-row:hover { background: #1e293b; }
        
        /* Auto Bill Generation Summary Cards Layout Block */
        .arms-billing-summary-box { grid-column: span 12; background: #f0f5ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 20px; margin-top: 16px; }
        .arms-bill-grid { display: flex; justify-content: space-around; text-align: center; }
        .arms-bill-stat h4 { margin: 0 0 4px 0; font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
        .arms-bill-stat .stat-price { font-size: 24px; font-weight: 800; color: #1e3a8a; }
        .arms-bill-divider { border-left: 1px solid #chd5e1; height: 40px; align-self: center; }
        
        .form-actions { display: flex; justify-content: space-between; items-items: center; margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .arms-btn { padding: 10px 20px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent; transition: all 0.2s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #fff; color: #475569; border-color: #cbd5e1; }
        .btn-secondary:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-print { background: #7c3aed; color: #fff; }
        .btn-print:hover { background: #6d28d9; }
        
        @keyframes armsFadeIn { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: translateY(0); } }

        /* Print Override Media Pipeline Blueprint */
        @media print {
            body * { visibility: hidden; }
            .arms-adm-wrapper, .arms-adm-wrapper * { visibility: visible; }
            .arms-adm-wrapper { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; padding: 0; }
            .arms-nav-bar, .arms-btn-add-row, .arms-btn-remove, .form-actions, .arms-pane { display: none !important; }
            .arms-pane.active { display: block !important; }
            .arms-repeater-row { grid-template-columns: repeat(6, 1fr) !important; border: 1px solid #000 !important; background: #fff !important; padding: 8px !important; }
            .arms-repeater-row div:last-child { display: none !important; }
            input, select, textarea { border: none !important; background: transparent !important; padding: 0 !important; appearance: none; -webkit-appearance: none; color: #000 !important; font-weight: bold; }
            .input-addon-wrap .addon { display: inline !important; position: static !important; padding-right: 2px; }
            .input-addon-wrap input { display: inline !important; padding-left: 0 !important; }
        }
    </style>

    <div class="arms-adm-wrapper" id="arms-printable-area">
        <div class="arms-adm-header">
            <div>
                <h2><?php echo $is_edit ? 'Manage Admission Ledger Blueprint' : 'Patient Admission & Clinical Tracking Console'; ?></h2>
                <p>Track spatial configurations, multi-tier transactional logs, and unified continuous auto-billing frameworks cleanly.</p>
            </div>
            <div>
                <span class="arms-status-badge">🟢 <?php echo esc_html($admission_data['status']); ?></span>
            </div>
        </div>

        <div class="arms-nav-bar">
            <button type="button" class="arms-nav-btn active" id="tab-btn-alloc" onclick="armsMovePane('pane-alloc')">
                <i class="fa-solid fa-bed"></i> 1. Structural Intake & Allocation
            </button>
            <button type="button" class="arms-nav-btn" id="tab-btn-discharge" onclick="armsMovePane('pane-discharge')">
                <i class="fa-solid fa-door-open"></i> 2. Clinical Discharge Protocol
            </button>
        </div>

        <form method="POST" action="" id="arms-admission-master-form">
            <?php wp_nonce_field('arms_admission_security_lock', 'arms_admission_nonce'); ?>
            <input type="hidden" name="patient_id" value="<?php echo esc_attr($patient_id); ?>" />

            <div id="pane-alloc" class="arms-pane active">
                <div class="arms-grid">
                    <div class="section-subtitle">Core Infrastructure Selection Matrix</div>
                    
                    <div class="arms-fgroup col-12">
                        <label class="arms-label">Select Registered Patient Database Target Record</label>
                        <select name="arms_selected_patient_id" class="arms-select" required>
                            <option value="">-- Choose Account Record Reference Line --</option>
                            <?php 
                            if ( ! empty( $patients_query ) ) {
                                foreach ( $patients_query as $p_obj ) {
                                    if ( isset($p_obj->ID) ) {
                                        $p_id = $p_obj->ID;
                                        $p_display = $p_obj->display_name . ' (' . $p_obj->user_email . ')';
                                    } else {
                                        $p_id = $p_obj->ID;
                                        $p_display = get_the_title($p_obj->ID);
                                    }
                                    echo '<option value="' . esc_attr($p_id) . '" ' . selected($patient_id, $p_id, false) . '>' . esc_html($p_display) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Room Allocation Topology</label>
                        <select name="room_type" id="arms_room_type_select" class="arms-select" onchange="armsToggleSpatialFields()" required>
                            <option value="Cabin" <?php selected($admission_data['room_type'], 'Cabin'); ?>>Cabin (Single Luxury Suite)</option>
                            <option value="Ward Bed" <?php selected($admission_data['room_type'], 'Ward Bed'); ?>>General Ward Bed Layout</option>
                        </select>
                    </div>

                    <div class="arms-fgroup col-4" id="arms-group-cabin" style="<?php echo ($admission_data['room_type'] === 'Cabin') ? '' : 'display:none;'; ?>">
                        <label class="arms-label">Assigned Cabin Room Number</label>
                        <input type="text" name="room_no" class="arms-input" placeholder="e.g. Cabin-402" value="<?php echo esc_attr($admission_data['room_no']); ?>" />
                    </div>

                    <div class="arms-fgroup col-4" id="arms-group-ward" style="<?php echo ($admission_data['room_type'] === 'Ward Bed') ? '' : 'display:none;'; ?>">
                        <label class="arms-label">Assigned Ward Bed Index Line</label>
                        <input type="text" name="ward_bed_no" class="arms-input" placeholder="e.g. Ward-B / Bed-12" value="<?php echo esc_attr($admission_data['ward_bed_no']); ?>" />
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Admission Ingress Date</label>
                        <input type="date" name="admission_date" class="arms-input" value="<?php echo esc_attr($admission_data['admission_date']); ?>" required />
                    </div>

                    <div class="section-subtitle">Multi-Tier Treatment Metrics Service Logs (Daily Charge Multipliers)</div>
                    
                    <div class="arms-repeater-container" id="arms-repeater-root">
                        <?php 
                        $idx = 0;
                        foreach ( $admission_data['repeater_charges'] as $row ) { 
                            $r_rent = isset($row['room_rent']) ? $row['room_rent'] : 0;
                            $n_chg  = isset($row['nursing_charge']) ? $row['nursing_charge'] : 0;
                            $p_chg  = isset($row['physio_charge']) ? $row['physio_charge'] : 0;
                            $d_chg  = isset($row['doctor_charge']) ? $row['doctor_charge'] : 0;
                            $a_chg  = isset($row['acupuncture_charge']) ? $row['acupuncture_charge'] : 0;
                            $prp_c  = isset($row['prp_charge']) ? $row['prp_charge'] : 0;
                            ?>
                            <div class="arms-repeater-row" data-index="<?php echo $idx; ?>">
                                <div class="arms-fgroup">
                                    <label class="arms-label">Room Rent ($)</label>
                                    <input type="number" name="repeater_charges[<?php echo $idx; ?>][room_rent]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr($r_rent); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Nursing ($)</label>
                                    <input type="number" name="repeater_charges[<?php echo $idx; ?>][nursing_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr($n_chg); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Physiotherapy ($)</label>
                                    <input type="number" name="repeater_charges[<?php echo $idx; ?>][physio_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr($p_chg); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Doctor Visit ($)</label>
                                    <input type="number" name="repeater_charges[<?php echo $idx; ?>][doctor_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr($d_chg); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Acupuncture ($)</label>
                                    <input type="number" name="repeater_charges[<?php echo $idx; ?>][acupuncture_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr($a_chg); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">PRP Charge ($)</label>
                                    <input type="number" name="repeater_charges[<?php echo $idx; ?>][prp_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr($prp_c); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div>
                                    <button type="button" class="arms-btn-remove" onclick="armsRemoveRepeaterRow(this)"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </div>
                            <?php 
                            $idx++;
                        } 
                        ?>
                    </div>

                    <div class="col-12">
                        <button type="button" class="arms-btn-add-row" onclick="armsAddRepeaterRow()"><i class="fa-solid fa-plus"></i> Append Continuous Entry Row</button>
                    </div>

                    <div class="arms-billing-summary-box">
                        <div class="arms-bill-grid">
                            <div class="arms-bill-stat">
                                <h4>Dynamic Global Accrued Gross Target</h4>
                                <div class="stat-price" id="arms-bill-gross-view">$0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <div></div>
                    <button type="button" class="arms-btn btn-primary" onclick="armsMovePane('pane-discharge')">
                        Proceed To Final Protocols Summary <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div id="pane-discharge" class="arms-pane">
                <div class="arms-grid">
                    <div class="section-subtitle">Clinical Outgress Closure Setup</div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Discharge Event Closure Date</label>
                        <input type="date" name="discharge_date" class="arms-input" value="<?php echo esc_attr($admission_data['discharge_date']); ?>" />
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Computed Aggregate Liability Amount ($)</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="final_bill_amount" id="arms_final_bill_amount" class="arms-input" value="<?php echo esc_attr($admission_data['final_bill_amount']); ?>" readonly />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Invoice Ledger Balance Status Map</label>
                        <select name="payment_status" class="arms-select">
                            <option value="Unpaid" <?php selected($admission_data['payment_status'], 'Unpaid'); ?>>🔴 Unpaid Balance Arrears Outstanding</option>
                            <option value="Partially Paid" <?php selected($admission_data['payment_status'], 'Partially Paid'); ?>>🟡 Partially Settled Ledger Sub-Account</option>
                            <option value="Paid" <?php selected($admission_data['payment_status'], 'Paid'); ?>>🟢 Fully Cleared Capital Processing Status</option>
                        </select>
                    </div>

                    <div class="arms-fgroup col-12">
                        <label class="arms-label">Comprehensive Treatment Clinical Outcome Summary Log</label>
                        <textarea name="discharge_summary" class="arms-textarea" rows="6" placeholder="Compile recovery trajectories, updates, medication requirements, and follow-up protocols safely..."><?php echo esc_html($admission_data['discharge_summary']); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="arms-btn btn-secondary" onclick="armsMovePane('pane-alloc')">
                        <i class="fa-solid fa-arrow-left"></i> Back to Resource Allocation
                    </button>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="arms-btn btn-print" onclick="armsExecutePrintReceipt()">
                            <i class="fa-solid fa-print"></i> Generate Invoice & Print
                        </button>
                        
                        <button type="submit" name="arms_save_admission_action" class="arms-btn btn-success">
                            <i class="fa-solid fa-floppy-disk"></i> <?php echo $is_edit ? 'Commit Records Update' : 'Finalize Records Deployment'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
    function armsMovePane(paneId) {
        document.querySelectorAll('.arms-pane').forEach(function(pane) {
            pane.classList.remove('active');
        });
        document.querySelectorAll('.arms-nav-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        
        document.getElementById(paneId).classList.add('active');
        
        if (paneId === 'pane-alloc') document.getElementById('tab-btn-alloc').classList.add('active');
        if (paneId === 'pane-discharge') document.getElementById('tab-btn-discharge').classList.add('active');
        
        document.querySelector('.arms-adm-header').scrollIntoView({ behavior: 'smooth' });
    }

    function armsToggleSpatialFields() {
        const typeSelect = document.getElementById('arms_room_type_select').value;
        const cabinGroup = document.getElementById('arms-group-cabin');
        const wardGroup = document.getElementById('arms-group-ward');
        
        if (typeSelect === 'Cabin') {
            cabinGroup.style.display = '';
            wardGroup.style.display = 'none';
            wardGroup.querySelector('input').value = '';
        } else {
            cabinGroup.style.display = 'none';
            wardGroup.style.display = '';
            cabinGroup.querySelector('input').value = '';
        }
    }

    function armsCalculateLiveBillingTotals() {
        let cumulativeTotal = 0;
        const inputRows = document.querySelectorAll('#arms-repeater-root .arms-repeater-row');
        
        inputRows.forEach(function(row) {
            const numericInputs = row.querySelectorAll('.arms-calc-trigger');
            numericInputs.forEach(function(inputElement) {
                let parsedVal = parseFloat(inputElement.value);
                if (!isNaN(parsedVal) && parsedVal > 0) {
                    cumulativeTotal += parsedVal;
                }
            });
        });
        
        // Match calculation indexes back directly to DOM fields gracefully
        document.getElementById('arms-bill-gross-view').innerText = '$' + cumulativeTotal.toFixed(2);
        document.getElementById('arms_final_bill_amount').value = cumulativeTotal.toFixed(2);
    }

    function armsAddRepeaterRow() {
        const rootContainer = document.getElementById('arms-repeater-root');
        const rowsCurrent = rootContainer.querySelectorAll('.arms-repeater-row');
        let maximumIndex = 0;
        
        rowsCurrent.forEach(function(row) {
            let currentIdx = parseInt(row.getAttribute('data-index'));
            if (currentIdx > maximumIndex) {
                maximumIndex = currentIdx;
            }
        });
        
        const runtimeNewIndex = maximumIndex + 1;
        const tableRowBlueprint = `
            <div class="arms-repeater-row" data-index="${runtimeNewIndex}">
                <div class="arms-fgroup">
                    <label class="arms-label">Room Rent ($)</label>
                    <input type="number" name="repeater_charges[${runtimeNewIndex}][room_rent]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Nursing ($)</label>
                    <input type="number" name="repeater_charges[${runtimeNewIndex}][nursing_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Physiotherapy ($)</label>
                    <input type="number" name="repeater_charges[${runtimeNewIndex}][physio_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Doctor Visit ($)</label>
                    <input type="number" name="repeater_charges[${runtimeNewIndex}][doctor_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Acupuncture ($)</label>
                    <input type="number" name="repeater_charges[${runtimeNewIndex}][acupuncture_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">PRP Charge ($)</label>
                    <input type="number" name="repeater_charges[${runtimeNewIndex}][prp_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div>
                    <button type="button" class="arms-btn-remove" onclick="armsRemoveRepeaterRow(this)"><i class="fa-solid fa-trash-can"></i></button>
                </div>
            </div>`;
            
        rootContainer.insertAdjacentHTML('beforeend', tableRowBlueprint);
        armsCalculateLiveBillingTotals();
    }

    function armsRemoveRepeaterRow(btnHandle) {
        const targetRowElement = btnHandle.closest('.arms-repeater-row');
        const rootContainer = document.getElementById('arms-repeater-root');
        
        if (rootContainer.querySelectorAll('.arms-repeater-row').length > 1) {
            targetRowElement.remove();
            armsCalculateLiveBillingTotals();
        } else {
            alert('Core Tracking Error: The system requires a minimum structural retention layer of 1 active charge data row.');
        }
    }

    function armsExecutePrintReceipt() {
        // Enforce synchronization verification checks prior to device printer frame execution
        armsCalculateLiveBillingTotals();
        window.print();
    }

    // Trigger initialization loops upon clean DOM structural ready event states
    document.addEventListener("DOMContentLoaded", function() {
        armsCalculateLiveBillingTotals();
    });
    </script>
    <?php
}