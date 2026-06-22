<?php 

function arms_patients_list_table() {
    global $wpdb;
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
    
    // Server-Side Sorting Setup matched to your valid schema columns
    $orderby_allowed = ['id', 'name', 'mobile', 'admission_date'];
    $orderby         = isset($_GET['orderby']) && in_array($_GET['orderby'], $orderby_allowed) ? $_GET['orderby'] : 'id';
    $order           = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
    
    // Server-Side Pagination Metrics
    $per_page     = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset       = ($current_page - 1) * $per_page;

    // DYNAMIC SQL COMPOSITION
    $where_clauses = [];
    $where_params  = [];

    if (!empty($search_query)) {
        $where_clauses[] = "(id LIKE %s OR name LIKE %s OR mobile LIKE %s)";
        $wildcard = '%' . $wpdb->esc_like($search_query) . '%';
        array_push($where_params, $wildcard, $wildcard, $wildcard);
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

    // Dynamic sorting toggle states
    $toggle_order = ($order === 'ASC') ? 'desc' : 'asc';

    // Include DataTables CSS and JavaScript dynamically from CDNs
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
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
        
        .arms-input-search { padding: 0 8px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px; min-width: 220px; height: 32px; box-sizing: border-box; background-color: #fff; color: #2c3338; }
        .arms-btn-filter { height: 32px; background: #f6f7f7; color: #2271b1; border: 1px solid #2271b1; padding: 0 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .arms-btn-filter:hover { background: #f0f6fa; }
        
        .wp-list-table.arms-patient-table th.sortable a { display: inline-block; padding-right: 15px; position: relative; text-decoration: none; color: #1d2327; }
        .wp-list-table.arms-patient-table th.sorted.asc a::after { content: '▲'; position: absolute; right: 0; font-size: 10px; top: 2px; }
        .wp-list-table.arms-patient-table th.sorted.desc a::after { content: '▼'; position: absolute; right: 0; font-size: 10px; top: 2px; }
        .wp-list-table.arms-patient-table td { vertical-align: middle; }

        .arms-actions-wrapper { display: flex; gap: 8px; align-items: center; }
        .arms-action-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 10px 15px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 4px; 
            border: 1px solid #8c8f94; 
            background: #fff; 
            cursor: pointer; 
            text-decoration: none; 
            line-height: 1;
        }
        .arms-action-btn svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        
        .arms-btn-view { color: #fff;background-color: #003376} 
        .arms-btn-view:hover { background: #2271b1; color: #fff; }
        
        .arms-btn-edit { color: #fff; background-color: #198754; } 
        .arms-btn-edit:hover { background: #8a5300; color: #fff; border-color: #8a5300; }
        
        .arms-btn-delete { color: #fff; background-color: #b32d2e; } 
        .arms-btn-delete:hover { background: #b32d2e; color: #fff; }

        .arms-pagination-nav { margin-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .arms-pagination-info { color: #646970; font-size: 13px; }
        .arms-pagination-buttons { display: flex; gap: 5px; }
        .arms-page-link { display: inline-block; padding: 4px 10px; min-width: 15px; text-align: center; background: #f6f7f7; border: 1px solid #8c8f94; border-radius: 3px; text-decoration: none; color: #2271b1; font-size: 13px; }
        .arms-page-link:hover { background: #f0f6fa; border-color: #2271b1; }
        .arms-page-link.arms-active-page { background: #2271b1; border-color: #2271b1; color: #fff; font-weight: 600; cursor: default; }
        .arms-page-link.disabled { color: #a7aaad; background: #f6f7f7; border-color: #dcdcde; cursor: not-allowed; pointer-events: none; }

        .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_paginate, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_info {
            display: none !important; 
        }
        table.dataTable {
            border-collapse: collapse !important;
        }
    </style>

    <div class="arms-container">
        
        <div class="arms-filter-bar">
            <form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="arms-filters-form">
                <input type="hidden" name="page" value="rehab_management_system">
                <input type="hidden" name="tab" value="patients">
                
                <input type="text" name="arms_search" class="arms-input-search" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php echo esc_attr__('Search ID, Name, Mobile...', 'arms-textdomain'); ?>">
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list arms-patient-table">
            <thead>
                <tr>
                    <th scope="col" class="sortable <?php echo ($orderby === 'id') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 15%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=id&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Patient ID', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'name') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 20%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=name&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Name', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'mobile') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 15%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=mobile&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Mobile', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" class="sortable <?php echo ($orderby === 'admission_date') ? 'sorted ' . strtolower($order) : ''; ?>" style="width: 25%;">
                        <a href="<?php echo esc_url($base_admin_url . '&orderby=admission_date&order=' . $toggle_order . $filter_url_params); ?>">
                            <span><?php echo esc_html__('Admission Date & Time', 'arms-textdomain'); ?></span>
                        </a>
                    </th>
                    <th scope="col" style="width: 25%; text-align: left;"><?php echo esc_html__('Actions', 'arms-textdomain'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($patients)) : ?>
                    <?php foreach ($patients as $patient): 
                        $action_base_url = admin_url('admin.php?page=rehab_management_system&tab=patients&id=' . urlencode($patient['id']));
                        $delete_nonce = wp_create_nonce('arms_delete_patient_' . $patient['id']);
                        
                        // Modified key targets to map directly onto schema field: admission_date
                        $admission_raw = !empty($patient['admission_date']) ? $patient['admission_date'] : '';
                        $formatted_admission = !empty($admission_raw) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($admission_raw)) : esc_html__('N/A', 'arms-textdomain');
                    ?>
                        <tr>
                            <td>
                                <span class="arms-patient-id" style="font-weight: 600; color: #1d2327;">
                                    #ARMS-<?php echo esc_html(str_pad($patient['id'], 5, '0', STR_PAD_LEFT)); ?>
                                </span>
                            </td>
                            <td><strong><?php echo esc_html($patient['name']); ?></strong></td>
                            <td><?php echo esc_html($patient['mobile']); ?></td>
                            <td><span class="arms-admission-date" style="color: #50575e; font-size: 13px;"><?php echo esc_html($formatted_admission); ?></span></td>
                            <td>
                                <div class="arms-actions-wrapper">
                                    <a href="<?php echo esc_url($action_base_url . '&action=view'); ?>" class="arms-action-btn arms-btn-view" title="<?php echo esc_attr__('View Patient Details', 'arms-textdomain'); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <span><?php echo esc_html__('View', 'arms-textdomain'); ?></span>
                                    </a>
                                    <a href="<?php echo esc_url($action_base_url . '&action=edit'); ?>" class="arms-action-btn arms-btn-edit" title="<?php echo esc_attr__('Edit Patient Info', 'arms-textdomain'); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        <span><?php echo esc_html__('Edit', 'arms-textdomain'); ?></span>
                                    </a>
                                    <a href="<?php echo esc_url($action_base_url . '&action=delete&_wpnonce=' . $delete_nonce); ?>" class="arms-action-btn arms-btn-delete" title="<?php echo esc_attr__('Delete Patient', 'arms-textdomain'); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to permanently delete this patient record?', 'arms-textdomain')); ?>');">
                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        <span><?php echo esc_html__('Delete', 'arms-textdomain'); ?></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 30px; color: #646970; font-style: italic;"><?php echo esc_html__('No dynamic data found matching filter requirements.', 'arms-textdomain'); ?></td></tr>
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

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.arms-patient-table').DataTable({
                "paging": false,       
                "ordering": false,     
                "info": false,         
                "searching": true,     
                "responsive": true
            });
        });
    </script>
    <?php
}