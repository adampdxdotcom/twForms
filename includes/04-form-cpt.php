<?php
/**
 * Registers the Custom Post Type for Forms.
 *
 * @package TW_Forms
 * @version 2.9.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == 1. REGISTER THE 'tw_form' CUSTOM POST TYPE
// =============================================================================

if ( ! function_exists( 'tw_forms_register_cpt' ) ) {
    /**
     * Registers the 'tw_form' custom post type.
     */
    function tw_forms_register_cpt() {
        $labels = [
            'name'                  => _x( 'Forms', 'Post Type General Name', 'tw-forms' ),
            'singular_name'         => _x( 'Form', 'Post Type Singular Name', 'tw-forms' ),
            'menu_name'             => __( 'TW Forms', 'tw-forms' ),
            'name_admin_bar'        => __( 'Form', 'tw-forms' ),
            'archives'              => __( 'Form Archives', 'tw-forms' ),
            'attributes'            => __( 'Form Attributes', 'tw-forms' ),
            'parent_item_colon'     => __( 'Parent Form:', 'tw-forms' ),
            'all_items'             => __( 'All Forms', 'tw-forms' ),
            'add_new_item'          => __( 'Add New Form', 'tw-forms' ),
            'add_new'               => __( 'Add New', 'tw-forms' ),
            'new_item'              => __( 'New Form', 'tw-forms' ),
            'edit_item'             => __( 'Edit Form', 'tw-forms' ),
            'update_item'           => __( 'Update Form', 'tw-forms' ),
            'view_item'             => __( 'View Form', 'tw-forms' ),
            'view_items'            => __( 'View Forms', 'tw-forms' ),
            'search_items'          => __( 'Search Form', 'tw-forms' ),
            'not_found'             => __( 'Not found', 'tw-forms' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'tw-forms' ),
            'featured_image'        => __( 'Featured Image', 'tw-forms' ),
            'set_featured_image'    => __( 'Set featured image', 'tw-forms' ),
            'remove_featured_image' => __( 'Remove featured image', 'tw-forms' ),
            'use_featured_image'    => __( 'Use as featured image', 'tw-forms' ),
            'insert_into_item'      => __( 'Insert into form', 'tw-forms' ),
            'uploaded_to_this_item' => __( 'Uploaded to this form', 'tw-forms' ),
            'items_list'            => __( 'Forms list', 'tw-forms' ),
            'items_list_navigation' => __( 'Forms list navigation', 'tw-forms' ),
            'filter_items_list'     => __( 'Filter forms list', 'tw-forms' ),
        ];
        $args = [
            'label'                 => __( 'Form', 'tw-forms' ),
            'description'           => __( 'Custom forms for the website', 'tw-forms' ),
            'labels'                => $labels,
            'supports'              => [ 'title' ],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => false, // We will add this manually to our own menu page.
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-email-alt',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => false,
        ];
        register_post_type( 'tw_form', $args );
    }
    add_action( 'init', 'tw_forms_register_cpt', 0 );
}

// =============================================================================
// == 2. DUPLICATE FORM FUNCTIONALITY
// =============================================================================

if ( ! function_exists( 'tw_forms_add_duplicate_link' ) ) {
    /**
     * Adds a "Duplicate" link to the row actions on the 'tw_form' post type list screen.
     */
    function tw_forms_add_duplicate_link( $actions, $post ) {
        if ( $post->post_type == 'tw_form' && current_user_can('edit_posts') ) {
            $url = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => 'tw_forms_duplicate_form',
                        'post'   => $post->ID,
                    ],
                    'admin.php'
                ),
                'tw_forms_duplicate_form_' . $post->ID
            );
            $actions['duplicate'] = '<a href="' . esc_url( $url ) . '" title="Duplicate this form" rel="permalink">Duplicate</a>';
        }
        return $actions;
    }
    add_filter( 'post_row_actions', 'tw_forms_add_duplicate_link', 10, 2 );
}

if ( ! function_exists( 'tw_forms_duplicate_form' ) ) {
    /**
     * Handles the logic for duplicating a form post and its metadata.
     */
    function tw_forms_duplicate_form() {
        // Security check: ensure this is a duplicate action and the nonce is valid.
        if ( ! ( isset( $_GET['post'] ) && ( isset( $_GET['action'] ) && 'tw_forms_duplicate_form' == $_GET['action'] ) ) ) {
            return;
        }

        $post_id = absint( $_GET['post'] );
        check_admin_referer( 'tw_forms_duplicate_form_' . $post_id );

        // Get the original post.
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_die( 'Form duplication failed, could not find original form with ID: ' . esc_html( $post_id ) );
        }
        
        // Prepare new post data.
        $current_user    = wp_get_current_user();
        $new_post_author = $current_user->ID;

        $args = [
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $new_post_author,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name,
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => 'draft',
            'post_title'     => $post->post_title . ' - Copy',
            'post_type'      => $post->post_type,
            'to_ping'        => $post->to_ping,
            'menu_order'     => $post->menu_order,
        ];

        // Create the new post.
        $new_post_id = wp_insert_post( $args );

        // Get all meta from the original post.
        $post_meta = get_post_meta( $post_id );
        if ( ! empty( $post_meta ) && is_array( $post_meta ) ) {
            foreach ( $post_meta as $meta_key => $meta_values ) {
                // We only need the first value, as our meta keys are not repeated.
                $meta_value = $meta_values[0];
                // Unserialize the data.
                $meta_value_unserialized = maybe_unserialize( $meta_value );
                // Add the meta to the new post.
                update_post_meta( $new_post_id, $meta_key, $meta_value_unserialized );
            }
        }

        // Redirect back to the list of forms.
        wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
        exit;
    }
    add_action( 'admin_action_tw_forms_duplicate_form', 'tw_forms_duplicate_form' );
}
