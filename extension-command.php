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
WP_CLI::add_command( 'theme cache', 'Theme_Cache_Command' );

// In admin context, WordPress hooks wp_update_plugins/wp_update_themes to
// various actions, causing update checks to run even when --skip-update-check is passed.

WP_CLI::add_hook(
	'before_wp_load',
	static function () {
		if ( ! \WP_CLI\Utils\get_flag_value( WP_CLI::get_runner()->assoc_args, 'skip-update-check' ) ) {
			return;
		}

		WP_CLI::add_wp_hook(
			'wp_loaded',
			static function () {
				remove_action( 'load-plugins.php', 'wp_update_plugins' );
				remove_action( 'load-update.php', 'wp_update_plugins' );
				remove_action( 'load-update-core.php', 'wp_update_plugins' );
				remove_action( 'admin_init', '_maybe_update_plugins' );
				remove_action( 'wp_update_plugins', 'wp_update_plugins' );

				remove_action( 'load-themes.php', 'wp_update_themes' );
				remove_action( 'load-update.php', 'wp_update_themes' );
				remove_action( 'load-update-core.php', 'wp_update_themes' );
				remove_action( 'admin_init', '_maybe_update_themes' );
				remove_action( 'wp_update_themes', 'wp_update_themes' );
			},
			PHP_INT_MIN
		);
	}
);
