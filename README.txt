=== WP Custom Login Manager ===
Contributors: Alynt
Tags: login, registration, custom login, user registration, authentication, email verification
Requires at least: 5.0
Tested up to: 6.7.1
Stable tag: 1.1.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, secure custom login and registration system for WordPress with email verification.

== Description ==

WP Custom Login Manager replaces the default WordPress login and registration system with a modern, secure, and customizable alternative. It features a responsive two-column design, email verification for new registrations, and comprehensive customization options.

= Key Features =

* Modern, responsive login and registration forms
* Two-column layout with customizable background image
* Email verification for new registrations
* Password strength indicator
* Custom logo upload with size control
* Customizable welcome message
* Custom redirect URLs after login/registration
* Password reuse prevention
* Failed login attempt limiting
* Customizable HTML email templates
* Custom CSS support
* WCAG accessibility compliance
* WooCommerce compatibility

= Security Features =

* Email verification for new registrations
* Password strength requirements
* Failed login attempt limiting
* Password reuse prevention
* CSRF protection
* Honeypot spam protection
* Secure password reset process
* XSS protection
* SQL injection protection

= Customization Options =

* Upload and resize logo
* Set custom background image
* Customize welcome message
* Set button and link colors
* Add custom CSS
* Customize email templates
* Set redirect URLs
* Choose default user role

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-custom-login-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Custom Login → Settings to configure the plugin.

= Quick Setup Guide =

1. After activation, the plugin automatically replaces the default WordPress login system
2. Configure your settings under Custom Login → Settings
3. Test the login and registration process
4. Customize the appearance and functionality as needed

== Frequently Asked Questions ==

= Can I keep the default WordPress login page accessible? =

Yes, you can disable the redirect to the custom login page in the plugin settings. The default wp-login.php will always be accessible by adding ?direct_login=true to the URL for emergency access.

= Does this work with WooCommerce? =

Yes, the plugin is fully compatible with WooCommerce and can use WooCommerce user roles and account pages.

= Can I customize the email templates? =

Yes, you can customize both the registration confirmation and password reset email templates using the built-in editor. Templates support HTML and various placeholders like {site_name} and {user_name}.

= Is the login form responsive? =

Yes, the form automatically adjusts to a single-column layout on screens smaller than 800px wide, and the background image is hidden on mobile devices for better performance.

= Can I add custom CSS? =

Yes, there's a dedicated custom CSS field in the settings where you can add your own styles.

== Translations ==

WP Custom Login Manager is translation-ready. To create a translation:

1. Use the included wp-custom-login-manager.pot file as a template
2. Create translations using a POT editor like Poedit
3. Save the .po and .mo files in the plugin's /languages directory using the correct locale:
   - wp-custom-login-manager-fr_FR.po
   - wp-custom-login-manager-fr_FR.mo

To contribute a translation, please visit the plugin's WordPress.org repository.

== Changelog ==

= 1.1.1 =
* Fixed: Password reset form now properly displays error messages when security verification fails
* Fixed: Improved error message handling using WordPress transients for more reliable message display
* Fixed: Cleaned up debug code and optimized error message styling

= 1.1.0 =
* Added rate limiting functionality to protect against brute force attacks
* Enhanced security with IP-based login attempt monitoring
* Added configurable settings for maximum login attempts and lockout duration
* Improved error handling and user feedback for login attempts
* Added secure IP detection through multiple proxy headers
* Integrated Cloudflare Turnstile for enhanced bot protection
* Added Reoon Email Verification API integration for email validation
* Added configurable settings for Turnstile and Email verification

= 1.0.3 =
* Fix: Removed duplicate Settings link from plugins page

= 1.0.2 =
* Fixed Composer autoloader conflict when multiple plugins use the same update checker

= 1.0.1 =
* Updated README.md

= 1.0.0 =
* Initial release
* Automatic login form integration
* WooCommerce compatibility
* Enhanced security features

== Upgrade Notice ==

= 1.1.1 =
This update fixes an important issue with error message display during password resets. If you're using the password reset functionality with Turnstile verification, we recommend updating.

= 1.1.0 =
Security enhancement: This version adds rate limiting, Cloudflare Turnstile integration, and email verification. Please configure your API keys in the settings after updating.

= 1.0.2 =
This update fixes a compatibility issue where the plugin could conflict with other plugins using the same update checker system. Update recommended if you use multiple plugins with GitHub-based updates.

= 1.0.0 =
Initial release with automatic login form integration and WooCommerce compatibility.

== Additional Information ==

= Email Template Placeholders =

The following placeholders are available in email templates:
* {site_name} - Your website name
* {user_name} - The user's name or email
* {confirmation_link} - Registration confirmation link
* {reset_link} - Password reset link

= Emergency Access =

If you ever need to access the default WordPress login page, you can do so by adding ?direct_login=true to your wp-login.php URL:
* example.com/wp-login.php?direct_login=true

= Recommended Image Sizes =

* Logo: 200x80px (will be automatically resized based on settings)
* Background Image: 1920x1080px minimum (will be scaled to fit)

== Privacy Policy ==

This plugin collects the following user data during registration:
* First Name
* Last Name
* Email Address

The plugin also tracks failed login attempts using IP addresses for security purposes. This data is automatically deleted after the lockout period expires.

For sites requiring GDPR compliance, please ensure your privacy policy includes information about the data collected during user registration.