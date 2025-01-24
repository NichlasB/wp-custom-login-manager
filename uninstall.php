<?php
/**
 * Uninstall WP Custom Login Manager
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
$options = array(
    // General Settings
    'wpclm_disable_registration',
    'wpclm_logo',
    'wpclm_logo_width',
    'wpclm_login_url',
    'wpclm_login_page_title',
    'wpclm_login_page_description',
    'wpclm_login_redirect',
    'wpclm_registration_redirect',
    'wpclm_logged_in_redirect',
    'wpclm_default_role',
    'wpclm_terms_url',
    'wpclm_privacy_url',
    'wpclm_remember_me_duration',
    'wpclm_wc_redirect',

    // Welcome Messages
    'wpclm_login_welcome_text',
    'wpclm_register_welcome_text',
    'wpclm_lostpassword_welcome_text',
    'wpclm_resetpass_welcome_text',
    'wpclm_setpassword_welcome_text',

    // Design Settings
    'wpclm_background_image',
    'wpclm_custom_css',
    'wpclm_button_background_color',
    'wpclm_button_text_color',
    'wpclm_link_color',
    'wpclm_login_form_background_color',
    'wpclm_email_background_color',
    'wpclm_heading_color',

    // Email Settings
    'wpclm_confirmation_email_template',
    'wpclm_reset_email_template',

    // Security Settings
    'wpclm_disable_wp_login',
    'wpclm_rate_limit_max_attempts',
    'wpclm_rate_limit_lockout_duration',
    'wpclm_rate_limit_monitoring_period',
    'wpclm_minify_assets',

    // Message Settings
    'wpclm_message_login_failed',
    'wpclm_message_email_exists',
    'wpclm_message_registration_disabled',
    'wpclm_message_password_mismatch',
    'wpclm_message_weak_password',
    'wpclm_message_invalid_email',
    'wpclm_message_required_fields',
    'wpclm_message_confirmation_sent',
    'wpclm_message_confirmation_success',
    'wpclm_message_confirmation_expired',
    'wpclm_message_password_reset_sent',
    'wpclm_message_password_reset_success',
    'wpclm_message_password_reset_expired'
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete user meta for all users
delete_metadata('user', 0, 'wpclm_password_history', '', true);

// Delete any remaining transients and clean up the options table
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE %s 
        OR option_name LIKE %s 
        OR option_name LIKE %s 
        OR option_name LIKE %s",
        '%wpclm_%',
        '_transient_wpclm_%',
        '_transient_timeout_wpclm_%',
        '_transient_wpclm_rate_limit_%'  // Rate limiter transients
    )
);

// Delete debug log file if it exists
$debug_log = WP_CONTENT_DIR . '/wpclm-debug.log';
if (file_exists($debug_log)) {
    unlink($debug_log);
}

// Clear any rewrite rules
delete_option('rewrite_rules');

// Flush rewrite rules
flush_rewrite_rules();