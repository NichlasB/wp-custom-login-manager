<?php
/**
 * Form Submission Handler Class
 *
 * Handles form POST processing for login, registration, password reset
 *
 * @package WPCustomLoginManager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Form_Submission_Handler {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Rate limiter instance
     */
    private $rate_limiter;

    /**
     * Email verifier instance
     */
    private $email_verifier;

    /**
     * Turnstile instance
     */
    private $turnstile;

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
        // Initialize component instances
        $this->messages = WPCLM_Messages::get_instance();
        $this->rate_limiter = WPCLM_Rate_Limiter::get_instance();
        $this->email_verifier = WPCLM_Email_Verifier::get_instance();

        if (class_exists('WPCLM_Turnstile')) {
            $this->turnstile = WPCLM_Turnstile::get_instance();
        }

        // Handle form submissions
        add_action('init', array($this, 'handle_form_submissions'), 15);
    }

    /**
     * Escape output for safe display
     *
     * @param string $data The data to escape
     * @param string $context The context for escaping
     * @return string
     */
    private function escape_output($data, $context = 'html') {
        $data = trim($data);
        
        switch ($context) {
            case 'attr':
                return esc_attr($data);
            case 'url':
                return esc_url($data);
            default:
                return esc_html($data);
        }
    }

    /**
     * Filter auth cookie expiration when Remember Me is enabled
     *
     * @param int $length Cookie length in seconds
     * @return int
     */
    public function filter_auth_cookie_expiration($length) {
        $days = absint(get_option('wpclm_remember_me_duration', 30));
        $days = max(1, min(365, $days));
        return $days * DAY_IN_SECONDS;
    }

    /**
     * Clear existing messages
     */
    private function clear_messages() {
        $cookie_name = 'wpclm_user_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
        
        if (isset($_COOKIE['wpclm_error_message'])) {
            setcookie('wpclm_error_message', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        if (isset($_COOKIE['wpclm_success_message'])) {
            setcookie('wpclm_success_message', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        
        delete_transient('wpclm_error_' . $cookie_name);
        delete_transient('wpclm_success_' . $cookie_name);
    }

    /**
     * Set error message
     *
     * @param string $message Error message
     */
    public function set_error_message($message) {
        $this->clear_messages();
        $cookie_name = 'wpclm_user_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
        
        $cookie_value = substr(base64_encode($message), 0, 80);
        setcookie('wpclm_error_message', $cookie_value, time() + 300, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        set_transient('wpclm_error_' . $cookie_name, $message, 300);
    }

    /**
     * Set success message
     *
     * @param string $message Success message
     */
    public function set_success_message($message) {
        $this->clear_messages();
        $cookie_name = 'wpclm_user_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
        
        $cookie_value = substr(base64_encode($message), 0, 80);
        setcookie('wpclm_success_message', $cookie_value, time() + 300, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        set_transient('wpclm_success_' . $cookie_name, $message, 300);
    }

    /**
     * Sanitize sensitive data for debug logging
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitize_debug_data($data) {
        $sensitive_fields = ['pass1', 'pass2', 'password', 'user_pass', 'wpclm_nonce'];
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive_fields)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $this->escape_output($value, 'html');
            }
        }

        return $sanitized;
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['wpclm_action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['wpclm_action']);
        
        switch ($action) {
            case 'login':
                $this->process_login();
                break;
            case 'register':
                $this->process_registration();
                break;
            case 'lostpassword':
                $this->process_lost_password();
                break;
            case 'resetpass':
                $this->process_reset_password();
                break;
            case 'setpassword':
                $this->process_password_setup();
                break;
        }
    }

    /**
     * Process login form
     */
    private function process_login() {
        if (!isset($_POST['wpclm_login_nonce']) || !wp_verify_nonce($_POST['wpclm_login_nonce'], 'wpclm-login-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'login_error' => urlencode($this->messages->get_message('invalid_nonce'))
            )));
            exit;
        }

        $ip_address = $this->rate_limiter->get_client_ip();
        $rate_check = $this->rate_limiter->check_rate_limit($ip_address);

        if (!$rate_check['allowed']) {
            $error_message = sprintf(
                __('Too many login attempts. Please try again in %d minutes and %d seconds.', 'wp-custom-login-manager'),
                floor($rate_check['remaining_time'] / 60),
                $rate_check['remaining_time'] % 60
            );
            wp_safe_redirect(add_query_arg('login_error', urlencode($error_message)));
            exit;
        }

        $creds = array(
            'user_login'    => isset($_POST['user_login']) ? sanitize_email($_POST['user_login']) : '',
            'user_password' => isset($_POST['user_pass']) ? $_POST['user_pass'] : '',
            'remember'      => isset($_POST['rememberme'])
        );

        if (empty($creds['user_login']) || empty($creds['user_password'])) {
            $error_message = $this->messages->get_message('required_fields');
            if ($rate_check['remaining_attempts'] < get_option('wpclm_rate_limit_max_attempts')) {
                $error_message .= ' ' . sprintf(
                    __('You have %d attempts remaining.', 'wp-custom-login-manager'),
                    $rate_check['remaining_attempts']
                );
            }
            wp_safe_redirect(add_query_arg('login_error', urlencode($error_message)));
            exit;
        }

        if (isset($_POST['rememberme'])) {
            add_filter('auth_cookie_expiration', array($this, 'filter_auth_cookie_expiration'), 999);
        }

        $user = wp_signon($creds, is_ssl());

        if (isset($_POST['rememberme'])) {
            remove_filter('auth_cookie_expiration', array($this, 'filter_auth_cookie_expiration'), 999);
        }

        if (is_wp_error($user)) {
            $error_message = $this->messages->get_message('login_failed');

            WPCLM_Auth::get_instance()->track_failed_login($creds['user_login']);
            $this->rate_limiter->record_attempt($ip_address);

            wp_safe_redirect(add_query_arg('login_error', urlencode($error_message)));
            exit;
        }

        WPCLM_Auth::get_instance()->clear_login_attempts($creds['user_login']);

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';
        $default_redirect = get_option('wpclm_login_redirect', admin_url());
        
        // Validate redirect URL to prevent open redirect attacks
        if (empty($redirect_to) || !wp_validate_redirect($redirect_to, $default_redirect)) {
            $redirect_to = $default_redirect;
        }

        wp_safe_redirect($redirect_to);
        exit;
    }

    /**
     * Process registration form
     */
    private function process_registration() {
        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));

        if (!isset($_POST['wpclm_register_nonce']) || !wp_verify_nonce($_POST['wpclm_register_nonce'], 'wpclm-register-nonce')) {
            $this->set_error_message($this->messages->get_message('invalid_nonce'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        // Verify Turnstile if enabled
        if ($this->turnstile && $this->turnstile->is_enabled_for('register')) {
            if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                WPCLM_Debug::get_instance()->log('Turnstile verification started for registration', [
                    'POST data' => $this->sanitize_debug_data($_POST),
                    'Has token' => isset($_POST['cf-turnstile-response']),
                    'Token value' => isset($_POST['cf-turnstile-response']) ? substr($_POST['cf-turnstile-response'], 0, 10) . '...' : 'not set'
                ], 'info');
            }

            $turnstile_response = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
            
            if (empty($turnstile_response)) {
                if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                    WPCLM_Debug::get_instance()->log('Turnstile token is missing', [], 'error');
                }
                $this->set_error_message($this->messages->get_message('security_verification_failed'));
                wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
                exit;
            }
            
            $turnstile_result = $this->turnstile->verify_token($turnstile_response);
            
            if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                WPCLM_Debug::get_instance()->log('Turnstile verification result', [
                    'is_wp_error' => is_wp_error($turnstile_result),
                    'result' => is_wp_error($turnstile_result) ? $turnstile_result->get_error_message() : 'Success'
                ], 'info');
            }
            
            if (is_wp_error($turnstile_result)) {
                $this->set_error_message($turnstile_result->get_error_message());
                wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
                exit;
            }
        }

        // Verify email if enabled
        if ($this->email_verifier && get_option('wpclm_email_verification_enabled')) {
            if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                $debug = WPCLM_Debug::get_instance();
                $debug->log('Email verification started for registration', [
                    'Email' => isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '',
                    'Verification Enabled' => get_option('wpclm_email_verification_enabled') ? 'Yes' : 'No'
                ], 'info');
            }
            
            $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
            $email_result = $this->email_verifier->verify_email($email);
            
            if (is_wp_error($email_result)) {
                if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                    WPCLM_Debug::get_instance()->log('Email verification failed', [
                        'Error' => $email_result->get_error_message()
                    ], 'error');
                }
                
                $this->set_error_message($email_result->get_error_message());
                wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
                exit;
            }
            
            if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                WPCLM_Debug::get_instance()->log('Email verification completed successfully', [], 'info');
            }
        } else {
            if (class_exists('WPCLM_Debug') && WPCLM_Debug::get_instance()) {
                WPCLM_Debug::get_instance()->log('Email verification skipped', [
                    'Verification Enabled' => get_option('wpclm_email_verification_enabled') ? 'Yes' : 'No',
                    'Verifier Initialized' => $this->email_verifier ? 'Yes' : 'No'
                ], 'info');
            }
        }

        // Check rate limiting
        $ip_address = $this->rate_limiter->get_client_ip();
        $rate_check = $this->rate_limiter->check_rate_limit($ip_address);

        if (!$rate_check['allowed']) {
            $error_message = sprintf(
                __('Too many registration attempts. Please try again in %d seconds.', 'wp-custom-login-manager'),
                $rate_check['remaining_time']
            );
            $this->set_error_message($error_message);
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        remove_action('register_new_user', 'wp_send_new_user_notifications');
        remove_action('user_register', 'wp_send_new_user_notifications');

        if (get_option('wpclm_disable_registration', 0)) {
            $this->set_error_message($this->messages->get_message('registration_disabled'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

        if (empty($email) || empty($first_name) || empty($last_name)) {
            $this->set_error_message($this->messages->get_message('required_fields'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        if (!is_email($email)) {
            $this->set_error_message($this->messages->get_message('invalid_email'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        if (email_exists($email)) {
            $this->set_error_message($this->messages->get_message('email_exists'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        $auth = WPCLM_Auth::get_instance();
        $encrypted_token = $auth->generate_confirmation_token();
        
        if (!$encrypted_token) {
            $this->set_error_message($this->messages->get_message('registration_failed'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        $token_data = $auth->validate_confirmation_token($encrypted_token);
        if (!$token_data) {
            $this->set_error_message($this->messages->get_message('registration_failed'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        // L1 Fix: Align transient TTL with token expiry (default 2 hours, filterable)
        $token_expiry = apply_filters('wpclm_confirmation_token_expiry', 2 * HOUR_IN_SECONDS);
        set_transient('wpclm_registration_' . $token_data['token'], array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'timestamp' => time()
        ), $token_expiry);

        $emails = WPCLM_Emails::get_instance();
        $sent = $emails->send_confirmation_email($email, $first_name, $encrypted_token);

        if (!$sent) {
            delete_transient('wpclm_registration_' . $token_data['token']);
            $this->set_error_message($this->messages->get_message('email_failed'));
            wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
            exit;
        }

        $this->rate_limiter->record_attempt($ip_address);

        $this->set_success_message($this->messages->get_message('confirmation_sent'));
        wp_safe_redirect(add_query_arg(array('action' => 'register'), $login_url));
        exit;
    }

    /**
     * Process lost password form
     */
    private function process_lost_password() {
        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
        $debug = class_exists('WPCLM_Debug') ? WPCLM_Debug::get_instance() : null;
        $ip_address = $this->rate_limiter->get_client_ip();
        $rate_check = $this->rate_limiter->check_rate_limit($ip_address);

        if ($debug) {
            $debug->log('Processing lost password form', [
                'post_data' => $this->sanitize_debug_data($_POST)
            ], 'info');
        }
        
        // Verify Turnstile if enabled
        if ($this->turnstile && $this->turnstile->is_enabled_for('reset')) {
            $turnstile_response = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
            
            if ($debug) {
                $debug->log('Turnstile enabled for reset form', [
                    'token_preview' => empty($turnstile_response) ? 'EMPTY' : substr($turnstile_response, 0, 10) . '...'
                ], 'info');
            }
            
            $turnstile_result = $this->turnstile->verify_token($turnstile_response);

            if (is_wp_error($turnstile_result)) {
                if ($debug) {
                    $debug->log('Turnstile verification failed for reset form', [
                        'error' => $turnstile_result->get_error_message()
                    ], 'error');
                }
                $error_message = $this->messages->get_message('security_verification_failed');
                
                wp_safe_redirect(add_query_arg(array(
                    'action' => 'lostpassword',
                    'login_error' => urlencode($error_message)
                ), $login_url));
                exit;
            }
            
            if ($debug) {
                $debug->log('Turnstile verification successful for reset form', [], 'info');
            }
        }
        
        // Verify nonce
        if (!isset($_POST['wpclm_lostpass_nonce']) || !wp_verify_nonce($_POST['wpclm_lostpass_nonce'], 'wpclm-lostpass-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'login_error' => urlencode($this->messages->get_message('invalid_nonce'))
            ), $login_url));
            exit;
        }

        $user_login = isset($_POST['user_login']) ? sanitize_email($_POST['user_login']) : '';

        if (empty($user_login)) {
            $error_message = $this->messages->get_message('required_fields');
            if (isset($rate_check) && isset($rate_check['remaining_attempts']) && $rate_check['remaining_attempts'] < get_option('wpclm_rate_limit_max_attempts')) {
                $error_message .= ' ' . sprintf(
                    __('You have %d attempts remaining.', 'wp-custom-login-manager'),
                    $rate_check['remaining_attempts']
                );
            }
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'login_error' => urlencode($error_message)
            ), $login_url));
            exit;
        }

        $user = get_user_by('email', $user_login);

        if (!$user) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'form_error' => 'invalid_email'
            ), $login_url));
            exit;
        }

        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($this->messages->get_message('password_reset_failed'))
            ), $login_url));
            exit;
        }

        $emails = WPCLM_Emails::get_instance();
        $sent = $emails->send_reset_password_email($user, $key);

        if (!$sent) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($this->messages->get_message('password_reset_failed'))
            ), $login_url));
            exit;
        }

        $this->rate_limiter->record_attempt($ip_address);

        wp_safe_redirect(add_query_arg(array(
            'action' => 'lostpassword',
            'checkemail' => 'confirm'
        ), $login_url));
        exit;
    }

    /**
     * Process reset password form
     */
    private function process_reset_password() {
        $rp_key = isset($_POST['rp_key']) ? sanitize_text_field($_POST['rp_key']) : '';
        $rp_login = isset($_POST['rp_login']) ? sanitize_text_field($_POST['rp_login']) : '';
        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));

        $debug = class_exists('WPCLM_Debug') ? WPCLM_Debug::get_instance() : null;

        if ($debug) {
            $debug->log('Processing reset password form', [
                'post_data' => $this->sanitize_debug_data($_POST)
            ], 'info');
        }
    
        // Verify Turnstile first if enabled
        if ($this->turnstile && $this->turnstile->is_enabled_for('reset')) {
            $turnstile_response = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
            $turnstile_result = $this->turnstile->verify_token($turnstile_response);

            if (is_wp_error($turnstile_result)) {
                $error_message = $this->messages->get_message('security_verification_failed');
                
                $error_key = 'wpclm_reset_error_' . md5($rp_key . $rp_login);
                set_transient($error_key, $error_message, 30);
                
                $redirect_url = add_query_arg(array(
                    'action' => 'resetpass',
                    'key' => $rp_key,
                    'login' => $rp_login,
                    'error_key' => $error_key
                ), $login_url);
                
                wp_safe_redirect($redirect_url);
                exit;
            }
        }

        // Then verify nonce
        if (!isset($_POST['wpclm_resetpass_nonce']) || !wp_verify_nonce($_POST['wpclm_resetpass_nonce'], 'wpclm-resetpass-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'resetpass',
                'key' => $rp_key,
                'login' => $rp_login,
                'error' => urlencode($this->messages->get_message('invalid_nonce'))
            ), $login_url));
            exit;
        }

        $rp_key = isset($_POST['rp_key']) ? sanitize_text_field($_POST['rp_key']) : '';
        $rp_login = isset($_POST['rp_login']) ? sanitize_text_field($_POST['rp_login']) : '';
        $pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        $pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';

        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));

        if (empty($rp_key) || empty($rp_login) || empty($pass1) || empty($pass2)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'resetpass',
                'key' => $rp_key,
                'login' => $rp_login,
                'error' => urlencode($this->messages->get_message('required_fields'))
            ), $login_url));
            exit;
        }

        $user = check_password_reset_key($rp_key, $rp_login);
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'resetpass',
                'error' => urlencode($this->messages->get_message('invalid_key'))
            ), $login_url));
            exit;
        }

        if ($pass1 !== $pass2) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'resetpass',
                'key' => $rp_key,
                'login' => $rp_login,
                'error' => urlencode($this->messages->get_message('password_mismatch'))
            ), $login_url));
            exit;
        }

        $auth = WPCLM_Auth::get_instance();
        $validation_result = $auth->validate_password($pass1);
        if (is_wp_error($validation_result)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'resetpass',
                'key' => $rp_key,
                'login' => $rp_login,
                'error' => urlencode($validation_result->get_error_message())
            ), $login_url));
            exit;
        }

        reset_password($user, $pass1);

        wp_safe_redirect(add_query_arg(array(
            'password' => 'changed'
        ), $login_url));
        exit;
    }

    /**
     * Process password setup
     */
    private function process_password_setup() {
        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));

        if (!isset($_POST['wpclm_setpass_nonce']) || !wp_verify_nonce($_POST['wpclm_setpass_nonce'], 'wpclm-setpass-nonce')) {
            $this->set_error_message($this->messages->get_message('invalid_nonce'));
            wp_safe_redirect(add_query_arg(array('action' => 'setpassword'), $login_url));
            exit;
        }

        $rp_key = isset($_POST['rp_key']) ? sanitize_text_field($_POST['rp_key']) : '';
        $rp_login = isset($_POST['rp_login']) ? sanitize_text_field($_POST['rp_login']) : '';
        $pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        $pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';

        if (empty($rp_key) || empty($rp_login) || empty($pass1) || empty($pass2)) {
            $this->set_error_message($this->messages->get_message('required_fields'));
            wp_safe_redirect(add_query_arg(array('action' => 'setpassword'), $login_url));
            exit;
        }

        $user = check_password_reset_key($rp_key, $rp_login);
        if (is_wp_error($user)) {
            $this->set_error_message($this->messages->get_message('invalid_key'));
            wp_safe_redirect(add_query_arg(array('action' => 'setpassword'), $login_url));
            exit;
        }

        if ($pass1 !== $pass2) {
            $this->set_error_message($this->messages->get_message('password_mismatch'));
            wp_safe_redirect(add_query_arg(array('action' => 'setpassword'), $login_url));
            exit;
        }

        $auth = WPCLM_Auth::get_instance();
        $validation_result = $auth->validate_password($pass1);
        if (is_wp_error($validation_result)) {
            $this->set_error_message($validation_result->get_error_message());
            wp_safe_redirect(add_query_arg(array('action' => 'setpassword'), $login_url));
            exit;
        }

        reset_password($user, $pass1);

        $this->set_success_message(__('Your password has been set successfully. Please log in with your email and password.', 'wp-custom-login-manager'));
        wp_safe_redirect(add_query_arg(array(
            'action' => 'login',
            'registration' => 'completed'
        ), $login_url));
        exit;
    }
}
