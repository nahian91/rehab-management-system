<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
function arms_create_inventory_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_inventory = $wpdb->prefix . 'arms_inventory';
    $table_movements = $wpdb->prefix . 'arms_stock_movements';

    // Core Inventory Table
    $sql_inventory = "CREATE TABLE $table_inventory (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        item_code varchar(100) NOT NULL,
        item_name varchar(255) NOT NULL,
        generic_name varchar(255) DEFAULT '' NOT NULL,
        category varchar(100) DEFAULT 'General' NOT NULL,
        sku varchar(100) DEFAULT '' NOT NULL,
        available_stock int(11) DEFAULT '0' NOT NULL,
        min_required_stock int(11) DEFAULT '10' NOT NULL,
        unit_type varchar(50) DEFAULT 'pieces' NOT NULL,
        purchase_price decimal(10,2) DEFAULT '0.00' NOT NULL,
        sale_price decimal(10,2) DEFAULT '0.00' NOT NULL,
        supplier_info text DEFAULT NULL,
        batch_number varchar(100) DEFAULT '' NOT NULL,
        expiry_date date DEFAULT '1970-01-01' NOT NULL,
        status varchar(50) DEFAULT 'In Stock' NOT NULL,
        updated_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY item_code (item_code)
    ) $charset_collate;";

    // Stock Movement History Ledger Table
    $sql_movements = "CREATE TABLE $table_movements (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        item_id bigint(20) NOT NULL,
        movement_type varchar(20) NOT NULL,
        quantity int(11) NOT NULL,
        reference_type varchar(100) NOT NULL,
        reference_id varchar(100) NOT NULL,
        remarks text DEFAULT NULL,
        logged_by bigint(20) NOT NULL,
        created_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_inventory );
    dbDelta( $sql_movements );
}

/**
 * Main routing and rendering gateway function for the inventory management desk.
 */
function arms_inventory_tab() {
    // Structural automation initializer hook (Clean with zero seed injections)
    arms_seed_static_inventory_data_fallback();

    $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';

    // Sub-tab navigation layout matrix
    $tabs = array(
        'all' => 'All Stock Items',
        'add' => 'Add Purchase Entry',
    );

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        $url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=' . $k );
        $active_class = ( $sub === $k ) ? 'nav-tab-active' : '';
        echo '<a class="nav-tab ' . esc_attr( $active_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    echo '<div class="arms-sub-tab-content" style="margin-top: 20px;">';

    if ( $sub === 'add' || $sub === 'edit' ) {
        $item_id = isset( $_GET['item'] ) ? intval( $_GET['item'] ) : 0;
        arms_inventory_form_view( $item_id );
    } elseif ( $sub === 'view' ) {
        $item_id = isset( $_GET['item'] ) ? intval( $_GET['item'] ) : 0;
        arms_inventory_single_item_view( $item_id );
    } else {
        arms_inventory_list_view();
    }

    echo '</div>';
}

/*--------------------------------------------------------------
# 1. Action Post Handler (Form Submissions Processing Core)
--------------------------------------------------------------*/
add_action( 'admin_init', function() {
    global $wpdb;
    $table_inventory = $wpdb->prefix . 'arms_inventory';
    $table_movements = $wpdb->prefix . 'arms_stock_movements';

    // GET Request Handler: Secure Row Deletion Routine
    if ( isset( $_GET['arms_inv_del_nonce'] ) && isset( $_GET['action'] ) && $_GET['action'] === 'delete_item' ) {
        if ( ! wp_verify_nonce( sanitize_key( $_GET['arms_inv_del_nonce'] ), 'arms_delete_item_action' ) ) {
            wp_die( esc_html__( 'Security signature mismatch validation error.', 'rehab-management-system' ) );
        }

        $delete_id = isset( $_GET['item'] ) ? intval( $_GET['item'] ) : 0;
        if ( $delete_id > 0 ) {
            $item_meta = $wpdb->get_row( $wpdb->prepare( "SELECT sku, available_stock FROM $table_inventory WHERE id = %d", $delete_id ) );
            
            if ( $item_meta ) {
                $wpdb->delete( $table_inventory, array( 'id' => $delete_id ), array( '%d' ) );

                // Post historical depletion marker into movement registry ledger
                $wpdb->insert(
                    $table_movements,
                    array(
                        'item_id'        => $delete_id,
                        'movement_type'  => 'out',
                        'quantity'       => intval( $item_meta->available_stock ),
                        'reference_type' => 'system_purged',
                        'reference_id'   => sanitize_text_field( $item_meta->sku ),
                        'remarks'        => 'Item asset completely dropped from active registration tables.',
                        'logged_by'      => get_current_user_id(),
                        'created_at'     => current_time( 'mysql' )
                    )
                );

                wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=item_deleted' ) );
                exit;
            }
        }
    }

    // POST Request Handlers: Creation and Update Processes
    if ( ! isset( $_POST['arms_inv_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( sanitize_key( $_POST['arms_inv_nonce'] ), 'arms_inv_action' ) ) {
        wp_die( esc_html__( 'Security payload verification failure.', 'rehab-management-system' ) );
    }

    $action = sanitize_key( $_POST['arms_action'] );

    if ( $action === 'save_item' ) {
        $item_id      = isset( $_POST['item_id'] ) ? intval( $_POST['item_id'] ) : 0;
        $sku          = sanitize_text_field( wp_unslash( $_POST['item_sku'] ) );
        $item_name    = sanitize_text_field( wp_unslash( $_POST['item_name'] ) );
        $category     = sanitize_text_field( wp_unslash( $_POST['item_category'] ) );
        $unit_price   = floatval( $_POST['item_unit_price'] );
        $qty          = intval( $_POST['item_qty'] );
        $min_stock    = intval( $_POST['item_min_stock'] );
        $supplier     = sanitize_text_field( wp_unslash( $_POST['supplier_info'] ) );
        $invoice      = sanitize_text_field( wp_unslash( $_POST['invoice_tracking'] ) );

        if ( empty( $item_name ) || empty( $sku ) ) {
            wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=missing_data' ) );
            exit;
        }

        // Avoid unique index collisions on item_code by checking duplicates explicitly
        $duplicate_check = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM $table_inventory WHERE (item_code = %s OR sku = %s) AND id != %d", 
            $sku, $sku, $item_id 
        ) );

        if ( $duplicate_check ) {
            wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=duplicate_sku' ) );
            exit;
        }

        $status_flag = ( $qty <= 0 ) ? 'Out of Stock' : 'In Stock';

        $data_payload = array(
            'item_code'          => $sku,
            'item_name'          => $item_name,
            'generic_name'       => '',
            'category'           => $category,
            'sku'                => $sku,
            'available_stock'    => $qty,
            'min_required_stock' => $min_stock,
            'unit_type'          => 'pieces',
            'purchase_price'     => $unit_price,
            'sale_price'         => 0.00,
            'supplier_info'      => $supplier,
            'batch_number'       => $invoice,
            'expiry_date'        => '1970-01-01',
            'status'             => $status_flag,
            'updated_at'         => current_time( 'mysql' )
        );

        if ( $item_id === 0 ) {
            // New Entry Record Pipeline Path
            $inserted = $wpdb->insert( $table_inventory, $data_payload );

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                
                // Write transaction immutable history log inside movements ledger
                $wpdb->insert(
                    $table_movements,
                    array(
                        'item_id'        => $new_id,
                        'movement_type'  => 'in',
                        'quantity'       => $qty,
                        'reference_type' => 'purchase_intake',
                        'reference_id'   => $invoice,
                        'remarks'        => 'Initial intake entry creation allocation.',
                        'logged_by'      => get_current_user_id(),
                        'created_at'     => current_time( 'mysql' )
                    )
                );
                
                wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=item_added' ) );
                exit;
            } else {
                error_log( "ARMS System Write Mismatch Log: " . $wpdb->last_error );
            }
        } else {
            // Modify Existing Database Configurations Node
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT available_stock FROM $table_inventory WHERE id = %d", $item_id ) );
            if ( $existing ) {
                $old_qty = intval( $existing->available_stock );

                $wpdb->update( $table_inventory, $data_payload, array( 'id' => $item_id ) );

                if ( $qty !== $old_qty ) {
                    $diff   = $qty - $old_qty;
                    $vector = ( $diff > 0 ) ? 'in' : 'out';
                    
                    $wpdb->insert(
                        $table_movements,
                        array(
                            'item_id'        => $item_id,
                            'movement_type'  => $vector,
                            'quantity'       => abs( $diff ),
                            'reference_type' => 'manual_correction',
                            'reference_id'   => $invoice,
                            'remarks'        => "Manual stock tracking balance correction: " . ( $diff > 0 ? '+' : '' ) . $diff . " units.",
                            'logged_by'      => get_current_user_id(),
                            'created_at'     => current_time( 'mysql' )
                        )
                    );
                }
                
                wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=item_updated' ) );
                exit;
            }
        }
    }
});

/*--------------------------------------------------------------
# 2. Tabular Master Stock Items View Render Engine
--------------------------------------------------------------*/
function arms_inventory_list_view() {
    global $wpdb;
    $table_inventory = $wpdb->prefix . 'arms_inventory';

    // Parse status feedback alerts matching custom structural UI definitions
    if ( isset( $_GET['notice'] ) ) {
        $notice = sanitize_key( $_GET['notice'] );
        if ( $notice === 'item_added' ) {
            echo '<div class="notice notice-success" style="padding: 12px; background: #f0fdf4; border-left: 4px solid #003376; margin-bottom: 20px; border-radius:4px;"><p style="margin:0; color:#003376; font-weight:600;">Purchase entry registered into database cluster maps successfully.</p></div>';
        } elseif ( $notice === 'item_updated' ) {
            echo '<div class="notice notice-success" style="padding: 12px; background: #f0fdf4; border-left: 4px solid #003376; margin-bottom: 20px; border-radius:4px;"><p style="margin:0; color:#003376; font-weight:600;">Asset structural configuration parameters saved.</p></div>';
        } elseif ( $notice === 'item_deleted' ) {
            echo '<div class="notice notice-success" style="padding: 12px; background: #f0fdf4; border-left: 4px solid #003376; margin-bottom: 20px; border-radius:4px;"><p style="margin:0; color:#003376; font-weight:600;">Asset listing deleted from active tables tracking registries.</p></div>';
        } elseif ( $notice === 'missing_data' ) {
            echo '<div class="notice notice-error" style="padding: 12px; background: #fff2f2; border-left: 4px solid #dc3232; margin-bottom: 20px; border-radius:4px;"><p style="margin:0; color:#dc3232; font-weight:600;">Validation Failure: SKU and Asset Name values cannot contain blank strings.</p></div>';
        } elseif ( $notice === 'duplicate_sku' ) {
            echo '<div class="notice notice-error" style="padding: 12px; background: #fff2f2; border-left: 4px solid #dc3232; margin-bottom: 20px; border-radius:4px;"><p style="margin:0; color:#dc3232; font-weight:600;">Conflict Error: An asset record already maps to this unique identifier SKU Key.</p></div>';
        }
    }

    $items = $wpdb->get_results( "SELECT * FROM $table_inventory ORDER BY id DESC" );
    
    // Evaluate low stock conditions
    $low_stock_alerts = array();
    foreach ( $items as $item ) {
        $q = intval( $item->available_stock );
        $t = intval( $item->min_required_stock );
        if ( $q <= $t ) {
            $low_stock_alerts[] = array( 'name' => $item->item_name, 'qty' => $q, 'min' => $t );
        }
    }

    if ( ! empty( $low_stock_alerts ) && ! empty( $items ) ) : ?>
        <div class="notice notice-error" style="padding: 15px; background: #fff2f2; border-left: 4px solid #dc3232; margin-bottom: 20px; border-radius: 6px;">
            <h4 style="margin: 0 0 8px 0; color: #dc3232; font-size: 14px; font-weight: 700;">⚠️ Critical Low Stock Warnings Detected</h4>
            <ul style="margin: 0; padding-left: 20px; color: #b91c1c; font-size: 13px; font-weight: 500;">
                <?php foreach ( $low_stock_alerts as $alert ) : ?>
                    <li><strong><?php echo esc_html( $alert['name'] ); ?></strong> is down to <strong><?php echo $alert['qty']; ?></strong> units (Safety limit: <?php echo $alert['min']; ?>).</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="arms-card-box" style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">

        <table class="wp-list-table widefat fixed striping posts arms-data-table" style="box-shadow:none; border:1px solid #f1f5f9; border-radius: 6px; overflow: hidden; width:100%;">
            <thead>
                <tr>
                    <th style="padding:14px 12px; font-weight:700; color:#475569; background: #f8fafc; width: 15%;">SKU</th>
                    <th style="padding:14px 12px; font-weight:700; color:#475569; background: #f8fafc; width: 35%;">Supply Item Designation</th>
                    <th style="padding:14px 12px; font-weight:700; color:#475569; text-align:center; background: #f8fafc; width: 12%;">Status Flag</th>
                    <th style="padding:14px 12px; font-weight:700; color:#475569; text-align:center; background: #f8fafc; width: 18%;">Action Handles</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="5" style="padding:24px; text-align:center; color:#94a3b8; font-weight:500;">No medical inventory assets discovered in mapping lookups.</td></tr>
                <?php else : ?>
                    <?php foreach ( $items as $item ) : 
                        $sku   = $item->sku;
                        $qty   = intval( $item->available_stock );
                        $min   = intval( $item->min_required_stock );
                        $name  = $item->item_name;

                        if ( $qty <= 0 ) {
                            $badge = '<span class="arms-badge arms-badge-inactive" style="background:#fee2e2; color:#ef4444; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700;">Depleted</span>';
                        } elseif ( $qty <= $min ) {
                            $badge = '<span class="arms-badge arms-badge-low" style="background:#fef3c7; color:#d97706; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700;">⚠️ Low Alert</span>';
                        } else {
                            $badge = '<span class="arms-badge arms-badge-active" style="background:#dcfce7; color:#003376; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700;">Stable</span>';
                        }

                        $view_url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=view&item=' . $item->id );
                        $edit_url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=edit&item=' . $item->id );
                        $del_url  = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&action=delete_item&item=' . $item->id );
                        $del_url  = wp_nonce_url( $del_url, 'arms_delete_item_action', 'arms_inv_del_nonce' );
                        ?>
                        <tr>
                            <td style="padding:12px; vertical-align:middle;"><code><?php echo esc_html( $sku ); ?></code></td>
                            <td style="padding:12px; vertical-align:middle; color:#0f172a;"><strong><?php echo esc_html( $name ); ?></strong></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;"><?php echo $badge; ?></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;">
                                <div style="display:flex; gap:6px; justify-content:center; align-items:center;">
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="arms-action-btn btn-view" style="padding:6px 12px; font-size:12px; font-weight:600; border-radius:6px; text-decoration:none; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; display:inline-flex; align-items:center; transition: all 0.2s;">
                                        <span class="dashicons dashicons-visibility" style="font-size:14px; width:14px; height:14px; margin-right:4px; line-height:1;"></span> View
                                    </a>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="arms-action-btn btn-edit" style="padding:6px 12px; font-size:12px; font-weight:600; border-radius:6px; text-decoration:none; background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; display:inline-flex; align-items:center; transition: all 0.2s;">
                                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px; margin-right:4px; line-height:1;"></span> Edit
                                    </a>
                                    <a href="<?php echo esc_url( $del_url ); ?>" class="arms-action-btn btn-delete" style="padding:6px 12px; font-size:12px; font-weight:600; border-radius:6px; text-decoration:none; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; display:inline-flex; align-items:center; transition: all 0.2s;" onclick="return confirm('Are you sure you want to completely remove this stock asset entry from database system maps?');">
                                        <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; margin-right:4px; line-height:1;"></span> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 3. Interactive Deep Dive Detail Sheet View (Single Record View)
--------------------------------------------------------------*/
function arms_inventory_single_item_view( $item_id = 0 ) {
    global $wpdb;
    $table_inventory = $wpdb->prefix . 'arms_inventory';
    $table_movements = $wpdb->prefix . 'arms_stock_movements';

    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_inventory WHERE id = %d", $item_id ) );

    if ( ! $item ) {
        echo '<div class="notice notice-error" style="padding:10px; background:#fff2f2; border-left:4px solid #dc3232; margin-bottom:20px;"><p style="margin:0; color:#dc3232; font-weight:600;">Requested medical asset configuration reference not found in target tables.</p></div>';
        return;
    }

    $history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_movements WHERE item_id = %d ORDER BY id DESC LIMIT 10", $item_id ) );
    ?>
    <div style="display:grid; grid-template-columns: 1fr; gap:20px;">

        <div class="arms-card-box" style="background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:#0284c7; background:#e0f2fe; padding:4px 8px; border-radius:4px; display:inline-block; margin-bottom:8px;"><?php echo esc_html( $item->category ); ?></span>
                <h2 style="margin:0 0 6px 0; font-size:22px; color:#0f172a; font-weight:700;"><?php echo esc_html( $item->item_name ); ?></h2>
                <p style="margin:0; font-family:monospace; color:#64748b; font-size:13px; font-weight:500;">System UUID Asset Key: <?php echo esc_html( $item->sku ); ?></p>
            </div>

            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600;">Available Registry Balance</span>
                    <strong style="font-size:24px; color:#0f172a; font-weight:700;"><?php echo intval( $item->available_stock ); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600;">Safety Buffer Limit</span>
                    <strong style="font-size:24px; color:#64748b; font-weight:700;"><?php echo intval( $item->min_required_stock ); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600;">Unit Base Price</span>
                    <strong style="font-size:24px; color:#003376; font-weight:700;">৳<?php echo number_format( floatval( $item->purchase_price ), 2 ); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600;">Status Condition</span>
                    <div style="margin-top:6px;">
                        <?php 
                        if ( intval( $item->available_stock ) <= 0 ) {
                            echo '<span class="arms-badge arms-badge-inactive" style="background:#fee2e2; color:#ef4444; padding:4px 12px; border-radius:4px; font-size:12px; font-weight:700;">Depleted</span>';
                        } elseif ( intval( $item->available_stock ) <= intval( $item->min_required_stock ) ) {
                            echo '<span class="arms-badge arms-badge-low" style="background:#fef3c7; color:#d97706; padding:4px 12px; border-radius:4px; font-size:12px; font-weight:700;">Low Supply Warning</span>';
                        } else {
                            echo '<span class="arms-badge arms-badge-active" style="background:#dcfce7; color:#003376; padding:4px 12px; border-radius:4px; font-size:12px; font-weight:700;">Optimal</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div style="margin-top:24px; padding-top:20px; border-top:1px solid #f1f5f9; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <h4 style="margin:0 0 6px 0; font-size:12px; color:#475569; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Distribution Supplier Identity</h4>
                    <p style="margin:0; font-size:14px; color:#0f172a; font-weight:600;"><?php echo !empty( $item->supplier_info ) ? esc_html( $item->supplier_info ) : 'No provider listed'; ?></p>
                </div>
                <div>
                    <h4 style="margin:0 0 6px 0; font-size:12px; color:#475569; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Invoice Tracking Reference ID</h4>
                    <p style="margin:0; font-size:14px; color:#0f172a; font-weight:600; font-family:monospace;"><?php echo !empty( $item->batch_number ) ? esc_html( $item->batch_number ) : 'No reference ID recorded'; ?></p>
                </div>
            </div>
        </div>

        <div class="arms-card-box" style="background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; font-weight:700; color:#0f172a;">Audit Trail: Last 10 Stock Movements</h3>
            
            <table class="wp-list-table widefat fixed striping posts arms-data-table" style="border:1px solid #f1f5f9; box-shadow:none; width:100%;">
                <thead>
                    <tr>
                        <th style="padding:12px 10px; background:#f8fafc; font-weight:700; color:#475569; width:20%;">Timestamp</th>
                        <th style="padding:12px 10px; background:#f8fafc; font-weight:700; color:#475569; width:15%;">Direction Vector</th>
                        <th style="padding:12px 10px; background:#f8fafc; font-weight:700; color:#475569; text-align:center; width:15%;">Quantity Delta</th>
                        <th style="padding:12px 10px; background:#f8fafc; font-weight:700; color:#475569; width:20%;">Reference Action</th>
                        <th style="padding:12px 10px; background:#f8fafc; font-weight:700; color:#475569; width:30%;">Operational Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $history ) ) : ?>
                        <tr><td colspan="5" style="padding:16px; text-align:center; color:#94a3b8; font-weight:500;">No history logs discovered for this structural key block.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $history as $log ) : 
                            $vector_badge = ( $log->movement_type === 'in' ) 
                                ? '<span class="arms-badge arms-badge-active" style="background:#dcfce7; color:#003376; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:700;">STOCK IN</span>'
                                : '<span class="arms-badge arms-badge-inactive" style="background:#fee2e2; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:700;">STOCK OUT</span>';
                            ?>
                            <tr>
                                <td style="padding:10px; font-size:12px; font-weight:500; color:#475569;"><?php echo esc_html( $log->created_at ); ?></td>
                                <td style="padding:10px; vertical-align:middle;"><?php echo $vector_badge; ?></td>
                                <td style="padding:10px; text-align:center; font-weight:700; color:#0f172a;"><?php echo intval( $log->quantity ); ?></td>
                                <td style="padding:10px; font-size:12px;"><code><?php echo esc_html( $log->reference_type ); ?></code></td>
                                <td style="padding:10px; font-size:12px; color:#475569; font-weight:500;"><?php echo esc_html( $log->remarks ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 4. Purchase Entry Interface Form (Form Modification / Create Workspace)
--------------------------------------------------------------*/
function arms_inventory_form_view( $item_id = 0 ) {
    global $wpdb;
    $table_inventory = $wpdb->prefix . 'arms_inventory';

    $is_edit    = ( $item_id > 0 );
    $name       = $sku = $supplier = $invoice = '';
    $category   = 'PRP kits';
    $qty        = 100; 
    $min_stock  = 10; 
    $unit_price = 0.00;

    if ( $is_edit ) {
        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_inventory WHERE id = %d", $item_id ) );
        if ( $item ) {
            $sku        = $item->sku;
            $qty        = intval( $item->available_stock );
            $min_stock  = intval( $item->min_required_stock );
            $unit_price = floatval( $item->purchase_price );
            $category   = $item->category;
            $name       = $item->item_name;
            $supplier   = $item->supplier_info;
            $invoice    = $item->batch_number; 
        }
    }

    $categories = array( 'PRP kits', 'Needles', 'Acupuncture needles', 'Consumables', 'Rehab equipment', 'Medicines', 'Syringes' );
    ?>
    <div class="arms-card-box" style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">

        <form method="post" action="">
            <?php wp_nonce_field( 'arms_inv_action', 'arms_inv_nonce' ); ?>
            <input type="hidden" name="arms_action" value="save_item">
            <input type="hidden" name="item_id" value="<?php echo intval( $item_id ); ?>">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">SKU *</label>
                    <input type="text" name="item_sku" value="<?php echo esc_attr( $sku ); ?>" required style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;" <?php disabled($is_edit); ?>>
                    <?php if($is_edit): ?>
                        <input type="hidden" name="item_sku" value="<?php echo esc_attr( $sku ); ?>">
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Asset Name *</label>
                    <input type="text" name="item_name" value="<?php echo esc_attr( $name ); ?>" required style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Supply Group</label>
                    <select name="item_category" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 8px;">
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Unit Buy Cost (৳)</label>
                    <input type="number" step="0.01" name="item_unit_price" value="<?php echo esc_attr( $unit_price ); ?>" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Quantity Balance</label>
                    <input type="number" name="item_qty" value="<?php echo esc_attr( $qty ); ?>" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Minimum Threshold</label>
                    <input type="number" name="item_min_stock" value="<?php echo esc_attr( $min_stock ); ?>" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Supplier Info</label>
                    <input type="text" name="supplier_info" value="<?php echo esc_attr( $supplier ); ?>" placeholder="e.g., Apex Medical Supplies Ltd" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Invoice ID</label>
                    <input type="text" name="invoice_tracking" value="<?php echo esc_attr( $invoice ); ?>" placeholder="e.g., INV-2026-X99" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1; padding:0 10px;">
                </div>
            </div>

            <div style="padding-top:16px; border-top:1px solid #f1f5f9;">
                <button type="submit" class="arms-submit-btn">
                    <span class="dashicons dashicons-database-add" style="font-size:16px; vertical-align:middle; margin-right:4px;"></span> Save Entry
                </button>
            </div>
        </form>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 5. Fallback Structural Automation & Data Seeding System
--------------------------------------------------------------*/
function arms_seed_static_inventory_data_fallback() {

    return;
}