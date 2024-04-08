<?php

namespace WP_CLI;

use WP_CLI;

trait ParsePluginReadme {
	/**
	 * @var array
	 */
	private $plugin_headers = [];

	/**
	 * These are the valid header mappings for the header.
	 *
	 * @var array
	 */
	public $valid_plugin_headers = array(
		'tested'       => 'tested_up_to',
		'tested up to' => 'tested_up_to',
	);

	/**
	 * Parse readme from the plugin name.
	 *
	 * @param string $name The plugin name e.g. "classic-editor".
	 */
	private function parse_readme( $name = '' ) {
		$plugin_readme = WP_PLUGIN_DIR . '/' . $name . '/readme.txt';

		if ( ! file_exists( $plugin_readme ) ) {
			// Reset the plugin headers if the readme.txt file does not exists
			// It ensures that it does not carry out stale data from a previous parsed plugin.
			$this->plugin_headers = [];

			return;
		}

		$context  = stream_context_create(
			array(
				'http' => array(
					'user_agent' => 'WP-CLI Plugin Readme Parser',
				),
			)
		);
		$contents = file_get_contents( $plugin_readme, false, $context );

		// At the moment, the parser only concern about parsing the plugin headers, which
		// appear after the plugin name header, and before the description header.
		if ( preg_match( '/=== .*? ===\s*(.*?)(?:== Description ==|$)/s', $contents, $matches ) ) {
			$contents = trim( $matches[1] );
		}

		if ( preg_match( '!!u', $contents ) ) {
			$contents = preg_split( '!\R!u', $contents );
		} else {
			$contents = preg_split( '!\R!', $contents ); // regex failed due to invalid UTF8 in $contents, see #2298
		}
		$contents = array_map( array( $this, 'strip_newlines' ), $contents );

		// Strip UTF8 BOM if present.
		if ( 0 === strpos( $contents[0], "\xEF\xBB\xBF" ) ) {
			$contents[0] = substr( $contents[0], 3 );
		}

		// Convert UTF-16 files.
		if ( 0 === strpos( $contents[0], "\xFF\xFE" ) ) {
			foreach ( $contents as $i => $line ) {
				$contents[ $i ] = mb_convert_encoding( $line, 'UTF-8', 'UTF-16' );
			}
		}

		$line                = $this->get_first_nonwhitespace( $contents );
		$last_line_was_blank = false;

		do {
			$value  = null;
			$header = $this->parse_possible_header( $line );
			$line   = array_shift( $contents );

			// If it doesn't look like a header value, maybe break to the next section.
			if ( ! $header ) {
				if ( empty( $line ) ) {
					// Some plugins have line-breaks within the headers...
					$last_line_was_blank = true;
					continue;
				} else {
					// We've hit a line that is not blank, but also doesn't look like a header, assume the Short Description and end Header parsing.
					break;
				}
			}

			list( $key, $value ) = $header;

			if ( isset( $this->valid_plugin_headers[ $key ] ) ) {
				$header_key = $this->valid_plugin_headers[ $key ];

				if ( 'tested_up_to' === $header_key && $value ) {
					$this->plugin_headers['tested_up_to'] = $this->sanitize_tested_version( $value );
				}

				$this->plugin_headers[ $this->valid_plugin_headers[ $key ] ] = $value;
			} elseif ( $last_line_was_blank ) {
				// If we skipped over a blank line, and then ended up with an unexpected header, assume we parsed too far and ended up in the Short Description.
				// This final line will be added back into the stack after the loop for further parsing.
				break;
			}
			$last_line_was_blank = false;
		} while ( null !== $line );
	}

	/**
	 * Gets the plugin header information from the plugin's readme.txt file.
	 *
	 * @param string $name The plugin name e.g. "classic-editor".
	 * @return array
	 */
	protected function get_plugin_headers( $name ) {
		$this->parse_readme( $name );

		return $this->plugin_headers;
	}

	/**
	 * Parse a line to see if it's a header.
	 *
	 * @param string $line       The line from the readme to parse.
	 * @param bool   $only_valid Whether to only return a valid known header.
	 * @return false|array
	 */
	private function parse_possible_header( $line, $only_valid = false ) {
		if ( ! str_contains( $line, ':' ) || str_starts_with( $line, '#' ) || str_starts_with( $line, '=' ) ) {
			return false;
		}

		list( $key, $value ) = explode( ':', $line, 2 );
		$key                 = strtolower( trim( $key, " \t*-\r\n" ) );
		$value               = trim( $value, " \t*-\r\n" );

		if ( $only_valid && ! isset( $this->valid_headers[ $key ] ) ) {
			return false;
		}

		return array( $key, $value );
	}

	/**
	 * Sanitizes the Tested header to ensure that it's a valid version header.
	 *
	 * @param string $version
	 * @return string The sanitized $version
	 */
	private function sanitize_tested_version( $version ) {
		$version = trim( $version );

		if ( $version ) {

			// Handle the edge-case of 'WordPress 5.0' and 'WP 5.0' for historical purposes.
			$strip_phrases = [
				'WordPress',
				'WP',
			];
			$version       = trim( str_ireplace( $strip_phrases, '', $version ) );

			// Strip off any -alpha, -RC, -beta suffixes, as these complicate comparisons and are rarely used.
			list( $version, ) = explode( '-', $version );
		}

		return $version;
	}

	/**
	 * @param string $line
	 * @return string
	 */
	private function strip_newlines( $line ) {
		return rtrim( $line, "\r\n" );
	}

	/**
	 * @param array $contents
	 * @return string
	 */
	private function get_first_nonwhitespace( &$contents ) {
		$line = array_shift( $contents );

		while ( null !== $line ) {
			$trimmed = trim( $line );

			if ( ! empty( $trimmed ) ) {
				break;
			}

			$line = array_shift( $contents );
		}

		return $line ? $line : '';
	}
}
