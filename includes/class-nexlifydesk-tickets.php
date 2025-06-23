<?php
if (!defined('ABSPATH')) {
    exit;
}

$start = microtime(true);

class NexlifyDesk_Tickets {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'));
    }
    
    public static function register_post_types() {
        // Register custom post type for ticket attachments if needed
    }
    
    public static function create_ticket($data) {
        global $wpdb;

        $defaults = array(
            'user_id' => get_current_user_id(),
            'subject' => '',
            'message' => '',
            'category_id' => 1,
            'priority' => 'medium',
            'status' => 'open'
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['subject']) || empty($data['message'])) {
            return new WP_Error('missing_fields', __('Subject and message are required.', 'nexlifydesk'));
        }

        $ticket_id = NexlifyDesk_Database::generate_ticket_id();
        $current_time = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
        $result = $wpdb->insert(
            NexlifyDesk_Database::get_table('tickets'),
            array(
                'ticket_id' => $ticket_id,
                'user_id' => $data['user_id'],
                'category_id' => $data['category_id'],
                'subject' => sanitize_text_field($data['subject']),
                'message' => wp_kses_post($data['message']),
                'priority' => sanitize_text_field($data['priority']),
                'status' => sanitize_text_field($data['status']),
                'created_at' => $current_time,
                'updated_at' => $current_time
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            return new WP_Error('db_error', __('Could not create ticket.', 'nexlifydesk'));
        }

        $new_ticket_id = $wpdb->insert_id;
        
        $ticket = self::get_ticket($new_ticket_id);

        register_shutdown_function(function() use ($ticket) {
            NexlifyDesk_Tickets::send_notification($ticket, 'new_ticket');
        });


        $settings = get_option('nexlifydesk_settings');
        if (!empty($settings['auto_assign'])) {
            $assigned_user_id = self::get_best_assignee();
            
            if ($assigned_user_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $wpdb->update(
                    NexlifyDesk_Database::get_table('tickets'),
                    array('assigned_to' => $assigned_user_id),
                    array('id' => $new_ticket_id),
                    array('%d'),
                    array('%d')
                );
                $ticket->assigned_to = $assigned_user_id;
                
                wp_cache_delete('nexlifydesk_ticket_' . intval($new_ticket_id));
                
                $assigned_user = get_userdata($assigned_user_id);
                $user_role = in_array('nexlifydesk_agent', $assigned_user->roles) ? 'Agent' : 'Administrator';
            } 
        }

        if (!empty($data['attachments'])) {
            self::handle_attachments($data['attachments'], $ticket->id, null, $data['user_id']);
        }

        return $ticket;
    }
    
    public static function get_ticket($id) {
        global $wpdb;

        $cache_key = 'nexlifydesk_ticket_' . intval($id);
        $ticket = wp_cache_get($cache_key);

        if (false === $ticket) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $ticket = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nexlifydesk_tickets WHERE id = %d",
                    $id
                )
            );

            wp_cache_set($cache_key, $ticket, '', 300); // Cache for 5 minutes
        }

        if (!$ticket) {
            return null;
        }

        $ticket->attachments = self::get_attachments($ticket->id);
        $ticket->replies = self::get_replies($ticket->id);
        $ticket->category = self::get_category($ticket->category_id);

        return $ticket;
    }
    
    public static function get_user_tickets($user_id, $status = null) {
        global $wpdb;
    
        $cache_key = 'nexlifydesk_user_tickets_' . intval($user_id) . '_' . (string)$status;
        $tickets = wp_cache_get($cache_key);
    
        if ($tickets === false) {
            $table_name = NexlifyDesk_Database::get_table('tickets');
            $query = "SELECT * FROM `{$wpdb->prefix}nexlifydesk_tickets` WHERE user_id = %d";
            $params = array($user_id);
    
            if ($status) {
                $query .= " AND status = %s";
                $params[] = $status;
            }
    
            $query .= " ORDER BY created_at DESC";
    
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
            $tickets = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safe and controlled
                $wpdb->prepare($query, ...$params)
            );
    
            foreach ($tickets as $ticket) {
                $ticket->category = self::get_category($ticket->category_id);
            }
    
            // Cache the results for 5 minutes
            wp_cache_set($cache_key, $tickets, '', 300);
        }
    
        return $tickets;
    }

    /**
     * Get tickets assigned to a specific user.
     *
     * @param int $user_id The user ID.
     * @param string|null $status Optional. Filter by status.
     * @return array Array of ticket objects.
     */
    public static function get_assigned_tickets($user_id, $status = null) {
        global $wpdb;

        $cache_key = 'nexlifydesk_assigned_tickets_' . intval($user_id) . '_' . (string)$status;
        $tickets = wp_cache_get($cache_key);

        if ($tickets === false) {
            $query = "SELECT * FROM `{$wpdb->prefix}nexlifydesk_tickets` WHERE assigned_to = %d";
            $params = array($user_id);

            if ($status) {
                $query .= " AND status = %s";
                $params[] = $status;
            }

            $query .= " ORDER BY created_at DESC";

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
            $tickets = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->prepare($query, ...$params)
            );

            foreach ($tickets as $ticket) {
                $ticket->category = self::get_category($ticket->category_id);
            }

            wp_cache_set($cache_key, $tickets, '', 300);
        }

        return $tickets;
    }
    
    public static function add_reply($data) {
        global $wpdb;
        
        $defaults = array(
            'ticket_id' => 0,
            'user_id' => get_current_user_id(),
            'message' => '',
            'is_admin_reply' => current_user_can('manage_options')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['message'])) {
            return new WP_Error('missing_message', __('Message is required.', 'nexlifydesk'));
        }
        
        $current_time = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
        $result = $wpdb->insert(
            NexlifyDesk_Database::get_table('replies'),
            array(
                'ticket_id' => $data['ticket_id'],
                'user_id' => $data['user_id'],
                'message' => wp_kses_post($data['message']),
                'created_at' => $current_time,
                'is_admin_reply' => $data['is_admin_reply']
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Could not add reply.', 'nexlifydesk'));
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
        $wpdb->update(
        NexlifyDesk_Database::get_table('tickets'),
        array('updated_at' => $current_time),
        array('id' => $data['ticket_id']),
        array('%s'),
        array('%d')
    );

    wp_cache_delete('nexlifydesk_ticket_' . intval($data['ticket_id']));
    wp_cache_delete('nexlifydesk_user_tickets_' . intval($data['user_id']));
    wp_cache_delete('nexlifydesk_ticket_replies_' . intval($data['ticket_id']));
        
        $reply_id = $wpdb->insert_id;
        
        if (!empty($data['attachments'])) {
            self::handle_attachments($data['attachments'], $data['ticket_id'], $reply_id, $data['user_id']);
        }
        
        $ticket = self::get_ticket($data['ticket_id']);
        
        // Send notification email
        register_shutdown_function(function() use ($ticket, $reply_id) {
            NexlifyDesk_Tickets::send_notification($ticket, 'new_reply', $reply_id);
        });

        return $reply_id;
    }
    
    public static function get_replies($ticket_id) {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_ticket_replies_' . intval($ticket_id);
        $replies = wp_cache_get($cache_key);
        
        if (false === $replies) {
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
            $query = $wpdb->prepare(
                "SELECT r.*, u.display_name, u.user_email 
                FROM `{$wpdb->prefix}nexlifydesk_replies` r 
                LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
                WHERE r.ticket_id = %d
                ORDER BY r.created_at ASC",
                $ticket_id
            );
            
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query with safe table names
            $replies = $wpdb->get_results($query);
            
            foreach ($replies as $reply) {
                $reply->attachments = self::get_attachments($ticket_id, $reply->id);
            }
            
            wp_cache_set($cache_key, $replies, '', 300);
        }
        
        return $replies;
    }
    
    public static function handle_attachments($attachments, $ticket_id, $reply_id = null, $user_id = null) {
        if (empty($attachments)) return;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $upload_dir = wp_upload_dir();
        $nexlifydesk_dir = $upload_dir['basedir'] . '/nexlifydesk';
        
        if (!file_exists($nexlifydesk_dir)) {
            wp_mkdir_p($nexlifydesk_dir);
        }
        
        $settings = get_option('nexlifydesk_settings');
        $max_size = $settings['max_file_size'] * 1024 * 1024;
        $allowed_types = explode(',', $settings['allowed_file_types']);
        
        foreach ($attachments as $attachment) {
            if ($attachment['size'] > $max_size) {
                continue;
            }
            
            $file_ext = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'pdf');
            
            if (!in_array($file_ext, $allowed_extensions, true)) {
                continue;
            }
            
            if (isset($attachment['tmp_name']) && is_uploaded_file($attachment['tmp_name'])) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $actual_mime = finfo_file($finfo, $attachment['tmp_name']);
                finfo_close($finfo);
                
                $valid_mime_map = array(
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg', 
                    'png' => 'image/png',
                    'pdf' => 'application/pdf'
                );
                
                if (!isset($valid_mime_map[$file_ext]) || $actual_mime !== $valid_mime_map[$file_ext]) {
                    continue;
                }
            }
        
            $file_data = array(
                'name'     => $attachment['name'],
                'type'     => $attachment['type'],
                'tmp_name' => $attachment['tmp_name'],
                'error'    => $attachment['error'],
                'size'     => $attachment['size']
            );
        
            $upload_result = wp_handle_sideload($file_data, array('test_form' => false));
        
            if (!isset($upload_result['error'])) {
                global $wpdb;
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $wpdb->insert(
                    NexlifyDesk_Database::get_table('attachments'),
                    array(
                        'ticket_id'  => $ticket_id,
                        'reply_id'   => $reply_id,
                        'user_id'    => $user_id,
                        'file_name'  => $attachment['name'],
                        'file_path'  => $upload_result['url'],
                        'file_type'  => $attachment['type'],
                        'file_size'  => $attachment['size'],
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s')
                );
            }
        }
    }

    public static function get_attachments($ticket_id, $reply_id = null) {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_attachments_' . intval($ticket_id) . '_' . (is_null($reply_id) ? 'null' : intval($reply_id));
        $results = wp_cache_get($cache_key);
        
        if (false === $results) {
            $query = "SELECT * FROM `{$wpdb->prefix}nexlifydesk_attachments` WHERE ticket_id = %d";
            $params = array($ticket_id);
            
            if ($reply_id) {
                $query .= " AND reply_id = %d";
                $params[] = $reply_id;
            } else {
                $query .= " AND reply_id IS NULL";
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
            $results = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->prepare($query, ...$params)
            );
            
            // Cache the results for 5 minutes
            wp_cache_set($cache_key, $results, '', 300);
        }
        
        return $results;
    }
    
    public static function get_categories() {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_categories';
        $categories = wp_cache_get($cache_key);
        
        if (false === $categories) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $categories = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}nexlifydesk_categories WHERE is_active = 1 ORDER BY name ASC"
            );
            
            wp_cache_set($cache_key, $categories, '', 3600);
        }
        
        return $categories;
    }
    
    public static function get_category($category_id) {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_category_' . intval($category_id);
        $category = wp_cache_get($cache_key);
        
        if (false === $category) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $category = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nexlifydesk_categories WHERE id = %d AND is_active = 1",
                    $category_id
                )
            );
            
            // Cache the result for 1 hour (3600 seconds) since categories don't change often
            wp_cache_set($cache_key, $category, '', 3600);
        }
        
        return $category;
    }
    
    public static function update_ticket_status($ticket_id, $status) {
        global $wpdb;
        
        $allowed_statuses = array('open', 'pending', 'resolved', 'closed');
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }
        
        $ticket_before = self::get_ticket($ticket_id);
        if (!$ticket_before) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
        $result = $wpdb->update(
            NexlifyDesk_Database::get_table('tickets'),
            array(
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $ticket_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result) {
            // Invalidate all relevant caches
            wp_cache_delete('nexlifydesk_ticket_' . intval($ticket_id));
            
            // Invalidate user's ticket caches (for all statuses)
            wp_cache_delete('nexlifydesk_user_tickets_' . intval($ticket_before->user_id) . '_');
            wp_cache_delete('nexlifydesk_user_tickets_' . intval($ticket_before->user_id) . '_open');
            wp_cache_delete('nexlifydesk_user_tickets_' . intval($ticket_before->user_id) . '_pending');
            wp_cache_delete('nexlifydesk_user_tickets_' . intval($ticket_before->user_id) . '_resolved');
            wp_cache_delete('nexlifydesk_user_tickets_' . intval($ticket_before->user_id) . '_closed');
            
            // If ticket is assigned, invalidate agent's ticket caches
            if (!empty($ticket_before->assigned_to)) {
                wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($ticket_before->assigned_to) . '_');
                wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($ticket_before->assigned_to) . '_open');
                wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($ticket_before->assigned_to) . '_pending');
                wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($ticket_before->assigned_to) . '_resolved');
                wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($ticket_before->assigned_to) . '_closed');
            }
            
            // Re-get the ticket after update
            $ticket = self::get_ticket($ticket_id);

            $settings = get_option('nexlifydesk_settings');
                if (!empty($settings['status_change_notification'])) {
                    register_shutdown_function(function() use ($ticket) {
                        NexlifyDesk_Tickets::send_notification($ticket, 'status_changed');
                    });
                }

            return true;
        }
        
        return false;
    }
    
    public static function send_notification($ticket, $type, $reply_id = null) {
        $settings = get_option('nexlifydesk_settings');
        if (empty($settings['email_notifications'])) return;

        $user = get_userdata($ticket->user_id);
        $admin_email = get_option('admin_email');
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $ticket_url = add_query_arg(
            array('ticket_id' => $ticket->ticket_id),
            NexlifyDesk_Admin::get_ticket_page_url()
        );
        $ticket_admin_url = add_query_arg(
            array(
                'page' => 'nexlifydesk_tickets',
                'ticket_id' => $ticket->id,
            ),
            admin_url('admin.php')
        );

        $emailed = array();

        switch ($type) {
            case 'new_ticket':
                // translators: %s: Ticket ID.
                $subject = sprintf(__('New Support Ticket #%s', 'nexlifydesk'), $ticket->ticket_id);
                $message = self::get_email_template('new_ticket', $ticket);
                $message = str_replace(
                    array('{ticket_url}', '{ticket_admin_url}'),
                    array(esc_url($ticket_url), esc_url($ticket_admin_url)),
                    $message
                );

                // Send to customer
                if ($user && !in_array($user->user_email, $emailed)) {
                    wp_mail($user->user_email, $subject, $message, $headers);
                    $emailed[] = $user->user_email;
                }

                // Send to admin
                if (!in_array($admin_email, $emailed)) {
                    wp_mail($admin_email, '[Admin] ' . $subject, $message, $headers);
                    $emailed[] = $admin_email;
                }

                // Send to assigned agent if exists and not already emailed
                if (!empty($ticket->assigned_to)) {
                    $agent = get_userdata($ticket->assigned_to);
                    if ($agent && !in_array($agent->user_email, $emailed)) {
                        wp_mail($agent->user_email, '[Agent] ' . $subject, $message, $headers);
                        $emailed[] = $agent->user_email;
                    }
                }
                break;

            case 'new_reply':
                // translators: %s: Ticket ID.
                $subject = sprintf(__('New Reply to Ticket #%s', 'nexlifydesk'), $ticket->ticket_id);
                $message = self::get_email_template('new_reply', $ticket, $reply_id);
                $message = str_replace(
                    array('{ticket_url}', '{ticket_admin_url}'),
                    array(esc_url($ticket_url), esc_url($ticket_admin_url)),
                    $message
                );

                if ($ticket->user_id != get_current_user_id()) {
                    if ($user && !in_array($user->user_email, $emailed)) {
                        wp_mail($user->user_email, $subject, $message, $headers);
                        $emailed[] = $user->user_email;
                    }
                } else {
                    if (!empty($ticket->assigned_to)) {
                        $agent = get_userdata($ticket->assigned_to);
                        if ($agent && !in_array($agent->user_email, $emailed)) {
                            wp_mail($agent->user_email, $subject, $message, $headers);
                            $emailed[] = $agent->user_email;
                        }
                    } else {
                        if (!in_array($admin_email, $emailed)) {
                            wp_mail($admin_email, $subject, $message, $headers);
                            $emailed[] = $admin_email;
                        }
                    }
                }
                break;

            case 'status_changed':
                // translators: %s: Ticket ID.
                $subject = sprintf(__('Ticket #%s Status Changed', 'nexlifydesk'), $ticket->ticket_id);
                $message = self::get_email_template('status_changed', $ticket);
                $message = str_replace(
                    array('{ticket_url}', '{ticket_admin_url}'),
                    array(esc_url($ticket_url), esc_url($ticket_admin_url)),
                    $message
                );
                if ($user && !in_array($user->user_email, $emailed)) {
                    wp_mail($user->user_email, $subject, $message, $headers);
                    $emailed[] = $user->user_email;
                }
                break;

            case 'sla_breach':
                // translators: %s: Ticket ID.
                                $subject = sprintf(__('SLA Breach: Ticket #%s', 'nexlifydesk'), $ticket->ticket_id);
                $message = self::get_email_template('sla_breach', $ticket);
                $message = str_replace(
                    array('{ticket_url}', '{ticket_admin_url}'),
                    array(esc_url($ticket_url), esc_url($ticket_admin_url)),
                    $message
                );
                if (!in_array($admin_email, $emailed)) {
                    wp_mail($admin_email, $subject, $message, $headers);
                    $emailed[] = $admin_email;
                }
                break;
        }
    }

    private static function get_email_template($template, $ticket, $reply_id = null) {
        $templates = get_option('nexlifydesk_email_templates', array());
        $template_content = isset($templates[$template]) ? $templates[$template] : '';

        if (empty($template_content)) {
            ob_start();
            include NEXLIFYDESK_PLUGIN_DIR . 'templates/emails/' . $template . '.php';
            return ob_get_clean();
        }

        $user = get_userdata($ticket->user_id);
        $placeholders = array(
            '{ticket_id}'   => esc_html($ticket->ticket_id),
            '{subject}'     => esc_html($ticket->subject),
            '{message}'     => wp_kses_post($ticket->message),
            '{user_name}'   => $user ? esc_html($user->display_name) : '',
            '{user_email}'  => $user ? esc_html($user->user_email) : '',
            '{status}'      => esc_html(ucfirst($ticket->status)),
            '{priority}'    => esc_html(ucfirst($ticket->priority)),
            '{category}'    => esc_html(($ticket->category_id ? (NexlifyDesk_Tickets::get_category($ticket->category_id)->name ?? '') : '')),
            '{created_at}'  => esc_html(gmdate(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->created_at))),
            '{updated_at}'  => esc_html(gmdate(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->updated_at))),
            '{ticket_url}' => esc_url(
                add_query_arg(
                    array('ticket_id' => $ticket->ticket_id),
                    NexlifyDesk_Admin::get_ticket_page_url()
                )
            ),
            '{ticket_admin_url}' => esc_url(
                add_query_arg(
                    array(
                        'page' => 'nexlifydesk_tickets',
                        'ticket_id' => $ticket->ticket_id,
                    ),
                    admin_url('admin.php')
                )
            ),
        );

        if ($template === 'new_reply' && $reply_id) {
            $reply = null;
            if (method_exists('NexlifyDesk_Tickets', 'get_reply')) {
                $reply = NexlifyDesk_Tickets::get_reply($reply_id);
            }
            if (!$reply) {
                $replies = NexlifyDesk_Tickets::get_replies($ticket->id);
                $reply = end($replies);
            }
            if ($reply) {
                $placeholders['{reply_message}'] = $reply->message;
                $reply_user = get_userdata($reply->user_id);
                $placeholders['{reply_user_name}'] = $reply_user ? $reply_user->display_name : '';
            }
        }

        $content = strtr($template_content, $placeholders);

        return $content;
    }

    /**
     * Get plugin image URL
     */
    public static function get_image_url($image_name) {
        return NEXLIFYDESK_PLUGIN_URL . 'assets/images/' . $image_name;
    }

    /**
     * Display status with icon
     */
    public static function status_with_icon($status) {
        $icon_url = self::get_image_url('status/' . $status . '.png');
        return '<span class="nexlifydesk-status status-' . esc_attr($status) . '">' . 
                // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Plugin asset image, not media library image
               '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($status) . '" width="16" height="16"> ' . 
               esc_html(ucfirst($status)) . '</span>';
    }

    /**
     * Display priority with icon
     */
    public static function priority_with_icon($priority) {
        $icon_url = self::get_image_url('priority/' . $priority . '.png');
        return '<span class="nexlifydesk-priority priority-' . esc_attr($priority) . '">' . 
        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Plugin asset image, not media library image
               '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($priority) . '" width="16" height="16"> ' . 
               esc_html(ucfirst($priority)) . '</span>';
    }

    /**
     * Get the best user to assign a ticket to
     * Priority: Available agents -> Admin (only if no agents available)
     */
    public static function get_best_assignee() {
        global $wpdb;

        $agents = get_users(array(
            'role' => 'nexlifydesk_agent',
            'fields' => array('ID', 'display_name'),
            'orderby' => 'registered',
            'order' => 'ASC'
        ));

        if (!empty($agents)) {
            $least_busy_agent = null;
            $min_tickets = null;

            foreach ($agents as $agent) {
                $user = get_userdata($agent->ID);
                if (!$user || !in_array('nexlifydesk_agent', $user->roles)) {
                    continue;
                }

                $cache_key = 'nexlifydesk_agent_ticket_count_' . $agent->ID;
                $count = wp_cache_get($cache_key);

                if ($count === false) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                    $count = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}nexlifydesk_tickets WHERE assigned_to = %d AND status IN ('open', 'pending')",
                            $agent->ID
                        )
                    );
                    wp_cache_set($cache_key, $count, '', 300);
                }

                if ($min_tickets === null || $count < $min_tickets) {
                    $min_tickets = $count;
                    $least_busy_agent = $agent->ID;
                }
            }

            if ($least_busy_agent) {
                return $least_busy_agent;
            }
            return null;
        }

        $admins = get_users(array(
            'role' => 'administrator',
            'fields' => array('ID', 'display_name')
        ));

        if (!empty($admins)) {
            $least_busy_admin = null;
            $min_admin_tickets = null;

            foreach ($admins as $admin) {
                $cache_key = 'nexlifydesk_admin_ticket_count_' . $admin->ID;
                $count = wp_cache_get($cache_key);

                if ($count === false) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                    $count = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}nexlifydesk_tickets WHERE assigned_to = %d AND status IN ('open', 'pending')",
                            $admin->ID
                        )
                    );
                    wp_cache_set($cache_key, $count, '', 300);
                }

                if ($min_admin_tickets === null || $count < $min_admin_tickets) {
                    $min_admin_tickets = $count;
                    $least_busy_admin = $admin->ID;
                }
            }

            if ($least_busy_admin) {
                return $least_busy_admin;
            }
        }

        return null;
    }

    /**
     * Reassign orphaned tickets when agents are deleted or deactivated
     */
    public static function handle_orphaned_tickets($user_id = null) {
        global $wpdb;
        
        if ($user_id) {
            $cache_key = 'nexlifydesk_orphaned_tickets_user_' . intval($user_id);
            $tickets = wp_cache_get($cache_key);
            
            if (false === $tickets) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $tickets = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, assigned_to FROM {$wpdb->prefix}nexlifydesk_tickets WHERE status IN ('open', 'pending') AND assigned_to = %d",
                        $user_id
                    )
                );
                
                wp_cache_set($cache_key, $tickets, '', 300);
            }
        } else {
            $cache_key = 'nexlifydesk_orphaned_tickets_all';
            $tickets = wp_cache_get($cache_key);
            
            if (false === $tickets) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $tickets = $wpdb->get_results(
                    "SELECT id, assigned_to FROM {$wpdb->prefix}nexlifydesk_tickets WHERE status IN ('open', 'pending') AND assigned_to IS NOT NULL"
                );
                
                wp_cache_set($cache_key, $tickets, '', 300);
            }
        }
        
        foreach ($tickets as $ticket) {
            $assigned_user = get_userdata($ticket->assigned_to);
            
            $needs_reassignment = false;
            
            if (!$assigned_user) {
                $needs_reassignment = true;
            } elseif (!in_array('nexlifydesk_agent', $assigned_user->roles) && 
                    !in_array('administrator', $assigned_user->roles)) {
                $needs_reassignment = true;
            }
            
            if ($needs_reassignment) {
                $new_assignee = self::get_best_assignee();
                
                if ($new_assignee && $new_assignee != $ticket->assigned_to) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                    $wpdb->update(
                        NexlifyDesk_Database::get_table('tickets'),
                        array('assigned_to' => $new_assignee, 'updated_at' => current_time('mysql')),
                        array('id' => $ticket->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                    
                    $new_user = get_userdata($new_assignee);
                    $assignee_type = in_array('nexlifydesk_agent', $new_user->roles) ? 'agent' : 'administrator';
                    
                    $system_note = array(
                        'ticket_id' => $ticket->id,
                        'user_id' => 0, // System user
                        'message' => sprintf(
                            /* translators: 1: New assignee display name, 2: Assignee type (agent or administrator) */
                            __('Ticket automatically reassigned to %1$s (%2$s) due to previous agent being unavailable.', 'nexlifydesk'),
                            $new_user->display_name,
                            $assignee_type
                        ),
                        'is_admin_reply' => 1
                    );
                    self::add_reply($system_note);
                    
                    // Clear caches
                    wp_cache_delete('nexlifydesk_ticket_' . $ticket->id);
                }
            }
        }
    }

    /**
     * Check if there are any active agents available
     * 
     * @return bool True if agents are available, false otherwise
     */
    public static function has_available_agents() {
        $agents = get_users(array(
            'role' => 'nexlifydesk_agent',
            'fields' => array('ID'),
            'number' => 1 // We only need to know if at least one exists
        ));
        
        foreach ($agents as $agent) {
            $user = get_userdata($agent->ID);
            if ($user && in_array('nexlifydesk_agent', $user->roles)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get assignment statistics for debugging
     * 
     * @return array Statistics about current assignments
     */
    public static function get_assignment_stats() {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_agent_stats';
        $agent_stats = wp_cache_get($cache_key);
        
        if (false === $agent_stats) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Complex join query with custom table, cached separately
            $agent_stats = $wpdb->get_results(
                "SELECT u.ID, u.display_name, COUNT(t.id) as ticket_count 
                 FROM {$wpdb->users} u
                 LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
                 LEFT JOIN {$wpdb->prefix}nexlifydesk_tickets t ON u.ID = t.assigned_to AND t.status IN ('open', 'pending')
                 WHERE um.meta_key = 'wp_capabilities' 
                 AND um.meta_value LIKE '%nexlifydesk_agent%'
                 GROUP BY u.ID"
            );
            
            wp_cache_set($cache_key, $agent_stats, '', 300);
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Complex join query with custom table, cannot use WordPress built-in functions
        $admin_stats = $wpdb->get_results(
            "SELECT u.ID, u.display_name, COUNT(t.id) as ticket_count 
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
             LEFT JOIN {$wpdb->prefix}nexlifydesk_tickets t ON u.ID = t.assigned_to AND t.status IN ('open', 'pending')
             WHERE um.meta_key = 'wp_capabilities' 
             AND um.meta_value LIKE '%administrator%'
             AND um.meta_value NOT LIKE '%nexlifydesk_agent%'
             GROUP BY u.ID"
        );
        
        return array(
            'agents' => $agent_stats,
            'admins' => $admin_stats,
            'has_agents' => self::has_available_agents()
        );
    }

    public static function get_ticket_by_ticket_id($ticket_id) {
        global $wpdb;
        $cache_key = 'nexlifydesk_ticket_by_ticket_id_' . sanitize_key($ticket_id);
        $ticket = wp_cache_get($cache_key);

        if (false === $ticket) {
            $table_name = NexlifyDesk_Database::get_table('tickets');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
            $ticket = $wpdb->get_row(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared $table_name is generated by (NexlifyDesk_Database::get_table('tickets')), not user input
                    "SELECT * FROM {$table_name} WHERE ticket_id = %s LIMIT 1", 
                    $ticket_id
                )
            );

            if (!$ticket && is_numeric($ticket_id)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $ticket = $wpdb->get_row(
                    $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared $table_name is generated by (NexlifyDesk_Database::get_table('tickets')), not user input
                        "SELECT * FROM {$table_name} WHERE ticket_id = %s LIMIT 1", 
                        'T' . $ticket_id
                    )
                );
            }

            if (!$ticket && is_numeric($ticket_id)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $ticket = $wpdb->get_row(
                    $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared $table_name is generated by (NexlifyDesk_Database::get_table('tickets')), not user input
                        "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", 
                        absint($ticket_id)
                    )
                );
            }

            if (!$ticket) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query
                $existing_ids = $wpdb->get_col("SELECT ticket_id FROM {$table_name} LIMIT 10");
            }

            if ($ticket) {
                $ticket->attachments = self::get_attachments($ticket->id);
                $ticket->replies = self::get_replies($ticket->id);
                $ticket->category = self::get_category($ticket->category_id);
            }

            wp_cache_set($cache_key, $ticket, '', 300);
        }

        return $ticket;
    }
    
}