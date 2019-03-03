<?php

namespace WP_CLI\Fetchers;

/**
 * Fetch a WordPress theme based on one of its attributes.
 */
class Theme extends Base {

	/**
	 * @var string $msg Error message to use when invalid data is provided
	 */
	protected $msg = "The '%s' theme could not be found.";

	/**
	 * Get a theme object by name
	 *
	 * @param string $name
	 * @return object|false
	 */
	public function get( $name ) {
		// Workaround to equalize folder naming conventions across Win/Mac/Linux
		// Returns false if theme stylesheet doesn't exactly match existing themes.
		$existing_themes = wp_get_themes( array( 'errors' => null ) );
		$existing_stylesheets = array_keys( $existing_themes );
		if ( ! in_array( $name, $existing_stylesheets, true ) ) {
			return false;
		}

		$theme = $existing_themes[ $name ];

		return $theme;
	}
}

