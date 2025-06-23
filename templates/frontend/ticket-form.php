<?php
if (!defined('ABSPATH')) {
    exit;
}

$categories = NexlifyDesk_Tickets::get_categories();
$settings = get_option('nexlifydesk_settings', array());
$max_file_size = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 2;
$allowed_types = isset($settings['allowed_file_types']) ? $settings['allowed_file_types'] : 'jpg,jpeg,png,pdf,doc,docx';
?>
<div class="nexlifydesk-frontend">
    <div class="nexlifydesk-ticket-form">
        <h2><?php esc_html_e('Submit Support Ticket', 'nexlifydesk'); ?></h2>
        <?php if (is_user_logged_in()) : ?>
            <?php
            $user = wp_get_current_user();
            if (current_user_can('manage_options') || in_array('administrator', $user->roles, true)) {
                ?>
                <div class="nexlifydesk-notice">
                    <p><?php esc_html_e('Administrators should use the admin panel to manage tickets.', 'nexlifydesk'); ?></p>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=nexlifydesk_tickets')); ?>" class="nexlifydesk-button">
                        <?php esc_html_e('Go to Admin Panel', 'nexlifydesk'); ?>
                    </a></p>
                </div>
                <?php
                return;
            }
            ?>
            
            <div id="nexlifydesk-message" class="nexlifydesk-message" style="display: none;"></div>
            
            <form id="nexlifydesk-new-ticket" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="ticket-subject"><?php esc_html_e('Subject', 'nexlifydesk'); ?> <span class="required">*</span></label>
                    <input type="text" id="ticket-subject" name="subject" required 
                           placeholder="<?php esc_attr_e('Brief description of your issue', 'nexlifydesk'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="ticket-message"><?php esc_html_e('Message', 'nexlifydesk'); ?> <span class="required">*</span></label>
                    <textarea id="ticket-message" name="message" rows="6" required 
                              placeholder="<?php esc_attr_e('Please provide detailed information about your issue...', 'nexlifydesk'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ticket-category"><?php esc_html_e('Category', 'nexlifydesk'); ?> <span class="required">*</span></label>
                    <select id="ticket-category" name="category_id" required>
                        <option value=""><?php esc_html_e('Select a category...', 'nexlifydesk'); ?></option>
                        <?php if (!empty($categories) && is_array($categories)) : ?>
                            <?php foreach ($categories as $category): ?>
                                <?php if (isset($category->id) && isset($category->name)): ?>
                                    <option value="<?php echo esc_attr((int)$category->id); ?>">
                                        <?php echo esc_html(wp_strip_all_tags($category->name)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <option value="1"><?php esc_html_e('General Support', 'nexlifydesk'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="ticket-priority"><?php esc_html_e('Priority', 'nexlifydesk'); ?></label>
                    <select id="ticket-priority" name="priority">
                        <option value="low"><?php esc_html_e('Low - General inquiry', 'nexlifydesk'); ?></option>
                        <option value="medium" selected><?php esc_html_e('Medium - Standard issue', 'nexlifydesk'); ?></option>
                        <option value="high"><?php esc_html_e('High - Important issue', 'nexlifydesk'); ?></option>
                        <option value="urgent"><?php esc_html_e('Urgent - Critical issue', 'nexlifydesk'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="ticket-attachments"><?php esc_html_e('Attachments', 'nexlifydesk'); ?></label>
                    <div class="file-upload-section">
                        <input type="file" 
                               id="ticket-attachments" 
                               name="attachments[]" 
                               multiple 
                               accept=".<?php echo esc_attr(str_replace(',', ',.', $allowed_types)); ?>"
                               class="file-input">
                        <div class="file-upload-info">
                            <p class="file-help-text">
                                <?php 
                                printf(
                                    /* translators: 1: Max file size, 2: Allowed file types */
                                    esc_html__('Maximum file size: %1$sMB. Allowed types: %2$s', 'nexlifydesk'),
                                    esc_html($max_file_size),
                                    esc_html(strtoupper(str_replace(',', ', ', $allowed_types)))
                                );
                                ?>
                            </p>
                            <div id="file-list" class="file-list"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions">
                    <input type="hidden" name="action" value="nexlifydesk_submit_ticket">
                    <input type="hidden" name="current_url" value="<?php echo esc_url(get_permalink()); ?>">
                    <?php wp_nonce_field('nexlifydesk-ajax-nonce', 'nonce'); ?>
                    
                    <button type="submit" id="submit-ticket-btn" class="nexlifydesk-button primary">
                        <span class="button-text"><?php esc_html_e('Submit Ticket', 'nexlifydesk'); ?></span>
                        <span class="button-spinner" style="display: none;">⏳</span>
                    </button>
                    
                    <p class="form-note">
                        <small><?php esc_html_e('Fields marked with * are required', 'nexlifydesk'); ?></small>
                    </p>
                </div>
            </form>
        <?php else : ?>
            <div class="login-prompt">
                <h3><?php esc_html_e('Login Required', 'nexlifydesk'); ?></h3>
                <p><?php esc_html_e('Please log in to submit a support ticket.', 'nexlifydesk'); ?></p>
                <div class="login-actions">
                    <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="nexlifydesk-button primary">
                        <?php esc_html_e('Log In', 'nexlifydesk'); ?>
                    </a>
                    <?php if (get_option('users_can_register')) : ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="nexlifydesk-button secondary">
                            <?php esc_html_e('Register', 'nexlifydesk'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$ticket_submitted = isset($_GET['ticket_submitted']) ? absint($_GET['ticket_submitted']) : 0;
$ticket_number = isset($_GET['ticket_number']) ? sanitize_text_field(wp_unslash($_GET['ticket_number'])) : '';
$ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

if ($ticket_submitted === 1 
    && !empty($ticket_number)
    && $ticket_id > 0
    && !empty($nonce)
    && wp_verify_nonce($nonce, 'ticket_view_' . $ticket_id)) : 
?>
    <div class="nexlifydesk-success-message">
        <div class="success-icon">✅</div>
        <h3><?php esc_html_e('Ticket Submitted Successfully!', 'nexlifydesk'); ?></h3>
        <p><?php 
            printf(
                /* translators: 1: Ticket ID */
                esc_html__('Your request has been received! Your ticket number is %1$s.', 'nexlifydesk'),
                '<strong>#' . esc_html($ticket_number) . '</strong>'
            );
        ?></p>
        <p><?php esc_html_e('You will receive updates via email when our support team responds.', 'nexlifydesk'); ?></p>
        <div class="success-actions">
            <a href="<?php echo esc_url(add_query_arg('ticket_id', $ticket_id, remove_query_arg(array('ticket_submitted', 'ticket_number', '_wpnonce')))); ?>" 
               class="nexlifydesk-button primary">
                <?php esc_html_e('View Your Ticket', 'nexlifydesk'); ?>
            </a>
            <a href="<?php echo esc_url(remove_query_arg(array('ticket_submitted', 'ticket_id', 'ticket_number', '_wpnonce'))); ?>" 
               class="nexlifydesk-button secondary">
                <?php esc_html_e('Submit Another Ticket', 'nexlifydesk'); ?>
            </a>
        </div>
    </div>
<?php endif; ?>