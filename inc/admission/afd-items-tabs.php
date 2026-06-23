<?php 

function arms_admission_tab() {
    $sub          = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';
    $patient_id   = isset( $_GET['patient'] ) ? intval( $_GET['patient'] ) : 0;
    $admission_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; // FIXED: Changed 'admission_id' to 'id'

    $tabs = array(
        'all' => 'All Admission',
        'add' => 'Add Admission',
        'map' => 'Cabin / Ward Map',
    );

    // Contextual Sub-tab Injection
    if ( $sub === 'edit' && $patient_id > 0 ) {
        $tabs['edit'] = 'Edit Admission (ID: ' . $patient_id . ')';
    } 
    elseif ( $sub === 'view' && $admission_id > 0 ) {
        $tabs['view'] = 'Admission Details (ADM-' . $admission_id . ')';
    }

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        $url_args = array(
            'page' => 'rehab_management_system',
            'tab'  => 'admission',
            'sub'  => $k
        );

        if ( $patient_id > 0 && $k === 'edit' ) {
            $url_args['patient'] = $patient_id;
        }
        if ( $admission_id > 0 && $k === 'view' ) {
            $url_args['id'] = $admission_id; // FIXED
        }

        $url = add_query_arg( $url_args, admin_url( 'admin.php' ) );
        $active_class = ( $sub === $k ) ? 'nav-tab-active' : '';
        echo '<a class="nav-tab ' . esc_attr( $active_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    echo '<div class="arms-sub-tab-content" style="margin-top: 20px;">';

    /* Router Matrix */
    if ( $sub === 'add' ) {
        arms_add_edit_admission_form();
    } 
    elseif ( $sub === 'edit' ) {
        arms_add_edit_admission_form( $patient_id );
    } 
    elseif ( $sub === 'view' ) {
        if ( $admission_id > 0 ) {
            if ( function_exists( 'arms_view_admission_details' ) ) {
                arms_view_admission_details( $admission_id );
            } else {
                echo '<div class="notice notice-warning"><p>Admission details view component is missing.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Invalid admission parameter ID.</p></div>';
        }
    } 
    elseif ( $sub === 'map' ) {
        arms_render_spatial_occupancy_map();
    }
    else {
        arms_admission_list_table();
    }

    echo '</div>';
}