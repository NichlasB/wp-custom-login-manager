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

    public function record_attempt($ip_address) {
        $attempts = $this->get_attempts($ip_address);
        $attempts++;
        
        set_transient(
            $this->options_prefix . 'attempts_' . $ip_address,
            $attempts,
            get_option($this->options_prefix . 'monitoring_period')
        );

        if ($attempts >= get_option($this->options_prefix . 'max_attempts')) {
            $this->set_lockout($ip_address);
        }
    }

    private function get_attempts($ip_address) {
        return (int) get_transient($this->options_prefix . 'attempts_' . $ip_address) ?: 0;
    }

    private function set_lockout($ip_address) {
        $lockout_duration = get_option($this->options_prefix . 'lockout_duration');
        set_transient(
            $this->options_prefix . 'lockout_' . $ip_address,
            time() + $lockout_duration,
            $lockout_duration
        );
    }

    private function get_lockout_time($ip_address) {
        return (int) get_transient($this->options_prefix . 'lockout_' . $ip_address) ?: 0;
    }

    private function reset_attempts($ip_address) {
        delete_transient($this->options_prefix . 'attempts_' . $ip_address);
        delete_transient($this->options_prefix . 'lockout_' . $ip_address);
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