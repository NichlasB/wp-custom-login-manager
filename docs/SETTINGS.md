# Settings Documentation

Complete reference for all WP Custom Login Manager settings.

## General Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_disable_registration` | boolean | 0 | absint | General | Disable new user registration completely |
| `wpclm_logo` | string | (default logo) | sanitize_text_field | General | Custom logo URL for login page |
| `wpclm_logo_width` | int | 200 | absint | General | Logo width in pixels |
| `wpclm_login_url` | string | '/account-login/' | sanitize_text_field | General | Custom login page URL slug |
| `wpclm_login_page_title` | string | 'Login' | sanitize_text_field | General | Login page title for SEO |
| `wpclm_login_page_description` | string | '' | sanitize_text_field | General | Login page meta description |
| `wpclm_default_role` | string | 'subscriber' | sanitize_text_field | General | Default role for new registrations |
| `wpclm_terms_url` | string | '' | esc_url_raw | General | Terms & Conditions page URL |
| `wpclm_privacy_url` | string | '' | esc_url_raw | General | Privacy Policy page URL |
| `wpclm_remember_me_duration` | int | 14 | absint | General | "Remember Me" duration in days |

## Redirect Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_login_redirect` | string | '' | sanitize_text_field | Redirects | Redirect URL after login (empty = default) |
| `wpclm_registration_redirect` | string | '' | sanitize_text_field | Redirects | Redirect URL after registration |
| `wpclm_logged_in_redirect` | string | '/my-account/' | sanitize_text_field | Redirects | Redirect URL for logged-in users visiting login page |
| `wpclm_wc_redirect` | string | '' | sanitize_text_field | Redirects | WooCommerce-specific redirect after login |

## Welcome Message Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_login_welcome_text` | string | 'Welcome back!' | sanitize_text_field | Messages | Welcome text on login form |
| `wpclm_register_welcome_text` | string | 'Create your account' | sanitize_text_field | Messages | Welcome text on registration form |
| `wpclm_lostpassword_welcome_text` | string | 'Reset your password' | sanitize_text_field | Messages | Welcome text on password reset form |
| `wpclm_resetpass_welcome_text` | string | 'Enter new password' | sanitize_text_field | Messages | Welcome text on reset password form |
| `wpclm_setpassword_welcome_text` | string | 'Set your password' | sanitize_text_field | Messages | Welcome text on set password form |

## Design Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_background_image` | string | (default image) | sanitize_text_field | Design | Background image URL for login page |
| `wpclm_custom_css` | string | '' | wp_strip_all_tags | Design | Custom CSS for login page |
| `wpclm_button_background_color` | string | '#2271B1' | sanitize_hex_color | Design | Button background color |
| `wpclm_button_text_color` | string | '#FFFFFF' | sanitize_hex_color | Design | Button text color |
| `wpclm_link_color` | string | '#2271B1' | sanitize_hex_color | Design | Link color |
| `wpclm_login_form_background_color` | string | '#F5F5F5' | sanitize_hex_color | Design | Form background color |
| `wpclm_email_background_color` | string | '#F5F5F5' | sanitize_hex_color | Design | Email template background color |
| `wpclm_heading_color` | string | '#1D2327' | sanitize_hex_color | Design | Heading text color |

## Email Template Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_confirmation_email_template` | string | (default template) | wp_kses_post | Emails | HTML template for confirmation emails |
| `wpclm_reset_email_template` | string | (default template) | wp_kses_post | Emails | HTML template for password reset emails |

## Security Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_disable_wp_login` | boolean | 1 | absint | Security | Disable access to wp-login.php (redirect to custom login) |
| `wpclm_max_login_attempts` | int | 6 | absint | Security | Maximum failed login attempts before lockout |
| `wpclm_lockout_duration` | int | 10 | absint | Security | Lockout duration in minutes |
| `wpclm_minify_assets` | boolean | 0 | absint | Security | Use minified CSS/JS assets |
| `wpclm_contact_url` | string | '' | esc_url_raw | Security | Contact URL shown in error messages |
| `wpclm_show_contact_help` | boolean | 0 | absint | Security | Show "contact us" message in errors |
| `wpclm_allow_role_emails` | boolean | 0 | absint | Security | Allow role-based email addresses (e.g., admin@domain.com) |

## Rate Limiting Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_rate_limit_max_attempts` | int | 6 | absint | Security | Max login attempts before rate limiting |
| `wpclm_rate_limit_lockout_duration` | int | 900 | absint | Security | Rate limit lockout duration in seconds (15 min) |
| `wpclm_rate_limit_monitoring_period` | int | 3600 | absint | Security | Period to monitor attempts in seconds (1 hour) |

## Cloudflare Turnstile Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_turnstile_enabled` | boolean | 0 | absint | Security | Enable Cloudflare Turnstile verification |
| `wpclm_turnstile_site_key` | string | '' | sanitize_text_field | Security | Turnstile site key |
| `wpclm_turnstile_secret_key` | string | '' | sanitize_text_field | Security | Turnstile secret key |
| `wpclm_turnstile_forms` | array | [] | array_map sanitize | Security | Forms to enable Turnstile on (login, register, reset) |

## Email Verification Settings (Reoon API)

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_email_verification_enabled` | boolean | 0 | absint | Security | Enable real-time email verification |
| `wpclm_reoon_api_key` | string | '' | sanitize_text_field | Security | Reoon Email Verifier API key |
| `wpclm_reoon_verification_mode` | string | 'quick' | sanitize_text_field | Security | Verification mode (quick/power) |

## Debug Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_enable_debugging` | boolean | 0 | absint | Advanced | Enable debug logging to wpclm-debug.log |

## Custom Error Message Settings

| Option Key | Type | Default | Sanitization | Tab | Description |
|------------|------|---------|--------------|-----|-------------|
| `wpclm_message_login_failed` | string | (default message) | sanitize_text_field | Messages | Custom message for failed login |
| `wpclm_message_email_exists` | string | (default message) | sanitize_text_field | Messages | Custom message when email already exists |
| `wpclm_message_registration_disabled` | string | (default message) | sanitize_text_field | Messages | Custom message when registration is disabled |
| `wpclm_message_password_mismatch` | string | (default message) | sanitize_text_field | Messages | Custom message for password mismatch |
| `wpclm_message_weak_password` | string | (default message) | sanitize_text_field | Messages | Custom message for weak password |
| `wpclm_message_invalid_email` | string | (default message) | sanitize_text_field | Messages | Custom message for invalid email |
| `wpclm_message_required_fields` | string | (default message) | sanitize_text_field | Messages | Custom message for required fields |
| `wpclm_message_confirmation_sent` | string | (default message) | sanitize_text_field | Messages | Custom message when confirmation email sent |
| `wpclm_message_confirmation_success` | string | (default message) | sanitize_text_field | Messages | Custom message on successful confirmation |
| `wpclm_message_confirmation_expired` | string | (default message) | sanitize_text_field | Messages | Custom message when confirmation expired |
| `wpclm_message_password_reset_sent` | string | (default message) | sanitize_text_field | Messages | Custom message when reset email sent |
| `wpclm_message_password_reset_success` | string | (default message) | sanitize_text_field | Messages | Custom message on successful password reset |
| `wpclm_message_password_reset_expired` | string | (default message) | sanitize_text_field | Messages | Custom message when reset link expired |
| `wpclm_message_too_many_attempts` | string | (default message) | sanitize_text_field | Messages | Custom message for too many login attempts |

## Notes

### Setting Default Values

Default values are created during plugin activation via `create_default_options()` method in the main plugin file.

### Retrieving Settings

All settings can be retrieved using WordPress's `get_option()` function:

```php
$logo_url = get_option('wpclm_logo');
$max_attempts = get_option('wpclm_max_login_attempts', 6);
```

### Security Best Practices

- Never expose API keys (Turnstile secret, Reoon API key) in frontend code
- All user-facing settings are properly sanitized on save
- Color settings use `sanitize_hex_color()` to prevent XSS
- URL settings use `esc_url_raw()` for security

### Database Cleanup

All settings are properly removed during plugin uninstall via `uninstall.php`.
