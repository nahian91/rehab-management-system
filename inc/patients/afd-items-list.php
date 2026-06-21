<?php
if (!defined('ABSPATH')) exit;

function arms_patients_list_table() {
    // 1. Demo Data (Expanded to demonstrate pagination/sorting)
    $patients = [
        ['id' => 'P-1001', 'name' => 'Abul Kashem', 'mobile' => '01712345678', 'age_gender' => '54 / Male', 'diagnosis' => 'Hypertension', 'last_visit' => '2026-06-10', 'status' => 'Active'],
        ['id' => 'P-1002', 'name' => 'Rahima Begum', 'mobile' => '01812345678', 'age_gender' => '42 / Female', 'diagnosis' => 'Diabetes', 'last_visit' => '2026-06-18', 'status' => 'Under Review'],
        ['id' => 'P-1003', 'name' => 'Suresh Chakraborty', 'mobile' => '01912345678', 'age_gender' => '65 / Male', 'diagnosis' => 'Chronic Kidney Disease', 'last_visit' => '2026-05-14', 'status' => 'Discharged'],
        ['id' => 'P-1004', 'name' => 'Fatima Zohra', 'mobile' => '01512345678', 'age_gender' => '28 / Female', 'diagnosis' => 'Pregnancy Checkup', 'last_visit' => '2026-06-20', 'status' => 'Active'],
        ['id' => 'P-1005', 'name' => 'John Doe', 'mobile' => '01312345678', 'age_gender' => '35 / Male', 'diagnosis' => 'Fever & Flu', 'last_visit' => '2026-06-21', 'status' => 'Active'],
        ['id' => 'P-1006', 'name' => 'Sarah Jenkins', 'mobile' => '01612345679', 'age_gender' => '50 / Female', 'diagnosis' => 'Asthma', 'last_visit' => '2026-06-01', 'status' => 'Under Review']
    ];

    // Include DataTables assets via CDN for demo purposes
    ?>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    
    <style>
        .arms-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Sans-Serif; margin: 20px; }
        .arms-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }
        .arms-filters { display: flex; gap: 10px; align-items: center; }
        .arms-input, .arms-select { padding: 6px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; min-width: 180px; height: 35px; box-sizing: border-box; }
        
        /* Modernized DataTables Layout override */
        .dataTables_wrapper .dataTables_filter { display: none; } /* Hide default search since we have a custom one */
        .arms-table { width: 100% !important; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; margin-top: 10px; border-bottom: none !important; }
        .arms-table th, .arms-table td { padding: 12px 15px !important; border-bottom: 1px solid #e5e5e5 !important; vertical-align: middle; }
        .arms-table th { background-color: #f9f9f9; font-weight: 600; color: #3c434a; }
        
        /* Status Badges */
        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-block; }
        .status-active { background-color: #e2f9df; color: #257a1e; }
        .status-review { background-color: #fef3d6; color: #b25e00; }
        .status-discharged { background-color: #f0f0f1; color: #50575e; }

        /* Action Buttons */
        .arms-actions { display: flex; gap: 6px; justify-content: center; }
        .action-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 4px; border: 1px solid #dcdcde; background: #fff; cursor: pointer; transition: all 0.2s ease; text-decoration: none; }
        .action-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        
        .btn-view { color: #2271b1; }
        .btn-view:hover { background: #f0f6fa; border-color: #2271b1; }
        .btn-edit { color: #eca500; }
        .btn-edit:hover { background: #fdfaf2; border-color: #eca500; }
        .btn-delete { color: #d63638; }
        .btn-delete:hover { background: #fcf0f1; border-color: #d63638; }
    </style>

    <div class="arms-container">
        <h2>All Patients List</h2>
        
        <div class="arms-header">
            <div class="arms-filters">
                <input type="text" id="arms_p_search" class="arms-input" placeholder="Search ID, Name, Mobile...">
                
                <select id="arms_p_status" class="arms-select">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Under Review">Under Review</option>
                    <option value="Discharged">Discharged</option>
                </select>
            </div>
        </div>

        <table id="arms-patients-table" class="arms-table display">
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Age/Gender</th>
                    <th>Diagnosis</th>
                    <th>Last Visit</th>
                    <th>Status</th>
                    <th style="width: 110px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $patient): 
                    $status_class = 'status-discharged';
                    if ($patient['status'] === 'Active') $status_class = 'status-active';
                    if ($patient['status'] === 'Under Review') $status_class = 'status-review';
                    
                    // Route endpoints - fixed by appending the plugin administration context hook
                    // Fixed URL Construction - Explicitly retains your main tab destination
                    $base_url = '?page=rehab_management_system&tab=patients&id=' . esc_attr($patient['id']);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($patient['id']); ?></strong></td>
                        <td><?php echo esc_html($patient['name']); ?></td>
                        <td><?php echo esc_html($patient['mobile']); ?></td>
                        <td><?php echo esc_html($patient['age_gender']); ?></td>
                        <td><?php echo esc_html($patient['diagnosis']); ?></td>
                        <td><?php echo esc_html($patient['last_visit']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo esc_html($patient['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="arms-actions">
                                <a href="<?php echo esc_url($base_url . '&action=view'); ?>" class="action-btn btn-view" title="View Patient Details">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                                <a href="<?php echo esc_url($base_url . '&action=edit'); ?>" class="action-btn btn-edit" title="Edit Patient Info">
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </a>
                                <a href="<?php echo esc_url($base_url . '&action=delete'); ?>" class="action-btn btn-delete" title="Delete Patient" onclick="return confirm('Are you sure you want to delete this record?');">
                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script>
    jQuery(document).ready(function($) {
        // Initialize DataTable
        var table = $('#arms-patients-table').DataTable({
            "pageLength": 5, // Set how many rows to show by default
            "lengthMenu": [5, 10, 25, 50],
            "dom": 'rtlp', // Arranges layout elements (hides native search field)
            "columnDefs": [
                { "orderable": false, "targets": 7 } // Disable column sorting for Actions column
            ]
        });

        // Link Custom Search input to DataTable global search
        $('#arms_p_search').on('keyup change', function() {
            table.search(this.value).draw();
        });

        // Link Custom Status dropdown to Column 6 (Status column) exact match
        $('#arms_p_status').on('change', function() {
            var val = $.fn.dataTable.util.escapeRegex($(this).val());
            table.column(6).search(val ? '^' + val + '$' : '', true, false).draw();
        });
    });
    </script>
    <?php
}