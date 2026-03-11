<?php
/**
 * Plugin Name: WP Custom Login Manager
 * Plugin URI: https://github.com/NichlasB/wp-custom-login-manager
 * Description: A modern, secure custom login and registration system for WordPress with email verification.
 * Version: 1.2.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: CueFox
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-custom-login-manager
 * Domain Path: /languages
 * 
 * GitHub Plugin URI: NichlasB/wp-custom-login-manager
 *
 * @package WPCustomLoginManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// Plugin constants
define('WPCLM_VERSION', '1.2.0');
define('WPCLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPCLM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPCLM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class WP_Custom_Login_Manager {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
    * Plugin components
    */
    private $settings;
    private $messages;
    private $forms;
    private $auth;
    private $rate_limiter;
    private $emails;
    private $debug;
    private $woocommerce;

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
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
    * Initialize plugin
    */
    public function init() {
        // Load text domain early on init
        add_action('init', array($this, 'load_textdomain'), 5);

        // Include required files
        $this->include_files();

        // Initialize email verifier early to register AJAX hooks (before init)
        WPCLM_Email_Verifier::get_instance();

        // Initialize components after init hook
        add_action('init', array($this, 'init_components'), 10);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-custom-login-manager',
            false,
            dirname(WPCLM_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Include required files
     */
    private function include_files() {
        // Core utilities (always needed)
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-debug.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-messages.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-rate-limiter.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-email-verifier.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-turnstile.php';

        // Admin-only: Settings page renderer (WPCLM_Settings needed on frontend for logo/background)
        if (is_admin()) {
            require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-settings-renderer.php';
        }
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-settings.php';

        // Auth and email handling
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-auth.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-emails.php';

        // Form components (loaded before facade)
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-login-router.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-frontend-assets.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-access-control.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-form-submission-handler.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-login-page-renderer.php';

        // Forms facade (coordinates form components)
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-forms.php';

        // Redirects and integrations
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-redirects.php';
        require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-woocommerce.php';
    }

    /**
     * Initialize components
     */
    public function init_components() {
        $this->settings = WPCLM_Settings::get_instance();
        $this->messages = WPCLM_Messages::get_instance();
        $this->rate_limiter = WPCLM_Rate_Limiter::get_instance();
        $this->forms = WPCLM_Forms::get_instance();
        $this->auth = WPCLM_Auth::get_instance();
        $this->emails = WPCLM_Emails::get_instance();
        $this->debug = WPCLM_Debug::get_instance();
        
        // Initialize email verifier to register AJAX hooks
        WPCLM_Email_Verifier::get_instance();

        // Initialize WooCommerce integration if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $this->woocommerce = WPCLM_WooCommerce::get_instance();
        }
    }

    /**
 * Get asset URL
 *
 * @param string $asset_path Path to the asset file
 * @return string URL to the asset
 */
    public function get_asset_url($asset_path) {
        $use_minified = get_option('wpclm_minify_assets', 0);
        
        if ($use_minified) {
            $ext = pathinfo($asset_path, PATHINFO_EXTENSION);
            $min_path = str_replace('.' . $ext, '.min.' . $ext, $asset_path);
            
            if (file_exists(WPCLM_PLUGIN_DIR . $min_path)) {
                return WPCLM_PLUGIN_URL . $min_path;
            }
        }
        
        return WPCLM_PLUGIN_URL . $asset_path;
    }

    /**
     * Plugin activation
     * M6 Fix: Handle network activation for multisite
     * 
     * @param bool $network_wide Whether the plugin is being activated network-wide
     */
    public function activate($network_wide = false) {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('This plugin requires WordPress version 5.0 or higher.', 'wp-custom-login-manager'),
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }

        // M6 Fix: Handle multisite network activation
        if (is_multisite() && $network_wide) {
            $sites = get_sites(array('fields' => 'ids', 'number' => 0));
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                $this->activate_single_site();
                restore_current_blog();
            }
        } else {
            $this->activate_single_site();
        }
    }
    
    /**
     * Activate plugin for a single site
     */
    private function activate_single_site() {
        // Create default options
        $this->create_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove all plugin actions and filters before deactivation
        remove_action('init', array(WPCLM_Redirects::get_instance(), 'handle_early_redirect'), 1);
        remove_action('init', array($this, 'init_components'), 10);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create default options
     */
    private function create_default_options() {
        $default_options = array(
            'wpclm_disable_registration' => 0,
            'wpclm_default_role' => 'subscriber',
            'wpclm_button_background_color' => '#2271B1',
            'wpclm_button_text_color' => '#FFFFFF',
            'wpclm_link_color' => '#2271B1',
            'wpclm_login_form_background_color' => '#F5F5F5',
            'wpclm_email_background_color' => '#F5F5F5',
            'wpclm_heading_color' => '#1D2327',
            'wpclm_disable_wp_login' => 1
        );

        foreach ($default_options as $option => $value) {
            if (false === get_option($option)) {
                update_option($option, $value);
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function wpclm_init() {
    return WP_Custom_Login_Manager::get_instance();
}

// Start the plugin
wpclm_init();