<?php
/**
 * Cloudflare Turnstile Integration
 *
 * @package WPCustomLoginManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class WPCLM_Turnstile {
    /**
     * Check if Turnstile is enabled for a specific form
     */
    public function is_enabled_for($form_type) {
        if (!get_option('wpclm_turnstile_enabled')) {
            return false;
        }

        $enabled_forms = get_option('wpclm_turnstile_forms', array('register'));
        return in_array($form_type, $enabled_forms);
    }

    /**
     * Render Turnstile widget
     */
    public function render_widget() {
        $site_key = get_option('wpclm_turnstile_site_key');
        if (empty($site_key)) {
            return;
        }
        ?>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($site_key); ?>"></div>
        <?php
    }

    /**
     * Verify Turnstile response
     *
     * @param string $token Response token from Turnstile
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public function verify_token($token) {
        if (empty($token)) {
            return new WP_Error('missing_token', __('Security verification failed. Please try again.', 'wp-custom-login-manager'));
        }

        $secret_key = get_option('wpclm_turnstile_secret_key');
        if (empty($secret_key)) {
            return new WP_Error('no_secret_key', __('Turnstile secret key is not configured.', 'wp-custom-login-manager'));
        }

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body);

        if (!$result || !$result->success) {
            return new WP_Error('verification_failed', __('Security verification failed. Please try again.', 'wp-custom-login-manager'));
        }

        return true;
    }
}