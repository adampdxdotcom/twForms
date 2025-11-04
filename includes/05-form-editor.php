<?php
/**
 * Creates and handles the Meta Boxes for the Form Editor screen.
 * This file adds the builder panels, makes them interactive, and saves the data.
 *
 * @package TW_Forms
 * @version 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// -----------------------------------------------------------------------------
// 1. REGISTER META BOXES
// -----------------------------------------------------------------------------

if ( ! function_exists( 'tw_forms_editor_add_meta_boxes' ) ) {
    /**
     * Registers all meta boxes for the 'tw_form' post type edit screen.
     */
    function tw_forms_editor_add_meta_boxes() {
        add_meta_box( 'tw_form_builder_mb', 'Form Builder', 'tw_forms_render_builder_mb', 'tw_form', 'normal', 'high' );
        add_meta_box( 'tw_form_settings_mb', 'Form Settings', 'tw_forms_render_settings_mb', 'tw_form', 'side', 'default' );
    }
    add_action( 'add_meta_boxes_tw_form', 'tw_forms_editor_add_meta_boxes' );
}


// -----------------------------------------------------------------------------
// 2. RENDER META BOX HTML
// -----------------------------------------------------------------------------

if ( ! function_exists( 'tw_forms_render_builder_mb' ) ) {
    /**
     * Renders the HTML for the main Form Builder meta box.
     * This now includes logic to load and display existing saved fields.
     */
    function tw_forms_render_builder_mb( $post ) {
        // Add a nonce field for security
        wp_nonce_field( 'tw_form_save_meta_box_data', 'tw_form_meta_box_nonce' );

        // Get saved fields from the database
        $saved_fields = get_post_meta( $post->ID, '_tw_form_fields', true );
        ?>
        <div class="tw-form-builder-wrapper">
            <div id="form-fields-container">
                <?php if ( ! empty( $saved_fields ) && is_array( $saved_fields ) ) : ?>
                    <?php foreach ( $saved_fields as $index => $field ) : ?>
                        <?php
                        // Sanitize data for display
                        $field_type = esc_attr( $field['type'] ?? 'text' );
                        $field_label = esc_attr( $field['label'] ?? '' );
                        $is_required = ! empty( $field['required'] );

                        // Generate a unique index for existing fields.
                        // Using the array index is fine here because PHP will re-key on save.
                        $field_index = $index; 
                        ?>
                        <div class="form-field-block">
                            <div class="field-header">
                                <span class="field-type-label"><?php echo esc_html( ucfirst( $field_type ) ); // Use helper function later ?></span>
                                <div class="field-actions">
                                    <a href="#" class="move-field" title="Drag to reorder">☰</a>
                                    <a href="#" class="delete-field" title="Delete this field">×</a>
                                </div>
                            </div>
                            <div class="field-settings">
                                <div class="setting-row">
                                    <label>Field Type</label>
                                    <select name="tw_form_fields[<?php echo $field_index; ?>][type]" class="field-type-select">
                                        <option value="text" <?php selected( $field_type, 'text' ); ?>>Text Input</option>
                                        <option value="email" <?php selected( $field_type, 'email' ); ?>>Email Address</option>
                                        <option value="tel" <?php selected( $field_type, 'tel' ); ?>>Phone Number</option>
                                        <option value="textarea" <?php selected( $field_type, 'textarea' ); ?>>Text Area (Message)</option>
                                        <option value="checkbox" <?php selected( $field_type, 'checkbox' ); ?>>Checkbox</option>
                                        <option value="submit" <?php selected( $field_type, 'submit' ); ?>>Submit Button</option>
                                    </select>
                                </div>
                                <div class="setting-row">
                                    <label>Field Label</label>
                                    <input type="text" name="tw_form_fields[<?php echo $field_index; ?>][label]" value="<?php echo $field_label; ?>" placeholder="e.g., Your Full Name">
                                </div>
                                <div class="setting-row">
                                    <label>
                                        <input type="checkbox" name="tw_form_fields[<?php echo $field_index; ?>][required]" value="1" <?php checked( $is_required ); ?>> Required?
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="empty-state">No fields yet. Click "Add Field" to begin.</p>
                <?php endif; ?>
            </div>
            <div class="builder-actions"><button type="button" id="add-new-field" class="button button-primary button-large">Add Field</button></div>
        </div>

        <?php // This is our hidden template for new fields. JS will clone this. ?>
        <div id="tw-form-field-template" style="display: none;">
            <div class="form-field-block">
                <div class="field-header">
                    <span class="field-type-label">Text Input</span>
                    <div class="field-actions"><a href="#" class="move-field" title="Drag to reorder">☰</a><a href="#" class="delete-field" title="Delete this field">×</a></div>
                </div>
                <div class="field-settings">
                    <div class="setting-row">
                        <label>Field Type</label>
                        <select name="tw_form_fields[__INDEX__][type]" class="field-type-select">
                            <option value="text" selected>Text Input</option><option value="email">Email Address</option><option value="tel">Phone Number</option><option value="textarea">Text Area (Message)</option><option value="checkbox">Checkbox</option><option value="submit">Submit Button</option>
                        </select>
                    </div>
                    <div class="setting-row">
                        <label>Field Label</label><input type="text" name="tw_form_fields[__INDEX__][label]" placeholder="e.g., Your Full Name">
                    </div>
                    <div class="setting-row">
                        <label><input type="checkbox" name="tw_form_fields[__INDEX__][required]" value="1"> Required?</label>
                    </div>
                </div>
            </div>
        </div>
        <style>#form-fields-container{border:1px solid #ccd0d4;background:#fff;padding:15px;min-height:100px}#form-fields-container .empty-state{color:#777;text-align:center;margin:20px 0}.builder-actions{margin-top:15px}.form-field-block{border:1px solid #ccd0d4;margin-bottom:10px;background:#fdfdfd}.field-header{display:flex;justify-content:space-between;align-items:center;background:#f0f0f1;padding:8px 12px;cursor:move}.field-header .field-type-label{font-weight:700}.field-header .field-actions a{text-decoration:none;font-size:1.4em;margin-left:10px}.field-header .delete-field{color:#d63638}.field-settings{padding:12px}.field-settings .setting-row{margin-bottom:10px}.field-settings label{display:block;margin-bottom:5px;font-weight:500}.field-settings select,.field-settings input[type=text]{width:100%}</style>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_settings_mb' ) ) {
    function tw_forms_render_settings_mb( $post ) {
        $recipients = get_post_meta( $post->ID, '_tw_form_recipients', true );
        ?>
        <div class="tw-form-settings-wrapper"><p><label for="tw_form_recipients"><strong>Send Notifications To:</strong></label><input type="text" id="tw_form_recipients" name="tw_form_recipients" value="<?php echo esc_attr( $recipients ); ?>" style="width:100%;"></p><p class="description">Enter recipient email addresses, separated by commas. Leave blank to disable.</p></div>
        <?php
    }
}


// -----------------------------------------------------------------------------
// 3. SAVE META BOX DATA
// -----------------------------------------------------------------------------

if ( ! function_exists( 'tw_forms_save_meta_box_data' ) ) {
    /**
     * Saves the custom field data when the 'tw_form' post is saved.
     */
    function tw_forms_save_meta_box_data( $post_id ) {
        // Security checks
        if ( ! isset( $_POST['tw_form_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_meta_box_nonce'], 'tw_form_save_meta_box_data' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( 'tw_form' !== get_post_type( $post_id ) ) return;

        // --- Save the Form Fields ---
        $sanitized_fields = [];
        if ( isset( $_POST['tw_form_fields'] ) && is_array( $_POST['tw_form_fields'] ) ) {
            foreach ( $_POST['tw_form_fields'] as $field_data ) {
                if ( ! is_array( $field_data ) ) continue; // Skip if data is malformed
                $sanitized_field = [
                    'type'     => isset( $field_data['type'] ) ? sanitize_key( $field_data['type'] ) : 'text',
                    'label'    => isset( $field_data['label'] ) ? sanitize_text_field( $field_data['label'] ) : '',
                    'required' => isset( $field_data['required'] ) ? 1 : 0,
                ];
                $sanitized_fields[] = $sanitized_field;
            }
        }
        update_post_meta( $post_id, '_tw_form_fields', $sanitized_fields );

        // --- Save the Form Settings ---
        if ( isset( $_POST['tw_form_recipients'] ) ) {
            $emails_raw = explode( ',', $_POST['tw_form_recipients'] );
            $sanitized_emails = [];
            foreach ( $emails_raw as $email ) {
                $trimmed_email = trim( $email );
                if ( is_email( $trimmed_email ) ) { $sanitized_emails[] = $trimmed_email; }
            }
            update_post_meta( $post_id, '_tw_form_recipients', implode( ', ', $sanitized_emails ) );
        }
    }
    add_action( 'save_post_tw_form', 'tw_forms_save_meta_box_data' );
}


// -----------------------------------------------------------------------------
// 4. ENQUEUE EDITOR JAVASCRIPT
// -----------------------------------------------------------------------------

if ( ! function_exists( 'tw_forms_editor_enqueue_scripts' ) ) {
    /**
     * Enqueues and prints the necessary JavaScript for the form builder.
     */
    function tw_forms_editor_enqueue_scripts( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ] ) || 'tw_form' !== get_post_type() ) return;
        wp_enqueue_script( 'jquery-ui-sortable' );
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                const fieldsContainer = $('#form-fields-container');
                const fieldTemplate = $('#tw-form-field-template');

                function updateEmptyState() {
                    if (fieldsContainer.find('.form-field-block').length === 0) {
                        fieldsContainer.html('<p class="empty-state">No fields yet. Click "Add Field" to begin.</p>');
                    } else {
                        fieldsContainer.find('.empty-state').remove();
                    }
                }

                fieldsContainer.sortable({ handle: '.field-header', placeholder: 'field-placeholder', start: function(e, ui){ ui.placeholder.height(ui.item.height()); } }).disableSelection();
                
                $('#add-new-field').on('click', function() {
                    updateEmptyState();
                    const newField = fieldTemplate.find('.form-field-block').clone();
                    const newIndex = new Date().getTime();
                    newField.find('[name*="__INDEX__"]').each(function() {
                        $(this).attr('name', $(this).attr('name').replace('__INDEX__', newIndex));
                    });
                    fieldsContainer.append(newField);
                });

                fieldsContainer.on('click', '.delete-field', function(e) { e.preventDefault(); if (confirm('Are you sure?')) { $(this).closest('.form-field-block').remove(); updateEmptyState(); } });
                fieldsContainer.on('change', '.field-type-select', function() { $(this).closest('.form-field-block').find('.field-type-label').text($(this).find('option:selected').text()); });

            });
        </script>
        <style>.field-placeholder{border:2px dashed #ccd0d4;background-color:#f0f8ff;margin-bottom:10px;box-sizing:border-box;}</style>
        <?php
    }
    add_action( 'admin_footer-post.php', 'tw_forms_editor_enqueue_scripts' );
    add_action( 'admin_footer-post-new.php', 'tw_forms_editor_enqueue_scripts' );
}
