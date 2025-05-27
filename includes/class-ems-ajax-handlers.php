<?php
/**
 * EmManSys AJAX Handlers
 *
 * @package EmManSys
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class EMS_AJAX_Handlers {

    /**
     * Leave Request CPT Handler instance.
     * @var EMS_Leave_Request_CPT
     */
    private $leave_request_cpt;

    /**
     * Constructor.
     * @param EMS_Leave_Request_CPT $leave_request_cpt Instance of leave request CPT handler.
     */
    public function __construct( EMS_Leave_Request_CPT $leave_request_cpt ) {
        $this->leave_request_cpt = $leave_request_cpt;
    }

    /**
     * AJAX handler for user profile/dashboard leave submission.
     */
    public function ajax_handle_profile_leave_submission() {
        // This method is identical to the one in version 1.1.20, just moved here.
        // It uses $this->leave_request_cpt->has_overlapping_active_leave() and $this->leave_request_cpt->generate_next_leave_request_id()
        check_ajax_referer('ems_ajax_profile_leave_nonce', 'security'); if ( !current_user_can('submit_profile_leave_request') ) { wp_send_json_error(array('message' => __( 'You do not have permission to submit leave requests.', 'emmansys' ) ) ); } if ( ! isset( $_POST['ems_user_id'], $_POST['ems_employee_cpt_id_profile'] ) || get_current_user_id() != $_POST['ems_user_id'] ) { wp_send_json_error(array('message' => __( 'Security check failed or user mismatch.', 'emmansys' ) ) ); } $user_id = absint( $_POST['ems_user_id'] ); $employee_cpt_id = absint( $_POST['ems_employee_cpt_id_profile'] ); $user_info = get_userdata( $user_id ); $employee_info = get_post( $employee_cpt_id ); if ( ! $user_info || ! $employee_info || $employee_info->post_type !== 'employee' ) { wp_send_json_error(array('message' => __( 'Invalid user or employee record for leave request.', 'emmansys' ) ) ); } $linked_user_on_employee_cpt = get_post_meta($employee_cpt_id, '_employee_user_id', true); if (absint($linked_user_on_employee_cpt) !== $user_id) { wp_send_json_error(array('message' => __( 'Employee record mismatch.', 'emmansys' ) ) ); } $leave_type_key = isset( $_POST['ems_profile_leave_type'] ) ? sanitize_key( $_POST['ems_profile_leave_type'] ) : ''; $start_date_val = isset( $_POST['ems_profile_start_date'] ) ? sanitize_text_field( $_POST['ems_profile_start_date'] ) : ''; $end_date_val = isset( $_POST['ems_profile_end_date'] ) ? sanitize_text_field( $_POST['ems_profile_end_date'] ) : ''; $leave_duration_val = isset( $_POST['ems_profile_leave_duration'] ) ? sanitize_key( $_POST['ems_profile_leave_duration'] ) : 'whole_day'; $leave_reason = isset( $_POST['ems_profile_leave_reason_field'] ) ? sanitize_textarea_field( $_POST['ems_profile_leave_reason_field'] ) : ''; $today_val = current_time('Y-m-d'); if ( empty( $leave_type_key ) || empty( $start_date_val ) || empty( $end_date_val ) || empty( $leave_reason ) || empty($leave_duration_val) ) { wp_send_json_error(array('message' => __( 'All fields are required for leave submission.', 'emmansys' ) ) ); } if ( $start_date_val < $today_val ) { wp_send_json_error(array('message' => __( 'Error: Start date cannot be in the past.', 'emmansys' ) ) ); } if ( $end_date_val < $start_date_val ) { wp_send_json_error(array('message' => __( 'Error: End date cannot be earlier than the start date.', 'emmansys' ) ) ); } $conflicting_leave_id = $this->leave_request_cpt->has_overlapping_active_leave($employee_cpt_id, $start_date_val, $end_date_val); if ($conflicting_leave_id) { wp_send_json_error(array('message' => sprintf(__( 'Error: Your leave request from %s to %s overlaps with an existing active leave request. Please cancel the existing one or choose different dates.', 'emmansys' ), $start_date_val, $end_date_val) ) ); } $post_title = $this->leave_request_cpt->generate_next_leave_request_id(); if (!$post_title) { $post_title = __('Leave Request', 'emmansys') . ' ' . time(); error_log("EmManSys: Failed to generate auto-increment ID for leave request during AJAX submission."); } $leave_request_data = array( 'post_title' => $post_title, 'post_status' => 'publish', 'post_type' => 'leave_request', 'post_author' => $user_id, ); $new_leave_request_id = wp_insert_post( $leave_request_data, true ); if ( is_wp_error( $new_leave_request_id ) ) { wp_send_json_error(array('message' => __( 'Failed to submit leave request: ', 'emmansys' ) . $new_leave_request_id->get_error_message() ) ); } else { update_post_meta( $new_leave_request_id, '_leave_employee_cpt_id', $employee_cpt_id ); update_post_meta( $new_leave_request_id, '_leave_user_id', $user_id ); update_post_meta( $new_leave_request_id, '_leave_employee_name', sanitize_text_field($employee_info->post_title) ); update_post_meta( $new_leave_request_id, '_leave_type', $leave_type_key ); update_post_meta( $new_leave_request_id, '_leave_start_date', $start_date_val ); update_post_meta( $new_leave_request_id, '_leave_end_date', $end_date_val ); update_post_meta( $new_leave_request_id, '_leave_duration', $leave_duration_val ); update_post_meta( $new_leave_request_id, '_leave_reason', $leave_reason ); update_post_meta( $new_leave_request_id, '_leave_status', 'pending' ); wp_send_json_success(array('message' => __( 'Leave request submitted successfully. It is now pending approval.', 'emmansys' ) ) ); } wp_die();
    }

    /**
     * AJAX handler for changing leave status from the list table.
     */
    public function ajax_ems_change_leave_status() {
        // This method is identical to the one in version 1.1.20, just moved here.
        // It uses $this->leave_request_cpt->update_leave_balance_on_status_change()
        // and EMS_Leave_Options statically.
        check_ajax_referer( 'ems_change_leave_status_nonce', 'nonce' ); if ( ! current_user_can( 'approve_leave_requests' ) ) { wp_send_json_error( array( 'message' => __( 'You do not have permission to change leave statuses.', 'emmansys' ) ) ); } $leave_request_id = isset( $_POST['leave_id'] ) ? intval( $_POST['leave_id'] ) : 0; $new_status = isset( $_POST['new_status'] ) ? sanitize_key( $_POST['new_status'] ) : ''; if ( ! $leave_request_id || empty( $new_status ) ) { wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'emmansys' ) ) ); } $valid_statuses = array_keys( EMS_Leave_Options::get_leave_statuses() ); if ( ! in_array( $new_status, $valid_statuses ) ) { wp_send_json_error( array( 'message' => __( 'Invalid status selected.', 'emmansys' ) ) ); } $old_status = get_post_meta( $leave_request_id, '_leave_status', true ); if ( !current_user_can('approve_leave_requests') ) { $current_employee_id = get_post_meta($leave_request_id, '_leave_employee_cpt_id', true); $user_employee_id = null; $current_wp_user_id = get_current_user_id(); $employee_query_args = array( 'post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array( array('key' => '_employee_user_id', 'value' => $current_wp_user_id, 'compare' => '=')), 'fields' => 'ids' ); $linked_employee_posts = get_posts($employee_query_args); if (!empty($linked_employee_posts)) { $user_employee_id = $linked_employee_posts[0]; } if ($current_employee_id != $user_employee_id || ($new_status !== 'cancelled' || $old_status !== 'pending')) { wp_send_json_error( array( 'message' => __( 'You are not authorized to make this status change.', 'emmansys' ) ) ); } } $updated = update_post_meta( $leave_request_id, '_leave_status', $new_status ); if ( $updated ) { $this->leave_request_cpt->update_leave_balance_on_status_change( $leave_request_id, $old_status, $new_status ); $statuses_map = EMS_Leave_Options::get_leave_statuses(); wp_send_json_success( array( 'message' => __( 'Leave status updated successfully.', 'emmansys' ), 'new_status_label' => $statuses_map[$new_status] ?? $new_status ) ); } else { $meta_check = get_post_meta( $leave_request_id, '_leave_status', true ); if ($meta_check === $new_status && $old_status === $new_status) { wp_send_json_success( array( 'message' => __( 'Leave status is already set to the selected value.', 'emmansys' ), 'new_status_label' => EMS_Leave_Options::get_leave_statuses()[$new_status] ?? $new_status ) ); } else { wp_send_json_error( array( 'message' => __( 'Failed to update leave status.', 'emmansys' ) ) ); } }
    }
}
