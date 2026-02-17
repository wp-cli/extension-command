<?php

/**
 * Manages theme cache.
 *
 * ## EXAMPLES
 *
 *     # Clear cache for a specific theme
 *     $ wp theme cache clear twentytwentyfour
 *     Success: Cleared cache for 'twentytwentyfour' theme.
 *
 *     # Flush the entire theme cache group
 *     $ wp theme cache flush
 *     Success: The theme cache was flushed.
 */
class Theme_Cache_Command extends WP_CLI_Command {

	/**
	 * Clears the cache for one or more themes.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>...]
	 * : One or more themes to clear the cache for.
	 *
	 * [--all]
	 * : If set, clear cache for all installed themes.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear cache for a single theme
	 *     $ wp theme cache clear twentytwentyfour
	 *     Success: Cleared cache for 'twentytwentyfour' theme.
	 *
	 *     # Clear cache for multiple themes
	 *     $ wp theme cache clear twentytwentythree twentytwentyfour
	 *     Success: Cleared cache for 2 themes.
	 *
	 *     # Clear cache for all themes
	 *     $ wp theme cache clear --all
	 *     Success: Cleared cache for all themes.
	 *
	 * @param string[]          $args       Positional arguments.
	 * @param array{all?: bool} $assoc_args Associative arguments.
	 */
	public function clear( $args, $assoc_args ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more themes, or use --all.' );
		}

		$themes = [];

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$all_themes = wp_get_themes();
			foreach ( $all_themes as $theme ) {
				$themes[] = $theme;
			}
		} else {
			foreach ( $args as $theme_slug ) {
				$theme = wp_get_theme( $theme_slug );
				if ( ! $theme->exists() ) {
					WP_CLI::warning( "Theme '{$theme_slug}' not found." );
					continue;
				}
				$themes[] = $theme;
			}
		}

		if ( empty( $themes ) ) {
			WP_CLI::error( 'No valid themes to clear cache for.' );
		}

		$cleared = 0;
		foreach ( $themes as $theme ) {
			$theme->cache_delete();
			++$cleared;
		}

		if ( 1 === $cleared ) {
			WP_CLI::success( "Cleared cache for '{$themes[0]->get_stylesheet()}' theme." );
		} else {
			WP_CLI::success( "Cleared cache for {$cleared} themes." );
		}
	}

	/**
	 * Flushes the entire theme cache group.
	 *
	 * ## EXAMPLES
	 *
	 *     # Flush the entire theme cache group
	 *     $ wp theme cache flush
	 *     Success: The theme cache was flushed.
	 *
	 * @param string[] $args       Positional arguments. Unused.
	 * @param array    $assoc_args Associative arguments. Unused.
	 */
	public function flush( $args, $assoc_args ) {
		// Only added in WordPress 6.1.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'themes' );
			WP_CLI::success( 'The theme cache was flushed.' );
			return;
		}

		// Fallback for WordPress versions prior to 6.1: clear cache for all themes.
		if ( function_exists( 'wp_get_themes' ) ) {
			$all_themes = wp_get_themes();
			foreach ( $all_themes as $theme ) {
				$theme->cache_delete();
			}
			WP_CLI::success( 'The theme cache was flushed.' );
		} else {
			WP_CLI::warning( 'Your WordPress version does not support flushing the theme cache group.' );
		}
	}
}
