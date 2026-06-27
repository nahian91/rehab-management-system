<?php

// Route backend requests safely 
add_action('wp_ajax_arms_save_opd_record', 'arms_save_opd_record_handler');
add_action('wp_ajax_arms_delete_opd_record', 'arms_delete_opd_record_handler');
add_action('wp_ajax_arms_get_opd_record', 'arms_get_opd_record_handler');

function arms_save_opd_record_handler() {
    global $wpdb;
    check_ajax_referer('arms_opd_security_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access.', 'arms-textdomain')]);
    }

    $table_opd = $wpdb->prefix . 'arms_opd_records';
    $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

    $patient_name       = sanitize_text_field($_POST['patient_name']);
    $phone              = sanitize_text_field($_POST['phone']);
    $age                = !empty($_POST['age']) ? intval($_POST['age']) : 0; 
    $address            = sanitize_textarea_field($_POST['address']);
    $practitioner_id    = !empty($_POST['practitioner_id']) ? intval($_POST['practitioner_id']) : 0;
    $practitioner_name  = !empty($_POST['practitioner_name']) ? sanitize_text_field($_POST['practitioner_name']) : 'None Assigned';
    $consultation_fee   = floatval($_POST['consultation_fee']);
    $services_rendered  = wp_kses_post(wp_unslash($_POST['services_rendered'])); 
    $subtotal           = floatval($_POST['subtotal']);
    $discount_amount    = floatval($_POST['discount_amount']);
    $net_total          = floatval($_POST['net_total']);
    $payment_method     = sanitize_text_field($_POST['payment_method']);

    if (empty($patient_name) || empty($phone)) {
        wp_send_json_error(['message' => __('Patient Name and Phone fields are strictly required.', 'arms-textdomain')]);
    }

    $data = [
        'patient_name'      => $patient_name,
        'phone'             => $phone,
        'age'               => $age,
        'address'           => $address,
        'practitioner_id'   => $practitioner_id,
        'practitioner_name' => $practitioner_name,
        'consultation_fee'  => $consultation_fee,
        'services_rendered' => $services_rendered,
        'subtotal'          => $subtotal,
        'discount_amount'   => $discount_amount,
        'net_total'         => $net_total,
        'payment_method'    => $payment_method,
    ];

    $format = ['%s', '%s', '%d', '%s', '%d', '%s', '%f', '%s', '%f', '%f', '%f', '%s'];

    if ($record_id > 0) {
        $updated = $wpdb->update($table_opd, $data, ['id' => $record_id], $format, ['%d']);
        if ($updated !== false) {
            wp_send_json_success(['message' => __('OPD Record updated successfully.', 'arms-textdomain'), 'record_id' => $record_id]);
        }
    } else {
        $data['ticket_no']  = 'TKT-' . strtoupper(wp_generate_password(6, false, false));
        $data['created_by'] = get_current_user_id();
        $data['created_at'] = current_time('mysql');
        
        $format[] = '%s'; 
        $format[] = '%d'; 
        $format[] = '%s'; 

        $inserted = $wpdb->insert($table_opd, $data, $format);
        if ($inserted) {
            wp_send_json_success(['message' => __('New OPD Ticket logged successfully.', 'arms-textdomain'), 'record_id' => $wpdb->insert_id]);
        } else {
            if (!empty($wpdb->last_error)) {
                wp_send_json_error(['message' => 'SQL Error: ' . $wpdb->last_error]);
            }
        }
    }
    wp_send_json_error(['message' => __('Database transaction error.', 'arms-textdomain')]);
}

function arms_get_opd_record_handler() {
    global $wpdb;
    check_ajax_referer('arms_opd_security_nonce', 'security');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $table_opd = $wpdb->prefix . 'arms_opd_records';
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_opd WHERE id = %d", $id), ARRAY_A);

    if ($record) {
        wp_send_json_success($record);
    }
    wp_send_json_error(['message' => __('Record data not discovered.', 'arms-textdomain')]);
}

function arms_delete_opd_record_handler() {
    global $wpdb;
    check_ajax_referer('arms_opd_security_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized privilege access.', 'arms-textdomain')]);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $table_opd = $wpdb->prefix . 'arms_opd_records';

    if ($wpdb->delete($table_opd, ['id' => $id], ['%d'])) {
        wp_send_json_success(['message' => __('OPD record dropped.', 'arms-textdomain')]);
    }
    wp_send_json_error(['message' => __('Failed to remove database ledger line.', 'arms-textdomain')]);
}

function arms_opd_tab() {
    global $wpdb;

    $staff_table = $wpdb->prefix . 'arms_staff'; 
    $practitioners = [];
    
    if ( $wpdb->get_var("SHOW TABLES LIKE '$staff_table'") === $staff_table ) {
        $db_staff = $wpdb->get_results("
            SELECT id, first_name, last_name, role_category 
            FROM $staff_table 
            WHERE status = 'active' 
              AND role_category IN ('doctor', 'physiotherapist')
            ORDER BY role_category ASC, first_name ASC
        ");

        if ( ! empty( $db_staff ) ) {
            foreach ( $db_staff as $member ) {
                $base_fee = ($member->role_category === 'doctor') ? 1200 : 800;
                $designation = ($member->role_category === 'doctor') ? 'Doctor' : 'Physiotherapist';
                $practitioners[] = [
                    'id'   => $member->id,
                    'name' => $member->first_name . ' ' . $member->last_name . ' (' . $designation . ')',
                    'fee'  => $base_fee
                ];
            }
        }
    }

    if ( empty( $practitioners ) ) {
        $practitioners = [
            ['id' => '1', 'name' => 'Dr. Mahfuzur Rahman (Doctor)', 'fee' => 1200],
            ['id' => '2', 'name' => 'Dr. Sarah Jenkins (Doctor)', 'fee' => 1000],
            ['id' => '4', 'name' => 'Alex Mercer, PT (Physiotherapist)', 'fee' => 800],
        ];
    }

    $saved_general_fees = get_option( 'arms_general_bdt_fees', array() );
    $services = [
        ['id' => 'consultation', 'name' => __('Consultation', 'arms-textdomain'), 'base_price' => 0]
    ]; 

    if ( ! empty( $saved_general_fees ) && is_array( $saved_general_fees ) ) {
        foreach ( $saved_general_fees as $index => $fee_item ) {
            if ( empty( $fee_item['fee_name'] ) ) continue;
            $services[] = [
                'id'         => 'gen_fee_' . $index,
                'name'       => $fee_item['fee_name'],
                'base_price' => floatval( $fee_item['fee_amount'] ?? 0 )
            ];
        }
    } else {
        $services = array_merge( $services, [
            ['id' => 'physio', 'name' => 'Physiotherapy Session', 'base_price' => 800],
            ['id' => 'acupuncture', 'name' => 'Acupuncture Procedure', 'base_price' => 1200],
            ['id' => 'prp', 'name' => 'PRP Therapy (Platelet-Rich Plasma)', 'base_price' => 4500],
            ['id' => 'procedures', 'name' => 'Minor Medical Procedures', 'base_price' => 1500],
        ]);
    }

    $payment_methods = [
        'cash'   => __('Cash', 'arms-textdomain'),
        'card'   => __('Credit/Debit Card', 'arms-textdomain'),
        'bank'   => __('Bank Transfer', 'arms-textdomain'),
        'mobile' => __('Mobile Banking (bKash/Nagad)', 'arms-textdomain')
    ];

    $table_opd = $wpdb->prefix . 'arms_opd_records';
    $records = [];
    if ( $wpdb->get_var("SHOW TABLES LIKE '$table_opd'") === $table_opd ) {
        $records = $wpdb->get_results("SELECT * FROM $table_opd ORDER BY id DESC LIMIT 50");
    }
    ?>
    
    <!-- CSS Interface Enhancements -->
    <style>
        .arms-opd-wrapper { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,0.04); font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; margin-top: 20px; }
        .arms-tabs-nav { background: #f6f7f7; border-bottom: 1px solid #ccd0d4; display: flex; list-style: none; margin: 0; padding: 0; }
        .arms-tab-link { border-right: 1px solid #ccd0d4; color: #1d2327; cursor: pointer; font-size: 14px; font-weight: 600; padding: 15px 20px; transition: background 0.15s ease-in-out; }
        .arms-tab-link:hover { background: #f0f0f1; }
        .arms-tab-link.active-tab { background: #fff; border-bottom: 2px solid #2271b1; color: #2271b1; margin-bottom: -1px; }
        .arms-tab-content { display: none; padding: 25px; }
        .arms-tab-content.active-tab-content { display: block; }
        .arms-opd-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; }
        .arms-opd-form-panel { background: #fff; }
        .arms-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .arms-form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .arms-form-group label { color: #1d2327; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
        .arms-input, .arms-select, .arms-textarea { border: 1px solid #8c8f94; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); box-sizing: border-box; font-size: 14px; padding: 8px 12px; width: 100%; }
        .arms-input:focus, .arms-select:focus, .arms-textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        .arms-textarea { height: 70px; resize: vertical; }
        
        /* Premium Checked Box & Services CSS Setup */
        .arms-services-list { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; padding: 5px 0; }
        .arms-service-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-bottom: 1px solid #e0e0e0; transition: background 0.1s ease; }
        .arms-service-item:last-child { border-bottom: none; }
        .arms-service-item:hover { background: #eff6ff; }
        .arms-service-item label { display: flex; align-items: center; gap: 10px; font-weight: 500; cursor: pointer; width: 80%; margin: 0; }
        .arms-service-checkbox { width: 16px; height: 16px; margin: 0 !important; cursor: pointer; }
        .arms-service-price { font-weight: 600; color: #475569; font-size: 13px; background: #e2e8f0; padding: 2px 8px; border-radius: 12px; }
        
        /* Receipt Panel Live Preview Settings */
        .arms-opd-preview-panel { background: #f0f0f1; border-radius: 8px; padding: 20px; display: flex; justify-content: center; align-items: flex-start; }
        .receipt-ticket-box { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 4px 10px rgba(0,0,0,0.06); box-sizing: border-box; font-family: "Courier New", Courier, monospace; font-size: 13px; padding: 20px; width: 100%; max-width: 340px; color: #000; }
        .receipt-center { text-align: center; }
        .receipt-divider { border-top: 1px dashed #000; margin: 12px 0; }
        .receipt-double-divider { border-top: 3px double #000; margin: 12px 0; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .receipt-item-line { display: grid; grid-template-columns: 3fr 1fr; gap: 5px; margin-bottom: 4px; }
        
        /* Interactive Panel Button Styling */
        .arms-btn-save { background: #2271b1; border: none; border-radius: 4px; color: #fff; cursor: pointer; font-size: 14px; font-weight: 600; padding: 10px 20px; transition: background 0.15s; }
        .arms-btn-save:hover { background: #135e96; }
        .arms-btn-reset { background: #f6f7f7; border: 1px solid #8c8f94; border-radius: 4px; color: #2c3338; cursor: pointer; font-size: 14px; font-weight: 600; padding: 9px 20px; margin-left: 10px; transition: all 0.15s; }
        .arms-btn-reset:hover { background: #f0f0f1; border-color: #50575e; }
        
        /* Table Layout Configuration */
        .arms-management-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .arms-management-table th, .arms-management-table td { text-align: left; padding: 12px; border-bottom: 1px solid #ccd0d4; }
        .arms-management-table th { background: #f6f7f7; font-weight: 700; color: #1d2327; }
        .opd-ticket-badge { background: #e7f4e4; color: #2e7d32; font-weight: bold; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .badge-payment { background: #f0f4f8; color: #1e3a8a; text-transform: uppercase; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
        .action-links a { margin-right: 12px; cursor: pointer; color: #2271b1; text-decoration: none; font-weight: 500; }
        .action-links a:hover { color: #135e96; text-decoration: underline; }
        .action-links a.delete-rec { color: #b32d2e; }
        .action-links a.delete-rec:hover { color: #852223; }
    </style>

    <div id="arms-receipt-print-target" style="display:none;"></div>

    <div class="arms-opd-wrapper">
        
        <!-- Tab Controls Navigation Bar -->
        <ul class="arms-tabs-nav">
            <li class="arms-tab-link active-tab" data-target="arms-tab-billing"><?php _e('Add OPD / Billing Terminal', 'arms-textdomain'); ?></li>
            <li class="arms-tab-link" data-target="arms-tab-records"><?php _e('All OPD Ledger Records', 'arms-textdomain'); ?></li>
        </ul>

        <!-- TAB 1: Billing Terminal -->
        <div id="arms-tab-billing" class="arms-tab-content active-tab-content">
            <div class="arms-opd-grid">
                <!-- Form Input Section -->
                <div class="arms-opd-form-panel">
                    <form id="arms-opd-billing-form" autocomplete="off">
                        <input type="hidden" id="record_id" value="0">
                        <?php wp_nonce_field('arms_opd_security_nonce', 'arms_opd_nonce'); ?>
                        
                        <h3 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:8px; color:#003376;"><?php _e('Patient Demographics & Practitioner Selection', 'arms-textdomain'); ?></h3>
                        
                        <div class="arms-form-row" style="grid-template-columns: 2fr 1fr;">
                            <div class="arms-form-group">
                                <label><?php _e('Select Doctor / Physiotherapist Name', 'arms-textdomain'); ?></label>
                                <select id="practitioner_select" class="arms-select">
                                    <option value="" data-id="" data-fee="0">-- <?php _e('Select Personnel', 'arms-textdomain'); ?> --</option>
                                    <?php foreach ($practitioners as $p) : ?>
                                        <option value="<?php echo esc_attr($p['name']); ?>" data-id="<?php echo esc_attr($p['id']); ?>" data-fee="<?php echo esc_attr($p['fee']); ?>"><?php echo esc_html($p['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="arms-form-group">
                                <label><?php _e('Fee (BDT)', 'arms-textdomain'); ?></label>
                                <input type="number" id="consultation_fee" class="arms-input" value="0" min="0">
                            </div>
                        </div>

                        <div class="arms-form-group">
                            <label><?php _e('Patient Name *', 'arms-textdomain'); ?></label>
                            <input type="text" id="patient_name" class="arms-input" placeholder="Enter patient name manually..." required>
                        </div>

                        <div class="arms-form-row">
                            <div class="arms-form-group">
                                <label><?php _e('Phone *', 'arms-textdomain'); ?></label>
                                <input type="text" id="patient_phone" class="arms-input" placeholder="01XXXXXXXXX" required>
                            </div>
                            <div class="arms-form-group">
                                <label><?php _e('Age', 'arms-textdomain'); ?></label>
                                <input type="number" id="patient_age" class="arms-input" min="0" max="130" placeholder="Age">
                            </div>
                        </div>

                        <div class="arms-form-group">
                            <label><?php _e('Address', 'arms-textdomain'); ?></label>
                            <textarea id="patient_address" class="arms-textarea" placeholder="Patient residential address..."></textarea>
                        </div>

                        <h3 style="border-bottom:1px solid #f0f0f1; padding-bottom:8px; color:#003376;"><?php _e('Services & Interventions', 'arms-textdomain'); ?></h3>
                        <div class="arms-form-group">
                            <div class="arms-services-list">
                                <?php foreach ($services as $s) : ?>
                                    <div class="arms-service-item">
                                        <label>
                                            <input type="checkbox" class="arms-service-checkbox" value="<?php echo esc_attr($s['name']); ?>" data-id="<?php echo esc_attr($s['id']); ?>" data-price="<?php echo esc_attr($s['base_price']); ?>">
                                            <span><?php echo esc_html($s['name']); ?></span>
                                        </label>
                                        <span class="arms-service-price" id="price-display-<?php echo esc_attr($s['id']); ?>">
                                            <?php echo $s['base_price'] > 0 ? esc_html($s['base_price']) . ' BDT' : __('Variable', 'arms-textdomain'); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <h3 style="border-bottom:1px solid #f0f0f1; padding-bottom:8px; color:#003376;"><?php _e('Billing & Settlement', 'arms-textdomain'); ?></h3>
                        <div class="arms-form-row">
                            <div class="arms-form-group">
                                <label><?php _e('Billing Method', 'arms-textdomain'); ?></label>
                                <select id="payment_method" class="arms-select">
                                    <?php foreach ($payment_methods as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="arms-form-group">
                                <label><?php _e('Discount Deductions (BDT)', 'arms-textdomain'); ?></label>
                                <input type="number" id="discount_amount" class="arms-input" value="0" min="0">
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="button" id="arms-save-opd-btn" class="arms-btn-save"><?php _e('Save Ticket', 'arms-textdomain'); ?></button>
                            <button type="button" id="arms-reset-opd-btn" class="arms-btn-reset"><?php _e('Clear / New', 'arms-textdomain'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Terminal Invoice Live Preview Panel Container -->
                <div class="arms-opd-preview-panel">
                    <div class="receipt-ticket-box">
                        <div class="receipt-center">
                            <h4 style="margin:0 0 4px 0; text-transform:uppercase;">Advanced Rehab & Wellness</h4>
                            <div>Garden Tower, Shahjalal Uposhahar, Sylhet</div>
                            <div>Hotline: +880 13 2476 3317</div>
                            <div style="font-weight:bold; margin-top:4px;">OPD LIVE PREVIEW</div>
                        </div>
                        <div class="receipt-divider"></div>
                        <div class="receipt-row">
                            <span>Invoice: <span id="rcpt_invoice_no">#OPD-NEW</span></span>
                            <span>Live Monitor</span>
                        </div>
                        <div class="receipt-divider"></div>
                        <div class="receipt-row"><strong>Patient:</strong> <span id="rcpt_patient_name">---------</span></div>
                        <div class="receipt-row"><strong>Age / Cell:</strong> <span id="rcpt_patient_meta">-- / ---------</span></div>
                        <div class="receipt-row"><strong>Consultant:</strong> <span id="rcpt_practitioner">None Selected</span></div>
                        <div class="receipt-double-divider"></div>
                        <div style="font-weight:bold; margin-bottom:6px;" class="receipt-item-line">
                            <span>Description</span><span style="text-align:right;">Amount</span>
                        </div>
                        <div id="rcpt_ledger_items_container">
                            <div style="color:#555; font-style:italic; text-align:center; padding:10px 0;">No active services checked</div>
                        </div>
                        <div class="receipt-double-divider"></div>
                        <div class="receipt-row"><span>SUBTOTAL</span><span id="rcpt_subtotal">0.00 BDT</span></div>
                        <div class="receipt-row"><span>DISCOUNT</span><span id="rcpt_discount">0.00 BDT</span></div>
                        <div class="receipt-divider"></div>
                        <div class="receipt-row" style="font-size:13px; font-weight:bold;"><span>TOTAL NET CASHED</span><span id="rcpt_net_total">0.00 BDT</span></div>
                        <div class="receipt-divider"></div>
                        <div class="receipt-row"><span>PAYMENT METHOD:</span><span id="rcpt_pay_method" style="text-transform:uppercase; font-weight:bold;">CASH</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Ledger View History Grid -->
        <div id="arms-tab-records" class="arms-tab-content">
            <div class="opd-history-section">
                <div class="opd-section-header">
                    <h3>Recent Out-Patient Transactions Logs</h3>
                </div>
                <table class="arms-management-table">
                    <thead>
                        <tr>
                            <th>Ticket No</th>
                            <th>Patient Details</th>
                            <th>Practitioner Assigned</th>
                            <th>Net Paid</th>
                            <th>Settlement Type</th>
                            <th>Date Processed</th>
                            <th>Control Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($records)) : foreach ($records as $r) : ?>
                            <tr id="row-opd-<?php echo $r->id; ?>">
                                <td><span class="opd-ticket-badge"><?php echo esc_html($r->ticket_no); ?></span></td>
                                <td>
                                    <strong><?php echo esc_html($r->patient_name); ?></strong> 
                                    <?php if(!empty($r->age)) { echo '('.esc_html($r->age).' Yrs)'; } ?>
                                    <br><span style="color:#646970; font-size:12px;"><?php echo esc_html($r->phone); ?></span>
                                </td>
                                <td><?php echo esc_html($r->practitioner_name); ?></td>
                                <td><span style="font-weight:600; color:#1d2327;"><?php echo number_format($r->net_total, 2); ?> BDT</span></td>
                                <td><span class="badge-payment"><?php echo esc_html($r->payment_method); ?></span></td>
                                <td style="color:#646970;"><?php echo date('d-M-Y H:i', strtotime($r->created_at)); ?></td>
                                <td class="action-links">
                                    <a class="print-rec" data-id="<?php echo $r->id; ?>">Print Receipt</a>
                                    <a class="edit-rec" data-id="<?php echo $r->id; ?>">Modify Data</a>
                                    <a class="delete-rec" data-id="<?php echo $r->id; ?>">Drop Record</a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="7" style="text-align:center; padding: 20px; color: #646970;">No records found within system storage ledger.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Core Tab Toggling
            $('.arms-tab-link').on('click', function() {
                const targetedContentId = $(this).data('target');
                
                $('.arms-tab-link').removeClass('active-tab');
                $('.arms-tab-content').removeClass('active-tab-content');
                
                $(this).addClass('active-tab');
                $('#' + targetedContentId).addClass('active-tab-content');
            });

            function clearOPDForm() {
                $('#record_id').val('0');
                $('#arms-opd-billing-form')[0].reset();
                $('#rcpt_invoice_no').text('#OPD-NEW');
                $('.arms-service-checkbox').prop('checked', false).trigger('change');
                updateOPDLedgerPreview();
            }

            // High-Reliability Thermal Receipt Generation & Printing Strategy
            $(document).on('click', '.print-rec', function() {
                const recordId = $(this).data('id');
                
                const dataPayload = {
                    action: 'arms_get_opd_record',
                    security: $('#arms_opd_nonce').val(),
                    id: recordId
                };

                $.post(ajaxurl, dataPayload, function(response) {
                    if(response.success) {
                        const data = response.data;
                        
                        // Parse JSON formatted string arrays for rendering inside thermal loop
                        let itemsHtml = '';
                        try {
                            const parsedServices = JSON.parse(data.services_rendered || '[]');
                            parsedServices.forEach(function(serv) {
                                itemsHtml += `<div class="receipt-item-line"><span>* ${serv.name}</span><span style="text-align:right;">${parseFloat(serv.price).toFixed(2)}</span></div>`;
                            });
                        } catch(e) {
                            itemsHtml = `<div class="receipt-item-line"><span>* Diagnostic Services</span><span style="text-align:right;">${parseFloat(data.subtotal).toFixed(2)}</span></div>`;
                        }

                        // Format parsing dates safely
                        let formattedDate = data.created_at;
                        if(data.created_at) {
                            const d = new Date(data.created_at.replace(/-/g, "/"));
                            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                            formattedDate = ("0" + d.getDate()).slice(-2) + "-" + months[d.getMonth()] + "-" + d.getFullYear() + " " + ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2);
                        }

                        // Construct full thermal document layout dynamically inside standard sandboxed block template
                        const printHtml = `
                            <div class="receipt-center">
                                <h4 style="margin:0 0 4px 0; text-transform:uppercase;">Advanced Rehab & Wellness</h4>
                                <div>Garden Tower, Shahjalal Uposhahar, Sylhet</div>
                                <div>Hotline: +880 13 2476 3317</div>
                                <div style="font-weight:bold; margin-top:4px;">OPD CASH RECEIPT</div>
                            </div>
                            <div class="receipt-divider"></div>
                            <div class="receipt-row">
                                <span>Invoice: <span>${data.ticket_no}</span></span>
                                <span>Date: ${formattedDate}</span>
                            </div>
                            <div class="receipt-divider"></div>
                            <div class="receipt-row"><strong>Patient:</strong> <span>${data.patient_name}</span></div>
                            <div class="receipt-row"><strong>Age / Cell:</strong> <span>${data.age ? data.age : '--'} Yrs / ${data.phone}</span></div>
                            <div class="receipt-row"><strong>Consultant:</strong> <span>${data.practitioner_name}</span></div>
                            <div class="receipt-double-divider"></div>
                            <div style="font-weight:bold; margin-bottom:6px;" class="receipt-item-line">
                                <span>Description</span><span style="text-align:right;">Amount</span>
                            </div>
                            <div>
                                ${itemsHtml}
                            </div>
                            <div class="receipt-double-divider"></div>
                            <div class="receipt-row"><span>SUBTOTAL</span><span>${parseFloat(data.subtotal).toFixed(2)} BDT</span></div>
                            <div class="receipt-row"><span>DISCOUNT</span><span>${parseFloat(data.discount_amount).toFixed(2)} BDT</span></div>
                            <div class="receipt-divider"></div>
                            <div class="receipt-row" style="font-size:13px; font-weight:bold;"><span>TOTAL NET CASHED</span><span>${parseFloat(data.net_total).toFixed(2)} BDT</span></div>
                            <div class="receipt-divider"></div>
                            <div class="receipt-row"><span>PAYMENT METHOD:</span><span style="text-transform:uppercase; font-weight:bold;">${data.payment_method}</span></div>
                        `;

                        // Render sandboxed isolated print sandbox to dodge admin css anomalies
                        const printFrame = $('<iframe id="arms-print-frame"></iframe>');
                        printFrame.css({ 'position': 'absolute', 'top': '-9999px', 'left': '-9999px', 'width': '0px', 'height': '0px' });
                        $('body').append(printFrame);
                        
                        const frameDoc = printFrame[0].contentWindow ? printFrame[0].contentWindow : (printFrame[0].contentDocument.document ? printFrame[0].contentDocument.document : printFrame[0].contentDocument);
                        frameDoc.document.open();
                        frameDoc.document.write(`
                            <html>
                            <head>
                                <title>OPD Receipt - ${data.ticket_no}</title>
                                <style>
                                    @page { size: 74mm auto; margin: 0mm; }
                                    body { font-family: "Courier New", Courier, monospace; font-size: 12px; color: #000; margin: 0; padding: 4mm 3mm; width: 68mm; background: #fff; }
                                    .receipt-center { text-align: center; }
                                    .receipt-divider { border-top: 1px dashed #000; margin: 10px 0; }
                                    .receipt-double-divider { border-top: 3px double #000; margin: 10px 0; }
                                    .receipt-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
                                    .receipt-item-line { display: grid; grid-template-columns: 3fr 1fr; gap: 5px; margin-bottom: 3px; }
                                    h4 { margin: 0 0 4px 0; text-transform: uppercase; font-size: 13px; }
                                </style>
                            </head>
                            <body>
                                ${printHtml}
                            </body>
                            </html>
                        `);
                        frameDoc.document.close();
                        
                        setTimeout(function() {
                            printFrame[0].contentWindow.focus();
                            printFrame[0].contentWindow.print();
                            setTimeout(function() { printFrame.remove(); }, 1000);
                        }, 500);

                    } else {
                        alert('Error loading dynamic ledger layout: ' + response.data.message);
                    }
                });
            });

            $('#arms-reset-opd-btn').on('click', function() {
                clearOPDForm();
            });

            $('#practitioner_select').on('change', function() {
                const defaultFee = parseFloat($(this).find('option:selected').data('fee')) || 0;
                $('#consultation_fee').val(defaultFee).trigger('change');
            });

            $('#practitioner_select, #consultation_fee, #patient_name, #patient_phone, #patient_age, #patient_address, #payment_method, #discount_amount, .arms-service-checkbox').on('input change', function() {
                updateOPDLedgerPreview();
            });

            function updateOPDLedgerPreview() {
                const name = $('#patient_name').val().trim() || '---------';
                const phone = $('#patient_phone').val().trim() || '---------';
                const age = $('#patient_age').val().trim() || '--';
                const docName = $('#practitioner_select option:selected').val() || 'None Selected';
                const activeConsultationPrice = parseFloat($('#consultation_fee').val()) || 0;

                $('#rcpt_patient_name').text(name);
                $('#rcpt_patient_meta').text(age + ' Yrs / ' + phone);
                $('#rcpt_practitioner').text(docName);

                const consultationBox = $('.arms-service-checkbox[data-id="consultation"]');
                consultationBox.data('price', activeConsultationPrice);
                $('#price-display-consultation').text(activeConsultationPrice > 0 ? activeConsultationPrice + ' BDT' : 'Variable');

                let subtotal = 0;
                let htmlLines = '';

                $('.arms-service-checkbox:checked').each(function() {
                    const itemName = $(this).val();
                    const itemPrice = parseFloat($(this).data('price')) || 0;
                    subtotal += itemPrice;
                    htmlLines += `<div class="receipt-item-line"><span>* ${itemName}</span><span style="text-align:right;">${itemPrice.toFixed(2)}</span></div>`;
                });

                if (htmlLines === '') {
                    htmlLines = '<div style="color:#555; font-style:italic; text-align:center; padding:10px 0;">No active services checked</div>';
                }

                $('#rcpt_ledger_items_container').html(htmlLines);
                const discount = parseFloat($('#discount_amount').val()) || 0;
                const netTotal = Math.max(0, subtotal - discount);

                $('#rcpt_subtotal').text(subtotal.toFixed(2) + ' BDT');
                $('#rcpt_discount').text(discount.toFixed(2) + ' BDT');
                $('#rcpt_net_total').text(netTotal.toFixed(2) + ' BDT');
                $('#rcpt_pay_method').text($('#payment_method option:selected').text());
            }

            // AJAX Save/Update Ticket Action
            $('#arms-save-opd-btn').on('click', function() {
                const $btn = $(this);
                const checkedServices = [];
                
                $('.arms-service-checkbox:checked').each(function() {
                    checkedServices.push({ 
                        name: $(this).val(), 
                        price: parseFloat($(this).data('price')) || 0 
                    });
                });

                // Compute financial lines safely inside UI execution flow
                let subtotal = 0;
                checkedServices.forEach(function(item) { subtotal += item.price; });
                const discount = parseFloat($('#discount_amount').val()) || 0;
                const netTotal = Math.max(0, subtotal - discount);

                const patientName = $('#patient_name').val().trim();
                const phone = $('#patient_phone').val().trim();

                if (!patientName || !phone) {
                    alert('Patient Name and Phone fields are strictly required.');
                    return;
                }

                $btn.prop('disabled', true).text('Processing...');

                const dataPayload = {
                    action: 'arms_save_opd_record',
                    security: $('#arms_opd_nonce').val(),
                    record_id: $('#record_id').val(),
                    patient_name: patientName,
                    phone: phone,
                    age: $('#patient_age').val(),
                    address: $('#patient_address').val(),
                    practitioner_id: $('#practitioner_select option:selected').data('id') || 0,
                    practitioner_name: $('#practitioner_select option:selected').val() || 'None Assigned',
                    consultation_fee: parseFloat($('#consultation_fee').val()) || 0,
                    services_rendered: JSON.stringify(checkedServices), // Enqueue as JSON String array
                    subtotal: subtotal,
                    discount_amount: discount,
                    net_total: netTotal,
                    payment_method: $('#payment_method').val()
                };

                $.post(ajaxurl, dataPayload, function(response) {
                    $btn.prop('disabled', false).text('Save Ticket');
                    if (response.success) {
                        alert(response.data.message);
                        location.reload(); // Refresh screen state to synchronize updated ledger tables
                    } else {
                        alert('Operation Failed: ' + (response.data.message || 'Unknown database issue occurred.'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Save Ticket');
                    alert('Server communication break. Please evaluate local network connection lines.');
                });
            });

            // Modify Data Trigger Handler (Edit)
            $(document).on('click', '.edit-rec', function() {
                const recordId = $(this).data('id');
                const dataPayload = {
                    action: 'arms_get_opd_record',
                    security: $('#arms_opd_nonce').val(),
                    id: recordId
                };

                $.post(ajaxurl, dataPayload, function(response) {
                    if(response.success) {
                        const data = response.data;
                        
                        // Switch active tabs safely
                        $('.arms-tab-link[data-target="arms-tab-billing"]').trigger('click');
                        
                        // Hydrate native form input variables
                        $('#record_id').val(data.id);
                        $('#patient_name').val(data.patient_name);
                        $('#patient_phone').val(data.phone);
                        $('#patient_age').val(data.age || '');
                        $('#patient_address').val(data.address);
                        $('#consultation_fee').val(data.consultation_fee);
                        $('#discount_amount').val(data.discount_amount);
                        $('#payment_method').val(data.payment_method);
                        
                        // Set selected Personnel Option
                        if(data.practitioner_name && data.practitioner_name !== 'None Assigned') {
                            $('#practitioner_select').val(data.practitioner_name);
                        } else {
                            $('#practitioner_select').val('');
                        }

                        // Reset checkboxes before re-evaluating matches
                        $('.arms-service-checkbox').prop('checked', false);
                        
                        try {
                            const parsedServices = JSON.parse(data.services_rendered || '[]');
                            parsedServices.forEach(function(serv) {
                                $(`.arms-service-checkbox[value="${serv.name}"]`).prop('checked', true);
                            });
                        } catch(e) {
                            console.error('Failure parsing nested checked structures.');
                        }

                        $('#rcpt_invoice_no').text('#' + (data.ticket_no || 'OPD-MOD'));
                        updateOPDLedgerPreview();
                        window.scrollTo({ top: $('.arms-opd-wrapper').offset().top - 40, behavior: 'smooth' });
                    } else {
                        alert('Failed to drop state onto editor: ' + response.data.message);
                    }
                });
            });

            // Drop Record Controller Handler (Delete)
            $(document).on('click', '.delete-rec', function() {
                if (!confirm('Are you absolutely certain you want to purge this database ledger entry line?')) return;
                
                const recordId = $(this).data('id');
                const dataPayload = {
                    action: 'arms_delete_opd_record',
                    security: $('#arms_opd_nonce').val(),
                    id: recordId
                };

                $.post(ajaxurl, dataPayload, function(response) {
                    if(response.success) {
                        $(`#row-opd-${recordId}`).fadeOut(400, function() { $(this).remove(); });
                    } else {
                        alert('Refused by server ledger access protocols: ' + response.data.message);
                    }
                });
            });

        });
    </script>
    <?php
}