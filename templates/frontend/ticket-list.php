<?php
if (!defined('ABSPATH')) {
    exit;
}

$user_tickets = array();
if (is_user_logged_in()) {
    $user_tickets = NexlifyDesk_Tickets::get_user_tickets(get_current_user_id());
}

if (empty($user_tickets)) {
    ?>
    <div class="nexlifydesk-no-tickets" style="text-align: center; padding: 40px 20px;">
        <div class="nexlifydesk-icon" style="font-size: 48px; margin-bottom: 20px;">📄</div>
        <h3><?php esc_html_e('No tickets found', 'nexlifydesk'); ?></h3>
        <p><?php esc_html_e('You haven\'t submitted any support tickets yet.', 'nexlifydesk'); ?></p>
        
        <?php 
        $submit_url = '';
        if (class_exists('NexlifyDesk_Shortcodes') && method_exists('NexlifyDesk_Shortcodes', 'get_submit_ticket_url')) {
            $submit_url = NexlifyDesk_Shortcodes::get_submit_ticket_url();
        }
        
        if (!empty($submit_url) && $submit_url !== home_url()) : 
        ?>
            <a href="<?php echo esc_url($submit_url); ?>" class="button button-primary nexlifydesk-submit-first-ticket" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600;">
                <?php esc_html_e('Submit Your First Ticket', 'nexlifydesk'); ?>
            </a>
        <?php else : ?>
            <p style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">
                <strong><?php esc_html_e('Configuration Required:', 'nexlifydesk'); ?></strong><br>
                <?php esc_html_e('Please configure the ticket form page in NexlifyDesk settings to enable ticket submission.', 'nexlifydesk'); ?>
                <?php if (current_user_can('manage_options')) : ?>
                    <br><a href="<?php echo esc_url(admin_url('admin.php?page=nexlifydesk_settings')); ?>" style="color: #0073aa; text-decoration: underline;">
                        <?php esc_html_e('→ Go to Settings', 'nexlifydesk'); ?>
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
} else {
    ?>
    <div class="nexlifydesk-tickets-list">
        <div class="nexlifydesk-tickets-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><?php echo esc_html__('Your Support Tickets', 'nexlifydesk'); ?></h3>
            <?php 
            $submit_url = '';
            if (class_exists('NexlifyDesk_Shortcodes') && method_exists('NexlifyDesk_Shortcodes', 'get_submit_ticket_url')) {
                $submit_url = NexlifyDesk_Shortcodes::get_submit_ticket_url();
            }
            
            if (!empty($submit_url) && $submit_url !== home_url()) : 
            ?>
                <a href="<?php echo esc_url($submit_url); ?>" class="button button-primary" style="background: #0073aa; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 4px;">
                    <?php esc_html_e('Submit New Ticket', 'nexlifydesk'); ?>
                </a>
            <?php endif; ?>
        </div>
        
        <table class="nexlifydesk-tickets-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;"><?php esc_html_e('Ticket ID', 'nexlifydesk'); ?></th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;"><?php esc_html_e('Subject', 'nexlifydesk'); ?></th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;"><?php esc_html_e('Status', 'nexlifydesk'); ?></th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;"><?php esc_html_e('Created', 'nexlifydesk'); ?></th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;"><?php esc_html_e('Actions', 'nexlifydesk'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_tickets as $ticket) : ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="<?php echo esc_url(add_query_arg('ticket_id', $ticket->ticket_id, get_permalink())); ?>" style="text-decoration: underline; color: #0073aa;">
                                <?php echo esc_html($ticket->ticket_id); ?>
                            </a>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo nl2br(esc_html($ticket->subject)); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <span class="status-<?php echo esc_attr($ticket->status); ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">
                                <?php echo esc_html(ucfirst($ticket->status)); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($ticket->created_at))); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="<?php echo esc_url(add_query_arg('ticket_id', $ticket->ticket_id, get_permalink())); ?>" class="button button-small" ...>
                            <?php esc_html_e('View', 'nexlifydesk'); ?>
                        </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>