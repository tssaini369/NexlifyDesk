<?php
if (!defined('ABSPATH')) exit;

/**
 * Nexlify Support System
 * Reusable support system for all Nexlify plugins
 */

if (!function_exists('nexlify_display_support_form')) {
    function nexlify_display_support_form($plugin_name, $plugin_version) {
        $admin_email = get_option('admin_email');
        $site_url = home_url();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($plugin_name); ?> - Support</h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Need Help?</h2>
                <p>We're here to help! If you're experiencing issues with <strong><?php echo esc_html($plugin_name); ?></strong> or have questions about its features, please don't hesitate to reach out.</p>
                
                <h3>Before contacting support:</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>Check our documentation and FAQ</li>
                    <li>Verify your WordPress and plugin versions are up to date</li>
                    <li>Test with other plugins disabled to rule out conflicts</li>
                </ul>
                
                <h3>Support Information:</h3>
                <table class="form-table">
                    <tr>
                        <th>Plugin:</th>
                        <td><?php echo esc_html($plugin_name); ?> v<?php echo esc_html($plugin_version); ?></td>
                    </tr>
                    <tr>
                        <th>Website:</th>
                        <td><?php echo esc_html($site_url); ?></td>
                    </tr>
                    <tr>
                        <th>WordPress Version:</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version:</th>
                        <td><?php echo esc_html(phpversion()); ?></td>
                    </tr>
                </table>
            </div>

            <?php if (!wp_mail('', '', '')): ?>
                <div class="notice notice-warning">
                    <p><strong>Before submitting a support request:</strong> please ensure your site can send emails. If you haven't configured SMTP, some hosts may block WordPress emails. Check your spam folder if you don't receive our reply.</p>
                </div>
            <?php endif; ?>

            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Contact Support</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('nexlify_support_form', 'nexlify_support_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="support_subject">Subject <span style="color: red;">*</span></label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="support_subject" 
                                       id="support_subject" 
                                       class="regular-text" 
                                       required 
                                       placeholder="Brief description of your issue" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="support_message">Message <span style="color: red;">*</span></label>
                            </th>
                            <td>
                                <textarea name="support_message" 
                                          id="support_message" 
                                          rows="8" 
                                          cols="50" 
                                          class="large-text" 
                                          required 
                                          placeholder="Please describe your issue in detail. Include any error messages, steps to reproduce the problem, and what you expected to happen."></textarea>
                                <p class="description">Please be as detailed as possible to help us resolve your issue quickly.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Reply Email</th>
                            <td>
                                <input type="email" 
                                       value="<?php echo esc_attr($admin_email); ?>" 
                                       class="regular-text" 
                                       readonly />
                                <p class="description">We'll reply to this email address. You can change this in <a href="<?php echo esc_url(admin_url('options-general.php')); ?>">Settings → General</a>.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="submit" 
                               id="submit" 
                               class="button button-primary" 
                               value="Send Support Request" />
                    </p>
                </form>
            </div>

            <div class="notice notice-info" style="margin-bottom: 20px;">
                <p>
                    <strong>Information We Collect:</strong><br>
                    When you submit this support form, we will collect your website address, WordPress version, PHP version, plugin name and version, your admin email address, and the details you provide in your message. This information helps us diagnose and resolve your issue more efficiently. Your data will only be used for support purposes and will not be shared with third parties.
                </p>
            </div>

            <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h3>Other Ways to Get Help</h3>
                <p><strong>Email:</strong> support@nexlifylabs.com</p>
                <p><strong>Website:</strong> <a href="https://nexlifylabs.com" target="_blank">nexlifylabs.com</a></p>
                <p><strong>Response Time:</strong> We typically respond within 24 hours during business days.</p>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('nexlify_send_support_email')) {
    function nexlify_send_support_email($data) {
        $to = 'support@nexlifylabs.com';
        $subject = '[' . $data['plugin_name'] . ' Support] ' . $data['subject'];
        
        $message = "New support request received:\n\n";
        $message .= "=== CUSTOMER INFORMATION ===\n";
        $message .= "Website: " . $data['site_url'] . "\n";
        $message .= "Customer Email: " . $data['customer_email'] . "\n";
        $message .= "Submitted: " . current_time('mysql') . "\n\n";
        
        $message .= "=== PLUGIN INFORMATION ===\n";
        $message .= "Plugin: " . $data['plugin_name'] . "\n";
        $message .= "Version: " . $data['plugin_version'] . "\n";
        
        $message .= "=== SYSTEM INFORMATION ===\n";
        $message .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $message .= "PHP Version: " . phpversion() . "\n";
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown';
        $message .= "Server: " . $server_software . "\n\n";
        
        $message .= "=== SUPPORT REQUEST ===\n";
        $message .= "Subject: " . $data['subject'] . "\n\n";
        $message .= "Message:\n" . $data['message'] . "\n\n";
        
        $message .= "=== END OF REQUEST ===\n";
        $message .= "Please reply to: " . $data['customer_email'];
        
        $headers = [
            'From: ' . $data['customer_email'],
            'Reply-To: ' . $data['customer_email'],
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        return wp_mail($to, $subject, $message, $headers);
    }
}