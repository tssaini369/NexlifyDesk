<?php
if (!defined('ABSPATH')) {
    exit;
}

class NexlifyDesk_Reports {
    
    public static function get_dashboard_stats() {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_dashboard_stats_' . gmdate('Y-m-d-H');
        $stats = wp_cache_get($cache_key);
        
        if (false === $stats) {
            $stats = array();
            
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // Total tickets
            $stats['total_tickets'] = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM %i", $wpdb->prefix . 'nexlifydesk_tickets')
            );
            
            // Active tickets (open + pending)
            $stats['active_tickets'] = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM %i WHERE status IN ('open', 'pending')", 
                $wpdb->prefix . 'nexlifydesk_tickets')
            );
            
            // Closed tickets
            $stats['closed_tickets'] = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM %i WHERE status = 'closed'", 
                $wpdb->prefix . 'nexlifydesk_tickets')
            );
            
            // Status breakdown
            $status_results = $wpdb->get_results(
                $wpdb->prepare("SELECT status, COUNT(*) as count FROM %i GROUP BY status", 
                $wpdb->prefix . 'nexlifydesk_tickets')
            );
            
            $stats['status_breakdown'] = array();
            foreach ($status_results as $row) {
                $stats['status_breakdown'][$row->status] = (int)$row->count;
            }
            
            // Priority breakdown
            $priority_results = $wpdb->get_results(
                $wpdb->prepare("SELECT priority, COUNT(*) as count FROM %i GROUP BY priority", 
                $wpdb->prefix . 'nexlifydesk_tickets')
            );
            // phpcs:enable
            
            $stats['priority_breakdown'] = array();
            foreach ($priority_results as $row) {
                $stats['priority_breakdown'][$row->priority] = (int)$row->count;
            }
            
            // Average response time
            $stats['avg_response_time'] = self::calculate_avg_response_time();
            
            // Monthly data for chart
            $stats['monthly_data'] = self::get_monthly_ticket_data();
            
            // Agent performance
            $stats['agent_performance'] = self::get_agent_performance();
            
            // Recent activity
            $stats['recent_activity'] = self::get_recent_activity();
            
            wp_cache_set($cache_key, $stats, 'nexlifydesk', HOUR_IN_SECONDS);
        }
        
        return $stats;
    }
    
    private static function calculate_avg_response_time() {
        global $wpdb;
        
        $cache_key = 'nexlifydesk_avg_response_time';
        $avg_response = wp_cache_get($cache_key);
        
        if (false === $avg_response) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $avg_seconds = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(TIMESTAMPDIFF(SECOND, t.created_at, r.created_at)) 
                    FROM %i t 
                    INNER JOIN %i r ON t.id = r.ticket_id 
                    WHERE r.is_admin_reply = 1 
                    AND r.id = (SELECT MIN(id) FROM %i WHERE ticket_id = t.id AND is_admin_reply = 1)",
                    $wpdb->prefix . 'nexlifydesk_tickets',
                    $wpdb->prefix . 'nexlifydesk_replies',
                    $wpdb->prefix . 'nexlifydesk_replies'
                )
            );
            // phpcs:enable
            if ($avg_seconds) {
                $hours = round($avg_seconds / 3600, 1);
                $avg_response = $hours . 'h';
            } else {
                $avg_response = 'N/A';
            }
            
            wp_cache_set($cache_key, $avg_response, 'nexlifydesk', 2 * HOUR_IN_SECONDS);
        }
        
        return $avg_response;
    }
    
    private static function get_monthly_ticket_data() {
        global $wpdb;
        
        $data = array();
        
        for ($i = 29; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-$i days"));
            $cache_key = 'nexlifydesk_tickets_count_' . $date;
            $count = wp_cache_get($cache_key, 'nexlifydesk');
            if (false === $count) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                $count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}nexlifydesk_tickets WHERE DATE(created_at) = %s",
                        $date
                    )
                );
                // phpcs:enable
                wp_cache_set($cache_key, $count, 'nexlifydesk', HOUR_IN_SECONDS);
            }
            
            $data[] = array(
                'date' => $date,
                'tickets' => (int)$count
            );
        }
        
        return $data;
    }
    
    private static function get_agent_performance() {
        global $wpdb;
        
        $agents = get_users(array(
            'role__in' => array('administrator', 'nexlifydesk_agent'),
            'fields' => array('ID', 'display_name')
        ));
        
        $performance = array();
        
        foreach ($agents as $agent) {
            $assigned_cache_key = 'nexlifydesk_agent_assigned_' . $agent->ID;
            $assigned = wp_cache_get($assigned_cache_key, 'nexlifydesk');
            if (false === $assigned) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $assigned = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}nexlifydesk_tickets WHERE assigned_to = %d",
                        $agent->ID
                    )
                );
                wp_cache_set($assigned_cache_key, $assigned, 'nexlifydesk', HOUR_IN_SECONDS);
            }
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $closed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}nexlifydesk_tickets WHERE assigned_to = %d AND status = 'closed'",
                    $agent->ID
                )
            );
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $replied_tickets = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.id)
                    FROM {$wpdb->prefix}nexlifydesk_tickets t
                    INNER JOIN {$wpdb->prefix}nexlifydesk_replies r ON t.id = r.ticket_id
                    WHERE t.assigned_to = %d AND r.user_id = %d AND r.is_admin_reply = 1",
                    $agent->ID,
                    $agent->ID
                )
            );

            $response_rate = $assigned > 0 ? round(($replied_tickets / $assigned) * 100) : 0;
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $avg_response = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(TIMESTAMPDIFF(SECOND, t.created_at, r.created_at)) 
                     FROM {$wpdb->prefix}nexlifydesk_tickets t 
                     INNER JOIN {$wpdb->prefix}nexlifydesk_replies r ON t.id = r.ticket_id 
                     WHERE r.user_id = %d AND r.is_admin_reply = 1",
                    $agent->ID
                )
            );
            
            $avg_response_formatted = $avg_response ? round($avg_response / 3600, 1) . 'h' : 'N/A';
            
            $performance[] = array(
                'name' => $agent->display_name,
                'assigned' => $assigned,
                'closed' => $closed,
                'response_rate' => $response_rate,
                'avg_response_time' => $avg_response_formatted
            );
        }
        
        return $performance;
    }
    
    private static function get_recent_activity() {
        global $wpdb;
        
        $activities = array();
        
        // Recent tickets
        $recent_tickets_cache_key = 'nexlifydesk_recent_tickets';
        $recent_tickets = wp_cache_get($recent_tickets_cache_key, 'nexlifydesk');
        if (false === $recent_tickets) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $recent_tickets = $wpdb->get_results(
                "SELECT id, ticket_id, subject, status, created_at 
                 FROM {$wpdb->prefix}nexlifydesk_tickets 
                 ORDER BY created_at DESC 
                 LIMIT 5"
            );
            wp_cache_set($recent_tickets_cache_key, $recent_tickets, 'nexlifydesk', HOUR_IN_SECONDS);
        }
        
        foreach ($recent_tickets as $ticket) {
            $activities[] = array(
                'type' => 'new',
                // translators: 1: Ticket ID (e.g., T1005), 2: Ticket subject
                'message' => sprintf(__('New ticket #%1$s: %2$s', 'nexlifydesk'), $ticket->ticket_id, $ticket->subject),
                'time' => human_time_diff(strtotime($ticket->created_at), current_time('timestamp')) . ' ago'
            );
        }

        
        // Recent replies
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $recent_replies = $wpdb->get_results(
            "SELECT r.*, t.ticket_id, t.subject 
             FROM {$wpdb->prefix}nexlifydesk_replies r 
             INNER JOIN {$wpdb->prefix}nexlifydesk_tickets t ON r.ticket_id = t.id 
             WHERE r.is_admin_reply = 1 
             ORDER BY r.created_at DESC 
             LIMIT 3"
        );
        
        foreach ($recent_replies as $reply) {
            $user = get_userdata($reply->user_id);
            $activities[] = array(
                'type' => 'reply',
                // translators: 1: User display name, 2: Ticket ID (e.g., T1005)
                'message' => sprintf(__('%1$s replied to ticket #%2$s', 'nexlifydesk'), $user->display_name, $reply->ticket_id),
                'time' => human_time_diff(strtotime($reply->created_at), current_time('timestamp')) . ' ago'
            );
        }
        
        // Sort by time
        usort($activities, function($a, $b) {
            return strcmp($b['time'], $a['time']);
        });
        
        return array_slice($activities, 0, 8);
    }
}