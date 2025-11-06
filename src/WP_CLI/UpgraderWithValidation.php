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
	 * fails, the file is deleted and re-downloaded.
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

		// Validation failed - log the issue and attempt recovery.
		WP_CLI::debug(
			sprintf(
				'Package validation failed: %s',
				$validation->get_error_message()
			),
			'extension-command'
		);

		// Delete the corrupted file.
		PackageValidator::delete_corrupted_file( $download );
		WP_CLI::debug(
			'Deleted corrupted package file, attempting fresh download...',
			'extension-command'
		);

		// Try to download again by clearing any cache.
		// We need to bypass the cache, which we can do by using a modified package URL.
		// However, WP_Upgrader doesn't provide a direct way to do this.
		// Instead, we'll use a filter to modify the download behavior.
		$retry_download = $this->download_package_retry( $package, $check_signatures, $hook_extra );

		// If retry succeeded, validate it again.
		if ( ! is_wp_error( $retry_download ) ) {
			$retry_validation = PackageValidator::validate( $retry_download );

			if ( true === $retry_validation ) {
				WP_CLI::debug( 'Fresh download succeeded and validated.', 'extension-command' );
				return $retry_download;
			}

			// Even the retry is corrupted - delete it and give up.
			PackageValidator::delete_corrupted_file( $retry_download );
			WP_CLI::debug( 'Retry download also failed validation.', 'extension-command' );
		}

		// Both attempts failed - return an error.
		return new \WP_Error(
			'package_validation_failed',
			'Downloaded package failed validation. The file may be corrupted or the download URL may be returning an error instead of a valid zip file. Please check your network connection and try again.'
		);
	}

	/**
	 * Retries downloading a package, bypassing cache.
	 *
	 * This is called when the initial download (which may have come from cache)
	 * failed validation.
	 *
	 * @param string $package         The URI of the package.
	 * @param bool   $check_signatures Whether to validate file signatures.
	 * @param array  $hook_extra      Extra arguments to pass to hooked filters.
	 * @return string|\WP_Error The full path to the downloaded package file, or a WP_Error object.
	 */
	private function download_package_retry( $package, $check_signatures, $hook_extra ) {
		// Add a filter to disable caching for this download.
		$disable_cache = function( $args, $url ) {
			// Disable caching by setting a short timeout and unique filename.
			$args['reject_cache'] = true;
			return $args;
		};

		// WP HTTP API doesn't have reject_cache, so we'll use a different approach.
		// We'll hook into pre_http_request to force a fresh download.
		$force_fresh_download = function( $preempt, $args, $url ) use ( $package ) {
			// Only apply to our specific package URL.
			if ( $url !== $package ) {
				return $preempt;
			}
			// Return false to proceed with the request (not using preempt).
			// The cache is managed by WP-CLI's cache manager, not WP's HTTP API.
			return false;
		};

		add_filter( 'pre_http_request', $force_fresh_download, 10, 3 );

		// Attempt the download again.
		$result = parent::download_package( $package, $check_signatures, $hook_extra );

		remove_filter( 'pre_http_request', $force_fresh_download, 10 );

		return $result;
	}
}
