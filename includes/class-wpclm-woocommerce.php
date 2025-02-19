<?php
/**
 * WooCommerce Integration Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_WooCommerce {
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
        // Only initialize if WooCommerce is active
        if ($this->is_woocommerce_active()) {
            $this->init_hooks();
        }
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add WooCommerce roles to role selection
        add_filter('wpclm_available_roles', array($this, 'add_woocommerce_roles'));

        // Add WooCommerce redirect options
        add_filter('wpclm_redirect_options', array($this, 'add_woocommerce_redirects'));

        // Handle WooCommerce-specific redirects
        add_filter('wpclm_login_redirect', array($this, 'handle_woocommerce_redirect'), 10, 2);

        // Add WooCommerce endpoints to allowed redirect URLs
        add_filter('wpclm_allowed_redirect_hosts', array($this, 'allow_woocommerce_endpoints'));

        // Handle order-pay redirects
        add_action('template_redirect', array($this, 'handle_order_pay_redirect'));
        add_filter('wpclm_login_messages', array($this, 'display_checkout_message'));
    }

    /**
     * Add WooCommerce roles to available roles
     */
    public function add_woocommerce_roles($roles) {
        if (isset($roles['customer'])) {
            return $roles;
        }

        $wc_roles = array(
            'customer' => __('Customer', 'wp-custom-login-manager'),
            'shop_manager' => __('Shop Manager', 'wp-custom-login-manager')
        );

        return array_merge($roles, $wc_roles);
    }

    /**
     * Add WooCommerce redirect options
     */
    public function add_woocommerce_redirects($options) {
        $wc_options = array(
            'myaccount' => __('WooCommerce My Account', 'wp-custom-login-manager'),
            'shop' => __('Shop Page', 'wp-custom-login-manager'),
            'cart' => __('Cart Page', 'wp-custom-login-manager'),
            'checkout' => __('Checkout Page', 'wp-custom-login-manager')
        );

        return array_merge($options, $wc_options);
    }

    /**
     * Handle WooCommerce-specific redirects
     */
    public function handle_woocommerce_redirect($redirect_to, $user) {
        $wc_redirect = get_option('wpclm_wc_redirect', '');

        if (empty($wc_redirect)) {
            return $redirect_to;
        }

        switch ($wc_redirect) {
            case 'myaccount':
                return wc_get_page_permalink('myaccount');
            case 'shop':
                return wc_get_page_permalink('shop');
            case 'cart':
                return wc_get_cart_url();
            case 'checkout':
                return wc_get_checkout_url();
            default:
                return $redirect_to;
        }
    }

    /**
     * Allow WooCommerce endpoints for redirects
     */
    public function allow_woocommerce_endpoints($hosts) {
        $wc_hosts = array();

        if (function_exists('wc_get_page_permalink')) {
            $wc_pages = array('myaccount', 'shop', 'cart', 'checkout');
            foreach ($wc_pages as $page) {
                $url = wc_get_page_permalink($page);
                if ($url) {
                    $parsed = parse_url($url);
                    if (isset($parsed['host'])) {
                        $wc_hosts[] = $parsed['host'];
                    }
                }
            }
        }

        return array_merge($hosts, array_unique($wc_hosts));
    }

    /**
     * Handle order-pay redirects
     */
    public function handle_order_pay_redirect() {
        // Check if user is not logged in and URL contains order-pay
        if (!is_user_logged_in() && 
            strpos($_SERVER['REQUEST_URI'], '/checkout/order-pay/') !== false) {
            
            // Get the current full URL including the order-pay part
            $current_url = home_url(add_query_arg(array()));
            
            // Get login URL from plugin settings
            $login_url = home_url(get_option('wpclm_login_url', '/login/'));
            
            // Add the redirect_to parameter to the login URL
            $login_url = add_query_arg('redirect_to', urlencode($current_url), $login_url);
            
            // Add debug logging
            error_log('WooCommerce Order Pay Redirect:');
            error_log('Current URL: ' . $current_url);
            error_log('Login URL: ' . $login_url);
            
            wp_safe_redirect($login_url);
            exit;
        }
    }

    /**
     * Display checkout message on login page if redirected from checkout
     */
    public function display_checkout_message($messages) {
        // Only run if we were redirected from checkout
        if (isset($_GET['redirect_to']) && strpos($_GET['redirect_to'], 'checkout') !== false) {
            $register_url = add_query_arg('action', 'register', remove_query_arg('action'));
            $messages[] = sprintf(
                __('🛒 To checkout and complete your order, please log in or <a href="%s">create an account</a>.', 'wp-custom-login-manager'),
                esc_url($register_url)
            );
        }
        return $messages;
    }

}