<?php
/*--------------------------------------------------------------
# 9. System Global Settings Tab Processor
--------------------------------------------------------------*/

/**
 * Render and handle the Settings Tab Dashboard
 */
function arms_settings_tab() {
    // 1. Authorization Guard Check
    if ( ! current_user_can( 'manage_options' ) && ! arms_has_access( array( 'admin_manager' ) ) ) {
        echo '<div class="notice notice-error"><p>Access Denied: Insufficient operational clearance.</p></div>';
        return;
    }

    // 2. Process Save Operations on POST Request
    if ( isset( $_POST['arms_save_settings'] ) ) {
        // Verify Security Nonce Matrix
        if ( ! isset( $_POST['arms_settings_nonce_field'] ) || ! wp_verify_nonce( $_POST['arms_settings_nonce_field'], 'arms_save_settings_action' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>Security verification failed. Please try again.</p></div>';
        } else {
            // Sanitize and structure the option parameters map safely
            $clean_settings = array(
                // Profile Configuration
                'clinic_name'             => sanitize_text_field( $_POST['clinic_name'] ?? '' ),
                'clinic_address'          => sanitize_textarea_field( $_POST['clinic_address'] ?? '' ),
                'currency_symbol'         => sanitize_text_field( $_POST['currency_symbol'] ?? '$' ),
                
                // Clinical Rate Master Matrix
                'tax_rate'                => max( 0, floatval( $_POST['tax_rate'] ?? 0 ) ),
                'fee_doctor'              => max( 0, floatval( $_POST['fee_doctor'] ?? 0 ) ),
                'fee_physio'              => max( 0, floatval( $_POST['fee_physio'] ?? 0 ) ),
                'fee_nursing'             => max( 0, floatval( $_POST['fee_nursing'] ?? 0 ) ),
                'fee_acupuncture'         => max( 0, floatval( $_POST['fee_acupuncture'] ?? 0 ) ),
                'fee_prp'                 => max( 0, floatval( $_POST['fee_prp'] ?? 0 ) ),
                
                // Inventory Operational Rules
                'low_stock_threshold'     => max( 1, intval( $_POST['low_stock_threshold'] ?? 10 ) ),
                
                // Accommodation Setup
                'room_rent_cabin'         => max( 0, floatval( $_POST['room_rent_cabin'] ?? 0 ) ),
                'room_rent_ward'          => max( 0, floatval( $_POST['room_rent_ward'] ?? 0 ) ),
                'room_rent_semi_private'  => max( 0, floatval( $_POST['room_rent_semi_private'] ?? 0 ) ),
            );

            // Update centralized serialized option table field
            update_option( 'arms_global_settings', $clean_settings );
            echo '<div class="notice notice-success is-dismissible"><p><strong>ARMS Control Desk:</strong> Configuration profiles saved successfully.</p></div>';
        }
    }

    // 3. Fetch Existing Setup Data Profile Defaults
    $defaults = array(
        'clinic_name'             => 'ARMS Rehabilitation Center',
        'clinic_address'          => '',
        'currency_symbol'         => '$',
        'tax_rate'                => 0,
        'fee_doctor'              => 0,
        'fee_physio'              => 0,
        'fee_nursing'             => 0,
        'fee_acupuncture'         => 0,
        'fee_prp'                 => 0,
        'low_stock_threshold'     => 10,
        'room_rent_cabin'         => 0,
        'room_rent_ward'          => 0,
        'room_rent_semi_private'  => 0,
    );

    $settings = wp_parse_args( get_option( 'arms_global_settings', array() ), $defaults );
    ?>

    <div class="arms-settings-container" style="padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2>System Global Parameters & Rules Configuration</h2>
        <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 20px;" />

        <form method="post" action="">
            <?php wp_nonce_field( 'arms_save_settings_action', 'arms_settings_nonce_field' ); ?>

            <h3 style="color: #2271b1;"><span class="dashicons dashicons-admin-home" style="vertical-align: middle; margin-right: 5px;"></span> 1. Clinic Information & Branding</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="clinic_name">Official Clinic Name</label></th>
                    <td><input type="text" id="clinic_name" name="clinic_name" value="<?php echo esc_attr( $settings['clinic_name'] ); ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="clinic_address">Print Layout Address</label></th>
                    <td><textarea id="clinic_address" name="clinic_address" rows="3" class="large-text"><?php echo esc_textarea( $settings['clinic_address'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="currency_symbol">Global Currency Symbol</label></th>
                    <td><input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" style="width: 80px; text-align: center;" required /></td>
                </tr>
            </table>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;" />

            <h3 style="color: #2271b1;"><span class="dashicons dashicons-cart" style="vertical-align: middle; margin-right: 5px;"></span> 2. Central Billing & Fee Matrix Settings</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tax_rate">Default Tax/VAT Rate (%)</label></th>
                    <td><input type="number" step="0.01" id="tax_rate" name="tax_rate" value="<?php echo esc_attr( $settings['tax_rate'] ); ?>" class="small-text" /> <span class="description">Automatically calculated during active invoice drafting.</span></td>
                </tr>
                <tr>
                    <th scope="row"><label for="fee_doctor">Base Consultation Fee (Doctor)</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="fee_doctor" name="fee_doctor" value="<?php echo esc_attr( $settings['fee_doctor'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="fee_physio">Standard Physiotherapy Session Fee</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="fee_physio" name="fee_physio" value="<?php echo esc_attr( $settings['fee_physio'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="fee_nursing">Standard Daily Nursing Care Fee</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="fee_nursing" name="fee_nursing" value="<?php echo esc_attr( $settings['fee_nursing'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="fee_acupuncture">Specialty Acupuncture Rate</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="fee_acupuncture" name="fee_acupuncture" value="<?php echo esc_attr( $settings['fee_acupuncture'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="fee_prp">Platelet-Rich Plasma (PRP) Procedure Rate</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="fee_prp" name="fee_prp" value="<?php echo esc_attr( $settings['fee_prp'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
            </table>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;" />

            <h3 style="color: #2271b1;"><span class="dashicons dashicons-category" style="vertical-align: middle; margin-right: 5px;"></span> 3. Room & Accommodation Rate Allocation Rules</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="room_rent_cabin">Private Cabin Daily Rent Price</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="room_rent_cabin" name="room_rent_cabin" value="<?php echo esc_attr( $settings['room_rent_cabin'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="room_rent_ward">General Ward Bed Daily Rent Price</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="room_rent_ward" name="room_rent_ward" value="<?php echo esc_attr( $settings['room_rent_ward'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="room_rent_semi_private">Semi-Private Room Daily Rent Price</label></th>
                    <td><?php echo esc_html($settings['currency_symbol']); ?> <input type="number" step="0.01" id="room_rent_semi_private" name="room_rent_semi_private" value="<?php echo esc_attr( $settings['room_rent_semi_private'] ); ?>" class="regular-text" style="width: 120px;" /></td>
                </tr>
            </table>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;" />

            <h3 style="color: #2271b1;"><span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span> 4. Core Safeguards & Stock Inventory Alerts</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="low_stock_threshold">Minimum Inventory Stock Trigger</label></th>
                    <td><input type="number" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo esc_attr( $settings['low_stock_threshold'] ); ?>" class="small-text" min="1" required /> <span class="description">Flags warnings on inventory lists when available stock count falls below this margin.</span></td>
                </tr>
            </table>

            <p class="submit" style="margin-top: 30px;">
                <input type="submit" name="arms_save_settings" id="submit" class="button button-primary button-large" value="Save System Parameters" />
            </p>
        </form>
    </div>
    <?php
}