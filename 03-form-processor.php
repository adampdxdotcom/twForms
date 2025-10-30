<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == FORM SHORTCODES, HANDLERS, AND ASSETS
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

if ( ! function_exists('volunteer_form_handler') ) {
    function volunteer_form_handler() {
        enqueue_form_spam_protection_scripts();
        $status_message = ''; $debug_message = ''; $recaptcha_options = get_option('my_recaptcha_settings', []);
        if (!empty($recaptcha_options['debug'])) { $debug_log = get_transient('form_debug_log'); if ($debug_log) { $debug_message = '<div style="background:#fffbef; border:1px solid #f1c40f; padding:15px; text-align:left; margin-bottom:15px; font-family: monospace; font-size: 12px; line-height: 1.6;"><strong>SERVER-SIDE DEBUG LOG:</strong><br>' . $debug_log . '</div>'; delete_transient('form_debug_log'); } }
        if ( isset($_POST['submit_volunteer_form']) ) {
            if ( ! empty($_POST['vf_user_nickname']) ) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            $time_check = isset($_POST['form_timestamp']) ? (time() - intval(base64_decode($_POST['form_timestamp']))) : 999; if ($time_check < 3) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            if ( ! isset($_POST['volunteer_form_nonce']) || ! wp_verify_nonce( $_POST['volunteer_form_nonce'], 'process_volunteer_form' ) ) { die( 'Security check failed.' ); }
            if (!verify_recaptcha_v3($_POST['recaptcha_token'] ?? '')) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            $email = sanitize_email($_POST['email_address']);
            if (is_email_blacklisted($email)) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            $first_name = sanitize_text_field($_POST['first_name']); $last_name = sanitize_text_field($_POST['last_name']); $confirm_email = sanitize_email($_POST['confirm_email']);
            $message = sanitize_textarea_field($_POST['user_message']); $phone_check = validate_and_format_phone_number($_POST['phone_number']); $newsletter = isset($_POST['newsletter']) ? 'Yes' : 'No';
            $interests = []; if ( isset($_POST['interests']) && is_array($_POST['interests']) ) { foreach ($_POST['interests'] as $interest) { $interests[] = sanitize_text_field($interest); } }
            $interests_string = implode(', ', $interests);
            if ( empty($first_name) || empty($last_name) || empty($email) || empty($_POST['phone_number']) ) { $status_message = '<p style="color: red;">Error: Please fill in all required fields.</p>'; } 
            elseif ( !$phone_check['is_valid'] ) { $status_message = '<p style="color: red;">Error: Please enter a valid 10-digit phone number.</p>'; } 
            elseif ( $email !== $confirm_email ) { $status_message = '<p style="color: red;">Error: The email addresses do not match.</p>'; } 
            elseif ( !is_email($email) ) { $status_message = '<p style="color: red;">Error: Please enter a valid email address.</p>'; } 
            else {
                $full_name = $first_name . " " . $last_name; $routing_options = get_option('my_form_email_routing', []); $recipients = $routing_options['volunteer'] ?? '';
                $admin_email_data = [ 'full_name' => $full_name, 'email' => $email, 'phone' => $phone_check['formatted'], 'form_source' => 'Volunteer Form', 'custom_fields' => [ ['label' => 'Newsletter Signup', 'value' => $newsletter] ], 'message' => $message . "\n\n---\n\nAREAS OF INTEREST:\n" . $interests_string ];
                $pods_message = $message . "\n\n---\n\nAREAS OF INTEREST:\n" . $interests_string . "\n\nSign up for newsletter: " . $newsletter;
                log_form_submission_to_pods(['messenger_name' => $full_name, 'phone' => $phone_check['formatted'], 'email' => $email, 'message' => $pods_message, 'form_source' => 'Volunteer Form']);
                send_formatted_admin_email($recipients, 'New Volunteer Submission from ' . $full_name, $admin_email_data);
                $user_copy = "Name: $full_name\nEmail: $email\nPhone: {$phone_check['formatted']}\n\nMessage:\n$message\n\nAreas of Interest:\n$interests_string\nNewsletter Signup: $newsletter";
                send_user_confirmation_email($email, $first_name, 'Volunteer Form', $user_copy);
                $status_message = '<p style="color: green;">Thank you! Your application has been received. A confirmation has been sent to your email.</p>';
            }
        }
        ob_start(); ?>
        <div id="volunteer-form-status" class="form-status-message"><?php echo $debug_message . $status_message; ?></div>
        <form method="post" action="" id="volunteer-form"><div style="position: absolute; left: -5000px;" aria-hidden="true"><label for="vf_nickname">Do not fill this out</label><input type="text" id="vf_nickname" name="vf_user_nickname" tabindex="-1" autocomplete="off"></div><input type="hidden" name="form_timestamp" value="<?php echo base64_encode(time()); ?>"><?php wp_nonce_field( 'process_volunteer_form', 'volunteer_form_nonce' ); ?><div style="display: flex; gap: 25px; margin-bottom: 15px;"><div style="flex: 1;"><label for="vf_first_name">First Name <span style="color:red;">*</span></label><br><input type="text" id="vf_first_name" name="first_name" style="width: 100%; padding: 12px;" required></div><div style="flex: 1;"><label for="vf_last_name">Last Name <span style="color:red;">*</span></label><br><input type="text" id="vf_last_name" name="last_name" style="width: 100%; padding: 12px;" required></div></div><div style="margin-bottom: 15px;"><label for="vf_phone">Phone Number <span style="color:red;">*</span></label><br><input type="tel" id="vf_phone" name="phone_number" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="vf_email">Email <span style="color:red;">*</span></label><br><input type="email" id="vf_email" name="email_address" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="vf_confirm_email">Confirm Email <span style="color:red;">*</span></label><br><input type="email" id="vf_confirm_email" name="confirm_email" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label><strong>Areas of interest</strong></label><br><div style="columns: 2; -webkit-columns: 2; -moz-columns: 2;"><label><input type="checkbox" name="interests[]" value="Acting"> Acting</label><br><label><input type="checkbox" name="interests[]" value="Directing"> Directing</label><br><label><input type="checkbox" name="interests[]" value="Back Stage"> Back Stage</label><br><label><input type="checkbox" name="interests[]" value="Music"> Music</label><br><label><input type="checkbox" name="interests[]" value="Lights/Sound"> Lights/Sound</label><br><label><input type="checkbox" name="interests[]" value="Set Design/Construction"> Set Design/Construction</label><br><label><input type="checkbox" name="interests[]" value="Costumes"> Costumes</label><br><label><input type="checkbox" name="interests[]" value="Publicity"> Publicity</label><br><label><input type="checkbox" name="interests[]" value="Administrative"> Administrative</label><br><label><input type="checkbox" name="interests[]" value="Box Office"> Box Office</label><br><label><input type="checkbox" name="interests[]" value="Ushering"> Ushering</label><br><label><input type="checkbox" name="interests[]" value="Fundraising/Grant Writing"> Fundraising/Grant Writing</label><br><label><input type="checkbox" name="interests[]" value="Makeup Artist"> Makeup Artist</label><br><label><input type="checkbox" name="interests[]" value="General Handyperson"> General Handyperson</label><br><label><input type="checkbox" name="interests[]" value="House Manager"> House Manager</label><br><label><input type="checkbox" name="interests[]" value="Volunteer Coordinator"> Volunteer Coordinator</label><br></div></div><div style="margin-bottom: 20px;"><label><input type="checkbox" name="newsletter" value="yes"> Please sign me up to the newsletter.</label></div><div style="margin-bottom: 15px;"><label for="vf_user_message">Message (Optional)</label><br><textarea id="vf_user_message" name="user_message" rows="5" style="width: 100%; padding: 12px;"></textarea></div><div style="margin-top: 25px; text-align: center;"><button type="submit" name="submit_volunteer_form" class="custom-form-submit-button">Submit</button></div></form>
        <?php return ob_get_clean();
    }
    add_shortcode('volunteer_form', 'volunteer_form_handler');
}

if ( ! function_exists('membership_form_handler') ) {
    function membership_form_handler() {
        enqueue_form_spam_protection_scripts();
        $status_message = ''; $debug_message = ''; $recaptcha_options = get_option('my_recaptcha_settings', []);
        if (!empty($recaptcha_options['debug'])) { $debug_log = get_transient('form_debug_log'); if ($debug_log) { $debug_message = '<div style="background:#fffbef; border:1px solid #f1c40f; padding:15px; text-align:left; margin-bottom:15px; font-family: monospace; font-size: 12px; line-height: 1.6;"><strong>SERVER-SIDE DEBUG LOG:</strong><br>' . $debug_log . '</div>'; delete_transient('form_debug_log'); } }
        if ( isset($_POST['submit_membership_form']) ) {
            if ( ! empty($_POST['mf_user_nickname']) ) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            $time_check = isset($_POST['form_timestamp']) ? (time() - intval(base64_decode($_POST['form_timestamp']))) : 999; if ($time_check < 3) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            if ( ! isset( $_POST['membership_form_nonce'] ) || ! wp_verify_nonce( $_POST['membership_form_nonce'], 'process_membership_form' ) ) { die( 'Security check failed.' ); }
            if (!verify_recaptcha_v3($_POST['recaptcha_token'] ?? '')) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            $email = sanitize_email($_POST['email_address']);
            if (is_email_blacklisted($email)) { return '<p style="color: green;">Thank you! Your submission has been received.</p>'; }
            $first_name = sanitize_text_field($_POST['first_name']); $last_name = sanitize_text_field($_POST['last_name']); $organization = sanitize_text_field($_POST['organization']); $confirm_email = sanitize_email($_POST['confirm_email']);
            $message = sanitize_textarea_field($_POST['user_message']); $phone_check = validate_and_format_phone_number($_POST['phone_number']);
            if ( empty($first_name) || empty($last_name) || empty($email) || empty($_POST['phone_number']) ) { $status_message = '<p style="color: red;">Error: Please fill in all required fields.</p>'; } 
            elseif ( !$phone_check['is_valid'] ) { $status_message = '<p style="color: red;">Error: Please enter a valid 10-digit phone number.</p>'; } 
            elseif ( $email !== $confirm_email ) { $status_message = '<p style="color: red;">Error: The email addresses do not match.</p>'; } 
            elseif ( !is_email($email) ) { $status_message = '<p style="color: red;">Error: Please enter a valid email address.</p>'; } 
            else {
                $full_name = $first_name . " " . $last_name; $routing_options = get_option('my_form_email_routing', []); $recipients = $routing_options['membership'] ?? '';
                $admin_email_data = [ 'full_name' => $full_name, 'email' => $email, 'phone' => $phone_check['formatted'], 'form_source' => 'Membership Form', 'custom_fields' => [ ['label' => 'Organization', 'value' => $organization] ], 'message' => $message ];
                $pods_message = 'Organization: ' . $organization . "\n\n---\n\n" . $message;
                log_form_submission_to_pods(['messenger_name' => $full_name, 'phone' => $phone_check['formatted'], 'email' => $email, 'message' => $pods_message, 'form_source' => 'Membership Form']);
                send_formatted_admin_email($recipients, 'New Membership Submission from ' . $full_name, $admin_email_data);
                $user_copy = "Name: $full_name\nEmail: $email\nPhone: {$phone_check['formatted']}\nOrganization: $organization\n\nMessage:\n$message";
                send_user_confirmation_email($email, $first_name, 'Membership Form', $user_copy);
                $status_message = '<p style="color: green;">Thank you! Your application has been received. A confirmation has been sent to your email.</p>';
            }
        }
        ob_start(); ?>
        <div id="membership-form-status" class="form-status-message"><?php echo $debug_message . $status_message; ?></div>
        <form method="post" action="" id="membership-form"><div style="position: absolute; left: -5000px;" aria-hidden="true"><label for="mf_nickname">Do not fill this out</label><input type="text" id="mf_nickname" name="mf_user_nickname" tabindex="-1" autocomplete="off"></div><input type="hidden" name="form_timestamp" value="<?php echo base64_encode(time()); ?>"><?php wp_nonce_field( 'process_membership_form', 'membership_form_nonce' ); ?><div style="display: flex; gap: 25px; margin-bottom: 15px;"><div style="flex: 1;"><label for="mf_first_name">First Name <span style="color:red;">*</span></label><br><input type="text" id="mf_first_name" name="first_name" style="width: 100%; padding: 12px;" required></div><div style="flex: 1;"><label for="mf_last_name">Last Name <span style="color:red;">*</span></label><br><input type="text" id="mf_last_name" name="last_name" style="width: 100%; padding: 12px;" required></div></div><div style="margin-bottom: 15px;"><label for="mf_organization">Organization</label><br><input type="text" id="mf_organization" name="organization" style="width: 100%; padding: 12px;"></div><div style="margin-bottom: 15px;"><label for="mf_phone">Phone Number <span style="color:red;">*</span></label><br><input type="tel" id="mf_phone" name="phone_number" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="mf_email">Email <span style="color:red;">*</span></label><br><input type="email" id="mf_email" name="email_address" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="mf_confirm_email">Confirm Email <span style="color:red;">*</span></label><br><input type="email" id="mf_confirm_email" name="confirm_email" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="mf_user_message">Message (Optional)</label><br><textarea id="mf_user_message" name="user_message" rows="5" style="width: 100%; padding: 12px;"></textarea></div><div style="margin-top: 25px; text-align: center;"><button type="submit" name="submit_membership_form" class="custom-form-submit-button">Submit</button></div></form>
        <?php return ob_get_clean();
    }
    add_shortcode('membership_form', 'membership_form_handler');
}

if ( ! function_exists('contact_form_handler') ) {
    function contact_form_handler() {
        enqueue_form_spam_protection_scripts();
        $status_message = ''; $debug_message = ''; $recaptcha_options = get_option('my_recaptcha_settings', []);
        if (!empty($recaptcha_options['debug'])) { $debug_log = get_transient('form_debug_log'); if ($debug_log) { $debug_message = '<div style="background:#fffbef; border:1px solid #f1c40f; padding:15px; text-align:left; margin-bottom:15px; font-family: monospace; font-size: 12px; line-height: 1.6;"><strong>SERVER-SIDE DEBUG LOG:</strong><br>' . $debug_log . '</div>'; delete_transient('form_debug_log'); } }
        if ( isset($_POST['submit_contact_form']) ) {
            if ( ! empty($_POST['cf_user_nickname']) ) { return '<p style="color: green;">Thank you! Your message has been received.</p>'; }
            $time_check = isset($_POST['form_timestamp']) ? (time() - intval(base64_decode($_POST['form_timestamp']))) : 999; if ($time_check < 3) { return '<p style="color: green;">Thank you! Your message has been received.</p>'; }
            if ( ! isset( $_POST['contact_form_nonce'] ) || ! wp_verify_nonce( $_POST['contact_form_nonce'], 'process_contact_form' ) ) { die( 'Security check failed.' ); }
            if (!verify_recaptcha_v3($_POST['recaptcha_token'] ?? '')) { return '<p style="color: green;">Thank you! Your message has been received.</p>'; }
            $email = sanitize_email($_POST['email_address']);
            if (is_email_blacklisted($email)) { return '<p style="color: green;">Thank you! Your message has been received.</p>'; }
            $first_name = sanitize_text_field($_POST['first_name']); $last_name = sanitize_text_field($_POST['last_name']); $confirm_email = sanitize_email($_POST['confirm_email']);
            $message = sanitize_textarea_field($_POST['user_message']); $phone_check = validate_and_format_phone_number($_POST['phone_number']);
            if ( empty($first_name) || empty($last_name) || empty($email) || empty($message) || empty($_POST['phone_number']) ) { $status_message = '<p style="color: red;">Error: Please fill in all required fields.</p>'; } 
            elseif ( !$phone_check['is_valid'] ) { $status_message = '<p style="color: red;">Error: Please enter a valid 10-digit phone number.</p>'; } 
            elseif ( $email !== $confirm_email ) { $status_message = '<p style="color: red;">Error: The email addresses do not match.</p>'; } 
            elseif ( !is_email($email) ) { $status_message = '<p style="color: red;">Error: Please enter a valid email address.</p>'; } 
            else {
                $full_name = $first_name . " " . $last_name; $routing_options = get_option('my_form_email_routing', []); $recipients = $routing_options['contact'] ?? '';
                $admin_email_data = [ 'full_name' => $full_name, 'email' => $email, 'phone' => $phone_check['formatted'], 'form_source' => 'Contact Form', 'custom_fields' => [], 'message' => $message ];
                log_form_submission_to_pods(['messenger_name' => $full_name, 'phone' => $phone_check['formatted'], 'email' => $email, 'message' => $message, 'form_source' => 'Contact Form']);
                send_formatted_admin_email($recipients, 'New Contact Form Submission from ' . $full_name, $admin_email_data);
                $user_copy = "Name: $full_name\nEmail: $email\nPhone: {$phone_check['formatted']}\n\nMessage:\n$message";
                send_user_confirmation_email($email, $first_name, 'Contact Form', $user_copy);
                $status_message = '<p style="color: green;">Thank you! Your message has been received. A confirmation has been sent to your email.</p>';
            }
        }
        ob_start(); ?>
        <div id="contact-form-status" class="form-status-message"><?php echo $debug_message . $status_message; ?></div>
        <form method="post" action="" id="contact-form"><div style="position: absolute; left: -5000px;" aria-hidden="true"><label for="cf_nickname">Do not fill this out</label><input type="text" id="cf_nickname" name="cf_user_nickname" tabindex="-1" autocomplete="off"></div><input type="hidden" name="form_timestamp" value="<?php echo base64_encode(time()); ?>"><?php wp_nonce_field( 'process_contact_form', 'contact_form_nonce' ); ?><div style="display: flex; gap: 25px; margin-bottom: 15px;"><div style="flex: 1;"><label for="cf_first_name">First Name <span style="color:red;">*</span></label><br><input type="text" id="cf_first_name" name="first_name" style="width: 100%; padding: 12px;" required></div><div style="flex: 1;"><label for="cf_last_name">Last Name <span style="color:red;">*</span></label><br><input type="text" id="cf_last_name" name="last_name" style="width: 100%; padding: 12px;" required></div></div><div style="margin-bottom: 15px;"><label for="cf_phone">Phone Number <span style="color:red;">*</span></label><br><input type="tel" id="cf_phone" name="phone_number" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="cf_email">Email <span style="color:red;">*</span></label><br><input type="email" id="cf_email" name="email_address" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="cf_confirm_email">Confirm Email <span style="color:red;">*</span></label><br><input type="email" id="cf_confirm_email" name="confirm_email" style="width: 100%; padding: 12px;" required></div><div style="margin-bottom: 15px;"><label for="cf_user_message">Message <span style="color:red;">*</span></label><br><textarea id="cf_user_message" name="user_message" rows="5" style="width: 100%; padding: 12px;" required></textarea></div><div style="margin-top: 25px; text-align: center;"><button type="submit" name="submit_contact_form" class="custom-form-submit-button">Submit</button></div></form>
        <?php return ob_get_clean();
    }
    add_shortcode('contact_form', 'contact_form_handler');
}

if ( ! function_exists('add_recaptcha_form_submission_script') ) {
    function add_recaptcha_form_submission_script() {
        $recaptcha_options = get_option('my_recaptcha_settings', []);
        $is_disabled = !empty($recaptcha_options['disable']); if ($is_disabled) { return; }
        $site_key = $recaptcha_options['site_key'] ?? ''; if (empty($site_key)) { return; }
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                const initRecaptchaForForm = function(formId, buttonName, actionName) {
                    const form = document.getElementById(formId); if (!form) return;
                    const button = form.querySelector('button[name="' + buttonName + '"]'); if (!button) return;
                    button.addEventListener('click', function (e) {
                        e.preventDefault(); e.stopPropagation();
                        const thisButton = this; const originalButtonText = thisButton.textContent; const statusDiv = document.getElementById(formId + '-status');
                        thisButton.disabled = true; thisButton.textContent = 'Verifying...';
                        if (typeof grecaptcha === 'undefined' || typeof grecaptcha.execute === 'undefined') {
                            if (statusDiv) statusDiv.innerHTML = '<p style="color: red;">Spam protection failed to load. Please disable ad blockers for this site and reload the page.</p>';
                            thisButton.disabled = false; thisButton.textContent = originalButtonText; return;
                        }
                        if (statusDiv) statusDiv.innerHTML = '';
                        grecaptcha.ready(function () {
                            grecaptcha.execute('<?php echo esc_js($site_key); ?>', { action: actionName }).then(function (token) {
                                const tokenInput = document.createElement('input'); tokenInput.type = 'hidden'; tokenInput.name = 'recaptcha_token'; tokenInput.value = token; form.appendChild(tokenInput);
                                const buttonInput = document.createElement('input'); buttonInput.type = 'hidden'; buttonInput.name = buttonName; buttonInput.value = '1'; form.appendChild(buttonInput);
                                form.submit();
                            }).catch(function(error){
                                if (statusDiv) statusDiv.innerHTML = '<p style="color: red;">Could not get spam protection token. Please try again.</p>';
                                thisButton.disabled = false; thisButton.textContent = originalButtonText;
                            });
                        });
                    });
                };
                initRecaptchaForForm('volunteer-form', 'submit_volunteer_form', 'volunteer_form_submit');
                initRecaptchaForForm('membership-form', 'submit_membership_form', 'membership_form_submit');
                initRecaptchaForForm('contact-form', 'submit_contact_form', 'contact_form_submit');
            });
        </script>
        <?php
    }
    add_action('wp_footer', 'add_recaptcha_form_submission_script');
}

if ( ! function_exists('custom_recaptcha_badge_styles') ) {
    function custom_recaptcha_badge_styles() { echo '<style>.grecaptcha-badge { left: 15px !important; right: auto !important; }</style>'; }
    add_action('wp_head', 'custom_recaptcha_badge_styles');
}
