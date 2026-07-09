<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function arms_add_edit_admission_form() {
    global $wpdb;
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_patients   = $wpdb->prefix . 'arms_patients';

    $message = '';
    $id = 0;

    // Process Form Post Actions
    if ( isset( $_POST['arms_finalize_admission'] ) && check_admin_referer( 'arms_console_action', 'arms_console_nonce' ) ) {
        $id             = isset( $_POST['admission_id'] ) ? absint( $_POST['admission_id'] ) : 0;
        $patient_id     = isset( $_POST['patient_id'] ) ? absint( $_POST['patient_id'] ) : 0;
        $room_no        = isset( $_POST['room_no'] ) ? sanitize_text_field( $_POST['room_no'] ) : '';
        $admission_date = isset( $_POST['admission_date'] ) ? sanitize_text_field( $_POST['admission_date'] ) : current_time('Y-m-d');
        
        // Financial Schema Extensions matching UI inputs
        $tracking_status  = isset( $_POST['tracking_status'] ) ? sanitize_text_field( $_POST['tracking_status'] ) : 'Stay (Active Recovery Inpatient)';
        $room_allocation = isset( $_POST['room_allocation'] ) ? sanitize_text_field( $_POST['room_allocation'] ) : '';
        $advance_paid    = isset( $_POST['advance_payment'] ) ? floatval( $_POST['advance_payment'] ) : 0.00;
        
        // Discharge context tracking fields
        $discharge_date  = isset( $_POST['discharge_date'] ) ? sanitize_text_field( $_POST['discharge_date'] ) : '';
        $ledger_status   = isset( $_POST['ledger_status'] ) ? sanitize_text_field( $_POST['ledger_status'] ) : 'Unpaid Balance Arrears Outstanding';
        $clinical_log    = isset( $_POST['clinical_log'] ) ? sanitize_textarea_field( $_POST['clinical_log'] ) : '';
        
        // Package serialized repeatable entries block 
        $daily_entries   = isset( $_POST['daily_rows'] ) ? utils_sanitize_rows_array( $_POST['daily_rows'] ) : array();
        $final_net_due   = isset( $_POST['calculated_net_due'] ) ? floatval( $_POST['calculated_net_due'] ) : 0.00;

        // ONLY use columns that exist inside your arms_admissions DB Table schema
        $data_array = array(
            'patient_id'        => $patient_id,
            'room_no'           => $room_no,
            'admission_date'    => $admission_date,
            'final_bill_amount' => $final_net_due, 
        );

        if ( $id > 0 ) {
            $updated = $wpdb->update( $table_admissions, $data_array, array( 'id' => $id ) );
            if ( $updated !== false ) {
                // Save meta definitions externally without breaking table queries
                $meta_payload = array(
                    'tracking_status'  => $tracking_status,
                    'room_allocation'  => $room_allocation,
                    'advance_payment'  => $advance_paid,
                    'discharge_date'   => $discharge_date,
                    'ledger_status'    => $ledger_status,
                    'clinical_log'     => $clinical_log,
                    'daily_entries'    => $daily_entries
                );
                update_option( 'arms_admission_meta_' . $id, $meta_payload );
                $message = '<div class="notice notice-success is-dismissible"><p>Console Deployment Synced Successfully.</p></div>';
            } else {
                $message = '<div class="error"><p>Failed to update records.</p></div>';
            }
        } else {
            // New entry creation pipeline execution
            $inserted = $wpdb->insert( $table_admissions, $data_array );
            if ( $inserted ) {
                $id = $wpdb->insert_id;
                
                // Save meta map now that we have a fresh insert ID reference
                $meta_payload = array(
                    'tracking_status'  => $tracking_status,
                    'room_allocation'  => $room_allocation,
                    'advance_payment'  => $advance_paid,
                    'discharge_date'   => $discharge_date,
                    'ledger_status'    => $ledger_status,
                    'clinical_log'     => $clinical_log,
                    'daily_entries'    => $daily_entries
                );
                update_option( 'arms_admission_meta_' . $id, $meta_payload );
                $message = '<div class="notice notice-success is-dismissible"><p>Console Records Deployed and Saved Cleanly.</p></div>';
            } else {
                // If it fails here, double check your explicit DB table column names
                $message = '<div class="error"><p>Database insertion failed: ' . esc_html($wpdb->last_error) . '</p></div>';
            }
        }
    }

    if ( $id === 0 && isset( $_GET['id'] ) ) {
        $id = absint( $_GET['id'] );
    }

    // Default configuration map arrays
    $form_data = array(
        'id'                => 0,
        'patient_id'        => '',
        'room_no'           => '',
        'admission_date'    => current_time('Y-m-d'),
        'final_bill_amount' => 0.00
    );

    $meta = array(
        'tracking_status'  => 'Stay (Active Recovery Inpatient)',
        'room_allocation'  => 'Cabin (Single Luxury Suite)',
        'advance_payment'  => 0,
        'discharge_date'   => '',
        'ledger_status'    => 'Unpaid Balance Arrears Outstanding',
        'clinical_log'     => '',
        'daily_entries'    => array( array('date' => current_time('Y-m-d'), 'rent'=>0, 'nursing'=>0, 'physio'=>0, 'doctor'=>0, 'acu'=>0, 'prp'=>0) )
    );

    if ( $id > 0 ) {
        $db_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_admissions} WHERE id = %d", $id ), ARRAY_A );
        if ( $db_row ) {
            $form_data = $db_row;
            // Fetch configuration fields from the external options table safely
            $saved_meta = get_option( 'arms_admission_meta_' . $id );
            if ( is_array( $saved_meta ) ) {
                $meta = array_merge( $meta, $saved_meta );
            }
        }
    }

    $patients = $wpdb->get_results( "SELECT id, name FROM {$table_patients} ORDER BY name ASC" );
    echo $message;
    ?>
    <style>
        .arms-console-container { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin-top: 20px; max-width: 1200px; }
        .arms-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 25px; position: relative; }
        .arms-header-flex { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .arms-title { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0 0 5px 0; }
        .arms-subtitle { font-size: 13px; color: #64748b; margin: 0; }
        .status-badge { background: #f0fdf4; color: #16a34a; font-weight: 600; font-size: 12px; padding: 6px 14px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #bbf7d0; }
        .status-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; }
        
        /* Tab Navigation */
        .arms-tabs-nav { display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 30px; gap: 5px; }
        .arms-tab-btn { background: none; border: none; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; border-bottom: 2px solid transparent; }
        .arms-tab-btn:hover { color: #1e293b; }
        .arms-tab-btn.active { color: #0284c7; border-bottom-color: #0284c7; }
        .arms-tab-content { display: none; }
        .arms-tab-content.active { display: block; }

        /* Form Layout components */
        .section-divider-title { font-size: 13px; font-weight: 700; letter-spacing: 0.05em; color: #0f172a; text-transform: uppercase; margin: 25px 0 20px 0; padding-left: 10px; border-left: 3px solid #0284c7; }
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-group-full { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .console-input, .console-select, .console-textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; font-size: 14px; color: #334155; background-color: #fff; box-shadow: scale(0); transition: border 0.15s; }
        
        /* Continuous row items framework layout */
        .repeater-header-row { display: grid; grid-template-columns: 1.2fr repeat(6, 1fr) 45px; gap: 10px; background: #f8fafc; padding: 10px; border-radius: 6px; margin-bottom: 8px; text-align: left; }
        .repeater-header-item { font-size: 12px; font-weight: 600; color: #64748b; }
        .repeater-data-row { display: grid; grid-template-columns: 1.2fr repeat(6, 1fr) 45px; gap: 10px; margin-bottom: 8px; align-items: center; }
        .btn-append { background: #0f172a; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; margin-top: 10px; }
        .btn-delete-row { background: #ef4444; color: #fff; border: none; width: 36px; height: 36px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
        
        /* Financial Metrics widget elements */
        .financial-summary-panel { display: grid; grid-template-columns: repeat(3, 1fr); background: #eff6ff; border-radius: 8px; padding: 20px; text-align: center; margin-top: 30px; gap: 15px; border: 1px dashed #bfdbfe; }
        .summary-block { border-right: 1px solid #dbeafe; }
        .summary-block:last-child { border-right: none; }
        .summary-lbl { font-size: 11px; text-transform: uppercase; font-weight: 600; color: #64748b; letter-spacing: 0.03em; margin-bottom: 5px; }
        .summary-val { font-size: 24px; font-weight: 800; color: #1e3a8a; }
        
        .panel-actions-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn-primary-action { background: #1e3a8a; color: #fff; font-weight: 600; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-secondary-action { background: #fff; color: #334155; font-weight: 600; border: 1px solid #cbd5e1; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; }
    </style>

    <div class="wrap arms-console-container">
        <div class="arms-card">
            <div class="arms-header-flex">
                <div>
                    <h1 class="arms-title">Patient Admission & Clinical Tracking Console</h1>
                    <p class="arms-subtitle">Track spatial configurations, multi-tier transactional logs, and unified continuous auto-billing frameworks cleanly.</p>
                </div>
                <div class="status-badge"><span class="status-dot"></span> Active Stay</div>
            </div>

            <div class="arms-tabs-nav">
                <button type="button" class="arms-tab-btn active" onclick="switchConsoleTab(event, 'intake_allocation_tab')">1. Structural Intake & Allocation</button>
                <button type="button" class="arms-tab-btn" onclick="switchConsoleTab(event, 'discharge_protocol_tab')">2. Clinical Discharge Protocol</button>
            </div>

            <form method="post" action="" id="arms_console_master_form">
                <?php wp_nonce_field( 'arms_console_action', 'arms_console_nonce' ); ?>
                <input type="hidden" name="admission_id" value="<?php echo esc_attr( $form_data['id'] ); ?>" />
                <input type="hidden" name="calculated_net_due" id="calculated_net_due" value="<?php echo esc_attr($form_data['final_bill_amount']); ?>" />

                <div id="intake_allocation_tab" class="arms-tab-content active">
                    <div class="section-divider-title">Basic Info</div>
                    
                    <div class="form-group-full">
                        <label class="form-label">Patient Name</label>
                        <select name="patient_id" id="patient_id" class="console-select" required>
                            <option value="">-- Type name, phone, or track record instantly via system lookups... --</option>
                            <?php if ( ! empty( $patients ) ) : foreach ( $patients as $p ) : ?>
                                <option value="<?php echo intval( $p->id ); ?>" <?php selected( $form_data['patient_id'], $p->id ); ?>>
                                    <?php echo esc_html( $p->name ); ?> (Patient ID: #<?php echo intval( $p->id ); ?>)
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="grid-row">
                        <div>
                            <label class="form-label">Patient Tracking Status</label>
                            <select name="tracking_status" class="console-select">
                                <option value="Stay (Active Recovery Inpatient)" <?php selected($meta['tracking_status'], 'Stay (Active Recovery Inpatient)'); ?>>🏨 Stay (Active Recovery Inpatient)</option>
                                <option value="Discharged Complete" <?php selected($meta['tracking_status'], 'Discharged Complete'); ?>>✅ Discharged Complete</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Room Allocation</label>
                            <select name="room_allocation" class="console-select">
                                <option value="Cabin (Single Luxury Suite)" <?php selected($meta['room_allocation'], 'Cabin (Single Luxury Suite)'); ?>>Cabin (Single Luxury Suite)</option>
                                <option value="Ward (General Shared Desk)" <?php selected($meta['room_allocation'], 'Ward (General Shared Desk)'); ?>>Ward (General Shared Desk)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_no" class="console-input" placeholder="e.g. Cabin-402" value="<?php echo esc_attr( $form_data['room_no'] ); ?>" required />
                        </div>
                        <div>
                            <label class="form-label">Admission Date</label>
                            <input type="date" name="admission_date" class="console-input" value="<?php echo esc_attr( date('Y-m-d', strtotime($form_data['admission_date'])) ); ?>" required />
                        </div>
                    </div>

                    <div class="grid-row" style="grid-template-columns: 1fr 3fr;">
                        <div>
                            <label class="form-label">Advance Payment Deposited (৳)</label>
                            <input type="number" step="0.01" name="advance_payment" id="advance_payment" class="console-input" value="<?php echo esc_attr($meta['advance_payment']); ?>" oninput="calculateConsoleFinancials()" />
                        </div>
                    </div>

                    <div class="section-divider-title">Daily Charge</div>
                    
                    <div id="arms_repeater_rows_container">
                        <div class="repeater-header-row">
                            <div class="repeater-header-item">Entry Date</div>
                            <div class="repeater-header-item">Room Rent (৳)</div>
                            <div class="repeater-header-item">Nursing (৳)</div>
                            <div class="repeater-header-item">Physotherapy (৳)</div>
                            <div class="repeater-header-item">Doctor Visit (৳)</div>
                            <div class="repeater-header-item">Acupuncture (৳)</div>
                            <div class="repeater-header-item">PRP Charge (৳)</div>
                            <div class="repeater-header-item" style="text-align: center;">✕</div>
                        </div>

                        <?php foreach ( $meta['daily_entries'] as $index => $row ) : ?>
                            <div class="repeater-data-row">
                                <input type="date" name="daily_rows[<?php echo $index; ?>][date]" class="console-input row-calc-trigger" value="<?php echo esc_attr($row['date']); ?>" required />
                                <input type="number" step="0.01" name="daily_rows[<?php echo $index; ?>][rent]" class="console-input calc-rent row-calc-trigger" value="<?php echo esc_attr($row['rent']); ?>" oninput="calculateConsoleFinancials()" />
                                <input type="number" step="0.01" name="daily_rows[<?php echo $index; ?>][nursing]" class="console-input calc-nursing row-calc-trigger" value="<?php echo esc_attr($row['nursing']); ?>" oninput="calculateConsoleFinancials()" />
                                <input type="number" step="0.01" name="daily_rows[<?php echo $index; ?>][physio]" class="console-input calc-physio row-calc-trigger" value="<?php echo esc_attr($row['physio']); ?>" oninput="calculateConsoleFinancials()" />
                                <input type="number" step="0.01" name="daily_rows[<?php echo $index; ?>][doctor]" class="console-input calc-doctor row-calc-trigger" value="<?php echo esc_attr($row['doctor']); ?>" oninput="calculateConsoleFinancials()" />
                                <input type="number" step="0.01" name="daily_rows[<?php echo $index; ?>][acu]" class="console-input calc-acu row-calc-trigger" value="<?php echo esc_attr($row['acu']); ?>" oninput="calculateConsoleFinancials()" />
                                <input type="number" step="0.01" name="daily_rows[<?php echo $index; ?>][prp]" class="console-input calc-prp row-calc-trigger" value="<?php echo esc_attr($row['prp']); ?>" oninput="calculateConsoleFinancials()" />
                                <div style="text-align:center;"><button type="button" class="btn-delete-row" onclick="removeContinuousRow(this)">&times;</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-append" onclick="appendContinuousEntryRow()">+ Append Continuous Entry Row</button>

                    <div class="financial-summary-panel">
                        <div class="summary-block">
                            <div class="summary-lbl">Accrued Gross Total</div>
                            <div class="summary-val" id="disp_gross_total">৳0.00</div>
                        </div>
                        <div class="summary-block">
                            <div class="summary-lbl">(-) Less Advance Paid</div>
                            <div class="summary-val" id="disp_advance_paid" style="color: #ef4444;">৳0.00</div>
                        </div>
                        <div class="summary-block">
                            <div class="summary-lbl">Adjusted Net Due Payable</div>
                            <div class="summary-val" id="disp_net_payable">৳0.00</div>
                        </div>
                    </div>

                    <div class="panel-actions-footer">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rehab_management_system&tab=admission&sub=all')); ?>" class="btn-secondary-action">&larr; Back to Records</a>
                        <button type="button" class="btn-primary-action" onclick="jumpToNextTab('discharge_protocol_tab')">Proceed To Final Protocols Summary &rarr;</button>
                    </div>
                </div>

                <div id="discharge_protocol_tab" class="arms-tab-content">
                    <div class="section-divider-title">Clinical Outgress</div>
                    
                    <div class="grid-row" style="grid-template-columns: repeat(3, 1fr);">
                        <div>
                            <label class="form-label">Discharge Event Closure Date</label>
                            <input type="date" name="discharge_date" class="console-input" value="<?php echo esc_attr($meta['discharge_date']); ?>" />
                        </div>
                        <div>
                            <label class="form-label">Final Adjusted Net Balance (৳)</label>
                            <input type="text" id="disabled_net_balance_display" class="console-input" style="background:#f1f5f9;" readonly value="0.00" />
                        </div>
                        <div>
                            <label class="form-label">Invoice Ledger Balance Status Map</label>
                            <select name="ledger_status" class="console-select">
                                <option value="Unpaid Balance Arrears Outstanding" <?php selected($meta['ledger_status'], 'Unpaid Balance Arrears Outstanding'); ?>>🔴 Unpaid Balance Arrears Outstanding</option>
                                <option value="Settled Closed Balance Fair" <?php selected($meta['ledger_status'], 'Settled Closed Balance Fair'); ?>>🟢 Settled Closed Balance Fair</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Comprehensive Treatment Clinical Outcome Summary Log</label>
                        <textarea name="clinical_log" class="console-textarea" rows="4" placeholder="Compile recovery trajectories..."><?php echo esc_textarea($meta['clinical_log']); ?></textarea>
                    </div>

                    <div class="panel-actions-footer">
                        <button type="button" class="btn-secondary-action" onclick="jumpToNextTab('intake_allocation_tab')">&larr; Back to Resource Allocation</button>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="btn-secondary-action" style="background:#0f172a; color:#fff;" onclick="window.print()">Generate Invoice & Print</button>
                            <button type="submit" name="arms_save_admission" id="real_submit_trigger" style="display:none;"></button>
                            <button type="button" class="btn-primary-action" style="background:#1e3a8a;" onclick="submitMasterConsoleForm()">Finalize Records Deployment</button>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="arms_finalize_admission" value="1" />
            </form>
        </div>
    </div>

    <script>
        let rowCounter = <?php echo count($meta['daily_entries']); ?>;

        function switchConsoleTab(evt, tabId) {
            let i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("arms-tab-content");
            for (i = 0; i < tabcontent.length; i++) { tabcontent[i].classList.remove("active"); }
            tablinks = document.getElementsByClassName("arms-tab-btn");
            for (i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove("active"); }
            document.getElementById(tabId).classList.add("active");
            if (evt) { evt.currentTarget.classList.add("active"); }
            else {
                for(let btn of tablinks) {
                    if(btn.getAttribute('onclick').includes(tabId)) btn.classList.add('active');
                }
            }
        }

        function jumpToNextTab(tabId) {
            switchConsoleTab(null, tabId);
            window.scrollTo({ top: 150, behavior: 'smooth' });
        }

        function appendContinuousEntryRow() {
            const container = document.getElementById('arms_repeater_rows_container');
            const today = new Date().toISOString().split('T')[0];
            const htmlRow = `
                <div class="repeater-data-row">
                    <input type="date" name="daily_rows[${rowCounter}][date]" class="console-input row-calc-trigger" value="${today}" required />
                    <input type="number" step="0.01" name="daily_rows[${rowCounter}][rent]" class="console-input calc-rent row-calc-trigger" value="0" oninput="calculateConsoleFinancials()" />
                    <input type="number" step="0.01" name="daily_rows[${rowCounter}][nursing]" class="console-input calc-nursing row-calc-trigger" value="0" oninput="calculateConsoleFinancials()" />
                    <input type="number" step="0.01" name="daily_rows[${rowCounter}][physio]" class="console-input calc-physio row-calc-trigger" value="0" oninput="calculateConsoleFinancials()" />
                    <input type="number" step="0.01" name="daily_rows[${rowCounter}][doctor]" class="console-input calc-doctor row-calc-trigger" value="0" oninput="calculateConsoleFinancials()" />
                    <input type="number" step="0.01" name="daily_rows[${rowCounter}][acu]" class="console-input calc-acu row-calc-trigger" value="0" oninput="calculateConsoleFinancials()" />
                    <input type="number" step="0.01" name="daily_rows[${rowCounter}][prp]" class="console-input calc-prp row-calc-trigger" value="0" oninput="calculateConsoleFinancials()" />
                    <div style="text-align:center;"><button type="button" class="btn-delete-row" onclick="removeContinuousRow(this)">&times;</button></div>
                </div>`;
            container.insertAdjacentHTML('beforeend', htmlRow);
            rowCounter++;
            calculateConsoleFinancials();
        }

        function removeContinuousRow(button) {
            const row = button.closest('.repeater-data-row');
            if (row) { row.remove(); calculateConsoleFinancials(); }
        }

        function calculateConsoleFinancials() {
            let grossTotal = 0;
            const entries = document.querySelectorAll('.repeater-data-row');
            entries.forEach(row => {
                const rent = parseFloat(row.querySelector('.calc-rent')?.value) || 0;
                const nursing = parseFloat(row.querySelector('.calc-nursing')?.value) || 0;
                const physio = parseFloat(row.querySelector('.calc-physio')?.value) || 0;
                const doctor = parseFloat(row.querySelector('.calc-doctor')?.value) || 0;
                const acu = parseFloat(row.querySelector('.calc-acu')?.value) || 0;
                const prp = parseFloat(row.querySelector('.calc-prp')?.value) || 0;
                grossTotal += (rent + nursing + physio + doctor + acu + prp);
            });

            const advancePaid = parseFloat(document.getElementById('advance_payment').value) || 0;
            const netPayable = grossTotal - advancePaid;

            document.getElementById('disp_gross_total').innerText = '৳' + grossTotal.toFixed(2);
            document.getElementById('disp_advance_paid').innerText = '৳' + advancePaid.toFixed(2);
            document.getElementById('disp_net_payable').innerText = '৳' + netPayable.toFixed(2);
            
            document.getElementById('disabled_net_balance_display').value = netPayable.toFixed(2);
            document.getElementById('calculated_net_due').value = netPayable.toFixed(2);
        }

        function submitMasterConsoleForm() {
            document.getElementById('real_submit_trigger').click();
        }

        document.addEventListener('DOMContentLoaded', () => { calculateConsoleFinancials(); });
    </script>
    <?php
}

/**
 * =========================================================================
 * 2. COMPACT ADMISSION VIEW DETAILS MODULE (FINANCIAL DATA SUMMARY)
 * =========================================================================
 */
function arms_view_admission_details( $admission_id ) {
    global $wpdb;
    $table_admissions = $wpdb->prefix . 'arms_admissions';

    $billing = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, room_no, final_bill_amount 
         FROM {$table_admissions} 
         WHERE id = %d", 
        intval( $admission_id )
    ), OBJECT );

    if ( ! $billing ) {
        echo '<div class="error"><p>Admission dynamic record not found.</p></div>';
        return;
    }

    $advance_paid = 0.00;
    $saved_meta = get_option( 'arms_admission_meta_' . $billing->id );
    if ( is_array( $saved_meta ) && isset( $saved_meta['advance_payment'] ) ) {
        $advance_paid = floatval( $saved_meta['advance_payment'] );
    }
    
    $gross_accrued = floatval($billing->final_bill_amount) + $advance_paid;
    ?>
    <div class="wrap" style="max-width:600px; margin-top:20px;">
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:30px; box-shadow:0 4px 6px rgba(0,0,0,0.02);">
            <h2 style="margin:0 0 5px 0; font-size:20px; font-weight:700; color:#0f172a;">Admission Financial Ledger Summary</h2>
            <p style="margin:0 0 20px 0; font-size:12px; color:#64748b;">Audited real-time billing metrics snapshot configuration.</p>
            
            <table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none;">
                <tbody>
                    <tr>
                        <td style="font-weight:600; padding:12px; width:45%;">Ledger Reference ID:</td>
                        <td style="padding:12px; color:#64748b; font-weight:500;">#ADMS-<?php echo intval( $billing->id ); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; padding:12px;">Allocated Room Track:</td>
                        <td style="padding:12px;">🚪 <?php echo esc_html( $billing->room_no ); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; padding:12px;">Accrued Gross Balance:</td>
                        <td style="padding:12px; font-weight:500;">৳ <?php echo number_format($gross_accrued, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; padding:12px;">(-) Less Advance Deposited:</td>
                        <td style="padding:12px; color:#ef4444; font-weight:500;">৳ <?php echo number_format($advance_paid, 2); ?></td>
                    </tr>
                    <tr style="background:#f0fdf4;">
                        <td style="font-weight:700; padding:14px; color:#16a34a;">Adjusted Net Payable Due:</td>
                        <td style="font-size:18px; font-weight:800; color:#16a34a; padding:14px;">৳ <?php echo number_format( floatval( $billing->final_bill_amount ), 2 ); ?> BDT</td>
                    </tr>
                </tbody>
            </table>
            <br />
            <div style="display:flex; justify-content:space-between; margin-top:10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=all' ) ); ?>" class="button" style="padding:5px 15px;">&larr; Back to List</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=edit&id=' . intval($billing->id) ) ); ?>" class="button button-primary" style="padding:5px 18px; background:#1e3a8a; border-color:#1e3a8a;">Edit Console Ledger</a>
            </div>
        </div>
    </div>
    <?php
}

function utils_sanitize_rows_array($arr) {
    if(!is_array($arr)) return array();
    $sanitized = array();
    foreach($arr as $k => $v) {
        $sanitized[$k] = array(
            'date'    => isset($v['date']) ? sanitize_text_field($v['date']) : '',
            'rent'    => isset($v['rent']) ? floatval($v['rent']) : 0,
            'nursing' => isset($v['nursing']) ? floatval($v['nursing']) : 0,
            'physio'  => isset($v['physio']) ? floatval($v['physio']) : 0,
            'doctor'  => isset($v['doctor']) ? floatval($v['doctor']) : 0,
            'acu'     => isset($v['acu']) ? floatval($v['acu']) : 0,
            'prp'     => isset($v['prp']) ? floatval($v['prp']) : 0,
        );
    }
    return $sanitized;
}