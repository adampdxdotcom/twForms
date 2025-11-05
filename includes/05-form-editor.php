<?php
/**
 * Creates the Meta Boxes for the Form Editor screen with a Row/Column Layout Builder.
 *
 * @package TW_Forms
 * @version 2.9.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// -----------------------------------------------------------------------------
// 1. REGISTER META BOXES
// -----------------------------------------------------------------------------
if ( ! function_exists( 'tw_forms_editor_add_meta_boxes' ) ) {
    function tw_forms_editor_add_meta_boxes() {
        add_meta_box( 'tw_form_builder_mb', 'Form Layout & Fields', 'tw_forms_render_builder_mb', 'tw_form', 'normal', 'high' );
        add_meta_box( 'tw_form_admin_notification_mb', 'Admin Notification Email', 'tw_forms_render_admin_notification_mb', 'tw_form', 'normal', 'default' );
        add_meta_box( 'tw_form_user_confirmation_mb', 'User Confirmation Email (Autoresponder)', 'tw_forms_render_user_confirmation_mb', 'tw_form', 'normal', 'default' );
        add_meta_box( 'tw_form_shortcode_mb', 'Shortcode', 'tw_forms_render_shortcode_mb', 'tw_form', 'side', 'high' );
    }
    add_action( 'add_meta_boxes_tw_form', 'tw_forms_editor_add_meta_boxes' );
}

// -----------------------------------------------------------------------------
// 2. RENDER META BOX HTML
// -----------------------------------------------------------------------------

if ( ! function_exists( 'tw_forms_render_builder_mb' ) ) {
    function tw_forms_render_builder_mb( $post ) {
        wp_nonce_field( 'tw_form_save_meta_box_data', 'tw_form_meta_box_nonce' );
        $saved_layout = get_post_meta( $post->ID, '_tw_form_layout', true );
        ?>
        <div class="tw-form-builder-wrapper">
            <div id="layout-container">
                <?php if ( ! empty( $saved_layout ) && is_array( $saved_layout ) ) : ?>
                    <?php foreach ( $saved_layout as $row_index => $row ) : tw_forms_render_row_partial( $row_index, $row ); endforeach; ?>
                <?php else : ?>
                    <p class="empty-state">No rows yet. Click "Add Row" to begin building your form.</p>
                <?php endif; ?>
            </div>
            <div class="builder-actions">
                <div class="add-row-container">
                    <span>Add New Row with Layout:</span>
                    <button type="button" class="button add-row-button" data-layout="100">1 Column</button>
                    <button type="button" class="button add-row-button" data-layout="50-50">2 Columns</button>
                    <button type="button" class="button add-row-button" data-layout="33-33-33">3 Columns</button>
                    <button type="button" class="button add-row-button" data-layout="25-25-25-25">4 Columns</button>
                </div>
            </div>
        </div>

        <div id="tw-form-templates" style="display: none;">
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '100', 'columns' => [[]] ], true ); ?>
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '50-50', 'columns' => [[], []] ], true ); ?>
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '33-33-33', 'columns' => [[], [], []] ], true ); ?>
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '25-25-25-25', 'columns' => [[], [], [], []] ], true ); ?>
            <?php tw_forms_render_field_partial( '__ROW_INDEX__', '__COL_INDEX__', '__FIELD_INDEX__', [], true ); ?>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_row_partial' ) ) {
    function tw_forms_render_row_partial( $row_index, $row_data, $is_template = false ) {
        $layout = esc_attr( $row_data['layout'] );
        ?>
        <div class="row-block" data-layout="<?php echo $layout; ?>" <?php if($is_template) echo 'id="template-row-'.$layout.'"'; ?>>
            <div class="row-header"><span class="row-label">Row (<?php echo str_replace('-', '/', $layout); ?>)</span><div class="row-actions"><a href="#" class="delete-row" title="Delete this row">×</a></div></div>
            <div class="row-columns">
                <?php foreach ( $row_data['columns'] as $col_index => $column ) : ?>
                    <div class="column-block" data-layout="<?php echo $layout; ?>">
                        <?php if ( ! empty( $column ) && is_array( $column ) ) : ?>
                            <?php foreach ( $column as $field_index => $field ) : tw_forms_render_field_partial( $row_index, $col_index, $field_index, $field, $is_template ); endforeach; ?>
                        <?php endif; ?>
                        <button type="button" class="button add-field-to-col">+ Add Field</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_field_partial' ) ) {
    function tw_forms_render_field_partial( $row_index, $col_index, $field_index, $field, $is_template = false ) {
        $field_type = esc_attr( $field['type'] ?? 'text' ); $field_label = esc_attr( $field['label'] ?? '' ); $is_required = ! empty( $field['required'] );
        $needs_confirm = ! empty( $field['confirm'] ); $options = esc_textarea( $field['options'] ?? '' ); $cols = esc_attr( $field['cols'] ?? '1' );
        $html_content = $field['html_content'] ?? ''; $placeholder_text = esc_attr( $field['placeholder'] ?? '' );
        $hide_label = ! empty( $field['hide_label'] );
        $name_base = "tw_form_fields[{$row_index}][columns][{$col_index}][{$field_index}]";
        $disabled = $is_template ? 'disabled' : '';
        ?>
        <div class="form-field-block" <?php if($is_template) echo 'id="template-field"'; ?>>
            <div class="field-header"><span class="field-type-label"><?php echo esc_html( ucfirst( str_replace('_', ' ', $field_type) ) ); ?></span><div class="field-actions"><a href="#" class="move-field" title="Drag to reorder">☰</a><a href="#" class="delete-field" title="Delete this field">×</a></div></div>
            <div class="field-settings">
                <div class="setting-row">
                    <label>Field Type</label>
                    <select name="<?php echo $name_base; ?>[type]" class="field-type-select" <?php echo $disabled; ?>>
                        <optgroup label="Input Fields"><option value="text" <?php selected($field_type,'text');?>>Text Input</option><option value="email" <?php selected($field_type,'email');?>>Email Address</option><option value="tel" <?php selected($field_type,'tel');?>>Phone Number</option><option value="textarea" <?php selected($field_type,'textarea');?>>Text Area</option><option value="dropdown" <?php selected($field_type,'dropdown');?>>Dropdown</option><option value="radio_group" <?php selected($field_type,'radio_group');?>>Radio Button Group</option><option value="checkbox_group" <?php selected($field_type,'checkbox_group');?>>Checkbox Group</option></optgroup>
                        <optgroup label="Structural Elements"><option value="section_header" <?php selected($field_type,'section_header');?>>Section Header</option><option value="html_block" <?php selected($field_type,'html_block');?>>HTML Block</option></optgroup>
                        <optgroup label="Actions"><option value="submit" <?php selected($field_type,'submit');?>>Submit Button</option></optgroup>
                    </select>
                </div>
                <div class="setting-row field-label-panel"><label>Field Label / Header Text</label><input type="text" name="<?php echo $name_base; ?>[label]" value="<?php echo $field_label; ?>" placeholder="e.g., Your Full Name" class="field-label-input" <?php echo $disabled; ?>></div>
                <div class="setting-row placeholder-panel"><label>Placeholder Text</label><input type="text" name="<?php echo $name_base; ?>[placeholder]" value="<?php echo $placeholder_text; ?>" placeholder="e.g., Enter your first name" <?php echo $disabled; ?>></div>
                <div class="setting-row html-content-panel"><label>Content (HTML allowed)</label><textarea name="<?php echo $name_base; ?>[html_content]" rows="5" placeholder="Enter your text or HTML here..." <?php echo $disabled; ?>><?php echo esc_textarea($html_content); ?></textarea></div>
                <div class="setting-row setting-row-options">
                    <label><input type="checkbox" name="<?php echo $name_base; ?>[required]" value="1" <?php checked( $is_required ); ?> <?php echo $disabled; ?>> Required?</label>
                    <label class="confirm-email-option"><input type="checkbox" name="<?php echo $name_base; ?>[confirm]" value="1" <?php checked( $needs_confirm ); ?> <?php echo $disabled; ?>> Confirm?</label>
                    <label class="hide-label-option"><input type="checkbox" name="<?php echo $name_base; ?>[hide_label]" value="1" <?php checked( $hide_label ); ?> <?php echo $disabled; ?>> Hide Field Label?</label>
                </div>
                <div class="options-panel"><div class="setting-row"><label>Options (one per line)</label><textarea name="<?php echo $name_base; ?>[options]" rows="4" class="field-options-textarea" <?php echo $disabled; ?>><?php echo $options; ?></textarea></div><div class="setting-row multi-column-option"><label>Display in Columns</label><select name="<?php echo $name_base; ?>[cols]" <?php echo $disabled; ?>><option value="1" <?php selected($cols, '1'); ?>>1 Column</option><option value="2" <?php selected($cols, '2'); ?>>2 Columns</option><option value="3" <?php selected($cols, '3'); ?>>3 Columns</option></select></div></div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_admin_notification_mb' ) ) {
    function tw_forms_render_admin_notification_mb( $post ) {
        $settings = get_post_meta( $post->ID, '_tw_form_admin_email', true );
        $settings = is_array( $settings ) ? $settings : [];
        $recipients_fallback = get_post_meta( $post->ID, '_tw_form_recipients', true );
        $to       = $settings['to'] ?? $recipients_fallback;
        $subject  = $settings['subject'] ?? 'New Submission from [form_name]';
        $message  = $settings['message'] ?? '<p>A new entry has been submitted via the [form_name] form on your website.</p>[all_fields]';
        $rules    = get_post_meta( $post->ID, '_tw_form_conditional_rules', true );
        $rules    = is_array($rules) ? $rules : [];

        $form_fields_data = [];
        $saved_layout = get_post_meta( $post->ID, '_tw_form_layout', true );
        if ( is_array( $saved_layout ) ) {
            foreach ( $saved_layout as $row ) {
                foreach ( $row['columns'] as $column ) {
                    foreach ( $column as $field ) {
                        if ( ! empty( $field['label'] ) ) {
                            $form_fields_data[ $field['label'] ] = ['type' => $field['type'], 'options' => $field['options'] ?? '',];
                        }
                    }
                }
            }
        }
        ?>
        <script type="text/javascript"> window.twFormFieldData = <?php echo json_encode( $form_fields_data ); ?>; </script>

        <div class="tw-email-settings-wrapper">
            <div class="email-settings-main">
                <div class="setting-row"><label for="admin-email-to"><strong>Send Notifications To</strong></label><input type="text" id="admin-email-to" name="tw_form_admin_email[to]" value="<?php echo esc_attr( $to ); ?>"><p class="description">Enter email addresses, comma-separated. If blank, defaults to the site admin email.</p></div>
                <div class="setting-row"><label for="admin-email-subject"><strong>Subject</strong></label><input type="text" id="admin-email-subject" name="tw_form_admin_email[subject]" value="<?php echo esc_attr( $subject ); ?>"></div>
                <div class="setting-row"><label for="admin-email-message"><strong>Message</strong></label>
                    <?php wp_editor( $message, 'admin-email-message', ['textarea_name' => 'tw_form_admin_email[message]', 'media_buttons' => false, 'textarea_rows' => 8, 'tinymce' => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ]] ); ?>
                </div>
                <hr>
                <h3>Conditional Notifications</h3>
                <p class="description">Send notifications based on user input. For example, send a notice to marketing if a user checks "Sign up for newsletter".</p>
                <div id="conditional-rules-container">
                    <?php if ( ! empty( $rules ) ) : ?>
                        <?php foreach ( $rules as $index => $rule ) : ?>
                            <?php tw_forms_render_conditional_rule_partial( $index, $rule, $form_fields_data ); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="button" id="add-conditional-rule">+ Add Notification Rule</button>
            </div>
            <div class="email-settings-sidebar">
                <p>This email is an alert that a new message is waiting in your site's inbox. All replies should be sent from the plugin's messaging system.</p>
                <hr>
                <strong>Available Tags</strong><p>Use these tags in your subject or message.</p>
                <div class="tags-list"><code>[all_fields]</code> <code>[form_name]</code> <code>[page_url]</code> <code>[user_ip]</code> <code>[submission_date]</code> <code>[submission_time]</code><p>You can also use tags for any field by wrapping its label in brackets, e.g., <code>[Your Name]</code>.</p></div>
            </div>
        </div>
        <div id="conditional-rule-template" style="display: none;">
             <?php tw_forms_render_conditional_rule_partial( '__INDEX__', [], $form_fields_data ); ?>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_conditional_rule_partial' ) ) {
    function tw_forms_render_conditional_rule_partial( $index, $rule_data, $form_fields ) {
        $field_label     = $rule_data['field'] ?? '';
        $operator        = $rule_data['operator'] ?? 'is';
        $value           = $rule_data['value'] ?? '';
        $recipient       = $rule_data['recipient'] ?? '';
        $suppress_main   = ! empty( $rule_data['suppress'] );
        $custom_subject  = $rule_data['subject'] ?? '';
        $custom_message  = $rule_data['message'] ?? '';
        $editor_visible  = ! empty( $custom_subject ) || ! empty( $custom_message );
        ?>
        <div class="conditional-rule-wrapper">
            <div class="conditional-rule-block">
                <span>IF</span>
                <select name="tw_form_conditional_rules[<?php echo esc_attr( $index ); ?>][field]" class="conditional-field-select">
                    <option value="">-- Select a Field --</option>
                    <?php foreach ( $form_fields as $label => $data ) : ?>
                        <option value="<?php echo esc_attr( $label ); ?>" <?php selected( $field_label, $label ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tw_form_conditional_rules[<?php echo esc_attr( $index ); ?>][operator]" class="conditional-operator-select"></select>
                <span class="conditional-value-wrapper"></span>
                <span>THEN send to</span>
                <input type="email" name="tw_form_conditional_rules[<?php echo esc_attr( $index ); ?>][recipient]" value="<?php echo esc_attr( $recipient ); ?>" placeholder="email@example.com">
                <a href="#" class="remove-conditional-rule" title="Remove Rule">×</a>
                <input type="hidden" class="conditional-value-data" value="<?php echo esc_attr( $value ); ?>">
                <input type="hidden" class="conditional-operator-data" value="<?php echo esc_attr( $operator ); ?>">
            </div>
            <div class="conditional-rule-options">
                <a href="#" class="toggle-custom-message"><?php echo $editor_visible ? 'Hide Custom Message' : 'Customize Message'; ?></a>
                <label class="suppress-main-label">
                    <input type="checkbox" name="tw_form_conditional_rules[<?php echo esc_attr( $index ); ?>][suppress]" value="1" <?php checked( $suppress_main ); ?>>
                    Suppress main notification if this is the only non-required action.
                </label>
            </div>
            <div class="custom-message-editor" style="<?php echo $editor_visible ? '' : 'display: none;'; ?>">
                <label>Custom Subject</label>
                <input type="text" name="tw_form_conditional_rules[<?php echo esc_attr( $index ); ?>][subject]" value="<?php echo esc_attr($custom_subject); ?>" placeholder="New Alert: <?php echo esc_attr($field_label); ?>">
                <label>Custom Message</label>
                <textarea name="tw_form_conditional_rules[<?php echo esc_attr( $index ); ?>][message]" rows="4" placeholder="Name: [Your Name]&#10;Email: [Your Email Address]"><?php echo esc_textarea($custom_message); ?></textarea>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_user_confirmation_mb' ) ) {
    function tw_forms_render_user_confirmation_mb( $post ) {
        $settings = get_post_meta( $post->ID, '_tw_form_user_email', true );
        $settings = is_array( $settings ) ? $settings : [];
        $enabled = ! empty( $settings['enabled'] );
        $subject = $settings['subject'] ?? 'Thank you for your submission';
        $message = $settings['message'] ?? '<p>Hi [Your Name],</p><p>Thank you for contacting us. We have received your message.</p><hr><p style="font-size: smaller; color: #777;">This is an automated message. Please do not reply to this email, as this inbox is not monitored.</p>';
        ?>
        <div class="tw-email-settings-wrapper">
             <div class="email-settings-main">
                <div class="setting-row"><label><input type="checkbox" name="tw_form_user_email[enabled]" value="1" <?php checked( $enabled ); ?>> <strong>Enable this email notification</strong></label></div>
                <div class="setting-row"><label for="user-email-subject"><strong>Subject</strong></label><input type="text" id="user-email-subject" name="tw_form_user_email[subject]" value="<?php echo esc_attr( $subject ); ?>"></div>
                <div class="setting-row"><label for="user-email-message"><strong>Message</strong></label>
                    <?php wp_editor( $message, 'user-email-message', ['textarea_name' => 'tw_form_user_email[message]', 'media_buttons' => false, 'textarea_rows' => 10, 'tinymce' => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ]] ); ?>
                </div>
            </div>
            <div class="email-settings-sidebar">
                <p>This email's <strong>To</strong> address is automatically set to the email provided by the user in the form.</p>
                <p>Replies to this message are disabled.</p>
                <hr>
                <strong>Available Tags</strong><p>Use these tags in your subject or message.</p>
                <div class="tags-list"><code>[all_fields]</code> <code>[form_name]</code> <code>[page_url]</code> <code>[user_ip]</code> <code>[submission_date]</code><p>You can also use tags for any field by wrapping its label in brackets, e.g., <code>[Your Name]</code>.</p></div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_shortcode_mb' ) ) {
    function tw_forms_render_shortcode_mb( $post ) {
        $shortcode = '[tw_form id="' . $post->ID . '"]';
        ?>
        <div class="tw-shortcode-wrapper"><p>Paste this shortcode onto any page or post to display this form.</p><input type="text" readonly="readonly" value="<?php echo esc_attr( $shortcode ); ?>" onclick="this.select();" style="width:100%;text-align:center;font-weight:bold;padding:5px;cursor:copy;"></div>
        <?php
    }
}

// -----------------------------------------------------------------------------
// 3. SAVE META BOX DATA
// -----------------------------------------------------------------------------
if ( ! function_exists( 'tw_forms_save_meta_box_data' ) ) {
    function tw_forms_save_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['tw_form_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_meta_box_nonce'], 'tw_form_save_meta_box_data' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( 'tw_form' !== get_post_type( $post_id ) ) return;

        // --- Save Form Layout & Fields ---
        $sanitized_layout = [];
        if ( isset( $_POST['tw_form_fields'] ) && is_array( $_POST['tw_form_fields'] ) ) {
            foreach ( $_POST['tw_form_fields'] as $row_data ) {
                if ( ! is_array( $row_data ) || ! isset( $row_data['columns'] ) ) continue;
                $sanitized_row = [ 'layout' => sanitize_key( $row_data['layout'] ?? '100' ), 'columns' => [] ];
                foreach ( $row_data['columns'] as $column_data ) {
                    $sanitized_column = [];
                    if ( is_array( $column_data ) ) {
                        foreach ( $column_data as $field_data ) {
                            $sanitized_column[] = ['type' => sanitize_key($field_data['type']??'text'),'label' => sanitize_text_field($field_data['label']??''),'placeholder' => sanitize_text_field($field_data['placeholder']??''),'html_content' => wp_kses_post($field_data['html_content']??''),'required' => isset($field_data['required'])?1:0,'confirm' => isset($field_data['confirm'])?1:0,'options' => sanitize_textarea_field($field_data['options']??''),'cols' => sanitize_key($field_data['cols']??'1'), 'hide_label' => isset($field_data['hide_label'])?1:0,];
                        }
                    }
                    $sanitized_row['columns'][] = $sanitized_column;
                }
                $sanitized_layout[] = $sanitized_row;
            }
        }
        update_post_meta( $post_id, '_tw_form_layout', $sanitized_layout );

        // --- Save Admin Notification Email Settings ---
        if ( isset( $_POST['tw_form_admin_email'] ) && is_array( $_POST['tw_form_admin_email'] ) ) {
            $data = $_POST['tw_form_admin_email'];
            $emails_raw = explode( ',', $data['to'] ?? '' ); $sanitized_emails = [];
            foreach ( $emails_raw as $email ) { if ( is_email( trim( $email ) ) ) { $sanitized_emails[] = trim( $email ); } }
            $sanitized_data = ['to' => implode( ', ', $sanitized_emails ), 'subject' => sanitize_text_field( $data['subject'] ?? '' ), 'message' => wp_kses_post( $data['message'] ?? '' ),];
            update_post_meta( $post_id, '_tw_form_admin_email', $sanitized_data );
            delete_post_meta( $post_id, '_tw_form_recipients' );
        }

        // --- Save Conditional Notification Rules ---
        $sanitized_rules = [];
        if ( isset( $_POST['tw_form_conditional_rules'] ) && is_array( $_POST['tw_form_conditional_rules'] ) ) {
            foreach ( $_POST['tw_form_conditional_rules'] as $rule_data ) {
                if ( ! empty( $rule_data['field'] ) && ! empty( $rule_data['recipient'] ) && is_email( $rule_data['recipient'] ) ) {
                    $sanitized_rules[] = [
                        'field'     => sanitize_text_field($rule_data['field']), 
                        'operator'  => sanitize_key($rule_data['operator']), 
                        'value'     => sanitize_text_field($rule_data['value'] ?? ''), 
                        'recipient' => sanitize_email($rule_data['recipient']),
                        'suppress'  => isset($rule_data['suppress']) ? 1 : 0,
                        'subject'   => sanitize_text_field($rule_data['subject'] ?? ''),
                        'message'   => sanitize_textarea_field($rule_data['message'] ?? ''),
                    ];
                }
            }
        }
        update_post_meta( $post_id, '_tw_form_conditional_rules', $sanitized_rules );

        // --- Save User Confirmation Email Settings ---
        if ( isset( $_POST['tw_form_user_email'] ) && is_array( $_POST['tw_form_user_email'] ) ) {
            $data = $_POST['tw_form_user_email'];
            $sanitized_data = ['enabled' => isset( $data['enabled'] ) ? 1 : 0, 'subject' => sanitize_text_field( $data['subject'] ?? '' ), 'message' => wp_kses_post( $data['message'] ?? '' ),];
            update_post_meta( $post_id, '_tw_form_user_email', $sanitized_data );
        }
    }
    add_action( 'save_post_tw_form', 'tw_forms_save_meta_box_data' );
}

// -----------------------------------------------------------------------------
// 4. ENQUEUE EDITOR SCRIPTS & STYLES
// -----------------------------------------------------------------------------
if ( ! function_exists( 'tw_forms_editor_enqueue_scripts' ) ) {
    function tw_forms_editor_enqueue_scripts() {
        global $pagenow, $post;
        $is_correct_screen = ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'tw_form') || ($pagenow === 'post.php' && isset($post) && $post->post_type === 'tw_form');
        if ( ! $is_correct_screen ) return;

        wp_enqueue_script( 'jquery-ui-sortable' );
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // ... (Form builder JS remains unchanged) ...
                const layoutContainer = $('#layout-container');
                const templates = $('#tw-form-templates');
                function getUniqueIndex() { return new Date().getTime(); }
                function updateEmptyState() { if (layoutContainer.find('.row-block').length === 0) { layoutContainer.html('<p class="empty-state">No rows yet. Click "Add Row" to begin building your form.</p>'); } else { layoutContainer.find('.empty-state').remove(); } }
                function reindexNames() {
                    layoutContainer.find('.row-block').each(function(rowIndex) {
                        $(this).find('[name*="tw_form_fields"]').each(function() { this.name = this.name.replace(/tw_form_fields\[.*?\]/, 'tw_form_fields[' + rowIndex + ']'); });
                        $(this).find('.column-block').each(function(colIndex) {
                            $(this).find('.form-field-block').each(function(fieldIndex) {
                                $(this).find('[name*="[columns]"]').each(function() {
                                    this.name = this.name.replace(/\[columns\]\[.*?\]/, '[columns][' + colIndex + ']');
                                    this.name = this.name.replace(/\[columns\]\[\d+\]\[.*?\]/, '[columns][' + colIndex + '][' + fieldIndex + ']');
                                });
                            });
                        });
                    });
                }
                function initSortables() {
                    layoutContainer.sortable({ handle: '.row-header', placeholder: 'row-placeholder', update: reindexNames }).disableSelection();
                    layoutContainer.find('.column-block').sortable({ handle: '.field-header', placeholder: 'field-placeholder', connectWith: '.column-block', update: reindexNames }).disableSelection();
                }
                $('.add-row-button').on('click', function() { updateEmptyState(); const layout = $(this).data('layout'); const newRow = templates.find('#template-row-' + layout).clone().removeAttr('id'); const rowIndex = getUniqueIndex(); newRow.find('[name*="__ROW_INDEX__"]').each(function() { this.name = this.name.replace(/__ROW_INDEX__/g, rowIndex); }); layoutContainer.append(newRow); initSortables(); reindexNames(); });
                layoutContainer.on('click', '.add-field-to-col', function() { const column = $(this).closest('.column-block'); const newField = templates.find('#template-field').clone().removeAttr('id'); newField.find('[disabled]').prop('disabled', false); newField.insertBefore($(this)); reindexNames(); newField.find('.field-type-select').trigger('change'); });
                layoutContainer.on('click', '.delete-row', function(e) { e.preventDefault(); if (confirm('Delete this entire row?')) { $(this).closest('.row-block').remove(); updateEmptyState(); reindexNames(); } });
                layoutContainer.on('click', '.delete-field', function(e) { e.preventDefault(); if (confirm('Delete this field?')) { $(this).closest('.form-field-block').remove(); reindexNames(); } });
                layoutContainer.on('change', '.field-type-select', function() {
                    const fieldBlock = $(this).closest('.form-field-block'); const selectedType = $(this).val(); fieldBlock.find('.field-type-label').text($(this).find('option:selected').text());
                    const fieldHasLabel = ['text', 'email', 'tel', 'textarea', 'dropdown', 'radio_group', 'checkbox_group', 'section_header', 'submit'].includes(selectedType);
                    const hasPlaceholder = ['text', 'email', 'tel', 'textarea'].includes(selectedType);
                    const hasOptions = ['dropdown', 'radio_group', 'checkbox_group'].includes(selectedType);
                    fieldBlock.find('.field-label-panel').toggle(fieldHasLabel);
                    fieldBlock.find('.placeholder-panel').toggle(hasPlaceholder);
                    fieldBlock.find('.html-content-panel').toggle(selectedType === 'html_block');
                    fieldBlock.find('.setting-row-options').toggle(fieldHasLabel && selectedType !== 'html_block' && selectedType !== 'section_header' && selectedType !== 'submit');
                    fieldBlock.find('.confirm-email-option').toggle(selectedType === 'email');
                    fieldBlock.find('.options-panel').toggle(hasOptions);
                    fieldBlock.find('.multi-column-option').toggle(selectedType === 'checkbox_group' || selectedType === 'radio_group');
                });
                initSortables();
                layoutContainer.find('.field-type-select').trigger('change');

                // --- CONDITIONAL NOTIFICATIONS JS ---
                const rulesContainer = $('#conditional-rules-container');
                $('#add-conditional-rule').on('click', function(e) { e.preventDefault(); const template = $('#conditional-rule-template').html(); const newIndex = new Date().getTime(); const newRuleHTML = template.replace(/__INDEX__/g, newIndex); const newRule = $(newRuleHTML); rulesContainer.append(newRule); newRule.find('.conditional-field-select').trigger('change'); });
                rulesContainer.on('click', '.remove-conditional-rule', function(e) { e.preventDefault(); if (confirm('Remove this rule?')) { $(this).closest('.conditional-rule-wrapper').remove(); } });
                rulesContainer.on('click', '.toggle-custom-message', function(e) { e.preventDefault(); const link = $(this); const editor = link.closest('.conditional-rule-wrapper').find('.custom-message-editor'); editor.slideToggle(200); link.text(editor.is(':hidden') ? 'Customize Message' : 'Hide Custom Message'); });

                function updateConditionalRule(ruleBlock) {
                    const fieldSelect = ruleBlock.find('.conditional-field-select');
                    const operatorSelect = ruleBlock.find('.conditional-operator-select');
                    const valueWrapper = ruleBlock.find('.conditional-value-wrapper');
                    const savedOperator = ruleBlock.find('.conditional-operator-data').val();
                    const savedValue = ruleBlock.find('.conditional-value-data').val();
                    const fieldName = fieldSelect.val();
                    const nameBase = fieldSelect.attr('name').replace('[field]', '');
                    
                    const fieldInfo = (window.twFormFieldData && window.twFormFieldData[fieldName]) ? window.twFormFieldData[fieldName] : { type: 'text', options: '' };
                    const fieldType = fieldInfo.type;

                    const operators = { text: {'is': 'is', 'is_not': 'is not', 'is_empty': 'is empty', 'is_not_empty': 'is not empty', 'contains': 'contains'}, choice: {'is': 'is', 'is_not': 'is not'}, checkbox: {'is_checked': 'is checked', 'is_not_checked': 'is not checked'} };
                    let currentOperators = (fieldType === 'checkbox_group') ? operators.checkbox : (['dropdown', 'radio_group'].includes(fieldType) ? operators.choice : operators.text);
                    
                    operatorSelect.empty();
                    $.each(currentOperators, (key, label) => { operatorSelect.append($('<option>', { value: key, text: label, selected: key === savedOperator })); });

                    const selectedOperator = operatorSelect.val() || Object.keys(currentOperators)[0];
                    const needsValue = !['is_empty', 'is_not_empty', 'is_checked', 'is_not_checked'].includes(selectedOperator);
                    
                    valueWrapper.empty().toggle(needsValue);
                    if (needsValue) {
                        let valueInputHtml;
                        if (['dropdown', 'radio_group'].includes(fieldType)) {
                            const options = fieldInfo.options.split('\n').map(opt => opt.trim()).filter(opt => opt);
                            valueInputHtml = `<select name="${nameBase}[value]"><option value="">-- Select --</option>`;
                            options.forEach(opt => { const selected = (opt === savedValue) ? 'selected' : ''; valueInputHtml += `<option value="${$('<div/>').text(opt).html()}" ${selected}>${$('<div/>').text(opt).html()}</option>`; });
                            valueInputHtml += `</select>`;
                        } else {
                            valueInputHtml = `<input type="text" name="${nameBase}[value]" value="${savedValue}" placeholder="Enter value">`;
                        }
                        valueWrapper.html(valueInputHtml);
                    }
                }

                rulesContainer.on('change', '.conditional-field-select', function() { updateConditionalRule($(this).closest('.conditional-rule-wrapper')); });
                rulesContainer.on('change', '.conditional-operator-select', function() { updateConditionalRule($(this).closest('.conditional-rule-wrapper')); });
                $('.conditional-rule-wrapper').each(function() { updateConditionalRule($(this)); });
            });
        </script>
        <style>
            .tw-form-builder-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; } #layout-container { border: 1px solid #ccd0d4; background: #fff; padding: 15px; min-height: 150px; } #layout-container .empty-state { color: #777; text-align: center; margin: 40px 0; font-size: 1.2em; } .builder-actions { margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ccd0d4; } .add-row-container { display: flex; align-items: center; gap: 10px; } .row-block { border: 1px solid #999; margin-bottom: 15px; background: #fdfdfd; } .row-header { display: flex; justify-content: space-between; align-items: center; background: #e0e0e0; padding: 5px 10px; cursor: move; border-bottom: 1px solid #ccc; } .row-header .row-label { font-weight: bold; } .row-columns { display: flex; gap: 10px; padding: 10px; } .column-block { flex: 1; border: 1px dashed #ccd0d4; background: #f9f9f9; min-height: 80px; padding: 10px; } .column-block[data-layout="50-50"] { flex-basis: 50%; } .column-block[data-layout="33-33-33"] { flex-basis: 33.33%; } .column-block[data-layout="25-25-25-25"] { flex-basis: 25%; } .column-block .add-field-to-col { width: 100%; margin-top: 5px; } .form-field-block { border: 1px solid #ccd0d4; margin-bottom: 10px; background: #fff; } .field-header { display: flex; justify-content: space-between; align-items: center; background: #f0f0f1; padding: 8px 12px; cursor: move; } .field-header .field-type-label { font-weight: 700; } .field-header .field-actions a { text-decoration: none; font-size: 1.4em; margin-left: 10px; } .field-settings { padding: 12px; } .field-settings .setting-row { margin-bottom: 10px; } .field-settings .setting-row-options label { display: inline-block; margin-right: 20px; } .field-settings label { display: block; margin-bottom: 5px; font-weight: 500; } .field-settings select, .field-settings input[type=text], .field-settings input[type=email], .field-settings textarea { width: 100%; } .row-placeholder, .field-placeholder{border:2px dashed #ccd0d4;background-color:#f0f8ff;margin-bottom:15px;box-sizing:border-box;}
            .tw-email-settings-wrapper { display: flex; gap: 20px; } .tw-email-settings-wrapper .email-settings-main { flex: 1; } .tw-email-settings-wrapper .email-settings-sidebar { flex-basis: 250px; background-color: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; } .tw-email-settings-wrapper .email-settings-sidebar strong { font-size: 1.1em; } .tw-email-settings-wrapper .email-settings-sidebar .tags-list { margin-top: 10px; } .tw-email-settings-wrapper .email-settings-sidebar code { background-color: #e0e0e0; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; } .tw-email-settings-wrapper .email-settings-sidebar p { margin-top: 5px; } .tw-email-settings-wrapper .setting-row { margin-bottom: 15px; } .tw-email-settings-wrapper .setting-row input[type="text"], .tw-email-settings-wrapper .setting-row input[type="email"] { width: 100%; }
            .conditional-rule-wrapper { background-color: #f0f0f1; padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; } .conditional-rule-block { display: flex; align-items: center; gap: 8px; } .conditional-rule-block span { font-style: italic; color: #555; white-space: nowrap; } .conditional-rule-block select, .conditional-rule-block input { flex-grow: 1; } .conditional-rule-block .conditional-field-select { flex-basis: 20%; } .conditional-rule-block .conditional-operator-select { flex-basis: 15%; } .conditional-rule-block .conditional-value-wrapper { flex-basis: 20%; } .conditional-rule-block input[type="email"] { flex-basis: 25%; } .conditional-rule-block .remove-conditional-rule { font-size: 1.5em; text-decoration: none; color: #a00; flex-grow: 0; }
            .conditional-rule-options { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ccc; } .toggle-custom-message { text-decoration: none; font-size: 12px; } .suppress-main-label { font-size: 12px; color: #444; }
            .custom-message-editor { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc; } .custom-message-editor label { font-weight: bold; display: block; margin: 10px 0 5px; } .custom-message-editor input, .custom-message-editor textarea { width: 100%; }
        </style>
        <?php
    }
    add_action( 'admin_footer', 'tw_forms_editor_enqueue_scripts' );
}
