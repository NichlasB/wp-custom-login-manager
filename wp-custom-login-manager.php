<?php
/**
 * Plugin Name: WP Custom Login Manager
 * Plugin URI: 
 * Description: A modern, secure custom login and registration system for WordPress with email verification.
 * Version: 1.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: CueFox
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-custom-login-manager
 * Domain Path: /languages
 *
 * @package WPCustomLoginManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// Plugin Update Checker
$composerAutoloaderPath = __DIR__ . '/vendor/autoload.php';
$composerAutoloaderExists = false;

// Check if any Composer autoloader is already loaded
foreach (get_included_files() as $file) {
    if (strpos($file, 'vendor/composer/autoload_real.php') !== false) {
        $composerAutoloaderExists = true;
        break;
    }
}

// Only load our autoloader if no other Composer autoloader is loaded
if (!$composerAutoloaderExists) {
    require_once $composerAutoloaderPath;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/NichlasB/wp-custom-login-manager',
        __FILE__,
        'wp-custom-login-manager'
    );
    
    // Set the branch that contains the stable release
    $myUpdateChecker->setBranch('main');
    // Enable GitHub releases
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
}

// Plugin constants
define('WPCLM_VERSION', '1.1.0');
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
        // Load text domain first
        $this->load_textdomain();

        // Include required files
        $this->include_files();

        // Initialize components after init hook
        add_action('init', array($this, 'init_components'), 10);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

    }

/**
 * Load plugin text domain
 */
private function load_textdomain() {
    add_action('init', function() {
        load_plugin_textdomain(
            'wp-custom-login-manager',
            false,
            dirname(WPCLM_PLUGIN_BASENAME) . '/languages'
        );
    }, 5); // Lower priority to ensure it loads early in init
}

/**
 * Include required files
 */
private function include_files() {
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-debug.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-settings.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-messages.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-rate-limiter.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-email-verifier.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-turnstile.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-forms.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-auth.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-redirects.php';
    require_once WPCLM_PLUGIN_DIR . 'includes/class-wpclm-emails.php';
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
     */
    public function activate() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('This plugin requires WordPress version 5.0 or higher.', 'wp-custom-login-manager'),
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }

        // Create default options
        $this->create_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create default options
     */
    private function create_default_options() {
        $default_options = array(
            'wpclm_disable_registration' => 0,
            'wpclm_welcome_text' => __('Welcome back!', 'wp-custom-login-manager'),
            'wpclm_default_role' => 'subscriber',
            'wpclm_max_login_attempts' => 6,
            'wpclm_lockout_duration' => 10,
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