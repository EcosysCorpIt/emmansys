<?php
/**
 * Plugin Name:       EmManSys
 * Description:       A simple plugin to create, edit, delete, and list employees and manage leave requests. Requires User Role Editor plugin.
 * Version:           1.2.1
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
    const VERSION = '1.2.1'; // Updated version

    /**
     * The single instance of the class.
     *
     * @var Employee_Management_System
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
    public $dashboard_page_hook_suffix = '';

    /**
     * Hook suffix for the leave types page.
     * @var string
     */
    public $leave_types_page_hook_suffix = '';

    /**
     * Hook suffix for the add new leave request page.
     * @var string
     */
    public $add_new_leave_page_hook_suffix = '';

    // Handler instances
    public $assets;
    public $admin_menus;
    public $user_profile;
    public $employee_cpt;
    public $leave_request_cpt;
    public $form_handlers;
    public $ajax_handlers;
    public $leave_options;


    /**
     * Ensures only one instance of the class is loaded or can be loaded.
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
        add_action( 'plugins_loaded', array( $this, 'init_plugin_if_dependency_met' ) );
    }

    /**
     * Define Plugin Constants.
     */
    private function define_constants() {
        define( 'EMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'EMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'EMS_PLUGIN_FILE', __FILE__ );
        define( 'EMS_VERSION', self::VERSION );
    }

    /**
     * Check for required plugin dependencies.
     * This is hooked to admin_init by init_plugin_if_dependency_met if needed.
     */
    public function check_dependencies_and_notify() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( !is_plugin_active( 'user-role-editor/user-role-editor.php' ) ) {
            $this->dependency_met = false;
            add_action( 'admin_notices', array( $this, 'dependency_missing_notice' ) );
        } else {
            $this->dependency_met = true;
        }
    }

    /**
     * Display admin notice if dependency is missing.
     */
    public function dependency_missing_notice() {
         if ( !$this->dependency_met ) { // Re-check just in case
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
     * Include required files.
     */
    private function includes() {
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-leave-options.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-assets.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-user-profile.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-admin-menus.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-cpt-employee.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-cpt-leave-request.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-form-handlers.php';
        require_once EMS_PLUGIN_DIR . 'includes/class-ems-ajax-handlers.php';
    }

    /**
     * Setup handler classes.
     */
    private function setup_handlers() {
        $this->leave_options     = new EMS_Leave_Options(); // Though mostly used statically
        $this->assets            = new EMS_Assets($this);
        $this->user_profile      = new EMS_User_Profile();
        $this->admin_menus       = new EMS_Admin_Menus($this, $this->user_profile);
        $this->employee_cpt      = new EMS_Employee_CPT();
        $this->leave_request_cpt = new EMS_Leave_Request_CPT();
        $this->form_handlers     = new EMS_Form_Handlers($this->leave_request_cpt);
        $this->ajax_handlers     = new EMS_AJAX_Handlers($this->leave_request_cpt);
    }


    /**
     * Initialize the plugin if dependencies are met.
     */
    public function init_plugin_if_dependency_met() {
        add_action( 'admin_init', array( $this, 'check_dependencies_and_notify' ) );

        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( is_plugin_active( 'user-role-editor/user-role-editor.php' ) ) {
            $this->dependency_met = true; 
            $this->includes();
            $this->setup_handlers();
            $this->init_hooks();
        } else {
            $this->dependency_met = false; 
        }
    }


    /**
     * Hook into actions and filters. Only called if dependencies are met.
     */
    private function init_hooks() {
        register_activation_hook( EMS_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( EMS_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Admin Menu & Pages
        add_action( 'admin_menu', array( $this->admin_menus, 'add_admin_menus' ) );
        add_action( 'admin_head', array( $this->admin_menus, 'hide_leave_request_add_new_button_css' ) ); 
        add_action( 'admin_menu', array( $this->admin_menus, 'remove_default_add_new_submenu' ), 999 ); // Remove default "Add New" submenu

        // CPT Registration and Management
        add_action( 'init', array( $this->employee_cpt, 'register_employee_cpt' ) );
        add_action( 'add_meta_boxes', array( $this->employee_cpt, 'add_employee_meta_boxes' ) );
        add_action( 'save_post_employee', array( $this->employee_cpt, 'save_employee_meta_data' ), 10, 2 );
        add_filter( 'manage_employee_posts_columns', array( $this->employee_cpt, 'set_employee_columns' ) );
        add_action( 'manage_employee_posts_custom_column', array( $this->employee_cpt, 'render_employee_columns' ), 10, 2 );
        add_filter( 'manage_edit-employee_sortable_columns', array( $this->employee_cpt, 'make_employee_columns_sortable' ) );
        add_action( 'pre_get_posts', array( $this->employee_cpt, 'sort_employee_columns_query' ) );
        add_shortcode( 'list_employees', array( $this->employee_cpt, 'render_employee_list_shortcode' ) );

        add_action( 'init', array( $this->leave_request_cpt, 'register_leave_request_cpt' ) );
        add_action( 'add_meta_boxes', array( $this->leave_request_cpt, 'add_leave_request_meta_boxes' ) );
        add_action( 'save_post_leave_request', array( $this->leave_request_cpt, 'save_leave_request_meta_data' ), 20, 3 );
        add_filter( 'manage_leave_request_posts_columns', array( $this->leave_request_cpt, 'set_leave_request_columns' ) );
        add_action( 'manage_leave_request_posts_custom_column', array( $this->leave_request_cpt, 'render_leave_request_columns' ), 10, 2 );
        add_filter( 'manage_edit-leave_request_sortable_columns', array( $this->leave_request_cpt, 'make_leave_request_columns_sortable' ) );
        add_action( 'pre_get_posts', array( $this->leave_request_cpt, 'sort_leave_request_columns_query' ) );

        // User Profile Integration
        add_action( 'show_user_profile', array( $this->user_profile, 'show_leave_management_on_profile' ) );
        add_action( 'edit_user_profile', array( $this->user_profile, 'show_leave_management_on_profile' ) );
        
        // Form Handlers (admin-post actions)
        add_action( 'admin_post_ems_submit_leave_request', array( $this->form_handlers, 'handle_profile_leave_request_submission' ) );
        add_action( 'admin_post_ems_admin_add_new_leave', array( $this->form_handlers, 'handle_admin_add_new_leave_request' ) );
        add_action( 'admin_post_ems_manage_leave_types', array( $this->form_handlers, 'handle_leave_types_form_submission' ) );
        
        // AJAX Handlers
        add_action( 'wp_ajax_ems_submit_profile_leave', array( $this->ajax_handlers, 'ajax_handle_profile_leave_submission' ) );
        add_action( 'wp_ajax_ems_change_leave_status', array( $this->ajax_handlers, 'ajax_ems_change_leave_status' ) );
        
        // Admin Notices
        add_action( 'admin_notices', array( $this->admin_menus, 'show_general_admin_notices' ) ); 

        // Assets
        add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_admin_scripts' ) );
    }
    
    /**
     * Plugin activation.
     */
    public function activate() {
        if (!isset($this->employee_cpt)) {
            require_once EMS_PLUGIN_DIR . 'includes/class-ems-cpt-employee.php';
            $this->employee_cpt = new EMS_Employee_CPT();
        }
        if (!isset($this->leave_request_cpt)) {
            require_once EMS_PLUGIN_DIR . 'includes/class-ems-cpt-leave-request.php';
            $this->leave_request_cpt = new EMS_Leave_Request_CPT();
        }

        $this->employee_cpt->register_employee_cpt();
        $this->leave_request_cpt->register_leave_request_cpt();
        flush_rewrite_rules();

        $js_dir = EMS_PLUGIN_DIR . 'js/';
        if (!is_dir($js_dir)) {
            wp_mkdir_p($js_dir);
        }
        $js_files = array(
            'admin-leave-validation.js' => '// Admin Leave Validation Script',
            'profile-leave-validation.js' => '// Profile/Dashboard Leave Validation Script',
            'admin-leave-list-actions.js' => '// Admin Leave List Actions Script'
        );
        foreach($js_files as $file => $content) {
            if (!file_exists($js_dir . $file)) {
                file_put_contents($js_dir . $file, $content);
            }
        }
        
        if ( false === get_option( 'ems_last_leave_request_id_counter' ) ) {
            add_option( 'ems_last_leave_request_id_counter', 0 );
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
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
 * Version 1.2.1 (Current - Remove Default "Add New" Submenu for Leave Requests)
 * - Added `remove_default_add_new_submenu` method to `EMS_Admin_Menus` class.
 * - This method uses `remove_submenu_page()` to hide the default "Add New" submenu item
 * for the "Leave Request" CPT, as the plugin uses a custom page for this.
 * - Hooked this new method to `admin_menu` with a late priority (999) in `emmansys.php`.
 * - Incremented plugin version to 1.2.1.
 *
 * Version 1.2.0 
 * - Refactored the main plugin class `Employee_Management_System` into multiple smaller classes
 * for better organization and maintainability.
 * - Incremented plugin version to 1.2.0.
 *
 * (Older versions summarized for brevity)
 * =====================================================================================
 */
