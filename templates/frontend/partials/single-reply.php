<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($reply) || !is_object($reply)) {
    return;
}
if (!isset($reply_user)) {
    $reply_user = get_userdata($reply->user_id);
}
?>
<?php
$is_admin = in_array('administrator', $reply_user->roles);
$is_agent = in_array('nexlifydesk_agent', $reply_user->roles);
$reply_class = 'nexlifydesk-reply';
if ($is_admin) {
    $reply_class .= ' reply-admin';
} elseif ($is_agent) {
    $reply_class .= ' reply-agent';
} else {
    $reply_class .= ' reply-customer';
}
?>
<div class="<?php echo esc_attr($reply_class); ?>">
    <div class="reply-meta">
        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($reply->created_at))); ?>
        -
        <strong><?php echo esc_html($reply_user ? $reply_user->display_name : __('Unknown', 'nexlifydesk')); ?></strong>
        <?php if ($is_admin): ?>
            <span class="reply-role">(Admin)</span>
        <?php elseif ($is_agent): ?>
            <span class="reply-role">(Agent)</span>
        <?php endif; ?>
    </div>
    <div class="reply-message">
        <?php echo nl2br(esc_html($reply->message)); ?>
    </div>
</div>