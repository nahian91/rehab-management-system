<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Dashboard Tab - Clinical Operations & Financial Control Panel
 * Database Mapping: arms_billing, arms_admissions, arms_ledger, arms_inventory
 */
function arms_dashboard_tab() {
    global $wpdb;

    // Core Database Table Registries
    $table_billing    = $wpdb->prefix . 'arms_billing';
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_ledger     = $wpdb->prefix . 'arms_ledger';
    $table_inventory  = $wpdb->prefix . 'arms_inventory';

    // Time Frames & Ranges
    $today_start = current_time( 'Y-m-d 00:00:00' );
    $today_end   = current_time( 'Y-m-d 23:59:59' );
    $month_start = current_time( 'Y-m-01 00:00:00' );
    $month_end   = current_time( 'Y-m-t 23:59:59' );

    /* =========================================================================
       1. PATIENTS COUNT ENGINE (OPD + IPD)
       ========================================================================= */
    $total_patients_today = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT patient_id) FROM $table_billing WHERE created_at BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );

    /* =========================================================================
       2. ACTIVE ADMISSIONS & BED OCCUPANCY
       ========================================================================= */
    $active_admissions_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_admissions WHERE status = %s",
        'admitted'
    ) );

    $occupancy_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT accommodation_type, COUNT(*) as booked_count FROM $table_admissions WHERE status = %s GROUP BY accommodation_type",
        'admitted'
    ) );

    /* =========================================================================
       3. APPOINTMENTS MONITORING
       ========================================================================= */
    $today_appointments_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_billing WHERE billing_type = 'opd' AND created_at BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );

    /* =========================================================================
       4. FINANCIAL METRICS (INCOME VS EXPENSE, PROFIT/LOSS, PENDING)
       ========================================================================= */
    // Today's Ledger Records
    $today_income = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM $table_ledger WHERE transaction_type = 'income' AND transaction_date BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );
    $today_expense = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM $table_ledger WHERE transaction_type = 'expense' AND transaction_date BETWEEN %s AND %s",
        $today_start,
        $today_end
    ) );

    // Month's Ledger Records
    $month_income = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM $table_ledger WHERE transaction_type = 'income' AND transaction_date BETWEEN %s AND %s",
        $month_start,
        $month_end
    ) );
    $month_expense = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM $table_ledger WHERE transaction_type = 'expense' AND transaction_date BETWEEN %s AND %s",
        $month_start,
        $month_end
    ) );

    // Net Profit/Loss calculations
    $net_profit_loss = $month_income - $month_expense;

    // Unpaid/Pending clinical accounts receivables tracking balance
    $pending_bills_amount = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(total_price) FROM $table_billing WHERE payment_status = %s",
        'unpaid'
    ) );

    /* =========================================================================
       5. DYNAMIC INVENTORY STOCK MONITORING ENGINE
       ========================================================================= */
    $low_stock_alerts_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_inventory WHERE CAST(available_stock AS SIGNED) <= CAST(min_required_stock AS SIGNED)"
    );

    // Admin redirection routing anchors
    $patient_tab_url = admin_url( 'admin.php?page=rehab_management_system&tab=patients&sub=add' );
    $billing_tab_url = admin_url( 'admin.php?page=rehab_management_system&tab=finance' );
    ?>

    <meta http-equiv="refresh" content="30">

    <style>
        :root {
            --arms-bg: #f8fafc;
            --arms-card: #ffffff;
            --arms-accent: #4f46e5;
            --arms-text: #0f172a;
            --arms-muted: #64748b;
            --arms-border: #e2e8f0;
            --arms-success: #10b981;
            --arms-warning: #f59e0b;
            --arms-danger: #ef4444;
            --arms-info: #06b6d4;
        }

        .arms-dashboard-wrapper {
            padding: 24px;
            background: var(--arms-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--arms-text);
            max-width: 1300px;
            margin: 20px auto;
            box-sizing: border-box;
        }
        .arms-dashboard-wrapper * { box-sizing: border-box; }

        /* Header Layout Top Bar */
        .arms-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--arms-border);
            padding-bottom: 20px;
        }
        .arms-header-block h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: var(--arms-text);
        }
        .arms-header-block p {
            margin: 0;
            color: var(--arms-muted);
            font-size: 13px;
            }
        .arms-live-timer-container {
            display: flex;
            align-items: center;
            background: #ffffff;
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid var(--arms-border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            gap: 12px;
            font-size: 13px;
        }
        .arms-ticker-digits {
            padding-left: 12px;
            border-left: 2px solid #e2e8f0;
            color: var(--arms-accent);
            font-weight: 700;
            font-family: monospace;
        }

        /* Quick Actions Grid Array */
        .arms-quick-actions-bar {
            background: #ffffff;
            border: 1px solid var(--arms-border);
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01);
        }
        .arms-action-buttons-group {
            display: flex;
            gap: 12px;
        }
        .arms-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #0f172a;
            color: #ffffff !important;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .arms-btn-action:hover {
            background: var(--arms-accent);
            transform: translateY(-1px);
        }
        .arms-btn-action.secondary {
            background: #f1f5f9;
            color: #0f172a !important;
            border: 1px solid var(--arms-border);
        }
        .arms-btn-action.secondary:hover {
            background: #e2e8f0;
        }

        /* Performance Stat Box Displays */
        .arms-summary-grid-matrix {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .arms-stat-card {
            background: var(--arms-card);
            border: 1px solid var(--arms-border);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            position: relative;
        }
        .arms-stat-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--arms-muted);
            letter-spacing: 0.05em;
        }
        .arms-stat-counter {
            font-size: 26px;
            font-weight: 700;
            color: var(--arms-text);
            margin-top: 6px;
            line-height: 1.2;
        }
        .arms-card-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--arms-border);
        }
        .arms-card-indicator.active-green { background: var(--arms-success); }
        .arms-card-indicator.active-orange { background: var(--arms-warning); }
        .arms-card-indicator.active-blue { background: var(--arms-info); }
        .arms-card-indicator.active-indigo { background: var(--arms-accent); }

        /* Split Operational Columns Panels */
        .arms-split-dashboard-deck {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .arms-split-dashboard-deck { grid-template-columns: 1fr; }
        }
        .arms-dashboard-panel-box {
            background: var(--arms-card);
            border: 1px solid var(--arms-border);
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .arms-panel-title {
            margin: 0 0 16px 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--arms-text);
            border-bottom: 1px solid var(--arms-border);
            padding-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Data & Ledger Rows */
        .arms-ledger-comparison-table {
            width: 100%;
            border-collapse: collapse;
        }
        .arms-ledger-comparison-table th {
            text-align: left;
            padding: 10px 12px;
            background: #f8fafc;
            color: var(--arms-muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .arms-ledger-comparison-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .arms-ledger-comparison-table tr:last-child td {
            border-bottom: none;
        }
        
        .arms-amount-pill {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            display: inline-block;
        }
        .arms-amount-pill.credit { background: #dcfce7; color: #15803d; }
        .arms-amount-pill.debit { background: #fee2e2; color: #b91c1c; }

        .arms-occupancy-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .arms-occupancy-row:last-child {
            border-bottom: none;
        }
        .arms-occupancy-tag {
            background: #f1f5f9;
            color: #334155;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
    </style>

    <div class="arms-dashboard-wrapper">
        
        <div class="arms-header-block">
    <div style="display: flex; align-items: center; gap: 15px;">
        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo.png' ); ?>" 
             alt="System Logo" style="height: 60px; width: auto;">
        
        <div>
            <h1 style="margin: 0;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
            <p style="margin: 0; color: var(--arms-muted); font-size: 13px;">
                <?php echo esc_html( get_bloginfo( 'description' ) ); ?>
            </p>
        </div>
    </div>
    
    <div class="arms-live-timer-container">
        <div><span class="dashicons dashicons-calendar-alt" style="font-size:16px; vertical-align:middle;"></span> <?php echo date( 'l, jS F Y' ); ?></div>
        <div class="arms-ticker-digits">
            <span class="dashicons dashicons-clock" style="font-size:14px; vertical-align:middle; margin-right:2px;"></span>
            <span id="armsLiveTickerClock">00:00:00</span>
        </div>
    </div>
</div>

        <div class="arms-quick-actions-bar">
            <div style="display:flex; flex-direction:column;">
                <span style="font-weight: 600; font-size: 14px;">Quick Actions Workflow Matrix</span>
                <span style="color: var(--arms-muted); font-size: 11px;">Bypass routing steps to perform instant data logging configuration routines.</span>
            </div>
            <div class="arms-action-buttons-group">
                <a href="<?php echo esc_url( $patient_tab_url ); ?>" class="arms-btn-action">
                    <span class="dashicons dashicons-plus-alt"></span> Add New Patient
                </a>
                <a href="<?php echo esc_url( $billing_tab_url ); ?>" class="arms-btn-action secondary">
                    <span class="dashicons dashicons-cart"></span> Create Invoice Bill
                </a>
            </div>
        </div>

        <div class="arms-summary-grid-matrix">
            
            <div class="arms-stat-card">
                <div class="arms-stat-label">Total Patients (Today)</div>
                <div class="arms-stat-counter"><?php echo $total_patients_today; ?></div>
                <span class="arms-card-indicator active-blue"></span>
            </div>

            <div class="arms-stat-card">
                <div class="arms-stat-label">Active Admissions</div>
                <div class="arms-stat-counter"><?php echo $active_admissions_count; ?></div>
                <span class="arms-card-indicator active-green"></span>
            </div>

            <div class="arms-stat-card">
                <div class="arms-stat-label">Today Appointments</div>
                <div class="arms-stat-counter"><?php echo $today_appointments_count; ?></div>
                <span class="arms-card-indicator active-indigo"></span>
            </div>

            <div class="arms-stat-card">
                <div class="arms-stat-label">Pending Bills</div>
                <div class="arms-stat-counter" style="color: var(--arms-danger);">৳<?php echo number_format( $pending_bills_amount, 2 ); ?></div>
                <span class="arms-card-indicator active-orange"></span>
            </div>
        </div>

        <div class="arms-split-dashboard-deck">
            
            <div class="arms-dashboard-panel-box">
                <h3 class="arms-panel-title">
                    <span>Financial Statement Ledgers</span>
                    <span style="font-size: 11px; font-weight: normal; color: var(--arms-muted);">Reporting Cycle: Net Calculations</span>
                </h3>
                
                <table class="arms-ledger-comparison-table">
                    <thead>
                        <tr>
                            <th>Timeline Interval</th>
                            <th>Total Income Collections</th>
                            <th>Total Operational Expenses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><b>Today Performance</b></td>
                            <td><span class="arms-amount-pill credit">৳<?php echo number_format( $today_income, 2 ); ?></span></td>
                            <td><span class="arms-amount-pill debit">৳<?php echo number_format( $today_expense, 2 ); ?></span></td>
                        </tr>
                        <tr>
                            <td><b>Current Month Stack</b></td>
                            <td><span class="arms-amount-pill credit">৳<?php echo number_format( $month_income, 2 ); ?></span></td>
                            <td><span class="arms-amount-pill debit">৳<?php echo number_format( $month_expense, 2 ); ?></span></td>
                        </tr>
                        <tr>
                            <td><b>Statement Margin (Profit/Loss)</b></td>
                            <td colspan="2" style="text-align: right; padding-top: 20px;">
                                <span class="arms-amount-pill <?php echo ( $net_profit_loss >= 0 ) ? 'credit' : 'debit'; ?>" style="font-size:13px; padding:6px 12px;">
                                    <?php echo ( $net_profit_loss >= 0 ) ? 'Net Profit: ৳' : 'Net Loss: ৳'; ?>
                                    <?php echo number_format( abs( $net_profit_loss ), 2 ); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <div class="arms-dashboard-panel-box" style="flex: 1;">
                    <h4 class="arms-panel-title" style="border-bottom:none; margin-bottom:10px; padding-bottom:0;">Bed/Cabin Occupancy</h4>
                    <?php if ( ! empty( $occupancy_rows ) ) : 
                        foreach ( $occupancy_rows as $row ) : ?>
                        <div class="arms-occupancy-row">
                            <span style="font-weight: 500; color:#334155;"><?php echo esc_html( ucwords( $row->accommodation_type ) ); ?></span>
                            <span class="arms-occupancy-tag"><?php echo (int) $row->booked_count; ?> Occupied</span>
                        </div>
                    <?php endforeach; else : ?>
                        <p style="color: var(--arms-muted); font-size: 13px; margin: 15px 0 0 0;">All clinical wards and cabins are vacant.</p>
                    <?php endif; ?>
                </div>

                <div class="arms-dashboard-panel-box" style="border-left: 4px solid <?php echo ($low_stock_alerts_count > 0) ? 'var(--arms-danger)' : 'var(--arms-success)'; ?>; padding: 16px 20px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px; color: <?php echo ($low_stock_alerts_count > 0) ? 'var(--arms-danger)' : 'var(--arms-success)'; ?>; margin-top:2px;"></span>
                        <div>
                            <h5 style="margin:0; font-size:13px; font-weight:600; color:var(--arms-text);">Low Stock Alerts</h5>
                            <p style="margin:4px 0 0 0; font-size:12px; color:var(--arms-muted); line-height:1.4;">
                                <?php echo ( $low_stock_alerts_count > 0 ) ? $low_stock_alerts_count . ' Critical items require inventory replenishment.' : 'All logistics supply lines are within safe baseline thresholds.'; ?>
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function armsDashboardClockEngine() {
            var timeObject = new Date();
            var processString = timeObject.getHours().toString().padStart(2, '0') + ':' + 
                                timeObject.getMinutes().toString().padStart(2, '0') + ':' + 
                                timeObject.getSeconds().toString().padStart(2, '0');
            var tickerContainer = document.getElementById('armsLiveTickerClock');
            if (tickerContainer) {
                tickerContainer.textContent = processString;
            }
        }
        setInterval(armsDashboardClockEngine, 1000);
        armsDashboardClockEngine();
    });
    </script>
    <?php
}