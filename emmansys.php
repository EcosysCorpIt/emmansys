<?php
/**
 * Plugin Name:       EmManSys
 * Description:       A simple plugin to create, edit, delete, and list employees and manage leave requests. Requires User Role Editor plugin.
 * Version:           1.4.0
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
    const VERSION = '1.4.0'; 

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
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
    }
    
    /**
     * Initialize the plugin.
     */
    public function init_plugin() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( is_plugin_active( 'user-role-editor/user-role-editor.php' ) ) {
            $this->dependency_met = true;
            $this->includes(); 
            $this->init_hooks(); 
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
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
     * Display admin notice if dependency is missing.
     */
    public function dependency_missing_notice() {
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

        add_action( 'admin_init', array( $this, 'handle_leave_type_actions' ) );

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
        add_action( 'save_post_leave_request', array( $this, 'save_leave_request_meta_data' ), 10, 2 );
        add_filter( 'manage_leave_request_posts_columns', array( $this, 'set_leave_request_columns' ) );
        add_action( 'manage_leave_request_posts_custom_column', array( $this, 'render_leave_request_columns' ), 10, 2 );
        add_filter( 'manage_edit-leave_request_sortable_columns', array( $this, 'make_leave_request_columns_sortable' ) );
        add_action( 'pre_get_posts', array( $this, 'sort_leave_request_columns_query' ) );

        // User Profile Leave Management
        add_action( 'show_user_profile', array( $this, 'show_leave_management_on_profile' ) );
        add_action( 'edit_user_profile', array( $this, 'show_leave_management_on_profile' ) );
        add_action( 'admin_post_ems_submit_leave_request', array( $this, 'handle_profile_leave_request_submission' ) );
        add_action( 'admin_notices', array( $this, 'show_leave_submission_notices' ) );

        // Admin Menus
        add_action( 'admin_menu', array( $this, 'add_leave_types_admin_menu' ) );
        add_action( 'admin_menu', array( $this, 'add_manager_dashboard_menu' ) ); // New Manager Dashboard Menu
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        global $pagenow;

        // For Leave Request CPT edit screen
        if ( ('post.php' === $pagenow || 'post-new.php' === $pagenow) && isset($_GET['post_type']) && $_GET['post_type'] === 'leave_request' ) {
             wp_enqueue_script( 'ems-admin-leave-validation', EMS_PLUGIN_URL . 'js/admin-leave-validation.js', array( 'jquery' ), self::VERSION, true );
             wp_localize_script('ems-admin-leave-validation', 'ems_leave_data', array(
                 'today' => current_time('Y-m-d'),
             ));
        }
        // For User Profile page
        if ( 'profile.php' === $hook_suffix || 'user-edit.php' === $hook_suffix ) {
            wp_enqueue_script( 'ems-profile-leave-validation', EMS_PLUGIN_URL . 'js/profile-leave-validation.js', array( 'jquery' ), self::VERSION, true );
             wp_localize_script('ems-profile-leave-validation', 'ems_profile_leave_data', array(
                'today' => current_time('Y-m-d'),
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ems-profile-leave-nonce' ),
                'action'   => 'ems_profile_leave_submit',
                'error_all_fields_required' => __( 'All fields are required.', 'emmansys' ),
                'error_start_date_past' => __( 'Start date cannot be in the past.', 'emmansys' ),
                'error_end_date_invalid' => __( 'End date cannot be earlier than the start date.', 'emmansys' ),
                'error_message_general' => __( 'An error occurred. Please try again.', 'emmansys' ),
            ));
        }
    }
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // No frontend scripts needed.
    }


    /**
     * Plugin activation.
     */
    public function activate() {
        $this->register_employee_cpt(); 
        $this->register_leave_request_cpt();
        flush_rewrite_rules();
        
        $includes_dir = EMS_PLUGIN_DIR . 'includes/';
        if (!is_dir($includes_dir)) {
            wp_mkdir_p($includes_dir);
        }

        // Initialize default leave types if not already set
        if ( false === get_option( EMS_Leave_Options::LEAVE_TYPES_OPTION_KEY ) ) {
            $default_leave_types = array(
                'vacation'    => __( 'Vacation', 'emmansys' ),
                'sick'        => __( 'Sick Leave', 'emmansys' ),
                'personal'    => __( 'Personal Leave', 'emmansys' ),
                'unpaid'      => __( 'Unpaid Leave', 'emmansys' ),
                'other'       => __( 'Other', 'emmansys' ),
            );
            update_option( EMS_Leave_Options::LEAVE_TYPES_OPTION_KEY, $default_leave_types );
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    // --- Employee CPT Methods ---
    // These methods (register_employee_cpt, etc.) are unchanged and omitted for brevity.
    public function register_employee_cpt() {
        $labels = array( 'name' => _x( 'Employees', 'Post type general name', 'emmansys' ), 'singular_name' => _x( 'Employee', 'Post type singular name', 'emmansys' ), 'menu_name' => _x( 'Employees', 'Admin Menu text', 'emmansys' ), 'name_admin_bar' => _x( 'Employee', 'Add New on Toolbar', 'emmansys' ), 'add_new' => __( 'Add New Employee', 'emmansys' ), 'add_new_item' => __( 'Add New Employee', 'emmansys' ), 'new_item' => __( 'New Employee', 'emmansys' ), 'edit_item' => __( 'Edit Employee', 'emmansys' ), 'view_item' => __( 'View Employee', 'emmansys' ), 'all_items' => __( 'All Employees', 'emmansys' ), 'search_items' => __( 'Search Employees', 'emmansys' ), 'parent_item_colon' => __( 'Parent Employees:', 'emmansys' ), 'not_found' => __( 'No employees found.', 'emmansys' ), 'not_found_in_trash' => __( 'No employees found in Trash.', 'emmansys' ), 'featured_image' => _x( 'Employee Photo', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'emmansys' ), 'set_featured_image' => _x( 'Set employee photo', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'emmansys' ), 'remove_featured_image' => _x( 'Remove employee photo', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'emmansys' ), 'use_featured_image' => _x( 'Use as employee photo', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'emmansys' ), 'archives' => _x( 'Employee archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'emmansys' ), 'insert_into_item' => _x( 'Insert into employee', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'emmansys' ), 'uploaded_to_this_item' => _x( 'Uploaded to this employee', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'emmansys' ), 'filter_items_list' => _x( 'Filter employees list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'emmansys' ), 'items_list_navigation' => _x( 'Employees list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'emmansys' ), 'items_list' => _x( 'Employees list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'emmansys' ),); $args = array( 'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => array( 'slug' => 'employee' ), 'capability_type' => 'employee', 'capabilities' => array( 'edit_post' => 'edit_employee', 'read_post' => 'read_employee', 'delete_post' => 'delete_employee', 'edit_posts' => 'edit_employees', 'edit_others_posts' => 'edit_others_employees', 'publish_posts' => 'publish_employees', 'read_private_posts' => 'read_private_employees', 'delete_posts' => 'delete_employees', 'delete_private_posts' => 'delete_private_employees', 'delete_published_posts' => 'delete_published_employees', 'delete_others_posts' => 'delete_others_employees', 'edit_private_posts' => 'edit_private_employees', 'edit_published_posts' => 'edit_published_employees', ), 'map_meta_cap' => true, 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 20, 'menu_icon' => 'dashicons-groups', 'supports' => array( 'thumbnail', 'custom-fields' ), ); register_post_type( 'employee', $args );
    }
    public function add_employee_meta_boxes() { 
        global $typenow; if ( $typenow === 'employee' ) { $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0); if ( ($post_id && current_user_can('edit_employee', $post_id)) || current_user_can('publish_employees') ) { add_meta_box('employee_details_meta_box', __( 'Employee Details', 'emmansys' ), array( $this, 'render_employee_details_meta_box' ), 'employee', 'normal', 'high'); } }
    }
    public function render_employee_details_meta_box( $post ) { 
        wp_nonce_field( 'ems_save_employee_details', 'ems_employee_details_nonce' ); $employee_full_name = get_post_meta( $post->ID, '_employee_full_name', true ); if (empty($employee_full_name) && $post->post_title !== 'Auto Draft' && $post->post_title !== 'auto-draft') { $employee_full_name = $post->post_title; } $linked_user_id = get_post_meta( $post->ID, '_employee_user_id', true ); $employee_id_meta = get_post_meta( $post->ID, '_employee_id', true ); $department  = get_post_meta( $post->ID, '_employee_department', true ); $position = get_post_meta( $post->ID, '_employee_position', true ); $email = get_post_meta( $post->ID, '_employee_email', true ); $phone = get_post_meta( $post->ID, '_employee_phone', true ); $salary = get_post_meta( $post->ID, '_employee_salary', true ); $users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) ); ?> <table class="form-table"><tbody><tr><th><label for="ems_employee_full_name"><?php _e( 'Employee Full Name', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_full_name" name="ems_employee_full_name" value="<?php echo esc_attr( $employee_full_name ); ?>" class="regular-text" required /><p class="description"><?php _e( 'This name will be used as the employee record identifier.', 'emmansys' ); ?></p></td></tr><tr><th><label for="ems_employee_user_id"><?php _e( 'Linked WordPress User', 'emmansys' ); ?></label></th><td><select id="ems_employee_user_id" name="ems_employee_user_id"><option value=""><?php _e( '-- Select a User --', 'emmansys' ); ?></option><?php foreach ( $users as $user ) : ?><option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $linked_user_id, $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?> (ID: <?php echo esc_html( $user->ID ); ?>)</option><?php endforeach; ?></select><p class="description"><?php _e( 'Tag this employee record to a WordPress user account. This is required for leave filing from profile.', 'emmansys' ); ?></p></td></tr><tr><th><label for="ems_employee_id_field"><?php _e( 'Employee ID', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_id_field" name="ems_employee_id_field" value="<?php echo esc_attr( $employee_id_meta ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_department"><?php _e( 'Department', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_department" name="ems_employee_department" value="<?php echo esc_attr( $department ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_position"><?php _e( 'Position', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_position" name="ems_employee_position" value="<?php echo esc_attr( $position ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_email"><?php _e( 'Email Address', 'emmansys' ); ?></label></th><td><input type="email" id="ems_employee_email" name="ems_employee_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_phone"><?php _e( 'Phone Number', 'emmansys' ); ?></label></th><td><input type="tel" id="ems_employee_phone" name="ems_employee_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td></tr><tr><th><label for="ems_employee_salary"><?php _e( 'Salary', 'emmansys' ); ?></label></th><td><input type="number" id="ems_employee_salary" name="ems_employee_salary" value="<?php echo esc_attr( $salary ); ?>" class="regular-text" step="0.01" /></td></tr></tbody></table> <?php
    }
    public function save_employee_meta_data( $post_id, $post ) { 
        if ( ! isset( $_POST['ems_employee_details_nonce'] ) || ! wp_verify_nonce( $_POST['ems_employee_details_nonce'], 'ems_save_employee_details' ) ) return; if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; if ( $post->post_type !== 'employee' ) return; if ( !current_user_can( 'edit_employee', $post_id ) ) { return; } $fields_to_save = array('_employee_full_name'  => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_full_name'), '_employee_user_id' => array('sanitize_callback' => 'absint', 'field_name' => 'ems_employee_user_id'), '_employee_id' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_id_field'), '_employee_department' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_department'), '_employee_position' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_position'), '_employee_email' => array('sanitize_callback' => 'sanitize_email', 'field_name' => 'ems_employee_email'), '_employee_phone' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_phone'), '_employee_salary' => array('sanitize_callback' => 'floatval', 'field_name' => 'ems_employee_salary'),); foreach ( $fields_to_save as $meta_key => $field_config ) { if ( isset( $_POST[ $field_config['field_name'] ] ) ) { $value = call_user_func( $field_config['sanitize_callback'], $_POST[ $field_config['field_name'] ] ); update_post_meta( $post_id, $meta_key, $value ); } } if (isset($_POST['ems_employee_full_name']) && !empty($_POST['ems_employee_full_name'])) { $full_name = sanitize_text_field($_POST['ems_employee_full_name']); if ($post->post_title !== $full_name) { remove_action('save_post_employee', array($this, 'save_employee_meta_data'), 10); wp_update_post(array('ID' => $post_id, 'post_title' => $full_name, 'post_name' => sanitize_title($full_name) )); add_action('save_post_employee', array($this, 'save_employee_meta_data'), 10, 2); } }
    }
    public function set_employee_columns( $columns ) { 
        unset($columns['title']); $new_columns = array(); $new_columns['cb'] = $columns['cb']; $new_columns['ems_employee_name_col'] = __( 'Employee Full Name', 'emmansys' ); $new_columns['ems_linked_user'] = __( 'Linked WP User', 'emmansys' ); $new_columns['ems_employee_id'] = __( 'Employee ID', 'emmansys' ); $new_columns['ems_department'] = __( 'Department', 'emmansys' ); $new_columns['ems_position'] = __( 'Position', 'emmansys' ); $new_columns['ems_email'] = __( 'Email', 'emmansys' ); $new_columns['date'] = $columns['date']; return $new_columns;
    }
    public function render_employee_columns( $column, $post_id ) { 
        switch ( $column ) { case 'ems_employee_name_col': echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>'; break; case 'ems_linked_user': $user_id = get_post_meta( $post_id, '_employee_user_id', true ); if ($user_id && $user = get_userdata($user_id)) { echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . ' (ID: ' . esc_html($user_id) . ')</a>'; } else { echo '—'; } break; case 'ems_employee_id': echo esc_html( get_post_meta( $post_id, '_employee_id', true ) ); break; case 'ems_department': echo esc_html( get_post_meta( $post_id, '_employee_department', true ) ); break; case 'ems_position': echo esc_html( get_post_meta( $post_id, '_employee_position', true ) ); break; case 'ems_email': echo esc_html( get_post_meta( $post_id, '_employee_email', true ) ); break; }
    }
    public function make_employee_columns_sortable( $columns ) { 
        $columns['ems_employee_name_col'] = 'title'; $columns['ems_linked_user'] = 'ems_linked_user_sort'; $columns['ems_employee_id'] = 'ems_employee_id_sort'; $columns['ems_department'] = 'ems_department_sort'; $columns['ems_position'] = 'ems_position_sort'; return $columns;
    }
    public function sort_employee_columns_query( $query ) { 
        if ( ! is_admin() || ! $query->is_main_query() ) return; $orderby = $query->get( 'orderby' ); $post_type = $query->get('post_type'); if ($post_type === 'employee') { if ( 'ems_linked_user_sort' === $orderby ) { $query->set( 'meta_key', '_employee_user_id' ); $query->set( 'orderby', 'meta_value_num' ); } elseif ( 'ems_employee_id_sort' === $orderby ) { $query->set( 'meta_key', '_employee_id' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_department_sort' === $orderby ) { $query->set( 'meta_key', '_employee_department' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_position_sort' === $orderby ) { $query->set( 'meta_key', '_employee_position' ); $query->set( 'orderby', 'meta_value' ); } }
    }
    public function render_employee_list_shortcode( $atts ) { 
        $atts = shortcode_atts( array('count' => 10, 'department' => ''), $atts, 'list_employees' ); $args = array( 'post_type' => 'employee', 'posts_per_page' => intval( $atts['count'] ), 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'); if ( ! empty( $atts['department'] ) ) { $args['meta_query'] = array( array( 'key' => '_employee_department', 'value' => sanitize_text_field( $atts['department'] ), 'compare' => '=' ) ); } $employees_query = new WP_Query( $args ); $output = ''; if ( $employees_query->have_posts() ) { $output .= '<ul class="employee-list">'; while ( $employees_query->have_posts() ) { $employees_query->the_post(); $post_id = get_the_ID(); $name = get_the_title(); $position = get_post_meta( $post_id, '_employee_position', true ); $department_meta = get_post_meta( $post_id, '_employee_department', true ); $email = get_post_meta( $post_id, '_employee_email', true ); $permalink = get_permalink(); $output .= '<li>'; if ( has_post_thumbnail() ) $output .= '<div class="employee-photo">' . get_the_post_thumbnail( $post_id, 'thumbnail' ) . '</div>'; $output .= '<div class="employee-info">'; $output .= '<strong><a href="' . esc_url($permalink) . '">' . esc_html( $name ) . '</a></strong><br />'; if ( $position ) $output .= esc_html( $position ) . '<br />'; if ( $department_meta ) $output .= '<em>' . esc_html( $department_meta ) . '</em><br />'; if ( $email ) $output .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a><br />'; $output .= '</div></li>'; } $output .= '</ul>'; wp_reset_postdata(); } else { $output = '<p>' . __( 'No employees found.', 'emmansys' ) . '</p>'; } $output .= '<style>.employee-list { list-style: none; padding: 0; } .employee-list li { display: flex; align-items: flex-start; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; } .employee-list .employee-photo img { width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 50%; } .employee-list .employee-info { flex-grow: 1; } .employee-list .employee-info strong { font-size: 1.2em; }</style>'; return $output;
    }


    // --- Leave Request CPT Methods ---
    // These methods are unchanged and omitted for brevity.
    public function register_leave_request_cpt() {
        $labels = array( 'name' => _x( 'Leave Requests', 'Post type general name', 'emmansys' ), 'singular_name' => _x( 'Leave Request', 'Post type singular name', 'emmansys' ), 'menu_name' => _x( 'Leave Requests', 'Admin Menu text', 'emmansys' ), 'name_admin_bar' => _x( 'Leave Request', 'Add New on Toolbar', 'emmansys' ), 'add_new' => __( 'Add New Leave Request', 'emmansys' ), 'add_new_item' => __( 'Add New Leave Request', 'emmansys' ), 'new_item' => __( 'New Leave Request', 'emmansys' ), 'edit_item' => __( 'Edit Leave Request', 'emmansys' ), 'view_item' => __( 'View Leave Request', 'emmansys' ), 'all_items' => __( 'All Leave Requests', 'emmansys' ), 'search_items' => __( 'Search Leave Requests', 'emmansys' ), 'not_found' => __( 'No leave requests found.', 'emmansys' ), 'not_found_in_trash' => __( 'No leave requests found in Trash.', 'emmansys' ), 'archives' => _x( 'Leave Request Archives', 'The post type archive label used in nav menus.', 'emmansys' ), 'insert_into_item' => _x( 'Insert into leave request', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post).', 'emmansys' ), 'uploaded_to_this_item' => _x( 'Uploaded to this leave request', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'emmansys' ), 'filter_items_list' => _x( 'Filter leave requests list', 'Screen reader text for the filter links heading on the post type listing screen.', 'emmansys' ), 'items_list_navigation' => _x( 'Leave requests list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'emmansys' ), 'items_list' => _x( 'Leave requests list', 'Screen reader text for the items list heading on the post type listing screen.', 'emmansys' ),);
        $args = array(
            'labels' => $labels, 'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_menu' => true,
            'query_var' => true, 'rewrite' => array( 'slug' => 'leave-request' ), 
            'capability_type' => 'leave_request',
            'capabilities' => array(
                'edit_post'          => 'edit_leave_request', 'read_post'          => 'read_leave_request',
                'delete_post'        => 'delete_leave_request', 'edit_posts'         => 'edit_leave_requests',
                'edit_others_posts'  => 'edit_others_leave_requests', 'publish_posts'      => 'publish_leave_requests',
                'read_private_posts' => 'read_private_leave_requests', 'delete_posts'       => 'delete_leave_requests',
                'delete_private_posts' => 'delete_private_leave_requests', 'delete_published_posts' => 'delete_published_leave_requests',
                'delete_others_posts' => 'delete_others_leave_requests', 'edit_private_posts' => 'edit_private_leave_requests',
                'edit_published_posts' => 'edit_published_leave_requests',
            ),
            'map_meta_cap' => true,
            'has_archive' => false, 'hierarchical' => false, 'menu_position' => 21, 'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array( 'custom-fields' ),
        );
        register_post_type( 'leave_request', $args );
    }
    public function add_leave_request_meta_boxes() {
        global $typenow; if ( $typenow === 'leave_request' ) { $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0); if ( ($post_id && current_user_can('edit_leave_request', $post_id)) || current_user_can('publish_leave_requests') ) { add_meta_box('leave_request_details_meta_box', __( 'Leave Request Details', 'emmansys' ), array( $this, 'render_leave_request_details_meta_box' ), 'leave_request', 'normal', 'high'); } }
    }
    
    public function render_leave_request_details_meta_box( $post ) {
        wp_nonce_field( 'ems_save_leave_request_details', 'ems_leave_request_details_nonce' );
        $selected_employee_cpt_id = get_post_meta( $post->ID, '_leave_employee_cpt_id', true );
        $leave_type = get_post_meta( $post->ID, '_leave_type', true ); 
        $start_date = get_post_meta( $post->ID, '_leave_start_date', true );
        $end_date   = get_post_meta( $post->ID, '_leave_end_date', true ); 
        $leave_duration = get_post_meta( $post->ID, '_leave_duration', true );
        $leave_reason = get_post_meta( $post->ID, '_leave_reason', true );
        $leave_status = get_post_meta( $post->ID, '_leave_status', true ); 
        $admin_notes = get_post_meta( $post->ID, '_leave_admin_notes', true );
        
        $leave_types = EMS_Leave_Options::get_leave_types(); 
        $statuses = EMS_Leave_Options::get_leave_statuses();
        $durations = EMS_Leave_Options::get_leave_durations();
        
        $current_user_can_manage_others = current_user_can('edit_others_leave_requests');
        $today = current_time('Y-m-d');
        ?>
        <p><em><?php $display_title = ($post->post_title === 'Auto Draft' || empty($post->post_title)) ? __('(Will be auto-generated on save)', 'emmansys') : $post->post_title; printf(__( 'Leave Request Title: %s', 'emmansys'), esc_html($display_title)); ?></em></p>
        <table class="form-table"><tbody><tr><th><label for="ems_leave_employee_cpt_id"><?php _e( 'Employee', 'emmansys' ); ?></label></th><td>
        <?php if ( $current_user_can_manage_others ) : $employee_posts_args = array('post_type' => 'employee', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'); $employee_posts = get_posts( $employee_posts_args ); ?>
        <select id="ems_leave_employee_cpt_id" name="ems_leave_employee_cpt_id" required><option value=""><?php _e( '-- Select Employee --', 'emmansys' ); ?></option><?php foreach ( $employee_posts as $employee_post ) : ?><option value="<?php echo esc_attr( $employee_post->ID ); ?>" <?php selected( $selected_employee_cpt_id, $employee_post->ID ); ?> data-user-id="<?php echo esc_attr(get_post_meta($employee_post->ID, '_employee_user_id', true)); ?>"><?php echo esc_html( $employee_post->post_title ); ?><?php $linked_wp_user_id_option = get_post_meta($employee_post->ID, '_employee_user_id', true); if ($linked_wp_user_id_option) { echo ' (WP User ID: ' . esc_html($linked_wp_user_id_option) . ')'; } ?></option><?php endforeach; ?></select><p class="description"><?php _e( 'Select the employee filing this leave.', 'emmansys' ); ?></p>
        <?php else: $current_wp_user_id = get_current_user_id(); $linked_employee_cpt_id_for_user = null; $employee_name_for_user = __('N/A - Your user is not linked to an Employee Record.', 'emmansys'); $args_user_employee = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_employee_user_id', 'value' => $current_wp_user_id, 'compare' => '='))); $current_user_employee_records = get_posts($args_user_employee); if (!empty($current_user_employee_records)) { $linked_employee_cpt_id_for_user = $current_user_employee_records[0]->ID; $employee_name_for_user = $current_user_employee_records[0]->post_title; if ($post->ID && $selected_employee_cpt_id && $selected_employee_cpt_id != $linked_employee_cpt_id_for_user && $selected_employee_cpt_id != 0) { echo '<p class="notice notice-warning">' . __('Warning: This leave request is for a different employee. You can only manage your own.', 'emmansys') . '</p>'; $original_employee_post = get_post($selected_employee_cpt_id); if ($original_employee_post) { echo '<strong>' . esc_html($original_employee_post->post_title) . '</strong>'; echo '<input type="hidden" name="ems_leave_employee_cpt_id" value="' . esc_attr($selected_employee_cpt_id) . '" />'; } else { echo '<strong>' . __('Unknown Employee', 'emmansys') . '</strong>'; } } else { echo '<strong>' . esc_html($employee_name_for_user) . '</strong>'; echo '<input type="hidden" name="ems_leave_employee_cpt_id" value="' . esc_attr($linked_employee_cpt_id_for_user) . '" />'; } } else { echo '<strong>' . esc_html($employee_name_for_user) . '</strong>'; } ?><p class="description"><?php _e( 'Leave request will be filed for your linked employee record.', 'emmansys' ); ?></p><?php endif; ?>
        </td></tr><tr><th><label for="ems_leave_type"><?php _e( 'Leave Type', 'emmansys' ); ?></label></th><td><select id="ems_leave_type" name="ems_leave_type" required><option value=""><?php _e( '-- Select Type --', 'emmansys' ); ?></option><?php foreach ( $leave_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_type, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
        <tr><th><label for="ems_leave_start_date"><?php _e( 'Start Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_leave_start_date" name="ems_leave_start_date" value="<?php echo esc_attr( $start_date ); ?>" class="regular-text ems-leave-start-date" min="<?php echo esc_attr($today); ?>" required/></td></tr>
        <tr><th><label for="ems_leave_end_date"><?php _e( 'End Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_leave_end_date" name="ems_leave_end_date" value="<?php echo esc_attr( $end_date ); ?>" class="regular-text ems-leave-end-date" min="<?php echo esc_attr($today); ?>" required/></td></tr>
        <tr><th><label for="ems_leave_duration"><?php _e( 'Leave Duration', 'emmansys' ); ?></label></th><td><select id="ems_leave_duration" name="ems_leave_duration" required><?php foreach ( $durations as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_duration, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
        <tr><th><label for="ems_leave_reason_field"><?php _e( 'Reason for Leave', 'emmansys' ); ?></label></th><td><textarea id="ems_leave_reason_field" name="ems_leave_reason_field" rows="5" class="large-text" required><?php echo esc_textarea( $leave_reason ); ?></textarea></td></tr><tr><th><label for="ems_leave_status"><?php _e( 'Leave Status', 'emmansys' ); ?></label></th><td><select id="ems_leave_status" name="ems_leave_status" <?php if (!current_user_can('approve_leave_requests')) echo 'disabled'; ?>><option value=""><?php _e( '-- Select Status --', 'emmansys' ); ?></option><?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr><tr><th><label for="ems_leave_admin_notes"><?php _e( 'Admin Notes', 'emmansys' ); ?></label></th><td><textarea id="ems_leave_admin_notes" name="ems_leave_admin_notes" rows="3" class="large-text" <?php if (!current_user_can('approve_leave_requests')) echo 'disabled'; ?>><?php echo esc_textarea( $admin_notes ); ?></textarea><p class="description"><?php _e( 'Notes for admin/manager regarding this leave request.', 'emmansys' ); ?></p></td></tr></tbody></table>
        <?php
    }
    public function save_leave_request_meta_data( $post_id, $post ) { 
        if ( ! isset( $_POST['ems_leave_request_details_nonce'] ) || ! wp_verify_nonce( $_POST['ems_leave_request_details_nonce'], 'ems_save_leave_request_details' ) ) return; if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; if ( $post->post_type !== 'leave_request' ) return; if ( !current_user_can( 'edit_leave_request', $post_id ) ) { return; }
        $start_date_val = isset( $_POST['ems_leave_start_date'] ) ? sanitize_text_field( $_POST['ems_leave_start_date'] ) : '';
        $end_date_val   = isset( $_POST['ems_leave_end_date'] ) ? sanitize_text_field( $_POST['ems_leave_end_date'] ) : '';
        $today_val      = current_time('Y-m-d');

        if ( !empty($start_date_val) && $start_date_val < $today_val ) { wp_die( __('Error: Start date cannot be in the past.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>'); }
        if ( !empty($start_date_val) && !empty($end_date_val) && $end_date_val < $start_date_val ) { wp_die( __('Error: End date cannot be earlier than the start date.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>'); }

        $employee_cpt_id_for_meta = null; if ( isset( $_POST['ems_leave_employee_cpt_id'] ) ) { $employee_cpt_id_for_meta = absint( $_POST['ems_leave_employee_cpt_id'] ); if ( $employee_cpt_id_for_meta > 0 ) { update_post_meta( $post_id, '_leave_employee_cpt_id', $employee_cpt_id_for_meta ); $employee_post = get_post( $employee_cpt_id_for_meta ); if ( $employee_post ) { update_post_meta( $post_id, '_leave_employee_name', sanitize_text_field( $employee_post->post_title ) ); $linked_wp_user_id = get_post_meta( $employee_cpt_id_for_meta, '_employee_user_id', true ); if ( $linked_wp_user_id ) { update_post_meta( $post_id, '_leave_user_id', absint( $linked_wp_user_id ) ); } else { delete_post_meta( $post_id, '_leave_user_id'); } } } else { delete_post_meta( $post_id, '_leave_employee_cpt_id'); delete_post_meta( $post_id, '_leave_employee_name'); if ( $post->post_author && !current_user_can('edit_others_leave_requests')) { update_post_meta( $post_id, '_leave_user_id', $post->post_author ); $author_data = get_userdata($post->post_author); if ($author_data) { $args_author_employee = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_employee_user_id', 'value' => $post->post_author, 'compare' => '='))); $author_employee_records = get_posts($args_author_employee); if(!empty($author_employee_records)){ update_post_meta( $post_id, '_leave_employee_name', sanitize_text_field($author_employee_records[0]->post_title)); update_post_meta( $post_id, '_leave_employee_cpt_id', $author_employee_records[0]->ID); } else { update_post_meta( $post_id, '_leave_employee_name', $author_data->display_name); } } } else { delete_post_meta( $post_id, '_leave_user_id'); } } }
        $other_fields_to_save = array( '_leave_type' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_type'), '_leave_start_date' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_leave_start_date'), '_leave_end_date' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_leave_end_date'), '_leave_duration' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_duration'), '_leave_reason' => array('sanitize_callback' => 'sanitize_textarea_field', 'field_name' => 'ems_leave_reason_field'), '_leave_status' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_status'), '_leave_admin_notes' => array('sanitize_callback' => 'sanitize_textarea_field', 'field_name' => 'ems_leave_admin_notes'),);
        if (!current_user_can('approve_leave_requests')) { unset($other_fields_to_save['_leave_status']); unset($other_fields_to_save['_leave_admin_notes']); }
        foreach ( $other_fields_to_save as $meta_key => $field_config ) { if ( isset( $_POST[ $field_config['field_name'] ] ) ) { $value = call_user_func( $field_config['sanitize_callback'], $_POST[ $field_config['field_name'] ] ); update_post_meta( $post_id, $meta_key, $value ); } }
        $current_employee_cpt_id = get_post_meta($post_id, '_leave_employee_cpt_id', true); $current_start_date = get_post_meta($post_id, '_leave_start_date', true); $current_end_date = get_post_meta($post_id, '_leave_end_date', true);
        if ( $current_employee_cpt_id && $current_start_date && $current_end_date ) { $employee_for_title = get_post($current_employee_cpt_id); if ($employee_for_title) { $new_title = sprintf(__( 'Leave: %s (%s to %s)', 'emmansys' ), $employee_for_title->post_title, $current_start_date, $current_end_date); if ($post->post_title !== $new_title && !($post->post_title === 'Auto Draft' && empty($new_title)) ) { remove_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 10); wp_update_post(array('ID' => $post_id, 'post_title' => $new_title, 'post_name' => sanitize_title($new_title) )); add_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 10, 2); } } } elseif (empty($post->post_title) || $post->post_title === 'Auto Draft') { $fallback_title = __('Leave Request', 'emmansys') . ' - ' . $post_id; remove_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 10); wp_update_post(array('ID' => $post_id, 'post_title' => $fallback_title, 'post_name' => sanitize_title($fallback_title) )); add_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 10, 2); }
    }
    
    public function set_leave_request_columns( $columns ) { 
        unset($columns['title']); 
        $new_columns = array(); 
        $new_columns['cb'] = $columns['cb'];
        $new_columns['ems_leave_title_col'] = __( 'Leave Request', 'emmansys' ); 
        $new_columns['ems_leave_employee'] = __( 'Employee', 'emmansys' );
        $new_columns['ems_leave_user'] = __( 'WP User', 'emmansys' );
        $new_columns['ems_leave_type'] = __( 'Leave Type', 'emmansys' );
        $new_columns['ems_leave_dates'] = __( 'Dates', 'emmansys' ); 
        $new_columns['ems_leave_duration_col'] = __( 'Duration', 'emmansys' );
        $new_columns['ems_leave_status'] = __( 'Status', 'emmansys' );
        $new_columns['date'] = $columns['date']; 
        return $new_columns;
    }
    
    public function render_leave_request_columns( $column, $post_id ) { 
        $leave_types = EMS_Leave_Options::get_leave_types(); 
        $statuses = EMS_Leave_Options::get_leave_statuses();
        $durations = EMS_Leave_Options::get_leave_durations();

        switch ( $column ) { 
            case 'ems_leave_title_col': echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>'; break; 
            case 'ems_leave_employee': $employee_cpt_id = get_post_meta( $post_id, '_leave_employee_cpt_id', true ); $employee_name = get_post_meta( $post_id, '_leave_employee_name', true ); if ($employee_cpt_id && $employee_post = get_post($employee_cpt_id)) { echo '<a href="' . esc_url(get_edit_post_link($employee_cpt_id)) . '"><strong>' . esc_html($employee_post->post_title) . '</strong></a>'; } elseif ($employee_name) { echo '<strong>' . esc_html($employee_name) . '</strong>'; } else { echo '—'; } break; 
            case 'ems_leave_user': $user_id = get_post_meta( $post_id, '_leave_user_id', true ); if ($user_id && $user = get_userdata($user_id)) { echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . '</a>'; } else { echo '—'; } break; 
            case 'ems_leave_type': $type_key = get_post_meta( $post_id, '_leave_type', true ); echo esc_html( $leave_types[$type_key] ?? $type_key ); break; 
            case 'ems_leave_dates': $start = get_post_meta( $post_id, '_leave_start_date', true ); $end = get_post_meta( $post_id, '_leave_end_date', true ); echo esc_html($start) . ' - ' . esc_html($end); break; 
            case 'ems_leave_duration_col': $duration_key = get_post_meta( $post_id, '_leave_duration', true ); echo esc_html( $durations[$duration_key] ?? $duration_key ); break;
            case 'ems_leave_status': $status_key = get_post_meta( $post_id, '_leave_status', true ); echo '<strong>' . esc_html( $statuses[$status_key] ?? $status_key ) . '</strong>'; break; 
        } 
    }

    public function make_leave_request_columns_sortable( $columns ) { 
        $columns['ems_leave_title_col'] = 'title'; 
        $columns['ems_leave_employee'] = 'ems_leave_employee_sort';
        $columns['ems_leave_user'] = 'ems_leave_user_sort';
        $columns['ems_leave_type'] = 'ems_leave_type_sort';
        $columns['ems_leave_status'] = 'ems_leave_status_sort';
        return $columns;
    }
    public function sort_leave_request_columns_query( $query ) { 
        if ( ! is_admin() || ! $query->is_main_query() ) return; $orderby = $query->get( 'orderby' ); $post_type = $query->get('post_type'); if ($post_type === 'leave_request') { if ( 'ems_leave_employee_sort' === $orderby ) { $query->set( 'meta_key', '_leave_employee_name' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_leave_user_sort' === $orderby ) { $query->set( 'meta_key', '_leave_user_id' ); $query->set( 'orderby', 'meta_value_num' ); } elseif ( 'ems_leave_type_sort' === $orderby ) { $query->set( 'meta_key', '_leave_type' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_leave_status_sort' === $orderby ) { $query->set( 'meta_key', '_leave_status' ); $query->set( 'orderby', 'meta_value' ); } }
    }

    // --- User Profile Leave Management Methods ---
    // These methods are unchanged and omitted for brevity.
    public function show_leave_management_on_profile( $user_profile ) { 
        if ( ! is_object( $user_profile ) || ! isset( $user_profile->ID ) ) return;
        
        $current_user_id = get_current_user_id(); 
        if ( $user_profile->ID !== $current_user_id && ! current_user_can( 'edit_users' ) ) {
            // This is a basic check. The logic below will handle specific capabilities.
        }

        $linked_employee_cpt_id = null;
        $employee_query_args = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array( array('key' => '_employee_user_id', 'value' => $user_profile->ID, 'compare' => '=')), 'fields' => 'ids');
        $linked_employee_posts = get_posts($employee_query_args);
        if (!empty($linked_employee_posts)) { $linked_employee_cpt_id = $linked_employee_posts[0]; } 
        
        $leave_types = EMS_Leave_Options::get_leave_types();
        $statuses = EMS_Leave_Options::get_leave_statuses();
        $durations = EMS_Leave_Options::get_leave_durations();
        
        $today = current_time('Y-m-d');
        ?> 
        <hr><h2><?php _e( 'Leave Management', 'emmansys' ); ?></h2> 
        <?php if ( $current_user_id === $user_profile->ID && $linked_employee_cpt_id && current_user_can('submit_profile_leave_request') ) : ?>
        <h3><?php _e( 'File a New Leave Request', 'emmansys' ); ?></h3>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="ems-profile-leave-form">
            <input type="hidden" name="action" value="ems_submit_leave_request">
            <input type="hidden" name="ems_user_id" value="<?php echo esc_attr( $user_profile->ID ); ?>">
            <input type="hidden" name="ems_employee_cpt_id_profile" value="<?php echo esc_attr( $linked_employee_cpt_id ); ?>">
            <?php wp_nonce_field( 'ems_submit_leave_request_nonce', 'ems_leave_request_profile_nonce' ); ?>
            <table class="form-table"><tbody>
                <tr><th><label for="ems_profile_leave_type"><?php _e( 'Leave Type', 'emmansys' ); ?></label></th><td><select id="ems_profile_leave_type" name="ems_profile_leave_type" required><option value=""><?php _e( '-- Select Type --', 'emmansys' ); ?></option><?php foreach ( $leave_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
                <tr><th><label for="ems_profile_start_date"><?php _e( 'Start Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_profile_start_date" name="ems_profile_start_date" class="regular-text ems-leave-start-date" min="<?php echo esc_attr($today); ?>" required /></td></tr>
                <tr><th><label for="ems_profile_end_date"><?php _e( 'End Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_profile_end_date" name="ems_profile_end_date" class="regular-text ems-leave-end-date" min="<?php echo esc_attr($today); ?>" required /></td></tr>
                <tr><th><label for="ems_profile_leave_duration"><?php _e( 'Leave Duration', 'emmansys' ); ?></label></th><td><select id="ems_profile_leave_duration" name="ems_profile_leave_duration" required><?php foreach ( $durations as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
                <tr><th><label for="ems_profile_leave_reason_field"><?php _e( 'Reason for Leave', 'emmansys' ); ?></label></th><td><textarea id="ems_profile_leave_reason_field" name="ems_profile_leave_reason_field" rows="5" class="large-text" required></textarea></td></tr>
            </tbody></table>
            <?php submit_button( __( 'Submit Leave Request', 'emmansys' ) ); ?>
        </form><hr style="margin: 20px 0;">
        <?php elseif ($current_user_id === $user_profile->ID && !$linked_employee_cpt_id && current_user_can('submit_profile_leave_request')): ?> <p><?php _e( 'To file leave requests from your profile, your WordPress user account must first be linked to an Employee record by an administrator.', 'emmansys' ); ?></p><hr style="margin: 20px 0;">
        <?php endif; ?>
        <?php 
        if ( (current_user_can('view_own_profile_leave_history') && $current_user_id == $user_profile->ID) || ($user_profile->ID !== $current_user_id && current_user_can('edit_others_leave_requests')) ) : 
        ?>
        <h3><?php _e( 'Leave Request History', 'emmansys' ); ?></h3>
        <?php
        $args = array('post_type' => 'leave_request', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_leave_user_id', 'value' => $user_profile->ID, 'compare' => '=', 'type' => 'NUMERIC')), 'orderby' => 'date', 'order' => 'DESC');
        $user_leave_requests = new WP_Query( $args );
        if ( $user_leave_requests->have_posts() ) : ?>
            <table class="wp-list-table widefat fixed striped"><thead><tr>
                <th><?php _e( 'Request Date', 'emmansys' ); ?></th><th><?php _e( 'Leave Type', 'emmansys' ); ?></th>
                <th><?php _e( 'Dates', 'emmansys' ); ?></th><th><?php _e( 'Duration', 'emmansys' ); ?></th>
                <th><?php _e( 'Status', 'emmansys' ); ?></th><th><?php _e( 'Reason', 'emmansys' ); ?></th>
            </tr></thead><tbody>
            <?php
            while ( $user_leave_requests->have_posts() ) : $user_leave_requests->the_post(); $request_id = get_the_ID(); ?>
                <tr>
                    <td><?php echo get_the_date( '', $request_id ); ?></td>
                    <td><?php echo esc_html( $leave_types[get_post_meta( $request_id, '_leave_type', true )] ?? get_post_meta( $request_id, '_leave_type', true ) ); ?></td>
                    <td><?php echo esc_html( get_post_meta( $request_id, '_leave_start_date', true ) ); ?> - <?php echo esc_html( get_post_meta( $request_id, '_leave_end_date', true ) ); ?></td>
                    <td><?php echo esc_html( $durations[get_post_meta( $request_id, '_leave_duration', true )] ?? get_post_meta( $request_id, '_leave_duration', true ) ); ?></td>
                    <td><strong><?php echo esc_html( $statuses[get_post_meta( $request_id, '_leave_status', true )] ?? get_post_meta( $request_id, '_leave_status', true ) ); ?></strong></td>
                    <td><?php echo wp_kses_post( get_post_meta( $request_id, '_leave_reason', true ) ); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody></table>
        <?php else : ?> <p><?php _e( 'No leave requests have been submitted for this user.', 'emmansys' ); ?></p>
        <?php endif; wp_reset_postdata(); ?>
        <?php endif; ?>
        <hr style="margin-top:20px;">
        <?php
    }

    public function handle_profile_leave_request_submission() { 
        if ( !current_user_can('submit_profile_leave_request') ) { wp_die( __( 'You do not have permission to submit leave requests.', 'emmansys' ) ); } 
        if ( ! isset( $_POST['ems_leave_request_profile_nonce'], $_POST['ems_user_id'], $_POST['ems_employee_cpt_id_profile'] ) || ! wp_verify_nonce( $_POST['ems_leave_request_profile_nonce'], 'ems_submit_leave_request_nonce' ) || get_current_user_id() != $_POST['ems_user_id'] ) { wp_die( __( 'Security check failed or you are not authorized to perform this action.', 'emmansys' ) ); } 
        $user_id = absint( $_POST['ems_user_id'] ); 
        $employee_cpt_id = absint( $_POST['ems_employee_cpt_id_profile'] );
        $user_info = get_userdata( $user_id ); 
        $employee_info = get_post( $employee_cpt_id );
        if ( ! $user_info || ! $employee_info || $employee_info->post_type !== 'employee' ) { set_transient( 'ems_leave_notice_error', __( 'Invalid user or employee record for leave request.', 'emmansys' ), 60 ); wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); exit; } 
        $linked_user_on_employee_cpt = get_post_meta($employee_cpt_id, '_employee_user_id', true);
        if (absint($linked_user_on_employee_cpt) !== $user_id) { set_transient( 'ems_leave_notice_error', __( 'Employee record mismatch.', 'emmansys' ), 60 ); wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); exit; }
        
        $leave_type = isset( $_POST['ems_profile_leave_type'] ) ? sanitize_key( $_POST['ems_profile_leave_type'] ) : '';
        $start_date_val = isset( $_POST['ems_profile_start_date'] ) ? sanitize_text_field( $_POST['ems_profile_start_date'] ) : '';
        $end_date_val = isset( $_POST['ems_profile_end_date'] ) ? sanitize_text_field( $_POST['ems_profile_end_date'] ) : '';
        $leave_duration_val = isset( $_POST['ems_profile_leave_duration'] ) ? sanitize_key( $_POST['ems_profile_leave_duration'] ) : 'whole_day'; 
        $leave_reason = isset( $_POST['ems_profile_leave_reason_field'] ) ? sanitize_textarea_field( $_POST['ems_profile_leave_reason_field'] ) : '';
        $today_val = current_time('Y-m-d');

        if ( empty( $leave_type ) || empty( $start_date_val ) || empty( $end_date_val ) || empty( $leave_reason ) || empty($leave_duration_val) ) { set_transient( 'ems_leave_notice_error', __( 'All fields are required for leave submission.', 'emmansys' ), 60 ); wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); exit; }
        if ( $start_date_val < $today_val ) { set_transient( 'ems_leave_notice_error', __( 'Error: Start date cannot be in the past.', 'emmansys' ), 60 ); wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); exit; }
        if ( $end_date_val < $start_date_val ) { set_transient( 'ems_leave_notice_error', __( 'Error: End date cannot be earlier than the start date.', 'emmansys' ), 60 ); wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) ); exit; }
        
        $post_title = sprintf( __( 'Leave: %s (%s to %s)', 'emmansys' ), $employee_info->post_title, $start_date_val, $end_date_val ); 
        $leave_request_data = array( 
            'post_title' => $post_title, 
            'post_status' => 'publish', 
            'post_type' => 'leave_request', 
            'post_author' => $user_id, 
        );
        $new_leave_request_id = wp_insert_post( $leave_request_data, true ); 
        if ( is_wp_error( $new_leave_request_id ) ) {
            set_transient( 'ems_leave_notice_error', __( 'Failed to submit leave request: ', 'emmansys' ) . $new_leave_request_id->get_error_message(), 60 );
        } else {
            update_post_meta( $new_leave_request_id, '_leave_employee_cpt_id', $employee_cpt_id ); 
            update_post_meta( $new_leave_request_id, '_leave_user_id', $user_id );
            update_post_meta( $new_leave_request_id, '_leave_employee_name', sanitize_text_field($employee_info->post_title) ); 
            update_post_meta( $new_leave_request_id, '_leave_type', $leave_type );
            update_post_meta( $new_leave_request_id, '_leave_start_date', $start_date_val ); 
            update_post_meta( $new_leave_request_id, '_leave_end_date', $end_date_val );
            update_post_meta( $new_leave_request_id, '_leave_duration', $leave_duration_val );
            update_post_meta( $new_leave_request_id, '_leave_reason', $leave_reason ); 
            update_post_meta( $new_leave_request_id, '_leave_status', 'pending' );
            set_transient( 'ems_leave_notice_success', __( 'Leave request submitted successfully. It is now pending approval.', 'emmansys' ), 60 );
        }
        wp_safe_redirect( wp_get_referer() ?: get_edit_user_link( $user_id ) );
        exit;
    }
    public function show_leave_submission_notices() { 
        if ( $message = get_transient( 'ems_leave_notice_success' ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; delete_transient( 'ems_leave_notice_success' ); } if ( $message = get_transient( 'ems_leave_notice_error' ) ) { echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; delete_transient( 'ems_leave_notice_error' ); }
    }

    // --- Leave Types Management ---
    /**
     * Adds the admin menu page for managing leave types.
     */
    public function add_leave_types_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=leave_request',
            __( 'Manage Leave Types', 'emmansys' ),
            __( 'Leave Types', 'emmansys' ),
            'manage_options', // Recommended: 'manage_leave_types'
            'ems-leave-types-settings',
            array( $this, 'render_leave_types_page' )
        );
    }

    /**
     * Renders the admin page for managing leave types.
     */
    public function render_leave_types_page() {
        if ( ! current_user_can( 'manage_options' ) ) { // Replace with 'manage_leave_types'
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) );
        }

        $current_action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
        $type_key_to_edit = isset( $_GET['type_key'] ) ? sanitize_key( $_GET['type_key'] ) : '';
        $all_leave_types = EMS_Leave_Options::get_leave_types();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Manage Leave Types', 'emmansys' ); ?></h1>
            <?php settings_errors( 'ems_leave_types' ); ?>

            <?php if ( 'edit' === $current_action && ! empty( $type_key_to_edit ) && array_key_exists( $type_key_to_edit, $all_leave_types ) ) : 
                $label_to_edit = $all_leave_types[$type_key_to_edit];
            ?>
                <hr class="wp-header-end">
                <h2><?php esc_html_e( 'Edit Leave Type', 'emmansys' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' ) ); ?>">
                    <input type="hidden" name="action" value="ems_update_leave_type">
                    <input type="hidden" name="ems_leave_type_key_original" value="<?php echo esc_attr( $type_key_to_edit ); ?>">
                    <?php wp_nonce_field( 'ems_update_leave_type_action', 'ems_update_leave_type_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="ems_leave_type_key_display"><?php esc_html_e( 'Leave Type Key', 'emmansys' ); ?></label></th>
                            <td>
                                <input type="text" id="ems_leave_type_key_display" value="<?php echo esc_attr( $type_key_to_edit ); ?>" class="regular-text" readonly="readonly" />
                                <p class="description"><?php esc_html_e( 'The key cannot be changed once created.', 'emmansys' ); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="ems_leave_type_label_edit"><?php esc_html_e( 'Leave Type Label', 'emmansys' ); ?></label></th>
                            <td><input type="text" id="ems_leave_type_label_edit" name="ems_leave_type_label" value="<?php echo esc_attr( $label_to_edit ); ?>" class="regular-text" required /></td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Update Leave Type', 'emmansys' ) ); ?>
                     <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'emmansys' ); ?></a>
                </form>
            <?php else : ?>
                <div id="col-container" class="wp-clearfix">
                    <div id="col-left">
                        <div class="col-wrap">
                            <h2><?php esc_html_e( 'Add New Leave Type', 'emmansys' ); ?></h2>
                            <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' ) ); ?>">
                                <input type="hidden" name="action" value="ems_add_leave_type">
                                <?php wp_nonce_field( 'ems_add_leave_type_action', 'ems_add_leave_type_nonce' ); ?>
                                
                                <div class="form-field">
                                    <label for="ems_leave_type_key"><?php esc_html_e( 'Leave Type Key', 'emmansys' ); ?></label>
                                    <input type="text" id="ems_leave_type_key" name="ems_leave_type_key" value="" class="regular-text" required pattern="[a-z0-9_]+" title="<?php esc_attr_e( 'Lowercase letters, numbers, and underscores only.', 'emmansys' ); ?>" />
                                    <p><?php esc_html_e( 'A unique identifier (e.g., "bereavement_leave"). Lowercase letters, numbers, and underscores only. Cannot be changed later.', 'emmansys' ); ?></p>
                                </div>
                                <div class="form-field">
                                    <label for="ems_leave_type_label"><?php esc_html_e( 'Leave Type Label', 'emmansys' ); ?></label>
                                    <input type="text" id="ems_leave_type_label" name="ems_leave_type_label" value="" class="regular-text" required />
                                    <p><?php esc_html_e( 'The display name for the leave type (e.g., "Bereavement Leave").', 'emmansys' ); ?></p>
                                </div>
                                <?php submit_button( __( 'Add New Leave Type', 'emmansys' ) ); ?>
                            </form>
                        </div>
                    </div>
                    <div id="col-right">
                        <div class="col-wrap">
                            <h2><?php esc_html_e( 'Current Leave Types', 'emmansys' ); ?></h2>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e( 'Label', 'emmansys' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Key', 'emmansys' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( ! empty( $all_leave_types ) ) : ?>
                                        <?php foreach ( $all_leave_types as $key => $label ) : ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html( $label ); ?></strong>
                                                    <div class="row-actions">
                                                        <span class="edit">
                                                            <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'type_key' => $key ), admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' ) ) ); ?>">
                                                                <?php esc_html_e( 'Edit', 'emmansys' ); ?>
                                                            </a> | 
                                                        </span>
                                                        <span class="delete">
                                                            <?php
                                                            $delete_nonce = wp_create_nonce( 'ems_delete_leave_type_action_' . $key );
                                                            $delete_url = add_query_arg(
                                                                array(
                                                                    'action' => 'ems_delete_leave_type',
                                                                    'type_key' => $key,
                                                                    '_wpnonce' => $delete_nonce
                                                                ),
                                                                admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' )
                                                            );
                                                            ?>
                                                            <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to delete the leave type "%s"? This action cannot be undone.', 'emmansys' ), $label ) ); ?>');" style="color:red;">
                                                                <?php esc_html_e( 'Delete', 'emmansys' ); ?>
                                                            </a>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td><code><?php echo esc_html( $key ); ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="2"><?php esc_html_e( 'No leave types configured yet.', 'emmansys' ); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> <?php endif; ?>
        </div><?php
    }

    /**
     * Handles adding, updating, and deleting leave types on admin_init.
     */
    public function handle_leave_type_actions() {
        // Only run this logic on our settings page
        if ( ! isset( $_REQUEST['page'] ) || 'ems-leave-types-settings' !== $_REQUEST['page'] ) {
            return;
        }
        
        if ( ! isset( $_REQUEST['action'] ) ) {
            return;
        }

        $action = sanitize_key( $_REQUEST['action'] );
        $option_key = EMS_Leave_Options::LEAVE_TYPES_OPTION_KEY;
        $current_leave_types = get_option( $option_key, array() );
        $redirect_url = admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' );

        // Add new leave type
        if ( 'ems_add_leave_type' === $action ) {
            if ( ! isset( $_POST['ems_add_leave_type_nonce'] ) || ! wp_verify_nonce( $_POST['ems_add_leave_type_nonce'], 'ems_add_leave_type_action' ) ) {
                wp_die( __( 'Security check failed.', 'emmansys' ) );
            }
            if ( ! current_user_can( 'manage_options' ) ) { // Replace with 'manage_leave_types'
                wp_die( __( 'You do not have permission to add leave types.', 'emmansys' ) );
            }

            $new_key = isset( $_POST['ems_leave_type_key'] ) ? sanitize_key( $_POST['ems_leave_type_key'] ) : '';
            $new_label = isset( $_POST['ems_leave_type_label'] ) ? sanitize_text_field( $_POST['ems_leave_type_label'] ) : '';

            if ( empty( $new_key ) || empty( $new_label ) ) {
                add_settings_error( 'ems_leave_types', 'empty_fields', __( 'Both key and label are required.', 'emmansys' ), 'error' );
            } elseif ( ! preg_match( '/^[a-z0-9_]+$/', $new_key ) ) {
                 add_settings_error( 'ems_leave_types', 'invalid_key', __( 'Leave Type Key can only contain lowercase letters, numbers, and underscores.', 'emmansys' ), 'error' );
            } elseif ( array_key_exists( $new_key, $current_leave_types ) ) {
                add_settings_error( 'ems_leave_types', 'duplicate_key', __( 'That leave type key already exists.', 'emmansys' ), 'error' );
            } else {
                $current_leave_types[ $new_key ] = $new_label;
                update_option( $option_key, $current_leave_types );
                add_settings_error( 'ems_leave_types', 'type_added', __( 'Leave type added successfully.', 'emmansys' ), 'success' ); // Use 'success' for green
            }
        
        // Update existing leave type label
        } elseif ( 'ems_update_leave_type' === $action ) {
            if ( ! isset( $_POST['ems_update_leave_type_nonce'] ) || ! wp_verify_nonce( $_POST['ems_update_leave_type_nonce'], 'ems_update_leave_type_action' ) ) {
                 wp_die( __( 'Security check failed.', 'emmansys' ) );
            }
             if ( ! current_user_can( 'manage_options' ) ) { // Replace with 'manage_leave_types'
                wp_die( __( 'You do not have permission to update leave types.', 'emmansys' ) );
            }

            $original_key = isset( $_POST['ems_leave_type_key_original'] ) ? sanitize_key( $_POST['ems_leave_type_key_original'] ) : '';
            $updated_label = isset( $_POST['ems_leave_type_label'] ) ? sanitize_text_field( $_POST['ems_leave_type_label'] ) : '';

            if ( empty( $original_key ) || ! array_key_exists( $original_key, $current_leave_types ) ) {
                add_settings_error( 'ems_leave_types', 'invalid_key_edit', __( 'Invalid leave type key for editing.', 'emmansys' ), 'error' );
            } elseif ( empty( $updated_label ) ) {
                add_settings_error( 'ems_leave_types', 'empty_label_edit', __( 'Leave type label cannot be empty.', 'emmansys' ), 'error' );
            } else {
                $current_leave_types[ $original_key ] = $updated_label;
                update_option( $option_key, $current_leave_types );
                add_settings_error( 'ems_leave_types', 'type_updated', __( 'Leave type updated successfully.', 'emmansys' ), 'success' );
            }
            
            // Redirect to show the message and clear the form
            wp_safe_redirect( add_query_arg( 'settings-updated', 'true', $redirect_url ) );
            exit;

        // Delete leave type
        } elseif ( 'ems_delete_leave_type' === $action ) {
            $key_to_delete = isset( $_GET['type_key'] ) ? sanitize_key( $_GET['type_key'] ) : '';
            $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

            if ( ! wp_verify_nonce( $nonce, 'ems_delete_leave_type_action_' . $key_to_delete ) ) {
                wp_die( __( 'Security check failed.', 'emmansys' ) );
            }
            if ( ! current_user_can( 'manage_options' ) ) { // Replace with 'manage_leave_types'
                wp_die( __( 'You do not have permission to delete leave types.', 'emmansys' ) );
            }

            if ( ! empty( $key_to_delete ) && array_key_exists( $key_to_delete, $current_leave_types ) ) {
                unset( $current_leave_types[ $key_to_delete ] );
                update_option( $option_key, $current_leave_types );
                add_settings_error( 'ems_leave_types', 'type_deleted', __( 'Leave type deleted successfully.', 'emmansys' ), 'success' );
            } else {
                add_settings_error( 'ems_leave_types', 'invalid_key_delete', __( 'Invalid leave type key for deletion.', 'emmansys' ), 'error' );
            }

            // Redirect to show the message
            wp_safe_redirect( add_query_arg( 'settings-updated', 'true', $redirect_url ) );
            exit;
        }
    }

    // --- Manager Dashboard ---
    /**
     * Adds the Manager Dashboard admin menu page.
     * A new capability 'view_ems_manager_dashboard' is recommended.
     */
    public function add_manager_dashboard_menu() {
        add_menu_page(
            __( 'Manager Dashboard', 'emmansys' ),    // Page title
            __( 'Manager Dashboard', 'emmansys' ),   // Menu title
            'approve_leave_requests', // Capability (placeholder, ideally 'view_ems_manager_dashboard')
            'ems-manager-dashboard',                // Menu slug
            array( $this, 'render_manager_dashboard_page' ), // Callback function
            'dashicons-businessman',                // Icon URL
            25                                      // Position
        );
    }

    /**
     * Renders the Manager Dashboard page.
     */
    public function render_manager_dashboard_page() {
        if ( ! current_user_can( 'approve_leave_requests' ) ) { // Replace with 'view_ems_manager_dashboard'
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Manager Dashboard', 'emmansys' ); ?></h1>
            <hr class="wp-header-end">

            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Pending Leave Requests', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                    <?php
                                    $pending_args = array(
                                        'post_type'      => 'leave_request',
                                        'posts_per_page' => -1,
                                        'post_status'    => 'publish', // CPT is not public, so 'publish' is fine for admin view
                                        'meta_query'     => array(
                                            array(
                                                'key'     => '_leave_status',
                                                'value'   => 'pending',
                                                'compare' => '=',
                                            ),
                                        ),
                                    );
                                    $pending_requests_query = new WP_Query( $pending_args );
                                    $pending_count = $pending_requests_query->found_posts;

                                    echo '<p>' . sprintf( _n( 'There is %s pending leave request.', 'There are %s pending leave requests.', $pending_count, 'emmansys' ), '<strong>' . number_format_i18n( $pending_count ) . '</strong>' ) . '</p>';

                                    if ( $pending_requests_query->have_posts() ) :
                                        echo '<ul style="list-style-type: disc; margin-left: 20px;">';
                                        while ( $pending_requests_query->have_posts() ) : $pending_requests_query->the_post();
                                            $employee_name = get_post_meta( get_the_ID(), '_leave_employee_name', true );
                                            $start_date = get_post_meta( get_the_ID(), '_leave_start_date', true );
                                            $end_date = get_post_meta( get_the_ID(), '_leave_end_date', true );
                                            $leave_types_map = EMS_Leave_Options::get_leave_types();
                                            $type_key = get_post_meta( get_the_ID(), '_leave_type', true );
                                            $leave_type_label = $leave_types_map[$type_key] ?? $type_key;
                                            ?>
                                            <li>
                                                <a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>">
                                                    <?php echo esc_html( $employee_name ? $employee_name : get_the_title() ); ?>
                                                </a> - <?php echo esc_html( $leave_type_label ); ?>
                                                (<?php echo esc_html( $start_date ); ?> to <?php echo esc_html( $end_date ); ?>)
                                            </li>
                                        <?php
                                        endwhile;
                                        echo '</ul>';
                                        wp_reset_postdata();
                                    else :
                                        echo '<p>' . esc_html__( 'No pending leave requests at this time.', 'emmansys' ) . '</p>';
                                    endif;
                                    ?>
                                    <p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&meta_key=_leave_status&meta_value=pending' ) ); ?>" class="button button-primary">
                                        <?php esc_html_e( 'View All Pending Requests', 'emmansys' ); ?>
                                    </a></p>
                                </div>
                            </div>
                             <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Quick Links', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                    <ul>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request' ) ); ?>"><?php esc_html_e( 'All Leave Requests', 'emmansys' ); ?></a></li>
                                        <li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=leave_request' ) ); ?>"><?php esc_html_e( 'Add New Leave Request', 'emmansys' ); ?></a></li>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=employee' ) ); ?>"><?php esc_html_e( 'Manage Employees', 'emmansys' ); ?></a></li>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types-settings' ) ); ?>"><?php esc_html_e( 'Manage Leave Types', 'emmansys' ); ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-2" class="postbox-container">
                         <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Recently Approved Requests (Last 7 Days)', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                   <?php
                                    $seven_days_ago = date( 'Y-m-d', strtotime( '-7 days' ) );
                                    $approved_args = array(
                                        'post_type'      => 'leave_request',
                                        'posts_per_page' => 5,
                                        'post_status'    => 'publish',
                                        'meta_query'     => array(
                                            'relation' => 'AND',
                                            array(
                                                'key'     => '_leave_status',
                                                'value'   => 'approved',
                                                'compare' => '=',
                                            ),
                                            // We would need to store approval date to filter by it accurately.
                                            // For now, let's show most recent approved ones by post modified date.
                                        ),
                                        'orderby' => 'modified', // or 'date' if approval date isn't stored
                                        'order' => 'DESC',
                                    );
                                    $approved_requests_query = new WP_Query( $approved_args );

                                    if ( $approved_requests_query->have_posts() ) :
                                        echo '<ul>';
                                        while ( $approved_requests_query->have_posts() ) : $approved_requests_query->the_post();
                                            $employee_name = get_post_meta( get_the_ID(), '_leave_employee_name', true );
                                            $start_date = get_post_meta( get_the_ID(), '_leave_start_date', true );
                                            ?>
                                            <li>
                                                <a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>">
                                                    <?php echo esc_html( $employee_name ? $employee_name : get_the_title() ); ?>
                                                </a> (<?php echo esc_html( $start_date ); ?>) - <?php printf(esc_html__('Approved on: %s', 'emmansys'), esc_html(get_the_modified_date())); ?>
                                            </li>
                                        <?php
                                        endwhile;
                                        echo '</ul>';
                                        wp_reset_postdata();
                                    else :
                                        echo '<p>' . esc_html__( 'No leave requests approved recently.', 'emmansys' ) . '</p>';
                                    endif;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


} // End class Employee_Management_System

/**
 * Begins execution of the plugin.
 */
function run_employee_management_system() {
    return Employee_Management_System::instance();
}
run_employee_management_system();
