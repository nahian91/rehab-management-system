<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Render the Patients module tab and manage internal sub-routing.
 */
function arms_admission_tab() {
    $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'all';
    $patient_id = isset( $_GET['patient'] ) ? intval( $_GET['patient'] ) : 0;

    // Sub-navigation architecture for the Patient Registry
    $tabs = array(
        'all'     => 'All Patients',
        'add'     => 'Add Patient',
        'history' => 'Medical History',
    );

    // Contextual Sub-tab Injection: Append temporary tab options for specific workflows
    if ( $sub === 'edit' && $patient_id > 0 ) {
        $tabs['edit'] = 'Edit Admission (ID: ' . $patient_id . ')';
    } elseif ( $sub === 'view' && $patient_id > 0 ) {
        $tabs['view'] = 'Patient Profile';
    }

    echo '<h2 class="nav-tab-wrapper arms-sub-tab-wrapper">';
    foreach ( $tabs as $k => $label ) {
        // Build base context locking query variables
        $url_args = array(
            'page' => 'rehab_management_system',
            'tab'  => 'patients',
            'sub'  => $k
        );

        // Retain the current patient context item inside single-patient operations
        if ( $patient_id > 0 && ( $k === 'edit' || $k === 'view' || $k === 'history' ) ) {
            $url_args['patient'] = $patient_id;
        }

        $url = add_query_arg( $url_args, admin_url( 'admin.php' ) );
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
        if ( function_exists( 'arms_add_edit_admission_form' ) ) {
            arms_add_edit_patient_form();
        } else {
            echo '<div class="notice notice-warning"><p>Patient entry form module missing from the patient template layer.</p></div>';
        }
    } 
    elseif ( $sub === 'edit' ) {
        // Safe integer verification filter on query request payload
        if ( $patient_id > 0 ) {
            if ( function_exists( 'arms_add_edit_patient_form' ) ) {
                arms_add_edit_patient_form( $patient_id );
            } else {
                echo '<div class="notice notice-warning"><p>Patient edit form module missing from the patient template layer.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Error: Invalid patient record identifier parameters targeted.</p></div>';
        }
    } 
    elseif ( $sub === 'view' ) {
        if ( function_exists( 'arms_view_patient_profile' ) ) {
            arms_view_patient_profile( $patient_id );
        }
    } 
    elseif ( $sub === 'history' ) {
        if ( function_exists( 'arms_render_patient_history_timeline' ) ) {
            arms_render_patient_history_timeline( $patient_id );
        } else {
            echo '<div class="notice notice-info"><p>Select a patient from the registry desk to run deep health record logs audits.</p></div>';
        }
    } 
    else {
        // Primary fallback node: tabular records listing component
        if ( function_exists( 'arms_patients_list_table' ) ) {
            arms_admission_list_table();
        } else {
            echo '<div class="notice notice-error"><p>Critical Error: Core data table components could not be successfully resolved.</p></div>';
        }
    }

    echo '</div>';
}