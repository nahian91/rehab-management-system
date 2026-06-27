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
    wp_enqueue_style( 'datatables', $plugin_uri . 'assets/css/jquery.dataTables.min.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'main-style', $plugin_uri . 'assets/css/style.css', array(), ARMS_VERSION );
    wp_enqueue_style( 'rehab-management-system-admin-style', $plugin_uri . 'assets/css/admin-style.css', array(), ARMS_VERSION );

    /* =====================
       Scripts
    ===================== */
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'bootstrap', $plugin_uri . 'assets/js/bootstrap.bundle.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'datatables', $plugin_uri . 'assets/js/jquery.dataTables.min.js', array('jquery'), ARMS_VERSION, true );
    wp_enqueue_script( 'datepicker', $plugin_uri . 'assets/js/bootstrap-datepicker.js', array('jquery'), ARMS_VERSION, true );
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
require_once ARMS_PATH . 'inc/settings.php';

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
        age int(3) unsigned NOT NULL,
        gender varchar(30) DEFAULT 'Male' NOT NULL,
        mobile varchar(50) NOT NULL,
        emergency_contact_name varchar(255) DEFAULT '' NOT NULL,
        emergency_contact_phone varchar(50) DEFAULT '' NOT NULL,
        address text NOT NULL,
        room_type varchar(50) DEFAULT 'Cabin' NOT NULL,
        room_no varchar(50) NOT NULL,
        admission_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        initial_diagnosis text NOT NULL,
        custom_diagnosis text NOT NULL,
        conditions longtext NOT NULL, 
        day_billing_ledger longtext NOT NULL,
        media_vault_urls longtext NOT NULL,
        followup_history longtext NOT NULL,
        status varchar(50) DEFAULT 'Active Stay' NOT NULL,
        PRIMARY KEY  (id),
        KEY status_idx (status)
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

    // Physiotherapy Rehabilitation & Session Tracking logs
$table_physio = $wpdb->prefix . 'arms_physio_logs';
$sql_physio = "CREATE TABLE $table_physio (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    patient_id bigint(20) NOT NULL,
    log_date date NOT NULL,
    initial_assessment text NOT NULL,
    rehab_goals text NOT NULL,
    daily_plan text NOT NULL,
    sessions_completed int(11) DEFAULT 0 NOT NULL,
    sessions_remaining int(11) DEFAULT 0 NOT NULL,
    progress_notes text NOT NULL,
    created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    created_by bigint(20) UNSIGNED DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY patient_log_idx (patient_id, log_date)
) $charset_collate;";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql_physio );

$charset_collate = $wpdb->get_charset_collate();

    // 1. Core Monthly Ledger Records Table
    $table_payroll = $wpdb->prefix . 'arms_payroll';
    $sql_payroll = "CREATE TABLE $table_payroll (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        staff_id mediumint(9) NOT NULL,
        pay_period DATE NOT NULL,
        base_salary DECIMAL(10,2) NOT NULL,
        bonus DECIMAL(10,2) DEFAULT '0.00',
        incentives DECIMAL(10,2) DEFAULT '0.00',
        attendance_deduction DECIMAL(10,2) DEFAULT '0.00',
        tax_deduction DECIMAL(10,2) DEFAULT '0.00',
        net_payable DECIMAL(10,2) NOT NULL,
        payment_date DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'unpaid',
        PRIMARY KEY  (id),
        KEY staff_id (staff_id)
    ) $charset_collate;";

    // 2. Performance Tracking & Audits Logs Table
    $table_incentives = $wpdb->prefix . 'arms_payroll_incentives';
    $sql_incentives = "CREATE TABLE $table_incentives (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        staff_id mediumint(9) NOT NULL,
        description VARCHAR(255),
        amount DECIMAL(10,2) NOT NULL,
        entry_date DATE NOT NULL,
        PRIMARY KEY  (id),
        KEY staff_id (staff_id)
    ) $charset_collate;";

    // Load WordPress core database upgrade runtime layer
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    dbDelta( $sql_payroll );
    dbDelta( $sql_incentives );
    

// 1. Payroll Monthly Records Table
$table_payroll = $wpdb->prefix . 'arms_payroll';
$sql_payroll = "CREATE TABLE $table_payroll (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    staff_id mediumint(9) NOT NULL,
    pay_period DATE NOT NULL, 
    base_salary DECIMAL(10,2) NOT NULL,
    bonus DECIMAL(10,2) DEFAULT 0.00,
    incentives DECIMAL(10,2) DEFAULT 0.00,
    attendance_deduction DECIMAL(10,2) DEFAULT 0.00,
    tax_deduction DECIMAL(10,2) DEFAULT 0.00,
    net_payable DECIMAL(10,2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'unpaid',
    PRIMARY KEY (id),
    KEY staff_id (staff_id)
) $charset_collate;";

// 2. Individual Incentive/Performance Logs Table
$table_incentives = $wpdb->prefix . 'arms_payroll_incentives';
$sql_incentives = "CREATE TABLE $table_incentives (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    staff_id mediumint(9) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    entry_date DATE NOT NULL,
    PRIMARY KEY (id),
    KEY staff_id (staff_id)
) $charset_collate;";

// Execute using dbDelta
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
dbDelta( $sql_payroll );
dbDelta( $sql_incentives );

    // Nursing Care & Clinical Logs
    $table_nursing = $wpdb->prefix . 'arms_nursing_logs';
    $sql_nursing = "CREATE TABLE $table_nursing (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        patient_id bigint(20) NOT NULL,
        log_date date NOT NULL,
        shift_type varchar(50) DEFAULT 'Morning' NOT NULL,
        location_type varchar(100) DEFAULT 'Ward' NOT NULL,
        bed_no varchar(50) DEFAULT '' NOT NULL,
        bp_systolic int(3) NOT NULL,
        bp_diastolic int(3) NOT NULL,
        pulse_rate int(3) NOT NULL,
        body_temp decimal(5,2) DEFAULT '0.00' NOT NULL,
        spo2_level int(3) NOT NULL,
        medication_chart longtext NOT NULL,
        nursing_notes longtext NOT NULL,
        shift_report longtext NOT NULL,
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
        UNIQUE KEY invoice_id (invoice_id)
    ) $charset_collate;";
    dbDelta( $sql_billing );

    // Inpatient Admissions & Accommodation Log
    $table_admissions = $wpdb->prefix . 'arms_admissions';
    $sql_admissions = "CREATE TABLE $table_admissions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        patient_id bigint(20) NOT NULL,
        room_type varchar(50) NOT NULL,
        room_no varchar(50) DEFAULT '' NOT NULL,
        ward_bed_no varchar(50) DEFAULT '' NOT NULL,
        admission_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        advance_payment decimal(10,2) DEFAULT '0.00' NOT NULL,
        discharge_date datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        final_bill_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_status varchar(30) DEFAULT 'Unpaid' NOT NULL,
        discharge_summary longtext NOT NULL,
        PRIMARY KEY  (id),
        KEY patient_id (patient_id),
        KEY payment_status (payment_status)
    ) $charset_collate;";
    dbDelta( $sql_admissions );

    // Repeater Row / Daily Service Logs Table
    $table_charges = $wpdb->prefix . 'arms_admission_charges';
    $sql_admission_charges = "CREATE TABLE $table_charges (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        admission_id bigint(20) NOT NULL,
        row_index int(11) NOT NULL,
        room_rent decimal(10,2) DEFAULT '0.00' NOT NULL,
        nursing_charge decimal(10,2) DEFAULT '0.00' NOT NULL,
        physio_charge decimal(10,2) DEFAULT '0.00' NOT NULL,
        doctor_charge decimal(10,2) DEFAULT '0.00' NOT NULL,
        acupuncture_charge decimal(10,2) DEFAULT '0.00' NOT NULL,
        prp_charge decimal(10,2) DEFAULT '0.00' NOT NULL,
        PRIMARY KEY  (id),
        KEY admission_id (admission_id)
    ) $charset_collate;";
    dbDelta( $sql_admission_charges );

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

    // Dedicated Expense Allocation Register
    $table_expenses = $wpdb->prefix . 'arms_expenses';
    $sql_expenses = "CREATE TABLE $table_expenses (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        expense_category varchar(50) NOT NULL,
        expense_type varchar(100) NOT NULL,
        target_month varchar(30) DEFAULT '' NOT NULL,
        target_year varchar(10) DEFAULT '' NOT NULL,
        base_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        adjustment_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        total_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        authorized_by varchar(255) DEFAULT '' NOT NULL,
        transaction_date date DEFAULT '1970-01-01' NOT NULL,
        notes text NOT NULL,
        created_by bigint(20) NOT NULL,
        created_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_expenses );

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

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_inventory = $wpdb->prefix . 'arms_inventory';
    $table_movements = $wpdb->prefix . 'arms_stock_movements';

    // Core Inventory Table
    $sql_inventory = "CREATE TABLE $table_inventory (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        item_code varchar(100) NOT NULL,
        item_name varchar(255) NOT NULL,
        generic_name varchar(255) DEFAULT '' NOT NULL,
        category varchar(100) DEFAULT 'General' NOT NULL,
        sku varchar(100) DEFAULT '' NOT NULL,
        available_stock int(11) DEFAULT '0' NOT NULL,
        min_required_stock int(11) DEFAULT '10' NOT NULL,
        unit_type varchar(50) DEFAULT 'pieces' NOT NULL,
        purchase_price decimal(10,2) DEFAULT '0.00' NOT NULL,
        sale_price decimal(10,2) DEFAULT '0.00' NOT NULL,
        supplier_info text DEFAULT NULL,
        batch_number varchar(100) DEFAULT '' NOT NULL,
        expiry_date date DEFAULT '1970-01-01' NOT NULL,
        status varchar(50) DEFAULT 'In Stock' NOT NULL,
        updated_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY item_code (item_code)
    ) $charset_collate;";

    // Stock Movement History Ledger Table
    $sql_movements = "CREATE TABLE $table_movements (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        item_id bigint(20) NOT NULL,
        movement_type varchar(20) NOT NULL,
        quantity int(11) NOT NULL,
        reference_type varchar(100) NOT NULL,
        reference_id varchar(100) NOT NULL,
        remarks text DEFAULT NULL,
        logged_by bigint(20) NOT NULL,
        created_at datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_inventory );
    dbDelta( $sql_movements );
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
        'opd' => array(
            'label' => 'OPD Billing',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M436 160H12c-6.6 0-12-5.4-12-12v-36c0-26.5 21.5-48 48-48h48V12c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v52h128V12c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v52h48c26.5 0 48 21.5 48 48v36c0 6.6-5.4 12-12 12zM0 192v272c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V192H0zm308 176c0 4.4-3.6 8-8 8h-48v48c0 4.4-3.6 8-8 8h-40c-4.4 0-8-3.6-8-8v-48h-48c-4.4 0-8-3.6-8-8v-40c0-4.4 3.6-8 8-8h48v-48c0-4.4 3.6-8 8-8h40c4.4 0 8 3.6 8 8v48h48c4.4 0 8 3.6 8 8v40z"/></svg>',
            'roles' => array('admin_manager', 'doctor')
        ),
        'admission' => array(
            'label' => 'Admission',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M528 112H48c-26.5 0-48 21.5-48 48v320c0 26.5 21.5 48 48 48h480c26.5 0 48-21.5 48-48V160c0-26.5-21.5-48-48-48zm-16 336H64V160h448v288zM176 256h224c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16v-32c0-8.8 7.2-16 16-16zm0 96h224c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16v-32c0-8.8 7.2-16 16-16zM112 48h352c8.8 0 16 7.2 16 16v16H96V64c0-8.8 7.2-16 16-16z"/></svg>',
            'roles' => array('admin_manager', 'doctor', 'nurse')
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
        'payroll' => array(
            'label' => 'Payroll',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M112 112c0-8.8 7.2-16 16-16H448c8.8 0 16 7.2 16 16v16H112V112zM48 224H528c26.5 0 48 21.5 48 48v144c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V272c0-26.5 21.5-48 48-48zm56 64c-13.3 0-24 10.7-24 24v48c0 13.3 10.7 24 24 24h48c13.3 0 24-10.7 24-24V312c0-13.3-10.7-24-24-24H104zm216 9.4c0-5.2-4.2-9.4-9.4-9.4H198.1c-12.6 0-24.3 6.9-30.4 17.9L152 318.4c-5.2 9.4-5.2 20.8 0 30.3l15.6 26.6c6.1 11 17.8 17.9 30.4 17.9h112.5c5.2 0 9.4-4.2 9.4-9.4V297.4zM400 288c-13.3 0-24 10.7-24 24v48c0 13.3 10.7 24 24 24h48c13.3 0 24-10.7 24-24V312c0-13.3-10.7-24-24-24H400z"/></svg>',
            'roles' => array( 'admin_manager', 'accountant' )
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
            'label' => 'Settings',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M487.4 315.7l-42.6-24.6c2.3-14.2 3.5-28.7 3.5-43.1s-1.2-28.9-3.5-43.1l42.6-24.6c11.5-6.6 15.4-21.3 8.7-32.8L447.5 61.2c-6.6-11.5-21.3-15.4-32.8-8.7L372 77.1c-22.1-14.8-46.7-26.3-72.9-33.8L292.8 12C291.1 5.2 285 0 278.1 0h-44.2c-6.9 0-13 5.2-14.7 12L213 43.3c-26.2 7.5-50.8 19-72.9 33.8l-42.7-24.6c-11.5-6.7-26.2-2.8-32.8 8.7L16.1 147.3c-6.7 11.5-2.8 26.2 8.7 32.8l42.6 24.6c-2.3 14.2-3.5 28.7-3.5 43.1s1.2 28.9 3.5 43.1l-42.6 24.6c-11.5 6.6-15.4 21.3-8.7 32.8l48.6 84.3c6.6 11.5 21.3 15.4 32.8 8.7l42.7-24.6c22.1 14.8 46.7 26.3 72.9 33.8L219.2 500c1.7 6.8 7.8 12 14.7 12h44.2c6.9 0 13-5.2 14.7-12l6.3-31.3c26.2-7.5 50.8-19 72.9-33.8l42.7 24.6c11.5 6.7 26.2 2.8 32.8-8.7l48.6-84.3c6.7-11.5 2.8-26.2-8.7-32.9zM256 336c-44.2 0-80-35.8-80-80s35.8-80 80-80 80 35.8 80 80-35.8 80-80 80z"/></svg>',
            'roles' => array('admin_manager')
        ),
        'logout' => array(
            'label' => 'Log Out',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96C43 32 0 75 0 128v256c0 53 43 96 96 96h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H96c-17.7 0-32-14.3-32-32V128c0-17.7 14.3-32 32-32h64zm273 135L313 111c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l123 123H192c-17.7 0-32 14.3-32 32s14.3 32 32 32h198.7L267.7 401.7c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l120-120c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
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

    // Custom data extraction logic mapping for the user context
    global $wpdb;
    $user_id       = get_current_user_id();
    $display_name  = '';
    $designation   = '';
    $custom_avatar = '';

    // Fetch information from Staff module storage table
    $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation, profile_image FROM {$wpdb->prefix}arms_staff WHERE wp_user_id = %d", $user_id ) );
    
    if ( $staff_row ) {
        $display_name  = $staff_row->full_name;
        $designation   = $staff_row->designation;
        $custom_avatar = $staff_row->profile_image;
    } else {
        // Fallback to core metadata if no unique staff profile record exists
        $display_name  = get_user_meta( $user_id, 'arms_staff_full_name', true );
        $designation   = get_user_meta( $user_id, 'arms_staff_designation', true );
        $custom_avatar = get_user_meta( $user_id, 'arms_staff_profile_image', true );
    }

    // Secondary core structural fallback defaults
    if ( empty( $display_name ) ) {
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name ? $current_user->display_name : 'Staff Member';
    }
    if ( empty( $designation ) ) {
        $designation = 'Medical Specialist';
    }
    ?>

    <div id="arms-wrapper" class="rehab-management-system <?php echo $is_print_mode ? 'arms-print' : ''; ?>">
        
        <?php if ( ! $is_print_mode ) : ?>
            <div class="arms-sidebar-container">
                
                <div class="arms-author-profile">
                    <div class="profile-avatar">
                        <?php 
                        global $wpdb;
                        $current_user_id = get_current_user_id();
                        $current_user    = wp_get_current_user();
                        
                        // Initialize fallback layout defaults
                        $display_name  = ! empty( $current_user->display_name ) ? $current_user->display_name : $current_user->user_login;
                        $designation   = 'Staff Member';
                        $custom_avatar = '';

                        // Check if the current user is an Administrator
                        if ( in_array( 'administrator', (array) $current_user->roles ) ) {
                            // --- ADMIN STRATEGY: Use pure WordPress Defaults ---
                            $designation = 'Administrator';
                        } else {
                            // --- STAFF STRATEGY: Fetch metadata metrics from custom DB matrix table ---
                            $table_name = $wpdb->prefix . 'arms_staff';
                            
                            // Query the row matching this WordPress User ID
                            $staff_row = $wpdb->get_row( $wpdb->prepare( 
                                "SELECT first_name, last_name, role_category, profile_image FROM $table_name WHERE wp_user_id = %d", 
                                $current_user_id 
                            ) );

                            if ( $staff_row ) {
                                // Construct real name dynamically from table columns
                                if ( ! empty( $staff_row->first_name ) ) {
                                    $display_name = trim( $staff_row->first_name . ' ' . $staff_row->last_name );
                                }
                                
                                // Format the role category designation slug string smoothly
                                if ( ! empty( $staff_row->role_category ) ) {
                                    $designation = ucwords( str_replace( '_', ' ', $staff_row->role_category ) );
                                }
                                
                                // Extract custom file upload source target
                                if ( ! empty( $staff_row->profile_image ) ) {
                                    $custom_avatar = $staff_row->profile_image;
                                }
                            } else {
                                // Secondary fallback if the user is staff but doesn't have a database row map yet
                                $designation = ! empty( $current_user->roles ) ? ucfirst( reset( $current_user->roles ) ) : 'Staff Member';
                            }
                        }

                        // Render clean structural image markup output
                        if ( ! empty( $custom_avatar ) ) {
                            echo '<img src="' . esc_url( $custom_avatar ) . '" alt="' . esc_attr( $display_name ) . '" width="64" height="64" style="border-radius: 50%; object-fit: cover;" />';
                        } else {
                            echo get_avatar( $current_user_id, 64, '', '', array( 'class' => 'avatar-round' ) ); 
                        }
                        ?>
                    </div>
                    <div class="profile-meta">
                        <h4 class="profile-name"><?php echo esc_html( $display_name ); ?></h4>
                        <span class="profile-designation"><?php echo esc_html( $designation ); ?></span>
                    </div>
                </div>

                <ul class="arms-left-tabs">
                    <?php 
                    foreach ( $all_tabs as $slug => $config ) : 
                        if ( ! arms_has_access( $config['roles'] ) ) {
                            continue; 
                        }
                        $active_class = ( $active_tab === $slug ) ? 'active' : '';
                        
                        if ( $slug === 'logout' ) {
                            $target_url = wp_logout_url( admin_url( 'admin.php?page=rehab_management_system' ) );
                        } else {
                            $target_url = admin_url( 'admin.php?page=rehab_management_system&tab=' . $slug );
                        }
                        ?>
                        <li class="<?php echo esc_attr( 'tab-' . $slug ); ?>">
                            <a class="<?php echo esc_attr( $active_class ); ?>" href="<?php echo esc_url( $target_url ); ?>">
                                <?php echo $config['svg']; ?>
                                <span><?php echo esc_html( $config['label'] ); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="arms-right-box">
            <?php
            switch ( $active_tab ) {
                case 'dashboard':
                    if ( function_exists( 'arms_dashboard_tab' ) ) { arms_dashboard_tab(); }
                    break;
                case 'patients':
                    if ( function_exists( 'arms_patients_tab' ) ) { arms_patients_tab(); }
                    break;
                case 'opd':
                    if ( function_exists( 'arms_opd_tab' ) ) { arms_opd_tab(); }
                    break;
                case 'admission':
                    if ( function_exists( 'arms_admission_tab' ) ) { arms_admission_tab(); }
                    break;
                case 'physiotherapy':
                    if ( function_exists( 'arms_physiotherapy_tab' ) ) { arms_physiotherapy_tab(); }
                    break;
                case 'nursing':
                    if ( function_exists( 'arms_nursing_tab' ) ) { arms_nursing_tab(); }
                    break;
                case 'inventory':
                    if ( function_exists( 'arms_inventory_tab' ) ) { arms_inventory_tab(); }
                    break;
                case 'finance':
                    if ( function_exists( 'arms_finance_tab' ) ) { arms_finance_tab(); }
                    break;
                case 'payroll':
                    if ( function_exists( 'arms_payroll_tab' ) ) { arms_payroll_tab(); }
                    break;
                case 'reports':
                    if ( function_exists( 'arms_reports_tab' ) ) { arms_reports_tab(); }
                    break;
                case 'staff':
                    if ( function_exists( 'arms_staff_tab' ) ) { arms_staff_tab(); }
                    break;
                case 'settings':
                    if ( function_exists( 'arms_settings_tab' ) ) { arms_settings_tab(); }
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

            .arms-left-tabs, .arms-author-profile {
                width: 240px;
                margin: 0;
                list-style: none;
                flex-shrink: 0;
                background: #fff;
                border-right: 1px solid #e2e8f0;
            }

.arms-author-profile {
  display: flex;
  align-items: center;
  gap: 15px;
  justify-content: center;
  padding: 20px;
  border-bottom: 1px solid #ddd;
}
.arms-author-profile img {
  width: 60px !important;
  height: 60px;
  border-radius: 50%;
  border: 3px solid #4f46e5;
}
.arms-author-profile .profile-name {
  margin-bottom: 0;
  font-size: 14px;
  font-weight: 700
}
  .profile-meta span {
	font-size: 13px;
	font-weight: 500;
}
            .arms-left-tabs li a {
	display: flex;
	align-items: center;
	padding: 10px 24px;
	color: #333;
	text-decoration: none;
	font-weight: 600;
	font-size: 15px;
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
                fill: #003376;
            }

            .arms-left-tabs li a.active {
                background: #003376;
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

/**
 * 1. ROOT HOMEPAGE TO WP-ADMIN REDIRECT
 * Dynamically intercepts root homepage requests and forwards visitors straight to the admin space.
 */
function arms_root_to_admin_redirect() {
    // Skip processing if we are inside the admin panel or handling an AJAX request
    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        return;
    }

    // Safely extract paths to handle subfolder environments (e.g., /rehab-wellness-theme/) cleanly
    $home_path   = upper_safe_path_parse( home_url() );
    $request_uri = upper_safe_path_parse( $_SERVER['REQUEST_URI'] );

    // If the clean requested path matches the home directory path exactly, execute the redirect
    if ( $request_uri === $home_path ) {
        wp_safe_redirect( admin_url(), 302 );
        exit;
    }
}
add_action( 'init', 'arms_root_to_admin_redirect', 5 );

/**
 * Helper function to normalize paths for safe tracking comparisons
 */
function upper_safe_path_parse( $url ) {
    $path = parse_url( $url, PHP_URL_PATH );
    return trim( $path, '/' );
}


/**
 * 2. POST-LOGIN DASHBOARD REDIRECT
 */
function arms_custom_login_redirect( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        return admin_url( 'admin.php?page=rehab_management_system' );
    }
    return $redirect_to;
}
add_filter( 'login_redirect', 'arms_custom_login_redirect', 10, 3 );


/**
 * 3. LOGOUT ROUTING OVERRIDE
 */
function arms_custom_logout_routing() {
    wp_safe_redirect( home_url() );
    exit;
}
add_action( 'wp_logout', 'arms_custom_logout_routing' );


/**
 * 4. CUSTOM WHITE-LABEL STYLES & DUMMY LOGO MASKING
 */
function arms_custom_login_styles() {
    // Custom inline SVG serving as a modern, clean dummy logo markup asset
    $custom_logo_url = plugin_dir_url( __FILE__ ) . 'assets/img/logo.png';
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url('<?php echo $custom_logo_url; ?>') !important;
            height: 80px !important;
            width: 100% !important;
            background-size: contain !important;
            background-position: center !important;
            margin-bottom: 25px !important;
            border: 1px solid #ddd;
            background-color: #fff;
        }
        .wp-core-ui .button-group.button-large .button, .wp-core-ui .button.button-large {
            background-color: #003376 !important;
        }

        body.login {
            background: #f0f4f8 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        }

        #login { padding: 6% 0 0 !important; width: 360px !important; }

        .login form {
            background: #ffffff !important;
            border: 1px solid #e1e8ed !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05) !important;
            border-radius: 8px !important;
            padding: 30px !important;
        }

        .login label { color: #4a5568 !important; font-weight: 500 !important; }

        .login input[type="text"], .login input[type="password"] {
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            background: #f8fafc !important;
            box-shadow: none !important;
        }

        .wp-core-ui .button-primary {
            background: #3182ce !important;
            border: none !important;
            border-radius: 6px !important;
            box-shadow: none !important;
            font-weight: 600 !important;
            height: 40px !important;
            width: 100% !important;
            margin-top: 15px !important;
        }

        .wp-core-ui .button-primary:hover { background: #2b6cb0 !important; }

        /* Hide default WordPress footer navigation text links */
        .login #backtoblog, .login #nav, .privacy-policy-page-link {
            display: none !important;
        }
        
        .arms-captcha-container { margin: 15px 0; }
        .arms-captcha-label { display: block; margin-bottom: 5px; font-weight: bold; }
    </style>
    <?php
}
add_action( 'login_enqueue_scripts', 'arms_custom_login_styles' );

function arms_login_logo_url() { return home_url(); }
add_filter( 'login_headerurl', 'arms_login_logo_url' );

function arms_login_logo_title() { return get_bloginfo( 'name' ); }
add_filter( 'login_headertext', 'arms_login_logo_title' );


/**
 * 5. MATHEMATICAL CAPTCHA ENGINE
 */
function arms_display_login_captcha() {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $captcha_token = md5( uniqid( rand(), true ) );
    set_transient( 'arms_captcha_' . $captcha_token, ($num1 + $num2), 300 );
    ?>
    <div class="arms-captcha-container">
        <label class="arms-captcha-label" for="arms_captcha_answer">Security Verification</label>
        <p style="margin: 0 0 8px 0; color: #718096; font-size: 13px;">
            Please solve: <strong><?php echo $num1; ?> + <?php echo $num2; ?> = ?</strong>
        </p>
        <input type="text" name="arms_captcha_answer" id="arms_captcha_answer" class="input" value="" size="4" autocomplete="off" required />
        <input type="hidden" name="arms_captcha_token" value="<?php echo esc_attr( $captcha_token ); ?>" />
    </div>
    <?php
}
add_action( 'login_form', 'arms_display_login_captcha' );

function arms_validate_login_captcha( $user, $username, $password ) {
    if ( is_wp_error( $user ) ) { return $user; }

    $user_answer = isset( $_POST['arms_captcha_answer'] ) ? sanitize_text_field( $_POST['arms_captcha_answer'] ) : '';
    $token       = isset( $_POST['arms_captcha_token'] ) ? sanitize_text_field( $_POST['arms_captcha_token'] ) : '';
    
    $correct_answer = get_transient( 'arms_captcha_' . $token );
    delete_transient( 'arms_captcha_' . $token );

    if ( $correct_answer === false || intval( $user_answer ) !== intval( $correct_answer ) ) {
        return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Incorrect security verification answer.' ) );
    }
    return $user;
}
add_filter( 'authenticate', 'arms_validate_login_captcha', 25, 3 );