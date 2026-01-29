<?php
/**
 * Login Page Renderer Class
 *
 * Handles rendering of login page and all form templates
 *
 * @package WPCustomLoginManager
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Login_Page_Renderer {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Turnstile instance
     */
    private $turnstile;

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
     * Filter login page document title
     *
     * @param string $title Current title
     * @return string
     */
    public function filter_document_title($title) {
        $custom_title = get_option('wpclm_login_page_title', __('Login', 'wp-custom-login-manager'));
        $custom_title = sanitize_text_field($custom_title);
        return $custom_title . ' - ' . get_bloginfo('name');
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->messages = WPCLM_Messages::get_instance();

        if (class_exists('WPCLM_Turnstile')) {
            $this->turnstile = WPCLM_Turnstile::get_instance();
        }
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
     * Clear existing messages
     */
    private function clear_messages() {
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

    /**
     * Render full page (called from router)
     */
    public function render_full_page() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <?php 
            remove_filter('pre_get_document_title', array($this, 'filter_document_title'), 99);
            add_filter('pre_get_document_title', array($this, 'filter_document_title'), 99);
            
            wp_head(); 

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
    }

    /**
     * Render login page content
     *
     * @return string
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
     * @param string $form_type The type of form
     */
    private function render_welcome_message($form_type = 'login') {
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
                    $custom_message = get_option('wpclm_login_welcome_text', '');
                    $message = empty($custom_message) ? $default_message : $custom_message;
                } else {
                    $default_message = sprintf(
                        '<strong>%s</strong><br>%s',
                        __('Welcome back!', 'wp-custom-login-manager'),
                        __('You can log in, create a new account, or reset your password.', 'wp-custom-login-manager')
                    );
                    $custom_message = get_option('wpclm_login_welcome_text', '');
                    $message = empty($custom_message) ? $default_message : $custom_message;
                }
                break;

            case 'register':
                $default_message = sprintf(
                    '<strong>%s</strong><br>%s',
                    __('Create your account', 'wp-custom-login-manager'),
                    __('Fill in your details to get started.', 'wp-custom-login-manager')
                );
                $custom_message = get_option('wpclm_register_welcome_text', '');
                $message = empty($custom_message) ? $default_message : $custom_message;
                break;

            case 'lostpassword':
                $default_message = sprintf(
                    '<strong>%s</strong><br>%s',
                    __('Reset your password', 'wp-custom-login-manager'),
                    __('Enter your email address to receive a password reset link.', 'wp-custom-login-manager')
                );
                $custom_message = get_option('wpclm_lostpassword_welcome_text', '');
                $message = empty($custom_message) ? $default_message : $custom_message;
                break;

            case 'resetpass':
                $default_message = sprintf(
                    '<strong>%s</strong><br>%s',
                    __('Set your new password', 'wp-custom-login-manager'),
                    __('Choose a strong password for your account.', 'wp-custom-login-manager')
                );
                $custom_message = get_option('wpclm_resetpass_welcome_text', '');
                $message = empty($custom_message) ? $default_message : $custom_message;
                break;

            case 'setpassword':
                $default_message = sprintf(
                    '<strong>%s</strong><br>%s',
                    __('Set your password', 'wp-custom-login-manager'),
                    __('Choose a password to complete your account setup.', 'wp-custom-login-manager')
                );
                $custom_message = get_option('wpclm_setpassword_welcome_text', '');
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

                if (isset($_GET['logged_out']) && $_GET['logged_out'] === 'true') {
                    echo '<div class="wpclm-success-message" role="alert">';
                    echo esc_html__('You have been successfully logged out.', 'wp-custom-login-manager');
                    echo '</div>';
                }

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

                $password_status = isset($_GET['password']) ? $this->escape_output($_GET['password'], 'attr') : '';
                if (isset($_GET['password']) && $password_status === 'changed') {
                    echo '<div class="wpclm-success-message" role="alert">';
                    echo isset($_GET['message']) ? 
                        $this->escape_output(urldecode($_GET['message']), 'html') : 
                        $this->escape_output(__('Your password has been reset successfully.', 'wp-custom-login-manager'), 'html');
                    echo '</div>';
                }

                $login_error = isset($_GET['login_error']) ? $this->escape_output(urldecode($_GET['login_error']), 'html') : '';
                if (isset($_GET['login_error'])) {
                    echo '<div class="wpclm-error-message" role="alert">';
                    echo $login_error;
                    echo '</div>';
                }

                $error = isset($_GET['error']) ? $this->escape_output(urldecode($_GET['error']), 'html') : '';
                if (isset($_GET['error'])) {
                    echo '<div class="wpclm-error-message" role="alert">';
                    echo $error;
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
                            data-target="user_pass">
                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
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
        $current_url = $_SERVER['REQUEST_URI'];
        $query_params = [];
        $url_parts = parse_url($current_url);
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);
        }

        if (get_option('wpclm_disable_registration', 0)) {
            echo '<div class="wpclm-form wpclm-register-form register-form">';
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

        <div class="wpclm-form wpclm-register-form register-form">
            <?php
            $cookie_name = 'wpclm_user_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
            $error_message = '';
            
            if (isset($_COOKIE['wpclm_error_message'])) {
                $error_message = get_transient('wpclm_error_' . $cookie_name);
                setcookie('wpclm_error_message', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                delete_transient('wpclm_error_' . $cookie_name);
            }
            
            if (!empty($error_message)) {
                echo $this->render_error_message($error_message);
            }

            $success_message = '';
            
            if (isset($_COOKIE['wpclm_success_message'])) {
                $success_message = get_transient('wpclm_success_' . $cookie_name);
                setcookie('wpclm_success_message', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                delete_transient('wpclm_success_' . $cookie_name);
            }
            
            if (!empty($success_message)) {
                echo $this->render_success_message($success_message);
            }
            ?>
            <form id="wpclm-register-form" method="post" action="<?php echo esc_url($current_url); ?>">
                <?php wp_nonce_field('wpclm-register-nonce', 'wpclm_register_nonce'); ?>
                <input type="hidden" name="wpclm_action" value="register">

                <div class="form-row">
                    <div class="form-field">
                        <label for="first_name">
                            <?php _e('First Name', 'wp-custom-login-manager'); ?>
                            <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                        </label>
                        <input type="text" 
                            name="first_name" 
                            id="first_name" 
                            aria-required="true"
                            required>
                    </div>

                    <div class="form-field">
                        <label for="last_name">
                            <?php _e('Last Name', 'wp-custom-login-manager'); ?>
                            <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                        </label>
                        <input type="text" 
                            name="last_name" 
                            id="last_name" 
                            aria-required="true"
                            required>
                    </div>
                </div>

                <div class="form-field">
                    <label for="user_email">
                        <?php _e('Email Address', 'wp-custom-login-manager'); ?>
                        <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                    </label>
                    <input type="email" 
                        name="user_email" 
                        id="user_email" 
                        aria-required="true"
                        required>
                </div>

                <div class="form-field terms-privacy">
                    <label for="terms_privacy">
                        <input type="checkbox" 
                            name="terms_privacy" 
                            id="terms_privacy"
                            aria-required="true"
                            aria-describedby="terms_privacy_desc"
                            required>
                        <?php 
                        $terms_url = get_option('wpclm_terms_url', '');
                        $privacy_url = get_option('wpclm_privacy_url', '');
                        printf(
                            __('By creating an account, you agree to our %1$s and %2$s', 'wp-custom-login-manager'),
                            '<a href="' . esc_url($terms_url) . '" target="_blank" rel="noopener">' . __('Terms', 'wp-custom-login-manager') . '<span class="screen-reader-text"> ' . __('(opens in new tab)', 'wp-custom-login-manager') . '</span></a>',
                            '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener">' . __('Privacy Policy', 'wp-custom-login-manager') . '<span class="screen-reader-text"> ' . __('(opens in new tab)', 'wp-custom-login-manager') . '</span></a>'
                        );
                        ?>
                    </label>
                    <span id="terms_privacy_desc" class="screen-reader-text">
                        <?php _e('You must agree to terms and privacy policy to create an account', 'wp-custom-login-manager'); ?>
                    </span>
                </div>

                <?php
                if ($this->turnstile && $this->turnstile->is_enabled_for('register')) {
                    $this->turnstile->render_widget();
                }
                ?>

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
            if (isset($_GET['checkemail']) && $_GET['checkemail'] == 'confirm') {
                echo '<div class="wpclm-success-message" role="alert">';
                echo $this->escape_output($this->messages->get_message('password_reset_sent'), 'html');
                echo '</div>';
            }
            if (isset($_GET['login_error'])) {
                echo '<div class="wpclm-error-message" role="alert">';
                echo $this->escape_output(urldecode($_GET['login_error']), 'html');
                echo '</div>';
            }
            if (isset($_GET['error'])) {
                echo '<div class="wpclm-error-message" role="alert">';
                echo $this->escape_output(urldecode($_GET['error']), 'html');
                echo '</div>';
            }
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

                <?php
                if ($this->turnstile && $this->turnstile->is_enabled_for('reset')) {
                    $this->turnstile->render_widget();
                }
                ?>

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
     * Render reset password form
     */
    private function render_reset_password_form() {
        $rp_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $rp_login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';
        
        $error_message = '';
        if (isset($_GET['error_key'])) {
            $error_key = sanitize_text_field($_GET['error_key']);
            $error_message = get_transient($error_key);
            if ($error_message) {
                delete_transient($error_key);
            }
        }
        
        if (empty($error_message) && isset($_GET['error'])) {
            $error_message = urldecode($_GET['error']);
        }
        ?>
        <div class="wpclm-form resetpass-form">
        <style>
            .wpclm-error-message {
                display: block;
                visibility: visible;
                opacity: 1;
                color: red;
                margin-bottom: 15px;
                padding: 10px;
                border: 1px solid red;
                background-color: #fff3f3;
            }
        </style>
        <?php
        
        if (!empty($error_message)) {
            echo '<div class="wpclm-error-message" role="alert">';
            echo $this->escape_output($error_message, 'html');
            echo '</div>';
        }

        if (isset($_GET['password']) && $_GET['password'] === 'changed') {
            echo '<div class="wpclm-success-message" role="alert">';
            echo $this->escape_output($this->messages->get_message('password_reset_success'), 'html');
            echo '</div>';
        }

        if (empty($rp_key) || empty($rp_login)) {
            echo '<div class="wpclm-error-message" role="alert" style="color: red; margin-bottom: 15px;">';
            echo $this->escape_output(__('Invalid password setup link.', 'wp-custom-login-manager'), 'html');
            echo '</div>';
            ?>
            <div class="form-links">
                <a href="<?php echo esc_url(remove_query_arg(array('action', 'key', 'login', 'password'))); ?>">
                    <?php _e('Back to Login', 'wp-custom-login-manager'); ?>
                </a>
            </div>
            </div>
            <?php
            return;
        }
        ?>
        <form id="wpclm-resetpass-form" method="post">
            <?php wp_nonce_field('wpclm-resetpass-nonce', 'wpclm_resetpass_nonce'); ?>
            <input type="hidden" name="wpclm_action" value="resetpass">
            <input type="hidden" name="rp_key" value="<?php echo esc_attr($rp_key); ?>">
            <input type="hidden" name="rp_login" value="<?php echo esc_attr($rp_login); ?>">

            <div class="form-field">
                <label for="pass1">
                    <?php _e('New Password', 'wp-custom-login-manager'); ?>
                    <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                </label>
                <input type="password" 
                    name="pass1" 
                    id="pass1" 
                    class="password-input" 
                    aria-required="true"
                    aria-describedby="pass1_requirements"
                    required>
                <div class="password-strength-meter"></div>
                <div class="password-requirements" id="pass1_requirements" role="status" aria-live="polite">
                    <h4 id="password_requirements_heading"><?php echo esc_html__('Password must:', 'wp-custom-login-manager'); ?></h4>
                    <ul aria-labelledby="password_requirements_heading">
                        <li class="requirement length">
                            <span class="check" aria-hidden="true">✓</span> 
                            <span class="requirement-text"><?php echo esc_html__('Be at least 12 characters long', 'wp-custom-login-manager'); ?></span>
                        </li>
                        <li class="requirement uppercase">
                            <span class="check" aria-hidden="true">✓</span> 
                            <span class="requirement-text"><?php echo esc_html__('Include at least one uppercase letter', 'wp-custom-login-manager'); ?></span>
                        </li>
                        <li class="requirement lowercase">
                            <span class="check" aria-hidden="true">✓</span> 
                            <span class="requirement-text"><?php echo esc_html__('Include at least one lowercase letter', 'wp-custom-login-manager'); ?></span>
                        </li>
                        <li class="requirement number">
                            <span class="check" aria-hidden="true">✓</span> 
                            <span class="requirement-text"><?php echo esc_html__('Include at least one number', 'wp-custom-login-manager'); ?></span>
                        </li>
                    </ul>
                </div> 
            </div>

            <div class="form-field">
                <label for="pass2">
                    <?php _e('Confirm New Password', 'wp-custom-login-manager'); ?>
                    <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                </label>
                <input type="password" 
                    name="pass2" 
                    id="pass2" 
                    aria-required="true"
                    aria-describedby="pass2_desc"
                    required>
                <span id="pass2_desc" class="screen-reader-text">
                    <?php _e('Must match the password entered above', 'wp-custom-login-manager'); ?>
                </span>
            </div>

            <?php
            if ($this->turnstile && $this->turnstile->is_enabled_for('reset')) {
                $this->turnstile->render_widget();
            }
            ?>

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
     * Render password setup form
     */
    private function render_password_setup_form() {
        $rp_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $rp_login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';

        if (empty($rp_key) || empty($rp_login)) {
            echo $this->render_error_message(__('Invalid password setup link.', 'wp-custom-login-manager'));
            return;
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
                    <label for="pass1">
                        <?php _e('New Password', 'wp-custom-login-manager'); ?>
                        <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                    </label>
                    <input type="password" 
                        name="pass1" 
                        id="pass1" 
                        class="password-input" 
                        aria-required="true"
                        aria-describedby="pass1_requirements"
                        required>
                    <div class="password-strength-meter"></div>
                    <div class="password-requirements" id="pass1_requirements" role="status" aria-live="polite">
                        <h4 id="password_requirements_heading"><?php echo esc_html__('Password must:', 'wp-custom-login-manager'); ?></h4>
                        <ul aria-labelledby="password_requirements_heading">
                            <li class="requirement length">
                                <span class="check" aria-hidden="true">✓</span> 
                                <span class="requirement-text"><?php echo esc_html__('Be at least 12 characters long', 'wp-custom-login-manager'); ?></span>
                            </li>
                            <li class="requirement uppercase">
                                <span class="check" aria-hidden="true">✓</span> 
                                <span class="requirement-text"><?php echo esc_html__('Include at least one uppercase letter', 'wp-custom-login-manager'); ?></span>
                            </li>
                            <li class="requirement lowercase">
                                <span class="check" aria-hidden="true">✓</span> 
                                <span class="requirement-text"><?php echo esc_html__('Include at least one lowercase letter', 'wp-custom-login-manager'); ?></span>
                            </li>
                            <li class="requirement number">
                                <span class="check" aria-hidden="true">✓</span> 
                                <span class="requirement-text"><?php echo esc_html__('Include at least one number', 'wp-custom-login-manager'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="form-field">
                    <label for="pass2">
                        <?php _e('Confirm New Password', 'wp-custom-login-manager'); ?>
                        <span class="screen-reader-text"><?php _e('Required', 'wp-custom-login-manager'); ?></span>
                    </label>
                    <input type="password" 
                        name="pass2" 
                        id="pass2" 
                        aria-required="true"
                        aria-describedby="pass2_desc"
                        required>
                    <span id="pass2_desc" class="screen-reader-text">
                        <?php _e('Must match the password entered above', 'wp-custom-login-manager'); ?>
                    </span>
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
     * Render logged in message
     *
     * @return string
     */
    public function render_logged_in_message() {
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
     *
     * @param string $message Error message
     * @return string
     */
    public function render_error_message($message) {
        $this->clear_messages();
        return sprintf(
            '<div class="wpclm-error-message">%s</div>',
            $this->escape_output($message, 'html')
        );
    }

    /**
     * Render success message
     *
     * @param string $message Success message
     * @return string
     */
    public function render_success_message($message) {
        $this->clear_messages();
        return sprintf(
            '<div class="wpclm-success-message">%s</div>',
            $this->escape_output($message, 'html')
        );
    }
}
