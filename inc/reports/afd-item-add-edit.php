<?php
if (!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
# 1. Enqueue Scripts & Styles
--------------------------------------------------------------*/
add_action('admin_enqueue_scripts', function () {
    // Only load on our specific plugin page
    if (!isset($_GET['page']) || $_GET['page'] !== 'awesome_food_delivery') return;

    // WordPress Core Media Uploader
    wp_enqueue_media();

    // DataTables & FontAwesome
    wp_enqueue_style('fd-datatable-css', 'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css');
    wp_enqueue_script('fd-datatable-js', 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js', ['jquery'], null, true);
    wp_enqueue_style('fd-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
});

/*--------------------------------------------------------------
# 2. Main Add/Edit Function
--------------------------------------------------------------*/
function fd_add_edit_item_tab($edit_item_id = 0) {
    $item = $edit_item_id ? get_post($edit_item_id) : null;

    // --- FORM SUBMISSION LOGIC ---
    if (!empty($_POST) && isset($_POST['fd_add_item_nonce']) && wp_verify_nonce($_POST['fd_add_item_nonce'], 'fd_add_item')) {
        
        $title  = sanitize_text_field($_POST['fd_item_name']);
        $desc   = wp_kses_post($_POST['fd_item_desc']);
        $price  = floatval($_POST['fd_item_price']);
        $cat    = intval($_POST['fd_item_cat']);

        $item_code = sanitize_text_field($_POST['fd_item_code']);
        
        // Handle Dynamic Repeater Extras
        $repeater_extras = [];
        if (isset($_POST['fd_extra_name']) && is_array($_POST['fd_extra_name'])) {
            foreach ($_POST['fd_extra_name'] as $index => $name) {
                if (!empty($name)) {
                    $repeater_extras[] = [
                        'name'    => sanitize_text_field($name),
                        'price'   => floatval($_POST['fd_extra_price'][$index] ?? 0),
                        'img_id'  => intval($_POST['fd_extra_img_id'][$index] ?? 0),
                        'img_url' => esc_url_raw($_POST['fd_extra_img_url'][$index] ?? ''),
                    ];
                }
            }
        }

        $post_args = [
            'post_type'    => 'food_item',
            'post_title'   => $title,
            'post_content' => $desc,
            'post_status'  => 'publish'
        ];

        // Create or Update
        if ($item) {
            $post_args['ID'] = $edit_item_id;
            wp_update_post($post_args);
        } else {
            $edit_item_id = wp_insert_post($post_args);
        }

        if ($edit_item_id) {
            update_post_meta($edit_item_id, 'price', $price);
            update_post_meta($edit_item_id, 'fd_item_code', $item_code);
            update_post_meta($edit_item_id, 'fd_serial_no', $serial_no);
            wp_set_post_terms($edit_item_id, [$cat], 'food_category');
            update_post_meta($edit_item_id, 'fd_item_extras_repeater', $repeater_extras);
            
            // Thumbnail Logic
            if (isset($_POST['fd_item_image_id'])) {
                $img_id = intval($_POST['fd_item_image_id']);
                ($img_id > 0) ? set_post_thumbnail($edit_item_id, $img_id) : delete_post_thumbnail($edit_item_id);
            }
            
            echo '<div class="updated notice is-dismissible" style="border-left-color: #d63638;"><p><strong>Success:</strong> Menu item saved successfully!</p></div>';
            $item = get_post($edit_item_id); // Refresh data
        }
    }

    // --- PREPARE DATA FOR VIEW ---
    $title_val    = $item ? $item->post_title : '';
    $desc_val     = $item ? $item->post_content : '';
    $price_val    = $item ? get_post_meta($edit_item_id, 'price', true) : '';
    $item_code_val = $item ? get_post_meta($edit_item_id, 'fd_item_code', true) : '';
    $cat_id       = $item ? (wp_get_post_terms($edit_item_id, 'food_category', ['fields' => 'ids'])[0] ?? 0) : 0;
    $img_id       = $item ? get_post_thumbnail_id($edit_item_id) : 0;
    $img_url      = $img_id ? wp_get_attachment_url($img_id) : '';
    $saved_extras = $item ? get_post_meta($edit_item_id, 'fd_item_extras_repeater', true) : [];
    $categories   = get_terms(['taxonomy' => 'food_category', 'hide_empty' => false]);
    ?>

    <style>
        :root { --afd-red: #d63638; --afd-dark: #1d2327; --afd-border: #ccd0d4; }
        
        .afd-admin-container { margin-top: 25px; max-width: 1200px; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; }
        .afd-header-input { width: 100%; border: 1px solid var(--afd-border); border-radius: 6px; font-size: 22px; font-weight: 700; padding: 15px; margin-bottom: 25px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.07); outline: none; }
        .afd-header-input:focus { border-color: var(--afd-red); box-shadow: 0 0 0 1px var(--afd-red); }

        .afd-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; }
        
        /* Card Styling */
        .afd-card { background: #fff; border: 1px solid var(--afd-border); border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .afd-card-head { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; background: #fafafa; display: flex; justify-content: space-between; align-items: center; }
        .afd-card-head h3 { margin: 0; font-size: 13px; font-weight: 700; text-transform: uppercase; color: #646970; letter-spacing: 0.5px; }
        .afd-card-body { padding: 20px; }

        /* Repeater UI */
        .afd-repeater-row { display: grid; grid-template-columns: 50px 1fr 100px 40px; gap: 15px; align-items: center; background: #fff; border: 1px solid #f0f0f1; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        .afd-extra-thumb { width: 50px; height: 50px; background: #f0f0f1; border: 1px dashed #ccd0d4; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .afd-extra-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .afd-repeater-row input { border: 1px solid #ddd; padding: 8px; border-radius: 4px; font-size: 14px; }
        .btn-remove-row { color: #d63638; cursor: pointer; font-size: 18px; text-align: center; opacity: 0.6; transition: 0.2s; }
        .btn-remove-row:hover { opacity: 1; transform: scale(1.1); }
        .btn-add-row { background: #003376; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }

        /* Sidebar UI */
        .afd-sidebar-label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: #50575e; }
        .afd-image-uploader { border: 2px dashed #ccd0d4; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; background: #fafafa; transition: 0.2s; }
        .afd-image-uploader:hover { border-color: var(--afd-red); background: #fff8f8; }
        .afd-image-uploader img { max-width: 100%; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        /* Bottom Bar */
        .afd-footer-bar { position: sticky; bottom: 20px; background: #fff; padding: 20px; border-radius: 10px; border: 1px solid var(--afd-border); box-shadow: 0 -5px 20px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-top: 40px; z-index: 99; }
        .afd-btn-save { background: var(--afd-red) !important; color: #fff !important; padding: 0 35px !important; height: 46px !important; border:none !important; border-radius: 6px !important; font-weight: 700 !important; cursor: pointer; font-size: 15px !important; }
    </style>

    <div class="wrap afd-admin-container">
        <form method="post" id="afd-main-form">
            <?php wp_nonce_field('fd_add_item', 'fd_add_item_nonce'); ?>
            
            <input type="text" name="fd_item_name" class="afd-header-input" placeholder="Enter Food Name (e.g. Grilled Chicken Wings)" value="<?php echo esc_attr($title_val); ?>" required autofocus>

            <div class="afd-grid">
                <div class="afd-main-col">
                    <div class="afd-card">
                        <div class="afd-card-head"><h3>Item Description</h3></div>
                        <div class="afd-card-body">
                            <?php wp_editor($desc_val, 'fd_item_desc', ['textarea_rows' => 12, 'media_buttons' => false]); ?>
                        </div>
                    </div>

                    <div class="afd-card">
                        <div class="afd-card-head">
                            <h3>Variants</h3>
                            <button type="button" class="btn-add-row" id="afd-add-row-trigger"><i class="fa fa-plus-circle"></i> Add Variant</button>
                        </div>
                        <div class="afd-card-body">
                            <div id="afd-repeater-container">
                                <?php 
                                if (!empty($saved_extras)) {
                                    foreach ($saved_extras as $extra) {
                                        afd_render_row_html($extra);
                                    }
                                } else {
                                    afd_render_row_html(); // Default empty row
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="afd-sidebar-col">
                    <div class="afd-card">
                        <div class="afd-card-head"><h3>Food Photo</h3></div>
                        <div class="afd-card-body">
                            <input type="hidden" name="fd_item_image_id" id="fd_item_image_id" value="<?php echo esc_attr($img_id); ?>">
                            <div id="afd-main-img-btn" class="afd-image-uploader">
                                <div id="afd-main-img-preview">
                                    <?php if ($img_url): ?>
                                        <img src="<?php echo $img_url; ?>">
                                    <?php else: ?>
                                        <i class="fa-regular fa-image" style="font-size: 40px; color: #dcdcde;"></i>
                                        <p style="margin-top:10px; font-weight: 600; color: #646970;">Set Main Image</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p style="text-align:center; margin-top:10px;"><a href="#" id="afd-remove-main-img" style="color:var(--afd-red); text-decoration:none; font-size:12px; display:<?php echo $img_url ? 'inline-block' : 'none'; ?>;">Remove Photo</a></p>
                        </div>
                    </div>

                    <div class="afd-card">
    <div class="afd-card-head"><h3>Configuration</h3></div>
    <div class="afd-card-body">
        <div style="margin-bottom: 20px;">
            <div>
                <label class="afd-sidebar-label">Item Code</label>
                <input type="text" name="fd_item_code" class="afd-header-input" style="font-size:14px; padding:8px; margin:0;" value="<?php echo esc_attr($item_code_val); ?>" placeholder="E.g. FD-01">
            </div>
        </div>

        <label class="afd-sidebar-label">Base Price (£)</label>
        <input type="number" step="0.01" name="fd_item_price" class="afd-header-input" style="font-size:16px; padding:10px; margin-bottom:20px;" value="<?php echo esc_attr($price_val); ?>" placeholder="0.00">

        <label class="afd-sidebar-label">Menu Category</label>
        <select name="fd_item_cat" style="width:100%; height:40px; border-radius:5px; border-color:var(--afd-border);">
            <?php foreach ($categories as $c) : ?>
                <option value="<?php echo $c->term_id; ?>" <?php selected($cat_id, $c->term_id); ?>><?php echo $c->name; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                </div>
            </div>

            <div class="afd-footer-bar">
                <div style="color:#646970; font-size:13px;"><i class="fa fa-info-circle" style="color:var(--afd-red)"></i> Review all prices before saving.</div>
                <div>
                    <a href="?page=awesome_food_delivery" style="margin-right:20px; text-decoration:none; color:#646970; font-weight:600;">Discard</a>
                    <input type="submit" class="afd-btn-save" value="Save To Menu">
                </div>
            </div>
        </form>
    </div>

    <script type="text/template" id="afd-row-template">
        <?php afd_render_row_html(); ?>
    </script>

    <script>
    jQuery(document).ready(function($){
        
        // 1. Handle Main Image Upload
        $('#afd-main-img-btn').on('click', function(){
            var frame = wp.media({ title: 'Select Food Photo', button: { text: 'Use Image' }, multiple: false }).open();
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $('#fd_item_image_id').val(attachment.id);
                $('#afd-main-img-preview').html('<img src="'+attachment.url+'">');
                $('#afd-remove-main-img').show();
            });
        });

        $('#afd-remove-main-img').on('click', function(e){
            e.preventDefault();
            $('#fd_item_image_id').val('0');
            $('#afd-main-img-preview').html('<i class="fa-regular fa-image" style="font-size: 40px; color: #dcdcde;"></i><p>Set Main Image</p>');
            $(this).hide();
        });

        // 2. Add New Repeater Row
        $('#afd-add-row-trigger').on('click', function(e){
            e.preventDefault();
            var html = $('#afd-row-template').html();
            $(html).appendTo('#afd-repeater-container').hide().fadeIn(200);
        });

        // 3. Remove Repeater Row (Fixed Delegation Logic)
        $(document).on('click', '.btn-remove-row', function(e){
            e.preventDefault();
            var $container = $('#afd-repeater-container');
            var $row = $(this).closest('.afd-repeater-row');

            // Only remove if there's more than one row remaining
            if($container.find('.afd-repeater-row').length > 1){
                $row.fadeOut(200, function(){ 
                    $(this).remove(); 
                });
            } else {
                // If it's the last row, clear values instead of deleting
                $row.find('input[type="text"], input[type="number"]').val('');
                $row.find('input[type="hidden"]').val('');
                $row.find('.afd-extra-thumb').html('<i class="fa fa-plus" style="color:#ccd0d4; font-size:12px;"></i>');
            }
        });

        // 4. Handle Repeater Image Upload
        $(document).on('click', '.afd-extra-thumb', function(){
            var $row = $(this).closest('.afd-repeater-row');
            var frame = wp.media({ title: 'Extra Image', button: { text: 'Set Icon' }, multiple: false }).open();
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                $row.find('.extra-img-id').val(attachment.id);
                $row.find('.extra-img-url').val(attachment.url);
                $row.find('.afd-extra-thumb').html('<img src="'+attachment.url+'">');
            });
        });
    });
    </script>
    <?php
}

/**
 * Helper function to render a single row of the extras repeater
 */
function afd_render_row_html($data = []) {
    $name    = $data['name'] ?? '';
    $price   = $data['price'] ?? '';
    $img_id  = $data['img_id'] ?? '';
    $img_url = $data['img_url'] ?? '';
    ?>
    <div class="afd-repeater-row">
        <div class="afd-extra-thumb" title="Click to set icon">
            <?php if ($img_url): ?>
                <img src="<?php echo esc_url($img_url); ?>">
            <?php else: ?>
                <i class="fa fa-plus" style="color:#ccd0d4; font-size:12px;"></i>
            <?php endif; ?>
        </div>
        
        <input type="hidden" name="fd_extra_img_id[]" class="extra-img-id" value="<?php echo esc_attr($img_id); ?>">
        <input type="hidden" name="fd_extra_img_url[]" class="extra-img-url" value="<?php echo esc_url($img_url); ?>">
        
        <input type="text" name="fd_extra_name[]" placeholder="Extra Name (e.g. Extra Mayo)" value="<?php echo esc_attr($name); ?>" style="flex-grow:1;">
        <input type="number" step="0.01" name="fd_extra_price[]" placeholder="Price" value="<?php echo esc_attr($price); ?>" style="width:100px;">
        
        <div class="btn-remove-row" title="Delete Row"><i class="fa-solid fa-circle-minus"></i></div>
    </div>
    <?php
}