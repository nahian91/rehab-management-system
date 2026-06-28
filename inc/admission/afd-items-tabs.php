<?php 

function arms_admission_tab() {
    global $wpdb;

    // 1. TIMING FIX: If a save action is happening right now, let's look ahead and intercept the ID
    $admission_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; 
    
    if ( $admission_id <= 0 && isset( $_POST['arms_save_admission_action'] ) ) {
        // If updating an existing record, grab it from the form's hidden primary key input
        if ( isset( $_POST['admission_id_pk'] ) && intval( $_POST['admission_id_pk'] ) > 0 ) {
            $admission_id = intval( $_POST['admission_id_pk'] );
        } else {
            // If creating a BRAND NEW record, look up the last inserted ID for this patient to display it immediately
            $selected_patient_id = isset( $_POST['arms_selected_patient_id'] ) ? intval( $_POST['arms_selected_patient_id'] ) : 0;
            if ( $selected_patient_id > 0 ) {
                $admission_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb}arms_admissions WHERE patient_id = %d ORDER BY id DESC LIMIT 1",
                    $selected_patient_id
                ) );
            }
        }
    }

    // Capture explicit Patient ID context parameter
    $patient_id = isset( $_GET['patient'] ) ? intval( $_GET['patient'] ) : 0;

    // Backward safety fallback lookup: if missing patient ID variable but have admission id, get it from database
    if ( $patient_id <= 0 && $admission_id > 0 ) {
        $patient_id = (int) $wpdb->get_var( $wpdb->prepare( 
            "SELECT patient_id FROM {$wpdb->prefix}arms_admissions WHERE id = %d", 
            $admission_id 
        ) );
    }

    // 2. State & Sub-tab Normalization Context
    $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';
    if ( isset( $_POST['arms_save_admission_action'] ) && $admission_id > 0 ) {
        $sub = 'edit'; // Override the view state to edit mode so the correct form loads
    }

    $tabs = array(
        'all' => 'All Admission',
        'add' => 'Add Admission',
        'map' => 'Cabin / Ward Map',
    );

    // Contextual Sub-tab Injection
    if ( $sub === 'edit' && $admission_id > 0 ) {
        $tabs['edit'] = 'Edit Admission (ID: ' . $admission_id . ')';
    } 
    elseif ( $sub === 'view' ) {
        $display_id = $patient_id > 0 ? $patient_id : 'Adm: ' . $admission_id;
        $tabs['view'] = 'Admission Details (ID: ' . $display_id . ')';
    }

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        $url_args = array(
            'page' => 'rehab_management_system',
            'tab'  => 'admission',
            'sub'  => $k
        );

        if ( $admission_id > 0 && ( $k === 'edit' || $k === 'view' ) ) {
            $url_args['id'] = $admission_id; 
            
            // CRITICAL FIX: Retain patient context inside generated sub-tab navigation hyperlinks
            if ( $patient_id > 0 ) {
                $url_args['patient'] = $patient_id;
            }
        }

        $url = add_query_arg( $url_args, admin_url( 'admin.php' ) );
        $active_class = ( $sub === $k ) ? 'nav-tab-active' : '';
        echo '<a class="nav-tab ' . esc_attr( $active_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    echo '<div class="arms-sub-tab-content" style="margin-top: 20px;">';

    /* Router Matrix Switcher */
    if ( $sub === 'add' ) {
        arms_add_edit_admission_form();
    } 
    elseif ( $sub === 'edit' ) {
        if ( $admission_id > 0 ) {
            arms_add_edit_admission_form( $admission_id );
        } else {
            echo '<div class="notice notice-error"><p>Invalid Parameter ID: Edits require a valid admission ledger entry ID.</p></div>';
        }
    } 
    elseif ( $sub === 'view' ) {
        /*--------------------------------------------------------------
        # ROUTED TO CUSTOM PATIENT CLINICAL CASE FILE PREVIEWER
        --------------------------------------------------------------*/
        if ( $patient_id > 0 ) {
            if ( function_exists( 'arms_view_patient_profile' ) ) {
                arms_view_patient_profile( $patient_id );
            } else {
                echo '<div class="notice notice-warning"><p>The primary patient case dashboard renderer is missing.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Invalid patient or admission ID provided for view generation.</p></div>';
        }
    } 
    elseif ( $sub === 'map' ) {
        if ( function_exists( 'arms_render_spatial_occupancy_map' ) ) {
            arms_render_spatial_occupancy_map();
        } else {
            echo '<div class="notice notice-warning"><p>Cabin / Ward Map component is missing.</p></div>';
        }
    }
    else {
        if ( function_exists( 'arms_admission_list_table' ) ) {
            arms_admission_list_table();
        } else {
            echo '<div class="notice notice-warning"><p>Admissions listing table component is missing.</p></div>';
        }
    }

    echo '</div>';
}