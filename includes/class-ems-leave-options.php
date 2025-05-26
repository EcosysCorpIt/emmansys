<?php
/**
 * EmManSys Leave Options Module
 *
 * @package EmManSys
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * A class to manage the options for leave requests, such as types, statuses, and durations.
 *
 * @since 1.2.0
 */
class EMS_Leave_Options {

    /**
     * Option key for storing custom leave types.
     * @var string
     */
    const LEAVE_TYPES_OPTION_KEY = 'ems_custom_leave_types';

    /**
     * Retrieves the array of available leave types from WordPress options.
     * If the option is not set, it returns a default set of leave types.
     *
     * @since 1.2.0
     * @return array Associative array of leave type keys and their translated labels.
     */
    public static function get_leave_types() {
        $default_leave_types = array(
            'vacation'    => __( 'Vacation', 'emmansys' ),
            'sick'        => __( 'Sick Leave', 'emmansys' ),
            'personal'    => __( 'Personal Leave', 'emmansys' ),
            'unpaid'      => __( 'Unpaid Leave', 'emmansys' ),
            'other'       => __( 'Other', 'emmansys' ),
        );
        // Get custom leave types from options, or use defaults if not set or empty
        $leave_types = get_option( self::LEAVE_TYPES_OPTION_KEY );

        if ( empty($leave_types) ) {
            return $default_leave_types;
        }
        // Ensure the returned array has labels translated if they were stored without translation context
        // For simplicity, we assume labels are stored as translated strings or will be translated upon display.
        // If keys were meant to be translated upon retrieval, more complex logic would be needed here.
        return $leave_types;
    }

    /**
     * Retrieves the array of available leave statuses.
     *
     * @since 1.2.0
     * @return array Associative array of leave status keys and their translated labels.
     */
    public static function get_leave_statuses() {
        // These could also be made manageable via an admin interface in the future
        return array(
            'pending'   => __( 'Pending', 'emmansys' ),
            'approved'  => __( 'Approved', 'emmansys' ),
            'rejected'  => __( 'Rejected', 'emmansys' ),
            'cancelled' => __( 'Cancelled by Employee', 'emmansys' ),
        );
    }

    /**
     * Retrieves the array of available leave durations.
     *
     * @since 1.2.0
     * @return array Associative array of leave duration keys and their translated labels.
     */
    public static function get_leave_durations() {
        // These could also be made manageable via an admin interface in the future
        return array(
            'whole_day'   => __('Whole Day', 'emmansys'),
            'half_day_am' => __('Half Day - AM', 'emmansys'),
            'half_day_pm' => __('Half Day - PM', 'emmansys'),
        );
    }
}
