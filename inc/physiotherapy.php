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

    // -------------------------------------------------------------------------
    // ACTION HANDLER: DELETE
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // ACTION HANDLER: SAVE (INSERT / UPDATE)
    // -------------------------------------------------------------------------
    if ( isset( $_POST['arms_save_physio'] ) && check_admin_referer( 'arms_physio_nonce_action', 'arms_physio_nonce' ) ) {
        
        $patient_id         = isset( $_POST['patient_id'] ) ? intval( $_POST['patient_id'] ) : 0;
        $log_date           = ! empty( $_POST['log_date'] ) ? sanitize_text_field( wp_unslash( $_POST['log_date'] ) ) : current_time('mysql', 0);
        $initial_assessment = isset( $_POST['initial_assessment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['initial_assessment'] ) ) : '';
        $rehab_goals        = isset( $_POST['rehab_goals'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rehab_goals'] ) ) : '';
        $daily_plan         = isset( $_POST['daily_plan'] ) ? sanitize_textarea_field( wp_unslash( $_POST['daily_plan'] ) ) : '';
        $sessions_completed = isset( $_POST['sessions_completed'] ) ? intval( $_POST['sessions_completed'] ) : 0;
        $sessions_remaining = isset( $_POST['sessions_remaining'] ) ? intval( $_POST['sessions_remaining'] ) : 0;
        $progress_notes     = isset( $_POST['progress_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['progress_notes'] ) ) : '';

        if ( $patient_id > 0 ) {
            $data_array = array(
                'patient_id'         => $patient_id,
                'log_date'           => $log_date,
                'initial_assessment' => $initial_assessment,
                'rehab_goals'        => $rehab_goals,
                'daily_plan'         => $daily_plan,
                'sessions_completed' => $sessions_completed,
                'sessions_remaining' => $sessions_remaining,
                'progress_notes'     => $progress_notes,
            );
            $format_array = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $updated = $wpdb->update( $table_physio, $data_array, array( 'id' => $log_id ), $format_array, array( '%d' ) );
                if ( $updated !== false ) {
                    $redirect_url = add_query_arg( 'arms_msg', 'updated', $list_url );
                    echo '<script type="text/javascript">window.location.href="' . esc_url_raw($redirect_url) . '";</script>';
                    exit;
                } else {
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

    // -------------------------------------------------------------------------
    // NOTICES ROUTER MAP
    // -------------------------------------------------------------------------
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
            <a href="<?php echo esc_url( $list_url ); ?>" class="nav-tab <?php echo ($current_sub === 'list') ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-accessibility" style="font-size:17px; vertical-align:middle; margin-right:4px; margin-top:-2px;"></span> 
                <?php _e('All Physiotherapy Logs', 'arms-textdomain'); ?>
            </a>
            <a href="<?php echo esc_url( $add_url ); ?>" class="nav-tab <?php echo ($current_sub === 'add') ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-plus" style="font-size:17px; vertical-align:middle; margin-right:4px; margin-top:-2px;"></span>
                <?php _e('Add New Chart', 'arms-textdomain'); ?>
            </a>
            <?php if ( $current_sub === 'edit' ) : ?>
                <a class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-edit" style="font-size:17px; vertical-align:middle; margin-right:4px; margin-top:-2px;"></span>
                    <?php _e('Modify Rehab Log', 'arms-textdomain'); ?>
                </a>
            <?php endif; ?>
            <?php if ( $current_sub === 'view' ) : ?>
                <a class="nav-tab nav-tab-active">
                    <span class="dashicons dashicons-visibility" style="font-size:17px; vertical-align:middle; margin-right:4px; margin-top:-2px;"></span>
                    <?php _e('Physiotherapy Case View', 'arms-textdomain'); ?>
                </a>
            <?php endif; ?>
        </h2> 

        <!-- =========================================================================
           SUB-VIEW: FORM (ADD & EDIT)
           ========================================================================= -->
        <?php 
        if ( $current_sub === 'add' || $current_sub === 'edit' ) :
            $form_heading = __("Create Physiotherapy Clinical Treatment Plan", 'arms-textdomain');
            $row_data = null;

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_physio WHERE id = %d", $log_id ) );
                $form_heading = __("Edit Physiotherapy Chart Matrix #ID: ", 'arms-textdomain') . esc_html($log_id);
            }
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3><?php echo esc_html($form_heading); ?></h3>
                    <a href="<?php echo esc_url($list_url); ?>" class="page-title-action" style="margin:0;"><?php _e('Back to Directory', 'arms-textdomain'); ?></a>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'arms_physio_nonce_action', 'arms_physio_nonce' ); ?>
                    
                    <div class="arms-form-grid">
                        <div class="arms-form-group fullwidth-col arms-searchable-group">
                            <label for="arms_patient_search"><?php _e('Target Patient Profile *', 'arms-textdomain'); ?></label>
                            
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
                                   placeholder="<?php _e('Type name or Patient ID here to lock record profile...', 'arms-textdomain'); ?>" 
                                   value="<?php echo $selected_display; ?>" autocomplete="off">
                            
                            <div id="arms_patient_dropdown_list" style="display: none; position: absolute; top: 100%; left: 16px; right: 16px; background: #fff; border: 1px solid #8c8f94; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 999; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 2px;">
                                <div class="arms-patient-option-empty" style="padding: 10px 12px; color: #003376; font-style: italic; display: none;"><?php _e('No matching profiles found...', 'arms-textdomain'); ?></div>
                                <?php 
                                if ( ! empty( $patients_list ) ) {
                                    foreach ( $patients_list as $pat ) {
                                        $clean_name = esc_html($pat->name);
                                        $clean_meta = esc_html('ID: #' . $pat->id . ' | ' . $pat->mobile);
                                        echo '<div class="arms-patient-option" data-id="'.intval($pat->id).'" data-search="'.esc_attr(strtolower($clean_name . ' ' . $pat->id)).'" style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; font-size: 13px; color: #2c3338;">';
                                        echo '<strong>' . $clean_name . '</strong> <span style="font-size:11px; color:#64748b; margin-left:6px;">(' . $clean_meta . ')</span>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="arms-form-grid">
                        <div class="arms-form-group">
                            <label for="log_date"><?php _e('Plan Generation Date', 'arms-textdomain'); ?></label>
                            <input type="date" id="log_date" name="log_date" value="<?php echo $row_data ? esc_attr(date('Y-m-d', strtotime($row_data->log_date))) : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="arms-form-group">
                            <label for="sessions_completed"><?php _e('Completed Therapy Sessions', 'arms-textdomain'); ?></label>
                            <input type="number" id="sessions_completed" name="sessions_completed" min="0" value="<?php echo $row_data ? intval($row_data->sessions_completed) : '0'; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="sessions_remaining"><?php _e('Remaining Charted Sessions', 'arms-textdomain'); ?></label>
                            <input type="number" id="sessions_remaining" name="sessions_remaining" min="0" value="<?php echo $row_data ? intval($row_data->sessions_remaining) : '0'; ?>">
                        </div>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 20px;">
                        <label for="initial_assessment"><?php _e('Initial Physical Assessment Baseline', 'arms-textdomain'); ?></label>
                        <textarea id="initial_assessment" name="initial_assessment" rows="4" placeholder="<?php _e('Log range of motion, muscle strength, dynamic mobility limitations...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->initial_assessment) : ''; ?></textarea>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 20px;">
                        <label for="rehab_goals"><?php _e('Target Rehabilitative Goals (Short/Long Term)', 'arms-textdomain'); ?></label>
                        <textarea id="rehab_goals" name="rehab_goals" rows="3" placeholder="<?php _e('e.g., Achieve independent transfers in 2 weeks, restore full shoulder flexion range...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->rehab_goals) : ''; ?></textarea>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 20px;">
                        <label for="daily_plan"><?php _e('Daily Structured Treatment Plan Protocols', 'arms-textdomain'); ?></label>
                        <textarea id="daily_plan" name="daily_plan" rows="4" placeholder="<?php _e('Detail standard daily routines: Gait exercises, neuromuscular stimulation, manual mobilization steps...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->daily_plan) : ''; ?></textarea>
                    </div>

                    <div class="arms-form-group fullwidth-col" style="margin-bottom: 24px;">
                        <label for="progress_notes"><?php _e('Physiotherapy Clinical Progress Notes', 'arms-textdomain'); ?></label>
                        <textarea id="progress_notes" name="progress_notes" rows="5" placeholder="<?php _e('Document regular adjustments, tolerance to exercise, pain scale responses...', 'arms-textdomain'); ?>"><?php echo $row_data ? esc_textarea($row_data->progress_notes) : ''; ?></textarea>
                    </div>

                    <div style="border-top: 1px solid #f0f0f1; padding-top: 20px;">
                        <button type="submit" name="arms_save_physio" class="arms-submit-btn">
                            <span class="dashicons dashicons-disk"></span> <?php _e('Save Physiotherapy File Block', 'arms-textdomain'); ?>
                        </button>
                        <a href="<?php echo esc_url($list_url); ?>" class="button button-secondary" style="margin-left:8px; height: 36px; line-height: 34px;"><?php _e('Cancel Changes', 'arms-textdomain'); ?></a>
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

                    dropdownList.addEventListener('mouseover', function(e) {
                        var option = e.target.closest('.arms-patient-option');
                        if (option) { option.style.backgroundColor = '#f0f0f1'; }
                    });
                    dropdownList.addEventListener('mouseout', function(e) {
                        var option = e.target.closest('.arms-patient-option');
                        if (option) { option.style.backgroundColor = '#ffffff'; }
                    });

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
            });
            </script>

        <!-- =========================================================================
           SUB-VIEW: LIST DIRECTORY (WP WP_List_Table standard UI feel)
           ========================================================================= -->
        <?php elseif ( $current_sub === 'list' ) : 
            $logs = $wpdb->get_results( "
                SELECT ph.*, p.name 
                FROM $table_physio ph 
                LEFT JOIN $table_patients p ON ph.patient_id = p.id 
                ORDER BY ph.log_date DESC, ph.id DESC
            " );
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3><?php _e('Physiotherapy Patient Tracking Index', 'arms-textdomain'); ?></h3>
                    <a href="<?php echo esc_url($add_url); ?>" class="arms-submit-btn">+ <?php _e('Open New Rehab File', 'arms-textdomain'); ?></a>
                </div>
                
                <table class="wp-list-table widefat fixed striped table-view-list arms-data-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 150px;"><?php _e('Generation Date', 'arms-textdomain'); ?></th>
                            <th scope="col"><?php _e('Patient Profile Reference', 'arms-textdomain'); ?></th>
                            <th scope="col" style="width: 200px;"><?php _e('Dynamic Session Status', 'arms-textdomain'); ?></th>
                            <th scope="col" style="text-align: right; width: 350px;"><?php _e('Clinical Actions', 'arms-textdomain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : foreach ( $logs as $log ) : 
                            $view_item_url = $base_url . '&sub=view&id=' . $log->id;
                            $edit_item_url = $base_url . '&sub=edit&id=' . $log->id;
                            $del_item_url  = wp_nonce_url( $base_url . '&sub=list&action=delete&id=' . $log->id, 'arms_delete_physio_' . $log->id );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( date('d-M-Y', strtotime($log->log_date)) ); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($log->name ? $log->name : __('Unmapped Identity Profile', 'arms-textdomain')); ?></strong><br>
                                    <span style="font-size:11px; color:#64748b;">System ID File: #<?php echo intval($log->patient_id); ?></span>
                                </td>
                                <td>
                                    <span style="font-size:13px; color:#2271b1; font-weight:600;">Completed: <?php echo intval($log->sessions_completed); ?></span><br>
                                    <span style="font-size:11px; color:#b28004; font-weight:600;">Pending: <?php echo intval($log->sessions_remaining); ?></span>
                                </td>
                                <td>
                                    <div class="arms-action-btn-group" style="justify-content: flex-end;">
                                        <a href="<?php echo esc_url($view_item_url); ?>" class="arms-action-btn"><span class="dashicons dashicons-visibility" style="font-size:15px; margin-right:3px;"></span> <?php _e('Review Case', 'arms-textdomain'); ?></a>
                                        <a href="<?php echo esc_url($edit_item_url); ?>" class="arms-action-btn btn-edit"><span class="dashicons dashicons-edit" style="font-size:15px; margin-right:3px;"></span> <?php _e('Modify', 'arms-textdomain'); ?></a>
                                        <a href="<?php echo esc_url($del_item_url); ?>" class="arms-action-btn btn-delete" onclick="return confirm('Clinical Warning: Completely remove this treatment log?');"><span class="dashicons dashicons-trash" style="font-size:15px; margin-right:3px;"></span> <?php _e('Drop', 'arms-textdomain'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 40px; color: #64748b;"><?php _e('No rehabilitation matrix entries mapped inside database records.', 'arms-textdomain'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- =========================================================================
           SUB-VIEW: INDIVIDUAL CASE ASSESSMENT FILE (FULLY RENDERED LAYOUT)
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
                ?>
                <div class="arms-card-box">
                    <div class="arms-card-header-flex">
                        <div>
                            <h3><?php _e('Physiotherapy Treatment Record', 'arms-textdomain'); ?>: <?php echo esc_html($log->name); ?></h3>
                            <p style="margin:4px 0 0 0; font-size:13px; color:#64748b;">Chart Index ID: #<?php echo intval($log->id); ?> &mdash; Assigned Date: <?php echo esc_html(date('d-M-Y', strtotime($log->log_date))); ?></p>
                        </div>
                        <div class="arms-action-btn-group">
                            <a href="<?php echo esc_url($base_url . '&sub=edit&id=' . $log->id); ?>" class="arms-submit-btn" style="background:#f6f7f7; color:#2271b1; border-color:#2271b1;"><span class="dashicons dashicons-edit"></span><?php _e('Edit Chart', 'arms-textdomain'); ?></a>
                            <a href="<?php echo esc_url($list_url); ?>" class="arms-submit-btn" style="background:#475569; border-color:#334155;"><?php _e('Back to Directory', 'arms-textdomain'); ?></a>
                        </div>
                    </div>

                    <div class="arms-view-grid">
                        <div class="arms-view-main">
                            <div class="arms-view-section">
                                <div class="arms-view-section-header">
                                    <span class="dashicons dashicons-clipboard"></span> <?php _e('Initial Physical Assessment Baseline', 'arms-textdomain'); ?>
                                </div>
                                <div class="arms-view-section-body">
                                    <?php echo !empty($log->initial_assessment) ? esc_html($log->initial_assessment) : __('No initial dynamic baseline parameters tracked.', 'arms-textdomain'); ?>
                                </div>
                            </div>

                            <div class="arms-view-section">
                                <div class="arms-view-section-header">
                                    <span class="dashicons dashicons-flag"></span> <?php _e('Target Rehabilitative Goals (Short/Long Term)', 'arms-textdomain'); ?>
                                </div>
                                <div class="arms-view-section-body">
                                    <?php echo !empty($log->rehab_goals) ? esc_html($log->rehab_goals) : __('No clinical execution milestone metrics defined.', 'arms-textdomain'); ?>
                                </div>
                            </div>

                            <div class="arms-view-section">
                                <div class="arms-view-section-header">
                                    <span class="dashicons dashicons-welcome-learn-more"></span> <?php _e('Daily Structured Treatment Plan Protocols', 'arms-textdomain'); ?>
                                </div>
                                <div class="arms-view-section-body">
                                    <?php echo !empty($log->daily_plan) ? esc_html($log->daily_plan) : __('No manual routine orchestration steps mapped.', 'arms-textdomain'); ?>
                                </div>
                            </div>

                            <div class="arms-view-section">
                                <div class="arms-view-section-header">
                                    <span class="dashicons dashicons-analytics"></span> <?php _e('Physiotherapy Clinical Progress Notes', 'arms-textdomain'); ?>
                                </div>
                                <div class="arms-view-section-body" style="border-left: 4px solid #2271b1; background: #fafafa;">
                                    <?php echo !empty($log->progress_notes) ? esc_html($log->progress_notes) : __('No continuous incremental evaluation log submitted.', 'arms-textdomain'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="arms-view-sidebar">
                            <div class="arms-view-section-header" style="border: 1px solid #c3c4c7; border-radius: 4px 4px 0 0; border-bottom:none;">
                                <span class="dashicons dashicons-clock"></span> <?php _e('Session Metrics Counter', 'arms-textdomain'); ?>
                            </div>
                            <div class="session-counter-grid" style="grid-template-columns: 1fr; gap: 0; border: 1px solid #c3c4c7; border-top:none; border-radius: 0 0 4px 4px; padding: 16px; background:#fff; margin-bottom: 20px;">
                                <div class="session-badge-box" style="margin-bottom:12px;">
                                    <div class="counter-display"><?php echo intval($log->sessions_completed); ?></div>
                                    <div class="counter-label"><?php _e('Sessions<br>Completed', 'arms-textdomain'); ?></div>
                                </div>
                                <div class="session-badge-box remaining">
                                    <div class="counter-display"><?php echo intval($log->sessions_remaining); ?></div>
                                    <div class="counter-label"><?php _e('Sessions<br>Remaining', 'arms-textdomain'); ?></div>
                                </div>
                            </div>

                            <div class="arms-view-section">
                                <div class="arms-view-section-header">
                                    <span class="dashicons dashicons-businessperson"></span> <?php _e('Patient Metadata', 'arms-textdomain'); ?>
                                </div>
                                <ul class="arms-meta-list">
                                    <li><strong><?php _e('Full Name:', 'arms-textdomain'); ?></strong> <span><?php echo esc_html($log->name); ?></span></li>
                                    <li><strong><?php _e('Patient ID Reference:', 'arms-textdomain'); ?></strong> <span>#<?php echo intval($log->patient_id); ?></span></li>
                                    <li><strong><?php _e('Contact Meta:', 'arms-textdomain'); ?></strong> <span><?php echo !empty($log->mobile) ? esc_html($log->mobile) : 'N/A'; ?></span></li>
                                    <li><strong><?php _e('System Creation:', 'arms-textdomain'); ?></strong> <span><?php echo isset($log->created_at) ? esc_html(date('d-M-Y H:i', strtotime($log->created_at))) : 'Legacy Log'; ?></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="notice notice-error"><p><?php _e('Error Map Validation: The physiotherapy record block target does not exist inside the server instance.', 'arms-textdomain'); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
?>