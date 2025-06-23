<?php
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_GET['ticket_id'])) {
    
    $ticket_id = sanitize_text_field(wp_unslash($_GET['ticket_id']));
    $ticket_exists = NexlifyDesk_Tickets::get_ticket_by_ticket_id($ticket_id);
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nexlifydesk_ticket_action')) {
        wp_die(esc_html__('Security check failed', 'nexlifydesk'));
    }

    if (isset($_POST['ticket_id'])) {
        $_GET['ticket_id'] = sanitize_text_field(wp_unslash($_POST['ticket_id']));
    }
}

if (!is_user_logged_in()) {
    ?>
    <div class="nexlifydesk-ticket-single">
        <p><?php esc_html_e('Please log in to view your support tickets.', 'nexlifydesk'); ?></p>
        <p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="nexlifydesk-button"><?php esc_html_e('Log In', 'nexlifydesk'); ?></a>
            <?php if (get_option('users_can_register')) : ?>
                <a href="<?php echo esc_url(wp_registration_url()); ?>" class="nexlifydesk-button"><?php esc_html_e('Register', 'nexlifydesk'); ?></a>
            <?php endif; ?>
        </p>
    </div>
    <?php
    return;
}

$ticket_id_param = isset($_GET['ticket_id']) ? sanitize_text_field(wp_unslash($_GET['ticket_id'])) : '';
$ticket = null;
$ticket = NexlifyDesk_Tickets::get_ticket_by_ticket_id($ticket_id_param);

if (!$ticket && is_numeric($ticket_id_param)) {
    $ticket = NexlifyDesk_Tickets::get_ticket(absint($ticket_id_param));
}

if (!$ticket && is_numeric($ticket_id_param)) {
    $settings = get_option('nexlifydesk_settings', array());
    $prefix = isset($settings['ticket_id_prefix']) ? $settings['ticket_id_prefix'] : 'T';
    $formatted_ticket_id = $prefix . $ticket_id_param;
    $ticket = NexlifyDesk_Tickets::get_ticket_by_ticket_id($formatted_ticket_id);
    
    if ($ticket) {
        $ticket_id_param = $formatted_ticket_id;
    }
}

if (!$ticket && preg_match('/^[A-Z]+(\d+)$/', $ticket_id_param, $matches)) {
    $ticket = NexlifyDesk_Tickets::get_ticket(absint($matches[1]));
}

$current_user = wp_get_current_user();
$is_agent = in_array('nexlifydesk_agent', $current_user->roles);
$is_admin = current_user_can('manage_options');
$is_assigned_agent = ($is_agent && $ticket && (int)$ticket->assigned_to === (int)$current_user->ID);
$is_ticket_owner = ($ticket && (int)$ticket->user_id === (int)$current_user->ID);
$can_view_ticket = $is_admin || $is_ticket_owner || $is_assigned_agent;


if (!$ticket || !$can_view_ticket) {
    ?>
    <div class="nexlifydesk-ticket-single">
        <p><?php esc_html_e('You are not authorized to view this ticket or the ticket does not exist.', 'nexlifydesk'); ?></p>
        <p><a href="<?php echo esc_url(get_permalink(get_option('nexlifydesk_settings')['ticket_page_id'])); ?>">
                <?php esc_html_e('← Back to All Tickets', 'nexlifydesk'); ?>
            </a></p>
    </div>
    <?php
    return;
}

$user = get_userdata($ticket->user_id);
$category = NexlifyDesk_Tickets::get_category($ticket->category_id);
$assigned_agent = $ticket->assigned_to ? get_userdata($ticket->assigned_to) : null;
$replies = NexlifyDesk_Tickets::get_replies($ticket->id);
$attachments = NexlifyDesk_Tickets::get_attachments($ticket->id);

?>
<div class="nexlifydesk-frontend">
    <div class="nexlifydesk-ticket-single">
        <h2><?php echo nl2br(esc_html($ticket->subject)); ?></h2>

        <div class="nexlifydesk-ticket-details">
            <table>
                <tr>
                    <td><strong><?php esc_html_e('Ticket ID:', 'nexlifydesk'); ?></strong></td>
                    <td><?php echo esc_html($ticket->ticket_id); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Submitted By:', 'nexlifydesk'); ?></strong></td>
                    <td><?php echo esc_html($user ? $user->display_name : __('Guest', 'nexlifydesk')); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Category:', 'nexlifydesk'); ?></strong></td>
                    <td><?php echo esc_html($category ? $category->name : __('N/A', 'nexlifydesk')); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Priority:', 'nexlifydesk'); ?></strong></td>
                    <td><span class="priority-<?php echo esc_attr($ticket->priority); ?>"><?php echo esc_html(ucfirst($ticket->priority)); ?></span></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Status:', 'nexlifydesk'); ?></strong></td>
                    <td>
                        <span class="status-<?php echo esc_attr($ticket->status); ?>">
                            <?php echo esc_html(ucfirst($ticket->status)); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Assigned To:', 'nexlifydesk'); ?></strong></td>
                    <td><?php echo esc_html($assigned_agent ? $assigned_agent->display_name : __('Unassigned', 'nexlifydesk')); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Created:', 'nexlifydesk'); ?></strong></td>
                    <td><?php 
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
                    ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Last Updated:', 'nexlifydesk'); ?></strong></td>
                    <td><?php 
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
                    ?></td>
                </tr>
            </table>
        </div>

        <h3><?php esc_html_e('Message', 'nexlifydesk'); ?></h3>
        <div class="nexlifydesk-ticket-message">
            <?php echo nl2br(esc_html($ticket->message)); ?>
        </div>

        <?php if (!empty($attachments)) : ?>
            <div class="nexlifydesk-attachments">
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
            </div>
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
        
        if ($ticket->status !== 'closed') {
            if ($is_ticket_owner || $is_assigned_agent || $is_admin) : ?>
                <div class="nexlifydesk-reply-form">
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
                                $max_size = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 2;
                                $allowed_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : 'jpg,jpeg,png,pdf';

                                printf(
                                    /* translators: 1: Maximum file size in megabytes, 2: Comma-separated list of allowed file extensions */
                                    esc_html__('Max file size: %1$sMB. Allowed types: %2$s', 'nexlifydesk'),
                                    esc_html($max_size),
                                    esc_html($allowed_types)
                                );
                                ?>
                            </p>
                        </div>
                        <input type="hidden" name="action" value="nexlifydesk_add_reply">
                        <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->ticket_id); ?>">
                        <?php wp_nonce_field('nexlifydesk-ajax-nonce', 'nonce'); ?>
                        <button type="submit"><?php esc_html_e('Add Reply', 'nexlifydesk'); ?></button>
                    </form>
                </div>
            <?php endif;
        } else {
            ?>
            <div class="nexlifydesk-closed-message">
                <p><?php esc_html_e('This ticket is closed. If you need further assistance, please open a new ticket.', 'nexlifydesk'); ?></p>
                <?php 
                $ticket_form_page_id = isset(get_option('nexlifydesk_settings')['ticket_form_page_id']) ? 
                    get_option('nexlifydesk_settings')['ticket_form_page_id'] : 0;
                
                if ($ticket_form_page_id) : ?>
                    <a href="<?php echo esc_url(get_permalink($ticket_form_page_id)); ?>" class="nexlifydesk-button">
                        <?php esc_html_e('Create New Ticket', 'nexlifydesk'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>

        <p class="back-link"><a href="<?php echo esc_url(remove_query_arg('ticket_id')); ?>"><?php esc_html_e('← Back to All Tickets', 'nexlifydesk'); ?></a></p>
    </div>
</div>