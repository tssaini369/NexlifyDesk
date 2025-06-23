=== NexlifyDesk ===
Contributors: nexlifylabs
Tags: support, ticketing, helpdesk, customer support, support system
Requires at least: 6.2
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.4

A complete WordPress support ticketing system with categories, attachments, email notifications, and agent roles.

== Description ==

NexlifyDesk is a powerful and user-friendly support ticketing system for WordPress. It enables you to provide exceptional customer service by allowing users to easily submit, track, and manage their support requests directly from your website. Agents can efficiently handle tickets through a dedicated admin interface, with features designed to improve response times and resolution rates.

== Key Features ==

*   **Frontend Ticket Submission & Management:** Users can create new tickets, view their existing tickets, and add replies with attachments.
*   **Admin Ticket Dashboard:** A central place for administrators and agents to view, manage, assign, and respond to all support tickets.
*   **Ticket Categories & Priorities:** Organize tickets effectively for better workflow and prioritization.
*   **File Attachments:** Allow users and agents to attach relevant files to tickets and replies (configurable allowed types and size).
*   **Customizable Email Notifications:** Keep users and agents informed with automated email notifications for new tickets, replies, status changes, and SLA breaches. Email templates are fully customizable with placeholders.
*   **Agent Roles & Permissions:** Define custom agent positions with specific capabilities (e.g., view all tickets, assign tickets, manage categories).
*   **SLA (Service Level Agreement) Monitoring:** Set expected response times and get notified of potential breaches to ensure timely support.
*   **Responsive Design:** The plugin interface is designed to work seamlessly across desktops, tablets, and mobile devices.
*   **Shortcodes for Easy Integration:** Embed ticket forms and ticket lists anywhere on your site using simple shortcodes.
*   **Secure & Robust:** Built with WordPress best practices, including nonce verification for forms and AJAX, and proper data sanitization.
*   **Database Management:** Creates dedicated tables for tickets, replies, attachments, and categories.
*   **AJAX Powered:** Smooth and fast interactions for submitting tickets, replies, and managing categories without page reloads.

== Installation ==

1.  Upload the `nexlifydesk` plugin folder to the `/wp-content/plugins/` directory via FTP, or install directly through the WordPress plugins screen by uploading the ZIP file.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to **NexlifyDesk > Settings** in your WordPress admin panel to configure the plugin according to your needs (e.g., email notification settings, file upload limits, default priority).
4.  Create necessary pages for your users:
    *   A page for submitting new tickets: Add the shortcode `[nexlifydesk_ticket_form]` to this page.
    *   A page for users to view their submitted tickets: Add the shortcode `[nexlifydesk_ticket_list]` to this page.
5.  (Optional) Configure Agent Positions and assign users to these positions if you have a support team.

== Frequently Asked Questions ==

= How do I create a page for users to submit tickets? =

Simply create a new page in WordPress and add the following shortcode to its content: `[nexlifydesk_ticket_form]`

= How can users see their submitted tickets? =

Create another page and add this shortcode: `[nexlifydesk_ticket_list]`. Logged-in users will see their tickets on this page.

= Can I customize the email notifications? =

Yes. Go to **NexlifyDesk > Email Templates** in the WordPress admin area. You can edit the content for new tickets, new replies, status changes, and SLA breach notifications. A list of available placeholders (like `{ticket_id}`, `{subject}`, `{user_name}`) is provided on the page.

= How do I set up support agents? =

1.  Go to **Users > Add New** in your WordPress admin area and create a new user for each support agent (or use an existing user).
2.  Assign the **NexlifyDesk Agent** role to the user, or give them a role with the `nexlifydesk_manage_tickets` capability.
3.  (Optional) Go to **NexlifyDesk > Agent Positions** to define different roles for your support staff (e.g., "Level 1 Support", "Technical Lead") and assign specific capabilities to each position.
4.  To assign an agent to a position, edit their user profile in WordPress (**Users > All Users**). You will find a "NexlifyDesk Agent Position" dropdown.

= What types of files can be attached to tickets? =

By default, common image and document types are allowed (e.g., jpg, png, pdf, docx). You can customize the list of allowed file extensions and the maximum file size (in MB) from the **NexlifyDesk > Settings** page.

= Is NexlifyDesk compatible with my WordPress theme? =

NexlifyDesk is designed to be compatible with most WordPress themes. It includes its own stylesheet (`assets/css/nexlifydesk.css`) for a consistent look and feel, which is also responsive. If you encounter styling issues, you can override the plugin's CSS with your theme's custom CSS.

== Screenshots ==

1.  **Frontend Ticket Submission Form:** Shows the form users fill out to create a new support ticket.
2.  **Frontend User Ticket List:** Displays a list of tickets submitted by the logged-in user.
3.  **Frontend Single Ticket View:** Shows the details of a specific ticket, including its message, replies, and attachments.
4.  **Admin Ticket Management Dashboard:** The main interface for administrators and agents to view and manage all tickets.
5.  **Admin Single Ticket View & Reply:** How agents view ticket details and add replies in the admin area.
6.  **Category Management Page:** Interface for adding, editing, and deleting ticket categories.
7.  **Plugin Settings Page:** Shows the various options available for configuring NexlifyDesk.
8.  **Agent Positions Configuration:** Page for creating agent roles and assigning capabilities.
9.  **Email Templates Editor:** Interface for customizing email notification content.

== Usage ==

=== Shortcodes ===

*   `[nexlifydesk_ticket_form]` - Displays the form for users to submit new support tickets.
    *   Optional attributes:
        *   `show_title="no"`: Hides the default "Submit a Ticket" title.
*   `[nexlifydesk_ticket_list]` - Displays a list of tickets for the currently logged-in user. If an agent with permission to view assigned tickets views this page, they will see tickets assigned to them.
    *   Optional attributes:
        *   `show_title="no"`: Hides the default "Your Submitted Tickets" or "Your Assigned Tickets" title.

=== Admin Menu ===

Once activated, NexlifyDesk adds the following items to your WordPress admin menu:

*   **NexlifyDesk** (Main Menu)
    *   **All Tickets:** View, manage, and reply to all support tickets.
    *   **Categories:** Create and manage ticket categories.
    *   **Settings:** Configure general plugin settings, email options, file uploads, and SLA.
    *   **Reports:** (Coming Soon) View statistics on ticket volume, response times, etc.
    *   **Agent Positions:** Define roles for your support agents and assign specific capabilities.
    *   **Email Templates:** Customize the content of email notifications sent by the plugin.
    *   **Support:** Contact us at support@nexlifylabs.com.

== Customization ==

NexlifyDesk offers several ways to tailor it to your needs:

*   **Settings Panel:** Access **NexlifyDesk > Settings** to configure options like email notification preferences, default ticket priority, auto-assignment rules, allowed file types, maximum file size, and SLA response times.
*   **Email Templates:** Modify the content of all outgoing emails from **NexlifyDesk > Email Templates**. Use the provided placeholders to include dynamic ticket information.
*   **CSS Styling:** The plugin comes with its own CSS (`assets/css/nexlifydesk.css`). You can override these styles using your theme's custom CSS or a child theme's stylesheet to match your site's design.
*   **Agent Capabilities:** Through **NexlifyDesk > Agent Positions**, you can create granular roles for your support staff, controlling exactly what each agent can do within the system.
*   **Template Overriding (Advanced):** For more significant structural changes, you can copy template files from the `nexlifydesk/templates/` directory into a `nexlifydesk` folder within your theme's directory (e.g., `yourtheme/nexlifydesk/frontend/ticket-form.php`). The plugin will then use your theme's version. *Note: This requires careful handling during plugin updates.*

== Changelog ==

= 1.0.0 =
*   Initial public release.
*   Core ticketing functionality: submission, replies, attachments.
*   Admin management for tickets, categories, and settings.
*   Agent roles and permissions system.
*   Email notifications with customizable templates.
*   SLA monitoring.
*   Shortcodes for frontend integration.

== Upgrade Notice ==

= 1.0.0 =
This is the first version of NexlifyDesk. No upgrade steps are necessary if installing for the first time.

== Support ==

If you require assistance or have a feature request, please contact us at support@nexlifylabs.com or visit our website at https://nexlifylabs.com.

== Privacy ==

NexlifyDesk stores support tickets, replies, and attachments in your WordPress database. No data is sent to third-party servers. All email notifications are sent via your site's configured mail system. For more details, see our privacy policy at https://nexlifylabs.com/privacy-policy.

== Uninstall ==

By default, when you uninstall NexlifyDesk, all plugin data (tickets, replies, attachments, categories, and settings) will be preserved in your database.  
If you wish to permanently delete all plugin data upon uninstall, you can disable the "Keep all tickets and data when plugin is uninstalled" option in NexlifyDesk > Settings.  
**Warning:** If this option is unchecked, all plugin data will be permanently deleted when the plugin is uninstalled. Please back up your database if you wish to keep your data.