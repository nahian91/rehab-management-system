<?php
/**
 * Plugin Name: Rehab Management System
 * Description: Standalone, ultra-high-performance management system for Rehabilitation centers featuring clinical workflows, IPD/OPD billing, and accounts tracking.
 * Version:     1.0.0
 * Author:      DevNahian
 * Text Domain: rehab-management-system
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*--------------------------------------------------------------
# 1. Constants & Definitions
--------------------------------------------------------------*/
if ( ! defined( 'ARMS_VERSION' ) ) {
    define( 'ARMS_VERSION', '1.0.0' );
}
if ( ! defined( 'ARMS_PATH' ) ) {
    define( 'ARMS_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ARMS_URL' ) ) {
    define( 'ARMS_URL', plugin_dir_url( __FILE__ ) );
}

/*--------------------------------------------------------------
# 2. Role-Based Access Control (RBAC) Utility
--------------------------------------------------------------*/
function arms_has_access( $allowed_roles = array() ) {
    if ( empty( $allowed_roles ) ) {
        return true;
    }
    
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        return false;
    }

    // Super Admin explicitly bypasses all checks
    if ( in_array( 'administrator', $current_user->roles, true ) || current_user_can( 'manage_options' ) ) {
        return true;
    }

    foreach ( $allowed_roles as $role ) {
        if ( in_array( $role, $current_user->roles, true ) ) {
            return true;
        }
    }
    
    return false;
}

/*--------------------------------------------------------------
# 3. Scripts & Styles Enqueue
--------------------------------------------------------------*/
function arms_admin_enqueue_assets( $hook ) {
    // Heavy admin assets are isolated solely to the app layout page to prevent site-wide bloat
    if ( $hook !== 'toplevel_page_rehab_management_system' ) {
        return;
    }

    $plugin_uri = ARMS_URL;

    /* =====================
       Styles
    ===================== */
    wp_enqueue_style( 'bootstrap', $plugin_uri . 'assets/css/bootstrap.min.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'swiper', $plugin_uri . 'assets/css/swiper-bundle.min.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'validnavs', $plugin_uri . 'assets/css/validnavs.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'helper', $plugin_uri . 'assets/css/helper.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'main-style', $plugin_uri . 'assets/css/style.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'responsive-style', $plugin_uri . 'assets/css/responsive.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'rehab-management-system-admin-style', $plugin_uri . 'assets/css/admin-style.css', array(), ARMS_VERSION );

    /* =====================
       Scripts
    ===================== */
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'bootstrap', $plugin_uri . 'assets/js/bootstrap.bundle.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'appear', $plugin_uri . 'assets/js/jquery.appear.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'swiper', $plugin_uri . 'assets/js/swiper-bundle.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'progress-bar', $plugin_uri . 'assets/js/progress-bar.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'wow', $plugin_uri . 'assets/js/wow.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'isotope', $plugin_uri . 'assets/js/isotope.pkgd.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'imagesloaded', $plugin_uri . 'assets/js/imagesloaded.pkgd.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'magnific-popup', $plugin_uri . 'assets/js/magnific-popup.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'count-to', $plugin_uri . 'assets/js/count-to.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'nice-select', $plugin_uri . 'assets/js/jquery.nice-select.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'ytplayer', $plugin_uri . 'assets/js/YTPlayer.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'loopcounter', $plugin_uri . 'assets/js/loopcounter.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'validnavs', $plugin_uri . 'assets/js/validnavs.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'datepicker', $plugin_uri . 'assets/js/bootstrap-datepicker.js', array('jquery'), ARMS_VERSION, true );
    
    // GSAP Core & Plugins
    wp_enqueue_script( 'gsap', $plugin_uri . 'assets/js/gsap.js', array(), ARMS_VERSION, true );
    wp_enqueue_script( 'scrolltrigger', $plugin_uri . 'assets/js/ScrollTrigger.min.js', array('gsap'), ARMS_VERSION, true );
    wp_enqueue_script( 'splittext', $plugin_uri . 'assets/js/SplitText.min.js', array('gsap'), ARMS_VERSION, true );
    
    // System Scripts
    wp_enqueue_script( 'items', $plugin_uri . 'assets/js/items.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'rehab-management-system-main', $plugin_uri . 'assets/js/main.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'rehab-management-system-admin-script', $plugin_uri . 'assets/js/admin-script.js', array('jquery'), ARMS_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'arms_admin_enqueue_assets' );

/*--------------------------------------------------------------
# 4. Include Modular Sub-Files
--------------------------------------------------------------*/
require_once ARMS_PATH . 'inc/dashboard.php';
require_once ARMS_PATH . 'inc/patients.php';
require_once ARMS_PATH . 'inc/opd-billing.php';
require_once ARMS_PATH . 'inc/admission.php';
require_once ARMS_PATH . 'inc/physiotherapy.php';
require_once ARMS_PATH . 'inc/nursing.php';
require_once ARMS_PATH . 'inc/finance.php';
require_once ARMS_PATH . 'inc/payroll.php';
require_once ARMS_PATH . 'inc/inventory.php';
require_once ARMS_PATH . 'inc/appointments.php';
require_once ARMS_PATH . 'inc/audit-logs.php';
require_once ARMS_PATH . 'inc/administration.php';
require_once ARMS_PATH . 'inc/report.php';
require_once ARMS_PATH . 'inc/staff.php';

/*--------------------------------------------------------------
# 5. Database Table Creation (Strict dbDelta Compliant)
--------------------------------------------------------------*/
function arms_create_system_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Core Consolidated Inpatient Registry
    $table_patients = $wpdb->prefix . 'arms_patients';
    $sql_patients = "CREATE TABLE $table_patients (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        age int(3) NOT NULL,
        gender varchar(30) DEFAULT 'Male' NOT NULL,
        mobile varchar(50) NOT NULL,
        emergency varchar(255) DEFAULT '' NOT NULL,
        address text DEFAULT NULL,
        room_type varchar(50) DEFAULT 'Cabin' NOT NULL,
        room_no varchar(50) NOT NULL,
        admission_date date DEFAULT '1970-01-01' NOT NULL,
        initial_diagnosis text DEFAULT NULL,
        custom_diagnosis text DEFAULT NULL,
        conditions text DEFAULT NULL,
        day_billing_ledger longtext NOT NULL,
        media_vault_urls longtext NOT NULL,
        status varchar(50) DEFAULT 'Active Stay' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_patients );

    // Clinical HR Registry & Profile Desk
    $table_staff = $wpdb->prefix . 'arms_staff';
    $sql_staff = "CREATE TABLE $table_staff (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        role_category varchar(50) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(30) NOT NULL,
        license_number varchar(100) DEFAULT '' NOT NULL,
        joining_date date DEFAULT '1970-01-01' NOT NULL,
        salary decimal(10,2) DEFAULT '0.00' NOT NULL,
        status varchar(30) DEFAULT 'active' NOT NULL,
        profile_image varchar(255) DEFAULT '' NOT NULL,
        created_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_staff );

    // Nursing Care & Clinical Logs
    $table_nursing = $wpdb->prefix . 'arms_nursing_logs';
    $sql_nursing = "CREATE TABLE $table_nursing (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        patient_id bigint(20) NOT NULL,
        log_date date NOT NULL,
        shift_type varchar(50) NOT NULL DEFAULT 'Morning',
        location_type varchar(100) NOT NULL DEFAULT 'Ward',
        bed_no varchar(50) DEFAULT '' NOT NULL,
        bp_systolic int(3) NOT NULL,
        bp_diastolic int(3) NOT NULL,
        pulse_rate int(3) NOT NULL,
        body_temp decimal(5,2) DEFAULT '0.00' NOT NULL,
        spo2_level int(3) NOT NULL,
        medication_chart longtext NOT NULL,
        nursing_notes longtext DEFAULT NULL,
        shift_report longtext DEFAULT NULL,
        created_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_nursing );

    // Transactions, OPD & IPD Billing Records
    $table_billing = $wpdb->prefix . 'arms_billing';
    $sql_billing = "CREATE TABLE $table_billing (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        invoice_id varchar(50) NOT NULL,
        patient_id bigint(20) NOT NULL,
        billing_type varchar(20) DEFAULT 'opd' NOT NULL,
        payment_method varchar(30) DEFAULT 'cash' NOT NULL,
        subtotal decimal(10,2) DEFAULT '0.00' NOT NULL,
        tax_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        discount_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        total_price decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_status varchar(20) DEFAULT 'unpaid' NOT NULL,
        created_by bigint(20) NOT NULL,
        created_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        items_json longtext NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY  invoice_id (invoice_id)
    ) $charset_collate;";
    dbDelta( $sql_billing );

    // Inpatient Admissions & Accommodation Log
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $sql_admissions = "CREATE TABLE $table_admissions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        patient_id bigint(20) NOT NULL,
        accommodation_type varchar(50) DEFAULT 'ward' NOT NULL,
        room_number varchar(50) NOT NULL,
        bed_number varchar(50) DEFAULT '' NOT NULL,
        admission_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        discharge_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        daily_rent decimal(10,2) DEFAULT '0.00' NOT NULL,
        status varchar(30) DEFAULT 'admitted' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_admissions );

    // Ledger entries for General Income & Expense Bookkeeping
    $table_ledger = $wpdb->prefix . 'arms_ledger';
    $sql_ledger = "CREATE TABLE $table_ledger (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        transaction_type varchar(20) NOT NULL,
        category varchar(100) NOT NULL,
        amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        description text NOT NULL,
        reference_id varchar(50) DEFAULT '' NOT NULL,
        logged_by bigint(20) NOT NULL,
        transaction_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_ledger );

    // Security Audit Trail Logging
    $table_audit = $wpdb->prefix . 'arms_audit_logs';
    $sql_audit = "CREATE TABLE $table_audit (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_role varchar(50) NOT NULL,
        action_performed text NOT NULL,
        ip_address varchar(45) NOT NULL,
        timestamp datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_audit );
}
register_activation_hook( __FILE__, 'arms_create_system_tables' );

/*--------------------------------------------------------------
# 6. Global Security Audit Logger Engine
--------------------------------------------------------------*/
function arms_log_activity( $action_description ) {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = $current_user->exists() ? $current_user->ID : 0;
    $user_roles = $current_user->exists() ? implode( ', ', $current_user->roles ) : 'guest';
    
    $ip_address = '0.0.0.0';
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $raw_ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
        if ( function_exists( 'rest_is_ip_address' ) && rest_is_ip_address( $raw_ip ) ) {
            $ip_address = $raw_ip;
        } elseif ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
            $ip_address = $raw_ip;
        }
    }

    $wpdb->insert(
        $wpdb->prefix . 'arms_audit_logs',
        array(
            'user_id'          => $user_id,
            'user_role'        => $user_roles,
            'action_performed' => sanitize_text_field( $action_description ),
            'ip_address'       => $ip_address,
            'timestamp'        => current_time( 'mysql' )
        ),
        array( '%d', '%s', '%s', '%s', '%s' )
    );
}

/*--------------------------------------------------------------
# 7. Admin Menu Core Mounting
--------------------------------------------------------------*/
add_action( 'admin_menu', function() {
    add_menu_page(
        'Rehab Management System',
        'Rehab POS',
        'read', 
        'rehab_management_system',
        'arms_main_router_page', 
        'dashicons-performance',
        20
    );
});

/*--------------------------------------------------------------
# 8. Main Dynamic Tab Router Engine
--------------------------------------------------------------*/
function arms_main_router_page() {
    $all_tabs = array(
        'dashboard' => array(
            'label' => 'Dashboard',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 544 512"><path d="M528 0H16C7.2 0 0 7.2 0 16v480c0 8.8 7.2 16 16 16h512c8.8 0 16-7.2 16-16V16c0-8.8-7.2-16-16-16zM272 248v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zm0 176v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zM72 152c0-4.4 3.6-8 8-8h112c4.4 0 8 3.6 8 8v208c0 4.4-3.6 8-8 8H80c-4.4 0-8-3.6-8-8V152z"/></svg>',
            'roles' => array()
        ),
        'patients' => array(
            'label' => 'Patients',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M224 256c70.7 0 128-57.3 128-128S294.7 0 224 0 96 57.3 96 128s57.3 128 128 128zm89.6 32h-16.7c-22.2 10.2-46.9 16-72.9 16s-50.6-5.8-72.9-16h-16.7C60.2 288 0 348.2 0 422.4V464c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48v-41.6c0-74.2-60.2-134.4-134.4-134.4zM160 48h48v48h-48V48zm128 336H160v-48h128v48z"/></svg>',
            'roles' => array('admin_manager', 'doctor', 'physiotherapist', 'nurse')
        ),
        'admission' => array(
            'label' => 'OPD Billing',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M436 160H12c-6.6 0-12-5.4-12-12v-36c0-26.5 21.5-48 48-48h48V12c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v52h128V12c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v52h48c26.5 0 48 21.5 48 48v36c0 6.6-5.4 12-12 12zM0 192v272c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V192H0zm308 176c0 4.4-3.6 8-8 8h-48v48c0 4.4-3.6 8-8 8h-40c-4.4 0-8-3.6-8-8v-48h-48c-4.4 0-8-3.6-8-8v-40c0-4.4 3.6-8 8-8h48v-48c0-4.4 3.6-8 8-8h40c4.4 0 8 3.6 8 8v48h48c4.4 0 8 3.6 8 8v40z"/></svg>',
            'roles' => array('admin_manager', 'doctor')
        ),
        'physiotherapy' => array(
            'label' => 'Physiotherapy',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M208 48c0 26.51-21.49 48-48 48s-48-21.49-48-48 21.49-48 48-48 48 21.49 48 48zm92.21 289.44l-40-64A15.937 15.937 0 0 0 246.77 266H200v-82.68l42.66 21.33c10.02 5.01 22.24 1.93 28.53-7.14l24-34.67c6.6-9.54 4.31-22.68-5.12-29.4l-80-57.14C201.76 106.66 190.22 102 178 102h-40c-15.65 0-30.06 6.94-39.63 18.91l-60 74.67c-7.05 8.77-6.2 21.56 1.95 29.3l29.33 27.87c8.39 7.97 21.71 7.28 29.27-1.54L136 204.62V282H73.37c-10.42 0-20 6.01-24.49 15.41l-44 92c-6.19 12.95-1.12 28.34 11.45 35.08l34.67 18.59c11.83 6.34 26.69 2.11 33.43-9.35L112 384h40v96c0 17.67 14.33 32 32 32h24c13.25 0 24-10.75 24-24v-160h43.3c7.22 0 13.98-3.9 17.61-10.22l20.43-34.33c4.13-6.95 3.01-15.72-3.13-21.47z"/></svg>',
            'roles' => array('physiotherapist', 'doctor')
        ),
        'nursing' => array(
            'label' => 'Nursing',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M304 128H144v32h160v-32zm0 192H144v32h160v-32zm112-256h-16V48c0-26.5-21.5-48-48-48H96C69.5 0 48 21.5 48 48v16H32C14.3 64 0 78.3 0 96v352c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64V96c0-17.7-14.3-32-32-32zM384 448c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V112h336v336zm-48-224H112v-32h224v32z"/></svg>',
            'roles' => array('nurse', 'doctor')
        ),
        'finance' => array(
            'label' => 'Finance',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M464 128H48c-26.5 0-48 21.5-48 48v240c0 26.5 21.5 48 48 48h416c26.5 0 48-21.5 48-48V176c0-26.5-21.5-48-48-48zm-16 240c0 13.3-10.7 24-24 24H96c-13.3 0-24-10.7-24-24V216c0-13.3 10.7-24 24-24h304c13.3 0 24 10.7 24 24v152zm-88-104c-22.1 0-40 17.9-40 40s17.9 40 40 40 40-17.9 40-40-17.9-40-40-40zM400 64H48c-8.8 0-16 7.2-16 16v16h448V80c0-8.8-7.2-16-16-16z"/></svg>',
            'roles' => array('accountant', 'admin_manager')
        ),
        'inventory' => array(
            'label' => 'Inventory',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M501.5 141.2c-5.8-11.4-16-19.4-28-21.8L285.8 81.7c-19.6-4-40 4-51.5 20L51.9 336.5c-7.9 11-10.7 24.8-7.6 38.2l31.5 137.3c4 17.5 19.4 30 37.5 30H440c22.1 0 40-17.9 40-40V166.8c0-9.8-3.6-19.3-10.1-26.6zM272 131.1l157 32.1-157 78.5V131.1zm-32 23.3v110.1L94.6 343.3 240 154.4zm208 317.6H112l-22.4-97.6L272 268v204h176V472z"/></svg>',
            'roles' => array('admin_manager', 'accountant')
        ),
        'reports' => array(
            'label' => 'Reports',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M336 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM144 432H96v-48h48v48zm0-96H96v-48h48v48zm0-96H96v-48h48v48zm144 192H176v-48h112v48zm0-96H176v-48h112v48zm0-96H176v-48h112v48zm0-112H96V80h192v48z"/></svg>',
            'roles' => array('admin_manager', 'accountant')
        ),
        'staff' => array(
            'label' => 'Staff',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M610.5 343.3l-34.7-18.6c-11.8-6.3-26.7-2.1-33.4 9.3L512 384h-40v-82l52-64.7c7.1-8.8 6.2-21.6-1.9-29.3L492.8 180c-8.4-8-21.7-7.3-29.3 1.5L424 224.6V102h-40c-12.2 0-23.8 4.7-32.5 13.1l-40 38.6c-5.8-11.4-16-19.4-28-21.8L100.8 102H48C21.5 102 0 123.5 0 150v260c0 26.5 21.5 48 48 48h240c13.3 0 24-10.7 24-24v-64h112v96c0 17.7 14.3 32 32 32h24c13.3 0 24-10.7 24-24v-160h43.3c7.2 0 14-3.9 17.6-10.2l20.4-34.3c4.2-6.9 3-15.7-3.2-21.5zM128 48c0 26.5-21.5 48-48 48S32 74.5 32 48 53.5 0 80 0s48 21.5 48 48zm352 0c0 26.5-21.5 48-48 48s-48-21.5-48-48 21.5-48 48-48 48 21.5 48 48z"/></svg>',
            'roles' => array('admin_manager')
        ),
        'settings' => array(
            'label' => 'Administration',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M496 384H160v-16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v16H16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h80v16c0 8.8 7.2 16 16 16h32c0 8.8 7.2 16 16-16v-16h336c8.8 0 16-7.2 16-16v-32c0-8.8-7.2-16-16-16zm0-192H288v-16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v16H16c-8.8 0-16 7.2-16 16v32c0 8.8 7.2 16 16 16h208v16c0 8.8 7.2 16 16 16h32c0 8.8 7.2 16 16-16v-16h208c8.8 0 16-7.2 16-16v-32c0-8.8-7.2-16-16-16zm0-192H416V-16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v16H16C7.2 0 0 7.2 0 16v32c0 8.8 7.2 16 16 16h336v16c0 8.8 7.2 16 16 16h32c0 8.8 7.2 16 16-16V64h80c8.8 0 16-7.2 16-16V16c0-8.8-7.2-16-16-16z"/></svg>',
            'roles' => array()
        ),
    );

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
    
    if ( ! array_key_exists( $active_tab, $all_tabs ) ) {
        $active_tab = 'dashboard';
    }

    if ( ! arms_has_access( $all_tabs[ $active_tab ]['roles'] ) ) {
        echo '<div class="notice notice-error"><p>Access Denied: You do not possess the required privilege level to monitor this operations desk.</p></div>';
        return;
    }

    $is_print_mode = ( isset( $_GET['action'] ) && $_GET['action'] === 'print' );
    ?>

    <div id="arms-wrapper" class="rehab-management-system <?php echo $is_print_mode ? 'arms-print' : ''; ?>">
        
        <?php if ( ! $is_print_mode ) : ?>
            <ul class="arms-left-tabs">
                <?php 
                foreach ( $all_tabs as $slug => $config ) : 
                    if ( ! arms_has_access( $config['roles'] ) ) {
                        continue; 
                    }
                    $active_class = ( $active_tab === $slug ) ? 'active' : '';
                    ?>
                    <li>
                        <a class="<?php echo esc_attr( $active_class ); ?>" 
                           href="<?php echo esc_url( admin_url( 'admin.php?page=rehab_management_system&tab=' . $slug ) ); ?>">
                            <?php echo $config['svg']; ?>
                            <span><?php echo esc_html( $config['label'] ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="arms-right-box">
            <?php
            switch ( $active_tab ) {
                case 'dashboard':
                    if ( function_exists( 'arms_dashboard_tab' ) ) {
                        arms_dashboard_tab();
                    }
                    break;
                case 'patients':
                    if ( function_exists( 'arms_patients_tab' ) ) {
                        arms_patients_tab();
                    }
                    break;
                case 'physiotherapy':
                    if ( function_exists( 'arms_render_physiotherapy_module' ) ) {
                        arms_render_physiotherapy_module();
                    }
                    break;
                case 'nursing':
                    if ( function_exists( 'arms_nursing_tab' ) ) {
                        arms_nursing_tab();
                    }
                    break;
                case 'inventory':
                    if ( function_exists( 'arms_inventory_tab' ) ) {
                        arms_inventory_tab();
                    }
                    break;
                case 'admission':
                    if ( function_exists( 'arms_admission_tab' ) ) {
                        arms_admission_tab();
                    }
                    break;
                case 'finance':
                    if ( function_exists( 'arms_finance_tab' ) ) {
                        arms_finance_tab();
                    }
                    break;
                case 'reports':
                    if ( function_exists( 'arms_reports_tab' ) ) {
                        arms_reports_tab();
                    }
                    break;
                case 'staff':
                    if ( function_exists( 'arms_staff_tab' ) ) {
                        arms_staff_tab();
                    }
                    break;
                case 'settings':
                    if ( function_exists( 'arms_render_global_settings_module' ) ) {
                        arms_render_global_settings_module();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/*--------------------------------------------------------------
# 9. Head CSS Layout Injection
--------------------------------------------------------------*/
add_action( 'admin_head', function() {
    $screen = get_current_screen();

    if ( $screen && $screen->id === 'toplevel_page_rehab_management_system' ) {
        echo '<style>
            #wpadminbar, 
            #adminmenu, #adminmenuback, #adminmenuwrap, 
            #wpfooter { display: none !important; }
            
            #wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            
            body.wp-admin { background: #f1f1f1; overflow-x: hidden; }

            .rehab-management-system {
                display: flex;
                position: relative;
                min-height: 100vh;
            }

            .arms-left-tabs {
                width: 240px;
                margin: 0;
                padding: 30px 0 0 0;
                list-style: none;
                flex-shrink: 0;
                background: #fff;
                border-right: 1px solid #e2e8f0;
            }

            .arms-left-tabs li a {
                display: flex;
                align-items: center;
                padding: 14px 24px;
                color: #475569;
                text-decoration: none;
                font-weight: 500;
                font-size: 14px;
                transition: all 0.2s ease;
            }

            .arms-left-tabs li a svg {
                width: 18px;
                height: 18px;
                margin-right: 14px;
                fill: #64748b;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }

            .arms-left-tabs li a:hover {
                background: #f8fafc;
                color: #1e293b;
            }

            .arms-left-tabs li a:hover svg {
                fill: #e53935;
            }

            .arms-left-tabs li a.active {
                background: #e53935;
                color: #fff;
                font-weight: 600;
            }

            .arms-left-tabs li a.active svg {
                fill: #fff;
            }

            .arms-right-box {
                flex-grow: 1;
                background: #f8fafc;
                padding: 30px;
            }

            .arms-print .arms-left-tabs {
                display: none !important;
            }
        </style>';
    }
});