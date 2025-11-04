<?php
/**
 * Registers the Custom Post Type for Forms.
 * This file creates the "Form" post type and customizes its admin list view.
 *
 * @package TW_Forms
 * @version 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'tw_forms_register_cpt' ) ) {
    /**
     * Registers the 'tw_form' custom post type.
     */
    function tw_forms_register_cpt() {

        $labels = [
            'name'                  => _x( 'Forms', 'Post type general name', 'tw-forms' ),
            'singular_name'         => _x( 'Form', 'Post type singular name', 'tw-forms' ),
            'menu_name'             => _x( 'Forms', 'Admin Menu text', 'tw-forms' ),
            'name_admin_bar'        => _x( 'Form', 'Add New on Toolbar', 'tw-forms' ),
            'add_new'               => __( 'Add New', 'tw-forms' ),
            'add_new_item'          => __( 'Add New Form', 'tw-forms' ),
            'new_item'              => __( 'New Form', 'tw-forms' ),
            'edit_item'             => __( 'Edit Form', 'tw-forms' ),
            'view_item'             => __( 'View Form', 'tw-forms' ),
            'all_items'             => __( 'All Forms', 'tw-forms' ),
            'search_items'          => __( 'Search Forms', 'tw-forms' ),
            'parent_item_colon'     => __( 'Parent Forms:', 'tw-forms' ),
            'not_found'             => __( 'No forms found.', 'tw-forms' ),
            'not_found_in_trash'    => __( 'No forms found in Trash.', 'tw-forms' ),
            'featured_image'        => _x( 'Form Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'tw-forms' ),
            'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'tw-forms' ),
            'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'tw-forms' ),
            'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'tw-forms' ),
            'archives'              => _x( 'Form archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'tw-forms' ),
            'insert_into_item'      => _x( 'Insert into form', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'tw-forms' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this form', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'tw-forms' ),
            'filter_items_list'     => _x( 'Filter forms list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'tw-forms' ),
            'items_list_navigation' => _x( 'Forms list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'tw-forms' ),
            'items_list'            => _x( 'Forms list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'tw-forms' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false, // This is not a public-facing post type
            'publicly_queryable' => false,
            'show_ui'            => true,  // We want to see it in the admin area
            'show_in_menu'       => false, // We will manually place this in our "TW Forms" menu
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title' ], // We only need a title for now. The builder will be a meta box.
            'menu_icon'          => 'dashicons-feedback', // A nice icon for forms
        ];

        register_post_type( 'tw_form', $args );
    }
    add_action( 'init', 'tw_forms_register_cpt' );
}


if ( ! function_exists( 'tw_forms_cpt_add_columns' ) ) {
    /**
     * Adds a "Shortcode" column to the All Forms list table.
     */
    function tw_forms_cpt_add_columns( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            if ( 'title' === $key ) {
                $new_columns['shortcode'] = 'Shortcode';
            }
        }
        return $new_columns;
    }
    add_filter( 'manage_tw_form_posts_columns', 'tw_forms_cpt_add_columns' );
}

if ( ! function_exists( 'tw_forms_cpt_render_columns' ) ) {
    /**
     * Renders the content for our custom "Shortcode" column.
     */
    function tw_forms_cpt_render_columns( $column, $post_id ) {
        if ( 'shortcode' === $column ) {
            // Create a read-only input field for easy copying of the shortcode.
            $shortcode = '[tw_form id="' . $post_id . '"]';
            echo '<input type="text" readonly="readonly" value="' . esc_attr( $shortcode ) . '" onclick="this.select();" style="width: 100%;">';
        }
    }
    add_action( 'manage_tw_form_posts_custom_column', 'tw_forms_cpt_render_columns', 10, 2 );
}
