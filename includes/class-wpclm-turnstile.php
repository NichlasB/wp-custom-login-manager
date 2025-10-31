<?php
/**
 * Cloudflare Turnstile Integration
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Turnstile {
    /**
     * Instance of this class
     *
     * @var WPCLM_Turnstile
     */
    private static $instance = null;
    /**
     * Get instance of this class
     *
     * @return WPCLM_Turnstile
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Check if Turnstile is globally enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public function is_enabled() {
        return (bool) get_option('wpclm_turnstile_enabled', false);
    }

    /**
     * Check if Turnstile is enabled for a specific form type
     *
     * @param string $form_type Form type (login, register, reset)
     * @return bool True if enabled, false otherwise
     */
    public function is_enabled_for($form_type) {
        // Always disable for login and reset forms regardless of settings
        if ($form_type === 'login' || $form_type === 'reset') {
            $this->log_debug('Turnstile disabled for ' . $form_type . ' form');
            return false;
        }
        
        // Check if Turnstile is globally enabled
        if (!$this->is_enabled()) {
            return false;
        }
        
        // Check if Turnstile is enabled for this form type
        $enabled_forms = get_option('wpclm_turnstile_forms', array('register'));
        $is_enabled = in_array($form_type, $enabled_forms);
        
        $this->log_debug("Turnstile for {$form_type} form: " . ($is_enabled ? 'enabled' : 'disabled'));
        
        return $is_enabled;
    }

    /**
     * Log debug information if debugging is enabled
     * 
     * @param string $message The debug message to log
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // Create logs directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $logs_dir = $upload_dir['basedir'] . '/wpclm-logs';
            
            if (!file_exists($logs_dir)) {
                wp_mkdir_p($logs_dir);
                // Create .htaccess to protect logs
                file_put_contents($logs_dir . '/.htaccess', 'deny from all');
            }
            
            $log_file = $logs_dir . '/turnstile-debug.log';
            $timestamp = date('[Y-m-d H:i:s]');
            $log_message = $timestamp . ' ' . $message . PHP_EOL;
            
            error_log($log_message, 3, $log_file);
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Render Turnstile widget
     * 
     * @param string $form_type The form type (login, register, reset)
     */
    public function render_widget($form_type = 'register') {
        if (!$this->is_enabled()) {
            return;
        }
        
        $site_key = get_option('wpclm_turnstile_site_key', '');
        
        if (empty($site_key)) {
            return;
        }
        
        // Add the form type as a data attribute to help with JavaScript targeting
        // Add a unique ID to ensure only one widget is rendered
        echo '<div id="wpclm-turnstile-widget" class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '" data-theme="light" data-form-type="' . esc_attr($form_type) . '" data-callback="onTurnstileCallback"></div>';
    }

    /**
     * Verify Turnstile response
     *
     * @param string $token Response token from Turnstile
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public function verify_token($token) {
        // Log verification attempt for debugging
        $this->log_debug('Turnstile verification attempt with token: ' . (empty($token) ? 'EMPTY' : substr($token, 0, 10) . '...'));
        $this->log_debug('POST data: ' . print_r($_POST, true));
        $this->log_debug('Form type detected: ' . (isset($_POST['wpclm_lostpass_nonce']) ? 'lost password' : (isset($_POST['wpclm_resetpass_nonce']) ? 'reset password' : 'unknown')));
        
        if (empty($token)) {
            $this->log_debug('Turnstile verification failed: Empty token');
            return new WP_Error('missing_token', __('Security verification failed. Please try again.', 'wp-custom-login-manager'));
        }

        $secret_key = get_option('wpclm_turnstile_secret_key', '');
        
        if (empty($secret_key)) {
            $this->log_debug('Turnstile verification failed: Missing secret key');
            return new WP_Error('missing_secret', __('Security verification failed. Please try again.', 'wp-custom-login-manager'));
        }

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $this->get_client_ip()
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log_debug('Turnstile API error: ' . $response->get_error_message());
            return new WP_Error('api_error', __('Security verification failed. Please try again.', 'wp-custom-login-manager'));
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!isset($result['success']) || $result['success'] !== true) {
            $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown';
            $this->log_debug('Turnstile verification failed: ' . $error_codes);
            return new WP_Error('verification_failed', __('Security verification failed. Please try again.', 'wp-custom-login-manager'));
        }
        
        $this->log_debug('Turnstile verification successful');

        return true;
    }
}