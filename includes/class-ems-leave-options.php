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
     * Retrieves the array of all available leave types (default and custom).
     * Each leave type is an array with 'label' and 'initial_balance'.
     *
     * @since 1.2.0
     * @return array Associative array of leave type keys and their data (label, initial_balance).
     */
    public static function get_leave_types() {
        $default_leave_types = array(
            'vacation'    => array(
                'label' => __( 'Vacation', 'emmansys' ),
                'initial_balance' => 15 
            ),
            'sick'        => array(
                'label' => __( 'Sick Leave', 'emmansys' ),
                'initial_balance' => 10 
            ),
            'personal'    => array(
                'label' => __( 'Personal Leave', 'emmansys' ),
                'initial_balance' => 5 
            ),
            'unpaid'      => array(
                'label' => __( 'Unpaid Leave', 'emmansys' ),
                'initial_balance' => 0 
            ),
            'other'       => array(
                'label' => __( 'Other', 'emmansys' ),
                'initial_balance' => 0
            ),
        );
        
        $custom_leave_types_option = self::get_custom_leave_types(); // Use helper to get raw custom types

        if ( empty($custom_leave_types_option) ) {
            return $default_leave_types;
        }

        $processed_custom_types = array();
        if (is_array($custom_leave_types_option)) {
            foreach ($custom_leave_types_option as $key => $type_data) {
                // Ensure the structure is correct, primarily for data integrity
                if (is_array($type_data) && isset($type_data['label'])) {
                    $processed_custom_types[$key] = array(
                        'label' => $type_data['label'], // Assume label is stored appropriately (e.g., already translated or translatable string)
                        'initial_balance' => isset($type_data['initial_balance']) ? (int) $type_data['initial_balance'] : 0
                    );
                }
                // Silently skip malformed custom types if necessary, or add error handling
            }
        }
        
        return array_merge($default_leave_types, $processed_custom_types);
    }

    /**
     * Retrieves only the custom leave types from WordPress options.
     *
     * @since 1.2.1
     * @return array Associative array of custom leave type keys and their data.
     */
    public static function get_custom_leave_types() {
        $custom_types = get_option( self::LEAVE_TYPES_OPTION_KEY, array() );
        return is_array($custom_types) ? $custom_types : array();
    }

    /**
     * Saves the entire array of custom leave types.
     *
     * @since 1.2.1
     * @param array $custom_types The array of custom leave types to save.
     * @return bool True if the option was successfully updated, false otherwise.
     */
    private static function save_all_custom_leave_types(array $custom_types) {
        return update_option( self::LEAVE_TYPES_OPTION_KEY, $custom_types );
    }

    /**
     * Adds or updates a custom leave type.
     *
     * @since 1.2.1
     * @param string $key The unique key for the leave type (e.g., 'study_leave').
     * @param string $label The display label for the leave type.
     * @param int    $initial_balance The initial balance for this leave type.
     * @return bool True on success, false on failure.
     */
    public static function add_or_update_custom_leave_type($key, $label, $initial_balance) {
        if (empty(trim($key)) || empty(trim($label))) {
            return false; // Key and Label are mandatory
        }
        $sanitized_key = sanitize_key($key);
        if ($key !== $sanitized_key) {
             // Optionally, you might want to prevent keys that change upon sanitization
             // or inform the user. For simplicity, we'll use the sanitized one.
        }

        $custom_types = self::get_custom_leave_types();
        $custom_types[$sanitized_key] = array(
            'label' => sanitize_text_field($label),
            'initial_balance' => intval($initial_balance)
        );
        return self::save_all_custom_leave_types($custom_types);
    }

    /**
     * Deletes a custom leave type.
     *
     * @since 1.2.1
     * @param string $key The key of the leave type to delete.
     * @return bool True on success, false if key not found or on failure.
     */
    public static function delete_custom_leave_type($key) {
        $sanitized_key = sanitize_key($key);
        $custom_types = self::get_custom_leave_types();
        if (isset($custom_types[$sanitized_key])) {
            unset($custom_types[$sanitized_key]);
            return self::save_all_custom_leave_types($custom_types);
        }
        return false; // Key not found
    }


    /**
     * Retrieves the array of available leave statuses.
     *
     * @since 1.2.0
     * @return array Associative array of leave status keys and their translated labels.
     */
    public static function get_leave_statuses() {
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
        return array(
            'whole_day'   => __('Whole Day', 'emmansys'),
            'half_day_am' => __('Half Day - AM', 'emmansys'),
            'half_day_pm' => __('Half Day - PM', 'emmansys'),
        );
    }
}
