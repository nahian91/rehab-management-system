<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * =========================================================================
 * 1. ADMISSION DATA TRANSACTION & SAVING ENGINE (CUSTOM DB ENGINE)
 * =========================================================================
 */
function arms_process_admission_actions() {
    global $wpdb;

    if ( ! isset( $_POST['arms_admission_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['arms_admission_nonce'], 'arms_admission_security_lock' ) ) {
        return;
    }

    if ( isset( $_POST['arms_save_admission_action'] ) ) {
        $admission_id = isset( $_POST['patient_id'] ) ? intval( $_POST['patient_id'] ) : 0; 
        
        $selected_patient_id = isset( $_POST['arms_selected_patient_id'] ) ? intval( $_POST['arms_selected_patient_id'] ) : 0;
        if ( $selected_patient_id <= 0 ) {
            wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=patients&sub=history&error=missing_patient_target' ) );
            exit;
        }

        $room_type         = isset( $_POST['room_type'] ) ? sanitize_text_field( $_POST['room_type'] ) : 'Cabin';
        $room_no           = ( 'Cabin' === $room_type && isset( $_POST['room_no'] ) ) ? sanitize_text_field( $_POST['room_no'] ) : '';
        $ward_bed_no       = ( 'Ward Bed' === $room_type && isset( $_POST['ward_bed_no'] ) ) ? sanitize_text_field( $_POST['ward_bed_no'] ) : '';
        $admission_date    = isset( $_POST['admission_date'] ) ? sanitize_text_field( $_POST['admission_date'] ) : date( 'Y-m-d' );
        $advance_payment   = isset( $_POST['advance_payment'] ) ? max( 0, floatval( $_POST['advance_payment'] ) ) : 0;
        $discharge_date    = ! empty( $_POST['discharge_date'] ) ? sanitize_text_field( $_POST['discharge_date'] ) : null;
        $payment_status    = isset( $_POST['payment_status'] ) ? sanitize_text_field( $_POST['payment_status'] ) : 'Unpaid';
        $final_bill_amount = isset( $_POST['final_bill_amount'] ) ? floatval( $_POST['final_bill_amount'] ) : 0;
        $discharge_summary = isset( $_POST['discharge_summary'] ) ? sanitize_textarea_field( $_POST['discharge_summary'] ) : '';

        $table_admissions = $wpdb->prefix . 'arms_admissions';
        $table_charges    = $wpdb->prefix . 'arms_admission_charges';

        $master_data = array(
            'patient_id'        => $selected_patient_id,
            'room_type'         => $room_type,
            'room_no'           => $room_no,
            'ward_bed_no'       => $ward_bed_no,
            'admission_date'    => $admission_date,
            'advance_payment'   => $advance_payment,
            'discharge_date'    => $discharge_date,
            'final_bill_amount' => $final_bill_amount, 
            'payment_status'    => $payment_status,
            'discharge_summary' => $discharge_summary,
        );

        $master_format = array( '%d', '%s', '%s', '%s', '%s', '%f', $discharge_date ? '%s' : '%s', '%f', '%s', '%s' );

        if ( $admission_id > 0 ) {
            $wpdb->update( $table_admissions, $master_data, array( 'id' => $admission_id ), $master_format, array( '%d' ) );
            $target_admission_id = $admission_id;
        } else {
            $wpdb->insert( $table_admissions, $master_data, $master_format );
            $target_admission_id = $wpdb->insert_id;
        }

        if ( $target_admission_id > 0 ) {
            $wpdb->delete( $table_charges, array( 'admission_id' => $target_admission_id ), array( '%d' ) );

            if ( isset( $_POST['repeater_charges'] ) && is_array( $_POST['repeater_charges'] ) ) {
                foreach ( $_POST['repeater_charges'] as $index => $row ) {
                    $wpdb->insert(
                        $table_charges,
                        array(
                            'admission_id'       => $target_admission_id,
                            'row_index'          => intval( $index ),
                            'charge_date'        => isset( $row['charge_date'] ) ? sanitize_text_field( $row['charge_date'] ) : date( 'Y-m-d' ),
                            'room_rent'          => isset( $row['room_rent'] ) ? max( 0, floatval( $row['room_rent'] ) ) : 0,
                            'nursing_charge'     => isset( $row['nursing_charge'] ) ? max( 0, floatval( $row['nursing_charge'] ) ) : 0,
                            'physio_charge'      => isset( $row['physio_charge'] ) ? max( 0, floatval( $row['physio_charge'] ) ) : 0,
                            'doctor_charge'      => isset( $row['doctor_charge'] ) ? max( 0, floatval( $row['doctor_charge'] ) ) : 0,
                            'acupuncture_charge' => isset( $row['acupuncture_charge'] ) ? max( 0, floatval( $row['acupuncture_charge'] ) ) : 0,
                            'prp_charge'         => isset( $row['prp_charge'] ) ? max( 0, floatval( $row['prp_charge'] ) ) : 0,
                        ),
                        array( '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f' )
                    );
                }
            }
        }

        wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=patients&sub=history&id=' . $target_admission_id . '&message=admission_saved' ) );
        exit;
    }
}
add_action( 'admin_init', 'arms_process_admission_actions' );


/**
 * =========================================================================
 * 2. COMPREHENSIVE RENDER SYSTEM CORE ENGINE INTERFACE
 * =========================================================================
 */
function arms_add_edit_admission_form( $admission_id = 0 ) {
    global $wpdb;
    $admission_id = intval( $admission_id );
    $is_edit      = ( $admission_id > 0 );

    echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
    echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';

    $admission_data = array(
        'patient_id'        => 0,
        'status'            => 'Active Stay',
        'room_type'         => 'Cabin',
        'room_no'           => '',
        'ward_bed_no'       => '',
        'admission_date'    => date( 'Y-m-d' ),
        'advance_payment'   => 0,
        'discharge_date'    => '',
        'discharge_summary' => '',
        'payment_status'    => 'Unpaid',
        'final_bill_amount' => 0,
        'repeater_charges'  => array(
            array(
                'charge_date'        => date( 'Y-m-d' ),
                'room_rent'          => 0,
                'nursing_charge'     => 0,
                'physio_charge'      => 0,
                'doctor_charge'      => 0,
                'acupuncture_charge' => 0,
                'prp_charge'         => 0,
            ),
        ),
    );

    if ( $is_edit ) {
        $table_admissions = $wpdb->prefix . 'arms_admissions';
        $table_charges    = $wpdb->prefix . 'arms_admission_charges';

        $db_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_admissions WHERE id = %d", $admission_id ), ARRAY_A );

        if ( $db_record ) {
            foreach ( $db_record as $key => $val ) {
                if ( 'id' !== $key ) {
                    $admission_data[ $key ] = $val;
                }
            }
            $admission_data['status'] = ! empty( $admission_data['discharge_date'] ) ? 'Discharged Case' : 'Active Stay';

            $db_charges = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_charges WHERE admission_id = %d ORDER BY row_index ASC", $admission_id ), ARRAY_A );
            if ( ! empty( $db_charges ) ) {
                $admission_data['repeater_charges'] = $db_charges;
            }
        }
    }

    $table_patients = $wpdb->prefix . 'arms_patients';
    $patients_query = $wpdb->get_results( "SELECT id, name, mobile FROM $table_patients ORDER BY name ASC" );

    if ( isset( $_GET['message'] ) && 'admission_saved' === $_GET['message'] ) {
        echo '<div class="notice notice-success is-dismissible"><p>System Integration Success: Ledger entry logs updated and verified cleanly.</p></div>';
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
        
        .select2-container--default .select2-selection--single { border: 1px solid #cbd5e1 !important; border-radius: 6px !important; height: 38px !important; padding: 4px 6px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }

        .arms-repeater-container { grid-column: span 12; display: flex; flex-direction: column; gap: 12px; }
        .arms-repeater-row { display: grid; grid-template-columns: 130px repeat(6, 1fr) 45px; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; align-items: flex-end; position: relative; }
        .arms-repeater-row .arms-fgroup { grid-column: auto; }
        .arms-btn-remove { background: #ef4444; color: #fff; border: none; padding: 9px; border-radius: 6px; cursor: pointer; text-align: center; font-size: 14px; line-height: 1; transition: background 0.2s; height: 38px; display: flex; align-items: center; justify-content: center; }
        .arms-btn-remove:hover { background: #dc2626; }
        .arms-btn-add-row { background: #0f172a; color: #fff; border: none; padding: 10px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; width: max-content; transition: background 0.2s; margin-top: 8px; }
        .arms-btn-add-row:hover { background: #1e293b; }
        
        .arms-billing-summary-box { grid-column: span 12; background: #f0f5ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 20px; margin-top: 16px; }
        .arms-bill-grid { display: flex; justify-content: space-around; text-align: center; }
        .arms-bill-stat h4 { margin: 0 0 4px 0; font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
        .arms-bill-stat .stat-price { font-size: 24px; font-weight: 800; color: #1e3a8a; }
        .arms-bill-divider { border-left: 1px solid #cbd5e1; height: 40px; align-self: center; }
        
        .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .arms-btn { padding: 10px 20px; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent; transition: all 0.2s; text-decoration: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #fff; color: #475569; border-color: #cbd5e1; }
        .btn-secondary:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-success { background: #16a34a; color: #fff; border: none; }
        .btn-success:hover { background: #15803d; }
        .btn-print { background: #7c3aed; color: #fff; border: none; }
        .btn-print:hover { background: #6d28d9; }
        
        @keyframes armsFadeIn { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="arms-adm-wrapper" id="arms-printable-area">
        <div class="arms-adm-header">
            <div>
                <h2><?php echo $is_edit ? 'Manage Admission Ledger Blueprint' : 'Patient Admission & Clinical Tracking Console'; ?></h2>
                <p>Track spatial configurations, multi-tier transactional logs, and unified continuous auto-billing frameworks cleanly.</p>
            </div>
            <div>
                <span class="arms-status-badge">🟢 <?php echo esc_html( $admission_data['status'] ); ?></span>
            </div>
        </div>

        <div class="arms-nav-bar">
            <button type="button" class="arms-nav-btn active" id="tab-btn-alloc" onclick="armsMovePane('pane-alloc')">
                1. Structural Intake & Allocation
            </button>
            <button type="button" class="arms-nav-btn" id="tab-btn-discharge" onclick="armsMovePane('pane-discharge')">
                2. Clinical Discharge Protocol
            </button>
        </div>

        <form method="POST" action="" id="arms-admission-master-form">
            <?php wp_nonce_field( 'arms_admission_security_lock', 'arms_admission_nonce' ); ?>
            <input type="hidden" name="patient_id" value="<?php echo esc_attr( $admission_id ); ?>" />

            <div id="pane-alloc" class="arms-pane active">
                <div class="arms-grid">
                    <div class="section-subtitle">Core Infrastructure Selection Matrix</div>
                    
                    <div class="arms-fgroup col-12">
                        <label class="arms-label">Select Registered Patient Database Target Record</label>
                        <select name="arms_selected_patient_id" id="arms_selected_patient_dropdown" class="arms-select2-patient" style="width: 100%;" required>
                            <option value="">-- Type name, phone, or track record instantly via system lookups... --</option>
                            <?php 
                            if ( ! empty( $patients_query ) ) {
                                foreach ( $patients_query as $p_row ) {
                                    $p_id      = intval( $p_row->id );
                                    $p_display = esc_html( $p_row->name ) . ' (ID: ' . $p_id . ' - ' . esc_html( $p_row->mobile ) . ')';
                                    echo '<option value="' . $p_id . '" ' . selected( $admission_data['patient_id'], $p_id, false ) . '>' . $p_display . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="arms-fgroup col-3">
                        <label class="arms-label">Room Allocation Topology</label>
                        <select name="room_type" id="arms_room_type_select" class="arms-select" onchange="armsToggleSpatialFields()" required>
                            <option value="Cabin" <?php selected( $admission_data['room_type'], 'Cabin' ); ?>>Cabin (Single Luxury Suite)</option>
                            <option value="Ward Bed" <?php selected( $admission_data['room_type'], 'Ward Bed' ); ?>>General Ward Bed Layout</option>
                        </select>
                    </div>

                    <div class="arms-fgroup col-3" id="arms-group-cabin" style="<?php echo ( 'Cabin' === $admission_data['room_type'] ) ? '' : 'display:none;'; ?>">
                        <label class="arms-label">Assigned Cabin Room Number</label>
                        <input type="text" name="room_no" class="arms-input" placeholder="e.g. Cabin-402" value="<?php echo esc_attr( $admission_data['room_no'] ); ?>" />
                    </div>

                    <div class="arms-fgroup col-3" id="arms-group-ward" style="<?php echo ( 'Ward Bed' === $admission_data['room_type'] ) ? '' : 'display:none;'; ?>">
                        <label class="arms-label">Assigned Ward Bed Index Line</label>
                        <input type="text" name="ward_bed_no" class="arms-input" placeholder="e.g. Ward-B / Bed-12" value="<?php echo esc_attr( $admission_data['ward_bed_no'] ); ?>" />
                    </div>

                    <div class="arms-fgroup col-3">
                        <label class="arms-label">Admission Ingress Date</label>
                        <input type="date" name="admission_date" class="arms-input" value="<?php echo esc_attr( $admission_data['admission_date'] ); ?>" required />
                    </div>

                    <div class="arms-fgroup col-3">
                        <label class="arms-label">Advance Payment Deposited (৳)</label>
                        <div class="input-addon-wrap">
                            <span class="addon">৳</span>
                            <input type="number" step="any" name="advance_payment" id="arms_advance_payment" class="arms-input" min="0" value="<?php echo esc_attr( $admission_data['advance_payment'] ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                        </div>
                    </div>

                    <div class="section-subtitle">Multi-Tier Treatment Metrics Service Logs (Daily Charge Multipliers)</div>
                    
                    <div class="arms-repeater-container" id="arms-repeater-root">
                        <?php 
                        $idx = 0;
                        foreach ( $admission_data['repeater_charges'] as $row ) { 
                            $c_date = isset( $row['charge_date'] ) ? $row['charge_date'] : date( 'Y-m-d' );
                            $r_rent = isset( $row['room_rent'] ) ? $row['room_rent'] : 0;
                            $n_chg  = isset( $row['nursing_charge'] ) ? $row['nursing_charge'] : 0;
                            $p_chg  = isset( $row['physio_charge'] ) ? $row['physio_charge'] : 0;
                            $d_chg  = isset( $row['doctor_charge'] ) ? $row['doctor_charge'] : 0;
                            $a_chg  = isset( $row['acupuncture_charge'] ) ? $row['acupuncture_charge'] : 0;
                            $prp_c  = isset( $row['prp_charge'] ) ? $row['prp_charge'] : 0;
                            ?>
                            <div class="arms-repeater-row" data-index="<?php echo $idx; ?>">
                                <div class="arms-fgroup">
                                    <label class="arms-label">Entry Date</label>
                                    <input type="date" name="repeater_charges[<?php echo $idx; ?>][charge_date]" class="arms-input" value="<?php echo esc_attr( $c_date ); ?>" required />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Room Rent (৳)</label>
                                    <input type="number" step="any" name="repeater_charges[<?php echo $idx; ?>][room_rent]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr( $r_rent ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Nursing (৳)</label>
                                    <input type="number" step="any" name="repeater_charges[<?php echo $idx; ?>][nursing_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr( $n_chg ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Physiotherapy (৳)</label>
                                    <input type="number" step="any" name="repeater_charges[<?php echo $idx; ?>][physio_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr( $p_chg ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Doctor Visit (৳)</label>
                                    <input type="number" step="any" name="repeater_charges[<?php echo $idx; ?>][doctor_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr( $d_chg ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">Acupuncture (৳)</label>
                                    <input type="number" step="any" name="repeater_charges[<?php echo $idx; ?>][acupuncture_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr( $a_chg ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div class="arms-fgroup">
                                    <label class="arms-label">PRP Charge (৳)</label>
                                    <input type="number" step="any" name="repeater_charges[<?php echo $idx; ?>][prp_charge]" class="arms-input arms-calc-trigger" min="0" value="<?php echo esc_attr( $prp_c ); ?>" oninput="armsCalculateLiveBillingTotals()" />
                                </div>
                                <div>
                                    <button type="button" class="arms-btn-remove" onclick="armsRemoveRepeaterRow(this)">✖</button>
                                </div>
                            </div>
                            <?php 
                            $idx++;
                        } 
                        ?>
                    </div>

                    <div class="col-12">
                        <button type="button" class="arms-btn-add-row" onclick="armsAddRepeaterRow()">+ Append Continuous Entry Row</button>
                    </div>

                    <div class="arms-billing-summary-box">
                        <div class="arms-bill-grid">
                            <div class="arms-bill-stat">
                                <h4>Accrued Gross Total</h4>
                                <div class="stat-price" id="arms-bill-gross-view">৳0.00</div>
                            </div>
                            <div class="arms-bill-divider"></div>
                            <div class="arms-bill-stat">
                                <h4>(-) Less Advance Paid</h4>
                                <div class="stat-price" id="arms-bill-advance-view" style="color: #dc2626;">৳0.00</div>
                            </div>
                            <div class="arms-bill-divider"></div>
                            <div class="arms-bill-stat">
                                <h4 id="arms-bill-net-label">Adjusted Net Due Payable</h4>
                                <div class="stat-price" id="arms-bill-net-view" style="color: #16a34a;">৳0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <div></div>
                    <button type="button" class="arms-btn btn-primary" onclick="armsMovePane('pane-discharge')">
                        Proceed To Final Protocols Summary →
                    </button>
                </div>
            </div>

            <div id="pane-discharge" class="arms-pane">
                <div class="arms-grid">
                    <div class="section-subtitle">Clinical Outgress Closure Setup</div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Discharge Event Closure Date</label>
                        <input type="date" name="discharge_date" class="arms-input" value="<?php echo esc_attr( $admission_data['discharge_date'] ); ?>" />
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label" id="arms-final-bill-label">Final Adjusted Net Balance (৳)</label>
                        <div class="input-addon-wrap">
                            <span class="addon">৳</span>
                            <input type="number" step="any" name="final_bill_amount" id="arms_final_bill_amount" class="arms-input" value="<?php echo esc_attr( $admission_data['final_bill_amount'] ); ?>" readonly />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Invoice Ledger Balance Status Map</label>
                        <select name="payment_status" class="arms-select">
                            <option value="Unpaid" <?php selected( $admission_data['payment_status'], 'Unpaid' ); ?>>🔴 Unpaid Balance Arrears Outstanding</option>
                            <option value="Partially Paid" <?php selected( $admission_data['payment_status'], 'Partially Paid' ); ?>>🟡 Partially Settled Ledger Sub-Account</option>
                            <option value="Paid" <?php selected( $admission_data['payment_status'], 'Paid' ); ?>>🟢 Fully Cleared Capital Processing Status</option>
                        </select>
                    </div>

                    <div class="arms-fgroup col-12">
                        <label class="arms-label">Comprehensive Treatment Clinical Outcome Summary Log</label>
                        <textarea name="discharge_summary" class="arms-textarea" rows="6" placeholder="Compile recovery trajectories, updates, medication requirements, and follow-up protocols safely..."><?php echo esc_html( $admission_data['discharge_summary'] ); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="arms-btn btn-secondary" onclick="armsMovePane('pane-alloc')">
                        ← Back to Resource Allocation
                    </button>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="arms-btn btn-print" onclick="armsLaunchInvoicePrintCanvas(<?php echo $admission_id; ?>)">
                            Generate Invoice & Print
                        </button>
                        
                        <button type="submit" name="arms_save_admission_action" class="arms-btn btn-success">
                            <?php echo $is_edit ? 'Commit Records Update' : 'Finalize Records Deployment'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.arms-select2-patient').select2({
            placeholder: "-- Type name, phone, or track record instantly via system lookups... --",
            allowClear: true
        });

        armsCalculateLiveBillingTotals();
    });

    function armsLaunchInvoicePrintCanvas(id) {
        if(!id || id <= 0) {
            alert("Please save the record deployment entries before printing invoice copies.");
            return;
        }
        // Redirect target path location to your separate print engine canvas script dynamically
        const printUrl = `<?php echo plugin_dir_url(__FILE__); ?>print-invoice.php?admission_id=${id}`;
        window.open(printUrl, '_blank', 'width=900,height=800,toolbar=0,resizable=1');
    }

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
        let grossCumulativeTotal = 0;
        
        const numericalInputs = document.querySelectorAll('#arms-repeater-root .arms-calc-trigger');
        numericalInputs.forEach(function(inputElement) {
            let parsedVal = parseFloat(inputElement.value);
            if (!isNaN(parsedVal) && parsedVal > 0) {
                grossCumulativeTotal += parsedVal;
            }
        });

        let advancePaidAmount = parseFloat(document.getElementById('arms_advance_payment').value);
        if (isNaN(advancePaidAmount) || advancePaidAmount < 0) {
            advancePaidAmount = 0;
        }
        
        let adjustedNetPayableValue = grossCumulativeTotal - advancePaidAmount;
        
        const netLabelNode = document.getElementById('arms-bill-net-label');
        const finalBillLabelNode = document.getElementById('arms-final-bill-label');
        const netViewNode = document.getElementById('arms-bill-net-view');

        if (adjustedNetPayableValue < 0) {
            let absoluteCreditValue = Math.abs(adjustedNetPayableValue).toFixed(2);
            if (netLabelNode) netLabelNode.innerText = "Patient Refund Due (Credit Balance)";
            if (finalBillLabelNode) finalBillLabelNode.innerText = "Final Adjusted Refund Balance (৳)";
            if (netViewNode) {
                netViewNode.innerText = '-৳' + absoluteCreditValue;
                netViewNode.style.color = '#2563eb';
            }
        } else {
            if (netLabelNode) netLabelNode.innerText = "Adjusted Net Due Payable";
            if (finalBillLabelNode) finalBillLabelNode.innerText = "Final Adjusted Net Balance (৳)";
            if (netViewNode) {
                netViewNode.innerText = '৳' + adjustedNetPayableValue.toFixed(2);
                netViewNode.style.color = adjustedNetPayableValue === 0 ? '#16a34a' : '#dc2626';
            }
        }
        
        document.getElementById('arms-bill-gross-view').innerText = '৳' + grossCumulativeTotal.toFixed(2);
        document.getElementById('arms-bill-advance-view').innerText = '৳' + advancePaidAmount.toFixed(2);
        
        const finalBillNode = document.getElementById('arms_final_bill_amount');
        if (finalBillNode) {
            finalBillNode.value = adjustedNetPayableValue.toFixed(2);
        }
    }

    function armsRemoveRepeaterRow(buttonElement) {
        const row = buttonElement.closest('.arms-repeater-row');
        if (row) {
            row.remove();
            armsCalculateLiveBillingTotals();
        }
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

        const targetIdx = maximumIndex + 1;
        const currentDateString = new Date().toISOString().split('T')[0];

        const htmlTemplateString = `
            <div class="arms-repeater-row" data-index="${targetIdx}">
                <div class="arms-fgroup">
                    <label class="arms-label">Entry Date</label>
                    <input type="date" name="repeater_charges[${targetIdx}][charge_date]" class="arms-input" value="${currentDateString}" required />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Room Rent (৳)</label>
                    <input type="number" step="any" name="repeater_charges[${targetIdx}][room_rent]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Nursing (৳)</label>
                    <input type="number" step="any" name="repeater_charges[${targetIdx}][nursing_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Physiotherapy (৳)</label>
                    <input type="number" step="any" name="repeater_charges[${targetIdx}][physio_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Doctor Visit (৳)</label>
                    <input type="number" step="any" name="repeater_charges[${targetIdx}][doctor_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">Acupuncture (৳)</label>
                    <input type="number" step="any" name="repeater_charges[${targetIdx}][acupuncture_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div class="arms-fgroup">
                    <label class="arms-label">PRP Charge (৳)</label>
                    <input type="number" step="any" name="repeater_charges[${targetIdx}][prp_charge]" class="arms-input arms-calc-trigger" min="0" value="0" oninput="armsCalculateLiveBillingTotals()" />
                </div>
                <div>
                    <button type="button" class="arms-btn-remove" onclick="armsRemoveRepeaterRow(this)">✖</button>
                </div>
            </div>`;

        rootContainer.insertAdjacentHTML('beforeend', htmlTemplateString);
        armsCalculateLiveBillingTotals();
    }
    </script>
    <?php
}