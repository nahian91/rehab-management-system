<?php
/**
 * ARMS Independent Print Layout Engine Canvas
 */
// Bootstraps minimal core layer of WordPress manually
$wp_load_path = dirname(__FILE__);
while (!file_exists($wp_load_path . '/wp-load.php')) {
    $wp_load_path = dirname($wp_load_path);
    if ($wp_load_path === '/' || strlen($wp_load_path) < 3) {
        exit("System Engine Connection Error.");
    }
}
require_once($wp_load_path . '/wp-load.php');

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die('Unauthorized Security Exception Access Blocked.');
}

global $wpdb;
$admission_id = isset($_GET['admission_id']) ? intval($_GET['admission_id']) : 0;

if($admission_id <= 0) {
    wp_die('Invalid Invoice Identifier Token Sent.');
}

// Data Fetch Processing Map Execution
$table_admissions = $wpdb->prefix . 'arms_admissions';
$table_charges    = $wpdb->prefix . 'arms_admission_charges';
$table_patients   = $wpdb->prefix . 'arms_patients';

$admission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_admissions WHERE id = %d", $admission_id));
if(!$admission) {
    wp_die('Target Record Data Node Block Not Found inside DB Ledger Contexts.');
}

$patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_patients WHERE id = %d", $admission->patient_id));
$charges = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_charges WHERE admission_id = %d ORDER BY row_index ASC", $admission_id));

// Calculation resolutions
$gross_total = 0;
foreach($charges as $c) {
    $gross_total += ($c->room_rent + $c->nursing_charge + $c->physio_charge + $c->doctor_charge + $c->acupuncture_charge + $c->prp_charge);
}
$net_payable = $gross_total - $admission->advance_payment;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Ledger #<?php echo $admission_id; ?> - ARMS Print Canvas</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.4; padding: 20px; background: #fff; margin:0; }
        .invoice-box { max-width: 800px; margin: auto; border: 1px solid #eee; padding: 30px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.02); }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .header-table td { vertical-align: top; }
        .title h2 { margin: 0; color: #1e3a8a; font-size: 26px; text-transform: uppercase; }
        .title p { margin: 4px 0; color: #64748b; font-size: 12px; }
        .meta-details { text-align: right; font-size: 13px; }
        .details-grid { width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .details-grid td { padding: 10px; font-size: 13px; border: 1px solid #e2e8f0; }
        .details-grid strong { color: #0f172a; }
        .charges-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .charges-table th { background: #0f172a; color: #fff; font-size: 11px; text-transform: uppercase; padding: 8px; text-align: right; font-weight: 600; }
        .charges-table th:first-child { text-align: left; }
        .charges-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 12px; text-align: right; }
        .charges-table td:first-child { text-align: left; font-weight: bold; }
        .summary-wrapper { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .summary-wrapper td { text-align: right; padding: 6px; font-size: 13px; }
        .summary-wrapper tr.bold td { font-size: 16px; font-weight: 800; color: #1e3a8a; border-top: 2px solid #1e3a8a; padding-top: 10px; }
        .summary-label { width: 80%; font-weight: bold; }
        .summary-val { width: 20%; font-weight: bold; }
        .clinical-box { margin-top: 25px; padding: 15px; background: #fafafa; border-left: 4px solid #cbd5e1; font-size: 12px; }
        .clinical-box h4 { margin: 0 0 6px 0; text-transform: uppercase; color: #475569; font-size: 11px; letter-spacing: 0.5px; }
        .footer-sig { margin-top: 60px; display: flex; justify-content: space-between; font-size: 13px; border-top: 1px dashed #cbd5e1; padding-top: 15px; }
        @media print {
            body { padding: 0; }
            .invoice-box { border: none; box-shadow: none; padding: 0; }
            .charges-table th { background: #222 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="invoice-box">
    <table class="header-table">
        <tr>
            <td class="title">
                <h2>REHABILITATION CENTRE</h2>
                <p>Clinical Medical Ingress & Ledger Accounting Settlement Form</p>
            </td>
            <td class="meta-details">
                <strong>Invoice Reference ID:</strong> #ARM-<?php echo esc_html($admission_id); ?><br>
                <strong>System Extract Date:</strong> <?php echo date('Y-m-d H:i'); ?><br>
                <strong>Ledger Account Status:</strong> <?php echo esc_html($admission->payment_status); ?>
            </td>
        </tr>
    </table>

    <table class="details-grid">
        <tr>
            <td><strong>Patient Name:</strong></td>
            <td><?php echo esc_html($patient ? $patient->name : 'N/A Data Link'); ?></td>
            <td><strong>Admission Date:</strong></td>
            <td><?php echo esc_html($admission->admission_date); ?></td>
        </tr>
        <tr>
            <td><strong>Contact No:</strong></td>
            <td><?php echo esc_html($patient ? $patient->mobile : 'N/A'); ?></td>
            <td><strong>Discharge Date:</strong></td>
            <td><?php echo esc_html($admission->discharge_date ? $admission->discharge_date : 'Continuous Stay Profile'); ?></td>
        </tr>
        <tr>
            <td><strong>Allocation Map:</strong></td>
            <td colspan="3">
                <?php 
                    echo esc_html($admission->room_type); 
                    if($admission->room_no) echo ' - Room: ' . esc_html($admission->room_no);
                    if($admission->ward_bed_no) echo ' - Bed: ' . esc_html($admission->ward_bed_no);
                ?>
            </td>
        </tr>
    </table>

    <table class="charges-table">
        <thead>
            <tr>
                <th style="width:15%;">Log Date</th>
                <th>Room (৳)</th>
                <th>Nursing (৳)</th>
                <th>Physio (৳)</th>
                <th>Doctor (৳)</th>
                <th>Acup. (৳)</th>
                <th>PRP (৳)</th>
                <th style="width:18%;">Day Total (৳)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($charges as $c): 
                $row_total = ($c->room_rent + $c->nursing_charge + $c->physio_charge + $c->doctor_charge + $c->acupuncture_charge + $c->prp_charge);
            ?>
                <tr>
                    <td><?php echo esc_html($c->charge_date); ?></td>
                    <td><?php echo number_format($c->room_rent, 2); ?></td>
                    <td><?php echo number_format($c->nursing_charge, 2); ?></td>
                    <td><?php echo number_format($c->physio_charge, 2); ?></td>
                    <td><?php echo number_format($c->doctor_charge, 2); ?></td>
                    <td><?php echo number_format($c->acupuncture_charge, 2); ?></td>
                    <td><?php echo number_format($c->prp_charge, 2); ?></td>
                    <td><strong>৳<?php echo number_format($row_total, 2); ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="summary-wrapper">
        <tr>
            <td class="summary-label">Accrued Cumulative Gross Total:</td>
            <td class="summary-val">৳<?php echo number_format($gross_total, 2); ?></td>
        </tr>
        <tr>
            <td class="summary-label">(-) Less Advance Capital Deposited:</td>
            <td class="summary-val" style="color:#dc2626;">৳<?php echo number_format($admission->advance_payment, 2); ?></td>
        </tr>
        <tr class="bold">
            <td class="summary-label">
                <?php echo ($net_payable < 0) ? 'Patient Refund Due (Credit Balance):' : 'Adjusted Net Due Payable Balance:'; ?>
            </td>
            <td class="summary-val">
                ৳<?php echo number_format(abs($net_payable), 2); ?>
            </td>
        </tr>
    </table>

    <?php if(!empty($admission->discharge_summary)): ?>
        <div class="clinical-box">
            <h4>Clinical Discharge & Recovery Summary Log</h4>
            <p><?php echo nl2br(esc_html($admission->discharge_summary)); ?></p>
        </div>
    <?php endif; ?>

    <div class="footer-sig">
        <div>Prepared System Logging Signature: _______________________</div>
        <div>Authorized Administration Verifier: _______________________</div>
    </div>
</div>

<script type="text/javascript">
    window.onload = function() { 
        window.print(); 
    };
</script>
</body>
</html>