<?php
/**
 * EmManSys Leave Request CPT Handler
 *
 * @package EmManSys
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class EMS_Leave_Request_CPT {

    /**
     * Constructor.
     */
    public function __construct() {
        // Filter row actions on the leave request list table
        add_filter( 'post_row_actions', array( $this, 'filter_leave_request_row_actions' ), 10, 2 );
    }

    /**
     * Register Leave Request Custom Post Type.
     */
    public function register_leave_request_cpt() {
        $labels = array(
            'name' => _x( 'Leave Requests', 'Post type general name', 'emmansys' ),
            'singular_name' => _x( 'Leave Request', 'Post type singular name', 'emmansys' ),
            'menu_name' => _x( 'Leave Requests', 'Admin Menu text', 'emmansys' ),
            'name_admin_bar' => _x( 'Leave Request', 'Add New on Toolbar', 'emmansys' ),
            'add_new' => '', // Intentionally blank
            'new_item' => __( 'New Leave Request', 'emmansys' ),
            'edit_item' => __( 'Edit Leave Request', 'emmansys' ), // This label is still used for the edit screen itself
            'view_item' => __( 'View Leave Request', 'emmansys' ),
            'all_items' => __( 'All Leave Requests', 'emmansys' ),
            'search_items' => __( 'Search Leave Requests', 'emmansys' ),
            'not_found' => __( 'No leave requests found.', 'emmansys' ),
            'not_found_in_trash' => __( 'No leave requests found in Trash.', 'emmansys' ),
            'archives' => _x( 'Leave Request Archives', 'The post type archive label used in nav menus.', 'emmansys' ),
            'insert_into_item' => _x( 'Insert into leave request', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post).', 'emmansys' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this leave request', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'emmansys' ),
            'filter_items_list' => _x( 'Filter leave requests list', 'Screen reader text for the filter links heading on the post type listing screen.', 'emmansys' ),
            'items_list_navigation' => _x( 'Leave requests list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'emmansys' ),
            'items_list' => _x( 'Leave requests list', 'Screen reader text for the items list heading on the post type listing screen.', 'emmansys' ),
        );
        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array( 'slug' => 'leave-request' ),
            'capability_type' => 'leave_request',
            'capabilities' => array(
                'edit_post'          => 'edit_leave_request',
                'read_post'          => 'read_leave_request',
                'delete_post'        => 'delete_leave_request',
                'edit_posts'         => 'edit_leave_requests',
                'edit_others_posts'  => 'edit_others_leave_requests',
                'publish_posts'      => 'publish_leave_requests',
                'read_private_posts' => 'read_private_leave_requests',
            ),
            'map_meta_cap' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 21,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => array( 'custom-fields' ), 
        );
        register_post_type( 'leave_request', $args );
    }

    /**
     * Filters the array of row action links on the Leave Request list table.
     * Removes "Edit" and "Quick Edit" for approved or rejected leave requests.
     *
     * @param array   $actions An array of action links for each post.
     * @param WP_Post $post    The current post object.
     * @return array Filtered array of action links.
     */
    public function filter_leave_request_row_actions( $actions, $post ) {
        if ( $post->post_type === 'leave_request' ) {
            $status = get_post_meta( $post->ID, '_leave_status', true );
            if ( in_array( $status, array( 'approved', 'rejected', 'cancelled' ) ) ) {
                unset( $actions['edit'] );
                unset( $actions['inline hide-if-no-js'] ); // Key for "Quick Edit"
            }
        }
        return $actions;
    }


    /**
     * Add Leave Request meta boxes.
     */
    public function add_leave_request_meta_boxes() {
        // ... (content remains the same as in version 1.2.3) ...
        global $typenow; if ( $typenow === 'leave_request' ) { $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0); if ( ($post_id && current_user_can('edit_leave_request', $post_id)) || current_user_can('publish_leave_requests') ) { add_meta_box('leave_request_details_meta_box', __( 'Leave Request Details', 'emmansys' ), array( $this, 'render_leave_request_details_meta_box' ), 'leave_request', 'normal', 'high'); } }
    }

    /**
     * Render Leave Request Details meta box.
     * @param WP_Post $post The post object.
     */
    public function render_leave_request_details_meta_box( $post ) {
        // ... (content remains the same as in version 1.2.3) ...
        wp_nonce_field( 'ems_save_leave_request_details', 'ems_leave_request_details_nonce' ); $selected_employee_cpt_id = get_post_meta( $post->ID, '_leave_employee_cpt_id', true ); $leave_type_key = get_post_meta( $post->ID, '_leave_type', true ); $start_date = get_post_meta( $post->ID, '_leave_start_date', true ); $end_date   = get_post_meta( $post->ID, '_leave_end_date', true ); $leave_duration = get_post_meta( $post->ID, '_leave_duration', true ); $leave_reason = get_post_meta( $post->ID, '_leave_reason', true ); $leave_status = get_post_meta( $post->ID, '_leave_status', true ); $admin_notes = get_post_meta( $post->ID, '_leave_admin_notes', true ); $all_leave_types = EMS_Leave_Options::get_leave_types(); $statuses = EMS_Leave_Options::get_leave_statuses(); $durations = EMS_Leave_Options::get_leave_durations(); $current_user_can_manage_others = current_user_can('edit_others_leave_requests'); $today = current_time('Y-m-d'); $min_date_attr = (current_user_can('approve_leave_requests')) ? '' : 'min="' . esc_attr($today) . '"'; $display_title = $post->post_title; if (strpos(strtolower($display_title), 'auto draft') !== false || empty($display_title) || strpos($display_title, 'LR') !== 0) { $display_title = __('(Title will be auto-generated: LR#######)', 'emmansys'); } ?> <p><em><?php printf(__( 'Leave Request Title: %s', 'emmansys'), esc_html($display_title)); ?></em></p> <table class="form-table"><tbody><tr><th><label for="ems_leave_employee_cpt_id"><?php _e( 'Employee', 'emmansys' ); ?></label></th><td> <?php if ( $current_user_can_manage_others ) : $employee_posts_args = array('post_type' => 'employee', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'); $employee_posts = get_posts( $employee_posts_args ); ?> <select id="ems_leave_employee_cpt_id" name="ems_leave_employee_cpt_id" required><option value=""><?php _e( '-- Select Employee --', 'emmansys' ); ?></option><?php foreach ( $employee_posts as $employee_post ) : ?><option value="<?php echo esc_attr( $employee_post->ID ); ?>" <?php selected( $selected_employee_cpt_id, $employee_post->ID ); ?> data-user-id="<?php echo esc_attr(get_post_meta($employee_post->ID, '_employee_user_id', true)); ?>"><?php echo esc_html( $employee_post->post_title ); ?><?php $linked_wp_user_id_option = get_post_meta($employee_post->ID, '_employee_user_id', true); if ($linked_wp_user_id_option) { echo ' (WP User ID: ' . esc_html($linked_wp_user_id_option) . ')'; } ?></option><?php endforeach; ?></select><p class="description"><?php _e( 'Select the employee filing this leave.', 'emmansys' ); ?></p> <?php else: $current_wp_user_id = get_current_user_id(); $linked_employee_cpt_id_for_user = null; $employee_name_for_user = __('N/A - Your user is not linked to an Employee Record.', 'emmansys'); $args_user_employee = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_employee_user_id', 'value' => $current_wp_user_id, 'compare' => '='))); $current_user_employee_records = get_posts($args_user_employee); if (!empty($current_user_employee_records)) { $linked_employee_cpt_id_for_user = $current_user_employee_records[0]->ID; $employee_name_for_user = $current_user_employee_records[0]->post_title; if ($post->ID && $selected_employee_cpt_id && $selected_employee_cpt_id != $linked_employee_cpt_id_for_user && $selected_employee_cpt_id != 0) { echo '<p class="notice notice-warning">' . __('Warning: This leave request is for a different employee. You can only manage your own.', 'emmansys') . '</p>'; $original_employee_post = get_post($selected_employee_cpt_id); if ($original_employee_post) { echo '<strong>' . esc_html($original_employee_post->post_title) . '</strong>'; echo '<input type="hidden" name="ems_leave_employee_cpt_id" value="' . esc_attr($selected_employee_cpt_id) . '" />'; } else { echo '<strong>' . __('Unknown Employee', 'emmansys') . '</strong>'; } } else { echo '<strong>' . esc_html($employee_name_for_user) . '</strong>'; echo '<input type="hidden" name="ems_leave_employee_cpt_id" value="' . esc_attr($linked_employee_cpt_id_for_user) . '" />'; } } else { echo '<strong>' . esc_html($employee_name_for_user) . '</strong>'; } ?><p class="description"><?php _e( 'Leave request will be filed for your linked employee record.', 'emmansys' ); ?></p><?php endif; ?> </td></tr><tr><th><label for="ems_leave_type"><?php _e( 'Leave Type', 'emmansys' ); ?></label></th><td><select id="ems_leave_type" name="ems_leave_type" required><option value=""><?php _e( '-- Select Type --', 'emmansys' ); ?></option><?php foreach ( $all_leave_types as $key => $type_data ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_type_key, $key ); ?>><?php echo esc_html( $type_data['label'] ); ?></option><?php endforeach; ?></select></td></tr> <tr><th><label for="ems_leave_start_date"><?php _e( 'Start Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_leave_start_date" name="ems_leave_start_date" value="<?php echo esc_attr( $start_date ); ?>" class="regular-text ems-leave-start-date" <?php echo $min_date_attr; ?> required/></td></tr> <tr><th><label for="ems_leave_end_date"><?php _e( 'End Date', 'emmansys' ); ?></label></th><td><input type="date" id="ems_leave_end_date" name="ems_leave_end_date" value="<?php echo esc_attr( $end_date ); ?>" class="regular-text ems-leave-end-date" <?php echo $min_date_attr; ?> required/></td></tr> <tr><th><label for="ems_leave_duration"><?php _e( 'Leave Duration', 'emmansys' ); ?></label></th><td><select id="ems_leave_duration" name="ems_leave_duration" required><?php foreach ( $durations as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_duration, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr> <tr><th><label for="ems_leave_reason_field"><?php _e( 'Reason for Leave', 'emmansys' ); ?></label></th><td><textarea id="ems_leave_reason_field" name="ems_leave_reason_field" rows="5" class="large-text" required><?php echo esc_textarea( $leave_reason ); ?></textarea></td></tr><tr><th><label for="ems_leave_status"><?php _e( 'Leave Status', 'emmansys' ); ?></label></th><td><select id="ems_leave_status" name="ems_leave_status" <?php if (!current_user_can('approve_leave_requests')) echo 'disabled'; ?>><option value=""><?php _e( '-- Select Status --', 'emmansys' ); ?></option><?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $leave_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr><tr><th><label for="ems_leave_admin_notes"><?php _e( 'Admin Notes', 'emmansys' ); ?></label></th><td><textarea id="ems_leave_admin_notes" name="ems_leave_admin_notes" rows="3" class="large-text" <?php if (!current_user_can('approve_leave_requests')) echo 'disabled'; ?>><?php echo esc_textarea( $admin_notes ); ?></textarea><p class="description"><?php _e( 'Notes for admin/manager regarding this leave request.', 'emmansys' ); ?></p></td></tr></tbody></table> <?php
    }
    
    /**
     * Save Leave Request meta data.
     * @param int $post_id The post ID.
     * @param WP_Post $post The post object.
     * @param bool $update Whether this is an existing post being updated or not.
     */
    public function save_leave_request_meta_data( $post_id, $post, $update ) {
        // ... (content remains the same as in version 1.2.3) ...
        if ( ! isset( $_POST['ems_leave_request_details_nonce'] ) || ! wp_verify_nonce( $_POST['ems_leave_request_details_nonce'], 'ems_save_leave_request_details' ) ) return; $is_autosave = (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE); if ( $is_autosave && !$update ) { return; } if ( $post->post_type !== 'leave_request' ) return; if ( !current_user_can( 'edit_leave_request', $post_id ) ) { return; } $old_status = get_post_meta($post_id, '_leave_status', true); $start_date_val = isset( $_POST['ems_leave_start_date'] ) ? sanitize_text_field( $_POST['ems_leave_start_date'] ) : ''; $end_date_val   = isset( $_POST['ems_leave_end_date'] ) ? sanitize_text_field( $_POST['ems_leave_end_date'] ) : ''; $employee_cpt_id_from_form = isset( $_POST['ems_leave_employee_cpt_id'] ) ? absint( $_POST['ems_leave_employee_cpt_id'] ) : null; $today_val = current_time('Y-m-d'); if ( !current_user_can('approve_leave_requests') && !empty($start_date_val) && $start_date_val < $today_val && !$update ) { wp_die( __('Error: Start date cannot be in the past for new requests.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>'); } if ( !empty($start_date_val) && !empty($end_date_val) && $end_date_val < $start_date_val ) { wp_die( __('Error: End date cannot be earlier than the start date.', 'emmansys') . ' <a href="javascript:history.back()">' . __('Go Back', 'emmansys') . '</a>'); } $force_status_to_draft = false; $redirect_to_edit_after_save = false; $submitted_status = isset($_POST['ems_leave_status']) ? sanitize_key($_POST['ems_leave_status']) : ($update ? $old_status : 'pending'); if ($employee_cpt_id_from_form && $start_date_val && $end_date_val && !$is_autosave) { $exclude_id_for_overlap_check = $update ? $post_id : 0; $conflicting_leave_id = $this->has_overlapping_active_leave($employee_cpt_id_from_form, $start_date_val, $end_date_val, $exclude_id_for_overlap_check); if ($conflicting_leave_id) { $conflicting_post_title = get_the_title($conflicting_leave_id); $conflicting_status_key = get_post_meta($conflicting_leave_id, '_leave_status', true); $statuses_map = EMS_Leave_Options::get_leave_statuses(); $conflicting_status_label = $statuses_map[$conflicting_status_key] ?? esc_html($conflicting_status_key); $error_message = sprintf( __('Error: This leave request (Dates: %s to %s) overlaps with an existing active leave request: "%s" (ID: %s, Status: %s, Dates: %s - %s). The current leave request has been saved as Draft. Please cancel or modify the conflicting request, or change the dates/details of this request.', 'emmansys'), esc_html($start_date_val), esc_html($end_date_val), esc_html($conflicting_post_title), $conflicting_leave_id, esc_html($conflicting_status_label), esc_html(get_post_meta($conflicting_leave_id, '_leave_start_date', true)), esc_html(get_post_meta($conflicting_leave_id, '_leave_end_date', true)) ); add_settings_error('ems_leave_notice', 'overlap_error_admin_forced_draft', $error_message, 'error'); set_transient('settings_errors', get_settings_errors('ems_leave_notice'), 30); if (in_array($submitted_status, array('pending', 'approved'))) { $force_status_to_draft = true; } $redirect_to_edit_after_save = true; } } if ( isset( $_POST['ems_leave_employee_cpt_id'] ) ) { $employee_cpt_id_for_meta = absint( $_POST['ems_leave_employee_cpt_id'] ); if ( $employee_cpt_id_for_meta > 0 ) { update_post_meta( $post_id, '_leave_employee_cpt_id', $employee_cpt_id_for_meta ); $employee_post = get_post( $employee_cpt_id_for_meta ); if ( $employee_post ) { update_post_meta( $post_id, '_leave_employee_name', sanitize_text_field( $employee_post->post_title ) ); $linked_wp_user_id = get_post_meta( $employee_cpt_id_for_meta, '_employee_user_id', true ); if ( $linked_wp_user_id ) { update_post_meta( $post_id, '_leave_user_id', absint( $linked_wp_user_id ) ); } else { delete_post_meta( $post_id, '_leave_user_id'); } } } else { delete_post_meta( $post_id, '_leave_employee_cpt_id'); delete_post_meta( $post_id, '_leave_employee_name'); if ( $post->post_author && !current_user_can('edit_others_leave_requests')) { update_post_meta( $post_id, '_leave_user_id', $post->post_author ); $author_data = get_userdata($post->post_author); if ($author_data) { $args_author_employee = array('post_type' => 'employee', 'posts_per_page' => 1, 'meta_query' => array(array('key' => '_employee_user_id', 'value' => $post->post_author, 'compare' => '='))); $author_employee_records = get_posts($args_author_employee); if(!empty($author_employee_records)){ update_post_meta( $post_id, '_leave_employee_name', sanitize_text_field($author_employee_records[0]->post_title)); update_post_meta( $post_id, '_leave_employee_cpt_id', $author_employee_records[0]->ID); } else { update_post_meta( $post_id, '_leave_employee_name', $author_data->display_name); } } } else { delete_post_meta( $post_id, '_leave_user_id'); } } } $other_fields_to_save = array( '_leave_type' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_type'), '_leave_start_date' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_leave_start_date'), '_leave_end_date' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_leave_end_date'), '_leave_duration' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_duration'), '_leave_reason' => array('sanitize_callback' => 'sanitize_textarea_field', 'field_name' => 'ems_leave_reason_field'), '_leave_status' => array('sanitize_callback' => 'sanitize_key', 'field_name' => 'ems_leave_status'), '_leave_admin_notes' => array('sanitize_callback' => 'sanitize_textarea_field', 'field_name' => 'ems_leave_admin_notes'),); if ($force_status_to_draft) { $_POST['ems_leave_status'] = 'draft';} if (!current_user_can('approve_leave_requests')) { unset($other_fields_to_save['_leave_status']); unset($other_fields_to_save['_leave_admin_notes']); } foreach ( $other_fields_to_save as $meta_key => $field_config ) { if ( isset( $_POST[ $field_config['field_name'] ] ) ) { $value_to_save = $_POST[ $field_config['field_name'] ]; $sanitized_value = call_user_func( $field_config['sanitize_callback'], $value_to_save ); update_post_meta( $post_id, $meta_key, $sanitized_value ); } } if (!current_user_can('approve_leave_requests') && $old_status === 'pending' && isset($_POST['ems_leave_status']) && sanitize_key($_POST['ems_leave_status']) === 'cancelled' && !$force_status_to_draft) { update_post_meta($post_id, '_leave_status', 'cancelled'); } $current_post_for_title = get_post($post_id); if ( $current_post_for_title && ( strtolower($current_post_for_title->post_title) === 'auto draft' || empty($current_post_for_title->post_title) || strpos($current_post_for_title->post_title, 'LR') !== 0 ) && !$is_autosave ) { $is_title_already_lr_format = strpos($current_post_for_title->post_title, 'LR') === 0; if (!$is_title_already_lr_format) { $new_lr_id_title = $this->generate_next_leave_request_id(); if ($new_lr_id_title) { remove_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 20); wp_update_post(array( 'ID' => $post_id, 'post_title' => $new_lr_id_title, 'post_name'  => sanitize_title($new_lr_id_title) )); add_action('save_post_leave_request', array($this, 'save_leave_request_meta_data'), 20, 3); } } } $final_new_status = get_post_meta($post_id, '_leave_status', true); $this->update_leave_balance_on_status_change($post_id, $old_status, $final_new_status); if ($redirect_to_edit_after_save && !$is_autosave) { wp_safe_redirect(add_query_arg('message', '101', get_edit_post_link($post_id, 'url'))); exit; }
    }

    /**
     * Set custom columns for Leave Request CPT.
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function set_leave_request_columns( $columns ) {
        // ... (content remains the same as in version 1.2.3) ...
        unset($columns['title']); $new_columns = array(); $new_columns['cb'] = $columns['cb']; $new_columns['ems_leave_title_col'] = __( 'Leave Request', 'emmansys' ); $new_columns['ems_leave_employee'] = __( 'Employee', 'emmansys' ); $new_columns['ems_leave_user'] = __( 'WP User', 'emmansys' ); $new_columns['ems_leave_type'] = __( 'Leave Type', 'emmansys' ); $new_columns['ems_leave_dates'] = __( 'Dates', 'emmansys' ); $new_columns['ems_leave_duration_col'] = __( 'Duration', 'emmansys' ); $new_columns['ems_leave_status'] = __( 'Status', 'emmansys' ); $new_columns['ems_leave_actions'] = __( 'Actions', 'emmansys' ); $new_columns['date'] = $columns['date']; return $new_columns;
    }

    /**
     * Render custom columns for Leave Request CPT.
     * @param string $column Column name.
     * @param int $post_id Post ID.
     */
    public function render_leave_request_columns( $column, $post_id ) {
        // ... (content remains the same as in version 1.2.3) ...
        $all_leave_types_map = EMS_Leave_Options::get_leave_types(); $statuses_map = EMS_Leave_Options::get_leave_statuses(); $durations_map = EMS_Leave_Options::get_leave_durations(); switch ( $column ) { case 'ems_leave_title_col': echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>'; break; case 'ems_leave_employee': $employee_cpt_id = get_post_meta( $post_id, '_leave_employee_cpt_id', true ); $employee_name = get_post_meta( $post_id, '_leave_employee_name', true ); if ($employee_cpt_id && $employee_post = get_post($employee_cpt_id)) { echo '<a href="' . esc_url(get_edit_post_link($employee_cpt_id)) . '"><strong>' . esc_html($employee_post->post_title) . '</strong></a>'; } elseif ($employee_name) { echo '<strong>' . esc_html($employee_name) . '</strong>'; } else { echo '—'; } break; case 'ems_leave_user': $user_id = get_post_meta( $post_id, '_leave_user_id', true ); if ($user_id && $user = get_userdata($user_id)) { echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . '</a>'; } else { echo '—'; } break; case 'ems_leave_type': $type_key = get_post_meta( $post_id, '_leave_type', true ); echo esc_html( $all_leave_types_map[$type_key]['label'] ?? $type_key ); break; case 'ems_leave_dates': $start = get_post_meta( $post_id, '_leave_start_date', true ); $end = get_post_meta( $post_id, '_leave_end_date', true ); echo esc_html($start) . ' - ' . esc_html($end); break; case 'ems_leave_duration_col': $duration_key = get_post_meta( $post_id, '_leave_duration', true ); echo esc_html( $durations_map[$duration_key] ?? $duration_key ); break; case 'ems_leave_status': $status_key = get_post_meta( $post_id, '_leave_status', true ); echo '<strong id="ems-status-text-' . esc_attr($post_id) . '">' . esc_html( $statuses_map[$status_key] ?? $status_key ) . '</strong>'; break; case 'ems_leave_actions': if ( current_user_can( 'approve_leave_requests' ) ) { $current_status = get_post_meta( $post_id, '_leave_status', true ); echo '<div class="ems-leave-status-changer" data-leave-id="' . esc_attr($post_id) . '">'; echo '<select name="ems_new_status" class="ems-new-status-select">'; foreach ( $statuses_map as $key => $label ) { if ($key === 'cancelled' && $current_status !== 'pending') { if ($current_status !== 'cancelled') continue; } printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $current_status, $key, false ), esc_html( $label ) ); } echo '</select>'; echo '<button type="button" class="button button-secondary ems-update-status-button" style="margin-left:5px;">' . esc_html__( 'Update', 'emmansys' ) . '</button>'; echo '<span class="spinner" style="float:none; vertical-align: middle;"></span>'; echo '</div>'; } else { echo '—'; } break; }
    }

    /**
     * Make custom columns sortable for Leave Request CPT.
     * @param array $columns Existing sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_leave_request_columns_sortable( $columns ) {
        // ... (content remains the same as in version 1.2.3) ...
        $columns['ems_leave_title_col'] = 'title'; $columns['ems_leave_employee'] = 'ems_leave_employee_sort'; $columns['ems_leave_user'] = 'ems_leave_user_sort'; $columns['ems_leave_type'] = 'ems_leave_type_sort'; $columns['ems_leave_status'] = 'ems_leave_status_sort'; return $columns;
    }

    /**
     * Handle sorting for custom columns in Leave Request CPT.
     * @param WP_Query $query The WP_Query instance.
     */
    public function sort_leave_request_columns_query( $query ) {
        // ... (content remains the same as in version 1.2.3) ...
        if ( ! is_admin() || ! $query->is_main_query() ) return; $orderby = $query->get( 'orderby' ); $post_type = $query->get('post_type'); if ($post_type === 'leave_request') { if ( 'ems_leave_employee_sort' === $orderby ) { $query->set( 'meta_key', '_leave_employee_name' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_leave_user_sort' === $orderby ) { $query->set( 'meta_key', '_leave_user_id' ); $query->set( 'orderby', 'meta_value_num' ); } elseif ( 'ems_leave_type_sort' === $orderby ) { $query->set( 'meta_key', '_leave_type' ); $query->set( 'orderby', 'meta_value' ); } elseif ( 'ems_leave_status_sort' === $orderby ) { $query->set( 'meta_key', '_leave_status' ); $query->set( 'orderby', 'meta_value' ); } }
    }

    /**
     * Generates the next auto-incrementing ID for leave requests.
     * @return string The formatted leave request ID.
     */
    public function generate_next_leave_request_id() {
        // ... (content remains the same as in version 1.2.3) ...
        $counter = get_option( 'ems_last_leave_request_id_counter', 0 ); $counter++; $new_id_str = 'LR' . str_pad( (string)$counter, 8, '0', STR_PAD_LEFT ); update_option( 'ems_last_leave_request_id_counter', $counter ); return $new_id_str;
    }

    /**
     * Calculates the number of days for a leave request.
     * @param string $start_date_str Start date (Y-m-d).
     * @param string $end_date_str End date (Y-m-d).
     * @param string $duration_key Duration key ('whole_day', 'half_day_am', 'half_day_pm').
     * @return float Number of days.
     */
    private function calculate_leave_days($start_date_str, $end_date_str, $duration_key) {
        // ... (content remains the same as in version 1.2.3) ...
        if (empty($start_date_str) || empty($end_date_str) || empty($duration_key)) { return 0; } if ($duration_key === 'half_day_am' || $duration_key === 'half_day_pm') { return 0.5; } elseif ($duration_key === 'whole_day') { try { $start_date = new DateTime($start_date_str); $end_date   = new DateTime($end_date_str); if ($start_date > $end_date) { return 0; } $end_date->modify('+1 day'); $interval = $start_date->diff($end_date); return (float) $interval->days; } catch (Exception $e) { error_log("EmManSys: Error calculating leave days - " . $e->getMessage()); return 0; } } return 0;
    }

    /**
     * Checks if an employee has an existing active (pending or approved) leave request
     * that overlaps with the given date range.
     * @param int $employee_cpt_id Employee CPT ID.
     * @param string $new_start_date_str New start date (Y-m-d).
     * @param string $new_end_date_str New end date (Y-m-d).
     * @param int $exclude_post_id Post ID to exclude from the check (e.g., when updating a request).
     * @return int|false Conflicting post ID if overlap found, false otherwise.
     */
    public function has_overlapping_active_leave($employee_cpt_id, $new_start_date_str, $new_end_date_str, $exclude_post_id = 0) {
        // ... (content remains the same as in version 1.2.3) ...
        if (empty($employee_cpt_id) || empty($new_start_date_str) || empty($new_end_date_str)) { return false; } $args = array( 'post_type' => 'leave_request', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_leave_employee_cpt_id', 'value'   => $employee_cpt_id, 'compare' => '=', ), array( 'key' => '_leave_status', 'value'   => array('pending', 'approved'), 'compare' => 'IN', ), ), ); if ($exclude_post_id > 0) { $args['post__not_in'] = array(intval($exclude_post_id)); } $existing_requests = get_posts($args); if (empty($existing_requests)) { return false; } try { $new_start_dt = new DateTime($new_start_date_str); $new_end_dt   = new DateTime($new_end_date_str); } catch (Exception $e) { error_log("EmManSys: Invalid date format for new leave request in overlap check - " . $e->getMessage()); return false; } foreach ($existing_requests as $request_post) { $existing_start_str = get_post_meta($request_post->ID, '_leave_start_date', true); $existing_end_str   = get_post_meta($request_post->ID, '_leave_end_date', true); if (empty($existing_start_str) || empty($existing_end_str)) { continue; } try { $existing_start_dt = new DateTime($existing_start_str); $existing_end_dt   = new DateTime($existing_end_str); } catch (Exception $e) { error_log("EmManSys: Invalid date format for existing leave (ID: {$request_post->ID}) in overlap check - " . $e->getMessage()); continue; } if (($new_start_dt <= $existing_end_dt) && ($new_end_dt >= $existing_start_dt)) { return $request_post->ID; } } return false;
    }

    /**
     * Handles the logic for updating an employee's leave balance when a leave request status changes.
     * @param int $leave_request_id The ID of the leave request post.
     * @param string $old_status The previous status of the leave request.
     * @param string $new_status The new status of the leave request.
     * @return bool True if balance was updated, false otherwise.
     */
    public function update_leave_balance_on_status_change($leave_request_id, $old_status, $new_status) {
        // ... (content remains the same as in version 1.2.3) ...
        $leave_type_key   = get_post_meta($leave_request_id, '_leave_type', true); $leave_start_date = get_post_meta($leave_request_id, '_leave_start_date', true); $leave_end_date   = get_post_meta($leave_request_id, '_leave_end_date', true); $leave_duration_key = get_post_meta($leave_request_id, '_leave_duration', true); $employee_id      = get_post_meta($leave_request_id, '_leave_employee_cpt_id', true); if (!$employee_id || !$leave_type_key || !$leave_start_date || !$leave_end_date || !$leave_duration_key) { return false; } $days_for_this_request = $this->calculate_leave_days($leave_start_date, $leave_end_date, $leave_duration_key); $balance_meta_key = '_leave_balance_' . $leave_type_key; $all_leave_type_definitions = EMS_Leave_Options::get_leave_types(); $current_leave_type_definition = $all_leave_type_definitions[$leave_type_key] ?? null; $tracks_balance = $current_leave_type_definition && ( (isset($current_leave_type_definition['initial_balance']) && $current_leave_type_definition['initial_balance'] > 0) || $leave_type_key !== 'unpaid'); if (!$tracks_balance) { return false; } $current_employee_balance_raw = get_post_meta($employee_id, $balance_meta_key, true); $current_employee_balance = ($current_employee_balance_raw !== '') ? (float) $current_employee_balance_raw : (isset($current_leave_type_definition['initial_balance']) ? (float) $current_leave_type_definition['initial_balance'] : 0); $previously_deducted_days = (float) get_post_meta($leave_request_id, '_ems_deducted_leave_days', true); if ($new_status === 'approved' && $old_status !== 'approved') { if ($days_for_this_request > 0) { $new_balance = $current_employee_balance - $days_for_this_request; update_post_meta($employee_id, $balance_meta_key, $new_balance); update_post_meta($leave_request_id, '_ems_deducted_leave_days', $days_for_this_request); return true; } } elseif ($old_status === 'approved' && $new_status !== 'approved') { if ($previously_deducted_days > 0) { $new_balance = $current_employee_balance + $previously_deducted_days; update_post_meta($employee_id, $balance_meta_key, $new_balance); delete_post_meta($leave_request_id, '_ems_deducted_leave_days'); return true; } } elseif ($new_status === 'approved' && $old_status === 'approved') { $new_deduction_amount = $days_for_this_request; if ($new_deduction_amount != $previously_deducted_days) { $adjustment = $previously_deducted_days - $new_deduction_amount; $new_balance = $current_employee_balance + $adjustment; update_post_meta($employee_id, $balance_meta_key, $new_balance); update_post_meta($leave_request_id, '_ems_deducted_leave_days', $new_deduction_amount); return true; } } return false;
    }
}
