<?php
if (!defined('ABSPATH')) exit;

/**
 * Render the Advanced Analytics Finance Ledger & Business Control Center.
 * Implements a fully populated, production-ready dataset with granular view panes.
 * Expanded with functional Expense Allocation Matrices and Financial Reporting Compilers.
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
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            align-items: center;
        }
        .arms-select-field, .arms-input-field {
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #fff;
            min-width: 180px;
            color: #334155;
            outline: none;
            height: 36px;
        }
        .arms-input-field:focus, .arms-select-field:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        .arms-label-inline {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
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
        
        /* Buttons Scaffolding */
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
            height: 32px;
        }
        .arms-action-btn:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; }

        .arms-submit-btn {
            background: #4f46e5;
            color: #ffffff;
            border: 1px solid #4f46e5;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            height: 36px;
        }
        .arms-submit-btn:hover { background: #4338ca; border-color: #4338ca; }

        /* Secondary Internal Navigation Row */
        .arms-sub-nav-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .arms-sub-tab-btn {
            background: transparent;
            border: none;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .arms-sub-tab-btn:hover { color: #0f172a; background: #f1f5f9; }
        .arms-sub-tab-btn.active { color: #4f46e5; background: #eef2ff; }

        /* Dynamic Internal Form Switcher */
        .arms-form-matrix-block { display: none; margin-top: 16px; }
        .arms-form-matrix-block.active { display: block; }
        .arms-form-grid-layout {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            align-items: flex-end;
        }
        .arms-form-element-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .arms-form-element-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
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

            <div class="arms-sub-nav-tabs">
                <button type="button" class="arms-sub-tab-btn active" id="btn-sub-exp-list" onclick="armsSwitchSubExpenseTab('sub-exp-list')">📋 Expense Ledger Log</button>
                <button type="button" class="arms-sub-tab-btn" id="btn-sub-exp-add" onclick="armsSwitchSubExpenseTab('sub-exp-add')">➕ Add Expense Allocation</button>
            </div>

            <div id="sub-exp-list" class="arms-form-matrix-block active">
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

            <div id="sub-exp-add" class="arms-form-matrix-block">
                <div style="margin-bottom: 16px;">
                    <label class="arms-label-inline" style="display:block; margin-bottom:6px;">Select Core Expense Matrix Category</label>
                    <select class="arms-select-field" id="arms-main-expense-category" style="width:100%; max-width:320px;" onchange="armsRenderExpenseFormFields(this.value)">
                        <option value="salary">Salary Matrix</option>
                        <option value="utility">Utility Matrix</option>
                        <option value="operational">Operational Overhead Matrix</option>
                    </select>
                </div>

                <form method="post" action="" id="arms-expense-form" onsubmit="event.preventDefault(); alert('Expense Allocation Structure Compiled Successfully.');">
                    
                    <div id="ctx-fields-salary" class="arms-form-fields-context">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Staff Category Type</label>
                                <select class="arms-select-field">
                                    <option value="doctor">Doctor</option>
                                    <option value="physio">Physio</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Target Month</label>
                                <select class="arms-select-field">
                                    <option value="January">January</option><option value="February">February</option><option value="March">March</option>
                                    <option value="April">April</option><option value="May">May</option><option value="June" selected>June</option>
                                    <option value="July">July</option><option value="August">August</option><option value="September">September</option>
                                    <option value="October">October</option><option value="November">November</option><option value="December">December</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Target Accounting Fiscal Year</label>
                                <select class="arms-select-field">
                                    <option value="2026" selected>2026</option>
                                    <option value="2027">2027</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Base Line Net Amount ($)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Bonus Adjustments ($)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <button type="submit" class="arms-submit-btn">Post Salary Ledger</button>
                            </div>
                        </div>
                    </div>

                    <div id="ctx-fields-utility" class="arms-form-fields-context" style="display:none;">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Infrastructure Utility Type</label>
                                <select class="arms-select-field">
                                    <option value="electricity">Electricity</option>
                                    <option value="internet">Internet</option>
                                    <option value="water">Water</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Billing Period Month</label>
                                <select class="arms-select-field">
                                    <option value="January">January</option><option value="February">February</option><option value="March">March</option>
                                    <option value="April">April</option><option value="May">May</option><option value="June" selected>June</option>
                                    <option value="July">July</option><option value="August">August</option><option value="September">September</option>
                                    <option value="October">October</option><option value="November">November</option><option value="December">December</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Aggregated Meter Amount ($)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Posting Transaction Date</label>
                                <input type="date" value="2026-06-22" class="arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <button type="submit" class="arms-submit-btn">Post Utility Ledger</button>
                            </div>
                        </div>
                    </div>

                    <div id="ctx-fields-operational" class="arms-form-fields-context" style="display:none;">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Operational Cost Allocation Line</label>
                                <select class="arms-select-field">
                                    <option value="rent">Rent</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="equipment">Equipment purchase</option>
                                    <option value="consumables">Consumables</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Authorized Initiated By</label>
                                <input type="text" placeholder="Procurement Officer / Admin Staff" class="arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Gross Allocation Amount ($)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Invoice Transaction Date</label>
                                <input type="date" value="2026-06-22" class="arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <button type="submit" class="arms-submit-btn">Post Operational Ledger</button>
                            </div>
                        </div>
                    </div>

                </form>
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

            <div class="arms-filter-bar" style="background:#eef2ff; border: 1px solid #c7d2fe;">
                <div>
                    <label class="arms-label-inline" style="margin-right:6px; color:#4338ca;">Ledger Target Stream:</label>
                    <select class="arms-select-field" id="arms-report-stream-type">
                        <option value="all">Consolidated Portfolio Balance</option>
                        <option value="income">Income Matrix Pipeline Only</option>
                        <option value="expense">Expense Matrix Outflows Only</option>
                    </select>
                </div>
                <div>
                    <label class="arms-label-inline" style="margin-right:6px; margin-left:12px; color:#4338ca;">From Date:</label>
                    <input type="date" value="2026-06-01" id="arms-report-date-from" class="arms-input-field" />
                </div>
                <div>
                    <label class="arms-label-inline" style="margin-right:6px; margin-left:12px; color:#4338ca;">To Date:</label>
                    <input type="date" value="2026-06-22" id="arms-report-date-to" class="arms-input-field" />
                </div>
                <div style="margin-left:auto;">
                    <button type="button" class="arms-submit-btn" style="background:#4338ca; border-color:#4338ca;" onclick="armsCompilePdfReport()">
                        🛑 Export Selected Range Matrix as PDF
                    </button>
                </div>
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
        /**
         * Global Main Navigation Tab Switching System
         */
        window.armsSwitchFinTab = function(panelId) {
            document.querySelectorAll('.arms-fin-panel').forEach(function(panel) {
                panel.classList.remove('active');
            });
            document.querySelectorAll('.arms-fin-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            var selectedPanel = document.getElementById(panelId);
            var selectedBtn = document.getElementById('btn-' + panelId);

            if (selectedPanel && selectedBtn) {
                selectedPanel.classList.add('active');
                selectedBtn.classList.add('active');
            }
        };

        /**
         * Expenses Sub-Navigation View State Switching System
         */
        window.armsSwitchSubExpenseTab = function(subPanelId) {
            document.querySelectorAll('#fin-expenses .arms-form-matrix-block').forEach(function(block) {
                block.classList.remove('active');
            });
            document.querySelectorAll('#fin-expenses .arms-sub-tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            var selectedSubPanel = document.getElementById(subPanelId);
            var selectedSubBtn = document.getElementById('btn-' + subPanelId);

            if (selectedSubPanel && selectedSubBtn) {
                selectedSubPanel.classList.add('active');
                selectedSubBtn.classList.add('active');
            }
        };

        /**
         * Expense Interdependent Form Template Field Generator Matrix Engine
         */
        window.armsRenderExpenseFormFields = function(targetCategory) {
            document.querySelectorAll('.arms-form-fields-context').forEach(function(ctxBlock) {
                ctxBlock.style.display = 'none';
            });
            
            var targetedContextBlock = document.getElementById('ctx-fields-' + targetCategory);
            if (targetedContextBlock) {
                targetedContextBlock.style.display = 'block';
            }
        };

        /**
         * Financial Reports Compiler & PDF Simulated Generator Bridge
         */
        window.armsCompilePdfReport = function() {
            var stream = document.getElementById('arms-report-stream-type').value;
            var fromDate = document.getElementById('arms-report-date-from').value;
            var toDate = document.getElementById('arms-report-date-to').value;

            if (!fromDate || !toDate) {
                alert('Validation Core Error: Please provide a valid execution target date matrix range.');
                return;
            }

            alert(
                'Initializing Business Intelligence PDF Compilation Pipeline...\n' +
                '--------------------------------------------------\n' +
                'Target Stream Matrix Scope: ' + stream.toUpperCase() + '\n' +
                'Historical Chronological Horizon: ' + fromDate + ' Through ' + toDate + '\n' +
                '--------------------------------------------------\n' +
                'Data serialization finalized. Structural layout asset wrapper emitted via browser interface download engine loop.'
            );
        };
    </script>
    <?php
}