<?php
/**
 * Debug Class
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Debug {
    /**
     * Maximum log file size (5MB)
     */
    private $max_file_size = 5242880;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Debug log file path
     */
    private $log_file;

    /**
     * Whether debugging is enabled
     */
    private $is_enabled = false;

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
        $this->log_file = WP_CONTENT_DIR . '/wpclm-debug.log';
        $this->is_enabled = get_option('wpclm_enable_debugging', false);

        // Register AJAX handlers
        add_action('wp_ajax_wpclm_view_debug_log', array($this, 'ajax_view_log'));
        add_action('wp_ajax_wpclm_clear_debug_log', array($this, 'ajax_clear_log'));

        if ($this->is_enabled) {
            $this->init_hooks();
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Email debugging
        add_filter('wp_mail', array($this, 'log_email_attempt'), 10, 1);
        
        // Form submission debugging
        add_action('wpclm_before_form_submission', array($this, 'log_form_submission'), 10, 2);
        
        // Authentication debugging
        add_filter('authenticate', array($this, 'log_authentication_attempt'), 10, 3);
        
        // Template loading debugging
        add_action('wpclm_template_load', array($this, 'log_template_load'), 10, 2);

        // Add debug information to admin footer
        add_action('admin_footer', array($this, 'add_debug_info'));
    }

    /**
    * Log message to debug file
    *
    * @param string|array $message Message or data to log
    * @param mixed $data Optional data to log
    * @param string $type Log type (info, error, warning)
    */
    public function log($message, $data = null, $type = 'info') {
        if (!$this->is_enabled) {
            return;
        }

        // Check file size and rotate if needed
        $this->rotate_log();

        $timestamp = current_time('Y-m-d H:i:s');
        
        if (is_array($message)) {
            $log_message = sprintf("[%s] [%s] %s", $timestamp, strtoupper($type), json_encode($message));
        } else {
            $log_message = sprintf("[%s] [%s] %s", $timestamp, strtoupper($type), $message);
            if ($data !== null) {
                $log_message .= ': ' . (is_array($data) || is_object($data) ? json_encode($data) : $data);
            }
        }

        $log_message .= "\n";
        error_log($log_message, 3, $this->log_file);
    }

    /**
    * Static method for easier logging
    *
    * @param string $message Message to log
    * @param mixed $data Optional data to log
    * @param string $type Log type (info, error, warning)
    */
    public static function log_message($message, $data = null, $type = 'info') {
        $instance = self::get_instance();
        $instance->log($message, $data, $type);
    }

    /**
     * Log email sending attempt
     */
    public function log_email_attempt($mail_args) {
        $this->log(array(
            'action' => 'email_attempt',
            'to' => $mail_args['to'],
            'subject' => $mail_args['subject'],
            'headers' => $mail_args['headers']
        ));

        return $mail_args;
    }

    /**
     * Log form submission
     */
    public function log_form_submission($form_type, $form_data) {
        $this->log(array(
            'action' => 'form_submission',
            'type' => $form_type,
            'data' => $this->sanitize_form_data($form_data)
        ));
    }

    /**
     * Log authentication attempt
     */
    public function log_authentication_attempt($user, $username, $password) {
        $this->log(array(
            'action' => 'authentication_attempt',
            'username' => $username,
            'successful' => !is_wp_error($user)
        ));

        return $user;
    }

    /**
     * Log template loading
     */
    public function log_template_load($template, $args) {
        $this->log(array(
            'action' => 'template_load',
            'template' => $template,
            'args' => $args
        ));
    }

    /**
     * Sanitize sensitive form data for logging
     */
    private function sanitize_form_data($data) {
        $sensitive_fields = array('password', 'user_pass', 'pass1', 'pass2');
        
        foreach ($sensitive_fields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '*** REDACTED ***';
            }
        }
        
        return $data;
    }

    /**
     * Add debug information to admin footer
     */
    public function add_debug_info() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $debug_info = array(
            'PHP Version' => PHP_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'Plugin Version' => WPCLM_VERSION,
            'Debug Mode' => $this->is_enabled ? 'Enabled' : 'Disabled',
            'Log File' => $this->log_file,
            'Log File Size' => file_exists($this->log_file) ? size_format(filesize($this->log_file)) : 'N/A',
            'Memory Usage' => size_format(memory_get_usage(true)),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Upload Size' => size_format(wp_max_upload_size())
        );

        echo '<div class="wpclm-debug-info" style="display:none;">';
        echo '<h3>WPCLM Debug Information</h3>';
        echo '<pre>';
        foreach ($debug_info as $key => $value) {
            printf("%s: %s\n", esc_html($key), esc_html($value));
        }
        echo '</pre>';
        echo '</div>';
    }

    /**
     * Get debug log contents
     */
    public function get_log_contents() {
        if (!file_exists($this->log_file)) {
            return '';
        }

        return file_get_contents($this->log_file);
    }

    /**
     * Clear debug log
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }

    /**
     * Rotate log file if it exceeds max size
     */
    private function rotate_log() {
        if (!file_exists($this->log_file)) {
            return;
        }

        if (filesize($this->log_file) > $this->max_file_size) {
            $backup_file = $this->log_file . '.1';
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            rename($this->log_file, $backup_file);
        }
    }

    /**
     * AJAX handler for viewing debug log
     */
    public function ajax_view_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-custom-login-manager')));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'wpclm-debug-nonce')) {
            wp_send_json_error(array('message' => __('Invalid nonce.', 'wp-custom-login-manager')));
        }

        $log_contents = $this->get_log_contents();
        
        if (empty($log_contents)) {
            $log_contents = __('Log is empty.', 'wp-custom-login-manager');
        }

        wp_send_json_success(array('log_contents' => $log_contents));
    }

    /**
     * AJAX handler for clearing debug log
     */
    public function ajax_clear_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-custom-login-manager')));
        }

        if (!wp_verify_nonce($_POST['nonce'], 'wpclm-debug-nonce')) {
            wp_send_json_error(array('message' => __('Invalid nonce.', 'wp-custom-login-manager')));
        }

        $this->clear_log();
        wp_send_json_success();
    }

}