<?php
/*--------------------------------------------------------------
# 1. AJAX Database Income Source Collector Processing Handler
--------------------------------------------------------------*/
function arms_get_income_data_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'arms_finance_secure_nonce')) {
        wp_send_json_error('Security validation failed.', 403);
    }

    global $wpdb;
    $table_billing = $wpdb->prefix . 'arms_billing';

    $results = $wpdb->get_results("SELECT * FROM $table_billing ORDER BY created_at DESC", ARRAY_A);
    $formatted_ledger_logs = array();

    if (!empty($results)) {
        foreach ($results as $row) {
            $formatted_ledger_logs[] = array(
                'id'             => intval($row['id']),
                'invoice_id'     => esc_html($row['invoice_id']),
                'billing_type'   => esc_html(strtolower($row['billing_type'])),
                'payment_method' => esc_html($row['payment_method']),
                'total_price'    => floatval($row['total_price']),
                'date'           => date('Y-m-d', strtotime($row['created_at']))
            );
        }
    }

    wp_send_json_success(array('ledger' => $formatted_ledger_logs));
}
add_action('wp_ajax_arms_get_income_data', 'arms_get_income_data_handler');


/*--------------------------------------------------------------
# 2. Main Dashboard Tab Display Grid Architecture
--------------------------------------------------------------*/
function arms_finance_tab() {
    global $wpdb;
    $table_expenses = $wpdb->prefix . 'arms_expenses';
    $table_billing = $wpdb->prefix . 'arms_billing';
    $security_nonce = wp_create_nonce('arms_finance_secure_nonce');

    // Fetch live entries from database logs
    $expenses_log = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_expenses'") === $table_expenses) {
        $expenses_log = $wpdb->get_results("SELECT * FROM $table_expenses ORDER BY id DESC", ARRAY_A);
    }

    $income_log = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_billing'") === $table_billing) {
        $income_log = $wpdb->get_results("SELECT * FROM $table_billing ORDER BY id DESC", ARRAY_A);
    }

    $fixed_lease_total = 0;
    $utility_matrix_total = 0;
    $pending_outflow_total = 0;

    if (!empty($expenses_log)) {
        foreach ($expenses_log as $row) {
            $amt = floatval($row['total_amount']);
            if ($row['expense_category'] === 'operational' && $row['expense_type'] === 'rent') {
                $fixed_lease_total += $amt;
            } elseif ($row['expense_category'] === 'utility') {
                $utility_matrix_total += $amt;
            } else {
                $pending_outflow_total += $amt;
            }
        }
    }
    ?>
    <style>
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
        .arms-fin-btn.active { color: #003376; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }

        .arms-fin-panel { display: none; animation: armsFinFadeIn 0.25s ease-out; }
        .arms-fin-panel.active { display: block; }
        @keyframes armsFinFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

        .arms-table-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            padding: 14px 16px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .arms-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .arms-filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

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
        .arms-stat-card.danger { border-left: 4px solid #ef4444; }

        .arms-table-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 8px 8px;
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
            background: #f1f5f9;
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

        .arms-actions-cell {
            display: flex;
            gap: 6px;
        }
        .arms-action-btn {
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            color: #475569;
            transition: all 0.15s ease;
        }
        .arms-action-btn.edit:hover { border-color: #b45309; color: #b45309; background: #fffbeb; }
        .arms-action-btn.delete:hover { border-color: #b91c1c; color: #b91c1c; background: #fef2f2; }

        .arms-select-field, .arms-input-field {
            padding: 6px 10px;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #fff;
            color: #334155;
            outline: none;
            height: 32px;
        }
        .arms-input-field:focus, .arms-select-field:focus { border-color: #003376; }
        .arms-label-inline { font-size: 13px; font-weight: 600; color: #475569; }
        
        .arms-submit-btn {
            background: #003376;
            color: #ffffff;
            border: 1px solid #003376;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            height: 36px;
        }
        .arms-submit-btn:hover { background: #4338ca; border-color: #4338ca; }
        .arms-submit-btn:disabled { background: #cbd5e1; border-color: #cbd5e1; cursor: not-allowed; }
        
        .arms-cancel-btn {
            background: #cbd5e1;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            height: 36px;
        }
        .arms-cancel-btn:hover { background: #94a3b8; border-color: #94a3b8; }

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
        .arms-sub-tab-btn.active { color: #003376; background: #eef2ff; }

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
        .arms-form-element-group label { font-size: 12px; font-weight: 600; color: #475569; }
    </style>

    <div class="arms-fin-wrapper">

        <div class="arms-fin-nav">
            <button type="button" class="arms-fin-btn active" id="btn-fin-income" onclick="armsSwitchFinTab('fin-income')">💵 Income</button>
            <button type="button" class="arms-fin-btn" id="btn-fin-expenses" onclick="armsSwitchFinTab('fin-expenses')">💸 Expenses</button>
        </div>

        <!-- 💵 INCOME PANEL -->
        <div id="fin-income" class="arms-fin-panel active">
            <div class="arms-table-toolbar">
                <div class="arms-filter-group">
                    <label>Category:</label>
                    <select class="arms-select-field arms-income-filter-cat" onchange="armsFilterIncomeTable()">
                        <option value="">All Categories</option>
                        <option value="opd">OPD</option>
                        <option value="ipd">IPD</option>
                    </select>
                </div>
                <div class="arms-filter-group">
                    <label>From Date:</label>
                    <input type="date" class="arms-input-field arms-income-filter-start" onchange="armsFilterIncomeTable()" />
                </div>
                <div class="arms-filter-group">
                    <label>To Date:</label>
                    <input type="date" class="arms-input-field arms-income-filter-end" onchange="armsFilterIncomeTable()" />
                </div>
            </div>

            <div class="arms-table-wrapper">
                <table class="arms-data-table" id="arms-income-log-table">
                    <thead>
                        <tr>
                            <th>Invoice Reference Key</th>
                            <th>Allocation Channel / Category</th>
                            <th>Payment Transferred Via</th>
                            <th>Value Transferred</th>
                            <th>Transactional Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($income_log)): ?>
                            <?php foreach ($income_log as $row): ?>
                                <tr data-category="<?php echo esc_attr(strtolower($row['billing_type'])); ?>" data-date="<?php echo esc_attr(date('Y-m-d', strtotime($row['created_at']))); ?>">
                                    <td><code><?php echo esc_html($row['invoice_id']); ?></code></td>
                                    <td><b><?php echo esc_html(strtoupper($row['billing_type'])); ?> Stream</b></td>
                                    <td><?php echo esc_html(ucfirst($row['payment_method'])); ?></td>
                                    <td>৳<?php echo number_format(floatval($row['total_price']), 2); ?></td>
                                    <td><?php echo esc_html(date('Y-m-d', strtotime($row['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="no-records-row"><td colspan="5" style="text-align:center; color:#94a3b8;">No revenue billing logs present.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 💸 EXPENSES PANEL -->
        <div id="fin-expenses" class="arms-fin-panel">
            <div class="arms-sub-nav-tabs">
                <button type="button" class="arms-sub-tab-btn active" id="btn-sub-exp-list" onclick="armsSwitchSubExpenseTab('sub-exp-list')">📋 Expense Ledger Log</button>
                <button type="button" class="arms-sub-tab-btn" id="btn-sub-exp-add" onclick="armsSwitchSubExpenseTab('sub-exp-add')">➕ Add Expense Allocation</button>
            </div>

            <div id="sub-exp-list" class="arms-form-matrix-block active">
                <div class="arms-stat-grid">
                    <div class="arms-stat-card"><span class="arms-stat-label">Fixed Lease Asset Obligations</span><span class="arms-stat-val" id="kpi-fixed-lease">৳<?php echo number_format($fixed_lease_total, 2); ?></span></div>
                    <div class="arms-stat-card"><span class="arms-stat-label">Infrastructure Utility Matrix</span><span class="arms-stat-val" id="kpi-utility-matrix">৳<?php echo number_format($utility_matrix_total, 2); ?></span></div>
                    <div class="arms-stat-card danger"><span class="arms-stat-label">Other Outflow Demands</span><span class="arms-stat-val" id="kpi-pending-outflow">৳<?php echo number_format($pending_outflow_total, 2); ?></span></div>
                </div>

                <div class="arms-table-toolbar">
                    <div class="arms-filter-group">
                        <label>Category:</label>
                        <select class="arms-select-field arms-expense-filter-cat" onchange="armsFilterExpenseTable()">
                            <option value="">All Categories</option>
                            <option value="salary">Salary Matrix</option>
                            <option value="utility">Utility Matrix</option>
                            <option value="operational">Operational Overhead</option>
                        </select>
                    </div>
                    <div class="arms-filter-group">
                        <label>From Date:</label>
                        <input type="date" class="arms-input-field arms-expense-filter-start" onchange="armsFilterExpenseTable()" />
                    </div>
                    <div class="arms-filter-group">
                        <label>To Date:</label>
                        <input type="date" class="arms-input-field arms-expense-filter-end" onchange="armsFilterExpenseTable()" />
                    </div>
                </div>

                <div class="arms-table-wrapper">
                    <table class="arms-data-table" id="arms-expenses-log-table">
                        <thead>
                            <tr>
                                <th>Operational Cost Line Element</th>
                                <th>System Accounting Code</th>
                                <th>Cost Classification Category</th>
                                <th>Current Fiscal Cycle Demand</th>
                                <th>Transactional Date</th>
                                <th style="text-align: center; width: 140px;">Action Controllers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($expenses_log)): ?>
                                <?php foreach ($expenses_log as $row): ?>
                                    <tr data-id="<?php echo intval($row['id']); ?>" 
                                        data-category="<?php echo esc_attr($row['expense_category']); ?>" 
                                        data-type="<?php echo esc_attr($row['expense_type']); ?>"
                                        data-month="<?php echo esc_attr($row['target_month']); ?>"
                                        data-year="<?php echo esc_attr($row['target_year']); ?>"
                                        data-base="<?php echo esc_attr($row['base_amount']); ?>"
                                        data-adjustment="<?php echo esc_attr($row['adjustment_amount']); ?>"
                                        data-auth="<?php echo esc_attr($row['authorized_by']); ?>"
                                        data-date="<?php echo esc_attr($row['transaction_date']); ?>">
                                        <td><b><?php echo esc_html(ucfirst($row['expense_type'])); ?></b></td>
                                        <td><code>EXP-<?php echo esc_html(strtoupper(substr($row['expense_category'], 0, 3))); ?></code></td>
                                        <td><?php echo esc_html(ucfirst($row['expense_category'])); ?> Matrix</td>
                                        <td class="row-total-amount">৳<?php echo number_format(floatval($row['total_amount']), 2); ?></td>
                                        <td><?php echo esc_html($row['transaction_date']); ?></td>
                                        <td>
                                            <div class="arms-actions-cell">
                                                <button type="button" class="arms-action-btn edit" onclick="armsEditExpenseRow(this)">Edit</button>
                                                <button type="button" class="arms-action-btn delete" onclick="armsRowAction('delete', <?php echo intval($row['id']); ?>)">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="no-records-row"><td colspan="6" style="text-align:center; color:#94a3b8;">No records saved yet. Add fields through the form array matrix.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Form Matrix Dropdown Input Panels -->
            <div id="sub-exp-add" class="arms-form-matrix-block">
                <div style="margin-bottom: 16px;">
                    <label class="arms-label-inline" style="display:block; margin-bottom:6px;">Select Core Expense Matrix Category</label>
                    <select class="arms-select-field" id="arms-main-expense-category" style="width:100%; max-width:320px;" onchange="armsRenderExpenseFormFields(this.value)">
                        <option value="salary">Salary Matrix</option>
                        <option value="utility">Utility Matrix</option>
                        <option value="operational">Operational Overhead Matrix</option>
                    </select>
                </div>

                <form method="post" action="" id="arms-expense-form">
                    <input type="hidden" id="arms-expense-row-id" value="0" />
                    
                    <div id="ctx-fields-salary" class="arms-form-fields-context">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Staff Category Type</label>
                                <select class="arms-data-type arms-select-field">
                                    <option value="doctor">Doctor</option>
                                    <option value="physio">Physio</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Target Month</label>
                                <select class="arms-data-month arms-select-field">
                                    <option value="January">January</option><option value="February">February</option><option value="March">March</option>
                                    <option value="April">April</option><option value="May">May</option><option value="June" selected>June</option>
                                    <option value="July">July</option><option value="August">August</option><option value="September">September</option>
                                    <option value="October">October</option><option value="November">November</option><option value="December">December</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Target Accounting Fiscal Year</label>
                                <select class="arms-data-year arms-select-field">
                                    <option value="2026" selected>2026</option>
                                    <option value="2027">2027</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Base Line Net Amount (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-base arms-input-field" required />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Bonus Adjustments (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-adjustment arms-input-field" />
                            </div>
                            <div class="arms-form-element-group" style="flex-direction:row; gap:8px;">
                                <button type="submit" class="arms-submit-btn" id="arms-salary-submit-text">Post Salary Ledger</button>
                                <button type="button" class="arms-cancel-btn" id="arms-salary-cancel-btn" style="display:none;" onclick="armsResetExpenseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div id="ctx-fields-utility" class="arms-form-fields-context" style="display:none;">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Infrastructure Utility Type</label>
                                <select class="arms-data-type arms-select-field">
                                    <option value="electricity">Electricity</option>
                                    <option value="internet">Internet</option>
                                    <option value="water">Water</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Billing Period Month</label>
                                <select class="arms-data-month arms-select-field">
                                    <option value="January">January</option><option value="February">February</option><option value="March">March</option>
                                    <option value="April">April</option><option value="May">May</option><option value="June" selected>June</option>
                                    <option value="July">July</option><option value="August">August</option><option value="September">September</option>
                                    <option value="October">October</option><option value="November">November</option><option value="December">December</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Aggregated Meter Amount (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-base arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Posting Transaction Date</label>
                                <input type="date" value="<?php echo current_time('Y-m-d'); ?>" class="arms-data-date arms-input-field" />
                            </div>
                            <div class="arms-form-element-group" style="flex-direction:row; gap:8px;">
                                <button type="submit" class="arms-submit-btn" id="arms-utility-submit-text">Post Utility Ledger</button>
                                <button type="button" class="arms-cancel-btn" id="arms-utility-cancel-btn" style="display:none;" onclick="armsResetExpenseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div id="ctx-fields-operational" class="arms-form-fields-context" style="display:none;">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Operational Cost Allocation Line</label>
                                <select class="arms-data-type arms-select-field">
                                    <option value="rent">Rent</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="equipment">Equipment purchase</option>
                                    <option value="consumables">Consumables</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Authorized Initiated By</label>
                                <input type="text" placeholder="Procurement Officer" class="arms-data-auth arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Gross Allocation Amount (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-base arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Invoice Transaction Date</label>
                                <input type="date" value="<?php echo current_time('Y-m-d'); ?>" class="arms-data-date arms-input-field" />
                            </div>
                            <div class="arms-form-element-group" style="flex-direction:row; gap:8px;">
                                <button type="submit" class="arms-submit-btn" id="arms-operational-submit-text">Post Operational Ledger</button>
                                <button type="button" class="arms-cancel-btn" id="arms-operational-cancel-btn" style="display:none;" onclick="armsResetExpenseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <script type="text/javascript">
        var arms_fin_meta = { 
            nonce: '<?php echo esc_js($security_nonce); ?>',
            ajaxurl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            current_date: '<?php echo current_time('Y-m-d'); ?>'
        };

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

        window.armsRenderExpenseFormFields = function(targetCategory) {
            document.querySelectorAll('.arms-form-fields-context').forEach(function(ctxBlock) {
                ctxBlock.style.display = 'none';
                jQuery(ctxBlock).find('.arms-input-field, .arms-select-field').removeAttr('required');
            });
            
            var targetedContextBlock = document.getElementById('ctx-fields-' + targetCategory);
            if (targetedContextBlock) {
                targetedContextBlock.style.display = 'block';
                jQuery(targetedContextBlock).find('.arms-data-base').attr('required', 'required');
            }
        };

        window.armsFilterIncomeTable = function() {
            var category = jQuery('.arms-income-filter-cat').val();
            var start = jQuery('.arms-income-filter-start').val();
            var end = jQuery('.arms-income-filter-end').val();

            jQuery('#arms-income-log-table tbody tr').each(function() {
                var $row = jQuery(this);
                var rowCat = $row.attr('data-category');
                var rowDate = $row.attr('data-date');
                var match = true;

                if (category && rowCat !== category) match = false;
                if (start && rowDate < start) match = false;
                if (end && rowDate > end) match = false;

                if (match) $row.show(); else $row.hide();
            });
        };

        window.armsFilterExpenseTable = function() {
            var category = jQuery('.arms-expense-filter-cat').val();
            var start = jQuery('.arms-expense-filter-start').val();
            var end = jQuery('.arms-expense-filter-end').val();

            jQuery('#arms-expenses-log-table tbody tr').each(function() {
                var $row = jQuery(this);
                if($row.hasClass('no-records-row')) return;
                
                var rowCat = $row.attr('data-category');
                var rowDate = $row.attr('data-date');
                var match = true;

                if (category && rowCat !== category) match = false;
                if (start && rowDate < start) match = false;
                if (end && rowDate > end) match = false;

                if (match) $row.show(); else $row.hide();
            });
        };

        window.armsEditExpenseRow = function(btn) {
            var $row = jQuery(btn).closest('tr');
            var rowId = $row.attr('data-id');
            var category = $row.attr('data-category');
            
            jQuery('#arms-expense-row-id').val(rowId);
            jQuery('#arms-main-expense-category').val(category).trigger('change').prop('disabled', true);
            
            var $ctx = jQuery('#ctx-fields-' + category);
            $ctx.find('.arms-data-type').val($row.attr('data-type'));
            $ctx.find('.arms-data-month').val($row.attr('data-month'));
            $ctx.find('.arms-data-year').val($row.attr('data-year'));
            $ctx.find('.arms-data-base').val($row.attr('data-base'));
            $ctx.find('.arms-data-adjustment').val($row.attr('data-adjustment'));
            $ctx.find('.arms-data-auth').val($row.attr('data-auth'));
            $ctx.find('.arms-data-date').val($row.attr('data-date'));
            
            jQuery('#arms-' + category + '-submit-text').text('Update Ledger Record');
            jQuery('#arms-' + category + '-cancel-btn').show();
            
            jQuery('#btn-sub-exp-add').text('📝 Edit Expense Record');
            armsSwitchSubExpenseTab('sub-exp-add');
        };

        window.armsResetExpenseForm = function() {
            jQuery('#arms-expense-form')[0].reset();
            jQuery('#arms-expense-row-id').val('0');
            jQuery('#arms-main-expense-category').prop('disabled', false);
            
            document.querySelectorAll('.arms-data-date').forEach(function(el) {
                el.value = arms_fin_meta.current_date;
            });
            
            jQuery('.arms-submit-btn').each(function() {
                var cat = jQuery(this).attr('id').split('-')[1];
                jQuery(this).text('Post ' + cat.charAt(0).toUpperCase() + cat.slice(1) + ' Ledger');
            });
            jQuery('.arms-cancel-btn').hide();
            jQuery('#btn-sub-exp-add').text('➕ Add Expense Allocation');
            
            armsRenderExpenseFormFields(jQuery('#arms-main-expense-category').val());
        };

        window.armsRowAction = function(actionType, rowId) {
            if (actionType === 'delete') {
                if (confirm('Are you absolutely sure you want to delete row log entry ID: ' + rowId + '?')) {
                    var $row = jQuery('#arms-expenses-log-table tbody tr[data-id="' + rowId + '"]');
                    var category = $row.attr('data-category');
                    var type = $row.attr('data-type');
                    var amt = parseFloat($row.find('.row-total-amount').text().replace(/[^0-9.-]+/g,"")) || 0;

                    // Dynamically deduct from KPIs on successful mock delete transaction
                    if (category === 'operational' && type === 'rent') {
                        var currentFixed = parseFloat(jQuery('#kpi-fixed-lease').text().replace(/[^0-9.-]+/g,"")) || 0;
                        jQuery('#kpi-fixed-lease').text('৳' + Math.max(0, currentFixed - amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    } else if (category === 'utility') {
                        var currentUtil = parseFloat(jQuery('#kpi-utility-matrix').text().replace(/[^0-9.-]+/g,"")) || 0;
                        jQuery('#kpi-utility-matrix').text('৳' + Math.max(0, currentUtil - amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    } else {
                        var currentOther = parseFloat(jQuery('#kpi-pending-outflow').text().replace(/[^0-9.-]+/g,"")) || 0;
                        jQuery('#kpi-pending-outflow').text('৳' + Math.max(0, currentOther - amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    }

                    $row.fadeOut('slow', function() {
                        jQuery(this).remove();
                        if (jQuery('#arms-expenses-log-table tbody tr').length === 0) {
                            jQuery('#arms-expenses-log-table tbody').append('<tr class="no-records-row"><td colspan="6" style="text-align:center; color:#94a3b8;">No records saved yet. Add fields through the form array matrix.</td></tr>');
                        }
                    });
                }
            }
        };

        jQuery(document).ready(function($) {
            armsRenderExpenseFormFields($('#arms-main-expense-category').val());

            $('#arms-expense-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var activeCategory = $('#arms-main-expense-category').val();
                var $activeContext = $('#ctx-fields-' + activeCategory);
                var $btn = $activeContext.find('.arms-submit-btn');
                var originalBtnText = $btn.text();
                var rowId = $('#arms-expense-row-id').val();
                
                var dataFields = {
                    id               : rowId,
                    expense_category : activeCategory,
                    expense_type     : $activeContext.find('.arms-data-type').val() || '',
                    target_month     : $activeContext.find('.arms-data-month').val() || '',
                    target_year      : $activeContext.find('.arms-data-year').val() || '2026',
                    base_amount      : $activeContext.find('.arms-data-base').val() || 0,
                    adjustment_amount: $activeContext.find('.arms-data-adjustment').val() || 0,
                    authorized_by    : $activeContext.find('.arms-data-auth').val() || '',
                    transaction_date : $activeContext.find('.arms-data-date').val() || arms_fin_meta.current_date
                };

                $btn.text('Saving...').prop('disabled', true);

                $.ajax({
                    url: arms_fin_meta.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'arms_save_expense_data',
                        nonce: arms_fin_meta.nonce,
                        fields: dataFields
                    },
                    success: function(response) {
                        if (response.success) {
                            var calculatedTotal = parseFloat(dataFields.base_amount) + parseFloat(dataFields.adjustment_amount);
                            var formattedTotal = calculatedTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            var cleanLabel = dataFields.expense_type.charAt(0).toUpperCase() + dataFields.expense_type.slice(1);
                            var codePrefix = 'EXP-' + activeCategory.substring(0, 3).toUpperCase();
                            
                            if (rowId !== '0') {
                                // Update Existing DOM Row
                                var $existingRow = $('#arms-expenses-log-table tbody tr[data-id="' + rowId + '"]');
                                var oldAmt = parseFloat($existingRow.find('.row-total-amount').text().replace(/[^0-9.-]+/g,"")) || 0;
                                var oldType = $existingRow.attr('data-type');

                                // Revert old amounts from KPIs
                                if (activeCategory === 'operational' && oldType === 'rent') {
                                    var cFixed = parseFloat($('#kpi-fixed-lease').text().replace(/[^0-9.-]+/g,"")) || 0;
                                    $('#kpi-fixed-lease').text('৳' + (cFixed - oldAmt).toFixed(2));
                                } else if (activeCategory === 'utility') {
                                    var cUtil = parseFloat($('#kpi-utility-matrix').text().replace(/[^0-9.-]+/g,"")) || 0;
                                    $('#kpi-utility-matrix').text('৳' + (cUtil - oldAmt).toFixed(2));
                                } else {
                                    var cOther = parseFloat($('#kpi-pending-outflow').text().replace(/[^0-9.-]+/g,"")) || 0;
                                    $('#kpi-pending-outflow').text('৳' + (cOther - oldAmt).toFixed(2));
                                }

                                // Inject updated parameters into element data attributes
                                $existingRow.attr({
                                    'data-type': dataFields.expense_type,
                                    'data-month': dataFields.target_month,
                                    'data-year': dataFields.target_year,
                                    'data-base': dataFields.base_amount,
                                    'data-adjustment': dataFields.adjustment_amount,
                                    'data-auth': dataFields.authorized_by,
                                    'data-date': dataFields.transaction_date
                                });

                                $existingRow.find('td:eq(0)').html('<b>' + cleanLabel + '</b>');
                                $existingRow.find('.row-total-amount').text('৳' + formattedTotal);
                                $existingRow.find('td:eq(4)').text(dataFields.transaction_date);
                            } else {
                                // Append New Row Log Node
                                var insertedId = response.data.row_id || Date.now();
                                var newRowHtml = '<tr data-id="' + insertedId + '" data-category="' + activeCategory + '" data-type="' + dataFields.expense_type + '" data-month="' + dataFields.target_month + '" data-year="' + dataFields.target_year + '" data-base="' + dataFields.base_amount + '" data-adjustment="' + dataFields.adjustment_amount + '" data-auth="' + dataFields.authorized_by + '" data-date="' + dataFields.transaction_date + '">' +
                                    '<td><b>' + cleanLabel + '</b></td>' +
                                    '<td><code>' + codePrefix + '</code></td>' +
                                    '<td>' + activeCategory.charAt(0).toUpperCase() + activeCategory.slice(1) + ' Matrix</td>' +
                                    '<td class="row-total-amount">৳' + formattedTotal + '</td>' +
                                    '<td>' + dataFields.transaction_date + '</td>' +
                                    '<td>' +
                                        '<div class="arms-actions-cell">' +
                                            '<button type="button" class="arms-action-btn edit" onclick="armsEditExpenseRow(this)">Edit</button> ' +
                                            '<button type="button" class="arms-action-btn delete" onclick="armsRowAction(\'delete\', ' + insertedId + ')">Delete</button>' +
                                        '</div>' +
                                    '</td>' +
                                '</tr>';
                                
                                $('#arms-expenses-log-table tbody .no-records-row').remove();
                                $('#arms-expenses-log-table tbody').prepend(newRowHtml);
                            }
                            
                            // Re-calculate and patch metrics summary cards
                            if (activeCategory === 'operational' && dataFields.expense_type === 'rent') {
                                var currentFixed = parseFloat($('#kpi-fixed-lease').text().replace(/[^0-9.-]+/g,"")) || 0;
                                $('#kpi-fixed-lease').text('৳' + (currentFixed + calculatedTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                            } else if (activeCategory === 'utility') {
                                var currentUtil = parseFloat($('#kpi-utility-matrix').text().replace(/[^0-9.-]+/g,"")) || 0;
                                $('#kpi-utility-matrix').text('৳' + (currentUtil + calculatedTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                            } else {
                                var currentOther = parseFloat($('#kpi-pending-outflow').text().replace(/[^0-9.-]+/g,"")) || 0;
                                $('#kpi-pending-outflow').text('৳' + (currentOther + calculatedTotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                            }

                            armsResetExpenseForm();
                            armsSwitchSubExpenseTab('sub-exp-list');
                        } else {
                            alert('Submission Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Server processing error.');
                    },
                    complete: function() {
                        $btn.text(originalBtnText).prop('disabled', false);
                    }
                });
            });
        });
    </script>
    <?php
}