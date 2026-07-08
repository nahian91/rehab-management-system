<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Render the Patients module tab and manage internal sub-routing.
 */
function arms_patients_tab() {
    $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';

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
        $active_class = ( $sub === $k ) ? 'nav-tab-active' : '';
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
        // Safe integer verification filter on query request payload
        $patient_id = isset( $_GET['patient'] ) ? intval( $_GET['patient'] ) : 0;
        if ( function_exists( 'arms_add_edit_patient_form' ) ) {
            arms_add_edit_patient_form( $patient_id );
        }
    } 
    elseif ( $sub === 'view' ) {
        $patient_id = isset( $_GET['patient'] ) ? intval( $_GET['patient'] ) : 0;
        if ( function_exists( 'arms_view_patient_profile' ) ) {
            arms_view_patient_profile( $patient_id );
        }
    } 
    elseif ( $sub === 'history' ) {
        $patient_id = isset( $_GET['patient'] ) ? intval( $_GET['patient'] ) : 0;
        if ( function_exists( 'arms_render_patient_history_timeline' ) ) {
            arms_render_patient_history_timeline( $patient_id );
        } else {
            echo '<div class="notice notice-info"><p>Select a patient from the registry desk to run deep health record logs audits.</p></div>';
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