<?php
/**
 * EmManSys Admin Menus Handler
 *
 * @package EmManSys
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class EMS_Admin_Menus {

    /**
     * Main plugin instance.
     * @var Employee_Management_System
     */
    private $plugin;

    /**
     * User Profile handler instance.
     * @var EMS_User_Profile
     */
    private $user_profile_handler;

    /**
     * Constructor.
     * @param Employee_Management_System $plugin Main plugin instance.
     * @param EMS_User_Profile $user_profile_handler Instance of user profile handler.
     */
    public function __construct( $plugin, $user_profile_handler ) {
        $this->plugin = $plugin;
        $this->user_profile_handler = $user_profile_handler;
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menus() {
        // Employee Dashboard (for individual employees)
        $this->plugin->employee_dashboard_page_hook_suffix = add_menu_page(
            __( 'My Dashboard', 'emmansys' ),
            __( 'My Dashboard', 'emmansys' ),
            'submit_profile_leave_request', 
            'ems-employee-dashboard',
            array( $this, 'render_employee_dashboard_page' ),
            'dashicons-id-alt',
            30 
        );

        // Manager Dashboard (for admins/managers)
        if ( current_user_can( 'approve_leave_requests' ) ) { 
            $this->plugin->manager_dashboard_page_hook_suffix = add_menu_page(
                __( 'Manager Dashboard', 'emmansys' ),
                __( 'Manager Dashboard', 'emmansys' ),
                'approve_leave_requests',
                'ems-manager-dashboard',
                array( $this, 'render_manager_dashboard_page' ),
                'dashicons-dashboard', 
                25 
            );
        }

        // Submenu items under "Leave Requests" CPT
        $this->plugin->add_new_leave_page_hook_suffix = add_submenu_page(
            'edit.php?post_type=leave_request',
            __( 'Add New Leave Request', 'emmansys' ),
            __( 'Add New Leave', 'emmansys' ),
            'publish_leave_requests',
            'ems-add-new-leave-request',
            array( $this, 'render_add_new_leave_request_page' )
        );

        $this->plugin->leave_types_page_hook_suffix = add_submenu_page(
            'edit.php?post_type=leave_request',
            __( 'Manage Leave Types', 'emmansys' ),
            __( 'Leave Types', 'emmansys' ),
            'manage_options', 
            'ems-leave-types',
            array( $this, 'render_leave_types_admin_page' )
        );
    }

    /**
     * Removes the default "Add New" submenu page for the Leave Request CPT.
     * @since 1.2.1
     */
    public function remove_default_add_new_submenu() {
        remove_submenu_page( 'edit.php?post_type=leave_request', 'post-new.php?post_type=leave_request' );
    }

    /**
     * Render the content for the Employee Dashboard admin page.
     */
    public function render_employee_dashboard_page() {
        if ( !current_user_can('submit_profile_leave_request') ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) ); } $user_for_dashboard = wp_get_current_user(); if ( ! ($user_for_dashboard instanceof WP_User) || ! $user_for_dashboard->ID ) { echo '<div class="wrap"><p>' . esc_html__('Error: Could not retrieve current user information. Please try logging in again.', 'emmansys') . '</p></div>'; return; } ?> <div class="wrap ems-dashboard"> <h1><?php esc_html_e( 'Employee Dashboard', 'emmansys' ); ?></h1> <?php $this->user_profile_handler->show_leave_management_on_profile( $user_for_dashboard, true ); ?> </div> <style> .ems-dashboard .form-table { margin-bottom: 20px; } .ems-dashboard .notice { margin-top: 15px; margin-bottom: 15px; } </style> <?php
    }

    /**
     * Render the content for the Manager Dashboard admin page.
     * @since 1.2.2
     */
    public function render_manager_dashboard_page() {
        if ( ! current_user_can( 'approve_leave_requests' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) );
        }

        // Fetch stats
        $pending_leave_args = array(
            'post_type' => 'leave_request',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_leave_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1, 
            'fields' => 'ids' 
        );
        $pending_leave_query = new WP_Query( $pending_leave_args );
        $pending_leave_count = $pending_leave_query->post_count;

        $total_employees_count = wp_count_posts('employee')->publish;

        // Fetch recent pending leave requests
        $recent_pending_args = array(
            'post_type' => 'leave_request',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_leave_status',
                    'value' => 'pending',
                    'compare' => '='
                )
            ),
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        $recent_pending_requests = get_posts( $recent_pending_args );

        // Fetch approved leave requests for the calendar
        $approved_leave_events = array();
        $approved_leave_args = array(
            'post_type' => 'leave_request',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_leave_status',
                    'value' => 'approved',
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1, // Get all approved
        );
        $approved_requests = get_posts( $approved_leave_args );

        foreach ( $approved_requests as $request ) {
            $employee_name = get_post_meta( $request->ID, '_leave_employee_name', true );
            $start_date = get_post_meta( $request->ID, '_leave_start_date', true );
            $end_date = get_post_meta( $request->ID, '_leave_end_date', true );

            if ( $employee_name && $start_date && $end_date ) {
                // FullCalendar's end date is exclusive. Add one day.
                $end_date_for_calendar = date('Y-m-d', strtotime($end_date . ' +1 day'));
                $approved_leave_events[] = array(
                    'title' => esc_js( $employee_name . ' (On Leave)' ),
                    'start' => esc_js( $start_date ),
                    'end'   => esc_js( $end_date_for_calendar ),
                    'allDay' => true // Assuming all leaves are full day events for simplicity on calendar
                );
            }
        }
        wp_reset_postdata();

        ?>
        <div class="wrap ems-manager-dashboard">
            <h1><?php esc_html_e( 'Manager Dashboard', 'emmansys' ); ?></h1>

            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Overview', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                    <p><strong><?php esc_html_e( 'Pending Leave Requests:', 'emmansys' ); ?></strong> <?php echo esc_html( $pending_leave_count ); ?></p>
                                    <p><strong><?php esc_html_e( 'Total Active Employees:', 'emmansys' ); ?></strong> <?php echo esc_html( $total_employees_count ); ?></p>
                                </div>
                            </div>
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Quick Actions', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                    <ul>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request' ) ); ?>"><?php esc_html_e( 'View All Leave Requests', 'emmansys' ); ?></a></li>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&page=ems-add-new-leave-request' ) ); ?>"><?php esc_html_e( 'Add New Leave Request', 'emmansys' ); ?></a></li>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types' ) ); ?>"><?php esc_html_e( 'Manage Leave Types', 'emmansys' ); ?></a></li>
                                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=employee' ) ); ?>"><?php esc_html_e( 'View All Employees', 'emmansys' ); ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="postbox-container-2" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Recent Pending Leave Requests', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                    <?php if ( ! empty( $recent_pending_requests ) ) : ?>
                                        <table class="wp-list-table widefat fixed striped">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e( 'Employee', 'emmansys' ); ?></th>
                                                    <th><?php esc_html_e( 'Leave Type', 'emmansys' ); ?></th>
                                                    <th><?php esc_html_e( 'Dates', 'emmansys' ); ?></th>
                                                    <th><?php esc_html_e( 'Action', 'emmansys' ); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $all_leave_types_map = EMS_Leave_Options::get_leave_types();
                                                foreach ( $recent_pending_requests as $request ) : 
                                                    $employee_name = get_post_meta( $request->ID, '_leave_employee_name', true );
                                                    $leave_type_key = get_post_meta( $request->ID, '_leave_type', true );
                                                    $leave_type_label = isset($all_leave_types_map[$leave_type_key]['label']) ? $all_leave_types_map[$leave_type_key]['label'] : $leave_type_key;
                                                    $start_date = get_post_meta( $request->ID, '_leave_start_date', true );
                                                    $end_date = get_post_meta( $request->ID, '_leave_end_date', true );
                                                ?>
                                                    <tr>
                                                        <td><?php echo esc_html( $employee_name ); ?></td>
                                                        <td><?php echo esc_html( $leave_type_label ); ?></td>
                                                        <td><?php echo esc_html( $start_date ) . ' - ' . esc_html( $end_date ); ?></td>
                                                        <td><a href="<?php echo esc_url( get_edit_post_link( $request->ID ) ); ?>"><?php esc_html_e( 'View/Edit', 'emmansys' ); ?></a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else : ?>
                                        <p><?php esc_html_e( 'No pending leave requests found.', 'emmansys' ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div id="postbox-container-3" class="postbox-container" style="width: 99%; margin-top: 20px;"> <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e( 'Leave Calendar', 'emmansys' ); ?></span></h2>
                                <div class="inside">
                                    <div id="ems-leave-calendar"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .ems-manager-dashboard .postbox-container { width: 49%; margin-right: 1%; float: left; margin-bottom: 20px; }
            .ems-manager-dashboard #postbox-container-2 { margin-right: 0; }
            .ems-manager-dashboard #postbox-container-3 { width: 98%; float:none; clear:both; } /* Full width for calendar */
            .ems-manager-dashboard .postbox .inside ul { margin-top: 0; }
            .ems-manager-dashboard .postbox .inside ul li { margin-bottom: 0.5em; }
            #ems-leave-calendar { max-width: 100%; margin: 0 auto; }
            @media screen and (max-width: 782px) {
                .ems-manager-dashboard .postbox-container { width: 100%; margin-right: 0; float: none; }
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('ems-leave-calendar');
                if (calendarEl) {
                    var calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,listWeek'
                        },
                        events: <?php echo wp_json_encode( $approved_leave_events ); ?>,
                        eventDidMount: function(info) {
                            // You can add tooltips or other interactions here if needed
                            // Example: info.el.title = info.event.title;
                        }
                    });
                    calendar.render();
                }
            });
        </script>
        <?php
    }


    /**
     * Render the admin page for managing custom leave types.
     */
    public function render_leave_types_admin_page() {
        // ... (content remains the same as in version 1.2.1) ...
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) ); } $editing_key = null; $edit_type_data = array('label' => '', 'initial_balance' => 0); if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['type_key'] ) ) { $current_editing_key_from_url = sanitize_key( $_GET['type_key'] ); $custom_types = EMS_Leave_Options::get_custom_leave_types(); if ( isset( $custom_types[ $current_editing_key_from_url ] ) ) { $retrieved_data = $custom_types[ $current_editing_key_from_url ]; if (is_array($retrieved_data) && isset($retrieved_data['label'])) { $edit_type_data = $retrieved_data; $editing_key = $current_editing_key_from_url; } else { add_settings_error( 'ems_leave_types_notices', 'error_malformed_data', sprintf( __('Data for leave type key "%s" is malformed. It might be old data. Please delete and re-add it, or check the raw option in the database if you are comfortable doing so.', 'emmansys'), esc_html($current_editing_key_from_url) ), 'error' ); } } else { add_settings_error('ems_leave_types_notices', 'error_editing', __('Leave type not found for editing.', 'emmansys'), 'error'); } } ?> <div class="wrap"> <h1><?php esc_html_e( 'Manage Custom Leave Types', 'emmansys' ); ?></h1> <?php settings_errors('ems_leave_types_notices'); ?> <div id="col-container" class="wp-clearfix"> <div id="col-left"> <div class="col-wrap"> <h2><?php echo $editing_key ? esc_html__( 'Edit Leave Type', 'emmansys' ) : esc_html__( 'Add New Leave Type', 'emmansys' ); ?></h2> <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"> <input type="hidden" name="action" value="ems_manage_leave_types"> <?php wp_nonce_field( 'ems_manage_leave_types_nonce', '_wpnonce_ems_leave_types' ); ?> <?php if ( $editing_key ) : ?> <input type="hidden" name="ems_leave_type_action" value="update"> <input type="hidden" name="ems_original_leave_type_key" value="<?php echo esc_attr( $editing_key ); ?>"> <?php else : ?> <input type="hidden" name="ems_leave_type_action" value="add"> <?php endif; ?> <div class="form-field term-name-wrap"> <label for="ems_leave_type_key"><?php esc_html_e( 'Leave Type Key', 'emmansys' ); ?></label> <input name="ems_leave_type_key" id="ems_leave_type_key" type="text" value="<?php echo esc_attr( $editing_key ? $editing_key : '' ); ?>" size="40" <?php echo $editing_key ? 'readonly' : ''; ?>> <p><?php esc_html_e('A unique identifier (e.g., "study_leave", "bereavement_leave"). Cannot be changed after creation. Only lowercase letters, numbers, and underscores.', 'emmansys'); ?></p> </div> <div class="form-field term-slug-wrap"> <label for="ems_leave_type_label"><?php esc_html_e( 'Label', 'emmansys' ); ?></label> <input name="ems_leave_type_label" id="ems_leave_type_label" type="text" value="<?php echo esc_attr( $edit_type_data['label'] ); ?>" size="40"> <p><?php esc_html_e('The name is how it appears on your site.', 'emmansys'); ?></p> </div> <div class="form-field term-description-wrap"> <label for="ems_leave_type_balance"><?php esc_html_e( 'Initial Balance (days/units)', 'emmansys' ); ?></label> <input name="ems_leave_type_balance" id="ems_leave_type_balance" type="number" value="<?php echo esc_attr( $edit_type_data['initial_balance'] ); ?>" min="0" step="0.5" style="width: 100px;"> <p><?php esc_html_e('Default initial balance for this leave type when assigned to new employees (can be overridden per employee).', 'emmansys'); ?></p> </div> <?php if ( $editing_key ) : ?> <?php submit_button( __( 'Update Leave Type', 'emmansys' ) ); ?> <a href="<?php echo esc_url( admin_url('edit.php?post_type=leave_request&page=ems-leave-types') ); ?>" class="button"><?php esc_html_e('Cancel Edit', 'emmansys'); ?></a> <?php else : ?> <?php submit_button( __( 'Add New Leave Type', 'emmansys' ) ); ?> <?php endif; ?> </form> </div> </div> <div id="col-right"> <div class="col-wrap"> <h2><?php esc_html_e( 'Current Custom Leave Types', 'emmansys' ); ?></h2> <table class="wp-list-table widefat fixed striped tags"> <thead> <tr> <th scope="col" id="name" class="manage-column column-name"><?php esc_html_e( 'Label', 'emmansys' ); ?></th> <th scope="col" id="slug" class="manage-column column-slug"><?php esc_html_e( 'Key', 'emmansys' ); ?></th> <th scope="col" id="balance" class="manage-column column-balance" style="width:120px;"><?php esc_html_e( 'Initial Balance', 'emmansys' ); ?></th> </tr> </thead> <tbody id="the-list" data-wp-lists="list:tag"> <?php $custom_types = EMS_Leave_Options::get_custom_leave_types(); if ( empty( $custom_types ) ) : ?> <tr class="no-items"><td class="colspanchange" colspan="3"><?php esc_html_e( 'No custom leave types found.', 'emmansys' ); ?></td></tr> <?php else : foreach ( $custom_types as $key => $data ) : $display_label = (is_array($data) && isset($data['label'])) ? $data['label'] : __('Malformed Data - Please Edit/Delete', 'emmansys'); $display_balance = (is_array($data) && isset($data['initial_balance'])) ? $data['initial_balance'] : 'N/A'; ?> <tr> <td class="name column-name"> <strong><a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'type_key' => $key ), admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types' ) ) ); ?>"><?php echo esc_html( $display_label ); ?></a></strong> <div class="row-actions"> <span class="edit"><a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'type_key' => $key ), admin_url( 'edit.php?post_type=leave_request&page=ems-leave-types' ) ) ); ?>"><?php esc_html_e( 'Edit', 'emmansys' ); ?></a> | </span> <span class="delete"><a class="delete-tag" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ems_manage_leave_types', 'ems_leave_type_action' => 'delete', 'type_key' => $key ), admin_url( 'admin-post.php' ) ), 'ems_delete_leave_type_' . $key, '_wpnonce_ems_delete_leave_type' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this leave type? This action cannot be undone.', 'emmansys' ); ?>');"><?php esc_html_e( 'Delete', 'emmansys' ); ?></a></span> </div> </td> <td class="slug column-slug"><?php echo esc_html( $key ); ?></td> <td class="balance column-balance"><?php echo esc_html( $display_balance ); ?></td> </tr> <?php endforeach; endif; ?> </tbody> <tfoot> <tr> <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Label', 'emmansys' ); ?></th> <th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Key', 'emmansys' ); ?></th> <th scope="col" class="manage-column column-balance"><?php esc_html_e( 'Initial Balance', 'emmansys' ); ?></th> </tr> </tfoot> </table> </div> </div> </div> </div> <?php
    }

    /**
     * Renders the custom "Add New Leave Request" page.
     */
    public function render_add_new_leave_request_page() {
        // ... (content remains the same as in version 1.2.1) ...
        if ( ! current_user_can( 'publish_leave_requests' ) ) { wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'emmansys' ) ); } $can_select_others_employee = current_user_can('edit_others_leave_requests'); $current_wp_user_id = get_current_user_id(); $linked_employee_cpt_id_for_user = null; $employee_name_for_user = ''; $form_submission_blocked_message = ''; if (!$can_select_others_employee) { $args_user_employee = array( 'post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_employee_user_id', 'value' => $current_wp_user_id, 'compare' => '=' ) ), 'fields' => 'ids' ); $current_user_employee_records = get_posts($args_user_employee); if (!empty($current_user_employee_records)) { $linked_employee_cpt_id_for_user = $current_user_employee_records[0]; $employee_post_for_user = get_post($linked_employee_cpt_id_for_user); if ($employee_post_for_user) { $employee_name_for_user = $employee_post_for_user->post_title; } } else { $form_submission_blocked_message = __('You are not linked to an Employee record. Please contact an administrator to link your user account before submitting leave requests.', 'emmansys'); } } ?> <div class="wrap"> <h1><?php esc_html_e( 'Add New Leave Request', 'emmansys' ); ?></h1> <?php $overlap_error = get_transient('ems_admin_leave_overlap_error'); if ( $overlap_error ) { echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $overlap_error ) . '</p></div>'; delete_transient('ems_admin_leave_overlap_error'); } settings_errors('ems_add_leave_notice'); if ( !empty($form_submission_blocked_message) ) { echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $form_submission_blocked_message ) . '</p></div>'; } $submitted_data = get_transient('ems_add_leave_form_data'); if ($submitted_data) { delete_transient('ems_add_leave_form_data'); } else { $submitted_data = array(); } ?> <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="ems-admin-add-new-leave-form"> <input type="hidden" name="action" value="ems_admin_add_new_leave"> <?php wp_nonce_field( 'ems_admin_add_new_leave_nonce', '_wpnonce_ems_admin_add_new_leave' ); ?> <table class="form-table"> <tbody> <tr> <th scope="row"><label for="ems_leave_employee_cpt_id"><?php esc_html_e( 'Employee', 'emmansys' ); ?></label></th> <td> <?php if ($can_select_others_employee) : ?> <?php $employee_posts_args = array('post_type' => 'employee', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'); $employee_posts = get_posts( $employee_posts_args ); $selected_employee_cpt_id = $submitted_data['ems_leave_employee_cpt_id'] ?? ''; ?> <select id="ems_leave_employee_cpt_id" name="ems_leave_employee_cpt_id" required> <option value=""><?php esc_html_e( '-- Select Employee --', 'emmansys' ); ?></option> <?php foreach ( $employee_posts as $employee_post ) : ?> <option value="<?php echo esc_attr( $employee_post->ID ); ?>" <?php selected( $selected_employee_cpt_id, $employee_post->ID ); ?>> <?php echo esc_html( $employee_post->post_title ); ?> </option> <?php endforeach; ?> </select> <p class="description"><?php esc_html_e( 'Select the employee filing this leave.', 'emmansys' ); ?></p> <?php elseif ($linked_employee_cpt_id_for_user) : ?> <input type="hidden" name="ems_leave_employee_cpt_id" value="<?php echo esc_attr($linked_employee_cpt_id_for_user); ?>" /> <strong><?php echo esc_html($employee_name_for_user); ?></strong> <p class="description"><?php esc_html_e( 'Leave will be submitted for your linked employee record.', 'emmansys' ); ?></p> <?php else : ?> <p><em><?php esc_html_e( 'Employee selection is not available.', 'emmansys' ); ?></em></p> <?php endif; ?> </td> </tr> <?php $all_leave_types = EMS_Leave_Options::get_leave_types(); $selected_leave_type = $submitted_data['ems_leave_type'] ?? ''; ?> <tr> <th scope="row"><label for="ems_leave_type"><?php esc_html_e( 'Leave Type', 'emmansys' ); ?></label></th> <td> <select id="ems_leave_type" name="ems_leave_type" required <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>> <option value=""><?php esc_html_e( '-- Select Type --', 'emmansys' ); ?></option> <?php foreach ( $all_leave_types as $key => $type_data ) : ?> <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_leave_type, $key ); ?>> <?php echo esc_html( $type_data['label'] ); ?> </option> <?php endforeach; ?> </select> </td> </tr> <?php $today = current_time('Y-m-d'); $min_date_attr = (current_user_can('approve_leave_requests')) ? '' : 'min="' . esc_attr($today) . '"'; $start_date_val = $submitted_data['ems_leave_start_date'] ?? ''; $end_date_val = $submitted_data['ems_leave_end_date'] ?? ''; ?> <tr> <th scope="row"><label for="ems_leave_start_date"><?php esc_html_e( 'Start Date', 'emmansys' ); ?></label></th> <td><input type="date" id="ems_leave_start_date" name="ems_leave_start_date" value="<?php echo esc_attr($start_date_val); ?>" class="regular-text ems-leave-start-date" <?php echo $min_date_attr; ?> required <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>/></td> </tr> <tr> <th scope="row"><label for="ems_leave_end_date"><?php esc_html_e( 'End Date', 'emmansys' ); ?></label></th> <td><input type="date" id="ems_leave_end_date" name="ems_leave_end_date" value="<?php echo esc_attr($end_date_val); ?>" class="regular-text ems-leave-end-date" <?php echo $min_date_attr; ?> required <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>/></td> </tr> <?php $durations = EMS_Leave_Options::get_leave_durations(); $selected_duration = $submitted_data['ems_leave_duration'] ?? 'whole_day'; ?> <tr> <th scope="row"><label for="ems_leave_duration"><?php esc_html_e( 'Leave Duration', 'emmansys' ); ?></label></th> <td> <select id="ems_leave_duration" name="ems_leave_duration" required <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>> <?php foreach ( $durations as $key => $label ) : ?> <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_duration, $key ); ?>> <?php echo esc_html( $label ); ?> </option> <?php endforeach; ?> </select> </td> </tr> <?php $reason_val = $submitted_data['ems_leave_reason_field'] ?? ''; ?> <tr> <th scope="row"><label for="ems_leave_reason_field"><?php esc_html_e( 'Reason for Leave', 'emmansys' ); ?></label></th> <td><textarea id="ems_leave_reason_field" name="ems_leave_reason_field" rows="5" class="large-text" required <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>><?php echo esc_textarea($reason_val); ?></textarea></td> </tr> <?php $statuses = EMS_Leave_Options::get_leave_statuses(); $selected_status = $submitted_data['ems_leave_status'] ?? 'pending'; ?> <tr> <th scope="row"><label for="ems_leave_status"><?php esc_html_e( 'Leave Status', 'emmansys' ); ?></label></th> <td> <select id="ems_leave_status" name="ems_leave_status" <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>> <?php foreach ( $statuses as $key => $label ) : if ( !current_user_can('approve_leave_requests') && $key === 'approved' && $selected_status !== 'approved') continue; if ( $key === 'rejected' && $selected_status !== 'rejected') continue; if ( $key === 'cancelled' && $selected_status !== 'cancelled') continue; $current_selection = $selected_status; if (empty($current_selection) && !current_user_can('approve_leave_requests')) { $current_selection = 'pending'; } ?> <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_selection, $key ); ?>> <?php echo esc_html( $label ); ?> </option> <?php endforeach; ?> </select> <p class="description"><?php esc_html_e('Default is "Pending". Admins can set to "Approved" directly if needed.', 'emmansys'); ?></p> </td> </tr> <tr> <th scope="row"><label for="ems_leave_admin_notes"><?php esc_html_e( 'Admin Notes (Optional)', 'emmansys' ); ?></label></th> <td><textarea id="ems_leave_admin_notes" name="ems_leave_admin_notes" rows="3" class="large-text" <?php if (!empty($form_submission_blocked_message)) echo 'disabled'; ?>><?php echo esc_textarea($submitted_data['ems_leave_admin_notes'] ?? ''); ?></textarea></td> </tr> </tbody> </table> <?php $submit_button_attributes = array(); if (!empty($form_submission_blocked_message)) { $submit_button_attributes['disabled'] = 'disabled'; } submit_button( __( 'Submit Leave Request', 'emmansys' ), 'primary', 'submit', true, $submit_button_attributes ); ?> </form> </div> <?php
    }

    /**
     * Adds custom CSS to the admin head to hide the 'Add New' button on the Leave Request list table.
     */
    public function hide_leave_request_add_new_button_css() {
        global $pagenow;
        $current_screen = get_current_screen();

        if ( 'edit.php' === $pagenow && isset( $current_screen->post_type ) && 'leave_request' === $current_screen->post_type ) {
            echo '<style>
                .page-title-action {
                    display: none !important;
                }
            </style>';
        }
    }

    /**
     * Display general admin notices stored in transient.
     */
    public function show_general_admin_notices() {
        if ( $errors = get_transient( 'settings_errors' ) ) { 
            settings_errors( 'ems_leave_types_notices', false, true ); 
            settings_errors( 'ems_leave_notice', false, true ); 
            delete_transient( 'settings_errors' ); 
        } 
        if ( $message = get_transient( 'ems_leave_notice_success' ) ) { 
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; 
            delete_transient( 'ems_leave_notice_success' ); 
        } 
        if ( $message = get_transient( 'ems_leave_notice_error' ) ) { 
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; 
            delete_transient( 'ems_leave_notice_error' ); 
        }
    }
}
