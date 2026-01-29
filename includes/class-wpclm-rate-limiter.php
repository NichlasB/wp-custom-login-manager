<?php
/**
 * Rate Limiter Class
 *
 * @package WPCustomLoginManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Rate_Limiter {
    private static $instance = null;
    private $options_prefix = 'wpclm_rate_limit_';
    
    // High limits for testing
    private $default_options = array(
        'max_attempts' => 6,         // Maximum login attempts
        'lockout_duration' => 900,   // 15 minutes (in seconds)
        'monitoring_period' => 3600  // 1 hour (in seconds)
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_options();
    }

    private function init_options() {
        foreach ($this->default_options as $key => $value) {
            if (false === get_option($this->options_prefix . $key)) {
                add_option($this->options_prefix . $key, $value);
            }
        }
    }

    public function check_rate_limit($ip_address) {
        $attempts = $this->get_attempts($ip_address);
        $max_attempts = get_option($this->options_prefix . 'max_attempts');
        
        if ($attempts >= $max_attempts) {
            $lockout_time = $this->get_lockout_time($ip_address);
            if ($lockout_time > time()) {
                return array(
                    'allowed' => false,
                    'remaining_time' => $lockout_time - time(),
                    'remaining_attempts' => 0
                );
            }
            $this->reset_attempts($ip_address);
        }
        
        return array(
            'allowed' => true,
            'remaining_time' => 0,
            'remaining_attempts' => $max_attempts - $attempts
        );
    }

    /**
     * Record an attempt with hybrid storage (transient + option fallback)
     * M3 Fix: Persists rate limit data even if object cache is flushed
     */
    public function record_attempt($ip_address) {
        $attempts_key = $this->build_transient_key('attempts', $ip_address);
        $ttl = (int) get_option($this->options_prefix . 'monitoring_period', 3600);
        
        // Get current data using hybrid retrieval
        $data = $this->get_rate_data($attempts_key, $ttl);
        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $data['first_attempt'] = $data['first_attempt'] ?? time();
        
        // Store in both transient AND option for persistence
        set_transient($attempts_key, $data, $ttl);
        update_option($attempts_key, $data, false); // autoload=false

        if ($data['attempts'] >= get_option($this->options_prefix . 'max_attempts')) {
            $this->set_lockout($ip_address);
        }
    }

    /**
     * Get attempts with hybrid retrieval (transient + option fallback)
     * M3 Fix: Falls back to option if transient is missing (cache flush)
     */
    private function get_attempts($ip_address) {
        $attempts_key = $this->build_transient_key('attempts', $ip_address);
        $ttl = (int) get_option($this->options_prefix . 'monitoring_period', 3600);
        $data = $this->get_rate_data($attempts_key, $ttl);
        return (int) ($data['attempts'] ?? 0);
    }
    
    /**
     * Get rate limit data with transient + option fallback
     */
    private function get_rate_data($key, $ttl) {
        // Try transient first (faster)
        $data = get_transient($key);
        if ($data !== false && is_array($data)) {
            return $data;
        }
        
        // Fallback to option
        $data = get_option($key, array());
        if (!empty($data) && is_array($data)) {
            // Check if expired
            if (isset($data['first_attempt']) && (time() - $data['first_attempt']) > $ttl) {
                delete_option($key);
                return array();
            }
            // Restore transient from option
            set_transient($key, $data, $ttl);
            return $data;
        }
        
        return array();
    }

    private function set_lockout($ip_address) {
        $lockout_duration = get_option($this->options_prefix . 'lockout_duration');
        set_transient(
            $this->build_transient_key('lockout', $ip_address),
            time() + $lockout_duration,
            $lockout_duration
        );
    }

    private function get_lockout_time($ip_address) {
        return (int) get_transient($this->build_transient_key('lockout', $ip_address)) ?: 0;
    }

    private function reset_attempts($ip_address) {
        $attempts_key = $this->build_transient_key('attempts', $ip_address);
        delete_transient($attempts_key);
        delete_option($attempts_key); // M3 Fix: Also clean up option fallback
        delete_transient($this->build_transient_key('lockout', $ip_address));
    }

    private function build_transient_key($type, $ip_address) {
        return $this->options_prefix . $type . '_' . md5($ip_address);
    }

    public function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}