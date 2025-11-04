<?php
/**
 * Creates the Meta Boxes for the Form Editor screen.
 * This file adds the main "Form Builder" panel and the "Form Settings" panel.
 *
 * @package TW_Forms
 * @version 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'tw_forms_editor_add_meta_boxes' ) ) {
    /**
     * Registers all the meta boxes for the 'tw_form' post type edit screen.
     */
    function tw_forms_editor_add_meta_boxes() {
        add_meta_box(
            'tw_form_builder_mb',       // Unique ID for the meta box
            'Form Builder',             // Title of the meta box
            'tw_forms_render_builder_mb', // Callback function to render the HTML
            'tw_form',                  // The post type to show this on
            'normal',                   // Context (normal, side, advanced)
            'high'                      // Priority
        );
        add_meta_box(
            'tw_form_settings_mb',
            'Form Settings',
            'tw_forms_render_settings_mb',
            'tw_form',
            'side', // Show this one in the sidebar
            'default'
        );
    }
    add_action( 'add_meta_boxes_tw_form', 'tw_forms_editor_add_meta_boxes' );
}

if ( ! function_exists( 'tw_forms_render_builder_mb' ) ) {
    /**
     * Renders the HTML for the main Form Builder meta box.
     * This is the static layout. JavaScript will make it interactive.
     */
    function tw_forms_render_builder_mb( $post ) {
        // Add a nonce field for security
        wp_nonce_field( 'tw_form_save_meta_box_data', 'tw_form_meta_box_nonce' );
        ?>
        <div class="tw-form-builder-wrapper">
            
            <div id="form-fields-container">
                <?php // Form fields will be dynamically added here by JavaScript. ?>
                <p class="empty-state">No fields yet. Click "Add Field" to begin.</p>
            </div>

            <div class="builder-actions">
                <button type="button" id="add-new-field" class="button button-primary button-large">Add Field</button>
            </div>

        </div>

        <?php // This is our hidden template for new fields. JS will clone this. ?>
        <div id="tw-form-field-template" style="display: none;">
            <div class="form-field-block">
                <div class="field-header">
                    <span class="field-type-label">New Field</span>
                    <div class="field-actions">
                        <a href="#" class="move-field" title="Drag to reorder">☰</a>
                        <a href="#" class="delete-field" title="Delete this field">×</a>
                    </div>
                </div>
                <div class="field-settings">
                    <div class="setting-row">
                        <label>Field Type</label>
                        <select name="field_type[]" class="field-type-select">
                            <option value="text">Text Input</option>
                            <option value="email">Email Address</option>
                            <option value="tel">Phone Number</option>
                            <option value="textarea">Text Area (Message)</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="submit">Submit Button</option>
                        </select>
                    </div>
                    <div class="setting-row">
                        <label>Field Label</label>
                        <input type="text" name="field_label[]" placeholder="e.g., Your Full Name">
                    </div>
                    <div class="setting-row">
                        <label><input type="checkbox" name="field_required[]" value="1"> Required?</label>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            #form-fields-container { border: 1px solid #ccd0d4; background: #fff; padding: 15px; min-height: 100px; }
            #form-fields-container .empty-state { color: #777; text-align: center; margin: 20px 0; }
            .builder-actions { margin-top: 15px; }
            .form-field-block { border: 1px solid #ccd0d4; margin-bottom: 10px; background: #fdfdfd; }
            .field-header { display: flex; justify-content: space-between; align-items: center; background: #f0f0f1; padding: 8px 12px; cursor: move; }
            .field-header .field-type-label { font-weight: bold; }
            .field-header .field-actions a { text-decoration: none; font-size: 1.4em; margin-left: 10px; }
            .field-header .delete-field { color: #d63638; }
            .field-settings { padding: 12px; }
            .field-settings .setting-row { margin-bottom: 10px; }
            .field-settings label { display: block; margin-bottom: 5px; font-weight: 500; }
            .field-settings select, .field-settings input[type="text"] { width: 100%; }
        </style>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_settings_mb' ) ) {
    /**
     * Renders the HTML for the Form Settings meta box in the sidebar.
     * This addresses your question about email routing.
     */
    function tw_forms_render_settings_mb( $post ) {
        // Get the saved value from post meta
        $recipients = get_post_meta( $post->ID, '_tw_form_recipients', true );
        ?>
        <div class="tw-form-settings-wrapper">
            <p>
                <label for="tw_form_recipients"><strong>Send Notifications To:</strong></label>
                <input type="text" id="tw_form_recipients" name="tw_form_recipients" value="<?php echo esc_attr( $recipients ); ?>" style="width: 100%;">
            </p>
            <p class="description">
                Enter recipient email addresses, separated by commas. Leave blank to disable email notifications for this form.
            </p>
        </div>
        <?php
    }
}
