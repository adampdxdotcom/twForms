<?php
/**
 * Renders the custom dashboard page for TW Forms.
 *
 * @package TW_Forms
 * @version 2.6.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'tw_forms_render_dashboard_page' ) ) {
    /**
     * The main callback function to render the dashboard page HTML.
     */
    function tw_forms_render_dashboard_page() {
        ?>
        <div class="wrap tw-forms-dashboard">
            <h1>TW Forms Dashboard</h1>
            <p>Welcome to the Theatre West forms management dashboard. Here you can see a summary of your data and quickly access key areas.</p>

            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div id="postbox-container-1" class="postbox-container">
                        <?php tw_forms_dashboard_recent_messages(); ?>
                    </div>
                    <div id="postbox-container-2" class="postbox-container">
                        <?php tw_forms_dashboard_at_a_glance(); ?>
                        <?php tw_forms_dashboard_quick_links(); ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .tw-forms-dashboard .postbox-container { width: 50%; }
            .tw-forms-dashboard .at-a-glance-widgets { display: flex; flex-wrap: wrap; gap: 15px; margin: -5px; }
            .tw-forms-dashboard .glance-widget { flex: 1 1 120px; background-color: #fff; border: 1px solid #ccd0d4; padding: 20px; text-align: center; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .tw-forms-dashboard .glance-widget .stat-number { font-size: 2.5em; font-weight: 500; display: block; line-height: 1.2; }
            .tw-forms-dashboard .glance-widget .stat-label { font-size: 1em; color: #555; }
            .tw-forms-dashboard .quick-links-container { display: flex; flex-wrap: wrap; gap: 10px; }
            .tw-forms-dashboard .recent-messages-list { list-style: none; margin: 0; padding: 0; }
            .tw-forms-dashboard .recent-messages-list li { padding: 10px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
            .tw-forms-dashboard .recent-messages-list li:last-child { border-bottom: none; }
            .tw-forms-dashboard .recent-messages-list .sender { font-weight: bold; }
            .tw-forms-dashboard .recent-messages-list .date { font-size: 0.9em; color: #777; }
        </style>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_dashboard_at_a_glance' ) ) {
    /**
     * Renders the "At a Glance" stats widget.
     */
    function tw_forms_dashboard_at_a_glance() {
        // Get stats
        $unread_count = pods('messages', ['where' => 'entry_status.meta_value = "Unread"'])->total_found();
        $total_forms = wp_count_posts('tw_form')->publish;
        $total_messages = wp_count_posts('messages')->publish;
        $messages_this_month = pods('messages', ['where' => 't.post_date >= "' . date('Y-m-01 00:00:00') . '"'])->total_found();
        $blacklisted = wp_count_posts('blacklist')->publish;
        
        $stats = [
            'Unread Messages' => $unread_count,
            'Total Forms' => $total_forms,
            'Total Messages' => $total_messages,
            'Messages This Month' => $messages_this_month,
            'Blacklisted Emails' => $blacklisted,
        ];
        ?>
        <div class="postbox">
            <h2 class="hndle"><span>At a Glance</span></h2>
            <div class="inside">
                <div class="at-a-glance-widgets">
                    <?php foreach ( $stats as $label => $number ) : ?>
                    <div class="glance-widget">
                        <span class="stat-number"><?php echo esc_html( number_format_i18n( $number ) ); ?></span>
                        <span class="stat-label"><?php echo esc_html( $label ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_dashboard_quick_links' ) ) {
    /**
     * Renders the "Quick Links" action buttons widget.
     */
    function tw_forms_dashboard_quick_links() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span>Quick Links</span></h2>
            <div class="inside">
                <div class="quick-links-container">
                    <a href="<?php echo admin_url('post-new.php?post_type=tw_form'); ?>" class="button button-secondary">+ Add New Form</a>
                    <a href="<?php echo admin_url('admin.php?page=tw-forms-inbox'); ?>" class="button button-secondary">View Message Inbox</a>
                    <a href="<?php echo admin_url('admin.php?page=my_message_settings_page'); ?>" class="button button-secondary">Go to Settings</a>
                </div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_dashboard_recent_messages' ) ) {
    /**
     * Renders the "Recent Unread Messages" widget.
     */
    function tw_forms_dashboard_recent_messages() {
        $recent_pods = pods('messages', [
            'limit' => 5,
            'orderby' => 't.post_date DESC',
            'where' => 'entry_status.meta_value = "Unread"'
        ]);
        ?>
        <div class="postbox">
            <h2 class="hndle"><span>Recent Unread Messages</span></h2>
            <div class="inside">
                <?php if ( $recent_pods->total() > 0 ) : ?>
                <ul class="recent-messages-list">
                    <?php while ( $recent_pods->fetch() ) : ?>
                    <li>
                        <div>
                            <a class="sender" href="<?php echo admin_url('admin.php?page=tw-forms-inbox&action=view&entry_id=' . $recent_pods->id()); ?>">
                                <?php echo esc_html( $recent_pods->field('messenger_name') ); ?>
                            </a>
                            <div class="date"><?php echo esc_html( get_the_date( 'M j, Y', $recent_pods->id() ) ); ?></div>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=tw-forms-inbox&action=view&entry_id=' . $recent_pods->id()); ?>" class="button button-small">View</a>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else : ?>
                <p>No unread messages. Great job!</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
