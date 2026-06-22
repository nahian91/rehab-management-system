<?php 

function arms_patients_list_table() {
    global $wpdb;
    // Updated to target your real table structure
    $table_name = $wpdb->prefix . 'arms_patients'; 

    // Handle Delete Security and Action Execution
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
        $patient_id_del = intval($_GET['id']);
        
        if (wp_verify_nonce($nonce, 'arms_delete_patient_' . $patient_id_del)) {
            $wpdb->delete($table_name, ['id' => $patient_id_del], ['%d']);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Patient record deleted successfully.', 'arms-textdomain') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Security verification failed. Unable to delete.', 'arms-textdomain') . '</p></div>';
        }
    }

    // Server-Side Processing Setup (Sanitizing Filters)
    $search_query  = isset($_GET['arms_search']) ? sanitize_text_field(trim($_GET['arms_search'])) : '';
    $status_filter = isset($_GET['arms_status']) ? sanitize_text_field(trim($_GET['arms_status'])) : '';
    
    // Server-Side Sorting Setup matched to valid schema columns
    $orderby_allowed = ['id', 'name', 'mobile', 'admission_date', 'status', 'room_no'];
    $orderby         = isset($_GET['orderby']) && in_array($_GET['orderby'], $orderby_allowed) ? $_GET['orderby'] : 'id';
    $order           = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
    
    // Server-Side Pagination Metrics
    $per_page     = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset       = ($current_page - 1) * $per_page;

    // 3. DYNAMIC SQL COMPOSITION
    $where_clauses = [];
    $where_params  = [];

    if (!empty($search_query)) {
        // Updated to search real schema columns
        $where_clauses[] = "(id LIKE %s OR name LIKE %s OR mobile LIKE %s OR initial_diagnosis LIKE %s OR custom_diagnosis LIKE %s)";
        $wildcard = '%' . $wpdb->esc_like($search_query) . '%';
        array_push($where_params, $wildcard, $wildcard, $wildcard, $wildcard, $wildcard);
    }

    if (!empty($status_filter)) {
        $where_clauses[] = "status = %s";
        $where_params[]  = $status_filter;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Count Total Dynamic Records Matching Filter Rules
    $total_query = "SELECT COUNT(*) FROM $table_name $where_sql";
    if (!empty($where_params)) {
        $total_records = $wpdb->get_var($wpdb->prepare($total_query, $where_params));
    } else {
        $total_records = $wpdb->get_var($total_query);
    }
    
    $total_pages = ceil($total_records / $per_page);

    // Fetch Target Subset Data Matching Limits, Sorting, and Filters
    $data_query = "SELECT * FROM $table_name $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
    $query_params = $where_params;
    array_push($query_params, $per_page, $offset);
    
    $patients = $wpdb->get_results($wpdb->prepare($data_query, $query_params), ARRAY_A);

    // Context URL Setup for Sorting Links
    $base_admin_url = admin_url('admin.php?page=rehab_management_system&tab=patients');
    $filter_url_params = '';
    if (!empty($search_query))  $filter_url_params .= '&arms_search=' . urlencode($search_query);
    if (!empty($status_filter)) $filter_url_params .= '&arms_status=' . urlencode($status_filter);

    // Dynamic sorting toggle states
    $toggle_order = ($order === 'ASC') ? 'desc' : 'asc';
    ?>
    <style>
        .arms-container { margin: 20px 20px 0 0; max-width: 100%; }
        .arms-title-wrapper { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .arms-title-left { display: flex; align-items: center; gap: 15px; }
        .arms-title-wrapper h2 { font-size: 23px; font-weight: 400; margin: 0; color: #1d2327; }
        .arms-btn-add-new { display: inline-block; text-decoration: none; padding: 4px 12px; font-size: 13px; font-weight: 600; border: 1px solid #2271b1; border-radius: 3px; color: #2271b1; background: #f6f7f7; cursor: pointer; vertical-align: middle; }
        .arms-btn-add-new:hover { background: #f0f6fa; color: #0a4b78; border-color: #0a4b78; }
        
        .arms-filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }
        .arms-filters-form { display: flex; gap: 10px; align-items: center; width: 100%; }
        
        .arms-input-search, .arms-select-filter { padding: 0 8px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; min-width: 220px; height: 32px; box-sizing: border-box; background-color: #fff; color: #2c3338; }
        .arms-btn-filter { height: 32px; background: #f6f7f7; color: #2271b1; border: 1px solid #2271b1; padding: 0 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .arms-btn-filter:hover { background: #f0f6fa; }
        
        .wp-list-table.arms-patient-table th.sortable a { display: inline-block; padding-right: 15px; position: relative; text-decoration: none; color: #1d2327; }
        .wp-list-table.arms-patient-table th.sorted.asc a::after { content: '▲'; position: absolute; right: 0; font-size: 10px; top: 2px; }
        .wp-list-table.arms-patient-table th.sorted.desc a::after { content: '▼'; position: absolute; right: 0; font-size: 10px; top: 2px; }
        .wp-list-table.arms-patient-table td { vertical-align: middle; }
        
        .arms-status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; display: inline-block; line-height: 1; text-transform: uppercase; letter-spacing: 0.3px; }
        .arms-status-active { background-color: #edfaec; color: #135e1e; border: 1px solid #b8e6b9; }
        .arms-status-review { background-color: #fff8e5; color: #8a5300; border: 1px solid #f6d173; }
        .arms-status-discharged { background-color: #f6f7f7; color: #646970; border: 1px solid #dcdcde; }

        .arms-actions-wrapper { display: flex; gap: 6px; }
        .arms-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 4px; border: 1px solid #8c8f94; background: #fff; cursor: pointer; text-decoration: none; }
        .arms-action-btn svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .arms-btn-view { color: #2271b1; } .arms-btn-view:hover { background: #f0f6fa; border-color: #2271b1; }
        .arms-btn-edit { color: #8a5300; } .arms-btn-edit:hover { background: #fff8e5; border-color: #c58200; }
        .arms-btn-delete { color: #b32d2e; } .arms-btn-delete:hover { background: #fcf0f1; border-color: #b32d2e; }

        .arms-pagination-nav { margin-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .arms-pagination-info { color: #646970; font-size: 13px; }
        .arms-pagination-buttons { display: flex; gap: 5px; }
        .arms-page-link { display: inline-block; padding: 4px 10px; min-width: 15px; text-align: center; background: #f6f7f7; border: 1px solid #8c8f94; border-radius: 3px; text-decoration: none; color: #2271b1; font-size: 13px; }
        .arms-page-link:hover { background: #f0f6fa; border-color: #2271b1; }
        .arms-page-link.arms-active-page { background: #2271b1; border-color: #2271b1; color: #fff; font-weight: 600; cursor: default; }
        .arms-page-link.disabled { color: #a7aaad; background: #f6f7f7; border-color: #dcdcde; cursor: not-allowed; pointer-events: none; }
    </style>

    <div class="arms-container">
        <div class="arms-title-wrapper">
            <div class="arms-title-left">
                <h2><?php echo esc_html__('All Patients List', 'arms-textdomain'); ?></h2>
                <a href="<?php echo esc_url($base_admin_url . '&action=add'); ?>" class="arms-btn-add-new">
                    <?php echo esc_html__('Add New Patient', 'arms-textdomain'); ?>
                </a>
            </div>
        </div>
        
        <div class="arms-filter-bar">
            <form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="arms-filters-form">
                <input type="hidden" name="page" value="rehab_management_system">
                <input type="hidden" name="tab" value="patients">
                
                <input type="text" name="arms_search" class="arms-input-search" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php echo esc_attr__('Search ID, Name, Mobile...', 'arms-textdomain'); ?>">
                
                <select name="arms_status" class="arms-select-filter">
                    <option value=""><?php echo esc_html__('All Statuses', 'arms-textdomain'); ?></option>
                    <option value="Active Stay" <?php selected($status_filter, 'Active Stay'); ?>><?php echo esc_html__('Active Stay', 'arms-textdomain'); ?></option>
                    <option value="Discharged" <?php selected($status_filter, 'Discharged'); ?>><?php echo esc_html__('Discharged', 'arms-textdomain'); ?></option>
                </select>
                
                <button type="submit" class="arms-btn-filter"><?php echo esc_html__('Apply Filter', 'arms-textdomain'); ?></button>
                <?php if (!empty($search_query) || !empty($status_filter)) : ?>
                    <a href="<?php echo esc_url($base_admin_url); ?>" class="arms-page-link"><?php echo esc_html__('Clear', 'arms-textdomain'); ?></a>
                <?php endif; ?>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list arms-patient-table">
            <thead>
                <tr>
                    <th scope="col" class="sortable <?php echo ($orderby === 'id') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 10%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=id&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('ID', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'name') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 18%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=name&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Name', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'mobile') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 15%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=mobile&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Mobile', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" style="width: 12%;"><?php echo esc_html__('Age / Gender', 'arms-textdomain'); ?></th>
                    <th scope="col" style="width: 12%;"><?php echo esc_html__('Room Details', 'arms-textdomain'); ?></th>
                    <th scope="col" style="width: 18%;"><?php echo esc_html__('Diagnosis', 'arms-textdomain'); ?></th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'admission_date') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 15%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=admission_date&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Admission Date', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'status') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 12%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=status&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Status', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" style="width: 110px; text-align: left;"><?php echo esc_html__('Actions', 'arms-textdomain'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($patients)) : ?>
                    <?php foreach ($patients as $patient): 
                        $status_clean = esc_attr($patient['status']);
                        $status_class = 'arms-status-discharged';
                        if ($status_clean === 'Active Stay') $status_class = 'arms-status-active';
                        
                        $action_base_url = admin_url('admin.php?page=rehab_management_system&tab=patients&id=' . urlencode($patient['id']));
                        $delete_nonce = wp_create_nonce('arms_delete_patient_' . $patient['id']);
                    ?>
                        <tr>
                            <td><span class="arms-patient-id" style="font-weight: 600; color: #1d2327;">#<?php echo esc_html($patient['id']); ?></span></td>
                            <td><strong><?php echo esc_html($patient['name']); ?></strong></td>
                            <td><?php echo esc_html($patient['mobile']); ?></td>
                            <td><?php echo esc_html($patient['age'] . ' Yrs / ' . $patient['gender']); ?></td>
                            <td><?php echo esc_html($patient['room_type'] . ' (' . $patient['room_no'] . ')'); ?></td>
                            <td><?php echo esc_html(!empty($patient['custom_diagnosis']) ? $patient['custom_diagnosis'] : wp_trim_words($patient['initial_diagnosis'], 6)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($patient['admission_date']))); ?></td>
                            <td>
                                <span class="arms-status-badge <?php echo $status_class; ?>">
                                    <?php echo esc_html($patient['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="arms-actions-wrapper">
                                    <a href="<?php echo esc_url($action_base_url . '&action=view'); ?>" class="arms-action-btn arms-btn-view" title="<?php echo esc_attr__('View Patient Details', 'arms-textdomain'); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </a>
                                    <a href="<?php echo esc_url($action_base_url . '&action=edit'); ?>" class="arms-action-btn arms-btn-edit" title="<?php echo esc_attr__('Edit Patient Info', 'arms-textdomain'); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <a href="<?php echo esc_url($action_base_url . '&action=delete&_wpnonce=' . $delete_nonce); ?>" class="arms-action-btn arms-btn-delete" title="<?php echo esc_attr__('Delete Patient', 'arms-textdomain'); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to permanently delete this patient record?', 'arms-textdomain')); ?>');">
                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align: center; padding: 30px; color: #646970; font-style: italic;"><?php echo esc_html__('No dynamic data found matching filter requirements.', 'arms-textdomain'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="arms-pagination-nav">
            <div class="arms-pagination-info">
                <?php 
                $start_entry = ($total_records > 0) ? $offset + 1 : 0;
                $end_entry   = min($offset + $per_page, $total_records);
                printf(
                    esc_html__('Showing %1$d to %2$d of %3$d entries', 'arms-textdomain'),
                    $start_entry,
                    $end_entry,
                    $total_records
                );
                ?>
            </div>
            
            <div class="arms-pagination-buttons">
                <a href="<?php echo ($current_page > 1) ? esc_url($base_admin_url . '&paged=' . ($current_page - 1) . '&orderby=' . $orderby . '&order=' . $order . $filter_url_params) : '#'; ?>" class="arms-page-link <?php echo ($current_page === 1) ? 'disabled' : ''; ?>">&laquo;</a>
                
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <a href="<?php echo esc_url($base_admin_url . '&paged=' . $i . '&orderby=' . $orderby . '&order=' . $order . $filter_url_params); ?>" class="arms-page-link <?php echo ($current_page === $i) ? 'arms-active-page' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <a href="<?php echo ($current_page < $total_pages) ? esc_url($base_admin_url . '&paged=' . ($current_page + 1) . '&orderby=' . $orderby . '&order=' . $order . $filter_url_params) : '#'; ?>" class="arms-page-link <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">&raquo;</a>
            </div>
        </div>
    </div>
    <?php
}