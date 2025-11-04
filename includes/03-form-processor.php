<?php
/**
 * Handles the universal [tw_form] shortcode, AJAX processing, and layout rendering.
 *
 * @package TW_Forms
 * @version 2.6.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == 1. DEDICATED FORM PROCESSING FUNCTION
// =============================================================================
if ( ! function_exists( 'tw_forms_process_submission' ) ) {
    function tw_forms_process_submission() {
        $form_id = isset( $_POST['tw_form_id'] ) ? intval( $_POST['tw_form_id'] ) : 0;
        if ( ! $form_id ) return [ 'success' => false, 'message' => '<p style="color: red;">Invalid form submission.</p>' ];

        // --- 1. Spam & Security Checks ---
        if ( ! empty($_POST['user_nickname_h']) ) { return [ 'success' => true, 'message' => '<p style="color: green;">Thank you for your submission.</p>' ]; }
        $time_check = isset($_POST['form_timestamp_h']) ? (time() - intval(base64_decode($_POST['form_timestamp_h']))) : 999; if ($time_check < 3) { return [ 'success' => true, 'message' => '<p style="color: green;">Thank you for your submission.</p>' ]; }
        if ( ! isset( $_POST['tw_form_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_nonce'], 'process_tw_form_' . $form_id ) ) { return [ 'success' => false, 'message' => '<p style="color: red;">Security check failed.</p>' ]; }
        if (!verify_recaptcha_v3($_POST['recaptcha_token'] ?? '')) { return [ 'success' => true, 'message' => '<p style="color: green;">Thank you for your submission.</p>' ]; }

        // --- 2. Validation Loop ---
        $saved_layout = get_post_meta( $form_id, '_tw_form_layout', true );
        $errors = []; $form_data = $_POST['tw_form_fields'] ?? [];
        if ( ! empty( $saved_layout ) && is_array( $saved_layout ) ) {
            foreach ( $saved_layout as $row_index => $row ) {
                foreach ( $row['columns'] as $col_index => $column ) {
                    foreach ( $column as $field_index => $field ) {
                        if ( in_array($field['type'], ['submit','section_header','html_block']) ) continue;
                        $value = isset( $form_data[$row_index][$col_index][$field_index] ) ? ( is_string( $form_data[$row_index][$col_index][$field_index] ) ? trim($form_data[$row_index][$col_index][$field_index]) : $form_data[$row_index][$col_index][$field_index] ) : '';
                        if ( ! empty( $field['required'] ) && empty( $value ) ) { $errors[] = 'The "' . esc_html( $field['label'] ) . '" field is required.'; }
                        if ( $field['type'] === 'email' && ! empty( $value ) && ! is_email( $value ) ) { $errors[] = 'Please enter a valid email for "' . esc_html( $field['label'] ) . '".'; }
                        if ( ! empty( $field['confirm'] ) ) {
                            $confirm_key = $row_index.'_'.$col_index.'_'.$field_index.'_confirm';
                            $confirm_value = isset( $_POST['tw_form_fields_confirm'][$confirm_key] ) ? trim( $_POST['tw_form_fields_confirm'][$confirm_key] ) : '';
                            if ( $value !== $confirm_value ) { $errors[] = 'The email addresses for "' . esc_html( $field['label'] ) . '" do not match.'; }
                        }
                    }
                }
            }
        }

        // --- 3. Process if No Errors ---
        if ( empty( $errors ) ) {
            $form_post = get_post($form_id);
            
            // --- 3a. Collect and Map All Submitted Data ---
            $data_map          = [];
            $all_fields_html   = '';
            $all_fields_text   = '';
            $user_email_address = '';
            $user_name_guess   = 'Guest';
            $user_phone_guess  = '';

            foreach ( $saved_layout as $row_index => $row ) {
                foreach ( $row['columns'] as $col_index => $column ) {
                    foreach ( $column as $field_index => $field ) {
                        if ( in_array($field['type'], ['submit','section_header','html_block']) ) continue;
                        
                        $label = $field['label'] ?? 'Field';
                        $value_raw = isset( $form_data[$row_index][$col_index][$field_index] ) ? $form_data[$row_index][$col_index][$field_index] : '';
                        $value = is_array( $value_raw ) ? implode(', ', array_map('sanitize_text_field', $value_raw)) : sanitize_textarea_field( $value_raw );
                        
                        $data_map[$label] = $value;
                        $all_fields_html .= '<p style="margin: 0 0 10px 0;"><strong>' . esc_html($label) . ':</strong><br>' . nl2br(esc_html($value)) . '</p>';
                        $all_fields_text .= esc_html($label) . ":\n" . $value . "\n\n";
                        
                        if ( $field['type'] === 'email' && empty($user_email_address) ) {
                            $user_email_address = $value;
                        }
                        if ( stripos( $label, 'name' ) !== false && $user_name_guess === 'Guest' ) $user_name_guess = $value;
                        if ( $field['type'] === 'tel' && empty($user_phone_guess) ) $user_phone_guess = $value;
                    }
                }
            }
            
            // --- 3b. Log to Pods ---
            log_form_submission_to_pods(
                ['messenger_name' => $user_name_guess, 'phone' => $user_phone_guess, 'email' => $user_email_address, 'message' => $all_fields_text, 'form_source' => $form_post->post_title],
                $data_map
            );
            
            // --- 3c. Send Main Admin Notification Email ---
            $admin_email_settings = get_post_meta( $form_id, '_tw_form_admin_email', true );
            $recipients = $admin_email_settings['to'] ?? get_post_meta( $form_id, '_tw_form_recipients', true );
            if ( empty($recipients) ) { $recipients = get_option('admin_email'); }

            if ( ! empty( $recipients ) && ! empty( $admin_email_settings ) && is_array( $admin_email_settings ) ) {
                $subject    = tw_forms_process_tags( $admin_email_settings['subject'], $data_map, $form_post, $all_fields_html );
                $message    = tw_forms_process_tags( $admin_email_settings['message'], $data_map, $form_post, $all_fields_html );
                $from_name  = get_bloginfo('name');
                $from_email = get_option('admin_email');
                $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$from_name} <{$from_email}>"];
                wp_mail( $recipients, $subject, $message, $headers );
            }
            
            // --- 3d. Process Conditional Notifications ---
            $conditional_rules = get_post_meta( $form_id, '_tw_form_conditional_rules', true );
            if ( ! empty( $conditional_rules ) && is_array( $conditional_rules ) ) {
                foreach ( $conditional_rules as $rule ) {
                    $trigger_field   = $rule['field'];
                    $expected_value  = $rule['value'];
                    $recipient_email = $rule['recipient'];

                    // Find the submitted value from our data map using the field label as the key
                    $submitted_value = $data_map[ $trigger_field ] ?? null;

                    if ( $submitted_value !== null ) {
                        $condition_met = false;
                        
                        // Handle both string and array (for checkboxes) comparisons
                        $submitted_values = is_array($submitted_value) ? $submitted_value : explode(', ', $submitted_value);
                        if ( in_array( $expected_value, $submitted_values ) ) {
                            $condition_met = true;
                        }

                        if ( $condition_met ) {
                            $conditional_subject = "New Alert from " . $form_post->post_title . ": " . $trigger_field;
                            $conditional_body    = "A new submission on the '{$form_post->post_title}' form triggered this notification because the '{$trigger_field}' field was set to '{$expected_value}'.\n\n";
                            $conditional_body   .= "Submitted by: " . ($user_name_guess ?? 'N/A') . "\n";
                            $conditional_body   .= "Email Address: " . ($user_email_address ?? 'N/A') . "\n";
                            
                            $from_name  = get_bloginfo('name');
                            $from_email = get_option('admin_email');
                            $headers    = ["From: {$from_name} <{$from_email}>"];
                            
                            wp_mail( $recipient_email, $conditional_subject, $conditional_body, $headers );
                        }
                    }
                }
            }

            // --- 3e. Send User Confirmation Email (Autoresponder) ---
            $user_email_settings = get_post_meta( $form_id, '_tw_form_user_email', true );
            
            if ( ! empty( $user_email_settings['enabled'] ) && ! empty( $user_email_address ) && is_array( $user_email_settings ) ) {
                $subject    = tw_forms_process_tags( $user_email_settings['subject'], $data_map, $form_post, $all_fields_html );
                $message    = tw_forms_process_tags( $user_email_settings['message'], $data_map, $form_post, $all_fields_html );
                $from_name  = get_bloginfo('name');
                $from_email = get_option('admin_email');
                $headers = ['Content-Type: text/html; charset=UTF-8', "From: {$from_name} <{$from_email}>", "Reply-To: {$from_name} <{$from_email}>"];
                wp_mail( $user_email_address, $subject, $message, $headers );
            }

            return [ 'success' => true, 'message' => '<p style="color: green;">Thank you! Your submission has been received.</p>' ];
        } else {
            $error_message = '<ul class="tw-form-errors">';
            foreach ( $errors as $error ) { $error_message .= '<li>' . $error . '</li>'; }
            $error_message .= '</ul>';
            return [ 'success' => false, 'message' => $error_message ];
        }
    }
}

// =============================================================================
// == 2. AJAX ENDPOINT
// =============================================================================
if ( ! function_exists( 'tw_forms_ajax_handler' ) ) {
    function tw_forms_ajax_handler() {
        $result = tw_forms_process_submission();
        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }
    add_action( 'wp_ajax_tw_forms_submit', 'tw_forms_ajax_handler' );
    add_action( 'wp_ajax_nopriv_tw_forms_submit', 'tw_forms_ajax_handler' );
}

// =============================================================================
// == 3. UNIVERSAL FORM RENDERER (SHORTCODE)
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

        if ( isset( $_POST['submit_tw_form'] ) && ! wp_doing_ajax() ) {
            $result = tw_forms_process_submission();
            $status_message = $result['message'];
            if ( ! $result['success'] ) {
                $submitted_values = $_POST['tw_form_fields'] ?? [];
            }
        }
        
        ob_start(); ?>
        <div class="tw-form-container">
            <div id="tw-form-status-<?php echo esc_attr( $form_id ); ?>" class="form-status-message"><?php echo $status_message; ?></div>
            <form method="post" action="#tw-form-status-<?php echo esc_attr( $form_id ); ?>" id="tw-form-<?php echo esc_attr( $form_id ); ?>" class="tw-form">
                <input type="hidden" name="tw_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <?php wp_nonce_field( 'process_tw_form_' . $form_id, 'tw_form_nonce' ); ?>
                <div style="position: absolute; left: -5000px;" aria-hidden="true"><label>Do not fill this out</label><input type="text" name="user_nickname_h" tabindex="-1" autocomplete="off"></div>
                <input type="hidden" name="form_timestamp_h" value="<?php echo base64_encode(time()); ?>">

                <?php if ( ! empty( $saved_layout ) && is_array( $saved_layout ) ) : ?>
                    <?php foreach ( $saved_layout as $row_index => $row ) : ?>
                        <div class="tw-form-row">
                        <?php foreach ( $row['columns'] as $col_index => $column ) : ?>
                            <div class="tw-form-col tw-form-col-<?php echo esc_attr( count($row['columns']) ); ?>">
                            <?php foreach ( $column as $field_index => $field ) :
                                $field_type = $field['type'] ?? 'text'; $field_label = $field['label'] ?? ''; $is_required = ! empty( $field['required'] ); $needs_confirm = ! empty( $field['confirm'] );
                                $html_content = $field['html_content'] ?? ''; $placeholder_text = $field['placeholder'] ?? '';
                                $field_id = 'tw-field-'.esc_attr($form_id).'-'.esc_attr($row_index).'-'.esc_attr($col_index).'-'.esc_attr($field_index);
                                
                                $field_name = 'tw_form_fields['.esc_attr($row_index).']['.esc_attr($col_index).']['.esc_attr($field_index).']';
                                
                                $required_html = $is_required ? ' required' : ''; $required_span = $is_required ? ' <span style="color:red;">*</span>' : '';
                                $repop_value = isset( $submitted_values[$row_index][$col_index][$field_index] ) ? $submitted_values[$row_index][$col_index][$field_index] : '';
                                ?>
                                <div class="tw-form-field-wrapper tw-field-type-<?php echo $field_type; ?>">
                                    <?php switch ( $field_type ) :
                                        case 'text': case 'email': case 'tel': ?>
                                            <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <input type="<?php echo $field_type; ?>" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" value="<?php echo esc_attr($repop_value); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>"<?php echo $required_html; ?>>
                                            <?php if ( $field_type === 'email' && $needs_confirm ) : ?>
                                                <div class="tw-form-field-wrapper tw-field-type-email-confirm" style="margin-top:10px;"><label for="<?php echo $field_id; ?>-confirm">Confirm <?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label><input type="email" id="<?php echo $field_id; ?>-confirm" name="tw_form_fields_confirm[<?php echo esc_attr($row_index.'_'.$col_index.'_'.$field_index); ?>_confirm]" placeholder="<?php echo esc_attr($placeholder_text); ?>"<?php echo $required_html; ?>></div>
                                            <?php endif; break;
                                        case 'textarea': ?>
                                            <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" rows="5" placeholder="<?php echo esc_attr($placeholder_text); ?>"<?php echo $required_html; ?>><?php echo esc_textarea( $repop_value ); ?></textarea>
                                            <?php break;
                                        case 'dropdown':
                                            $options = explode("\n", str_replace("\r", "", $field['options'] ?? '')); ?>
                                            <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <select id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"<?php echo $required_html; ?>>
                                                <option value="">-- Select an Option --</option>
                                                <?php foreach ($options as $option_label) : $option_label = trim($option_label); if(empty($option_label)) continue; ?>
                                                    <option value="<?php echo esc_attr($option_label); ?>" <?php selected($repop_value, $option_label); ?>><?php echo esc_html($option_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php break;
                                        case 'radio_group':
                                            $options = explode("\n", str_replace("\r", "", $field['options'] ?? '')); $cols = intval($field['cols'] ?? 1); ?>
                                            <label><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <div class="tw-radio-group" style="columns: <?php echo $cols; ?>; -webkit-columns: <?php echo $cols; ?>; -moz-columns: <?php echo $cols; ?>;">
                                            <?php foreach ($options as $opt_idx => $option_label) : $option_label = trim($option_label); if(empty($option_label)) continue; ?>
                                                <label><input type="radio" name="<?php echo $field_name; ?>" value="<?php echo esc_attr($option_label); ?>" <?php checked($repop_value, $option_label); ?>> <?php echo esc_html($option_label); ?></label><br>
                                            <?php endforeach; ?>
                                            </div>
                                            <?php break;
                                        case 'checkbox_group':
                                            $options = explode("\n", str_replace("\r", "", $field['options'] ?? '')); $cols = intval($field['cols'] ?? 1); ?>
                                            <label><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                            <div class="tw-checkbox-group" style="columns: <?php echo $cols; ?>; -webkit-columns: <?php echo $cols; ?>; -moz-columns: <?php echo $cols; ?>;"><?php foreach ($options as $opt_idx => $option_label) : $option_label = trim($option_label); if(empty($option_label)) continue; ?><label><input type="checkbox" name="<?php echo $field_name; ?>[]" value="<?php echo esc_attr($option_label); ?>" <?php if(is_array($repop_value) && in_array($option_label, $repop_value)) echo 'checked'; ?>> <?php echo esc_html($option_label); ?></label><br><?php endforeach; ?></div>
                                            <?php break;
                                        case 'section_header': ?>
                                            <h3 class="tw-form-section-header"><?php echo esc_html( $field_label ); ?></h3>
                                            <?php break;
                                        case 'html_block': ?>
                                            <div class="tw-form-html-block"><?php echo wp_kses_post( $html_content ); ?></div>
                                            <?php break;
                                        case 'submit': ?>
                                            <button type="submit" name="submit_tw_form" class="custom-form-submit-button"><?php echo esc_html( $field_label ?: 'Submit' ); ?></button>
                                            <?php break;
                                    endswitch; ?>
                                </div>
                            <?php endforeach; ?></div>
                        <?php endforeach; ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
        enqueue_form_spam_protection_scripts(); tw_forms_print_layout_css(); return ob_get_clean();
    }
    add_shortcode( 'tw_form', 'tw_forms_universal_shortcode_handler' );
}

// =============================================================================
// == 4. HELPER SCRIPTS & STYLES
// =============================================================================
if ( ! function_exists('tw_forms_print_layout_css') ) {
    function tw_forms_print_layout_css() {
        static $css_printed = false; if ($css_printed) return; ?>
        <style>
            .tw-form-errors { list-style-type: none !important; margin: 0 0 20px 0 !important; padding: 1rem !important; background-color: #f8d7da !important; color: #721c24 !important; border: 1px solid #f5c6cb !important; border-radius: 4px !important; }
            .tw-form-errors li { margin-bottom: 0.5rem !important; } .tw-form-errors li:last-child { margin-bottom: 0 !important; }
            .tw-form-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
            .tw-form-col { flex: 1 1 0; min-width: 0; }
            .tw-form-col-2 { flex-basis: calc(50% - 10px); } .tw-form-col-3 { flex-basis: calc(33.33% - 14px); } .tw-form-col-4 { flex-basis: calc(25% - 15px); }
            .tw-form-field-wrapper label { display: block; margin-bottom: 8px; font-weight: bold; }
            .tw-form-field-wrapper input[type="text"], .tw-form-field-wrapper input[type="email"], .tw-form-field-wrapper input[type="tel"], .tw-form-field-wrapper textarea, .tw-form-field-wrapper select { width: 100%; padding: 12px; box-sizing: border-box; }
            .tw-checkbox-group label, .tw-radio-group label { font-weight: normal; margin-bottom: 5px; display: block; } 
            .tw-checkbox-group input, .tw-radio-group input { margin-right: 8px; }
            .tw-field-type-submit { margin-top: 10px; }
            .tw-form-section-header { margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
            .tw-form-html-block p:first-child { margin-top: 0; } .tw-form-html-block p:last-child { margin-bottom: 0; }
        </style>
        <?php $css_printed = true;
    }
}

if ( ! function_exists('enqueue_form_spam_protection_scripts') ) {
    function enqueue_form_spam_protection_scripts() {
        static $scripts_enqueued = false; if ($scripts_enqueued) { return; }
        $recaptcha_options = get_option('my_recaptcha_settings',[]);
        if ( !empty($recaptcha_options['disable']) ) { return; }
        $site_key = $recaptcha_options['site_key'] ?? '';
        if (!empty($site_key)) {
            wp_enqueue_script('google-recaptcha','https://www.google.com/recaptcha/api.js?render='.esc_attr($site_key),[],null,true);
            $scripts_enqueued = true;
        }
    }
}

if ( ! function_exists('add_recaptcha_form_submission_script') ) {
    function add_recaptcha_form_submission_script() {
        // This function is now deprecated as its logic has been moved to assets/js/form-submit.js
    }
    // The hook is intentionally left here but the function is empty.
    add_action('wp_footer', 'add_recaptcha_form_submission_script');
}

if ( ! function_exists('custom_recaptcha_badge_styles') ) {
    function custom_recaptcha_badge_styles() {
        echo '<style>.grecaptcha-badge { left: 15px !important; right: auto !important; }</style>';
    }
    add_action('wp_head', 'custom_recaptcha_badge_styles');
}
