<?php
/**
 * Plugin Name:       TW Forms
 * Description:       A custom forms and messaging plugin for the Theatre West website
 * Version:           2.0.0
 * Author:            Adam Michaels
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == Load all plugin components
// =============================================================================

// Use plugin_dir_path( __FILE__ ) to get the full server path to this folder.

// Loads the backend Message Inbox and Blacklist UI
require_once plugin_dir_path( __FILE__ ) . 'includes/01-admin-inbox.php';

// Loads reusable helper functions for forms (validation, email sending, etc.)
require_once plugin_dir_path( __FILE__ ) . 'includes/02-form-helpers.php';

// Loads the form shortcodes, processing logic, and related scripts
require_once plugin_dir_path( __FILE__ ) . 'includes/03-form-processor.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/04-form-cpt.php';

// Loads the meta boxes for the form editor screen
require_once plugin_dir_path( __FILE__ ) . 'includes/05-form-editor.php';
