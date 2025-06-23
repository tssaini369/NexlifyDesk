<?php
/**
 * Plugin Name: NexlifyDesk
 * Description: A complete support ticketing system for WordPress.
 * Version: 1.0.0
 * Supported Versions: 6.2+
 * Tested up to: 6.2 < 6.8.
 * Author: NexlifyLabs
 * Author URI: https://nexlifylabs.com
 * License: GPL-2.0+
 * Text Domain: nexlifydesk
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if ( ! function_exists( 'nexlify_ds' ) ) {
    // Create a helper function for easy SDK access.
    function nexlify_ds() {
        global $nexlify_ds;

        if ( ! isset( $nexlify_ds ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
            $nexlify_ds = fs_dynamic_init( array(
                'id'                  => '19551',
                'slug'                => 'nexlifydesk',
                'type'                => 'plugin',
                'public_key'          => 'pk_cbbd298a238d22db2fed1cc83307e',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'nexlifydesk_tickets',
                    'account'        => false,
                    'contact'        => false,
                ),
            ) );
        }

        return $nexlify_ds;
    }

    // Init Freemius.
    nexlify_ds();
    // Signal that SDK was initiated.
    do_action( 'nexlify_ds_loaded' );
}

define('NEXLIFYDESK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEXLIFYDESK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXLIFYDESK_VERSION', '1.0.0');
define('NEXLIFYDESK_TABLE_PREFIX', 'nexlifydesk_');
define('NEXLIFYDESK_CAP_VIEW_ALL_TICKETS', 'nexlifydesk_view_all_tickets');
define('NEXLIFYDESK_CAP_ASSIGN_TICKETS', 'nexlifydesk_assign_tickets');
define('NEXLIFYDESK_CAP_MANAGE_CATEGORIES', 'nexlifydesk_manage_categories');
define('NEXLIFYDESK_CAP_VIEW_REPORTS', 'nexlifydesk_view_reports');

require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-database.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-tickets.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-users.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-ajax.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-shortcodes.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-admin.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/nexlifydesk-functions.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-reports.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-support.php';
require_once NEXLIFYDESK_PLUGIN_DIR . 'includes/class-nexlifydesk-livechat.php';

// Initialize the plugin
function nexlifydesk_init() {
    load_plugin_textdomain('nexlifydesk', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    NexlifyDesk_Database::init();
    NexlifyDesk_Tickets::init();
    NexlifyDesk_Users::init();
    NexlifyDesk_Ajax::init();
    NexlifyDesk_Shortcodes::init();
    NexlifyDesk_LiveChat::init();
    
    if (is_admin()) {
        NexlifyDesk_Admin::init();
    }
}
add_action('plugins_loaded', 'nexlifydesk_init');

// Activation function
function nexlifydesk_activate() {
    // Activate database tables
    NexlifyDesk_Database::activate();

    // Activate user roles and capabilities
    NexlifyDesk_Users::activate();

    $default_settings = get_option('nexlifydesk_settings', array());
    if (!isset($default_settings['keep_data_on_uninstall'])) {
        $default_settings['keep_data_on_uninstall'] = 1;
        update_option('nexlifydesk_settings', $default_settings);
    }
}

// Deactivation function
function nexlifydesk_deactivate() {

    // Remove scheduled events
    wp_clear_scheduled_hook('nexlifydesk_sla_check');
    wp_clear_scheduled_hook('nexlifydesk_auto_close_tickets');

    // Default email templates
    $default_templates = array(
        'new_ticket' => '<p>Dear {user_name},</p><p>Your ticket <strong>#{ticket_id}</strong> has been received.</p><p>Subject: {subject}</p><p>Message: {message}</p><p>We will get back to you soon.</p>',
        'new_reply' => '<p>Dear {user_name},</p><p>You have a new reply on ticket <strong>#{ticket_id}</strong>.</p><p>Reply: {reply_message}</p>',
        'status_changed' => '<p>Dear {user_name},</p><p>The status of your ticket <strong>#{ticket_id}</strong> has changed to <strong>{status}</strong>.</p>',
        'sla_breach' => '<p>Attention: Ticket <strong>#{ticket_id}</strong> has breached its SLA.</p>',
    );

    if (!get_option('nexlifydesk_email_templates')) {
        add_option('nexlifydesk_email_templates', $default_templates);
    }
}

register_activation_hook(__FILE__, 'nexlifydesk_activate'); 
register_deactivation_hook(__FILE__, 'nexlifydesk_deactivate');

register_activation_hook(__FILE__, function() {
    add_option('nexlifydesk_last_ticket_number', 0);
});

add_action('nexlifydesk_sla_check', 'nexlifydesk_check_sla');

function nexlifydesk_check_sla() {
    global $wpdb;
    $settings = get_option('nexlifydesk_settings');
    $sla_hours = isset($settings['sla_response_time']) ? intval($settings['sla_response_time']) : 0;

    $cache_key = 'nexlifydesk_sla_tickets_' . md5($sla_hours . gmdate('Y-m-d-H'));
    $tickets = wp_cache_get($cache_key);
    
    if (false === $tickets) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $tickets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nexlifydesk_tickets WHERE status IN ('open', 'pending') AND created_at < %s",
                gmdate('Y-m-d H:i:s', strtotime("-$sla_hours hours"))
            )
        );
        
        wp_cache_set($cache_key, $tickets, '', 1800);
    }
    
    foreach ($tickets as $ticket) {
        NexlifyDesk_Tickets::send_notification($ticket, 'sla_breach');
    }
}

add_action('wp', function() {
    if (!wp_next_scheduled('nexlifydesk_sla_check')) {
        wp_schedule_event(time(), 'hourly', 'nexlifydesk_sla_check');
    }
});

function nexlifydesk_auto_close_resolved_tickets() {
    global $wpdb;
    
    $cache_key = 'nexlifydesk_resolved_tickets_' . gmdate('Y-m-d-H');
    $resolved_tickets = wp_cache_get($cache_key);
    
    if (false === $resolved_tickets) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $resolved_tickets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, ticket_id, user_id, subject, status, updated_at FROM {$wpdb->prefix}nexlifydesk_tickets 
                WHERE status = 'resolved' 
                AND updated_at < %s
                LIMIT 50",
                gmdate('Y-m-d H:i:s', strtotime('-48 hours'))
            )
        );
        
        wp_cache_set($cache_key, $resolved_tickets, '', 1800);
    }
    
    if (!empty($resolved_tickets) && is_array($resolved_tickets)) {
        foreach ($resolved_tickets as $ticket) {
            $update_result = NexlifyDesk_Tickets::update_ticket_status($ticket->id, 'closed');
            
            if ($update_result) {
                $system_note = array(
                    'ticket_id' => $ticket->id,
                    'user_id' => 0,
                    'message' => __('This ticket was automatically closed after being resolved for 48 hours with no activity.', 'nexlifydesk'),
                    'is_admin_reply' => 1
                );
                NexlifyDesk_Tickets::add_reply($system_note);
                
                wp_cache_delete('nexlifydesk_ticket_' . $ticket->id);
                wp_cache_delete('nexlifydesk_user_tickets_' . $ticket->user_id);
            }
        }
        
        wp_cache_delete($cache_key);
    }
}

add_action('nexlifydesk_auto_close_tickets', 'nexlifydesk_auto_close_resolved_tickets');

add_action('wp', function() {
    if (!wp_next_scheduled('nexlifydesk_auto_close_tickets')) {
        wp_schedule_event(time(), 'hourly', 'nexlifydesk_auto_close_tickets');
    }
});

add_action('wp_enqueue_scripts', function() {
    global $post;
    
    $should_enqueue = false;
    
    if (is_a($post, 'WP_Post')) {
        if (has_shortcode($post->post_content, 'nexlifydesk_ticket_form') || 
            has_shortcode($post->post_content, 'nexlifydesk_ticket_list')) {
            $should_enqueue = true;
        }
    }
    
    if (!$should_enqueue && (is_page() || is_single())) {
        $should_enqueue = true;
    }
    
    if ($should_enqueue) {
        wp_enqueue_script('jquery');
        
        // Enqueue CSS
        wp_enqueue_style(
            'nexlifydesk-frontend',
            NEXLIFYDESK_PLUGIN_URL . 'assets/css/nexlifydesk.css',
            array(),
            NEXLIFYDESK_VERSION
        );
        
        // Enqueue JavaScript with jQuery dependency
        wp_enqueue_script(
            'nexlifydesk-frontend',
            NEXLIFYDESK_PLUGIN_URL . 'assets/js/nexlifydesk.js',
            array('jquery'),
            NEXLIFYDESK_VERSION,
            true
        );
        
        $settings = get_option('nexlifydesk_settings', array());
        $max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 2;
        $allowed_file_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : 'jpg,jpeg,png,gif,pdf,doc,docx,txt';
        
        wp_localize_script('nexlifydesk-frontend', 'nexlifydesk_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexlifydesk-ajax-nonce'),
            'uploading_text' => __('Uploading...', 'nexlifydesk'),
            'max_file_size' => $max_file_size,
            'allowed_file_types' => $allowed_file_types,
            'submitting_text' => __('Submitting...', 'nexlifydesk'),
            'submit_ticket_text' => __('Submit Ticket', 'nexlifydesk'),
            'error_occurred_text' => __('An error occurred. Please try again.', 'nexlifydesk'),
            'adding_text' => __('Adding...', 'nexlifydesk'),
            'add_category_text' => __('Add Category', 'nexlifydesk'),
            'add_reply_text' => __('Add Reply', 'nexlifydesk'),
            'ticket_form_url' => get_permalink(get_option('nexlifydesk_settings')['ticket_form_page_id'] ?? 0),
            'create_new_ticket_text' => __('Create New Ticket', 'nexlifydesk'),
            'ticket_closed_text' => __('This ticket is closed. Please create a new ticket for further assistance.', 'nexlifydesk'),
            'status_updated_text' => __('Status updated successfully!', 'nexlifydesk'),
            'ticket_page_url' => NexlifyDesk_Admin::get_ticket_page_url(),
        ));
    }
}, 20);

add_action('admin_init', function() {
    $current_user = wp_get_current_user();
});

add_action('admin_menu', function() {
    if (current_user_can('nexlifydesk_manage_tickets') && !current_user_can('manage_options')) {
        remove_menu_page('edit.php');
        remove_menu_page('upload.php');
        remove_menu_page('edit-comments.php');
    }
}, 999);

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'nexlifydesk-css',
        plugins_url('assets/css/nexlifydesk.css', __FILE__),
        [],
        '1.0.0'
    );
});


function nexlifyds_fs_uninstall_cleanup() {
    $settings = get_option('nexlifydesk_settings', array());
    $keep_data = isset($settings['keep_data_on_uninstall']) ? (bool)$settings['keep_data_on_uninstall'] : true;
    
    if ($keep_data) {
        
        $options_to_remove = array(
            'nexlifydesk_db_version',
        );
        
        foreach ($options_to_remove as $option) {
            delete_option($option);
        }
        
        delete_transient('nexlifydesk_sla_tickets');
        delete_transient('nexlifydesk_resolved_tickets');
        
        wp_clear_scheduled_hook('nexlifydesk_sla_check');
        wp_clear_scheduled_hook('nexlifydesk_auto_close_tickets');
        
        return;
    }
    
    global $wpdb;

    $ticket_count = wp_cache_get('nexlifydesk_ticket_count', 'nexlifydesk');

    if (false === $ticket_count) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ticket_count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM %i", $wpdb->prefix . 'nexlifydesk_tickets')
        );
        
        wp_cache_set('nexlifydesk_ticket_count', $ticket_count, 'nexlifydesk', HOUR_IN_SECONDS);
    }

    
    delete_option('nexlifydesk_settings');
    delete_option('nexlifydesk_email_templates');
    delete_option('nexlifydesk_last_ticket_number');
    delete_option('nexlifydesk_db_version');
    
    delete_transient('nexlifydesk_sla_tickets');
    delete_transient('nexlifydesk_resolved_tickets');
    wp_cache_flush();
    
    $table_names = array(
        $wpdb->prefix . 'nexlifydesk_tickets',
        $wpdb->prefix . 'nexlifydesk_replies', 
        $wpdb->prefix . 'nexlifydesk_attachments',
        $wpdb->prefix . 'nexlifydesk_categories'
    );
    
    function nexlifydesk_uninstall_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        foreach ($table_names as $table_name) {
            $sql = "DROP TABLE IF EXISTS {$wpdb->prefix}{$table_name}";
            dbDelta($sql);
        }
    }
    
    remove_role('nexlifydesk_agent');
    remove_role('nexlifydesk_supervisor');
    
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $capabilities = array(
            'nexlifydesk_manage_tickets',
            'nexlifydesk_view_all_tickets', 
            'nexlifydesk_assign_tickets',
            'nexlifydesk_manage_categories',
            'nexlifydesk_view_reports'
        );
        
        foreach ($capabilities as $cap) {
            $admin_role->remove_cap($cap);
        }
    }
    
    wp_clear_scheduled_hook('nexlifydesk_sla_check');
    wp_clear_scheduled_hook('nexlifydesk_auto_close_tickets');
    
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/nexlifydesk/';
    if (is_dir($plugin_upload_dir)) {
        $file_count = nexlifydesk_count_files($plugin_upload_dir);
        nexlifydesk_delete_directory($plugin_upload_dir);
    }
    
}

function nexlifydesk_count_files($dir) {
    if (!is_dir($dir)) {
        return 0;
    }
    
    $count = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $count++;
        }
    }
    
    return $count;
}

function nexlifydesk_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                nexlifydesk_delete_directory($path);
            } else {
                wp_delete_file($path);
            }
        }
        return WP_Filesystem($dir);
    }

if (function_exists('nexlifyds_fs')) {
    nexlifyds_fs()->add_action('after_uninstall', 'nexlifyds_fs_uninstall_cleanup');
}

add_action('wp_ajax_nexlifydesk_update_status', 'nexlifydesk_update_status_callback');
function nexlifydesk_update_status_callback() {
    check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');

    $ticket_id = isset($_POST['ticket_id']) ? intval(wp_unslash($_POST['ticket_id'])) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

    if (!$ticket_id || !$status) {
        wp_send_json_error(__('Invalid request.', 'nexlifydesk'));
    }

    $allowed_statuses = ['open', 'pending', 'resolved', 'closed'];
    if (!in_array($status, $allowed_statuses, true)) {
        wp_send_json_error(__('Invalid status.', 'nexlifydesk'));
    }

    $result = NexlifyDesk_Tickets::update_ticket_status($ticket_id, $status);

    if ($result) {
        wp_send_json_success(__('Status updated successfully!', 'nexlifydesk'));
    } else {
        wp_send_json_error(__('Failed to update status.', 'nexlifydesk'));
    }
}

add_action('nexlifydesk_check_orphaned_tickets', 'nexlifydesk_check_orphaned_tickets');

function nexlifydesk_check_orphaned_tickets() {
    NexlifyDesk_Tickets::handle_orphaned_tickets();
}

add_action('wp', function() {
    if (!wp_next_scheduled('nexlifydesk_check_orphaned_tickets')) {
        wp_schedule_event(time(), 'daily', 'nexlifydesk_check_orphaned_tickets');
    }
});

add_action('admin_enqueue_scripts', function($hook) {

    if (strpos($hook, 'nexlifydesk') === false) {
        return;
    }
    
    wp_enqueue_script(
        'nexlifydesk-admin',
        NEXLIFYDESK_PLUGIN_URL . 'assets/js/nexlifydesk.js',
        array('jquery'),
        NEXLIFYDESK_VERSION,
        true
    );

    $settings = get_option('nexlifydesk_settings', array());
    $max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 2;
    $allowed_file_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : 'jpg,jpeg,png,gif,pdf,doc,docx,txt';

    wp_localize_script('nexlifydesk-admin', 'nexlifydesk_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('nexlifydesk-ajax-nonce'),
        'uploading_text' => __('Uploading...', 'nexlifydesk'),
        'max_file_size' => $max_file_size,
        'allowed_file_types' => $allowed_file_types,
        'submitting_text' => __('Submitting...', 'nexlifydesk'),
        'submit_ticket_text' => __('Submit Ticket', 'nexlifydesk'),
        'error_occurred_text' => __('An error occurred. Please try again.', 'nexlifydesk'),
        'adding_text' => __('Adding...', 'nexlifydesk'),
        'add_category_text' => __('Add Category', 'nexlifydesk'),
        'add_reply_text' => __('Add Reply', 'nexlifydesk'),
        'ticket_form_url' => get_permalink(get_option('nexlifydesk_settings')['ticket_form_page_id'] ?? 0),
        'create_new_ticket_text' => __('Create New Ticket', 'nexlifydesk'),
        'ticket_closed_text' => __('This ticket is closed. Please create a new ticket for further assistance.', 'nexlifydesk'),
        'status_updated_text' => __('Status updated successfully!', 'nexlifydesk'),
        'ticket_page_url' => NexlifyDesk_Admin::get_ticket_page_url(),
    ));
});