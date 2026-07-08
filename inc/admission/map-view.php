<?php 

/**
 * Visual Spatial Infrastructure Occupancy Mapping Engine Matrix
 */
function arms_render_spatial_occupancy_map() {
    global $wpdb;

    // Define your physical hospital/rehab real estate blueprints map manually here:
    $defined_cabins = array( '401', '402', '403', '404', '405', '501', '502', '503', '504', '505' );
    $defined_beds   = array( 'Bed-01', 'Bed-02', 'Bed-03', 'Bed-04', 'Bed-05', 'Bed-06', 'Bed-07', 'Bed-08', 'Bed-09', 'Bed-12' );

    // Fetch only active staying patients from DB records
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $table_patients   = $wpdb->prefix . 'arms_patients';

    $active_stays = $wpdb->get_results(
        "SELECT a.id, a.room_type, a.room_no, a.ward_bed_no, p.name as patient_name, p.mobile 
         FROM $table_admissions a 
         LEFT JOIN $table_patients p ON a.patient_id = p.id 
         WHERE a.discharge_date IS NULL OR a.discharge_date = '0000-00-00 00:00:00'"
    );

    // Map database values for high-speed tracking arrays
    $occupied_cabins = array();
    $occupied_wards  = array();

    if ( ! empty( $active_stays ) ) {
        foreach ( $active_stays as $stay ) {
            if ( 'Cabin' === $stay->room_type && ! empty( $stay->room_no ) ) {
                // Normalize formatting to match string maps cleanly
                $clean_room = str_replace( array('Cabin-', 'cabin-', ' '), '', $stay->room_no );
                $occupied_cabins[ $clean_room ] = $stay;
            } elseif ( 'Ward Bed' === $stay->room_type && ! empty( $stay->ward_bed_no ) ) {
                $clean_bed = str_replace( array('Ward-B / ', 'Ward-A / ', ' '), '', $stay->ward_bed_no );
                $occupied_wards[ $clean_bed ] = $stay;
            }
        }
    }
    ?>
    <style>
        .arms-map-container { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; font-family: sans-serif; box-sizing: border-box; }
        .arms-map-legend { display: flex; gap: 20px; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .legend-color { width: 18px; height: 18px; border-radius: 4px; }
        .color-blank { background: #ef4444; border: 1px solid #ef4444; } /* Red for Available/Blank Room */
        .color-full { background: #16a34a; border: 1px solid #16a34a; }  /* Green for Occupied/Full Room */
        
        .section-map-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 24px 0 12px 0; border-left: 4px solid #2563eb; padding-left: 8px; }
        .spatial-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 30px; }
        
        .spatial-card { border-radius: 8px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; min-height: 100px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: transform 0.2s; text-decoration: none !important; }
        .spatial-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        /* Available Status Theme (Red) */
        .status-blank { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .status-blank .status-pill { background: #ef4444; color: #fff; }
        /* Occupied Status Theme (Green) */
        .status-full { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .status-full .status-pill { background: #16a34a; color: #fff; }
        
        .card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .room-num { font-size: 18px; font-weight: 800; }
        .status-pill { padding: 3px 8px; font-size: 10px; font-weight: 700; border-radius: 9999px; text-transform: uppercase; }
        .patient-meta { font-size: 12px; font-weight: 600; margin-top: auto; line-height: 1.3; border-top: 1px dashed rgba(0,0,0,0.1); padding-top: 8px; }
    </style>

    <div class="arms-map-container">
        <div class="arms-map-legend">
            <div class="legend-item"><div class="legend-color color-blank"></div> Vacant Space (Blank / Available)</div>
            <div class="legend-item"><div class="legend-color color-full"></div> Patient Stay Logged (Full / Occupied)</div>
        </div>

        <div class="section-map-title">Luxury Suite Cabins Matrix Block</div>
        <div class="spatial-grid">
            <?php foreach ( $defined_cabins as $cabin ): 
                $is_occupied = isset( $occupied_cabins[ $cabin ] );
                $card_class  = $is_occupied ? 'status-full' : 'status-blank';
                $status_txt  = $is_occupied ? 'Full' : 'Blank';
                
                // If occupied, make card clickable straight into their edit/billing profile window sheet
                $href = $is_occupied ? admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=edit&patient=' . $occupied_cabins[ $cabin ]->id ) : '#';
                ?>
                <a href="<?php echo esc_url($href); ?>" class="spatial-card <?php echo $card_class; ?>">
                    <div class="card-top">
                        <span class="room-num">Cabin-<?php echo esc_html( $cabin ); ?></span>
                        <span class="status-pill"><?php echo $status_txt; ?></span>
                    </div>
                    <?php if ( $is_occupied ) : ?>
                        <div class="patient-meta">
                            👤 <?php echo esc_html( $occupied_cabins[ $cabin ]->patient_name ); ?><br>
                            📞 <?php echo esc_html( $occupied_cabins[ $cabin ]->mobile ); ?>
                        </div>
                    <?php else: ?>
                        <div class="patient-meta" style="color: #cbd5e1; border:none;">-- Empty Bed Bay --</div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="section-map-title">General Layout Ward Bed Bays Line</div>
        <div class="spatial-grid">
            <?php foreach ( $defined_beds as $bed ): 
                $is_occupied = isset( $occupied_wards[ $bed ] );
                $card_class  = $is_occupied ? 'status-full' : 'status-blank';
                $status_txt  = $is_occupied ? 'Full' : 'Blank';
                
                $href = $is_occupied ? admin_url( 'admin.php?page=rehab_management_system&tab=admission&sub=edit&patient=' . $occupied_wards[ $bed ]->id ) : '#';
                ?>
                <a href="<?php echo esc_url($href); ?>" class="spatial-card <?php echo $card_class; ?>">
                    <div class="card-top">
                        <span class="room-num"><?php echo esc_html( $bed ); ?></span>
                        <span class="status-pill"><?php echo $status_txt; ?></span>
                    </div>
                    <?php if ( $is_occupied ) : ?>
                        <div class="patient-meta">
                            👤 <?php echo esc_html( $occupied_wards[ $bed ]->patient_name ); ?><br>
                            📞 <?php echo esc_html( $occupied_wards[ $bed ]->mobile ); ?>
                        </div>
                    <?php else: ?>
                        <div class="patient-meta" style="color: #cbd5e1; border:none;">-- Empty Bed Bay --</div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}