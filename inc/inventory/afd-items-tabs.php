<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * =========================================================================
 * REHAB HOSPITAL INVENTORY AND SUPPLY MANAGEMENT CORE EXPERT LAYER
 * =========================================================================
 * Operational Workflow Coverage:
 * - Stock Items: Medicines, PRP kits, Syringes, Needles, Consumables, Equipment
 * - Stock In: Purchase entry tracking, Supplier info, Invoice records
 * - Stock Out: Usage tracking, Treatment consumption runs
 * - Alerts: Minimum stock warning parameters & Dashboard triggers
 * - Reports: Distribution charts, Usage reports, Total stock valuations
 */

/**
 * Main routing and rendering gateway function for the inventory management desk.
 */
function arms_inventory_tab() {
    // Force seeding of high-fidelity static operational records if database is empty
    arms_seed_static_inventory_data_fallback();

    $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';

    // Structural navigation matching hospital infrastructure workflow demands
    $tabs = array(
        'all'     => '📋 Stock Items',
        'add'     => '📥 Purchase Entry (Stock In)',
        'out'     => '📤 Usage Tracking (Stock Out)',
        'history' => '🔍 Audit Trail Logs',
        'reports' => '📊 Inventory Reports',
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
    } elseif ( $sub === 'out' ) {
        arms_inventory_stock_out_view();
    } elseif ( $sub === 'history' ) {
        $item_id = isset( $_GET['item'] ) ? intval( $_GET['item'] ) : 0;
        arms_inventory_history_view( $item_id );
    } elseif ( $sub === 'reports' ) {
        arms_inventory_reports_view();
    } else {
        arms_inventory_list_view();
    }

    echo '</div>';
}

/*--------------------------------------------------------------
# 1. Action Post Handler (Form Submissions Processing Core)
--------------------------------------------------------------*/
add_action( 'admin_init', function() {
    if ( ! isset( $_POST['arms_inv_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( sanitize_key( $_POST['arms_inv_nonce'] ), 'arms_inv_action' ) ) {
        wp_die( esc_html__( 'Security payload verification failure.', 'rehab-management-system' ) );
    }

    global $wpdb;
    $table_ledger = $wpdb->prefix . 'arms_ledger';
    $action       = sanitize_key( $_POST['arms_action'] );

    // PROCESS STOCK IN / ASSIST CONFIGURATION SAVE
    if ( $action === 'save_item' ) {
        $item_id     = isset( $_POST['item_id'] ) ? intval( $_POST['item_id'] ) : 0;
        $item_name   = sanitize_text_field( wp_unslash( $_POST['item_name'] ) );
        $sku         = sanitize_text_field( wp_unslash( $_POST['item_sku'] ) );
        $category    = sanitize_text_field( wp_unslash( $_POST['item_category'] ) );
        $qty         = intval( $_POST['item_qty'] );
        $min_stock   = intval( $_POST['item_min_stock'] );
        $unit_price  = floatval( $_POST['item_unit_price'] );
        $supplier    = sanitize_text_field( wp_unslash( $_POST['supplier_info'] ) );
        $invoice     = sanitize_text_field( wp_unslash( $_POST['invoice_tracking'] ) );

        if ( empty( $item_name ) || empty( $sku ) ) {
            wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=missing_data' ) );
            exit;
        }

        if ( $item_id === 0 ) {
            // New Entry Code Execution Path
            $meta_payload = array(
                'sku'         => $sku,
                'qty'         => $qty,
                'min_stock'   => $min_stock,
                'unit_price'  => $unit_price,
                'last_update' => current_time( 'mysql' )
            );

            $inserted = $wpdb->insert(
                $table_ledger,
                array(
                    'transaction_type' => 'inventory_asset',
                    'category'         => $category,
                    'amount'           => $unit_price * $qty,
                    'description'      => $item_name . ' | Supp: ' . $supplier . ' | Inv: ' . $invoice,
                    'reference_id'     => json_encode( $meta_payload ),
                    'logged_by'        => get_current_user_id(),
                    'transaction_date' => current_time( 'mysql' )
                )
            );

            if ( $inserted ) {
                $new_id = $wpdb->insert_id;
                $wpdb->insert(
                    $table_ledger,
                    array(
                        'transaction_type' => 'stock_log',
                        'category'         => 'Stock In',
                        'amount'           => floatval( $qty ),
                        'description'      => "Purchase Entry Intake Allocation. Supplier: {$supplier} | Invoice: {$invoice}",
                        'reference_id'     => (string) $new_id,
                        'logged_by'        => get_current_user_id(),
                        'transaction_date' => current_time( 'mysql' )
                    )
                );
                wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=item_added' ) );
                exit;
            }
        } else {
            // Modify Existing Configuration Elements
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_ledger WHERE id = %d", $item_id ) );
            if ( $existing ) {
                $meta = json_decode( $existing->reference_id, true );
                $old_qty = isset( $meta['qty'] ) ? intval( $meta['qty'] ) : 0;

                $meta['sku']        = $sku;
                $meta['qty']        = $qty;
                $meta['min_stock']  = $min_stock;
                $meta['unit_price'] = $unit_price;
                $meta['last_update']= current_time( 'mysql' );

                $wpdb->update(
                    $table_ledger,
                    array(
                        'category'         => $category,
                        'amount'           => $unit_price * $qty,
                        'description'      => $item_name . ' | Supp: ' . $supplier . ' | Inv: ' . $invoice,
                        'reference_id'     => json_encode( $meta ),
                        'transaction_date' => current_time( 'mysql' )
                    ),
                    array( 'id' => $item_id )
                );

                if ( $qty !== $old_qty ) {
                    $diff = $qty - $old_qty;
                    $vector = ( $diff > 0 ) ? 'Stock In' : 'Stock Out';
                    $wpdb->insert(
                        $table_ledger,
                        array(
                            'transaction_type' => 'stock_log',
                            'category'         => $vector,
                            'amount'           => floatval( abs( $diff ) ),
                            'description'      => "Manual stock tracking balance correction: " . ( $diff > 0 ? '+' : '' ) . $diff . " units.",
                            'reference_id'     => (string) $item_id,
                            'logged_by'        => get_current_user_id(),
                            'transaction_date' => current_time( 'mysql' )
                        )
                    );
                }
                wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=item_updated' ) );
                exit;
            }
        }
    }

    // PROCESS STOCK OUT TREATMENT DISBURSAL RUN
    if ( $action === 'stock_out' ) {
        $item_id   = isset( $_POST['target_item_id'] ) ? intval( $_POST['target_item_id'] ) : 0;
        $out_qty   = isset( $_POST['out_qty'] ) ? intval( $_POST['out_qty'] ) : 0;
        $tracking  = sanitize_text_field( wp_unslash( $_POST['usage_tracking_note'] ) );

        if ( $item_id > 0 && $out_qty > 0 ) {
            $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_ledger WHERE id = %d", $item_id ) );
            if ( $item ) {
                $meta = json_decode( $item->reference_id, true );
                $current_stock = isset( $meta['qty'] ) ? intval( $meta['qty'] ) : 0;

                if ( $out_qty > $current_stock ) {
                    wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=out&notice=insufficient_stock' ) );
                    exit;
                }

                $meta['qty'] = $current_stock - $out_qty;
                $meta['last_update'] = current_time( 'mysql' );
                $unit_price = isset( $meta['unit_price'] ) ? floatval( $meta['unit_price'] ) : 0.00;

                // Update Valuation Balance Parameters
                $wpdb->update(
                    $table_ledger,
                    array(
                        'amount'       => $unit_price * $meta['qty'],
                        'reference_id' => json_encode( $meta )
                    ),
                    array( 'id' => $item_id )
                );

                // Write Target Flow Log Audit Trail
                $wpdb->insert(
                    $table_ledger,
                    array(
                        'transaction_type' => 'stock_log',
                        'category'         => 'Stock Out',
                        'amount'           => floatval( $out_qty ),
                        'description'      => "Usage Tracking Run: " . $tracking,
                        'reference_id'     => (string) $item_id,
                        'logged_by'        => get_current_user_id(),
                        'transaction_date' => current_time( 'mysql' )
                    )
                );

                wp_redirect( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&notice=stock_disbursed' ) );
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
    $table_ledger = $wpdb->prefix . 'arms_ledger';

    // Parse status transactional feedback alerts
    if ( isset( $_GET['notice'] ) ) {
        $notice = sanitize_key( $_GET['notice'] );
        if ( $notice === 'item_added' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Purchase entry registered into database cluster maps successfully.</p></div>';
        } elseif ( $notice === 'item_updated' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Asset structural configuration parameters saved.</p></div>';
        } elseif ( $notice === 'stock_disbursed' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Stock consumption track applied to target profile successfully.</p></div>';
        } elseif ( $notice === 'missing_data' ) {
            echo '<div class="notice notice-error is-dismissible"><p>Validation Failure: SKU and Asset Name values cannot contain blank strings.</p></div>';
        }
    }

    $items = $wpdb->get_results( "SELECT * FROM $table_ledger WHERE transaction_type = 'inventory_asset' ORDER BY id DESC" );
    
    // Check for systemic threshold violations to generate global real-time dashboard notifications
    $low_stock_alerts = array();
    foreach ( $items as $item ) {
        $m = json_decode( $item->reference_id, true );
        $q = isset( $m['qty'] ) ? intval( $m['qty'] ) : 0;
        $t = isset( $m['min_stock'] ) ? intval( $m['min_stock'] ) : 0;
        if ( $q <= $t ) {
            $parts = explode( ' | Supp:', $item->description );
            $low_stock_alerts[] = array(
                'name' => isset( $parts[0] ) ? trim( $parts[0] ) : $item->description,
                'qty'  => $q,
                'min'  => $t
            );
        }
    }

    if ( ! empty( $low_stock_alerts ) ) : ?>
        <div class="notice notice-warning" style="border-left-color: #ef4444; background: #fff5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 8px 0; color: #b91c1c; font-size: 14px; font-weight: 600;">⚠️ Critical Low Stock Warnings Detected</h4>
            <ul style="margin: 0; padding-left: 20px; color: #7f1d1d; font-size: 13px;">
                <?php foreach ( $low_stock_alerts as $alert ) : ?>
                    <li><strong><?php echo esc_html( $alert['name'] ); ?></strong> is down to <strong><?php echo $alert['qty']; ?></strong> units (Minimum safety threshold limit configured: <?php echo $alert['min']; ?>).</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; width:100%;">
            <div><h3 style="margin:0; font-size:16px; color:#0f172a; font-weight:600;">Active Clinical Stock Registry Desk</h3></div>
            <div><a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=add' ) ); ?>" class="button button-primary" style="background:#0284c7; border-color:#0284c7; font-weight: 600;">+ New Purchase Intake</a></div>
        </div>

        <table class="wp-list-table widefat fixed striping posts" style="box-shadow:none; border:1px solid #f1f5f9; border-radius: 6px; overflow: hidden;">
            <thead>
                <tr>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">SKU Key</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">Supply Item Designation</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">Classification Group</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:right; background: #f8fafc;">Unit Cost</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:center; background: #f8fafc;">Current Balance</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:center; background: #f8fafc;">Status Flag</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:right; background: #f8fafc;">Action Handles</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="7" style="padding:24px; text-align:center; color:#94a3b8;">No medical inventory assets discovered in mapping lookups.</td></tr>
                <?php else : ?>
                    <?php foreach ( $items as $item ) : 
                        $meta  = json_decode( $item->reference_id, true );
                        $sku   = isset( $meta['sku'] ) ? esc_html( $meta['sku'] ) : 'N/A';
                        $qty   = isset( $meta['qty'] ) ? intval( $meta['qty'] ) : 0;
                        $min   = isset( $meta['min_stock'] ) ? intval( $meta['min_stock'] ) : 0;
                        $price = isset( $meta['unit_price'] ) ? floatval( $meta['unit_price'] ) : 0.00;

                        $parts = explode( ' | Supp:', $item->description );
                        $name  = isset( $parts[0] ) ? trim( $parts[0] ) : $item->description;

                        if ( $qty <= 0 ) {
                            $badge = '<span style="background:#fee2e2; color:#ef4444; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">Depleted</span>';
                        } elseif ( $qty <= $min ) {
                            $badge = '<span style="background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">⚠️ Low Alert</span>';
                        } else {
                            $badge = '<span style="background:#dcfce7; color:#16a34a; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">Stable</span>';
                        }
                        ?>
                        <tr>
                            <td style="padding:12px; vertical-align:middle;"><code><?php echo esc_html( $sku ); ?></code></td>
                            <td style="padding:12px; vertical-align:middle;"><strong><?php echo esc_html( $name ); ?></strong></td>
                            <td style="padding:12px; vertical-align:middle;"><span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; color:#475569; font-size:12px; font-weight:500;"><?php echo esc_html( $item->category ); ?></span></td>
                            <td style="padding:12px; vertical-align:middle; text-align:right; font-weight: 500;">$<?php echo esc_html( number_format( $price, 2 ) ); ?></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;"><strong><?php echo esc_html( $qty ); ?></strong> <span style="font-size:11px; color:#64748b;">(Min: <?php echo $min; ?>)</span></td>
                            <td style="padding:12px; vertical-align:middle; text-align:center;"><?php echo $badge; ?></td>
                            <td style="padding:12px; vertical-align:middle; text-align:right;">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=edit&item=' . $item->id ) ); ?>" class="button button-small">Edit Parameters</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory&sub=history&item=' . $item->id ) ); ?>" class="button button-small" style="color:#0284c7; border-color:#0284c7;">Flow Logs</a>
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
# 3. Purchase Entry Interface Form (Stock In Layer)
--------------------------------------------------------------*/
function arms_inventory_form_view( $item_id = 0 ) {
    global $wpdb;
    $table_ledger = $wpdb->prefix . 'arms_ledger';

    $is_edit = ( $item_id > 0 );
    $name = $sku = $supplier = $invoice = '';
    $category = 'Medicines';
    $qty = 100; $min_stock = 10; $unit_price = 0.00;

    if ( $is_edit ) {
        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_ledger WHERE id = %d", $item_id ) );
        if ( $item ) {
            $meta       = json_decode( $item->reference_id, true );
            $sku        = isset( $meta['sku'] ) ? $meta['sku'] : '';
            $qty        = isset( $meta['qty'] ) ? intval( $meta['qty'] ) : 0;
            $min_stock  = isset( $meta['min_stock'] ) ? intval( $meta['min_stock'] ) : 10;
            $unit_price = isset( $meta['unit_price'] ) ? floatval( $meta['unit_price'] ) : 0.00;
            $category   = $item->category;

            $parts    = explode( ' | Supp: ', $item->description );
            $name     = isset( $parts[0] ) ? trim( $parts[0] ) : $item->description;
            $rem      = isset( $parts[1] ) ? $parts[1] : '';
            $sub_parts= explode( ' | Inv: ', $rem );
            $supplier = isset( $sub_parts[0] ) ? trim( $sub_parts[0] ) : '';
            $invoice  = isset( $sub_parts[1] ) ? trim( $sub_parts[1] ) : '';
        }
    }

    $categories = array( 'Medicines', 'PRP kits', 'Syringes', 'Needles', 'Consumables', 'Equipment' );
    ?>
    <div style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; max-width:800px; margin:0 auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
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
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=inventory' ) ); ?>" class="button" style="margin-right:8px; height:38px; line-height:36px;">Cancel</a>
                <input type="submit" class="button button-primary" style="background:#0284c7; border-color:#0284c7; height:38px; line-height:36px; padding:0 20px;" value="Commit Supply Allocation Ledger Entry">
            </div>
        </form>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 4. Usage Tracking Engine Form View (Stock Out Layer)
--------------------------------------------------------------*/
function arms_inventory_stock_out_view() {
    global $wpdb;
    $table_ledger = $wpdb->prefix . 'arms_ledger';

    if ( isset( $_GET['notice'] ) && $_GET['notice'] === 'insufficient_stock' ) {
        echo '<div class="notice notice-error is-dismissible"><p>Transaction Blocked: Outbound disbursal volume exceeds current available vault reserves.</p></div>';
    }

    $items = $wpdb->get_results( "SELECT * FROM $table_ledger WHERE transaction_type = 'inventory_asset' ORDER BY id DESC" );
    ?>
    <div style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; max-width:650px; margin:0 auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; margin-bottom:20px; font-weight:600; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">📤 Log Treatment Consumption Run (Stock Out)</h3>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'arms_inv_action', 'arms_inv_nonce' ); ?>
            <input type="hidden" name="arms_action" value="stock_out">

            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Target Active Supply Asset Item *</label>
                <select name="target_item_id" required style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
                    <option value="">-- Select Active Vault Item --</option>
                    <?php foreach ( $items as $item ) : 
                        $meta = json_decode( $item->reference_id, true );
                        $qty  = isset( $meta['qty'] ) ? intval( $meta['qty'] ) : 0;
                        $parts = explode( ' | Supp:', $item->description );
                        $name  = isset( $parts[0] ) ? trim( $parts[0] ) : $item->description;
                        ?>
                        <option value="<?php echo intval( $item->id ); ?>" <?php disabled( $qty, 0 ); ?>>
                            <?php echo esc_html( $name ); ?> (Available Balance: <?php echo $qty; ?> Units)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Disbursed Quantity Volume *</label>
                <input type="number" min="1" name="out_qty" required style="width:100%; height:38px; border-radius:4px; border:1px solid #cbd5e1;">
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Treatment Consumption / Usage Tracking Audit Statements</label>
                <textarea name="usage_tracking_note" placeholder="e.g., Consumed 2 PRP Kits and 4 Syringes for patient session ID case record ref #7712." rows="4" style="width:100%; border-radius:4px; border:1px solid #cbd5e1; font-family:inherit;" required></textarea>
            </div>

            <div style="text-align:right;">
                <input type="submit" class="button button-primary" style="background:#e11d48; border-color:#e11d48; height:38px; line-height:36px; padding:0 24px; font-weight: 600;" value="Verify Treatment Asset Disbursal Run">
            </div>
        </form>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 5. Inventory Flow Trail Audits View Layout Component
--------------------------------------------------------------*/
function arms_inventory_history_view( $item_id = 0 ) {
    global $wpdb;
    $table_ledger = $wpdb->prefix . 'arms_ledger';

    $where = "WHERE transaction_type = 'stock_log'";
    if ( $item_id > 0 ) {
        $where .= $wpdb->prepare( " AND reference_id = %s", (string) $item_id );
    }

    $logs = $wpdb->get_results( "SELECT * FROM $table_ledger $where ORDER BY id DESC LIMIT 50" );
    ?>
    <div style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; margin-bottom:20px; font-weight:600; color:#0f172a;">🔍 Supply Vector Flow & Audit Log Registries</h3>
        <table class="wp-list-table widefat fixed striping posts" style="box-shadow:none; border:1px solid #f1f5f9; border-radius: 6px; overflow: hidden;">
            <thead>
                <tr>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">Timestamp Mark</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">Target Supply Item Reference</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">Vector Direction</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; text-align:right; background: #f8fafc;">Scalar Delta</th>
                    <th style="padding:14px 12px; font-weight:600; color:#475569; background: #f8fafc;">Audit Statement Context Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="5" style="padding:24px; text-align:center; color:#94a3b8;">No asset delta transactions saved in tracking databases.</td></tr>
                <?php else : ?>
                    <?php foreach ( $logs as $log ) : 
                        $target = intval( $log->reference_id );
                        $item_data = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM $table_ledger WHERE id = %d", $target ) );
                        $parts = explode( ' | Supp:', $item_data );
                        $item_name = isset( $parts[0] ) ? trim( $parts[0] ) : ( $item_data ? $item_data : '#' . $target );

                        $is_in = ( $log->category === 'Stock In' );
                        $text_color = $is_in ? '#16a34a' : '#ef4444';
                        ?>
                        <tr>
                            <td style="padding:12px; vertical-align:middle; color:#64748b;"><?php echo esc_html( $log->transaction_date ); ?></td>
                            <td style="padding:12px; vertical-align:middle;"><strong><?php echo esc_html( $item_name ); ?></strong></td>
                            <td style="padding:12px; vertical-align:middle;"><span style="color:<?php echo $text_color; ?>; font-weight:700;"><?php echo esc_html( $log->category ); ?></span></td>
                            <td style="padding:12px; vertical-align:middle; text-align:right; font-weight:700; color:<?php echo $text_color; ?>;"><?php echo ( $is_in ? '+' : '-' ) . intval( $log->amount ); ?></td>
                            <td style="padding:12px; vertical-align:middle; color:#475569;"><?php echo esc_html( $log->description ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 6. Analytics Metrics Report Desk Dashboard Layout Component
--------------------------------------------------------------*/
function arms_inventory_reports_view() {
    global $wpdb;
    $table_ledger = $wpdb->prefix . 'arms_ledger';

    $items = $wpdb->get_results( "SELECT * FROM $table_ledger WHERE transaction_type = 'inventory_asset'" );

    $total_items      = 0;
    $gross_valuation  = 0.00;
    $category_counts  = array();
    $low_stock_count  = 0;

    foreach ( $items as $item ) {
        $meta  = json_decode( $item->reference_id, true );
        $qty   = isset( $meta['qty'] ) ? intval( $meta['qty'] ) : 0;
        $min   = isset( $meta['min_stock'] ) ? intval( $meta['min_stock'] ) : 0;
        $price = isset( $meta['unit_price'] ) ? floatval( $meta['unit_price'] ) : 0.00;

        $total_items     += $qty;
        $gross_valuation += ( $qty * $price );

        if ( ! isset( $category_counts[ $item->category ] ) ) {
            $category_counts[ $item->category ] = 0;
        }
        $category_counts[ $item->category ] += $qty;

        if ( $qty <= $min ) {
            $low_stock_count++;
        }
    }
    ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:24px;">
        <div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
            <span style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; display:block; margin-bottom:6px;">Gross Vault Stocks Volume</span>
            <strong style="font-size:24px; color:#0f172a;"><?php echo $total_items; ?> Total Units</strong>
        </div>
        <div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
            <span style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; display:block; margin-bottom:6px;">Current Asset Valuation Valuation</span>
            <strong style="font-size:24px; color:#16a34a;">$<?php echo number_format( $gross_valuation, 2 ); ?></strong>
        </div>
        <div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
            <span style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; display:block; margin-bottom:6px;">Active Threshold Violations</span>
            <strong style="font-size:24px; color:<?php echo $low_stock_count > 0 ? '#ef4444' : '#0f172a'; ?>;"><?php echo $low_stock_count; ?> Items Low</strong>
        </div>
    </div>

    <div style="background:#fff; padding:24px; border-radius:8px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; color:#0f172a; font-weight:600;">Distribution Proportions Across Supply Categories</h3>
        <table class="wp-list-table widefat fixed striping posts" style="box-shadow:none; border:1px solid #f1f5f9; border-radius:6px; overflow:hidden;">
            <thead>
                <tr>
                    <th style="padding:12px; font-weight:600; background:#f8fafc; color:#475569;">Supply Category Group Name</th>
                    <th style="padding:12px; font-weight:600; text-align:right; background:#f8fafc; color:#475569;">Tracked Stock Quantities Inside Vault</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $category_counts ) ) : ?>
                    <tr><td colspan="2" style="padding:16px; text-align:center; color:#94a3b8;">No distribution data discovered in database tables.</td></tr>
                <?php else : ?>
                    <?php 
                    $workflow_categories = array( 'Medicines', 'PRP kits', 'Syringes', 'Needles', 'Consumables', 'Equipment' );
                    foreach ( $workflow_categories as $cat_name ) : 
                        $count_val = isset( $category_counts[$cat_name] ) ? $category_counts[$cat_name] : 0;
                        ?>
                        <tr>
                            <td style="padding:12px; vertical-align:middle;"><strong><?php echo esc_html( $cat_name ); ?></strong></td>
                            <td style="padding:12px; vertical-align:middle; text-align:right; font-weight:700; color:#334155;"><?php echo intval( $count_val ); ?> Units</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 7. High-Fidelity Static Mock Fallback Seeding Logic Engine
--------------------------------------------------------------*/
function arms_seed_static_inventory_data_fallback() {
    global $wpdb;
    $table_ledger = $wpdb->prefix . 'arms_ledger';

    // Verification check: Only run fallback seeding cycle if the asset matrix table returns 0 rows
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_ledger WHERE transaction_type = 'inventory_asset'" );
    if ( intval( $count ) > 0 ) {
        return; // Core records found. Shield runtime processing execution maps.
    }

    // High fidelity static dataset structure covering all required types
    $static_data = array(
        array(
            'sku'         => 'MED-XP-500',
            'name'        => 'Methylprednisolone Injection USP 40mg',
            'category'    => 'Medicines',
            'qty'         => 120,
            'min_stock'   => 25,
            'unit_price'  => 14.50,
            'supplier'    => 'Global Pharma Corp',
            'invoice'     => 'INV-2026-M01',
            'description' => 'Corticosteroid therapy stock allocation packages.'
        ),
        array(
            'sku'         => 'PRP-KIT-X4',
            'name'        => 'High Density PRP Harvesting Tubes Kit',
            'category'    => 'PRP kits',
            'qty'         => 45,
            'min_stock'   => 10,
            'unit_price'  => 32.00,
            'supplier'    => 'Biotech Matrix Solutions',
            'invoice'     => 'INV-2026-P09',
            'description' => 'Acellular separation tubes optimized for joint therapy runs.'
        ),
        array(
            'sku'         => 'SYR-3ML-LUER',
            'name'        => '3mL Sterile Luer Lock Syringes',
            'category'    => 'Syringes',
            'qty'         => 800,
            'min_stock'   => 150,
            'unit_price'  => 0.45,
            'supplier'    => 'MedLine Distribution',
            'invoice'     => 'INV-2026-S12',
            'description' => 'Single use medical grade clinical syringes packs.'
        ),
        array(
            'sku'         => 'NDL-21G-15',
            'name'        => 'Hypodermic Needles 21G x 1.5 inch',
            'category'    => 'Needles',
            'qty'         => 12, // Lowered intentionally to fire your '⚠️ Low Stock Alerts' mechanics
            'min_stock'   => 100,
            'unit_price'  => 0.15,
            'supplier'    => 'MedLine Distribution',
            'invoice'     => 'INV-2026-N03',
            'description' => 'Intramuscular delivery precision needle variables.'
        ),
        array(
            'sku'         => 'CNS-STER-GAU',
            'name'        => 'Sterile Gauze Sponges 4x4 Pack of 100',
            'category'    => 'Consumables',
            'qty'         => 60,
            'min_stock'   => 15,
            'unit_price'  => 8.75,
            'supplier'    => 'Apex Care Logistics',
            'invoice'     => 'INV-2026-C88',
            'description' => 'Pure cotton 12-ply non-woven surgical dressing supplies.'
        ),
        array(
            'sku'         => 'EQP-USUND-PT',
            'name'        => 'Intelect Mobile 2 Ultrasound Therapy Unit',
            'category'    => 'Equipment',
            'qty'         => 4,
            'min_stock'   => 1,
            'unit_price'  => 1850.00,
            'supplier'    => 'Chattanooga Medical Group',
            'invoice'     => 'INV-2026-E55',
            'description' => 'High frequency therapeutic physical ultrasound hardware equipment.'
        )
    );

    // Run structural serialization tracking injection mapping loop
    foreach ( $static_data as $data ) {
        $meta_payload = array(
            'sku'         => $data['sku'],
            'qty'         => $data['qty'],
            'min_stock'   => $data['min_stock'],
            'unit_price'  => $data['unit_price'],
            'last_update' => current_time( 'mysql' )
        );

        $wpdb->insert(
            $table_ledger,
            array(
                'transaction_type' => 'inventory_asset',
                'category'         => $data['category'],
                'amount'           => $data['unit_price'] * $data['qty'],
                'description'      => $data['name'] . ' | Supp: ' . $data['supplier'] . ' | Inv: ' . $data['invoice'],
                'reference_id'     => json_encode( $meta_payload ),
                'logged_by'        => get_current_user_id(),
                'transaction_date' => current_time( 'mysql' )
            )
        );

        $inserted_id = $wpdb->insert_id;

        // Populate corresponding tracking baseline log registries entries
        $wpdb->insert(
            $table_ledger,
            array(
                'transaction_type' => 'stock_log',
                'category'         => 'Stock In',
                'amount'           => floatval( $data['qty'] ),
                'description'      => "System initialization fallback data load loop verification run.",
                'reference_id'     => (string) $inserted_id,
                'logged_by'        => get_current_user_id(),
                'transaction_date' => current_time( 'mysql' )
            )
        );
    }
}