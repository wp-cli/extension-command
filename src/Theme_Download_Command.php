<?php

use WP_CLI\Utils;
use WP_CLI\WpOrgApi;

/**
 * Downloads theme zip files from the WordPress.org repository.
 *
 * ## OPTIONS
 *
 * <slug>
 * : Slug of the theme to download.
 *
 * [--path=<path>]
 * : Directory to store the downloaded zip file. Defaults to the current directory.
 *
 * [--version=<version>]
 * : Version to download. Accepts a version number or `dev`.
 *
 * [--force]
 * : Overwrite destination file if it already exists.
 *
 * [--insecure]
 * : Retry download without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.
 *
 * ## EXAMPLES
 *
 *     $ wp theme download twentytwelve
 *     Downloading twentytwelve (1.3)...
 *     Success: Downloaded theme package to /path/to/twentytwelve.1.3.zip
 *
 * @when before_wp_load
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
class Theme_Download_Command {

	/**
	 * Downloads a theme zip package without loading WordPress.
	 *
	 * @param array{0: string}                                   $args       Positional arguments.
	 * @param array{path?: string, version?: string, force?: bool, insecure?: bool} $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$slug         = (string) $args[0];
		$insecure     = Utils\get_flag_value( $assoc_args, 'insecure', false );
		$force        = Utils\get_flag_value( $assoc_args, 'force', false );
		$requested    = Utils\get_flag_value( $assoc_args, 'version', null );
		$download_dir = Utils\get_flag_value( $assoc_args, 'path', getcwd() );
		if ( '' === $slug ) {
			WP_CLI::error( 'Please provide a theme slug.' );
		}

		if ( ! is_dir( $download_dir ) ) {
			if ( ! @mkdir( $download_dir, 0755, true ) ) {
				WP_CLI::error( "Failed to create directory '{$download_dir}'." );
			}
		}

		if ( ! is_writable( $download_dir ) ) {
			WP_CLI::error( "'{$download_dir}' is not writable by current user." );
		}

		try {
			$theme_data = ( new WpOrgApi( [ 'insecure' => $insecure ] ) )->get_theme_info( $slug );
		} catch ( Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}

		if ( ! is_array( $theme_data ) || empty( $theme_data['download_link'] ) || empty( $theme_data['version'] ) ) {
			WP_CLI::error( "The '{$slug}' theme could not be found." );
		}

		$download_url = $theme_data['download_link'];
		$version      = $theme_data['version'];

		if ( is_string( $requested ) && '' !== $requested && $requested !== $theme_data['version'] ) {
			$current_zip = basename( (string) Utils\parse_url( $download_url, PHP_URL_PATH ) );
			if ( 'dev' === $requested ) {
				$download_url = str_replace( $current_zip, $slug . '.zip', $download_url );
				$version      = 'Development Version';
			} else {
				$download_url = str_replace( $current_zip, $slug . '.' . $requested . '.zip', $download_url );
				$version      = $requested;
			}
		}

		$zip_name = basename( (string) Utils\parse_url( $download_url, PHP_URL_PATH ) );
		if ( '' === $zip_name ) {
			$zip_name = "{$slug}.zip";
		}

		$download_file = rtrim( $download_dir, '/\\' ) . DIRECTORY_SEPARATOR . $zip_name;

		if ( ! $force && file_exists( $download_file ) ) {
			WP_CLI::error( "Destination file already exists: {$download_file}" );
		}

		WP_CLI::log( "Downloading {$slug} ({$version})..." );

		try {
			Utils\http_request(
				'GET',
				$download_url,
				null,
				[],
				[
					'filename' => $download_file,
					'insecure' => (bool) $insecure,
				]
			);
		} catch ( Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}

		WP_CLI::success( "Downloaded theme package to {$download_file}" );
	}
}
