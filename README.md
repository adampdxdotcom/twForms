# TW Forms - Custom Form & Inbox System for WordPress

A custom functionality plugin for WordPress to manage form submissions, provide a secure message inbox, and handle spam protection.

---

## About This Project

This project was designed as a custom form handling and messaging system for the Theatre West website. The basic requirements were that I didn't want to use a 3rd party plugin.

## Key Features

-   **Secure Message Inbox**: A custom admin interface built with `WP_List_Table` to view all form submissions.
    -   Search, sort, and filter messages.
    -   Full trash management (Move to Trash, Restore, Delete Permanently).
    -   Bulk actions to manage multiple messages at once.
-   **Detailed Message Viewer**:
    -   A clean interface to read individual messages.
    -   "Quick Reply" functionality to email the sender directly from the admin panel.
    -   Ability to add private notes to each submission.
-   **Blacklist Management**:
    -   One-click blacklisting of sender email addresses to prevent future submissions.
    -   A simple admin screen to view and manage all blacklisted emails.
-   **Three Custom Forms**: Provides secure, user-friendly forms via shortcodes.
    -   `[membership_form]`
    -   `[volunteer_form]`
    -   `[contact_form]`
-   **Advanced Spam Protection**: A multi-layered approach to stop spam.
    -   **Google reCAPTCHA v3**: Transparently scores users to block bots.
    -   **Honeypot Field**: A hidden field that tricks bots into revealing themselves.
    -   **Time-based Check**: Blocks submissions that happen too quickly for a human to complete.
-   **Dynamic Notifications**:
    -   Route admin notifications for each form to different email addresses.
    -   Send customizable HTML confirmation emails to users after submission.
-   **Centralized Settings Panel**: A dedicated settings page under `Messages > Settings` to configure:
    -   Google reCAPTCHA v3 Site & Secret Keys.
    -   Email routing for each form.
    -   User-facing email confirmation templates.

## Installation

1.  Download or clone this repository to your local machine.
2.  Using an FTP client or your hosting file manager, upload the entire `twForms` folder to your WordPress site's `/wp-content/plugins/` directory.
3.  Navigate to the **Plugins** page in your WordPress admin dashboard.
4.  Find **"TW Forms"** in the list and click **"Activate"**.

**Important:** Before activating, ensure any previous versions of these scripts running in a snippets plugin have been deactivated to prevent function conflicts.

## Usage & Configuration

### Displaying Forms

To display a form on any page or post, simply use the corresponding shortcode in the content editor:

-   **Membership Form**: `[membership_form]`
-   **Volunteer Form**: `[volunteer_form]`
-   **Contact Form**: `[contact_form]`

### Admin Panel

Once activated, the plugin adds a **"Messages"** menu item to your WordPress admin sidebar. This is the central hub for all functionality:

-   **Messages → All Messages**: The main inbox to view submissions.
-   **Messages → Trash**: View and manage trashed submissions.
-   **Messages → Blacklist Manager**: View and remove blacklisted emails.
-   **Messages → Settings**: Configure all plugin options.

### Initial Setup

After activating, navigate to **Messages > Settings** to configure the following:

1.  **Email Routing**: Enter the email addresses that should receive notifications for each form.
2.  **Google reCAPTCHA v3**: Add your Site Key and Secret Key to enable spam protection.
3.  **Email Templates**: Customize the confirmation email sent to users.

## Dependencies

This plugin has one hard dependency:

-   **[Pods Framework](https://wordpress.org/plugins/pods/)**: The Pods plugin is **required** for this plugin to function. It is used to create and manage the `messages` and `blacklist` Custom Post Types where all data is stored.

## File Structure

The plugin is organized into a modular structure for easy maintenance and future development.

```
/twForms/
├── twForms.php                 # Main plugin file (the "conductor")
└── /includes/
    ├── 01-admin-inbox.php      # All backend UI code (message list, detail view, blacklist UI)
    ├── 02-form-helpers.php     # Reusable functions (validation, email, Pods logging, reCAPTCHA)
    └── 03-form-processor.php   # Frontend code (shortcodes, form handlers, JS/CSS enqueueing)
```

## License

This project is licensed under the GPLv2 or later License.
