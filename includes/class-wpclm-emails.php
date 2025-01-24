<?php
/**
 * Emails Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Emails {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
    * Constructor
    */
    private function __construct() {
    // Customize WordPress's default new user email
        add_filter('wp_new_user_notification_email', array($this, 'customize_new_user_email'), 10, 3);

    // Set email content type to HTML
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

    // Override password reset email
        add_filter('retrieve_password_message', array($this, 'custom_reset_password_notification_email'), 10, 4);
        add_filter('retrieve_password_title', function($title) {
            return sprintf(__('[%s] Password Reset Request', 'wp-custom-login-manager'), 
                wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
        });

    // Remove default password change notification
        add_filter('send_password_change_email', '__return_false');
    }

/**
 * Customize the WordPress new user notification email
 */
public function customize_new_user_email($wp_new_user_notification_email, $user, $blogname) {
    $template = get_option('wpclm_confirmation_email_template');
    if (empty($template)) {
        $template = $this->get_default_confirmation_email();
    }

    $auth = WPCLM_Auth::get_instance();
$token = $auth->generate_confirmation_token();

if (defined('WP_DEBUG') && WP_DEBUG === true) {
    error_log("[WPCLM Email Debug] Generated token for user: {$user->user_email}");
}

// Store registration data in transient
$registration_data = array(
    'email' => $user->user_email,
    'first_name' => $user->first_name,
    'last_name' => $user->last_name
);

// Decode token to get the internal token for transient key
$token_data = json_decode(base64_decode($token), true);
if (!$token_data || !isset($token_data['token'])) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log("[WPCLM Email Debug] Failed to decode token for transient");
    }
    return $wp_new_user_notification_email;
}

$transient_key = 'wpclm_registration_' . $token_data['token'];
set_transient($transient_key, $registration_data, DAY_IN_SECONDS);

if (defined('WP_DEBUG') && WP_DEBUG === true) {
    error_log("[WPCLM Email Debug] Stored registration data in transient: $transient_key");
}

$login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
$confirmation_link = add_query_arg(array(
    'action' => 'confirm',
    'key' => $token
), $login_url);

    $replacements = array(
        '{site_name}' => $blogname,
        '{first_name}' => $user->first_name ?: $user->user_login,
        '{confirmation_link}' => sprintf(
            '<a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">%s</a>',
            esc_url($confirmation_link),
            __('Confirm Email Address', 'wp-custom-login-manager')
        )
    );

    $message = str_replace(array_keys($replacements), array_values($replacements), $template);
    $message = wpautop($message);
    $message = $this->get_email_template($message);

    $wp_new_user_notification_email['subject'] = sprintf(__('[%s] Confirm your registration', 'wp-custom-login-manager'), $blogname);
    $wp_new_user_notification_email['headers'] = array('Content-Type: text/html; charset=UTF-8');
    $wp_new_user_notification_email['message'] = $message;

    return $wp_new_user_notification_email;
}

    /**
    * Customize password reset notification email
    */
    public function custom_reset_password_notification_email($message, $key, $user_login, $user_data) {
        $template = get_option('wpclm_reset_email_template');
    
        if (empty($template)) {
            $template = $this->get_default_reset_email();
        }
    
        $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

    $replacements = array(
        '{site_name}' => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
        '{first_name}' => $user_data->first_name ?: __('there', 'wp-custom-login-manager'),
        '{reset_link}' => sprintf(
            '<a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">%s</a>',
            esc_url($reset_link),
            __('Reset Password', 'wp-custom-login-manager')
        )
    );

    $message = str_replace(array_keys($replacements), array_values($replacements), $template);
    $message = wpautop($message); // Format the message with proper paragraphs
    $message = $this->get_email_template($message); // Wrap in HTML template

    return $message;
}

    /**
    * Send reset password email
    *
    * @param WP_User $user_data User object
    * @param string $key Reset key
    * @return boolean Success status
    */
    public function send_reset_password_email($user_data, $key) {
        $template = get_option('wpclm_reset_email_template', $this->get_default_reset_email());
        
        $reset_link = add_query_arg(array(
            'action' => 'resetpass',
            'key' => $key,
            'login' => rawurlencode($user_data->user_login)
        ), home_url(get_option('wpclm_login_url', '/account-login/')));

        $replacements = array(
            '{site_name}' => get_bloginfo('name'),
            '{first_name}' => $user_data->first_name ?: $user_data->display_name,
            '{reset_link}' => sprintf(
                '<a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">%s</a>',
                esc_url($reset_link),
                __('Reset Password', 'wp-custom-login-manager')
            )
        );

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        $message = wpautop($message);
        
        return wp_mail(
            $user_data->user_email,
            sprintf(__('Password Reset Request for %s', 'wp-custom-login-manager'), get_bloginfo('name')),
            $this->get_email_template($message),
            array('Content-Type: text/html; charset=UTF-8')
        );
    }

    /**
     * Set HTML content type for emails
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
    * Get default confirmation email template
    */
    private function get_default_confirmation_email() {
        return __(
            '<!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{site_name} - Email Confirmation</title>
            </head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f5f5f5;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #ffffff; padding: 40px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: ' . esc_attr($heading_color) . '; margin-top: 0;">Welcome to {site_name}!</h2>
            <p>Hello {first_name},</p>
            <p>Thank you for registering at {site_name}. Please click the button below to confirm your email address:</p>
            <div style="text-align: center; margin: 30px 0;">
            {confirmation_link}
            </div>
            <p style="color: #666666;">If the button above doesn\'t work, you can copy and paste this link into your browser:</p>
            <p style="background: #f5f5f5; padding: 10px; border-radius: 3px; word-break: break-all;">
            {confirmation_link_raw}
            </p>
            <p style="color: #666666;">If you didn\'t create an account, you can safely ignore this email.</p>
            </div>
            </div>
            </body>
            </html>',
            'wp-custom-login-manager'
        );
    }

       /**
     * Get default reset email template
     */
    private function get_default_reset_email() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_name} - Password Reset</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #333333; margin: 0; font-size: 24px; font-weight: normal;">Password Reset Request</h1>
            </div>

            <div style="color: #555555; font-size: 16px;">
                <p>Hello {first_name},</p>
                
                <p>Someone has requested a password reset for your account at {site_name}. If this was you, click the button below to set a new password:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    {reset_link}
                </div>
                
                <p style="color: #666666; font-size: 14px;">If you didn\'t request a password reset, you can safely ignore this email.</p>
                
                <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                
                <p style="color: #666666; font-size: 12px; margin-bottom: 0; text-align: center;">
                    This password reset link will expire in 24 hours.<br>
                    This is an automated email from {site_name}. Please do not reply to this email.
                </p>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
    * Send confirmation email
    */
    public function send_confirmation_email($email, $first_name, $activation_key) {
        // Get template from settings
        $template = get_option('wpclm_confirmation_email_template');

        // Wrap template content with HTML styling
        if (!empty($template)) {
            // Split content by newlines and wrap each line in paragraph tags with proper styling
            $lines = explode("\n", $template);
            $formatted_content = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    // Skip wrapping if line already contains the confirmation link
                    if (strpos($line, '{confirmation_link}') !== false) {
                        $formatted_content .= '<div style="text-align: center; margin: 25px 0;">' . $line . '</div>';
                    } else {
                        $formatted_content .= '<p style="margin: 16px 0; color: #333333; font-size: 16px;">' . $line . '</p>';
                    }
                }
            }

            // Get logo URL and width from settings
            $logo_url = get_option('wpclm_logo');
            $logo_width = get_option('wpclm_logo_width', '100');

            // Prepare logo HTML if logo exists
            $logo_html = '';
            if (!empty($logo_url)) {
                $logo_html = sprintf(
                    '<div style="text-align: center; margin-bottom: 30px;">
                    <img src="%s" alt="%s" style="width: %spx; height: auto; max-width: 100%%;">
                    </div>',
                    esc_url($logo_url),
                    esc_attr(get_bloginfo('name')),
                    esc_attr($logo_width)
                );
            }

            // Get color settings
            $button_background_color = get_option('wpclm_button_background_color', '#2271B1');
            $button_text_color = get_option('wpclm_button_text_color', '#FFFFFF');
            $link_color = get_option('wpclm_link_color', '#2271B1');
            $email_background_color = get_option('wpclm_email_background_color', '#F5F5F5');
            $heading_color = get_option('wpclm_heading_color', '#1D2327');

            $template = '<!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . get_bloginfo('name') . ' - Email Confirmation</title>
            </head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; background-color: ' . esc_attr($email_background_color) . ';">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #ffffff; padding: 40px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            ' . $logo_html . '
            <div style="color: ' . esc_attr($heading_color) . ';">' . 
            $formatted_content . 
            '</div>
            </div>
            </div>
            </body>
            </html>';
        }

        // If no template in settings, get default from file
        if (empty($template)) {
            $template = $this->get_default_template('confirmation-email');
        }

        // If still empty, use basic fallback template
        if (empty($template)) {
            $template = $this->get_default_confirmation_email();
        }

        // Get the full site URL
        $site_url = site_url();

        // Build the confirmation URL
        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
        $confirmation_link = add_query_arg(array(
            'action' => 'confirm',
            'key' => $activation_key
        ), $login_url);

        // Ensure we have a full URL
        if (strpos($confirmation_link, 'http') !== 0) {
            $confirmation_link = $site_url . $confirmation_link;
        }

        // Create button HTML
        $button_html = sprintf(
            '<a href="%s" style="display: inline-block; padding: 15px 25px; background-color: %s; color: %s !important; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 16px; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">%s</a>',
            esc_url($confirmation_link),
            esc_attr($button_background_color),
            esc_attr($button_text_color),
            __('Confirm Email Address', 'wp-custom-login-manager')
        );

        // Create plain link container
        $plain_link_container = '<div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0;">
        <code style="word-break: break-all; font-family: monospace; font-size: 14px; color: #444444;">' 
        . esc_url($confirmation_link) . 
        '</code>
        </div>';

        // Replace placeholders
        $replacements = array(
            '{site_name}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            '{first_name}' => $first_name,
            '{confirmation_link}' => $button_html,
            '{confirmation_link_raw}' => esc_url($confirmation_link),
            '{confirmation_link_plain}' => $plain_link_container
        );

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        return wp_mail(
            $email,
            sprintf(__('Confirm Registration | %s', 'wp-custom-login-manager'), 
                wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)),
            $message,
            $headers
        );
    }

    /**
     * Get email template
     */
    public function get_email_template($content) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    line-height: 1.6;
                    margin: 0;
                    padding: 0;
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                }
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .email-content {
                    background-color: #ffffff;
                    padding: 40px;
                    border-radius: 4px;
                }
                .button {
                    display: inline-block;
                    padding: 12px 24px;
                    background-color: #0073aa;
                    color: #ffffff !important;
                    text-decoration: none;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                @media only screen and (max-width: 480px) {
                    .email-content {
                        padding: 20px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send email
     */
    public function send_email($to, $subject, $content) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail(
            $to,
            $subject,
            $this->get_email_template($content),
            $headers
        );
    }
}

// Initialize Emails
WPCLM_Emails::get_instance();