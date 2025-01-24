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

### 1.0.0
- Initial release
- Modern login/registration system
- Email verification
- Security features
- WooCommerce integration
- Mobile-first responsive design
- Custom CSS editor
- Comprehensive settings panel