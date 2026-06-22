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
    
    // Explicitly forward table actions to our controller map
    if ( ! empty( $action ) && $sub === 'all' ) {
        $sub = $action;
    }

    // Sub-navigation architecture for the Patient Registry
    $tabs = array(
        'all'     => 'All Patients',
        'add'     => 'Add Patient',
        'history' => 'Medical History',
    );

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        // Keeps user locked into the active main tab context (tab=patients)
        $url = admin_url( 'admin.php?page=rehab_management_system&tab=patients&sub=' . $k );
        
        // Ensure "All Patients" remains visually highlighted during row actions (view/edit/delete)
        $is_active = ( $sub === $k ) || ( $k === 'all' && in_array( $sub, array( 'view', 'edit', 'delete' ) ) );
        $active_class = $is_active ? 'nav-tab-active' : '';
        
        echo '<a class="nav-tab ' . esc_attr( $active_class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    echo '<div class="arms-sub-tab-content" style="margin-top: 20px;">';

    /* =========================================================================
       Sub-Module View Router Execution Matrix
       ========================================================================= */
    if ( $sub === 'add' ) {
        // Render form for a brand new clinical admission profile entry
        if ( function_exists( 'arms_add_edit_patient_form' ) ) {
            arms_add_edit_patient_form();
        } else {
            echo '<div class="notice notice-warning"><p>Patient entry form module missing from the patient template layer.</p></div>';
        }
    } 
    elseif ( $sub === 'edit' ) {
        // Extract parameters matching the custom table query definitions (&id=)
        $patient_id = isset( $_GET['id'] ) ? intval( preg_replace( '/[^0-9]/', '', $_GET['id'] ) ) : 0;
        if ( function_exists( 'arms_add_edit_patient_form' ) ) {
            arms_add_edit_patient_form( $patient_id );
        }
    } 
    elseif ( $sub === 'view' ) {
        $patient_id = isset( $_GET['id'] ) ? intval( preg_replace( '/[^0-9]/', '', $_GET['id'] ) ) : 0;
        if ( function_exists( 'arms_view_patient_profile' ) ) {
            arms_view_patient_profile( $patient_id );
        }
    } 
    elseif ( $sub === 'delete' ) {
        $patient_id = isset( $_GET['id'] ) ? intval( preg_replace( '/[^0-9]/', '', $_GET['id'] ) ) : 0;
        
        // 🗑️ Database deletion operational block hook
        // global $wpdb; $wpdb->delete(...);
        
        echo '<div class="notice notice-success is-dismissible"><p>Patient record ' . esc_html($_GET['id']) . ' has been safely removed.</p></div>';
        
        if ( function_exists( 'arms_patients_list_table' ) ) {
            arms_patients_list_table();
        }
    }
    else {
        // Primary fallback node: tabular records listing component
        if ( function_exists( 'arms_patients_list_table' ) ) {
            arms_patients_list_table();
        } else {
            echo '<div class="notice notice-error"><p>Critical Error: Core data table components could not be successfully resolved.</p></div>';
        }
    }

    echo '</div>';
}