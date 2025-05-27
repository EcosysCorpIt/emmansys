<?php
/**
 * Plugin Name:       EmManSys
 * Description:       A simple plugin to create, edit, delete, and list employees and manage leave requests. Requires User Role Editor plugin.
 * Version:           1.1.7
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       emmansys
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main plugin class
 */
final class Employee_Management_System {

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.1.7'; 

    /**
     * The single instance of the class.
     *
     * @var Employee_Management_System
     * @since 1.0.0
     */
    private static $_instance = null;

    /**
     * Flag to indicate if dependency is met.
     * @var bool
     */
    private $dependency_met = false;

    /**
     * Hook suffix for the dashboard page.
     * @var string
     */
    private $dashboard_page_hook_suffix = '';

    /**
     * Hook suffix for the leave types page.
     * @var string
     */
    private $leave_types_page_hook_suffix = '';


    /**
     * Ensures only one instance of the class is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Employee_Management_System - Main instance.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();
        add_action( 'admin_init', array( $this, 'check_dependencies' ) );
        add_action( 'plugins_loaded', array( $this, 'init_plugin_if_dependency_met' ) );
    }

    /**
     * Initialize the plugin if dependencies are met.
     */
    public function init_plugin_if_dependency_met() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( is_plugin_active( 'user-role-editor/user-role-editor.php' ) ) {
            $this->dependency_met = true;
            $this->includes(); 
            $this->init_hooks(); 
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        } else {
            add_action( 'admin_notices', array( $this, 'dependency_missing_notice' ) );
        }
    }


    /**
     * Define Plugin Constants.
     */
    private function define_constants() {
        define( 'EMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'EMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'EMS_PLUGIN_FILE', __FILE__ );
    }

    /**
     * Check for required plugin dependencies.
     */
    public function check_dependencies() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( is_plugin_active( 'user-role-editor/user-role-editor.php' ) ) {
            $this->dependency_met = true;
        } else {
            $this->dependency_met = false;
        }
    }

    /**
     * Display admin notice if dependency is missing.
     */
    public function dependency_missing_notice() {
        if ( !$this->dependency_met ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <?php
                    printf(
                        /* translators: 1: Plugin Name, 2: Required Plugin Name */
                        esc_html__( '%1$s requires the %2$s plugin to be installed and activated. Please install and activate %2$s.', 'emmansys' ),
                        '<strong>EmManSys (Employee Management System)</strong>',
                        '<strong>User Role Editor</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }


    /**
     * Include required files. Only called if dependencies are met.
     */
    private function includes() {
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-leave-options.php';
    }

    /**
     * Hook into actions and filters. Only called if dependencies are met.
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook( EMS_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( EMS_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Admin Menu
        add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );


        // Register Employee CPT
        add_action( 'init', array( $this, 'register_employee_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_employee_meta_boxes' ) );
        add_action( 'save_post_employee', array( $this, 'save_employee_meta_data' ), 10, 2 );
        add_filter( 'manage_employee_posts_columns', array( $this, 'set_employee_columns' ) );
        add_action( 'manage_employee_posts_custom_column', array( $this, 'render_employee_columns' ), 10, 2 );
        add_filter( 'manage_edit-employee_sortable_columns', array( $this, 'make_employee_columns_sortable' ) );
        add_action( 'pre_get_posts', array( $this, 'sort_employee_columns_query' ) );
        add_shortcode( 'list_employees', array( $this, 'render_employee_list_shortcode' ) );

        // Register Leave Request CPT
        add_action( 'init', array( $this, 'register_leave_request_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_leave_request_meta_boxes' ) );
        add_action( 'save_post_leave_request', array( $this, 'save_leave_request_meta_data' ), 20, 3 ); 
        add_filter( 'manage_leave_request_posts_columns', array( $this, 'set_leave_request_columns' ) );
        add_action( 'manage_leave_request_posts_custom_column', array( $this, 'render_leave_request_columns' ), 10, 2 );
        add_filter( 'manage_edit-leave_request_sortable_columns', array( $this, 'make_leave_request_columns_sortable' ) );
        add_action( 'pre_get_posts', array( $this, 'sort_leave_request_columns_query' ) );

        // User Profile Leave Management
        add_action( 'show_user_profile', array( $this, 'show_leave_management_on_profile' ) );
        add_action( 'edit_user_profile', array( $this, 'show_leave_management_on_profile' ) );
        
        // AJAX and Form Handlers
        add_action( 'admin_post_ems_submit_leave_request', array( $this, 'handle_profile_leave_request_submission' ) ); 
        add_action( 'wp_ajax_ems_submit_profile_leave', array( $this, 'ajax_handle_profile_leave_submission' ) ); 
        add_action( 'admin_notices', array( $this, 'show_general_admin_notices' ) );

        // Handler for Leave Types Management
        add_action( 'admin_post_ems_manage_leave_types', array( $this, 'handle_leave_types_form_submission' ) );
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menus() {
        // Employee Dashboard (My Dashboard)
        $this->dashboard_page_hook_suffix = add_menu_page(
            __( 'My Dashboard', 'emmansys' ),
            __( 'My Dashboard', 'emmansys' ),
            'submit_profile_leave_request', 
            'ems-employee-dashboard',
            array( $this, 'render_employee_dashboard_page' ),
            'dashicons-id-alt', 
            30 
        );

        // Leave Types Management Page (Submenu under Leave Requests CPT menu)
        $this->leave_types_page_hook_suffix = add_submenu_page(
            'edit.php?post_type=leave_request', 
            __( 'Manage Leave Types', 'emmansys' ),    
            __( 'Leave Types', 'emmansys' ),       
            'manage_options',                      
            'ems-leave-types',                     
            array( $this, 'render_leave_types_admin_page' ) 
        );
    }

    /**
     * Render the content for the Employee Dashboard admin page.
     */
    public function render_employee_dashboard_page() { /* ... same as 1.1.6 ... */ if ( !current_user_can('submit_profile_leave_request') ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) ); } $user_for_dashboard = wp_get_current_user(); if ( ! ($user_for_dashboard instanceof WP_User) || ! $user_for_dashboard->ID ) { echo '<div class="wrap"><p>' . esc_html__('Error: Could not retrieve current user information. Please try logging in again.', 'emmansys') . '</p></div>'; return; } ?> <div class="wrap ems-dashboard"> <h1><?php esc_html_e( 'Employee Dashboard', 'emmansys' ); ?></h1> <?php $this->show_leave_management_on_profile( $user_for_dashboard, true ); ?> </div> <style> .ems-dashboard .form-table { margin-bottom: 20px; } .ems-dashboard .notice { margin-top: 15px; margin-bottom: 15px; } </style> <?php }

    /**
     * Render the admin page for managing custom leave types.
     * @since 1.1.4
     */
    public function render_leave_types_admin_page() { /* ... same as 1.1.6 ... */ if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) ); } $editing_key = null; $edit_type_data = array('label' => '', 'initial_balance' => 0); if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['type_key'] ) ) { $current_editing_key_from_url = sanitize_key( $_GET['type_key'] ); $custom_types = EMS_Leave_Options::get_custom_leave_types(); if ( isset( $custom_types[ $current_editing_key_from_url ] ) ) { $retrieved_data = $custom_types[ $current_editing_key_from_url ]; if (is_array($retrieved_data) && isset($retrieved_data['label'])) { $edit_type_data = $retrieved_data; $editing_key = $current_editing_key_from_url; } else { add_settings_error( 'ems_leave_types_notices', 'error_malformed_data', sprintf( __('Data for leave type key "%s" is malformed. It might be old data. Please delete and re-add it, or check the raw option in the database if you are comfortable doing so.', 'emmansys'), esc_html($current_editing_key_from_url) ), 'error' ); } } else { add_settings_error('ems_leave_types_notices', 'error_editing', __('Leave type not found for editing.', 'emmansys'), 'error'); } } ?> <div class="wrap"> <h1><?php esc_html_e( 'Manage Custom Leave Types', 'emmansys' ); ?></h1> <?php settings_errors('ems_leave_types_notices'); ?> <div id="col-container" class="wp-clearfix"> <div id="col-left"> <div class="col-wrap"> <h2><?php echo $editing_key ? esc_html__( 'Edit Leave Type', 'emmansys' ) : esc_html__( 'Add New Leave Type', 'emmansys' ); ?></h2> <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"> <input type="hidden" name="action" value="ems_manage_leave_types"> <?php wp_nonce_field( 'ems_manage_leave_types_nonce', '_wpnonce_ems_leave_types' ); ?> <?php if ( $editing_key ) : ?> <input type="hidden" name="ems_leave_type_action" value="update"> <input type="hidden" name="ems_original_leave_type_key" value="<?php echo esc_attr( $editing_key ); ?>"> <?php else : ?> <input type="hidden" name="ems_leave_type_action" value="add"> <?php endif; ?> <div class="form-field term-name-wrap"> <label for="ems_leave_type_key"><?php esc_html_e( 'Leave Type Key', 'emmansys' ); ?></label> <input name="ems_leave_type_key" id="ems_leave_type_key" type="text" value="<?php echo esc_attr( $editing_key ? $editing_key : '' ); ?>" size="40" <?php echo $editing_key ? 'readonly' : ''; ?>> <p><?php esc_html_e('A unique identifier (e.g., "study_leave", "bereavement_leave"). Cannot be changed after creation. Only lowercase letters, numbers, and underscores.', 'emmansys'); ?></p> </div> <div class="form-field term-slug-wrap"> <label for="ems_leave_type_label"><?php esc_html_e( 'Label', 'emmansys' ); ?></label> <input name="ems_leave_type_label" id="ems_leave_type_label" type="text" value="<?php echo esc_attr( $edit_type_data['label'] ); ?>" size="40"> <p><?php esc_html_e('The name is how it appears on your site.', 'emmansys'); ?></p> </div> <div class="form-field term-description-wrap"> <label for="ems_leave_type_balance"><?php esc_html_e( 'Initial Balance (days/units)', 'emmansys' ); ?></label> <input name="ems_leave_type_balance" id="ems_leave_type_balance" type="number" value="<?php echo esc_attr( $edit_type_data['initial_balance'] ); ?>" min="0" step="0.5" style="width: 100px;"> <p><?php esc_html_e('Default initial balance for this leave type when assigned to new employees (can be overridden per employee).', 'emmansys'); ?></p> </div> <?php if ( $editing_key ) : ?> <?php submit_button( __( 'Update Leave Type', 'emmansys' ) ); ?> <a href="<?php echo esc_url( admin_url('edit.php?post_type=leave_request&page=ems-leave-types') ); ?>" class="button"><?php esc_html_e('Cancel Edit', 'emmansys'); ?></a> <?php else : ?> <?php submit_button( __( 'Add New Leave Type', 'emmansys' ) ); ?> <?php endif; ?> </form> </div> </div> <div id="col-right"> <div class="col-wrap"> <h2><?php esc_html_e( 'Current Custom Leave Types', 'emmansys' ); ?></h2> <table class="wp-list-table widefat fixed striped tags"> <thead> <tr> <th scope="col" id="name" class="manage-column column-name"><?php esc_html_e( 'Label', 'emmansys' ); ?></th> <th scope="col" id="slug" class="manage-column column-slug"><?php esc_html_e( 'Key', 'emmansys' ); ?></th> <th scope="col" id="balance" class="manage-column column-balance" style="width:120px;"><?php esc_html_e( 'Initial Balance', 'emmansys' ); ?></th> </tr> </thead> <tbody id="the-list" data-wp-lists="list:tag"> <?php $custom_types = EMS_Leave_Options::get_custom_leave_types(); if ( empty( $custom_types ) ) : ?> <tr class="no-items"><td class="colspanchange" colspan="3"><?php esc_html_e( 'No custom leave types found.', 'emmansys' ); ?></td></tr> <?php else : foreach ( $custom_types as $key => $data ) : $display_label = (is_array($data) && isset($data['label'])) ? $data['label'] : __('Malformed Data - Please Edit/Delete', 'emmansys'); $display_balance = (is_array($data) && isset($data['initial_balance'])) ? $data['initial_balance'] : 'N/A'; ?> <tr> <td class="name column-name"> <strong><a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'type_key' => $key ), admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types' ) ) ); ?>"><?php echo esc_html( $display_label ); ?></a></strong> <div class="row-actions"> <span class="edit"><a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'type_key' => $key ), admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types' ) ) ); ?>"><?php esc_html_e( 'Edit', 'emmansys' ); ?></a> | </span> <span class="delete"><a class="delete-tag" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ems_manage_leave_types', 'ems_leave_type_action' => 'delete', 'type_key' => $key ), admin_url( 'admin-post.php' ) ), 'ems_delete_leave_type_' . $key, '_wpnonce_ems_delete_leave_type' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this leave type? This action cannot be undone.', 'emmansys' ); ?>');"><?php esc_html_e( 'Delete', 'emmansys' ); ?></a></span> </div> </td> <td class="slug column-slug"><?php echo esc_html( $key ); ?></td> <td class="balance column-balance"><?php echo esc_html( $display_balance ); ?></td> </tr> <?php endforeach; endif; ?> </tbody> <tfoot> <tr> <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Label', 'emmansys' ); ?></th> <th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Key', 'emmansys' ); ?></th> <th scope="col" class="manage-column column-balance"><?php esc_html_e( 'Initial Balance', 'emmansys' ); ?></th> </tr> </tfoot> </table> </div> </div> </div> </div> <?php }

    /**
     * Handle form submissions for managing custom leave types.
     * @since 1.1.4
     */
    public function handle_leave_types_form_submission() { /* ... same as 1.1.6 ... */ if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to manage leave types.', 'emmansys' ) ); } $action = isset( $_POST['ems_leave_type_action'] ) ? sanitize_key( $_POST['ems_leave_type_action'] ) : (isset($_GET['ems_leave_type_action']) ? sanitize_key($_GET['ems_leave_type_action']) : ''); $redirect_url = admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types' ); if ( $action === 'add' || $action === 'update' ) { check_admin_referer( 'ems_manage_leave_types_nonce', '_wpnonce_ems_leave_types' ); $key = isset( $_POST['ems_leave_type_key'] ) ? sanitize_key( $_POST['ems_leave_type_key'] ) : ''; $label = isset( $_POST['ems_leave_type_label'] ) ? sanitize_text_field( $_POST['ems_leave_type_label'] ) : ''; $balance = isset( $_POST['ems_leave_type_balance'] ) ? floatval( $_POST['ems_leave_type_balance'] ) : 0; if ( empty( $key ) || empty( $label ) ) { add_settings_error( 'ems_leave_types_notices', 'fields_required', __( 'Leave Type Key and Label are required.', 'emmansys' ), 'error' ); } else { if ($action === 'update' && isset($_POST['ems_original_leave_type_key'])) { $key = sanitize_key($_POST['ems_original_leave_type_key']); } if ( EMS_Leave_Options::add_or_update_custom_leave_type( $key, $label, $balance ) ) { add_settings_error( 'ems_leave_types_notices', 'success', $action === 'add' ? __( 'Leave type added successfully.', 'emmansys' ) : __( 'Leave type updated successfully.', 'emmansys' ), 'success' ); } else { add_settings_error( 'ems_leave_types_notices', 'error_saving', __( 'Failed to save leave type.', 'emmansys' ), 'error' ); } } } elseif ( $action === 'delete' ) { $key_to_delete = isset( $_GET['type_key'] ) ? sanitize_key( $_GET['type_key'] ) : ''; check_admin_referer( 'ems_delete_leave_type_' . $key_to_delete, '_wpnonce_ems_delete_leave_type' ); if ( ! empty( $key_to_delete ) ) { if ( EMS_Leave_Options::delete_custom_leave_type( $key_to_delete ) ) { add_settings_error( 'ems_leave_types_notices', 'success_delete', __( 'Leave type deleted successfully.', 'emmansys' ), 'success' ); } else { add_settings_error( 'ems_leave_types_notices', 'error_deleting', __( 'Failed to delete leave type or type not found.', 'emmansys' ), 'error' ); } } else { add_settings_error( 'ems_leave_types_notices', 'error_deleting', __( 'No leave type specified for deletion.', 'emmansys' ), 'error' ); } } set_transient('settings_errors', get_settings_errors(), 30); wp_safe_redirect( $redirect_url ); exit; }

    /**
     * Display general admin notices stored in transient.
     * @since 1.1.4
     */
    public function show_general_admin_notices() { /* ... same as 1.1.6 ... */ if ( $errors = get_transient( 'settings_errors' ) ) { settings_errors( 'ems_leave_types_notices', false, true ); settings_errors( 'ems_leave_notice', false, true ); delete_transient( 'settings_errors' ); } if ( $message = get_transient( 'ems_leave_notice_success' ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; delete_transient( 'ems_leave_notice_success' ); } if ( $message = get_transient( 'ems_leave_notice_error' ) ) { echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; delete_transient( 'ems_leave_notice_error' ); } }


    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts( $hook_suffix ) { /* ... same as 1.1.6 ... */ if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) { $screen = get_current_screen(); if ( $screen && 'leave_request' === $screen->post_type ) { wp_enqueue_script( 'ems-admin-leave-validation', EMS_PLUGIN_URL . 'js/admin-leave-validation.js', array( 'jquery' ), self::VERSION, true ); wp_localize_script('ems-admin-leave-validation', 'ems_leave_admin_data', array( 'today' => current_time('Y-m-d'), )); } } if ( 'profile.php' === $hook_suffix || 'user-edit.php' === $hook_suffix || $hook_suffix === $this->dashboard_page_hook_suffix ) { wp_enqueue_script( 'ems-profile-leave-validation', EMS_PLUGIN_URL . 'js/profile-leave-validation.js', array( 'jquery' ), self::VERSION, true ); wp_localize_script('ems-profile-leave-validation', 'ems_profile_leave_data', array( 'today' => current_time('Y-m-d'), 'ajax_url' => admin_url('admin-ajax.php'), 'nonce'    => wp_create_nonce('ems_ajax_profile_leave_nonce'), 'action'   => 'ems_submit_profile_leave', 'success_message' => __('Leave request submitted successfully. It is now pending approval.', 'emmansys'), 'error_message_general' => __('An error occurred. Please try again.', 'emmansys'), 'error_all_fields_required' => __('All fields are required for leave submission.', 'emmansys'), 'error_start_date_past' => __('Error: Start date cannot be in the past.', 'emmansys'), 'error_end_date_invalid' => __('Error: End date cannot be earlier than the start date.', 'emmansys'), 'form_id_profile' => '#ems-profile-leave-form', 'form_id_dashboard' => '#ems-dashboard-leave-form' )); } if ($hook_suffix === $this->leave_types_page_hook_suffix) { wp_enqueue_style( 'wp-admin' ); } }
    
    /**
     * Plugin activation.
     */
    public function activate() { /* ... same as 1.1.6 ... */ $this->register_employee_cpt(); $this->register_leave_request_cpt(); flush_rewrite_rules(); $js_admin_dir = EMS_PLUGIN_DIR . 'js/'; if (!is_dir($js_admin_dir)) { wp_mkdir_p($js_admin_dir); } if (!file_exists($js_admin_dir . 'admin-leave-validation.js')) { file_put_contents($js_admin_dir . 'admin-leave-validation.js', '// Admin Leave Validation Script - For Leave CPT Edit Screen'); } if (!file_exists($js_admin_dir . 'profile-leave-validation.js')) { file_put_contents($js_admin_dir . 'profile-leave-validation.js', '// Profile/Dashboard Leave Validation Script'); } }

    /**
     * Plugin deactivation.
     */
    public function deactivate() { /* ... same as 1.1.6 ... */ flush_rewrite_rules(); }

    // --- Employee CPT Methods ---
    public function register_employee_cpt() { /* ... same as v1.1.3 ... */ $labels = array( 'name' => _x( 'Employees', 'Post type general name', 'emmansys' ), 'singular_name' => _x( 'Employee', 'Post type singular name', 'emmansys' ), 'menu_name' => _x( 'Employees', 'Admin Menu text', 'emmansys' ), 'name_admin_bar' => _x( 'Employee', 'Add New on Toolbar', 'emmansys' ), 'add_new' => __( 'Add New Employee', 'emmansys' ), 'add_new_item' => __( 'Add New Employee', 'emmansys' ), 'new_item' => __( 'New Employee', 'emmansys' ), 'edit_item' => __( 'Edit Employee', 'emmansys' ), 'view_item' => __( 'View Employee', 'emmansys' ), 'all_items' => __( 'All Employees', 'emmansys' ), 'search_items' => __( 'Search Employees', 'emmansys' ), 'parent_item_colon' => __( 'Parent Employees:', 'emmansys' ), 'not_found' => __( 'No employees found.', 'emmansys' ), 'not_found_in_trash' => __( 'No employees found in Trash.', 'emmansys' ), 'featured_image' => _x( 'Employee Photo', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'emmansys' ), 'set_featured_image' => _x( 'Set employee photo', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'emmansys' ), 'remove_featured_image' => _x( 'Remove employee photo', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'emmansys' ), 'use_featured_image' => _x( 'Use as employee photo', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'emmansys' ), 'archives' => _x( 'Employee archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'emmansys' ), 'insert_into_item' => _x( 'Insert into employee', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'emmansys' ), 'uploaded_to_this_item' => _x( 'Uploaded to this employee', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'emmansys' ), 'filter_items_list' => _x( 'Filter employees list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'emmansys' ), 'items_list_navigation' => _x( 'Employees list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'emmansys' ), 'items_list' => _x( 'Employees list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'emmansys' ),); $args = array( 'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => array( 'slug' => 'employee' ), 'capability_type' => 'employee', 'capabilities' => array( 'edit_post' => 'edit_employee', 'read_post' => 'read_employee', 'delete_post' => 'delete_employee', 'edit_posts' => 'edit_employees', 'edit_others_posts' => 'edit_others_employees', 'publish_posts' => 'publish_employees', 'read_private_posts' => 'read_private_employees', 'delete_posts' => 'delete_employees', 'delete_private_posts' => 'delete_private_employees', 'delete_published_posts' => 'delete_published_employees', 'delete_others_posts' => 'delete_others_employees', 'edit_private_posts' => 'edit_private_employees', 'edit_published_posts' => 'edit_published_employees', ), 'map_meta_cap' => true, 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 20, 'menu_icon' => 'dashicons-groups', 'supports' => array( 'thumbnail', 'custom-fields' ), ); register_post_type( 'employee', $args ); }
    public function add_employee_meta_boxes() { /* ... same as v1.1.3 ... */ global $typenow; if ( $typenow === 'employee' ) { $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0); if ( ($post_id && current_user_can('edit_employee', $post_id)) || current_user_can('publish_employees') ) { add_meta_box('employee_details_meta_box', __( 'Employee Details', 'emmansys' ), array( $this, 'render_employee_details_meta_box' ), 'employee', 'normal', 'high'); } } }
    public function render_employee_details_meta_box( $post ) { /* ... same as v1.1.3 ... */ wp_nonce_field( 'ems_save_employee_details', 'ems_employee_details_nonce' ); $employee_full_name = get_post_meta( $post->ID, '_employee_full_name', true ); if (empty($employee_full_name) && $post->post_title !== 'Auto Draft' && $post->post_title !== 'auto-draft') { $employee_full_name = $post->post_title; } $linked_user_id = get_post_meta( $post->ID, '_employee_user_id', true ); $employee_id_meta = get_post_meta( $post->ID, '_employee_id', true ); $department  = get_post_meta( $post->ID, '_employee_department', true ); $position = get_post_meta( $post->ID, '_employee_position', true ); $email = get_post_meta( $post->ID, '_employee_email', true ); $phone = get_post_meta( $post->ID, '_employee_phone', true ); $salary = get_post_meta( $post->ID, '_employee_salary', true ); $users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) ); ?> <table class="form-table"><tbody><tr><th><label for="ems_employee_full_name"><?php _e( 'Employee Full Name', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_full_name" name="ems_employee_full_name" value="<?php echo esc_attr( $employee_full_name ); ?>" class="regular-text" required /><p class="description"><?php _e( 'This name will be used as the employee record identifier.', 'emmansys' ); ?></p></td></tr><tr><th><label for="ems_employee_user_id"><?php _e( 'Linked WordPress User', 'emmansys' ); ?></label></th><td><select id="ems_employee_user_id" name="ems_employee_user_id"><option value=""><?php _e( '-- Select a User --', 'emmansys' ); ?></option><?php foreach ( $users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $linked_user_id, $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?> (ID: <?php echo esc_html( $user->ID ); ?>)</option><?php endforeach; ?></select><p class="description"><?php _e( 'Tag this employee record to a WordPress user account. This is required for leave filing from profile.', 'emmansys' ); ?></p></td></tr><tr><th><label for="ems_employee_id_field"><?php _e( 'Employee ID', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_id_field" name="ems_employee_id_field" value="<?php echo esc_attr( $employee_id_meta ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_department"><?php _e( 'Department', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_department" name="ems_employee_department" value="<?php echo esc_attr( $department ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_position"><?php _e( 'Position', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_position" name="ems_employee_position" value="<?php echo esc_attr( $position ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_email"><?php _e( 'Email Address', 'emmansys' ); ?></label></th><td><input type="email" id="ems_employee_email" name="ems_employee_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_phone"><?php _e( 'Phone Number', 'emmansys' ); ?></label></th><td><input type="tel" id="ems_employee_phone" name="ems_employee_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_salary"><?php _e( 'Salary', 'emmansys' ); ?></label></th><td><input type="number" id="ems_employee_salary" name="ems_employee_salary" value="<?php echo esc_attr( $salary ); ?>" class="regular-text" step="0.01" /></td></tr></tbody></table> <?php }
    public function save_employee_meta_data( $post_id, $post ) { /* ... same as v1.1.3 ... */ if ( ! isset( $_POST['ems_employee_details_nonce'] ) || ! wp_verify_nonce( $_POST['ems_employee_details_nonce'], 'ems_save_employee_details' ) ) return; if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; if ( $post->post_type !== 'employee' ) return; if ( !current_user_can( 'edit_employee', $post_id ) ) { return; } $fields_to_save = array('_employee_full_name'  => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_full_name'), '_employee_user_id' => array('sanitize_callback' => 'absint', 'field_name' => 'ems_employee_user_id'), '_employee_id' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_id_field'), '_employee_department' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_department'), '_employee_position' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_position'), '_employee_email' => array('sanitize_callback' => 'sanitize_email', 'field_name' => 'ems_employee_email'), '_employee_phone' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_phone'), '_employee_salary' => array('sanitize_callback' => 'floatval', 'field_name' => 'ems_employee_salary'),); foreach ( $fields_to_save as $meta_key => $field_config ) { if ( isset( $_POST[ $field_config['field_name'] ] ) ) { $value = call_user_func( $field_config['sanitize_callback'], $_POST[ $field_config['field_name'] ] ); update_post_meta( $post_id, $meta_key, $value ); } } if (isset($_POST['ems_employee_full_name']) && !empty($_POST['ems_employee_full_name'])) { $full_name = sanitize_text_field($_POST['ems_employee_full_name']); if ($post->post_title !== $full_name) { remove_action('save_post_employee', array($this, 'save_employee_meta_data'), 10); wp_update_post(array('ID' => $post_id, 'post_title' => $full_name, 'post_name' => sanitize_title($full_name) )); add_action('save_post_employee', array($this, 'save_employee_meta_data'), 10, 2); } } }
    public function set_employee_columns( $columns ) { /* ... same as v1.1.3 ... */ unset($columns['title']); $new_columns = array(); $new_columns['cb'] = $columns['cb']; $new_columns['ems_employee_name_col'] = __( 'Employee Full Name', 'emmansys' ); $new_columns['ems_linked_user'] = __( 'Linked WP User', 'emmansys' ); $new_columns['ems_employee_id'] = __( 'Employee ID', 'emmansys' ); $new_columns['ems_department'] = __( 'Department', 'emmansys' ); $new_columns['ems_position'] = __( 'Position', 'emmansys' ); $new_columns['ems_email'] = __( 'Email', 'emmansys' ); $new_columns['date'] = $columns['date']; return $new_columns; }
    public function render_employee_columns( $column, $post_id ) { /* ... same as v1.1.3 ... */ switch ( $column ) { case 'ems_employee_name_col': echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>'; break; case 'ems_linked_user': $user_id = get_post_meta( $post_id, '_employee_user_id', true ); if ($user_id && $user = get_userdata($user_id)) { echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . ' (ID: ' . esc_html($user_id) . ')</a>'; } else { echo '—'; } break; case 'ems_employee_id': echo esc_html( get_post_meta( $post_id, '_employee_id', true ) ); break; case 'ems_department': echo esc_html( get_post_meta( $post_id, '_employee_department', true ) ); break; case 'ems_position': echo esc_html( get_post_meta( $post_id, '_employee_position', true ) ); break; case 'ems_email': echo esc_html( get_post_meta( $post_id, '_employee_email', true ) ); break; } }
    public function make_employee_columns_sortable( $columns ) { /* ... same as v1.1.3 ... */ $columns['ems_employee_name_col'] = 'title'; $columns['ems_linked_user'] = 'ems_linked_user_sort'; $columns['ems_employee_id'] = 'ems_employee_id_sort'; $columns['ems_department'] = 'ems_department_sort'; $columns['ems_position'] = 'ems_position_sort'; return $columns; }
    public function sort_employee_columns_query( $query ) { /* ... same as v1.1.3 ... */ if ( ! is_admin() || ! $query->is_main_query() ) return; $orderby = $query->get( 'orderby' ); $post_type = $query->get('post_type'); if ($post_type === 'employee') { if ( 'ems_linked_user_sort' === $orderby ) { $query->set( 'meta_key', '_employee_user_id' ); $query->set( 'orderby', 'meta_value_num' ); } elseif ( 'ems_employee_id_sort' === $orderby ) { $query->set( 'meta_key', '_employee_id' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_department_sort' === $orderby ) { $query->set( 'meta_key', '_employee_department' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_position_sort' === $orderby ) { $query->set( 'meta_key', '_employee_position' ); $query->set( 'orderby', 'meta_value' ); } } }
    public function render_employee_list_shortcode( $atts ) { /* ... same as v1.1.3 ... */ $atts = shortcode_atts( array('count' => 10, 'department' => ''), $atts, 'list_employees' ); $args = array( 'post_type' => 'employee', 'posts_per_page' => intval( $atts['count'] ), 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'); if ( ! empty( $atts['department'] ) ) { $args['meta_query'] = array( array( 'key' => '_employee_department', 'value' => sanitize_text_field( $atts['department'] ), 'compare' => '=' ) ); } $employees_query = new WP_Query( $args ); $output = ''; if ( $employees_query->have_posts() ) { $output .= '<ul class="employee-list">'; while ( $employees_query->have_posts() ) { $employees_query->the_post(); $post_id = get_the_ID(); $name = get_the_title(); $position = get_post_meta( $post_id, '_employee_position', true ); $department_meta = get_post_meta( $post_id, '_employee_department', true ); $email = get_post_meta( $post_id, '_employee_email', true ); $permalink = get_permalink(); $output .= '<li>'; if ( has_post_thumbnail() ) $output .= '<div class="employee-photo">' . get_the_post_thumbnail( $post_id, 'thumbnail' ) . '</div>'; $output .= '<div class="employee-info">'; $output .= '<strong><a href="' . esc_url($permalink) . '">' . esc_html( $name ) . '</a></strong><br />'; if ( $position ) $output .= esc_html( $position ) . '<br />'; if ( $department_meta ) $output .= '<em>' . esc_html( $department_meta ) . '</em><br />'; if ( $email ) $output .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a><br />'; $output .= '</div></li>'; } $output .= '</ul>'; wp_reset_postdata(); } else { $output = '<p>' . __( 'No employees found.', 'emmansys' ) . '</p>'; } $output .= '<style>.employee-list { list-style: none; padding: 0; } .employee-list li { display: flex; align-items: flex-start; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; } .employee-list .employee-photo img { width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 50%; } .employee-list .employee-info { flex-grow: 1; } .employee-list .employee-info strong { font-size: 1.2em; }</style>'; return $output; }


    // --- Leave Request CPT Methods ---
    public function register_leave_request_cpt() { /* ... same as v1.1.3 ... */ $labels = array( 'name' => _x( 'Leave Requests', 'Post type general name', 'emmansys' ), 'singular_name' => _x( 'Leave Request', 'Post type singular name', 'emmansys' ), 'menu_name' => _x( 'Leave Requests', 'Admin Menu text', 'emmansys' ), 'name_admin_bar' => _x( 'Leave Request', 'Add New on Toolbar', 'emmansys' ), 'add_new' => __( 'Add New Leave Request', 'emmansys' ), 'add_new_item' => __( 'Add New Leave Request', 'emmansys' ), 'new_item' => __( 'New Leave Request', 'emmansys' ), 'edit_item' => __( 'Edit Leave Request', 'emmansys' ), 'view_item' => __( 'View Leave Request', 'emmansys' ), 'all_items' => __( 'All Leave Requests', 'emmansys' ), 'search_items' => __( 'Search Leave Requests', 'emmansys' ), 'not_found' => __( 'No leave requests found.', 'emmansys' ), 'not_found_in_trash' => __( 'No leave requests found in Trash.', 'emmansys' ), 'archives' => _x( 'Leave Request Archives', 'The post type archive label used in nav menus.', 'emmansys' ), 'insert_into_item' => _x( 'Insert into leave request', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post).', 'emmansys' ), 'uploaded_to_this_item' => _x( 'Uploaded to this leave request', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'emmansys' ), 'filter_items_list' => _x( 'Filter leave requests list', 'Screen reader text for the filter links heading on the post type listing screen.', 'emmansys' ), 'items_list_navigation' => _x( 'Leave requests list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'emmansys' ), 'items_list' => _x( 'Leave requests list', 'Screen reader text for the items list heading on the post type listing screen.', 'emmansys' ),); $args = array( 'labels' => $labels, 'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => array( 'slug' => 'leave-request' ), 'capability_type' => 'leave_request', 'capabilities' => array( 'edit_post' => 'edit_leave_request', 'read_post' => 'read_leave_request', 'delete_post' => 'delete_leave_request', 'edit_posts' => 'edit_leave_requests', 'edit_others_posts' => 'edit_others_leave_requests', 'publish_posts' => 'publish_leave_requests', 'read_private_posts' => 'read_private_leave_requests', 'delete_posts' => 'delete_leave_requests', 'delete_private_posts' => 'delete_private_leave_requests', 'delete_published_posts' => 'delete_published_leave_requests', 'delete_others_posts' => 'delete_others_leave_requests', 'edit_private_posts' => 'edit_private_leave_requests', 'edit_published_posts' => 'edit_published_leave_requests', ), 'map_meta_cap' => true, 'has_archive' => false, 'hierarchical' => false, 'menu_position' => 21, 'menu_icon' => 'dashicons-calendar-alt', 'supports' => array( 'custom-fields' ), ); register_post_type( 'leave_request', $args ); }
    public function add_leave_request_meta_boxes() { /* ... same as v1.1.3 ... */ global $typenow; if ( $typenow === 'leave_request' ) { $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0); if ( ($post_id && current_user_can('edit_leave_request', $post_id)) || current_user_can('publish_leave_requests') ) { add_meta_box('leave_request_details_meta_box', __( 'Leave Request Details', 'emmansys' ), array( $this, 'render_leave_request_details_meta_box' ), 'leave_request', 'normal', 'high'); } } }
    public function render_leave_request_details_meta_box( $post ) { /* ... same as v1.1.3 ... */ wp_nonce_field( 'ems_save_leave_request_details', 'ems_leave_request_details_nonce' ); $selected_employee_cpt_id = get_post_meta( $post->ID, '_leave_employee_cpt_id', true ); $leave_type_key = get_post_meta( $post->ID, '_leave_type', true ); $start_date = get_post_meta( $post->ID, '_leave_start_date', true ); $end_date   = get_post_meta( $post->ID, '_leave_end_date', true ); $leave_duration = get_post_meta( $post->ID, '_leave_duration', true ); $leave_reason = get_post_meta( $post->ID, '_leave_reason', true ); $leave_status = get_post_meta( $post->ID, '_leave_status', true ); $admin_notes = get_post_meta( $post->ID, '_leave_admin_notes', true ); $all_leave_types = EMS_Leave_Options::get_leave_types(); $statuses = EMS_Leave_Options::get_leave_statuses(); $durations = EMS_Leave_Options::get_leave_durations(); $current_user_can_manage_others = current_user_can('edit_others_leave_requests'); $today = current_time('Y-m-d'); ?> <p><em><?php $display_title = ($post->post_title === 'Auto Draft' || empty($post->post_title)) ? __('(Will be auto-generated on save)', 'emmansys') : $post->post_title; printf(__( 'Leave Request Title: %s', 'emmansys'), esc_html($display_title)); ?></em></p> <table class="form-table"><tbody><tr><th><label for="ems_leave_employee_cpt_id"><?php _e( 'Employee', 'emmansys' ); ?></label></th><td> <?php if ( $current_user_can_manage_others ) : $employee_posts_args = array('post_type' => 'employee', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'); $employee_posts = get_posts( $employee_posts_args ); ?> <select id="ems_leave_employee_cpt_id" name="ems_leave_employee_cpt_id" required><option value=""><?php _e( '-- Select Employee --', 'emmansys' ); ?></option><?php foreach ( $employee_posts as $employee_post ) : ?><option value="<?php echo esc_attr( $employee_post->ID ); ?>" <?php selected( $selected_employee_cpt_id, $employee_post->ID ); ?> data-user-id="<?php echo esc_attr(get_post_meta($employee_post->ID, '_employee_user_id', true)); ?>"><?php echo esc_html( $employee_post->post_title ); ?><?php $linked_wp_user_id_option = get_post_meta($employee_post->ID, '_employee_user_id', true); if ($linked_wp_user_id_option) { echo ' (WP User ID: ' . esc_html($linked_wp_user_id_option) . ')'; } ?></option><?php endforeach; ?></select><p class="description"><?php _e( 'Select the employee filing this leave.', 'emmansys' ); ?></p> <?php else: $current_wp_user_id = get_current_user_id(); $linked_employee_cpt_id_for_user = null; $employee_name_for_user = __('N/A - Your user is not linked to an Employee Record.', 'emmansys'); $args_user_employee = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_employee_user_id', 'value' => $current_wp_user_id, 'compare' => '='))); $current_user_employee_records = get_posts($args_user_employee); if (!empty($current_user_employee_records)) { $linked_employee_cpt_id_for_user = $current_user_employee_records[0]->ID; $employee_name_for_user = $current_user_employee_records[0]->post_title; if ($post->ID && $selected_employee_cpt_id && $selected_employee_cpt_id != $linked_employee_cpt_id_for_user && $selected_employee_cpt_id != 0) { echo '<p class="notice notice-warning">' . __('Warning: This leave request is for a different employee. You can only manage your own.', 'emmansys') . '</p>'; $original_employee_post = get_post($selected_employee_cpt_id); if ($original_employee_post) { echo '<strong>' . esc_html($original_employee_post->post_title) . '</strong>'; echo '<input type="hidden" name="ems_leave_employee_cpt_id" value="' . esc_attr($selected_employee_cpt_id) . '" />'; } else { echo '<strong>' . __('Unknown Employee', 'emmansys') . '</strong>'; } } else { echo '<strong>' . esc_html($employee_name_for_user) . '</strong>'; echo '<input type="hidden" name="ems_leave_employee_cpt_id" value="' . esc_attr($linked_employee_cpt_id_for_user) . '" />'; } } else { echo '<strong>' . esc_html($employee_name_for_user) . '</strong>'; } ?><p class="description"><?php _e( 'Leave request will be filed for your linked employee record.', 'emmansys' ); ?></p><?php endif; ?> </td></tr><tr><th><label for="ems_leave_type"><?php _e( 'Leave Type', 'emmansys' ); ?></label></th><td><select id="ems_leave_type" name="ems_leave_type" required><option value=""><?php _e( '-- Select Type --', 'emmansys' ); ?></option><?php foreach ( $all_leave_types as $key => $type_data ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_type_key, $key ); ?>><?php echo esc_html( $type_data['label'] ); ?></option><?php endforeach; ?></select></td></tr> <tr><th><label for="ems_leave_start_date"><?php _e( 'Start Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_leave_start_date" name="ems_leave_start_date" value="<?php echo esc_attr( $start_date ); ?>" class="regular-text ems-leave-start-date" min="<?php echo esc_attr($today); ?>" required/></td></tr> <tr><th><label for="ems_leave_end_date"><?php _e( 'End Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_leave_end_date" name="ems_leave_end_date" value="<?php echo esc_attr( $end_date ); ?>" class="regular-text ems-leave-end-date" min="<?php echo esc_attr($today); ?>" required/></td></tr> <tr><th><label for="ems_leave_duration"><?php _e( 'Leave Duration', 'emmansys' ); ?></label></th><td><select id="ems_leave_duration" name="ems_leave_duration" required><?php foreach ( $durations as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_duration, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr> <tr><th><label for="ems_leave_reason_field"><?php _e( 'Reason for Leave', 'emmansys' ); ?></label></th><td><textarea id="ems_leave_reason_field" name="ems_leave_reason_field" rows="5" class="large-text" required><?php echo esc_textarea( $leave_reason ); ?></textarea></td></tr><tr><th><label for="ems_leave_status"><?php _e( 'Leave Status', 'emmansys' ); ?></label></th><td><select id="ems_leave_status" name="ems_leave_status" <?php if (!current_user_can('approve_leave_requests')) echo 'disabled'; ?>><option value=""><?php _e( '-- Select Status --', 'emmansys' ); ?></option><?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr><tr><th><label for="ems_leave_admin_notes"><?php _e( 'Admin Notes', 'emmansys' ); ?></label></th><td><textarea id="ems_leave_admin_notes" name="ems_leave_admin_notes" rows="3" class="large-text" <?php if (!current_user_can('approve_leave_requests')) echo 'disabled'; ?>><?php echo esc_textarea( $admin_notes ); ?></textarea><p class="description"><?php _e( 'Notes for admin/manager regarding this leave request.', 'emmansys' ); ?></p></td></tr></tbody></table> <?php }
    
    /**
     * Calculates the number of days for a leave request.
     * Considers whole days and half days.
     * For whole days, it calculates the number of days in the date range (inclusive).
     * Does not currently exclude weekends or public holidays.
     *
     * @param string $start_date_str YYYY-MM-DD format.
     * @param string $end_date_str   YYYY-MM-DD format.
     * @param string $duration_key   'whole_day', 'half_day_am', 'half_day_pm'.
     * @return float Number of days.
     */
    private function calculate_leave_request_days($start_date_str, $end_date_str, $duration_key) {
        if (empty($start_date_str) || empty($end_date_str) || empty($duration_key)) {
            return 0;
        }

        if ($duration_key === 'half_day_am' || $duration_key === 'half_day_pm') {
            return 0.5;
        } elseif ($duration_key === 'whole_day') {
            try {
                $start_date = new DateTime($start_date_str);
                $end_date   = new DateTime($end_date_str);

                if ($start_date > $end_date) {
                    return 0; 
                }
                $end_date->modify('+1 day'); 
                $interval = $start_date->diff($end_date);
                return (float) $interval->days;
            } catch (Exception $e) {
                error_log("EmManSys: Error calculating leave days - " . $e->getMessage());
                return 0;
            }
        }
        return 0; 
    }

    /**
     * Checks if an employee has an existing active (pending or approved) leave request
     * that overlaps with the given date range.
     *
     * @param int    $employee_cpt_id The CPT ID of the employee.
     * @param string $new_start_date_str The proposed start date (YYYY-MM-DD).
     * @param string $new_end_date_str   The proposed end date (YYYY-MM-DD).
     * @param int    $exclude_post_id Optional. A leave request Post ID to exclude from the check (e.g. when editing).
     * @return int|false The Post ID of the conflicting request if found, otherwise false.
     */
    private function has_overlapping_active_leave($employee_cpt_id, $new_start_date_str, $new_end_date_str, $exclude_post_id = 0) {
        if (empty($employee_cpt_id) || empty($new_start_date_str) || empty($new_end_date_str)) {
            return false;
        }
        $args = array(
            'post_type'      => 'leave_request',
            'post_status'    => 'publish', 
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_leave_employee_cpt_id',
                    'value'   => $employee_cpt_id,
                    'compare' => '=',
                ),
                array(
                    'key'     => '_leave_status',
                    'value'   => array('pending', 'approved'), // Active statuses
                    'compare' => 'IN',
                ),
            ),
        );

        if ($exclude_post_id > 0) {
            $args['post__not_in'] = array(intval($exclude_post_id));
        }

        $existing_requests = get_posts($args);

        if (empty($existing_requests)) {
            return false;
        }

        try {
            $new_start_dt = new DateTime($new_start_date_str);
            $new_end_dt   = new DateTime($new_end_date_str);
        } catch (Exception $e) {
            error_log("EmManSys: Invalid date format for new leave request in overlap check - " . $e->getMessage());
            return false; // Cannot perform check with invalid dates
        }


        foreach ($existing_requests as $request_post) {
            $existing_start_str = get_post_meta($request_post->ID, '_leave_start_date', true);
            $existing_end_str   = get_post_meta($request_post->ID, '_leave_end_date', true);

            if (empty($existing_start_str) || empty($existing_end_str)) {
                continue;
            }
            
            try {
                $existing_start_dt = new DateTime($existing_start_str);
                $existing_end_dt   = new DateTime($existing_end_str);
            } catch (Exception $e) {
                error_log("EmManSys: Invalid date format for existing leave (ID: {$request_post->ID}) in overlap check - " . $e->getMessage());
                continue; 
            }

            // Check for overlap: (StartA <= EndB) and (EndA >= StartB)
            if (($new_start_dt <= $existing_end_dt) && ($new_end_dt >= $existing_start_dt)) {
                return $request_post->ID; 
            }
        }
        return false;
    }


    public function save_leave_request_meta_data( $post_id, $post, $update ) { 
        if ( ! isset( $_POST['ems_leave_request_details_nonce'] ) || ! wp_verify_nonce( $_POST['ems_leave_request_details_nonce'], 'ems_save_leave_request_details' ) ) return; 
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE && !$update ) return;  // Allow autosave for updates but not for initial creation to avoid premature overlap checks
        if ( $post->post_type !== 'leave_request' ) return; 
        if ( !current_user_can( 'edit_leave_request', $post_id ) ) { return; } 

        $old_status = get_post_meta($post_id, '_leave_status', true);

        $start_date_val = isset( $_POST['ems_leave_start_date'] ) ? sanitize_text_field( $_POST['ems_leave_start_date'] ) : ''; 
        $end_date_val   = isset( $_POST['ems_leave_end_date'] ) ? sanitize_text_field( $_POST['ems_leave_end_date'] ) : ''; 
        $employee_cpt_id_from_form = isset( $_POST['ems_leave_employee_cpt_id'] ) ? absint( $_POST['ems_leave_employee_cpt_id'] ) : null;
        
        // Date validations
        $today_val = current_time('Y-m-d'); 
        if ( !empty($start_date_val) && $start_date_val < $today_val && !$update ) { 
             wp_die( __('Error: Start date cannot be in the past for new requests.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>'); 
        } 
        if ( !empty($start_date_val) && !empty($end_date_val) && $end_date_val < $start_date_val ) { 
            wp_die( __('Error: End date cannot be earlier than the start date.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>'); 
        } 

        // Overlap Check - primarily for new requests or if dates/employee change significantly on update
        if ($employee_cpt_id_from_form && $start_date_val && $end_date_val) {
            $exclude_id_for_overlap_check = $update ? $post_id : 0;
            $conflicting_leave_id = $this->has_overlapping_active_leave($employee_cpt_id_from_form, $start_date_val, $end_date_val, $exclude_id_for_overlap_check);
            if ($conflicting_leave_id) {
                // For admin, show a notice. For user submissions, this would be blocked earlier.
                if (is_admin() && current_user_can('edit_others_leave_requests')) {
                     add_settings_error('ems_leave_notice', 'overlap_error', sprintf(__('Warning: This leave request overlaps with an existing active leave request (ID: %s). Please review.', 'emmansys'), $conflicting_leave_id), 'warning');
                     set_transient('settings_errors', get_settings_errors(), 30);
                     // Don't wp_die for admins, let them save but with a warning. The balance logic will handle status.
                } else {
                    // This case should ideally be caught before reaching here for non-admins (e.g. in AJAX handler)
                     wp_die( __('Error: This leave request overlaps with an existing active leave request.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>');
                }
            }
        }
        
        $employee_cpt_id_for_meta = null; 
        if ( isset( $_POST['ems_leave_employee_cpt_id'] ) ) { 
            $employee_cpt_id_for_meta = absint( $_POST['ems_leave_employee_cpt_id'] ); 
            if ( $employee_cpt_id_for_meta > 0 ) { 
                update_post_meta( $post_id, '_leave_employee_cpt_id', $employee_cpt_id_for_meta ); 
                $employee_post = get_post( $employee_cpt_id_for_meta ); 
                if ( $employee_post ) { 
                    update_post_meta( $post_id, '_leave_employee_name', sanitize_text_field( $employee_post->post_title ) ); 
                    $linked_wp_user_id = get_post_meta( $employee_cpt_id_for_meta, '_employee_user_id', true ); 
                    if ( $linked_wp_user_id ) { 
                        update_post_meta( $post_id, '_leave_user_id', absint( $linked_wp_user_id ) ); 
                    } else { 
                        delete_post_meta( $post_id, '_leave_user_id'); 
                    } 
                } 
            } else { 
                delete_post_meta( $post_id, '_leave_employee_cpt_id'); 
                delete_post_meta( $post_id, '_leave_employee_name'); 
                if ( $post->post_author && !current_user_can('edit_others_leave_requests')) { 
                    update_post_meta( $post_id, '_leave_user_id', $post->post_author ); 
                    $author_data = get_userdata($post->post_author); 
                    if ($author_data) { 
                        $args_author_employee = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_employee_user_id', 'value' => $post->post_author, 'compare' => '='))); 
                        $author_employee_records = get_posts($args_author_employee); 
                        if(!empty($author_employee_records)){ 
                            update_post_meta( $post_id, '_leave_employee_name', sanitize_text_field($author_employee_records[0]->post_title)); 
                            update_post_meta( $post_id, '_leave_employee_cpt_id', $author_employee_records[0]->ID); 
                        } else { 
                            update_post_meta( $post_id, '_leave_employee_name', $author_data->display_name); 
                        } 
                    } 
                } else { 
                    delete_post_meta( $post_id, '_leave_user_id'); 
                } 
            } 
        } 
        
        $other_fields_to_save = array( 
            '_leave_type' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_type'), 
            '_leave_start_date' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_leave_start_date'), 
            '_leave_end_date' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_leave_end_date'), 
            '_leave_duration' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_duration'), 
            '_leave_reason' => array('sanitize_callback' => 'sanitize_textarea_field', 'field_name' => 'ems_leave_reason_field'), 
            '_leave_status' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_status'), 
            '_leave_admin_notes' => array('sanitize_callback' => 'sanitize_textarea_field', 'field_name' => 'ems_leave_admin_notes'),
        ); 
        
        if (!current_user_can('approve_leave_requests')) { 
            unset($other_fields_to_save['_leave_status']); 
            unset($other_fields_to_save['_leave_admin_notes']); 
        } 
        
        foreach ( $other_fields_to_save as $meta_key => $field_config ) { 
            if ( isset( $_POST[ $field_config['field_name'] ] ) ) { 
                $value = call_user_func( $field_config['sanitize_callback'], $_POST[ $field_config['field_name'] ] ); 
                update_post_meta( $post_id, $meta_key, $value ); 
            } 
        } 
        if (!current_user_can('approve_leave_requests') && $old_status === 'pending' && isset($_POST['ems_leave_status']) && sanitize_key($_POST['ems_leave_status']) === 'cancelled') {
             update_post_meta($post_id, '_leave_status', 'cancelled');
        }

        $current_employee_cpt_id = get_post_meta($post_id, '_leave_employee_cpt_id', true); 
        $current_start_date = get_post_meta($post_id, '_leave_start_date', true); 
        $current_end_date = get_post_meta($post_id, '_leave_end_date', true); 
        if ( $current_employee_cpt_id && $current_start_date && $current_end_date ) { 
            $employee_for_title = get_post($current_employee_cpt_id); 
            if ($employee_for_title) { 
                $new_title = sprintf(__( 'Leave: %s (%s to %s)', 'emmansys' ), $employee_for_title->post_title, $current_start_date, $current_end_date); 
                if ($post->post_title !== $new_title && !($post->post_title === 'Auto Draft' && empty($new_title)) ) { 
                    remove_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 20); 
                    wp_update_post(array('ID' => $post_id, 'post_title' => $new_title, 'post_name' => sanitize_title($new_title) )); 
                    add_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 20, 3); 
                } 
            } 
        } elseif (empty($post->post_title) || $post->post_title === 'Auto Draft') { 
            $fallback_title = __('Leave Request', 'emmansys') . ' - ' . $post_id; 
            remove_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 20); 
            wp_update_post(array('ID' => $post_id, 'post_title' => $fallback_title, 'post_name' => sanitize_title($fallback_title) )); 
            add_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 20, 3); 
        }

        $final_new_status = get_post_meta($post_id, '_leave_status', true); 
        $leave_type_key   = get_post_meta($post_id, '_leave_type', true);
        $leave_start_date = get_post_meta($post_id, '_leave_start_date', true);
        $leave_end_date   = get_post_meta($post_id, '_leave_end_date', true);
        $leave_duration_key = get_post_meta($post_id, '_leave_duration', true);
        $employee_id      = get_post_meta($post_id, '_leave_employee_cpt_id', true); // This is the Employee CPT ID
        $previously_deducted_days = (float) get_post_meta($post_id, '_ems_deducted_leave_days', true);

        if ($employee_id && $leave_type_key && $leave_start_date && $leave_end_date && $leave_duration_key) {
            $days_for_this_request = $this->calculate_leave_request_days($leave_start_date, $leave_end_date, $leave_duration_key);
            $balance_meta_key = '_leave_balance_' . $leave_type_key;
            $all_leave_type_definitions = EMS_Leave_Options::get_leave_types();
            $current_leave_type_definition = $all_leave_type_definitions[$leave_type_key] ?? null;
            
            $tracks_balance = $current_leave_type_definition && ($current_leave_type_definition['initial_balance'] > 0 || $leave_type_key !== 'unpaid');

            if ($tracks_balance) {
                $current_employee_balance_raw = get_post_meta($employee_id, $balance_meta_key, true);
                $current_employee_balance = ($current_employee_balance_raw !== '') ? (float) $current_employee_balance_raw : (isset($current_leave_type_definition['initial_balance']) ? (float) $current_leave_type_definition['initial_balance'] : 0);

                if ($final_new_status === 'approved' && $old_status !== 'approved') {
                    if ($days_for_this_request > 0) {
                        $new_balance = $current_employee_balance - $days_for_this_request;
                        update_post_meta($employee_id, $balance_meta_key, $new_balance);
                        update_post_meta($post_id, '_ems_deducted_leave_days', $days_for_this_request); 
                    }
                } elseif ($old_status === 'approved' && $final_new_status !== 'approved') {
                    if ($previously_deducted_days > 0) {
                        $new_balance = $current_employee_balance + $previously_deducted_days;
                        update_post_meta($employee_id, $balance_meta_key, $new_balance);
                        delete_post_meta($post_id, '_ems_deducted_leave_days'); 
                    }
                } elseif ($final_new_status === 'approved' && $old_status === 'approved') {
                    $new_deduction_amount = $days_for_this_request;
                    if ($new_deduction_amount != $previously_deducted_days) {
                        $adjustment = $previously_deducted_days - $new_deduction_amount;
                        $new_balance = $current_employee_balance + $adjustment; 
                        update_post_meta($employee_id, $balance_meta_key, $new_balance);
                        update_post_meta($post_id, '_ems_deducted_leave_days', $new_deduction_amount);
                    }
                }
            }
        }
    }

    public function set_leave_request_columns( $columns ) { /* ... same as v1.1.3 ... */ unset($columns['title']); $new_columns = array(); $new_columns['cb'] = $columns['cb']; $new_columns['ems_leave_title_col'] = __( 'Leave Request', 'emmansys' ); $new_columns['ems_leave_employee'] = __( 'Employee', 'emmansys' ); $new_columns['ems_leave_user'] = __( 'WP User', 'emmansys' ); $new_columns['ems_leave_type'] = __( 'Leave Type', 'emmansys' ); $new_columns['ems_leave_dates'] = __( 'Dates', 'emmansys' ); $new_columns['ems_leave_duration_col'] = __( 'Duration', 'emmansys' ); $new_columns['ems_leave_status'] = __( 'Status', 'emmansys' ); $new_columns['date'] = $columns['date']; return $new_columns; }
    public function render_leave_request_columns( $column, $post_id ) { /* ... same as v1.1.3 ... */ $all_leave_types_map = EMS_Leave_Options::get_leave_types(); $statuses_map = EMS_Leave_Options::get_leave_statuses(); $durations_map = EMS_Leave_Options::get_leave_durations(); switch ( $column ) { case 'ems_leave_title_col': echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>'; break; case 'ems_leave_employee': $employee_cpt_id = get_post_meta( $post_id, '_leave_employee_cpt_id', true ); $employee_name = get_post_meta( $post_id, '_leave_employee_name', true ); if ($employee_cpt_id && $employee_post = get_post($employee_cpt_id)) { echo '<a href="' . esc_url(get_edit_post_link($employee_cpt_id)) . '"><strong>' . esc_html($employee_post->post_title) . '</strong></a>'; } elseif ($employee_name) { echo '<strong>' . esc_html($employee_name) . '</strong>'; } else { echo '—'; } break; case 'ems_leave_user': $user_id = get_post_meta( $post_id, '_leave_user_id', true ); if ($user_id && $user = get_userdata($user_id)) { echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . '</a>'; } else { echo '—'; } break; case 'ems_leave_type': $type_key = get_post_meta( $post_id, '_leave_type', true ); echo esc_html( $all_leave_types_map[$type_key]['label'] ?? $type_key ); break; case 'ems_leave_dates': $start = get_post_meta( $post_id, '_leave_start_date', true ); $end = get_post_meta( $post_id, '_leave_end_date', true ); echo esc_html($start) . ' - ' . esc_html($end); break; case 'ems_leave_duration_col': $duration_key = get_post_meta( $post_id, '_leave_duration', true ); echo esc_html( $durations_map[$duration_key] ?? $duration_key ); break; case 'ems_leave_status': $status_key = get_post_meta( $post_id, '_leave_status', true ); echo '<strong>' . esc_html( $statuses_map[$status_key] ?? $status_key ) . '</strong>'; break; } }
    public function make_leave_request_columns_sortable( $columns ) { /* ... same as v1.1.3 ... */ $columns['ems_leave_title_col'] = 'title'; $columns['ems_leave_employee'] = 'ems_leave_employee_sort'; $columns['ems_leave_user'] = 'ems_leave_user_sort'; $columns['ems_leave_type'] = 'ems_leave_type_sort'; $columns['ems_leave_status'] = 'ems_leave_status_sort'; return $columns; }
    public function sort_leave_request_columns_query( $query ) { /* ... same as v1.1.3 ... */ if ( ! is_admin() || ! $query->is_main_query() ) return; $orderby = $query->get( 'orderby' ); $post_type = $query->get('post_type'); if ($post_type === 'leave_request') { if ( 'ems_leave_employee_sort' === $orderby ) { $query->set( 'meta_key', '_leave_employee_name' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_leave_user_sort' === $orderby ) { $query->set( 'meta_key', '_leave_user_id' ); $query->set( 'orderby', 'meta_value_num' ); } elseif ( 'ems_leave_type_sort' === $orderby ) { $query->set( 'meta_key', '_leave_type' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_leave_status_sort' === $orderby ) { $query->set( 'meta_key', '_leave_status' ); $query->set( 'orderby', 'meta_value' ); } } }
    
    /**
     * Display leave management section on user profile or dedicated dashboard page.
     * @param WP_User $user_object_from_hook The user object from the hook or current user for dashboard.
     * @param bool    $is_dashboard_context Whether this is being rendered in the dashboard context.
     */
    public function show_leave_management_on_profile( $user_object_from_hook, $is_dashboard_context = false ) { /* ... same as v1.1.5 ... */ $user_to_display = null; if ( $is_dashboard_context ) { $user_to_display = wp_get_current_user(); if ( ! ($user_to_display instanceof WP_User) || ! $user_to_display->ID ) { echo '<p>' . esc_html__('Error: Could not retrieve current user information for the dashboard.', 'emmansys') . '</p>'; return; } if ( !current_user_can('submit_profile_leave_request') ) { echo '<div class="notice notice-error"><p>' . esc_html__('You do not have permission to view this dashboard content.', 'emmansys') . '</p></div>'; return; } } else { $user_to_display = $user_object_from_hook; if ( ! ($user_to_display instanceof WP_User) || ! $user_to_display->ID ) { return; } } $current_user_acting_id = get_current_user_id(); $target_profile_user_id = $user_to_display->ID; $linked_employee_cpt_id = null; $employee_query_args = array( 'post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array( array('key' => '_employee_user_id', 'value' => $target_profile_user_id, 'compare' => '=')), 'fields' => 'ids'); $linked_employee_posts = get_posts($employee_query_args); if (!empty($linked_employee_posts) && isset($linked_employee_posts[0])) { $linked_employee_cpt_id = $linked_employee_posts[0]; } $today = current_time('Y-m-d'); $all_leave_types = EMS_Leave_Options::get_leave_types(); $statuses = EMS_Leave_Options::get_leave_statuses(); $durations = EMS_Leave_Options::get_leave_durations(); if (!$is_dashboard_context) { echo '<hr>'; echo '<h2>' . esc_html__( 'Leave Management', 'emmansys' ) . '</h2>'; } if ($linked_employee_cpt_id && ($current_user_acting_id === $target_profile_user_id || current_user_can('edit_users'))) { echo '<h3>' . esc_html__('Your Leave Balances', 'emmansys') . '</h3>'; echo '<table class="form-table"><tbody>'; if (!empty($all_leave_types)) { foreach ($all_leave_types as $type_key => $type_data) { if (is_array($type_data) && isset($type_data['label'])) { $employee_specific_balance_raw = get_post_meta($linked_employee_cpt_id, '_leave_balance_' . $type_key, true); $balance_notice = ''; $display_balance_val = 0; if ($employee_specific_balance_raw !== '') { $display_balance_val = (float) $employee_specific_balance_raw; } else { $display_balance_val = isset($type_data['initial_balance']) ? (float) $type_data['initial_balance'] : 0; $balance_notice = ' <small><em>(' . __('default', 'emmansys') . ')</em></small>'; } echo '<tr>'; echo '<th>' . esc_html($type_data['label']) . '</th>'; echo '<td>' . number_format_i18n($display_balance_val, ($display_balance_val == (int)$display_balance_val) ? 0 : 1) . ' ' . __('days/units', 'emmansys') . $balance_notice . '</td>'; echo '</tr>'; } } } else { echo '<tr><td colspan="2">' . esc_html__('No leave types defined.', 'emmansys') . '</td></tr>'; } echo '</tbody></table><hr style="margin: 20px 0;">'; } if ( $current_user_acting_id === $target_profile_user_id && $linked_employee_cpt_id && current_user_can('submit_profile_leave_request') ) : $form_id = $is_dashboard_context ? 'ems-dashboard-leave-form' : 'ems-profile-leave-form'; ?> <h3><?php esc_html_e( 'File a New Leave Request', 'emmansys' ); ?></h3> <div id="<?php echo esc_attr($form_id); ?>-messages"></div> <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="<?php echo esc_attr($form_id); ?>"> <input type="hidden" name="action" value="ems_submit_leave_request"> <input type="hidden" name="ems_user_id" value="<?php echo esc_attr( $target_profile_user_id ); ?>"> <input type="hidden" name="ems_employee_cpt_id_profile" value="<?php echo esc_attr( $linked_employee_cpt_id ); ?>"> <?php wp_nonce_field( 'ems_submit_leave_request_nonce', 'ems_leave_request_profile_nonce' ); ?> <table class="form-table"><tbody> <tr><th><label for="<?php echo esc_attr($form_id); ?>-leave-type"><?php _e( 'Leave Type', 'emmansys' ); ?></label></th><td><select id="<?php echo esc_attr($form_id); ?>-leave-type" name="ems_profile_leave_type" required><option value=""><?php _e( '-- Select Type --', 'emmansys' ); ?></option><?php foreach ( $all_leave_types as $key => $type_data ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $type_data['label'] ); ?></option><?php endforeach; ?></select></td></tr> <tr><th><label for="<?php echo esc_attr($form_id); ?>-start-date"><?php _e( 'Start Date', 'emmansys' ); ?></label></th><td><input type="date" id="<?php echo esc_attr($form_id); ?>-start-date" name="ems_profile_start_date" class="regular-text ems-leave-start-date" min="<?php echo esc_attr($today); ?>" required /></td></tr> <tr><th><label for="<?php echo esc_attr($form_id); ?>-end-date"><?php _e( 'End Date', 'emmansys' ); ?></label></th><td><input type="date" id="<?php echo esc_attr($form_id); ?>-end-date" name="ems_profile_end_date" class="regular-text ems-leave-end-date" min="<?php echo esc_attr($today); ?>" required /></td></tr> <tr><th><label for="<?php echo esc_attr($form_id); ?>-leave-duration"><?php _e( 'Leave Duration', 'emmansys' ); ?></label></th><td><select id="<?php echo esc_attr($form_id); ?>-leave-duration" name="ems_profile_leave_duration" required><?php foreach ( $durations as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr> <tr><th><label for="<?php echo esc_attr($form_id); ?>-leave-reason"><?php _e( 'Reason for Leave', 'emmansys' ); ?></label></th><td><textarea id="<?php echo esc_attr($form_id); ?>-leave-reason" name="ems_profile_leave_reason_field" rows="5" class="large-text" required></textarea></td></tr> </tbody></table> <?php submit_button( __( 'Submit Leave Request', 'emmansys' ) ); ?> </form><hr style="margin: 20px 0;"> <?php elseif ($current_user_acting_id === $target_profile_user_id && !$linked_employee_cpt_id && current_user_can('submit_profile_leave_request')): ?> <p><?php _e( 'To file leave requests, your WordPress user account must first be linked to an Employee record by an administrator.', 'emmansys' ); ?></p><hr style="margin: 20px 0;"> <?php endif; ?> <?php $can_view_history = false; if ($is_dashboard_context && current_user_can('view_own_profile_leave_history')) { $can_view_history = true; } elseif (!$is_dashboard_context && $current_user_acting_id === $target_profile_user_id && current_user_can('view_own_profile_leave_history')) { $can_view_history = true; } elseif (!$is_dashboard_context && current_user_can('edit_users') && $current_user_acting_id !== $target_profile_user_id) { $can_view_history = true; } if ( $can_view_history ) : ?> <h3><?php esc_html_e( 'Leave Request History', 'emmansys' ); ?></h3> <?php $history_args = array( 'post_type' => 'leave_request', 'posts_per_page' => -1, 'meta_query' => array(), 'orderby' => 'date', 'order' => 'DESC', ); if ($linked_employee_cpt_id) { $history_args['meta_query']['relation'] = 'OR'; $history_args['meta_query'][] = array( 'key' => '_leave_employee_cpt_id', 'value' => $linked_employee_cpt_id, 'compare' => '=', 'type' => 'NUMERIC'); } $history_args['meta_query'][] = array( 'key' => '_leave_user_id', 'value' => $target_profile_user_id, 'compare' => '=', 'type' => 'NUMERIC'); if (count($history_args['meta_query']) === 1 && isset($history_args['meta_query']['relation'])) { unset($history_args['meta_query']['relation']); } elseif (empty($history_args['meta_query'])) { $history_args['author'] = $target_profile_user_id; } $user_leave_requests = new WP_Query( $history_args ); if ( $user_leave_requests->have_posts() ) : ?> <table class="wp-list-table widefat fixed striped"><thead><tr> <th><?php _e( 'Request Date', 'emmansys' ); ?></th><th><?php _e( 'Leave Type', 'emmansys' ); ?></th> <th><?php _e( 'Dates', 'emmansys' ); ?></th><th><?php _e( 'Duration', 'emmansys' ); ?></th> <th><?php _e( 'Status', 'emmansys' ); ?></th><th><?php _e( 'Reason', 'emmansys' ); ?></th> </tr></thead><tbody> <?php $leave_types_map_history = EMS_Leave_Options::get_leave_types(); $statuses_map_history = EMS_Leave_Options::get_leave_statuses(); $durations_map_history = EMS_Leave_Options::get_leave_durations(); while ( $user_leave_requests->have_posts() ) : $user_leave_requests->the_post(); $request_id = get_the_ID(); $type_key_history = get_post_meta( $request_id, '_leave_type', true ); ?> <tr> <td><?php echo get_the_date( '', $request_id ); ?></td> <td><?php echo esc_html( $leave_types_map_history[$type_key_history]['label'] ?? $type_key_history ?: __('N/A', 'emmansys') ); ?></td> <td><?php echo esc_html( get_post_meta( $request_id, '_leave_start_date', true ) ); ?> - <?php echo esc_html( get_post_meta( $request_id, '_leave_end_date', true ) ); ?></td> <td><?php echo esc_html( $durations_map_history[get_post_meta( $request_id, '_leave_duration', true )] ?? get_post_meta( $request_id, '_leave_duration', true ) ?: __('N/A', 'emmansys') ); ?></td> <td><strong><?php echo esc_html( $statuses_map_history[get_post_meta( $request_id, '_leave_status', true )] ?? get_post_meta( $request_id, '_leave_status', true ) ?: __('N/A', 'emmansys') ); ?></strong></td> <td><?php echo wp_kses_post( get_post_meta( $request_id, '_leave_reason', true ) ); ?></td> </tr> <?php endwhile; ?> </tbody></table> <?php else : ?> <p><?php _e( 'No leave requests found for this user.', 'emmansys' ); ?></p> <?php endif; wp_reset_postdata(); ?> <?php endif; ?> <?php if (!$is_dashboard_context) echo '<hr style="margin-top:20px;">'; ?> <?php }

    public function ajax_handle_profile_leave_submission() { 
        check_ajax_referer('ems_ajax_profile_leave_nonce', 'security'); 
        if ( !current_user_can('submit_profile_leave_request') ) { 
            wp_send_json_error(array('message' => __( 'You do not have permission to submit leave requests.', 'emmansys' ) ) ); 
        } 
        if ( ! isset( $_POST['ems_user_id'], $_POST['ems_employee_cpt_id_profile'] ) || get_current_user_id() != $_POST['ems_user_id'] ) { 
            wp_send_json_error(array('message' => __( 'Security check failed or user mismatch.', 'emmansys' ) ) ); 
        } 
        $user_id = absint( $_POST['ems_user_id'] ); 
        $employee_cpt_id = absint( $_POST['ems_employee_cpt_id_profile'] ); 
        $user_info = get_userdata( $user_id ); 
        $employee_info = get_post( $employee_cpt_id ); 
        if ( ! $user_info || ! $employee_info || $employee_info->post_type !== 'employee' ) { 
            wp_send_json_error(array('message' => __( 'Invalid user or employee record for leave request.', 'emmansys' ) ) ); 
        } 
        $linked_user_on_employee_cpt = get_post_meta($employee_cpt_id, '_employee_user_id', true); 
        if (absint($linked_user_on_employee_cpt) !== $user_id) { 
            wp_send_json_error(array('message' => __( 'Employee record mismatch.', 'emmansys' ) ) ); 
        } 
        $leave_type_key = isset( $_POST['ems_profile_leave_type'] ) ? sanitize_key( $_POST['ems_profile_leave_type'] ) : ''; 
        $start_date_val = isset( $_POST['ems_profile_start_date'] ) ? sanitize_text_field( $_POST['ems_profile_start_date'] ) : ''; 
        $end_date_val = isset( $_POST['ems_profile_end_date'] ) ? sanitize_text_field( $_POST['ems_profile_end_date'] ) : ''; 
        $leave_duration_val = isset( $_POST['ems_profile_leave_duration'] ) ? sanitize_key( $_POST['ems_profile_leave_duration'] ) : 'whole_day'; 
        $leave_reason = isset( $_POST['ems_profile_leave_reason_field'] ) ? sanitize_textarea_field( $_POST['ems_profile_leave_reason_field'] ) : ''; 
        $today_val = current_time('Y-m-d'); 
        
        if ( empty( $leave_type_key ) || empty( $start_date_val ) || empty( $end_date_val ) || empty( $leave_reason ) || empty($leave_duration_val) ) { 
            wp_send_json_error(array('message' => __( 'All fields are required for leave submission.', 'emmansys' ) ) ); 
        } 
        if ( $start_date_val < $today_val ) { 
            wp_send_json_error(array('message' => __( 'Error: Start date cannot be in the past.', 'emmansys' ) ) ); 
        } 
        if ( $end_date_val < $start_date_val ) { 
            wp_send_json_error(array('message' => __( 'Error: End date cannot be earlier than the start date.', 'emmansys' ) ) ); 
        } 

        // Check for overlapping active leave requests
        $conflicting_leave_id = $this->has_overlapping_active_leave($employee_cpt_id, $start_date_val, $end_date_val);
        if ($conflicting_leave_id) {
            wp_send_json_error(array('message' => sprintf(__( 'Error: Your leave request from %s to %s overlaps with an existing active leave request. Please cancel the existing one or choose different dates.', 'emmansys' ), $start_date_val, $end_date_val) ) );
        }

        // TODO: Add balance check here before inserting the post.
        
        $post_title = sprintf( __( 'Leave: %s (%s to %s)', 'emmansys' ), $employee_info->post_title, $start_date_val, $end_date_val ); 
        $leave_request_data = array( 'post_title' => $post_title, 'post_status' => 'publish', 'post_type' => 'leave_request', 'post_author' => $user_id, ); 
        $new_leave_request_id = wp_insert_post( $leave_request_data, true ); 
        if ( is_wp_error( $new_leave_request_id ) ) { 
            wp_send_json_error(array('message' => __( 'Failed to submit leave request: ', 'emmansys' ) . $new_leave_request_id->get_error_message() ) ); 
        } else { 
            update_post_meta( $new_leave_request_id, '_leave_employee_cpt_id', $employee_cpt_id ); 
            update_post_meta( $new_leave_request_id, '_leave_user_id', $user_id ); 
            update_post_meta( $new_leave_request_id, '_leave_employee_name', sanitize_text_field($employee_info->post_title) ); 
            update_post_meta( $new_leave_request_id, '_leave_type', $leave_type_key ); 
            update_post_meta( $new_leave_request_id, '_leave_start_date', $start_date_val ); 
            update_post_meta( $new_leave_request_id, '_leave_end_date', $end_date_val ); 
            update_post_meta( $new_leave_request_id, '_leave_duration', $leave_duration_val ); 
            update_post_meta( $new_leave_request_id, '_leave_reason', $leave_reason ); 
            update_post_meta( $new_leave_request_id, '_leave_status', 'pending' ); // New requests are pending
            // Balance deduction will happen in save_post_leave_request when status becomes 'approved'
            wp_send_json_success(array('message' => __( 'Leave request submitted successfully. It is now pending approval.', 'emmansys' ) ) ); 
        } 
        wp_die(); 
    }
    public function handle_profile_leave_request_submission() { 
        if ( ! isset( $_POST['ems_leave_request_profile_nonce'] ) || ! wp_verify_nonce( $_POST['ems_leave_request_profile_nonce'], 'ems_submit_leave_request_nonce' ) ) { 
            set_transient( 'ems_leave_notice_error', __( 'Security check failed.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: home_url() ); 
            exit; 
        } 
        if ( !current_user_can('submit_profile_leave_request') ) { 
            set_transient( 'ems_leave_notice_error', __( 'You do not have permission to submit leave requests.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: home_url() ); 
            exit; 
        } 
        $user_id = isset($_POST['ems_user_id']) ? absint( $_POST['ems_user_id'] ) : 0; 
        $employee_cpt_id = isset($_POST['ems_employee_cpt_id_profile']) ? absint( $_POST['ems_employee_cpt_id_profile'] ) : 0; 
        if (get_current_user_id() != $user_id) { 
            set_transient( 'ems_leave_notice_error', __( 'User mismatch.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: home_url() ); 
            exit; 
        } 
        $user_info = get_userdata( $user_id ); 
        $employee_info = get_post( $employee_cpt_id ); 
        if ( ! $user_info || ! $employee_info || $employee_info->post_type !== 'employee' ) { 
            set_transient( 'ems_leave_notice_error', __( 'Invalid user or employee record for leave request.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); 
            exit; 
        } 
        $linked_user_on_employee_cpt = get_post_meta($employee_cpt_id, '_employee_user_id', true); 
        if (absint($linked_user_on_employee_cpt) !== $user_id) { 
            set_transient( 'ems_leave_notice_error', __( 'Employee record mismatch.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); 
            exit; 
        } 
        $leave_type_key = isset( $_POST['ems_profile_leave_type'] ) ? sanitize_key( $_POST['ems_profile_leave_type'] ) : ''; 
        $start_date_val = isset( $_POST['ems_profile_start_date'] ) ? sanitize_text_field( $_POST['ems_profile_start_date'] ) : ''; 
        $end_date_val = isset( $_POST['ems_profile_end_date'] ) ? sanitize_text_field( $_POST['ems_profile_end_date'] ) : ''; 
        $leave_duration_val = isset( $_POST['ems_profile_leave_duration'] ) ? sanitize_key( $_POST['ems_profile_leave_duration'] ) : 'whole_day'; 
        $leave_reason = isset( $_POST['ems_profile_leave_reason_field'] ) ? sanitize_textarea_field( $_POST['ems_profile_leave_reason_field'] ) : ''; 
        $today_val = current_time('Y-m-d'); 
        
        if ( empty( $leave_type_key ) || empty( $start_date_val ) || empty( $end_date_val ) || empty( $leave_reason ) || empty($leave_duration_val)) { 
            set_transient( 'ems_leave_notice_error', __( 'All fields are required for leave submission.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); 
            exit; 
        } 
        if ( $start_date_val < $today_val ) { 
            set_transient( 'ems_leave_notice_error', __( 'Error: Start date cannot be in the past.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); 
            exit; 
        } 
        if ( $end_date_val < $start_date_val ) { 
            set_transient( 'ems_leave_notice_error', __( 'Error: End date cannot be earlier than the start date.', 'emmansys' ), 60 ); 
            wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); 
            exit; 
        } 

        // Check for overlapping active leave requests
        $conflicting_leave_id = $this->has_overlapping_active_leave($employee_cpt_id, $start_date_val, $end_date_val);
        if ($conflicting_leave_id) {
            set_transient( 'ems_leave_notice_error', sprintf(__( 'Error: Your leave request from %s to %s overlaps with an existing active leave request. Please cancel the existing one or choose different dates.', 'emmansys' ), $start_date_val, $end_date_val), 60 );
            wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) );
            exit;
        }
        
        // TODO: Add balance check here as well for non-AJAX fallback.

        $post_title = sprintf( __( 'Leave: %s (%s to %s)', 'emmansys' ), $employee_info->post_title, $start_date_val, $end_date_val ); 
        $leave_request_data = array( 'post_title' => $post_title, 'post_status' => 'publish', 'post_type' => 'leave_request', 'post_author' => $user_id, ); 
        $new_leave_request_id = wp_insert_post( $leave_request_data, true ); 
        if ( is_wp_error( $new_leave_request_id ) ) { 
            set_transient( 'ems_leave_notice_error', __( 'Failed to submit leave request: ', 'emmansys' ) . $new_leave_request_id->get_error_message(), 60 ); 
        } else { 
            update_post_meta( $new_leave_request_id, '_leave_employee_cpt_id', $employee_cpt_id ); 
            update_post_meta( $new_leave_request_id, '_leave_user_id', $user_id ); 
            update_post_meta( $new_leave_request_id, '_leave_employee_name', sanitize_text_field($employee_info->post_title) ); 
            update_post_meta( $new_leave_request_id, '_leave_type', $leave_type_key ); 
            update_post_meta( $new_leave_request_id, '_leave_start_date', $start_date_val ); 
            update_post_meta( $new_leave_request_id, '_leave_end_date', $end_date_val ); 
            update_post_meta( $new_leave_request_id, '_leave_duration', $leave_duration_val ); 
            update_post_meta( $new_leave_request_id, '_leave_reason', $leave_reason ); 
            update_post_meta( $new_leave_request_id, '_leave_status', 'pending' ); // New requests are pending
            set_transient( 'ems_leave_notice_success', __( 'Leave request submitted successfully. It is now pending approval.', 'emmansys' ), 60 ); 
        } 
        wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); 
        exit; 
    }

} // End class Employee_Management_System

/**
 * Begins execution of the plugin.
 */
function run_employee_management_system() {
    return Employee_Management_System::instance();
}
run_employee_management_system();

/**
 * =====================================================================================
 * UPDATE HISTORY:
 * =====================================================================================
 * Version 1.1.7 (Current - Prevent Overlapping Active Leave Requests)
 * - Added `has_overlapping_active_leave()` helper method to check for existing 'pending' or 'approved' leaves for an employee within a given date range.
 * - Integrated this check into:
 * - `ajax_handle_profile_leave_submission()`: Prevents submission and returns an error if an overlap is found.
 * - `handle_profile_leave_request_submission()`: Prevents submission and sets an error notice if an overlap is found.
 * - `save_leave_request_meta_data()`: 
 * - For new leave requests created by admins, if an overlap is found, an admin warning notice is displayed. (Does not strictly block admin saving but warns).
 * - When updating, the check excludes the current leave request being edited.
 * - Incremented plugin version to 1.1.7.
 *
 * Version 1.1.6
 * - Added `calculate_leave_request_days()` helper method to determine leave duration in numeric days.
 * - Modified `save_leave_request_meta_data()` to deduct/credit leave balances upon approval/status change.
 * - Stores `_ems_deducted_leave_days` on leave request for accurate crediting.
 *
 * Version 1.1.5
 * - Updated `show_leave_management_on_profile` to improve display of leave balances (employee-specific vs. default).
 *
 * Version 1.1.4
 * - Fixed PHP TypeError in `render_leave_types_admin_page`.
 * - Added "Leave Types" admin page for CRUD operations on custom leave types.
 *
 * (Older versions summarized for brevity)
 * =====================================================================================
 */
