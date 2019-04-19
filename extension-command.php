<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_extension_autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $wpcli_extension_autoloader ) ) {
	require_once $wpcli_extension_autoloader;
}

WP_CLI::add_command( 'plugin', 'Plugin_Command' );
WP_CLI::add_command( 'theme', 'Theme_Command' );
WP_CLI::add_command( 'theme mod', 'Theme_Mod_Command' );
