<?php

/**
 * Visual Render Component: Detailed Admission File Sheet
 */
function arms_view_admission_details( $admission_id ) {
    global $wpdb;

    $admission_id = intval( $admission_id );
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_patients   = $wpdb->prefix . 'arms_patients';
    $table_charges    = $wpdb->prefix . 'arms_admission_charges';

    // Pull combined stay log dataset
    $stay = $wpdb->get_row( $wpdb->prepare(
        "SELECT a.*, p.name as patient_name, p.mobile, p.email 
         FROM $table_admissions a 
         LEFT JOIN $table_patients p ON a.patient_id = p.id 
         WHERE a.id = %d", 
        $admission_id
    ) );

    if ( ! $stay ) {
        echo '<div class="notice notice-error"><p>Admission record ledger item not found.</p></div>';
        return;
    }

    // Pull child continuous dynamic itemized billing log data
    $charges_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_charges WHERE admission_id = %d ORDER BY row_index ASC",
        $admission_id
    ) );

    // Normalize pure date string structures into clean, localized presentation formats
    $wp_date_format = get_option( 'date_format' );
    
    $adm_date = ( ! empty( $stay->admission_date ) && $stay->admission_date !== '0000-00-00' && $stay->admission_date !== '1970-01-01' ) 
        ? date_i18n( $wp_date_format, strtotime( $stay->admission_date ) ) 
        : '—';
        
    $is_active_stay = ( empty( $stay->discharge_date ) || $stay->discharge_date === '0000-00-00' || $stay->discharge_date === '1970-01-01' );
    $dis_date = ! $is_active_stay 
        ? date_i18n( $wp_date_format, strtotime( $stay->discharge_date ) ) 
        : '<span class="arms-active-admit" style="background:#fdf2f2; color:#d63638; padding:4px 12px; border-radius:4px; font-weight:700; font-size:12px; border:1px solid #f8b4b4;">Active Stay</span>';

    // Backwards compatibility calculation layer for the billing breakdown display
    $gross_calculated_total = 0;
    if ( ! empty( $charges_rows ) ) {
        foreach ( $charges_rows as $c_row ) {
            $gross_calculated_total += floatval( $c_row->room_rent );
            $gross_calculated_total += floatval( $c_row->nursing_charge );
            $gross_calculated_total += floatval( $c_row->physio_charge );
            $gross_calculated_total += floatval( $c_row->doctor_charge );
            $gross_calculated_total += floatval( $c_row->acupuncture_charge );
            $gross_calculated_total += floatval( $c_row->prp_charge );
        }
    } else {
        $gross_calculated_total = floatval( $stay->final_bill_amount ) + floatval( $stay->advance_payment );
    }
    ?>
    <style>
        .adm-detail-view { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 950px; overflow: hidden; margin-top: 20px; }
        .adm-view-header { background: #f6f7f7; padding: 20px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center; }
        .adm-view-body { padding: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .adm-data-group { margin-bottom: 5px; }
        .adm-label { font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        .adm-val { font-size: 15px; color: #1d2327; font-weight: 500; }
        .adm-full-width { grid-column: span 2; border-top: 1px dashed #e2e8f0; padding-top: 25px; }
        
        /* Continuous Charge Ledger Styling */
        .adm-ledger-table-wrapper { margin-top: 15px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
        .adm-ledger-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .adm-ledger-table th { background: #f8fafc; padding: 10px 12px; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .adm-ledger-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .adm-ledger-table tr:last-child td { border-bottom: none; }
        .adm-summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px; }
        .adm-stat-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; }
        .adm-stat-card h4 { margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; color: #646970; }
        .adm-stat-card .card-price { font-size: 18px; font-weight: 800; }
    </style>

    <div class="adm-detail-view">
        <div class="adm-view-header">
            <h2 style="margin:0; font-size:18px;">Admission Summary: File ADM-<?php echo $admission_id; ?></h2>
            <code style="font-weight:700; background:#e7f6ec; color:#0b692d; padding:4px 10px; border-radius:4px; font-size:12px;">🗃️ <?php echo esc_html($stay->payment_status); ?></code>
        </div>
        
        <div class="adm-view-body">
            <div class="adm-data-group">
                <span class="adm-label">Patient Name</span>
                <div class="adm-val" style="font-size:16px; font-weight:700;"><?php echo esc_html($stay->patient_name); ?></div>
                <span style="font-size:12px; color:#646970;">ID Reference: #<?php echo intval($stay->patient_id); ?></span>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Contact Comms</span>
                <div class="adm-val">📞 <?php echo esc_html($stay->mobile ?: '—'); ?></div>
                <?php if ( ! empty( $stay->email ) ) : ?>
                    <span style="font-size:12px; color:#646970;">✉️ <?php echo esc_html($stay->email); ?></span>
                <?php endif; ?>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Assigned Space Layout</span>
                <div class="adm-val">
                    <span class="arms-badge-room" style="background:#f0f0f1; border:1px solid #ccd0d4; padding:2px 6px; font-size:11px; border-radius:4px; font-weight:700;"><?php echo esc_html($stay->room_type); ?></span>
                    <div style="margin-top:5px; font-size:13px;">
                        <?php echo !empty($stay->room_no) ? '<strong>Room No:</strong> '.esc_html($stay->room_no) : ''; ?>
                        <?php echo !empty($stay->ward_bed_no) ? '<strong>Bed Row:</strong> '.esc_html($stay->ward_bed_no) : ''; ?>
                    </div>
                </div>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Current Processing Balance</span>
                <div class="adm-val" style="font-size:20px; font-weight:800; color:#003376;">
                    ৳<?php echo number_format((float)$stay->final_bill_amount, 2); ?>
                </div>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Check-In Date</span>
                <div class="adm-val">📅 <?php echo $adm_date; ?></div>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Discharge Event Date</span>
                <div class="adm-val"><?php echo $dis_date; ?></div>
            </div>

            <div class="adm-full-width">
                <span class="adm-label">Dynamic Clinical Continuous Charge Ledger Breakdown</span>
                <?php if ( ! empty( $charges_rows ) ) : ?>
                    <div class="adm-ledger-table-wrapper">
                        <table class="adm-ledger-table">
                            <thead>
                                <tr>
                                    <th>Entry Date</th>
                                    <th>Room Rent</th>
                                    <th>Nursing</th>
                                    <th>Physio</th>
                                    <th>Doctor</th>
                                    <th>Acupuncture</th>
                                    <th>PRP Charge</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $charges_rows as $c_row ) : 
                                    $row_subtotal = floatval($c_row->room_rent) + floatval($c_row->nursing_charge) + floatval($c_row->physio_charge) + floatval($c_row->doctor_charge) + floatval($c_row->acupuncture_charge) + floatval($c_row->prp_charge);
                                    
                                    // Fallback for custom log columns that might fall out of bounds
                                    $row_date_string = !empty($c_row->log_date) ? $c_row->log_date : (!empty($c_row->created_at) ? $c_row->created_at : 'now');
                                    ?>
                                    <tr>
                                        <td><strong><?php echo date_i18n( $wp_date_format, strtotime( $row_date_string ) ); ?></strong></td>
                                        <td>৳<?php echo number_format($c_row->room_rent, 2); ?></td>
                                        <td>৳<?php echo number_format($c_row->nursing_charge, 2); ?></td>
                                        <td>৳<?php echo number_format($c_row->physio_charge, 2); ?></td>
                                        <td>৳<?php echo number_format($c_row->doctor_charge, 2); ?></td>
                                        <td>৳<?php echo number_format($c_row->acupuncture_charge, 2); ?></td>
                                        <td>৳<?php echo number_format($c_row->prp_charge, 2); ?></td>
                                        <td><strong>৳<?php echo number_format($row_subtotal, 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div style="background:#f8fafc; border:1px dashed #cbd5e1; padding:15px; border-radius:6px; font-size:13px; color:#64748b; font-style:italic;">
                        No dynamic daily multi-tier charge log rows tracked against this continuous ledger. Gross values were inherited on asset intake migration.
                    </div>
                <?php endif; ?>

                <div class="adm-summary-cards">
                    <div class="adm-stat-card">
                        <h4>Accrued Gross Total</h4>
                        <div class="card-price" style="color:#1e293b;">৳<?php echo number_format($gross_calculated_total, 2); ?></div>
                    </div>
                    <div class="adm-stat-card">
                        <h4>(-) Less Advance Paid</h4>
                        <div class="card-price" style="color:#dc2626;">৳<?php echo number_format((float)$stay->advance_payment, 2); ?></div>
                    </div>
                    <div class="adm-stat-card">
                        <h4>Final Net Balance Log</h4>
                        <div class="card-price" style="color:#003376; background:#eff6ff; padding:2px 6px; border-radius:4px; display:inline-block;">
                            ৳<?php echo number_format((float)$stay->final_bill_amount, 2); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="adm-full-width adm-data-group" style="border-top:none; padding-top:0; margin-top:-5px;">
                <span class="adm-label">Clinical Discharge Summary / Remarks Case Notes</span>
                <div class="adm-val" style="background:#fbfbfc; border:1px solid #e2e8f0; padding:15px; border-radius:6px; line-height:1.6; font-size:13.5px; color:#334155;">
                    <?php echo !empty($stay->discharge_summary) ? nl2br(esc_html($stay->discharge_summary)) : '<i>No clinical discharge notes or comprehensive trajectory logs filed yet for this operational record.</i>'; ?>
                </div>
            </div>
        </div>
    </div>

    <p style="margin-top:20px;">
        <a href="?page=rehab_management_system&tab=admission&sub=all" class="button"><span class="dashicons dashicons-arrow-left-alt" style="margin-top:4px;"></span> Back to Admissions Registry</a>
    </p>
    <?php
}