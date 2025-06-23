<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!$ticket) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Ticket not found.', 'nexlifydesk') . '</p></div>';
    return;
}

$user = get_userdata($ticket->user_id);
$category = NexlifyDesk_Tickets::get_category($ticket->category_id);
$assigned_agent = $ticket->assigned_to ? get_userdata($ticket->assigned_to) : null;
$replies = NexlifyDesk_Tickets::get_replies($ticket->id);
$attachments = NexlifyDesk_Tickets::get_attachments($ticket->id);

?>
<div class="nexlifydesk-ticket-single nexlifydesk-admin-single">
    <div class="nexlifydesk-header" style="text-align: center; margin-bottom: 20px;">
        <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Plugin asset image, not media library image ?>
        <img src="<?php echo esc_url(NEXLIFYDESK_PLUGIN_URL . 'assets/images/nexlifydesk-logo.png'); ?>" 
             alt="<?php esc_attr_e('NexlifyDesk', 'nexlifydesk'); ?>" 
             class="nexlifydesk-logo-img"
             style="max-width: 200px; height: auto;">
    </div>

    <h2><?php echo nl2br(esc_html($ticket->subject)); ?></h2>

    <div class="nexlifydesk-ticket-details">
        <p><strong><?php esc_html_e('Ticket ID:', 'nexlifydesk'); ?></strong> #<?php echo esc_html($ticket->id); ?></p>
        <p><strong><?php esc_html_e('Submitted By:', 'nexlifydesk'); ?></strong> <?php echo esc_html($user ? $user->display_name : __('Guest', 'nexlifydesk')); ?></p>
        <p><strong><?php esc_html_e('Category:', 'nexlifydesk'); ?></strong> <?php echo esc_html($category ? $category->name : __('N/A', 'nexlifydesk')); ?></p>
        <p><strong><?php esc_html_e('Priority:', 'nexlifydesk'); ?></strong> <span class="priority-<?php echo esc_attr($ticket->priority); ?>"><?php echo esc_html(ucfirst($ticket->priority)); ?></span></p>
        <p><strong><?php esc_html_e('Status:', 'nexlifydesk'); ?></strong>
            <?php
            $can_change_status = (
                current_user_can('manage_options') ||
                NexlifyDesk_Users::agent_can('nexlifydesk_assign_tickets', get_current_user_id()) ||
                ($assigned_agent && $assigned_agent->ID == get_current_user_id())
            );

            if ($ticket->status === 'closed' && !current_user_can('manage_options')): ?>
                <span class="status-<?php echo esc_attr($ticket->status); ?>">
                    <?php echo esc_html(ucfirst($ticket->status)); ?>
                </span>
                <em class="description"><?php esc_html_e('(Only administrators can change the status of closed tickets)', 'nexlifydesk'); ?></em>
            <?php elseif ($can_change_status): ?>
                <select class="ticket-status-select" data-ticket-id="<?php echo esc_attr($ticket->id); ?>">
                    <option value="open" <?php selected($ticket->status, 'open'); ?>><?php esc_html_e('Open', 'nexlifydesk'); ?></option>
                    <option value="pending" <?php selected($ticket->status, 'pending'); ?>><?php esc_html_e('Pending', 'nexlifydesk'); ?></option>
                    <option value="resolved" <?php selected($ticket->status, 'resolved'); ?>><?php esc_html_e('Resolved', 'nexlifydesk'); ?></option>
                    <option value="closed" <?php selected($ticket->status, 'closed'); ?>><?php esc_html_e('Closed', 'nexlifydesk'); ?></option>
                </select>
            <?php else: ?>
                <span class="status-<?php echo esc_attr($ticket->status); ?>">
                    <?php echo esc_html(ucfirst($ticket->status)); ?>
                </span>
            <?php endif; ?>
        </p>
        <p><strong><?php esc_html_e('Assigned To:', 'nexlifydesk'); ?></strong>
            <?php if (
                    current_user_can('manage_options') ||
                    NexlifyDesk_Users::agent_can('nexlifydesk_assign_tickets', get_current_user_id())
            ) : ?>
                <select class="ticket-agent-select" 
                    data-ticket-id="<?php echo esc_attr($ticket->id); ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('nexlifydesk_assign_ticket_' . $ticket->id)); ?>">
                    <option value="0"><?php esc_html_e('Unassigned', 'nexlifydesk'); ?></option>
                    <?php
                    $agents = get_users(array(
                        'role__in' => array('administrator', 'nexlifydesk_agent'),
                        'orderby' => 'display_name',
                        'fields' => array('ID', 'display_name', 'user_email')
                    ));

                    if (!is_array($agents)) {
                        $agents = array();
                    }

                    foreach ($agents as $agent) : 
                        if (isset($agent->ID) && isset($agent->display_name)): ?>
                            <option value="<?php echo esc_attr((int)$agent->ID); ?>" <?php selected((int)$ticket->assigned_to, (int)$agent->ID); ?>>
                                <?php 
                                $user = get_userdata($agent->ID);
                                $role_display = '';
                                if (in_array('administrator', $user->roles)) {
                                    $role_display = ' (Admin)';
                                } elseif (in_array('nexlifydesk_agent', $user->roles)) {
                                    $role_display = ' (Agent)';
                                }
                                echo esc_html(wp_strip_all_tags($agent->display_name . $role_display)); 
                                ?>
                            </option>
                        <?php endif;
                    endforeach; ?>
                </select>
            <?php else : ?>
                <?php echo esc_html($assigned_agent ? $assigned_agent->display_name : __('Unassigned', 'nexlifydesk')); ?>
            <?php endif; ?>
        </p>
        <p><strong><?php esc_html_e('Created:', 'nexlifydesk'); ?></strong> 
            <?php 
            $created_timestamp = strtotime($ticket->created_at);
            if ($created_timestamp !== false) {
                echo esc_html(
                    date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $created_timestamp
                    )
                );
            } else {
                esc_html_e('Invalid date', 'nexlifydesk');
            }
            ?>
        </p>
        <p><strong><?php esc_html_e('Last Updated:', 'nexlifydesk'); ?></strong> 
            <?php 
            $updated_timestamp = strtotime($ticket->updated_at);
            if ($updated_timestamp !== false) {
                echo esc_html(
                    date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $updated_timestamp
                    )
                );
            } else {
                esc_html_e('Invalid date', 'nexlifydesk');
            }
            ?>
        </p>
    </div>

    <hr>

    <h3><?php esc_html_e('Message', 'nexlifydesk'); ?></h3>
        <div class="nexlifydesk-ticket-message">
            <?php echo nl2br(esc_html($ticket->message)); ?>
        </div>

    <?php if (!empty($attachments)) : ?>
        <h3><?php esc_html_e('Attachments', 'nexlifydesk'); ?></h3>
        <ul>
            <?php foreach ($attachments as $attachment) : ?>
                <?php if (isset($attachment->file_path) && isset($attachment->file_name)): ?>
                    <?php 
                    $file_url = esc_url($attachment->file_path);
                    if (wp_http_validate_url($file_url)): 
                        $file_ext = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
                        $file_type = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 
                                   ($file_ext === 'pdf' ? 'pdf' : 'document');
                    ?>
                        <li>
                            <span class="nexlifydesk-attachment" data-type="<?php echo esc_attr($file_type); ?>">
                                <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($attachment->file_name); ?>
                                </a>
                            </span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3><?php esc_html_e('Replies', 'nexlifydesk'); ?></h3>
    <div class="nexlifydesk-replies" id="nexlifydesk-replies-list">
        <?php if (!empty($replies)) :
            foreach ($replies as $reply) :
                $reply_user = get_userdata($reply->user_id);
                include NEXLIFYDESK_PLUGIN_DIR . 'templates/frontend/partials/single-reply.php';
            endforeach;
        endif; ?>
    </div>

    <?php
    if ($ticket->status === 'closed') : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('This ticket is closed. Replies are disabled.', 'nexlifydesk'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($ticket->status !== 'closed') : ?>
        <h3><?php esc_html_e('Add Reply', 'nexlifydesk'); ?></h3>
        <form id="nexlifydesk-reply-form" method="post">
            <div class="nexlifydesk-form-messages"></div>
            <div class="form-group">
                <label for="reply-message"><?php esc_html_e('Your Reply', 'nexlifydesk'); ?></label>
                <textarea id="reply-message" name="message" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label for="reply-attachments"><?php esc_html_e('Attachments', 'nexlifydesk'); ?></label>
                <input type="file" name="attachments[]" id="reply-attachments" multiple>
                <p class="description">
                    <?php
$settings = get_option('nexlifydesk_settings', array());
$allowed_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : 'jpg,jpeg,png,pdf';
$max_size = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 2;

printf(
    /* translators: 1: Maximum file size in megabytes, 2: Comma-separated list of allowed file types */
    esc_html__('Max file size: %1$sMB. Allowed types: %2$s', 'nexlifydesk'),
    esc_html($max_size),
    esc_html($allowed_types)
);
?>
                </p>
            </div>
            <input type="hidden" name="action" value="nexlifydesk_add_reply">
            <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->id); ?>">
            <?php wp_nonce_field('nexlifydesk-ajax-nonce', 'nonce'); ?>
            <button type="submit"><?php esc_html_e('Add Reply', 'nexlifydesk'); ?></button>
        </form>
    <?php endif; ?>

</div>