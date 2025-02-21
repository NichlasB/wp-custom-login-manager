<?php
/**
 * Settings Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

// Prevent direct access
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
            // Form Labels
            'email_label' => __('Email Address', 'wp-custom-login-manager'),
            'password_label' => __('Password', 'wp-custom-login-manager'),
            'first_name_label' => __('First Name', 'wp-custom-login-manager'),
            'last_name_label' => __('Last Name', 'wp-custom-login-manager'),
            
            // Buttons
            'login_button' => __('Log In', 'wp-custom-login-manager'),
            'register_button' => __('Create Account', 'wp-custom-login-manager'),
            'reset_button' => __('Reset Password', 'wp-custom-login-manager'),
            
            // Messages
            'welcome_message' => __('Welcome back!', 'wp-custom-login-manager'),
            'register_message' => __('Create your account', 'wp-custom-login-manager'),
            'reset_message' => __('Reset your password', 'wp-custom-login-manager'),
            
            // Error Messages
            'required_field' => __('This field is required.', 'wp-custom-login-manager'),
            'invalid_email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
            'email_exists' => __('This email address is already registered.', 'wp-custom-login-manager'),
            'password_mismatch' => __('The passwords do not match.', 'wp-custom-login-manager'),
            
            // Success Messages
            'password_reset' => __('Your password has been reset successfully.', 'wp-custom-login-manager'),
            'registration_success' => __('Registration successful! Please check your email to confirm your account.', 'wp-custom-login-manager'),
            
            // Email Subjects
            'confirmation_subject' => __('Confirm your registration at %s', 'wp-custom-login-manager'),
            'reset_subject' => __('Password Reset Request for %s', 'wp-custom-login-manager'),
            
            // Links
            'forgot_password' => __('Forgot Password?', 'wp-custom-login-manager'),
            'back_to_login' => __('Back to Login', 'wp-custom-login-manager'),
            
            // Accessibility
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

        // Check if file exists
        if (file_exists($file_path)) {
            return $file_url;
        }

        // Log warning if default image is missing
        error_log(sprintf(
            'WPCLM: Default image missing - %s',
            $file_path
        ));

        return '';
    }

/**
 * Get logo URL
 *
 * @return string URL to logo image
 */
public function get_logo_url() {
    $logo = get_option('wpclm_logo');
    
    // Check if custom logo exists and is accessible
    if (!empty($logo) && $this->image_exists($logo)) {
        return $logo;
    }
    
    // Fallback to default logo
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
    $background = get_option('wpclm_background_image');
    
    // Check if custom background exists and is accessible
    if (!empty($background) && $this->image_exists($background)) {
        return $background;
    }
    
    // Fallback to default background
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
 * @param string $url URL to check
 * @return boolean
 */
private function image_exists($url) {
    // For local URLs within the plugin directory
    if (strpos($url, WPCLM_PLUGIN_URL) === 0) {
        $file_path = str_replace(
            WPCLM_PLUGIN_URL,
            WPCLM_PLUGIN_DIR,
            $url
        );
        return file_exists($file_path);
    }
    
    // For URLs within the WordPress installation
    if (strpos($url, get_site_url()) === 0) {
        $file_path = str_replace(
            get_site_url(),
            ABSPATH,
            $url
        );
        return file_exists($file_path);
    }
    
    // For external URLs, do a light-weight check
    $headers = get_headers($url);
    return $headers && strpos($headers[0], '200') !== false;
}

/**
 * Validate image upload
 */
public function validate_image_upload($file, $type = 'image') {
    // List of allowed image types
    $allowed_types = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'svg'          => 'image/svg+xml'
    );

    // Get file type
    $file_type = wp_check_filetype($file['name'], $allowed_types);

    // Validate file type
    if (!$file_type['type']) {
        return new WP_Error('invalid_type', __('Invalid file type', 'wp-custom-login-manager'));
    }

    // Validate file size
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', __('File is too large', 'wp-custom-login-manager'));
    }

    return true;
}

/**
 * Get image URL with fallback
 */
public function get_image_url($option_name, $default_type) {
    $url = get_option($option_name);
    
    if ($this->image_exists($url)) {
        return $url;
    }

    return $this->get_default_image_url($default_type);
}

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize tabs
        $this->init_tabs();

        // Add menu item
        add_action('admin_menu', array($this, 'add_menu_page'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . WPCLM_PLUGIN_BASENAME, array($this, 'add_settings_link'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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

        // Set active tab
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
       
        // Welcome messages for different forms      
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
        register_setting('wpclm_security_settings', 'wpclm_minify_assets');
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
        register_setting('wpclm_security_settings', 'wpclm_rate_limit_max_attempts');
        register_setting('wpclm_security_settings', 'wpclm_rate_limit_lockout_duration');
        register_setting('wpclm_security_settings', 'wpclm_rate_limit_monitoring_period');
        register_setting('wpclm_security_settings', 'wpclm_disable_wp_login');
        register_setting('wpclm_security_settings', 'wpclm_contact_url', array(
            'type' => 'string',
            'default' => '/contact/',
            'sanitize_callback' => 'esc_url_raw'
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

        // Add validation and rewrite flush for login URL
        add_filter('pre_update_option_wpclm_login_url', function($new_value, $old_value) {
            // Ensure value starts and ends with forward slash
            if (!empty($new_value)) {
                $new_value = '/' . trim($new_value, '/') . '/';
            } else {
                // If empty, set to default
                $new_value = '/account-login/';
            }

            // Flush rewrite rules if value changed
            if ($new_value !== $old_value) {
                flush_rewrite_rules();
            }

            return $new_value;
        }, 10, 2);
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
                    $this->render_general_settings();
                    break;
                    case 'design':
                    settings_fields('wpclm_design_settings');
                    $this->render_design_settings();
                    break;
                    case 'email':
                    settings_fields('wpclm_email_settings');
                    $this->render_email_settings();
                    break;
                    case 'security':
                    settings_fields('wpclm_security_settings');
                    $this->render_security_settings();
                    break;
                    case 'messages':
                    settings_fields('wpclm_message_settings');
                    $this->render_messages_settings();
                    break;
                }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings tab
     */
    private function render_general_settings() {
        ?>
        <table class="form-table" role="presentation">
            <!-- Login URL -->
            <tr>
                <th scope="row">
                    <label for="wpclm_login_url">
                        <?php _e('Login URL Path', 'wp-custom-login-manager'); ?>
                        <span class="required">*</span>
                    </label>
                </th>
                <td>
                    <input type="text" 
                    name="wpclm_login_url" 
                    id="wpclm_login_url" 
                    value="<?php echo esc_attr(get_option('wpclm_login_url', '/account-login/')); ?>" 
                    class="regular-text"
                    required
                    pattern="^/[a-zA-Z0-9-_/]*/$"
                    aria-required="true">
                    <p class="description">
                        <?php _e('Enter the URL path for the login page (must start and end with "/"). Default: /account-login/', 'wp-custom-login-manager'); ?>
                    </p>
                    <p class="description">
                        <?php _e('Example: /login/ or /account/login/', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <!-- Login Page SEO -->
            <tr>
                <th scope="row">
                    <label for="wpclm_login_page_title">
                        <?php _e('Login Page Title', 'wp-custom-login-manager'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                    name="wpclm_login_page_title" 
                    id="wpclm_login_page_title" 
                    value="<?php echo esc_attr(get_option('wpclm_login_page_title', __('Login', 'wp-custom-login-manager'))); ?>" 
                    class="regular-text">
                    <p class="description">
                        <?php _e('The title tag for the login page (for SEO).', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wpclm_login_page_description">
                        <?php _e('Login Page Description', 'wp-custom-login-manager'); ?>
                    </label>
                </th>
                <td>
                    <textarea name="wpclm_login_page_description" 
                    id="wpclm_login_page_description" 
                    class="large-text" 
                    rows="3"><?php echo esc_textarea(get_option('wpclm_login_page_description', '')); ?></textarea>
                    <p class="description">
                        <?php _e('Meta description for the login page (for SEO).', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <!-- Disable User Registrations -->
            <tr>
                <th scope="row"><?php _e('User Registration', 'wp-custom-login-manager'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                        name="wpclm_disable_registration" 
                        value="1" 
                        <?php checked(get_option('wpclm_disable_registration', 0)); ?>>
                        <?php _e('Disable new user registrations', 'wp-custom-login-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('When enabled, the registration form will be disabled and users will not be able to create new accounts.', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <!-- Logo Upload -->
            <tr>
                <th scope="row"><?php _e('Logo', 'wp-custom-login-manager'); ?></th>
                <td>
                    <?php
                    $logo = get_option('wpclm_logo');
                    $logo_width = get_option('wpclm_logo_width', '200');
                    ?>
                    <div class="wpclm-logo-preview">
                        <?php if ($logo): ?>
                            <img src="<?php echo esc_url($logo); ?>" style="max-width: <?php echo esc_attr($logo_width); ?>px">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="wpclm_logo" id="wpclm_logo" value="<?php echo esc_attr($logo); ?>">
                    <input type="button" class="button" id="wpclm_upload_logo_button" value="<?php _e('Upload Logo', 'wp-custom-login-manager'); ?>">
                    <?php if ($logo): ?>
                        <input type="button" class="button" id="wpclm_remove_logo_button" value="<?php _e('Remove Logo', 'wp-custom-login-manager'); ?>">
                    <?php endif; ?>
                    <p class="description"><?php _e('Recommended size: 200x80px', 'wp-custom-login-manager'); ?></p>
                    
                    <div class="logo-width-setting" style="margin-top: 10px;">
                        <label for="wpclm_logo_width"><?php _e('Logo Width (px):', 'wp-custom-login-manager'); ?></label>
                        <input type="number" name="wpclm_logo_width" id="wpclm_logo_width" value="<?php echo esc_attr($logo_width); ?>" min="50" max="500">
                    </div>
                </td>
            </tr>

            <!-- Welcome Messages -->
<tr>
    <th scope="row"><?php _e('Welcome Messages', 'wp-custom-login-manager'); ?></th>
    <td>
        <h4><?php _e('Login Form Welcome Message', 'wp-custom-login-manager'); ?></h4>
        <?php
        $login_welcome = get_option('wpclm_login_welcome_text', __('Welcome back! You can log in, create a new account, or reset your password.', 'wp-custom-login-manager'));
        wp_editor($login_welcome, 'wpclm_login_welcome_text', array(
            'textarea_name' => 'wpclm_login_welcome_text',
            'textarea_rows' => 3,
            'media_buttons' => false,
            'teeny' => true,
        ));
        ?>
        
        <h4><?php _e('Registration Form Welcome Message', 'wp-custom-login-manager'); ?></h4>
        <?php
        $register_welcome = get_option('wpclm_register_welcome_text', __('Create your account! Fill in your details to get started.', 'wp-custom-login-manager'));
        wp_editor($register_welcome, 'wpclm_register_welcome_text', array(
            'textarea_name' => 'wpclm_register_welcome_text',
            'textarea_rows' => 3,
            'media_buttons' => false,
            'teeny' => true,
        ));
        ?>
        
        <h4><?php _e('Lost Password Form Welcome Message', 'wp-custom-login-manager'); ?></h4>
        <?php
        $lostpass_welcome = get_option('wpclm_lostpassword_welcome_text', __('Reset your password. Enter your email address to receive a password reset link.', 'wp-custom-login-manager'));
        wp_editor($lostpass_welcome, 'wpclm_lostpassword_welcome_text', array(
            'textarea_name' => 'wpclm_lostpassword_welcome_text',
            'textarea_rows' => 3,
            'media_buttons' => false,
            'teeny' => true,
        ));
        ?>
        
        <h4><?php _e('Reset Password Form Welcome Message', 'wp-custom-login-manager'); ?></h4>
        <?php
        $resetpass_welcome = get_option('wpclm_resetpass_welcome_text', __('Set your new password. Choose a strong password for your account.', 'wp-custom-login-manager'));
        wp_editor($resetpass_welcome, 'wpclm_resetpass_welcome_text', array(
            'textarea_name' => 'wpclm_resetpass_welcome_text',
            'textarea_rows' => 3,
            'media_buttons' => false,
            'teeny' => true,
        ));
        ?>
        
        <h4><?php _e('Set Password Form Welcome Message', 'wp-custom-login-manager'); ?></h4>
        <?php
        $setpass_welcome = get_option('wpclm_setpassword_welcome_text', __('Set your password. Choose a password to complete your account setup.', 'wp-custom-login-manager'));
        wp_editor($setpass_welcome, 'wpclm_setpassword_welcome_text', array(
            'textarea_name' => 'wpclm_setpassword_welcome_text',
            'textarea_rows' => 3,
            'media_buttons' => false,
            'teeny' => true,
        ));
        ?>
                </td>
            </tr>

            <!-- Default Role -->
            <tr>
                <th scope="row"><?php _e('Default User Role', 'wp-custom-login-manager'); ?></th>
                <td>
                    <select name="wpclm_default_role" id="wpclm_default_role">
                        <?php
                        $selected_role = get_option('wpclm_default_role', 'subscriber');
                        wp_dropdown_roles($selected_role);
                        ?>
                    </select>
                </td>
            </tr>

            <!-- Remember Me Duration -->
            <tr>
                <th scope="row"><?php _e('Remember Me Duration', 'wp-custom-login-manager'); ?></th>
                <td>
                    <input type="number" 
                    name="wpclm_remember_me_duration" 
                    value="<?php echo esc_attr(get_option('wpclm_remember_me_duration', 30)); ?>" 
                    min="1" 
                    max="365" 
                    style="width: 80px;">
                    <?php _e('days', 'wp-custom-login-manager'); ?>
                    <p class="description">
                        <?php _e('Number of days to keep users logged in when "Remember Me" is checked. Default is 30 days.', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <?php if (class_exists('WooCommerce')): ?>
                <!-- WooCommerce Settings -->
                <tr>
                    <th scope="row"><?php _e('WooCommerce Redirect', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <select name="wpclm_wc_redirect" id="wpclm_wc_redirect">
                            <option value=""><?php _e('Default Redirect', 'wp-custom-login-manager'); ?></option>
                            <option value="myaccount" <?php selected(get_option('wpclm_wc_redirect'), 'myaccount'); ?>>
                                <?php _e('My Account', 'wp-custom-login-manager'); ?>
                            </option>
                            <option value="shop" <?php selected(get_option('wpclm_wc_redirect'), 'shop'); ?>>
                                <?php _e('Shop Page', 'wp-custom-login-manager'); ?>
                            </option>
                            <option value="cart" <?php selected(get_option('wpclm_wc_redirect'), 'cart'); ?>>
                                <?php _e('Cart Page', 'wp-custom-login-manager'); ?>
                            </option>
                            <option value="checkout" <?php selected(get_option('wpclm_wc_redirect'), 'checkout'); ?>>
                                <?php _e('Checkout Page', 'wp-custom-login-manager'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Select where to redirect WooCommerce users after login.', 'wp-custom-login-manager'); ?>
                        </p>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- Redirect URLs -->
            <tr>
                <th scope="row"><?php _e('Login Redirect URL', 'wp-custom-login-manager'); ?></th>
                <td>
                    <input type="text" name="wpclm_login_redirect" class="regular-text" 
                    value="<?php echo esc_attr(get_option('wpclm_login_redirect', '/wp-admin/')); ?>">
                    <p class="description">
                        <?php _e('Enter the URL where users should be redirected after login. Leave blank for default.', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Registration Redirect URL', 'wp-custom-login-manager'); ?></th>
                <td>
                    <input type="text" name="wpclm_registration_redirect" class="regular-text" 
                    value="<?php echo esc_attr(get_option('wpclm_registration_redirect', '/wp-admin/')); ?>">
                    <p class="description">
                        <?php _e('Enter the URL where users should be redirected after registration. Leave blank for default.', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Logged-In User Redirect URL', 'wp-custom-login-manager'); ?></th>
                <td>
                    <input type="text" name="wpclm_logged_in_redirect" class="regular-text" 
                    value="<?php echo esc_attr(get_option('wpclm_logged_in_redirect', '/my-account/')); ?>">
                    <p class="description">
                        <?php _e('Enter the URL where to redirect already logged-in users who try to access the login page. Default: /my-account/', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <!-- Terms and Privacy Policy URLs -->
            <tr>
                <th scope="row"><?php _e('Terms URL', 'wp-custom-login-manager'); ?></th>
                <td>
                    <input type="text" name="wpclm_terms_url" class="regular-text" 
                    value="<?php echo esc_attr(get_option('wpclm_terms_url')); ?>">
                    <p class="description">
                        <?php _e('Enter the URL of your Terms page.', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Privacy Policy URL', 'wp-custom-login-manager'); ?></th>
                <td>
                    <input type="text" name="wpclm_privacy_url" class="regular-text" 
                    value="<?php echo esc_attr(get_option('wpclm_privacy_url')); ?>">
                    <p class="description">
                        <?php _e('Enter the URL of your Privacy Policy page.', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

        /**
        * Render design settings tab
        */
        private function render_design_settings() {
            ?>
            <table class="form-table" role="presentation">
                <!-- Background Image -->
                <tr>
                    <th scope="row"><?php _e('Background Image', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <?php
                        $background_image = get_option('wpclm_background_image');
                        ?>
                        <div class="wpclm-background-preview">
                            <?php if ($background_image): ?>
                                <img src="<?php echo esc_url($background_image); ?>" style="max-width: 300px;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="wpclm_background_image" id="wpclm_background_image" 
                        value="<?php echo esc_attr($background_image); ?>">
                        <input type="button" class="button" id="wpclm_upload_background_button" 
                        value="<?php _e('Upload Background', 'wp-custom-login-manager'); ?>">
                        <?php if ($background_image): ?>
                            <input type="button" class="button" id="wpclm_remove_background_button" 
                            value="<?php _e('Remove Background', 'wp-custom-login-manager'); ?>">
                        <?php endif; ?>
                        <p class="description">
                            <?php _e('Recommended size: 1920x1080px minimum. Will be scaled to fit.', 'wp-custom-login-manager'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Login Form Background Color -->
                <tr>
                    <th scope="row"><?php _e('Login Form Background Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_login_form_background_color" id="wpclm_login_form_background_color" 
                        value="<?php echo esc_attr(get_option('wpclm_login_form_background_color', '#F5F5F5')); ?>" 
                        class="wpclm-color-picker">
                    </td>
                </tr>

                <!-- Email Background Color -->
                <tr>
                    <th scope="row"><?php _e('Email Background Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_email_background_color" id="wpclm_email_background_color" 
                        value="<?php echo esc_attr(get_option('wpclm_email_background_color', '#F5F5F5')); ?>" 
                        class="wpclm-color-picker">
                    </td>
                </tr>

                <!-- Button Background Color -->
                <tr>
                    <th scope="row"><?php _e('Button Background Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_button_background_color" id="wpclm_button_background_color" 
                        value="<?php echo esc_attr(get_option('wpclm_button_background_color', '#2271B1')); ?>" 
                        class="wpclm-color-picker">
                    </td>
                </tr>

                <!-- Button Text Color -->
                <tr>
                    <th scope="row"><?php _e('Button Text Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_button_text_color" id="wpclm_button_text_color" 
                        value="<?php echo esc_attr(get_option('wpclm_button_text_color', '#FFFFFF')); ?>" 
                        class="wpclm-color-picker">
                    </td>
                </tr>

                <!-- Heading Color -->
                <tr>
                    <th scope="row"><?php _e('Heading Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_heading_color" id="wpclm_heading_color" 
                        value="<?php echo esc_attr(get_option('wpclm_heading_color', '#1D2327')); ?>" 
                        class="wpclm-color-picker">
                    </td>
                </tr>

                <!-- Text Color -->
                <tr>
                    <th scope="row"><?php _e('Text Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_text_color" id="wpclm_text_color" 
                        value="<?php echo esc_attr(get_option('wpclm_text_color', '#4A5568')); ?>" 
                        class="wpclm-color-picker">
                        <p class="description">
                            <?php _e('Color for general text, labels, and messages.', 'wp-custom-login-manager'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Link Color -->
                <tr>
                    <th scope="row"><?php _e('Link Color', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <input type="text" name="wpclm_link_color" id="wpclm_link_color" 
                        value="<?php echo esc_attr(get_option('wpclm_link_color', '#2271B1')); ?>" 
                        class="wpclm-color-picker">
                    </td>
                </tr>

                <!-- Custom CSS -->
                <tr>
                    <th scope="row"><?php _e('Custom CSS', 'wp-custom-login-manager'); ?></th>
                    <td>
                        <textarea name="wpclm_custom_css" id="wpclm_custom_css" rows="10" cols="50" 
                        class="large-text code"><?php echo esc_textarea(get_option('wpclm_custom_css')); ?></textarea>
                        <p class="description">
                            <?php _e('Add custom CSS to customize the login page appearance.', 'wp-custom-login-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        }

    /**
     * Render email settings tab
     */
    private function render_email_settings() {
        ?>
        <table class="form-table" role="presentation">
            <!-- Registration Confirmation Email -->
            <tr>
                <th scope="row"><?php _e('Registration Confirmation Email', 'wp-custom-login-manager'); ?></th>
                <td>
                    <?php
                    $confirmation_email = get_option('wpclm_confirmation_email_template', $this->get_default_confirmation_email());
                    wp_editor($confirmation_email, 'wpclm_confirmation_email_template', array(
                        'textarea_rows' => 10,
                        'media_buttons' => false
                    ));
                    ?>
                    <p class="description">
                        <?php _e('Available placeholders: {site_name}, {first_name}, {confirmation_link}, {confirmation_link_plain}', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>

            <!-- Password Reset Email -->
            <tr>
                <th scope="row"><?php _e('Password Reset Email', 'wp-custom-login-manager'); ?></th>
                <td>
                    <?php
                    $reset_email = get_option('wpclm_reset_email_template', $this->get_default_reset_email());
                    wp_editor($reset_email, 'wpclm_reset_email_template', array(
                        'textarea_rows' => 10,
                        'media_buttons' => false
                    ));
                    ?>
                    <p class="description">
                        <?php _e('Available placeholders: {site_name}, {first_name}, {reset_link}', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

/**
 * Render security settings tab
 */
private function render_security_settings() {
    ?>
    <table class="form-table" role="presentation">
        <!-- WordPress Login Page -->
        <tr>
            <th scope="row"><?php _e('WordPress Login Page', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_disable_wp_login" 
                    value="1" <?php checked(get_option('wpclm_disable_wp_login', 1)); ?>>
                    <?php _e('Redirect WordPress login page to custom login page', 'wp-custom-login-manager'); ?>
                </label>
                <p class="description">
                    <?php _e('If disabled, both the default WordPress login page and your custom login page will be accessible.', 'wp-custom-login-manager'); ?>
                </p>
                <p class="description">
                    <?php printf(
                        __('Emergency access to wp-login.php is always available by adding %s to the URL.', 'wp-custom-login-manager'),
                        '<code>?direct_login=true</code>'
                    ); ?>
                </p>
            </td>
        </tr>

        <!-- Cloudflare Turnstile Settings -->
        <tr>
            <th scope="row" colspan="2">
                <hr>
                <h3><?php _e('Cloudflare Turnstile Settings', 'wp-custom-login-manager'); ?></h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php _e('Enable Turnstile', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_turnstile_enabled" 
                        value="1" <?php checked(get_option('wpclm_turnstile_enabled', 0)); ?>>
                    <?php _e('Enable Cloudflare Turnstile protection', 'wp-custom-login-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Site Key', 'wp-custom-login-manager'); ?></th>
            <td>
                <input type="text" name="wpclm_turnstile_site_key" 
                    value="<?php echo esc_attr(get_option('wpclm_turnstile_site_key')); ?>" 
                    class="regular-text">
                <p class="description">
                    <?php _e('Enter your Cloudflare Turnstile site key', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Secret Key', 'wp-custom-login-manager'); ?></th>
            <td>
                <input type="password" name="wpclm_turnstile_secret_key" 
                    value="<?php echo esc_attr(get_option('wpclm_turnstile_secret_key')); ?>" 
                    class="regular-text">
                <p class="description">
                    <?php _e('Enter your Cloudflare Turnstile secret key', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Enable On Forms', 'wp-custom-login-manager'); ?></th>
            <td>
                <?php
                $enabled_forms = get_option('wpclm_turnstile_forms', array('register'));
                $form_options = array(
                    'register' => __('Registration Form', 'wp-custom-login-manager'),
                    'login' => __('Login Form', 'wp-custom-login-manager'),
                    'reset' => __('Password Reset Form', 'wp-custom-login-manager')
                );
                foreach ($form_options as $value => $label) : ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="wpclm_turnstile_forms[]" 
                            value="<?php echo esc_attr($value); ?>"
                            <?php checked(in_array($value, $enabled_forms)); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>

        <!-- Reoon Email Verification Settings -->
        <tr>
            <th scope="row" colspan="2">
                <hr>
                <h3><?php _e('Email Verification Settings', 'wp-custom-login-manager'); ?></h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php _e('Enable Email Verification', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_email_verification_enabled" 
                        value="1" <?php checked(get_option('wpclm_email_verification_enabled', 0)); ?>>
                    <?php _e('Enable Reoon email verification', 'wp-custom-login-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('API Key', 'wp-custom-login-manager'); ?></th>
            <td>
                <input type="password" name="wpclm_reoon_api_key" 
                    value="<?php echo esc_attr(get_option('wpclm_reoon_api_key')); ?>" 
                    class="regular-text">
                <p class="description">
                    <?php _e('Enter your Reoon API key', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Verification Mode', 'wp-custom-login-manager'); ?></th>
            <td>
                <select name="wpclm_reoon_verification_mode">
                    <option value="quick" <?php selected(get_option('wpclm_reoon_verification_mode', 'quick'), 'quick'); ?>>
                        <?php _e('Quick Mode (0.5s, less thorough)', 'wp-custom-login-manager'); ?>
                    </option>
                    <option value="power" <?php selected(get_option('wpclm_reoon_verification_mode', 'quick'), 'power'); ?>>
                        <?php _e('Power Mode (1-60s, more thorough)', 'wp-custom-login-manager'); ?>
                    </option>
                </select>
                <p class="description">
                    <?php _e('Quick Mode is recommended for registration forms to maintain good user experience', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
        <th scope="row"><?php _e('Contact Link URL', 'wp-custom-login-manager'); ?></th>
            <td>
                <input type="text" name="wpclm_contact_url" 
                    value="<?php echo esc_attr(get_option('wpclm_contact_url', '/contact/')); ?>" 
                    class="regular-text">
                    <p class="description">
                        <?php _e('Enter the URL for the contact link shown in error messages. Can be absolute (https://example.com/contact) or relative (/contact/)', 'wp-custom-login-manager'); ?>
                    </p>
                </td>
            </tr>
            <tr>
            <th scope="row"><?php _e('Role-Based Emails', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_allow_role_emails" 
                        value="1" <?php checked(get_option('wpclm_allow_role_emails', false)); ?>>
                    <?php _e('Allow role-based email addresses (like info@, admin@, support@)', 'wp-custom-login-manager'); ?>
                </label>
                <p class="description">
                    <?php _e('When enabled, users can register with role-based email addresses. For security reasons, it\'s recommended to keep this disabled.', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>

        <!-- Debug Settings -->
        <tr>
            <th scope="row" colspan="2">
                <hr>
                <h3><?php _e('Debug Settings', 'wp-custom-login-manager'); ?></h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php _e('Enable Debug Logging', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_enable_debugging" 
                        value="1" <?php checked(get_option('wpclm_enable_debugging', 0)); ?>>
                    <?php _e('Enable debug logging for troubleshooting', 'wp-custom-login-manager'); ?>
                </label>
                <p class="description">
                    <?php _e('When enabled, debug information will be logged to wpclm-debug.log in the wp-content directory.', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>

        <!-- Rate Limiting Settings -->
        <tr>
            <th scope="row"><?php _e('Rate Limiting', 'wp-custom-login-manager'); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <?php _e('Rate Limiting Settings', 'wp-custom-login-manager'); ?>
                    </legend>
                    
                    <!-- Max Attempts -->
                    <label for="wpclm_rate_limit_max_attempts">
                        <?php _e('Maximum Login Attempts:', 'wp-custom-login-manager'); ?>
                    </label><br>
                    <input type="number" 
                    id="wpclm_rate_limit_max_attempts"
                    name="wpclm_rate_limit_max_attempts" 
                    value="<?php echo esc_attr(get_option('wpclm_rate_limit_max_attempts', 50)); ?>" 
                    min="1" max="100">
                    <p class="description">
                        <?php _e('Number of login attempts allowed before temporary lockout. (Default: 6, max: 100. Recommended: 5-10 for production, up to 50 for testing)', 'wp-custom-login-manager'); ?>
                    </p>
                    
                    <!-- Lockout Duration -->
                    <label for="wpclm_rate_limit_lockout_duration">
                        <?php _e('Lockout Duration (seconds):', 'wp-custom-login-manager'); ?>
                    </label><br>
                    <input type="number" 
                    id="wpclm_rate_limit_lockout_duration"
                    name="wpclm_rate_limit_lockout_duration" 
                    value="<?php echo esc_attr(get_option('wpclm_rate_limit_lockout_duration', 300)); ?>" 
                    min="60" max="86400">
                    <p class="description">
                        <?php _e('Duration of the temporary lockout in seconds. (Default: 300 for testing, recommended: 1800 for production)', 'wp-custom-login-manager'); ?>
                    </p>
                    
                    <!-- Monitoring Period -->
                    <label for="wpclm_rate_limit_monitoring_period">
                        <?php _e('Monitoring Period (seconds):', 'wp-custom-login-manager'); ?>
                    </label><br>
                    <input type="number" 
                    id="wpclm_rate_limit_monitoring_period"
                    name="wpclm_rate_limit_monitoring_period" 
                    value="<?php echo esc_attr(get_option('wpclm_rate_limit_monitoring_period', 3600)); ?>" 
                    min="300" max="86400">
                    <p class="description">
                        <?php _e('Time period during which attempts are counted. (Default: 3600 seconds / 1 hour)', 'wp-custom-login-manager'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>

        <!-- Performance Settings -->
        <tr>
            <th scope="row"><?php _e('Performance Settings', 'wp-custom-login-manager'); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <?php _e('Performance Settings', 'wp-custom-login-manager'); ?>
                    </legend>
                    <label>
                        <input type="checkbox" 
                        name="wpclm_minify_assets" 
                        value="1" 
                        <?php checked(get_option('wpclm_minify_assets', 0)); ?>>
                        <?php _e('Minify CSS and JavaScript files', 'wp-custom-login-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Enable this option to serve minified versions of CSS and JavaScript files.', 'wp-custom-login-manager'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Render messages settings tab
 */
private function render_messages_settings() {
    $default_messages = array(
        'login_failed' => __('Invalid username or password.', 'wp-custom-login-manager'),
        'email_exists' => __('This email address is already registered.', 'wp-custom-login-manager'),
        'registration_disabled' => __('User registration is currently disabled.', 'wp-custom-login-manager'),
        'password_mismatch' => __('The passwords do not match.', 'wp-custom-login-manager'),
        'weak_password' => __('Please choose a stronger password.', 'wp-custom-login-manager'),
        'invalid_email' => __('Please enter a valid email address.', 'wp-custom-login-manager'),
        'required_fields' => __('Please fill in all required fields.', 'wp-custom-login-manager')
    );

    ?>
    <table class="form-table" role="presentation">
        <?php foreach ($default_messages as $key => $default_message): ?>
            <tr>
                <th scope="row">
                    <label for="wpclm_message_<?php echo esc_attr($key); ?>">
                        <?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                    name="wpclm_message_<?php echo esc_attr($key); ?>" 
                    id="wpclm_message_<?php echo esc_attr($key); ?>"
                    value="<?php echo esc_attr(get_option('wpclm_message_' . $key, $default_message)); ?>"
                    class="regular-text">
                    <p class="description">
                        <?php _e('Default:', 'wp-custom-login-manager'); ?> 
                        <?php echo esc_html($default_message); ?>
                    </p>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
}

    /**
     * Get default confirmation email template
     */
    private function get_default_confirmation_email() {
        return __(
            "Hello {user_name},\n\n" .
            "Welcome to {site_name}! Please click the link below to confirm your email address and complete your registration:\n\n" .
            "{confirmation_link}\n\n" .
            "If you didn't create an account, you can safely ignore this email.\n\n" .
            "Best regards,\n" .
            "{site_name} Team",
            'wp-custom-login-manager'
        );
    }

    /**
     * Get default password reset email template
     */
    private function get_default_reset_email() {
        return __(
            "Hello {user_name},\n\n" .
            "Someone has requested a password reset for your account at {site_name}. If this was you, click the link below to set a new password:\n\n" .
            "{reset_link}\n\n" .
            "If you didn't request this, you can safely ignore this email.\n\n" .
            "Best regards,\n" .
            "{site_name} Team",
            'wp-custom-login-manager'
        );
    }

/**
 * Enqueue admin scripts and styles
 */
public function enqueue_admin_scripts($hook) {
    if ('toplevel_page_wpclm-settings' !== $hook) {
        return;
    }

    // Enqueue admin styles
    wp_enqueue_style(
        'wpclm-admin-settings',
        WPCLM_PLUGIN_URL . 'assets/css/admin-settings.css',
        array(),
        WPCLM_VERSION
    );

    // Enqueue WordPress media uploader
    wp_enqueue_media();

    // Enqueue WordPress color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // Enqueue jQuery UI Dialog
    wp_enqueue_style(
        'wp-jquery-ui-dialog',
        includes_url('css/jquery-ui-dialog.min.css'),
        array(),
        WPCLM_VERSION
    );
    
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_script('jquery-ui-tooltip');

    // Enqueue our admin script
    wp_enqueue_script(
        'wpclm-admin-settings',
        WPCLM_PLUGIN_URL . 'assets/js/admin-settings.js',
        array('jquery', 'wp-color-picker', 'jquery-ui-dialog', 'jquery-ui-tooltip'),
        WPCLM_VERSION,
        true
    );

    // Localize script
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
        // Convert to integer and ensure it's positive
        $number = absint($value);
        
        // Get the field name from the current filter
        $field = str_replace('sanitize_option_', '', current_filter());
        
        // Set min/max values based on field
        switch ($field) {
            case 'wpclm_rate_limit_max_attempts':
                $min = 1;
                $max = 100;
                $default = 6;
                break;
            case 'wpclm_rate_limit_lockout_duration':
                $min = 60;      // 1 minute minimum
                $max = 86400;   // 24 hours maximum
                $default = 900; // 15 minutes default
                break;
            case 'wpclm_rate_limit_monitoring_period':
                $min = 300;     // 5 minutes minimum
                $max = 86400;   // 24 hours maximum
                $default = 3600;// 1 hour default
                break;
            default:
                return $number;
        }
        
        // Ensure value is within allowed range
        if ($number < $min || $number > $max) {
            return $default;
        }
        
        return $number;
    }

}

// Initialize Settings
WPCLM_Settings::get_instance();