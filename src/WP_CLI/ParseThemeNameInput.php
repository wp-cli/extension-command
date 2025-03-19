<?php

namespace WP_CLI;

use WP_CLI;
use Theme_AutoUpdates_Command;

trait ParseThemeNameInput {

	/**
	 * If have optional args ([<theme>...]) and an all option, then check have something to do.
	 *
	 * @param array  $args Passed-in arguments.
	 * @param bool   $all All flag.
	 * @param string $verb Optional. Verb to use. Defaults to 'install'.
	 * @return array Same as $args if not all, otherwise all slugs.
	 * @throws ExitException If neither plugin name nor --all were provided.
	 */
	protected function check_optional_args_and_all( $args, $all, $verb = 'install' ) {
		if ( $all ) {
			$args = array_map(
				'\WP_CLI\Utils\get_theme_name',
				array_keys( $this->get_all_themes() )
			);
		}

		if ( empty( $args ) ) {
			if ( ! $all ) {
				WP_CLI::error( 'Please specify one or more themes, or use --all.' );
			}

			$past_tense_verb = Utils\past_tense_verb( $verb );
			WP_CLI::success( "No themes {$past_tense_verb}." ); // Don't error if --all given for BC.
		}

		return $args;
	}

	/**
	 * Gets all available themes.
	 *
	 * Uses the same filter core uses in themes.php to determine which themes
	 * should be available to manage through the WP_Themes_List_Table class.
	 *
	 * @return array
	 */
	private function get_all_themes() {
		global $wp_version;
		// Extract the major WordPress version (e.g., "6.3") from the full version string
		list($wp_core_version) = explode( '-', $wp_version );
		$wp_core_version       = implode( '.', array_slice( explode( '.', $wp_core_version ), 0, 2 ) );

		$items              = array();
		$theme_version_info = array();

		if ( is_multisite() ) {
			$site_enabled = get_option( 'allowedthemes' );
			if ( empty( $site_enabled ) ) {
				$site_enabled = array();
			}

			$network_enabled = get_site_option( 'allowedthemes' );
			if ( empty( $network_enabled ) ) {
				$network_enabled = array();
			}
		}

		$all_update_info = $this->get_update_info();
		$checked_themes  = isset( $all_update_info->checked ) ? $all_update_info->checked : array();

		if ( ! empty( $checked_themes ) ) {
			foreach ( $checked_themes as $slug => $version ) {
				$theme_version_info[ $slug ] = $this->is_theme_version_valid( $slug, $version );
			}
		}

		$auto_updates = get_site_option( Theme_AutoUpdates_Command::SITE_OPTION );

		if ( ! is_array( $auto_updates ) ) {
			$auto_updates = [];
		}

		foreach ( wp_get_themes() as $key => $theme ) {
			$stylesheet  = $theme->get_stylesheet();
			$update_info = ( isset( $all_update_info->response[ $stylesheet ] ) && null !== $all_update_info->response[ $theme->get_stylesheet() ] ) ? (array) $all_update_info->response[ $theme->get_stylesheet() ] : null;

			// Unlike plugin update responses, the wordpress.org API does not seem to check and filter themes that don't meet
			// WordPress version requirements into a separate no_updates array
			// Also unlike plugin update responses, the wordpress.org API seems to always include requires AND requires_php
			$requires     = isset( $update_info ) && isset( $update_info['requires'] ) ? $update_info['requires'] : null;
			$requires_php = isset( $update_info ) && isset( $update_info['requires_php'] ) ? $update_info['requires_php'] : null;

			$compatible_php = empty( $requires_php ) || version_compare( PHP_VERSION, $requires_php, '>=' );
			$compatible_wp  = empty( $requires ) || version_compare( $wp_version, $requires, '>=' );

			if ( ! $compatible_php ) {
				$update = 'unavailable';

				$update_unavailable_reason = sprintf(
					'This update requires PHP version %s, but the version installed is %s.',
					$requires_php,
					PHP_VERSION
				);
			} elseif ( ! $compatible_wp ) {
				$update = 'unavailable';

				$update_unavailable_reason = sprintf(
					'This update requires WordPress version %s, but the version installed is %s.',
					$requires,
					$wp_version
				);
			} else {
				$update = $update_info ? 'available' : 'none';
			}

			// For display consistency, get these values from the current plugin file if they aren't in this response
			if ( null === $requires ) {
				$requires = ! empty( $theme->get( 'RequiresWP' ) ) ? $theme->get( 'RequiresWP' ) : '';
			}

			if ( null === $requires_php ) {
				$requires_php = ! empty( $theme->get( 'RequiresPHP' ) ) ? $theme->get( 'RequiresPHP' ) : '';
			}

			$items[ $stylesheet ] = [
				'name'                      => $key,
				'status'                    => $this->get_status( $theme ),
				'update'                    => $update,
				'update_version'            => isset( $update_info['new_version'] ) ? $update_info['new_version'] : null,
				'update_package'            => isset( $update_info['package'] ) ? $update_info['package'] : null,
				'version'                   => $theme->get( 'Version' ),
				'update_id'                 => $stylesheet,
				'title'                     => $theme->get( 'Name' ),
				'description'               => wordwrap( $theme->get( 'Description' ) ),
				'author'                    => $theme->get( 'Author' ),
				'auto_update'               => in_array( $stylesheet, $auto_updates, true ),
				'requires'                  => $requires,
				'requires_php'              => $requires_php,
				'update_unavailable_reason' => isset( $update_unavailable_reason ) ? $update_unavailable_reason : '',
			];

			// Compare version and update information in theme list.
			if ( isset( $theme_version_info[ $key ] ) && false === $theme_version_info[ $key ] ) {
				$items[ $stylesheet ]['update'] = 'version higher than expected';
			}

			if ( is_multisite() ) {
				if ( ! empty( $site_enabled[ $key ] ) && ! empty( $network_enabled[ $key ] ) ) {
					$items[ $stylesheet ]['enabled'] = 'network,site';
				} elseif ( ! empty( $network_enabled[ $key ] ) ) {
					$items[ $stylesheet ]['enabled'] = 'network';
				} elseif ( ! empty( $site_enabled[ $key ] ) ) {
					$items[ $stylesheet ]['enabled'] = 'site';
				} else {
					$items[ $stylesheet ]['enabled'] = 'no';
				}
			}
		}

		return $items;
	}

	/**
	 * Check if current version of the theme is higher than the one available at WP.org.
	 *
	 * @param string $slug Theme slug.
	 * @param string $version Theme current version.
	 *
	 * @return bool|string
	 */
	protected function is_theme_version_valid( $slug, $version ) {
		// Get Theme Info.
		$theme_info = themes_api( 'theme_information', array( 'slug' => $slug ) );

		// Return empty string for themes not on WP.org.
		if ( is_wp_error( $theme_info ) ) {
			return '';
		}

		// Compare theme version info.
		return ! version_compare( $version, $theme_info->version, '>' );
	}

	/**
	 * Get the status for a given theme.
	 *
	 * @param WP_Theme $theme Theme to get the status for.
	 *
	 * @return string Status of the theme.
	 */
	protected function get_status( $theme ) {
		if ( $this->is_active_theme( $theme ) ) {
			return 'active';
		}

		if ( $theme->get_stylesheet_directory() === get_template_directory() ) {
			return 'parent';
		}

		return 'inactive';
	}

	/**
	 * Check whether a given theme is the active theme.
	 *
	 * @param WP_Theme $theme Theme to check.
	 *
	 * @return bool Whether the provided theme is the active theme.
	 */
	protected function is_active_theme( $theme ) {
		return $theme->get_stylesheet_directory() === get_stylesheet_directory();
	}

	/**
	 * Check whether a given theme is the active theme parent.
	 *
	 * @param WP_Theme $theme Theme to check.
	 *
	 * @return bool Whether the provided theme is the active theme.
	 */
	protected function is_active_parent_theme( $theme ) {
		return $theme->get_stylesheet_directory() === get_template_directory();
	}

	/**
	 * Get the available update info.
	 *
	 * @return mixed Available update info.
	 */
	protected function get_update_info() {
		return get_site_transient( 'update_themes' );
	}
}
