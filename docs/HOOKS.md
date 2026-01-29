# Hooks Documentation

Complete reference for all actions and filters provided by WP Custom Login Manager.

## Actions

### `wpclm_before_form_submission`

Fires before processing a form submission (login, registration, or password reset).

**Location:** `class-wpclm-form-submission-handler.php`

**Parameters:**
- `$form_type` (string) — The type of form being submitted ('login', 'register', 'reset_password')
- `$submission_data` (array) — The sanitized form data being submitted

**Example:**
```php
add_action('wpclm_before_form_submission', function($form_type, $data) {
    if ($form_type === 'login') {
        // Log login attempt
        error_log('Login attempt for: ' . $data['email']);
    }
}, 10, 2);
```

---

### `wpclm_template_load`

Fires when a custom template is being loaded.

**Location:** `class-wpclm-login-page-renderer.php`

**Parameters:**
- `$template_name` (string) — Name of the template being loaded
- `$template_data` (array) — Data being passed to the template

**Example:**
```php
add_action('wpclm_template_load', function($template_name, $data) {
    // Track which templates are being used
    if ($template_name === 'login-form') {
        // Custom logic for login form loads
    }
}, 10, 2);
```

---

### `wp_logout`

Standard WordPress action that WPCLM hooks into to clear message cookies.

**Usage in WPCLM:** Clears error and success message cookies on logout.

---

## Filters

### `wpclm_logged_in_redirect_url`

Filters the redirect URL for logged-in users who visit the login page.

**Location:** `class-wpclm-login-page-renderer.php:145`, `class-wpclm-access-control.php:265`

**Parameters:**
- `$redirect_url` (string) — The default redirect URL

**Return:** (string) Modified redirect URL

**Example:**
```php
add_filter('wpclm_logged_in_redirect_url', function($redirect_url) {
    // Redirect logged-in users to a custom dashboard
    return home_url('/custom-dashboard/');
});
```

---

### `wpclm_login_messages`

Filters messages displayed on the login form.

**Location:** `class-wpclm-login-page-renderer.php:319`

**Parameters:**
- `$messages` (array) — Array of message strings to display

**Return:** (array) Modified array of messages

**Example:**
```php
add_filter('wpclm_login_messages', function($messages) {
    // Add a custom promotional message
    $messages[] = 'Sign up today and get 20% off!';
    return $messages;
});
```

---

### `wpclm_protected_paths`

Filters the list of URL paths that require authentication.

**Location:** `class-wpclm-access-control.php:213, 241`

**Parameters:**
- `$protected_paths` (array) — Array of path strings (e.g., ['my-account', 'dashboard'])

**Return:** (array) Modified array of protected paths

**Default:** `array('my-account')`

**Example:**
```php
add_filter('wpclm_protected_paths', function($paths) {
    // Add custom protected paths
    $paths[] = 'premium-content';
    $paths[] = 'members-area';
    return $paths;
});
```

---

### `wpclm_available_roles`

Filters the list of roles available for new user registration.

**Location:** `class-wpclm-woocommerce.php:51`

**Parameters:**
- `$roles` (array) — Associative array of role slug => role name

**Return:** (array) Modified roles array

**Example:**
```php
add_filter('wpclm_available_roles', function($roles) {
    // Add custom role to registration dropdown
    $roles['premium_member'] = __('Premium Member', 'your-textdomain');
    return $roles;
});
```

---

### `wpclm_redirect_options`

Filters the redirect options available in plugin settings.

**Location:** `class-wpclm-woocommerce.php:54`

**Parameters:**
- `$options` (array) — Array of redirect option configurations

**Return:** (array) Modified options array

**Example:**
```php
add_filter('wpclm_redirect_options', function($options) {
    // Add custom redirect option
    $options[] = array(
        'value' => '/custom-page/',
        'label' => 'Custom Page'
    );
    return $options;
});
```

---

### `wpclm_login_redirect`

Filters the redirect URL after successful login.

**Location:** `class-wpclm-woocommerce.php:57`

**Parameters:**
- `$redirect_url` (string) — The default redirect URL
- `$user` (WP_User) — The user object who just logged in

**Return:** (string) Modified redirect URL

**Example:**
```php
add_filter('wpclm_login_redirect', function($redirect_url, $user) {
    // Redirect administrators to wp-admin, others to my-account
    if (in_array('administrator', $user->roles)) {
        return admin_url();
    }
    return $redirect_url;
}, 10, 2);
```

---

### `wpclm_allowed_redirect_hosts`

Filters the list of allowed redirect hosts for security.

**Location:** `class-wpclm-woocommerce.php:60`

**Parameters:**
- `$hosts` (array) — Array of allowed host strings

**Return:** (array) Modified hosts array

**Example:**
```php
add_filter('wpclm_allowed_redirect_hosts', function($hosts) {
    // Allow redirects to a subdomain
    $hosts[] = 'app.yourdomain.com';
    return $hosts;
});
```

---

### `wp_mail`

Standard WordPress filter that WPCLM hooks into for email debugging.

**Usage in WPCLM:** Logs all email attempts when debug mode is enabled.

---

### `wp_new_user_notification_email`

Standard WordPress filter that WPCLM uses to customize new user emails.

**Usage in WPCLM:** Replaces default WordPress registration email with custom confirmation email template.

---

### `retrieve_password_message`

Standard WordPress filter that WPCLM uses to customize password reset emails.

**Usage in WPCLM:** Replaces default WordPress password reset email with custom template.

---

### `retrieve_password_title`

Standard WordPress filter that WPCLM uses to customize password reset email subject.

**Usage in WPCLM:** Sets custom subject line for password reset emails.

---

### `authenticate`

Standard WordPress filter that WPCLM hooks into for rate limiting.

**Usage in WPCLM:** Checks login attempts and enforces rate limiting before authentication.

---

### `wp_mail_content_type`

Standard WordPress filter that WPCLM uses to set HTML email content type.

**Usage in WPCLM:** Forces HTML content type for all emails sent by the plugin.

---

## WooCommerce Integration Hooks

These hooks are only active when WooCommerce is installed and active.

### WooCommerce-Specific Actions

- **`template_redirect`** — Used to handle order-pay redirects
- **`wpclm_login_messages`** — Used to display checkout-specific messages

### WooCommerce Roles Added

When WooCommerce is active, the following roles are automatically added to `wpclm_available_roles`:
- `customer` — WooCommerce Customer
- `shop_manager` — WooCommerce Shop Manager

---

## Debug Hooks

When debugging is enabled (`wpclm_enable_debugging` = true), the following hooks are active:

### Actions
- `wpclm_before_form_submission` — Logs form submissions
- `wpclm_template_load` — Logs template loads
- `admin_footer` — Adds debug information to admin footer

### Filters
- `wp_mail` — Logs email attempts
- `authenticate` — Logs authentication attempts

---

## Internal Hooks (For Plugin Architecture)

These hooks are used internally by the plugin's architecture and are not intended for external use:

- `init` — Used to initialize plugin components
- `plugins_loaded` — Used to load text domain
- `wp_enqueue_scripts` — Used to enqueue frontend assets

---

## Hook Naming Convention

All public hooks follow this naming convention:
- Prefix: `wpclm_`
- Format: `wpclm_{context}_{action/filter_name}`

Examples:
- `wpclm_login_redirect`
- `wpclm_protected_paths`
- `wpclm_available_roles`

---

## Creating Custom Integrations

### Example: Custom Role-Based Redirects

```php
add_filter('wpclm_login_redirect', function($redirect_url, $user) {
    // Custom redirect logic based on user role
    if (in_array('premium_member', $user->roles)) {
        return home_url('/premium-dashboard/');
    }
    if (in_array('vendor', $user->roles)) {
        return home_url('/vendor-dashboard/');
    }
    return $redirect_url;
}, 10, 2);
```

### Example: Adding Custom Protected Pages

```php
add_filter('wpclm_protected_paths', function($paths) {
    // Protect course content
    $paths[] = 'courses';
    $paths[] = 'lessons';
    $paths[] = 'my-learning';
    return $paths;
});
```

### Example: Logging Login Attempts

```php
add_action('wpclm_before_form_submission', function($form_type, $data) {
    if ($form_type === 'login') {
        // Log to custom analytics
        do_action('my_analytics_track', 'login_attempt', array(
            'email' => $data['email'],
            'timestamp' => current_time('mysql')
        ));
    }
}, 10, 2);
```

---

## Security Considerations

When using hooks:

1. **Always validate and sanitize data** passed through filters
2. **Check user capabilities** before allowing actions
3. **Escape output** when displaying filtered content
4. **Verify nonces** in form submission hooks
5. **Use proper redirect validation** for redirect URL filters

---

## Support

For questions about using these hooks, please open an issue on GitHub:
https://github.com/NichlasB/wp-custom-login-manager/issues
