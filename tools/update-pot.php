<?php
/**
 * Generate POT file for the plugin
 */

// Path to wp-cli
$wp_cli = '/usr/local/bin/wp';

// Plugin paths
$plugin_dir = dirname(__DIR__);
$languages_dir = $plugin_dir . '/languages';
$pot_file = $languages_dir . '/wp-custom-login-manager.pot';

// Make sure languages directory exists
if (!file_exists($languages_dir)) {
    mkdir($languages_dir, 0755, true);
}

// Command to generate POT file
$command = sprintf(
    '%s i18n make-pot %s %s --exclude="tools,tests,node_modules" --headers=\'{"Report-Msgid-Bugs-To":"https://wordpress.org/support/plugin/wp-custom-login-manager"}\' --domain="wp-custom-login-manager"',
    $wp_cli,
    escapeshellarg($plugin_dir),
    escapeshellarg($pot_file)
);

// Execute command
exec($command, $output, $return_var);

if ($return_var === 0) {
    echo "POT file generated successfully!\n";
} else {
    echo "Error generating POT file.\n";
}