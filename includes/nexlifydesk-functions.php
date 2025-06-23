<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translate ticket status to localized strings
 */
function nexlifydesk_translate_status($status) {
    $statuses = array(
        'open'     => __('Open', 'nexlifydesk'),
        'pending'  => __('Pending', 'nexlifydesk'),
        'resolved' => __('Resolved', 'nexlifydesk'),
        'closed'   => __('Closed', 'nexlifydesk')
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}