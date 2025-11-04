<?php
/**
 * Handles the new universal [tw_form] shortcode and form processing logic.
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
     * The new, single shortcode handler for [tw_form].
     *
     * It retrieves the form CPT based on the ID and renders it.
     * For Phase 1, it only renders a placeholder. The visual builder will be added in Phase 2.
     * It also handles the submission processing for all forms.
     *
     * @param array $atts The attributes passed to the shortcode, e.g., [tw_form id="123"].
     * @return string The HTML for the form and status messages.
     */
    function tw_forms_universal_shortcode_handler( $atts ) {
        // --- 1. Sanitize the Shortcode Attributes ---
        $atts = shortcode_atts( [
            'id' => 0,
        ], $atts, 'tw_form' );

        $form_id = intval( $atts['id'] );

        // If no valid ID is provided, don't render anything.
        if ( ! $form_id ) {
            return '<p style="color: red;">Error: Form ID is missing or invalid.</p>';
        }

        // --- 2. Retrieve the Form Post from the Database ---
        $form_post = get_post( $form_id );

        // Check if the form exists and is the correct post type.
        if ( ! $form_post || 'tw_form' !== get_post_type( $form_post ) ) {
            return '<p style="color: red;">Error: Form not found.</p>';
        }

        // --- 3. Handle Form Submission (if applicable) ---
        $status_message = '';
        $debug_message  = '';
        
        // Check if this specific form was submitted.
        // We will add a hidden field `tw_form_id` to our forms.
        if ( isset( $_POST['submit_tw_form'] ) && isset( $_POST['tw_form_id'] ) && intval( $_POST['tw_form_id'] ) === $form_id ) {
            
            // For now, the processing logic will just show a success message.
            // In Phase 2, we will add full validation and processing here.
            
            // A simple nonce check for security
            if ( ! isset( $_POST['tw_form_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_nonce'], 'process_tw_form_' . $form_id ) ) {
                 $status_message = '<p style="color: red;">Security check failed. Please try again.</p>';
            } else {
                // All processing will eventually go here.
                // For now, just a placeholder success message.
                $status_message = '<p style="color: green;">Thank you! Your submission has been received.</p>';
            }
        }
        
        // --- 4. Render the Form HTML ---
        ob_start();
        ?>
        <div class="tw-form-container">
            
            <div id="tw-form-status-<?php echo esc_attr( $form_id ); ?>" class="form-status-message">
                <?php echo $debug_message . $status_message; ?>
            </div>

            <form method="post" action="" id="tw-form-<?php echo esc_attr( $form_id ); ?>">
                
                <?php // --- Security and Tracking Fields --- ?>
                <input type="hidden" name="tw_form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <?php wp_nonce_field( 'process_tw_form_' . $form_id, 'tw_form_nonce' ); ?>
                
                <h2><?php echo esc_html( $form_post->post_title ); ?></h2>
                <p><em>(This is a placeholder for Phase 1. The visual form builder will appear here in Phase 2.)</em></p>

                <?php // --- Placeholder Fields for Demonstration --- ?>
                <div style="margin-bottom: 15px;">
                    <label for="name_<?php echo esc_attr( $form_id ); ?>">Your Name</label><br>
                    <input type="text" id="name_<?php echo esc_attr( $form_id ); ?>" name="your_name" style="width: 100%; padding: 12px;">
                </div>
                 <div style="margin-bottom: 15px;">
                    <label for="email_<?php echo esc_attr( $form_id ); ?>">Your Email</label><br>
                    <input type="email" id="email_<?php echo esc_attr( $form_id ); ?>" name="your_email" style="width: 100%; padding: 12px;">
                </div>

                <?php // The actual form fields will be dynamically generated here in Phase 2 ?>

                <div style="margin-top: 25px;">
                    <button type="submit" name="submit_tw_form" class="custom-form-submit-button">Submit Form</button>
                </div>

            </form>
        </div>
        <?php
        
        // The reCAPTCHA script loader can remain as it will be needed later.
        enqueue_form_spam_protection_scripts();

        return ob_get_clean();
    }
    // Register the new universal shortcode
    add_shortcode( 'tw_form', 'tw_forms_universal_shortcode_handler' );

}


// =============================================================================
// == RECAPTCHA AND HELPER SCRIPTS (Can be kept as they are)
// =============================================================================

if ( ! function_exists('enqueue_form_spam_protection_scripts') ) {
    function enqueue_form_spam_protection_scripts() {
        static $scripts_enqueued = false;
        if ($scripts_enqueued) { return; }
        $recaptcha_options = get_option('my_recaptcha_settings', []);
        if ( !empty($recaptcha_options['disable']) ) { return; }
        $site_key = $recaptcha_options['site_key'] ?? '';
        if (!empty($site_key)) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
            $scripts_enqueued = true;
        }
    }
}

if ( ! function_exists('add_recaptcha_form_submission_script') ) {
    function add_recaptcha_form_submission_script() {
        // This function will need to be rewritten in Phase 2 to work with the dynamic forms.
        // For now, it's okay to leave it, but it will not be functional.
    }
    add_action('wp_footer', 'add_recaptcha_form_submission_script');
}

if ( ! function_exists('custom_recaptcha_badge_styles') ) {
    function custom_recaptcha_badge_styles() { echo '<style>.grecaptcha-badge { left: 15px !important; right: auto !important; }</style>'; }
    add_action('wp_head', 'custom_recaptcha_badge_styles');
}
