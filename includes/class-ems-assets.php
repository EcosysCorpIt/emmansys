<?php
/**
 * EmManSys Assets Handler
 *
 * @package EmManSys
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class EMS_Assets {

    /**
     * Main plugin instance.
     * @var Employee_Management_System
     */
    private $plugin;

    /**
     * Constructor.
     * @param Employee_Management_System $plugin Main plugin instance.
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Enqueue admin scripts and styles.
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        global $pagenow;
        $plugin_url = EMS_PLUGIN_URL;
        $plugin_version = EMS_VERSION;

        // Validation script for Leave Request CPT edit screen and custom "Add New Leave" page
        if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix || $hook_suffix === $this->plugin->add_new_leave_page_hook_suffix ) {
            $screen = get_current_screen();
            if ( ($screen && 'leave_request' === $screen->post_type) || $hook_suffix === $this->plugin->add_new_leave_page_hook_suffix ) {
                wp_enqueue_script( 'ems-admin-leave-validation', $plugin_url . 'js/admin-leave-validation.js', array( 'jquery' ), $plugin_version, true );
                wp_localize_script('ems-admin-leave-validation', 'ems_leave_admin_data', array( 
                    'today' => current_time('Y-m-d'),
                    'can_select_past_dates' => current_user_can('approve_leave_requests') 
                ));
            }
        } 

        // Validation script for User Profile page and Employee Dashboard
        if ( 'profile.php' === $hook_suffix || 'user-edit.php' === $hook_suffix || $hook_suffix === $this->plugin->employee_dashboard_page_hook_suffix ) { // Corrected property name
            wp_enqueue_script( 'ems-profile-leave-validation', $plugin_url . 'js/profile-leave-validation.js', array( 'jquery' ), $plugin_version, true );
            wp_localize_script('ems-profile-leave-validation', 'ems_profile_leave_data', array( 
                'today' => current_time('Y-m-d'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ems_ajax_profile_leave_nonce'), 
                'action'   => 'ems_submit_profile_leave', 
                'success_message' => __('Leave request submitted successfully. It is now pending approval.', 'emmansys'),
                'error_message_general' => __('An error occurred. Please try again.', 'emmansys'),
                'error_all_fields_required' => __('All fields are required for leave submission.', 'emmansys'),
                'error_start_date_past' => __('Error: Start date cannot be in the past.', 'emmansys'),
                'error_end_date_invalid' => __('Error: End date cannot be earlier than the start date.', 'emmansys'),
                'form_id_profile' => '#ems-profile-leave-form', 
                'form_id_dashboard' => '#ems-dashboard-leave-form' 
            ));
        }

        // General admin styles for specific plugin pages
        if ($hook_suffix === $this->plugin->leave_types_page_hook_suffix || $hook_suffix === $this->plugin->add_new_leave_page_hook_suffix) {
            wp_enqueue_style( 'wp-admin' ); 
        }

        // Script for actions on the Leave Request list table
        if ( 'edit.php' === $pagenow && isset($_GET['post_type']) && $_GET['post_type'] === 'leave_request' ) {
            wp_enqueue_script(
                'ems-admin-leave-list-actions',
                $plugin_url . 'js/admin-leave-list-actions.js',
                array( 'jquery' ),
                $plugin_version,
                true
            );
            wp_localize_script(
                'ems-admin-leave-list-actions',
                'ems_leave_list_data',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'ems_change_leave_status_nonce' ),
                    'confirm_change' => __('Are you sure you want to change the status of this leave request?', 'emmansys'),
                    'error_generic' => __('An error occurred. Please try again.', 'emmansys'),
                )
            );
        }

        // Enqueue FullCalendar for Manager Dashboard
        if ( $hook_suffix === $this->plugin->manager_dashboard_page_hook_suffix ) {
            wp_enqueue_style( 'fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', array(), '5.11.3' );
            wp_enqueue_script( 'fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array(), '5.11.3', true );
            // Example: If you need specific plugins like dayGrid
            // wp_enqueue_script( 'fullcalendar-daygrid', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/daygrid/main.global.min.js', array('fullcalendar-js'), '5.11.3', true );
        }
    }
}
