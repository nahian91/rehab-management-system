<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Advanced ARMS Core Reporting Engine Interface with Native PDF Engine
 */
function arms_reports_tab() {
    global $wpdb;

    // Mapping dynamic system endpoints
    $table_billing  = $wpdb->prefix . 'arms_billing';
    $table_expenses = $wpdb->prefix . 'arms_expenses';
    $table_payroll  = $wpdb->prefix . 'arms_payroll';

    // Sticky Parameters Processing Layouts
    $report_type  = isset( $_POST['report_type'] ) ? sanitize_key( $_POST['report_type'] ) : '';
    $sub_criteria = isset( $_POST['sub_criteria'] ) ? sanitize_text_field( $_POST['sub_criteria'] ) : 'all';
    $date_from    = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : date('Y-m-01');
    $date_to      = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : date('Y-m-d');

    $compiled_data = [];
    $total_income  = 0;
    $total_expense = 0;
    $net_profit    = 0;

    // ==========================================
    // BACKEND CONTROLLER: PDF ENGINE INJECTION
    // ==========================================
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_download_pdf'] ) ) {
        check_admin_referer( 'arms_system_reporting_nonce', 'security' );

        // 1. Load Dompdf Library Safely
        if ( file_exists( WP_PLUGIN_DIR . '/arms-medical/lib/dompdf/autorun.inf' ) ) {
            require_once WP_PLUGIN_DIR . '/arms-medical/lib/dompdf/autoload.inc.php';
        } elseif ( file_exists( ABSPATH . 'vendor/autoload.php' ) ) {
            require_once ABSPATH . 'vendor/autoload.php'; 
        } else {
            wp_die( __( 'Dompdf library wrapper not found! Please run "composer require dompdf/dompdf" or install it inside libs folder.', 'arms-textdomain' ) );
        }

        $start_date = $date_from . ' 00:00:00';
        $end_date   = $date_to . ' 23:59:59';
        $pdf_data   = [];
        $pdf_inc    = 0;
        $pdf_exp    = 0;

        // Fetch Data matching parameters
        if ( $report_type === 'income' ) {
            $bill_query = "SELECT invoice_id as ref, billing_type as category, total_price as amount, created_at as t_date, 'Patient Invoice' as notes FROM $table_billing WHERE created_at >= %s AND created_at <= %s";
            $bill_args  = [ $start_date, $end_date ];
            if ( $sub_criteria !== 'all' ) { $bill_query .= " AND billing_type = %s"; $bill_args[] = $sub_criteria; }
            $pdf_data = $wpdb->get_results( $wpdb->prepare( $bill_query, $bill_args ) );
            foreach ( $pdf_data as $row ) { $pdf_inc += $row->amount; }
        } elseif ( $report_type === 'expense' ) {
            $exp_query = "SELECT id as ref, expense_category as category, total_amount as amount, transaction_date as t_date, notes FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s";
            $exp_args  = [ $date_from, $date_to ];
            if ( $sub_criteria !== 'all' && $sub_criteria !== 'payroll' ) { $exp_query .= " AND expense_category = %s"; $exp_args[] = $sub_criteria; }
            $general = $wpdb->get_results( $wpdb->prepare( $exp_query, $exp_args ) );
            $payroll = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'payroll' ) {
                $payroll = $wpdb->get_results( $wpdb->prepare( "SELECT id as ref, 'Staff Payroll' as category, net_payable as amount, payment_date as t_date, 'Salary Allocation' as notes FROM $table_payroll WHERE payment_date >= %s AND payment_date <= %s", $start_date, $end_date ) );
            }
            $pdf_data = array_merge( $general, $payroll );
            foreach ( $pdf_data as $row ) { $pdf_exp += $row->amount; }
            usort( $pdf_data, function($a, $b) { return strtotime($b->t_date) - strtotime($a->t_date); } );
        } elseif ( $report_type === 'profit' ) {
            $pdf_inc = floatval($wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_price) FROM $table_billing WHERE created_at >= %s AND created_at <= %s", $start_date, $end_date ) ) ?: 0);
            $vouch   = floatval($wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_amount) FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s", $date_from, $date_to ) ) ?: 0);
            $sal     = floatval($wpdb->get_var( $wpdb->prepare( "SELECT SUM(net_payable) FROM $table_payroll WHERE payment_date >= %s AND payment_date <= %s", $start_date, $end_date ) ) ?: 0);
            $pdf_exp = $vouch + $sal;
        }

        // 2. Build HTML Template Data Content
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Helvetica, Arial, sans-serif; color: #333; font-size: 12px; line-height: 1.4; }
                .invoice-header { border-bottom: 2px solid #2271b1; padding-bottom: 10px; margin-bottom: 20px; }
                .invoice-title { font-size: 20px; font-weight: bold; color: #2271b1; text-transform: uppercase; }
                .meta-table { width: 100%; margin-bottom: 20px; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .data-table th { background-color: #f2f2f2; font-weight: bold; text-align: left; padding: 8px; border: 1px solid #ddd; text-transform: uppercase; font-size: 11px; }
                .data-table td { padding: 8px; border: 1px solid #ddd; }
                .summary-box { margin-top: 30px; text-align: right; font-size: 14px; font-weight: bold; background: #f9f9f9; padding: 10px; border-right: 5px solid #2271b1; }
                .matrix-container { margin-top: 20px; }
                .matrix-card { padding: 15px; border: 1px solid #ddd; margin-bottom: 10px; background: #fff; }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <table class="meta-table">
                    <tr>
                        <td><span class="invoice-title">ARMS Financial Statement</span></td>
                        <td style="text-align: right;"><strong>Date:</strong> <?php echo date('Y-m-d'); ?></td>
                    </tr>
                </table>
            </div>

            <table class="meta-table">
                <tr>
                    <td><strong>Report Target Metric:</strong> <?php echo strtoupper($report_type); ?> (<?php echo esc_html($sub_criteria); ?>)</td>
                    <td style="text-align: right;"><strong>Statement Period:</strong> <?php echo esc_html($date_from) . ' to ' . esc_html($date_to); ?></td>
                </tr>
            </table>

            <?php if ( $report_type === 'profit' ) : ?>
                <div class="matrix-container">
                    <div class="matrix-card" style="border-left: 4px solid #16a34a;"><h4>GROSS SYSTEM REVENUE</h4><h3><?php echo number_format($pdf_inc, 2); ?> BDT</h3></div>
                    <div class="matrix-card" style="border-left: 4px solid #dc2626;"><h4>TOTAL OPERATIONAL DEDUCTIONS</h4><h3><?php echo number_format($pdf_exp, 2); ?> BDT</h3></div>
                    <div class="matrix-card" style="border-left: 4px solid #2271b1;"><h4>NET MARGIN YIELD</h4><h3><?php echo number_format(($pdf_inc - $pdf_exp), 2); ?> BDT</h3></div>
                </div>
            <?php else : ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Reference Index</th>
                            <th>Target Sector</th>
                            <th>Supplemental Notes</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pdf_data as $item ) : ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($item->t_date)); ?></td>
                                <td><code><?php echo esc_html($item->ref); ?></code></td>
                                <td><?php echo strtoupper(esc_html($item->category)); ?></td>
                                <td><?php echo esc_html($item->notes ?: 'N/A'); ?></td>
                                <td style="text-align: right; font-weight: bold;">
                                    <?php echo number_format($item->amount, 2); ?> BDT
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="summary-box">
                    Cumulated Accounting Aggregate: 
                    <span style="color: #2271b1;"><?php echo number_format(($report_type === 'income' ? $pdf_inc : $pdf_exp), 2); ?> BDT</span>
                </div>
            <?php endif; ?>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // 3. Fire Dompdf Operations using Fully Qualified Paths directly
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); 

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if (ob_get_length()) ob_end_clean();

        // Download Force Header Handlers
        $filename = "ARMS_Report_" . $report_type . "_" . date('Ymd') . ".pdf";
        $dompdf->stream($filename, array("Attachment" => true));
        exit;
    }

    // ==========================================
    // BACKEND ENGINE: SCREEN SCREEN GENERATOR
    // ==========================================
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_trigger_report'] ) ) {
        check_admin_referer( 'arms_system_reporting_nonce', 'security' );

        $start_date = $date_from . ' 00:00:00';
        $end_date   = $date_to . ' 23:59:59';

        if ( $report_type === 'income' ) {
            $bill_query = "SELECT invoice_id as ref, billing_type as category, total_price as amount, created_at as t_date, 'Patient Invoice' as notes FROM $table_billing WHERE created_at >= %s AND created_at <= %s";
            $bill_args  = [ $start_date, $end_date ];
            if ( $sub_criteria !== 'all' ) { $bill_query .= " AND billing_type = %s"; $bill_args[] = $sub_criteria; }
            $compiled_data = $wpdb->get_results( $wpdb->prepare( $bill_query, $bill_args ) );
            foreach ( $compiled_data as $row ) { $total_income += $row->amount; }
        } elseif ( $report_type === 'expense' ) {
            $exp_query = "SELECT id as ref, expense_category as category, total_amount as amount, transaction_date as t_date, notes FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s";
            $exp_args  = [ $date_from, $date_to ];
            if ( $sub_criteria !== 'all' && $sub_criteria !== 'payroll' ) { $exp_query .= " AND expense_category = %s"; $exp_args[] = $sub_criteria; }
            $general_expenses = $wpdb->get_results( $wpdb->prepare( $exp_query, $exp_args ) );
            $payroll_expenses = [];
            if ( $sub_criteria === 'all' || $sub_criteria === 'payroll' ) {
                $payroll_expenses = $wpdb->get_results( $wpdb->prepare( "SELECT p.id as ref, 'Staff Payroll' as category, p.net_payable as amount, p.payment_date as t_date, CONCAT('Salary Period: ', p.pay_period) as notes FROM $table_payroll p WHERE p.payment_date >= %s AND p.payment_date <= %s", $start_date, $end_date ) );
            }
            $compiled_data = array_merge( $general_expenses, $payroll_expenses );
            foreach ( $compiled_data as $row ) { $total_expense += $row->amount; }
            usort( $compiled_data, function($a, $b) { return strtotime($b->t_date) - strtotime($a->t_date); } );
        } elseif ( $report_type === 'profit' ) {
            $inc_bills = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_price) FROM $table_billing WHERE created_at >= %s AND created_at <= %s", $start_date, $end_date ) ) ?: 0;
            $exp_vouchers = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_amount) FROM $table_expenses WHERE transaction_date >= %s AND transaction_date <= %s", $date_from, $date_to ) ) ?: 0;
            $exp_salaries = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(net_payable) FROM $table_payroll WHERE payment_date >= %s AND payment_date <= %s", $start_date, $end_date ) ) ?: 0;
            $total_income  = floatval($inc_bills);
            $total_expense = floatval($exp_vouchers) + floatval($exp_salaries);
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

                    <!-- ACTION BUTTON 1: SCREEN ANALYSIS -->
                    <button type="submit" name="arms_trigger_report" class="arms-btn-view">
                        <?php _e( 'Preview on Screen', 'arms-textdomain' ); ?>
                    </button>

                    <!-- ACTION BUTTON 2: DOWNLOAD DIRECT PDF -->
                    <button type="submit" name="arms_download_pdf" class="arms-btn-pdf">
                        <span class="dashicons dashicons-pdf"></span> <?php _e( 'Download PDF', 'arms-textdomain' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- SCREEN DISPLAY LAYER -->
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

    <!-- FIELD REBUILD JAVASCRIPT ENGINE -->
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
                    secondaryMenu.add(new Option("All Revenue Streams (OPD & IPD)", "all"));
                    secondaryMenu.add(new Option("Outpatient Care Department (OPD)", "opd"));
                    secondaryMenu.add(new Option("Inpatient Admissions Ledger (IPD)", "ipd"));
                } else if (selection === 'expense') {
                    wrapper.style.display = 'flex'; label.textContent = "Expense Categorization Channels";
                    secondaryMenu.add(new Option("All System Expenditures Combined", "all"));
                    secondaryMenu.add(new Option("Staff Payroll Remittances", "payroll"));
                    secondaryMenu.add(new Option("Utilities & Hospital Bills", "Utility"));
                    secondaryMenu.add(new Option("Medical Equipment & Inventory", "Supplies"));
                } else { wrapper.style.display = 'none'; return; }
                if (initialLoad && currentStickyValue !== '') { secondaryMenu.value = currentStickyValue; }
            }
            primaryMenu.addEventListener('change', function() { rebuildFormDropdownMap(this.value, false); });
            if (primaryMenu.value !== '') { rebuildFormDropdownMap(primaryMenu.value, true); }
        });
    </script>
    <?php
}