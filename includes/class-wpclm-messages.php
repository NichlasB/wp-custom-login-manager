<?php
/**
 * Messages Handler Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Messages {
    /**
     * Singleton instance
     */
    private static $instance = null;
    private $debug;

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
        $this->debug = WPCLM_Debug::get_instance();
    }

    /**
     * Get error message
    *
    * @param string $key Message key
    * @return string
    */
    public function get_message($key) {
        $debug = WPCLM_Debug::get_instance();

        $debug->log("Getting message for key: " . $key, null, 'debug');

        $default_messages = $this->get_default_messages();
        $debug->log("Default messages", $default_messages, 'debug');

        // First try to get custom message
        $custom_message = get_option('wpclm_message_' . $key);
        $debug->log("Custom message from option", ($custom_message ?: 'none'), 'debug');

        // If no custom message, use default
        $message = !empty($custom_message) ? $custom_message : 
        (isset($default_messages[$key]) ? $default_messages[$key] : '');

        $debug->log("Final message", $message, 'debug');

        return $message;
    }

    /**
     * Get default messages
     *
     * @return array
     */
    private function get_default_messages() {
        return array(
            // Login messages
            'login_failed' => __('Invalid email or password.', 'wp-custom-login-manager'),
            'too_many_attempts' => __('Too many login attempts. Please try again later.', 'wp-custom-login-manager'),
            'logged_out' => __('You have been logged out successfully.', 'wp-custom-login-manager'),

            // Registration messages
            'email_exists' => __('This email address is already registered.', 'wp-custom-login-manager'),
            'registration_disabled' => __('Account registration is currently disabled.', 'wp-custom-login-manager'),
            'registration_failed' => __('Registration failed. Please try again.', 'wp-custom-login-manager'),
            'confirmation_sent' => __('Please check your email to confirm your registration.', 'wp-custom-login-manager'),
            'confirmation_success' => __('Your email has been confirmed. Please set your password.', 'wp-custom-login-manager'),
            'confirmation_expired' => __('The confirmation link has expired. Please register again.', 'wp-custom-login-manager'),

            // Password-related messages
            'password_mismatch' => __('The passwords do not match.', 'wp-custom-login-manager'),
            'weak_password' => __('Please choose a stronger password.', 'wp-custom-login-manager'),
            'password_reset_sent' => __('Password reset instructions have been sent to your email.', 'wp-custom-login-manager'),
            'password_reset_success' => __('Your password has been reset successfully.', 'wp-custom-login-manager'),
            'password_reset_expired' => __('The password reset link has expired. Please try again.', 'wp-custom-login-manager'),
            'password_reset_failed' => __('Failed to generate password reset key. Please try again.', 'wp-custom-login-manager'),
            'password_updated' => __('Your password has been updated successfully.', 'wp-custom-login-manager'),
            'password_set' => __('Your password has been set successfully.', 'wp-custom-login-manager'),

            // Validation messages
            'invalid_email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
            'required_fields' => __('Please fill in all required fields.', 'wp-custom-login-manager'),
            'invalid_key' => __('Invalid or expired key. Please try again.', 'wp-custom-login-manager'),
            'invalid_combo' => __('Invalid password reset key combination.', 'wp-custom-login-manager'),
            'invalid_nonce' => __('Security verification failed. Please try again.', 'wp-custom-login-manager'),
            'security_verification_failed' => __('Please complete the security verification before submitting the form.', 'wp-custom-login-manager'),

            // Generic messages
            'generic_error' => __('An error occurred. Please try again.', 'wp-custom-login-manager'),
            'success' => __('Operation completed successfully.', 'wp-custom-login-manager'),
            'changes_saved' => __('Changes have been saved successfully.', 'wp-custom-login-manager'),
            'access_denied' => __('Access denied. Please log in to continue.', 'wp-custom-login-manager'),
            'session_expired' => __('Your session has expired. Please log in again.', 'wp-custom-login-manager')
        );
    }
}

// Initialize Messages
WPCLM_Messages::get_instance();