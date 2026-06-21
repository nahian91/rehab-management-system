<?php
/**
 * RESTAURANT OPERATIONS ENGINE
 * Includes: Global Status Logic, Admin Dashboard, and Frontend Modal
 * Updated: Discount split into Delivery % and Pickup %
 */

// --- 1. THE LOGIC ENGINE ---
if ( ! function_exists( 'get_afd_restaurant_status' ) ) {
    function get_afd_restaurant_status() {
        $schedule    = get_option('afd_schedule', []);
        $closed_msg  = get_option('afd_status_message', 'Sorry, we are currently closed!');
        $warning_msg = get_option('afd_warning_message', 'Hurry! We are closing in %min% minutes.');

        $now          = current_datetime(); 
        $current_day  = $now->format('D'); 
        $current_time = $now->format('H:i');
        $current_ts   = strtotime($current_time);

        if (empty($schedule[$current_day]) || !isset($schedule[$current_day]['enabled']) || !$schedule[$current_day]['enabled']) {
            return ['is_open' => false, 'status' => 'closed', 'message' => $closed_msg];
        }

        $day_settings = $schedule[$current_day];
        $open_ts       = strtotime($day_settings['open']);
        $close_ts      = strtotime($day_settings['close']);

        $is_open = ($close_ts < $open_ts) 
            ? ($current_ts >= $open_ts || $current_ts <= $close_ts) 
            : ($current_ts >= $open_ts && $current_ts <= $close_ts);

        if (!$is_open) return ['is_open' => false, 'status' => 'closed', 'message' => $closed_msg];

        if ($current_ts >= ($close_ts - 1800) && $current_ts < $close_ts) {
            $minutes_left = ceil(($close_ts - $current_ts) / 60);
            $final_warning = str_replace('%min%', $minutes_left, $warning_msg);
            return ['is_open' => true, 'status' => 'warning', 'message' => $final_warning];
        }

        return ['is_open' => true, 'status' => 'open', 'message' => ''];
    }
}

// --- 2. THE FRONTEND MODAL ---
function afd_frontend_closed_modal() {
    if ( is_front_page() && function_exists('get_afd_restaurant_status') ) {
        $store_status = get_afd_restaurant_status(); 

        if ( isset($store_status['status']) && $store_status['status'] === 'closed' ) : 
            $schedule     = get_option('afd_schedule', []);
            $current_day  = current_datetime()->format('D');
            $day_settings = isset($schedule[$current_day]) ? $schedule[$current_day] : null;
            $is_off_day   = ( ! $day_settings || empty($day_settings['enabled']) );
            $menu_url     = home_url('/menu/'); 
            ?>
            <div id="afd-closed-modal" class="afd-modal-overlay" style="display:none;">
                <div class="afd-modal-box">
                    <div class="afd-modal-header">
                        <div class="closed-icon" style="background: #f59e0b; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px;">🕒</div>
                        <h2 style="margin:0; text-align: center;"><?php esc_html_e( "We're Currently Closed", 'text-domain' ); ?></h2>
                    </div>
                    <div class="afd-modal-body" style="padding: 20px; text-align: center;">
                        <p class="main-msg" style="font-weight: 600; color: #1e293b;"><?php echo nl2br( esc_html( $store_status['message'] ) ); ?></p>
                        
                        <div class="time-info" style="background: #f8fafc; padding: 15px; border-radius: 10px; margin: 15px 0;">
                            <?php if ($is_off_day) : ?>
                                <span style="font-size: 11px; color: #64748b; text-transform: uppercase;"><?php esc_html_e( 'Status', 'text-domain' ); ?></span>
                                <p class="off-day-text" style="margin: 5px 0 0; font-weight: 700; color: #ef4444;"><?php esc_html_e( 'Today is our Day Off', 'text-domain' ); ?></p>
                            <?php else : ?>
                                <span style="font-size: 11px; color: #64748b; text-transform: uppercase;"><?php esc_html_e( "Today's Operating Hours", 'text-domain' ); ?></span>
                                <p style="margin: 5px 0 0; font-weight: 700; color: #1e293b;"><?php echo esc_html( $day_settings['open'] ); ?> — <?php echo esc_html( $day_settings['close'] ); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <p style="font-size: 13px; color: #64748b; margin-top: 15px;">
                            <?php echo wp_kses_post( __( 'You can still place a <strong>Pre-Order</strong> for our next opening time!', 'text-domain' ) ); ?>
                        </p>
                    </div>
                    <div class="afd-modal-footer" style="display: flex; gap: 10px; padding: 0 20px 20px;">
                        <button onclick="closeAfdModal()" class="afd-btn-close" style="background: #e2e8f0; color: #475569; border: none; padding: 12px 15px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            <?php esc_html_e( 'Close', 'text-domain' ); ?>
                        </button>
                        
                        <a href="<?php echo esc_url( $menu_url ); ?>" class="afd-btn-preorder" style="background: #ef4444; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; flex-grow: 1; text-align: center;">
                            <?php esc_html_e( 'Pre-Order Now', 'text-domain' ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <style>
                .afd-modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(15, 23, 42, 0.7); z-index: 99999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
                .afd-modal-box { background: #fff; width: 90%; max-width: 400px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: afdPop 0.3s ease-out; }
                @keyframes afdPop { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (!sessionStorage.getItem('afd_modal_dismissed')) {
                        document.getElementById('afd-closed-modal').style.display = 'flex';
                    }
                });
                function closeAfdModal() {
                    document.getElementById('afd-closed-modal').remove();
                    sessionStorage.setItem('afd_modal_dismissed', 'true');
                }
            </script>
        <?php endif; 
    }
}

// --- 3. THE ADMIN SETTINGS PAGE ---
function fd_settings_tab() {
    if (isset($_POST['afd_save_settings'])) {
        check_admin_referer('afd_settings_nonce_action', 'afd_settings_nonce');

        update_option('afd_status_message', sanitize_textarea_field($_POST['afd_status_message']));
        update_option('afd_warning_message', sanitize_textarea_field($_POST['afd_warning_message']));
        update_option('afd_minimum_order', sanitize_text_field($_POST['afd_minimum_order']));
        update_option('afd_delivery_charge', sanitize_text_field($_POST['afd_delivery_charge']));
        update_option('afd_pickup_charge', sanitize_text_field($_POST['afd_pickup_charge']));
        update_option('afd_service_charge', sanitize_text_field($_POST['afd_service_charge']));
        update_option('afd_bag_charge', sanitize_text_field($_POST['afd_bag_charge']));
        
        // Split Discount Updates
        update_option('afd_delivery_discount_percent', sanitize_text_field($_POST['afd_delivery_discount_percent'])); 
        update_option('afd_pickup_discount_percent', sanitize_text_field($_POST['afd_pickup_discount_percent'])); 
        
        update_option('afd_cooking_time', sanitize_text_field($_POST['afd_cooking_time'])); 
        
        $new_schedule = [];
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        foreach ($days as $day) {
            $new_schedule[$day] = [
                'enabled' => isset($_POST['afd_sched'][$day]['enabled']),
                'open'    => sanitize_text_field($_POST['afd_sched'][$day]['open'] ?: '09:00'),
                'close'   => sanitize_text_field($_POST['afd_sched'][$day]['close'] ?: '22:00'),
            ];
        }
        update_option('afd_schedule', $new_schedule);
        
        echo '<div class="afd-sync-toast"><span class="dashicons dashicons-saved"></span> Settings synchronized successfully</div>';
    }

    $schedule           = get_option('afd_schedule', []);
    $message            = get_option('afd_status_message', 'Sorry, we are currently closed!');
    $warning_msg        = get_option('afd_warning_message', 'Hurry! We are closing in %min% minutes.');
    $minimum_order    = get_option('afd_minimum_order', '0.00');
    $delivery_charge    = get_option('afd_delivery_charge', '0.00');
    $pickup_charge      = get_option('afd_pickup_charge', '0.00');
    $service_charge     = get_option('afd_service_charge', '0.00');
    $bag_charge         = get_option('afd_bag_charge', '0.00');
    
    // Split Discount Retrieval
    $del_discount_percent = get_option('afd_delivery_discount_percent', '0'); 
    $pic_discount_percent = get_option('afd_pickup_discount_percent', '0'); 

    $cooking_time     = get_option('afd_cooking_time', '20-30');
    $status_info      = get_afd_restaurant_status();
    $days_of_week     = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    $badge_map = [
        'open'    => ['class' => 'badge-open', 'text' => '● LIVE: OPEN'],
        'warning' => ['class' => 'badge-warning', 'text' => '● CLOSING SOON'],
        'closed'  => ['class' => 'badge-closed', 'text' => '● LIVE: CLOSED']
    ];
    $current_badge = $badge_map[$status_info['status']];
    ?>

    <div class="afd-wrapper">
        <div class="afd-header-main slide-down">
            <div class="header-left">
                <h1>Restaurant Operations</h1>
                <p>Manage your store availability, delivery fees, and customer alerts.</p>
                <div class="afd-system-time">
                    <span class="dashicons dashicons-clock"></span> 
                    System Time: <strong><?php echo current_datetime()->format('H:i'); ?></strong> 
                    <span class="time-sep-pipe">|</span> 
                    Timezone: <code><?php echo wp_timezone_string(); ?></code>
                </div>
            </div>
            <div class="afd-live-status <?php echo $current_badge['class']; ?>">
                <span class="pulse-dot"></span>
                <?php echo $current_badge['text']; ?>
            </div>
        </div>

        <?php if($status_info['status'] === 'warning'): ?>
            <div class="afd-smart-preview">
                <span class="dashicons dashicons-info"></span>
                <strong>Live Preview:</strong> <?php echo $status_info['message']; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('afd_settings_nonce_action', 'afd_settings_nonce'); ?>
            <div class="afd-grid">
                
                <div class="afd-card slide-left">
                    <div class="card-title">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <h2>Weekly Operating Hours</h2>
                    </div>
                    
                    <div class="schedule-container">
                        <?php foreach ($days_of_week as $index => $day) : 
                            $data = isset($schedule[$day]) ? $schedule[$day] : ['enabled' => false, 'open' => '09:00', 'close' => '22:00'];
                        ?>
                            <div class="schedule-row <?php echo $data['enabled'] ? 'is-active' : ''; ?>" style="animation-delay: <?php echo $index * 0.05; ?>s">
                                <label class="day-pill-toggle">
                                    <input type="checkbox" name="afd_sched[<?php echo $day; ?>][enabled]" class="day-check" <?php checked($data['enabled']); ?>>
                                    <span class="day-name"><?php echo $day; ?></span>
                                </label>
                                
                                <div class="time-input-group">
                                    <input type="time" name="afd_sched[<?php echo $day; ?>][open]" value="<?php echo $data['open']; ?>">
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                    <input type="time" name="afd_sched[<?php echo $day; ?>][close]" value="<?php echo $data['close']; ?>">
                                </div>
                                
                                <div class="row-status">
                                    <?php echo $data['enabled'] ? '<span class="status-on">Open</span>' : '<span class="status-off">Closed</span>'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="afd-sidebar slide-right">
                    <div class="afd-card mini-card">
                        <div class="card-title">
                            <span class="dashicons dashicons-money-alt"></span>
                            <h2>Financials & Estimations</h2>
                        </div>
                        
                        <div class="input-field">
                            <label>Minimum Order (£)</label>
                            <div class="currency-input">
                                <span class="currency-symbol">£</span>
                                <input type="text" name="afd_minimum_order" value="<?php echo esc_attr($minimum_order); ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Delivery Charge (£)</label>
                            <div class="currency-input">
                                <span class="currency-symbol">£</span>
                                <input type="text" name="afd_delivery_charge" value="<?php echo esc_attr($delivery_charge); ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Collection Charge (£)</label>
                            <div class="currency-input">
                                <span class="currency-symbol">£</span>
                                <input type="text" name="afd_pickup_charge" value="<?php echo esc_attr($pickup_charge); ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Service Charge (£)</label>
                            <div class="currency-input">
                                <span class="currency-symbol">£</span>
                                <input type="text" name="afd_service_charge" value="<?php echo esc_attr($service_charge); ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Bag Charge (£)</label>
                            <div class="currency-input">
                                <span class="currency-symbol">£</span>
                                <input type="text" name="afd_bag_charge" value="<?php echo esc_attr($bag_charge); ?>" placeholder="0.00">
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Delivery Discount (%)</label>
                            <div class="currency-input">
                                <input type="text" name="afd_delivery_discount_percent" value="<?php echo esc_attr($del_discount_percent); ?>" placeholder="0" style="padding-left: 12px; padding-right: 28px;">
                                <span class="currency-symbol" style="left: auto; right: 12px;">%</span>
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Collection Discount (%)</label>
                            <div class="currency-input">
                                <input type="text" name="afd_pickup_discount_percent" value="<?php echo esc_attr($pic_discount_percent); ?>" placeholder="0" style="padding-left: 12px; padding-right: 28px;">
                                <span class="currency-symbol" style="left: auto; right: 12px;">%</span>
                            </div>
                        </div>

                        <div class="input-field" style="margin-top: 15px;">
                            <label>Cooking Time (Mins)</label>
                            <div class="currency-input">
                                <span class="currency-symbol"><span class="dashicons dashicons-performance" style="font-size:15px; margin-top:2px;"></span></span>
                                <input type="text" name="afd_cooking_time" value="<?php echo esc_attr($cooking_time); ?>" placeholder="e.g. 20-30">
                            </div>
                        </div>
                    </div>

                    <div class="afd-card">
                        <div class="card-title">
                            <span class="dashicons dashicons-megaphone"></span>
                            <h2>Store Messaging</h2>
                        </div>
                        <div class="input-field">
                            <label>Warning (Closing Soon)</label>
                            <textarea name="afd_warning_message" rows="2" placeholder="Use %min% for dynamic minutes"><?php echo esc_textarea($warning_msg); ?></textarea>
                            <p style="font-size:10px; color:#64748b; margin-top:5px;">Tip: Use <code>%min%</code> to show exact minutes left.</p>
                        </div>
                        <div class="input-field" style="margin-top:15px;">
                            <label>Store Closed Message</label>
                            <textarea name="afd_status_message" rows="3"><?php echo esc_textarea($message); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="afd_save_settings" class="afd-save-button">
                        <span class="dashicons dashicons-saved"></span> Save Configuration
                    </button>
                </div>
            </div>
        </form>
    </div>

    <style>
        .afd-wrapper { max-width: 1000px; margin: 20px 0; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color: #1e293b; }
        .afd-smart-preview { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; animation: slideInUp 0.3s ease-out; }
        .afd-sync-toast { position: fixed; top: 40px; right: 40px; background: #10b981; color: #fff; padding: 15px 25px; border-radius: 12px; z-index: 9999; font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4); animation: toastIn 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards, fadeOut 0.5s 3s forwards; }
        @keyframes toastIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; visibility: hidden; } }
        @keyframes slideInUp { from { transform: translateY(15px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes pulseGlow { 0% { box-shadow: 0 0 0 0 rgba(103, 194, 58, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(103, 194, 58, 0); } 100% { box-shadow: 0 0 0 0 rgba(103, 194, 58, 0); } }
        .slide-down { animation: slideInUp 0.4s ease-out; }
        .slide-left { animation: slideInUp 0.5s ease-out; }
        .slide-right { animation: slideInUp 0.6s ease-out; }
        .afd-header-main { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .header-left h1 { margin: 0; font-size: 22px; font-weight: 800; }
        .header-left p { margin: 4px 0 12px; color: #64748b; font-size: 14px; }
        .afd-system-time { font-size: 12px; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; }
        .afd-live-status { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 11px; letter-spacing: 0.5px; }
        .badge-open { background: #f0f9eb; color: #67c23a; border: 1px solid #c2e7b0; animation: pulseGlow 2s infinite; }
        .badge-warning { background: #fdf6ec; color: #e6a23c; border: 1px solid #f5dab1; }
        .badge-closed { background: #ffeded; color: #f56c6c; border: 1px solid #fbc4c4; }
        .pulse-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .afd-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
        .afd-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: 0.3s; }
        .afd-card:hover { border-color: #cbd5e1; }
        .card-title { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
        .card-title h2 { font-size: 14px; font-weight: 700; margin: 0; text-transform: uppercase; }
        .schedule-row { display: flex; align-items: center; gap: 15px; padding: 10px; border-radius: 10px; margin-bottom: 4px; border: 1px solid transparent; opacity: 0; animation: slideInUp 0.4s ease forwards; }
        .schedule-row.is-active { background: #f8fafc; border-color: #e2e8f0; }
        .day-pill-toggle input { display: none; }
        .day-name { display: block; width: 45px; text-align: center; padding: 8px 0; border-radius: 6px; background: #ffeded; color: #f56c6c; border: 1px solid #fbc4c4; font-weight: 700; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .day-check:checked + .day-name { background: #67c23a; color: #fff; border-color: #5daf34; box-shadow: 0 3px 8px rgba(103, 194, 58, 0.2); }
        .time-input-group { display: flex; align-items: center; gap: 8px; opacity: 0.3; pointer-events: none; transition: 0.3s; }
        .schedule-row.is-active .time-input-group { opacity: 1; pointer-events: all; }
        .time-input-group input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px; font-weight: 600; transition: 0.2s; }
        .row-status { margin-left: auto; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-on { color: #67c23a; }
        .status-off { color: #94a3b8; }
        .currency-input { position: relative; display: flex; align-items: center; }
        .currency-symbol { position: absolute; left: 12px; font-weight: 700; color: #64748b; }
        .currency-input input { width: 100%; padding: 8px 8px 8px 28px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; }
        label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 5px; font-size: 13px; }
        textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-size: 13px; resize: none; transition: 0.2s; }
        .afd-save-button { width: 100%; padding: 14px; background: #2271b1; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
        .afd-save-button:hover { background: #135e96; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34, 113, 177, 0.2); }
        @media (max-width: 850px) { .afd-grid { grid-template-columns: 1fr; } }
    </style>

    <script>
        document.querySelectorAll('.day-check').forEach(input => {
            input.addEventListener('change', function() {
                const row = this.closest('.schedule-row');
                const statusDiv = row.querySelector('.row-status');
                if(this.checked) {
                    row.classList.add('is-active');
                    statusDiv.innerHTML = '<span class="status-on">Open</span>';
                } else {
                    row.classList.remove('is-active');
                    statusDiv.innerHTML = '<span class="status-off">Closed</span>';
                }
            });
        });
    </script>
    <?php
}