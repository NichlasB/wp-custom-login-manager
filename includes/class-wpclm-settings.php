<?php
/**
 * Settings Class
 *
 * Core settings registration and management. Rendering is delegated to WPCLM_Settings_Renderer.
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 * @since 1.3.0 Refactored to delegate rendering to WPCLM_Settings_Renderer
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Settings {

    /**
     * Singleton instance
     *
     * @var WPCLM_Settings
     */
    private static $instance = null;

    /**
     * Settings tabs
     *
     * @var array
     */
    private $tabs = array();

    /**
     * Current active tab
     *
     * @var string
     */
    private $active_tab = '';

    /**
     * Renderer instance
     *
     * @var WPCLM_Settings_Renderer
     */
    private $renderer;

    /**
     * Get singleton instance
     *
     * @return WPCLM_Settings
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get translatable strings
     *
     * @return array
     */
    public static function get_translatable_strings() {
        return array(
            'email_label' => __('Email Address', 'wp-custom-login-manager'),
            'password_label' => __('Password', 'wp-custom-login-manager'),
            'first_name_label' => __('First Name', 'wp-custom-login-manager'),
            'last_name_label' => __('Last Name', 'wp-custom-login-manager'),
            'login_button' => __('Log In', 'wp-custom-login-manager'),
            'register_button' => __('Create Account', 'wp-custom-login-manager'),
            'reset_button' => __('Reset Password', 'wp-custom-login-manager'),
            'welcome_message' => __('Welcome back!', 'wp-custom-login-manager'),
            'register_message' => __('Create your account', 'wp-custom-login-manager'),
            'reset_message' => __('Reset your password', 'wp-custom-login-manager'),
            'required_field' => __('This field is required.', 'wp-custom-login-manager'),
            'invalid_email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
            'email_exists' => __('This email address is already registered.', 'wp-custom-login-manager'),
            'password_mismatch' => __('The passwords do not match.', 'wp-custom-login-manager'),
            'password_reset' => __('Your password has been reset successfully.', 'wp-custom-login-manager'),
            'registration_success' => __('Registration successful! Please check your email to confirm your account.', 'wp-custom-login-manager'),
            'confirmation_subject' => __('Confirm your registration at %s', 'wp-custom-login-manager'),
            'reset_subject' => __('Password Reset Request for %s', 'wp-custom-login-manager'),
            'forgot_password' => __('Forgot Password?', 'wp-custom-login-manager'),
            'back_to_login' => __('Back to Login', 'wp-custom-login-manager'),
            'required' => __('Required', 'wp-custom-login-manager'),
            'skip_to_content' => __('Skip to main content', 'wp-custom-login-manager'),
        );
    }

    /**
     * Get default image URL
     *
     * @param string $image_type Type of image (background, logo, login-icon, register-icon)
     * @return string URL to default image
     */
    private function get_default_image_url($image_type) {
        $default_images = array(
            'background' => 'default-background.jpg',
            'logo' => 'default-logo.png',
            'login-icon' => 'login-icon.svg',
            'register-icon' => 'register-icon.svg'
        );

        if (!isset($default_images[$image_type])) {
            return '';
        }

        $file_path = WPCLM_PLUGIN_DIR . 'assets/images/' . $default_images[$image_type];
        $file_url = WPCLM_PLUGIN_URL . 'assets/images/' . $default_images[$image_type];

        if (file_exists($file_path)) {
            return $file_url;
        }

        return '';
    }

    /**
     * Get logo URL
     *
     * @return string URL to logo image
     */
    public function get_logo_url() {
        $logo = get_option('wpclm_logo', '');
        
        if (!empty($logo) && $this->image_exists($logo)) {
            return $logo;
        }
        
        $default_logo = $this->get_default_image_url('logo');
        if (!empty($default_logo)) {
            return $default_logo;
        }
        
        return '';
    }

    /**
     * Get background image URL
     *
     * @return string URL to background image
     */
    public function get_background_image_url() {
        $background = get_option('wpclm_background_image', '');
        
        if (!empty($background) && $this->image_exists($background)) {
            return $background;
        }
        
        $default_background = $this->get_default_image_url('background');
        if (!empty($default_background)) {
            return $default_background;
        }
        
        return '';
    }

    /**
     * Get icon URL
     *
     * @param string $icon_type Type of icon (login-icon, register-icon)
     * @return string URL to icon
     */
    public function get_icon_url($icon_type) {
        return $this->get_default_image_url($icon_type);
    }

    /**
     * Check if image exists and is accessible
     *
     * Uses transient caching for external URLs to avoid repeated HTTP requests.
     *
     * @param string $url URL to check
     * @return boolean
     */
    private function image_exists($url) {
        if (empty($url)) {
            return false;
        }

        // Local plugin assets - check filesystem directly
        if (strpos($url, WPCLM_PLUGIN_URL) === 0) {
            $file_path = str_replace(WPCLM_PLUGIN_URL, WPCLM_PLUGIN_DIR, $url);
            return file_exists($file_path);
        }
        
        // Local site assets - check filesystem directly
        if (strpos($url, get_site_url()) === 0) {
            $file_path = str_replace(get_site_url(), ABSPATH, $url);
            return file_exists($file_path);
        }
        
        // External URL - use cached check to avoid repeated HTTP requests
        $cache_key = 'wpclm_img_' . md5($url);
        $cached_result = get_transient($cache_key);
        
        if (false !== $cached_result) {
            return $cached_result === 'exists';
        }
        
        $response = wp_remote_head($url, array(
            'timeout' => 5,
            'redirection' => 3
        ));

        if (is_wp_error($response)) {
            // Cache negative result for shorter period (5 minutes)
            set_transient($cache_key, 'missing', 5 * MINUTE_IN_SECONDS);
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        $exists = $status >= 200 && $status < 400;
        
        // Cache result (1 hour for existing, 5 minutes for missing)
        set_transient($cache_key, $exists ? 'exists' : 'missing', $exists ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
        
        return $exists;
    }

    /**
     * Validate image upload
     */
    public function validate_image_upload($file, $type = 'image') {
        $allowed_types = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'svg'          => 'image/svg+xml'
        );

        $file_type = wp_check_filetype($file['name'], $allowed_types);

        if (!$file_type['type']) {
            return new WP_Error('invalid_type', __('Invalid file type', 'wp-custom-login-manager'));
        }

        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File is too large', 'wp-custom-login-manager'));
        }

        return true;
    }

    /**
     * Get image URL with fallback
     */
    public function get_image_url($option_name, $default_type) {
        $url = get_option($option_name, '');

        if (!empty($url) && $this->image_exists($url)) {
            return $url;
        }

        return $this->get_default_image_url($default_type);
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Admin-only: settings pages, menu, and renderer
        if (is_admin()) {
            $this->init_tabs();
            $this->renderer = WPCLM_Settings_Renderer::get_instance();

            add_action('admin_menu', array($this, 'add_menu_page'));
            add_action('admin_init', array($this, 'register_settings'));
            add_filter('plugin_action_links_' . WPCLM_PLUGIN_BASENAME, array($this, 'add_settings_link'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
    }

    /**
     * Initialize settings tabs
     */
    private function init_tabs() {
        $this->tabs = array(
            'general' => __('General Settings', 'wp-custom-login-manager'),
            'design' => __('Design Settings', 'wp-custom-login-manager'),
            'email' => __('Email Templates', 'wp-custom-login-manager'),
            'security' => __('Security Settings', 'wp-custom-login-manager'),
            'messages' => __('Error Messages', 'wp-custom-login-manager')
        );

        $this->active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    }

    /**
     * Add settings menu page
     */
    public function add_menu_page() {
        add_menu_page(
            __('Custom Login Manager', 'wp-custom-login-manager'),
            __('Custom Login', 'wp-custom-login-manager'),
            'manage_options',
            'wpclm-settings',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            30
        );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Existing plugin links
     * @return array Modified plugin links
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wpclm-settings'),
            __('Settings', 'wp-custom-login-manager')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General Settings
        register_setting('wpclm_general_settings', 'wpclm_login_url');
        register_setting('wpclm_general_settings', 'wpclm_login_page_title');
        register_setting('wpclm_general_settings', 'wpclm_login_page_description');
        register_setting('wpclm_general_settings', 'wpclm_disable_registration');
        register_setting('wpclm_general_settings', 'wpclm_logo');
        register_setting('wpclm_general_settings', 'wpclm_logo_width');
        register_setting('wpclm_general_settings', 'wpclm_login_welcome_text');
        register_setting('wpclm_general_settings', 'wpclm_register_welcome_text');
        register_setting('wpclm_general_settings', 'wpclm_lostpassword_welcome_text');
        register_setting('wpclm_general_settings', 'wpclm_resetpass_welcome_text');
        register_setting('wpclm_general_settings', 'wpclm_setpassword_welcome_text');
        register_setting('wpclm_general_settings', 'wpclm_login_redirect');
        register_setting('wpclm_general_settings', 'wpclm_registration_redirect');
        register_setting('wpclm_general_settings', 'wpclm_default_role');
        register_setting('wpclm_general_settings', 'wpclm_terms_url');
        register_setting('wpclm_general_settings', 'wpclm_privacy_url');
        register_setting('wpclm_general_settings', 'wpclm_wc_redirect');
        register_setting('wpclm_general_settings', 'wpclm_logged_in_redirect');
        register_setting('wpclm_general_settings', 'wpclm_remember_me_duration');

        // Design Settings
        register_setting('wpclm_design_settings', 'wpclm_background_image');
        register_setting('wpclm_design_settings', 'wpclm_custom_css');
        register_setting('wpclm_design_settings', 'wpclm_button_background_color');
        register_setting('wpclm_design_settings', 'wpclm_button_text_color');
        register_setting('wpclm_design_settings', 'wpclm_link_color');
        register_setting('wpclm_design_settings', 'wpclm_login_form_background_color');
        register_setting('wpclm_design_settings', 'wpclm_email_background_color');
        register_setting('wpclm_design_settings', 'wpclm_heading_color');
        register_setting('wpclm_design_settings', 'wpclm_text_color');

        // Email Settings
        register_setting('wpclm_email_settings', 'wpclm_confirmation_email_template');
        register_setting('wpclm_email_settings', 'wpclm_reset_email_template');

        // Security Settings
        register_setting('wpclm_security_settings', 'wpclm_minify_assets');
        register_setting('wpclm_security_settings', 'wpclm_disable_wp_login');
        register_setting('wpclm_security_settings', 'wpclm_contact_url', array(
            'type' => 'string',
            'default' => '/contact/',
            'sanitize_callback' => 'esc_url_raw'
        ));
        register_setting('wpclm_security_settings', 'wpclm_show_contact_help', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
        register_setting('wpclm_security_settings', 'wpclm_allow_role_emails', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        // Cloudflare Turnstile Settings
        register_setting('wpclm_security_settings', 'wpclm_turnstile_enabled');
        register_setting('wpclm_security_settings', 'wpclm_turnstile_site_key');
        register_setting('wpclm_security_settings', 'wpclm_turnstile_secret_key');
        register_setting('wpclm_security_settings', 'wpclm_turnstile_forms', array(
            'type' => 'array',
            'default' => array('register')
        ));

        // Reoon Email Verification Settings
        register_setting('wpclm_security_settings', 'wpclm_email_verification_enabled');
        register_setting('wpclm_security_settings', 'wpclm_reoon_api_key');
        register_setting('wpclm_security_settings', 'wpclm_reoon_verification_mode', array(
            'type' => 'string',
            'default' => 'quick'
        ));

        // Debug Settings
        register_setting('wpclm_security_settings', 'wpclm_enable_debugging', array(
            'type' => 'boolean',
            'description' => 'Enable debug logging for troubleshooting',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));

        // Rate Limiting Settings
        register_setting('wpclm_security_settings', 'wpclm_rate_limit_max_attempts', array(
            'type' => 'number',
            'description' => 'Maximum number of login attempts before lockout',
            'default' => 6,
            'sanitize_callback' => array($this, 'sanitize_number_field')
        ));
        register_setting('wpclm_security_settings', 'wpclm_rate_limit_lockout_duration', array(
            'type' => 'number',
            'description' => 'Duration of lockout in seconds',
            'default' => 900,
            'sanitize_callback' => array($this, 'sanitize_number_field')
        ));
        register_setting('wpclm_security_settings', 'wpclm_rate_limit_monitoring_period', array(
            'type' => 'number',
            'description' => 'Period in seconds during which attempts are counted',
            'default' => 3600,
            'sanitize_callback' => array($this, 'sanitize_number_field')
        ));

        // Error Messages Settings
        register_setting('wpclm_message_settings', 'wpclm_message_login_failed');
        register_setting('wpclm_message_settings', 'wpclm_message_email_exists');
        register_setting('wpclm_message_settings', 'wpclm_message_registration_disabled');
        register_setting('wpclm_message_settings', 'wpclm_message_password_mismatch');
        register_setting('wpclm_message_settings', 'wpclm_message_weak_password');
        register_setting('wpclm_message_settings', 'wpclm_message_invalid_email');
        register_setting('wpclm_message_settings', 'wpclm_message_required_fields');

        // Login URL validation filter
        add_filter('pre_update_option_wpclm_login_url', array($this, 'pre_update_login_url'), 10, 2);
    }

    /**
     * Normalize login URL before saving
     *
     * @param string $new_value New option value
     * @param string $old_value Old option value
     * @return string
     */
    public function pre_update_login_url($new_value, $old_value) {
        if (!empty($new_value)) {
            $new_value = '/' . trim($new_value, '/') . '/';
        } else {
            $new_value = '/account-login/';
        }

        if ($new_value !== $old_value) {
            update_option('wpclm_needs_rewrite_flush', 1);
        }

        return $new_value;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php
                foreach ($this->tabs as $tab => $name) {
                    $class = ($tab === $this->active_tab) ? ' nav-tab-active' : '';
                    printf(
                        '<a class="nav-tab%s" href="?page=wpclm-settings&tab=%s">%s</a>',
                        esc_attr($class),
                        esc_attr($tab),
                        esc_html($name)
                    );
                }
                ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                switch ($this->active_tab) {
                    case 'general':
                        settings_fields('wpclm_general_settings');
                        $this->renderer->render_general_settings();
                        break;
                    case 'design':
                        settings_fields('wpclm_design_settings');
                        $this->renderer->render_design_settings();
                        break;
                    case 'email':
                        settings_fields('wpclm_email_settings');
                        $this->renderer->render_email_settings();
                        break;
                    case 'security':
                        settings_fields('wpclm_security_settings');
                        $this->renderer->render_security_settings();
                        break;
                    case 'messages':
                        settings_fields('wpclm_message_settings');
                        $this->renderer->render_messages_settings();
                        break;
                }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_wpclm-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'wpclm-admin-settings',
            WPCLM_PLUGIN_URL . 'assets/css/admin-settings.css',
            array(),
            WPCLM_VERSION
        );

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_style(
            'wp-jquery-ui-dialog',
            includes_url('css/jquery-ui-dialog.min.css'),
            array(),
            WPCLM_VERSION
        );
        
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-tooltip');

        wp_enqueue_script(
            'wpclm-admin-settings',
            WPCLM_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery', 'wp-color-picker', 'jquery-ui-dialog', 'jquery-ui-tooltip'),
            WPCLM_VERSION,
            true
        );

        wp_localize_script('wpclm-admin-settings', 'wpclm_admin', array(
            'logo_upload_title' => __('Choose Logo', 'wp-custom-login-manager'),
            'logo_upload_button' => __('Set Logo', 'wp-custom-login-manager'),
            'bg_upload_title' => __('Choose Background Image', 'wp-custom-login-manager'),
            'bg_upload_button' => __('Set Background', 'wp-custom-login-manager'),
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'loading_text' => __('Loading...', 'wp-custom-login-manager'),
            'clearing_text' => __('Clearing...', 'wp-custom-login-manager'),
            'view_log_text' => __('View Log', 'wp-custom-login-manager'),
            'clear_log_text' => __('Clear Log', 'wp-custom-login-manager'),
            'refresh_log_text' => __('Refresh', 'wp-custom-login-manager'),
            'download_log_text' => __('Download', 'wp-custom-login-manager'),
            'log_viewer_title' => __('Debug Log Viewer', 'wp-custom-login-manager'),
            'confirm_clear_log' => __('Are you sure you want to clear the debug log?', 'wp-custom-login-manager'),
            'log_cleared_message' => __('Debug log has been cleared.', 'wp-custom-login-manager'),
            'error_message' => __('An error occurred. Please try again.', 'wp-custom-login-manager'),
            'nonce' => wp_create_nonce('wpclm-debug-nonce')
        ));
    }

    /**
     * Sanitize number field
     *
     * @param mixed $value The value to sanitize
     * @return int Sanitized value
     */
    public function sanitize_number_field($value) {
        $number = absint($value);
        $field = str_replace('sanitize_option_', '', current_filter());
        
        switch ($field) {
            case 'wpclm_rate_limit_max_attempts':
                $min = 1;
                $max = 100;
                $default = 6;
                break;
            case 'wpclm_rate_limit_lockout_duration':
                $min = 60;
                $max = 86400;
                $default = 900;
                break;
            case 'wpclm_rate_limit_monitoring_period':
                $min = 300;
                $max = 86400;
                $default = 3600;
                break;
            default:
                return $number;
        }
        
        if ($number < $min || $number > $max) {
            return $default;
        }
        
        return $number;
    }
}

// Initialize Settings
WPCLM_Settings::get_instance();
