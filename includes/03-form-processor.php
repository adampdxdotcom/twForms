<?php
/**
 * Handles the universal [tw_form] shortcode and form processing logic.
 *
 * @package TW_Forms
 * @version 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == UNIVERSAL FORM SHORTCODE
// =============================================================================

if ( ! function_exists( 'tw_forms_universal_shortcode_handler' ) ) {

    /**
     * The final, single shortcode handler for [tw_form].
     *
     * It now reads the saved fields from the form builder and renders them dynamically.
     *
     * @param array $atts The attributes passed to the shortcode, e.g., [tw_form id="123"].
     * @return string The HTML for the form and status messages.
     */
    function tw_forms_universal_shortcode_handler( $atts ) {
        // --- 1. Sanitize the Shortcode Attributes ---
        $atts = shortcode_atts( [ 'id' => 0, ], $atts, 'tw_form' );
        $form_id = intval( $atts['id'] );
        if ( ! $form_id ) return '<p style="color: red;">Error: Form ID is missing or invalid.</p>';

        // --- 2. Retrieve the Form Post and its Fields from the Database ---
        $form_post = get_post( $form_id );
        if ( ! $form_post || 'tw_form' !== get_post_type( $form_post ) ) return '<p style="color: red;">Error: Form not found.</p>';
        
        $saved_fields = get_post_meta( $form_id, '_tw_form_fields', true );

        // --- 3. Handle Form Submission (if applicable) ---
        $status_message = '';
        $debug_message  = '';
        
        if ( isset( $_POST['submit_tw_form'] ) && isset( $_POST['tw_form_id'] ) && intval( $_POST['tw_form_id'] ) === $form_id ) {
            if ( ! isset( $_POST['tw_form_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_nonce'], 'process_tw_form_' . $form_id ) ) {
                 $status_message = '<p style="color: red;">Security check failed. Please try again.</p>';
            } else {
                // NOTE: Full processing logic (validation, emailing, saving) will be the very next step.
                // For now, this confirms the dynamic form can be submitted.
                $status_message = '<p style="color: green;">Thank you! Your submission has been received.</p>';
            }
        }
        
        // --- 4. Render the Form HTML ---
        ob_start();
        ?>
        <div class="tw-form-container">
            <div id="tw-form-status-<?php echo esc_attr( $form_id ); ?>" class="form-status-message">
                <?php echo $status_message; // We removed $debug_message for now ?>
            </div>

            <form method="post" action="" id="tw-form-<?php echo esc_attr( $form_id ); ?>">
                
                <input type="hidden" name="tw_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <?php wp_nonce_field( 'process_tw_form_' . $form_id, 'tw_form_nonce' ); ?>
                
                <?php if ( ! empty( $saved_fields ) && is_array( $saved_fields ) ) : ?>
                    <?php foreach ( $saved_fields as $index => $field ) : ?>
                        <?php
                        // Prepare variables for the field
                        $field_type     = $field['type'] ?? 'text';
                        $field_label    = $field['label'] ?? '';
                        $is_required    = ! empty( $field['required'] );
                        $field_id       = 'tw-field-' . esc_attr( $form_id ) . '-' . esc_attr( $index );
                        $field_name     = 'tw_form_field[' . esc_attr( $index ) . ']';
                        $required_html  = $is_required ? ' required' : '';
                        $required_span  = $is_required ? ' <span style="color:red;">*</span>' : '';
                        ?>
                        <div class="tw-form-field-wrapper tw-field-type-<?php echo $field_type; ?>" style="margin-bottom: 20px;">
                            <?php // Use a switch to render the correct HTML for each field type ?>
                            <?php switch ( $field_type ) :
                                case 'text':
                                case 'email':
                                case 'tel': ?>
                                    <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                    <input type="<?php echo $field_type; ?>" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" style="width: 100%; padding: 12px;"<?php echo $required_html; ?>>
                                    <?php break; ?>

                                <?php case 'textarea': ?>
                                    <label for="<?php echo $field_id; ?>"><?php echo esc_html( $field_label ); ?><?php echo $required_span; ?></label>
                                    <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" rows="5" style="width: 100%; padding: 12px;"<?php echo $required_html; ?>></textarea>
                                    <?php break; ?>
                                
                                <?php case 'checkbox': ?>
                                    <label for="<?php echo $field_id; ?>">
                                        <input type="checkbox" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" value="1"<?php echo $required_html; ?>>
                                        <?php echo esc_html( $field_label ); ?><?php echo $required_span; ?>
                                    </label>
                                    <?php break; ?>
                                
                                <?php case 'submit': ?>
                                    <button type="submit" name="submit_tw_form" class="custom-form-submit-button"><?php echo esc_html( $field_label ?: 'Submit' ); ?></button>
                                    <?php break; ?>

                            <?php endswitch; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p>This form has no fields yet. Please add fields in the form editor.</p>
                <?php endif; ?>
            </form>
        </div>
        <?php
        
        enqueue_form_spam_protection_scripts();
        return ob_get_clean();
    }
    add_shortcode( 'tw_form', 'tw_forms_universal_shortcode_handler' );
}


// =============================================================================
// == RECAPTCHA AND HELPER SCRIPTS (No changes needed here for now)
// =============================================================================
if ( ! function_exists('enqueue_form_spam_protection_scripts') ) { function enqueue_form_spam_protection_scripts() { static $s=false; if($s){return;} $o=get_option('my_recaptcha_settings',[]); if(!empty($o['disable'])){return;} $k=$o['site_key']??''; if(!empty($k)){wp_enqueue_script('google-recaptcha','https://www.google.com/recaptcha/api.js?render='.esc_attr($k),[],null,true);$s=true;}}}
if ( ! function_exists('add_recaptcha_form_submission_script') ) { function add_recaptcha_form_submission_script() { /* This will be re-implemented later */ } add_action('wp_footer', 'add_recaptcha_form_submission_script'); }
if ( ! function_exists('custom_recaptcha_badge_styles') ) { function custom_recaptcha_badge_styles() { echo '<style>.grecaptcha-badge { left: 15px !important; right: auto !important; }</style>'; } add_action('wp_head', 'custom_recaptcha_badge_styles');}
