<?php
/**
 * Handles the universal [tw_form] shortcode, layout rendering, and processing.
 *
 * @package TW_Forms
 * @version 2.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == UNIVERSAL FORM SHORTCODE, RENDERER, & PROCESSOR
// =============================================================================

if ( ! function_exists( 'tw_forms_universal_shortcode_handler' ) ) {

    function tw_forms_universal_shortcode_handler( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0, ], $atts, 'tw_form' );
        $form_id = intval( $atts['id'] );
        if ( ! $form_id ) return '<p style="color: red;">Error: Form ID is missing or invalid.</p>';

        $form_post = get_post( $form_id );
        if ( ! $form_post || 'tw_form' !== get_post_type( $form_post ) ) return '<p style="color: red;">Error: Form not found.</p>';
        
        $saved_layout = get_post_meta( $form_id, '_tw_form_layout', true );
        $status_message = '';
        $submitted_values = [];

        // --- Full Submission Processing ---
        if ( isset( $_POST['submit_tw_form'] ) && isset( $_POST['tw_form_id'] ) && intval( $_POST['tw_form_id'] ) === $form_id ) {
            
            if ( ! isset( $_POST['tw_form_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_nonce'], 'process_tw_form_' . $form_id ) ) {
                 $status_message = '<p style="color: red;">Security check failed. Please try again.</p>';
            } else {
                
                $errors = [];
                $form_data = $_POST['tw_form_fields'] ?? [];
                $submitted_values = $form_data;

                // --- Validation Loop (Nested) ---
                if ( ! empty( $saved_layout ) && is_array( $saved_layout ) ) {
                    foreach ( $saved_layout as $row_index => $row ) {
                        foreach ( $row['columns'] as $col_index => $column ) {
                            foreach ( $column as $field_index => $field ) {
                                if ( $field['type'] === 'submit' ) continue;

                                $value = isset( $form_data[$row_index][$col_index][$field_index] ) ? $form_data[$row_index][$col_index][$field_index] : '';
                                if ( is_string($value) ) $value = trim($value);

                                if ( ! empty( $field['required'] ) && empty( $value ) ) {
                                    $errors[] = 'The "' . esc_html( $field['label'] ) . '" field is required.';
                                }

                                if ( $field['type'] === 'email' && ! empty( $value ) && ! is_email( $value ) ) {
                                    $errors[] = 'Please enter a valid email for "' . esc_html( $field['label'] ) . '".';
                                }

                                if ( ! empty( $field['confirm'] ) ) {
                                    $confirm_key = $row_index . '_' . $col_index . '_' . $field_index . '_confirm';
                                    $confirm_value = isset( $_POST['tw_form_fields_confirm'][$confirm_key] ) ? trim( $_POST['tw_form_fields_confirm'][$confirm_key] ) : '';
                                    if ( $value !== $confirm_value ) {
                                        $errors[] = 'The email addresses for "' . esc_html( $field['label'] ) . '" do not match.';
                                    }
                                }
                            }
                        }
                    }
                }

                // --- Process if No Errors ---
                if ( empty( $errors ) ) {
                    $submitted_values = []; // Clear form on success
                    
                    $submitted_data_string = '';
                    $user_name = 'Guest'; $user_email = ''; $user_phone = '';
                    
                    foreach ( $saved_layout as $row_index => $row ) {
                        foreach ( $row['columns'] as $col_index => $column ) {
                            foreach ( $column as $field_index => $field ) {
                                $label = $field['label'] ?? 'Field';
                                $value_raw = isset( $form_data[$row_index][$col_index][$field_index] ) ? $form_data[$row_index][$col_index][$field_index] : 'N/A';
                                
                                if ( $field['type'] === 'submit' ) continue;

                                if ( is_array( $value_raw ) ) {
                                    $value = implode(', ', array_map('sanitize_text_field', $value_raw));
                                } else {
                                    $value = sanitize_textarea_field( $value_raw );
                                }

                                $submitted_data_string .= esc_html( $label ) . ":\n" . $value . "\n\n";
                                
                                if ( stripos( $label, 'name' ) !== false && $user_name === 'Guest' ) $user_name = $value;
                                if ( $field['type'] === 'email' && empty($user_email) ) $user_email = $value;
                                if ( $field['type'] === 'tel' && empty($user_phone) ) $user_phone = $value;
                            }
                        }
                    }

                    log_form_submission_to_pods([ 'messenger_name' => $user_name, 'phone' => $user_phone, 'email' => $user_email, 'message' => $submitted_data_string, 'form_source' => $form_post->post_title ]);
                    $admin_recipients = get_post_meta( $form_id, '_tw_form_recipients', true );
                    if ( ! empty( $admin_recipients ) ) {
                        $admin_subject = "New Submission: " . $form_post->post_title;
                        $admin_body = "You have received a new submission from the \"" . $form_post->post_title . "\" form.\n\n--- Submitted Data ---\n" . $submitted_data_string;
                        $headers = [ 'Reply-To: ' . $user_name . ' <' . $user_email . '>' ];
                        wp_mail( $admin_recipients, $admin_subject, $admin_body, $headers );
                    }
                    if ( ! empty( $user_email ) ) { send_user_confirmation_email( $user_email, $user_name, $form_post->post_title, $submitted_data_string ); }
                    $status_message = '<p style="color: green;">Thank you! Your submission has been received.</p>';
                } else {
                    $status_message = '<ul style="color: red; border: 1px solid red; padding: 15px; list-style-position: inside;">';
                    foreach ( $errors as $error ) { $status_message .= '<li>' . $error . '</li>'; }
                    $status_message .= '</ul>';
                }
            }
        }
        
        // --- Render Form ---
        ob_start(); ?>
        <div class="tw-form-container">
            <div id="tw-form-status-<?php echo esc_attr( $form_id ); ?>" class="form-status-message"><?php echo $status_message; ?></div>
            <form method="post" action="#tw-form-status-<?php echo esc_attr( $form_id ); ?>" id="tw-form-<?php echo esc_attr( $form_id ); ?>">
                <input type="hidden" name="tw_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <?php wp_nonce_field( 'process_tw_form_' . $form_id, 'tw_form_nonce' ); ?>
                <?php if ( ! empty( $saved_layout ) && is_array( $saved_layout ) ) : ?>
                    <?php foreach ( $saved_layout as $row_index => $row ) : ?>
                        <div class="tw-form-row">
                        <?php foreach ( $row['columns'] as $col_index => $column ) : ?>
                            <div class="tw-form-col tw-form-col-<?php echo esc_attr( count($row['columns']) ); ?>">
                            <?php foreach ( $column as $field_index => $field ) :
                                $field_type = $field['type'] ?? 'text'; $field_label = $field['label'] ?? ''; $is_required = ! empty( $field['required'] ); $needs_confirm = ! empty( $field['confirm'] );
                                $field_id = 'tw-field-'.esc_attr($form_id).'-'.esc_attr($row_index).'-'.esc_attr($col_index).'-'.esc_attr($field_index);
                                $field_name = 'tw_form_field['.esc_attr($row_index).']['.esc_attr($col_index).']['.esc_attr($field_index).']';
                                $required_html = $is_required ? ' required' : ''; $required_span = $is_required ? ' <span style="color:red;">*</span>' : '';
                                $repop_value = isset( $submitted_values[$row_index][$col_index][$field_index] ) ? $submitted_values[$row_index][$col_index][$field_index] : '';
                                ?>
                                <div class="tw-form-field-wrapper tw-field-type-<?php echo $field_type; ?>">
                                    <?php switch ( $field_type ) :
                                        case 'text': case 'email': case 'tel': ?>
                                            <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <input type="<?php echo $field_type; ?>" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" value="<?php echo esc_attr($repop_value); ?>"<?php echo $required_html; ?>>
                                            <?php if ( $field_type === 'email' && $needs_confirm ) : ?>
                                                <div class="tw-form-field-wrapper tw-field-type-email-confirm" style="margin-top:10px;">
                                                <label for="<?php echo $field_id; ?>-confirm">Confirm <?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                                <input type="email" id="<?php echo $field_id; ?>-confirm" name="tw_form_fields_confirm[<?php echo esc_attr($row_index.'_'.$col_index.'_'.$field_index); ?>_confirm]"<?php echo $required_html; ?>>
                                                </div>
                                            <?php endif; break;
                                        case 'textarea': ?>
                                            <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" rows="5"<?php echo $required_html; ?>><?php echo esc_textarea( $repop_value ); ?></textarea>
                                            <?php break;
                                        case 'checkbox_group':
                                            $options = explode("\n", str_replace("\r", "", $field['options'] ?? ''));
                                            $cols = intval($field['cols'] ?? 1); ?>
                                            <label><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <div class="tw-checkbox-group" style="columns: <?php echo $cols; ?>; -webkit-columns: <?php echo $cols; ?>; -moz-columns: <?php echo $cols; ?>;">
                                            <?php foreach ($options as $opt_idx => $option_label) : $option_label = trim($option_label); if(empty($option_label)) continue; ?>
                                                <label><input type="checkbox" name="<?php echo $field_name; ?>[]" value="<?php echo esc_attr($option_label); ?>" <?php if(is_array($repop_value) && in_array($option_label, $repop_value)) echo 'checked'; ?>> <?php echo esc_html($option_label); ?></label><br>
                                            <?php endforeach; ?>
                                            </div>
                                            <?php break;
                                        case 'submit': ?>
                                            <button type="submit" name="submit_tw_form" class="custom-form-submit-button"><?php echo esc_html( $field_label ?: 'Submit' ); ?></button>
                                            <?php break;
                                    endswitch; ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
        
        enqueue_form_spam_protection_scripts();
        tw_forms_print_layout_css(); // Helper to add our CSS
        return ob_get_clean();
    }
    add_shortcode( 'tw_form', 'tw_forms_universal_shortcode_handler' );
}

// =============================================================================
// == HELPER SCRIPTS & STYLES
// =============================================================================
if ( ! function_exists('tw_forms_print_layout_css') ) {
    function tw_forms_print_layout_css() {
        static $css_printed = false; if ($css_printed) return;
        ?>
        <style>
            .tw-form-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
            .tw-form-col { flex: 1 1 0; min-width: 0; }
            .tw-form-col-2 { flex-basis: calc(50% - 10px); }
            .tw-form-col-3 { flex-basis: calc(33.33% - 14px); }
            .tw-form-col-4 { flex-basis: calc(25% - 15px); }
            .tw-form-field-wrapper label { display: block; margin-bottom: 8px; font-weight: bold; }
            .tw-form-field-wrapper input[type="text"], .tw-form-field-wrapper input[type="email"], .tw-form-field-wrapper input[type="tel"], .tw-form-field-wrapper textarea { width: 100%; padding: 12px; box-sizing: border-box; }
            .tw-checkbox-group label { font-weight: normal; margin-bottom: 5px; }
            .tw-checkbox-group input { margin-right: 8px; }
        </style>
        <?php
        $css_printed = true;
    }
}
if ( ! function_exists('enqueue_form_spam_protection_scripts') ) { function enqueue_form_spam_protection_scripts() { static $s=false; if($s){return;} $o=get_option('my_recaptcha_settings',[]); if(!empty($o['disable'])){return;} $k=$o['site_key']??''; if(!empty($k)){wp_enqueue_script('google-recaptcha','https://www.google.com/recaptcha/api.js?render='.esc_attr($k),[],null,true);$s=true;}}}
if ( ! function_exists('add_recaptcha_form_submission_script') ) { function add_recaptcha_form_submission_script() { /* This will be re-implemented later */ } add_action('wp_footer', 'add_recaptcha_form_submission_script'); }
if ( ! function_exists('custom_recaptcha_badge_styles') ) { function custom_recaptcha_badge_styles() { echo '<style>.grecaptcha-badge { left: 15px !important; right: auto !important; }</style>'; } add_action('wp_head', 'custom_recaptcha_badge_styles');}
