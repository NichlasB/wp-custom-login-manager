# WP Custom Login Manager

A modern, secure custom login and registration system for WordPress with email verification.

![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)
![WordPress Version](https://img.shields.io/badge/WordPress-5.0%2B-blue)

## Features

- 🔐 Modern, responsive login and registration forms
- ✉️ Email verification for new registrations
- 🎨 Two-column layout with customizable background
- 🔑 Password strength indicator
- 🖼️ Custom logo upload with size control
- 📱 Mobile-first design
- 🛡️ Advanced security features
- 🔄 Automatic updates via GitHub
- 🛒 WooCommerce integration
- ♿ WCAG accessibility compliance

## Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/wp-custom-login-manager`
3. Activate through WordPress plugins screen
4. Go to Settings > WP Custom Login Manager to configure
5. Updates will be automatically detected through WordPress

## Usage

### Basic Configuration

1. Upload your logo and background image
2. Customize welcome message
3. Configure security settings
4. Set up email templates
5. Configure redirect rules

### Available Filters

The plugin provides various filters to customize forms, emails, and redirects. See our documentation for a complete list of available filters.

### Available Actions

Multiple action hooks are available for form customization and user verification events. Refer to our documentation for detailed usage.

## Template Customization

### Override Templates

Templates can be overridden by copying them from the plugin's `/templates/` directory to your theme directory under `wp-custom-login-manager/`.

Available templates:
- Login Form
- Registration Form
- Reset Password Form

### Style Customization

The plugin uses CSS variables for easy styling customization. You can override these variables in your theme's stylesheet.

## Security Features

- Email verification for new registrations
- Password strength requirements
- Failed login attempt limiting
- Password reuse prevention
- CSRF protection
- Honeypot spam protection
- Secure password reset process
- XSS protection
- SQL injection protection

## WooCommerce Integration

- Seamless integration with WooCommerce login/register forms
- Support for WooCommerce endpoints in redirects
- WooCommerce-specific role management
- Custom redirects for WooCommerce pages

## Requirements

- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## License

This project is licensed under the GPL v2 or later - see the LICENSE file for details.

## Changelog

### 1.1.6
* Fixed and optimized GitHub-based update system
* Improved update reliability with better URL handling

### 1.1.5
* Replaced plugin update checker with custom GitHub updater implementation
* Removed Composer dependencies for a lighter plugin footprint
* Improved update process reliability

### 1.1.4
- Added option to disable the "If you need help creating an account, contact us" message in error messages
- The contact help message is now disabled by default on new installations

### 1.1.3
* Fixed role-based email detection to handle both API status and flag
* Fixed role-based email validation to properly respect allow setting
* Enhanced role-based email examples in settings description

### 1.1.2
* Added option to allow role-based email addresses during registration
* Added configurable contact URL in security settings for error messages
* Enhanced contact link in error messages to use configured URL instead of hardcoded path
* Added proper URL escaping for contact links

### 1.1.1
- Fixed: Password reset form now properly displays error messages when security verification fails
- Fixed: Improved error message handling using WordPress transients for more reliable message display
- Fixed: Cleaned up debug code and optimized error message styling

### 1.1.0
- Implemented rate limiting system to prevent brute force attacks
- Added IP-based monitoring of login attempts
- Introduced configurable security settings for attempt limits and lockouts
- Enhanced error handling with clear user feedback
- Added robust IP detection supporting proxy configurations
- Integrated Cloudflare Turnstile for advanced bot protection
- Added Reoon Email Verification API for real-time email validation
- Added new configuration options for security features

### 1.0.3
- Fix: Removed duplicate Settings link from plugins page

### 1.0.2
- Fixed Composer autoloader conflict when multiple plugins use the same update checker

### 1.0.1
- Updated README.md

### 1.0.0
- Initial release
- Modern login/registration system
- Email verification
- Security features
- WooCommerce integration
- Mobile-first responsive design
- Custom CSS editor
- Comprehensive settings panel