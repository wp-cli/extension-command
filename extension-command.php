<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'plugin', 'Plugin_Command' );
WP_CLI::add_command( 'theme', 'Theme_Command' );
WP_CLI::add_command( 'theme mod', 'Theme_Mod_Command' );
