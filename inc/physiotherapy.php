<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Physiotherapy Tab - Rehabilitation & Session Tracking Module
 * Database Mapping Target: arms_physio_logs
 */
function arms_physiotherapy_tab() {
    global $wpdb;
    $wpdb->show_errors(); // <--- ADD THIS LINE TEMPORARILY TO SEE ERRORS
    $table_physio   = $wpdb->prefix . 'arms_physio_logs';
    $table_patients = $wpdb->prefix . 'arms_patients';

    // Sub-tab engine switcher
    $current_sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'list';
    $log_id      = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    
    // Internal Routing Base Links
    $base_url = admin_url( 'admin.php?page=rehab_management_system&tab=physiotherapy' );
    $list_url = $base_url . '&sub=list';
    $add_url  = $base_url . '&sub=add';

    /* =========================================================================
       ACTION ROUTER: PURGE REHAB RECORD
       ========================================================================= */
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $log_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'arms_delete_physio_' . $log_id ) ) {
            $deleted = $wpdb->delete( $table_physio, array( 'id' => $log_id ), array( '%d' ) );
            if ( $deleted ) {
                wp_safe_redirect( add_query_arg( 'arms_msg', 'deleted', $list_url ) );
                exit;
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Database processing error dropping file record.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Security Error: Nonce verification failed.</p></div>';
        }
    }

    /* =========================================================================
       POST ENGINE: HANDLING TREATMENTS & PROGRESS PERSISTENCE
       ========================================================================= */
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
                    wp_safe_redirect( add_query_arg( 'arms_msg', 'updated', $list_url ) );
                    exit;
                }
            } else {
                $data_array['created_at'] = current_time( 'mysql' );
                $format_array[] = '%s';
                
                // Add Audit Trail accountability factor matching platform schema structures
                if( function_exists('get_current_user_id') ) {
                    $data_array['created_by'] = get_current_user_id();
                    $format_array[] = '%d';
                }

                $inserted = $wpdb->insert( $table_physio, $data_array, $format_array );
                if ( $inserted ) {
                    wp_safe_redirect( add_query_arg( 'arms_msg', 'inserted', $list_url ) );
                    exit;
                }
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Validation Error: You must link this mapping block to an active patient profile.</p></div>';
        }
    }

    // Process Post-Redirect System Notices
    if ( isset( $_GET['arms_msg'] ) ) {
        $msg = sanitize_key( $_GET['arms_msg'] );
        if ( $msg === 'inserted' ) {
            echo '<div class="notice notice-success is-dismissible"><p>New physical rehabilitation log systematically written.</p></div>';
        } elseif ( $msg === 'updated' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Physiotherapy rehabilitation files updated.</p></div>';
        } elseif ( $msg === 'deleted' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Physiotherapy chart entry dropped successfully.</p></div>';
        }
    }
    ?>

    <style>
        .arms-physio-wrapper { padding: 24px; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; max-width: 1300px; margin: 20px auto; box-sizing: border-box; }
        .arms-physio-wrapper * { box-sizing: border-box; }
        .arms-subnav-bar { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; margin-bottom: 24px; }
        .arms-subnav-link { padding: 10px 20px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease; }
        .arms-subnav-link:hover { color: #0ea5e9; }
        .arms-subnav-link.active { color: #0ea5e9; border-bottom-color: #0ea5e9; }
        .arms-card-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); margin-bottom: 24px; }
        .arms-card-header-flex { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
        .arms-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .arms-form-group { display: flex; flex-direction: column; gap: 6px; position: relative; }
        .arms-form-group.fullwidth-col { grid-column: 1 / -1; }
        .arms-form-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; }
        .arms-form-group input, .arms-form-group select, .arms-form-group textarea { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background-color: #fff; width: 100%; }
        .arms-form-group input:focus, .arms-form-group select:focus, .arms-form-group textarea:focus { border-color: #0ea5e9; outline: none; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.1); }
        
        .arms-searchable-group { background: #fdfdfd; padding: 16px; border: 1px dashed #cbd5e1; border-radius: 8px; margin-bottom: 8px; }
        .arms-patient-search-input { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%2394a3b8" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>'); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px !important; font-weight: 500; }

        .arms-submit-btn { background: #0ea5e9; color: #fff; border: none; padding: 11px 22px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; text-decoration: none; display: inline-block; }
        .arms-submit-btn:hover { background: #0284c7; }
        
        .session-counter-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; max-width: 500px; margin-bottom: 24px; }
        .session-badge-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 8px; display: flex; align-items: center; gap: 16px; }
        .session-badge-box.remaining { background: #fef9c3; border-color: #fef08a; }
        .session-badge-box .counter-display { font-size: 28px; font-weight: 800; color: #166534; line-height: 1; }
        .session-badge-box.remaining .counter-display { color: #854d0e; }
        .session-badge-box .counter-label { font-size: 12px; font-weight: 600; color: #475569; }

        .arms-data-table { width: 100%; border-collapse: collapse; text-align: left; }
        .arms-data-table th { background: #f8fafc; padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        .arms-data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .arms-action-btn-group { display: flex; gap: 4px; justify-content: flex-end; }
        .arms-action-btn { padding: 5px 10px; font-size: 12px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; }
        .btn-view { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn-edit { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-view:hover { background:#e2e8f0; } .btn-edit:hover { background:#e0f2fe; } .btn-delete:hover { background:#fee2e2; }
    </style>

    <div class="arms-physio-wrapper">
        <nav class="arms-subnav-bar">
            <a href="<?php echo esc_url( $list_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'list') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-accessibility" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Rehab Master Directory
            </a>
            <a href="<?php echo esc_url( $add_url ); ?>" class="arms-subnav-link <?php echo ($current_sub === 'add') ? 'active' : ''; ?>">
                <span class="dashicons dashicons-welcome-write-blog" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Draft Treatment Chart
            </a>
            <?php if ( $current_sub === 'edit' ) : ?><a class="arms-subnav-link active">Modify Rehab Log</a><?php endif; ?>
            <?php if ( $current_sub === 'view' ) : ?><a class="arms-subnav-link active">Physiotherapy Case View</a><?php endif; ?>
        </nav>

        <?php 
        /* =========================================================================
           SUB-VIEW: ADD / EDIT PHYSIOTHERAPY CHARTS
           ========================================================================= */
        if ( $current_sub === 'add' || $current_sub === 'edit' ) :
            $form_heading = "Create Physiotherapy Clinical Treatment Plan";
            $row_data = null;

            if ( $current_sub === 'edit' && $log_id > 0 ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_physio WHERE id = %d", $log_id ) );
                $form_heading = "Edit Physiotherapy Chart Matrix #ID: " . esc_html($log_id);
            }
            ?>
            <div class="arms-card-box">
                <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 700; color:#1e293b;"><?php echo esc_html($form_heading); ?></h3>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'arms_physio_nonce_action', 'arms_physio_nonce' ); ?>
                    
                    <div class="arms-form-grid">
                        <div class="arms-form-group fullwidth-col arms-searchable-group">
                            <label for="arms_patient_search" style="display:block; margin-bottom: 6px;">Target Patient Profile *</label>
                            
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
                                   placeholder="Type name or Patient ID here to lock record profile..." 
                                   value="<?php echo $selected_display; ?>" autocomplete="off">
                            
                            <div id="arms_patient_dropdown_list" style="display: none; position: absolute; top: 100%; left: 16px; right: 16px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; max-height: 200px; overflow-y: auto; z-index: 999; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 2px;">
                                <div class="arms-patient-option-empty" style="padding: 10px 12px; color: #64748b; font-style: italic; display: none;">No matching tracking blocks discovered...</div>
                                <?php 
                                if ( ! empty( $patients_list ) ) {
                                    foreach ( $patients_list as $pat ) {
                                        $clean_name = esc_html($pat->name);
                                        $clean_meta = esc_html('ID: #' . $pat->id . ' | ' . $pat->mobile);
                                        echo '<div class="arms-patient-option" data-id="'.intval($pat->id).'" data-search="'.esc_attr(strtolower($clean_name . ' ' . $pat->id)).'" style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #0f172a;">';
                                        echo '<strong>' . $clean_name . '</strong> <span style="font-size:11px; color:#64748b; margin-left:6px;">(' . $clean_meta . ')</span>';
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="arms-form-grid" style="grid-template-columns: 1fr;">
                        <div class="arms-form-group" style="max-width: 300px;">
                            <label for="log_date">Plan Generation Date</label>
                            <input type="date" id="log_date" name="log_date" value="<?php echo $row_data ? esc_attr(date('Y-m-d', strtotime($row_data->log_date))) : date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #0ea5e9; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 20px;">
                        Clinical Treatment Plan Formulation
                    </h4>
                    <div class="arms-form-grid">
                        <div class="arms-form-group fullwidth-col">
                            <label for="initial_assessment">Initial Physical Assessment Baseline</label>
                            <textarea id="initial_assessment" name="initial_assessment" rows="4" placeholder="Log range of motion, muscle strength, dynamic mobility limitations..."><?php echo $row_data ? esc_textarea($row_data->initial_assessment) : ''; ?></textarea>
                        </div>
                        <div class="arms-form-group fullwidth-col">
                            <label for="rehab_goals">Target Rehabilitative Goals (Short/Long Term)</label>
                            <textarea id="rehab_goals" name="rehab_goals" rows="3" placeholder="e.g., Achieve independent transfers in 2 weeks, restore full shoulder flexion range..."><?php echo $row_data ? esc_textarea($row_data->rehab_goals) : ''; ?></textarea>
                        </div>
                        <div class="arms-form-group fullwidth-col">
                            <label for="daily_plan">Daily Structured Treatment Plan Protocols</label>
                            <textarea id="daily_plan" name="daily_plan" rows="4" placeholder="Detail standard daily routines: Gait exercises, neuromuscular stimulation, manual mobilization steps..."><?php echo $row_data ? esc_textarea($row_data->daily_plan) : ''; ?></textarea>
                        </div>
                    </div>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #0ea5e9; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px;">
                        Session Accountability Metrics Tracker
                    </h4>
                    <div class="arms-form-grid">
                        <div class="arms-form-group">
                            <label for="sessions_completed">Completed Therapy Sessions</label>
                            <input type="number" id="sessions_completed" name="sessions_completed" min="0" value="<?php echo $row_data ? intval($row_data->sessions_completed) : '0'; ?>">
                        </div>
                        <div class="arms-form-group">
                            <label for="sessions_remaining">Remaining Charted Sessions</label>
                            <input type="number" id="sessions_remaining" name="sessions_remaining" min="0" value="<?php echo $row_data ? intval($row_data->sessions_remaining) : '0'; ?>">
                        </div>
                    </div>

                    <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #0ea5e9; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px;">
                        Continuous Evaluation Records
                    </h4>
                    <div class="arms-form-grid">
                        <div class="arms-form-group fullwidth-col">
                            <label for="progress_notes">Physiotherapy Clinical Progress Notes</label>
                            <textarea id="progress_notes" name="progress_notes" rows="5" placeholder="Document regular adjustments, tolerance to exercise, pain scale responses..."><?php echo $row_data ? esc_textarea($row_data->progress_notes) : ''; ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="arms_save_physio" class="arms-submit-btn">
                        <span class="dashicons dashicons-disk" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Save Physiotherapy File Block
                    </button>
                    <a href="<?php echo esc_url($list_url); ?>" class="arms-action-btn btn-view" style="padding:11px 18px; margin-left:10px; font-size:13px; font-weight:600; border-radius:6px;">Exit Management Form</a>
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
                        if (option) {
                            option.style.backgroundColor = '#0ea5e9';
                            option.style.color = '#ffffff';
                            var subSpan = option.querySelector('span');
                            if(subSpan) subSpan.style.color = '#e0f2fe';
                        }
                    });
                    dropdownList.addEventListener('mouseout', function(e) {
                        var option = e.target.closest('.arms-patient-option');
                        if (option) {
                            option.style.backgroundColor = '#ffffff';
                            option.style.color = '#0f172a';
                            var subSpan = option.querySelector('span');
                            if(subSpan) subSpan.style.color = '#64748b';
                        }
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
        <?php 
        /* =========================================================================
           SUB-VIEW: ACTIVE LOG ARCHIVE DIRECTORY VIEW
           ========================================================================= */
        elseif ( $current_sub === 'list' ) : 
            $logs = $wpdb->get_results( "
                SELECT ph.*, p.name 
                FROM $table_physio ph 
                LEFT JOIN $table_patients p ON ph.patient_id = p.id 
                ORDER BY ph.log_date DESC, ph.id DESC
            " );
            ?>
            <div class="arms-card-box">
                <div class="arms-card-header-flex">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700; color:#1e293b;">Physiotherapy Patient Tracking Index</h3>
                    <a href="<?php echo esc_url($add_url); ?>" class="arms-submit-btn" style="padding: 8px 16px; font-size:12px;">+ Open New Rehab File</a>
                </div>
                
                <table class="arms-data-table">
                    <thead>
                        <tr>
                            <th>Generation Date</th>
                            <th>Patient Profile Reference</th>
                            <th>Dynamic Session Status</th>
                            <th>Treatment Snapshot Summary</th>
                            <th style="text-align: right;">Clinical Panel Interactions</th>
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
                                    <strong><?php echo esc_html($log->name ? $log->name : 'Unmapped Identity Profile'); ?></strong><br>
                                    <span style="font-size:11px; color:#64748b;">System ID File: #<?php echo intval($log->patient_id); ?></span>
                                </td>
                                <td>
                                    <span style="font-size:12px; color:#15803d; font-weight:700;">Completed: <?php echo intval($log->sessions_completed); ?></span><br>
                                    <span style="font-size:11px; color:#a16207; font-weight:600;">Pending: <?php echo intval($log->sessions_remaining); ?></span>
                                </td>
                                <td>
                                    <div style="font-size:12px; max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <strong>Goal:</strong> <?php echo esc_html($log->rehab_goals ? wp_strip_all_tags($log->rehab_goals) : 'None Mapped'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="arms-action-btn-group">
                                        <a href="<?php echo esc_url($view_item_url); ?>" class="arms-action-btn btn-view">Review Case</a>
                                        <a href="<?php echo esc_url($edit_item_url); ?>" class="arms-action-btn btn-edit">Modify</a>
                                        <a href="<?php echo esc_url($del_item_url); ?>" class="arms-action-btn btn-delete" onclick="return confirm('Clinical Warning Validation: Completely remove this treatment log?');">Drop</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 40px; color: #64748b;">No rehabilitation matrix entries mapped inside database records.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php 
        /* =========================================================================
           SUB-VIEW: INDIVIDUAL CASE ASSESSMENT FILE
           ========================================================================= */
        elseif ( $current_sub === 'view' && $log_id > 0 ) :
            $log = $wpdb->get_row( $wpdb->prepare( "
                SELECT ph.*, p.name 
                FROM $table_physio ph 
                LEFT JOIN $table_patients p ON ph.patient_id = p.id 
                WHERE ph.id = %d
            ", $log_id ) );

            if ( $log ) :
                ?>
                <div class="arms-card-box">
                    <div class="arms-card-header-flex" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
                        <div>
                            <h2 style="margin:0 0 4px 0; font-size:18px; color:#0f172a;">Physiotherapy Treatment Record: <?php echo esc_html($log->name); ?></h2>
                            <p style="margin:0; font-size:12px; color:#64748b;">Chart Index ID: #<?php echo intval($log->id); ?> &mdash; Assigned Date: <?php echo esc_html(date('d-M-Y', strtotime($log->log_date))); ?></p>
                        </div>
                        <a href="<?php echo esc_url($list_url); ?>" class="arms-submit-btn" style="background:#475569;">Back to Directory</a>
                    </div>

                    <h4 style="font-size:11px; text-transform:uppercase; color:#0ea5e9; margin: 24px 0 12px 0; letter-spacing: 0.05em;">Session Accountability Summary</h4>
                    <div class="session-counter-grid">
                        <div class="session-badge-box">
                            <div class="counter-display"><?php echo intval($log->sessions_completed); ?></div>
                            <div class="counter-label">Sessions<br>Completed</div>
                        </div>
                        <div class="session-badge-box remaining">
                            <div class="counter-display"><?php echo intval($log->sessions_remaining); ?></div>
                            <div class="counter-label">Sessions<br>Remaining</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top:20px;">
                        <div>
                            <h4 style="font-size:11px; text-transform:uppercase; color:#0ea5e9; margin-bottom:8px;">Initial Physical Assessment Framework</h4>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; font-size:13px; line-height:1.5; white-space:pre-wrap; color:#334155; min-height:100px;"><?php echo $log->initial_assessment ? esc_html($log->initial_assessment) : '<em>No initial baseline metric declared.</em>'; ?></div>
                        </div>
                        <div>
                            <h4 style="font-size:11px; text-transform:uppercase; color:#0ea5e9; margin-bottom:8px;">Target Recovery Goals</h4>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; font-size:13px; line-height:1.5; white-space:pre-wrap; color:#334155; min-height:100px;"><?php echo $log->rehab_goals ? esc_html($log->rehab_goals) : '<em>No target milestones saved.</em>'; ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <h4 style="font-size:11px; text-transform:uppercase; color:#0ea5e9; margin-bottom:8px;">Daily Strategic Treatment Plan Protocol</h4>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; font-size:13px; line-height:1.5; white-space:pre-wrap; color:#334155;"><?php echo $log->daily_plan ? esc_html($log->daily_plan) : '<em>No strategic treatment operations documented.</em>'; ?></div>
                    </div>

                    <div style="margin-top: 24px;">
                        <h4 style="font-size:11px; text-transform:uppercase; color:#0ea5e9; margin-bottom:8px;">Continuous Evaluation & Clinical Progress Notes</h4>
                        <div style="background:#f0fdfa; border:1px solid #ccfbf1; border-radius:6px; padding:16px; font-size:13px; line-height:1.5; white-space:pre-wrap; color:#115e59; font-weight:500;"><?php echo $log->progress_notes ? esc_html($log->progress_notes) : '<em>No physical progression updates tracked yet.</em>'; ?></div>
                    </div>
                </div>
            <?php else : ?>
                <div class="notice notice-error"><p>Clinical Error: Targeted identifier could not be validated against registry records.</p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}