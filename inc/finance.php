<?php
if (!defined('ABSPATH')) exit;

/**
 * Hook Core WP Admin Action Endpoints for Expense Tracking Data Save Routing
 */
add_action('wp_ajax_arms_save_expense_data', 'arms_ajax_save_expense_data');
add_action('wp_ajax_nopriv_arms_save_expense_data', 'arms_ajax_save_expense_data'); 

function arms_ajax_save_expense_data() {
    // 1. Verify Nonce Security Context
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'arms_finance_secure_nonce')) {
        wp_send_json_error('Security validation failed.');
    }

    // 2. Access User System Control Capability Check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized operation execution rejected.');
    }

    // 3. Destructure and Sanitize Request Fields
    if (!isset($_POST['fields']) || !is_array($_POST['fields'])) {
        wp_send_json_error('No valid ledger data array found.');
    }

    $raw_fields = $_POST['fields'];
    
    // String data constraints matched to schema varchar allocations
    $expense_category  = substr(sanitize_text_field($raw_fields['expense_category']), 0, 50);
    $expense_type      = substr(sanitize_text_field($raw_fields['expense_type']), 0, 100);
    $target_month      = substr(sanitize_text_field($raw_fields['target_month']), 0, 30);
    $target_year       = substr(sanitize_text_field($raw_fields['target_year']), 0, 10);
    $authorized_by     = substr(sanitize_text_field($raw_fields['authorized_by']), 0, 255);
    $transaction_date  = sanitize_text_field($raw_fields['transaction_date']);
    
    // Decimal float values formatting extraction
    $base_amount       = isset($raw_fields['base_amount']) && is_numeric($raw_fields['base_amount']) ? floatval($raw_fields['base_amount']) : 0.00;
    $adjustment_amount = isset($raw_fields['adjustment_amount']) && is_numeric($raw_fields['adjustment_amount']) ? floatval($raw_fields['adjustment_amount']) : 0.00;
    $total_amount      = $base_amount + $adjustment_amount;
    
    // Safe evaluation matching DEFAULT '1970-01-01' NOT NULL schema logic
    if (empty($transaction_date) || !strtotime($transaction_date)) {
        $transaction_date = current_time('Y-m-d');
    }

    global $wpdb;
    $table_expenses = $wpdb->prefix . 'arms_expenses';

    // 4. Ingestion Matrix matching the exact schema configurations
    $inserted = $wpdb->insert(
        $table_expenses,
        array(
            'expense_category'  => !empty($expense_category) ? $expense_category : 'general',
            'expense_type'      => !empty($expense_type) ? $expense_type : 'unclassified',
            'target_month'      => !empty($target_month) ? $target_month : '',
            'target_year'       => !empty($target_year) ? $target_year : '',
            'base_amount'       => $base_amount,
            'adjustment_amount' => $adjustment_amount,
            'total_amount'      => $total_amount,
            'authorized_by'     => !empty($authorized_by) ? $authorized_by : '',
            'transaction_date'  => $transaction_date,
            'notes'             => null, 
            'created_by'        => intval(get_current_user_id()),
            'created_at'        => current_time('mysql')
        ),
        array(
            '%s', // expense_category (varchar(50))
            '%s', // expense_type (varchar(100))
            '%s', // target_month (varchar(30))
            '%s', // target_year (varchar(10))
            '%f', // base_amount (decimal(10,2))
            '%f', // adjustment_amount (decimal(10,2))
            '%f', // total_amount (decimal(10,2))
            '%s', // authorized_by (varchar(255))
            '%s', // transaction_date (date)
            '%s', // notes (text DEFAULT NULL)
            '%d', // created_by (bigint(20))
            '%s'  // created_at (datetime)
        )
    );

    if ($inserted !== false) {
        wp_send_json_success(array('row_id' => $wpdb->insert_id));
    } else {
        if (!empty($wpdb->last_error)) {
            wp_send_json_error('Database failure: ' . $wpdb->last_error);
        } else {
            wp_send_json_error('Database failure: Row validation failed against schema logic.');
        }
    }
}

/**
 * Render the Advanced Analytics Finance Ledger & Business Control Center.
 */
function arms_finance_tab() {
    global $wpdb;
    $table_expenses = $wpdb->prefix . 'arms_expenses';
    $security_nonce = wp_create_nonce('arms_finance_secure_nonce');

    // Fetch live entries from the database
    $expenses_log = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_expenses'") === $table_expenses) {
        $expenses_log = $wpdb->get_results("SELECT * FROM $table_expenses ORDER BY id DESC", ARRAY_A);
    }

    // Dynamic Aggregations from live table records
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
        .arms-fin-btn.active { color: #003376; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }

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
        .arms-stat-card.accent { border-left: 4px solid #003376; background: #f8fafc; }
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
            border-color: #003376;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        .arms-label-inline {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }
        
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
        .arms-sub-tab-btn.active { color: #003376; background: #eef2ff; }

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

        <div class="arms-fin-nav">
            <button type="button" class="arms-fin-btn active" id="btn-fin-income" onclick="armsSwitchFinTab('fin-income')">💵 Income</button>
            <button type="button" class="arms-fin-btn" id="btn-fin-expenses" onclick="armsSwitchFinTab('fin-expenses')">💸 Expenses</button>
        </div>

        <div id="fin-income" class="arms-fin-panel active">
            <div class="arms-panel-meta">
                <h3>Revenue Inflow Categorization Matrices</h3>
                <span class="arms-pill green">Total Inflow Synced</span>
            </div>

            <div class="arms-stat-grid">
                <div class="arms-stat-card accent"><span class="arms-stat-label">Clinical Core Stream</span><span class="arms-stat-val">৳০.০০</span></div>
                <div class="arms-stat-card"><span class="arms-stat-label">Commercial Auxiliary Sales</span><span class="arms-stat-val">৳০.০০</span></div>
                <div class="arms-stat-card"><span class="arms-stat-label">Academy & Training Income</span><span class="arms-stat-val">৳০.০০</span></div>
                <div class="arms-stat-card success"><span class="arms-stat-label">Gross Consolidated Flow</span><span class="arms-stat-val">৳০.০০</span></div>
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
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No live income data discovered.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="fin-expenses" class="arms-fin-panel">
            <div class="arms-panel-meta">
                <h3>Operational Overhead Encumbrances</h3>
                <span class="arms-pill gray" id="arms-live-sync-indicator">Sync Live</span>
            </div>

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

                <div class="arms-table-wrapper">
                    <table class="arms-data-table" id="arms-expenses-log-table">
                        <thead>
                            <tr>
                                <th>Operational Cost Line Element</th>
                                <th>System Accounting Code</th>
                                <th>Cost Classification Category</th>
                                <th>Current Fiscal Cycle Demand</th>
                                <th>Transactional Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($expenses_log)): ?>
                                <?php foreach ($expenses_log as $row): ?>
                                    <tr>
                                        <td><b><?php echo esc_html(ucfirst($row['expense_type'])); ?></b></td>
                                        <td><code>EXP-<?php echo esc_html(strtoupper(substr($row['expense_category'], 0, 3))); ?></code></td>
                                        <td><?php echo esc_html(ucfirst($row['expense_category'])); ?> Matrix</td>
                                        <td>৳<?php echo number_format(floatval($row['total_amount']), 2); ?></td>
                                        <td><?php echo esc_html($row['transaction_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="no-records-row"><td colspan="5" style="text-align:center; color:#94a3b8;">No records saved yet. Add fields through the form array matrix.</td></tr>
                            <?php endif; ?>
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

                <form method="post" action="" id="arms-expense-form">
                    
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
                            <div class="arms-form-element-group">
                                <button type="submit" class="arms-submit-btn">Post Salary Ledger</button>
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
                            <div class="arms-form-element-group">
                                <button type="submit" class="arms-submit-btn">Post Utility Ledger</button>
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
                            <div class="arms-form-element-group">
                                <button type="submit" class="arms-submit-btn">Post Operational Ledger</button>
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

        jQuery(document).ready(function($) {
            armsRenderExpenseFormFields($('#arms-main-expense-category').val());

            $('#arms-expense-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var activeCategory = $('#arms-main-expense-category').val();
                var $activeContext = $('#ctx-fields-' + activeCategory);
                var $btn = $activeContext.find('.arms-submit-btn');
                var originalBtnText = $btn.text();
                
                var dataFields = {
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
                            // Calculate values on the fly to append to table structure dynamically
                            var calculatedTotal = parseFloat(dataFields.base_amount) + parseFloat(dataFields.adjustment_amount);
                            var formattedTotal = calculatedTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            var cleanLabel = dataFields.expense_type.charAt(0).toUpperCase() + dataFields.expense_type.slice(1);
                            var codePrefix = 'EXP-' + activeCategory.substring(0, 3).toUpperCase();
                            
                            var newRowHtml = '<tr>' +
                                '<td><b>' + cleanLabel + '</b></td>' +
                                '<td><code>' + codePrefix + '</code></td>' +
                                '<td>' + activeCategory.charAt(0).toUpperCase() + activeCategory.slice(1) + ' Matrix</td>' +
                                '<td>৳' + formattedTotal + '</td>' +
                                '<td>' + dataFields.transaction_date + '</td>' +
                            '</tr>';
                            
                            // Remove empty information row container if existing
                            $('#arms-expenses-log-table tbody .no-records-row').remove();
                            
                            // Prepend row array inside the list architecture matrix
                            $('#arms-expenses-log-table tbody').prepend(newRowHtml);
                            
                            // Dynamic real-time calculation update for the KPI card layers
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

                            // Flash operational state confirmation notice indicator
                            var $indicator = $('#arms-live-sync-indicator');
                            $indicator.text('Entry Saved!').css({'background':'#10b981', 'color':'#fff'});
                            setTimeout(function(){
                                $indicator.text('Sync Live').css({'background':'#f1f5f9', 'color':'#475569'});
                            }, 3000);

                            // Purge configurations back to core defaults
                            $form[0].reset();
                            document.querySelectorAll('.arms-data-date').forEach(function(el) {
                                el.value = arms_fin_meta.current_date;
                            });
                            armsRenderExpenseFormFields(activeCategory);
                            
                            // Route navigation back to visual display log pane block
                            armsSwitchSubExpenseTab('sub-exp-list');
                        } else {
                            alert('Submission Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('ARMS Trace Exception:', xhr.responseText);
                        alert('Server processing trace lost. Check dev console log output.');
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