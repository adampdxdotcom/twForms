<?php
/**
 * Renders the custom dashboard page for TW Forms.
 *
 * @package TW_Forms
 * @version 2.7.0
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
            <h1 class="tw-dashboard-title">TW Forms Dashboard</h1>
            <p class="tw-dashboard-intro">Welcome to the Theatre West forms management dashboard. Here you can see a summary of your data and quickly access key areas.</p>

            <?php // -- 1. Full-Width "At a Glance" Section -- ?>
            <?php tw_forms_dashboard_at_a_glance(); ?>
            
            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">

                    <?php // -- 2. Main Content Column (Wider) -- ?>
                    <div id="postbox-container-1" class="postbox-container">
                        <?php tw_forms_dashboard_recent_messages(); ?>
                    </div>

                    <?php // -- 3. Sidebar Column (Narrower) -- ?>
                    <div id="postbox-container-2" class="postbox-container">
                        <?php tw_forms_dashboard_quick_links(); ?>
                    </div>

                </div>
            </div>
        </div>

        <style>
            /* Main Layout & Typography */
            .tw-forms-dashboard .tw-dashboard-title { font-size: 28px; font-weight: 500; }
            .tw-forms-dashboard .tw-dashboard-intro { font-size: 1.1em; color: #555; margin-bottom: 25px; }

            /* New 2-Column Layout */
            .tw-forms-dashboard #dashboard-widgets.metabox-holder { display: flex; gap: 20px; }
            .tw-forms-dashboard #postbox-container-1 { flex: 2; min-width: 0; } /* 2/3 width */
            .tw-forms-dashboard #postbox-container-2 { flex: 1; min-width: 0; } /* 1/3 width */

            /* "At a Glance" Widget Styles */
            .tw-forms-dashboard .at-a-glance-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
                margin-bottom: 25px;
            }
            .tw-forms-dashboard .glance-widget {
                background-color: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                text-align: center;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }
            .tw-forms-dashboard .glance-widget .dashicons {
                font-size: 28px;
                width: 40px;
                height: 40px;
                line-height: 40px;
                color: #555;
            }
            .tw-forms-dashboard .glance-widget .stat-number {
                font-size: 2.5em;
                font-weight: 500;
                display: block;
                line-height: 1.1;
            }
            .tw-forms-dashboard .glance-widget .stat-label {
                font-size: 1em;
                color: #555;
            }

            /* Hero "Unread" Widget Style */
            .tw-forms-dashboard .glance-widget.hero-stat {
                background-color: #f0f6fc;
                border-color: #7e8993;
            }
            .tw-forms-dashboard .glance-widget.hero-stat .dashicons {
                color: #0073aa;
            }

            /* Quick Links Widget Styles */
            .tw-forms-dashboard .quick-links-container { display: flex; flex-wrap: wrap; gap: 10px; }

            /* Recent Unread Messages Widget Styles */
            .tw-forms-dashboard .recent-messages-list { list-style: none; margin: 0; padding: 0; }
            .tw-forms-dashboard .recent-messages-list li { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
            .tw-forms-dashboard .recent-messages-list li:last-child { border-bottom: none; }
            .tw-forms-dashboard .recent-messages-list .sender-info { display: flex; align-items: center; gap: 10px; }
            .tw-forms-dashboard .recent-messages-list .status-dot { font-size: 1.5em; color: #28a745; line-height: 1; }
            .tw-forms-dashboard .recent-messages-list .sender { font-weight: bold; }
            .tw-forms-dashboard .recent-messages-list .meta-info { font-size: 0.9em; color: #777; }
        </style>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_dashboard_at_a_glance' ) ) {
    function tw_forms_dashboard_at_a_glance() {
        $unread_count = pods('messages', ['where' => 'entry_status.meta_value = "Unread"'])->total_found();
        $total_forms = wp_count_posts('tw_form')->publish;
        $total_messages = wp_count_posts('messages')->publish;
        $messages_this_month = pods('messages', ['where' => 't.post_date >= "' . date('Y-m-01 00:00:00') . '"'])->total_found();
        $blacklisted = wp_count_posts('blacklist')->publish;
        
        $stats = [
            'Unread Messages' => ['number' => $unread_count, 'icon' => 'dashicons-email-alt', 'hero' => true],
            'Total Forms' => ['number' => $total_forms, 'icon' => 'dashicons-media-text', 'hero' => false],
            'Total Messages' => ['number' => $total_messages, 'icon' => 'dashicons-inbox', 'hero' => false],
            'Messages This Month' => ['number' => $messages_this_month, 'icon' => 'dashicons-calendar-alt', 'hero' => false],
            'Blacklisted Emails' => ['number' => $blacklisted, 'icon' => 'dashicons-shield-alt', 'hero' => false],
        ];
        ?>
        <div class="at-a-glance-container">
            <?php foreach ( $stats as $label => $data ) : ?>
            <div class="glance-widget <?php echo $data['hero'] ? 'hero-stat' : ''; ?>">
                <span class="dashicons <?php echo esc_attr($data['icon']); ?>"></span>
                <span class="stat-number"><?php echo esc_html( number_format_i18n( $data['number'] ) ); ?></span>
                <span class="stat-label"><?php echo esc_html( $label ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_dashboard_quick_links' ) ) {
    function tw_forms_dashboard_quick_links() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span>Quick Links</span></h2>
            <div class="inside">
                <div class="quick-links-container">
                    <a href="<?php echo admin_url('post-new.php?post_type=tw_form'); ?>" class="button button-primary">+ Add New Form</a>
                    <a href="<?php echo admin_url('admin.php?page=tw-forms-inbox'); ?>" class="button button-secondary">View Message Inbox</a>
                    <a href="<?php echo admin_url('admin.php?page=my_message_settings_page'); ?>" class="button button-secondary">Go to Settings</a>
                </div>
            </div>
        </div>
        <?php
    }
}

if ( ! function_exists( 'tw_forms_dashboard_recent_messages' ) ) {
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
                        <div class="sender-info">
                            <span class="status-dot">●</span>
                            <div>
                                <a class="sender" href="<?php echo admin_url('admin.php?page=tw-forms-inbox&action=view&entry_id=' . $recent_pods->id()); ?>">
                                    <?php echo esc_html( $recent_pods->field('messenger_name') ); ?>
                                </a>
                                <div class="meta-info">
                                    from <em><?php echo esc_html($recent_pods->field('form_source')); ?></em>
                                    &bull; 
                                    <?php echo esc_html( human_time_diff( get_the_time('U', $recent_pods->id()), current_time('timestamp') ) ) . ' ago'; ?>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=tw-forms-inbox&action=view&entry_id=' . $recent_pods->id()); ?>" class="button button-secondary">View</a>
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
