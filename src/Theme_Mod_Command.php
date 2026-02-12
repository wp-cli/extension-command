<?php

use \WP_CLI\Utils;

/**
 * Sets, gets, and removes theme mods.
 *
 * ## EXAMPLES
 *
 *     # Set the 'background_color' theme mod to '000000'.
 *     $ wp theme mod set background_color 000000
 *     Success: Theme mod background_color set to 000000.
 *
 *     # Get single theme mod in JSON format.
 *     $ wp theme mod get background_color --format=json
 *     [{"key":"background_color","value":"dd3333"}]
 *
 *     # Remove all theme mods.
 *     $ wp theme mod remove --all
 *     Success: Theme mods removed.
 */
class Theme_Mod_Command extends WP_CLI_Command {

	private $fields = [ 'key', 'value' ];

	/**
	 * Gets one or more theme mods.
	 *
	 * ## OPTIONS
	 *
	 * [<mod>...]
	 * : One or more mods to get.
	 *
	 * [--field=<field>]
	 * : Returns the value of a single field.
	 *
	 * [--all]
	 * : List all theme mods
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get all theme mods.
	 *     $ wp theme mod get --all
	 *     +------------------+---------+
	 *     | key              | value   |
	 *     +------------------+---------+
	 *     | background_color | dd3333  |
	 *     | link_color       | #dd9933 |
	 *     | main_text_color  | #8224e3 |
	 *     +------------------+---------+
	 *
	 *     # Get single theme mod in JSON format.
	 *     $ wp theme mod get background_color --format=json
	 *     [{"key":"background_color","value":"dd3333"}]
	 *
	 *     # Get value of a single theme mod.
	 *     $ wp theme mod get background_color --field=value
	 *     dd3333
	 *
	 *     # Get multiple theme mods.
	 *     $ wp theme mod get background_color header_textcolor
	 *     +------------------+--------+
	 *     | key              | value  |
	 *     +------------------+--------+
	 *     | background_color | dd3333 |
	 *     | header_textcolor |        |
	 *     +------------------+--------+
	 *
	 * @param string[] $args Positional arguments.
	 * @param array{field?: string, all?: bool, format: string} $assoc_args Associative arguments.
	 */
	public function get( $args, $assoc_args ) {

		if ( ! Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( 'You must specify at least one mod or use --all.' );
		}

		if ( Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$args = array();
		}

		// This array will hold the list of theme mods in a format suitable for the WP CLI Formatter.
		$mod_list = array();

		// If specific mods are requested, filter out any that aren't requested.
		$mods = ! empty( $args ) ? array_intersect_key( get_theme_mods(), array_flip( $args ) ) : get_theme_mods();

		// Generate the list of items ready for output. We use an initial separator that we can replace later depending on format.
		$separator = '\t';
		array_walk(
			$mods,
			function ( $value, $key ) use ( &$mod_list, $separator ) {
				$this->mod_to_string( $key, $value, $mod_list, $separator );
			}
		);

		// Take our Formatter-friendly list and adjust it according to the requested format.
		switch ( Utils\get_flag_value( $assoc_args, 'format' ) ) {
			// For tables we use a double space to indent child items.
			case 'table':
				$mod_list = array_map(
					static function ( $item ) use ( $separator ) {
						$parts   = explode( $separator, $item['key'] );
						$new_key = array_pop( $parts );
						if ( ! empty( $parts ) ) {
							$new_key = str_repeat( '  ', count( $parts ) ) . $new_key;
						}
						return [
							'key'   => $new_key,
							'value' => $item['value'],
						];
					},
					$mod_list
				);
				break;

			// For JSON, CSV, and YAML formats we use dot notation to show the hierarchy.
			case 'csv':
			case 'yaml':
			case 'json':
				$mod_list = array_filter(
					array_map(
						static function ( $item ) use ( $separator ) {
							return [
								'key'   => str_replace( $separator, '.', $item['key'] ),
								'value' => $item['value'],
							];
						},
						$mod_list
					),
					function ( $item ) {
						return ! empty( $item['value'] );
					}
				);
				break;
		}

		// Output the list using the WP CLI Formatter.
		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields, 'thememods' );
		$formatter->display_items( $mod_list );
	}

	/**
	 * Convert the theme mods to a flattened array with a string representation of the keys.
	 *
	 * @param string $key       The mod key
	 * @param mixed  $value     The value of the mod.
	 * @param array  $mod_list  The list so far, passed by reference.
	 * @param string $separator A string to separate keys to denote their place in the tree.
	 */
	private function mod_to_string( $key, $value, &$mod_list, $separator ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			// Convert objects to arrays for easier handling.
			$value = (array) $value;

			// Explicitly handle empty arrays to ensure they are displayed.
			if ( empty( $value ) ) {
				$mod_list[] = array(
					'key'   => $key,
					'value' => '[empty array]',
				);
				return;
			}

			// Arrays get their own entry in the list to allow for sensible table output.
			$mod_list[] = array(
				'key'   => $key,
				'value' => '',
			);

			foreach ( $value as $child_key => $child_value ) {
				$this->mod_to_string( $key . $separator . $child_key, $child_value, $mod_list, $separator );
			}
		} else {
			// Explicitly handle boolean values to ensure they are displayed correctly.
			if ( is_bool( $value ) ) {
				$value = $value ? '[true]' : '[false]';
			}

			$mod_list[] = array(
				'key'   => $key,
				'value' => $value,
			);
		}
	}

	/**
	 * Gets a list of theme mods.
	 *
	 * ## OPTIONS
	 *
	 * [--field=<field>]
	 * : Returns the value of a single field.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Gets a list of theme mods.
	 *     $ wp theme mod list
	 *     +------------------+---------+
	 *     | key              | value   |
	 *     +------------------+---------+
	 *     | background_color | dd3333  |
	 *     | link_color       | #dd9933 |
	 *     | main_text_color  | #8224e3 |
	 *     +------------------+---------+
	 *
	 * @subcommand list
	 *
	 * @param string[]                              $args       Positional arguments. Unused.
	 * @param array{field?: string, format: string} $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {

		$assoc_args['all'] = true;

		$this->get( $args, $assoc_args );
	}

	/**
	 * Removes one or more theme mods.
	 *
	 * ## OPTIONS
	 *
	 * [<mod>...]
	 * : One or more mods to remove.
	 *
	 * [--all]
	 * : Remove all theme mods.
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove all theme mods.
	 *     $ wp theme mod remove --all
	 *     Success: Theme mods removed.
	 *
	 *     # Remove single theme mod.
	 *     $ wp theme mod remove background_color
	 *     Success: 1 mod removed.
	 *
	 *     # Remove multiple theme mods.
	 *     $ wp theme mod remove background_color header_textcolor
	 *     Success: 2 mods removed.
	 *
	 * @param string[]          $args       Positional arguments.
	 * @param array{all?: bool} $assoc_args Associative arguments.
	 */
	public function remove( $args, $assoc_args ) {

		if ( ! Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( 'You must specify at least one mod or use --all.' );
		}

		if ( Utils\get_flag_value( $assoc_args, 'all' ) ) {
			remove_theme_mods();
			WP_CLI::success( 'Theme mods removed.' );
			return;
		}

		foreach ( $args as $mod ) {
			remove_theme_mod( $mod );
		}

		$count           = count( $args );
		$success_message = ( 1 === $count ) ? '%d mod removed.' : '%d mods removed.';
		WP_CLI::success( sprintf( $success_message, $count ) );
	}

	/**
	 * Sets the value of a theme mod.
	 *
	 * ## OPTIONS
	 *
	 * <mod>
	 * : The name of the theme mod to set or update.
	 *
	 * <value>
	 * : The new value.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set theme mod
	 *     $ wp theme mod set background_color 000000
	 *     Success: Theme mod background_color set to 000000.
	 *
	 * @param array{0: string, 1: string} $args Positional arguments.
	 * @param array $assoc_args Associative arguments. Unused.
	 */
	public function set( $args, $assoc_args ) {
		list( $mod, $value ) = $args;

		set_theme_mod( $mod, $value );

		if ( get_theme_mod( $mod ) === $value ) {
			WP_CLI::success( "Theme mod {$mod} set to {$value}." );
		} else {
			WP_CLI::success( "Could not update theme mod {$mod}." );
		}
	}
}
