--- a/original.php
+++ b/modified.php
@@ -21,7 +21,6 @@
 
         // Side meta boxes
         add_meta_box( 'tw_form_shortcode_mb', 'Shortcode', 'tw_forms_render_shortcode_mb', 'tw_form', 'side', 'high' );
-        add_meta_box( 'tw_form_settings_mb', 'Form Settings', 'tw_forms_render_settings_mb', 'tw_form', 'side', 'default' );
     }
     add_action( 'add_meta_boxes_tw_form', 'tw_forms_editor_add_meta_boxes' );
 }
@@ -33,8 +32,6 @@
 // --- Main Form Builder ---
 if ( ! function_exists( 'tw_forms_render_builder_mb' ) ) {
     function tw_forms_render_builder_mb( $post ) {
-        // A nonce field is now added in the first meta box rendered.
-        wp_nonce_field( 'tw_form_save_meta_box_data', 'tw_form_meta_box_nonce' );
         $saved_layout = get_post_meta( $post->ID, '_tw_form_layout', true );
         ?>
         <div class="tw-form-builder-wrapper">
@@ -62,6 +59,8 @@
             <?php tw_forms_render_field_partial( '__ROW_INDEX__', '__COL_INDEX__', '__FIELD_INDEX__', [], true ); ?>
         </div>
         <?php
+        // The nonce field is added here, in the first meta box to be rendered.
+        wp_nonce_field( 'tw_form_save_meta_box_data', 'tw_form_meta_box_nonce' );
     }
 }
 
@@ -142,66 +141,60 @@
 // --- Admin Notification Meta Box ---
 if ( ! function_exists( 'tw_forms_render_admin_notification_mb' ) ) {
     function tw_forms_render_admin_notification_mb( $post ) {
         $settings = get_post_meta( $post->ID, '_tw_form_admin_email', true );
         $settings = is_array( $settings ) ? $settings : [];
-
-        $subject    = $settings['subject'] ?? 'New Submission from [form_name]';
-        $message    = $settings['message'] ?? '<p>A new entry has been submitted via the [form_name] form on your website.</p>[all_fields]';
-        $from_name  = $settings['from_name'] ?? get_bloginfo('name');
-        $from_email = $settings['from_email'] ?? get_bloginfo('admin_email');
-        $reply_to   = $settings['reply_to'] ?? '';
+        $recipients_fallback = get_post_meta( $post->ID, '_tw_form_recipients', true ); // For backward compatibility if updating
+
+        $to       = $settings['to'] ?? $recipients_fallback;
+        $subject  = $settings['subject'] ?? 'New Submission from [form_name]';
+        $reply_to = $settings['reply_to'] ?? '[Your Email Address]';
+        $message  = $settings['message'] ?? '<p>A new entry has been submitted via the [form_name] form on your website.</p>[all_fields]';
         ?>
         <div class="tw-email-settings-wrapper">
             <div class="email-settings-main">
-                <div class="setting-row">
-                    <label for="admin-email-subject"><strong>Subject</strong></label>
-                    <input type="text" id="admin-email-subject" name="tw_form_admin_email[subject]" value="<?php echo esc_attr( $subject ); ?>">
-                </div>
-                 <div class="setting-row">
-                    <label for="admin-email-from-name"><strong>From Name</strong></label>
-                    <input type="text" id="admin-email-from-name" name="tw_form_admin_email[from_name]" value="<?php echo esc_attr( $from_name ); ?>">
-                </div>
-                 <div class="setting-row">
-                    <label for="admin-email-from-email"><strong>From Email</strong></label>
-                    <input type="email" id="admin-email-from-email" name="tw_form_admin_email[from_email]" value="<?php echo esc_attr( $from_email ); ?>">
-                </div>
-                 <div class="setting-row">
-                    <label for="admin-email-reply-to"><strong>Reply-To</strong></label>
-                    <input type="text" id="admin-email-reply-to" name="tw_form_admin_email[reply_to]" value="<?php echo esc_attr( $reply_to ); ?>" placeholder="e.g., [Your Email Address]">
-                     <p class="description">Use a field tag to set the reply-to address to the user's email.</p>
-                </div>
-                <div class="setting-row">
-                    <label for="admin-email-message"><strong>Message</strong></label>
-                    <?php wp_editor( $message, 'admin-email-message', [
-                        'textarea_name' => 'tw_form_admin_email[message]',
-                        'media_buttons' => false,
-                        'textarea_rows' => 10,
-                        'tinymce' => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ]
-                    ] ); ?>
-                </div>
+                <div class="setting-row"><label for="admin-email-to"><strong>Send Notifications To</strong></label><input type="text" id="admin-email-to" name="tw_form_admin_email[to]" value="<?php echo esc_attr( $to ); ?>"><p class="description">Enter email addresses, comma-separated. If blank, defaults to the site admin email.</p></div>
+                <div class="setting-row"><label for="admin-email-subject"><strong>Subject</strong></label><input type="text" id="admin-email-subject" name="tw_form_admin_email[subject]" value="<?php echo esc_attr( $subject ); ?>"></div>
+                <div class="setting-row"><label for="admin-email-reply-to"><strong>Reply-To</strong></label><input type="text" id="admin-email-reply-to" name="tw_form_admin_email[reply_to]" value="<?php echo esc_attr( $reply_to ); ?>" placeholder="e.g., [Your Email Address]"><p class="description">Use an email field tag so your staff can reply directly to the user.</p></div>
+                <div class="setting-row"><label for="admin-email-message"><strong>Message</strong></label>
+                    <?php wp_editor( $message, 'admin-email-message', ['textarea_name' => 'tw_form_admin_email[message]', 'media_buttons' => false, 'textarea_rows' => 10, 'tinymce' => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ]] ); ?>
+                </div>
             </div>
             <div class="email-settings-sidebar">
-                <strong>Available Tags</strong>
-                <p>Use these tags in your subject or message. They will be replaced with form data.</p>
-                <div class="tags-list">
-                    <code>[all_fields]</code> <code>[form_name]</code> <code>[page_url]</code> <code>[user_ip]</code> <code>[submission_date]</code> <code>[submission_time]</code>
-                    <p>You can also use tags for any field by wrapping its label in brackets, e.g., <code>[Your Name]</code>.</p>
-                </div>
+                <p>This email will be sent from your site's default address (as configured in WP Mail SMTP).</p>
+                <hr>
+                <strong>Available Tags</strong><p>Use these tags in your subject or message. They will be replaced with form data.</p>
+                <div class="tags-list"><code>[all_fields]</code> <code>[form_name]</code> <code>[page_url]</code> <code>[user_ip]</code> <code>[submission_date]</code> <code>[submission_time]</code><p>You can also use tags for any field by wrapping its label in brackets, e.g., <code>[Your Name]</code>.</p></div>
             </div>
         </div>
         <?php
     }
 }
 
 // --- User Confirmation Meta Box ---
 if ( ! function_exists( 'tw_forms_render_user_confirmation_mb' ) ) {
     function tw_forms_render_user_confirmation_mb( $post ) {
         $settings = get_post_meta( $post->ID, '_tw_form_user_email', true );
         $settings = is_array( $settings ) ? $settings : [];
 
-        $enabled    = ! empty( $settings['enabled'] );
-        $subject    = $settings['subject'] ?? 'Thank you for your submission';
-        $message    = $settings['message'] ?? '<p>Hi [Your Name],</p><p>Thank you for contacting us. We have received your message and will get back to you shortly.</p>';
-        $from_name  = $settings['from_name'] ?? get_bloginfo('name');
-        $from_email = $settings['from_email'] ?? get_bloginfo('admin_email');
-        $reply_to   = $settings['reply_to'] ?? get_bloginfo('admin_email');
+        $enabled = ! empty( $settings['enabled'] );
+        $subject = $settings['subject'] ?? 'Thank you for your submission';
+        $message = $settings['message'] ?? '<p>Hi [Your Name],</p><p>Thank you for contacting us. We have received your message and will get back to you shortly.</p><hr><p style="font-size: smaller; color: #777;">This is an automated message. Please do not reply to this email.</p>';
         ?>
         <div class="tw-email-settings-wrapper">
              <div class="email-settings-main">
-                <div class="setting-row">
-                    <label>
-                        <input type="checkbox" name="tw_form_user_email[enabled]" value="1" <?php checked( $enabled ); ?>>
-                        <strong>Enable this email notification</strong>
-                    </label>
-                </div>
-                <div class="setting-row">
-                    <label for="user-email-subject"><strong>Subject</strong></label>
-                    <input type="text" id="user-email-subject" name="tw_form_user_email[subject]" value="<?php echo esc_attr( $subject ); ?>">
-                </div>
-                 <div class="setting-row">
-                    <label for="user-email-from-name"><strong>From Name</strong></label>
-                    <input type="text" id="user-email-from-name" name="tw_form_user_email[from_name]" value="<?php echo esc_attr( $from_name ); ?>">
-                </div>
-                 <div class="setting-row">
-                    <label for="user-email-from-email"><strong>From Email</strong></label>
-                    <input type="email" id="user-email-from-email" name="tw_form_user_email[from_email]" value="<?php echo esc_attr( $from_email ); ?>">
-                </div>
-                 <div class="setting-row">
-                    <label for="user-email-reply-to"><strong>Reply-To</strong></label>
-                    <input type="text" id="user-email-reply-to" name="tw_form_user_email[reply_to]" value="<?php echo esc_attr( $reply_to ); ?>">
-                </div>
-                <div class="setting-row">
-                    <label for="user-email-message"><strong>Message</strong></label>
-                    <?php wp_editor( $message, 'user-email-message', [
-                        'textarea_name' => 'tw_form_user_email[message]',
-                        'media_buttons' => false,
-                        'textarea_rows' => 10,
-                        'tinymce' => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ]
-                    ] ); ?>
-                </div>
+                <div class="setting-row"><label><input type="checkbox" name="tw_form_user_email[enabled]" value="1" <?php checked( $enabled ); ?>> <strong>Enable this email notification</strong></label></div>
+                <div class="setting-row"><label for="user-email-subject"><strong>Subject</strong></label><input type="text" id="user-email-subject" name="tw_form_user_email[subject]" value="<?php echo esc_attr( $subject ); ?>"></div>
+                <div class="setting-row"><label for="user-email-message"><strong>Message</strong></label>
+                    <?php wp_editor( $message, 'user-email-message', ['textarea_name' => 'tw_form_user_email[message]', 'media_buttons' => false, 'textarea_rows' => 10, 'tinymce' => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ]] ); ?>
+                </div>
             </div>
             <div class="email-settings-sidebar">
-                <strong>Available Tags</strong>
-                <p>Use these tags in your subject or message. They will be replaced with form data.</p>
-                <div class="tags-list">
-                    <code>[all_fields]</code> <code>[form_name]</code> <code>[page_url]</code> <code>[user_ip]</code> <code>[submission_date]</code> <code>[submission_time]</code>
-                    <p>You can also use tags for any field by wrapping its label in brackets, e.g., <code>[Your Name]</code>.</p>
-                </div>
-                 <p class="description"><strong>Note:</strong> The "To" address for this email is automatically set to the value of the "Email Address" field in your form.</p>
+                <p>This email's <strong>To</strong> address is automatically set to the email provided by the user in the form.</p>
+                <p>It will be sent from your site's default address, and replies will be disabled.</p>
+                <hr>
+                <strong>Available Tags</strong><p>Use these tags in your subject or message.</p>
+                <div class="tags-list"><code>[all_fields]</code> <code>[form_name]</code> <code>[page_url]</code> <code>[user_ip]</code> <code>[submission_date]</code><p>You can also use tags for any field by wrapping its label in brackets, e.g., <code>[Your Name]</code>.</p></div>
             </div>
         </div>
         <?php
     }
 }
 
 // --- Side Meta Boxes ---
-if ( ! function_exists( 'tw_forms_render_settings_mb' ) ) {
-    function tw_forms_render_settings_mb( $post ) {
-        $recipients = get_post_meta( $post->ID, '_tw_form_recipients', true );
-        ?>
-        <div class="tw-form-settings-wrapper"><p><label for="tw_form_recipients"><strong>Send Admin Notifications To:</strong></label><input type="text" id="tw_form_recipients" name="tw_form_recipients" value="<?php echo esc_attr( $recipients ); ?>" style="width:100%;"></p><p class="description">Enter email addresses, comma-separated. If blank, defaults to the site admin email.</p></div>
-        <?php
-    }
-}
-
 if ( ! function_exists( 'tw_forms_render_shortcode_mb' ) ) {
     function tw_forms_render_shortcode_mb( $post ) {
         $shortcode = '[tw_form id="' . $post->ID . '"]';
@@ -216,7 +209,6 @@
 // -----------------------------------------------------------------------------
 if ( ! function_exists( 'tw_forms_save_meta_box_data' ) ) {
     function tw_forms_save_meta_box_data( $post_id ) {
-        if ( ! isset( $_POST['tw_form_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['tw_form_meta_box_nonce'] ) ) return;
         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
         if ( ! current_user_can( 'edit_post', $post_id ) ) return;
         if ( 'tw_form' !== get_post_type( $post_id ) ) return;
@@ -250,42 +242,40 @@
         }
         update_post_meta( $post_id, '_tw_form_layout', $sanitized_layout );
 
-        // --- Save Form Settings (Recipients) ---
-        if ( isset( $_POST['tw_form_recipients'] ) ) {
-            $emails_raw = explode( ',', $_POST['tw_form_recipients'] ); $sanitized_emails = [];
-            foreach ( $emails_raw as $email ) { if ( is_email( trim( $email ) ) ) { $sanitized_emails[] = trim( $email ); } }
-            update_post_meta( $post_id, '_tw_form_recipients', implode( ', ', $sanitized_emails ) );
-        }
-
         // --- Save Admin Notification Email Settings ---
         if ( isset( $_POST['tw_form_admin_email'] ) && is_array( $_POST['tw_form_admin_email'] ) ) {
             $data = $_POST['tw_form_admin_email'];
+            $emails_raw = explode( ',', $data['to'] ?? '' ); $sanitized_emails = [];
+            foreach ( $emails_raw as $email ) { if ( is_email( trim( $email ) ) ) { $sanitized_emails[] = trim( $email ); } }
+            
             $sanitized_data = [
+                'to'         => implode( ', ', $sanitized_emails ),
                 'subject'    => sanitize_text_field( $data['subject'] ?? '' ),
                 'message'    => wp_kses_post( $data['message'] ?? '' ),
-                'from_name'  => sanitize_text_field( $data['from_name'] ?? '' ),
-                'from_email' => sanitize_email( $data['from_email'] ?? '' ),
                 'reply_to'   => sanitize_text_field( $data['reply_to'] ?? '' ),
             ];
             update_post_meta( $post_id, '_tw_form_admin_email', $sanitized_data );
+            // Delete the old meta key to avoid confusion
+            delete_post_meta( $post_id, '_tw_form_recipients' );
         }
 
         // --- Save User Confirmation Email Settings ---
         if ( isset( $_POST['tw_form_user_email'] ) && is_array( $_POST['tw_form_user_email'] ) ) {
             $data = $_POST['tw_form_user_email'];
             $sanitized_data = [
                 'enabled'    => isset( $data['enabled'] ) ? 1 : 0,
                 'subject'    => sanitize_text_field( $data['subject'] ?? '' ),
                 'message'    => wp_kses_post( $data['message'] ?? '' ),
-                'from_name'  => sanitize_text_field( $data['from_name'] ?? '' ),
-                'from_email' => sanitize_email( $data['from_email'] ?? '' ),
-                'reply_to'   => sanitize_email( $data['reply_to'] ?? '' ),
             ];
             update_post_meta( $post_id, '_tw_form_user_email', $sanitized_data );
         }
     }
     add_action( 'save_post_tw_form', 'tw_forms_save_meta_box_data' );
 }
 
 // -----------------------------------------------------------------------------
 // 4. ENQUEUE EDITOR SCRIPTS & STYLES
 // -----------------------------------------------------------------------------
@@ -361,24 +351,21 @@
                 layoutContainer.find('.field-type-select').trigger('change');
             });
         </script>
-        <style>
-            /* Builder Styles */
-            .tw-form-builder-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
-            #layout-container { border: 1px solid #ccd0d4; background: #fff; padding: 15px; min-height: 150px; }
-            #layout-container .empty-state { color: #777; text-align: center; margin: 40px 0; font-size: 1.2em; }
-            .builder-actions { margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ccd0d4; }
-            .add-row-container { display: flex; align-items: center; gap: 10px; }
-            .row-block { border: 1px solid #999; margin-bottom: 15px; background: #fdfdfd; }
-            .row-header { display: flex; justify-content: space-between; align-items: center; background: #e0e0e0; padding: 5px 10px; cursor: move; border-bottom: 1px solid #ccc; }
-            .row-header .row-label { font-weight: bold; }
-            .row-columns { display: flex; gap: 10px; padding: 10px; }
-            .column-block { flex: 1; border: 1px dashed #ccd0d4; background: #f9f9f9; min-height: 80px; padding: 10px; }
-            .column-block[data-layout="50-50"] { flex-basis: 50%; } .column-block[data-layout="33-33-33"] { flex-basis: 33.33%; } .column-block[data-layout="25-25-25-25"] { flex-basis: 25%; }
-            .column-block .add-field-to-col { width: 100%; margin-top: 5px; }
-            .form-field-block { border: 1px solid #ccd0d4; margin-bottom: 10px; background: #fff; }
-            .field-header { display: flex; justify-content: space-between; align-items: center; background: #f0f0f1; padding: 8px 12px; cursor: move; }
-            .field-header .field-type-label { font-weight: 700; }
-            .field-header .field-actions a { text-decoration: none; font-size: 1.4em; margin-left: 10px; }
-            .field-settings { padding: 12px; }
-            .field-settings .setting-row { margin-bottom: 10px; }
-            .field-settings .setting-row-options label { display: inline-block; margin-right: 20px; }
-            .field-settings label { display: block; margin-bottom: 5px; font-weight: 500; }
-            .field-settings select, .field-settings input[type=text], .field-settings input[type=email], .field-settings textarea { width: 100%; }
-            .row-placeholder, .field-placeholder{border:2px dashed #ccd0d4;background-color:#f0f8ff;margin-bottom:15px;box-sizing:border-box;}
-            
-            /* Email Settings Styles */
-            .tw-email-settings-wrapper { display: flex; gap: 20px; }
-            .tw-email-settings-wrapper .email-settings-main { flex: 1; }
-            .tw-email-settings-wrapper .email-settings-sidebar { flex-basis: 250px; background-color: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; }
-            .tw-email-settings-wrapper .email-settings-sidebar strong { font-size: 1.1em; }
-            .tw-email-settings-wrapper .email-settings-sidebar .tags-list { margin-top: 10px; }
-            .tw-email-settings-wrapper .email-settings-sidebar code { background-color: #e0e0e0; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; }
-            .tw-email-settings-wrapper .email-settings-sidebar p { margin-top: 5px; }
-            .tw-email-settings-wrapper .setting-row { margin-bottom: 15px; }
-            .tw-email-settings-wrapper .setting-row input[type="text"],
-            .tw-email-settings-wrapper .setting-row input[type="email"] { width: 100%; }
-        </style>
+        <style>
+            /* Builder Styles */
+            .tw-form-builder-wrapper { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; } #layout-container { border: 1px solid #ccd0d4; background: #fff; padding: 15px; min-height: 150px; } #layout-container .empty-state { color: #777; text-align: center; margin: 40px 0; font-size: 1.2em; } .builder-actions { margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ccd0d4; } .add-row-container { display: flex; align-items: center; gap: 10px; } .row-block { border: 1px solid #999; margin-bottom: 15px; background: #fdfdfd; } .row-header { display: flex; justify-content: space-between; align-items: center; background: #e0e0e0; padding: 5px 10px; cursor: move; border-bottom: 1px solid #ccc; } .row-header .row-label { font-weight: bold; } .row-columns { display: flex; gap: 10px; padding: 10px; } .column-block { flex: 1; border: 1px dashed #ccd0d4; background: #f9f9f9; min-height: 80px; padding: 10px; } .column-block[data-layout="50-50"] { flex-basis: 50%; } .column-block[data-layout="33-33-33"] { flex-basis: 33.33%; } .column-block[data-layout="25-25-25-25"] { flex-basis: 25%; } .column-block .add-field-to-col { width: 100%; margin-top: 5px; } .form-field-block { border: 1px solid #ccd0d4; margin-bottom: 10px; background: #fff; } .field-header { display: flex; justify-content: space-between; align-items: center; background: #f0f0f1; padding: 8px 12px; cursor: move; } .field-header .field-type-label { font-weight: 700; } .field-header .field-actions a { text-decoration: none; font-size: 1.4em; margin-left: 10px; } .field-settings { padding: 12px; } .field-settings .setting-row { margin-bottom: 10px; } .field-settings .setting-row-options label { display: inline-block; margin-right: 20px; } .field-settings label { display: block; margin-bottom: 5px; font-weight: 500; } .field-settings select, .field-settings input[type=text], .field-settings input[type=email], .field-settings textarea { width: 100%; } .row-placeholder, .field-placeholder{border:2px dashed #ccd0d4;background-color:#f0f8ff;margin-bottom:15px;box-sizing:border-box;}
+            
+            /* Email Settings Styles */
+            .tw-email-settings-wrapper { display: flex; gap: 20px; } 
+            .tw-email-settings-wrapper .email-settings-main { flex: 1; } 
+            .tw-email-settings-wrapper .email-settings-sidebar { flex-basis: 250px; background-color: #f8f9fa; border: 1px solid #ccd0d4; padding: 15px; } 
+            .tw-email-settings-wrapper .email-settings-sidebar strong { font-size: 1.1em; } 
+            .tw-email-settings-wrapper .email-settings-sidebar .tags-list { margin-top: 10px; } 
+            .tw-email-settings-wrapper .email-settings-sidebar code { background-color: #e0e0e0; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; } 
+            .tw-email-settings-wrapper .email-settings-sidebar p { margin-top: 5px; } 
+            .tw-email-settings-wrapper .setting-row { margin-bottom: 15px; } 
+            .tw-email-settings-wrapper .setting-row input[type="text"] { width: 100%; }
+        </style>
         <?php
     }
     add_action( 'admin_footer', 'tw_forms_editor_enqueue_scripts' );
