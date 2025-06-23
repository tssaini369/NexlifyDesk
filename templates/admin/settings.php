<?php
if (!defined('ABSPATH')) {
    exit;
}

    $default_settings = array(
        'email_notifications' => 1,
        'default_priority' => 'medium',
        'auto_assign' => 0,
        'allowed_file_types' => 'jpg,jpeg,png,pdf',
        'max_file_size' => 2,
        'default_category' => 1,
        'sla_response_time' => 24,
        'ticket_page_id' => 0,
        'ticket_form_page_id' => 0,
        'ticket_id_prefix' => 'T',
        'ticket_id_start' => 1001,
        'keep_data_on_uninstall' => 1,
        'status_change_notification' => 1,
    );

    $settings = get_option('nexlifydesk_settings', $default_settings);

    $settings = array_merge($default_settings, array_filter($settings, function($key) use ($default_settings) {
        return array_key_exists($key, $default_settings);
    }, ARRAY_FILTER_USE_KEY));

    foreach ($settings as $key => $value) {
        if (in_array($key, array('default_priority', 'allowed_file_types', 'ticket_id_prefix'), true)) {
            $settings[$key] = sanitize_text_field($value);
        } elseif (in_array($key, array('max_file_size', 'default_category', 'sla_response_time', 'ticket_page_id', 'ticket_id_start'), true)) {
            $settings[$key] = (int)$value;
        }
    }

    $categories = NexlifyDesk_Tickets::get_categories();
    $pages = get_pages();

    if (!is_array($categories)) {
        $categories = array();
    }
    if (!is_array($pages)) {
        $pages = array();
    }
?>

<div class="nexlifydesk-settings">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="nexlifydesk_save_settings">
        <?php wp_nonce_field('nexlifydesk_save_settings', 'nexlifydesk_settings_nonce'); ?>
        
        <h2><?php esc_html_e('General Settings', 'nexlifydesk'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="email_notifications"><?php esc_html_e('Enable Email Notifications', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="checkbox" name="email_notifications" id="email_notifications" value="1" <?php checked($settings['email_notifications'], 1); ?>>
                    <p class="description"><?php esc_html_e('Send email notifications for new tickets, replies, and status changes.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="default_priority"><?php esc_html_e('Default Priority', 'nexlifydesk'); ?></label></th>
                <td>
                    <select name="default_priority" id="default_priority">
                        <option value="low" <?php selected($settings['default_priority'], 'low'); ?>><?php esc_html_e('Low', 'nexlifydesk'); ?></option>
                        <option value="medium" <?php selected($settings['default_priority'], 'medium'); ?>><?php esc_html_e('Medium', 'nexlifydesk'); ?></option>
                        <option value="high" <?php selected($settings['default_priority'], 'high'); ?>><?php esc_html_e('High', 'nexlifydesk'); ?></option>
                        <option value="urgent" <?php selected($settings['default_priority'], 'urgent'); ?>><?php esc_html_e('Urgent', 'nexlifydesk'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Default priority for new tickets.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="auto_assign"><?php esc_html_e('Auto-Assign Tickets', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="checkbox" name="auto_assign" id="auto_assign" value="1" <?php checked($settings['auto_assign'], 1); ?>>
                    <p class="description"><?php esc_html_e('Automatically assign new tickets to available support agents.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ticket_page_id"><?php esc_html_e('Ticket List Page', 'nexlifydesk'); ?></label></th>
                <td>
                    <select name="ticket_page_id" id="ticket_page_id">
                        <option value="0"><?php esc_html_e('Select a page', 'nexlifydesk'); ?></option>
                        <?php if (!empty($pages) && is_array($pages)): ?>
                            <?php foreach ($pages as $page): ?>
                                <?php if (isset($page->ID) && isset($page->post_title)): ?>
                                    <option value="<?php echo esc_attr((int)$page->ID); ?>" <?php selected((int)$settings['ticket_page_id'], (int)$page->ID); ?>>
                                        <?php echo esc_html(wp_strip_all_tags($page->post_title)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0"><?php esc_html_e('No pages available', 'nexlifydesk'); ?></option>
                        <?php endif; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select the page where the ticket list shortcode [nexlifydesk_ticket_list] is embedded.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ticket_form_page_id"><?php esc_html_e('Ticket Form Page', 'nexlifydesk'); ?></label></th>
                <td>
                    <select name="ticket_form_page_id" id="ticket_form_page_id">
                        <option value="0"><?php esc_html_e('Select a page', 'nexlifydesk'); ?></option>
                        <?php if (!empty($pages) && is_array($pages)): ?>
                            <?php foreach ($pages as $page): ?>
                                <?php if (isset($page->ID) && isset($page->post_title)): ?>
                                    <option value="<?php echo esc_attr((int)$page->ID); ?>" <?php selected((int)$settings['ticket_form_page_id'], (int)$page->ID); ?>>
                                        <?php echo esc_html(wp_strip_all_tags($page->post_title)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0"><?php esc_html_e('No pages available', 'nexlifydesk'); ?></option>
                        <?php endif; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select the page where the ticket form shortcode [nexlifydesk_ticket_form] is embedded.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="status_change_notification"><?php esc_html_e('Send Status Change Notification', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="checkbox" name="status_change_notification" id="status_change_notification" value="1" <?php checked($settings['status_change_notification'] ?? 1, 1); ?>>
                    <p class="description"><?php esc_html_e('Send an email notification to the user when the ticket status changes.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ticket_id_prefix"><?php esc_html_e('Ticket ID Prefix', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="text" name="ticket_id_prefix" id="ticket_id_prefix" class="regular-text" value="<?php echo esc_attr($settings['ticket_id_prefix'] ?? 'T'); ?>">
                    <p class="description"><?php esc_html_e('Prefix for ticket IDs (e.g., T, #, TKT-).', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ticket_id_start"><?php esc_html_e('Ticket ID Start Number', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="number" name="ticket_id_start" id="ticket_id_start" min="1" value="<?php echo esc_attr($settings['ticket_id_start'] ?? 1001); ?>">
                    <p class="description"><?php esc_html_e('The number to start ticket IDs from (e.g., 1001). Only used if no tickets exist yet.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="keep_data_on_uninstall"><?php esc_html_e('Data Retention on Uninstall', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="checkbox" name="keep_data_on_uninstall" id="keep_data_on_uninstall" value="1" <?php checked($settings['keep_data_on_uninstall'] ?? 1, 1); ?>>
                    <label for="keep_data_on_uninstall" style="margin-left: 8px;"><?php esc_html_e('Keep all tickets and data when plugin is uninstalled', 'nexlifydesk'); ?></label>
                    <p class="description">
                        <strong><?php esc_html_e('Recommended: Keep enabled', 'nexlifydesk'); ?></strong><br>
                        <?php esc_html_e('When enabled, all tickets, replies, attachments, and customer data will be preserved even after plugin deletion. Disable only if you want to completely remove all plugin data during uninstall.', 'nexlifydesk'); ?>
                    </p>
                    <div id="data-deletion-warning" style="margin-top: 10px; padding: 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; display: none;">
                        <p style="color: #dc2626; margin: 0; font-weight: 600;">
                            ⚠️ <?php esc_html_e('Warning: Unchecking this option will permanently delete ALL ticket data when the plugin is uninstalled!', 'nexlifydesk'); ?>
                        </p>
                        <p style="color: #dc2626; margin: 5px 0 0 0; font-size: 0.9em;">
                            <?php esc_html_e('This includes: All tickets, customer replies, agent responses, attachments, categories, and support history.', 'nexlifydesk'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('File Upload Settings', 'nexlifydesk'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="allowed_file_types"><?php esc_html_e('Allowed File Types', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="text" name="allowed_file_types" id="allowed_file_types" class="regular-text" value="<?php echo esc_attr($settings['allowed_file_types']); ?>">
                    <p class="description"><?php esc_html_e('Comma-separated list of allowed file extensions (e.g., jpg,png,pdf).', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="max_file_size"><?php esc_html_e('Maximum File Size (MB)', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="number" name="max_file_size" id="max_file_size" min="1" max="100" value="<?php echo esc_attr($settings['max_file_size']); ?>">
                    <p class="description"><?php esc_html_e('Maximum size for uploaded files in megabytes.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('SLA Settings', 'nexlifydesk'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="sla_response_time"><?php esc_html_e('SLA Response Time (Hours)', 'nexlifydesk'); ?></label></th>
                <td>
                    <input type="number" name="sla_response_time" id="sla_response_time" min="1" max="168" value="<?php echo esc_attr($settings['sla_response_time']); ?>">
                    <p class="description"><?php esc_html_e('Expected response time for tickets (in hours).', 'nexlifydesk'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="default_category"><?php esc_html_e('Default Category', 'nexlifydesk'); ?></label></th>
                <td>
                    <select name="default_category" id="default_category">
                        <?php if (!empty($categories) && is_array($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <?php if (isset($category->id) && isset($category->name)): ?>
                                    <option value="<?php echo esc_attr((int)$category->id); ?>" <?php selected((int)$settings['default_category'], (int)$category->id); ?>>
                                        <?php echo esc_html(wp_strip_all_tags($category->name)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0"><?php esc_html_e('No categories available', 'nexlifydesk'); ?></option>
                        <?php endif; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Default category for new tickets.', 'nexlifydesk'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="nexlifydesk_settings_save" class="button button-primary" value="<?php esc_html_e('Save Settings', 'nexlifydesk'); ?>">
        </p>
    </form>
</div>