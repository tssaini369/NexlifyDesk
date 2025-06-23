<?php
if (!defined('ABSPATH')) {
    exit;
}

class NexlifyDesk_Database {
    private static $tables = array();
    
    public static function init() {
        global $wpdb;
        self::$tables = array(
            'tickets' => $wpdb->prefix . 'nexlifydesk_tickets',
            'replies' => $wpdb->prefix . 'nexlifydesk_replies',
            'attachments' => $wpdb->prefix . 'nexlifydesk_attachments',
            'categories' => $wpdb->prefix . 'nexlifydesk_categories'
        );
    }   

    public static function activate() {
        global $wpdb;

        self::$tables = array(
            'tickets' => $wpdb->prefix . NEXLIFYDESK_TABLE_PREFIX . 'tickets',
            'replies' => $wpdb->prefix . NEXLIFYDESK_TABLE_PREFIX . 'replies',
            'attachments' => $wpdb->prefix . NEXLIFYDESK_TABLE_PREFIX . 'attachments',
            'categories' => $wpdb->prefix . NEXLIFYDESK_TABLE_PREFIX . 'categories'
        );

        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tickets table
        $sql = "CREATE TABLE " . self::$tables['tickets'] . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id varchar(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            category_id bigint(20) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            priority varchar(20) NOT NULL DEFAULT 'medium',
            assigned_to bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_id (ticket_id)
        ) $charset_collate;";
        
        // Replies table
        $sql .= "CREATE TABLE " . self::$tables['replies'] . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            message longtext NOT NULL,
            created_at datetime NOT NULL,
            is_admin_reply tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";
        
        // Attachments table
        $sql .= "CREATE TABLE " . self::$tables['attachments'] . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) DEFAULT NULL,
            reply_id bigint(20) DEFAULT NULL,
            user_id bigint(20) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(255) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY reply_id (reply_id)
        ) $charset_collate;";
        
        // Categories table
        $sql .= "CREATE TABLE " . self::$tables['categories'] . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        $result = dbDelta($sql);

        // Check if the tables were created
        $tables_to_check = array('tickets', 'replies', 'attachments', 'categories');
        foreach ($tables_to_check as $table_key) {
            $table_name = self::$tables[$table_key];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table existence check during activation, no caching needed
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name;
        }

        $categories_table = self::$tables['categories'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table existence check during activation, no caching needed
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $categories_table)) === $categories_table) {
            self::insert_default_categories();
        }

        add_option('nexlifydesk_version', NEXLIFYDESK_VERSION);
        add_option('nexlifydesk_settings', array(
            'email_notifications' => 1,
            'default_priority' => 'medium',
            'auto_assign' => 0,
            'allowed_file_types' => 'jpg,jpeg,png,pdf',
            'max_file_size' => 2, // 2MB
            'ticket_form_page_id' => 0
        ));
    }
    
    private static function insert_default_categories() {
        global $wpdb;
        
        $categories = array(
            'General Support',
            'Billing',
            'Technical Issues',
            'Feature Requests'
        );
        
        foreach ($categories as $category) {
            $slug = sanitize_title($category);
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Category existence check during activation, no caching needed
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM `{$wpdb->prefix}nexlifydesk_categories` WHERE slug = %s",
                    $slug
                )
            );
            
            if (!$exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert during activation, no caching needed
                $result = $wpdb->insert(
                    self::$tables['categories'],
                    array(
                        'name' => $category,
                        'slug' => $slug,
                        'description' => ''
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }
    }
    
    public static function deactivate() {
        // Optional: Clean up on deactivation
    }
    
    public static function get_table($name) {
        return isset(self::$tables[$name]) ? self::$tables[$name] : null;
    }
    
    public static function generate_ticket_id() {
        $settings = get_option('nexlifydesk_settings');
        $prefix = isset($settings['ticket_id_prefix']) ? $settings['ticket_id_prefix'] : 'T';
        $start = isset($settings['ticket_id_start']) ? (int)$settings['ticket_id_start'] : 1001;

        $last_number = (int)get_option('nexlifydesk_last_ticket_number', 0);

        if ($last_number < $start - 1) {
            $last_number = $start - 1;
        }

        $next_number = $last_number + 1;

        update_option('nexlifydesk_last_ticket_number', $next_number);

        return $prefix . $next_number;
    }
}