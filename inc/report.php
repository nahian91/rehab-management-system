<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Early Request Interceptor for Clean A4 Printing
 * Fully isolates print outputs to bypass standard WordPress theme wrappers
 */
add_action( 'admin_init', 'arms_handle_early_print_request' );
function arms_handle_early_print_request() {
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_download_pdf'] ) ) {
        if ( ! isset($_POST['security']) || ! wp_verify_nonce( $_POST['security'], 'arms_system_reporting_nonce' ) ) {
            return;
        }

        global $wpdb;
        $report_type  = isset( $_POST['report_type'] ) ? sanitize_key( $_POST['report_type'] ) : '';
        $sub_criteria = isset( $_POST['sub_criteria'] ) ? sanitize_text_field( $_POST['sub_criteria'] ) : 'all';
        $date_from    = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : date('Y-m-01');
        $date_to      = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : date('Y-m-d');

        $table_billing    = $wpdb->prefix . 'arms_billing';
        $table_expenses   = $wpdb->prefix . 'arms_expenses';
        $table_payroll    = $wpdb->prefix . 'arms_payroll';
        $table_admissions = $wpdb->prefix . 'arms_admissions';
        $table_physio     = $wpdb->prefix . 'arms_physio_logs';
        $table_inventory  = $wpdb->prefix . 'arms_inventory';

        $compiled_data = [];
        $total_income  = 0;
        $total_expense = 0;
        $net_profit    = 0;

        $start_date = $date_from . ' 00:00:00';
        $end_date   = $date_to . ' 23:59:59';

        if ( $report_type === 'income' ) {
            $income_segments = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'opd' ) {
                $income_segments[] = $wpdb->get_results( $wpdb->prepare( "SELECT invoice_id as ref, 'OPD Invoice' as category, total_price as amount, created_at as t_date, 'Outpatient General Invoicing' as notes FROM $table_billing WHERE created_at >= %s AND created_at <= %s AND billing_type = 'opd'", $start_date, $end_date ) );
            }
            if ( $sub_criteria === 'all' || $sub_criteria === 'admission' ) {
                $income_segments[] = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Admission' as category, final_bill_amount as amount, discharge_date as t_date, 'Inpatient Accommodation & Stay' as notes FROM $table_admissions WHERE discharge_date >= %s AND discharge_date <= %s AND payment_status = 'Paid'", $start_date, $end_date ) );
            }
            if ( $sub_criteria === 'all' || $sub_criteria === 'physio' ) {
                $income_segments[] = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Physiotherapy' as category, (advance_bill + per_session_bill) as amount, created_at as t_date, 'Rehab Assessment & Session Tracking' as notes FROM $table_physio WHERE created_at >= %s AND created_at <= %s", $start_date, $end_date ) );
            }
            foreach ( $income_segments as $segment ) { if ( ! empty( $segment ) ) { $compiled_data = array_merge( $compiled_data, $segment ); } }
            foreach ( $compiled_data as $row ) { $total_income += $row->amount; }
            usort( $compiled_data, function($a, $b) { return strtotime($b->t_date) - strtotime($a->t_date); } );

        } elseif ( $report_type === 'expense' ) {
            $general_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'Utility' ) {
                $general_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Utility' as category, total_amount as amount, transaction_date as t_date, notes FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s AND expense_category = 'Utility'", $date_from, $date_to ) );
            }
            
            $payroll_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'payroll' ) {
                $payroll_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT p.id as ref, 'Staff Salary' as category, p.net_payable as amount, p.payment_date as t_date, CONCAT('Salary Period: ', p.pay_period) as notes FROM $table_payroll p WHERE p.payment_date >= %s AND p.payment_date <= %s", $start_date, $end_date ) );
            }

            $inventory_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'Equipment' ) {
                $inventory_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Equipment' as category, (purchase_price * available_stock) as amount, updated_at as t_date, CONCAT(item_name, ' (SKU: ', sku, ') Purchase Intake') as notes FROM $table_inventory WHERE updated_at >= %s AND updated_at <= %s", $start_date, $end_date ) );
            }

            $compiled_data = array_merge( $general_expenses, $payroll_expenses, $inventory_expenses );
            foreach ( $compiled_data as $row ) { $total_expense += $row->amount; }
            usort( $compiled_data, function($a, $b) { return strtotime($b->t_date) - strtotime($a->t_date); } );

        } elseif ( $report_type === 'profit' ) {
            $inc_bills     = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_price) FROM $table_billing WHERE created_at >= %s AND created_at <= %s AND billing_type = 'opd'", $start_date, $end_date ) ) ?: 0;
            $inc_adms      = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(final_bill_amount) FROM $table_admissions WHERE discharge_date >= %s AND discharge_date <= %s AND payment_status = 'Paid'", $start_date, $end_date ) ) ?: 0;
            $inc_physio    = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(advance_bill + per_session_bill) FROM $table_physio WHERE created_at >= %s AND created_at <= %s", $start_date, $end_date ) ) ?: 0;
            
            $exp_vouchers  = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_amount) FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s AND expense_category = 'Utility'", $date_from, $date_to ) ) ?: 0;
            $exp_salaries  = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(net_payable) FROM $table_payroll WHERE payment_date >= %s AND payment_date <= %s", $start_date, $end_date ) ) ?: 0;
            $exp_inventory = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(purchase_price * available_stock) FROM $table_inventory WHERE updated_at >= %s AND updated_at <= %s", $start_date, $end_date ) ) ?: 0;
            
            $total_income  = floatval($inc_bills) + floatval($inc_adms) + floatval($inc_physio);
            $total_expense = floatval($exp_vouchers) + floatval($exp_salaries) + floatval($exp_inventory);
            $net_profit    = $total_income - $total_expense;
        }

        self_contained_print_stream($report_type, $sub_criteria, $date_from, $date_to, $compiled_data, $total_income, $total_expense, $net_profit);
    }
}

/**
 * Screen View Engine (Main Reports Dashboard UI Wrapper)
 */
function arms_reports_tab() {
    global $wpdb;

    $table_billing    = $wpdb->prefix . 'arms_billing';
    $table_expenses   = $wpdb->prefix . 'arms_expenses';
    $table_payroll    = $wpdb->prefix . 'arms_payroll';
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_physio     = $wpdb->prefix . 'arms_physio_logs';
    $table_inventory  = $wpdb->prefix . 'arms_inventory';

    $report_type  = isset( $_POST['report_type'] ) ? sanitize_key( $_POST['report_type'] ) : '';
    $sub_criteria = isset( $_POST['sub_criteria'] ) ? sanitize_text_field( $_POST['sub_criteria'] ) : 'all';
    $date_from    = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : date('Y-m-01');
    $date_to      = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : date('Y-m-d');

    $compiled_data = [];
    $total_income  = 0;
    $total_expense = 0;
    $net_profit    = 0;

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_trigger_report'] ) ) {
        check_admin_referer( 'arms_system_reporting_nonce', 'security' );

        $start_date = $date_from . ' 00:00:00';
        $end_date   = $date_to . ' 23:59:59';

        if ( $report_type === 'income' ) {
            $income_segments = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'opd' ) {
                $income_segments[] = $wpdb->get_results( $wpdb->prepare( "SELECT invoice_id as ref, 'OPD Invoice' as category, total_price as amount, created_at as t_date, 'Outpatient General Invoicing' as notes FROM $table_billing WHERE created_at >= %s AND created_at <= %s AND billing_type = 'opd'", $start_date, $end_date ) );
            }
            if ( $sub_criteria === 'all' || $sub_criteria === 'admission' ) {
                $income_segments[] = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Admission' as category, final_bill_amount as amount, discharge_date as t_date, 'Inpatient Accommodation & Stay' as notes FROM $table_admissions WHERE discharge_date >= %s AND discharge_date <= %s AND payment_status = 'Paid'", $start_date, $end_date ) );
            }
            if ( $sub_criteria === 'all' || $sub_criteria === 'physio' ) {
                $income_segments[] = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Physiotherapy' as category, (advance_bill + per_session_bill) as amount, created_at as t_date, 'Rehab Assessment & Session Tracking' as notes FROM $table_physio WHERE created_at >= %s AND created_at <= %s", $start_date, $end_date ) );
            }
            foreach ( $income_segments as $segment ) { if ( ! empty( $segment ) ) { $compiled_data = array_merge( $compiled_data, $segment ); } }
            foreach ( $compiled_data as $row ) { $total_income += $row->amount; }
            usort( $compiled_data, function($a, $b) { return strtotime($b->t_date) - strtotime($a->t_date); } );

        } elseif ( $report_type === 'expense' ) {
            $general_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'Utility' ) {
                $general_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Utility' as category, total_amount as amount, transaction_date as t_date, notes FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s AND expense_category = 'Utility'", $date_from, $date_to ) );
            }
            
            $payroll_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'payroll' ) {
                $payroll_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT p.id as ref, 'Staff Salary' as category, p.net_payable as amount, p.payment_date as t_date, CONCAT('Salary Period: ', p.pay_period) as notes FROM $table_payroll p WHERE p.payment_date >= %s AND p.payment_date <= %s", $start_date, $end_date ) );
            }

            $inventory_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'Equipment' ) {
                $inventory_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Equipment' as category, (purchase_price * available_stock) as amount, updated_at as t_date, CONCAT(item_name, ' (SKU: ', sku, ') Purchase Intake') as notes FROM $table_inventory WHERE updated_at >= %s AND updated_at <= %s", $start_date, $end_date ) );
            }

            $compiled_data = array_merge( $general_expenses, $payroll_expenses, $inventory_expenses );
            foreach ( $compiled_data as $row ) { $total_expense += $row->amount; }
            usort( $compiled_data, function($a, $b) { return strtotime($b->t_date) - strtotime($a->t_date); } );

        } elseif ( $report_type === 'profit' ) {
            $inc_bills     = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_price) FROM $table_billing WHERE created_at >= %s AND created_at <= %s AND billing_type = 'opd'", $start_date, $end_date ) ) ?: 0;
            $inc_adms      = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(final_bill_amount) FROM $table_admissions WHERE discharge_date >= %s AND discharge_date <= %s AND payment_status = 'Paid'", $start_date, $end_date ) ) ?: 0;
            $inc_physio    = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(advance_bill + per_session_bill) FROM $table_physio WHERE created_at >= %s AND created_at <= %s", $start_date, $end_date ) ) ?: 0;
            
            $exp_vouchers  = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_amount) FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s AND expense_category = 'Utility'", $date_from, $date_to ) ) ?: 0;
            $exp_salaries  = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(net_payable) FROM $table_payroll WHERE payment_date >= %s AND payment_date <= %s", $start_date, $end_date ) ) ?: 0;
            $exp_inventory = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(purchase_price * available_stock) FROM $table_inventory WHERE updated_at >= %s AND updated_at <= %s", $start_date, $end_date ) ) ?: 0;
            
            $total_income  = floatval($inc_bills) + floatval($inc_adms) + floatval($inc_physio);
            $total_expense = floatval($exp_vouchers) + floatval($exp_salaries) + floatval($exp_inventory);
            $net_profit    = $total_income - $total_expense;
        }
    }
    ?>

    <style>
        .arms-rep-box { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .arms-rep-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 20px; }
        .arms-rep-title { margin: 0 0 16px 0; padding-bottom: 12px; border-bottom: 1px solid #f0f0f1; color: #2271b1; font-size: 18px; font-weight: 500; }
        .arms-rep-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: flex-end; }
        .arms-rep-field { display: flex; flex-direction: column; }
        .arms-rep-field label { font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #1d2327; }
        .arms-select-ctrl, .arms-input-ctrl { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .arms-btn-view { height: 36px; background: #f6f7f7; color: #2271b1; border: 1px solid #2271b1; border-radius: 4px; font-weight: 600; cursor: pointer; }
        .arms-btn-view:hover { background: #f0f6fa; }
        .arms-btn-pdf { height: 36px; background: #2271b1; color: #fff; border: 1px solid #0a4b78; border-radius: 4px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .arms-btn-pdf:hover { background: #135e96; color: #fff; }
        
        .arms-table-rep { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #c3c4c7; margin-top: 15px; }
        .arms-table-rep th, .arms-table-rep td { padding: 12px; text-align: left; border-bottom: 1px solid #c3c4c7; font-size: 13px; }
        .arms-table-rep th { background: #f6f7f7; font-weight: 600; }
        .arms-summary-strip { background: #f0f6fa; padding: 15px; border-left: 4px solid #2271b1; font-size: 15px; font-weight: bold; margin-top: 20px; display: flex; justify-content: space-between; }
        .arms-profit-matrix { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 15px; }
        .matrix-widget { padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; border-top-width: 4px; }
        .matrix-widget.inc { border-top-color: #16a34a; }
        .matrix-widget.exp { border-top-color: #dc2626; }
        .matrix-widget.prf { border-top-color: #2271b1; }
        .matrix-widget h4 { margin: 0 0 8px 0; color: #64748b; font-size: 13px; text-transform: uppercase; }
        .matrix-widget .value { font-size: 24px; font-weight: 700; }
    </style>

    <div class="arms-rep-box">
        <div class="arms-rep-card">
            <h3 class="arms-rep-title"><?php _e( 'Financial Statement Audits & Intelligence Desk', 'arms-textdomain' ); ?></h3>
            
            <form method="POST" action="">
                <?php wp_nonce_field( 'arms_system_reporting_nonce', 'security' ); ?>

                <div class="arms-rep-grid">
                    <div class="arms-rep-field">
                        <label><?php _e( 'Primary Target Filter', 'arms-textdomain' ); ?></label>
                        <select name="report_type" id="arms_report_type" class="arms-select-ctrl" required>
                            <option value=""><?php _e( '-- Choose Ledger Sector --', 'arms-textdomain' ); ?></option>
                            <option value="income" <?php selected( $report_type, 'income' ); ?>><?php _e( 'Income Ledger', 'arms-textdomain' ); ?></option>
                            <option value="expense" <?php selected( $report_type, 'expense' ); ?>><?php _e( 'Expense Sheets', 'arms-textdomain' ); ?></option>
                            <option value="profit" <?php selected( $report_type, 'profit' ); ?>><?php _e( 'Net Profit Matrix', 'arms-textdomain' ); ?></option>
                        </select>
                    </div>

                    <div class="arms-rep-field" id="criteria_wrapper" style="display: none;">
                        <label id="criteria_label"><?php _e( 'Context Category Filter', 'arms-textdomain' ); ?></label>
                        <select name="sub_criteria" id="arms_sub_criteria" class="arms-select-ctrl"></select>
                    </div>

                    <div class="arms-rep-field">
                        <label><?php _e( 'Timeline Bounds (From)', 'arms-textdomain' ); ?></label>
                        <input type="date" name="date_from" class="arms-input-ctrl" value="<?php echo esc_attr( $date_from ); ?>" required>
                    </div>

                    <div class="arms-rep-field">
                        <label><?php _e( 'Timeline Bounds (To)', 'arms-textdomain' ); ?></label>
                        <input type="date" name="date_to" class="arms-input-ctrl" value="<?php echo esc_attr( $date_to ); ?>" required>
                    </div>

                    <button type="submit" name="arms_trigger_report" class="arms-btn-view">
                        <?php _e( 'Preview on Screen', 'arms-textdomain' ); ?>
                    </button>

                    <button type="submit" name="arms_download_pdf" class="arms-btn-pdf">
                        <span class="dashicons dashicons-pdf"></span> <?php _e( 'Print / Save PDF', 'arms-textdomain' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_trigger_report'] ) ) : ?>
            <?php if ( $report_type === 'profit' ) : ?>
                <div class="arms-profit-matrix">
                    <div class="matrix-widget inc"><h4>Gross Revenue</h4><div class="value" style="color: #16a34a;"><?php echo number_format($total_income, 2); ?> BDT</div></div>
                    <div class="matrix-widget exp"><h4>Total Deductions</h4><div class="value" style="color: #dc2626;"><?php echo number_format($total_expense, 2); ?> BDT</div></div>
                    <div class="matrix-widget prf"><h4>Net Yield</h4><div class="value" style="color: #2271b1;"><?php echo number_format($net_profit, 2); ?> BDT</div></div>
                </div>
            <?php else : ?>
                <div class="arms-rep-card">
                    <table class="arms-table-rep">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Reference Index</th>
                                <th>Target Sector</th>
                                <th>Memo Descriptions</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty($compiled_data) ) : ?>
                                <tr><td colspan="5" style="text-align:center; color:#64748b; font-style:italic;">No records found.</td></tr>
                            <?php else : foreach ( $compiled_data as $item ) : ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($item->t_date)); ?></td>
                                    <td><code><?php echo esc_html($item->ref); ?></code></td>
                                    <td><span style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size:11px; font-weight:600; text-transform:uppercase;"><?php echo esc_html($item->category); ?></span></td>
                                    <td><?php echo esc_html($item->notes ?: 'N/A'); ?></td>
                                    <td style="font-weight: 600; color: <?php echo $report_type === 'income' ? '#16a34a' : '#dc2626'; ?>;">
                                        <?php echo $report_type === 'income' ? '+' : '-'; ?> <?php echo number_format($item->amount, 2); ?> BDT
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <div class="arms-summary-strip">
                        <span>Cumulated Accounting Aggregate:</span>
                        <span style="color: <?php echo $report_type === 'income' ? '#16a34a' : '#dc2626'; ?>;"><?php echo number_format(($report_type === 'income' ? $total_income : $total_expense), 2); ?> BDT</span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var primaryMenu = document.getElementById('arms_report_type');
            var wrapper     = document.getElementById('criteria_wrapper');
            var label       = document.getElementById('criteria_label');
            var secondaryMenu = document.getElementById('arms_sub_criteria');
            var currentStickyValue = "<?php echo esc_js($sub_criteria); ?>";

            function rebuildFormDropdownMap(selection, initialLoad) {
                secondaryMenu.innerHTML = '';
                if (selection === 'income') {
                    wrapper.style.display = 'flex'; label.textContent = "Billing Channel Stream Source";
                    secondaryMenu.add(new Option("All Revenue Streams Combined", "all"));
                    secondaryMenu.add(new Option("Physiotherapy", "physio"));
                    secondaryMenu.add(new Option("OPD Invoices", "opd"));
                    secondaryMenu.add(new Option("Admission Registry", "admission"));
                } else if (selection === 'expense') {
                    wrapper.style.display = 'flex'; label.textContent = "Expense Categorization Channels";
                    secondaryMenu.add(new Option("All System Expenditures Combined", "all"));
                    secondaryMenu.add(new Option("Staff Salary", "payroll"));
                    secondaryMenu.add(new Option("Utility Bills", "Utility"));
                    secondaryMenu.add(new Option("Equipment & Assets", "Equipment"));
                } else { wrapper.style.display = 'none'; return; }
                if (initialLoad && currentStickyValue !== '') { secondaryMenu.value = currentStickyValue; }
            }
            primaryMenu.addEventListener('change', function() { rebuildFormDropdownMap(this.value, false); });
            if (primaryMenu.value !== '') { rebuildFormDropdownMap(primaryMenu.value, true); }
        });
    </script>
    <?php
}

/**
 * Isolated A4 Printable Formatter Engine
 */
function self_contained_print_stream($report_type, $sub_criteria, $date_from, $date_to, $compiled_data, $total_income, $total_expense, $net_profit) {
    if (ob_get_length()) ob_end_clean();
    
    $brand_name    = "Advanced Rehab & Wellness";
    $brand_logo_url = "https://pos.arawbd.com/wp-content/plugins/rehab-management-system/inc/img/logo.png";
    $brand_phone   = "+880 13 2476 3317";
    $brand_email   = "arawsylhet@gmail.com";
    $brand_address = "Shahjalal City College Building,\nGarden Tower,\nShahjalal Uposhahar, Sylhet";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>ARMS_Print_Receipt_<?php echo date('Ymd_His'); ?></title>
        <style>
            @media print, screen {
                #wpadminbar, #adminmenuback, #adminmenuwrap, #wpfooter, .notice, #wpbody-content #screen-meta-links {
                    display: none !important;
                    width: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                }
                html, body, #wpwrap, #wpcontent, #wpbody, #wpbody-content {
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff !important;
                    position: static !important;
                    width: 100% !important;
                }
            }
            @page { size: A4 portrait; margin: 1.5cm 1.2cm 1.5cm 1.2cm; }
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e293b; font-size: 12px; line-height: 1.4; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-header { width: 100%; border-bottom: 2px solid #1e3a8a; padding-bottom: 18px; margin-bottom: 22px; }
            .header-table { width: 100%; border-collapse: collapse; }
            .header-table td { padding: 0; vertical-align: top; border: none; }
            .logo-box { width: 75px; height: 75px; text-align: center; }
            .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .brand-headline { font-size: 22px; font-weight: 800; color: #1e3a8a; margin: 0 0 4px 0; letter-spacing: -0.3px; }
            .brand-address-block { font-size: 11px; color: #475569; line-height: 1.4; white-space: pre-line; margin-bottom: 6px; }
            .brand-meta-info { font-size: 11px; color: #334155; line-height: 1.3; }
            .doc-badge { text-align: right; }
            .badge-title { font-size: 15px; font-weight: 700; color: #0f172a; text-transform: uppercase; margin: 0 0 4px 0; }
            .badge-details { font-size: 11px; color: #64748b; line-height: 1.4; }
            .scope-bar { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; margin-bottom: 20px; font-size: 11px; box-sizing: border-box; }
            .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
            .data-table th { background-color: #f1f5f9 !important; color: #0f172a; font-weight: 700; text-align: left; padding: 10px 12px; border: 1px solid #cbd5e1; text-transform: uppercase; font-size: 10px; letter-spacing: 0.3px; }
            .data-table td { padding: 9px 12px; border: 1px solid #e2e8f0; font-size: 11px; color: #334155; }
            .data-table tr:nth-child(even) { background-color: #f8fafc !important; }
            .grand-total-strip { margin-top: 20px; text-align: right; font-size: 13px; font-weight: bold; background: #f1f5f9 !important; padding: 12px 18px; border: 1px solid #cbd5e1; border-right: 5px solid #1e3a8a; }
            .matrix-container { width: 100%; }
            .matrix-card { padding: 16px; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 14px; border-left: 5px solid #1e3a8a; }
            .matrix-card h5 { margin: 0 0 4px 0; color: #64748b; font-size: 10px; text-transform: uppercase; }
            .matrix-card h3 { margin: 0; font-size: 20px; font-weight: 700; }
            @media print { body { padding: 0; margin: 0; } .data-table th { background-color: #f1f5f9 !important; } .grand-total-strip { background-color: #f1f5f9 !important; } }
        </style>
    </head>
    <body>
        <div class="print-header">
            <table class="header-table">
                <tr>
                    <td style="width: 65%;">
                        <table style="width:100%; border:none; border-collapse:collapse;">
                            <tr>
                                <td style="width: 85px; border:none; padding:0;">
                                    <div class="logo-box"><img src="<?php echo esc_url($brand_logo_url); ?>" alt="Logo"></div>
                                </td>
                                <td style="border:none; padding-left:18px; vertical-align:top;">
                                    <h1 class="brand-headline"><?php echo esc_html($brand_name); ?></h1>
                                    <div class="brand-address-block"><?php echo esc_html($brand_address); ?></div>
                                    <div class="brand-meta-info"><strong>Phone:</strong> <?php echo esc_html($brand_phone); ?> &nbsp;|&nbsp; <strong>Email:</strong> <?php echo esc_html($brand_email); ?></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 35%; text-align: right; vertical-align: top;">
                        <div class="doc-badge">
                            <div class="badge-title">Audit Statement</div>
                            <div class="badge-details"><strong>Date Generated:</strong> <?php echo date('Y-m-d'); ?><br><strong>Time:</strong> <?php echo date('h:i A'); ?><br><strong>Status:</strong> System Extract</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="scope-bar">
            <table style="width:100%; border:none; border-collapse:collapse;">
                <tr>
                    <td style="padding:0; border:none;"><strong>Ledger Filter Target:</strong> <?php echo strtoupper($report_type); ?> (<?php echo esc_html($sub_criteria); ?>)</td>
                    <td style="padding:0; border:none; text-align:right;"><strong>Statement Timeline Window:</strong> <?php echo esc_html($date_from) . ' to ' . esc_html($date_to); ?></td>
                </tr>
            </table>
        </div>

        <?php if ( $report_type === 'profit' ) : ?>
            <div class="matrix-container">
                <div class="matrix-card" style="border-left-color: #16a34a;"><h5>Gross System Revenue Yield</h5><h3 style="color: #16a34a;"><?php echo number_format($total_income, 2); ?> BDT</h3></div>
                <div class="matrix-card" style="border-left-color: #dc2626;"><h5>Total Deductions & Overheads</h5><h3 style="color: #dc2626;"><?php echo number_format($total_expense, 2); ?> BDT</h3></div>
                <div class="matrix-card" style="border-left-color: #2271b1;"><h5>Net Financial Surplus Yield</h5><h3 style="color: #2271b1;"><?php echo number_format($net_profit, 2); ?> BDT</h3></div>
            </div>
        <?php else : ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Timestamp</th>
                        <th style="width: 15%;">Ref Index</th>
                        <th style="width: 25%;">Target Category Sector</th>
                        <th style="width: 30%;">Memo Descriptions</th>
                        <th style="width: 15%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compiled_data)): ?>
                        <tr><td colspan="5" style="text-align:center; font-style:italic; color:#64748b;">No transaction entries compiled for this timeframe context.</td></tr>
                    <?php else: foreach ( $compiled_data as $item ) : ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($item->t_date)); ?></td>
                            <td><code><?php echo esc_html($item->ref); ?></code></td>
                            <td><strong><?php echo strtoupper(esc_html($item->category)); ?></strong></td>
                            <td><?php echo esc_html($item->notes ?: '──'); ?></td>
                            <td style="text-align: right; font-weight: 700;"><?php echo number_format($item->amount, 2); ?> BDT</td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <div class="grand-total-strip">Cumulated Accounting Aggregate: <span style="color: #1e3a8a; margin-left: 10px; font-size:14px;"><?php echo number_format(($report_type === 'income' ? $total_income : $total_expense), 2); ?> BDT</span></div>
        <?php endif; ?>
        <script type="text/javascript">window.onload = function() { window.print(); };</script>
    </body>
    </html>
    <?php
    exit;
}