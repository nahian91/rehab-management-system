<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Reports Configuration Renderer Engine
 * Form actions processed via standard POST page-reloads (No AJAX)
 */
function arms_reports_tab() {
    global $wpdb;

    // Define table targets matching database schema patterns
    $table_payroll = $wpdb->prefix . 'arms_payroll';
    $table_staff   = $wpdb->prefix . 'arms_staff';

    // Fetch dynamic master lists for mapping Dropdown 2 categories
    $staff_entries    = $wpdb->get_results( "SELECT id, first_name, last_name, role_category FROM $table_staff ORDER BY first_name ASC" );
    $role_categories  = $wpdb->get_col( "SELECT DISTINCT role_category FROM $table_staff WHERE role_category IS NOT NULL AND role_category != ''" );

    // Maintain sticky data variables across page reload forms
    $report_type  = isset( $_POST['report_type'] ) ? sanitize_key( $_POST['report_type'] ) : '';
    $sub_criteria = isset( $_POST['sub_criteria'] ) ? sanitize_text_field( $_POST['sub_criteria'] ) : 'all';
    $date_from    = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : date('Y-m-01'); // Defaults to first day of month
    $date_to      = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : date('Y-m-d');

    // ==========================================
    // BACKEND ENGINE: PDF REPORT GENERATION ROUTE
    // ==========================================
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['arms_generate_pdf_report'] ) ) {
        check_admin_referer( 'arms_reporting_engine_nonce', 'security' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized access mapping runtime layers.', 'arms-textdomain' ) );
        }

        // -------------------------------------------------------------
        // NOTE FOR LATER IMPLEMENTATION: PDF EXPORT SYSTEM HOOK
        // You can easily plug in TCPDF, FPDF, or Dompdf inside this block.
        // For now, it compiles standard ledger datasets onto your screen cleanly.
        // -------------------------------------------------------------
        
        // Build raw database analytics querying parameters based on selection criteria rules
        $query_conditions = [];
        $query_arguments  = [];

        // Filter dates rules
        $query_conditions[] = "p.payment_date >= %s";
        $query_arguments[]  = $date_from . ' 00:00:00';
        $query_conditions[] = "p.payment_date <= %s";
        $query_arguments[]  = $date_to . ' 23:59:59';

        if ( $report_type === 'income' && $sub_criteria !== 'all' ) {
            $query_conditions[] = "s.role_category = %s";
            $query_arguments[]  = $sub_criteria;
        } elseif ( $report_type === 'expense' && $sub_criteria !== 'all' ) {
            $query_conditions[] = "p.staff_id = %d";
            $query_arguments[]  = intval( $sub_criteria );
        }

        $where_clause = implode( ' AND ', $query_conditions );
        
        $report_results = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.*, s.first_name, s.last_name, s.role_category 
            FROM $table_payroll p
            INNER JOIN $table_staff s ON p.staff_id = s.id
            WHERE $where_clause
            ORDER BY p.payment_date DESC
        ", $query_arguments ) );
    }
    ?>

    <style>
        .arms-report-wrapper { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .arms-report-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .arms-report-header { margin-top: 0; border-bottom: 1px solid #f0f0f1; padding-bottom: 12px; color: #2271b1; font-weight: 500; font-size: 18px; }
        .arms-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; align-items: flex-end; }
        .arms-filter-group { display: flex; flex-direction: column; }
        .arms-filter-group label { font-weight: 600; margin-bottom: 6px; color: #1d2327; font-size: 13px; }
        .arms-report-select, .arms-report-input { width: 100%; height: 36px; padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; font-size: 14px; color: #2c3338; }
        .arms-report-select:focus, .arms-report-input:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: 2px solid transparent; }
        .arms-pdf-btn { height: 36px; background: #2271b1; color: #fff; border: 1px solid #0a4b78; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; box-shadow: 0 1px 0 #0a4b78; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .arms-pdf-btn:hover { background: #135e96; border-color: #0a4b78; color: #fff; }
        
        .arms-table-ledger { width: 100%; border-collapse: collapse; margin-top: 24px; background: #fff; border: 1px solid #c3c4c7; }
        .arms-table-ledger th, .arms-table-ledger td { padding: 12px; text-align: left; border-bottom: 1px solid #c3c4c7; font-size: 13px; }
        .arms-table-ledger th { background: #f6f7f7; font-weight: 600; color: #1d2327; }
        .arms-badge-summary { font-size: 14px; font-weight: bold; background: #f0f6fa; padding: 12px; border-left: 4px solid #2271b1; margin-top: 20px; display: flex; justify-content: space-between; }
    </style>

    <div class="arms-report-wrapper">
        <div class="arms-report-card">
            <h3 class="arms-report-header"><?php echo esc_html__( 'Financial Reports Configuration Desk', 'arms-textdomain' ); ?></h3>
            
            <form method="POST" action="" id="armsReportEngineForm">
                <?php wp_nonce_field( 'arms_reporting_engine_nonce', 'security' ); ?>
                <input type="hidden" name="arms_generate_pdf_report" value="1">

                <div class="arms-filter-grid">
                    <div class="arms-filter-group">
                        <label><?php _e( 'Select Report Core Metric', 'arms-textdomain' ); ?></label>
                        <select name="report_type" id="arms_report_type" class="arms-report-select" required>
                            <option value=""><?php _e( '-- Choose Target Metric --', 'arms-textdomain' ); ?></option>
                            <option value="income" <?php selected( $report_type, 'income' ); ?>><?php _e( 'Income Ledger', 'arms-textdomain' ); ?></option>
                            <option value="expense" <?php selected( $report_type, 'expense' ); ?>><?php _e( 'Expense Statements', 'arms-textdomain' ); ?></option>
                            <option value="profit" <?php selected( $report_type, 'profit' ); ?>><?php _e( 'Net Profit Matrix', 'arms-textdomain' ); ?></option>
                        </select>
                    </div>

                    <div class="arms-filter-group" id="container_sub_criteria" style="display: none;">
                        <label id="label_sub_criteria"><?php _e( 'Context Category Filter', 'arms-textdomain' ); ?></label>
                        <select name="sub_criteria" id="arms_sub_criteria" class="arms-report-select">
                            </select>
                    </div>

                    <div class="arms-filter-group">
                        <label><?php _e( 'Statement Timeline (From)', 'arms-textdomain' ); ?></label>
                        <input type="date" name="date_from" class="arms-report-input" value="<?php echo esc_attr( $date_from ); ?>" required>
                    </div>

                    <div class="arms-filter-group">
                        <label><?php _e( 'Statement Timeline (To)', 'arms-textdomain' ); ?></label>
                        <input type="date" name="date_to" class="arms-report-input" value="<?php echo esc_attr( $date_to ); ?>" required>
                    </div>

                    <button type="submit" class="arms-pdf-btn">
                        <span class="dashicons dashicons-pdf"></span> <?php _e( 'Compile Statement Logs', 'arms-textdomain' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if ( isset( $report_results ) ) : ?>
            <div class="arms-report-card" style="margin-top: 20px;">
                <h3 class="arms-report-header" style="color: #1d2327; border-color: #c3c4c7;">
                    <?php echo esc_html( strtoupper( $report_type ) ) . ' ' . esc_html__( 'Statements Report Analysis View', 'arms-textdomain' ); ?>
                    <span style="float: right; font-size: 12px; color: #64748b;"><?php echo esc_html( $date_from ) . ' to ' . esc_html( $date_to ); ?></span>
                </h3>

                <table class="arms-table-ledger">
                    <thead>
                        <tr>
                            <th><?php _e( 'Payment Date', 'arms-textdomain' ); ?></th>
                            <th><?php _e( 'Reference Staff', 'arms-textdomain' ); ?></th>
                            <th><?php _e( 'Role Department', 'arms-textdomain' ); ?></th>
                            <th><?php _e( 'Base Salary Structure', 'arms-textdomain' ); ?></th>
                            <th><?php _e( 'Allowances (+)', 'arms-textdomain' ); ?></th>
                            <th><?php _e( 'Deductions (-)', 'arms-textdomain' ); ?></th>
                            <th><?php _e( 'Computed Operational Balance', 'arms-textdomain' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_base       = 0;
                        $total_allowance  = 0;
                        $total_deductions = 0;
                        $total_net        = 0;

                        if ( empty( $report_results ) ) : 
                        ?>
                            <tr><td colspan="7" style="text-align:center; color:#64748b; font-style:italic;"><?php _e( 'No historic records verified inside specified context configurations.', 'arms-textdomain' ); ?></td></tr>
                        <?php 
                        else : 
                            foreach ( $report_results as $statement ) : 
                                $allowance  = $statement->bonus + $statement->incentives;
                                $deduction  = $statement->attendance_deduction + $statement->tax_deduction;
                                
                                $total_base       += $statement->base_salary;
                                $total_allowance  += $allowance;
                                $total_deductions += $deduction;
                                $total_net        += $statement->net_payable;
                        ?>
                            <tr>
                                <td><?php echo date( 'Y-m-d', strtotime( $statement->payment_date ) ); ?></td>
                                <td><strong><?php echo esc_html( $statement->first_name . ' ' . $statement->last_name ); ?></strong></td>
                                <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $statement->role_category ) ) ); ?></td>
                                <td><?php echo number_format( $statement->base_salary, 2 ); ?> BDT</td>
                                <td style="color:#16a34a;">+ <?php echo number_format( $allowance, 2 ); ?> BDT</td>
                                <td style="color:#dc2626;">- <?php echo number_format( $deduction, 2 ); ?> BDT</td>
                                <td><strong><?php echo number_format( $statement->net_payable, 2 ); ?> BDT</strong></td>
                            </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </tbody>
                </table>

                <div class="arms-badge-summary">
                    <span><?php _e( 'Cumulated Summary Total (Net Balance Flow Calculation Scheme):', 'arms-textdomain' ); ?></span>
                    <span style="color: #2271b1;"><?php echo number_format( $total_net, 2 ); ?> BDT</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var primaryMetric = document.getElementById('arms_report_type');
            var subCriteriaContainer = document.getElementById('container_sub_criteria');
            var subCriteriaLabel = document.getElementById('label_sub_criteria');
            var subCriteriaDropdown = document.getElementById('arms_sub_criteria');

            // Dynamic mapping objects built from server configurations
            var incomeCategories = <?php echo wp_json_encode( $role_categories ); ?>;
            var staffProfiles = <?php echo wp_json_encode( array_map( function($s) {
                return [ 'id' => $s->id, 'name' => $s->first_name . ' ' . $s->last_name ];
            }, $staff_entries ) ); ?>;

            // Retain values on tracking reloads
            var oldSubValue = "<?php echo esc_js( $sub_criteria ); ?>";

            function processContextMapping(currentSelection, dynamicReload) {
                // Clear active listings options
                subCriteriaDropdown.innerHTML = '';

                if (currentSelection === 'income') {
                    subCriteriaContainer.style.display = 'flex';
                    subCriteriaLabel.textContent = "<?php _e( 'Filter by Income Source (Role Category)', 'arms-textdomain' ); ?>";
                    
                    var allOpt = document.createElement('option'); allOpt.value = 'all'; allOpt.textContent = "<?php _e( 'All Income Categories', 'arms-textdomain' ); ?>";
                    subCriteriaDropdown.appendChild(allOpt);

                    incomeCategories.forEach(function(cat) {
                        var opt = document.createElement('option');
                        opt.value = cat;
                        opt.textContent = cat.charAt(0).toUpperCase() + cat.slice(1).replace('_', ' ');
                        if(dynamicReload && cat === oldSubValue) opt.selected = true;
                        subCriteriaDropdown.appendChild(opt);
                    });

                } else if (currentSelection === 'expense') {
                    subCriteriaContainer.style.display = 'flex';
                    subCriteriaLabel.textContent = "<?php _e( 'Filter by Expense Payee (Employee Staff Profile)', 'arms-textdomain' ); ?>";

                    var allOpt = document.createElement('option'); allOpt.value = 'all'; allOpt.textContent = "<?php _e( 'All Staff Expense Records', 'arms-textdomain' ); ?>";
                    subCriteriaDropdown.appendChild(allOpt);

                    staffProfiles.forEach(function(staff) {
                        var opt = document.createElement('option');
                        opt.value = staff.id;
                        opt.textContent = staff.name;
                        if(dynamicReload && staff.id.toString() === oldSubValue) opt.selected = true;
                        subCriteriaDropdown.appendChild(opt);
                    });

                } else {
                    // Hide dynamic filter if set to 'profit' metric calculations
                    subCriteriaContainer.style.display = 'none';
                    subCriteriaDropdown.removeAttribute('required');
                }
            }

            // Hook Event execution monitors
            primaryMetric.addEventListener('change', function() {
                processContextMapping(this.value, false);
            });

            // Maintain visual configurations state tracking directly upon page loading lifecycle
            if(primaryMetric.value !== '') {
                processContextMapping(primaryMetric.value, true);
            }
        });
    </script>
    <?php
}