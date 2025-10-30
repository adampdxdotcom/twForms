<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == REUSABLE HELPER FUNCTIONS FOR FORMS
// =============================================================================

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
    function log_form_submission_to_pods($data) {
        if ( !function_exists('pods') ) { return; }
        $pods = pods('messages');
        $pod_data = [ 'post_title' => 'Submission from ' . $data['messenger_name'] . ' on ' . date('Y-m-d H:i:s'), 'post_status' => 'publish', 'messenger_name' => $data['messenger_name'], 'phone' => $data['phone'], 'email' => $data['email'], 'message' => $data['message'], 'form_source' => $data['form_source'], 'entry_status' => 'Unread', 'user_ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ];
        $pods->add($pod_data);
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

if (!function_exists('send_formatted_admin_email')) {
    function send_formatted_admin_email($to, $subject, $data) {
        if (empty($to)) { return true; }
        $headers = ['Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $data['full_name'] . ' <' . $data['email'] . '>'];
        ob_start(); ?>
        <div style="font-family: sans-serif; font-size: 14px; color: #333; line-height: 1.6;"><h2 style="color: #ba0000;">New Submission: <?php echo esc_html($data['form_source']); ?></h2><table cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;"><tr style="background-color: #f7f7f7;"><td style="width: 120px;"><strong>Name:</strong></td><td><?php echo esc_html($data['full_name']); ?></td></tr><tr><td><strong>Email:</strong></td><td><a href="mailto:<?php echo esc_attr($data['email']); ?>"><?php echo esc_html($data['email']); ?></a></td></tr><tr style="background-color: #f7f7f7;"><td><strong>Phone:</strong></td><td><?php echo esc_html($data['phone']); ?></td></tr><?php if (isset($data['custom_fields'])) { foreach($data['custom_fields'] as $field): ?><tr><td><strong><?php echo esc_html($field['label']); ?>:</strong></td><td><?php echo esc_html($field['value']); ?></td></tr><?php endforeach; } ?></table><h3 style="margin-top: 20px; color: #333;">Message</h3><div style="padding: 15px; background-color: #fdfdfd; border: 1px solid #eee;"><?php echo nl2br(esc_html($data['message'])); ?></div></div>
        <?php $body = ob_get_clean();
        return wp_mail($to, $subject, $body, $headers);
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
