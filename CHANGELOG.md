# Changelog

All notable changes to WP Custom Login Manager will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2025-03-07

### Fixed
- Turnstile widget now properly appears on login form when enabled in settings
- Improved debugging for Turnstile integration to help troubleshoot visibility issues

## [1.1.9] - 2025-02-23

### Added
- Special styling for checkout redirect messages
- JavaScript handling for checkout redirect scenarios

### Changed
- Improved checkout redirect functionality for WooCommerce
- Enhanced login page UI for checkout redirects

### Fixed
- Duplicate settings links in plugin actions
- Version number inconsistencies
- Improved error handling for redirect scenarios

## [1.1.8]

### Changed
- Updated plugin description for clarity

## [1.1.7]

### Changed
- Removed unnecessary whitespace in main plugin file
- Improved code readability

## [1.1.6]

### Fixed
- Fixed and optimized GitHub-based update system
- Improved update reliability with better URL handling

## [1.1.5]

### Changed
- Replaced plugin update checker with custom GitHub updater implementation
- Removed Composer dependencies for a lighter plugin footprint

### Fixed
- Improved update process reliability

## [1.1.4]

### Added
- Option to disable the "If you need help creating an account, contact us" message in error messages
- The contact help message is now disabled by default on new installations

## [1.1.3]

### Fixed
- Role-based email detection to handle both API status and flag
- Role-based email validation to properly respect allow setting
- Enhanced role-based email examples in settings description

## [1.1.2]

### Added
- Option to allow role-based email addresses during registration
- Configurable contact URL in security settings for error messages
- Enhanced contact link in error messages to use configured URL instead of hardcoded path
- Proper URL escaping for contact links

## [1.1.1]

### Fixed
- Password reset form now properly displays error messages when security verification fails
- Improved error message handling using WordPress transients for more reliable message display
- Cleaned up debug code and optimized error message styling

## [1.1.0]

### Added
- Rate limiting system to prevent brute force attacks
- IP-based monitoring of login attempts
- Configurable security settings for attempt limits and lockouts
- Robust IP detection supporting proxy configurations
- Cloudflare Turnstile integration for advanced bot protection
- Reoon Email Verification API for real-time email validation
- New configuration options for security features

### Changed
- Enhanced error handling with clear user feedback

## [1.0.3]

### Fixed
- Removed duplicate Settings link from plugins page

## [1.0.2]

### Fixed
- Composer autoloader conflict when multiple plugins use the same update checker

## [1.0.1]

### Changed
- Updated README.md

## [1.0.0] - Initial Release

### Added
- Modern login/registration system
- Email verification for new registrations
- Two-column layout with customizable background
- Password strength indicator
- Custom logo upload with size control
- Mobile-first responsive design
- Advanced security features
- WooCommerce integration
- WCAG accessibility compliance
- Custom CSS editor
- Comprehensive settings panel
- Password reset functionality
- Rate limiting
- Failed login attempt tracking
