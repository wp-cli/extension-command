<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Trait for upgraders that validates downloaded packages before installation.
 *
 * This trait adds package validation to WP_Upgrader subclasses to detect and
 * handle corrupted cache files and failed downloads.
 */
trait UpgraderWithValidation {

	/**
	 * Downloads a package with validation.
	 *
	 * This method overrides WP_Upgrader::download_package() to add validation
	 * of the downloaded file before it's used for installation. If validation
	 * fails, the corrupted file is deleted and an error is returned.
	 *
	 * @param string $package              The URI of the package.
	 * @param bool   $check_signatures     Whether to validate file signatures. Default false.
	 * @param array  $hook_extra           Extra arguments to pass to hooked filters. Default empty array.
	 * @return string|\WP_Error The full path to the downloaded package file, or a WP_Error object.
	 */
	public function download_package( $package, $check_signatures = false, $hook_extra = array() ) {
		// Call parent download_package to get the file (from cache or fresh download).
		$download = parent::download_package( $package, $check_signatures, $hook_extra );

		// If download failed, return the error.
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		// Validate the downloaded file.
		$validation = PackageValidator::validate( $download );

		// If validation passed, return the file path.
		if ( true === $validation ) {
			return $download;
		}

		// Validation failed - log the issue and clean up.
		WP_CLI::debug(
			sprintf(
				'Package validation failed: %s',
				$validation->get_error_message()
			),
			'extension-command'
		);

		// Delete the corrupted file to prevent it from being reused.
		if ( PackageValidator::delete_corrupted_file( $download ) ) {
			WP_CLI::debug(
				'Deleted corrupted package file from cache.',
				'extension-command'
			);
		}

		// Return a detailed error message.
		return new \WP_Error(
			'package_validation_failed',
			sprintf(
				'Downloaded package failed validation (%s). The corrupted file has been removed from cache. Please try the command again.',
				$validation->get_error_message()
			)
		);
	}
}
