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
		// Hook into wp_opcache_invalidate_file filter to track files being invalidated
		add_filter(
			'wp_opcache_invalidate_file',
			function ( $file ) {
				if ( $this->track_files && ! empty( $file ) ) {
					$this->changed_files[] = $file;
				}
				return $file;
			},
			10,
			1
		);
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
