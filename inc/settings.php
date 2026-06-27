<?php
/*--------------------------------------------------------------
# 9. Dynamic System Fees Dashboard Matrix (Dynamic Tab View)
--------------------------------------------------------------*/

/**
 * Render and handle the operational fees panels (Staff Fees & General Fees tabs)
 */
function arms_settings_tab() {
    global $wpdb;

    // 1. Authorization Guard Check
    if ( ! current_user_can( 'manage_options' ) && ! arms_has_access( array( 'admin_manager' ) ) ) {
        echo '<div class="notice notice-error"><p>Access Denied: Insufficient operational clearance.</p></div>';
        return;
    }

    // 2. Process Save Operations on POST Requests
    if ( isset( $_POST['arms_save_fees_ledger'] ) ) {
        // Verify Security Nonce Matrix
        if ( ! isset( $_POST['arms_fees_nonce_field'] ) || ! wp_verify_nonce( $_POST['arms_fees_nonce_field'], 'arms_save_fees_action' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>Security verification failed. Please try again.</p></div>';
        } else {
            // Process both sets of inputs simultaneously if they exist in the post array
            
            // Handle Staff Fees Repeater Data Processing
            $raw_repeater = $_POST['arms_fees_repeater'] ?? array();
            $clean_repeater = array();
            if ( is_array( $raw_repeater ) ) {
                foreach ( $raw_repeater as $row ) {
                    if ( empty( $row['staff_id'] ) ) {
                        continue;
                    }
                    $clean_repeater[] = array(
                        'type'     => sanitize_text_field( $row['type'] ?? 'doctor' ),
                        'staff_id' => intval( $row['staff_id'] ),
                        'fee'      => max( 0, floatval( $row['fee'] ?? 0 ) )
                    );
                }
            }
            update_option( 'arms_individual_staff_fees', $clean_repeater );

            // Handle General Fees Repeater Data Processing
            $raw_general = $_POST['arms_general_repeater'] ?? array();
            $clean_general = array();
            if ( is_array( $raw_general ) ) {
                foreach ( $raw_general as $row ) {
                    if ( empty( $row['fee_name'] ) ) {
                        continue;
                    }
                    $clean_general[] = array(
                        'fee_name'   => sanitize_text_field( $row['fee_name'] ),
                        'fee_amount' => max( 0, floatval( $row['fee_amount'] ?? 0 ) )
                    );
                }
            }
            update_option( 'arms_general_bdt_fees', $clean_general );

            echo '<div class="notice notice-success is-dismissible"><p><strong>ARMS Control Desk:</strong> All fee ledgers (Staff Matrix & General BDT Rates) compiled and updated successfully.</p></div>';
        }
    }

    // 3. Load Datasets & Dependencies
    $saved_staff_fees   = get_option( 'arms_individual_staff_fees', array() );
    $saved_general_fees = get_option( 'arms_general_bdt_fees', array() );
    $global_settings    = get_option( 'arms_global_settings', array() );
    $currency           = $global_settings['currency_symbol'] ?? '$';
    
    // Pull complete user profiles for the Staff Dropdowns
    $all_staff = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) ); 
    ?>

    <style>
        .arms-fees-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 1200px; margin-top: 20px; }
        .arms-tab-nav { display: flex; gap: 4px; margin-bottom: -1px; position: relative; z-index: 2; }
        .arms-tab-link { padding: 10px 20px; background: #eaeaea; border: 1px solid #c3c4c7; border-bottom: none; color: #1d2327; text-decoration: none; font-weight: 600; font-size: 14px; border-radius: 4px 4px 0 0; cursor: pointer; transition: all 0.15s ease-in-out; }
        .arms-tab-link:hover { background: #f6f7f7; color: #2271b1; }
        .arms-tab-link.is-active { background: #fff; border-color: #c3c4c7; border-bottom: 1px solid #fff; color: #2271b1; }
        .arms-panel-body { padding: 24px; background: #fff; border: 1px solid #c3c4c7; border-radius: 0 4px 4px 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .arms-tab-content { display: none; }
        .arms-tab-content.is-active { display: block; }
        .arms-panel-title { margin: 0 0 10px 0; padding-bottom: 12px; color: #1d2327; font-size: 18px; font-weight: 600; }
        .arms-repeater-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; margin-top: 15px; }
        .arms-repeater-table th, .arms-repeater-table td { padding: 12px; text-align: left; border-bottom: 1px solid #c3c4c7; font-size: 13px; vertical-align: middle; }
        .arms-repeater-table th { background: #f6f7f7; font-weight: 600; color: #1d2327; }
        .repeater-select, .repeater-input { width: 100%; max-width: 280px; height: 34px; border-radius: 4px; border: 1px solid #8c8f94; padding: 0 8px; font-size: 13px; }
        .fee-wrapper { display: flex; align-items: center; gap: 8px; }
        .btn-remove-row { color: #b32d2e; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 500; }
        .btn-remove-row:hover { color: #d63638; text-decoration: underline; }
        .add-row-container { margin: 15px 0 25px 0; }
        .bdt-badge { background: #e2e8f0; color: #334155; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 12px; border: 1px solid #cbd5e1; }
    </style>

    <div class="arms-fees-wrapper">
        <!-- JS-POWERED TAB NAVIGATION HEADER -->
        <nav class="arms-tab-nav">
            <div data-target="arms-staff-tab" class="arms-tab-link is-active">
                <span class="dashicons dashicons-businessman" style="vertical-align: text-top; margin-right: 4px;"></span> Staff Fees
            </div>
            <div data-target="arms-general-tab" class="arms-tab-link">
                <span class="dashicons dashicons-money-alt" style="vertical-align: text-top; margin-right: 4px;"></span> General Fees (BDT)
            </div>
        </nav>

        <!-- CONTAINER MAIN VIEW PANEL -->
        <div class="arms-panel-body">
            <!-- Normal submission action maps directly back to the active page context seamlessly -->
            <form method="post" action="">
                <?php wp_nonce_field( 'arms_save_fees_action', 'arms_fees_nonce_field' ); ?>

                <!-- VIEW 1: STAFF INDIVIDUAL FEES CONTENT -->
                <div id="arms-staff-tab" class="arms-tab-content is-active">
                    <h3 class="arms-panel-title">Staff Individual Fee Mapping Matrix</h3>
                    <p class="description">Configure specialized standalone billing pricing distributions explicitly for Doctor & Physiotherapy profiles.</p>

                    <table class="arms-repeater-table" id="arms-staff-repeater-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Service Profile Type</th>
                                <th style="width: 40%;">Select Professional</th>
                                <th style="width: 25%;">Custom Session Fee</th>
                                <th style="width: 10%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $staff_index = 0;
                            if ( ! empty( $saved_staff_fees ) ) : 
                                foreach ( $saved_staff_fees as $mapping ) : 
                            ?>
                                <tr>
                                    <td>
                                        <select name="arms_fees_repeater[<?php echo $staff_index; ?>][type]" class="repeater-select">
                                            <option value="doctor" <?php selected( $mapping['type'], 'doctor' ); ?>>Doctor</option>
                                            <option value="physiotherapy" <?php selected( $mapping['type'], 'physiotherapy' ); ?>>Physiotherapy</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="arms_fees_repeater[<?php echo $staff_index; ?>][staff_id]" class="repeater-select" style="max-width: 100%;">
                                            <option value="">-- Choose Staff Member --</option>
                                            <?php foreach ( $all_staff as $staff ) : ?>
                                                <option value="<?php echo intval( $staff->ID ); ?>" <?php selected( $mapping['staff_id'], $staff->ID ); ?>>
                                                    <?php echo esc_html( $staff->display_name ); ?> (#USR-<?php echo intval( $staff->ID ); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="fee-wrapper">
                                            <span><strong><?php echo esc_html( $currency ); ?></strong></span>
                                            <input type="number" step="0.01" name="arms_fees_repeater[<?php echo $staff_index; ?>][fee]" value="<?php echo esc_attr( $mapping['fee'] ); ?>" class="repeater-input" style="width: 140px;" placeholder="0.00" required />
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <a class="btn-remove-row"><span class="dashicons dashicons-trash"></span> Remove</a>
                                    </td>
                                </tr>
                            <?php 
                                $staff_index++;
                                endforeach; 
                            endif; 
                            ?>
                        </tbody>
                    </table>

                    <div class="add-row-container">
                        <button type="button" id="arms-add-staff-row" class="button button-secondary">
                            <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 4px;"></span> Add Professional Mapping
                        </button>
                    </div>
                </div>

                <!-- VIEW 2: GENERAL FEES CONTENT (BDT EXCLUSIVE) -->
                <div id="arms-general-tab" class="arms-tab-content">
                    <h3 class="arms-panel-title">General Center Operating Fees Ledger</h3>
                    <p class="description">Maintain universal utility facility rates, standard admissions, or ancillary clinical parameters directly calculated in BDT.</p>

                    <table class="arms-repeater-table" id="arms-general-repeater-table">
                        <thead>
                            <tr>
                                <th style="width: 55%;">Fee Name / Operational Description</th>
                                <th style="width: 35%;">Standard Base Rate Amount</th>
                                <th style="width: 10%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $general_index = 0;
                            if ( ! empty( $saved_general_fees ) ) : 
                                foreach ( $saved_general_fees as $fee_item ) : 
                            ?>
                                <tr>
                                    <td>
                                        <input type="text" name="arms_general_repeater[<?php echo $general_index; ?>][fee_name]" value="<?php echo esc_attr( $fee_item['fee_name'] ); ?>" class="repeater-input" style="max-width: 90%;" placeholder="e.g., Admission Processing Fee, Nebulization, Equipment Charge" required />
                                    </td>
                                    <td>
                                        <div class="fee-wrapper">
                                            <span class="bdt-badge">BDT</span>
                                            <input type="number" step="0.01" name="arms_general_repeater[<?php echo $general_index; ?>][fee_amount]" value="<?php echo esc_attr( $fee_item['fee_amount'] ); ?>" class="repeater-input" style="width: 150px;" placeholder="0.00" required />
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <a class="btn-remove-row"><span class="dashicons dashicons-trash"></span> Remove</a>
                                    </td>
                                </tr>
                            <?php 
                                $general_index++;
                                endforeach; 
                            endif; 
                            ?>
                        </tbody>
                    </table>

                    <div class="add-row-container">
                        <button type="button" id="arms-add-general-row" class="button button-secondary">
                            <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 4px;"></span> Add New General Fee Row
                        </button>
                    </div>
                </div>

                <!-- SUBMIT BUTTON SECTIONS -->
                <hr style="border:0; border-top:1px solid #f0f0f1; margin: 20px 0;" />
                <p class="submit">
                    <input type="submit" name="arms_save_fees_ledger" id="submit" class="button button-primary button-large" value="Save All Changes" />
                </p>
            </form>
        </div>
    </div>

    <!-- JavaScript Controller Matrix -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        
        // --- Dynamic JS Tab Switching Engine ---
        $('.arms-tab-link').on('click', function() {
            let targetTab = $(this).data('target');
            
            // Adjust Navigation link active state
            $('.arms-tab-link').removeClass('is-active');
            $(this).addClass('is-active');
            
            // Display targeted content segment
            $('.arms-tab-content').removeClass('is-active');
            $('#' + targetTab).addClass('is-active');
        });

        // --- Staff Fees Repeater Script Matrix ---
        let staffIndex = <?php echo isset($staff_index) ? $staff_index : 0; ?>;
        $('#arms-add-staff-row').on('click', function(e) {
            e.preventDefault();
            let html = `
                <tr>
                    <td>
                        <select name="arms_fees_repeater[${staffIndex}][type]" class="repeater-select">
                            <option value="doctor">Doctor</option>
                            <option value="physiotherapy">Physiotherapy</option>
                        </select>
                    </td>
                    <td>
                        <select name="arms_fees_repeater[${staffIndex}][staff_id]" class="repeater-select" style="max-width: 100%;" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ( $all_staff as $staff ) : ?>
                                <option value="<?php echo intval( $staff->ID ); ?>">
                                    <?php echo esc_js( $staff->display_name ); ?> (#USR-<?php echo intval( $staff->ID ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <div class="fee-wrapper">
                            <span><strong><?php echo esc_js( $currency ); ?></strong></span>
                            <input type="number" step="0.01" name="arms_fees_repeater[${staffIndex}][fee]" class="repeater-input" style="width: 140px;" placeholder="0.00" required />
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <a class="btn-remove-row"><span class="dashicons dashicons-trash"></span> Remove</a>
                    </td>
                </tr>`;
            $('#arms-staff-repeater-table tbody').append(html);
            staffIndex++;
        });

        // --- General Fees Repeater Script Matrix ---
        let generalIndex = <?php echo isset($general_index) ? $general_index : 0; ?>;
        $('#arms-add-general-row').on('click', function(e) {
            e.preventDefault();
            let html = `
                <tr>
                    <td>
                        <input type="text" name="arms_general_repeater[${generalIndex}][fee_name]" class="repeater-input" style="max-width: 90%;" placeholder="Fee Name Description" required />
                    </td>
                    <td>
                        <div class="fee-wrapper">
                            <span class="bdt-badge">BDT</span>
                            <input type="number" step="0.01" name="arms_general_repeater[${generalIndex}][fee_amount]" class="repeater-input" style="width: 150px;" placeholder="0.00" required />
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <a class="btn-remove-row"><span class="dashicons dashicons-trash"></span> Remove</a>
                    </td>
                </tr>`;
            $('#arms-general-repeater-table tbody').append(html);
            generalIndex++;
        });

        // Universal Event Delegation for Dynamic Table Rows Removals
        $(document).on('click', '.btn-remove-row', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
        });
    });
    </script>
    <?php
}