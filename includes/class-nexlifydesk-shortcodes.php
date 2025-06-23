<?php
if (!defined('ABSPATH')) {
    exit;
}

class NexlifyDesk_Shortcodes {
    public static function init() {
        add_shortcode('nexlifydesk_ticket_form', array(__CLASS__, 'ticket_form'));
        add_shortcode('nexlifydesk_ticket_list', array(__CLASS__, 'ticket_list'));
        
    }

    public static function ticket_form($atts) {
        $atts = shortcode_atts(array(
        ), $atts, 'nexlifydesk_ticket_form');
        
        ob_start();
        
        $template_path = NEXLIFYDESK_PLUGIN_DIR . 'templates/frontend/ticket-form.php';
        if (file_exists($template_path) && is_readable($template_path)) {
            include $template_path;
        }
        
        return ob_get_clean();
    }

    public static function ticket_list($atts) {
        $atts = shortcode_atts(array(
            'show_title' => 'no',
            'per_page' => 10,
            'show_closed' => 'no',
            'title' => ''
        ), $atts, 'nexlifydesk_ticket_list');
        
        ob_start();
        
        if ($atts['show_title'] === 'no') {
            echo '<h3 class="nexlifydesk-list-title">' . esc_html($atts['title']) . '</h3>';
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter for read-only ticket viewing, no nonce needed
        if (isset($_GET['ticket_id']) && !empty($_GET['ticket_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Viewing tickets is read-only, no nonce needed
            $ticket_id_param = sanitize_text_field(wp_unslash($_GET['ticket_id']));
            if (self::can_user_view_ticket($ticket_id_param)) {
                $template_path = NEXLIFYDESK_PLUGIN_DIR . 'templates/frontend/ticket-single.php';
                if (file_exists($template_path) && is_readable($template_path)) {
                    include $template_path;
                } else {
                    echo '<p>' . esc_html__('Single ticket template not found.', 'nexlifydesk') . '</p>';
                }
            } 

        } else {
            // Show ticket list
            $template_path = NEXLIFYDESK_PLUGIN_DIR . 'templates/frontend/ticket-list.php';
            if (file_exists($template_path) && is_readable($template_path)) {
                // Pass attributes to the template
                $shortcode_atts = $atts;
                include $template_path;
            } else {
                echo '<p>' . esc_html__('Ticket list template not found.', 'nexlifydesk') . '</p>';
            }
        }
        
        return ob_get_clean();
    }
    
    /**
     * Check if current user can view a specific ticket
     */
    private static function can_user_view_ticket($ticket_id_param) {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();

        if (current_user_can('manage_options') || current_user_can('nexlifydesk_view_all_tickets')) {
            return true;
        }

        $ticket = NexlifyDesk_Tickets::get_ticket_by_ticket_id($ticket_id_param);

        if (!$ticket) {
            return false;
        }

        return ((int)$ticket->user_id === (int)$current_user->ID);
    }
    
    /**
     * Maybe enqueue scripts based on post content
     */
    public static function maybe_enqueue_scripts() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        if (has_shortcode($post->post_content, 'nexlifydesk_ticket_form') || 
            has_shortcode($post->post_content, 'nexlifydesk_ticket_list')) {
        }
    }

    public static function get_submit_ticket_url() {
        if (class_exists('NexlifyDesk_Admin') && method_exists('NexlifyDesk_Admin', 'get_ticket_form_page_url')) {
            return NexlifyDesk_Admin::get_ticket_form_page_url();
        }
        
        $settings = get_option('nexlifydesk_settings', array());
        $ticket_form_page_id = isset($settings['ticket_form_page_id']) ? (int)$settings['ticket_form_page_id'] : 0;
        
        if ($ticket_form_page_id > 0) {
            $page = get_post($ticket_form_page_id);
            if ($page && $page->post_status === 'publish') {
                $url = get_permalink($ticket_form_page_id);
                if ($url && wp_http_validate_url($url)) {
                    return $url;
                }
            }
        }
        
        $pages = get_pages(array('post_status' => 'publish'));
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'nexlifydesk_ticket_form')) {
                $page_title = strtolower($page->post_title);
                $page_slug = $page->post_name;
                
                if (strpos($page_title, 'doc') !== false || 
                    strpos($page_title, 'help') !== false || 
                    strpos($page_title, 'guide') !== false ||
                    strpos($page_slug, 'doc') !== false ||
                    strpos($page_slug, 'help') !== false ||
                    strpos($page_slug, 'guide') !== false) {
                    continue;
                }
                
                return get_permalink($page->ID);
            }
        }
        
        return '';
    }
}