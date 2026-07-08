<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Payroll Management Workspace Renderer
 * Handles Form Processing (POST, No AJAX) and UI Rendering
 */
function arms_payroll_tab() {
    global $wpdb;
    
    // Define exact database table names
    $table_staff   = $wpdb->prefix . 'arms_staff';
    $table_payroll = $wpdb->prefix . 'arms_payroll';

    // Sub-tab Navigation Controller
    $current_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'process';

    // Form Notification Handlers
    $notice_message = '';
    $notice_type    = '';

    // ==========================================================
    // BACKEND CONTROLLER: NATIVE FORM POST EXECUTION (NO AJAX)
    // ==========================================================
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_payroll_action'] ) ) {
        // Enforce CSRF Security
        check_admin_referer( 'arms_payroll_management_nonce', 'security' );

        // Enforce Core Capability Restrictions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized workspace execution.', 'arms-textdomain' ) );
        }

        $action = sanitize_key( $_POST['arms_payroll_action'] );

        // ACTION 1: SAVE NEW PAYROLL RECORD
        if ( $action === 'save_payroll' ) {
            $staff_id             = intval( $_POST['staff_id'] );
            $month                = sanitize_text_field( $_POST['cycle_month'] );
            $year                 = sanitize_text_field( $_POST['cycle_year'] );
            $pay_period           = date( 'Y-m-01', strtotime( "$year-$month-01" ) );
            
            $payment_date         = ! empty( $_POST['payment_date'] ) ? sanitize_text_field( $_POST['payment_date'] ) : current_time( 'mysql' );
            $base_salary          = floatval( $_POST['base_salary'] );
            $bonus                = floatval( $_POST['bonus'] );
            $incentives           = floatval( $_POST['incentives'] );
            $attendance_deduction = floatval( $_POST['attendance_deduction'] );
            $tax_deduction        = floatval( $_POST['tax_deduction'] );
            
            // Ledger balance math engine
            $net_payable          = ( $base_salary + $bonus + $incentives ) - ( $attendance_deduction + $tax_deduction );
            if ( $net_payable < 0 ) { $net_payable = 0; }

            // Check duplicate ledger item for employee within the identical cycle period
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_payroll WHERE staff_id = %d AND pay_period = %s", $staff_id, $pay_period ) );
            
            if ( $exists > 0 ) {
                $notice_message = __( 'A ledger item already exists for this profile within the specified cycle.', 'arms-textdomain' );
                $notice_type    = 'error';
            } else {
                // Insert exactly matching your schema layout sequence
                $inserted = $wpdb->insert(
                    $table_payroll,
                    [
                        'staff_id'             => $staff_id,
                        'pay_period'           => $pay_period,
                        'base_salary'          => $base_salary,
                        'bonus'                => $bonus,
                        'incentives'           => $incentives,
                        'attendance_deduction' => $attendance_deduction,
                        'tax_deduction'        => $tax_deduction,
                        'net_payable'          => $net_payable,
                        'payment_date'         => $payment_date,
                        'status'               => 'paid'
                    ],
                    [ '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s' ]
                );

                if ( $inserted ) {
                    $notice_message = __( 'Payroll processed and tracked successfully!', 'arms-textdomain' );
                    $notice_type    = 'success';
                } else {
                    $notice_message = __( 'Failed to log payroll entry parameters. Check database structure alignment.', 'arms-textdomain' );
                    $notice_type    = 'error';
                }
            }
        }

        // ACTION 2: UPDATE EXISTING LEDGER INSTANCE
        if ( $action === 'update_payroll' ) {
            $row_id               = intval( $_POST['row_id'] );
            $base_salary          = floatval( $_POST['base_salary'] );
            $bonus                = floatval( $_POST['bonus'] );
            $incentives           = floatval( $_POST['incentives'] );
            $attendance_deduction = floatval( $_POST['attendance_deduction'] );
            $tax_deduction        = floatval( $_POST['tax_deduction'] );
            
            $net_payable          = ( $base_salary + $bonus + $incentives ) - ( $attendance_deduction + $tax_deduction );
            if ( $net_payable < 0 ) { $net_payable = 0; }

            $updated = $wpdb->update(
                $table_payroll,
                [
                    'bonus'                => $bonus,
                    'incentives'           => $incentives,
                    'attendance_deduction' => $attendance_deduction,
                    'tax_deduction'        => $tax_deduction,
                    'net_payable'          => $net_payable,
                ],
                [ 'id' => $row_id ],
                [ '%f', '%f', '%f', '%f', '%f' ],
                [ '%d' ]
            );

            if ( $updated !== false ) {
                $notice_message = __( 'Ledger entry configuration updated successfully.', 'arms-textdomain' );
                $notice_type    = 'success';
            } else {
                $notice_message = __( 'System error optimization failure during execution.', 'arms-textdomain' );
                $notice_type    = 'error';
            }
        }

        // ACTION 3: DELETE PAYROLL ENTRY
        if ( $action === 'delete_payroll' ) {
            $row_id  = intval( $_POST['row_id'] );
            $deleted = $wpdb->delete( $table_payroll, [ 'id' => $row_id ], [ '%d' ] );
            
            if ( $deleted ) {
                $notice_message = __( 'Payroll ledger entry purged successfully.', 'arms-textdomain' );
                $notice_type    = 'success';
            } else {
                $notice_message = __( 'Purge command discarded by table runtime handler.', 'arms-textdomain' );
                $notice_type    = 'error';
            }
        }
    }

    // Fetch database items for structural rendering
    $staff_entries = $wpdb->get_results( "SELECT id, first_name, last_name, role_category, salary FROM $table_staff WHERE status = 'active' ORDER BY first_name ASC" );
    $payroll_history = $wpdb->get_results( "
        SELECT p.*, s.first_name, s.last_name, s.role_category 
        FROM $table_payroll p 
        LEFT JOIN $table_staff s ON p.staff_id = s.id 
        ORDER BY p.pay_period DESC, p.payment_date DESC
    " );
    ?>

    <style>
        .arms-payroll-container { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .arms-subtab-navigation { display: flex; border-bottom: 1px solid #c3c4c7; margin-bottom: 20px; gap: 4px; }
        .arms-subtab-link { padding: 8px 16px; text-decoration: none; border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; color: #003376; font-weight: 600; margin-bottom: -1px; }
        .arms-subtab-link:hover { background: #f0f0f1; color: #135e96; }
        .arms-subtab-link.active { background: #fff; border-color: #c3c4c7; color: #1d2327; }
        
        .arms-payroll-grid { display: flex; gap: 24px; align-items: flex-start; }
        .arms-payroll-form-panel { flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .arms-payroll-summary-panel { width: 360px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px; box-sizing: border-box; position: sticky; top: 50px; }
        .arms-payroll-title { margin-top: 0; border-bottom: 1px solid #f0f0f1; padding-bottom: 12px; color: #003376; font-weight: 500; font-size: 18px; }
        .arms-form-row-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .arms-form-row-three { display: grid; grid-template-columns: 1.5fr 1fr 1.5fr; gap: 12px; margin-bottom: 16px; }
        .arms-form-group { margin-bottom: 16px; }
        .arms-form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; font-size: 13px; }
        .arms-input-field { width: 100%; height: 36px; padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; font-size: 14px; color: #2c3338; }
        .arms-input-field:focus { border-color: #003376; box-shadow: 0 0 0 1px #003376; outline: 2px solid transparent; }
        .arms-input-field:disabled, .arms-input-field[readonly] { background-color: #f0f0f1; color: #64748b; cursor: not-allowed; }
        
        .summary-ledger-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #444; }
        .summary-ledger-total { display: flex; justify-content: space-between; margin-top: 15px; padding-top: 15px; border-top: 2px dashed #c3c4c7; font-size: 18px; font-weight: bold; }
        .arms-submit-btn { margin-top: 30px }
        
        .arms-notice-msg { padding: 12px; margin-bottom: 16px; border-radius: 4px; font-size: 13px; }
        .arms-notice-success { background: #edf7ed; color: #1e4620; border: 1px solid #c3e6cb; }
        .arms-notice-error { background: #fdeded; color: #5f2120; border: 1px solid #f5c6cb; }

        .arms-data-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #c3c4c7; }
        .arms-data-table th, .arms-data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #c3c4c7; font-size: 13px; }
        .arms-data-table th { background: #f6f7f7; font-weight: 600; color: #1d2327; }
        .arms-btn-sm { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 4px; border: 1px solid #8c8f94; background: #f6f7f7; color: #2c3338; }
        .arms-btn-view { border-color: #003376; color: #003376; }
        .arms-btn-view:hover { background: #f0f6fa; }
        .arms-btn-delete { border-color: #b32d2e; color: #b32d2e; background: none; }
        .arms-btn-delete:hover { background: #fcf0f1; }

        .arms-modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 99999; }
    </style>

    <div class="arms-payroll-container">
        <!-- SUB-TAB TOGGLE LINKS -->
        <h2 class="nav-tab-wrapper arms-sub-tab-wrapper">
    <?php 
    // Query parameters থেকে ডাটা নিয়ে স্যানিটাইজ করা
    $current_page       = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
    $current_parent_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
    
    // active subtab চেক করা (যদি ইউআরএল-এ না থাকে তবে ডিফল্ট 'process' থাকবে)
    $current_sub_tab    = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'process'; 

    // বেস URL তৈরি করা
    $base_tab_url = admin_url( 'admin.php?page=' . $current_page );
    if ( ! empty( $current_parent_tab ) ) {
        $base_tab_url = add_query_arg( 'tab', $current_parent_tab, $base_tab_url );
    }

    // প্রতিটা ট্যাবের জন্য ডাইনামিক URL জেনারেট করা
    $process_url = esc_url( add_query_arg( 'subtab', 'process', $base_tab_url ) );
    $history_url = esc_url( add_query_arg( 'subtab', 'history', $base_tab_url ) );
    ?>

    <a class="nav-tab <?php echo $current_sub_tab === 'process' ? 'nav-tab-active' : ''; ?>" href="<?php echo $process_url; ?>">
        <?php _e('Add Payroll', 'arms-textdomain'); ?>
    </a>
    
    <a class="nav-tab <?php echo $current_sub_tab === 'history' ? 'nav-tab-active' : ''; ?>" href="<?php echo $history_url; ?>">
        <?php _e('All Payroll', 'arms-textdomain'); ?>
    </a>
</h2>

        <!-- NOTIFICATION BANNER OUTPUT -->
        <?php if ( ! empty( $notice_message ) ) : ?>
            <div class="arms-notice-msg arms-notice-<?php echo esc_attr( $notice_type ); ?>">
                <?php echo esc_html( $notice_message ); ?>
            </div>
        <?php endif; ?>

        <!-- MODULE 1: PROCESS NEW PAYROLL TAB -->
        <?php if ( $current_sub_tab === 'process' ) : ?>
        <form id="armsPayrollProcessingForm" method="POST" autocomplete="off">
            <?php wp_nonce_field( 'arms_payroll_management_nonce', 'security' ); ?>
            <input type="hidden" name="arms_payroll_action" value="save_payroll">

            <div class="arms-payroll-grid">
                <div class="arms-payroll-form-panel">
                    <h3 class="arms-payroll-title"><?php echo esc_html__( 'Staff Profile & Pay Assignment', 'arms-textdomain' ); ?></h3>
                    
                    <div class="arms-form-group">
                        <label><?php echo esc_html__( 'Select Employee Profile', 'arms-textdomain' ); ?></label>
                        <select name="staff_id" id="payroll_staff_select" class="arms-input-field" required>
                            <option value="" data-salary="0">-- <?php echo esc_html__( 'Choose Staff Member', 'arms-textdomain' ); ?> --</option>
                            <?php foreach ( $staff_entries as $staff ) : ?>
                                <option value="<?php echo esc_attr( $staff->id ); ?>" data-salary="<?php echo esc_attr( $staff->salary ); ?>">
                                    <?php echo esc_html( $staff->first_name . ' ' . $staff->last_name ); ?> (<?php echo esc_html( ucfirst( str_replace( '_', ' ', $staff->role_category ) ) ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="arms-form-row-three">
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Target Settlement Month', 'arms-textdomain' ); ?></label>
                            <select name="cycle_month" id="payroll_cycle_month" class="arms-input-field" required>
                                <?php 
                                $months = [
                                    '01' => __('January', 'arms-textdomain'), '02' => __('February', 'arms-textdomain'), 
                                    '03' => __('March', 'arms-textdomain'), '04' => __('April', 'arms-textdomain'), 
                                    '05' => __('May', 'arms-textdomain'), '06' => __('June', 'arms-textdomain'), 
                                    '07' => __('July', 'arms-textdomain'), '08' => __('August', 'arms-textdomain'), 
                                    '09' => __('September', 'arms-textdomain'), '10' => __('October', 'arms-textdomain'), 
                                    '11' => __('November', 'arms-textdomain'), '12' => __('December', 'arms-textdomain')
                                ];
                                $current_m = date('m');
                                foreach ( $months as $num => $name ) {
                                    echo '<option value="' . esc_attr($num) . '" ' . selected($current_m, $num, false) . '>' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Year', 'arms-textdomain' ); ?></label>
                            <select name="cycle_year" id="payroll_cycle_year" class="arms-input-field" required>
                                <?php 
                                $current_y = intval(date('Y'));
                                for ( $y = $current_y - 1; $y <= $current_y + 1; $y++ ) {
                                    echo '<option value="' . esc_attr($y) . '" ' . selected($current_y, $y, false) . '>' . esc_html($y) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Payment Date', 'arms-textdomain' ); ?></label>
                            <input type="date" name="payment_date" id="payroll_payment_date" class="arms-input-field" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="arms-form-row-two">
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Contracted Base Salary (BDT)', 'arms-textdomain' ); ?></label>
                            <input type="number" name="base_salary" id="payroll_base_salary" class="arms-input-field" value="0.00" readonly>
                        </div>
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Incentive Appraisals (BDT)', 'arms-textdomain' ); ?></label>
                            <input type="number" name="incentives" id="payroll_incentives" class="arms-input-field" value="0.00" min="0" step="0.01">
                        </div>
                    </div>

                    <h3 class="arms-payroll-title" style="margin-top: 24px; color: #003376;"><?php echo esc_html__( 'Adjustments & Supplementary Schedules', 'arms-textdomain' ); ?></h3>
                    <div class="arms-form-row-two">
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Performance Bonus (BDT)', 'arms-textdomain' ); ?></label>
                            <input type="number" name="bonus" id="payroll_bonus" class="arms-input-field" value="0.00" min="0" step="0.01">
                        </div>
                        <div class="arms-form-group">
                            <label><?php echo esc_html__( 'Attendance Absency Deductions (BDT)', 'arms-textdomain' ); ?></label>
                            <input type="number" name="attendance_deduction" id="payroll_attendance_deduction" class="arms-input-field" value="0.00" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="arms-form-row-two" style="grid-template-columns: 1fr;">
                        <div class="arms-form-group" style="width: calc(50% - 8px);">
                            <label><?php echo esc_html__( 'Withholding Tax Deductions (BDT)', 'arms-textdomain' ); ?></label>
                            <input type="number" name="tax_deduction" id="payroll_tax_deduction" class="arms-input-field" value="0.00" min="0" step="0.01">
                        </div>
                    </div>
                </div>

                <!-- REAL-TIME VISUAL CALCULATOR SIDEBAR -->
                <div class="arms-payroll-summary-panel">
                    <h3 class="arms-payroll-title" style="border-color: #c3c4c7; color: #1d2327;"><?php echo esc_html__( 'Settlement Summary', 'arms-textdomain' ); ?></h3>
                    <div class="summary-ledger-row"><span>Base Earnings</span><strong><span id="lbl_base">0.00</span> BDT</strong></div>
                    <div class="summary-ledger-row"><span>Incentives</span><div><strong style="color: #16a34a;">+ <span id="lbl_incentive">0.00</span> BDT</strong></div></div>
                    <div class="summary-ledger-row"><span>Bonuses</span><div><strong style="color: #16a34a;">+ <span id="lbl_bonus">0.00</span> BDT</strong></div></div>
                    <div class="summary-ledger-row"><span>Attendance Adj.</span><div><strong style="color: #dc2626;">- <span id="lbl_attendance">0.00</span> BDT</strong></div></div>
                    <div class="summary-ledger-row"><span>Withholding Tax</span><div><strong style="color: #dc2626;">- <span id="lbl_tax">0.00</span> BDT</strong></div></div>
                    <div class="summary-ledger-total"><span>Net Payable</span><span style="color: #003376;"><span id="lbl_net_total">0.00</span> BDT</span></div>
                    <button type="submit" class="arms-submit-btn"><?php echo esc_html__( 'Commit Ledger & Pay', 'arms-textdomain' ); ?></button>
                </div>
            </div>
        </form>

        <!-- MODULE 2: HISTORY LEDGER ARCHIVE -->
        <?php else : ?>
        <table class="arms-data-table">
            <thead>
                <tr>
                    <th><?php _e('Pay Period', 'arms-textdomain'); ?></th>
                    <th><?php _e('Payment Date', 'arms-textdomain'); ?></th>
                    <th><?php _e('Employee Name', 'arms-textdomain'); ?></th>
                    <th><?php _e('Role Designation', 'arms-textdomain'); ?></th>
                    <th><?php _e('Base Pay', 'arms-textdomain'); ?></th>
                    <th><?php _e('Adjustments (+/-)', 'arms-textdomain'); ?></th>
                    <th><?php _e('Net Remittance', 'arms-textdomain'); ?></th>
                    <th><?php _e('Status', 'arms-textdomain'); ?></th>
                    <th><?php _e('Actions Management', 'arms-textdomain'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $payroll_history ) ) : ?>
                    <tr><td colspan="9" style="text-align: center; padding: 20px; color: #64748b; font-style: italic;"><?php _e('No logged records found in database tables.', 'arms-textdomain'); ?></td></tr>
                <?php else : foreach ( $payroll_history as $row ) : 
                    $net_adjustments = ($row->bonus + $row->incentives) - ($row->attendance_deduction + $row->tax_deduction);
                ?>
                    <tr id="payroll-row-<?php echo esc_attr($row->id); ?>">
                        <td><strong><?php echo date('M Y', strtotime($row->pay_period)); ?></strong></td>
                        <td><?php echo date('Y-m-d', strtotime($row->payment_date)); ?></td>
                        <td><?php echo esc_html($row->first_name . ' ' . $row->last_name ?: __('Purged Profile', 'arms-textdomain')); ?></td>
                        <td><span class="description"><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->role_category))); ?></span></td>
                        <td><?php echo number_format($row->base_salary, 2); ?></td>
                        <td style="color: <?php echo $net_adjustments >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                            <?php echo ($net_adjustments >= 0 ? '+' : '') . number_format($net_adjustments, 2); ?>
                        </td>
                        <td><strong><?php echo number_format($row->net_payable, 2); ?> BDT</strong></td>
                        <td><span class="badge" style="background:#edf7ed; color:#1e4620; padding:3px 6px; border-radius:3px; font-size:11px; font-weight:bold;"><?php echo strtoupper($row->status); ?></span></td>
                        <td>
                            <button type="button" class="arms-btn-sm arms-btn-view arms-edit-trigger" 
                                    data-json="<?php echo esc_attr(wp_json_encode($row)); ?>"><?php _e('View / Edit', 'arms-textdomain'); ?></button>
                            
                            <!-- Pure POST Purge Action Button -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you absolutely sure you want to permanently erase this payroll ledger statement?');">
                                <?php wp_nonce_field( 'arms_payroll_management_nonce', 'security' ); ?>
                                <input type="hidden" name="arms_payroll_action" value="delete_payroll">
                                <input type="hidden" name="row_id" value="<?php echo esc_attr($row->id); ?>">
                                <button type="submit" class="arms-btn-sm arms-btn-delete"><?php _e('Delete', 'arms-textdomain'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- UPDATE MODAL POPUP WRAPPER (POST FORM SUBMIT BASED) -->
    <div id="armsPayrollModal" class="arms-modal-overlay" style="display: none;">
        <div class="arms-payroll-form-panel" style="max-width: 600px; position: relative; margin: auto;">
            <form method="POST">
                <?php wp_nonce_field( 'arms_payroll_management_nonce', 'security' ); ?>
                <input type="hidden" name="arms_payroll_action" value="update_payroll">
                <input type="hidden" name="row_id" id="modal_row_id">
                <input type="hidden" name="base_salary" id="modal_base_salary">

                <h3 class="arms-payroll-title" id="modalTitle"><?php _e('Alter History Record Instance', 'arms-textdomain'); ?></h3>

                <div class="arms-form-row-two">
                    <div class="arms-form-group"><label>Employee Name</label><input type="text" id="modal_staff_name" class="arms-input-field" disabled></div>
                    <div class="arms-form-group"><label>Pay Period</label><input type="text" id="modal_pay_period" class="arms-input-field" disabled></div>
                </div>
                <div class="arms-form-row-two">
                    <div class="arms-form-group"><label>Incentives Balance (BDT)</label><input type="number" name="incentives" id="modal_incentives" class="arms-input-field" step="0.01"></div>
                    <div class="arms-form-group"><label>Performance Bonuses (BDT)</label><input type="number" name="bonus" id="modal_bonus" class="arms-input-field" step="0.01"></div>
                </div>
                <div class="arms-form-row-two">
                    <div class="arms-form-group"><label>Absency Deductions (BDT)</label><input type="number" name="attendance_deduction" id="modal_attendance" class="arms-input-field" step="0.01"></div>
                    <div class="arms-form-group"><label>Withholding Tax (BDT)</label><input type="number" name="tax_deduction" id="modal_tax" class="arms-input-field" step="0.01"></div>
                </div>
                
                <div style="text-align: right; margin-top: 20px; font-size: 16px;">
                    <strong>Estimated Calculated Balance: <span id="modal_net_payable_lbl" style="color:#003376;">0.00</span> BDT</strong>
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
                    <button type="button" class="button" id="closePayrollModal"><?php _e('Cancel', 'arms-textdomain'); ?></button>
                    <button type="submit" class="button button-primary"><?php _e('Save Changes', 'arms-textdomain'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- UI MATHEMATICAL EVALUATION LAYER ONLY (NO AJAX NETWORK INTERACTION) -->
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Live processing view sync
            $('#payroll_staff_select').on('change', function() {
                var contractSalary = parseFloat($(this).find(':selected').data('salary')) || 0;
                $('#payroll_base_salary').val(contractSalary.toFixed(2));
                evaluateRealtimePayrollMath();
            });
            $('#payroll_incentives, #payroll_bonus, #payroll_attendance_deduction, #payroll_tax_deduction').on('input', evaluateRealtimePayrollMath);

            function evaluateRealtimePayrollMath() {
                var base = parseFloat($('#payroll_base_salary').val()) || 0,
                    inc  = parseFloat($('#payroll_incentives').val()) || 0,
                    bon  = parseFloat($('#payroll_bonus').val()) || 0,
                    att  = parseFloat($('#payroll_attendance_deduction').val()) || 0,
                    tax  = parseFloat($('#payroll_tax_deduction').val()) || 0;
                var netPayable = (base + inc + bon) - (att + tax);
                if(netPayable < 0) netPayable = 0;

                $('#lbl_base').text(base.toFixed(2));
                $('#lbl_incentive').text(inc.toFixed(2));
                $('#lbl_bonus').text(bon.toFixed(2));
                $('#lbl_attendance').text(att.toFixed(2));
                $('#lbl_tax').text(tax.toFixed(2));
                $('#lbl_net_total').text(netPayable.toFixed(2));
            }

            // Modal calculation updates
            $('#modal_incentives, #modal_bonus, #modal_attendance, #modal_tax').on('input', function() {
                var base = parseFloat($('#modal_base_salary').val()) || 0,
                    inc  = parseFloat($('#modal_incentives').val()) || 0,
                    bon  = parseFloat($('#modal_bonus').val()) || 0,
                    att  = parseFloat($('#modal_attendance').val()) || 0,
                    tax  = parseFloat($('#modal_tax').val()) || 0;
                var total = (base + inc + bon) - (att + tax);
                $('#modal_net_payable_lbl').text(Math.max(0, total).toFixed(2));
            });

            // Open Modal Handler
            $('.arms-edit-trigger').on('click', function() {
                var data = $(this).data('json');
                $('#modal_row_id').val(data.id);
                $('#modal_staff_name').val(data.first_name + ' ' + data.last_name);
                $('#modal_pay_period').val(data.pay_period);
                $('#modal_base_salary').val(data.base_salary);
                $('#modal_incentives').val(data.incentives);
                $('#modal_bonus').val(data.bonus);
                $('#modal_attendance').val(data.attendance_deduction);
                $('#modal_tax').val(data.tax_deduction);
                
                var total = (parseFloat(data.base_salary) + parseFloat(data.incentives) + parseFloat(data.bonus)) - (parseFloat(data.attendance_deduction) + parseFloat(data.tax_deduction));
                $('#modal_net_payable_lbl').text(Math.max(0, total).toFixed(2));
                $('#armsPayrollModal').fadeIn();
            });

            $('#closePayrollModal').on('click', function() { $('#armsPayrollModal').fadeOut(); });
        });
    </script>
    <?php
}