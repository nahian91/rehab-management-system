<?php
if (!defined('ABSPATH')) exit;

/**
 * Render the Advanced Admission, Bed Allocation, Daily Charges, 
 * Discharge Protocols, and Treatment History Matrix.
 * 
 * @param int $patient_id Optional. Pass a valid ID to switch to editing/auditing mode.
 */
function arms_add_edit_admission_form( $patient_id = 0 ) {
    $patient_id = intval($patient_id);
    $is_edit    = ($patient_id > 0);

    // Default structural schema initialization map
    $admission_data = array(
        'status'            => 'Active Stay',
        'room_type'         => 'Cabin',
        'room_no'           => '',
        'admission_date'    => date('Y-m-d'),
        // Multi-tier billing charges config
        'charge_room_rent'  => 0,
        'charge_doctor'     => 0,
        'charge_nursing'    => 0,
        'charge_physio'     => 0,
        'charge_acupuncture'=> 0,
        'charge_prp'        => 0,
        // Discharge tracking matrices
        'discharge_summary' => '',
        'payment_status'    => 'Unpaid',
        'final_bill_amount' => 0,
    );

    // Place DB hydration scripts here if $is_edit is true
    ?>
    <style>
        .arms-adm-wrapper {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            max-width: 1100px;
            margin: 20px auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        }
        .arms-adm-header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .arms-adm-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 5px 0;
        }
        .arms-adm-header p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
        .arms-status-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .arms-nav-bar {
            display: flex;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 6px;
            gap: 6px;
            margin-bottom: 30px;
        }
        .arms-nav-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 10px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .arms-nav-btn:hover {
            color: #0f172a;
            background: #f1f5f9;
        }
        .arms-nav-btn.active {
            color: #003376;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .arms-pane {
            display: none;
            animation: armsFade 0.2s ease-out;
        }
        .arms-pane.active {
            display: block;
        }
        @keyframes armsFade {
            from { opacity: 0; transform: translateY(3px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .arms-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
        }
        .col-3 { grid-column: span 3; }
        .col-4 { grid-column: span 4; }
        .col-6 { grid-column: span 6; }
        .col-8 { grid-column: span 8; }
        .col-12 { grid-column: span 12; }
        
        .arms-fgroup {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .arms-label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .arms-input, .arms-select, .arms-textarea {
            padding: 10px 14px;
            font-size: 14px;
            color: #0f172a;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            transition: all 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .arms-input:focus, .arms-select:focus, .arms-textarea:focus {
            background: #ffffff;
            border-color: #003376;
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }
        .input-addon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-addon-wrap .addon {
            position: absolute;
            left: 12px;
            font-size: 13px;
            color: #94a3b8;
            font-weight: 600;
        }
        .input-addon-wrap .arms-input {
            padding-left: 28px;
        }
        .section-subtitle {
            grid-column: span 12;
            font-size: 14px;
            font-weight: 700;
            color: #003376;
            margin: 10px 0 0 0;
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 6px;
        }
        .arms-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .arms-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .arms-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .arms-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        .badge-success { background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; }
        .badge-warning { background: #fef9c3; color: #a16207; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }
        .arms-btn {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }
        .btn-primary { background: #003376; color: #ffffff; }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-success { background: #003376; color: #ffffff; }
        .btn-success:hover { background: #15803d; }
    </style>

    <div class="arms-adm-wrapper">
        <div class="arms-adm-header">
            <div>
                <h2><?php echo $is_edit ? 'Manage Admission Ledger' : 'New Patient Admission Intake'; ?></h2>
                <p>Track ward placements, multi-tier daily operational billing, medical charting, and discharge closures.</p>
            </div>
            <div>
                <span class="arms-status-badge">🟢 <?php echo esc_html($admission_data['status']); ?></span>
            </div>
        </div>

        <!-- System Tab Navigation Header -->
        <div class="arms-nav-bar">
            <button type="button" class="arms-nav-btn active" id="tab-btn-alloc" onclick="armsMovePane('pane-alloc')">
                <i class="fa-solid fa-bed"></i> 1. Allocation & Charges
            </button>
            <button type="button" class="arms-nav-btn" id="tab-btn-discharge" onclick="armsMovePane('pane-discharge')">
                <i class="fa-solid fa-door-open"></i> 2. Discharge Protocol
            </button>
            <button type="button" class="arms-nav-btn" id="tab-btn-billing" onclick="armsMovePane('pane-billing')">
                <i class="fa-solid fa-money-check-dollar"></i> 3. Billing History
            </button>
            <button type="button" class="arms-nav-btn" id="tab-btn-treatment" onclick="armsMovePane('pane-treatment')">
                <i class="fa-solid fa-dumbbell"></i> 4. Treatment History
            </button>
        </div>

        <form method="POST" action="">
            <?php wp_nonce_field('arms_admission_security_lock', 'arms_admission_nonce'); ?>
            <input type="hidden" name="patient_id" value="<?php echo esc_attr($patient_id); ?>" />

            <!-- ========================================== -->
            <!-- 🛏️ PANE 1: CABIN ALLOCATION & DAILY CHARGES-->
            <!-- ========================================== -->
            <div id="pane-alloc" class="arms-pane active">
                <div class="arms-grid">
                    <div class="section-subtitle">Bed/Cabin Allocation Infrastructure</div>
                    
                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Room Type Option</label>
                        <select name="room_type" class="arms-select" required>
                            <option value="Cabin" <?php selected($admission_data['room_type'], 'Cabin'); ?>>Cabin (Single Luxury)</option>
                            <option value="Semi-Private" <?php selected($admission_data['room_type'], 'Semi-Private'); ?>>Semi-Private Shared Room</option>
                            <option value="General Ward" <?php selected($admission_data['room_type'], 'General Ward'); ?>>General Medical Ward</option>
                            <option value="ICU Suite" <?php selected($admission_data['room_type'], 'ICU Suite'); ?>>ICU Isolation Suite</option>
                        </select>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Assigned Cabin/Bed Number</label>
                        <input type="text" name="room_no" class="arms-input" placeholder="e.g. Cabin-402, Bed-B" value="<?php echo esc_attr($admission_data['room_no']); ?>" required />
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Admission Clock Date</label>
                        <input type="date" name="admission_date" class="arms-input" value="<?php echo esc_attr($admission_data['admission_date']); ?>" required />
                    </div>

                    <div class="section-subtitle">Daily Charges System Tracker (Per-Day Base Rate Engine)</div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Daily Room Rent</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="charge_room_rent" class="arms-input" min="0" value="<?php echo esc_attr($admission_data['charge_room_rent']); ?>" />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Doctor Consultant Visit Fee</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="charge_doctor" class="arms-input" min="0" value="<?php echo esc_attr($admission_data['charge_doctor']); ?>" />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Nursing Care Support Line</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="charge_nursing" class="arms-input" min="0" value="<?php echo esc_attr($admission_data['charge_nursing']); ?>" />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Physiotherapy Unit Sessions</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="charge_physio" class="arms-input" min="0" value="<?php echo esc_attr($admission_data['charge_physio']); ?>" />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">Acupuncture Clinical Rate</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="charge_acupuncture" class="arms-input" min="0" value="<?php echo esc_attr($admission_data['charge_acupuncture']); ?>" />
                        </div>
                    </div>

                    <div class="arms-fgroup col-4">
                        <label class="arms-label">PRP Therapy Allocation Index</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="charge_prp" class="arms-input" min="0" value="<?php echo esc_attr($admission_data['charge_prp']); ?>" />
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <div></div>
                    <button type="button" class="arms-btn btn-primary" onclick="armsMovePane('pane-discharge')">
                        Proceed to Discharge System <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 🚪 PANE 2: DISCHARGE PROTOCOL MATRIX       -->
            <!-- ========================================== -->
            <div id="pane-discharge" class="arms-pane">
                <div class="arms-grid">
                    <div class="section-subtitle">Clinical Discharge Closure Desk</div>

                    <div class="arms-fgroup col-6">
                        <label class="arms-label">Final Gross Statement Liability</label>
                        <div class="input-addon-wrap">
                            <span class="addon">$</span>
                            <input type="number" name="final_bill_amount" class="arms-input" value="<?php echo esc_attr($admission_data['final_bill_amount']); ?>" />
                        </div>
                    </div>

                    <div class="arms-fgroup col-6">
                        <label class="arms-label">Invoice Payment Status Mapping</label>
                        <select name="payment_status" class="arms-select">
                            <option value="Unpaid" <?php selected($admission_data['payment_status'], 'Unpaid'); ?>>🔴 Unpaid Balance Due</option>
                            <option value="Partially Paid" <?php selected($admission_data['payment_status'], 'Partially Paid'); ?>>🟡 Partially Settled Account</option>
                            <option value="Paid" <?php selected($admission_data['payment_status'], 'Paid'); ?>>🟢 Fully Paid Account Cleared</option>
                        </select>
                    </div>

                    <div class="arms-fgroup col-12">
                        <label class="arms-label">Comprehensive Treatment Summary & Clinical Discharge Report</label>
                        <textarea name="discharge_summary" class="arms-textarea" rows="8" placeholder="Compile recovery trajectories, physical limitations baselines updates, drug instructions, and follow-up clinical calendar protocols cleanly..."><?php echo esc_html($admission_data['discharge_summary']); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="arms-btn btn-secondary" onclick="armsMovePane('pane-alloc')">
                        <i class="fa-solid fa-arrow-left"></i> Back to Bed Allocations
                    </button>
                    <button type="button" class="arms-btn btn-primary" onclick="armsMovePane('pane-billing')">
                        View Auditable Financial Invoices <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 💰 PANE 3: INTEGRATED BILLING HISTORY LOGS -->
            <!-- ========================================== -->
            <div id="pane-billing" class="arms-pane">
                <div class="arms-card" style="display:flex; justify-content: space-around; text-align: center; background:#fff;">
                    <div>
                        <div style="font-size:11px; font-weight:700; color:#64748b; uppercase">Total Gross Accrued</div>
                        <div style="font-size:22px; font-weight:800; color:#0f172a; margin-top:4px;">$4,850.00</div>
                    </div>
                    <div style="border-left: 1px solid #e2e8f0;"></div>
                    <div>
                        <div style="font-size:11px; font-weight:700; color:#64748b; uppercase">Payments Logged</div>
                        <div style="font-size:22px; font-weight:800; color:#003376; margin-top:4px;">$3,200.00</div>
                    </div>
                    <div style="border-left: 1px solid #e2e8f0;"></div>
                    <div>
                        <div style="font-size:11px; font-weight:700; color:#64748b; uppercase">Outstanding Due Balance</div>
                        <div style="font-size:22px; font-weight:800; color:#dc2626; margin-top:4px;">$1,650.00</div>
                    </div>
                </div>

                <div class="arms-card" style="padding:0; overflow:hidden;">
                    <table class="arms-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Statement Date</th>
                                <th>Billing Description Category</th>
                                <th>Gross Amount</th>
                                <th>Collection Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>#INV-9842</strong></td>
                                <td>June 12, 2026</td>
                                <td>Base Accommodation Cabin Rent (7 Days Stay Package)</td>
                                <td>$2,100.00</td>
                                <td><span class="badge-success">Fully Paid</span></td>
                            </tr>
                            <tr>
                                <td><strong>#INV-9901</strong></td>
                                <td>June 19, 2026</td>
                                <td>Clinical Therapy Multi-Tier Sessions (Physio + Acupuncture)</td>
                                <td>$1,100.00</td>
                                <td><span class="badge-success">Fully Paid</span></td>
                            </tr>
                            <tr>
                                <td><strong>#INV-9940</strong></td>
                                <td>June 21, 2026</td>
                                <td>Advanced Regenerative Orthopedics (PRP Treatment Injection Line)</td>
                                <td>$1,650.00</td>
                                <td><span class="badge-warning">Balance Due</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button type="button" class="arms-btn btn-secondary" onclick="armsMovePane('pane-discharge')">
                        <i class="fa-solid fa-arrow-left"></i> Back to Discharge Desk
                    </button>
                    <button type="button" class="arms-btn btn-primary" onclick="armsMovePane('pane-treatment')">
                        Analyze Therapy Progress Logs <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 💪 PANE 4: CLINICAL PROGRESS & TREATMENTS   -->
            <!-- ========================================== -->
            <div id="pane-treatment" class="arms-pane">
                <div class="arms-card" style="padding:0; overflow:hidden;">
                    <table class="arms-table">
                        <thead>
                            <tr>
                                <th>Date Scheduled</th>
                                <th>Modality Category</th>
                                <th>Practitioner Notes & Milestones Achieved</th>
                                <th>Physiological Outcome Tracking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>June 10, 2026</td>
                                <td><strong>Physiotherapy</strong></td>
                                <td>Baseline spinal decompression routines. Patient presented limited movement scope.</td>
                                <td><span class="badge-warning">Initial Assessment</span></td>
                            </tr>
                            <tr>
                                <td>June 14, 2026</td>
                                <td><strong>Acupuncture</strong></td>
                                <td>Neuromuscular point stimulation across lower vertebrae structures to map local response.</td>
                                <td><span class="badge-success">Pain Scale Reduced (8 to 5)</span></td>
                            </tr>
                            <tr>
                                <td>June 18, 2026</td>
                                <td><strong>PRP Therapy</strong></td>
                                <td>Platelet-rich plasma joint execution targeted precisely to the localized chronic damage matrix.</td>
                                <td><span class="badge-success">Inflammation Stabilized</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button type="button" class="arms-btn btn-secondary" onclick="armsMovePane('pane-billing')">
                        <i class="fa-solid fa-arrow-left"></i> Back to Invoices
                    </button>
                    
                    <button type="submit" name="arms_save_admission_action" class="arms-btn btn-success">
                        <i class="fa-solid fa-floppy-disk"></i> <?php echo $is_edit ? 'Commit Records Update' : 'Finalize Admission & Save'; ?>
                    </button>
                </div>
            </div>

        </form>
    </div>

    <script>
    function armsMovePane(paneId) {
        // Hide all layout processing panes safely
        document.querySelectorAll('.arms-pane').forEach(function(pane) {
            pane.classList.remove('active');
        });
        // De-register functional class configurations on tabs
        document.querySelectorAll('.arms-nav-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
        
        // Activate target panel view matrix
        document.getElementById(paneId).classList.add('active');
        
        // Map target key triggers back seamlessly
        if (paneId === 'pane-alloc') document.getElementById('tab-btn-alloc').classList.add('active');
        if (paneId === 'pane-discharge') document.getElementById('tab-btn-discharge').classList.add('active');
        if (paneId === 'pane-billing') document.getElementById('tab-btn-billing').classList.add('active');
        if (paneId === 'pane-treatment') document.getElementById('tab-btn-treatment').classList.add('active');
        
        // Scroll workspace view window frame softly to standard heights
        document.querySelector('.arms-adm-header').scrollIntoView({ behavior: 'smooth' });
    }
    </script>
    <?php
}