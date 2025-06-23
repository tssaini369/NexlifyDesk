<?php
if (!defined('ABSPATH')) {
    exit;
}

class NexlifyDesk_Ajax {
    public static function init() {
        add_action('wp_ajax_nexlifydesk_submit_ticket', array(__CLASS__, 'submit_ticket'));
        add_action('wp_ajax_nopriv_nexlifydesk_submit_ticket', array(__CLASS__, 'submit_ticket'));
        add_action('wp_ajax_nexlifydesk_add_reply', array(__CLASS__, 'add_reply'));
        add_action('wp_ajax_nexlifydesk_update_status', array(__CLASS__, 'update_status'));
        add_action('wp_ajax_nexlifydesk_upload_attachment', array(__CLASS__, 'upload_attachment'));
        
        // Admin actions
        add_action('wp_ajax_nexlifydesk_admin_get_tickets', array(__CLASS__, 'admin_get_tickets'));
        add_action('wp_ajax_nexlifydesk_admin_assign_ticket', array(__CLASS__, 'admin_assign_ticket'));

        // Category actions
        add_action('wp_ajax_nexlifydesk_delete_category', array(__CLASS__, 'delete_category'));
        add_action('wp_ajax_nexlifydesk_add_category', array(__CLASS__, 'add_category'));
    }
    
    public static function submit_ticket() {

        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to submit a ticket.', 'nexlifydesk'));
        }
        
        if (empty($_POST['subject']) || empty($_POST['message'])) {
            wp_send_json_error(esc_html__('Subject and message are required.', 'nexlifydesk'));
        }

        $data = array(
            'subject' => sanitize_text_field(wp_unslash($_POST['subject'])),
            'message' => wp_kses_post(wp_unslash($_POST['message'])),
            'category_id' => isset($_POST['category_id']) ? absint(wp_unslash($_POST['category_id'])) : 0,
            'priority' => isset($_POST['priority']) ? sanitize_text_field(wp_unslash($_POST['priority'])) : 'medium'
        );
        
        $attachments = array();

        $attachments = array();

        if (!empty($_FILES['attachments']) && isset($_FILES['attachments']['name'])) {
            
            $file_names = array_map('sanitize_file_name', wp_unslash($_FILES['attachments']['name']));
            
            foreach ($file_names as $key => $sanitized_name) {
                if (!isset(
                    $_FILES['attachments']['type'][$key],
                    $_FILES['attachments']['tmp_name'][$key],
                    $_FILES['attachments']['error'][$key],
                    $_FILES['attachments']['size'][$key]
                )) {
                    continue;
                }

                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $attachments[] = [
                        'name' => $sanitized_name, // Already sanitized
                        'type' => sanitize_mime_type(wp_unslash($_FILES['attachments']['type'][$key])),
                        'tmp_name' => sanitize_text_field(wp_unslash($_FILES['attachments']['tmp_name'][$key])),
                        'error' => absint($_FILES['attachments']['error'][$key]),
                        'size' => absint($_FILES['attachments']['size'][$key])
                    ];
                }
            }
        }
        
        $data['attachments'] = $attachments;
        
        $ticket = NexlifyDesk_Tickets::create_ticket($data);
        
        if (is_wp_error($ticket)) {
            wp_send_json_error($ticket->get_error_message());
        } else {
            $settings = get_option('nexlifydesk_settings', array());
            $ticket_page_id = isset($settings['ticket_page_id']) ? (int)$settings['ticket_page_id'] : 0;
            
            if ($ticket_page_id > 0) {
                $ticket_page_url = get_permalink($ticket_page_id);
            } else {
                $ticket_page_url = isset($_POST['current_url']) ? esc_url_raw(wp_unslash($_POST['current_url'])) : home_url();
            }
            
            if (!$ticket_page_url || !wp_http_validate_url($ticket_page_url)) {
                $ticket_page_url = home_url();
            }
            
            $redirect_url = add_query_arg(array(
            'ticket_id' => $ticket->ticket_id,
            'ticket_submitted' => '1',
            '_wpnonce' => wp_create_nonce('ticket_view_' . $ticket->id)
        ), $ticket_page_url);
            
            $response = array(
                'message' => sprintf(
                    /* translators: %s: Ticket ID number */
                    __('Ticket #%s submitted successfully! Redirecting...', 'nexlifydesk'),
                    $ticket->ticket_id
                ),
                'redirect' => $redirect_url,
                'ticket_id' => $ticket->ticket_id,
                'ticket_number' => $ticket->ticket_id
            );

            wp_send_json_success($response);
        }
    }
    
    public static function add_reply() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to reply.', 'nexlifydesk'));
        }

        if (empty($_POST['ticket_id']) || empty($_POST['message'])) {
            wp_send_json_error(__('Missing required fields.', 'nexlifydesk'));
        }

        $ticket_id_param = sanitize_text_field(wp_unslash($_POST['ticket_id']));
        
        $ticket = NexlifyDesk_Tickets::get_ticket_by_ticket_id($ticket_id_param);
        
        if (!$ticket && is_numeric($ticket_id_param)) {
            $ticket = NexlifyDesk_Tickets::get_ticket(absint($ticket_id_param));
        }
        
        if (!$ticket) {
            wp_send_json_error(__('Ticket not found.', 'nexlifydesk'));
            return;
        }

        $data = array(
            'ticket_id' => $ticket->id,
            'message' => wp_kses_post(wp_unslash($_POST['message']))
        );

        $current_user = wp_get_current_user();
        
        if ($ticket && $ticket->status === 'closed') {
            wp_send_json_error(__('This ticket is closed. Please create a new ticket for further assistance.', 'nexlifydesk'));
        }
        
        if ($ticket && $ticket->status === 'resolved' && !current_user_can('nexlifydesk_manage_tickets')) {
            NexlifyDesk_Tickets::update_ticket_status($ticket->id, 'open');
        }

        $attachments = array();
        if (!empty($_FILES['attachments']) && 
            isset($_FILES['attachments']['name'], $_FILES['attachments']['error']) &&
            is_array($_FILES['attachments']['name'])) {
            
            $file_names = array_map('sanitize_file_name', $_FILES['attachments']['name']);
            
            foreach ($file_names as $key => $value) {
                if (isset(
                    $_FILES['attachments']['error'][$key],
                    $_FILES['attachments']['type'][$key],
                    $_FILES['attachments']['tmp_name'][$key],
                    $_FILES['attachments']['size'][$key]
                )) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $attachments[] = array(
                            'name' => $value, // Already sanitized via array_map
                            'type' => sanitize_mime_type($_FILES['attachments']['type'][$key]),
                            // No sanitization needed for tmp_name
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$key],  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                            'error' => (int) $_FILES['attachments']['error'][$key],
                            'size' => (int) $_FILES['attachments']['size'][$key]
                        );
                    }
                }
            }
        }

        $data['attachments'] = $attachments;
        $data['user_id'] = $current_user->ID;

        $reply_id = NexlifyDesk_Tickets::add_reply($data);

        if (is_wp_error($reply_id)) {
            wp_send_json_error($reply_id->get_error_message());
        } else {
            ob_start();
            $ticket = NexlifyDesk_Tickets::get_ticket($data['ticket_id']);
            $reply = null; 
            
            $replies = NexlifyDesk_Tickets::get_replies($data['ticket_id']);
            foreach ($replies as $r) {
                if ($r->id == $reply_id) {
                    $reply = $r;
                    break;
                }
            }
            
            if ($reply) {
                $reply_user = get_userdata($reply->user_id);
                include NEXLIFYDESK_PLUGIN_DIR . 'templates/frontend/partials/single-reply.php';
            }
            $reply_html = ob_get_clean();

            $response = array(
                'message' => __('Reply added successfully!', 'nexlifydesk'),
                'reply_html' => $reply_html
            );

            wp_send_json_success($response);
        }
    }
    
    public static function update_status() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to update ticket status.', 'nexlifydesk'));
        }

        if (!isset($_POST['ticket_id']) || !isset($_POST['status'])) {
            wp_send_json_error(__('Missing required fields.', 'nexlifydesk'));
        }

        $ticket_id = absint($_POST['ticket_id']);
        $status = sanitize_text_field(wp_unslash($_POST['status']));
        $current_user = wp_get_current_user();
        $ticket = NexlifyDesk_Tickets::get_ticket($ticket_id);

        if ($ticket && $ticket->status === 'closed' && !current_user_can('manage_options')) {
            wp_send_json_error(__('Only administrators can change the status of closed tickets.', 'nexlifydesk'));
        }

        $can_update_status = current_user_can('manage_options') || ($ticket && $ticket->assigned_to == $current_user->ID);

        if (!$can_update_status) {
            wp_send_json_error(__('You do not have permission to update this ticket status.', 'nexlifydesk'));
        }

        $allowed_statuses = ['open', 'pending', 'resolved', 'closed'];
        if (!in_array($status, $allowed_statuses, true)) {
            wp_send_json_error(__('Invalid ticket status.', 'nexlifydesk'));
        }

        $success = NexlifyDesk_Tickets::update_ticket_status($ticket_id, $status);

        if ($success) {
            wp_send_json_success(__('Ticket status updated!', 'nexlifydesk'));
        } else {
            wp_send_json_error(__('Could not update ticket status.', 'nexlifydesk'));
        }
    }
    
    public static function upload_attachment() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to upload files.', 'nexlifydesk'));
        }
        
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(__('No file uploaded or invalid file format.', 'nexlifydesk'));
        }
    
        $file = array(
            'name'     => isset($_FILES['file']['name']) ? sanitize_file_name($_FILES['file']['name']) : '',
            'type'     => isset($_FILES['file']['type']) ? sanitize_mime_type($_FILES['file']['type']) : '',
            'tmp_name' => isset($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            'error'    => isset($_FILES['file']['error']) ? (int)$_FILES['file']['error'] : UPLOAD_ERR_NO_FILE,
            'size'     => isset($_FILES['file']['size']) ? (int)$_FILES['file']['size'] : 0
        );
    
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error occurred.', 'nexlifydesk'));
        }
    
        if (!is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(__('Invalid file upload.', 'nexlifydesk'));
        }
    
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'pdf');
        
        if (!in_array($file_ext, $allowed_extensions, true)) {
            wp_send_json_error(__('File type not allowed. Only JPG, JPEG, PNG, and PDF files are permitted.', 'nexlifydesk'));
        }
    
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actual_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = array(
            'image/jpeg' => array('jpg', 'jpeg'),
            'image/png'  => array('png'),
            'application/pdf' => array('pdf')
        );
        
        $mime_valid = false;
        foreach ($allowed_mimes as $mime => $extensions) {
            if ($actual_mime === $mime && in_array($file_ext, $extensions, true)) {
                $mime_valid = true;
                break;
            }
        }
        
        if (!$mime_valid) {
            wp_send_json_error(__('File content does not match file extension.', 'nexlifydesk'));
        }
    
        $settings = get_option('nexlifydesk_settings', array());
        $max_size = (isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 2) * 1024 * 1024;
    
        if ($file['size'] > $max_size) {
            wp_send_json_error(
                sprintf(
                    /* translators: %d: Maximum file size in megabytes */
                    __('File size exceeds maximum limit of %dMB.', 'nexlifydesk'),
                    isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 2
                )
            );
        }
    
        $upload_overrides = array(
            'test_form' => false,
            'test_type' => true,
            'mimes' => array(
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'pdf'      => 'application/pdf'
            )
        );
    
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
        if ($uploaded_file && !isset($uploaded_file['error'])) {
            // File was successfully uploaded
            wp_send_json_success(array(
                'name' => basename($uploaded_file['file']),
                'url'  => $uploaded_file['url'],
                'type' => $file['type'],
                'size' => $file['size']
            ));
        } else {
            $error_message = isset($uploaded_file['error']) ? $uploaded_file['error'] : __('Could not upload file.', 'nexlifydesk');
            wp_send_json_error($error_message);
        }
    }
    
    public static function admin_get_tickets() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        
        if (!current_user_can('nexlifydesk_manage_tickets')) {
            wp_send_json_error(__('You do not have permission to view tickets.', 'nexlifydesk'));
        }
        
        try {
            // Get current user
            $current_user = wp_get_current_user();
            
            // Build query parameters
            $status = isset($_POST['status']) && $_POST['status'] !== 'all' ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
            $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
            
            // Cache key
            $cache_key = 'nexlifydesk_admin_tickets_' . md5($status . '_' . $search . '_' . $current_user->ID);
            $tickets = wp_cache_get($cache_key);
            
            if (false === $tickets) {
                global $wpdb;
                
                $base_query = "SELECT t.*, c.name as category_name 
                            FROM {$wpdb->prefix}nexlifydesk_tickets t 
                            LEFT JOIN {$wpdb->prefix}nexlifydesk_categories c ON t.category_id = c.id 
                            WHERE 1=1";
                
                $where_conditions = array();
                $params = array();
                
                // Status filter
                if (!empty($status)) {
                    $where_conditions[] = "t.status = %s";
                    $params[] = $status;
                }
                
                // Search filter
                if (!empty($search)) {
                    $where_conditions[] = "(t.subject LIKE %s OR t.message LIKE %s OR t.ticket_id LIKE %s)";
                    $search_term = '%' . $wpdb->esc_like($search) . '%';
                    $params[] = $search_term;
                    $params[] = $search_term;
                    $params[] = $search_term;
                }
                
                if (!current_user_can('manage_options')) {
                    if (in_array('nexlifydesk_agent', $current_user->roles)) {
                        $can_view_all = false;
                        
                        $assigned_position_slug = get_user_meta($current_user->ID, 'nexlifydesk_agent_position', true);
                        
                        if (!empty($assigned_position_slug)) {
                            $positions = get_option('nexlifydesk_agent_positions', array());
                            
                            if (isset($positions[$assigned_position_slug]) && 
                                isset($positions[$assigned_position_slug]['capabilities']) &&
                                is_array($positions[$assigned_position_slug]['capabilities'])) {
                                $can_view_all = in_array('nexlifydesk_view_all_tickets', $positions[$assigned_position_slug]['capabilities']);
                            }
                        }
                        
                        if (!$can_view_all) {
                            $where_conditions[] = "t.assigned_to = %d";
                            $params[] = $current_user->ID;
                        }
                    } else {
                        $where_conditions[] = "t.user_id = %d";
                        $params[] = $current_user->ID;
                    }
                }

                $query = $base_query;
                if (!empty($where_conditions)) {
                    $query .= " AND " . implode(" AND ", $where_conditions);
                }
                $query .= " ORDER BY t.created_at DESC";
                
                // Execute query
                if (!empty($params)) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Query is built safely with escaped table names and validated parameters
                    $tickets = $wpdb->get_results($wpdb->prepare($query, $params));
                } else {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Query is built safely with escaped table names
                    $tickets = $wpdb->get_results($query);
                }
                
                // Validate query result
                if ($tickets === null) {
                    throw new Exception('Database query failed: ' . $wpdb->last_error);
                }
                
                // Process results
                if (is_array($tickets)) {
                    foreach ($tickets as $ticket) {
                        if (isset($ticket->category_id)) {
                            $ticket->category = NexlifyDesk_Tickets::get_category($ticket->category_id);
                        }
                        if (isset($ticket->created_at)) {
                            $ticket->created_at = gmdate(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->created_at));
                        }
                        // Add assigned user display name
                        if (!empty($ticket->assigned_to)) {
                            $agent = get_userdata($ticket->assigned_to);
                            $ticket->assigned_to_display_name = $agent ? $agent->display_name : __('Unassigned', 'nexlifydesk');
                        } else {
                            $ticket->assigned_to_display_name = __('Unassigned', 'nexlifydesk');
                        }
                    }
                }
                
                wp_cache_set($cache_key, $tickets, '', 120);
            }

            wp_send_json_success($tickets);
            
        } catch (Exception $e) {

            wp_send_json_error(__('Error loading tickets. Please try again or contact support.', 'nexlifydesk'));
        }
    }
    
    public static function admin_assign_ticket() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
        
        if (
            !current_user_can('manage_options') &&
            !NexlifyDesk_Users::agent_can('nexlifydesk_assign_tickets', get_current_user_id())
        ) {
            wp_send_json_error(__('You do not have permission to assign tickets.', 'nexlifydesk'));
        }
        
        if (!isset($_POST['ticket_id']) || !isset($_POST['user_id'])) {
            wp_send_json_error(__('Missing required parameters.', 'nexlifydesk'));
        }
        
        $ticket_id = absint($_POST['ticket_id']);
        $user_id = absint($_POST['user_id']);
        
        if (!$ticket_id || !$user_id) {
            wp_send_json_error(__('Invalid ticket ID or user ID.', 'nexlifydesk'));
        }
        
        $ticket_before = NexlifyDesk_Tickets::get_ticket($ticket_id);
        
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
        $result = $wpdb->update(
            NexlifyDesk_Database::get_table('tickets'),
            array(
                'assigned_to' => $user_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $ticket_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result) {
            wp_cache_delete('nexlifydesk_ticket_' . intval($ticket_id));
            
            $current_user = wp_get_current_user();
            
            $statuses = array('all', 'open', 'pending', 'resolved', 'closed');
            foreach ($statuses as $status) {
                wp_cache_delete('nexlifydesk_admin_tickets_' . md5($status . '__' . $current_user->ID));
            }
            
            if ($ticket_before && !empty($ticket_before->assigned_to)) {
                foreach ($statuses as $status) {
                    wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($ticket_before->assigned_to) . '_' . $status);
                }
            }
            
            foreach ($statuses as $status) {
                wp_cache_delete('nexlifydesk_assigned_tickets_' . intval($user_id) . '_' . $status);
            }
            
            if ($ticket_before && !empty($ticket_before->user_id)) {
                foreach ($statuses as $status) {
                    wp_cache_delete('nexlifydesk_user_tickets_' . intval($ticket_before->user_id) . '_' . $status);
                }
            }
            
            wp_send_json_success(__('Ticket assigned successfully!', 'nexlifydesk'));
        } else {
            wp_send_json_error(__('Could not assign ticket.', 'nexlifydesk'));
        }
    }

    public static function add_category() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to add categories.', 'nexlifydesk'));
        }

        global $wpdb;

        if (empty($_POST['category_name'])) {
            wp_send_json_error(__('Category name is required.', 'nexlifydesk'));
        }

        $category_name = sanitize_text_field(wp_unslash($_POST['category_name']));
        $category_description = isset($_POST['category_description']) ? 
            sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';
        $slug = sanitize_title($category_name); 

        $table_name = NexlifyDesk_Database::get_table('categories');

        $cache_key = 'nexlifydesk_category_slug_check_' . md5($slug);
        $existing = wp_cache_get($cache_key);

        if (false === $existing) {
            $query = "SELECT id FROM " . $table_name . " WHERE slug = %s AND is_active = 1";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
            $existing = $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is safe and controlled
                $wpdb->prepare($query, $slug)
            );
            
            wp_cache_set($cache_key, $existing, '', 300);
        }

        if ($existing) {
            wp_send_json_error(__('A category with this name already exists.', 'nexlifydesk'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
        $result = $wpdb->insert(
            NexlifyDesk_Database::get_table('categories'),
            array(
                'name' => $category_name,
                'slug' => $slug,
                'description' => $category_description,
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%d')
        );

        if ($result === false) {
            wp_send_json_error(
                sprintf(
                    /* translators: %s: Database error message */
                    __('Failed to add category: %s', 'nexlifydesk'),
                    $wpdb->last_error
                )
            );
        }

        wp_send_json_success(array(
            'message' => __('Category added successfully!', 'nexlifydesk')
        ));
    }

    public static function delete_category() {
        check_ajax_referer('nexlifydesk-ajax-nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to delete categories.', 'nexlifydesk'));
        }
    
        if (!isset($_POST['category_id'])) {
            wp_send_json_error(__('Category ID is required.', 'nexlifydesk'));
        }
    
        global $wpdb;
        $category_id = absint($_POST['category_id']);
        
        // Additional validation to ensure we have a valid ID
        if (!$category_id) {
            wp_send_json_error(__('Invalid category ID.', 'nexlifydesk'));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Table name is safe and controlled
        $result = $wpdb->update(
            NexlifyDesk_Database::get_table('categories'),
            array('is_active' => 0),
            array('id' => $category_id),
            array('%d'),
            array('%d')
        );
    
        if ($result) {
            wp_cache_delete('nexlifydesk_categories');
            wp_cache_delete('nexlifydesk_category_' . intval($category_id));
            wp_send_json_success(__('Category deleted successfully!', 'nexlifydesk'));
        } else {
            wp_send_json_error(__('Could not delete category.', 'nexlifydesk'));
        }
    }
}