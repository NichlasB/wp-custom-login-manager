<?php
/**
 * Frontend Assets Class
 *
 * Handles script and style enqueueing for the login page
 *
 * @package WPCustomLoginManager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Frontend_Assets {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Turnstile instance
     */
    private $turnstile;

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
        // Initialize Turnstile if available
        if (class_exists('WPCLM_Turnstile')) {
            $this->turnstile = WPCLM_Turnstile::get_instance();
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
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

        // Determine current action for conditional asset loading
        $current_action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'login';

        // Add Turnstile script only if enabled for the current form action
        $turnstile_needed = false;
        if ($this->turnstile) {
            if ($current_action === 'login' && $this->turnstile->is_enabled_for('login')) {
                $turnstile_needed = true;
            } elseif ($current_action === 'register' && $this->turnstile->is_enabled_for('register')) {
                $turnstile_needed = true;
            } elseif (in_array($current_action, array('lostpassword', 'resetpass', 'setpassword'), true) && $this->turnstile->is_enabled_for('reset')) {
                $turnstile_needed = true;
            }
        }

        if ($turnstile_needed) {
            wp_enqueue_script(
                'cf-turnstile',
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileReady',
                array(),
                null,
                true
            );
            
            // Add inline script to handle Turnstile ready event
            wp_add_inline_script('cf-turnstile', "
                function onTurnstileReady() {
                    // Dispatch custom event when Turnstile is ready
                    window.dispatchEvent(new Event('turnstile_ready'));
                }
            ");
        }

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

        // Only enqueue password strength scripts on forms that need them (resetpass/setpassword/register)
        $needs_password_strength = in_array($current_action, array('register', 'resetpass', 'setpassword'), true);
        $forms_dependencies = array('jquery');

        if ($needs_password_strength) {
            wp_enqueue_script('password-strength-meter');
            wp_enqueue_script('wp-util');
            $forms_dependencies = array('jquery', 'password-strength-meter', 'wp-util');
        }

        // Enqueue main forms script
        wp_enqueue_script(
            'wpclm-forms',
            $this->get_asset_url('assets/js/forms.js'),
            $forms_dependencies,
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

        // Enqueue password strength customization only when needed
        if ($needs_password_strength) {
            wp_enqueue_script(
                'wpclm-password-strength',
                $this->get_asset_url('assets/js/password-strength.js'),
                array('jquery', 'password-strength-meter', 'wp-util', 'wpclm-forms'),
                WPCLM_VERSION,
                true
            );

            // Localize password strength script
            wp_localize_script('wpclm-password-strength', 'wpclm_password_strength', array(
                'very_weak' => __('Very weak', 'wp-custom-login-manager'),
                'weak' => __('Weak', 'wp-custom-login-manager'),
                'medium' => __('Medium', 'wp-custom-login-manager'),
                'strong' => __('Strong', 'wp-custom-login-manager'),
                'mismatch' => __('Passwords do not match', 'wp-custom-login-manager'),
            ));
        }

        // Localize scripts
        wp_localize_script('wpclm-forms', 'wpclm_forms', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpclm-forms-nonce'),
            'messages' => array(
                'required' => __('This field is required.', 'wp-custom-login-manager'),
                'required_field' => __('%s is required', 'wp-custom-login-manager'),
                'email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
                'invalid_email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
                'password_match' => __('Passwords do not match.', 'wp-custom-login-manager'),
                'password_strength' => __('Password is too weak.', 'wp-custom-login-manager'),
                'password_length' => __('Password must be at least 12 characters long.', 'wp-custom-login-manager'),
                'password_uppercase' => __('Password must include at least one uppercase letter.', 'wp-custom-login-manager'),
                'password_lowercase' => __('Password must include at least one lowercase letter.', 'wp-custom-login-manager'),
                'password_number' => __('Password must include at least one number.', 'wp-custom-login-manager'),
                'security_verification_loading' => __('Security verification loading...', 'wp-custom-login-manager'),
                'security_verification_required' => __('Please complete the security verification before submitting.', 'wp-custom-login-manager'),
                'password_strength_label' => __('Password strength:', 'wp-custom-login-manager'),
            )
        ));

        // Only enqueue email verification script on registration form
        if ($current_action === 'register') {
            wp_enqueue_script(
                'wpclm-email-verification',
                $this->get_asset_url('assets/js/email-verification.js'),
                array('jquery'),
                WPCLM_VERSION,
                true
            );

            wp_localize_script('wpclm-email-verification', 'wpclm_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpclm_verify_email'),
                'verifying_long' => __('Please wait while we verify your email address. This may take up to a minute...', 'wp-custom-login-manager'),
                'invalid_format' => __('Please enter a valid email address', 'wp-custom-login-manager'),
                'error_occurred' => __('An error occurred while verifying the email', 'wp-custom-login-manager'),
                'verification_pending' => __('Please wait for email verification to complete', 'wp-custom-login-manager')
            ));
        }
    }
}
