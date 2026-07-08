<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function arms_physiotherapy_tab() {
    global $wpdb;
    
    $wpdb->show_errors(); 

    $table_physio   = $wpdb->prefix . 'arms_physio_logs';
    $table_patients = $wpdb->prefix . 'arms_patients';

    // Sub-tab engine switcher
    $current_sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';
    $log_id      = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    $base_url = admin_url( 'admin.php?page=rehab_management_system&tab=physiotherapy' );
    $list_url = $base_url . '&sub=list';
    $add_url  = $base_url . '&sub=add';

    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $log_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'arms_delete_physio_' . $log_id ) ) {
            $deleted = $wpdb->delete( $table_physio, array( 'id' => $log_id ), array( '%d' ) );
            if ( $deleted ) {
                $redirect_url = add_query_arg( 'arms_msg', 'deleted', $list_url );
                echo '<script type="text/javascript">window.location.href="' . esc_url_raw($redirect_url) . '";</script>';
                exit;
            }
        } else {
            add_settings_error( 'arms_physio_messages', 'security_error', 'Security Error: Nonce verification failed.', 'error' );
        }
    }

    if ( isset( $_POST['arms_save_physio'] ) && check_admin_referer( 'arms_physio_nonce_action', 'arms_physio_nonce' ) ) {
        
        $patient_id         = isset( $_POST['patient_id'] ) ? intval( $_POST['patient_id'] ) : 0;
        $log_date           = ! empty( $_POST['log_date'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date'] ) ) : current_time('mysql', 0);
        $initial_assessment = isset( $_POST['initial_assessment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['initial_assessment'] ) ) : '';
        $rehab_goals        = isset( $_POST['rehab_goals'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rehab_goals'] ) ) : '';
        $daily_plan         = isset( $_POST['daily_plan'] ) ? sanitize_textarea_field( wp_unslash( $_POST['daily_plan'] ) ) : '';
        
        // Dynamic Repeater Data collection
        $sessions_completed = isset( $_POST['sessions_completed'] ) ? intval( $_POST['sessions_completed'] ) : 0;
        $sessions_remaining = isset( $_POST['sessions_remaining'] ) ? intval( $_POST['sessions_remaining'] ) : 0;
        
        // Advanced Billing Fields Integration (Taka)
        $advance_bill       = isset( $_POST['advance_bill'] ) ? floatval( $_POST['advance_bill'] ) : 0.00;
        $per_session_bill   = isset( $_POST['per_session_bill'] ) ? floatval( $_POST['per_session_bill'] ) : 0.00;
        
        // Capture Repeater Log Items with custom Session Name (Description)
        $repeater_items = array();
        if ( isset($_POST['repeater_items']) && is_array($_POST['repeater_items']) ) {
            foreach ( $_POST['repeater_items'] as $item ) {
                if ( ! empty($item['date']) ) {
                    $repeater_items[] = array(
                        'date'  => sanitize_text_field($item['date']),
                        'name'  => ! empty($item['name']) ? sanitize_text_field($item['name']) : __('General Therapy Session', 'arms-textdomain'),
                        'count' => intval($item['count']),
                        'fee'   => floatval($item['fee'])
                    );
                }
            }
        }
        $progress_notes = isset( $_POST['progress_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['progress_notes'] ) ) : '';

        if ( $patient_id > 0 ) {
            $data_array = array(
                'patient_id'         => $patient_id,
                'log_date'           => $log_date,
                'initial_assessment' => $initial_assessment,
                'rehab_goals'        => $rehab_goals,
                'daily_plan'         => $daily_plan,
                'sessions_completed' => $sessions_completed,
                'sessions_remaining' => $sessions_remaining,
                'advance_bill'       => $advance_bill,
                'per_session_bill'   => $per_session_bill,
                // Saving repeater items directly safely serialized into progress notes
                'progress_notes'     => !empty($repeater_items) ? 'REPEATER_DATA:' . wp_json_encode($repeater_items) . '|||' . $progress_notes : $progress_notes,
            );
            $format_array = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s' );

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $updated = $wpdb->update( $table_physio, $data_array, array( 'id' => $log_id ), $format_array, array( '%d' ) );
                if ( $updated !== false ) {
                    $redirect_url = add_query_arg( 'arms_msg', 'updated', $list_url );
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw($redirect_url) . '";</script>';
                    exit;
                } else {
                    $wpdb->print_error();
                    add_settings_error( 'arms_physio_messages', 'db_error', 'Database processing error updating record.', 'error' );
                }
            } else {
                $data_array['created_at'] = current_time( 'mysql' );
                $format_array[] = '%s';
                
                if ( function_exists('get_current_user_id') ) {
                    $data_array['created_by'] = get_current_user_id();
                    $format_array[] = '%d';
                }

                $inserted = $wpdb->insert( $table_physio, $data_array, $format_array );
                if ( $inserted ) {
                    $redirect_url = add_query_arg( 'arms_msg', 'inserted', $list_url );
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw($redirect_url) . '";</script>';
                    exit;
                } else {
                    add_settings_error( 'arms_physio_messages', 'db_error', 'Database processing error inserting record.', 'error' );
                }
            }
        } else {
            add_settings_error( 'arms_physio_messages', 'validation_error', 'Validation Error: You must link this mapping block to an active patient profile.', 'error' );
        }
    }

    if ( isset( $_GET['arms_msg'] ) ) {
        $msg = sanitize_key( $_GET['arms_msg'] );
        if ( $msg === 'inserted' ) {
            add_settings_error( 'arms_physio_messages', 'inserted', 'New physical rehabilitation log systematically written.', 'updated' );
        } elseif ( $msg === 'updated' ) {
            add_settings_error( 'arms_physio_messages', 'updated', 'Physiotherapy rehabilitation files updated.', 'updated' );
        } elseif ( $msg === 'deleted' ) {
            add_settings_error( 'arms_physio_messages', 'deleted', 'Physiotherapy chart entry dropped successfully.', 'updated' );
        }
    }
    ?>

   

    <div class="arms-physio-container">
        <?php settings_errors( 'arms_physio_messages' ); ?>

        <h2 class="nav-tab-wrapper arms-sub-tab-wrapper">
    <!-- List View -->
    <a href="<?php echo esc_url( $list_url ); ?>" class="nav-tab <?php echo ($current_sub === 'list') ? 'nav-tab-active' : ''; ?>">
        <span class="dashicons dashicons-list-view" style="font-size:17px; vertical-align:middle; margin-right:4px;"></span> 
        <?php _e('Physiotherapy Directory', 'arms-textdomain'); ?>
    </a>
    
    <!-- Add New -->
    <a href="<?php echo esc_url( $add_url ); ?>" class="nav-tab <?php echo ($current_sub === 'add') ? 'nav-tab-active' : ''; ?>">
        <span class="dashicons dashicons-plus-alt" style="font-size:17px; vertical-align:middle; margin-right:4px;"></span>
        <?php _e('New Physiotherapy ', 'arms-textdomain'); ?>
    </a>
    
    <!-- Edit Mode -->
    <?php if ( $current_sub === 'edit' ) : ?>
        <a class="nav-tab nav-tab-active">
            <span class="dashicons dashicons-edit" style="font-size:17px; vertical-align:middle; margin-right:4px;"></span>
            <?php _e('Modify Case File', 'arms-textdomain'); ?>
        </a>
    <?php endif; ?>
    
    <!-- View Mode -->
    <?php if ( $current_sub === 'view' ) : ?>
        <a class="nav-tab nav-tab-active">
            <span class="dashicons dashicons-analytics" style="font-size:17px; vertical-align:middle; margin-right:4px;"></span>
            <?php _e('Case Summary Dashboard', 'arms-textdomain'); ?>
        </a>
    <?php endif; ?>
</h2>

        <!-- =========================================================================
           SUB-VIEW: FORM (ADD & EDIT)
           ========================================================================= -->
        <?php 
        if ( $current_sub === 'add' || $current_sub === 'edit' ) :
            $form_heading = __("Create Physiotherapy Case", 'arms-textdomain');
            $row_data = null;
            $items_array = array();

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_physio WHERE id = %d", $log_id ) );
                $form_heading = __("Edit Case Profiles Matrix #ID: ", 'arms-textdomain') . esc_html($log_id);
                
                // Parse out saved items array block
                if ( $row_data && strpos($row_data->progress_notes, 'REPEATER_DATA:') === 0 ) {
                    $parts = explode('|||', $row_data->progress_notes, 2);
                    $json_str = str_replace('REPEATER_DATA:', '', $parts[0]);
                    $items_array = json_decode($json_str, true);
                    // Reset raw clean progress text values
                    $row_data->progress_notes = isset($parts[1]) ? $parts[1] : '';
                }
            }

            // Fallback default calculation values
            $completed_val = $row_data ? intval($row_data->sessions_completed) : 0;
            $remaining_val = $row_data ? intval($row_data->sessions_remaining) : 0;
            $total_val     = $completed_val + $remaining_val;
            if ($total_val === 0) $total_val = 10; // default contracted sessions base
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3><?php echo esc_html($form_heading); ?></h3>
                    <a href="<?php echo esc_url($list_url); ?>" class="page-title-action" style="margin:0;"><?php _e('Back to Directory', 'arms-textdomain'); ?></a>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'arms_physio_nonce_action', 'arms_physio_nonce' ); ?>
                    
                    <div class="arms-form-grid" style="margin-bottom: 20px;">
                        <div class="arms-form-group fullwidth-col arms-searchable-group" style="position:relative;">
                            <label for="arms_patient_search"><?php _e('Link Patient Profile Account *', 'arms-textdomain'); ?></label>
                            
                            <?php 
                            $patients_list = $wpdb->get_results("SELECT id, name, mobile FROM $table_patients ORDER BY name ASC");
                            $selected_id = ($row_data) ? intval($row_data->patient_id) : 0;
                            $selected_display = '';
                            if ( $selected_id > 0 && ! empty( $patients_list ) ) {
                                foreach ( $patients_list as $pat ) {
                                    if ( intval($pat->id) === $selected_id ) {
                                        $selected_display = esc_html($pat->name . ' (#' . $pat->id . ')');
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="hidden" id="patient_id" name="patient_id" value="<?php echo $selected_id; ?>" required>
                            <input type="text" id="arms_patient_search" class="arms-patient-search-input" 
                                   placeholder="<?php _e('Search and query matching profiles...', 'arms-textdomain'); ?>" 
                                   value="<?php echo $selected_display; ?>" autocomplete="off">
                            
                            <div id="arms_patient_dropdown_list" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; max-height: 200px; overflow-y: auto; z-index: 999; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 4px;">
                                <div class="arms-patient-option-empty" style="padding: 10px 12px; color: #64748b; font-style: italic; display: none;"><?php _e('No matching profiles found...', 'arms-textdomain'); ?></div>
                                <?php 
                                if ( ! empty( $patients_list ) ) {
                                    foreach ( $patients_list as $pat ) {
                                        $clean_name = esc_html($pat->name);
                                        $clean_meta = esc_html('ID: #' . $pat->id . ' | ' . $pat->mobile);
                                        echo '<div class="arms-patient-option" data-id="'.intval($pat->id).'" data-search="'.esc_attr(strtolower($clean_name . ' ' . $pat->id)).'" style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155;">';
                                        echo '<strong>' . $clean_name . '</strong> <span style="font-size:11px; color:#64748b; margin-left:6px;">(' . $clean_meta . ')</span>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- ACCOUNT BILLING CONFIGURATION SECTION PLACED DIRECTLY BELOW PATIENT SELECTION -->
                    <h4 style="margin: 24px 0 12px 0; color:#1e293b; border-bottom:1px dashed #e2e8f0; padding-bottom:6px; font-size:14px;"><span class="dashicons dashicons-money-alt" style="font-size:16px; margin-right:4px;"></span><?php _e('Account Billing Configuration (All Values in Taka)', 'arms-textdomain'); ?></h4>
                    <div class="arms-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom:20px;">
                        <div class="arms-form-group">
                            <label for="advance_bill"><?php _e('Advanced Deposit Collected (৳)', 'arms-textdomain'); ?></label>
                            <div class="currency-prefix-wrapper">
                                <span>৳</span>
                                <input type="number" id="advance_bill" name="advance_bill" min="0" step="0.01" value="<?php echo $row_data ? esc_attr(number_format((float)$row_data->advance_bill, 2, '.', '')) : '0.00'; ?>">
                            </div>
                        </div>
                        <div class="arms-form-group">
                            <label for="per_session_bill"><?php _e('Rate Per Individual Session (৳)', 'arms-textdomain'); ?></label>
                            <div class="currency-prefix-wrapper">
                                <span>৳</span>
                                <input type="number" id="per_session_bill" name="per_session_bill" min="0" step="0.01" value="<?php echo $row_data ? esc_attr(number_format((float)$row_data->per_session_bill, 2, '.', '')) : '0.00'; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Live Auto-Updated micro-ledger summary panel to keep track of live parameters -->
                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 24px; background: #ecfdf5; padding: 16px; border-radius: 8px; border: 1px solid #d1fae5; display: flex; gap: 32px; box-sizing: border-box;">
                        <div>
                            <span style="font-size:11px; text-transform:uppercase; color:#047857; font-weight:700; display:block; letter-spacing:0.05em;"><?php _e('Live Calculated Accrued Cost', 'arms-textdomain'); ?></span>
                            <span id="live_accrued_cost" style="font-size:18px; font-weight:800; color:#065f46; margin-top:2px; display:block;">৳ 0.00</span>
                        </div>
                        <div>
                            <span style="font-size:11px; text-transform:uppercase; color:#047857; font-weight:700; display:block; letter-spacing:0.05em;"><?php _e('Live Ledger Outstanding Due', 'arms-textdomain'); ?></span>
                            <span id="live_balance_due" style="font-size:18px; font-weight:800; color:#991b1b; margin-top:2px; display:block;">৳ 0.00</span>
                        </div>
                    </div>

                    <!-- REPEATER CONTROL INTERACTIVE WIDGET COMPONENT ROW WITH SESSION NAME -->
                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 24px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; box-sizing: border-box;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                            <label style="margin:0; font-size:14px; font-weight:700; color:#0f172a;"><?php _e('Dynamic Therapy Sessions Repeater Ledger', 'arms-textdomain'); ?></label>
                            <button type="button" class="btn-add-item" id="add_repeater_row_trigger">+ Add Session Row</button>
                        </div>
                        <table class="repeater-box-table">
                            <thead>
                                <tr>
                                    <th>Session Occurrence Date</th>
                                    <th>Session Title / Name</th>
                                    <th style="width:120px;">Session Count</th>
                                    <th style="width:180px;">Assigned Session Fee</th>
                                    <th style="width:80px; text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="repeater_entries_tbody_target">
                                <?php if(!empty($items_array)): foreach($items_array as $k => $item): ?>
                                    <tr class="item-session-row">
                                        <td><input type="date" name="repeater_items[<?php echo $k; ?>][date]" class="form-control-input item-date-field" value="<?php echo esc_attr($item['date']); ?>" required></td>
                                        <td><input type="text" name="repeater_items[<?php echo $k; ?>][name]" class="form-control-input item-name-field" placeholder="<?php _e('e.g., Manual Mobilization', 'arms-textdomain'); ?>" value="<?php echo esc_attr(isset($item['name']) ? $item['name'] : ''); ?>" required></td>
                                        <td><input type="number" name="repeater_items[<?php echo $k; ?>][count]" class="form-control-input item-count-field" min="1" value="<?php echo intval($item['count']); ?>" required></td>
                                        <td>
                                            <div class="currency-prefix-wrapper">
                                                <span>৳</span>
                                                <input type="number" name="repeater_items[<?php echo $k; ?>][fee]" class="form-control-input item-fee-field" min="0" step="0.01" value="<?php echo esc_attr($item['fee']); ?>" required>
                                            </div>
                                        </td>
                                        <td style="text-align:center;"><button type="button" class="btn-del-item remove-row-btn">Delete</button></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 style="margin: 24px 0 12px 0; color:#1e293b; border-bottom:1px dashed #e2e8f0; padding-bottom:6px; font-size:14px;"><span class="dashicons dashicons-calendar-alt" style="font-size:16px; margin-right:4px;"></span><?php _e('Scheduling & Therapy Volume Metrics', 'arms-textdomain'); ?></h4>
                    <div class="arms-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom:24px;">
                        <div class="arms-form-group">
                            <label for="log_date"><?php _e('Record Creation Date', 'arms-textdomain'); ?></label>
                            <input type="date" id="log_date" name="log_date" value="<?php echo $row_data ? esc_attr(date('Y-m-d', strtotime($row_data->log_date))) : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="arms-form-group">
                            <label for="total_sessions_allocated"><?php _e('Total Allocated Contracted Sessions', 'arms-textdomain'); ?></label>
                            <input type="number" id="total_sessions_allocated" name="total_sessions_allocated" min="1" value="<?php echo $total_val; ?>" required>
                        </div>
                        <div class="arms-form-group">
                            <label for="sessions_completed"><?php _e('Completed Therapy Sessions (Calculated)', 'arms-textdomain'); ?></label>
                            <input type="number" id="sessions_completed" name="sessions_completed" min="0" value="<?php echo $completed_val; ?>" readonly>
                        </div>
                        <div class="arms-form-group">
                            <label for="sessions_remaining"><?php _e('Remaining Scheduled Sessions (Auto-Updated)', 'arms-textdomain'); ?></label>
                            <input type="number" id="sessions_remaining" name="sessions_remaining" min="0" value="<?php echo $remaining_val; ?>" readonly>
                        </div>
                    </div>

                    <h4 style="margin: 24px 0 12px 0; color:#1e293b; border-bottom:1px dashed #e2e8f0; padding-bottom:6px; font-size:14px;"><span class="dashicons dashicons-testimonial" style="font-size:16px; margin-right:4px;"></span><?php _e('Clinical Evaluation Logs', 'arms-textdomain'); ?></h4>
                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 20px;">
                        <label for="initial_assessment"><?php _e('Initial Physical Assessment Baseline', 'arms-textdomain'); ?></label>
                        <textarea id="initial_assessment" name="initial_assessment" rows="4" placeholder="<?php _e('Log range of motion metrics, functional limits, muscle structural baselines...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->initial_assessment) : ''; ?></textarea>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 20px;">
                        <label for="rehab_goals"><?php _e('Target Rehabilitative Milestones & Goals', 'arms-textdomain'); ?></label>
                        <textarea id="rehab_goals" name="rehab_goals" rows="3" placeholder="<?php _e('Define operational metrics to hit over short and long-term timelines...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->rehab_goals) : ''; ?></textarea>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 20px;">
                        <label for="daily_plan"><?php _e('Daily Structured Treatment Protocols', 'arms-textdomain'); ?></label>
                        <textarea id="daily_plan" name="daily_plan" rows="4" placeholder="<?php _e('Detail routine schedules: Manual mobilizations, modalities configurations, exercises...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->daily_plan) : ''; ?></textarea>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 24px;">
                        <label for="progress_notes"><?php _e('Physiotherapy Session Progress & Adjustments Notes', 'arms-textdomain'); ?></label>
                        <textarea id="progress_notes" name="progress_notes" rows="4" placeholder="<?php _e('Track pain mapping responses, routine deviations, and continuous evaluation updates...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->progress_notes) : ''; ?></textarea>
                    </div>

                    <!-- DYNAMIC DAY-WISE REAL-TIME TABLE REPLACED ACCORDING TO THE USER REQUEST -->
                    <div class="daywise-section">
                        <h4 style="margin:0 0 12px 0; font-size:14px; color:#1e293b;"><?php _e('Live Itemized Session Breakdown Summary (Auto-Updated)', 'arms-textdomain'); ?></h4>
                        <table class="clinical-ledger-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Session Name / Type</th>
                                    <th>Sessions Deliv.</th>
                                    <th>Fee Total</th>
                                </tr>
                            </thead>
                            <tbody id="live_daywise_container_target">
                                <!-- Populated dynamically by javascript -->
                            </tbody>
                        </table>
                    </div>

                    <div style="border-top: 1px solid #edf2f7; padding-top: 20px; text-align:right; margin-top: 20px;">
                        <a href="<?php echo esc_url($list_url); ?>" class="button button-secondary" style="margin-right:8px; height: 38px; line-height: 36px; border-radius:6px; font-weight:500;"><?php _e('Discard Changes', 'arms-textdomain'); ?></a>
                        <button type="submit" name="arms_save_physio" class="arms-submit-btn" style="height:38px;">
                            <span class="dashicons dashicons-disk"></span> <?php _e('Commit Record Matrix', 'arms-textdomain'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var searchInput  = document.getElementById('arms_patient_search');
                var hiddenInput  = document.getElementById('patient_id');
                var dropdownList = document.getElementById('arms_patient_dropdown_list');
                
                if (searchInput && dropdownList) {
                    var options    = Array.from(dropdownList.querySelectorAll('.arms-patient-option'));
                    var emptyState = dropdownList.querySelector('.arms-patient-option-empty');

                    searchInput.addEventListener('focus', function() { dropdownList.style.display = 'block'; });
                    searchInput.addEventListener('input', function() {
                        dropdownList.style.display = 'block';
                        var term = searchInput.value.toLowerCase().trim();
                        var matches = 0;

                        options.forEach(function(opt) {
                            var searchString = opt.getAttribute('data-search') || '';
                            if (searchString.includes(term)) {
                                opt.style.display = 'block';
                                matches++;
                            } else {
                                opt.style.display = 'none';
                            }
                        });
                        if (emptyState) { emptyState.style.display = (matches === 0) ? 'block' : 'none'; }
                    });

                    dropdownList.addEventListener('click', function(e) {
                        var option = e.target.closest('.arms-patient-option');
                        if (option) {
                            var pId = option.getAttribute('data-id');
                            if (pId) {
                                hiddenInput.value = pId;
                                searchInput.value = option.querySelector('strong').textContent + ' (#' + pId + ')';
                                dropdownList.style.display = 'none';
                            }
                        }
                    });

                    document.addEventListener('click', function(e) {
                        if (!searchInput.contains(e.target) && !dropdownList.contains(e.target)) {
                            dropdownList.style.display = 'none';
                        }
                    });
                }

                // Dynamic Javascript Pipeline for handling items calculations + aggregation blocks 
                const tbody = document.getElementById('repeater_entries_tbody_target');
                const addBtn = document.getElementById('add_repeater_row_trigger');
                
                const completedField = document.getElementById('sessions_completed');
                const remainingField = document.getElementById('sessions_remaining');
                const totalAllocatedField = document.getElementById('total_sessions_allocated');
                const daywiseWrapper = document.getElementById('live_daywise_container_target');
                
                const perSessionBillField = document.getElementById('per_session_bill');
                const advanceBillField = document.getElementById('advance_bill');
                const liveAccruedDisplay = document.getElementById('live_accrued_cost');
                const liveBalanceDisplay = document.getElementById('live_balance_due');

                function computeRepeaterMetrics() {
                    const rows = tbody.querySelectorAll('.item-session-row');
                    let totalCompleted = 0;
                    let totalFees = 0;
                    let sessionItems = [];
                    
                    // Live global rate per session
                    const perSessionBill = parseFloat(perSessionBillField.value) || 0;

                    rows.forEach(row => {
                        const dateVal  = row.querySelector('.item-date-field').value;
                        const nameVal  = row.querySelector('.item-name-field').value || 'Therapy Session';
                        const countVal = parseInt(row.querySelector('.item-count-field').value) || 0;
                        const feeField = row.querySelector('.item-fee-field');

                        // USER REQUEST: Auto-calculate and update Assigned Session Fee field (Sessions Count * Rate Per Individual Session)
                        const calculatedRowFee = countVal * perSessionBill;
                        feeField.value = calculatedRowFee.toFixed(2);

                        totalCompleted += countVal;
                        totalFees += calculatedRowFee;
                        if(dateVal) {
                            sessionItems.push({
                                date: dateVal,
                                name: nameVal,
                                count: countVal,
                                fee: calculatedRowFee
                            });
                        }
                    });

                    // Set completed field values instantly
                    completedField.value = totalCompleted;

                    // Compute dynamic remaining calculation logic based on core allocations setup
                    const totalAllocated = parseInt(totalAllocatedField.value) || 0;
                    const remainingSessions = Math.max(0, totalAllocated - totalCompleted);
                    remainingField.value = remainingSessions;

                    // Dynamic live billing calculation pipeline
                    const advanceBillPaid = parseFloat(advanceBillField.value) || 0;
                    const activeAccruedFees = (rows.length > 0) ? totalFees : (totalCompleted * perSessionBill);
                    const outstandingBalance = activeAccruedFees - advanceBillPaid;

                    liveAccruedDisplay.textContent = '৳ ' + activeAccruedFees.toFixed(2);
                    
                    if (outstandingBalance > 0) {
                        liveBalanceDisplay.textContent = '৳ ' + outstandingBalance.toFixed(2) + ' Due';
                        liveBalanceDisplay.style.color = '#b91c1c'; // Red
                    } else if (outstandingBalance < 0) {
                        liveBalanceDisplay.textContent = '৳ ' + Math.abs(outstandingBalance).toFixed(2) + ' Credit';
                        liveBalanceDisplay.style.color = '#047857'; // Green
                    } else {
                        liveBalanceDisplay.textContent = '৳ 0.00 Settled';
                        liveBalanceDisplay.style.color = '#059669'; // Light Green
                    }

                    // Sort chronologically
                    sessionItems.sort((a, b) => new Date(a.date) - new Date(b.date));

                    // Clean and append day-wise session table records
                    daywiseWrapper.innerHTML = '';
                    if(sessionItems.length === 0) {
                        daywiseWrapper.innerHTML = '<tr><td colSpan="4" style="color:#94a3b8; font-style:italic; font-size:13px; text-align:center; padding: 24px;"><?php _e("No sessions logged.", "arms-textdomain"); ?></td></tr>';
                    } else {
                        sessionItems.forEach(item => {
                            let formattedDate = item.date;
                            try {
                                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                                formattedDate = new Date(item.date).toLocaleDateString('en-US', options);
                            } catch(e) {}

                            const rowHtml = `
                                <tr>
                                    <td class="badge-date">${formattedDate}</td>
                                    <td><strong>${item.name}</strong></td>
                                    <td>${item.count} Session(s)</td>
                                    <td class="badge-fee">৳ ${item.fee.toFixed(2)}</td>
                                </tr>
                            `;
                            daywiseWrapper.insertAdjacentHTML('beforeend', rowHtml);
                        });
                    }
                }

                // Trigger listeners for real-time auto-updating matrices
                tbody.addEventListener('input', computeRepeaterMetrics);
                totalAllocatedField.addEventListener('input', computeRepeaterMetrics);
                advanceBillField.addEventListener('input', computeRepeaterMetrics);
                perSessionBillField.addEventListener('input', computeRepeaterMetrics);

                tbody.addEventListener('click', function(e) {
                    if(e.target.classList.contains('remove-row-btn')) {
                        e.target.closest('.item-session-row').remove();
                        computeRepeaterMetrics();
                    }
                });

                addBtn.addEventListener('click', function() {
                    const index = Date.now();
                    const today = new Date().toISOString().split('T')[0];
                    const defaultFee = parseFloat(perSessionBillField.value) || 0;

                    const rowHtml = `
                        <tr class="item-session-row">
                            <td><input type="date" name="repeater_items[${index}][date]" class="form-control-input item-date-field" value="${today}" required></td>
                            <td><input type="text" name="repeater_items[${index}][name]" class="form-control-input item-name-field" placeholder="e.g., Manual Mobilization" value="Therapy Session" required></td>
                            <td><input type="number" name="repeater_items[${index}][count]" class="form-control-input item-count-field" min="1" value="1" required></td>
                            <td>
                                <div class="currency-prefix-wrapper">
                                    <span>৳</span>
                                    <input type="number" name="repeater_items[${index}][fee]" class="form-control-input item-fee-field" min="0" step="0.01" value="${defaultFee}" required>
                                </div>
                            </td>
                            <td style="text-align:center;"><button type="button" class="btn-del-item remove-row-btn">Delete</button></td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', rowHtml);
                    computeRepeaterMetrics();
                });

                // Initial run on render trigger
                computeRepeaterMetrics();
            });
            </script>

        <!-- =========================================================================
           SUB-VIEW: LIST DIRECTORY
           ========================================================================= -->
        <?php elseif ( $current_sub === 'list' ) : 
            $logs = $wpdb->get_results( "
                SELECT ph.*, p.name 
                FROM $table_physio ph 
                LEFT JOIN $table_patients p ON ph.patient_id = p.id 
                ORDER BY ph.log_date DESC, ph.id DESC
            " );
            ?>
            <div class="arms-card-box" style="padding:0; overflow:hidden;">
                <div class="arms-card-header-flex" style="padding: 24px 24px 16px 24px; margin-bottom: 0;">
                    <h3><?php _e('Physiotherapy Dashboard', 'arms-textdomain'); ?></h3>
                    <a href="<?php echo esc_url($add_url); ?>" class="arms-submit-btn">+ <?php _e('Open Intake File', 'arms-textdomain'); ?></a>
                </div>
                
                <table class="wp-list-table widefat fixed striped table-view-list arms-data-table" style="border:none; box-shadow:none;">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 130px; padding-left:24px;"><?php _e('Date', 'arms-textdomain'); ?></th>
                            <th scope="col"><?php _e('Linked Patient Profile', 'arms-textdomain'); ?></th>
                            <th scope="col" style="width: 160px;"><?php _e('Session Delivery', 'arms-textdomain'); ?></th>
                            <th scope="col" style="width: 180px;"><?php _e('Financial Summary', 'arms-textdomain'); ?></th>
                            <th scope="col" style="text-align: right; width: 280px; padding-right:24px;"><?php _e('Actions', 'arms-textdomain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $log ) : 
                            $view_item_url = $base_url . '&sub=view&id=' . $log->id;
                            $edit_item_url = $base_url . '&sub=edit&id=' . $log->id;
                            $del_item_url  = wp_nonce_url( $base_url . '&sub=list&action=delete&id=' . $log->id, 'arms_delete_physio_' . $log->id );
                            
                            // Real-time server side math calculations with Taka localization currency
                            $completed_sessions = intval($log->sessions_completed);
                            $rate_per_session   = floatval($log->per_session_bill);
                            $advance_payment    = floatval($log->advance_bill);
                            
                            // Calculate fees based on repeater rows if available
                            $total_accrued_cost = 0;
                            if ( strpos($log->progress_notes, 'REPEATER_DATA:') === 0 ) {
                                $parts = explode('|||', $log->progress_notes, 2);
                                $json_str = str_replace('REPEATER_DATA:', '', $parts[0]);
                                $repeater_items = json_decode($json_str, true);
                                if (is_array($repeater_items)) {
                                    foreach ($repeater_items as $item) {
                                        $total_accrued_cost += floatval($item['fee']);
                                    }
                                }
                            } else {
                                $total_accrued_cost = $completed_sessions * $rate_per_session;
                            }
                            $financial_balance  = $total_accrued_cost - $advance_payment;
                            ?>
                            <tr>
                                <td style="padding-left:24px; vertical-align: middle;"><strong><?php echo esc_html( date('d-M-Y', strtotime($log->log_date)) ); ?></strong></td>
                                <td style="vertical-align: middle;">
                                    <strong style="color:#0f172a; font-size:14px; <?php echo $log->name ? '' : 'font-weight:400; font-style:italic;'; ?>"><?php echo esc_html($log->name ? $log->name : __('Unknown System Profile', 'arms-textdomain')); ?></strong><br>
                                    <span style="font-size:11px; color:#64748b;">Case Ref Token: #<?php echo intval($log->patient_id); ?></span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span style="font-size:12px; color:#3b82f6; font-weight:600;"><span class="dashicons dashicons-marker" style="font-size:14px; width:14px; height:14px;"></span> Completed: <?php echo $completed_sessions; ?></span><br>
                                    <span style="font-size:11px; color:#64748b;">Plan Backlog: <?php echo intval($log->sessions_remaining); ?></span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span style="font-size:12px; font-weight:600; color:#1e293b;">Accrued: ৳<?php echo number_format($total_accrued_cost, 2); ?></span><br>
                                    <?php if ($financial_balance > 0) : ?>
                                        <span class="badge-pill badge-danger"><?php printf(__('Due: ৳%s', 'arms-textdomain'), number_format($financial_balance, 2)); ?></span>
                                    <?php elseif ($financial_balance < 0) : ?>
                                        <span class="badge-pill badge-success"><?php printf(__('Credit: ৳%s', 'arms-textdomain'), number_format(abs($financial_balance), 2)); ?></span>
                                    <?php else : ?>
                                        <span class="badge-pill badge-warning"><?php _e('Settled', 'arms-textdomain'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; padding-right:24px; vertical-align: middle;">
    <div class="arms-action-btn-group" style="justify-content: flex-end; gap:6px; display:flex;">
        <a href="<?php echo esc_url($view_item_url); ?>" class="arms-action-btn" style="background:#f1f5f9; color:#334155; padding:6px 10px; border-radius:6px; font-size:12px; text-decoration:none;">
            View
        </a>
        
        <a href="<?php echo esc_url($edit_item_url); ?>" class="arms-action-btn btn-edit" style="background:#eff6ff; color:#2563eb; padding:6px 10px; border-radius:6px; font-size:12px; text-decoration:none;">
            Edit
        </a>
        
        <a href="<?php echo esc_url($del_item_url); ?>" class="arms-action-btn btn-delete" style="background:#fef2f2; color:#ef4444; padding:6px 10px; border-radius:6px; font-size:12px; text-decoration:none;" onclick="return confirm('Clinical Warning: Drop case metrics tracking completely?');">
            Delete
        </a>
    </div>
</td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 60px 24px; color: #94a3b8;"><span class="dashicons dashicons-database" style="font-size:32px; width:32px; height:32px; display:block; margin:0 auto 12px auto;"></span><?php _e('No active physiotherapy cases found within database directory.', 'arms-textdomain'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- =========================================================================
           SUB-VIEW: INDIVIDUAL CASE METRICS SUMMARY DASHBOARD
           ========================================================================= -->
        <?php 
        elseif ( $current_sub === 'view' && $log_id > 0 ) :
            $log = $wpdb->get_row( $wpdb->prepare( "
                SELECT ph.*, p.name, p.mobile, p.gender 
                FROM $table_physio ph 
                LEFT JOIN $table_patients p ON ph.patient_id = p.id 
                WHERE ph.id = %d
            ", $log_id ) );

            if ( $log ) :
                // Parse out saved items array block
                $day_wise_summary = array();
                $clean_progress_notes = $log->progress_notes;
                $total_fees_from_repeater = 0;
                $items_array = array();

                if ( strpos($log->progress_notes, 'REPEATER_DATA:') === 0 ) {
                    $parts = explode('|||', $log->progress_notes, 2);
                    $json_str = str_replace('REPEATER_DATA:', '', $parts[0]);
                    $items_array = json_decode($json_str, true);
                    $clean_progress_notes = isset($parts[1]) ? $parts[1] : '';

                    if (is_array($items_array)) {
                        // Chronological sorting of view summary elements
                        usort($items_array, function($a, $b) {
                            return strtotime($a['date']) - strtotime($b['date']);
                        });
                        
                        foreach ($items_array as $item) {
                            $total_fees_from_repeater += floatval($item['fee']);
                        }
                    }
                }

                // Metrics calculations
                $c_sessions   = intval($log->sessions_completed);
                $r_sessions   = intval($log->sessions_remaining);
                $t_sessions   = $c_sessions + $r_sessions;
                
                $advance_bill = floatval($log->advance_bill);
                $accrued_bill = ($total_fees_from_repeater > 0) ? $total_fees_from_repeater : ($c_sessions * floatval($log->per_session_bill));
                $net_balance  = $accrued_bill - $advance_bill;
                ?>
                <div class="financial-summary-grid" style="margin-top:20px;">
                    <div class="financial-card">
                        <span class="fin-label">Total Contracted Sessions Volume</span>
                        <span class="fin-val"><?php echo $t_sessions; ?> Sessions</span>
                    </div>
                    <div class="financial-card accent-paid">
                        <span class="fin-label">Advanced Collected Deposit</span>
                        <span class="fin-val">৳ <?php echo number_format($advance_bill, 2); ?></span>
                    </div>
                    <div class="financial-card">
                        <span class="fin-label">Gross Value of Completed Services</span>
                        <span class="fin-val">৳ <?php echo number_format($accrued_bill, 2); ?></span>
                    </div>
                    <?php if ($net_balance > 0) : ?>
                        <div class="financial-card accent-due">
                            <span class="fin-label">Outstanding Liability Balance</span>
                            <span class="fin-val">৳ <?php echo number_format($net_balance, 2); ?> Due</span>
                        </div>
                    <?php else : ?>
                        <div class="financial-card accent-paid">
                            <span class="fin-label">Surplus Allocation Credit Balance</span>
                            <span class="fin-val">৳ <?php echo number_format(abs($net_balance), 2); ?> Credit</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="arms-card-box" style="margin-top:0;">
                    <div class="arms-card-header-flex">
                        <h3><?php _e('Physiotherapy Rehabilitation Clinical Summary Ledger', 'arms-textdomain'); ?></h3>
                        <div>
                            <a href="<?php echo esc_url($base_url . '&sub=edit&id=' . $log->id); ?>" class="button button-primary" style="margin-right:4px; height: 32px; line-height: 30px;"><span class="dashicons dashicons-edit" style="font-size:16px; margin-top:6px;"></span> Adjust File Matrix</a>
                            <a href="<?php echo esc_url($list_url); ?>" class="button button-secondary" style="height: 32px; line-height: 30px;"><?php _e('Close View', 'arms-textdomain'); ?></a>
                        </div>
                    </div>

                    <table class="form-table arms-view-table" style="margin:0;">
                        <tr>
                            <th scope="row" style="width:220px; font-weight:600; color:#475569;"><?php _e('Linked Patient Profile', 'arms-textdomain'); ?></th>
                            <td><strong style="font-size:15px; color:#0f172a;"><?php echo esc_html($log->name); ?></strong> <span style="color:#64748b; margin-left:8px;">(Ref ID: #<?php echo intval($log->patient_id); ?> | <?php echo esc_html($log->mobile); ?>)</span></td>
                        </tr>
                        <tr>
                            <th scope="row" style="font-weight:600; color:#475569;"><?php _e('Log Event Creation Date', 'arms-textdomain'); ?></th>
                            <td><strong><?php echo esc_html(date('d F Y', strtotime($log->log_date))); ?></strong></td>
                        </tr>
                        <tr>
                            <th scope="row" style="font-weight:600; color:#475569;"><?php _e('Initial Evaluation Baseline', 'arms-textdomain'); ?></th>
                            <td><div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; white-space:pre-wrap;"><?php echo $log->initial_assessment ? esc_html($log->initial_assessment) : '<i>No initial structural assessment logged.</i>'; ?></div></td>
                        </tr>
                        <tr>
                            <th scope="row" style="font-weight:600; color:#475569;"><?php _e('Target Milestones & Goals', 'arms-textdomain'); ?></th>
                            <td><div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; white-space:pre-wrap;"><?php echo $log->rehab_goals ? esc_html($log->rehab_goals) : '<i>No clinical objectives documented.</i>'; ?></div></td>
                        </tr>
                        <tr>
                            <th scope="row" style="font-weight:600; color:#475569;"><?php _e('Daily Operational Plan', 'arms-textdomain'); ?></th>
                            <td><div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; white-space:pre-wrap;"><?php echo $log->daily_plan ? esc_html($log->daily_plan) : '<i>No procedural execution notes attached.</i>'; ?></div></td>
                        </tr>
                        <tr>
                            <th scope="row" style="font-weight:600; color:#475569;"><?php _e('Progress Logs & Adjustments', 'arms-textdomain'); ?></th>
                            <td><div style="background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #e2e8f0; white-space:pre-wrap;"><?php echo $clean_progress_notes ? esc_html($clean_progress_notes) : '<i>No progress observations documented.</i>'; ?></div></td>
                        </tr>
                    </table>

                    <!-- REPLACED BASIC BADGES CONTAINER WITH AN ELEGANT STRUCTURAL CLINICAL SUMMARY TABLE WITH TITLES -->
                    <?php if(!empty($items_array)): ?>
                        <div class="daywise-section" style="margin-top:30px;">
                            <h4 style="margin:0 0 12px 0; font-size:14px; color:#1e293b;"><?php _e('Itemized Session Treatment Ledger Breakdown', 'arms-textdomain'); ?></h4>
                            <table class="clinical-ledger-table">
                                <thead>
                                    <tr>
                                        <th style="width: 150px;">Service Date</th>
                                        <th>Rehabilitation Treatment / Session Name</th>
                                        <th style="width: 120px;">Units Deliv.</th>
                                        <th style="width: 150px; text-align: right;">Total Fee (Taka)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items_array as $item): ?>
                                        <tr>
                                            <td class="badge-date"><?php echo esc_html(date('d M Y', strtotime($item['date']))); ?></td>
                                            <td><strong><?php echo esc_html(!empty($item['name']) ? $item['name'] : __('General Therapy Session', 'arms-textdomain')); ?></strong></td>
                                            <td><?php echo intval($item['count']); ?> Session(s)</td>
                                            <td class="badge-fee" style="text-align: right;">৳ <?php echo number_format(floatval($item['fee']), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}