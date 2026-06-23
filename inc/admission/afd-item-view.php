<?php

/**
 * Visual Render Component: Detailed Admission File Sheet
 */
function arms_view_admission_details( $admission_id ) {
    global $wpdb;

    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_patients   = $wpdb->prefix . 'arms_patients';

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

    $adm_date = ( $stay->admission_date !== '1970-01-01 00:00:00' ) ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime($stay->admission_date) ) : '—';
    $dis_date = ( empty($stay->discharge_date) || $stay->discharge_date === '0000-00-00 00:00:00' ) ? '<span class="arms-active-admit" style="background:#fdf2f2; color:#d63638; padding:3px 8px; border-radius:4px; font-weight:600;">Active Stay</span>' : date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime($stay->discharge_date) );
    ?>
    <style>
        .adm-detail-view { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 900px; overflow: hidden; }
        .adm-view-header { background: #f6f7f7; padding: 20px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center; }
        .adm-view-body { padding: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .adm-data-group { margin-bottom: 20px; }
        .adm-label { font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        .adm-val { font-size: 15px; color: #1d2327; font-weight: 500; }
        .adm-full-width { grid-column: span 2; border-top: 1px dashed #e2e8f0; padding-top: 20px; } /* FIXED: Invalid HEX code typo fixed here */
    </style>

    <div class="adm-detail-view">
        <div class="adm-view-header">
            <h2 style="margin:0; font-size:18px;">Admission Summary: File ADM-<?php echo $admission_id; ?></h2>
            <code style="font-weight:700; background:#e7f6ec; color:#0b692d; padding:4px 10px; border-radius:4px;"><?php echo esc_html($stay->payment_status); ?></code>
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
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Assigned Space Layout</span>
                <div class="adm-val">
                    <span class="arms-badge-room" style="background:#f0f0f1; border:1px solid #ccd0d4; padding:2px 6px; font-size:11px; border-radius:4px; font-weight:700;"><?php echo esc_html($stay->room_type); ?></span>
                    <div style="margin-top:5px; font-size:13px;">
                        <?php echo !empty($stay->room_no) ? '<strong>Room:</strong> '.esc_html($stay->room_no) : ''; ?>
                        <?php echo !empty($stay->ward_bed_no) ? ' | <strong>Bed:</strong> '.esc_html($stay->ward_bed_no) : ''; ?>
                    </div>
                </div>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Financial Balance Ledger</span>
                <div class="adm-val" style="font-size:18px; font-weight:800; color:#2271b1;">
                    <?php echo number_format((float)$stay->final_bill_amount, 2); ?> $
                </div>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Check-In Date Time</span>
                <div class="adm-val">📅 <?php echo $adm_date; ?></div>
            </div>

            <div class="adm-data-group">
                <span class="adm-label">Discharge Event Date</span>
                <div class="adm-val">📅 <?php echo $dis_date; ?></div>
            </div>

            <div class="adm-full-width adm-data-group">
                <span class="adm-label">Clinical Discharge Summary / Remarks Case Notes</span>
                <div class="adm-val" style="background:#fbfbfc; border:1px solid #e2e8f0; padding:15px; border-radius:6px; line-height:1.5; font-size:13.5px; color:#475569;">
                    <?php echo !empty($stay->discharge_summary) ? nl2br(esc_html($stay->discharge_summary)) : '<i>No clinical discharge notes or remarks filed yet for this record entry.</i>'; ?>
                </div>
            </div>
        </div>
    </div>

    <p style="margin-top:20px;">
        <a href="?page=rehab_management_system&tab=admission&sub=all" class="button"><span class="dashicons dashicons-arrow-left-alt" style="margin-top:4px;"></span> Back to Admissions Registry</a>
    </p>
    <?php
}