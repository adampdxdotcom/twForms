<?php
/**
 * Creates the Meta Boxes for the Form Editor screen with a Row/Column Layout Builder.
 *
 * @package TW_Forms
 * @version 2.1.1
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
        add_meta_box( 'tw_form_settings_mb', 'Form Settings', 'tw_forms_render_settings_mb', 'tw_form', 'side', 'default' );
        
        // NEW: Add a meta box specifically for displaying the shortcode.
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
            
            <!-- NEW: A dedicated header for the builder controls -->
            <div class="builder-header">
                <h3 class="builder-title">Form Layout</h3>
                <div class="add-row-container">
                    <span>Add Row:</span>
                    <button type="button" class="button add-row-button" data-layout="100">1 Col</button>
                    <button type="button" class="button add-row-button" data-layout="50-50">2 Col</button>
                    <button type="button" class="button add-row-button" data-layout="33-33-33">3 Col</button>
                    <button type="button" class="button add-row-button" data-layout="25-25-25-25">4 Col</button>
                </div>
            </div>

            <div id="layout-container">
                <?php if ( ! empty( $saved_layout ) && is_array( $saved_layout ) ) : ?>
                    <?php foreach ( $saved_layout as $row_index => $row ) : ?>
                        <?php tw_forms_render_row_partial( $row_index, $row ); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="empty-state">No rows yet. Click "Add Row" to begin building your form.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- HIDDEN HTML TEMPLATES --- ?>
        <div id="tw-form-templates" style="display: none;">
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '100', 'columns' => [[]] ], true ); ?>
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '50-50', 'columns' => [[], []] ], true ); ?>
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '33-33-33', 'columns' => [[], [], []] ], true ); ?>
            <?php tw_forms_render_row_partial( '__ROW_INDEX__', [ 'layout' => '25-25-25-25', 'columns' => [[], [], [], []] ], true ); ?>
            <?php tw_forms_render_field_partial( '__ROW_INDEX__', '__COL_INDEX__', '__FIELD_INDEX__', [], true ); ?>
        </div>
        
        <style>
            .tw-form-builder-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
            .builder-header { display: flex; align-items: center; justify-content: space-between; padding: 10px; background: #f9f9f9; border: 1px solid #ccd0d4; border-bottom: none; }
            .builder-header .builder-title { margin: 0; font-size: 1.2em; }
            .add-row-container { display: flex; align-items: center; gap: 10px; }
            #layout-container { border: 1px solid #ccd0d4; background: #fff; padding: 15px; min-height: 150px; }
            #layout-container .empty-state { color: #777; text-align: center; margin: 40px 0; font-size: 1.2em; }
            .row-block { border: 1px solid #999; margin-bottom: 15px; background: #fdfdfd; }
            .row-header { display: flex; justify-content: space-between; align-items: center; background: #e0e0e0; padding: 5px 10px; cursor: move; border-bottom: 1px solid #ccc; }
            .row-header .row-label { font-weight: bold; }
            .row-columns { display: flex; gap: 10px; padding: 10px; }
            .column-block { flex: 1; border: 1px dashed #ccd0d4; background: #f9f9f9; min-height: 80px; padding: 10px; }
            .column-block[data-layout="50-50"] { flex-basis: 50%; } .column-block[data-layout="33-33-33"] { flex-basis: 33.33%; } .column-block[data-layout="25-25-25-25"] { flex-basis: 25%; }
            .column-block .add-field-to-col { width: 100%; margin-top: 5px; }
            .form-field-block { border: 1px solid #ccd0d4; margin-bottom: 10px; background: #fff; }
            .field-header { display: flex; justify-content: space-between; align-items: center; background: #f0f0f1; padding: 8px 12px; cursor: move; }
            .field-header .field-type-label { font-weight: 700; }
            .field-header .field-actions a { text-decoration: none; font-size: 1.4em; margin-left: 10px; }
            .field-settings { padding: 12px; }
            .field-settings .setting-row { margin-bottom: 10px; }
            .field-settings .setting-row-options label { display: inline-block; margin-right: 20px; }
            .field-settings label { display: block; margin-bottom: 5px; font-weight: 500; }
            .field-settings select, .field-settings input[type=text], .field-settings textarea { width: 100%; }
        </style>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_row_partial' ) ) {
    function tw_forms_render_row_partial( $row_index, $row_data, $is_template = false ) {
        $layout = esc_attr( $row_data['layout'] );
        ?>
        <div class="row-block" data-layout="<?php echo $layout; ?>" <?php if($is_template) echo 'id="template-row-'.$layout.'"'; ?>>
            <div class="row-header">
                <span class="row-label">Row (<?php echo str_replace('-', '/', $layout); ?>)</span>
                <div class="row-actions"><a href="#" class="delete-row" title="Delete this row and all its fields">×</a></div>
            </div>
            <div class="row-columns">
                <?php foreach ( $row_data['columns'] as $col_index => $column ) : ?>
                    <div class="column-block" data-layout="<?php echo $layout; ?>">
                        <?php if ( ! empty( $column ) && is_array( $column ) ) : ?>
                            <?php foreach ( $column as $field_index => $field ) : ?>
                                <?php tw_forms_render_field_partial( $row_index, $col_index, $field_index, $field, $is_template ); ?>
                            <?php endforeach; ?>
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
        $needs_confirm = ! empty( $field['confirm'] ); $checkbox_options = esc_textarea( $field['options'] ?? '' ); $checkbox_cols = esc_attr( $field['cols'] ?? '1' );
        $name_base = "tw_form_fields[{$row_index}][columns][{$col_index}][{$field_index}]";
        ?>
        <div class="form-field-block" <?php if($is_template) echo 'id="template-field"'; ?>>
            <div class="field-header"><span class="field-type-label"><?php echo esc_html( ucfirst( $field_type ) ); ?></span><div class="field-actions"><a href="#" class="move-field" title="Drag to reorder">☰</a><a href="#" class="delete-field" title="Delete this field">×</a></div></div>
            <div class="field-settings">
                <div class="setting-row">
                    <label>Field Type</label>
                    <select name="<?php echo $name_base; ?>[type]" class="field-type-select" <?php if($is_template) echo 'disabled'; ?>>
                        <option value="text" <?php selected( $field_type, 'text' ); ?>>Text Input</option>
                        <option value="email" <?php selected( $field_type, 'email' ); ?>>Email Address</option>
                        <option value="tel" <?php selected( $field_type, 'tel' ); ?>>Phone Number</option>
                        <option value="textarea" <?php selected( $field_type, 'textarea' ); ?>>Text Area</option>
                        <option value="checkbox_group" <?php selected( $field_type, 'checkbox_group' ); ?>>Checkbox Group</option>
                        <option value="submit" <?php selected( $field_type, 'submit' ); ?>>Submit Button</option>
                    </select>
                </div>
                <div class="setting-row"><label>Field Label</label><input type="text" name="<?php echo $name_base; ?>[label]" value="<?php echo $field_label; ?>" placeholder="e.g., Your Full Name" <?php if($is_template) echo 'disabled'; ?>></div>
                <div class="setting-row setting-row-options">
                    <label><input type="checkbox" name="<?php echo $name_base; ?>[required]" value="1" <?php checked( $is_required ); ?> <?php if($is_template) echo 'disabled'; ?>> Required?</label>
                    <label class="confirm-email-option" style="<?php echo ( $field_type === 'email' ) ? '' : 'display:none;'; ?>"><input type="checkbox" name="<?php echo $name_base; ?>[confirm]" value="1" <?php checked( $needs_confirm ); ?> <?php if($is_template) echo 'disabled'; ?>> Confirm?</label>
                </div>
                <div class="checkbox-options-panel" style="<?php echo ( $field_type === 'checkbox_group' ) ? '' : 'display:none;'; ?>">
                    <div class="setting-row"><label>Checkbox Options (one per line)</label><textarea name="<?php echo $name_base; ?>[options]" rows="4" <?php if($is_template) echo 'disabled'; ?>><?php echo $checkbox_options; ?></textarea></div>
                    <div class="setting-row"><label>Display in Columns</label><select name="<?php echo $name_base; ?>[cols]" <?php if($is_template) echo 'disabled'; ?>><option value="1" <?php selected($checkbox_cols, '1'); ?>>1 Column</option><option value="2" <?php selected($checkbox_cols, '2'); ?>>2 Columns</option><option value="3" <?php selected($checkbox_cols, '3'); ?>>3 Columns</option></select></div>
                </div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_render_settings_mb' ) ) {
    function tw_forms_render_settings_mb( $post ) {
        $recipients = get_post_meta( $post->ID, '_tw_form_recipients', true );
        ?>
        <div class="tw-form-settings-wrapper"><p><label for="tw_form_recipients"><strong>Send Notifications To:</strong></label><input type="text" id="tw_form_recipients" name="tw_form_recipients" value="<?php echo esc_attr( $recipients ); ?>" style="width:100%;"></p><p class="description">Enter email addresses, comma-separated.</p></div>
        <?php
    }
}

// NEW: Render function for our shortcode meta box.
if ( ! function_exists( 'tw_forms_render_shortcode_mb' ) ) {
    function tw_forms_render_shortcode_mb( $post ) {
        $shortcode = '[tw_form id="' . $post->ID . '"]';
        ?>
        <div class="tw-shortcode-wrapper">
            <p>Paste this shortcode onto any page or post to display this form.</p>
            <input type="text" readonly="readonly" value="<?php echo esc_attr( $shortcode ); ?>" onclick="this.select();" style="width: 100%; text-align: center; font-weight: bold; padding: 5px; cursor: copy;">
        </div>
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

        $sanitized_layout = [];
        if ( isset( $_POST['tw_form_fields'] ) && is_array( $_POST['tw_form_fields'] ) ) {
            foreach ( $_POST['tw_form_fields'] as $row_data ) {
                if ( ! is_array( $row_data ) || ! isset( $row_data['columns'] ) ) continue;
                $sanitized_row = [ 'layout' => sanitize_key( $row_data['layout'] ?? '100' ), 'columns' => [] ];
                foreach ( $row_data['columns'] as $column_data ) {
                    $sanitized_column = [];
                    if ( is_array( $column_data ) ) {
                        foreach ( $column_data as $field_data ) {
                            $sanitized_column[] = [
                                'type'     => sanitize_key( $field_data['type'] ?? 'text' ),
                                'label'    => sanitize_text_field( $field_data['label'] ?? '' ),
                                'required' => isset( $field_data['required'] ) ? 1 : 0,
                                'confirm'  => isset( $field_data['confirm'] ) ? 1 : 0,
                                'options'  => sanitize_textarea_field( $field_data['options'] ?? '' ),
                                'cols'     => sanitize_key( $field_data['cols'] ?? '1' ),
                            ];
                        }
                    }
                    $sanitized_row['columns'][] = $sanitized_column;
                }
                $sanitized_layout[] = $sanitized_row;
            }
        }
        update_post_meta( $post_id, '_tw_form_layout', $sanitized_layout );

        if ( isset( $_POST['tw_form_recipients'] ) ) {
            $emails_raw = explode( ',', $_POST['tw_form_recipients'] ); $sanitized_emails = [];
            foreach ( $emails_raw as $email ) { if ( is_email( trim( $email ) ) ) { $sanitized_emails[] = trim( $email ); } }
            update_post_meta( $post_id, '_tw_form_recipients', implode( ', ', $sanitized_emails ) );
        }
    }
    add_action( 'save_post_tw_form', 'tw_forms_save_meta_box_data' );
}

// -----------------------------------------------------------------------------
// 4. ENQUEUE EDITOR JAVASCRIPT
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
                const layoutContainer = $('#layout-container');
                const templates = $('#tw-form-templates');

                function getUniqueIndex() { return new Date().getTime(); }
                function updateEmptyState() { if (layoutContainer.find('.row-block').length === 0) { layoutContainer.html('<p class="empty-state">No rows yet. Click "Add Row" to begin building your form.</p>'); } else { layoutContainer.find('.empty-state').remove(); } }
                function reindexNames() {
                    layoutContainer.find('.row-block').each(function(rowIndex) {
                        $(this).find('input[name*="[layout]"], select[name*="[layout]"]').val($(this).data('layout'));
                        $(this).find('[name*="tw_form_fields"]').each(function() {
                            this.name = this.name.replace(/tw_form_fields\[.*?\]/, 'tw_form_fields[' + rowIndex + ']');
                        });
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

                $('.add-row-button').on('click', function() {
                    updateEmptyState();
                    const layout = $(this).data('layout');
                    const newRow = templates.find('#template-row-' + layout).clone().removeAttr('id');
                    const rowIndex = getUniqueIndex();
                    
                    newRow.append('<input type="hidden" name="tw_form_fields[' + rowIndex + '][layout]" value="' + layout + '">');
                    newRow.find('[name*="__ROW_INDEX__"]').each(function() { this.name = this.name.replace(/__ROW_INDEX__/g, rowIndex); });
                    
                    layoutContainer.append(newRow);
                    initSortables();
                    reindexNames();
                });

                layoutContainer.on('click', '.add-field-to-col', function() {
                    const column = $(this).closest('.column-block');
                    const newField = templates.find('#template-field').clone().removeAttr('id');
                    newField.find('[name*="__INDEX__"]').each(function() { $(this).prop('disabled', false); });
                    newField.insertBefore($(this));
                    reindexNames();
                });

                layoutContainer.on('click', '.delete-row', function(e) { e.preventDefault(); if (confirm('Delete this entire row?')) { $(this).closest('.row-block').remove(); updateEmptyState(); reindexNames(); } });
                layoutContainer.on('click', '.delete-field', function(e) { e.preventDefault(); if (confirm('Delete this field?')) { $(this).closest('.form-field-block').remove(); reindexNames(); } });
                
                layoutContainer.on('change', '.field-type-select', function() {
                    const fieldBlock = $(this).closest('.form-field-block');
                    const selectedType = $(this).val();
                    fieldBlock.find('.field-type-label').text($(this).find('option:selected').text());
                    fieldBlock.find('.confirm-email-option').toggle(selectedType === 'email');
                    fieldBlock.find('.checkbox-options-panel').toggle(selectedType === 'checkbox_group');
                });
                
                initSortables();
            });
        </script>
        <style>.row-placeholder, .field-placeholder{border:2px dashed #ccd0d4;background-color:#f0f8ff;margin-bottom:15px;box-sizing:border-box;}</style>
        <?php
    }
    add_action( 'admin_footer', 'tw_forms_editor_enqueue_scripts' );
}
