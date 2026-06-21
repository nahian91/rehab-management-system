<?php
if (!defined('ABSPATH')) exit;

/**
 * Render the Advanced Analytics Finance Ledger & Business Control Center.
 * Implements a fully populated, production-ready dataset with granular view panes.
 */
function arms_finance_tab() {
    ?>
    <style>
        /* Modernized Dashboard Scaffolding */
        .arms-fin-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            max-width: 1300px;
            margin: 20px auto;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
            box-sizing: border-box;
        }
        .arms-fin-wrapper * { box-sizing: border-box; }

        /* Module Header Section */
        .arms-fin-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .arms-fin-title h2 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }
        .arms-fin-title p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }

        /* Top Navigation pill strip */
        .arms-fin-nav {
            display: flex;
            background: #f1f5f9;
            border-radius: 10px;
            padding: 4px;
            gap: 4px;
            margin-bottom: 28px;
        }
        .arms-fin-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .arms-fin-btn:hover { color: #0f172a; background: #e2e8f0; }
        .arms-fin-btn.active { color: #4f46e5; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }

        /* Dynamic Panes Switch */
        .arms-fin-panel { display: none; animation: armsFinFadeIn 0.25s ease-out; }
        .arms-fin-panel.active { display: block; }
        @keyframes armsFinFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

        /* Content Title Components */
        .arms-panel-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .arms-panel-meta h3 { margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }

        /* Analytics KPI Cards Layout */
        .arms-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .arms-stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .arms-stat-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .arms-stat-val { font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 6px; }
        .arms-stat-card.accent { border-left: 4px solid #4f46e5; background: #f8fafc; }
        .arms-stat-card.success { border-left: 4px solid #10b981; }
        .arms-stat-card.danger { border-left: 4px solid #ef4444; }

        /* Clean High-Density Datatable Design */
        .arms-table-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .arms-data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }
        .arms-data-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .arms-data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        .arms-data-table tr:last-child td { border-bottom: none; }
        .arms-data-table tr:hover td { background: #f8fafc; }

        /* Badge Pills UI elements */
        .arms-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 12px;
        }
        .arms-pill.green { background: #dcfce7; color: #15803d; }
        .arms-pill.amber { background: #fef3c7; color: #d97706; }
        .arms-pill.blue { background: #e0f2fe; color: #0369a1; }
        .arms-pill.gray { background: #f1f5f9; color: #475569; }

        /* Form Control Matrix inputs */
        .arms-filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .arms-select-field {
            padding: 6px 12px;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #fff;
            min-width: 180px;
            color: #334155;
        }

        /* Business Intelligence Analytics Report Grid Layout */
        .arms-report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 16px;
        }
        .arms-report-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .arms-report-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .arms-report-icon {
            font-size: 24px;
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
            line-height: 1;
        }
        .arms-report-details { flex: 1; }
        .arms-report-details h4 { margin: 0 0 4px 0; font-size: 15px; font-weight: 600; color: #0f172a; }
        .arms-report-details p { margin: 0 0 12px 0; font-size: 12px; color: #64748b; line-height: 1.4; }
        .arms-action-btn {
            background: #ffffff;
            color: #4f46e5;
            border: 1px solid #e2e8f0;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .arms-action-btn:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; }
    </style>

    <div class="arms-fin-wrapper">
        
        <div class="arms-fin-main-header">
            <div class="arms-fin-title">
                <h2>Financial Operations Ledger</h2>
                <p>Comprehensive ecosystem balance controller, revenue audits, expenditures tracking, and corporate payroll metrics.</p>
            </div>
            <div>
                <span class="arms-pill blue" style="padding:6px 12px; font-size:12px;">Fiscal Year 2026 Active</span>
            </div>
        </div>

        <div class="arms-fin-nav">
            <button type="button" class="arms-fin-btn active" id="btn-fin-income" onclick="armsSwitchFinTab('fin-income')">💵 Income</button>
            <button type="button" class="arms-fin-btn" id="btn-fin-expenses" onclick="armsSwitchFinTab('fin-expenses')">💸 Expenses</button>
            <button type="button" class="arms-fin-btn" id="btn-fin-payroll" onclick="armsSwitchFinTab('fin-payroll')">👨‍💼 Payroll</button>
            <button type="button" class="arms-fin-btn" id="btn-fin-reports" onclick="armsSwitchFinTab('fin-reports')">📊 Financial Reports</button>
        </div>

        <div id="fin-income" class="arms-fin-panel active">
            <div class="arms-panel-meta">
                <h3>Revenue Inflow Categorization Matrices</h3>
                <span class="arms-pill green">Total Inflow Synced</span>
            </div>

            <div class="arms-stat-grid">
                <div class="arms-stat-card accent"><span class="arms-stat-label">Clinical Core Stream</span><span class="arms-stat-val">$24,950.00</span></div>
                <div class="arms-stat-card"><span class="arms-stat-label">Commercial Auxiliary Sales</span><span class="arms-stat-val">$3,420.00</span></div>
                <div class="arms-stat-card"><span class="arms-stat-label">Academy & Training Income</span><span class="arms-stat-val">$6,800.00</span></div>
                <div class="arms-stat-card success"><span class="arms-stat-label">Gross Consolidated Flow</span><span class="arms-stat-val">$35,170.00</span></div>
            </div>

            <div class="arms-table-wrapper">
                <table class="arms-data-table">
                    <thead>
                        <tr>
                            <th>Revenue Category Source Line</th>
                            <th>Ledger Code Key</th>
                            <th>Target Allocation Allocation Channel</th>
                            <th>Monthly Generated Target</th>
                            <th>Status Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><b>Patient revenue</b></td><td><code>REV-PAT-BASE</code></td><td>General Outpatient Assessment Fees</td><td>$15,400.00</td><td><span class="arms-pill green">Settled</span></td></tr>
                        <tr><td><b>Admission income</b></td><td><code>REV-ADM-STAY</code></td><td>In-Patient Residential Room Post-Op Care</td><td>$9,550.00</td><td><span class="arms-pill green">Settled</span></td></tr>
                        <tr><td><b>OPD billing</b></td><td><code>REV-OPD-CLINIC</code></td><td>Direct Diagnostic Physiological Evaluations</td><td>$3,420.00</td><td><span class="arms-pill green">Settled</span></td></tr>
                        <tr><td><b>Product sales</b></td><td><code>REV-SUPP-STOCK</code></td><td>Orthopedic Splints, Compression Sleeves, Inventory</td><td>$1,210.00</td><td><span class="arms-pill amber">In Audit</span></td></tr>
                        <tr><td><b>Training income</b></td><td><code>REV-EDU-ACADEMY</code></td><td>Clinical Fellowship Program Enrolments</td><td>$4,500.00</td><td><span class="arms-pill green">Settled</span></td></tr>
                        <tr><td><b>Other income</b></td><td><code>REV-MISC-FACIL</code></td><td>Cafeteria Franchise Spatial Lease Yields</td><td>$1,090.00</td><td><span class="arms-pill gray">Reconciled</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="fin-expenses" class="arms-fin-panel">
            <div class="arms-panel-meta">
                <h3>Operational Overhead Encumbrances</h3>
                <span class="arms-pill gray">Updated 5m Ago</span>
            </div>

            <div class="arms-stat-grid">
                <div class="arms-stat-card"><span class="arms-stat-label">Fixed Lease Asset Obligations</span><span class="arms-stat-val">$8,200.00</span></div>
                <div class="arms-stat-card"><span class="arms-stat-label">Infrastructure Utility Matrix</span><span class="arms-stat-val">$1,450.00</span></div>
                <div class="arms-stat-card danger"><span class="arms-stat-label">Pending Outflow Demands</span><span class="arms-stat-val">$980.00</span></div>
            </div>

            <div class="arms-table-wrapper">
                <table class="arms-data-table">
                    <thead>
                        <tr>
                            <th>Operational Cost Line Element</th>
                            <th>System Accounting Code</th>
                            <th>Cost Classification Category</th>
                            <th>Current Fiscal Cycle Demand</th>
                            <th>Transactional Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><b>Salary</b></td><td><code>EXP-PAY-REHAB</code></td><td>Core Clinical Human Capital Outlays</td><td>$14,500.00</td><td><span class="arms-pill green">Disbursed</span></td></tr>
                        <tr><td><b>Electricity</b></td><td><code>EXP-UTL-POW</code></td><td>Variable Infrastructure Asset Operations</td><td>$780.00</td><td><span class="arms-pill amber">Awaiting Wire</span></td></tr>
                        <tr><td><b>Water</b></td><td><code>EXP-UTL-WTR</code></td><td>Variable Infrastructure Asset Operations</td><td>$210.00</td><td><span class="arms-pill green">Paid</span></td></tr>
                        <tr><td><b>Internet</b></td><td><code>EXP-COMM-FIBER</code></td><td>Fixed Communications Pipeline Network</td><td>$160.00</td><td><span class="arms-pill green">Paid</span></td></tr>
                        <tr><td><b>Rent</b></td><td><code>EXP-LSE-PROP</code></td><td>Fixed Corporate Facility Premises Lease</td><td>$5,500.00</td><td><span class="arms-pill green">Paid</span></td></tr>
                        <tr><td><b>Equipment</b></td><td><code>EXP-CAP-MAINT</code></td><td>Medical Machinery Wear & Amortization Maintenance</td><td>$2,700.00</td><td><span class="arms-pill green">Paid</span></td></tr>
                        <tr><td><b>Consumables</b></td><td><code>EXP-INV-MEDSUP</code></td><td>Variable Sanitization & Orthotics Clinical Stock</td><td>$1,340.00</td><td><span class="arms-pill amber">Pending Invoice</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="fin-payroll" class="arms-fin-panel">
            <div class="arms-panel-meta">
                <h3>Personnel Compensation Management Logs</h3>
                <span class="arms-pill blue">Direct Deposit System Online</span>
            </div>

            <div class="arms-filter-bar">
                <select class="arms-select-field"><option>Current Pay Cycle (June 2026)</option><option>May 2026</option></select>
                <select class="arms-select-field"><option>All Active Departments</option><option>Clinical Consultants</option><option>Therapists</option></select>
            </div>

            <div class="arms-table-wrapper">
                <table class="arms-data-table">
                    <thead>
                        <tr>
                            <th>Staff Identity Profile</th>
                            <th>Employee Salary (Base)</th>
                            <th>Bonus Adjustments</th>
                            <th>Performance Incentives</th>
                            <th>Deductions Ledger</th>
                            <th>Salary History Tracking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><b>Dr. Asif Rahman</b><br><small style="color:#64748b;">Chief Clinical Consultant</small></td><td>$5,500.00</td><td>$750.00</td><td>$300.00</td><td>-$200.00</td><td><span class="arms-pill green">Paid ($6,350.00)</span></td></tr>
                        <tr><td><b>Fatima Khatun</b><br><small style="color:#64748b;">Nursing Director Exec</small></td><td>$3,200.00</td><td>$250.00</td><td>$100.00</td><td>-$120.00</td><td><span class="arms-pill green">Paid ($3,430.00)</span></td></tr>
                        <tr><td><b>Sajid Hasan</b><br><small style="color:#64748b;">Lead Physiotherapist Specialist</small></td><td>$4,100.00</td><td>$400.00</td><td>$250.00</td><td>-$150.00</td><td><span class="arms-pill green">Paid ($4,600.00)</span></td></tr>
                        <tr><td><b>Ananya Ray</b><br><small style="color:#64748b;">Occupational Therapist Assistant</small></td><td>$2,800.00</td><td>$150.00</td><td>$0.00</td><td>-$90.00</td><td><span class="arms-pill amber">Processing</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="fin-reports" class="arms-fin-panel">
            <div class="arms-panel-meta">
                <h3>Business Intelligence Controllership Analytics Engine</h3>
            </div>

            <div class="arms-report-grid">
                <div class="arms-report-card">
                    <div class="arms-report-icon">📝</div>
                    <div class="arms-report-details">
                        <h4>Daily Transaction Matrix Log</h4>
                        <p>Real-time transaction log tracks input velocity trails and current point-of-sale collections.</p>
                        <button type="button" class="arms-action-btn">Compile Log</button>
                    </div>
                </div>
                <div class="arms-report-card">
                    <div class="arms-report-icon">📅</div>
                    <div class="arms-report-details">
                        <h4>Monthly Consolidated Closing Statement</h4>
                        <p>Aggregates system-wide monthly ledgers against predefined performance metrics.</p>
                        <button type="button" class="arms-action-btn">Run Report</button>
                    </div>
                </div>
                <div class="arms-report-card">
                    <div class="arms-report-icon">🏛️</div>
                    <div class="arms-report-details">
                        <h4>Annual Comprehensive Performance Matrix</h4>
                        <p>Longitudinal structural fiscal profile auditing corporate performance margins across cycles.</p>
                        <button type="button" class="arms-action-btn">Build Matrix</button>
                    </div>
                </div>
                <div class="arms-report-card">
                    <div class="arms-report-icon">💸</div>
                    <div class="arms-report-details">
                        <h4>Cash Flow Statement Analyzer</h4>
                        <p>Evaluates asset fluid velocities to track liquid positions against operational obligations.</p>
                        <button type="button" class="arms-action-btn">Track Flow</button>
                    </div>
                </div>
                <div class="arms-report-card">
                    <div class="arms-report-icon">📈</div>
                    <div class="arms-report-details">
                        <h4>Profit & Loss (P&L) Ledger Balance</h4>
                        <p>Contrasts live raw institutional revenues directly against active operational costs.</p>
                        <button type="button" class="arms-action-btn">Calculate Margin</button>
                    </div>
                </div>
                <div class="arms-report-card">
                    <div class="arms-report-icon">⚖️</div>
                    <div class="arms-report-details">
                        <h4>Balance Sheet Assets Evaluation Matrix</h4>
                        <p>Consolidates value models of clinical machinery and physical inventory against long-term liabilities.</p>
                        <button type="button" class="arms-action-btn">Verify Balance</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        window.armsSwitchFinTab = function(panelId) {
            // Drop active view states safely without disturbing generic WP frameworks
            document.querySelectorAll('.arms-fin-panel').forEach(function(panel) {
                panel.classList.remove('active');
            });
            document.querySelectorAll('.arms-fin-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            // Isolate individual DOM identifiers
            var selectedPanel = document.getElementById(panelId);
            var selectedBtn = document.getElementById('btn-' + panelId);

            if (selectedPanel && selectedBtn) {
                selectedPanel.classList.add('active');
                selectedBtn.classList.add('active');
            }
        }
    </script>
    <?php
}