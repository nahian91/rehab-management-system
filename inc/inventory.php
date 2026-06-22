<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * =========================================================================
 * REHAB HOSPITAL INVENTORY AND SUPPLY MANAGEMENT CORE EXTENSION
 * =========================================================================
 * Operational Workflow Coverage:
 * - Stock Items: Medicines, PRP kits, Syringes, Needles, Acupuncture needles, Consumables, Rehab equipment
 * - Stock In: Purchase entry tracking, Supplier info, Invoice records
 * - Action Handles: Interactive View Modal, Edit Workspace, Secure Cascading Delete
 */

/**
 * Main routing and rendering gateway function for the inventory management desk.
 */
function arms_inventory_tab() {
    // Force seeding of static operational records if database is empty
    arms_seed_static_inventory_data_fallback();

    $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';

    // Strict sub-tab matrix filtering down to requested operational handles
    $tabs = array(
        'all' => '📋 All Stock Items',
        'add' => '📥 Add Purchase Entry',
    );

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        $url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=' . $k );
        $active_class = ( $sub === $k ) ? 'nav-tab-active' : '';
        echo '<a class="nav-tab ' . esc_attr( $active_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    echo '<div class="arms-sub-tab-content" style="margin-top: 20px;">';

    /* =========================================================================
       Sub-Module View Router Execution Matrix
       ========================================================================= */
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
    $table_inventory  = $wpdb->prefix . 'arms_inventory';
    $table_movements  = $wpdb->prefix . 'arms_stock_movements';

    // GET Request Handlers: Secure Row Deletion Routine
    if ( isset( $_GET['arms_inv_del_nonce'] ) && isset( $_GET['action'] ) && $_GET['action'] === 'delete_item' ) {
        if ( ! wp_verify_nonce( sanitize_key( $_GET['arms_inv_del_nonce'] ), 'arms_delete_item_action' ) ) {
            wp_die( esc_html__( 'Security signature mismatch validation error.', 'rehab-management-system' ) );
        }

        $delete_id = isset( $_GET['item'] ) ? intval( $_GET['item'] ) : 0;
        if ( $delete_id > 0 ) {
            // Fetch asset metadata trail prior to deletion for historical logging continuity
            $item_meta = $wpdb->get_row( $wpdb->prepare( "SELECT sku, available_stock FROM $table_inventory WHERE id = %d", $delete_id ) );
            
            if ( $item_meta ) {
                // Remove asset record node from core database tracking space
                $wpdb->delete( $table_inventory, array( 'id' => $delete_id ), array( '%d' ) );

                // Post a tracking marker into the ledger detailing structural depletion
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

        $status_flag = ( $qty <= 0 ) ? 'Out of Stock' : 'In Stock';

        if ( $item_id === 0 ) {
            // New Entry Path - Write to core arms_inventory table
            $inserted = $wpdb->insert(
                $table_inventory,
                array(
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
                )
            );

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                
                // Write transaction immutable history log inside arms_stock_movements
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
            }
        } else {
            // Modify Existing Configuration Elements
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT available_stock FROM $table_inventory WHERE id = %d", $item_id ) );
            if ( $existing ) {
                $old_qty = intval( $existing->available_stock );

                $wpdb->update(
                    $table_inventory,
                    array(
                        'item_code'          => $sku,
                        'item_name'          => $item_name,
                        'category'           => $category,
                        'sku'                => $sku,
                        'available_stock'    => $qty,
                        'min_required_stock' => $min_stock,
                        'purchase_price'     => $unit_price,
                        'supplier_info'      => $supplier,
                        'batch_number'       => $invoice,
                        'status'             => $status_flag,
                        'updated_at'         => current_time( 'mysql' )
                    ),
                    array( 'id' => $item_id )
                );

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

    // Parse status transactional feedback alerts
    if ( isset( $_GET['notice'] ) ) {
        $notice = sanitize_key( $_GET['notice'] );
        if ( $notice === 'item_added' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Purchase entry registered into database cluster maps successfully.</p></div>';
        } elseif ( $notice === 'item_updated' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Asset structural configuration parameters saved.</p></div>';
        } elseif ( $notice === 'item_deleted' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Asset listing deleted from active tables tracking registries.</p></div>';
        } elseif ( $notice === 'missing_data' ) {
            echo '<div class="notice notice-error is-dismissible"><p>Validation Failure: SKU and Asset Name values cannot contain blank strings.</p></div>';
        }
    }

    $items = $wpdb->get_results( "SELECT * FROM $table_inventory ORDER BY id DESC" );
    
    // Check for systemic threshold violations to generate global real-time dashboard notifications
    $low_stock_alerts = array();
    foreach ( $items as $item ) {
        $q = intval( $item->available_stock );
        $t = intval( $item->min_required_stock );
        if ( $q <= $t ) {
            $low_stock_alerts[] = array(
                'name' => $item->item_name,
                'qty'  => $q,
                'min'  => $t
            );
        }
    }

    if ( ! empty( $low_stock_alerts ) ) : ?>
        <div class="notice notice-warning arms-low-stock-alert-box" style="border-left-color: #ef4444; background: #fff5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px 0; color: #b91c1c; font-size: 14px; font-weight: 600;">⚠️ Critical Low Stock Warnings Detected</h4>
            <ul style="margin: 0; padding-left: 20px; color: #7f1d1d; font-size: 13px;">
                <?php foreach ( $low_stock_alerts as $alert ) : ?>
                    <li><strong><?php echo esc_html( $alert['name'] ); ?></strong> is down to <strong><?php echo $alert['qty']; ?></strong> units (Minimum safety threshold limit configured: <?php echo $alert['min']; ?>).</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="arms-card-container" style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div class="arms-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; width:100%;">
            <div><h3 style="margin:0; font-size:16px; color:#0f172a; font-weight:600;">Active Clinical Stock Registry Desk</h3></div>
            <div><a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=add' ) ); ?>" class="button button-primary arms-btn-primary" style="background:#0284c7; border-color:#0284c7; font-weight: 600;">+ New Purchase Intake</a></div>
        </div>

        <table class="wp-list-table widefat fixed striping posts arms-data-table" style="box-shadow:none; border:1px solid #f1f5f9; border-radius: 6px; overflow: hidden;">
            <thead>
                <tr>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc; width: 15%;">SKU Key</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc; width: 25%;">Supply Item Designation</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc; width: 15%;">Classification Group</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:right; background: #f8fafc; width: 10%;">Unit Cost</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:center; background: #f8fafc; width: 15%;">Current Balance</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:center; background: #f8fafc; width: 10%;">Status Flag</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:center; background: #f8fafc; width: 18%;">Action Handles</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="7" style="padding:24px; text-align:center; color:#94a3b8;">No medical inventory assets discovered in mapping lookups.</td></tr>
                <?php else : ?>
                    <?php foreach ( $items as $item ) : 
                        $sku   = $item->sku;
                        $qty   = intval( $item->available_stock );
                        $min   = intval( $item->min_required_stock );
                        $price = floatval( $item->purchase_price );
                        $name  = $item->item_name;

                        if ( $qty <= 0 ) {
                            $badge = '<span class="arms-badge arms-badge-depleted" style="background:#fee2e2; color:#ef4444; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">Depleted</span>';
                        } elseif ( $qty <= $min ) {
                            $badge = '<span class="arms-badge arms-badge-low" style="background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">⚠️ Low Alert</span>';
                        } else {
                            $badge = '<span class="arms-badge arms-badge-stable" style="background:#dcfce7; color:#16a34a; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">Stable</span>';
                        }

                        // Generate operational URLs for distinct item records
                        $view_url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=view&item=' . $item->id );
                        $edit_url = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=edit&item=' . $item->id );
                        $del_url  = admin_url( 'admin.php?page=rehab_management_system&tab=inventory&action=delete_item&item=' . $item->id );
                        $del_url  = wp_nonce_url( $del_url, 'arms_delete_item_action', 'arms_inv_del_nonce' );
                        ?>
                        <tr>
                            <td style="padding:12px; vertical-align:middle;"><code><?php echo esc_html( $sku ); ?></code></td>
                            <td style="padding:12px; vertical-align:middle;"><strong><?php echo esc_html( $name ); ?></strong></td>
                            <td style="padding:12px; vertical-align:middle;"><span class="arms-table-cat" style="background:#f1f5f9; padding:4px 8px; border-radius:4px; color:#475569; font-size:12px; font-weight:500;"><?php echo esc_html( $item->category ); ?></span></td>
                            <td style="padding:12px; vertical-align:middle; text-align:right; font-weight: 500;">$<?php echo esc_html( number_format( $price, 2 ) ); ?></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;"><strong><?php echo esc_html( $qty ); ?></strong> <span style="font-size:11px; color:#64748b;">(Min: <?php echo $min; ?>)</span></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;"><?php echo $badge; ?></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;">
                                <div class="arms-actions-cluster" style="display:flex; gap:6px; justify-content:center;">
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="button button-small" style="background:#f8fafc; border-color:#cbd5e1; color:#334155;">View</a>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" style="background:#f1f5f9; border-color:#cbd5e1; color:#0284c7;">Edit</a>
                                    <a href="<?php echo esc_url( $del_url ); ?>" class="button button-small" style="background:#fff5f5; border-color:#fecaca; color:#ef4444;" onclick="return confirm('Are you sure you want to completely remove this stock asset entry from database system maps?');">Delete</a>
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
        echo '<div class="notice notice-error"><p>Requested medical asset configuration reference not found in target tables.</p></div>';
        return;
    }

    // Fetch chronological historical transactional adjustments for this resource item allocation
    $history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_movements WHERE item_id = %d ORDER BY id DESC LIMIT 10", $item_id ) );
    ?>
    <div class="arms-detail-workspace" style="max-width:900px; margin: 0 auto; display:grid; grid-template-columns: 1fr; gap:20px;">
        
        <!-- Back Navigation Control -->
        <div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory' ) ); ?>" class="button" style="font-weight:500;">← Return to Master Stock Registry</a>
        </div>

        <!-- Master Specification Readout Card -->
        <div style="background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <span style="font-size:12px; font-weight:600; text-transform:uppercase; color:#0284c7; background:#e0f2fe; padding:4px 8px; border-radius:4px;"><?php echo esc_html( $item->category ); ?></span>
                    <h2 style="margin:8px 0 4px 0; font-size:22px; color:#0f172a; font-weight:700;"><?php echo esc_html( $item->item_name ); ?></h2>
                    <p style="margin:0; font-family:monospace; color:#64748b; font-size:13px;">System UUID Asset Key: <?php echo esc_html( $item->sku ); ?></p>
                </div>
                <div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=edit&item=' . $item->id ) ); ?>" class="button button-primary" style="background:#0284c7; border-color:#0284c7;">Modify Configuration Blueprint</a>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:500;">Available Registry Balance</span>
                    <strong style="font-size:24px; color:#0f172a;"><?php echo intval( $item->available_stock ); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:500;">Safety Buffer Limit</span>
                    <strong style="font-size:24px; color:#64748b;"><?php echo intval( $item->min_required_stock ); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:500;">Unit Base Price</span>
                    <strong style="font-size:24px; color:#10b981;">$<?php echo number_format( floatval( $item->purchase_price ), 2 ); ?></strong>
                </div>
                <div style="background:#f8fafc; padding:16px; border-radius:6px; border:1px solid #f1f5f9; text-align:center;">
                    <span style="display:block; font-size:12px; color:#64748b; margin-bottom:4px; font-weight:500;">Status Condition</span>
                    <div style="margin-top:6px;">
                        <?php 
                        if ( intval( $item->available_stock ) <= 0 ) {
                            echo '<span style="background:#fee2e2; color:#ef4444; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">Depleted</span>';
                        } elseif ( intval( $item->available_stock ) <= intval( $item->min_required_stock ) ) {
                            echo '<span style="background:#fef3c7; color:#d97706; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">Low Supply Warning</span>';
                        } else {
                            echo '<span style="background:#dcfce7; color:#16a34a; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">Optimal</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div style="margin-top:24px; padding-top:20px; border-top:1px solid #f1f5f9; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <h4 style="margin:0 0 6px 0; font-size:13px; color:#475569; font-weight:600;">Distribution Supplier Identity</h4>
                    <p style="margin:0; font-size:14px; color:#0f172a; font-weight:500;"><?php echo !empty( $item->supplier_info ) ? esc_html( $item->supplier_info ) : 'No provider listed'; ?></p>
                </div>
                <div>
                    <h4 style="margin:0 0 6px 0; font-size:13px; color:#475569; font-weight:600;">Invoice Tracking Reference ID</h4>
                    <p style="margin:0; font-size:14px; color:#0f172a; font-weight:500; font-family:monospace;"><?php echo !empty( $item->batch_number ) ? esc_html( $item->batch_number ) : 'No reference ID recorded'; ?></p>
                </div>
            </div>
        </div>

        <!-- System Stock Movements Log Sheet Tracking Element -->
        <div style="background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; font-weight:600; color:#0f172a;">Audit Trail: Last 10 Stock Movements</h3>
            
            <table class="wp-list-table widefat fixed striping posts" style="border:1px solid #f1f5f9; box-shadow:none;">
                <thead>
                    <tr>
                        <th style="padding:10px; background:#f8fafc; font-weight:600; color:#475569; width:20%;">Timestamp</th>
                        <th style="padding:10px; background:#f8fafc; font-weight:600; color:#475569; width:15%;">Direction Vector</th>
                        <th style="padding:10px; background:#f8fafc; font-weight:600; color:#475569; text-align:center; width:15%;">Quantity Delta</th>
                        <th style="padding:10px; background:#f8fafc; font-weight:600; color:#475569; width:20%;">Reference Action</th>
                        <th style="padding:10px; background:#f8fafc; font-weight:600; color:#475569; width:30%;">Operational Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $history ) ) : ?>
                        <tr><td colspan="5" style="padding:16px; text-align:center; color:#94a3b8;">No immutable history logging vectors discovered for this structural key block.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $history as $log ) : 
                            $vector_badge = ( $log->movement_type === 'in' ) 
                                ? '<span style="background:#dcfce7; color:#16a34a; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">STOCK IN</span>'
                                : '<span style="background:#fee2e2; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">STOCK OUT</span>';
                            ?>
                            <tr>
                                <td style="padding:10px; font-size:12px;"><?php echo esc_html( $log->created_at ); ?></td>
                                <td style="padding:10px; vertical-align:middle;"><?php echo $vector_badge; ?></td>
                                <td style="padding:10px; text-align:center; font-weight:600; color:#0f172a;"><?php echo intval( $log->quantity ); ?></td>
                                <td style="padding:10px; font-size:12px;"><code><?php echo esc_html( $log->reference_type ); ?></code></td>
                                <td style="padding:10px; font-size:12px; color:#475569;"><?php echo esc_html( $log->remarks ); ?></td>
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
# 4. Purchase Entry Interface Form (Stock In Layer)
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
            $invoice    = $item->batch_number; // Invoice tracking mapped dynamically onto setup
        }
    }

    $categories = array( 
        'PRP kits', 
        'Needles', 
        'Acupuncture needles', 
        'Consumables', 
        'Rehab equipment',
        'Medicines',
        'Syringes'
    );
    ?>
    <div class="arms-form-container" style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; max-width:800px; margin:0 auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; margin-bottom:20px; font-weight:600; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <?php echo $is_edit ? '⚙️ Modify Supply Configuration Matrix' : '📥 Record Supply Purchase Intake Entry (Stock In)'; ?>
        </h3>

        <form method="post" action="">
            <?php wp_nonce_field( 'arms_inv_action', 'arms_inv_nonce' ); ?>
            <input type="hidden" name="arms_action" value="save_item">
            <input type="hidden" name="item_id" value="<?php echo intval( $item_id ); ?>">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Unique Identifier SKU Code Key *</label>
                    <input type="text" name="item_sku" value="<?php echo esc_attr( $sku ); ?>" required style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Supply Item Asset Name *</label>
                    <input type="text" name="item_name" value="<?php echo esc_attr( $name ); ?>" required style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Supply Group Classification</label>
                    <select name="item_category" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Unit Buy Cost ($)</label>
                    <input type="number" step="0.01" name="item_unit_price" value="<?php echo esc_attr( $unit_price ); ?>" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Intake Quantity Volume Balance</label>
                    <input type="number" name="item_qty" value="<?php echo esc_attr( $qty ); ?>" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Minimum Safety Buffer Threshold (Low Alert Level)</label>
                    <input type="number" name="item_min_stock" value="<?php echo esc_attr( $min_stock ); ?>" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:24px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Supplier Distribution Info</label>
                    <input type="text" name="supplier_info" value="<?php echo esc_attr( $supplier ); ?>" placeholder="e.g., Apex Medical Supplies Ltd" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Invoice Tracking Reference ID</label>
                    <input type="text" name="invoice_tracking" value="<?php echo esc_attr( $invoice ); ?>" placeholder="e.g., INV-2026-X99" style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                </div>
            </div>

            <div style="text-align:right; padding-top:16px; border-top:1px solid #f1f5f9;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory' ) ); ?>" class="button arms-btn-cancel" style="margin-right:8px; height:38px; line-height:36px;">Cancel</a>
                <input type="submit" class="button button-primary arms-btn-submit" style="background:#0284c7; border-color:#0284c7; height:38px; line-height:36px; padding:0 20px;" value="Commit Supply Allocation Ledger Entry">
            </div>
        </form>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 5. Fallback Structural Automation & Data Seeding System
--------------------------------------------------------------*/
function arms_seed_static_inventory_data_fallback() {
    global $wpdb;
    $table_inventory = $wpdb->prefix . 'arms_inventory';

    // Verify if dedicated inventory table contains entries already
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_inventory" );
    if ( intval( $count ) > 0 ) {
        return; 
    }

    $seeds = array(
        array(
            'sku'      => 'PRP-KIT-H22',
            'name'     => 'Premium PRP Extraction Gel Kits',
            'cat'      => 'PRP kits',
            'qty'      => 45,
            'min'      => 15,
            'price'    => 24.50,
            'supplier' => 'BioMed Solutions Inc.',
            'inv'      => 'INV-99211'
        ),
        array(
            'sku'      => 'NDL-30G-X2',
            'name'     => 'Hypodermic Needle Tips 30G x 0.5"',
            'cat'      => 'Needles',
            'qty'      => 12, 
            'min'      => 20,
            'price'    => 0.35,
            'supplier' => 'Apex Logistics Corp',
            'inv'      => 'INV-88301'
        ),
        array(
            'sku'      => 'ACU-NDL-02',
            'name'     => 'Sterile Acupuncture Needles (0.25mm x 40mm)',
            'cat'      => 'Acupuncture needles',
            'qty'      => 800,
            'min'      => 150,
            'price'    => 0.12,
            'supplier' => 'Eastern Supply House',
            'inv'      => 'INV-2026-A1'
        ),
        array(
            'sku'      => 'CNS-SAB-01',
            'name'     => 'Antiseptic Alcohol Swab Wraps',
            'cat'      => 'Consumables',
            'qty'      => 2500,
            'min'      => 500,
            'price'    => 0.05,
            'supplier' => 'Global Care Distributors',
            'inv'      => 'INV-7731'
        ),
        array(
            'sku'      => 'REH-TNS-L4',
            'name'     => 'Digital 4-Channel TENS Nerve Stimulator Machine',
            'cat'      => 'Rehab equipment',
            'qty'      => 2, 
            'min'      => 2,
            'price'    => 185.00,
            'supplier' => 'MedTech Infrastructure Co.',
            'inv'      => 'INV-55102'
        )
    );

    foreach ( $seeds as $seed ) {
        $status_flag = ( $seed['qty'] <= 0 ) ? 'Out of Stock' : 'In Stock';
        
        $wpdb->insert(
            $table_inventory,
            array(
                'item_code'          => $seed['sku'],
                'item_name'          => $seed['name'],
                'generic_name'       => '',
                'category'           => $seed['cat'],
                'sku'                => $seed['sku'],
                'available_stock'    => $seed['qty'],
                'min_required_stock' => $seed['min'],
                'unit_type'          => 'pieces',
                'purchase_price'     => $seed['price'],
                'sale_price'         => 0.00,
                'supplier_info'      => $seed['supplier'],
                'batch_number'       => $seed['inv'],
                'expiry_date'        => '1970-01-01',
                'status'             => $status_flag,
                'updated_at'         => current_time( 'mysql' )
            )
        );
    }
}