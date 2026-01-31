<?php
/**
 * Access Control Class
 *
 * Handles redirects, authentication checks, and access gates
 *
 * @package WPCustomLoginManager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Access_Control {

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
        // Handle logout redirect
        add_action('wp_logout', array($this, 'handle_logout_redirect'));

        // Modify login redirection
        add_filter('login_redirect', array($this, 'check_auth_redirect'), 10, 3);

        // Protect admin access
        add_action('admin_init', array($this, 'check_admin_access'));

        // Add body class
        add_filter('body_class', array($this, 'add_body_class'));

        // Redirect logged-in users
        add_action('template_redirect', array($this, 'redirect_logged_in_users'), 1);

        // Add protection for specific paths
        add_action('template_redirect', array($this, 'protect_paths'), 1);
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
     * Get login page path
     *
     * @return string
     */
    private function get_login_path() {
        static $cached_path = null;
        if ($cached_path !== null) {
            return $cached_path;
        }

        $login_path = $this->escape_output(get_option('wpclm_login_url', '/account-login/'), 'url');
        $cached_path = trim($login_path, '/');

        return $cached_path;
    }

    /**
     * Check if current URL is login page
     *
     * @return bool
     */
    private function is_login_url() {
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = trim($current_path, '/');
        $login_path = trim(get_option('wpclm_login_url', '/account-login/'), '/');
        return $current_path === $login_path;
    }

    /**
     * Check if current page is login page
     *
     * @return bool
     */
    public function is_login_page() {
        return $this->is_login_url();
    }

    /**
     * Add body class
     *
     * @param array $classes Body classes
     * @return array
     */
    public function add_body_class($classes) {
        if ($this->is_login_page()) {
            $classes[] = $this->escape_output('wpclm-page', 'attr');
        }
        return $classes;
    }

    /**
     * Force login page redirect
     */
    public function force_login_redirect() {
        if (!is_user_logged_in()) {
            $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
            wp_safe_redirect($login_url);
            exit;
        }
    }

    /**
     * Handle logout redirect
     */
    public function handle_logout_redirect() {
        $login_url = home_url($this->get_login_path());
        $login_url = add_query_arg('logged_out', 'true', $login_url);
        wp_safe_redirect($login_url);
        exit;
    }

    /**
     * Check authentication and redirect
     *
     * @param string $redirect_to Redirect URL
     * @param string $requested_redirect_to Requested redirect URL
     * @param WP_User|WP_Error $user User object or error
     * @return string
     */
    public function check_auth_redirect($redirect_to, $requested_redirect_to = '', $user = null) {
        // Check if redirect is enabled
        if (!get_option('wpclm_disable_wp_login', 1)) {
            return $redirect_to;
        }

        // Allow direct access with special parameter
        if (isset($_GET['direct_login']) && $_GET['direct_login'] === 'true') {
            return $redirect_to;
        }

        // Don't redirect certain actions
        $allowed_actions = array('logout', 'postpass', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'activate');
        if (isset($_GET['action']) && in_array($_GET['action'], $allowed_actions)) {
            return $redirect_to;
        }

        // If accessing wp-login.php directly, redirect to custom login page
        if (strpos($_SERVER['PHP_SELF'], 'wp-login.php') !== false) {
            $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
            if (isset($_GET['redirect_to'])) {
                $login_url = add_query_arg('redirect_to', $_GET['redirect_to'], $login_url);
            }
            wp_redirect($login_url);
            exit;
        }

        return $redirect_to;
    }

    /**
     * Check admin access
     */
    public function check_admin_access() {
        // Skip AJAX requests - check multiple ways to be safe
        if (wp_doing_ajax() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }
        
        // Also check request URI for admin-ajax.php
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') !== false) {
            return;
        }

        if (!is_admin()) {
            return;
        }

        if (!is_user_logged_in()) {
            $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
            $login_url = add_query_arg('redirect_to', admin_url(), $login_url);
            wp_redirect($login_url);
            exit;
        }
    }

    /**
     * Protect specific paths
     * M4 Fix: Added redirect loop detection
     */
    public function protect_paths() {
        if (is_user_logged_in() || $this->is_login_url()) {
            return;
        }
        
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $path_segments = explode('/', $current_path);
        
        // M4 Fix: Detect redirect loop via counter in query param
        $loop_count = isset($_GET['_wpclm_loop']) ? absint($_GET['_wpclm_loop']) : 0;
        if ($loop_count > 2) {
            // Break out of loop - allow access to prevent infinite redirect
            if (class_exists('WPCLM_Debug')) {
                WPCLM_Debug::log_message('Redirect loop detected, breaking out', array('path' => $current_path), 'warning');
            }
            return;
        }
        
        $protected_paths = apply_filters('wpclm_protected_paths', array('my-account'));
        
        foreach ($protected_paths as $protected_path) {
            if (in_array($protected_path, $path_segments)) {
                $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
                $redirect_to = home_url($_SERVER['REQUEST_URI']);
                $login_url = add_query_arg(array(
                    'redirect_to' => urlencode($redirect_to),
                    '_wpclm_loop' => $loop_count + 1
                ), $login_url);
                wp_safe_redirect($login_url);
                exit;
            }
        }
    }

    /**
     * Redirect logged-in users from login page
     */
    public function redirect_logged_in_users() {
        // Check if we're on the login page
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // If user is logged in and on login page, redirect
        if (is_user_logged_in() && $current_path === trim(get_option('wpclm_login_url', '/account-login/'), '/')) {

            // Get redirect URL from settings or use default
            $redirect_url = get_option('wpclm_logged_in_redirect', home_url('/my-account/'));

            // Ensure we have a valid URL
            if (empty($redirect_url) || !filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                $redirect_url = home_url('/my-account/');
            }

            // Allow developers to modify the redirect URL
            $redirect_url = apply_filters('wpclm_logged_in_redirect_url', $redirect_url);

            // Perform redirect
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
