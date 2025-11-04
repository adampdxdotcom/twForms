<?php
/**
 * Handles the Message Inbox, Admin Menus, and Settings UI for TW Forms.
 *
 * @package TW_Forms
 * @version 2.6.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =============================================================================
// == MESSAGE VIEWER & ADMIN UI
// =============================================================================

if ( ! class_exists( 'WP_List_Table' ) ) { 
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); 
}

if ( ! class_exists( 'Messages_List_Table' ) ) {
    class Messages_List_Table extends WP_List_Table {
        private $blacklisted_emails = [];

        function __construct() { 
            parent::__construct(['singular' => 'Message', 'plural' => 'Messages', 'ajax' => false]); 
        }

        function get_views() {
            $status_links = [];
            $post_status = isset($_REQUEST['post_status']) ? $_REQUEST['post_status'] : 'all';
            $base_url = admin_url('admin.php?page=tw-forms-inbox');
            $counts = wp_count_posts('messages');
            $all_count = ($counts->publish ?? 0) + ($counts->draft ?? 0) + ($counts->pending ?? 0) + ($counts->private ?? 0) + ($counts->future ?? 0);
            $all_url = remove_query_arg('post_status', $base_url);
            $status_links['all'] = sprintf('<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url($all_url), $post_status === 'all' ? 'current' : '', __('All'), $all_count);
            if (isset($counts->trash) && $counts->trash > 0) {
                $trash_url = add_query_arg('post_status', 'trash', $base_url);
                $status_links['trash'] = sprintf('<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url($trash_url), $post_status === 'trash' ? 'current' : '', __('Trash'), $counts->trash);
            }
            return $status_links;
        }

        function get_columns() { return ['cb' => '<input type="checkbox" />', 'messenger_name' => 'From', 'email' => 'Email', 'form_source' => 'Source', 'entry_status' => 'Status', 'post_date' => 'Received']; }
        function get_sortable_columns() { return ['messenger_name' => ['messenger_name', false], 'form_source' => ['form_source', false], 'entry_status' => ['entry_status', false], 'post_date' => ['post_date', true]]; }
        function column_default( $item, $column_name ) { return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—'; }
        function column_cb( $item ) { return sprintf('<input type="checkbox" name="entry_id[]" value="%s" />', $item['ID']); }
        function column_email( $item ) { return esc_html($item['email']); }
        function column_messenger_name( $item ) {
            $post_status_view = isset($_REQUEST['post_status']) ? $_REQUEST['post_status'] : 'all';
            $base_page_url = 'admin.php?page=tw-forms-inbox';
            $actions = [];
            if ($post_status_view === 'trash') {
                $restore_url = wp_nonce_url(admin_url($base_page_url . '&action=untrash&post_status=trash&entry_id=' . $item['ID']), 'untrash_message_' . $item['ID']);
                $delete_url = wp_nonce_url(admin_url($base_page_url . '&action=delete&post_status=trash&entry_id=' . $item['ID']), 'delete_message_' . $item['ID']);
                $actions['untrash'] = '<a href="' . esc_url($restore_url) . '">Restore</a>';
                $actions['delete'] = '<a href="' . esc_url($delete_url) . '" class="submitdelete">Delete Permanently</a>';
            } else {
                $view_url = admin_url($base_page_url . '&action=view&entry_id=' . $item['ID']);
                $trash_url = wp_nonce_url(admin_url($base_page_url . '&action=trash&entry_id=' . $item['ID']), 'trash_message_' . $item['ID']);
                $actions['view'] = '<a href="' . esc_url($view_url) . '">View</a>';
                $actions['edit'] = '<a href="' . get_edit_post_link($item['ID']) . '">Edit (Raw)</a>';
                $actions['trash'] = '<a href="' . esc_url($trash_url) . '" class="submitdelete">Trash</a>';
            }
            return '<strong>' . esc_html($item['messenger_name']) . '</strong>' . $this->row_actions($actions);
        }
        function column_entry_status( $item ) { if ( $item['is_blacklisted'] ) { return 'Blocked <span class="status-indicator-dot red-dot" title="This sender is on the blacklist">●</span>'; } switch ($item['entry_status']) { case 'Unread': return 'Unread <span class="status-indicator-dot green-dot" title="Unread Message">●</span>'; case 'Replied': return 'Replied <span class="status-indicator-dot purple-dot" title="You have replied to this message">●</span>'; case 'Read': default: return 'Read <span class="status-indicator-dot blue-dot" title="Message has been read">●</span>'; } }
        function get_bulk_actions() { $post_status = isset($_REQUEST['post_status']) ? $_REQUEST['post_status'] : 'all'; if ($post_status === 'trash') { return ['untrash' => 'Restore', 'delete' => 'Delete Permanently']; } return ['mark_read' => 'Mark as Read', 'mark_unread' => 'Mark as Unread', 'trash' => 'Move to Trash']; }
        function prepare_items() {
            $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'messenger_name'];
            if (function_exists('pods')) { 
                $blacklist_pod = pods('blacklist', ['limit' => -1]); 
                if ($blacklist_pod->total() > 0) {
                    while ($blacklist_pod->fetch()) { $this->blacklisted_emails[] = $blacklist_pod->field('email_address'); } 
                }
            }
            $per_page = 20; $current_page = $this->get_pagenum(); $offset = ( $current_page - 1 ) * $per_page; $search_term = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
            $params = ['limit' => $per_page, 'offset' => $offset, 'orderby' => (isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 't.post_date') . ' ' . (isset($_GET['order']) ? strtoupper(sanitize_key($_GET['order'])) : 'DESC')];
            $where_conditions = ['relation' => 'AND'];
            if (isset($_REQUEST['post_status']) && $_REQUEST['post_status'] === 'trash') {
                $where_conditions[] = ['key' => 't.post_status', 'value' => 'trash'];
            } else {
                $where_conditions[] = ['key' => 't.post_status', 'value' => ['publish', 'draft', 'pending', 'private', 'future'], 'compare' => 'IN'];
            }
            if ($search_term) {
                $where_conditions[] = ['relation' => 'OR', ['key' => 'messenger_name.meta_value', 'value' => $search_term, 'compare' => 'LIKE'], ['key' => 'email.meta_value', 'value' => $search_term, 'compare' => 'LIKE']];
            }
            $params['where'] = $where_conditions;
            $pods = pods('messages', $params); 
            $this->set_pagination_args(['total_items' => $pods->total_found(), 'per_page' => $per_page]); 
            $items = [];
            while ($pods->fetch()) { 
                $items[] = ['ID' => $pods->id(), 'messenger_name' => $pods->field('messenger_name'), 'email' => $pods->field('email'), 'form_source' => $pods->field('form_source'), 'entry_status' => $pods->field('entry_status'), 'post_date' => get_the_date('Y/m/d g:i a', $pods->id()), 'is_blacklisted' => in_array($pods->field('email'), $this->blacklisted_emails)]; 
            }
            $this->items = $items;
        }
    }
}

if ( ! function_exists( 'render_message_detail_view' ) ) {
    function render_message_detail_view() {
        $entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0; if (!$entry_id) { wp_die('Invalid entry ID.'); } 
        $pod = pods('messages', $entry_id); if (!$pod->exists()) { wp_die('Entry not found.'); }
        if ($pod->field('entry_status') === 'Unread' && $pod->field('post_status') === 'publish') { $pod->save('entry_status', 'Read'); }
        
        // --- Get structured data for new display format ---
        $submitted_data = get_post_meta( $pod->id(), '_tw_form_submitted_data', true );

        // --- Fallback for old data format ---
        $raw_message = $pod->field('message'); $user_message = $raw_message; $auto_data = ''; $parts = explode("\n\n---\n\n", $raw_message, 2); 
        if (count($parts) === 2) { $user_message = $parts[0]; $auto_data = $parts[1]; }
        $notes = $pod->field('notes'); if ( is_array($notes) ) { $notes = ''; }
        ?>
        <style>
            .message-viewer-wrap{display:flex;gap:20px}
            .message-main-content{flex:1}
            .message-sidebar{width:280px}
            .postbox .inside{padding:15px}
            .postbox h2.hndle{font-size:14px;padding:8px 12px}
            .message-content{white-space:pre-wrap;font-size:14px;line-height:1.6}
            .sidebar-actions a,.sidebar-actions span{display:block;padding:5px 0;text-decoration:none}
            .sidebar-actions span{color:#888}
            .sender-info-header{background-color:#f6f7f7;padding:10px 15px;border-bottom:1px solid #ddd}
            .sender-info-header p{margin:0 0 8px;font-size:14px}
            .quick-reply-form textarea{width:100%;min-height:150px;margin-bottom:10px}
            .quick-reply-form .description{font-style:italic;color:#666}
            .notes-history{margin-top:20px}
            .note-item{background-color:#fdfaf1;border-left:3px solid #f1c40f;padding:15px;margin-bottom:15px}
            .note-item p{margin:0}
            .blacklist-button{display:inline-block;text-decoration:none;background:#a00;color:#fff!important;padding:4px 8px;border-radius:3px;font-size:12px;margin-left:10px;vertical-align:middle}
            .blacklist-button:hover{background:#c00}
            /* New styles for our submission data table */
            table.tw-submission-data { width: 100%; border-collapse: collapse; }
            table.tw-submission-data th, table.tw-submission-data td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
            table.tw-submission-data th { width: 25%; font-weight: bold; background-color: #fcfcfc; }
        </style>
        <div class="wrap">
            <?php if (isset($_GET['blacklisted']) && $_GET['blacklisted'] === 'true'): ?><div class="notice notice-success is-dismissible"><p>Email address has been successfully added to the blacklist.</p></div><?php endif; ?>
            <?php if (isset($_GET['replied']) && $_GET['replied'] === 'true'): ?><div class="notice notice-success is-dismissible"><p>Reply sent and saved as a note.</p></div><?php endif; ?>
            <h1>Viewing Message from <?php echo esc_html($pod->field('messenger_name')); ?></h1>
            <div class="message-viewer-wrap">
                <div class="message-main-content">
                    <div class="postbox">
                        <div class="sender-info-header">
                            <p><strong>From:</strong> <?php echo esc_html($pod->field('messenger_name')); ?></p>
                            <p><strong>Email:</strong><a href="mailto:<?php echo esc_attr($pod->field('email')); ?>"><?php echo esc_html($pod->field('email')); ?></a>
                                <?php $email_to_check=$pod->field('email');if(is_email_blacklisted($email_to_check)){echo '<span style="margin-left:10px;font-style:italic;color:#888;">(Blacklisted)</span>';}else{$blacklist_nonce=wp_create_nonce('blacklist_email_'.$entry_id);$blacklist_url=admin_url('admin.php?page=tw-forms-inbox&action=blacklist&entry_id='.$entry_id.'&_wpnonce='.$blacklist_nonce);echo '<a href="'.esc_url($blacklist_url).'" class="blacklist-button">Blacklist</a>';}?>
                            </p>
                            <p style="margin-bottom:0"><strong>Phone:</strong> <?php echo esc_html($pod->field('phone')?:'N/A'); ?></p>
                        </div>
                        <div class="inside">
                            <?php // --- NEW DISPLAY LOGIC --- ?>
                            <?php if ( ! empty( $submitted_data ) && is_array( $submitted_data ) ) : ?>
                                <table class="tw-submission-data">
                                    <tbody>
                                        <?php foreach ( $submitted_data as $label => $value ) : ?>
                                            <?php if ( empty( $value ) ) continue; // Skip empty fields ?>
                                            <tr>
                                                <th><?php echo esc_html( $label ); ?></th>
                                                <td><?php echo nl2br( esc_html( $value ) ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : // Fallback for old entries ?>
                                <div class="message-content">
                                    <?php echo nl2br(esc_html(trim($user_message))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($notes)): ?><div class="notes-history"><h2>Notes & Reply History</h2><div class="note-item"><p><?php echo nl2br(esc_html($notes));?></p></div></div><?php endif; ?>
                    <div class="postbox quick-reply-form">
                        <h2 class="hndle">Quick Reply</h2>
                        <div class="inside"><form method="post"><textarea name="reply_message" required></textarea><input type="hidden" name="entry_id" value="<?php echo $entry_id;?>"><input type="hidden" name="action" value="send_reply"><?php wp_nonce_field('send_reply_'.$entry_id);?><?php submit_button('Send Reply');?><p class="description">A copy of this reply will be automatically saved as a note on this entry. Replies from the user will go to your main inbox.</p></form></div>
                    </div>
                </div>
                <div class="message-sidebar">
                    <div class="postbox"><h2 class="hndle">Entry Details</h2><div class="inside"><p><strong>Received:</strong> <?php echo get_the_date('F j, Y \a\t g:i a',$pod->id());?></p><p><strong>Source:</strong> <?php echo esc_html($pod->field('form_source'));?></p><p><strong>IP Address:</strong> <?php echo esc_html($pod->field('user_ip_address'));?></p><p><strong>Status:</strong> <?php echo esc_html($pod->field('entry_status'));?></p></div></div>
                    <div class="postbox"><h2 class="hndle">Actions</h2><div class="inside sidebar-actions"><a href="<?php echo get_delete_post_link($pod->id());?>" class="submitdelete">Trash Entry</a><a href="#">Print</a><hr><?php if(is_email_blacklisted($email_to_check)){echo '<span>Already Blacklisted</span>';}else{$blacklist_nonce=wp_create_nonce('blacklist_email_'.$entry_id);$blacklist_url=admin_url('admin.php?page=tw-forms-inbox&action=blacklist&entry_id='.$entry_id.'&_wpnonce='.$blacklist_nonce);echo '<a href="'.esc_url($blacklist_url).'" style="color:#a00;">Blacklist this Email</a>';}?></div></div>
                </div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'my_custom_message_settings_page' ) ) {
    function my_custom_message_settings_page() {
        ?><div class="wrap"><h1>Form Message Settings</h1><form method="post" action="options.php"><?php settings_fields('my_message_settings_group');do_settings_sections('my_message_settings_page');submit_button();?></form></div><?php
    }
}

if ( ! function_exists( 'my_custom_message_settings_init' ) ) {
    function my_custom_message_settings_init() {
        register_setting('my_message_settings_group','my_form_email_templates');
        add_settings_section('email_template_section','User Confirmation Email Template',function(){echo '<p>Customize the automated email sent to users after they submit a form. Use the available placeholders.</p><p>Available placeholders: <code>{user_name}</code>, <code>{form_source}</code>, <code>{submitted_data}</code></p>';},'my_message_settings_page');
        add_settings_field('user_email_subject','Subject',function(){$options=get_option('my_form_email_templates');echo '<input type="text" name="my_form_email_templates[subject]" value="'.esc_attr($options['subject']??'Thank you for your submission!').'" class="regular-text">';},'my_message_settings_page','email_template_section');
        add_settings_field('user_email_body','Body',function(){$options=get_option('my_form_email_templates');$default_body="Hello {user_name},\n\nWe have successfully received your submission from the {form_source}.\n\nFor your records, here is a copy of the information you submitted:\n\n{submitted_data}\n\nSincerely,\nThe Team";echo '<textarea name="my_form_email_templates[body]" rows="10" class="large-text">'.esc_textarea($options['body']??$default_body).'</textarea>';},'my_message_settings_page','email_template_section');
        
        register_setting('my_message_settings_group','my_recaptcha_settings');
        add_settings_section('recaptcha_settings_section','Google reCAPTCHA v3 Settings',function(){echo '<p>Enter your Google reCAPTCHA v3 keys here to enable spam protection on all forms. Get keys from the <a href="https://www.google.com/recaptcha/admin/create" target="_blank">reCAPTCHA Admin Console</a>.</p>';},'my_message_settings_page');
        add_settings_field('disable_recaptcha','Disable reCAPTCHA',function(){$options=get_option('my_recaptcha_settings');$checked=isset($options['disable'])&&$options['disable']==='1'?'checked="checked"':'';echo '<label><input type="checkbox" name="my_recaptcha_settings[disable]" value="1" '.$checked.'> Temporarily disable reCAPTCHA for testing.</label>';},'my_message_settings_page','recaptcha_settings_section');
        add_settings_field('enable_debug_mode','Enable Debug Mode',function(){$options=get_option('my_recaptcha_settings');$checked=isset($options['debug'])&&$options['debug']==='1'?'checked="checked"':'';echo '<label><input type="checkbox" name="my_recaptcha_settings[debug]" value="1" '.$checked.'> Output diagnostic info to the browser console and page.</label>';},'my_message_settings_page','recaptcha_settings_section');
        add_settings_field('recaptcha_site_key','Site Key',function(){$options=get_option('my_recaptcha_settings');echo '<input type="text" name="my_recaptcha_settings[site_key]" value="'.esc_attr($options['site_key']??'').'" class="regular-text">';},'my_message_settings_page','recaptcha_settings_section');
        add_settings_field('recaptcha_secret_key','Secret Key',function(){$options=get_option('my_recaptcha_settings');echo '<input type="text" name="my_recaptcha_settings[secret_key]" value="'.esc_attr($options['secret_key']??'').'" class="regular-text">';},'my_message_settings_page','recaptcha_settings_section');
        add_settings_field('recaptcha_score_threshold','Score Threshold',function(){$options=get_option('my_recaptcha_settings');echo '<input type="number" step="0.1" min="0.1" max="1.0" name="my_recaptcha_settings[score_threshold]" value="'.esc_attr($options['score_threshold']??'0.5').'" class="small-text"> <p class="description">Submissions with a score below this value will be blocked. Default is 0.5. Try 0.3 if you are losing valid submissions.</p>';},'my_message_settings_page','recaptcha_settings_section');
    }
    add_action('admin_init', 'my_custom_message_settings_init');
}

// =============================================================================
// == ADMIN MENU
// =============================================================================
if ( ! function_exists( 'my_custom_message_viewer_menu' ) ) {
    function my_custom_message_viewer_menu() {
        
        $parent_slug = 'tw-forms-dashboard';
        $inbox_slug = 'tw-forms-inbox';
        $base_page_url = 'admin.php?page=' . $inbox_slug;

        if ( isset($_GET['page']) && $_GET['page'] === $inbox_slug ) {
            if (isset($_GET['action']) && !isset($_GET['action2']) && isset($_GET['entry_id'])) {
                $entry_id=intval($_GET['entry_id']);$action=sanitize_key($_GET['action']);$redirect_url=admin_url($base_page_url);if(isset($_REQUEST['post_status'])){$redirect_url=add_query_arg('post_status',sanitize_key($_REQUEST['post_status']),$redirect_url);}
                switch ($action) {
                    case 'blacklist':if(isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'blacklist_email_'.$entry_id)){$pod=pods('messages',$entry_id);if($pod->exists()){$email_to_blacklist=$pod->field('email');if($email_to_blacklist&&!is_email_blacklisted($email_to_blacklist)){pods('blacklist')->add(['post_title'=>$email_to_blacklist,'post_status'=>'publish','email_address'=>$email_to_blacklist]);}wp_safe_redirect(admin_url('admin.php?page=tw-forms-inbox&action=view&entry_id='.$entry_id.'&blacklisted=true'));exit;}}else{wp_die('Security check failed.');}break;
                    case 'trash':if(isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'trash_message_'.$entry_id)){wp_trash_post($entry_id);wp_safe_redirect($redirect_url);exit;}break;
                    case 'untrash':if(isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'untrash_message_'.$entry_id)){wp_untrash_post($entry_id);$redirect_url=remove_query_arg('post_status',$redirect_url);wp_safe_redirect($redirect_url);exit;}break;
                    case 'delete':if(isset($_GET['_wpnonce'])&&wp_verify_nonce($_GET['_wpnonce'],'delete_message_'.$entry_id)){wp_delete_post($entry_id,true);wp_safe_redirect($redirect_url);exit;}break;
                }
            }
            if (isset($_POST['action']) && $_POST['action'] === 'send_reply' && isset($_POST['entry_id'])) {
                $entry_id = intval($_POST['entry_id']); check_admin_referer('send_reply_' . $entry_id); $pod = pods('messages', $entry_id);
                if ($pod->exists()) {
                    $reply_message=sanitize_textarea_field($_POST['reply_message']);$to=$pod->field('email');$subject='Re: Your submission from '.$pod->field('form_source');$admin_email=get_option('admin_email');$headers=['Reply-To: '.$admin_email];
                    if(wp_mail($to,$subject,$reply_message,$headers)){$current_notes=$pod->field('notes');if(is_array($current_notes)){$current_notes='';}$new_note="--- Replied on ".current_time('mysql')." ---\n".$reply_message;$pod->save('notes',$current_notes."\n\n".$new_note);$pod->save('entry_status','Replied');wp_safe_redirect(admin_url('admin.php?page=tw-forms-inbox&action=view&entry_id='.$entry_id.'&replied=true'));exit;}
                }
            }
            $list_table = new Messages_List_Table(); $action = $list_table->current_action(); $entry_ids = isset($_REQUEST['entry_id']) ? (is_array($_REQUEST['entry_id']) ? $_REQUEST['entry_id'] : [$_REQUEST['entry_id']]) : []; $entry_ids = array_map('intval', $entry_ids);
            if ( in_array( $action, [ 'mark_read', 'mark_unread', 'trash', 'untrash', 'delete' ] ) && ! empty( $entry_ids ) ) { 
                check_admin_referer('bulk-messages'); 
                foreach($entry_ids as $entry_id){switch($action){case 'mark_read':$pod=pods('messages',$entry_id);if($pod->exists()){$pod->save('entry_status','Read');}break;case 'mark_unread':$pod=pods('messages',$entry_id);if($pod->exists()){$pod->save('entry_status','Unread');}break;case 'trash':wp_trash_post($entry_id);break;case 'untrash':wp_untrash_post($entry_id);break;case 'delete':wp_delete_post($entry_id,true);break;}} 
                $redirect_url=remove_query_arg(['action','action2','entry_id','_wpnonce']);if($action==='untrash'){$redirect_url=remove_query_arg('post_status',$redirect_url);}wp_safe_redirect($redirect_url);exit; 
            }
        }
        
        if($unread_count=pods('messages',['where'=>['entry_status.meta_value'=>'Unread']])->total_found()){
            global $menu;
            foreach($menu as $key=>$item){
                if(isset($item[2]) && $item[2] === $parent_slug){
                    $menu[$key][0] .= ' <span class="awaiting-mod">'.$unread_count.'</span>';
                    break;
                }
            }
        }
        
        add_menu_page('TW Forms Dashboard', 'TW Forms', 'edit_posts', $parent_slug, 'tw_forms_render_dashboard_page', 'dashicons-email-alt', 26);
        add_submenu_page( $parent_slug, 'All Messages', 'All Messages', 'edit_posts', $inbox_slug,
            function() {
                if(isset($_GET['action']) && $_GET['action']==='view'){ render_message_detail_view(); } 
                else {
                    $post_status=isset($_REQUEST['post_status'])&&$_REQUEST['post_status']==='trash'?'trash':'all';
                    $page_title=($post_status==='trash')?'Trashed Messages':'Message Entries';
                    echo '<div class="wrap"><h1>'.esc_html($page_title).'</h1><form method="post">';
                    $list_table=new Messages_List_Table(); $list_table->views(); $list_table->prepare_items(); $list_table->search_box('Search Messages','search_id'); $list_table->display();
                    echo '</form></div>';
                }
            }
        );

        add_submenu_page($parent_slug, 'All Forms', 'All Forms', 'edit_posts', 'edit.php?post_type=tw_form');
        add_submenu_page($parent_slug, 'Add New Form', 'Add New Form', 'edit_posts', 'post-new.php?post_type=tw_form');
        add_submenu_page($parent_slug, 'Blacklist Manager', 'Blacklist Manager', 'edit_posts', 'edit.php?post_type=blacklist');
        add_submenu_page($parent_slug, 'Form Settings', 'Settings', 'manage_options', 'my_message_settings_page', 'my_custom_message_settings_page');
    }
    add_action('admin_menu', 'my_custom_message_viewer_menu', 9);
}


// =============================================================================
// == ADMIN BAR MENU & STYLES
// =============================================================================
if ( ! function_exists( 'my_custom_messages_admin_bar_menu' ) ) {
    function my_custom_messages_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('edit_posts')) { return; }
        $unread_count = pods('messages', ['where' => ['entry_status.meta_value' => 'Unread']])->total_found();
        $title = __('TW Forms'); 
        if ($unread_count > 0) { $title .= ' <span class="ab-label awaiting-mod">' . $unread_count . '</span>'; }
        $wp_admin_bar->add_node(['id'=>'my_messages_menu','title'=>$title,'href'=>admin_url('admin.php?page=tw-forms-dashboard')]);
        $wp_admin_bar->add_node(['id'=>'all_messages','parent'=>'my_messages_menu','title'=>__('All Messages'),'href'=>admin_url('admin.php?page=tw-forms-inbox')]);
        $wp_admin_bar->add_node(['id'=>'all_forms_link','parent'=>'my_messages_menu','title'=>__('All Forms'),'href'=>admin_url('edit.php?post_type=tw_form')]);
        $wp_admin_bar->add_node(['id'=>'message_settings','parent'=>'my_messages_menu','title'=>__('Form Settings'),'href'=>admin_url('admin.php?page=my_message_settings_page')]);
    }
    add_action('admin_bar_menu', 'my_custom_messages_admin_bar_menu', 90);
}

if ( ! function_exists( 'custom_admin_styles_for_messages' ) ) {
    function custom_admin_styles_for_messages() { 
        echo '<style>
            .status-indicator-dot{font-size:1.5em;line-height:1;vertical-align:middle;margin-right:8px}
            .red-dot{color:#d63638}.green-dot{color:#28a745}.purple-dot{color:#6f42c1}.blue-dot{color:#3498db}
            #adminmenu a[href*="tw-forms-dashboard"] .awaiting-mod,
            #wp-admin-bar-my_messages_menu .awaiting-mod {
                display: inline-block; vertical-align: text-top; background-color: #d63638;
                color: #fff; font-size: 10px; line-height: 16px; height: 16px;
                min-width: 16px; text-align: center; border-radius: 8px;
                font-weight: 600; padding: 0 5px; margin-left: 4px;
            }
        </style>'; 
    }
    add_action('admin_head', 'custom_admin_styles_for_messages');
}

// ==================================================================
// == BLACKLIST ADMIN UI TWEAKS
// ==================================================================

if ( ! function_exists( 'my_custom_blacklist_admin_view_tweaks' ) ) {
    function my_custom_blacklist_admin_view_tweaks() {
        add_filter( 'manage_blacklist_posts_columns', function( $columns ) { $columns['title'] = 'Blacklisted Email Address'; return $columns; });
        add_filter( 'post_row_actions', function( $actions, $post ) { if ( $post->post_type === 'blacklist' ) { unset( $actions['edit'], $actions['inline hide-if-no-js'], $actions['view'] ); } return $actions; }, 10, 2 );
        add_filter( 'bulk_actions-edit-blacklist', function( $actions ) { unset( $actions['edit'] ); return $actions; });
    }
    add_action( 'admin_init', 'my_custom_blacklist_admin_view_tweaks' );
}

if ( ! function_exists('my_custom_blacklist_admin_styles') ) {
    function my_custom_blacklist_admin_styles() {
        $screen = get_current_screen();
        if ( 'edit-blacklist' === $screen->id ) { echo '<style>.post-type-blacklist .column-title strong a { color: inherit; pointer-events: none; }</style>'; }
    }
    add_action( 'admin_head', 'my_custom_blacklist_admin_styles' );
}
