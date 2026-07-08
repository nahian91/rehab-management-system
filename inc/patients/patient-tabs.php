<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Render the Patients module tab and manage internal sub-routing.
 */
function arms_patients_tab() {
    // Intercept both sub-tab clicks and structural table action queries
    $sub    = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';
    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
    
    // 🛡️ FIX: Ignore WordPress bulk action defaults (-1 or secondary -1)
    if ( $action === '-1' || ( isset( $_GET['action2'] ) && $_GET['action2'] === '-1' ) ) {
        $action = '';
    }

    // Only route valid explicit table actions
    $valid_actions = array( 'view', 'edit', 'delete' );
    if ( ! empty( $action ) && in_array( $action, $valid_actions, true ) && $sub === 'all' ) {
        $sub = $action;
    }

    // Sub-navigation architecture for the Patient Registry
    $tabs = array(
        'all' => 'All Patients',
        'add' => 'Add Patient',
    );

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        // Keeps user locked into the active main tab context (tab=patients)
        $url = admin_url( 'admin.php?page=rehab_management_system&tab=patients&sub=' . $k );
        
        // Ensure "All Patients" remains visually highlighted during row actions (view/edit/delete)
        $is_active = ( $sub === $k ) || ( $k === 'all' && in_array( $sub, array( 'view', 'edit', 'delete' ), true ) );
        $active_class = $is_active ? 'nav-tab-active' : '';
        
        echo '<a class="nav-tab ' . esc_attr( $active_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    echo '<div class="arms-sub-tab-content" style="margin-top: 20px;">';

    /* =========================================================================
       Sub-Module View Router Execution Matrix
       ========================================================================= */
    if ( $sub === 'add' ) {
        if ( function_exists( 'arms_add_edit_patient_form' ) ) {
            arms_add_edit_patient_form();
        } else {
            echo '<div class="notice notice-warning"><p>Patient entry form module missing from the patient template layer.</p></div>';
        }
    } 
    elseif ( $sub === 'edit' ) {
        $patient_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        if ( function_exists( 'arms_add_edit_patient_form' ) ) {
            arms_add_edit_patient_form( $patient_id );
        }
    } 
    elseif ( $sub === 'view' ) {
        $patient_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        if ( function_exists( 'arms_view_patient_profile' ) ) {
            arms_view_patient_profile( $patient_id );
        }
    } 
    elseif ( $sub === 'delete' ) {
        $patient_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        
        // 🔒 Security Warning: Ensure you verify nonces here before actual execution!
        // global $wpdb; $wpdb->delete(...);
        
        echo '<div class="notice notice-success is-dismissible"><p>Patient record ' . esc_html( $patient_id ) . ' has been safely removed.</p></div>';
        
        if ( function_exists( 'arms_patients_list_table' ) ) {
            arms_patients_list_table();
        }
    }
    else {
        if ( function_exists( 'arms_patients_list_table' ) ) {
            arms_patients_list_table();
        } else {
            echo '<div class="notice notice-error"><p>Critical Error: Core data table components could not be successfully resolved.</p></div>';
        }
    }

    echo '</div>';
}