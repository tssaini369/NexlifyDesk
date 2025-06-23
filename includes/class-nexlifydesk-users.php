<?php
if (!defined('ABSPATH')) {
    exit;
}

class NexlifyDesk_Users {
    public static function init() {
        add_action('show_user_profile', array(__CLASS__, 'add_agent_position_field'));
        add_action('edit_user_profile', array(__CLASS__, 'add_agent_position_field'));
        add_action('personal_options_update', array(__CLASS__, 'save_agent_position_field'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_agent_position_field'));
        add_filter('login_redirect', array(__CLASS__, 'nexlifydesk_login_redirect'), 10, 3);
        add_action('delete_user', array(__CLASS__, 'handle_agent_deletion'));
        add_action('set_user_role', array(__CLASS__, 'handle_role_change'), 10, 3);
        add_action('wp_ajax_nexlifydesk_reassign_orphaned_tickets', array(__CLASS__, 'ajax_reassign_orphaned_tickets'));
    }

    public static function activate() {
        $capabilities = array(
            'read' => true,
            'edit_posts' => true,
            'nexlifydesk_manage_tickets' => true,
            'nexlifydesk_view_all_tickets' => true,
            'nexlifydesk_assign_tickets' => true,
            'nexlifydesk_manage_categories' => true,
            'nexlifydesk_view_reports' => true,
        );

        if (!get_role('nexlifydesk_agent')) {
            add_role('nexlifydesk_agent', __('NexlifyDesk Agent', 'nexlifydesk'), $capabilities);
        } else {
            $role = get_role('nexlifydesk_agent');
            foreach ($capabilities as $cap => $grant) {
                $role->add_cap($cap);
            }
        }

        $admin = get_role('administrator');
        if ($admin) {
            foreach ($capabilities as $cap => $grant) {
                $admin->add_cap($cap);
            }
        }

        $shop_manager = get_role('shop_manager');
        if ($shop_manager) {
            foreach ($capabilities as $cap => $grant) {
                $shop_manager->add_cap($cap);
            }
        }

        if (!get_role('customer') && !get_role('member')) {
            add_role('customer', __('Customer', 'nexlifydesk'), array(
                'read' => true,
                'edit_posts' => false,
                'nexlifydesk_manage_tickets' => false,
                'nexlifydesk_view_all_tickets' => false,
                'nexlifydesk_assign_tickets' => false,
                'nexlifydesk_manage_categories' => false,
                'nexlifydesk_view_reports' => false,
            ));
        }
    }

    public static function get_current_user_tickets() {
        if (!is_user_logged_in()) return array();
        return NexlifyDesk_Tickets::get_user_tickets(get_current_user_id());
    }

    public static function add_agent_position_field($user) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $positions = get_option('nexlifydesk_agent_positions', array());
        $current_position = get_user_meta($user->ID, 'nexlifydesk_agent_position', true);
        ?>
        <h3><?php esc_html_e('NexlifyDesk Agent Position', 'nexlifydesk'); ?></h3>
            <table class="form-table">
                <?php wp_nonce_field('update_agent_position_' . $user->ID, 'nexlifydesk_agent_nonce'); ?>
                <tr>
                    <th><label for="nexlifydesk_agent_position"><?php esc_html_e('Assigned Position', 'nexlifydesk'); ?></label></th>
                    <td>
                        <select name="nexlifydesk_agent_position" id="nexlifydesk_agent_position">
                            <option value=""><?php esc_html_e('None', 'nexlifydesk'); ?></option>
                            <?php foreach ($positions as $slug => $position) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($current_position, $slug); ?>>
                                    <?php echo esc_html($position['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Assign a predefined position to this agent.', 'nexlifydesk'); ?></p>
                    </td>
                </tr>
            </table>
            <?php
    }

    public static function save_agent_position_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
    
        $user = get_userdata($user_id);
        if (!user_can($user, 'nexlifydesk_manage_tickets')) {
             return false;
        }
    
        if (!isset($_POST['nexlifydesk_agent_nonce'])) {
            return false;
        }
    
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_nonce'])), 'update_agent_position_' . $user_id)) {
            wp_die(esc_html__('Security verification failed.', 'nexlifydesk'));
        }
    
        if (isset($_POST['nexlifydesk_agent_position'])) {
            $position_slug = sanitize_text_field(wp_unslash($_POST['nexlifydesk_agent_position']));
            
            $positions = get_option('nexlifydesk_agent_positions', array());
            if (empty($position_slug) || isset($positions[$position_slug])) {
                update_user_meta($user_id, 'nexlifydesk_agent_position', $position_slug);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Redirect NexlifyDesk Agents to the plugin's admin page after login.
     *
     * @param string $redirect_to The default redirect URL.
     * @param string $request The requested URL.
     * @param object $user The user object.
     * @return string The new redirect URL.
     */

    public static function nexlifydesk_login_redirect($redirect_to, $request, $user) {
        if (!isset($user->roles) || !is_array($user->roles)) {
            return $redirect_to;
        }
        
        if (in_array('nexlifydesk_agent', $user->roles, true)) {
            $redirect_url = admin_url('admin.php?page=nexlifydesk_tickets');
            
            if (wp_http_validate_url($redirect_url)) {
                return $redirect_url;
            }
        }
        
        return $redirect_to;
    }

    /**
     * Check if an agent has a specific capability based on their assigned position.
     *
     * @param string $capability The capability slug to check.
     * @param int $user_id The user ID.
     * @return bool True if the user has the capability, false otherwise.
     */
    public static function agent_can($capability, $user_id = 0) {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        if (in_array('administrator', $user->roles, true)) {
            return true;
        }

        $assigned_position_slug = get_user_meta($user_id, 'nexlifydesk_agent_position', true);
        if (empty($assigned_position_slug)) {
            return false;
        }

        $positions = get_option('nexlifydesk_agent_positions', array());

        if (
            isset($positions[$assigned_position_slug]) &&
            isset($positions[$assigned_position_slug]['capabilities']) &&
            is_array($positions[$assigned_position_slug]['capabilities'])
        ) {
            return in_array($capability, $positions[$assigned_position_slug]['capabilities'], true);
        }

        return false;
    }

    /**
     * Handle agent deletion - reassign their tickets
     */
    public static function handle_agent_deletion($user_id) {
        $user = get_userdata($user_id);
        
        if ($user && (in_array('nexlifydesk_agent', $user->roles) || in_array('administrator', $user->roles))) {
            NexlifyDesk_Tickets::handle_orphaned_tickets($user_id);
        }
    }

    /**
     * Handle role changes - reassign tickets if agent role is removed
     */
    public static function handle_role_change($user_id, $role, $old_roles) {
        if (in_array('nexlifydesk_agent', $old_roles) && $role !== 'nexlifydesk_agent' && $role !== 'administrator') {
            NexlifyDesk_Tickets::handle_orphaned_tickets($user_id);
        }
    }

    /**
     * AJAX handler to manually reassign orphaned tickets
     */
    public static function ajax_reassign_orphaned_tickets() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'nexlifydesk'));
        }
        
        NexlifyDesk_Tickets::handle_orphaned_tickets();
        
        wp_send_json_success(__('Orphaned tickets have been reassigned.', 'nexlifydesk'));
    }
}