<?php
/**
 * Login Router Class
 *
 * Handles URL routing, rewrites, and wp-login.php redirection
 *
 * @package WPCustomLoginManager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Login_Router {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Cached login path
     */
    private $cached_path = null;

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
        // Initialize login hooks if wp-login.php redirect is enabled
        if (get_option('wpclm_disable_wp_login', 1)) {
            add_action('init', array($this, 'maybe_redirect_login_page'), 1);
            add_action('login_init', array($this, 'maybe_redirect_login_page'), 1);
            add_filter('login_url', array($this, 'get_login_page_url'), 10, 3);
            add_filter('site_url', array($this, 'filter_login_url'), 10, 4);
        }

        // Add rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Handle custom login URL
        add_action('parse_request', array($this, 'handle_custom_login_url'), 1);
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
    public function get_login_path() {
        if ($this->cached_path !== null) {
            return $this->cached_path;
        }

        $login_path = $this->escape_output(get_option('wpclm_login_url', '/account-login/'), 'url');
        $this->cached_path = trim($login_path, '/');

        return $this->cached_path;
    }

    /**
     * Check if current URL is login page
     *
     * @return bool
     */
    public function is_login_url() {
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = trim($current_path, '/');
        $login_path = trim(get_option('wpclm_login_url', '/account-login/'), '/');
        return $current_path === $login_path;
    }

    /**
     * Redirect default login page to custom login page
     */
    public function maybe_redirect_login_page() {
        global $pagenow;

        // Don't redirect certain WordPress login actions
        $allowed_actions = array(
            'logout',
            'postpass',
            'lostpassword',
            'retrievepassword',
            'rp',
            'resetpass',
            'activate'
        );

        if (isset($_GET['action']) && in_array($_GET['action'], $allowed_actions)) {
            return;
        }

        // Allow direct wp-login.php access with a special parameter
        if (isset($_GET['direct_login']) && $_GET['direct_login'] === 'true') {
            return;
        }

        // Check if we're on the login page or trying to access admin
        if (($pagenow === 'wp-login.php' && !isset($_GET['action'])) || 
            (!is_user_logged_in() && strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false)) {

            // Get custom login URL
            $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));

            // Add redirect_to parameter
            if (isset($_GET['redirect_to'])) {
                $login_url = add_query_arg('redirect_to', $_GET['redirect_to'], $login_url);
            } elseif (strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) {
                $login_url = add_query_arg('redirect_to', admin_url(), $login_url);
            }

            wp_redirect($login_url);
            exit;
        }
    }

    /**
     * Get login page URL (filter callback for login_url)
     *
     * @param string $login_url The login URL
     * @param string $redirect The redirect URL
     * @param bool $force_reauth Whether to force reauth
     * @return string
     */
    public function get_login_page_url($login_url, $redirect, $force_reauth) {
        $login_path = $this->get_login_path();
        $login_url = home_url($login_path);

        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', $this->escape_output($redirect, 'url'), $login_url);
        }

        return $login_url;
    }

    /**
     * Filter login URLs (filter callback for site_url)
     *
     * @param string $url The URL
     * @param string $path The path
     * @param string $scheme The scheme
     * @param int $blog_id The blog ID
     * @return string
     */
    public function filter_login_url($url, $path, $scheme, $blog_id) {
        if ($path === 'wp-login.php' && !isset($_GET['direct_login'])) {
            return home_url(get_option('wpclm_login_url', '/account-login/'));
        }
        return $url;
    }

    /**
     * Handle custom login URL
     */
    public function handle_custom_login_url() {
        // Get the current path
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        // Get the configured login path
        $login_path = trim($this->escape_output(get_option('wpclm_login_url', '/account-login/'), 'url'), '/');
        
        // If the current path matches the old default login path and it's not the current configured path
        if ($current_path === 'account-login' && $login_path !== 'account-login') {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        
        if ($this->is_login_url()) {
            // Prevent caching of login page to avoid stale nonce issues
            nocache_headers();

            // Redirect to login page if registrations are disabled and user tries to register
            if (isset($_GET['action']) && $_GET['action'] === 'register' && get_option('wpclm_disable_registration', 0)) {
                $login_url = home_url($this->escape_output(get_option('wpclm_login_url', '/account-login/'), 'url'));
                wp_safe_redirect($login_url);
                exit;
            }

            status_header(200);
            
            // Get renderer instance and render the page
            $renderer = WPCLM_Login_Page_Renderer::get_instance();
            $renderer->render_full_page();
            exit;
        }
    }

    /**
     * Update login URL and flush rewrite rules
     *
     * @param string $new_url The new login URL
     */
    public function update_login_url($new_url) {
        $old_url = get_option('wpclm_login_url', '/account-login/');
        
        if ($old_url !== $new_url) {
            update_option('wpclm_login_url', $new_url);
            update_option('wpclm_needs_rewrite_flush', 1);
            $this->cached_path = null;
        }
    }

    /**
     * Add rewrite rules
     */
    public function add_rewrite_rules() {
        $login_path = trim($this->escape_output(get_option('wpclm_login_url', '/account-login/'), 'url'), '/');
        add_rewrite_rule(
            '^' . $login_path . '/?$',
            'index.php?wpclm_login_page=1',
            'top'
        );

        if (get_option('wpclm_needs_rewrite_flush')) {
            flush_rewrite_rules();
            delete_option('wpclm_needs_rewrite_flush');
        }
    }

    /**
     * Add query vars
     *
     * @param array $vars Query vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'wpclm_login_page';
        return $vars;
    }
}
