<?php
/**
 * Forms Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Forms {

    /**
     * Rate limiter instance
     *
     * @var WPCLM_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Sanitize and escape form data
     *
     * @param string $data The data to sanitize and escape
     * @param string $context The context for escaping (html, attr, url, etc.)
     * @return string
     */
    private function escape_output($data, $context = 'html') {
        $data = trim($data);
        
        switch ($context) {
            case 'attr':
            return esc_attr($data);
            case 'url':
            return esc_url($data);
            case 'js':
            return esc_js($data);
            case 'textarea':
            return esc_textarea($data);
            case 'html':
            return wp_kses_post($data);
            default:
            return esc_html($data);
        }
    }

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
    * Get icon URL
    *
    * @param string $icon_type Type of icon (login or register)
    * @return string URL to icon
    */
    private function get_icon_url($icon_type) {
        $settings = WPCLM_Settings::get_instance();
        return $settings->get_icon_url($icon_type . '-icon');
    }

    /**
    * Render button with icon
    *
    * @param string $type Button type (login or register)
    * @param string $text Button text
    */
    private function render_button($type, $text) {
        $settings = WPCLM_Settings::get_instance();
        $icon_url = $this->get_icon_url($type);
        ?>
        <button type="submit" class="wpclm-button wpclm-button-<?php echo $this->escape_output($type, 'attr'); ?>">
            <?php if ($icon_url): ?>
                <img src="<?php echo $this->escape_output($icon_url, 'url'); ?>" 
                alt="" 
                class="wpclm-button-icon"
                width="24" 
                height="24"
                aria-hidden="true">
            <?php endif; ?>
            <span><?php echo $this->escape_output($text, 'html'); ?></span>
        </button>
        <?php
    }

    /**
    * Constructor
    */
    private function __construct() {
        add_action('init', array($this, 'handle_form_submissions'), 15); // Higher priority than default
        
        // Initialize Messages instance
        $this->messages = WPCLM_Messages::get_instance();

        // Initialize Rate Limiter instance
        $this->rate_limiter = WPCLM_Rate_Limiter::get_instance();

        // Add session handling
        add_action('init', function() {
            if (!session_id()) {
                session_start();
            }
        });

        add_action('template_redirect', array($this, 'redirect_logged_in_users'), 1);
        add_action('wp_logout', array($this, 'handle_logout_redirect'));

        // Replace default login page - using earlier hooks
        add_action('plugins_loaded', array($this, 'init_login_hooks'));

        // Authentication hooks
        add_action('init', function() {
            add_filter('authenticate', array($this, 'check_auth_redirect'), 1, 1);
            add_action('wp_logout', array($this, 'force_login_redirect'));
            add_action('admin_init', array($this, 'check_admin_access'));
        }, 1);

        // Filter template redirect for protected pages
        add_action('template_redirect', function() {
            // Don't redirect if user is logged in or if we're already on the login page
            if (is_user_logged_in() || $this->is_login_url()) {
                return;
            }

            // Check if current page requires authentication
            $protected_paths = apply_filters('wpclm_protected_paths', array(
                'my-account'
            ));

            $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            $path_segments = explode('/', $current_path);

            foreach ($protected_paths as $protected_path) {
                if (in_array($protected_path, $path_segments)) {
                    $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
                    $redirect_to = home_url($_SERVER['REQUEST_URI']);
                    $login_url = add_query_arg('redirect_to', urlencode($redirect_to), $login_url);
                    wp_safe_redirect($login_url);
                    exit;
                }
            }
        }, 1);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp', array($this, 'redirect_logged_in_users'), 1);
        add_action('template_redirect', array($this, 'redirect_logged_in_users'), 1);

        // Handle form submissions
        add_action('init', array($this, 'handle_form_submissions'));

        // Add body class
        add_filter('body_class', array($this, 'add_body_class'));

        // Handle custom login URL
        add_action('parse_request', array($this, 'handle_custom_login_url'), 1);

        // Add rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', function($vars) {
            $vars[] = 'wpclm_login_page';
            return $vars;
        });

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
    * Initialize login hooks
    */
    public function init_login_hooks() {
        if (get_option('wpclm_disable_wp_login', 1)) {
            add_action('init', array($this, 'maybe_redirect_login_page'), 1);
            add_action('login_init', array($this, 'maybe_redirect_login_page'), 1);
            add_filter('login_url', array($this, 'get_login_page_url'), 10, 3);
            add_filter('site_url', array($this, 'filter_login_url'), 10, 4);
        }
    }

    /**
    * Get asset URL
    *
    * @param string $asset_path Path to the asset file
    * @return string URL to the asset
    */
    private function get_asset_url($asset_path) {
        $use_minified = get_option('wpclm_minify_assets', 0);
        
        if ($use_minified) {
            $ext = pathinfo($asset_path, PATHINFO_EXTENSION);
            $min_path = str_replace('.' . $ext, '.min.' . $ext, $asset_path);
            
            if (file_exists(WPCLM_PLUGIN_DIR . $min_path)) {
                return $this->escape_output(WPCLM_PLUGIN_URL . $min_path, 'url');
            }
        }
        
        return $this->escape_output(WPCLM_PLUGIN_URL . $asset_path, 'url');
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
     * Get login page URL
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
    * Check if current URL is login page
    */
    private function is_login_url() {
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_path = trim($current_path, '/');
        $login_path = trim(get_option('wpclm_login_url', '/account-login/'), '/');
        return $current_path === $login_path;
    }

/**
* Enqueue scripts and styles
*/
public function enqueue_scripts() {
    $load_assets = false;

    // Load assets if we're on our custom login URL
    $current_url = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $login_url = trim(get_option('wpclm_login_url', '/account-login/'), '/');
    if ($current_url === $login_url) {
        $load_assets = true;
    }

    // Also check for AJAX requests to our forms
    if (wp_doing_ajax() && isset($_POST['wpclm_action'])) {
        $load_assets = true;
    }

    // Return if assets aren't needed
    if (!$load_assets) {
        return;
    }

    // Enqueue main stylesheet with high priority
    wp_enqueue_style(
        'wpclm-forms',
        $this->get_asset_url('assets/css/forms.css'),
        array(),
        WPCLM_VERSION,
        'all'
    );

    // Enqueue Dashicons for password toggle
    wp_enqueue_style('dashicons');

    // Add body class fix for admin bar
    $custom_css = "
    body.admin-bar.wpclm-template-page {
        min-height: calc(100vh - 32px);
    }
    @media screen and (max-width: 782px) {
        body.admin-bar.wpclm-template-page {
            min-height: calc(100vh - 46px);
        }
    }";
    wp_add_inline_style('wpclm-forms', $custom_css);

        // Get color settings
    $button_background_color = get_option('wpclm_button_background_color', '#2271B1');
    $button_text_color = get_option('wpclm_button_text_color', '#FFFFFF');
    $link_color = get_option('wpclm_link_color', '#2271B1');
    $login_form_background_color = get_option('wpclm_login_form_background_color', '#F5F5F5');
    
        // Add dynamic colors with high specificity
    $custom_css = "
    .wpclm-form-wrapper .wpclm-button {
        background-color: {$button_background_color} !important;
        color: {$button_text_color} !important;
        border-color: {$button_background_color} !important;
    }
    
    .wpclm-form-wrapper .wpclm-button:hover {
        background-color: " . $this->adjust_brightness($button_background_color, -15) . " !important;
    }
    
    .wpclm-form-wrapper .form-links a,
    .wpclm-form-wrapper .wpclm-link,
    .wpclm-form-wrapper .terms-privacy a {
        color: {$link_color} !important;
        text-decoration: none !important;
        border-bottom: 1px dotted {$link_color} !important;
        padding-bottom: 1px !important;
        transition: all 0.2s ease !important;
    }
    
    .wpclm-form-wrapper .form-links a:hover,
    .wpclm-form-wrapper .wpclm-link:hover,
    .wpclm-form-wrapper .terms-privacy a:hover {
        color: " . $this->adjust_brightness($link_color, -15) . " !important;
        border-bottom-color: " . $this->adjust_brightness($link_color, -15) . " !important;
        padding-bottom: 3px !important;
    }
    
    .wpclm-form-wrapper .form-links a:focus,
    .wpclm-form-wrapper .wpclm-link:focus,
    .wpclm-form-wrapper .terms-privacy a:focus {
        outline: 2px solid {$link_color} !important;
        outline-offset: 2px !important;
    }
    
    .wpclm-form-container {
        background-color: {$login_form_background_color} !important;
    }
    ";

    // Add custom CSS from settings
    $custom_css .= get_option('wpclm_custom_css', '');

    // Add inline styles with higher specificity
    wp_add_inline_style('wpclm-forms', $custom_css);

    // Enqueue WordPress password strength meter scripts
    wp_enqueue_script('password-strength-meter');
    wp_enqueue_script('wp-util');

    // Enqueue main forms script
    wp_enqueue_script(
        'wpclm-forms',
        $this->get_asset_url('assets/js/forms.js'),
        array('jquery', 'password-strength-meter', 'wp-util'),
        WPCLM_VERSION,
        true
    );

    // Add password toggle functionality
    wp_add_inline_script('wpclm-forms', '
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector(".dashicons");

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("dashicons-visibility");
                icon.classList.add("dashicons-hidden");
                button.setAttribute("aria-label", "' . esc_js(__('Hide password', 'wp-custom-login-manager')) . '");
                } else {
                    input.type = "password";
                    icon.classList.remove("dashicons-hidden");
                    icon.classList.add("dashicons-visibility");
                    button.setAttribute("aria-label", "' . esc_js(__('Show password', 'wp-custom-login-manager')) . '");
                }
            }');

    // Enqueue password strength customization
    wp_enqueue_script(
        'wpclm-password-strength',
        $this->get_asset_url('assets/js/password-strength.js'),
        array('jquery', 'password-strength-meter', 'wp-util', 'wpclm-forms'),
        WPCLM_VERSION,
        true
    );

    // Localize scripts
    wp_localize_script('wpclm-forms', 'wpclm_forms', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpclm-forms-nonce'),
        'messages' => array(
            'required' => __('This field is required.', 'wp-custom-login-manager'),
            'email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
            'password_match' => __('Passwords do not match.', 'wp-custom-login-manager'),
            'password_strength' => __('Password is too weak.', 'wp-custom-login-manager'),
        )
    ));

    // Localize password strength script
    wp_localize_script('wpclm-password-strength', 'wpclm_password_strength', array(
        'very_weak' => __('Very weak', 'wp-custom-login-manager'),
        'weak' => __('Weak', 'wp-custom-login-manager'),
        'medium' => __('Medium', 'wp-custom-login-manager'),
        'strong' => __('Strong', 'wp-custom-login-manager'),
        'mismatch' => __('Passwords do not match', 'wp-custom-login-manager'),
    ));
}


/**
 * Adjust color brightness
 *
 * @param string $hex Hex color code
 * @param int $steps Steps to adjust (-255 to 255)
 * @return string Adjusted hex color
 */
private function adjust_brightness($hex, $steps) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Render login page
 */
public function render_login_page() {
    if (is_user_logged_in()) {
        $redirect_url = get_option('wpclm_logged_in_redirect', home_url('/my-account/'));

        if (empty($redirect_url) || !filter_var($redirect_url, FILTER_VALIDATE_URL)) {
            $redirect_url = home_url('/my-account/');
        }

        $redirect_url = apply_filters('wpclm_logged_in_redirect_url', $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'login';

    $text_color = get_option('wpclm_text_color', '#4A5568');
        echo '<style>
            :root {
                --wpclm-text-color: ' . esc_attr($text_color) . ';
            }
        </style>';
    
    ob_start();
    ?>
    <div class="wpclm-container">
        <a href="#wpclm-main-content" class="skip-link screen-reader-text">
            <?php _e('Skip to main content', 'wp-custom-login-manager'); ?>
        </a>
        <div id="wpclm-main-content" class="wpclm-background">
            <?php $this->render_background(); ?>
        </div>
        
        <div class="wpclm-form-container">
            <div class="wpclm-form-wrapper">
                <?php
                $this->render_logo();
                $this->render_welcome_message($action);
                
                switch ($action) {
                    case 'register':
                    $this->render_register_form();
                    break;
                    case 'lostpassword':
                    $this->render_lost_password_form();
                    break;
                    case 'resetpass':
                    $this->render_reset_password_form();
                    break;
                    case 'setpassword':
                    $this->render_password_setup_form();
                    break;
                    default:
                    $this->render_login_form();
                    break;
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render reset password form
 */
private function render_reset_password_form() {
    $rp_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    $rp_login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';

    if (empty($rp_key) || empty($rp_login)) {
        return $this->render_error_message($this->escape_output(__('Invalid password setup link.', 'wp-custom-login-manager'), 'html'));
    }
    ?>
    <div class="wpclm-form resetpass-form">
        <?php
        // Display success message if password was changed
        if (isset($_GET['password']) && $_GET['password'] === 'changed') {
            echo '<div class="wpclm-success-message" role="alert">';
            echo $this->escape_output($this->messages->get_message('password_reset_success'), 'html');
            echo '</div>';
        }
        // Display error message if there was an error
        if (isset($_GET['error'])) {
            echo '<div class="wpclm-error-message" role="alert">';
            echo $this->escape_output(urldecode($_GET['error']), 'html');
            echo '</div>';
        }
        ?>
        <form id="wpclm-resetpass-form" method="post">
            <?php wp_nonce_field('wpclm-resetpass-nonce', 'wpclm_resetpass_nonce'); ?>
            <input type="hidden" name="wpclm_action" value="resetpass">
            <input type="hidden" name="rp_key" value="<?php echo esc_attr($rp_key); ?>">
            <input type="hidden" name="rp_login" value="<?php echo esc_attr($rp_login); ?>">

            <div class="form-field">
                <label for="pass1"><?php _e('New Password', 'wp-custom-login-manager'); ?></label>
                <input type="password" 
                name="pass1" 
                id="pass1" 
                class="password-input" 
                required 
                aria-required="true">
                <div class="password-strength-meter"></div>
                <div class="password-requirements">
                    <h4><?php echo esc_html__('Password must:', 'wp-custom-login-manager'); ?></h4>
                    <ul>
                        <li class="requirement length">
                            <span class="check">✓</span> <?php echo esc_html__('Be at least 12 characters long', 'wp-custom-login-manager'); ?>
                        </li>
                        <li class="requirement uppercase">
                            <span class="check">✓</span> <?php echo esc_html__('Include at least one uppercase letter', 'wp-custom-login-manager'); ?>
                        </li>
                        <li class="requirement lowercase">
                            <span class="check">✓</span> <?php echo esc_html__('Include at least one lowercase letter', 'wp-custom-login-manager'); ?>
                        </li>
                        <li class="requirement number">
                            <span class="check">✓</span> <?php echo esc_html__('Include at least one number', 'wp-custom-login-manager'); ?>
                        </li>
                    </ul>
                </div> 
            </div>

            <div class="form-field">
                <label for="pass2"><?php _e('Confirm New Password', 'wp-custom-login-manager'); ?></label>
                <input type="password" 
                name="pass2" 
                id="pass2" 
                required 
                aria-required="true">
            </div>

            <div class="form-field submit-button">
                <button type="submit" class="wpclm-button">
                    <?php _e('Save Password', 'wp-custom-login-manager'); ?>
                </button>
            </div>

            <div class="form-links">
                <a href="<?php echo esc_url(remove_query_arg(array('action', 'key', 'login', 'password'))); ?>">
                    <?php _e('Back to Login', 'wp-custom-login-manager'); ?>
                </a>
            </div>
        </form>
    </div>
    <?php
}

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['wpclm_action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['wpclm_action']);
        
        // Each form type will verify its own specific nonce in its process method
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
     * Add body class
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
 */
public function check_auth_redirect($user) {
    // Check if redirect is enabled
    if (!get_option('wpclm_disable_wp_login', 1)) {
        return $user;
    }

    // Allow direct access with special parameter
    if (isset($_GET['direct_login']) && $_GET['direct_login'] === 'true') {
        return $user;
    }

    // Don't redirect certain actions
    $allowed_actions = array('logout', 'postpass', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'activate');
    if (isset($_GET['action']) && in_array($_GET['action'], $allowed_actions)) {
        return $user;
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

    return $user;
}

/**
 * Check admin access
 */
public function check_admin_access() {
    if (!is_admin() || wp_doing_ajax()) {
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
 * Filter login URLs
 */
public function filter_login_url($url, $path, $scheme, $blog_id) {
    if ($path === 'wp-login.php' && !isset($_GET['direct_login'])) {
        return home_url(get_option('wpclm_login_url', '/account-login/'));
    }
    return $url;
}

/**
 * Check if current page is login page
 */
private function is_login_page() {
    return $this->is_login_url();
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
        // Redirect to login page if registrations are disabled and user tries to register
        if (isset($_GET['action']) && $_GET['action'] === 'register' && get_option('wpclm_disable_registration', 0)) {
            $login_url = home_url($this->escape_output(get_option('wpclm_login_url', '/account-login/'), 'url'));
            wp_safe_redirect($login_url);
            exit;
        }

        status_header(200);
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <?php 
            // Remove existing title filter from SEO plugins
            remove_all_filters('pre_get_document_title');
            remove_all_filters('document_title_parts');
            
            // Add our custom title filter
            add_filter('pre_get_document_title', function() {
                $title = get_option('wpclm_login_page_title', __('Login', 'wp-custom-login-manager'));
                return esc_html($title) . ' - ' . get_bloginfo('name');
            });
            
            // Let WordPress output the title first
            wp_head(); 

            // Then output meta description
            $description = get_option('wpclm_login_page_description', '');
            if (!empty($description)) {
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            }
            ?>
        </head>
        <body <?php body_class('wpclm-template-page'); ?>>
            <?php wp_body_open(); ?>
            <?php echo $this->render_login_page(); ?>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Update login URL and flush rewrite rules
 */
public function update_login_url($new_url) {
    $old_url = get_option('wpclm_login_url', '/account-login/');
    
    if ($old_url !== $new_url) {
        update_option('wpclm_login_url', $new_url);
        flush_rewrite_rules();
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
    }

    /**
    * Get login page path
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
    * Redirect logged-in users from login page
    */
    public function redirect_logged_in_users() {

        // Check if we're on the login page
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $login_path = trim(get_option('wpclm_login_url', '/account-login/'), '/');

        // If user is logged in and on login page, redirect
        if (is_user_logged_in() && $current_path === $login_path) {

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

   /**
    * Render background
    */
   private function render_background() {
    $settings = WPCLM_Settings::get_instance();
    $background_image = $settings->get_background_image_url();
    
    if ($background_image) {
        echo '<div class="wpclm-background">';
        echo '<div class="wpclm-background-image" style="background-image: url(' . $this->escape_output($background_image, 'url') . ');"></div>';
        echo '</div>';
    }
}

    /**
    * Render logo
    */
    private function render_logo() {
        $settings = WPCLM_Settings::get_instance();
        $logo = $settings->get_logo_url();
        $logo_width = get_option('wpclm_logo_width', '200');

        if ($logo) {
            echo '<div class="wpclm-logo">';
            echo '<a href="' . $this->escape_output(home_url('/'), 'url') . '" title="' . $this->escape_output(get_bloginfo('name'), 'attr') . '">';
            echo '<img src="' . $this->escape_output($logo, 'url') . '" 
            alt="' . $this->escape_output(get_bloginfo('name'), 'attr') . '" 
            style="max-width: ' . $this->escape_output($logo_width, 'attr') . 'px"
            width="' . $this->escape_output($logo_width, 'attr') . '"
            height="auto">';
            echo '</a>';
            echo '</div>';
        }
    }

 /**
 * Render welcome message
 * 
 * @param string $form_type The type of form (login, register, lostpassword, resetpass, setpassword)
*/
 private function render_welcome_message($form_type = 'login') {
    // Get the appropriate welcome message based on form type
    $message = '';
    switch ($form_type) {
        case 'login':
        $registrations_disabled = get_option('wpclm_disable_registration', 0);
        if ($registrations_disabled) {
            $default_message = sprintf(
                '<strong>%s</strong><br>%s',
                __('Welcome back!', 'wp-custom-login-manager'),
                __('You can log in or reset your password.', 'wp-custom-login-manager')
            );
            $custom_message = get_option('wpclm_login_welcome_text');
            $message = empty($custom_message) ? $default_message : $custom_message;
        } else {
            $default_message = sprintf(
                '<strong>%s</strong><br>%s',
                __('Welcome back!', 'wp-custom-login-manager'),
                __('You can log in, create a new account, or reset your password.', 'wp-custom-login-manager')
            );
            $custom_message = get_option('wpclm_login_welcome_text');
            $message = empty($custom_message) ? $default_message : $custom_message;
        }
        break;

        case 'register':
        $default_message = sprintf(
            '<strong>%s</strong><br>%s',
            __('Create your account', 'wp-custom-login-manager'),
            __('Fill in your details to get started.', 'wp-custom-login-manager')
        );
        $custom_message = get_option('wpclm_register_welcome_text');
        $message = empty($custom_message) ? $default_message : $custom_message;
        break;

        case 'lostpassword':
        $default_message = sprintf(
            '<strong>%s</strong><br>%s',
            __('Reset your password', 'wp-custom-login-manager'),
            __('Enter your email address to receive a password reset link.', 'wp-custom-login-manager')
        );
        $custom_message = get_option('wpclm_lostpassword_welcome_text');
        $message = empty($custom_message) ? $default_message : $custom_message;
        break;

        case 'resetpass':
        $default_message = sprintf(
            '<strong>%s</strong><br>%s',
            __('Set your new password', 'wp-custom-login-manager'),
            __('Choose a strong password for your account.', 'wp-custom-login-manager')
        );
        $custom_message = get_option('wpclm_resetpass_welcome_text');
        $message = empty($custom_message) ? $default_message : $custom_message;
        break;

        case 'setpassword':
        $default_message = sprintf(
            '<strong>%s</strong><br>%s',
            __('Set your password', 'wp-custom-login-manager'),
            __('Choose a password to complete your account setup.', 'wp-custom-login-manager')
        );
        $custom_message = get_option('wpclm_setpassword_welcome_text');
        $message = empty($custom_message) ? $default_message : $custom_message;
        break;
    }

    echo '<div class="wpclm-welcome-message">' . $this->escape_output($message, 'html') . '</div>';
}

    /**
    * Render login form
    */
    private function render_login_form() {
        $redirect_to = isset($_GET['redirect_to']) ? $this->escape_output($_GET['redirect_to'], 'url') : '';
        ?>
        <div class="wpclm-form login-form" role="form">
            <form id="wpclm-login-form" method="post" novalidate>
                <?php wp_nonce_field('wpclm-login-nonce', 'wpclm_login_nonce'); ?>
                <?php
                // Display messages from wpclm_login_messages filter
                $login_messages = apply_filters('wpclm_login_messages', array());
                if (!empty($login_messages) && is_array($login_messages)) {
                    foreach ($login_messages as $message) {
                        echo '<div class="wpclm-message" role="alert">';
                        echo $this->escape_output($message, 'html');
                        echo '</div>';
                    }
                }
                ?>
                <?php
                // Display confirmation messages
                $confirmation = isset($_GET['confirmation']) ? $this->escape_output($_GET['confirmation'], 'attr') : '';
                if (isset($_GET['confirmation'])) {
                    switch ($confirmation) {
                        case 'success':
                        echo '<div class="wpclm-success-message" role="alert">';
                        echo esc_html__('Your email has been confirmed. Please check your email for your login details.', 'wp-custom-login-manager');
                        echo '</div>';
                        break;
                        case 'expired':
                        echo '<div class="wpclm-error-message" role="alert">';
                        echo esc_html__('The confirmation link has expired. Please register again.', 'wp-custom-login-manager');
                        echo '</div>';
                        break;
                        case 'failed':
                        echo '<div class="wpclm-error-message" role="alert">';
                        echo $this->escape_output(__('There was an error confirming your email. Please try registering again.', 'wp-custom-login-manager'), 'html');
                        echo '</div>';
                        break;
                    }
                }

                // Display logout success message
                if (isset($_GET['logged_out']) && $_GET['logged_out'] === 'true') {
                    echo '<div class="wpclm-success-message" role="alert">';
                    echo esc_html__('You have been successfully logged out.', 'wp-custom-login-manager');
                    echo '</div>';
                }

                // Display registration messages
                $registration = isset($_GET['registration']) ? $this->escape_output($_GET['registration'], 'attr') : '';
                if (isset($_GET['registration'])) {
                    switch ($registration) {
                        case 'check_email':
                        echo '<div class="wpclm-success-message" role="alert">';
                        echo esc_html($this->messages->get_message('confirmation_sent'));
                        echo '</div>';
                        break;
                        case 'success':
                        echo '<div class="wpclm-success-message" role="alert">';
                        echo isset($_GET['message']) ? 
                        $this->escape_output(urldecode($_GET['message']), 'html') : 
                        $this->escape_output(__('Registration successful!', 'wp-custom-login-manager'), 'html');
                        echo '</div>';
                        break;
                        case 'completed':
                        echo '<div class="wpclm-success-message" role="alert">';
                        echo isset($_GET['message']) ? 
                        $this->escape_output(urldecode($_GET['message']), 'html') : 
                        $this->escape_output(__('Your password has been set successfully. Please log in.', 'wp-custom-login-manager'), 'html');
                        echo '</div>';
                        break;
                    }
                }

                // Display password reset success message
                $password_status = isset($_GET['password']) ? $this->escape_output($_GET['password'], 'attr') : '';
                if (isset($_GET['password']) && $password_status === 'changed') {
                    echo '<div class="wpclm-success-message" role="alert">';
                    echo isset($_GET['message']) ? 
                    $this->escape_output(urldecode($_GET['message']), 'html') : 
                    $this->escape_output(__('Your password has been reset successfully.', 'wp-custom-login-manager'), 'html');
                    echo '</div>';
                }

                // Display error message if present in URL
                $login_error = isset($_GET['login_error']) ? $this->escape_output(urldecode($_GET['login_error']), 'html') : '';
                if (isset($_GET['login_error'])) {
                    echo '<div class="wpclm-error-message" role="alert">';
                    echo $login_error;
                    echo '</div>';
                }
                ?>
                <input type="hidden" name="wpclm_action" value="login">
                <input type="hidden" name="redirect_to" value="<?php echo $this->escape_output($redirect_to, 'attr'); ?>">

                <div class="form-field">
                    <label for="user_login" id="user_login_label">
                        <?php _e('Email Address', 'wp-custom-login-manager'); ?>
                        <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                    </label>
                    <input type="email" 
                    name="user_login" 
                    id="user_login" 
                    aria-required="true"
                    aria-labelledby="user_login_label"
                    aria-describedby="user_login_desc"
                    required>
                    <span id="user_login_desc" class="description screen-reader-text">
                        <?php _e('Enter your email address', 'wp-custom-login-manager'); ?>
                    </span>
                </div>

                <div class="form-field">
                    <label for="user_pass" id="user_pass_label">
                        <?php _e('Password', 'wp-custom-login-manager'); ?>
                        <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                    </label>
                    <div class="password-field-wrapper">
                        <input type="password" 
                        name="user_pass" 
                        id="user_pass" 
                        aria-required="true"
                        aria-labelledby="user_pass_label"
                        aria-describedby="user_pass_desc"
                        required>
                        <button type="button" 
                        class="password-toggle" 
                        aria-label="<?php _e('Toggle password visibility', 'wp-custom-login-manager'); ?>"
                        onclick="togglePassword('user_pass', this)">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
                <span id="user_pass_desc" class="description screen-reader-text">
                    <?php _e('Enter your password', 'wp-custom-login-manager'); ?>
                </span>
            </div>

            <div class="form-field remember-me">
                <label for="rememberme">
                    <input type="checkbox" 
                    name="rememberme" 
                    id="rememberme" 
                    value="forever"
                    aria-describedby="rememberme_desc">
                    <?php _e('Remember Me', 'wp-custom-login-manager'); ?>
                </label>
                <span id="rememberme_desc" class="screen-reader-text">
                    <?php _e('Keep me signed in', 'wp-custom-login-manager'); ?>
                </span>
            </div>

            <div class="form-field submit-button">
                <button type="submit" 
                class="wpclm-button"
                aria-label="<?php esc_attr_e('Log in to your account', 'wp-custom-login-manager'); ?>">
                <?php _e('Log In', 'wp-custom-login-manager'); ?>
            </button>
        </div>

        <div class="form-links" role="navigation" aria-label="<?php esc_attr_e('Additional options', 'wp-custom-login-manager'); ?>">
            <?php if (!get_option('wpclm_disable_registration', 0)): ?>
                <a href="<?php echo esc_url(add_query_arg('action', 'register')); ?>"
                   aria-label="<?php esc_attr_e('Create a new account', 'wp-custom-login-manager'); ?>">
                   <?php _e('Create Account', 'wp-custom-login-manager'); ?>
               </a>
           <?php endif; ?>
           <a href="<?php echo esc_url(add_query_arg('action', 'lostpassword')); ?>"
               aria-label="<?php esc_attr_e('Reset your forgotten password', 'wp-custom-login-manager'); ?>">
               <?php _e('Forgot Password?', 'wp-custom-login-manager'); ?>
           </a>
       </div>
   </form>
</div>
<?php
}

/**
 * Render registration form
*/
private function render_register_form() {

    // Parse the URL manually to get query parameters
    $current_url = $_SERVER['REQUEST_URI'];
    $query_params = [];
    $url_parts = parse_url($current_url);
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
    }

    if (get_option('wpclm_disable_registration', 0)) {
        echo '<div class="wpclm-form register-form">';
        echo '<div class="wpclm-error-message" role="alert">';
        echo esc_html($this->messages->get_message('registration_disabled'));
        echo '</div>';
        echo '<div class="form-links">';
        echo '<a href="' . esc_url(remove_query_arg('action')) . '">';
        echo esc_html__('Back to Login', 'wp-custom-login-manager');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        return;
    }
    ?>

    <div class="wpclm-form register-form">
        <?php
// Display success message
        if (isset($_GET['success'])) {
            echo '<div class="wpclm-success-message" role="alert">';
            echo $this->escape_output(urldecode($_GET['success']), 'html');
            echo '</div>';
        }

        ?>
        <?php
        // Display error messages using parsed query parameters
        if (isset($query_params['error'])) {

            echo '<div class="wpclm-error-message" role="alert">';
            if ($query_params['error'] === 'email_exists') {
                echo $this->escape_output(__('This email address is already registered.', 'wp-custom-login-manager'), 'html');
            } else {
                $error_message = $this->messages->get_message($query_params['error']);
                if (!empty($error_message)) {
                    echo $this->escape_output($error_message, 'html');
                } else {
                    echo $this->escape_output(__('An error occurred. Please try again.', 'wp-custom-login-manager'), 'html');
                }
            }
            echo '</div>';
        }

        // Display success messages
        if (isset($query_params['registration'])) {
            switch ($query_params['registration']) {
                case 'check_email':
                echo '<div class="wpclm-success-message" role="alert">';
                echo $this->escape_output($this->messages->get_message('confirmation_sent'), 'html');
                echo '</div>';
                break;
                case 'success':
                echo '<div class="wpclm-success-message" role="alert">';
                echo isset($query_params['message']) ? 
                $this->escape_output(urldecode($query_params['message']), 'html') : 
                $this->escape_output(__('Registration successful!', 'wp-custom-login-manager'), 'html');
                echo '</div>';
                break;
            }
        }
        ?>
        <form id="wpclm-register-form" method="post" action="<?php echo esc_url($current_url); ?>">
            <?php wp_nonce_field('wpclm-register-nonce', 'wpclm_register_nonce'); ?>
            <input type="hidden" name="wpclm_action" value="register">

            <div class="form-row">
                <div class="form-field">
                    <label for="first_name"><?php _e('First Name', 'wp-custom-login-manager'); ?></label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>

                <div class="form-field">
                    <label for="last_name"><?php _e('Last Name', 'wp-custom-login-manager'); ?></label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>
            </div>

            <div class="form-field">
                <label for="user_email"><?php _e('Email Address', 'wp-custom-login-manager'); ?></label>
                <input type="email" name="user_email" id="user_email" required>
            </div>

            <div class="form-field terms-privacy">
                <label>
                    <input type="checkbox" name="terms_privacy" required>
                    <?php 
                    $terms_url = get_option('wpclm_terms_url');
                    $privacy_url = get_option('wpclm_privacy_url');
                    printf(
                        __('By creating an account, you agree to our %1$s and %2$s', 'wp-custom-login-manager'),
                        '<a href="' . esc_url($terms_url) . '" target="_blank">' . __('Terms', 'wp-custom-login-manager') . '</a>',
                        '<a href="' . esc_url($privacy_url) . '" target="_blank">' . __('Privacy Policy', 'wp-custom-login-manager') . '</a>'
                    );
                    ?>
                </label>
            </div>

            <div class="form-field submit-button">
                <button type="submit" class="wpclm-button">
                    <?php _e('Create Account', 'wp-custom-login-manager'); ?>
                </button>
            </div>

            <div class="form-links">
                <a href="<?php echo esc_url(remove_query_arg('action')); ?>">
                    <?php _e('Back to Login', 'wp-custom-login-manager'); ?>
                </a>
            </div>
        </form>
    </div>
    <?php
}

    /**
     * Render lost password form
     */
    private function render_lost_password_form() {
        ?>
        <div class="wpclm-form lostpassword-form">
            <?php
            // Display messages
            if (isset($_GET['checkemail']) && $_GET['checkemail'] == 'confirm') {
                echo '<div class="wpclm-success-message" role="alert">';
                echo $this->escape_output($this->messages->get_message('password_reset_sent'), 'html');
                echo '</div>';
            }
            if (isset($_GET['error'])) {
                echo '<div class="wpclm-error-message" role="alert">';
                echo $this->escape_output(urldecode($_GET['error']), 'html');
                echo '</div>';
            }
            // Display form error message if it exists in the URL
            if (isset($_GET['form_error'])) {
                echo '<div class="wpclm-error-message" role="alert">';
                echo $this->escape_output($this->messages->get_message('invalid_email'), 'html');
                echo '</div>';
            }
            ?>
            <form id="wpclm-lostpassword-form" method="post">
                <?php wp_nonce_field('wpclm-lostpass-nonce', 'wpclm_lostpass_nonce'); ?>
                <input type="hidden" name="wpclm_action" value="lostpassword">

                <div class="form-field">
                    <label for="user_login"><?php _e('Email Address', 'wp-custom-login-manager'); ?></label>
                    <input type="email" 
                    name="user_login" 
                    id="user_login" 
                    required 
                    aria-required="true"
                    aria-describedby="lostpassword-email-desc">
                    <span id="lostpassword-email-desc" class="screen-reader-text">
                        <?php _e('Enter your email address to reset your password', 'wp-custom-login-manager'); ?>
                    </span>
                </div>

                <div class="form-field submit-button">
                    <button type="submit" class="wpclm-button">
                        <?php _e('Reset Password', 'wp-custom-login-manager'); ?>
                    </button>
                </div>

                <div class="form-links">
                    <a href="<?php echo esc_url(remove_query_arg(array('action', 'checkemail', 'error'))); ?>">
                        <?php _e('Back to Login', 'wp-custom-login-manager'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }

    /**
    * Render password setup form
    */
    private function render_password_setup_form() {
        $rp_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $rp_login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';

        if (empty($rp_key) || empty($rp_login)) {
            return $this->render_error_message(__('Invalid password setup link.', 'wp-custom-login-manager'));
        }
        ?>
        <div class="wpclm-form password-setup-form">
            <h2><?php _e('Set Your Password', 'wp-custom-login-manager'); ?></h2>
            <form id="wpclm-password-setup-form" method="post">
                <?php wp_nonce_field('wpclm-setpass-nonce', 'wpclm_setpass_nonce'); ?>
                <input type="hidden" name="wpclm_action" value="setpassword">
                <input type="hidden" name="rp_key" value="<?php echo esc_attr($rp_key); ?>">
                <input type="hidden" name="rp_login" value="<?php echo esc_attr($rp_login); ?>">

                <div class="form-field">
                    <label for="pass1"><?php _e('New Password', 'wp-custom-login-manager'); ?></label>
                    <input type="password" name="pass1" id="pass1" class="password-input" required>
                    <div class="password-strength-meter"></div>
                    <div class="password-requirements">
                        <h4><?php echo esc_html__('Password must:', 'wp-custom-login-manager'); ?></h4>
                        <ul>
                            <li class="requirement length">
                                <span class="check">✓</span> <?php echo esc_html__('Be at least 12 characters long', 'wp-custom-login-manager'); ?>
                            </li>
                            <li class="requirement uppercase">
                                <span class="check">✓</span> <?php echo esc_html__('Include at least one uppercase letter', 'wp-custom-login-manager'); ?>
                            </li>
                            <li class="requirement lowercase">
                                <span class="check">✓</span> <?php echo esc_html__('Include at least one lowercase letter', 'wp-custom-login-manager'); ?>
                            </li>
                            <li class="requirement number">
                                <span class="check">✓</span> <?php echo esc_html__('Include at least one number', 'wp-custom-login-manager'); ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="form-field">
                    <label for="pass2"><?php _e('Confirm New Password', 'wp-custom-login-manager'); ?></label>
                    <input type="password" name="pass2" id="pass2" required>
                </div>

                <div class="form-field submit-button">
                    <button type="submit" class="wpclm-button">
                        <?php _e('Set Password', 'wp-custom-login-manager'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
    * Process login form
    */
    private function process_login() {
        if (!isset($_POST['wpclm_login_nonce']) || !wp_verify_nonce($_POST['wpclm_login_nonce'], 'wpclm-login-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'error' => urlencode($this->messages->get_message('invalid_nonce'))
            )));
            exit;
        }
        // Check rate limiting before processing login
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

        // Validate required fields
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

        $creds = array(
            'user_login'    => isset($_POST['user_login']) ? sanitize_email($_POST['user_login']) : '',
            'user_password' => isset($_POST['user_pass']) ? $_POST['user_pass'] : '',
            'remember'      => isset($_POST['rememberme'])
        );

        // Validate required fields
        if (empty($creds['user_login']) || empty($creds['user_password'])) {
            $error_message = $this->messages->get_message('required_fields');
            wp_safe_redirect(add_query_arg('login_error', urlencode($error_message)));
            exit;
        }

        // Set custom cookie expiration if "Remember Me" is checked
        if (isset($_POST['rememberme'])) {
            add_filter('auth_cookie_expiration', function($length) {
                $days = absint(get_option('wpclm_remember_me_duration', 30));
                // Ensure at least 1 day and no more than 365 days
                $days = max(1, min(365, $days));
                return $days * DAY_IN_SECONDS;
            }, 999);
        }

        $user = wp_signon($creds, is_ssl());

        // Remove the filter after login attempt
        if (isset($_POST['rememberme'])) {
            remove_all_filters('auth_cookie_expiration', 999);
        }

        if (is_wp_error($user)) {
            $error_message = $this->messages->get_message('login_failed');

            // Track failed login attempt
            WPCLM_Auth::get_instance()->track_failed_login($creds['user_login']);

            // Record rate limit attempt
            $this->rate_limiter->record_attempt($ip_address);

            wp_safe_redirect(add_query_arg('login_error', urlencode($error_message)));
            exit;
        }

        // Clear any failed login attempts
        WPCLM_Auth::get_instance()->clear_login_attempts($creds['user_login']);

        $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : '';
        if (empty($redirect_to)) {
            $redirect_to = get_option('wpclm_login_redirect', admin_url());
        }

        wp_safe_redirect($redirect_to);
        exit;
    }

    /**
    * Process registration form
    */
    private function process_registration() {
        if (!isset($_POST['wpclm_register_nonce']) || !wp_verify_nonce($_POST['wpclm_register_nonce'], 'wpclm-register-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => urlencode($this->messages->get_message('invalid_nonce'))
            )));
            exit;
        }

        // Check rate limiting before processing registration
        $ip_address = $this->rate_limiter->get_client_ip();
        $rate_check = $this->rate_limiter->check_rate_limit($ip_address);

        if (!$rate_check['allowed']) {
            $error_message = sprintf(
                __('Too many registration attempts. Please try again in %d seconds.', 'wp-custom-login-manager'),
                $rate_check['remaining_time']
            );
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => urlencode($error_message)
            )));
            exit;
        }

        remove_all_filters('wp_new_user_notification_email');
        remove_all_filters('wp_new_user_notification_email_admin');
        remove_action('register_new_user', 'wp_send_new_user_notifications');
        remove_action('user_register', 'wp_send_new_user_notifications');

        if (get_option('wpclm_disable_registration', 0)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => $this->messages->get_message('registration_disabled')
            )));
            exit;
        }

        $email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

        if (empty($email) || empty($first_name) || empty($last_name)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => $this->messages->get_message('required_fields')
            )));
            exit;
        }

        if (!is_email($email)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => $this->messages->get_message('invalid_email')
            )));
            exit;
        }

        if (email_exists($email)) {

            $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));
            $redirect_url = add_query_arg(
                array(
                    'action' => 'register',
                    'error' => 'email_exists'
                ),
                $login_url
            );

            wp_safe_redirect($redirect_url);
            exit;
        }

        // Get auth instance for token generation
        $auth = WPCLM_Auth::get_instance();

        // Generate secure confirmation token
        $encrypted_token = $auth->generate_confirmation_token();
        if (!$encrypted_token) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => $this->messages->get_message('registration_failed')
            )));
            exit;
        }

        // Validate token before storing data
        $token_data = $auth->validate_confirmation_token($encrypted_token);
        if (!$token_data) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => $this->messages->get_message('registration_failed')
            )));
            exit;
        }

        // Store registration data in transient using the validated token
        set_transient('wpclm_registration_' . $token_data['token'], array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'timestamp' => time()
        ), DAY_IN_SECONDS);

        // Send confirmation email
        $emails = WPCLM_Emails::get_instance();
        $sent = $emails->send_confirmation_email($email, $first_name, $encrypted_token);

        if (!$sent) {
            delete_transient('wpclm_registration_' . $token_data['token']);
            wp_safe_redirect(add_query_arg(array(
                'action' => 'register',
                'error' => $this->messages->get_message('email_failed')
            )));
            exit;
        }

        // Record successful registration attempt for rate limiting
        $this->rate_limiter->record_attempt($ip_address);

        wp_safe_redirect(add_query_arg(array(
            'action' => 'register',
            'success' => $this->messages->get_message('confirmation_sent')
        )));
        exit;

    }

    /**
    * Process lost password form
    */
    private function process_lost_password() {
        if (!isset($_POST['wpclm_lostpass_nonce']) || !wp_verify_nonce($_POST['wpclm_lostpass_nonce'], 'wpclm-lostpass-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($this->messages->get_message('invalid_nonce'))
            ), $login_url));
            exit;
        }

        $user_login = isset($_POST['user_login']) ? sanitize_email($_POST['user_login']) : '';
        $login_url = home_url(get_option('wpclm_login_url', '/account-login/'));

        // Check rate limiting before processing password reset
        $ip_address = $this->rate_limiter->get_client_ip();
        $rate_check = $this->rate_limiter->check_rate_limit($ip_address);

        if (!$rate_check['allowed']) {
            $error_message = sprintf(
                __('Too many password reset attempts. Please try again in %d minutes and %d seconds.', 'wp-custom-login-manager'),
                floor($rate_check['remaining_time'] / 60),
                $rate_check['remaining_time'] % 60
            );
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($error_message)
            ), $login_url));
            exit;
        }

        if (empty($user_login)) {
            $error_message = $this->messages->get_message('required_fields');
            if ($rate_check['remaining_attempts'] < get_option('wpclm_rate_limit_max_attempts')) {
                $error_message .= ' ' . sprintf(
                    __('You have %d attempts remaining.', 'wp-custom-login-manager'),
                    $rate_check['remaining_attempts']
                );
            }
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($error_message)
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

        // Allow password reset
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($this->messages->get_message('password_reset_failed'))
            ), $login_url));
            exit;
        }

        // Send reset email using our custom email handler
        $emails = WPCLM_Emails::get_instance();
        $sent = $emails->send_reset_password_email($user, $key);

        if (!$sent) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'lostpassword',
                'error' => urlencode($this->messages->get_message('password_reset_failed'))
            ), $login_url));
            exit;
        }

        // Record password reset attempt for rate limiting
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
        if (!isset($_POST['wpclm_resetpass_nonce']) || !wp_verify_nonce($_POST['wpclm_resetpass_nonce'], 'wpclm-resetpass-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'resetpass',
                'error' => urlencode($this->messages->get_message('invalid_nonce'))
            )));
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

        // Verify key / login combo
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

        // Validate password requirements
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

        // Reset password
        reset_password($user, $pass1);

        // Redirect to login page with success message
        wp_safe_redirect(add_query_arg(array(
            'password' => 'changed'
        ), $login_url));
        exit;
    }

    /**
    * Process password setup
    */
    private function process_password_setup() {
        if (!isset($_POST['wpclm_setpass_nonce']) || !wp_verify_nonce($_POST['wpclm_setpass_nonce'], 'wpclm-setpass-nonce')) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'setpassword',
                'error' => urlencode($this->messages->get_message('invalid_nonce'))
            )));
            exit;
        }

        $rp_key = isset($_POST['rp_key']) ? sanitize_text_field($_POST['rp_key']) : '';
        $rp_login = isset($_POST['rp_login']) ? sanitize_text_field($_POST['rp_login']) : '';
        $pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        $pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';

        if (empty($rp_key) || empty($rp_login) || empty($pass1) || empty($pass2)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'setpassword',
                'error' => $this->messages->get_message('required_fields')
            ), home_url(get_option('wpclm_login_url', '/account-login/'))));
            exit;
        }

        // Verify key / login combo
        $user = check_password_reset_key($rp_key, $rp_login);
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'setpassword',
                'error' => $this->messages->get_message('invalid_key')
            ), home_url(get_option('wpclm_login_url', '/account-login/'))));
            exit;
        }

        if ($pass1 !== $pass2) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'setpassword',
                'error' => $this->messages->get_message('password_mismatch')
            ), home_url(get_option('wpclm_login_url', '/account-login/'))));
            exit;
        }

        // Validate password requirements
        $auth = WPCLM_Auth::get_instance();
        $validation_result = $auth->validate_password($pass1);
        if (is_wp_error($validation_result)) {
            wp_safe_redirect(add_query_arg(array(
                'action' => 'setpassword',
                'error' => $validation_result->get_error_message()
            ), home_url(get_option('wpclm_login_url', '/account-login/'))));
            exit;
        }

        // Reset the password
        reset_password($user, $pass1);

        // Instead of trying to log in automatically, redirect to login page with success message
        wp_safe_redirect(add_query_arg(array(
            'action' => 'login',
            'registration' => 'completed',
            'message' => urlencode(__('Your password has been set successfully. Please log in with your email and password.', 'wp-custom-login-manager'))
        ), home_url(get_option('wpclm_login_url', '/account-login/'))));
        exit;
    }

    /**
     * Render logged in message
     */
    private function render_logged_in_message() {
        $current_user = wp_get_current_user();
        return sprintf(
            '<div class="wpclm-logged-in-message">%s <a href="%s">%s</a></div>',
            $this->escape_output(
                sprintf(
                    __('You are currently logged in as %s.', 'wp-custom-login-manager'),
                    $current_user->display_name
                ),
                'html'
            ),
            $this->escape_output(wp_logout_url(get_permalink()), 'url'),
            $this->escape_output(__('Log Out', 'wp-custom-login-manager'), 'html')
        );
    }

    /**
     * Render error message
     */
    private function render_error_message($message) {
        return sprintf(
            '<div class="wpclm-error-message">%s</div>',
            $this->escape_output($message, 'html')
        );
    }
}

// Initialize Forms
WPCLM_Forms::get_instance();