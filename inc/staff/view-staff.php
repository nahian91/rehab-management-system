<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

$initial_char = ! empty( $staff->first_name ) ? strtoupper( substr( $staff->first_name, 0, 1 ) ) : '?';
?>
<div class="arms-card-box">
    <div class="arms-card-header-flex">
        <h3 style="margin:0; font-size:18px; font-weight:700;">Clinical Staff Record Profile Sheet</h3>
        <a href="<?php echo esc_url($list_url); ?>" class="arms-submit-btn" style="padding: 8px 16px;">
            <span class="dashicons dashicons-arrow-left-alt" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Back to Roster
        </a>
    </div>

    <div class="profile-view-grid">
        <div class="profile-view-sidebar">
            <?php if ( ! empty( $staff->profile_image ) ) : ?>
                <img src="<?php echo esc_url( $staff->profile_image ); ?>" class="profile-large-avatar" alt="Profile pic">
            <?php else : ?>
                <div class="profile-large-fallback"><?php echo esc_html( $initial_char ); ?></div>
            <?php endif; ?>
            <h4 style="margin:5px 0 0 0; font-size:18px;"><?php echo esc_html($staff->first_name . ' ' . $staff->last_name); ?></h4>
            <p style="margin:4px 0 15px 0; color:#64748b; font-weight:500; text-transform:capitalize;"><?php echo esc_html(str_replace('_', ' ', $staff->role_category)); ?></p>
            
            <span class="arms-status-dot <?php echo ($staff->status === 'active') ? 'status-active' : 'status-inactive'; ?>">
                Status: <?php echo ($staff->status === 'active') ? 'Active Duty' : 'Inactive'; ?>
            </span>
        </div>

        <div class="profile-view-details">
            <div class="profile-detail-row">
                <span class="profile-detail-label">System Record Reference ID</span>
                <span class="profile-detail-val">#<?php echo esc_html($staff->id); ?></span>
            </div>
            <div class="profile-detail-row">
                <span class="profile-detail-label">Institutional Contact Email</span>
                <span class="profile-detail-val"><?php echo esc_html($staff->email); ?></span>
            </div>
            <div class="profile-detail-row">
                <span class="profile-detail-label">Primary Secure Contact</span>
                <span class="profile-detail-val"><?php echo esc_html($staff->phone); ?></span>
            </div>
            <div class="profile-detail-row">
                <span class="profile-detail-label">Medical Board Registration / Code</span>
                <span class="profile-detail-val"><code><?php echo ! empty($staff->license_number) ? esc_html($staff->license_number) : 'Not Applicable'; ?></code></span>
            </div>
            <div class="profile-detail-row">
                <span class="profile-detail-label">Official Registry Joining Date</span>
                <span class="profile-detail-val"><?php echo esc_html(date('F j, Y', strtotime($staff->joining_date))); ?></span>
            </div>
            <div class="profile-detail-row">
                <span class="profile-detail-label">Base Monthly Salary Baseline</span>
                <span class="profile-detail-val">৳<?php echo number_format($staff->salary, 2); ?></span>
            </div>
            <div class="profile-detail-row">
                <span class="profile-detail-label">System Profile Creation Timestamp</span>
                <span class="profile-detail-val"><?php echo esc_html(date('g:ia - M j, Y', strtotime($staff->created_at))); ?></span>
            </div>
            
            <div style="margin-top:25px; display:flex; gap:10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rehab_management_system&tab=staff&sub=edit&id='.$staff->id)); ?>" class="arms-action-btn btn-edit" style="padding:10px 20px; font-weight:600; border-radius:6px;">Modify Data Profile</a>
            </div>
        </div>
    </div>
</div>