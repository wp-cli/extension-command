<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress plugin based on one of its attributes.
 *
 * @extends Base<object{name: string, file: string}>
 */
class Plugin extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "The '%s' plugin could not be found.";

	/**
	 * Get a plugin object by name
	 *
	 * @param string|int $name Plugin name.
	 * @return object{name: string, file: string}|false
	 */
	public function get( $name ) {
		$name = (string) $name;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Calling native WordPress hook.
		foreach ( apply_filters( 'all_plugins', get_plugins() ) as $file => $_ ) {
			if ( "$name.php" === $file ||
				( $name && $file === $name ) ||
				( dirname( $file ) === $name && '.' !== $name ) ) {
				return (object) compact( 'name', 'file' );
			}
		}

		return false;
	}
}
