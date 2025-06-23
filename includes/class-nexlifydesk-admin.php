<?php
if (!defined('ABSPATH')) {
    exit;
}

class NexlifyDesk_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('admin_notices', array(__CLASS__, 'display_admin_notices'));
        add_action('admin_init', array(__CLASS__, 'handle_settings_save'));
        add_action('admin_post_nexlifydesk_save_settings', array(__CLASS__, 'save_settings'));
        add_action('admin_post_nopriv_nexlifydesk_save_settings', array(__CLASS__, 'save_settings'));
        add_action('admin_post_nexlifydesk_save_agent_position', array(__CLASS__, 'handle_save_agent_position'));
        add_action('admin_post_nexlifydesk_delete_agent_position', array(__CLASS__, 'handle_delete_agent_position'));
        add_action('admin_post_nexlifydesk_save_agent_assignments', array(__CLASS__, 'handle_save_agent_assignments'));
    }

    public static function register_admin_menu() {
        // Main menu
        $hook = add_menu_page(
            __('NexlifyDesk Tickets', 'nexlifydesk'),
            __('NexlifyDesk', 'nexlifydesk'),
            'nexlifydesk_manage_tickets',
            'nexlifydesk_tickets',
            array(__CLASS__, 'render_ticket_list_page'),
            NEXLIFYDESK_PLUGIN_URL . 'assets/images/dashboard-icon.png',
            25
        );

        // Submenu: Tickets
        add_submenu_page(
            'nexlifydesk_tickets',
            __('All Tickets', 'nexlifydesk'),
            __('All Tickets', 'nexlifydesk'),
            'nexlifydesk_manage_tickets',
            'nexlifydesk_tickets',
            array(__CLASS__, 'render_ticket_list_page')
        );

        // Submenu: Categories
        add_submenu_page(
            'nexlifydesk_tickets',
            __('Ticket Categories', 'nexlifydesk'),
            __('Categories', 'nexlifydesk'),
            'nexlifydesk_manage_categories',
            'read',
            array(__CLASS__, 'render_categories_page')
        );

        // Submenu: Settings
        add_submenu_page(
            'nexlifydesk_tickets',
            __('Settings', 'nexlifydesk'),
            __('Settings', 'nexlifydesk'),
            'manage_options',
            'nexlifydesk_settings',
            array(__CLASS__, 'render_settings_page')
        );

        // Submenu: Reports
        add_submenu_page(
            'nexlifydesk_tickets',
            __('Reports', 'nexlifydesk'),
            __('Reports', 'nexlifydesk'),
            'read',
            'nexlifydesk_reports',
            array(__CLASS__, 'render_reports_page')
        );

        // Submenu: Order History
        add_submenu_page(
            'nexlifydesk_tickets',
            __('Order History', 'nexlifydesk'),
            __('Order History', 'nexlifydesk'),
            'nexlifydesk_manage_tickets',
            'nexlifydesk_order_history',
            'nexlifydesk_render_order_history_page'
        );

        // Submenu: Agent Positions
         add_submenu_page(
            'nexlifydesk_tickets',
            __('Agent Positions', 'nexlifydesk'),
            __('Agent Positions', 'nexlifydesk'),
            'manage_options',
            'nexlifydesk_agent_positions',
            array(__CLASS__, 'render_agent_positions_page')
        );

        // Submenu: Email Templates
         add_submenu_page(
            'nexlifydesk_tickets',
            __('Email Templates', 'nexlifydesk'),
            __('Email Templates', 'nexlifydesk'),
            'manage_options',
            'nexlifydesk_email_templates',
            array(__CLASS__, 'render_email_templates_page')
        );

        // Add Support submenu
        add_submenu_page(
            'nexlifydesk_tickets',
            __('Support', 'nexlifydesk'),
            __('Support', 'nexlifydesk'),
            'manage_options',
            'nexlifydesk_support',
            array('NexlifyDesk_Admin', 'render_support_page')
        );
    
    }

    public static function render_support_page() {
        if (isset($_POST['submit']) && isset($_POST['nexlify_support_nonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexlify_support_nonce'])), 'nexlify_support_form')) {
                $subject = isset($_POST['support_subject']) ? sanitize_text_field(wp_unslash($_POST['support_subject'])) : '';
                $message = isset($_POST['support_message']) ? sanitize_textarea_field(wp_unslash($_POST['support_message'])) : '';
                $admin_email = get_option('admin_email');

                if (!empty($subject) && !empty($message)) {
                    $email_data = array(
                        'plugin_name'    => 'NexlifyDesk',
                        'plugin_version' => NEXLIFYDESK_VERSION,
                        'subject'        => $subject,
                        'message'        => $message,
                        'customer_email' => $admin_email,
                        'site_url'       => home_url(),
                    );
                    
                    if (function_exists('nexlify_send_support_email')) {
                        $sent = nexlify_send_support_email($email_data);
                        if ($sent) {
                            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Support request sent successfully!', 'nexlifydesk') . '</p></div>';
                        } else {
                            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to send support request. Please ensure your WordPress site can send emails.', 'nexlifydesk') . '</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error: Support email function not found.', 'nexlifydesk') . '</p></div>';
                    }
                } else {
                     echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Subject and Message are required fields.', 'nexlifydesk') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Security check failed. Please try again.', 'nexlifydesk') . '</p></div>';
            }
        }

        if (function_exists('nexlify_display_support_form')) {
            nexlify_display_support_form('NexlifyDesk', NEXLIFYDESK_VERSION);
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Support', 'nexlifydesk') . '</h1><p>' . esc_html__('Error: Support form display function not found.', 'nexlifydesk') . '</p></div>';
        }
    }

    public static function enqueue_admin_assets($hook) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading $_GET for asset loading, not processing sensitive data.
        $page = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading $_GET for asset loading, not processing sensitive data.
        $ticket_id = isset($_GET['ticket_id']) ? sanitize_text_field( wp_unslash( $_GET['ticket_id'] ) ) : '';

            if (
                ($page && strpos($page, 'nexlifydesk') === 0) ||
                (strpos($hook, 'nexlifydesk') !== false) ||
                ($page === 'nexlifydesk_tickets' && $ticket_id)
            ) {
                wp_enqueue_style(
                    'nexlifydesk-admin',
                    NEXLIFYDESK_PLUGIN_URL . 'assets/css/nexlifydesk-admin.css',
                    array(),
                    NEXLIFYDESK_VERSION
                );
                wp_enqueue_script(
                    'nexlifydesk-admin',
                    NEXLIFYDESK_PLUGIN_URL . 'assets/js/nexlifydesk.js',
                    array('jquery'),
                    NEXLIFYDESK_VERSION,
                    true
                );

            $available_capabilities = array(
                NEXLIFYDESK_CAP_VIEW_ALL_TICKETS => __('View All Tickets', 'nexlifydesk'),
                NEXLIFYDESK_CAP_ASSIGN_TICKETS => __('Assign Tickets', 'nexlifydesk'),
                NEXLIFYDESK_CAP_MANAGE_CATEGORIES => __('Manage Categories', 'nexlifydesk'),
                NEXLIFYDESK_CAP_VIEW_REPORTS => __('View Reports', 'nexlifydesk'),
            );

            wp_localize_script(
                'nexlifydesk-admin',
                'nexlifydesk_admin_vars',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('nexlifydesk-ajax-nonce'),
                    'position_nonce' => wp_create_nonce('nexlifydesk_save_agent_position'),
                    'admin_post_url' => admin_url('admin-post.php'),
                    'available_capabilities' => $available_capabilities,
                    'position_name_label' => __('Position Name:', 'nexlifydesk'),
                    'position_slug_label' => __('Position Slug (unique):', 'nexlifydesk'),
                    'assign_capabilities_label' => __('Assign Capabilities:', 'nexlifydesk'),
                    'save_text' => __('Save', 'nexlifydesk'),
                    'cancel_text' => __('Cancel', 'nexlifydesk'),
                    'delete_confirm' => __('Are you sure you want to delete this position?', 'nexlifydesk'),
                    'loading_tickets_text' => __('Loading tickets...', 'nexlifydesk'),
                    'no_tickets_found_text' => __('No tickets found.', 'nexlifydesk'),
                    'error_loading_tickets_text' => __('Error loading tickets:', 'nexlifydesk'),
                    'ajax_error_loading_tickets_text' => __('AJAX Error loading tickets.', 'nexlifydesk'),
                    'ticket_id_header' => __('Ticket ID', 'nexlifydesk'),
                    'subject_header' => __('Subject', 'nexlifydesk'),
                    'status_header' => __('Status', 'nexlifydesk'),
                    'created_header' => __('Created', 'nexlifydesk'),
                    'actions_header' => __('Actions', 'nexlifydesk'),
                    'view_text' => __('View', 'nexlifydesk'),
                    'assigned_to_header' => __('Assigned To', 'nexlifydesk'),
                )
            );
        }
    }

    public static function render_ticket_list_page() {
    if (!current_user_can('nexlifydesk_manage_tickets')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'nexlifydesk'));
    }

    if (current_user_can('manage_options')) {
        echo '<div class="wrap">';
        echo '<button id="reassign-orphaned-tickets" class="button button-secondary" style="margin-bottom: 10px;">';
        echo esc_html__('Reassign Orphaned Tickets', 'nexlifydesk');
        echo '</button>';
        echo '</div>';
    }

    $ticket_id = 0;
    
    if (isset($_GET['ticket_id']) && !isset($_POST['action'])) {
        $ticket_id = absint($_GET['ticket_id']);
        
        if (!current_user_can('nexlifydesk_view_all_tickets')) {
            $ticket = NexlifyDesk_Tickets::get_ticket($ticket_id);
            if (!$ticket || (int)$ticket->user_id !== get_current_user_id()) {
                wp_die(esc_html__('You do not have permission to view this ticket.', 'nexlifydesk'));
            }
        }
        
        if ($ticket_id > 0) {
            $ticket = NexlifyDesk_Tickets::get_ticket($ticket_id);
            if (!$ticket) {
                wp_die(esc_html__('Ticket not found.', 'nexlifydesk'));
            }
        }
    }
    
    if (isset($_POST['action']) || isset($_GET['s']) || isset($_GET['status'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'nexlifydesk_tickets_action')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'nexlifydesk'));
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('NexlifyDesk Tickets', 'nexlifydesk'); ?></h1>

        <?php if ($ticket_id > 0) : ?>
            <?php
            $ticket = NexlifyDesk_Tickets::get_ticket($ticket_id);
            if ($ticket) {
                include NEXLIFYDESK_PLUGIN_DIR . 'templates/admin/ticket-single.php';
            } else {
                echo '<p>' . esc_html__('Ticket not found.', 'nexlifydesk') . '</p>';
            }
            ?>
        <?php else : ?>
            <?php include NEXLIFYDESK_PLUGIN_DIR . 'templates/admin/tickets-list.php'; ?>
        <?php endif; ?>

    </div>
    <?php
}

    public static function render_categories_page() {

        if (!NexlifyDesk_Users::agent_can('nexlifydesk_manage_categories')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nexlifydesk'));
        }

        $cache_key = 'nexlifydesk_categories_admin_page';
        $categories = wp_cache_get($cache_key);

        if (false === $categories) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
            $categories = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$wpdb->prefix}nexlifydesk_categories` WHERE is_active = %d", 
                    1
                )
            );
            wp_cache_set($cache_key, $categories, '', 600);
        }
    ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Ticket Categories', 'nexlifydesk'); ?> <a href="#add-category" class="page-title-action"><?php esc_html_e('Add New', 'nexlifydesk'); ?></a></h1>
            <form id="nexlifydesk-category-form" method="post" action="" style="display: none;">
                <?php wp_nonce_field('nexlifydesk_save_category', 'nexlifydesk_category_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="category_name"><?php esc_html_e('Category Name', 'nexlifydesk'); ?></label></th>
                        <td><input type="text" name="category_name" id="category_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="category_description"><?php esc_html_e('Description', 'nexlifydesk'); ?></label></th>
                        <td><textarea name="category_description" id="category_description" rows="4" cols="50"></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit_category" class="button button-primary" value="<?php esc_html_e('Add Category', 'nexlifydesk'); ?>">
                </p>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'nexlifydesk'); ?></th>
                        <th><?php esc_html_e('Slug', 'nexlifydesk'); ?></th>
                        <th><?php esc_html_e('Description', 'nexlifydesk'); ?></th>
                        <th><?php esc_html_e('Actions', 'nexlifydesk'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo esc_html($category->name); ?></td>
                            <td><?php echo esc_html($category->slug); ?></td>
                            <td><?php echo esc_html($category->description); ?></td>
                            <td>
                                <a href="#" class="delete-category" data-id="<?php echo esc_attr($category->id); ?>"><?php esc_html_e('Delete', 'nexlifydesk'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('NexlifyDesk Settings', 'nexlifydesk'); ?></h1>
            <?php include NEXLIFYDESK_PLUGIN_DIR . 'templates/admin/settings.php'; ?>
        </div>
        <?php
    }

    public static function render_reports_page() {

        if (!NexlifyDesk_Users::agent_can('nexlifydesk_view_reports')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nexlifydesk'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('NexlifyDesk Reports', 'nexlifydesk'); ?></h1>
            <?php include NEXLIFYDESK_PLUGIN_DIR . 'templates/admin/reports.php'; ?>
        </div>
        <?php
    }

    public static function render_agent_positions_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('NexlifyDesk Agent Positions', 'nexlifydesk'); ?></h1>
            <?php settings_errors('nexlifydesk_agent_positions'); ?>
            <?php self::display_agent_positions_management(); ?>
        </div>
        <?php
    }

    private static function handle_agent_positions_save() {
    if (isset($_POST['add_position']) && current_user_can('manage_options')) {
        if (
            !isset($_POST['nexlifydesk_agent_position_nonce']) || 
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_position_nonce'])),
                'nexlifydesk_save_agent_position'
            )
        ) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        $position_name = isset($_POST['position_name']) 
            ? sanitize_text_field(wp_unslash($_POST['position_name'])) 
            : '';

        $position_slug = isset($_POST['position_slug']) 
            ? sanitize_title(wp_unslash($_POST['position_slug']))
            : '';

        $position_capabilities = isset($_POST['position_capabilities']) 
            ? array_map('sanitize_text_field', wp_unslash((array)$_POST['position_capabilities']))
            : array();

        if (empty($position_name) || empty($position_slug)) {
            add_settings_error(
                'nexlifydesk_agent_positions',
                'empty_fields',
                esc_html__('Position Name and Slug are required.', 'nexlifydesk'),
                'error'
            );
            return;
        }

        $positions = get_option('nexlifydesk_agent_positions', array());

        if (isset($positions[$position_slug])) {
            add_settings_error('nexlifydesk_agent_positions', 'duplicate_slug', __('A position with this slug already exists.', 'nexlifydesk'), 'error');
            return;
        }

        $positions[$position_slug] = array(
            'name' => $position_name,
            'capabilities' => $position_capabilities,
        );

        update_option('nexlifydesk_agent_positions', $positions);

        add_settings_error('nexlifydesk_agent_positions', 'position_added', __('Agent position added successfully.', 'nexlifydesk'), 'success');

        $redirect_url = add_query_arg(array(
            'page' => 'nexlifydesk_agent_positions',
            'settings-updated' => 'true'
        ), admin_url('admin.php'));
        wp_redirect($redirect_url);
        exit;
    }

    if (isset($_POST['delete_position'], $_POST['position_slug']) && current_user_can('manage_options')) {
        $nonce = isset($_POST['nexlifydesk_agent_position_nonce']) 
            ? sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_position_nonce']))
            : '';
        
        if (!wp_verify_nonce($nonce, 'nexlifydesk_save_agent_position')) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        $slug = sanitize_title(wp_unslash($_POST['position_slug']));
        
        $positions = get_option('nexlifydesk_agent_positions', array());
        if (isset($positions[$slug])) {
            unset($positions[$slug]);
            update_option('nexlifydesk_agent_positions', $positions);
            add_settings_error(
                'nexlifydesk_agent_positions', 
                'position_deleted', 
                esc_html__('Agent position deleted.', 'nexlifydesk'), 
                'success'
            );
        } else {
            add_settings_error(
                'nexlifydesk_agent_positions', 
                'position_not_found', 
                esc_html__('Position not found.', 'nexlifydesk'), 
                'error'
            );
        }

        $redirect_url = add_query_arg(array(
            'page' => 'nexlifydesk_agent_positions',
            'settings-updated' => 'true'
        ), admin_url('admin.php'));
        wp_redirect($redirect_url);
        exit;
    }

    if (isset($_POST['edit_position'], $_POST['original_slug']) && current_user_can('manage_options')) {
        $nonce = isset($_POST['nexlifydesk_agent_position_nonce']) 
            ? sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_position_nonce']))
            : '';
        
        if (!wp_verify_nonce($nonce, 'nexlifydesk_save_agent_position')) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        $original_slug = sanitize_title(wp_unslash($_POST['original_slug']));
        $position_name = sanitize_text_field(wp_unslash($_POST['position_name']));
        $position_slug = sanitize_title(wp_unslash($_POST['position_slug']));
        $position_capabilities = isset($_POST['position_capabilities']) 
            ? array_map('sanitize_text_field', wp_unslash((array)$_POST['position_capabilities']))
            : array();

        $positions = get_option('nexlifydesk_agent_positions', array());

        if ($original_slug !== $position_slug && isset($positions[$original_slug])) {
            unset($positions[$original_slug]);
        }

        $positions[$position_slug] = array(
            'name' => $position_name,
            'capabilities' => $position_capabilities,
        );

        update_option('nexlifydesk_agent_positions', $positions);
        add_settings_error('nexlifydesk_agent_positions', 'position_edited', __('Agent position updated.', 'nexlifydesk'), 'success');

        $redirect_url = add_query_arg(array(
            'page' => 'nexlifydesk_agent_positions',
            'settings-updated' => 'true'
        ), admin_url('admin.php'));
        wp_redirect($redirect_url);
        exit;
    }
}

    public static function handle_save_agent_position() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nexlifydesk'));
        }

        if (!isset($_POST['nexlifydesk_agent_position_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_position_nonce'])), 'nexlifydesk_save_agent_position')) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        $positions = get_option('nexlifydesk_agent_positions', array());

        if (isset($_POST['add_position'])) {
            $position_name = isset($_POST['position_name']) ? sanitize_text_field(wp_unslash($_POST['position_name'])) : '';
            $position_slug = isset($_POST['position_slug']) ? sanitize_title(wp_unslash($_POST['position_slug'])) : '';
            $position_capabilities = isset($_POST['position_capabilities']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['position_capabilities'])) : array();

            if (empty($position_name) || empty($position_slug)) {
                add_settings_error('nexlifydesk_agent_positions', 'empty_fields', __('Position Name and Slug are required.', 'nexlifydesk'), 'error');
            } elseif (isset($positions[$position_slug])) {
                add_settings_error('nexlifydesk_agent_positions', 'duplicate_slug', __('A position with this slug already exists.', 'nexlifydesk'), 'error');
            } else {
                $positions[$position_slug] = array(
                    'name' => $position_name,
                    'capabilities' => $position_capabilities,
                );
                update_option('nexlifydesk_agent_positions', $positions);
                add_settings_error('nexlifydesk_agent_positions', 'position_added', __('Agent position added successfully.', 'nexlifydesk'), 'success');
            }
        } elseif (isset($_POST['edit_position'])) {
            $position_slug = isset($_POST['position_slug']) ? sanitize_title(wp_unslash($_POST['position_slug'])) : '';
            $position_name = isset($_POST['position_name']) ? sanitize_text_field(wp_unslash($_POST['position_name'])) : '';
            $position_slug = isset($_POST['position_slug']) ? sanitize_title(wp_unslash($_POST['position_slug'])) : '';
            $position_capabilities = isset($_POST['position_capabilities']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['position_capabilities'])) : array();

            if (isset($positions[$original_slug])) {
                unset($positions[$original_slug]);
            }
            $positions[$position_slug] = array(
                'name' => $position_name,
                'capabilities' => $position_capabilities,
            );
            update_option('nexlifydesk_agent_positions', $positions);
            add_settings_error('nexlifydesk_agent_positions', 'position_edited', __('Agent position updated.', 'nexlifydesk'), 'success');
        }

        wp_redirect(add_query_arg(array(
            'page' => 'nexlifydesk_agent_positions',
            'settings-updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    public static function handle_delete_agent_position() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nexlifydesk'));
        }

        if (!isset($_POST['nexlifydesk_agent_position_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_position_nonce'])), 'nexlifydesk_save_agent_position')) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        $slug = isset($_POST['position_slug']) ? sanitize_title(wp_unslash($_POST['position_slug'])) : '';
        $positions = get_option('nexlifydesk_agent_positions', array());
        if (isset($positions[$slug])) {
            unset($positions[$slug]);
            update_option('nexlifydesk_agent_positions', $positions);
            add_settings_error('nexlifydesk_agent_positions', 'position_deleted', __('Agent position deleted.', 'nexlifydesk'), 'success');
        } else {
            add_settings_error('nexlifydesk_agent_positions', 'position_not_found', __('Position not found.', 'nexlifydesk'), 'error');
        }

        wp_redirect(add_query_arg(array(
            'page' => 'nexlifydesk_agent_positions',
            'settings-updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    private static function display_agent_positions_management() {
        $positions = get_option('nexlifydesk_agent_positions', array());
        $available_capabilities = array(
            NEXLIFYDESK_CAP_VIEW_ALL_TICKETS => __('View All Tickets', 'nexlifydesk'),
            NEXLIFYDESK_CAP_ASSIGN_TICKETS => __('Assign Tickets', 'nexlifydesk'),
            NEXLIFYDESK_CAP_MANAGE_CATEGORIES => __('Manage Categories', 'nexlifydesk'),
            NEXLIFYDESK_CAP_VIEW_REPORTS => __('View Reports', 'nexlifydesk'),
        );

        echo '<h3>' . esc_html__('Existing Positions', 'nexlifydesk') . '</h3>';
        if (!empty($positions)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__('Name', 'nexlifydesk') . '</th><th>' . esc_html__('Slug', 'nexlifydesk') . '</th><th>' . esc_html__('Capabilities', 'nexlifydesk') . '</th><th>' . esc_html__('Actions', 'nexlifydesk') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($positions as $slug => $position) {
                $assigned_caps = isset($position['capabilities']) ? implode(',', $position['capabilities']) : '';
                echo '<tr data-capabilities="' . esc_attr($assigned_caps) . '">';
                echo '<td><strong>' . esc_html($position['name']) . '</strong></td>';
                echo '<td>' . esc_html($slug) . '</td>';
                echo '<td>';
                if (!empty($position['capabilities'])) {
                    $cap_names = array_map(function($cap_slug) use ($available_capabilities) {
                        return $available_capabilities[$cap_slug] ?? $cap_slug;
                    }, $position['capabilities']);
                    echo esc_html(implode(', ', $cap_names));
                } else {
                    esc_html_e('None', 'nexlifydesk');
                }
                echo '</td>';
                echo '<td>';
                echo '<button class="button button-small edit-position" data-slug="' . esc_attr($slug) . '">' . esc_html__('Edit', 'nexlifydesk') . '</button> ';
                echo '<button class="button button-small button-danger delete-position" data-slug="' . esc_attr($slug) . '">' . esc_html__('Delete', 'nexlifydesk') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>' . esc_html__('No agent positions defined yet.', 'nexlifydesk') . '</p>';
        }

        echo '<h3>' . esc_html__('Add New Position', 'nexlifydesk') . '</h3>';
        echo '<h3>' . esc_html__('Add New Position', 'nexlifydesk') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="nexlifydesk_save_agent_position">';
        wp_nonce_field('nexlifydesk_save_agent_position', 'nexlifydesk_agent_position_nonce');
        echo '<table class="form-table">';
        echo '<tr><th><label for="position_name">' . esc_html__('Position Name:', 'nexlifydesk') . '</label></th><td><input type="text" name="position_name" id="position_name" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="position_slug">' . esc_html__('Position Slug (unique):', 'nexlifydesk') . '</label></th><td><input type="text" name="position_slug" id="position_slug" class="regular-text" required></td></tr>';
        echo '<tr><th><strong>' . esc_html__('Assign Capabilities:', 'nexlifydesk') . '</strong></th><td><ul>';
        foreach ($available_capabilities as $cap_slug => $cap_name) {
            echo '<li><label><input type="checkbox" name="position_capabilities[]" value="' . esc_attr($cap_slug) . '"> ' . esc_html($cap_name) . '</label></li>';
        }
        echo '</ul></td></tr>';
        echo '</table>';
        submit_button(__('Add Position', 'nexlifydesk'), 'primary', 'add_position', false);
        echo '</form>';

        echo '<hr>';
        echo '<h3>' . esc_html__('Assign Positions to Agents', 'nexlifydesk') . '</h3>';
        self::display_agent_assignment_management($positions);
    }

    private static function display_agent_assignment_management($positions) {
        settings_errors('nexlifydesk_agent_assignments');

        $agents = get_users(array('role' => 'nexlifydesk_agent', 'orderby' => 'display_name'));

        if (empty($agents)) {
            echo '<p>' . esc_html__('No NexlifyDesk Agents found.', 'nexlifydesk') . '</p>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="nexlifydesk_save_agent_assignments">';
        wp_nonce_field('nexlifydesk_save_agent_assignments', 'nexlifydesk_agent_assignments_nonce');

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Agent Name', 'nexlifydesk') . '</th><th>' . esc_html__('Current Position', 'nexlifydesk') . '</th><th>' . esc_html__('Assign Position', 'nexlifydesk') . '</th></tr></thead>';
        echo '<tbody>';

        foreach ($agents as $agent) {
            $current_position_slug = get_user_meta($agent->ID, 'nexlifydesk_agent_position', true);
            $current_position_name = $positions[$current_position_slug]['name'] ?? __('None', 'nexlifydesk');

            echo '<tr>';
            echo '<td>' . esc_html($agent->display_name) . '</td>';
            echo '<td>' . esc_html($current_position_name) . '</td>';
            echo '<td>';
            echo '<select name="agent_positions[' . esc_attr($agent->ID) . ']">';
            echo '<option value="">' . esc_html__('None', 'nexlifydesk') . '</option>';
            foreach ($positions as $slug => $position) {
                echo '<option value="' . esc_attr($slug) . '" ' . selected($current_position_slug, $slug, false) . '>' . esc_html($position['name']) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        submit_button(__('Save Agent Assignments', 'nexlifydesk'), 'primary', 'save_assignments', false);
        echo '</form>';
    }

    private static function handle_agent_assignments_save() {

        if (isset($_POST['save_assignments']) && current_user_can('manage_options')) {
            if (
                !isset($_POST['nexlifydesk_agent_assignments_nonce']) || 
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_assignments_nonce'])),
                    'nexlifydesk_save_agent_assignments'
                )
            ) {
                wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
            }

            $agent_positions = isset($_POST['agent_positions']) 
                ? array_map('sanitize_text_field', wp_unslash((array)$_POST['agent_positions'])) 
                : array();

            if (!empty($agent_positions)) {
                foreach ($agent_positions as $user_id => $position_slug) {
                    $user_id = absint($user_id);
                    $position_slug = sanitize_text_field($position_slug);

                    $user = get_userdata($user_id);
                    if ($user && in_array('nexlifydesk_agent', $user->roles)) {
                        update_user_meta($user_id, 'nexlifydesk_agent_position', $position_slug);
                    }
                }
                add_settings_error('nexlifydesk_agent_assignments', 'assignments_saved', __('Agent assignments saved successfully.', 'nexlifydesk'), 'success');
            } else {
                 add_settings_error('nexlifydesk_agent_assignments', 'no_assignments', __('No agent assignments to save.', 'nexlifydesk'), 'warning');
            }


            $redirect_url = add_query_arg(array(
                'page' => 'nexlifydesk_agent_positions',
                'settings-updated' => 'true'
            ), admin_url('admin.php'));
            if (function_exists('ob_end_clean')) { @ob_end_clean(); }
            wp_redirect($redirect_url);
            exit;
        }
    }

    public static function handle_save_agent_assignments() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nexlifydesk'));
        }

        if (!isset($_POST['nexlifydesk_agent_assignments_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_assignments_nonce'])), 'nexlifydesk_save_agent_assignments')) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        if (isset($_POST['save_assignments'])) {
            $agent_positions = isset($_POST['agent_positions']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['agent_positions'])) : array();

            if (!empty($agent_positions)) {
                foreach ($agent_positions as $user_id => $position_slug) {
                    $user_id = absint($user_id);
                    $position_slug = sanitize_text_field($position_slug);

                    $user = get_userdata($user_id);
                    if ($user && in_array('nexlifydesk_agent', $user->roles)) {
                        update_user_meta($user_id, 'nexlifydesk_agent_position', $position_slug);
                    }
                }
                add_settings_error('nexlifydesk_agent_assignments', 'assignments_saved', __('Agent assignments saved successfully.', 'nexlifydesk'), 'success');
            } else {
                add_settings_error('nexlifydesk_agent_assignments', 'no_assignments', __('No agent assignments to save.', 'nexlifydesk'), 'warning');
            }
        }

        wp_redirect(add_query_arg(array(
            'page' => 'nexlifydesk_agent_positions',
            'settings-updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    public static function display_admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved successfully!', 'nexlifydesk'); ?></p>
            </div>
            <?php
        }
    
        if (isset($_GET['category_added']) && $_GET['category_added'] === 'true') {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            
            if (!wp_verify_nonce($nonce, 'nexlifydesk_category_nonce')) {
                return;
            }   
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Category added successfully!', 'nexlifydesk'); ?></p>
            </div>
            <?php
        }
    }

    public static function handle_settings_save() {
        if (isset($_POST['nexlifydesk_settings_save']) && check_admin_referer('nexlifydesk_save_settings', 'nexlifydesk_settings_nonce')) {
            $settings = array(
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'default_priority' => isset($_POST['default_priority']) ? sanitize_text_field(wp_unslash($_POST['default_priority'])) : '',
                'auto_assign' => isset($_POST['auto_assign']) ? 1 : 0,
                'allowed_file_types' => isset($_POST['allowed_file_types']) ? self::validate_file_types(sanitize_text_field(wp_unslash($_POST['allowed_file_types']))) : 'jpg,jpeg,png,pdf',
                'max_file_size' => isset($_POST['max_file_size']) ? absint($_POST['max_file_size']) : 0,
                'default_category' => isset($_POST['default_category']) ? absint($_POST['default_category']) : 0,
                'sla_response_time' => isset($_POST['sla_response_time']) ? absint($_POST['sla_response_time']) : 0,
                'ticket_page_id' => isset($_POST['ticket_page_id']) ? absint($_POST['ticket_page_id']) : 0,
                'ticket_form_page_id' => isset($_POST['ticket_form_page_id']) ? absint($_POST['ticket_form_page_id']) : 0, // Add this line
                'ticket_id_prefix' => isset($_POST['ticket_id_prefix']) ? sanitize_text_field(wp_unslash($_POST['ticket_id_prefix'])) : '',
                'ticket_id_start' => isset($_POST['ticket_id_start']) ? absint($_POST['ticket_id_start']) : 0,
                'status_change_notification' => isset($_POST['status_change_notification']) ? 1 : 0,
            );

            update_option('nexlifydesk_settings', $settings);
            wp_redirect(add_query_arg(array('page' => 'nexlifydesk_settings', 'settings-updated' => 'true'), admin_url('admin.php')));
            exit;
        }

        if (isset($_POST['submit_category']) && check_admin_referer('nexlifydesk_save_category', 'nexlifydesk_category_nonce')) {
            global $wpdb;

            if (empty($_POST['category_name'])) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Category name is required.', 'nexlifydesk') . '</p></div>';
                });
                return;
            }

            $category_name = sanitize_text_field(wp_unslash($_POST['category_name']));
            $category_description = isset($_POST['category_description']) ? sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';
            $slug = sanitize_title($category_name);

            $table_name = NexlifyDesk_Database::get_table('categories');
            if (empty($table_name) || strpos($table_name, $wpdb->prefix) !== 0) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid database table. Please contact support.', 'nexlifydesk') . '</p></div>';
                });
                return;
            }

            $cache_key = 'nexlifydesk_category_slug_check_admin_add_' . md5($slug . '_' . get_current_user_id());
            $existing = wp_cache_get($cache_key);

            if (false === $existing) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled, query is prepared
                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM `" . esc_sql($table_name) . "` WHERE slug = %s AND is_active = %d",
                        $slug,
                        1
                    )
                );
                wp_cache_set($cache_key, $existing, '', 300);
            }

            if ($existing) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('A category with this name already exists.', 'nexlifydesk') . '</p></div>';
                });
                return;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled, query is prepared
            $result = $wpdb->insert(
                $table_name,
                array(
                    'name' => $category_name,
                    'slug' => $slug,
                    'description' => $category_description,
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%d')
            );

            if ($result === false) {
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    wp_send_json_error(__('Failed to add category.', 'nexlifydesk'));
                }
                add_action('admin_notices', function() use ($wpdb) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . 
                        sprintf(
                            /* translators: %s: Database error message */
                            esc_html__('Failed to add category: %s', 'nexlifydesk'),
                            esc_html($wpdb->last_error)
                        ) . 
                    '</p></div>';
                });
                return;
            }

            wp_cache_delete($cache_key);

            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_success(array('message' => __('Category added successfully!', 'nexlifydesk')));
            }

            wp_redirect(add_query_arg(array(
                'page' => 'nexlifydesk_categories',
                'category_added' => 'true'
            ), admin_url('admin.php')));
            exit;
        }
    }

    private static function validate_file_types($file_types) {
        $allowed_types = array(
            'jpg', 'jpeg', 'png', 'pdf'
        );
        
        $file_types = strtolower(trim($file_types));
        if (empty($file_types)) {
            return 'jpg,jpeg,png,pdf';
        }
        
        $types = array_map('trim', explode(',', $file_types));
        $valid_types = array();
        
        foreach ($types as $type) {
            $type = ltrim($type, '.');
            if (in_array($type, $allowed_types, true)) {
                $valid_types[] = $type;
            }
        }
        
        return !empty($valid_types) ? implode(',', array_unique($valid_types)) : 'jpg,jpeg,png,pdf';
    }

    public static function render_email_templates_page() {
        $templates = get_option('nexlifydesk_email_templates', array(
            'new_ticket' => '',
            'new_reply' => '',
            'status_changed' => '',
            'sla_breach' => '',
        ));

        if (isset($_POST['nexlifydesk_save_email_templates']) && check_admin_referer('nexlifydesk_save_email_templates')) {
            $templates = [
                'new_ticket' => isset($_POST['new_ticket']) ? wp_kses_post(wp_unslash($_POST['new_ticket'])) : '',
                'new_reply' => isset($_POST['new_reply']) ? wp_kses_post(wp_unslash($_POST['new_reply'])) : '',
                'status_changed' => isset($_POST['status_changed']) ? wp_kses_post(wp_unslash($_POST['status_changed'])) : '',
                'sla_breach' => isset($_POST['sla_breach']) ? wp_kses_post(wp_unslash($_POST['sla_breach'])) : '',
            ];
                update_option('nexlifydesk_email_templates', $templates);
                
                echo '<div class="notice notice-success is-dismissible"><p>' 
                    . esc_html__('Email templates saved!', 'nexlifydesk') 
                    . '</p></div>';
            }
            ?>
        <div class="wrap">
            <h1><?php esc_html_e('NexlifyDesk Email Templates', 'nexlifydesk'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('nexlifydesk_save_email_templates'); ?>
                
                <h2><?php esc_html_e('New Ticket', 'nexlifydesk'); ?></h2>
                <?php wp_editor(
                    $templates['new_ticket'],
                    'new_ticket',
                    array(
                        'textarea_name' => 'new_ticket',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce' => false,
                        'quicktags' => true,
                        'default_editor' => 'html',
                    )
                ); ?>
                <button type="button" class="button preview-email-template" data-editor="new_ticket" style="margin-top:5px;">
                    <?php esc_html_e('Preview', 'nexlifydesk'); ?>
                </button>
                <div id="preview-new_ticket" class="nexlifydesk-email-preview" style="border:1px solid #ddd; margin-top:10px; padding:10px; display:none;"></div>
                
                <h2><?php esc_html_e('New Reply', 'nexlifydesk'); ?></h2>
                <?php wp_editor(
                    $templates['new_reply'],
                    'new_reply',
                    array(
                        'textarea_name' => 'new_reply',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce' => false,
                        'quicktags' => true,
                        'default_editor' => 'html',
                    )
                ); ?>
                <button type="button" class="button preview-email-template" data-editor="new_reply" style="margin-top:5px;">
                    <?php esc_html_e('Preview', 'nexlifydesk'); ?>
                </button>
                <div id="preview-new_reply" class="nexlifydesk-email-preview" style="border:1px solid #ddd; margin-top:10px; padding:10px; display:none;"></div>
                
                <h2><?php esc_html_e('Status Changed', 'nexlifydesk'); ?></h2>
                <?php wp_editor(
                    $templates['status_changed'],
                    'status_changed',
                    array(
                        'textarea_name' => 'status_changed',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce' => false,
                        'quicktags' => true,
                        'default_editor' => 'html',
                    )
                ); ?>
                <button type="button" class="button preview-email-template" data-editor="status_changed" style="margin-top:5px;">
                    <?php esc_html_e('Preview', 'nexlifydesk'); ?>
                </button>
                <div id="preview-status_changed" class="nexlifydesk-email-preview" style="border:1px solid #ddd; margin-top:10px; padding:10px; display:none;"></div>
                
                <h2><?php esc_html_e('SLA Breach', 'nexlifydesk'); ?></h2>
                <?php wp_editor(
                    $templates['sla_breach'],
                    'sla_breach',
                    array(
                        'textarea_name' => 'sla_breach',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'tinymce' => false,
                        'quicktags' => true,
                        'default_editor' => 'html',
                    )
                ); ?>
                <button type="button" class="button preview-email-template" data-editor="sla_breach" style="margin-top:5px;">
                    <?php esc_html_e('Preview', 'nexlifydesk'); ?>
                </button>
                <div id="preview-sla_breach" class="nexlifydesk-email-preview" style="border:1px solid #ddd; margin-top:10px; padding:10px; display:none;"></div>
                
                <p>
                    <input type="submit" name="nexlifydesk_save_email_templates" class="button button-primary" value="<?php esc_attr_e('Save Templates', 'nexlifydesk'); ?>">
                </p>
            </form>
            <div class="nexlifydesk-email-placeholders" style="margin-top:2em;">
                <h2><?php esc_html_e('Available Placeholders', 'nexlifydesk'); ?></h2>
                <ul>
                    <li><code>{ticket_id}</code> – <?php esc_html_e('Ticket ID', 'nexlifydesk'); ?></li>
                    <li><code>{subject}</code> – <?php esc_html_e('Ticket Subject', 'nexlifydesk'); ?></li>
                    <li><code>{message}</code> – <?php esc_html_e('Ticket Message', 'nexlifydesk'); ?></li>
                    <li><code>{user_name}</code> – <?php esc_html_e('User Name', 'nexlifydesk'); ?></li>
                    <li><code>{user_email}</code> – <?php esc_html_e('User Email', 'nexlifydesk'); ?></li>
                    <li><code>{status}</code> – <?php esc_html_e('Ticket Status', 'nexlifydesk'); ?></li>
                    <li><code>{priority}</code> – <?php esc_html_e('Ticket Priority', 'nexlifydesk'); ?></li>
                    <li><code>{category}</code> – <?php esc_html_e('Ticket Category', 'nexlifydesk'); ?></li>
                    <li><code>{created_at}</code> – <?php esc_html_e('Created Date/Time', 'nexlifydesk'); ?></li>
                    <li><code>{updated_at}</code> – <?php esc_html_e('Last Updated Date/Time', 'nexlifydesk'); ?></li>
                    <li><code>{reply_message}</code> – <?php esc_html_e('Reply Message (for reply emails)', 'nexlifydesk'); ?></li>
                    <li><code>{reply_user_name}</code> – <?php esc_html_e('Reply Author Name (for reply emails)', 'nexlifydesk'); ?></li>
                    <li><code>{ticket_url}</code> – <?php esc_html_e('Direct link to the ticket conversation', 'nexlifydesk'); ?></li>
                    <li><code>{ticket_admin_url}</code> – <?php esc_html_e('Direct link to the ticket in the admin area (for agents/admins)', 'nexlifydesk'); ?></li>
                </ul>
                <p><?php esc_html_e('Copy and paste these placeholders into your templates. They will be replaced with real ticket data.', 'nexlifydesk'); ?></p>
            </div>
        </div>
        <?php
    }

    private static function check_rate_limit($action, $user_id) {
        $key = "nexlifydesk_rate_limit_{$action}_{$user_id}";
        $attempts = get_transient($key);
        
        if ($attempts && $attempts > 5) {
            wp_die(esc_html__('Too many attempts. Please try again later.', 'nexlifydesk'));
        }
        
        set_transient($key, ($attempts + 1), 300); // 5 minutes
    }

    public static function get_ticket_page_url() {
        $settings = get_option('nexlifydesk_settings', array());
        $ticket_page_id = isset($settings['ticket_page_id']) ? (int)$settings['ticket_page_id'] : 0;
        
        if ($ticket_page_id > 0) {
            $url = get_permalink($ticket_page_id);
            if ($url && wp_http_validate_url($url)) {
                return $url;
            }
        }
        
        return home_url();
    }

    public static function show_data_retention_notice() {
        $settings = get_option('nexlifydesk_settings', array());
        $keep_data = isset($settings['keep_data_on_uninstall']) ? $settings['keep_data_on_uninstall'] : 1;
        
        if (!$keep_data) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('NexlifyDesk Data Warning:', 'nexlifydesk'); ?></strong> 
                    <?php esc_html_e('Data retention is currently disabled. All tickets and customer data will be permanently deleted if you uninstall the plugin.', 'nexlifydesk'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nexlifydesk_settings')); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php esc_html_e('Review Settings', 'nexlifydesk'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    public static function save_settings() {
        if (!isset($_POST['nexlifydesk_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexlifydesk_settings_nonce'])), 'nexlifydesk_save_settings')) {
            wp_die(esc_html__('Security check failed.', 'nexlifydesk'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nexlifydesk'));
        }

        $settings = get_option('nexlifydesk_settings', array());

        $settings['email_notifications'] = isset($_POST['email_notifications']) ? 1 : 0;
        $settings['default_priority'] = isset($_POST['default_priority']) ? sanitize_text_field(wp_unslash($_POST['default_priority'])) : 'medium';
        $settings['auto_assign'] = isset($_POST['auto_assign']) ? 1 : 0;
        $settings['ticket_page_id'] = isset($_POST['ticket_page_id']) ? (int)$_POST['ticket_page_id'] : 0;
        $settings['ticket_form_page_id'] = isset($_POST['ticket_form_page_id']) ? (int)$_POST['ticket_form_page_id'] : 0;

        if ($settings['ticket_page_id'] === $settings['ticket_form_page_id'] && $settings['ticket_page_id'] !== 0) {
            add_settings_error(
                'nexlifydesk_settings',
                'nexlifydesk_page_conflict',
                __('Ticket List Page and Ticket Form Page cannot be the same.', 'nexlifydesk'),
                'error'
            );
            
            wp_redirect(add_query_arg('settings-updated', 'false', wp_get_referer()));
            exit;
        }

        $settings['ticket_id_prefix'] = isset($_POST['ticket_id_prefix']) ? sanitize_text_field(wp_unslash($_POST['ticket_id_prefix'])) : 'T';
        $settings['ticket_id_start'] = isset($_POST['ticket_id_start']) ? absint($_POST['ticket_id_start']) : 1001;
        $settings['max_file_size'] = isset($_POST['max_file_size']) ? absint($_POST['max_file_size']) : 2;
        $settings['allowed_file_types'] = isset($_POST['allowed_file_types']) ? sanitize_text_field(wp_unslash($_POST['allowed_file_types'])) : 'jpg,jpeg,png,pdf';
        $settings['sla_response_time'] = isset($_POST['sla_response_time']) ? absint($_POST['sla_response_time']) : 24;
        $settings['default_category'] = isset($_POST['default_category']) ? absint($_POST['default_category']) : 1;
        
        if (isset($_POST['keep_data_on_uninstall'])) {
            $settings['keep_data_on_uninstall'] = 1;
        } else {
            $settings['keep_data_on_uninstall'] = 0;
        }

        update_option('nexlifydesk_settings', $settings);

        wp_redirect(add_query_arg(array(
            'page' => 'nexlifydesk_settings',
            'settings-updated' => 'true'
        ), admin_url('admin.php')));
        exit;
    }

    public static function get_ticket_form_page_url() {
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

add_action('admin_notices', array('NexlifyDesk_Admin', 'show_data_retention_notice'));

add_filter('wp_editor_settings', function($settings, $editor_id) {
    if (in_array($editor_id, array('new_ticket', 'new_reply', 'status_changed', 'sla_breach'))) {
        $settings['tinymce'] = false;
        $settings['quicktags'] = true;
    }
    return $settings;
}, 10, 2);

function nexlifydesk_render_order_history_page() {
    if (!function_exists('wc_get_orders')) {
        wp_die(esc_html__('WooCommerce is required for this feature.', 'nexlifydesk'));
    }

    if (!current_user_can('nexlifydesk_manage_tickets')) {
        wp_die(esc_html__('You do not have permission to view this page.', 'nexlifydesk'));
    }

    $search_order_id = '';
        if (
            isset($_GET['nexlifydesk_order_search_nonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nexlifydesk_order_search_nonce'])), 'nexlifydesk_order_search')
        ) {
            $search_order_id = (isset($_GET['order_id']) && $_GET['order_id'] !== '') ? absint($_GET['order_id']) : '';
        }

    $order = null;

    if ($search_order_id) {
        $args = array(
            'limit' => 1,
            'include' => array($search_order_id),
            'return' => 'ids',
        );
        $order_ids = wc_get_orders($args);
        if (!empty($order_ids)) {
            $order = wc_get_order($order_ids[0]);
        }
    }

    echo '<div class="wrap"><h1>' . esc_html__('Order Search', 'nexlifydesk') . '</h1>';

    echo '<form method="get" style="margin-bottom:20px;">';
    echo '<input type="hidden" name="page" value="nexlifydesk_order_history" />';
    wp_nonce_field('nexlifydesk_order_search', 'nexlifydesk_order_search_nonce');
    echo '<input type="text" name="order_id" placeholder="' . esc_attr__('Enter Order ID', 'nexlifydesk') . '" value="' . esc_attr($search_order_id) . '" style="margin-right:10px;" />';
    if (function_exists('submit_button')) {
        submit_button(__('Search', 'nexlifydesk'), 'secondary', '', false);
    } else {
        echo '<input type="submit" class="button button-secondary" value="' . esc_attr__('Search', 'nexlifydesk') . '" />';
    }
    echo ' <a href="' . esc_url(admin_url('admin.php?page=nexlifydesk_order_history')) . '" class="button">' . esc_html__('Reset', 'nexlifydesk') . '</a>';
    echo '</form>';

    if ($search_order_id && !$order) {
        echo '<p>' . esc_html__('No order found with ID #', 'nexlifydesk') . esc_html($search_order_id) . '</p>';
    } elseif ($order) {
        echo '<table class="widefat"><thead><tr>
            <th>' . esc_html__('Order', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Customer', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Email', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Phone', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Total', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Status', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Date', 'nexlifydesk') . '</th>
            <th>' . esc_html__('Items', 'nexlifydesk') . '</th>
        </tr></thead><tbody>';

        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = esc_html($item->get_name()) . ' x' . esc_html($item->get_quantity());
        }
        echo '<tr>';
        echo '<td>#' . esc_html($order->get_id()) . '</td>';
        echo '<td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>';
        echo '<td>' . esc_html($order->get_billing_email()) . '</td>';
        echo '<td>' . esc_html($order->get_billing_phone()) . '</td>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe HTML from WooCommerce
        echo '<td>' . $order->get_formatted_order_total() . '</td>'; // Safe: WooCommerce escapes this output
        echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
        echo '<td>' . esc_html($order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '') . '</td>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each item is already escaped, only <br> is output as HTML
        echo '<td>' . implode('<br>', $items) . '</td>';
        echo '</tr>';
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('Please enter an Order ID to search.', 'nexlifydesk') . '</p>';
    }

    echo '</div>';
}