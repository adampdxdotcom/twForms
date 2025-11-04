<?php
/**
 * Plugin Name:       TW Forms
 * Description:       A custom forms and messaging plugin for the Theatre West website
 * Version:           2.5.0
 * Author:            Adam Michaels
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == Load all plugin components
// =============================================================================

require_once plugin_dir_path( __FILE__ ) . 'includes/01-admin-inbox.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/02-form-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/03-form-processor.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/04-form-cpt.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/05-form-editor.php';

// =============================================================================
// == Enqueue Front-End Scripts
// =============================================================================

if ( ! function_exists( 'tw_forms_enqueue_scripts' ) ) {
    /**
     * Enqueues scripts needed for the front-end forms.
     */
    function tw_forms_enqueue_scripts() {
        // Enqueue our new AJAX submission script.
        wp_enqueue_script(
            'tw-forms-submit',
            plugin_dir_url( __FILE__ ) . 'assets/js/form-submit.js',
            [], // No dependencies
            '2.5.0',
            true // Load in the footer
        );

        // Pass the WordPress AJAX URL to our script.
        wp_localize_script(
            'tw-forms-submit',
            'twForms', // This creates a `twForms` object in JavaScript
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            ]
        );
    }
    add_action( 'wp_enqueue_scripts', 'tw_forms_enqueue_scripts' );
}
