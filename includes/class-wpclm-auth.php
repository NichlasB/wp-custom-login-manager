<?php
/**
 * Authentication Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Auth {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Messages instance
     */
    private $messages;

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
        // Initialize Messages
        $this->messages = WPCLM_Messages::get_instance();
    
        // Handle email confirmation
        add_action('init', array($this, 'handle_email_confirmation'));
    
        // Track failed login attempts
        add_filter('authenticate', array($this, 'check_login_attempts'), 30, 3);
    
        // Clear failed login attempts on successful login
        add_action('wp_login', array($this, 'clear_login_attempts'));
    
    }

/**
 * Debug log for nonce operations
 *
 * @param string $message Message to log
 * @param mixed $data Optional data to log
 */
private function debug_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        $timestamp = current_time('Y-m-d H:i:s');
        $log_message = "[WPCLM Nonce Debug][$timestamp] $message";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $log_message .= "\nData: " . print_r($data, true);
            } else {
                $log_message .= "\nData: $data";
            }
        }
        
        error_log($log_message);
    }
}

/**
 * Generate secure confirmation token
 * 
 * @return string Encrypted token
 */
public function generate_confirmation_token() {
    // Generate random token
    $token = wp_generate_password(32, false);
    
    // Add timestamp for expiration
    $timestamp = time();
    
    // Create a nonce for security
    $nonce = wp_create_nonce('wpclm_email_confirmation');
    
    // Combine data
    $token_data = array(
        'token' => $token,
        'timestamp' => $timestamp,
        'nonce' => $nonce
    );
    
    $this->debug_log('Generating confirmation token', array(
        'token_length' => strlen($token),
        'timestamp' => date('Y-m-d H:i:s', $timestamp),
        'nonce' => $nonce
    ));
    
    // Encode token data
    $encoded_token = base64_encode(json_encode($token_data));
    
    $this->debug_log('Token generated successfully', array(
        'encoded_length' => strlen($encoded_token)
    ));
    
    return $encoded_token;
}

/**
 * Validate confirmation token
 * 
 * @param string $encoded_token The encoded token to validate
 * @return array|false Token data if valid, false if invalid
 */
public function validate_confirmation_token($encoded_token) {
    $this->debug_log('Validating confirmation token', array(
        'received_token' => substr($encoded_token, 0, 32) . '...' // Only log first 32 chars for security
    ));
    
    // Decode token
    $token_data = json_decode(base64_decode($encoded_token), true);
    
    if (!is_array($token_data) || 
        !isset($token_data['token']) || 
        !isset($token_data['timestamp']) || 
        !isset($token_data['nonce'])) {
        $this->debug_log('Token validation failed: Invalid token structure', array(
            'is_array' => is_array($token_data),
            'has_token' => isset($token_data['token']),
            'has_timestamp' => isset($token_data['timestamp']),
            'has_nonce' => isset($token_data['nonce'])
        ));
        return false;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($token_data['nonce'], 'wpclm_email_confirmation')) {
        $this->debug_log('Token validation failed: Invalid nonce');
        return false;
    }
    
    // Check if token is expired (24 hours)
    $time_elapsed = time() - $token_data['timestamp'];
    if ($time_elapsed > DAY_IN_SECONDS) {
        $this->debug_log('Token validation failed: Token expired', array(
            'time_elapsed' => $time_elapsed,
            'max_age' => DAY_IN_SECONDS
        ));
        return false;
    }
    
    $this->debug_log('Token validated successfully', array(
        'age_in_hours' => round($time_elapsed / 3600, 2)
    ));
    
    return $token_data;
}

 /**
 * Handle email confirmation
 */
public function handle_email_confirmation() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'confirm' || !isset($_GET['key'])) {
        return;
    }

    $this->debug_log('Starting email confirmation process');

    $encrypted_token = sanitize_text_field($_GET['key']);
    $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
    
    // Validate token
    $token_data = $this->validate_confirmation_token($encrypted_token);
    if (!$token_data) {
        $this->debug_log('Token validation failed, redirecting to error');
        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'error' => urlencode($this->messages->get_message('confirmation_expired'))
        ), $login_url));
        exit;
    }
    
    // Get registration data using validated token
    $transient_key = 'wpclm_registration_' . $token_data['token'];
    $registration_data = get_transient($transient_key);
    
    $this->debug_log('Retrieved registration data from transient', array(
        'transient_key' => $transient_key,
        'has_data' => !empty($registration_data)
    ));

    if (!$registration_data) {
        $this->debug_log('Registration data not found or expired');
        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'error' => urlencode($this->messages->get_message('confirmation_expired'))
        ), $login_url));
        exit;
    }

    // Check if email already exists
    if (email_exists($registration_data['email'])) {
        $this->debug_log('Email already exists', array(
            'email' => $registration_data['email']
        ));
        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'error' => urlencode($this->messages->get_message('email_exists'))
        ), $login_url));
        exit;
    }

    // Disable WordPress's new user notifications
    remove_action('register_new_user', 'wp_send_new_user_notification');
    remove_action('edit_user_created_user', 'wp_send_new_user_notification');
    add_filter('wp_send_new_user_notification', '__return_false');

    // Create user account
    $userdata = array(
        'user_login'    => $registration_data['email'],
        'user_email'    => $registration_data['email'],
        'first_name'    => $registration_data['first_name'],
        'last_name'     => $registration_data['last_name'],
        'display_name'  => $registration_data['first_name'] . ' ' . $registration_data['last_name'],
        'role'          => get_option('wpclm_default_role', 'subscriber'),
        'user_pass'     => wp_generate_password() // Temporary random password
    );

    $this->debug_log('Attempting to create new user', array(
        'email' => $registration_data['email']
    ));

    $user_id = wp_insert_user($userdata);

    if (is_wp_error($user_id)) {
        $this->debug_log('User creation failed', array(
            'error' => $user_id->get_error_message()
        ));
        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'error' => urlencode($this->messages->get_message('registration_failed'))
        ), $login_url));
        exit;
    }

    // Clean up the transient
    delete_transient($transient_key);
    $this->debug_log('Deleted registration transient after successful user creation');

    // Generate password reset key for the new user
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        $this->debug_log('Failed to get user after creation');
        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'error' => urlencode($this->messages->get_message('registration_failed'))
        ), $login_url));
        exit;
    }

    // Generate a proper password reset key
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        $this->debug_log('Failed to generate password reset key', array(
            'error' => $key->get_error_message()
        ));
        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'error' => urlencode($this->messages->get_message('registration_failed'))
        ), $login_url));
        exit;
    }

    $this->debug_log('Email confirmation successful, redirecting to password setup');

    // Redirect to password setup form
    wp_safe_redirect(add_query_arg(array(
        'action' => 'setpassword',
        'key' => $key,
        'login' => rawurlencode($user->user_login)
    ), $login_url));
    exit;
}

    /**
     * Create new user account
     */
    public function create_user_account($registration_data, $password) {
        $userdata = array(
            'user_login'    => $registration_data['email'],
            'user_email'    => $registration_data['email'],
            'user_pass'     => $password,
            'first_name'    => $registration_data['first_name'],
            'last_name'     => $registration_data['last_name'],
            'display_name'  => $registration_data['first_name'] . ' ' . $registration_data['last_name'],
            'role'          => get_option('wpclm_default_role', 'subscriber')
        );

        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            return false;
        }

        return $user_id;
    }

    /**
     * Check login attempts
     */
    public function check_login_attempts($user, $username, $password) {
        if (empty($username)) {
            return $user;
        }

        $ip = $this->get_client_ip();
        $failed_attempts = get_transient('wpclm_failed_login_' . $ip);
        $max_attempts = get_option('wpclm_max_login_attempts', 6);

        if ($failed_attempts && $failed_attempts >= $max_attempts) {
            return new WP_Error(
                'too_many_attempts',
                sprintf(
                    __('Too many failed login attempts. Please try again in %d minutes.', 'wp-custom-login-manager'),
                    get_option('wpclm_lockout_duration', 10)
                )
            );
        }

        return $user;
    }

    /**
     * Track failed login attempt
     */
    public function track_failed_login($username) {
        $ip = $this->get_client_ip();
        $failed_attempts = get_transient('wpclm_failed_login_' . $ip);
        
        if (!$failed_attempts) {
            $failed_attempts = 1;
        } else {
            $failed_attempts++;
        }

        set_transient(
            'wpclm_failed_login_' . $ip,
            $failed_attempts,
            get_option('wpclm_lockout_duration', 10) * MINUTE_IN_SECONDS
        );
    }

    /**
     * Clear login attempts
     */
    public function clear_login_attempts($username) {
        $ip = $this->get_client_ip();
        delete_transient('wpclm_failed_login_' . $ip);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
 * Validate password against requirements
 * 
 * @param string $password The password to validate
 * @return bool|WP_Error True if valid, WP_Error if invalid
 */
public function validate_password($password) {
    if (strlen($password) < 12) {
        return new WP_Error('password_too_short', __('Password must be at least 12 characters long.', 'wp-custom-login-manager'));
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return new WP_Error('password_no_upper', __('Password must include at least one uppercase letter.', 'wp-custom-login-manager'));
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return new WP_Error('password_no_lower', __('Password must include at least one lowercase letter.', 'wp-custom-login-manager'));
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return new WP_Error('password_no_number', __('Password must include at least one number.', 'wp-custom-login-manager'));
    }
    
    return true;
}

}

// Initialize Authentication
WPCLM_Auth::get_instance();