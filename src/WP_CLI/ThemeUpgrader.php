<?php

namespace WP_CLI;

/**
 * A theme upgrader class that tracks changed files.
 */
class ThemeUpgrader extends \Theme_Upgrader {
	/**
	 * List of files that were changed during the update process.
	 *
	 * @var array<string>
	 */
	private $changed_files = [];

	public function install_package( $args = array() ) {
		parent::upgrade_strings(); // Needed for the 'remove_old' string.

		$track_files = function ( $will_invalidate, $filepath ) {
			$this->changed_files[] = $filepath;
			return $will_invalidate;
		};

		add_filter( 'wp_opcache_invalidate_file', $track_files, 10, 2 );

		$result = parent::install_package( $args );

		remove_filter( 'wp_opcache_invalidate_file', $track_files );

		// Remove duplicates and sort files.
		$this->changed_files = array_unique( $this->changed_files );
		sort( $this->changed_files );

		return $result;
	}

	/**
	 * Returns a list of files that were changed during the update process.
	 *
	 * @return array<string> Changed files.
	 */
	public function get_changed_files() {
		return $this->changed_files;
	}
}
