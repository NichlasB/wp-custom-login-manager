<?php
/**
 * Forms Class (Facade)
 *
 * This class serves as a facade that coordinates the refactored form components.
 * The original monolithic class (2273 lines) has been split into:
 * - WPCLM_Login_Router: URL routing and rewrite logic
 * - WPCLM_Frontend_Assets: Script and style enqueueing
 * - WPCLM_Access_Control: Redirects and access gates
 * - WPCLM_Form_Submission_Handler: POST processing
 * - WPCLM_Login_Page_Renderer: Page and form rendering
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 * @since 1.3.0 Refactored into facade pattern
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Forms {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Router instance
     *
     * @var WPCLM_Login_Router
     */
    private $router;

    /**
     * Assets instance
     *
     * @var WPCLM_Frontend_Assets
     */
    private $assets;

    /**
     * Access control instance
     *
     * @var WPCLM_Access_Control
     */
    private $access_control;

    /**
     * Form handler instance
     *
     * @var WPCLM_Form_Submission_Handler
     */
    private $form_handler;

    /**
     * Renderer instance
     *
     * @var WPCLM_Login_Page_Renderer
     */
    private $renderer;

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
     * Constructor - Initialize all component classes
     */
    private function __construct() {
        // Initialize component classes
        $this->router = WPCLM_Login_Router::get_instance();
        $this->assets = WPCLM_Frontend_Assets::get_instance();
        $this->access_control = WPCLM_Access_Control::get_instance();
        $this->form_handler = WPCLM_Form_Submission_Handler::get_instance();
        $this->renderer = WPCLM_Login_Page_Renderer::get_instance();

        // Clear message cookies on logout
        add_action('wp_logout', array($this, 'clear_message_cookies'));
    }

    /**
     * Clear message cookies on logout
     */
    public function clear_message_cookies() {
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

    // =========================================================================
    // Delegate methods for backward compatibility
    // These methods delegate to the appropriate component class
    // =========================================================================

    /**
     * Check if current page is login page
     *
     * @return bool
     */
    public function is_login_page() {
        return $this->access_control->is_login_page();
    }

    /**
     * Get login path
     *
     * @return string
     */
    public function get_login_path() {
        return $this->router->get_login_path();
    }

    /**
     * Render login page
     *
     * @return string
     */
    public function render_login_page() {
        return $this->renderer->render_login_page();
    }

    /**
     * Update login URL
     *
     * @param string $new_url New login URL
     */
    public function update_login_url($new_url) {
        $this->router->update_login_url($new_url);
    }

    // =========================================================================
    // Legacy method stubs for backward compatibility
    // These delegate to the new component classes
    // =========================================================================

    /**
     * @deprecated Use WPCLM_Login_Router directly
     */
    public function maybe_redirect_login_page() {
        $this->router->maybe_redirect_login_page();
    }

    /**
     * @deprecated Use WPCLM_Login_Router directly
     */
    public function get_login_page_url($login_url, $redirect, $force_reauth) {
        return $this->router->get_login_page_url($login_url, $redirect, $force_reauth);
    }

    /**
     * @deprecated Use WPCLM_Frontend_Assets directly
     */
    public function enqueue_scripts() {
        $this->assets->enqueue_scripts();
    }

    /**
     * @deprecated Use WPCLM_Access_Control directly
     */
    public function handle_logout_redirect() {
        $this->access_control->handle_logout_redirect();
    }

    /**
     * @deprecated Use WPCLM_Access_Control directly
     */
    public function check_auth_redirect($redirect_to, $requested_redirect_to = '', $user = null) {
        return $this->access_control->check_auth_redirect($redirect_to, $requested_redirect_to, $user);
    }

    /**
     * @deprecated Use WPCLM_Access_Control directly
     */
    public function check_admin_access() {
        $this->access_control->check_admin_access();
    }

    /**
     * @deprecated Use WPCLM_Login_Router directly
     */
    public function filter_login_url($url, $path, $scheme, $blog_id) {
        return $this->router->filter_login_url($url, $path, $scheme, $blog_id);
    }

    /**
     * @deprecated Use WPCLM_Access_Control directly
     */
    public function redirect_logged_in_users() {
        $this->access_control->redirect_logged_in_users();
    }

    /**
     * @deprecated Use WPCLM_Form_Submission_Handler directly
     */
    public function handle_form_submissions() {
        $this->form_handler->handle_form_submissions();
    }

    /**
     * @deprecated Use WPCLM_Access_Control directly
     */
    public function add_body_class($classes) {
        return $this->access_control->add_body_class($classes);
    }

    /**
     * @deprecated Use WPCLM_Login_Router directly
     */
    public function handle_custom_login_url() {
        $this->router->handle_custom_login_url();
    }

    /**
     * @deprecated Use WPCLM_Login_Router directly
     */
    public function add_rewrite_rules() {
        $this->router->add_rewrite_rules();
    }

    /**
     * @deprecated Now handled by router constructor
     */
    public function init_login_hooks() {
        // Now handled by router constructor
    }

    /**
     * @deprecated Use WPCLM_Access_Control directly
     */
    public function force_login_redirect() {
        $this->access_control->force_login_redirect();
    }
}

// Initialize Forms
WPCLM_Forms::get_instance();
