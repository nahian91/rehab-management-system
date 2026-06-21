<?php
if(!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
# View Item - Restaurant Dashboard Style
--------------------------------------------------------------*/
function fd_view_item_tab($item_id){

    $item = get_post($item_id);
    if(!$item){
        echo '<div class="notice notice-error"><p>Item not found.</p></div>';
        return;
    }

    $price      = get_post_meta($item_id,'price',true);
    $cats       = wp_get_post_terms($item_id,'food_category');
    $extra_ids  = get_post_meta($item_id,'fd_item_extras',true);
    $extras_all = get_option('fd_extras',[]);
    ?>

    <style>
        :root { 
            --res-primary: #d63638; 
            --res-dark: #1d2327;    
            --res-border: #ccd0d4; 
        }

        .afd-view-wrapper { margin-top: 20px; max-width: 900px; }
        
        /* Header Area */
        .afd-view-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .afd-view-header h1 { margin: 0; font-size: 24px; color: var(--res-dark); }

        /* Card System */
        .afd-card { background: #fff; border: 1px solid var(--res-border); border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
        .afd-card-head { padding: 12px 20px; border-bottom: 1px solid #f0f0f1; background: #fafafa; }
        .afd-card-head h3 { margin: 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #646970; }
        .afd-card-body { padding: 25px; display: grid; grid-template-columns: 200px 1fr; gap: 30px; }

        /* Badges */
        .afd-tag { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; margin: 0 5px 5px 0; background: #f0f0f1; color: var(--res-dark); border: 1px solid #dcdcde; }
        .afd-tag-cat { background: #fff9f9; color: var(--res-primary); border-color: #f5c2c2; }
        
        /* UI Elements */
        .afd-price-display { font-size: 28px; font-weight: 800; color: var(--res-primary); margin-bottom: 15px; display: block; }
        .afd-img-frame { border: 1px solid var(--res-border); border-radius: 8px; padding: 5px; background: #fff; width: 100%; height: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .afd-description { line-height: 1.6; color: #50575e; font-size: 14px; margin-top: 0; }
        
        .btn-edit { background: var(--res-primary) !important; color: #fff !important; border: none !important; padding: 8px 20px !important; border-radius: 4px !important; text-decoration: none; font-weight: 600; }
        .btn-back { text-decoration: none; color: #646970; font-weight: 500; display: inline-flex; align-items: center; margin-top: 10px; }
        .btn-back:hover { color: var(--res-primary); }
    </style>

    <div class="wrap afd-view-wrapper">
        <div class="afd-view-header">
            <h1><?php echo esc_html($item->post_title); ?></h1>
            <a href="?page=awesome_food_delivery&tab=items&sub=edit&item=<?php echo $item_id; ?>" class="btn-edit">
                <span class="dashicons dashicons-edit" style="font-size:17px; margin-top:3px;"></span> Edit Item
            </a>
        </div>

        <div class="afd-card">
            <div class="afd-card-head"><h3>Product Details</h3></div>
            <div class="afd-card-body">
                
                <div class="afd-view-sidebar">
                    <?php if(has_post_thumbnail($item_id)): ?>
                        <?php echo get_the_post_thumbnail($item_id, 'medium', ['class' => 'afd-img-frame']); ?>
                    <?php else: ?>
                        <div class="afd-img-frame" style="height:180px; background:#f0f0f1; display:flex; align-items:center; justify-content:center; color:#a7aaad;">
                            <span class="dashicons dashicons-format-image" style="font-size:48px; width:48px; height:48px;"></span>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top:20px; text-align:center;">
                        <span class="afd-price-display"><?php echo number_format(floatval($price), 2, ',', '.'); ?> £</span>
                        <small style="text-transform:uppercase; color:#a7aaad; font-weight:700; letter-spacing:1px;">Base Price</small>
                    </div>
                </div>

                <div class="afd-view-main">
                    <div style="margin-bottom:25px;">
                        <h4 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#a7aaad;">Description</h4>
                        <p class="afd-description"><?php echo nl2br(esc_html($item->post_content ?: 'No description provided for this item.')); ?></p>
                    </div>

                    <div style="margin-bottom:25px;">
                        <h4 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#a7aaad;">Category</h4>
                        <?php if($cats): foreach($cats as $c): ?>
                            <span class="afd-tag afd-tag-cat"><?php echo esc_html($c->name); ?></span>
                        <?php endforeach; else: echo '<span style="color:#a7aaad;">—</span>'; endif; ?>
                    </div>

                    <div>
                        <h4 style="margin:0 0 10px; font-size:12px; text-transform:uppercase; color:#a7aaad;">Available Extras</h4>
                        <?php if($extra_ids): foreach($extra_ids as $id): if(isset($extras_all[$id])): ?>
                            <span class="afd-tag"><?php echo esc_html($extras_all[$id]['name']); ?></span>
                        <?php endif; endforeach; else: echo '<span style="color:#a7aaad;">No extras assigned.</span>'; endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <a href="?page=awesome_food_delivery&tab=items&sub=all" class="btn-back">
            <span class="dashicons dashicons-arrow-left-alt" style="margin-right:8px;"></span> Back to Menu List
        </a>
    </div>
    <?php
}