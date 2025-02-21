<?php
/**
 * Email Verification Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Email_Verifier {
    /**
     * Cache group name
     */
    private $cache_group = 'wpclm_email_verification';

    /**
     * Cache expiration in seconds (1 hour)
     */
    private $cache_expiration = 3600;

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
        $this->init_ajax_hooks();
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $data
     */
    private function log_debug($message, $data = array()) {
        if (class_exists('WPCLM_Debug')) {
            WPCLM_Debug::log_message($message, $data, 'email_verify');
        }
    }

    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks() {
        add_action('wp_ajax_wpclm_verify_email', array($this, 'ajax_verify_email'));
        add_action('wp_ajax_nopriv_wpclm_verify_email', array($this, 'ajax_verify_email'));
    }

    /**
     * Verify email using Reoon API
     *
     * @param string $email Email to verify
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public function verify_email($email) {
        if (!get_option('wpclm_email_verification_enabled')) {
            return true;
        }

        $this->log_debug('Starting email verification', array(
            'email' => $email,
            'mode' => get_option('wpclm_reoon_verification_mode', 'quick')
        ));

        // Check cache first
        $cached_result = wp_cache_get(md5($email), $this->cache_group);
        if (false !== $cached_result) {
            $this->log_debug('Using cached result', array(
                'email' => $email,
                'result' => $cached_result
            ));
            if ($cached_result === 'valid') {
                return true;
            }
            return new WP_Error('invalid_email', $this->format_error_message(
                __('This email address appears to be invalid. Please check and try again.', 'wp-custom-login-manager')
            ));
        }

        $api_key = get_option('wpclm_reoon_api_key');
        if (empty($api_key)) {
            $this->log_debug('API key not configured', array(
                'email' => $email
            ));
            return new WP_Error('no_api_key', __('Reoon API key is not configured.', 'wp-custom-login-manager'));
        }

        $mode = get_option('wpclm_reoon_verification_mode', 'quick');
        $url = add_query_arg(
            array(
                'email' => urlencode($email),
                'key' => urlencode($api_key),
                'mode' => urlencode($mode)
            ),
            'https://emailverifier.reoon.com/api/v1/verify'
        );

        $args = array(
            'timeout' => 80,  // Increase timeout to 80 seconds
            'httpversion' => '2.0',
            'sslverify' => true
        );

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $this->log_debug('API request failed', array(
                'error' => $response->get_error_message()
            ));
            return new WP_Error('api_error', __('Email verification service error. Please try again later.', 'wp-custom-login-manager'));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->log_debug('Unexpected API status code', array(
                'status_code' => $status_code
            ));
            return new WP_Error('api_error', __('Email verification service error. Please try again later.', 'wp-custom-login-manager'));
        }

        $body = wp_remote_retrieve_body($response);
        $this->log_debug('API response received', array(
            'body' => $body
        ));
        
        $result = json_decode($body);
        if (!$result || !isset($result->status)) {
            $this->log_debug('Invalid API response format', array(
                'body' => $body
            ));
            return new WP_Error('invalid_response', __('Invalid API response. Please try again later.', 'wp-custom-login-manager'));
        }

       // Handle response based on verification mode
        if ($mode === 'quick') {
            $is_valid = $result->status === 'valid';
        } else {
            // Power mode has additional checks
            $is_valid = $result->status === 'safe' && 
                        $result->is_safe_to_send && 
                        $result->is_deliverable && 
                        $result->overall_score >= 80; // Minimum acceptable score
        }

        // Cache the result
        wp_cache_set(md5($email), $is_valid ? 'valid' : 'invalid', $this->cache_group, $this->cache_expiration);

        if (!$is_valid) {
            $error_message = $this->get_error_message($result);
            $this->log_debug('Email validation failed', array(
                'email' => $email,
                'error' => $error_message,
                'result' => $result
            ));
            return new WP_Error('invalid_email', $error_message);
        }

        $this->log_debug('Email validation successful', array(
            'email' => $email,
            'result' => $result
        ));
        return true;
    }

    /**
     * Formats an error message with a contact link
     *
     * @param string $message The base error message
     * @return string Formatted message with contact link
     */
    private function format_error_message($message) {
        $contact_url = get_option('wpclm_contact_url', '/contact/');
        return sprintf(
            '%s %s',
            $message,
            sprintf(
                __('If you need help creating an account, %s.', 'wp-custom-login-manager'),
                '<a href="' . esc_url($contact_url) . '" class="wpclm-contact-link" target="_blank">' . __('contact us', 'wp-custom-login-manager') . '</a>'
            )
        );
    }

    /**
     * Get appropriate error message based on API response
     */
    private function get_error_message($result) {
        $mode = get_option('wpclm_reoon_verification_mode', 'quick');
        
        // Common checks for both modes
        if ($result->is_disposable) {
            return $this->format_error_message(
                __('Please use a permanent email address. Temporary or disposable email addresses are not allowed.', 'wp-custom-login-manager')
            );
        }
        if (!get_option('wpclm_allow_role_emails', false) && isset($result->is_role_account) && $result->is_role_account) {
            return $this->format_error_message(
                __('Please use a personal email address. Role-based email addresses (like info@, admin@, etc.) are not allowed.', 'wp-custom-login-manager')
            );
        }
        if (!$result->mx_accepts_mail || $result->mx_records === null) {
            return $this->format_error_message(
                __('This email address appears to be invalid. The domain does not accept emails. Please check the address or use a different one.', 'wp-custom-login-manager')
            );
        }
        if (isset($result->has_inbox_full) && $result->has_inbox_full) {
            return $this->format_error_message(
                __('This email inbox is full. Please use a different email address.', 'wp-custom-login-manager')
            );
        }
        if (isset($result->is_disabled) && $result->is_disabled) {
            return $this->format_error_message(
                __('This email address has been disabled. Please use a different email address.', 'wp-custom-login-manager')
            );
        }
        
        // Power mode specific checks
        if ($mode === 'power') {
            if ($result->status !== 'safe') {
                return $this->format_error_message(
                    __('This email address has been flagged as potentially unsafe. Please use a different email address.', 'wp-custom-login-manager')
                );
            }
            if (!$result->is_safe_to_send) {
                return $this->format_error_message(
                    __('This email address cannot receive emails safely. Please use a different address.', 'wp-custom-login-manager')
                );
            }
            if (!$result->is_deliverable) {
                return $this->format_error_message(
                    __('This email address appears to be undeliverable. Please check the address or use a different one.', 'wp-custom-login-manager')
                );
            }
            if ($result->overall_score < 80) {
                return $this->format_error_message(
                    __('This email address does not meet our quality requirements. Please use a different address.', 'wp-custom-login-manager')
                );
            }
        }
        
        // Default error for any other cases
        return $this->format_error_message(
            __('This email address appears to be invalid. Please check and try again.', 'wp-custom-login-manager')
        );
    }

    public function ajax_verify_email() {
        // Verify nonce
        if (!check_ajax_referer('wpclm_verify_email', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'wp-custom-login-manager')]);
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) {
            wp_send_json_error(['message' => __('Please enter an email address', 'wp-custom-login-manager')]);
        }

        // Use power mode for thorough verification
        $result = $this->verify_email($email);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Email verification successful!', 'wp-custom-login-manager')]);
    }

}