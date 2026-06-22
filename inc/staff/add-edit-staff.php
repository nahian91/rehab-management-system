<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

$form_title = "Human Resources Registry Configuration Desk";
$btn_text   = "Commit Profile Entry";

if ( $row_data ) {
    $form_title = "Modify Existing Profile Data Matrix: " . esc_html($row_data->first_name . ' ' . $row_data->last_name);
    $btn_text   = "Save Updated Profile Changes";
}
?>
<div class="arms-card-box">
    <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 700;"><?php echo esc_html($form_title); ?></h3>
    
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field( 'arms_staff_nonce_action', 'arms_staff_nonce' ); ?>
        <?php if ( $row_data && ! empty( $row_data->profile_image ) ) : ?>
            <input type="hidden" name="existing_profile_image" value="<?php echo esc_url($row_data->profile_image); ?>">
        <?php endif; ?>
        
        <div class="arms-form-grid">
            <div class="arms-form-group">
                <label for="first_name">First Name *</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo $row_data ? esc_attr($row_data->first_name) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="last_name">Last Name *</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo $row_data ? esc_attr($row_data->last_name) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="role_category">Role Assignment Track *</label>
                <select id="role_category" name="role_category" required>
                    <?php 
                    $roles = array('doctor'=>'Doctor', 'physiotherapist'=>'Physiotherapist', 'nurse'=>'Nurse', 'accountant'=>'Accountant', 'support_staff'=>'Support Staff');
                    foreach($roles as $key => $label) {
                        $sel = ($row_data && $row_data->role_category === $key) ? 'selected' : '';
                        echo '<option value="'.esc_attr($key).'" '.$sel.'>'.esc_html($label).'</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="arms-form-group">
                <label for="email">Institutional Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo $row_data ? esc_attr($row_data->email) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="phone">Primary Contact Phone *</label>
                <input type="text" id="phone" name="phone" required value="<?php echo $row_data ? esc_attr($row_data->phone) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="license_number">BMDC / Professional Registry License Code</label>
                <input type="text" id="license_number" name="license_number" value="<?php echo $row_data ? esc_attr($row_data->license_number) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="joining_date">Official Joining Date</label>
                <input type="date" id="joining_date" name="joining_date" value="<?php echo $row_data ? esc_attr($row_data->joining_date) : date('Y-m-d'); ?>">
            </div>
            <div class="arms-form-group">
                <label for="salary">Base Monthly Remuneration (Gross)</label>
                <input type="number" step="0.01" id="salary" name="salary" value="<?php echo $row_data ? esc_attr($row_data->salary) : ''; ?>">
            </div>
            <div class="arms-form-group">
                <label for="status">Initial Operations Status</label>
                <select id="status" name="status">
                    <option value="active" <?php echo ($row_data && $row_data->status === 'active') ? 'selected' : ''; ?>>Active Duty</option>
                    <option value="inactive" <?php echo ($row_data && $row_data->status === 'inactive') ? 'selected' : ''; ?>>On Leave / Suspended</option>
                </select>
            </div>
            <div class="arms-form-group">
                <label for="profile_image">Profile Picture (JPG/PNG)</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" style="padding: 6px;">
            </div>
        </div>

        <button type="submit" name="arms_save_staff" class="arms-submit-btn">
            <span class="dashicons dashicons-id" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> <?php echo esc_html($btn_text); ?>
        </button>
        <a href="<?php echo esc_url($list_url); ?>" class="arms-action-btn btn-view" style="padding: 11px 18px; margin-left: 10px; font-size:13px; font-weight:600; border-radius:6px;">Cancel</a>
    </form>
</div>