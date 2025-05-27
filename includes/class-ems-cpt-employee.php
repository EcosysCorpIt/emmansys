<?php
/**
 * EmManSys Employee CPT Handler
 *
 * @package EmManSys
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class EMS_Employee_CPT {

    /**
     * Constructor.
     */
    public function __construct() {
        // Actions and filters specific to Employee CPT can be initialized here if needed,
        // but most are added in the main plugin's init_hooks.
    }

    /**
     * Register Employee Custom Post Type.
     */
    public function register_employee_cpt() {
        $labels = array(
            'name' => _x( 'Employees', 'Post type general name', 'emmansys' ),
            'singular_name' => _x( 'Employee', 'Post type singular name', 'emmansys' ),
            'menu_name' => _x( 'Employees', 'Admin Menu text', 'emmansys' ),
            'name_admin_bar' => _x( 'Employee', 'Add New on Toolbar', 'emmansys' ),
            'add_new' => __( 'Add New Employee', 'emmansys' ),
            'add_new_item' => __( 'Add New Employee', 'emmansys' ),
            'new_item' => __( 'New Employee', 'emmansys' ),
            'edit_item' => __( 'Edit Employee', 'emmansys' ),
            'view_item' => __( 'View Employee', 'emmansys' ),
            'all_items' => __( 'All Employees', 'emmansys' ),
            'search_items' => __( 'Search Employees', 'emmansys' ),
            'parent_item_colon' => __( 'Parent Employees:', 'emmansys' ),
            'not_found' => __( 'No employees found.', 'emmansys' ),
            'not_found_in_trash' => __( 'No employees found in Trash.', 'emmansys' ),
            'featured_image' => _x( 'Employee Photo', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'emmansys' ),
            'set_featured_image' => _x( 'Set employee photo', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'emmansys' ),
            'remove_featured_image' => _x( 'Remove employee photo', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'emmansys' ),
            'use_featured_image' => _x( 'Use as employee photo', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'emmansys' ),
            'archives' => _x( 'Employee archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'emmansys' ),
            'insert_into_item' => _x( 'Insert into employee', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'emmansys' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this employee', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'emmansys' ),
            'filter_items_list' => _x( 'Filter employees list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'emmansys' ),
            'items_list_navigation' => _x( 'Employees list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'emmansys' ),
            'items_list' => _x( 'Employees list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'emmansys' ),
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array( 'slug' => 'employee' ),
            'capability_type' => 'employee',
            'capabilities' => array(
                'edit_post' => 'edit_employee',
                'read_post' => 'read_employee',
                'delete_post' => 'delete_employee',
                'edit_posts' => 'edit_employees',
                'edit_others_posts' => 'edit_others_employees',
                'publish_posts' => 'publish_employees',
                'read_private_posts' => 'read_private_employees',
                'delete_posts' => 'delete_employees',
                'delete_private_posts' => 'delete_private_employees',
                'delete_published_posts' => 'delete_published_employees',
                'delete_others_posts' => 'delete_others_employees',
                'edit_private_posts' => 'edit_private_employees',
                'edit_published_posts' => 'edit_published_employees',
            ),
            'map_meta_cap' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-groups',
            'supports' => array( 'thumbnail', 'custom-fields' ), // No 'title' or 'editor'
        );
        register_post_type( 'employee', $args );
    }

    /**
     * Add Employee meta boxes.
     */
    public function add_employee_meta_boxes() {
        global $typenow;
        if ( $typenow === 'employee' ) {
            $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0);
            if ( ($post_id && current_user_can('edit_employee', $post_id)) || current_user_can('publish_employees') ) {
                add_meta_box(
                    'employee_details_meta_box',
                    __( 'Employee Details', 'emmansys' ),
                    array( $this, 'render_employee_details_meta_box' ),
                    'employee',
                    'normal',
                    'high'
                );
            }
        }
    }

    /**
     * Render Employee Details meta box.
     * @param WP_Post $post The post object.
     */
    public function render_employee_details_meta_box( $post ) {
        wp_nonce_field( 'ems_save_employee_details', 'ems_employee_details_nonce' );
        $employee_full_name = get_post_meta( $post->ID, '_employee_full_name', true );
        if (empty($employee_full_name) && $post->post_title !== 'Auto Draft' && $post->post_title !== 'auto-draft') {
            $employee_full_name = $post->post_title;
        }
        $linked_user_id = get_post_meta( $post->ID, '_employee_user_id', true );
        $employee_id_meta = get_post_meta( $post->ID, '_employee_id', true );
        $department  = get_post_meta( $post->ID, '_employee_department', true );
        $position = get_post_meta( $post->ID, '_employee_position', true );
        $email = get_post_meta( $post->ID, '_employee_email', true );
        $phone = get_post_meta( $post->ID, '_employee_phone', true );
        $salary = get_post_meta( $post->ID, '_employee_salary', true );
        $users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );
        ?>
        <table class="form-table"><tbody>
            <tr>
                <th><label for="ems_employee_full_name"><?php _e( 'Employee Full Name', 'emmansys' ); ?></label></th>
                <td>
                    <input type="text" id="ems_employee_full_name" name="ems_employee_full_name" value="<?php echo esc_attr( $employee_full_name ); ?>" class="regular-text" required />
                    <p class="description"><?php _e( 'This name will be used as the employee record identifier.', 'emmansys' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ems_employee_user_id"><?php _e( 'Linked WordPress User', 'emmansys' ); ?></label></th>
                <td>
                    <select id="ems_employee_user_id" name="ems_employee_user_id">
                        <option value=""><?php _e( '-- Select a User --', 'emmansys' ); ?></option>
                        <?php foreach ( $users as $user ) : ?>
                            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $linked_user_id, $user->ID ); ?>>
                                <?php echo esc_html( $user->display_name ); ?> (ID: <?php echo esc_html( $user->ID ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e( 'Tag this employee record to a WordPress user account. This is required for leave filing from profile.', 'emmansys' ); ?></p>
                </td>
            </tr>
            <tr><th><label for="ems_employee_id_field"><?php _e( 'Employee ID', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_id_field" name="ems_employee_id_field" value="<?php echo esc_attr( $employee_id_meta ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="ems_employee_department"><?php _e( 'Department', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_department" name="ems_employee_department" value="<?php echo esc_attr( $department ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="ems_employee_position"><?php _e( 'Position', 'emmansys' ); ?></label></th><td><input type="text" id="ems_employee_position" name="ems_employee_position" value="<?php echo esc_attr( $position ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="ems_employee_email"><?php _e( 'Email Address', 'emmansys' ); ?></label></th><td><input type="email" id="ems_employee_email" name="ems_employee_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="ems_employee_phone"><?php _e( 'Phone Number', 'emmansys' ); ?></label></th><td><input type="tel" id="ems_employee_phone" name="ems_employee_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" /></td></tr>
            <tr><th><label for="ems_employee_salary"><?php _e( 'Salary', 'emmansys' ); ?></label></th><td><input type="number" id="ems_employee_salary" name="ems_employee_salary" value="<?php echo esc_attr( $salary ); ?>" class="regular-text" step="0.01" /></td></tr>
        </tbody></table>
        <?php
    }

    /**
     * Save Employee meta data.
     * @param int $post_id The post ID.
     * @param WP_Post $post The post object.
     */
    public function save_employee_meta_data( $post_id, $post ) {
        if ( ! isset( $_POST['ems_employee_details_nonce'] ) || ! wp_verify_nonce( $_POST['ems_employee_details_nonce'], 'ems_save_employee_details' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( $post->post_type !== 'employee' ) return;
        if ( !current_user_can( 'edit_employee', $post_id ) ) { return; }

        $fields_to_save = array(
            '_employee_full_name'  => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_full_name'),
            '_employee_user_id' => array('sanitize_callback' => 'absint', 'field_name' => 'ems_employee_user_id'),
            '_employee_id' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_id_field'),
            '_employee_department' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_department'),
            '_employee_position' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_position'),
            '_employee_email' => array('sanitize_callback' => 'sanitize_email', 'field_name' => 'ems_employee_email'),
            '_employee_phone' => array('sanitize_callback' => 'sanitize_text_field', 'field_name' => 'ems_employee_phone'),
            '_employee_salary' => array('sanitize_callback' => 'floatval', 'field_name' => 'ems_employee_salary'),
        );

        foreach ( $fields_to_save as $meta_key => $field_config ) {
            if ( isset( $_POST[ $field_config['field_name'] ] ) ) {
                $value = call_user_func( $field_config['sanitize_callback'], $_POST[ $field_config['field_name'] ] );
                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Update post title from full name field
        if (isset($_POST['ems_employee_full_name']) && !empty(trim($_POST['ems_employee_full_name']))) {
            $full_name = sanitize_text_field($_POST['ems_employee_full_name']);
            if (strtolower($post->post_title) === 'auto draft' || $post->post_title !== $full_name) {
                // Temporarily remove this action to prevent infinite loop
                remove_action('save_post_employee', array($this, 'save_employee_meta_data'), 10);
                wp_update_post(array(
                    'ID'         => $post_id,
                    'post_title' => $full_name,
                    'post_name'  => sanitize_title($full_name) // Update slug as well
                ));
                // Re-add the action
                add_action('save_post_employee', array($this, 'save_employee_meta_data'), 10, 2);
            }
        }
    }

    /**
     * Set custom columns for Employee CPT.
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function set_employee_columns( $columns ) {
        unset($columns['title']); // Remove default title column as we use Full Name
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['ems_employee_name_col'] = __( 'Employee Full Name', 'emmansys' );
        $new_columns['ems_linked_user'] = __( 'Linked WP User', 'emmansys' );
        $new_columns['ems_employee_id'] = __( 'Employee ID', 'emmansys' );
        $new_columns['ems_department'] = __( 'Department', 'emmansys' );
        $new_columns['ems_position'] = __( 'Position', 'emmansys' );
        $new_columns['ems_email'] = __( 'Email', 'emmansys' );
        $new_columns['date'] = $columns['date']; // Keep date column
        return $new_columns;
    }

    /**
     * Render custom columns for Employee CPT.
     * @param string $column Column name.
     * @param int $post_id Post ID.
     */
    public function render_employee_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'ems_employee_name_col':
                echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
                break;
            case 'ems_linked_user':
                $user_id = get_post_meta( $post_id, '_employee_user_id', true );
                if ($user_id && $user = get_userdata($user_id)) {
                    echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '">' . esc_html($user->display_name) . ' (ID: ' . esc_html($user_id) . ')</a>';
                } else {
                    echo '—';
                }
                break;
            case 'ems_employee_id':
                echo esc_html( get_post_meta( $post_id, '_employee_id', true ) );
                break;
            case 'ems_department':
                echo esc_html( get_post_meta( $post_id, '_employee_department', true ) );
                break;
            case 'ems_position':
                echo esc_html( get_post_meta( $post_id, '_employee_position', true ) );
                break;
            case 'ems_email':
                echo esc_html( get_post_meta( $post_id, '_employee_email', true ) );
                break;
        }
    }

    /**
     * Make custom columns sortable for Employee CPT.
     * @param array $columns Existing sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_employee_columns_sortable( $columns ) {
        $columns['ems_employee_name_col'] = 'title'; // Sort by post_title
        $columns['ems_linked_user'] = 'ems_linked_user_sort';
        $columns['ems_employee_id'] = 'ems_employee_id_sort';
        $columns['ems_department'] = 'ems_department_sort';
        $columns['ems_position'] = 'ems_position_sort';
        return $columns;
    }

    /**
     * Handle sorting for custom columns in Employee CPT.
     * @param WP_Query $query The WP_Query instance.
     */
    public function sort_employee_columns_query( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;

        $orderby = $query->get( 'orderby' );
        $post_type = $query->get('post_type');

        if ($post_type === 'employee') {
            if ( 'ems_linked_user_sort' === $orderby ) {
                $query->set( 'meta_key', '_employee_user_id' );
                $query->set( 'orderby', 'meta_value_num' );
            } elseif ( 'ems_employee_id_sort' === $orderby ) {
                $query->set( 'meta_key', '_employee_id' );
                $query->set( 'orderby', 'meta_value' );
            } elseif ( 'ems_department_sort' === $orderby ) {
                $query->set( 'meta_key', '_employee_department' );
                $query->set( 'orderby', 'meta_value' );
            } elseif ( 'ems_position_sort' === $orderby ) {
                $query->set( 'meta_key', '_employee_position' );
                $query->set( 'orderby', 'meta_value' );
            }
        }
    }

    /**
     * Render [list_employees] shortcode.
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the shortcode.
     */
    public function render_employee_list_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'count' => 10,
            'department' => ''
        ), $atts, 'list_employees' );

        $args = array(
            'post_type' => 'employee',
            'posts_per_page' => intval( $atts['count'] ),
            'post_status' => 'publish',
            'orderby' => 'title', // Sort by full name (post_title)
            'order' => 'ASC'
        );

        if ( ! empty( $atts['department'] ) ) {
            $args['meta_query'] = array(
                array(
                    'key' => '_employee_department',
                    'value' => sanitize_text_field( $atts['department'] ),
                    'compare' => '='
                )
            );
        }

        $employees_query = new WP_Query( $args );
        $output = '';

        if ( $employees_query->have_posts() ) {
            $output .= '<ul class="employee-list">';
            while ( $employees_query->have_posts() ) {
                $employees_query->the_post();
                $post_id = get_the_ID();
                $name = get_the_title(); // This is the Employee Full Name
                $position = get_post_meta( $post_id, '_employee_position', true );
                $department_meta = get_post_meta( $post_id, '_employee_department', true );
                $email = get_post_meta( $post_id, '_employee_email', true );
                $permalink = get_permalink(); // Link to single employee view if public

                $output .= '<li>';
                if ( has_post_thumbnail() ) {
                    $output .= '<div class="employee-photo">' . get_the_post_thumbnail( $post_id, 'thumbnail' ) . '</div>';
                }
                $output .= '<div class="employee-info">';
                $output .= '<strong><a href="' . esc_url($permalink) . '">' . esc_html( $name ) . '</a></strong><br />';
                if ( $position ) $output .= esc_html( $position ) . '<br />';
                if ( $department_meta ) $output .= '<em>' . esc_html( $department_meta ) . '</em><br />';
                if ( $email ) $output .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a><br />';
                $output .= '</div></li>';
            }
            $output .= '</ul>';
            wp_reset_postdata();
        } else {
            $output = '<p>' . __( 'No employees found.', 'emmansys' ) . '</p>';
        }
        // Basic styling for the shortcode output
        $output .= '<style>.employee-list { list-style: none; padding: 0; } .employee-list li { display: flex; align-items: flex-start; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; } .employee-list .employee-photo img { width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 50%; } .employee-list .employee-info { flex-grow: 1; } .employee-list .employee-info strong { font-size: 1.2em; }</style>';
        return $output;
    }
}
