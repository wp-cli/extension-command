<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_extension_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wpcli_extension_autoloader ) ) {
	require_once $wpcli_extension_autoloader;
}

$wpcli_extension_requires_wp_5_5 = [
	'before_invoke' => static function () {
		if ( WP_CLI\Utils\wp_version_compare( '5.5', '<' ) ) {
			WP_CLI::error( 'Requires WordPress 5.5 or greater.' );
		}
	},
];

WP_CLI::add_command( 'plugin', 'Plugin_Command' );
WP_CLI::add_command( 'plugin auto-updates', 'Plugin_AutoUpdates_Command', $wpcli_extension_requires_wp_5_5 );
WP_CLI::add_command( 'theme', 'Theme_Command' );
WP_CLI::add_command( 'theme auto-updates', 'Theme_AutoUpdates_Command', $wpcli_extension_requires_wp_5_5 );
WP_CLI::add_command( 'theme mod', 'Theme_Mod_Command' );

// In admin context, WordPress hooks wp_update_plugins/wp_update_themes to the
// load-plugins.php/load-themes.php actions, causing update checks to run even
// when --skip-update-check is passed. Remove those callbacks via admin_init,
// which fires before load-plugins.php/load-themes.php.
if ( isset( WP_CLI::get_runner()->assoc_args['skip-update-check'] ) ) {
	WP_CLI::add_wp_hook(
		'admin_init',
		static function () {
			remove_action( 'load-plugins.php', 'wp_update_plugins' );
			remove_action( 'load-themes.php', 'wp_update_themes' );
		}
	);
}
