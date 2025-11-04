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
        // Enqueue our AJAX submission script.
        wp_enqueue_script(
            'tw-forms-submit',
            plugin_dir_url( __FILE__ ) . 'assets/js/form-submit.js',
            [],
            '2.5.1', // Bump version
            true
        );

        // Get reCAPTCHA settings to pass to the script.
        $recaptcha_options = get_option('my_recaptcha_settings', []);
        $site_key = ( !empty($recaptcha_options['disable']) ) ? '' : ($recaptcha_options['site_key'] ?? '');

        // Pass both the AJAX URL and the reCAPTCHA site key.
        wp_localize_script(
            'tw-forms-submit',
            'twForms',
            [
                'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                'siteKey'  => $site_key,
            ]
        );
    }
    add_action( 'wp_enqueue_scripts', 'tw_forms_enqueue_scripts' );
}
