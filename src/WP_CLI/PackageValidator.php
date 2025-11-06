<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Validates downloaded package files (zip archives) before installation.
 *
 * This class provides validation for cached and freshly downloaded package files
 * to ensure they are not corrupted. Corrupted files can occur when:
 * - A download was interrupted
 * - Filesystem issues caused incomplete writes
 * - A license expired and the download returned an error message instead of a zip
 * - Network issues caused partial downloads
 */
class PackageValidator {

	/**
	 * Minimum acceptable file size in bytes.
	 * Files smaller than this are considered corrupted.
	 */
	const MIN_FILE_SIZE = 20;

	/**
	 * Validates a package file to ensure it's a valid zip archive.
	 *
	 * Performs the following checks:
	 * 1. File exists
	 * 2. File size is at least MIN_FILE_SIZE bytes
	 * 3. If 'unzip' command is available, validates zip integrity
	 *
	 * @param string $file_path Path to the file to validate.
	 * @return true|\WP_Error True if valid, WP_Error if validation fails.
	 */
	public static function validate( $file_path ) {
		// Check if file exists.
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error(
				'package_not_found',
				sprintf( 'Package file not found: %s', $file_path )
			);
		}

		// Check minimum file size.
		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size < self::MIN_FILE_SIZE ) {
			return new \WP_Error(
				'package_too_small',
				sprintf(
					'Package file is too small (%d bytes). This usually indicates a corrupted download.',
					$file_size ?: 0
				)
			);
		}

		// If unzip is available, test the zip file integrity.
		if ( self::is_unzip_available() ) {
			$validation_result = self::validate_with_unzip( $file_path );
			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}
		}

		return true;
	}

	/**
	 * Checks if the 'unzip' command is available in the system PATH.
	 *
	 * @return bool True if unzip is available, false otherwise.
	 */
	private static function is_unzip_available() {
		static $is_available = null;

		if ( null === $is_available ) {
			// Check if unzip is in PATH by trying to get its version.
			$result = WP_CLI::launch(
				'unzip -v',
				false,
				true
			);
			$is_available = ( 0 === $result->return_code );
		}

		return $is_available;
	}

	/**
	 * Validates zip file integrity using the 'unzip -t' command.
	 *
	 * @param string $file_path Path to the zip file.
	 * @return true|\WP_Error True if valid, WP_Error if validation fails.
	 */
	private static function validate_with_unzip( $file_path ) {
		$result = WP_CLI::launch(
			sprintf( 'unzip -t %s', escapeshellarg( $file_path ) ),
			false,
			true
		);

		if ( 0 !== $result->return_code ) {
			return new \WP_Error(
				'package_corrupted',
				sprintf(
					'Package file failed zip integrity check. This usually indicates a corrupted or incomplete download.'
				)
			);
		}

		return true;
	}

	/**
	 * Deletes a corrupted package file.
	 *
	 * @param string $file_path Path to the file to delete.
	 * @return bool True if file was deleted or didn't exist, false on failure.
	 */
	public static function delete_corrupted_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return true;
		}

		return @unlink( $file_path );
	}
}
