<?php

use WP_CLI\Utils;
use WP_CLI\WpOrgApi;

/**
 * Downloads plugin zip files from the WordPress.org repository.
 */
class Plugin_Download_Command {

	/**
	 * Downloads a plugin zip package without loading WordPress.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Slug of the plugin to download.
	 *
	 * [--target-path=<path>]
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
	 *     $ wp plugin download bbpress
	 *     Downloading bbpress (2.5.9)...
	 *     Success: Downloaded plugin package to /path/to/bbpress.2.5.9.zip
	 *
	 * @when before_wp_load
	 *
	 * @param array{0: string}                                   $args       Positional arguments.
	 * @param array{target-path?: string, version?: string, force?: bool, insecure?: bool} $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$slug = (string) $args[0];
		if ( '' === trim( $slug ) ) {
			WP_CLI::error( 'Please provide a plugin slug.' );
		}

		$insecure     = Utils\get_flag_value( $assoc_args, 'insecure', false );
		$force        = Utils\get_flag_value( $assoc_args, 'force', false );
		$requested    = Utils\get_flag_value( $assoc_args, 'version', null );
		$download_dir = Utils\get_flag_value( $assoc_args, 'target-path', getcwd() ?: '.' );

		if ( ! is_dir( $download_dir ) ) {
			if ( ! @mkdir( $download_dir, 0755, true ) ) {
				WP_CLI::error( "Failed to create directory '{$download_dir}'." );
			}
		}

		if ( ! is_writable( $download_dir ) ) {
			WP_CLI::error( "'{$download_dir}' is not writable by current user." );
		}

		try {
			$plugin_data = ( new WpOrgApi( [ 'insecure' => $insecure ] ) )->get_plugin_info( $slug );
		} catch ( Exception $exception ) {
			WP_CLI::error( "The '{$slug}' plugin could not be found. " . $exception->getMessage() );
		}

		if ( ! is_array( $plugin_data ) || empty( $plugin_data['download_link'] ) || empty( $plugin_data['version'] ) ) {
			WP_CLI::error( "The '{$slug}' plugin could not be found." );
		}

		$download_url = $plugin_data['download_link'];
		$version      = $plugin_data['version'];

		if ( is_string( $requested ) && '' !== $requested && $requested !== $plugin_data['version'] ) {
			$current_zip = basename( (string) Utils\parse_url( $download_url, PHP_URL_PATH ) );
			if ( 'dev' === $requested ) {
				$download_url = str_replace( $current_zip, $slug . '.zip', $download_url );
				$version      = 'Development Version';
			} else {
				$download_url = str_replace( $current_zip, $slug . '.' . $requested . '.zip', $download_url );
				$version      = $requested;

				try {
					$head_response = Utils\http_request( 'HEAD', $download_url, null, [], [ 'insecure' => (bool) $insecure ] );
				} catch ( Exception $exception ) {
					WP_CLI::error( $exception->getMessage() );
				}

				if ( 200 !== (int) $head_response->status_code ) {
					WP_CLI::error(
						sprintf(
							"Can't find the requested plugin's version %s in the WordPress.org plugin repository (HTTP code %d).",
							$requested,
							$head_response->status_code
						)
					);
				}
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

		$destination_file = $download_file;
		$tmp_file         = $download_file;

		if ( $force && file_exists( $destination_file ) ) {
			$tmp_file = $destination_file . '.tmp.' . uniqid( '', true );
		}

		WP_CLI::log( "Downloading {$slug} ({$version})..." );

		try {
			$response = Utils\http_request(
				'GET',
				$download_url,
				null,
				[],
				[
					'filename' => $tmp_file,
					'insecure' => (bool) $insecure,
				]
			);
		} catch ( Exception $exception ) {
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file );
			}
			WP_CLI::error( $exception->getMessage() );
		}

		if ( 200 !== (int) $response->status_code ) {
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file );
			}
			WP_CLI::error( sprintf( 'Failed to download plugin package (HTTP code %d).', $response->status_code ) );
		}

		if ( $tmp_file !== $destination_file ) {
			if ( file_exists( $destination_file ) && ! @unlink( $destination_file ) ) {
				WP_CLI::error( "Failed to remove existing destination file: {$destination_file}" );
			}
			if ( ! @rename( $tmp_file, $destination_file ) ) {
				WP_CLI::error( "Failed to move downloaded file into place: {$destination_file}" );
			}
		}

		WP_CLI::success( "Downloaded plugin package to {$destination_file}" );
	}
}
