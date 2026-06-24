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
       2. ACTIVE ADMISSIONS
       ========================================================================= */
    $active_admissions_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_admissions WHERE status = %s",
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
    $inventory_tab_url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory' );

    /* =========================================================================
       CUSTOM TIMELINE GREETING CALCULATOR ENGINE
       ========================================================================= */
    $current_hour = (int) current_time( 'H' ); // 24-hour scale format (00 - 23)
    
    if ( $current_hour >= 6 && $current_hour < 12 ) {
        $greeting_prefix = 'Good Morning';
    } elseif ( $current_hour >= 12 && $current_hour < 18 ) {
        $greeting_prefix = 'Good Evening';
    } else {
        $greeting_prefix = 'Good Night';
    }

    $current_wp_user = wp_get_current_user();
    $user_display_name = ! empty( $current_wp_user->display_name ) ? $current_wp_user->display_name : 'User';
    $rendered_greeting = $greeting_prefix . ', ' . $user_display_name;
    ?>

    <meta http-equiv="refresh" content="45">

    <style>
        :root {
            --arms-bg: #f8fafc;
            --arms-card: #ffffff;
            --arms-accent: #4f46e5;
            --arms-accent-hover: #4338ca;
            --arms-text: #0f172a;
            --arms-muted: #64748b;
            --arms-border: #e2e8f0;
            --arms-success: #10b981;
            --arms-success-bg: #e6f4ea;
            --arms-warning: #f59e0b;
            --arms-warning-bg: #fef3c7;
            --arms-danger: #ef4444;
            --arms-danger-bg: #fee2e2;
            --arms-info: #06b6d4;
            --arms-info-bg: #ecfeff;
        }

        .arms-dashboard-wrapper {
            padding: 24px;
            background: var(--arms-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--arms-text);
            max-width: 1440px;
            margin: 20px auto;
            box-sizing: border-box;
        }
        .arms-dashboard-wrapper * { box-sizing: border-box; }

        /* Header Layout Styling */
        .arms-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            border-bottom: 1px solid var(--arms-border);
            padding-bottom: 20px;
        }
        .arms-header-block h1 {
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: var(--arms-text);
            letter-spacing: -0.02em;
        }
        .arms-header-block p {
            margin: 0;
            color: var(--arms-muted);
            font-size: 14px;
        }
        .arms-live-timer-container {
            display: flex;
            align-items: center;
            background: #ffffff;
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid var(--arms-border);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
            gap: 14px;
            font-size: 13px;
            font-weight: 500;
        }
        .arms-ticker-digits {
            padding-left: 14px;
            border-left: 2px solid var(--arms-border);
            color: var(--arms-accent);
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        /* 8-Box Premium Responsive Grid Matrix */
        .arms-summary-grid-matrix {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 1200px) {
            .arms-summary-grid-matrix { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .arms-summary-grid-matrix { grid-template-columns: 1fr; }
        }

        /* Enhanced Metric Cards */
        .arms-stat-card {
            background: var(--arms-card);
            border: 1px solid var(--arms-border);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .arms-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.05), 0 4px 6px -4px rgb(0 0 0 / 0.05);
        }
        .arms-stat-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--arms-muted);
            letter-spacing: 0.07em;
            margin-bottom: 8px;
        }
        .arms-stat-counter {
            font-size: 28px;
            font-weight: 800;
            color: var(--arms-text);
            line-height: 1.1;
            letter-spacing: -0.03em;
        }
        
        /* Interactive Card Subtext & Links */
        .arms-card-footer {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px dashed #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--arms-muted);
        }
        .arms-card-link {
            color: var(--arms-accent);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        .arms-card-link:hover {
            color: var(--arms-accent-hover);
            text-decoration: underline;
        }
.arms-header-logo {
	border: 1px solid #ddd;
	background-color: #ffff;
}
        /* Semantic Dynamic Pill Indicators */
        .arms-status-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }
        .arms-status-badge.info { background: var(--arms-info-bg); color: var(--arms-info); }
        .arms-status-badge.success { background: var(--arms-success-bg); color: var(--arms-success); }
        .arms-status-badge.warning { background: var(--arms-warning-bg); color: var(--arms-warning); }
        .arms-status-badge.danger { background: var(--arms-danger-bg); color: var(--arms-danger); }
        .arms-status-badge.neutral { background: #f1f5f9; color: var(--arms-muted); }

        .arms-card-indicator {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--arms-border);
        }
        .arms-card-indicator.active-green { background: var(--arms-success); box-shadow: 0 0 8px var(--arms-success); }
        .arms-card-indicator.active-orange { background: var(--arms-warning); box-shadow: 0 0 8px var(--arms-warning); }
        .arms-card-indicator.active-blue { background: var(--arms-info); box-shadow: 0 0 8px var(--arms-info); }
        .arms-card-indicator.active-indigo { background: var(--arms-accent); box-shadow: 0 0 8px var(--arms-accent); }
        .arms-card-indicator.active-red { background: var(--arms-danger); box-shadow: 0 0 8px var(--arms-danger); }
    </style>

    <div class="arms-dashboard-wrapper">
        
        <div class="arms-header-block">
            <div style="display: flex; align-items: center; gap: 18px;">
                <div class="arms-header-logo">
                    <img src="<?php echo esc_url( plugins_url( 'img/logo.png', __FILE__ ) ); ?>" 
                         alt="System Logo" style="height: 64px; width: auto; display: block; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.05));">
                </div>
                <div>
                    <h1><?php echo esc_html( $rendered_greeting ); ?></h1>
                    <p>Clinical Intelligence & Integrated Financial Operations Control Panel</p>
                </div>
            </div>

            <div class="arms-live-timer-container">
                <div><span class="dashicons dashicons-calendar-alt" style="font-size:16px; vertical-align:middle; margin-right:4px; color:var(--arms-muted);"></span> <?php echo date( 'l, jS F Y' ); ?></div>
                <div class="arms-ticker-digits">
                    <span class="dashicons dashicons-clock" style="font-size:14px; vertical-align:middle; margin-right:4px;"></span>
                    <span id="armsLiveTickerClock">00:00:00</span>
                </div>
            </div>
        </div>

        <div class="arms-summary-grid-matrix">
            
            <div class="arms-stat-card">
                <span class="arms-card-indicator active-blue"></span>
                <div>
                    <div class="arms-stat-label">Total Patients (Today)</div>
                    <div class="arms-stat-counter"><?php echo $total_patients_today; ?></div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge info">Active Registration</span>
                    <a href="<?php echo esc_url( $patient_tab_url ); ?>" class="arms-card-link">Add Patient &rarr;</a>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator active-green"></span>
                <div>
                    <div class="arms-stat-label">Active Admissions</div>
                    <div class="arms-stat-counter"><?php echo $active_admissions_count; ?></div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge success">In-Patient Care</span>
                    <span style="font-weight:500;">Live Ward Load</span>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator active-indigo"></span>
                <div>
                    <div class="arms-stat-label">Today Appointments</div>
                    <div class="arms-stat-counter"><?php echo $today_appointments_count; ?></div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge neutral">OPD Queue</span>
                    <span style="font-weight:500;">Scheduled</span>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator active-red"></span>
                <div>
                    <div class="arms-stat-label">Pending Bills Balance</div>
                    <div class="arms-stat-counter" style="color: var(--arms-danger);">৳<?php echo number_format( $pending_bills_amount, 2 ); ?></div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge danger">Receivables</span>
                    <a href="<?php echo esc_url( $billing_tab_url ); ?>" class="arms-card-link">Collect &rarr;</a>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator active-green"></span>
                <div>
                    <div class="arms-stat-label">Today Income</div>
                    <div class="arms-stat-counter" style="color: var(--arms-success);">৳<?php echo number_format( $today_income, 2 ); ?></div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge success">Gross Inflow</span>
                    <span style="font-weight:500;">Real-time</span>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator"></span>
                <div>
                    <div class="arms-stat-label">Today Operational Expense</div>
                    <div class="arms-stat-counter" style="color: var(--arms-muted);">৳<?php echo number_format( $today_expense, 2 ); ?></div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge neutral">Outflow Stack</span>
                    <span style="font-weight:500;">Ledger Entry</span>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator <?php echo ( $net_profit_loss >= 0 ) ? 'active-green' : 'active-red'; ?>"></span>
                <div>
                    <div class="arms-stat-label">Net Margin (Current Month)</div>
                    <div class="arms-stat-counter" style="color: <?php echo ( $net_profit_loss >= 0 ) ? 'var(--arms-success)' : 'var(--arms-danger)'; ?>;">
                        ৳<?php echo number_format( $net_profit_loss, 2 ); ?>
                    </div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge <?php echo ( $net_profit_loss >= 0 ) ? 'success' : 'danger'; ?>">
                        <?php echo ( $net_profit_loss >= 0 ) ? 'Net Profit' : 'Net Loss'; ?>
                    </span>
                    <a href="<?php echo esc_url( $billing_tab_url ); ?>" class="arms-card-link">Ledger &rarr;</a>
                </div>
            </div>

            <div class="arms-stat-card">
                <span class="arms-card-indicator <?php echo ( $low_stock_alerts_count > 0 ) ? 'active-orange' : 'active-green'; ?>"></span>
                <div>
                    <div class="arms-stat-label">Low Stock Alerts</div>
                    <div class="arms-stat-counter" style="color: <?php echo ( $low_stock_alerts_count > 0 ) ? 'var(--arms-warning)' : 'var(--arms-text)'; ?>;">
                        <?php echo $low_stock_alerts_count; ?> <span style="font-size: 16px; font-weight: 500; color: var(--arms-muted);">Line Items</span>
                    </div>
                </div>
                <div class="arms-card-footer">
                    <span class="arms-status-badge <?php echo ( $low_stock_alerts_count > 0 ) ? 'warning' : 'success'; ?>">
                        <?php echo ( $low_stock_alerts_count > 0 ) ? 'Replenish Required' : 'Inventory Safe'; ?>
                    </span>
                    <a href="<?php echo esc_url( $inventory_tab_url ); ?>" class="arms-card-link">Logistics &rarr;</a>
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