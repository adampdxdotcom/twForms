<?php
/**
 * Handles the universal [tw_form] shortcode and form processing logic.
 *
 * @package TW_Forms
 * @version 2.0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == UNIVERSAL FORM SHORTCODE & PROCESSOR
// =============================================================================

if ( ! function_exists( 'tw_forms_universal_shortcode_handler' ) ) {

    function tw_forms_universal_shortcode_handler( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0, ], $atts, 'tw_form' );
        $form_id = intval( $atts['id'] );
        if ( ! $form_id ) return '<p style="color: red;">Error: Form ID is missing or invalid.</p>';

        $form_post = get_post( $form_id );
        if ( ! $form_post || 'tw_form' !== get_post_type( $form_post ) ) return '<p style="color: red;">Error: Form not found.</p>';
        
        $saved_fields = get_post_meta( $form_id, '_tw_form_fields', true );
        $status_message = '';
        $submitted_values = [];

        // --- Full Submission Processing ---
        if ( isset( $_POST['submit_tw_form'] ) && isset( $_POST['tw_form_id'] ) && intval( $_POST['tw_form_id'] ) === $form_id ) {
            
            if ( ! isset( $_POST['tw_form_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_nonce'], 'process_tw_form_' . $form_id ) ) {
                 $status_message = '<p style="color: red;">Security check failed. Please try again.</p>';
            } else {
                
                $errors = [];
                $form_data = $_POST['tw_form_field'] ?? [];
                $submitted_values = $form_data;

                // --- Validation Loop ---
                foreach ( $saved_fields as $index => $field ) {
                    
                    // FIX #2: Skip validation for the submit button itself.
                    if ( $field['type'] === 'submit' ) {
                        continue;
                    }

                    $value = isset( $form_data[$index] ) ? trim( $form_data[$index] ) : '';
                    
                    if ( ! empty( $field['required'] ) && empty( $value ) ) {
                        $errors[] = 'The "' . esc_html( $field['label'] ) . '" field is required.';
                    }

                    if ( $field['type'] === 'email' && ! empty( $value ) && ! is_email( $value ) ) {
                        $errors[] = 'Please enter a valid email address for "' . esc_html( $field['label'] ) . '".';
                    }

                    if ( ! empty( $field['confirm'] ) ) {
                        // Correctly look for the confirmation field in the submitted data.
                        $confirm_value = isset( $form_data[$index . '_confirm'] ) ? trim( $form_data[$index . '_confirm'] ) : '';
                        if ( $value !== $confirm_value ) {
                            $errors[] = 'The email addresses for "' . esc_html( $field['label'] ) . '" do not match.';
                        }
                    }
                }

                // --- Process if No Errors ---
                if ( empty( $errors ) ) {
                    $submitted_values = []; // Clear form on success
                    
                    $submitted_data_string = '';
                    $user_name = 'Guest'; $user_email = ''; $user_phone = '';
                    
                    foreach ( $saved_fields as $index => $field ) {
                        $label = $field['label'] ?? 'Field ' . $index;
                        $value = isset( $form_data[$index] ) ? sanitize_textarea_field( $form_data[$index] ) : 'N/A';
                        
                        if ( $field['type'] !== 'submit' ) {
                            $submitted_data_string .= esc_html( $label ) . ":\n" . $value . "\n\n";
                        }
                        
                        if ( stripos( $label, 'name' ) !== false && $user_name === 'Guest' ) $user_name = $value;
                        if ( $field['type'] === 'email' && empty($user_email) ) $user_email = $value;
                        if ( $field['type'] === 'tel' && empty($user_phone) ) $user_phone = $value;
                    }

                    log_form_submission_to_pods([
                        'messenger_name' => $user_name, 'phone' => $user_phone, 'email' => $user_email,
                        'message' => $submitted_data_string, 'form_source' => $form_post->post_title
                    ]);

                    $admin_recipients = get_post_meta( $form_id, '_tw_form_recipients', true );
                    if ( ! empty( $admin_recipients ) ) {
                        $admin_subject = "New Submission: " . $form_post->post_title;
                        $admin_body = "You have received a new submission from the \"" . $form_post->post_title . "\" form.\n\n--- Submitted Data ---\n" . $submitted_data_string;
                        $headers = [ 'Reply-To: ' . $user_name . ' <' . $user_email . '>' ];
                        wp_mail( $admin_recipients, $admin_subject, $admin_body, $headers );
                    }

                    if ( ! empty( $user_email ) ) {
                        send_user_confirmation_email( $user_email, $user_name, $form_post->post_title, $submitted_data_string );
                    }

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
                <?php if ( ! empty( $saved_fields ) && is_array( $saved_fields ) ) : ?>
                    <?php foreach ( $saved_fields as $index => $field ) :
                        $field_type = $field['type'] ?? 'text'; $field_label = $field['label'] ?? ''; $is_required = ! empty( $field['required'] ); $needs_confirm = ! empty( $field['confirm'] );
                        $field_id = 'tw-field-'.esc_attr($form_id).'-'.esc_attr($index); $field_name_base = 'tw_form_field['.esc_attr($index).']';
                        $required_html = $is_required ? ' required' : ''; $required_span = $is_required ? ' <span style="color:red;">*</span>' : '';
                        $repop_value = isset( $submitted_values[$index] ) ? esc_attr( $submitted_values[$index] ) : '';
                        ?>
                        <div class="tw-form-field-wrapper tw-field-type-<?php echo $field_type; ?>" style="margin-bottom: 20px;">
                            <?php switch ( $field_type ) :
                                case 'text': case 'email': case 'tel': ?>
                                    <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                    <input type="<?php echo $field_type; ?>" id="<?php echo $field_id; ?>" name="<?php echo $field_name_base; ?>" value="<?php echo $repop_value; ?>" style="width: 100%; padding: 12px;"<?php echo $required_html; ?>>
                                    <?php if ( $field_type === 'email' && $needs_confirm ) : ?>
                                        </div><div class="tw-form-field-wrapper tw-field-type-email-confirm" style="margin-bottom: 20px;">
                                        <label for="<?php echo $field_id; ?>-confirm">Confirm <?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                        <?php // FIX #1: Correctly format the name attribute for the confirmation field. ?>
                                        <input type="email" id="<?php echo $field_id; ?>-confirm" name="tw_form_field[<?php echo esc_attr($index); ?>_confirm]" style="width: 100%; padding: 12px;"<?php echo $required_html; ?>>
                                    <?php endif; break;
                                case 'textarea': ?>
                                    <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                    <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name_base; ?>" rows="5" style="width: 100%; padding: 12px;"<?php echo $required_html; ?>><?php echo esc_textarea( $repop_value ); ?></textarea>
                                    <?php break;
                                case 'checkbox': ?>
                                    <label for="<?php echo $field_id; ?>"><input type="checkbox" id="<?php echo $field_id; ?>" name="<?php echo $field_name_base; ?>" value="1" <?php checked( $repop_value, '1' ); ?><?php echo $required_html; ?>> <?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                    <?php break;
                                case 'submit': ?>
                                    <button type="submit" name="submit_tw_form" class="custom-form-submit-button"><?php echo esc_html( $field_label ?: 'Submit' ); ?></button>
                                    <?php break;
                            endswitch; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
        enqueue_form_spam_protection_scripts(); return ob_get_clean();
    }
    add_shortcode( 'tw_form', 'tw_forms_universal_shortcode_handler' );
}

if ( ! function_exists('enqueue_form_spam_protection_scripts') ) { function enqueue_form_spam_protection_scripts() { static $s=false; if($s){return;} $o=get_option('my_recaptcha_settings',[]); if(!empty($o['disable'])){return;} $k=$o['site_key']??''; if(!empty($k)){wp_enqueue_script('google-recaptcha','https://www.google.com/recaptcha/api.js?render='.esc_attr($k),[],null,true);$s=true;}}}
if ( ! function_exists('add_recaptcha_form_submission_script') ) { function add_recaptcha_form_submission_script() { /* This will be re-implemented later */ } add_action('wp_footer', 'add_recaptcha_form_submission_script'); }
if ( ! function_exists('custom_recaptcha_badge_styles') ) { function custom_recaptcha_badge_styles() { echo '<style>.grecaptcha-badge { left: 15px !important; right: auto !important; }</style>'; } add_action('wp_head', 'custom_recaptcha_badge_styles');}
