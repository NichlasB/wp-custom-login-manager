<?php
/**
 * Redirects Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Redirects {
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
        if (!isset($_GET['action']) || $_GET['action'] !== 'deactivate') {
            // Only add redirect handler if we're not deactivating
            add_action('init', array($this, 'handle_early_redirect'), 1);
        }
    }

    /**
     * Handle early redirects for login pages
     */
    public function handle_early_redirect() {
        if (!did_action('init')) {
            return;
        }

        // Allow WordPress to handle logout nonce verification
        if (isset($_GET['action']) && $_GET['action'] === 'logout' && isset($_GET['_wpnonce'])) {
            return;
        }

        // First check: Redirect from wp-login.php to custom login page
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false && 
            !isset($_GET['direct_login']) && 
            (!isset($_GET['action']) || $_GET['action'] !== 'logout') &&
            get_option('wpclm_disable_wp_login', 1)) {

            if (!is_user_logged_in()) {
                $login_page = get_posts(array(
                    'post_type' => 'page',
                    'posts_per_page' => 1,
                    'meta_key' => '_wp_page_template',
                    'meta_value' => 'wpclm-login-template.php'
                ));

                if (!empty($login_page)) {
                    wp_redirect(home_url('/account-login/'));
                    exit;
                }
            }
        }

            // Second check: Redirect logged-in users away from login page
            if (is_user_logged_in()) {
                // Check if current page is login page by URL and not a logout request
                if ((strpos($_SERVER['REQUEST_URI'], 'login') !== false || 
                     strpos($_SERVER['REQUEST_URI'], 'account-login') !== false) && 
                    (!isset($_GET['action']) || $_GET['action'] !== 'logout')) {
                
                // For admins, redirect to wp-admin
                if (current_user_can('administrator')) {
                    wp_redirect(admin_url());
                    exit;
                }
                
                // For other users, get redirect URL from settings
                $redirect_url = get_option('wpclm_logged_in_redirect', '/my-account/');
                if (empty($redirect_url)) {
                    $redirect_url = '/my-account/';
                }
                
                // Ensure URL is absolute
                if (strpos($redirect_url, 'http') !== 0) {
                    $redirect_url = home_url($redirect_url);
                }
                
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
}

// Initialize Redirects
WPCLM_Redirects::get_instance();