<?php
/**
 * Settings Renderer Class
 *
 * Handles rendering of all settings page tabs
 *
 * @package WPCustomLoginManager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Settings_Renderer {

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
        // Renderer is initialized by Settings class
    }

    /**
     * Render general settings tab
     */
    public function render_general_settings() {
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
                    <label for="wpclm_disable_registration">
                        <input type="checkbox" 
                        id="wpclm_disable_registration"
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
                    <input type="button" 
                        class="button" 
                        id="wpclm_upload_logo_button" 
                        aria-describedby="logo_description"
                        value="<?php _e('Upload Logo', 'wp-custom-login-manager'); ?>">
                    <?php if ($logo): ?>
                        <input type="button" 
                            class="button" 
                            id="wpclm_remove_logo_button"
                            aria-label="<?php esc_attr_e('Remove current logo image', 'wp-custom-login-manager'); ?>"
                            value="<?php _e('Remove Logo', 'wp-custom-login-manager'); ?>">
                    <?php endif; ?>
                    <p id="logo_description" class="description"><?php _e('Recommended size: 200x80px', 'wp-custom-login-manager'); ?></p>
                    
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
                    <?php $this->render_welcome_messages(); ?>
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
     * Render welcome messages editors
     */
    private function render_welcome_messages() {
        ?>
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
    }

    /**
     * Render design settings tab
     */
    public function render_design_settings() {
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
                    <input type="button" 
                        class="button" 
                        id="wpclm_upload_background_button" 
                        aria-describedby="background_description"
                        value="<?php _e('Upload Background', 'wp-custom-login-manager'); ?>">
                    <?php if ($background_image): ?>
                        <input type="button" 
                            class="button" 
                            id="wpclm_remove_background_button"
                            aria-label="<?php esc_attr_e('Remove current background image', 'wp-custom-login-manager'); ?>"
                            value="<?php _e('Remove Background', 'wp-custom-login-manager'); ?>">
                    <?php endif; ?>
                    <p id="background_description" class="description">
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
    public function render_email_settings() {
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
    public function render_security_settings() {
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

            <?php $this->render_turnstile_settings(); ?>
            <?php $this->render_email_verification_settings(); ?>
            <?php $this->render_debug_settings(); ?>
            <?php $this->render_rate_limiting_settings(); ?>
            <?php $this->render_performance_settings(); ?>
        </table>
        <?php
    }

    /**
     * Render Cloudflare Turnstile settings
     */
    private function render_turnstile_settings() {
        ?>
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
            <th scope="row"><?php _e('Enable On Form', 'wp-custom-login-manager'); ?></th>
            <td>
                <?php
                $enabled_forms = (array) get_option('wpclm_turnstile_forms', array('register'));
                ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="wpclm_turnstile_forms[]" 
                        value="register" <?php checked(in_array('register', $enabled_forms, true)); ?>>
                    <?php echo esc_html(__('Registration Form', 'wp-custom-login-manager')); ?>
                </label>
                <p class="description">
                    <?php _e('Turnstile is only enabled on the registration form for security purposes.', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render email verification settings
     */
    private function render_email_verification_settings() {
        ?>
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
            <th scope="row"><?php _e('Show Contact Help Message', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_show_contact_help" 
                        value="1" <?php checked(get_option('wpclm_show_contact_help', false)); ?>>
                    <?php _e('Show "If you need help creating an account, contact us" message', 'wp-custom-login-manager'); ?>
                </label>
                <p class="description">
                    <?php _e('When enabled, error messages will include a link to contact support for help.', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e('Role-Based Emails', 'wp-custom-login-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="wpclm_allow_role_emails" 
                        value="1" <?php checked(get_option('wpclm_allow_role_emails', false)); ?>>
                    <?php _e('Allow role-based email addresses (like info@, contact@, @orders, admin@, team@, etc.)', 'wp-custom-login-manager'); ?>
                </label>
                <p class="description">
                    <?php _e('When enabled, users can register with role-based email addresses. For security reasons, it\'s recommended to keep this disabled.', 'wp-custom-login-manager'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render debug settings
     */
    private function render_debug_settings() {
        ?>
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
        <?php
    }

    /**
     * Render rate limiting settings
     */
    private function render_rate_limiting_settings() {
        ?>
        <tr>
            <th scope="row"><?php _e('Rate Limiting', 'wp-custom-login-manager'); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">
                        <?php _e('Rate Limiting Settings', 'wp-custom-login-manager'); ?>
                    </legend>
                    
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
        <?php
    }

    /**
     * Render performance settings
     */
    private function render_performance_settings() {
        ?>
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
        <?php
    }

    /**
     * Render messages settings tab
     */
    public function render_messages_settings() {
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
}
