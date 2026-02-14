<?php

namespace WP_CLI;

use WP_CLI;

/**
 * An Upgrader Skin for extensions (plugins/themes) that displays which item is being updated
 *
 * @package wp-cli
 *
 * @property-read array{Name?: string}|null $plugin_info
 * @property-read \WP_Theme|null $theme_info
 */
class ExtensionUpgraderSkin extends UpgraderSkin {

	/**
	 * List of files that were changed during the update process.
	 *
	 * @var array<string>
	 */
	private $changed_files = [];

	/**
	 * Whether to track changed files.
	 *
	 * @var bool
	 */
	private $track_files = false;

	/**
	 * Enable file tracking for opcache invalidation.
	 */
	public function enable_file_tracking() {
		$this->track_files = true;
		$this->setup_file_tracking_hooks();
	}

	/**
	 * Get the list of changed files.
	 *
	 * @return array<string> List of file paths.
	 */
	public function get_changed_files() {
		return $this->changed_files;
	}

	/**
	 * Setup hooks to track file changes during upgrade.
	 */
	private function setup_file_tracking_hooks() {
		// Hook into upgrader_post_install to capture the destination directory
		add_filter(
			'upgrader_post_install',
			function ( $response, $hook_extra, $result ) {
				if ( $this->track_files && is_array( $result ) && isset( $result['destination'] ) && is_string( $result['destination'] ) ) {
					$this->scan_directory_for_files( $result['destination'] );
				}
				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Recursively scan a directory and add all PHP files to the changed files list.
	 *
	 * @param string $dir Directory to scan.
	 */
	private function scan_directory_for_files( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$this->changed_files[] = $file->getPathname();
			}
		}
	}

	/**
	 * Called before an update is performed.
	 */
	public function before() {
		// These properties are defined in `Bulk_Plugin_Upgrader_Skin`/`Bulk_Theme_Upgrader_Skin`
		if ( isset( $this->plugin_info ) && is_array( $this->plugin_info ) && isset( $this->plugin_info['Name'] ) ) {
			WP_CLI::log( sprintf( 'Updating %s...', html_entity_decode( $this->plugin_info['Name'], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		} elseif ( isset( $this->theme_info ) && is_object( $this->theme_info ) && method_exists( $this->theme_info, 'get' ) ) {
			WP_CLI::log( sprintf( 'Updating %s...', html_entity_decode( $this->theme_info->get( 'Name' ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		}
	}
}
