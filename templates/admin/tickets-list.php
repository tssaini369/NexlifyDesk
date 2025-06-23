<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('nexlifydesk_manage_tickets')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'nexlifydesk'));
}
?>

<div class="wrap nexlifydesk-tickets-page">

    <!-- Filters -->
    <div class="nexlifydesk-filters" style="margin: 20px 0;">
        <label for="ticket-status-filter"><?php esc_html_e('Status:', 'nexlifydesk'); ?></label>
        <select id="ticket-status-filter">
            <option value="all"><?php esc_html_e('All Statuses', 'nexlifydesk'); ?></option>
            <option value="open"><?php esc_html_e('Open', 'nexlifydesk'); ?></option>
            <option value="pending"><?php esc_html_e('Pending', 'nexlifydesk'); ?></option>
            <option value="resolved"><?php esc_html_e('Resolved', 'nexlifydesk'); ?></option>
            <option value="closed"><?php esc_html_e('Closed', 'nexlifydesk'); ?></option>
        </select>

        <label for="ticket-search" style="margin-left: 20px;"><?php esc_html_e('Search:', 'nexlifydesk'); ?></label>
        <input type="text" id="ticket-search" placeholder="<?php esc_attr_e('Search tickets...', 'nexlifydesk'); ?>">
        
        <button id="ticket-filter-btn" class="button"><?php esc_html_e('Filter', 'nexlifydesk'); ?></button>
    </div>

    <!-- Tickets Container -->
    <div id="nexlifydesk-tickets-list">
        <p><?php esc_html_e('Loading tickets...', 'nexlifydesk'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load tickets on page load
    loadTickets();
    
    // Filter button click
    $('#ticket-filter-btn').on('click', function() {
        var status = $('#ticket-status-filter').val();
        var search = $('#ticket-search').val();
        loadTickets(status, search);
    });
    
    // Enter key in search
    $('#ticket-search').on('keypress', function(e) {
        if (e.which == 13) {
            $('#ticket-filter-btn').click();
        }
    });

    function loadTickets(status, search) {
        status = status || 'all';
        search = search || '';
        
        $('#nexlifydesk-tickets-list').html('<p><?php esc_html_e("Loading tickets...", "nexlifydesk"); ?></p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'nexlifydesk_admin_get_tickets',
                status: status,
                search: search,
                nonce: '<?php echo esc_attr( wp_create_nonce("nexlifydesk-ajax-nonce") ); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayTickets(response.data);
                } else {
                    $('#nexlifydesk-tickets-list').html('<p><?php esc_html_e("No tickets found.", "nexlifydesk"); ?></p>');
                }
            },
            error: function() {
                $('#nexlifydesk-tickets-list').html('<p style="color: red;"><?php esc_html_e("Error loading tickets.", "nexlifydesk"); ?></p>');
            }
        });
    }

    function displayTickets(tickets) {
        if (!tickets || tickets.length === 0) {
            $('#nexlifydesk-tickets-list').html('<p><?php esc_html_e("No tickets found.", "nexlifydesk"); ?></p>');
            return;
        }

        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th><?php esc_html_e("Ticket ID", "nexlifydesk"); ?></th>';
        html += '<th><?php esc_html_e("Subject", "nexlifydesk"); ?></th>';
        html += '<th><?php esc_html_e("Status", "nexlifydesk"); ?></th>';
        html += '<th><?php esc_html_e("Assigned To", "nexlifydesk"); ?></th>';
        html += '<th><?php esc_html_e("Created", "nexlifydesk"); ?></th>';
        html += '<th><?php esc_html_e("Actions", "nexlifydesk"); ?></th>';
        html += '</tr></thead><tbody>';

        $.each(tickets, function(index, ticket) {
            html += '<tr>';
            html += '<td>' + escapeHtml(ticket.ticket_id) + '</td>';
            html += '<td>' + nl2br_js(escapeHtml(ticket.subject)) + '</td>';
            html += '<td><span class="status-' + escapeHtml(ticket.status) + '">' + escapeHtml(ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1)) + '</span></td>';
            html += '<td>' + escapeHtml(ticket.assigned_to_display_name) + '</td>';
            html += '<td>' + escapeHtml(ticket.created_at) + '</td>';
            html += '<td><a href="?page=nexlifydesk_tickets&ticket_id=' + ticket.id + '" class="button button-small"><?php esc_html_e("View", "nexlifydesk"); ?></a></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#nexlifydesk-tickets-list').html(html);
    }

    function nl2br_js(str) {
        if (!str) return '';
        return str.replace(/\n/g, '<br>');
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>