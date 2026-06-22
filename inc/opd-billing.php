<?php

function arms_opd_tab() {
    global $wpdb;

    // 1. DYNAMIC DATA FETCH: Filter exclusively for Active Doctors and Physiotherapists
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
                // Establish structural base configuration fees
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

    // Secure operational registry fallback logic
    if ( empty( $practitioners ) ) {
        $practitioners = [
            ['id' => '1', 'name' => 'Dr. Mahfuzur Rahman (Doctor)', 'fee' => 1200],
            ['id' => '2', 'name' => 'Dr. Sarah Jenkins (Doctor)', 'fee' => 1000],
            ['id' => '4', 'name' => 'Alex Mercer, PT (Physiotherapist)', 'fee' => 800],
        ];
    }

    $services = [
        ['id' => 'consultation', 'name' => 'Doctor Consultation', 'base_price' => 0], 
        ['id' => 'physio', 'name' => 'Physiotherapy Session', 'base_price' => 800],
        ['id' => 'acupuncture', 'name' => 'Acupuncture Procedure', 'base_price' => 1200],
        ['id' => 'prp', 'name' => 'PRP Therapy (Platelet-Rich Plasma)', 'base_price' => 4500],
        ['id' => 'procedures', 'name' => 'Minor Medical Procedures', 'base_price' => 1500],
    ];

    $payment_methods = [
        'cash'   => __('Cash', 'arms-textdomain'),
        'card'   => __('Credit/Debit Card', 'arms-textdomain'),
        'bank'   => __('Bank Transfer', 'arms-textdomain'),
        'mobile' => __('Mobile Banking (bKash/Nagad)', 'arms-textdomain')
    ];
    ?>
    <style>
        /* ONSCREEN DASHBOARD STYLING */
        .arms-opd-wrapper { margin: 10px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; color: #2c3338; }
        .arms-opd-header h2 { font-size: 23px; font-weight: 400; margin-bottom: 20px; color: #1d2327; }
        
        .arms-opd-grid { display: flex; gap: 24px; align-items: flex-start; }
        .arms-opd-form-panel { flex: 1; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .arms-opd-preview-panel { width: 380px; position: sticky; top: 50px; }
        
        .arms-form-group { margin-bottom: 16px; position: relative; }
        .arms-form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; font-size: 13px; }
        .arms-form-row { display: grid; grid-template-columns: 2fr 1fr; gap: 12px; }
        
        .arms-input, .arms-select, .arms-textarea { width: 100%; height: 36px; padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box; background-color: #fff; font-size: 14px; color: #2c3338; }
        .arms-textarea { height: 70px; resize: vertical; }
        .arms-input:focus, .arms-select:focus, .arms-textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: 2px solid transparent; }

        .arms-services-list { border: 1px solid #dcdcde; border-radius: 4px; padding: 12px; max-height: 220px; overflow-y: auto; background: #f6f7f7; }
        .arms-service-item { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e5e5e5; }
        .arms-service-item:last-child { border-bottom: none; }
        .arms-service-item label { font-weight: 400; display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; }
        .arms-service-price { font-weight: 600; color: #444; font-size: 13px; }
        
        /* PREVIEW CONTAINER STYLING */
        .receipt-ticket-box { background: #fff; border: 1px solid #a7aaad; border-radius: 2px; width: 100%; box-shadow: 0 4px 10px rgba(0,0,0,0.08); padding: 24px 16px; box-sizing: border-box; position: relative; font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.4; color: #000; }
        .receipt-ticket-box::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: repeating-linear-gradient(90deg, #a7aaad, #a7aaad 4px, transparent 4px, transparent 8px); }
        
        .receipt-center { text-align: center; }
        .receipt-divider { border-top: 1px dashed #000; margin: 10px 0; }
        .receipt-double-divider { border-top: 3px double #000; margin: 10px 0; }
        .receipt-header-title { font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 0 0 4px 0; letter-spacing: 0.5px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .receipt-item-line { display: grid; grid-template-columns: 3fr 1fr; gap: 5px; margin-bottom: 4px; }
        .receipt-val-right { text-align: right; }
        
        .arms-print-action-btn { width: 100%; height: 40px; margin-top: 15px; background: #2271b1; color: #fff; border: 1px solid #0a4b78; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 1px 0 #0a4b78; }
        .arms-print-action-btn:hover { background: #135e96; border-color: #0a4b78; color: #fff; }
        .arms-print-action-btn svg { width: 16px; height: 16px; fill: currentColor; }

        /* HIGH-PRECISION REVISED THERMAL PRINT SYSTEM */
        @media print {
            @page { 
                size: auto; 
                margin: 0mm !important; 
            }
            
            /* Hide the root layout containers entirely to clear the dashboard grid space */
            #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter, .arms-opd-form-panel, .arms-print-action-btn, .arms-opd-header, .admin-menu, h1, h2, h3, .notice { 
                display: none !important;
            }

            /* Reset the main structural wrappers to plain block elements so the print preview doesn't freeze loading */
            html, body, #wpwrap, #wpcontent, #wpbody, #wpbody-content, .arms-opd-wrapper, .arms-opd-grid, .arms-opd-preview-panel { 
                position: static !important;
                display: block !important;
                float: none !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important; 
                padding: 0 !important; 
                border: none !important;
                background: #fff !important;
                box-shadow: none !important;
                overflow: visible !important;
            }

            /* Absolute top-left positioning constraint for the receipt target element */
            #arms-receipt-print-target { 
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                z-index: 9999999 !important;
                width: 74mm !important; /* Standard POS Thermal Width spacing */
                margin: 0 !important;
                padding: 6mm 4mm !important;
                border: none !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
                font-family: "Courier New", Courier, monospace !important;
                font-size: 12px !important;
                line-height: 1.4 !important;
                display: block !important;
            }

            /* Strip the jagged UI receipt edge decoration graphic */
            #arms-receipt-print-target::before { 
                display: none !important; 
            }
            
            /* Re-force functional structure flex layouts for internal item rows */
            #arms-receipt-print-target .receipt-row, 
            #arms-receipt-print-target .receipt-item-line {
                display: flex !important;
                justify-content: space-between !important;
                width: 100% !important;
            }
            #arms-receipt-print-target .receipt-center {
                text-align: center !important;
                width: 100% !important;
            }
            #arms-receipt-print-target .receipt-val-right {
                text-align: right !important;
            }
        }
    </style>

    <div class="arms-opd-wrapper">
        <div class="arms-opd-grid">
            <div class="arms-opd-form-panel">
                <form id="arms-opd-billing-form" onsubmit="return false;" autocomplete="off">
                    
                    <h3 style="margin-top:0; border-bottom:1px solid #f0f0f1; padding-bottom:8px; color:#2271b1; font-weight:500; font-size:16px;"><?php echo esc_html__('Patient Demographics & Practitioner Selection', 'arms-textdomain'); ?></h3>
                    
                    <div class="arms-form-row" style="grid-template-columns: 2fr 1fr; gap: 12px;">
                        <div class="arms-form-group">
                            <label for="practitioner_select"><?php echo esc_html__('Select Doctor / Physiotherapist Name', 'arms-textdomain'); ?></label>
                            <select id="practitioner_select" class="arms-select">
                                <option value="" data-fee="0">-- <?php echo esc_html__('Select Medical Personnel', 'arms-textdomain'); ?> --</option>
                                <?php foreach ($practitioners as $p) : ?>
                                    <option value="<?php echo esc_attr($p['name']); ?>" data-fee="<?php echo esc_attr($p['fee']); ?>">
                                        <?php echo esc_html($p['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label for="consultation_fee"><?php echo esc_html__('Fee (BDT)', 'arms-textdomain'); ?></label>
                            <input type="number" id="consultation_fee" class="arms-input" value="0" min="0">
                        </div>
                    </div>

                    <div class="arms-form-group">
                        <label for="patient_name"><?php echo esc_html__('Patient Name', 'arms-textdomain'); ?></label>
                        <input type="text" id="patient_name" class="arms-input" placeholder="Enter patient name manually..." required>
                    </div>

                    <div class="arms-form-row">
                        <div class="arms-form-group">
                            <label for="patient_phone"><?php echo esc_html__('Phone', 'arms-textdomain'); ?></label>
                            <input type="text" id="patient_phone" class="arms-input" placeholder="01XXXXXXXXX" required>
                        </div>
                        <div class="arms-form-group">
                            <label for="patient_age"><?php echo esc_html__('Age', 'arms-textdomain'); ?></label>
                            <input type="number" id="patient_age" class="arms-input" min="0" max="130" placeholder="Age">
                        </div>
                    </div>

                    <div class="arms-form-group">
                        <label for="patient_address"><?php echo esc_html__('Address', 'arms-textdomain'); ?></label>
                        <textarea id="patient_address" class="arms-textarea" placeholder="Patient residential address..."></textarea>
                    </div>

                    <h3 style="border-bottom:1px solid #f0f0f1; padding-bottom:8px; color:#2271b1; margin-top:25px; font-weight:500; font-size:16px;"><?php echo esc_html__('Services & Interventions', 'arms-textdomain'); ?></h3>
                    
                    <div class="arms-form-group">
                        <div class="arms-services-list">
                            <?php foreach ($services as $s) : ?>
                                <div class="arms-service-item">
                                    <label>
                                        <input type="checkbox" class="arms-service-checkbox" value="<?php echo esc_attr($s['name']); ?>" data-id="<?php echo esc_attr($s['id']); ?>" data-price="<?php echo esc_attr($s['base_price']); ?>">
                                        <span class="arms-service-label-text"><?php echo esc_html($s['name']); ?></span>
                                    </label>
                                    <span class="arms-service-price" id="price-display-<?php echo esc_attr($s['id']); ?>">
                                        <?php echo $s['base_price'] > 0 ? esc_html($s['base_price']) . ' BDT' : esc_html__('Variable', 'arms-textdomain'); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <h3 style="border-bottom:1px solid #f0f0f1; padding-bottom:8px; color:#2271b1; margin-top:25px; font-weight:500; font-size:16px;"><?php echo esc_html__('Billing & Settlement', 'arms-textdomain'); ?></h3>
                    
                    <div class="arms-form-row" style="grid-template-columns: 1fr 1fr;">
                        <div class="arms-form-group">
                            <label for="payment_method"><?php echo esc_html__('Billing Method', 'arms-textdomain'); ?></label>
                            <select id="payment_method" class="arms-select">
                                <?php foreach ($payment_methods as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="arms-form-group">
                            <label for="discount_amount"><?php echo esc_html__('Discount Deductions (BDT)', 'arms-textdomain'); ?></label>
                            <input type="number" id="discount_amount" class="arms-input" value="0" min="0">
                        </div>
                    </div>
                </form>
            </div>

            <div class="arms-opd-preview-panel">
                <div id="arms-receipt-print-target" class="receipt-ticket-box">
                    <div class="receipt-center">
                        <h4 class="receipt-header-title">REHAB CARE NETWORK</h4>
                        <div>122/A Bluecrest Medical Strip, Dhaka</div>
                        <div>Hotline: +880-1700-000000</div>
                        <div style="font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 0px;">OPD CASH RECEIPT</div>
                    </div>

                    <div class="receipt-divider"></div>

                    <div class="receipt-row">
                        <span>Invoice: #OPD-<?php echo date('ymd'); ?>-<span id="rcpt_rand_id">101</span></span>
                        <span class="receipt-val-right">Date: <?php echo date('d-M-Y H:i'); ?></span>
                    </div>
                    
                    <div class="receipt-divider"></div>
                    
                    <div class="receipt-row"><strong>Patient:</strong> <span id="rcpt_patient_name">---------</span></div>
                    <div class="receipt-row"><strong>Age / Cell:</strong> <span id="rcpt_patient_meta">-- / ---------</span></div>
                    <div class="receipt-row" style="align-items: flex-start;"><strong>Address:</strong> <span id="rcpt_patient_address" style="text-align: right; max-width: 70%; word-break: break-all;">---</span></div>
                    <div class="receipt-row"><strong>Consultant:</strong> <span id="rcpt_practitioner">None Selected</span></div>

                    <div class="receipt-double-divider"></div>
                    
                    <div style="font-weight: bold; margin-bottom: 6px;" class="receipt-item-line">
                        <span>Description</span>
                        <span class="receipt-val-right">Amount</span>
                    </div>
                    <div class="receipt-divider" style="margin: 4px 0;"></div>
                    
                    <div id="rcpt_ledger_items_container">
                        <div style="color: #555; font-style: italic; text-align: center; padding: 10px 0;">No active services checked</div>
                    </div>

                    <div class="receipt-double-divider"></div>

                    <div class="receipt-row">
                        <span>SUBTOTAL</span>
                        <span id="rcpt_subtotal" class="receipt-val-right">0.00 BDT</span>
                    </div>
                    <div class="receipt-row">
                        <span>DISCOUNT DEDUCTION</span>
                        <span id="rcpt_discount" class="receipt-val-right">0.00 BDT</span>
                    </div>
                    <div class="receipt-divider" style="margin: 4px 0;"></div>
                    <div class="receipt-row" style="font-size: 14px; font-weight: bold;">
                        <span>TOTAL AMOUNT CASHED</span>
                        <span id="rcpt_net_total" class="receipt-val-right">0.00 BDT</span>
                    </div>
                    
                    <div class="receipt-divider"></div>
                    <div class="receipt-row">
                        <span>METHOD OF PAYMENT:</span>
                        <span id="rcpt_pay_method" class="receipt-val-right" style="text-transform: uppercase; font-weight: bold;">CASH</span>
                    </div>
                    
                    <div class="receipt-double-divider"></div>
                    <div class="receipt-center" style="margin-top: 15px; font-style: italic;">
                        Thank you for choosing Rehab Care Network.<br>Get Well Soon.
                    </div>
                </div>

                <button type="button" onclick="window.print();" class="arms-print-action-btn">
                    <svg viewBox="0 0 24 24" style="fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round;"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    <span><?php echo esc_html__('Print Receipt', 'arms-textdomain'); ?></span>
                </button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#rcpt_rand_id').text(Math.floor(100 + Math.random() * 900));

            // Auto-populate consultation fee field when practitioner selection resets
            $('#practitioner_select').on('change', function() {
                const defaultFee = parseFloat($(this).find('option:selected').data('fee')) || 0;
                $('#consultation_fee').val(defaultFee);
            });

            // Re-render receipt ticket updates on any explicit form text mutations
            $('#practitioner_select, #consultation_fee, #patient_name, #patient_phone, #patient_age, #patient_address, #payment_method, #discount_amount, .arms-service-checkbox').on('input change', function() {
                updateOPDLedgerPreview();
            });

            function updateOPDLedgerPreview() {
                const name = $('#patient_name').val().trim() || '---------';
                const phone = $('#patient_phone').val().trim() || '---------';
                const age = $('#patient_age').val().trim() || '--';
                const address = $('#patient_address').val().trim() || '---';
                
                const selectedDocOption = $('#practitioner_select option:selected');
                const docName = selectedDocOption.val() || 'None Selected';
                const activeConsultationPrice = parseFloat($('#consultation_fee').val()) || 0;

                $('#rcpt_patient_name').text(name);
                $('#rcpt_patient_meta').text(age + ' Yrs / ' + phone);
                $('#rcpt_patient_address').text(address);
                $('#rcpt_practitioner').text(docName);

                // Update the hidden data profile price on the checkup line checkbox dynamically
                const consultationBox = $('.arms-service-checkbox[data-id="consultation"]');
                consultationBox.data('price', activeConsultationPrice);
                $('#price-display-consultation').text(activeConsultationPrice > 0 ? activeConsultationPrice + ' BDT' : 'Variable');

                let subtotal = 0;
                let htmlLines = '';

                $('.arms-service-checkbox:checked').each(function() {
                    const itemName = $(this).val();
                    const itemPrice = parseFloat($(this).data('price')) || 0;
                    subtotal += itemPrice;

                    htmlLines += `
                        <div class="receipt-item-line">
                            <span>* ${itemName}</span>
                            <span class="receipt-val-right">${itemPrice.toFixed(2)}</span>
                        </div>
                    `;
                });

                if (htmlLines === '') {
                    htmlLines = '<div style="color: #555; font-style: italic; text-align: center; padding: 10px 0;">No active services checked</div>';
                }

                $('#rcpt_ledger_items_container').html(htmlLines);

                const discount = parseFloat($('#discount_amount').val()) || 0;
                const netTotal = Math.max(0, subtotal - discount);

                $('#rcpt_subtotal').text(subtotal.toFixed(2) + ' BDT');
                $('#rcpt_discount').text(discount.toFixed(2) + ' BDT');
                $('#rcpt_net_total').text(netTotal.toFixed(2) + ' BDT');

                const methodLabel = $('#payment_method option:selected').text();
                $('#rcpt_pay_method').text(methodLabel);
            }
        });
    </script>
    <?php
}