<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == REUSABLE HELPER FUNCTIONS FOR FORMS
// =============================================================================

if ( ! function_exists( 'tw_forms_process_tags' ) ) {
    /**
     * Processes a string (like an email subject or body) and replaces all merge tags with their corresponding values.
     *
     * @param string   $content         The string containing merge tags like [field_label].
     * @param array    $data_map        An associative array of submitted data, mapping 'Field Label' => 'Submitted Value'.
     * @param WP_Post  $form_post       The post object for the form being processed.
     * @param string   $all_fields_html A pre-formatted HTML string of all submitted fields.
     * @return string The processed string with all tags replaced.
     */
    function tw_forms_process_tags( $content, $data_map, $form_post, $all_fields_html ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }

        // --- 1. Process Utility and Magic Tags ---
        $utility_replacements = [
            '[all_fields]'      => $all_fields_html,
            '[form_name]'       => get_the_title( $form_post ),
            '[page_url]'        => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '',
            '[user_ip]'         => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            '[submission_date]' => wp_date( get_option( 'date_format' ), time() ),
            '[submission_time]' => wp_date( get_option( 'time_format' ), time() ),
        ];

        foreach ( $utility_replacements as $tag => $value ) {
            $content = str_replace( $tag, $value, $content );
        }

        // --- 2. Process Field-Specific Tags ---
        if ( ! empty( $data_map ) && is_array( $data_map ) ) {
            foreach ( $data_map as $label => $value ) {
                $html_safe_value = nl2br( esc_html( (string) $value ) );
                $content = str_replace( '[' . $label . ']', $html_safe_value, $content );
            }
        }

        return $content;
    }
}

if ( ! function_exists('verify_recaptcha_v3') ) {
    function verify_recaptcha_v3($token) {
        $recaptcha_options = get_option('my_recaptcha_settings', []);
        $is_debug = !empty($recaptcha_options['debug']);
        $log = [];
        if ($is_debug) { $log[] = "--- Starting reCAPTCHA Server Verification ---"; $log[] = "1. Token received by server: " . ($token ? '<strong>Yes</strong> (Starts with: ' . substr($token, 0, 15) . '...)' : '<strong>!! TOKEN NOT FOUND !!</strong>'); }
        if ( !empty($recaptcha_options['disable']) ) { if ($is_debug) $log[] = "2. Verification Result: <strong>PASS</strong> (reCAPTCHA is disabled in settings)."; set_transient('form_debug_log', implode('<br>', $log), 60); return true; }
        $secret_key = $recaptcha_options['secret_key'] ?? ''; $threshold = (float) ($recaptcha_options['score_threshold'] ?? 0.5);
        if ($is_debug) { $log[] = "2. Secret Key is set: " . (!empty($secret_key) ? "<strong>Yes</strong> (Starts with: " . substr($secret_key, 0, 4) . "...)" : "<strong>No - Verification will be skipped.</strong>"); }
        if (empty($secret_key)) { if ($is_debug) set_transient('form_debug_log', implode('<br>', $log), 60); return true;}
        if (empty($token)) { if ($is_debug) $log[] = "3. Verification Result: <strong>FAIL</strong> (Token was empty)."; set_transient('form_debug_log', implode('<br>', $log), 60); return false; }
        if ($is_debug) $log[] = "3. Sending token and secret key to Google via <code>wp_remote_post()</code>...";
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', ['body' => [ 'secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '' ],]);
        if (is_wp_error($response)) {
            if ($is_debug) { $log[] = "4. Verification Result: <strong>ERROR</strong> - The <code>wp_remote_post()</code> call failed."; $log[] = "   -&gt; Error Message: <strong>" . $response->get_error_message() . "</strong>"; $log[] = "   -&gt; This usually means your host is blocking outbound connections. The submission was allowed to pass to prevent blocking real users."; set_transient('form_debug_log', implode('<br>', $log), 60); }
            return true;
        }
        $body = wp_remote_retrieve_body($response); if ($is_debug) $log[] = "4. Raw Response Body from Google: <code>" . esc_html($body) . "</code>";
        $result = json_decode($body);
        if (isset($result->success) && $result->success && isset($result->score) && $result->score >= $threshold) {
            if ($is_debug) $log[] = "5. Verification Result: <strong>PASS</strong> (Score: " . $result->score . " is >= Threshold: " . $threshold . ")"; set_transient('form_debug_log', implode('<br>', $log), 60);
            return true;
        } else {
            $reason = 'Unknown';
            if (isset($result->success) && !$result->success) { $reason = 'Google said success was false. Error codes: <strong>' . implode(', ', $result->{'error-codes'}) . '</strong> (This usually means an invalid Secret Key).'; } 
            else if (isset($result->score)) { $reason = 'Score ' . $result->score . ' was below threshold ' . $threshold; }
            if ($is_debug) { $log[] = "5. Verification Result: <strong>FAIL</strong>"; $log[] = "   -&gt; Reason: " . $reason; set_transient('form_debug_log', implode('<br>', $log), 60); }
            return false;
        }
    }
}

if ( ! function_exists('is_email_blacklisted') ) {
    function is_email_blacklisted($email) {
        if ( !function_exists('pods') || empty($email) ) { return false; }
        $blacklist_pod = pods('blacklist', [ 'limit' => 1, 'where' => "email_address.meta_value = '" . esc_sql($email) . "'" ]);
        return $blacklist_pod->total() > 0;
    }
}

if ( ! function_exists('log_form_submission_to_pods') ) {
    function log_form_submission_to_pods( $data, $data_map = [] ) {
        if ( !function_exists('pods') ) { return; }
        
        $pods = pods('messages');
        
        // Check the privacy setting for IP storage.
        $privacy_options = get_option('tw_forms_privacy_settings', []);
        $disable_ip = isset($privacy_options['disable_ip_storage']) && $privacy_options['disable_ip_storage'] === '1';
        $ip_address = $disable_ip ? 'Storage disabled by admin' : ($_SERVER['REMOTE_ADDR'] ?? 'Unknown');

        $pod_data = [ 
            'post_title'      => 'Submission from ' . $data['messenger_name'] . ' on ' . date('Y-m-d H:i:s'), 
            'post_status'     => 'publish', 
            'messenger_name'  => $data['messenger_name'], 
            'phone'           => $data['phone'], 
            'email'           => $data['email'], 
            'message'         => $data['message'], 
            'form_source'     => $data['form_source'], 
            'entry_status'    => 'Unread', 
            'user_ip_address' => $ip_address
        ];
        
        $new_message_id = $pods->add($pod_data);
        
        if ( $new_message_id && ! empty( $data_map ) && is_array( $data_map ) ) {
            update_post_meta( $new_message_id, '_tw_form_submitted_data', $data_map );
        }
    }
}

if ( ! function_exists('validate_and_format_phone_number') ) {
    function validate_and_format_phone_number($phone_input) {
        $phone_trimmed = trim($phone_input);
        if (empty($phone_trimmed)) { return ['is_valid' => false, 'formatted' => '']; }
        $stripped_phone = preg_replace('/[^0-9]/', '', $phone_trimmed);
        if (strlen($stripped_phone) !== 10) { return ['is_valid' => false, 'formatted' => '']; }
        $formatted_phone = sprintf('(%s) %s-%s', substr($stripped_phone, 0, 3), substr($stripped_phone, 3, 3), substr($stripped_phone, 6, 4));
        return ['is_valid' => true, 'formatted' => $formatted_phone];
    }
}

if (!function_exists('send_custom_admin_notification')) {
    function send_custom_admin_notification($to, $template_key, $form_data, $submitted_data_string) {
        if (empty($to)) {
            return true; 
        }
        $admin_templates = get_option('my_admin_email_templates', []);
        $defaults = [
            'volunteer_subject' => 'New Volunteer Submission from {user_name}',
            'volunteer_body' => "You have received a new volunteer submission.\n\nFrom: {user_name}\nEmail: {user_email}\nPhone: {user_phone}\n\n--- Details ---\n{submitted_data}",
            'newsletter_subject' => 'New Newsletter Signup: {user_email}',
            'newsletter_body' => "A new user has signed up for the newsletter.\n\nName: {user_name}\nEmail: {user_email}",
            'membership_subject' => 'New Membership Submission from {user_name}',
            'membership_body' => "You have received a new membership submission.\n\nFrom: {user_name}\nEmail: {user_email}\nPhone: {user_phone}\n\n--- Details ---\n{submitted_data}",
            'contact_subject' => 'New Contact Submission from {user_name}',
            'contact_body' => "You have received a new contact submission.\n\nFrom: {user_name}\nEmail: {user_email}\nPhone: {user_phone}\n\n--- Message ---\n{submitted_data}",
        ];
        $subject_template = $admin_templates[$template_key . '_subject'] ?? $defaults[$template_key . '_subject'];
        $body_template = $admin_templates[$template_key . '_body'] ?? $defaults[$template_key . '_body'];
        $replacements = [
            '{user_name}'      => $form_data['full_name'],
            '{user_email}'     => $form_data['email'],
            '{user_phone}'     => $form_data['phone'],
            '{form_source}'    => $form_data['form_source'],
            '{submitted_data}' => $submitted_data_string,
        ];
        $final_subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
        $final_body = str_replace(array_keys($replacements), array_values($replacements), $body_template);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $form_data['full_name'] . ' <' . $form_data['email'] . '>',
        ];
        $html_body = '<div style="font-family: sans-serif; font-size: 14px; color: #333; line-height: 1.6;">' . nl2br(esc_html($final_body)) . '</div>';
        return wp_mail($to, $final_subject, $html_body, $headers);
    }
}

if (!function_exists('send_user_confirmation_email')) {
    function send_user_confirmation_email($user_email, $user_name, $form_source, $submitted_data) {
        $templates = get_option('my_form_email_templates', []);
        $subject = $templates['subject'] ?? 'Thank you for your submission!';
        $default_body = "Hello {user_name},\n\nWe have successfully received your submission from the {form_source}.\n\nFor your records, here is a copy of the information you submitted:\n\n{submitted_data}\n\nSincerely,\nThe Team";
        $body_template = $templates['body'] ?? $default_body;
        $replacements = [ '{user_name}' => $user_name, '{form_source}' => $form_source, '{submitted_data}' => $submitted_data ];
        $final_body = str_replace(array_keys($replacements), array_values($replacements), $body_template);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $html_body = '<div style="font-family: sans-serif; font-size: 14px; color: #333; line-height: 1.6;">' . nl2br(esc_html($final_body)) . '</div>';
        return wp_mail($user_email, $subject, $html_body, $headers);
    }
}
