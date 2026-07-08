<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="arms-card-box">
    <div class="arms-card-header-flex">
        <input type="text" id="armsStaffTableSearch" class="arms-search-input-field" placeholder="Search staff registry entries...">
    </div>
    
    <div class="arms-table-container">
        <table class="arms-data-table" id="armsStaffSystemDirectoryTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th style="text-align: right; padding-right: 24px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $staff_entries ) ) : ?>
                    <?php foreach ( $staff_entries as $staff ) : 
                        $badge_class = 'badge-support';
                        if ( $staff->role_category === 'doctor' ) $badge_class = 'badge-doctor';
                        elseif ( $staff->role_category === 'physiotherapist' ) $badge_class = 'badge-physio';
                        elseif ( $staff->role_category === 'nurse' ) $badge_class = 'badge-nurse';
                        elseif ( $staff->role_category === 'accountant' ) $badge_class = 'badge-accountant';

                        $initial_char = ! empty( $staff->first_name ) ? strtoupper( substr( $staff->first_name, 0, 1 ) ) : '?';
                        
                        $view_profile_url   = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=view&id=' . $staff->id );
                        $edit_profile_url   = admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=edit&id=' . $staff->id );
                        $delete_profile_url = wp_nonce_url( admin_url( 'admin.php?page=rehab_management_system&tab=staff&sub=list&action=delete&id=' . $staff->id ), 'arms_delete_staff_' . $staff->id );
                    ?>
                        <tr>
                            <td>
                                <div class="arms-staff-profile-meta">
                                    <?php if ( ! empty( $staff->profile_image ) ) : ?>
                                        <img src="<?php echo esc_url( $staff->profile_image ); ?>" class="arms-staff-avatar" alt="Avatar">
                                    <?php else : ?>
                                        <div class="arms-avatar-fallback"><?php echo esc_html( $initial_char ); ?></div>
                                    <?php endif; ?>
                                    <strong><?php echo esc_html( $staff->first_name . ' ' . $staff->last_name ); ?></strong>
                                </div>
                            </td>
                            <td><span class="arms-role-badge <?php echo $badge_class; ?>"><?php echo esc_html( str_replace('_', ' ', $staff->role_category) ); ?></span></td>
                            <td><?php echo esc_html( $staff->email ); ?></td>
                            <td><?php echo esc_html( $staff->phone ); ?></td>
                            <td>
                                <span class="arms-status-dot <?php echo ($staff->status === 'active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ($staff->status === 'active') ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="arms-action-btn-group" style="justify-content: flex-end;">
                                    <a href="<?php echo esc_url( $view_profile_url ); ?>" class="arms-action-btn btn-view">
                                        <span class="dashicons dashicons-visibility" style="font-size:14px; margin-right:2px;"></span> View
                                    </a>
                                    <a href="<?php echo esc_url( $edit_profile_url ); ?>" class="arms-action-btn btn-edit">
                                        <span class="dashicons dashicons-edit" style="font-size:14px; margin-right:2px;"></span> Edit
                                    </a>
                                    <a href="<?php echo esc_url( $delete_profile_url ); ?>" class="arms-action-btn btn-delete" onclick="return confirm('Warning: Purge this staff entry?');">
                                        <span class="dashicons dashicons-trash" style="font-size:14px; margin-right:2px;"></span> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="no-records-row">
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 30px;">No registered staff member profiles found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var tableEl = $('#armsStaffSystemDirectoryTable');
    
    // Fallback detection logic optimization: ensure we do not apply datatables to completely unseeded table arrays
    if (tableEl.find('tbody tr.no-records-row').length === 0) {
        var staffDataTable = tableEl.DataTable({
            "dom": 'rt<"arms-dt-footer-layout"ip>', // Suppress internal search layout component blocks to protect native configuration aesthetics
            "pageLength": 10,
            "ordering": true,
            "responsive": true,
            "columnDefs": [
                { "orderable": false, "targets": 5 } // Disable standard sorting operations over action columns
            ],
            "language": {
                "zeroRecords": "No records found matching the query criteria."
            }
        });

        // Intercept native event hook handling routines to pass external custom search inputs downstream
        $('#armsStaffTableSearch').on('keyup', function() {
            staffDataTable.search(this.value).draw();
        });
    }
});
</script>