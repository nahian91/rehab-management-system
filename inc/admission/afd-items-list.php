<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * =========================================================================
 * INPATIENT ADMISSIONS & ACCOMMODATION LEDGER
 * =========================================================================
 */
function arms_admission_list_table() {
    global $wpdb;
    
    // Explicit Database Table Definitions
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_patients   = $wpdb->prefix . 'arms_patients';

    // Fetch records with an optimization join to grab real patient names
    $results = $wpdb->get_results( 
        "SELECT a.*, p.name as patient_name 
         FROM {$table_admissions} a 
         LEFT JOIN {$table_patients} p ON a.patient_id = p.id 
         ORDER BY a.id DESC", 
        ARRAY_A 
    );
    ?>

    <style>
        :root { 
            --arms-primary: #2271b1; 
            --arms-dark: #1d2327; 
            --arms-border: #ccd0d4; 
            --arms-success: #46b450; 
            --arms-warning: #dba617;
            --arms-bg-soft: #fafafa; 
        }
        .arms-dashboard { 
            margin-top: 20px; 
            max-width: 1300px; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; 
        }
        
        .arms-normal-table { 
            border: 1px solid var(--arms-border); 
            background: #fff; 
            border-radius: 8px; 
            width: 100%; 
            border-collapse: collapse; 
            overflow: hidden; 
            margin-top: 15px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
        }
        .arms-normal-table thead th { 
            background: var(--arms-bg-soft); 
            padding: 15px; 
            text-align: left; 
            font-size: 11px; 
            text-transform: uppercase; 
            color: #50575e; 
            border-bottom: 2px solid #f0f0f1; 
            letter-spacing: 0.5px; 
            font-weight: 700; 
        }
        .arms-normal-table tbody tr:hover { 
            background-color: #f9f9f9; 
        }
        .arms-normal-table td { 
            padding: 15px; 
            vertical-align: middle; 
            border-bottom: 1px solid #f0f0f1; 
            color: #2c3338; 
            font-size: 14px; 
        }
        
        /* Badges Formatting */
        .arms-badge-room { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 700; 
            border: 1px solid #b4b9be; 
            background: #f6f7f7; 
            color: var(--arms-dark); 
            text-transform: uppercase;
        }
        .arms-status-badge { 
            display: inline-block; 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        .arms-status-paid { background: #e7f6ec; color: #0b692d; }
        .arms-status-unpaid { background: #fcf0e3; color: #a45300; }
        .arms-status-partially-paid { background: #f0f6fc; color: #0969da; }
        
        .arms-active-admit {
            background: #fdf2f2;
            color: #d63638;
            border: 1px solid #f5c2c2;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .arms-icon-wrap { 
            width: 40px; 
            height: 40px; 
            background: #f0f0f1; 
            border-radius: 6px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #8c8f94; 
        }

        /* Modern SVG Action Layout System */
        .arms-actions-wrapper {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
            align-items: center;
        }
        .arms-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #ccd0d4;
            background: #fff;
            color: #2c3338;
            transition: all 0.15s ease-in-out;
        }
        .arms-action-btn svg {
            width: 14px;
            height: 14px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        /* Button Style Varieties */
        .arms-btn-view:hover {
            background: #f0f6fc;
            border-color: #0969da;
            color: #0969da;
        }
        .arms-btn-edit:hover {
            background: #f0f6fc;
            border-color: #1a73e8;
            color: #1a73e8;
        }
        .arms-btn-delete:hover {
            background: #fdf2f2;
            border-color: #d63638;
            color: #d63638;
        }
    </style>

    <div class="wrap arms-dashboard">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1 style="margin:0; font-weight: 800; font-size: 24px; color: var(--arms-dark);">Inpatient Admissions Log</h1>
                <p style="color: #646970; margin: 5px 0 0;">Complete registration list of active and past patient accommodations.</p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=rehab_management_system&tab=admission&sub=add'); ?>" class="button button-primary" style="background:var(--arms-primary); border:none; padding: 8px 20px; height: auto; font-weight: 600; border-radius: 6px;">+ New Admission</a>
        </div>

        <table class="arms-normal-table">
            <thead>
                <tr>
                    <th width="50">Log</th>
                    <th width="120">Admission ID</th>
                    <th>Patient Identification</th>
                    <th>Accommodation Assignment</th>
                    <th>Timeline Dates</th>
                    <th width="130">Payment Status</th>
                    <th width="120">Final Bill</th>
                    <th width="320" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $results ) ) : ?>
                    <?php foreach ( $results as $row ) : 
                        $admission_id = intval( $row['id'] );
                        $patient_id   = intval( $row['patient_id'] );
                        $patient_name = ! empty( $row['patient_name'] ) ? esc_html( $row['patient_name'] ) : 'Unknown Patient';
                        
                        // Parse dates beautifully
                        $admission_date = ( $row['admission_date'] !== '1970-01-01 00:00:00' ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['admission_date'] ) ) : '—';
                        
                        if ( empty( $row['discharge_date'] ) || $row['discharge_date'] === '0000-00-00 00:00:00' ) {
                            $discharge_date = '<span class="arms-active-admit">Still Admitted</span>';
                        } else {
                            $discharge_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['discharge_date'] ) );
                        }

                        // Determine standard clean class names matching billing states
                        $payment_status = sanitize_text_field( $row['payment_status'] );
                        $status_class   = 'arms-status-unpaid';
                        if ( strcasecmp( $payment_status, 'Paid' ) === 0 ) {
                            $status_class = 'arms-status-paid';
                        } elseif ( strcasecmp( $payment_status, 'Partial' ) === 0 || strcasecmp( $payment_status, 'Partially Paid' ) === 0 ) {
                            $status_class = 'arms-status-partially-paid';
                        }

                        // FIXED URLs: Built clearly per row to provide individual identifiers to the router matrix
                        $view_route_url = admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=view&id=' . $admission_id );
                        $edit_route_url = admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=edit&patient=' . $patient_id );
                        $delete_nonce   = wp_create_nonce( 'arms_delete_admission_' . $admission_id );
                        ?>
                        <tr>
                            <td>
                                <div class="arms-icon-wrap">
                                    <span class="dashicons dashicons-id-alt"></span>
                                </div>
                            </td>
                            <td>
                                <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 4px; font-weight: 600;">ADM-<?php echo $admission_id; ?></code>
                            </td>
                            <td>
                                <strong style="font-size:15px; color: var(--arms-dark);"><?php echo $patient_name; ?></strong><br>
                                <span style="font-size: 11px; color:#646970;">Patient Reference ID: #<?php echo $patient_id; ?></span>
                            </td>
                            <td>
                                <span class="arms-badge-room"><?php echo esc_html( $row['room_type'] ); ?></span><br>
                                <div style="margin-top: 4px; font-size: 12px; color: #50575e;">
                                    <?php if ( ! empty( $row['room_no'] ) ) : ?>
                                        <strong>Room:</strong> <?php echo esc_html( $row['room_no'] ); ?> 
                                    <?php endif; ?>
                                    <?php if ( ! empty( $row['ward_bed_no'] ) ) : ?>
                                        | <strong>Bed:</strong> <?php echo esc_html( $row['ward_bed_no'] ); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:13px;"><strong>In:</strong> <?php echo $admission_date; ?></span><br>
                                <span style="font-size:13px; display:inline-block; margin-top:3px;"><strong>Out:</strong> <?php echo $discharge_date; ?></span>
                            </td>
                            <td>
                                <span class="arms-status-badge <?php echo $status_class; ?>">
                                    <?php echo esc_html( $payment_status ); ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-size:15px; color: var(--arms-dark);"><?php echo number_format( (float) $row['final_bill_amount'], 2 ); ?> $</strong>
                            </td>
                            <td>
                                <div class="arms-actions-wrapper">
                                    <a href="<?php echo esc_url( $view_route_url ); ?>" class="arms-action-btn arms-btn-view" title="View Admission Summary Details">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <span>View</span>
                                    </a>
                                    
                                    <a href="<?php echo esc_url( $edit_route_url ); ?>" class="arms-action-btn arms-btn-edit" title="Edit Admission Data">
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        <span>Edit</span>
                                    </a>

                                    <a href="<?php echo admin_url( 'admin-post.php?action=arms_delete_admission&id=' . $admission_id . '&_wpnonce=' . $delete_nonce ); ?>" class="arms-action-btn arms-btn-delete" title="Delete Admission Record" onclick="return confirm('Are you sure you want to permanently delete this admission record?');">
                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        <span>Delete</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #646970;">
                            <span class="dashicons dashicons-database" style="font-size: 32px; width: 32px; height: 32px; display: block; margin: 0 auto 10px;"></span>
                            No inpatient admission tracking records found inside the database table.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}