<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Live Chat functionality for NexlifyDesk.
 *
 * This class handles live chat interactions, including bot responses and ticket creation.
 */
class NexlifyDesk_LiveChat {
    public static function init() {
        add_action('wp_ajax_nexlifydesk_livechat_bot', [__CLASS__, 'handle_bot']);
        add_action('wp_ajax_nopriv_nexlifydesk_livechat_bot', [__CLASS__, 'handle_bot']);
        add_action('wp_ajax_nexlifydesk_livechat_create_ticket', [__CLASS__, 'create_ticket']);
        add_action('wp_ajax_nopriv_nexlifydesk_livechat_create_ticket', [__CLASS__, 'create_ticket']);
    }

    public static function handle_bot() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        $message = sanitize_text_field($_POST['message']);
        // Simple bot logic (replace with AI API if needed)
        if (stripos($message, 'refund') !== false) {
            wp_send_json_success(['reply' => 'Refunds are processed in 3-5 days.']);
        }
        // Escalate if not understood
        wp_send_json_success(['reply' => "I'm not sure. Would you like to create a support ticket?", 'escalate' => true]);
    }

    public static function create_ticket() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        $user_id = get_current_user_id();
        $subject = 'Live Chat Escalation';
        $message = sanitize_textarea_field($_POST['transcript']);
        $ticket_data = [
            'user_id' => $user_id,
            'subject' => $subject,
            'message' => $message,
            'category_id' => 1,
            'priority' => 'medium',
            'status' => 'open'
        ];
        $ticket = NexlifyDesk_Tickets::create_ticket($ticket_data);
        if (is_wp_error($ticket)) {
            wp_send_json_error($ticket->get_error_message());
        }
        wp_send_json_success(['ticket_id' => $ticket->ticket_id]);
    }
}