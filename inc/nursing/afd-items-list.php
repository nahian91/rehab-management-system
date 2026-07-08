<?php
if (!defined('ABSPATH')) exit;

/**
 * 1. BACKEND AJAX HANDLER
 */
add_action('wp_ajax_fd_toggle_item_status', 'fd_handle_status_toggle');
function fd_handle_status_toggle() {
    check_ajax_referer('fd_status_nonce', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized');

    $item_id = intval($_POST['item_id']);
    $new_status = sanitize_text_field($_POST['status']);

    if ($item_id > 0) {
        $updated = wp_update_post(['ID' => $item_id, 'post_status' => $new_status]);
        if (!is_wp_error($updated)) wp_send_json_success();
    }
    wp_send_json_error('Update failed');
}

/**
 * 2. MAIN DASHBOARD VIEW
 */
function fd_items_list() {
    // REMOVED 'meta_key' to ensure all items are fetched, including those without codes
    $items = get_posts([
        'post_type'   => 'food_item',
        'numberposts' => -1, 
        'post_status' => array('publish', 'pending', 'draft'),
    ]);
    
    $categories = get_terms(['taxonomy' => 'food_category', 'hide_empty' => false]);
    ?>

    <style>
        :root { --res-primary: #d63638; --res-dark: #1d2327; --res-border: #ccd0d4; --res-success: #46b450; --res-bg-soft: #fafafa; }
        .afd-dashboard { margin-top: 20px; max-width: 1200px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        .afd-filter-bar { display: flex; align-items: center; gap: 20px; background: #fff; padding: 15px 20px; border: 1px solid var(--res-border); border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,.02); }
        .afd-filter-group { display: flex; align-items: center; gap: 10px; }
        .afd-filter-group label { font-weight: 700; color: var(--res-dark); font-size: 13px; }
        .afd-filter-select { border: 1px solid var(--res-border); border-radius: 6px; padding: 5px 12px; height: 38px; min-width: 160px; cursor: pointer; }
        #fd-items-table { border: 1px solid var(--res-border); background: #fff; border-radius: 8px; width: 100%; border-collapse: collapse; overflow: hidden; }
        #fd-items-table thead th { background: var(--res-bg-soft); padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; color: #50575e; border-bottom: 2px solid #f0f0f1; letter-spacing: 0.5px; }
        #fd-items-table td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f0f0f1; }
        .fd-switch { position: relative; display: inline-block; width: 42px; height: 22px; }
        .fd-switch input { opacity: 0; width: 0; height: 0; }
        .fd-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .fd-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .fd-slider { background-color: var(--res-success); }
        input:checked + .fd-slider:before { transform: translateX(20px); }
        .fd-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; border: 1px solid #f5c2c2; background: #fff9f9; color: var(--res-primary); }
        .fd-btn { padding: 8px 12px; border-radius: 6px; border: 1px solid #dcdcde; background: #fff; color: #2c3338; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 13px; transition: 0.2s; }
        .fd-btn:hover { color: var(--res-primary); border-color: var(--res-primary); background: #fff9f9; }
        .fd-item-img { border-radius: 6px; border: 1px solid #eee; object-fit: cover; }
        .fd-no-img { width: 50px; height: 50px; background: #f0f0f1; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #ccd0d4; }
        .dataTables_wrapper .dataTables_filter input { border: 1px solid var(--res-border); border-radius: 6px; padding: 8px 12px; width: 250px; outline: none; }
    </style>

    <div class="wrap afd-dashboard">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h1 style="margin:0; font-weight: 800; font-size: 24px; color: var(--res-dark);">Menu Items</h1>
                <p style="color: #646970; margin: 5px 0 0;"><?php echo count($items); ?> Total items in catalog.</p>
            </div>
            <a href="?page=awesome_food_delivery&tab=items&sub=add" class="button button-primary" style="background:var(--res-primary); border:none; padding: 8px 20px; height: auto; font-weight: 600; border-radius: 6px;">+ Add New Item</a>
        </div>

        <div class="afd-filter-bar">
            <div class="afd-filter-group">
                <label>Category:</label>
                <select id="cat-filter" class="afd-filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat) echo '<option value="'.$cat->name.'">'.$cat->name.'</option>'; ?>
                </select>
            </div>
            <div class="afd-filter-group">
                <label for="visibility-filter">Visibility Status:</label>
                <select id="visibility-filter" class="afd-filter-select">
                    <option value="">All Statuses</option>
                    <option value="Live">Live (Published)</option>
                    <option value="Hidden">Hidden (Draft/Pending)</option>
                </select>
            </div>
            <div id="custom-search-wrap" style="margin-left:auto;"></div>
        </div>

        <table id="fd-items-table" class="display nowrap">
            <thead>
                <tr>
                    <th width="60">Preview</th>
                    <th>Item No</th>
                    <th>Item Info</th>
                    <th>Category</th>
                    <th width="80">Visibility</th>
                    <th width="100">Price</th>
                    <th width="150" style="text-align:right;">Actions</th>
                    <th style="display:none;">FilterKey</th> 
                </tr>
            </thead>
            <tbody>
                <?php if($items): foreach($items as $item): 
                    $price = get_post_meta($item->ID, 'price', true);
                    $item_code = get_post_meta($item->ID, 'fd_item_code', true); 
                    $cats = wp_get_post_terms($item->ID, 'food_category');
                    $is_published = ($item->post_status === 'publish');
                    $status_label = $is_published ? 'Live' : 'Hidden';
                    
                    // Logic: Use meta value for sorting, 9999 for items without codes
                    $sort_order = (!empty($item_code)) ? intval($item_code) : 9999;
                ?>
                <tr id="item-row-<?php echo $item->ID; ?>">
                    <td>
                        <?php if (has_post_thumbnail($item->ID)): ?>
                            <?php echo get_the_post_thumbnail($item->ID, [50, 50], ['class' => 'fd-item-img']); ?>
                        <?php else: ?>
                            <div class="fd-no-img"><span class="dashicons dashicons-format-image"></span></div>
                        <?php endif; ?>
                    </td>
                    <td data-order="<?php echo $sort_order; ?>">
                        <?php if (!empty($item_code)): ?>
                            <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html($item_code); ?></code>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="font-size:15px; color: var(--res-dark);"><?php echo esc_html($item->post_title); ?></strong><br>
                        <code style="font-size: 10px; background: #f0f0f1; padding: 1px 4px; border-radius: 3px;">#<?php echo $item->ID; ?></code>
                    </td>
                    <td>
                        <?php if(!empty($cats)): ?>
                            <span class="fd-badge"><?php echo esc_html($cats[0]->name); ?></span>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td>
                        <label class="fd-switch">
                            <input type="checkbox" class="fd-status-toggle" data-id="<?php echo $item->ID; ?>" <?php checked($is_published); ?>>
                            <span class="fd-slider"></span>
                        </label>
                    </td>
                    <td><strong style="font-size:16px; color: var(--res-primary);"><?php echo number_format((float)$price, 2); ?> £</strong></td>
                    <td align="right">
                        <div style="display: flex; gap: 5px; justify-content: flex-end;">
                            <a class="fd-btn" href="?page=awesome_food_delivery&tab=items&sub=edit&item=<?php echo $item->ID; ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <a class="fd-btn" style="color:#d63638;" onclick="if(confirm('Delete this item?')){ $(this).closest('tr').fadeOut(); return true; }" href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=fd_delete_item&item='.$item->ID), 'fd_delete_item_'.$item->ID); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </a>
                        </div>
                    </td>
                    <td style="display:none;"><?php echo $status_label; ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($){
        if ($.fn.DataTable) {
            var table = $('#fd-items-table').DataTable({
                "pageLength": 20,
                "order": [[1, "asc"]], 
                "dom": '<"top"f>rt<"bottom"ip><"clear">',
                "columnDefs": [
                    { "type": "num", "targets": [1] },
                    { "orderable": false, "targets": [0, 4, 6] },
                    { "visible": false, "targets": [7] }
                ],
                "language": { "search": "", "searchPlaceholder": "Search menu items..." }
            });

            $('.dataTables_filter').appendTo('#custom-search-wrap');

            $('#cat-filter, #visibility-filter').on('change', function(){
                table.column(3).search($('#cat-filter').val())
                     .column(7).search($('#visibility-filter').val() ? '^' + $('#visibility-filter').val() + '$' : '', true, false)
                     .draw();
            });

            $('.fd-status-toggle').on('change', function(){
                var $this = $(this);
                var $row = $this.closest('tr');
                var isActive = $this.is(':checked');
                $this.closest('.fd-switch').css('opacity', '0.5');

                $.post(ajaxurl, {
                    action: 'fd_toggle_item_status',
                    item_id: $this.data('id'),
                    status: isActive ? 'publish' : 'pending',
                    nonce: '<?php echo wp_create_nonce("fd_status_nonce"); ?>'
                }, function(res) {
                    $this.closest('.fd-switch').css('opacity', '1');
                    if(res.success) {
                        table.cell($row, 7).data(isActive ? 'Live' : 'Hidden').draw(false);
                    } else {
                        alert('Error: Could not update status.');
                        $this.prop('checked', !isActive);
                    }
                });
            });
        }
    });
    </script>
    <?php
}