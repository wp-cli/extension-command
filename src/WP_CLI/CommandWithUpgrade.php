<?php

namespace WP_CLI;

use Composer\Semver\VersionParser;
use Composer\Semver\Comparator;
use Exception;
use WP_CLI;
use WP_CLI\Fetchers;
use WP_CLI\Loggers;
use WP_CLI\Utils;
use WP_Error;

/**
 * @phpstan-import-type ThemeInformation from \Theme_Command
 * @phpstan-import-type PluginInformation from \Plugin_Command
 *
 * @template T
 */
abstract class CommandWithUpgrade extends \WP_CLI_Command {

	protected $fetcher;
	protected $item_type;
	protected $obj_fields;

	protected $upgrade_refresh;
	protected $upgrade_transient;

	protected $chained_command = false;

	/**
	 * The GitHub Releases public api endpoint.
	 *
	 * @var string
	 */
	private $github_releases_api_endpoint = 'https://api.github.com/repos/%s/releases';

	/**
	 * The GitHub latest release url format.
	 *
	 * @var string
	 */
	private $github_latest_release_url = '/^https:\/\/github\.com\/(.*)\/releases\/latest\/?$/';

	// Invalid version message.
	const INVALID_VERSION_MESSAGE = 'version higher than expected';

	public function __construct() {

		// Do not automatically check translations updates after updating plugins/themes.
		add_action(
			'upgrader_process_complete',
			function () {
				remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );
			},
			1
		);

		add_filter(
			'http_request_timeout',
			function () {
				return 1 * MINUTE_IN_SECONDS;
			},
			999
		);

		$this->fetcher = new Fetchers\Plugin();
	}

	/**
	 * @return class-string<\WP_Upgrader>
	 */
	abstract protected function get_upgrader_class( $force );

	abstract protected function get_item_list();

	/**
	 * @param array $items List of update candidates
	 * @param array $args  List of item names
	 * @return array List of update candidates
	 */
	abstract protected function filter_item_list( $items, $args );

	abstract protected function get_all_items();

	/**
	 * Get the status for a given extension.
	 *
	 * @param T $file Extension to get the status for.
	 *
	 * @return string Status of the extension.
	 */
	abstract protected function get_status( $file );

	abstract protected function status_single( $args );

	abstract protected function install_from_repo( $slug, $assoc_args );

	/**
	 * Activates an extension.
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Associative arguments.
	 */
	abstract public function activate( $args, $assoc_args = [] );

	public function status( $args ) {
		// Force WordPress to check for updates.
		call_user_func( $this->upgrade_refresh );

		if ( empty( $args ) ) {
			$this->status_all();
		} else {
			$this->status_single( $args );
		}
	}

	private function status_all() {
		$items = $this->get_all_items();

		$n = count( $items );

		WP_CLI::log(
			sprintf( '%d installed %s:', $n, Utils\pluralize( $this->item_type, absint( $n ) ) )
		);

		$padding = $this->get_padding( $items );

		foreach ( $items as $file => $details ) {
			if ( 'available' === $details['update'] ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			$line .= $this->format_status( $details['status'], 'short' );
			$line .= ' ' . str_pad( $details['name'], $padding ) . '%n';
			if ( ! empty( $details['version'] ) ) {
				$line .= ' ' . $details['version'];
			}

			WP_CLI::line( WP_CLI::colorize( $line ) );
		}

		WP_CLI::line();

		$this->show_legend( $items );
	}

	private function get_padding( $items ) {
		$max_len = 0;

		foreach ( $items as $details ) {
			$len = strlen( $details['name'] );

			if ( $len > $max_len ) {
				$max_len = $len;
			}
		}

		return $max_len;
	}

	private function show_legend( $items ) {
		$statuses = array_unique( wp_list_pluck( $items, 'status' ) );

		$legend_line = array();

		foreach ( $statuses as $status ) {
			$legend_line[] = sprintf(
				'%s%s = %s%%n',
				$this->get_color( $status ),
				$this->map['short'][ $status ],
				$this->map['long'][ $status ]
			);
		}
		if ( in_array( 'available', wp_list_pluck( $items, 'update' ), true ) ) {
			$legend_line[] = '%yU = Update Available%n';
		}

		WP_CLI::line( 'Legend: ' . WP_CLI::colorize( implode( ', ', $legend_line ) ) );
	}

	public function install( $args, $assoc_args ) {
		$successes = 0;
		$errors    = 0;
		foreach ( $args as $slug ) {

			if ( empty( $slug ) ) {
				WP_CLI::warning( 'Ignoring ambiguous empty slug value.' );
				continue;
			}

			$result = false;

			$is_remote = false !== strpos( $slug, '://' );

			if ( $is_remote ) {
				$github_repo = $this->get_github_repo_from_releases_url( $slug );

				if ( $github_repo ) {
					$version = $this->get_the_latest_github_version( $github_repo );

					if ( is_wp_error( $version ) ) {
						WP_CLI::error( $version->get_error_message() );
					}

					/**
					 * Sets the $slug that will trigger the installation based on a zip file.
					 */
					$slug = $version['url'];

					WP_CLI::log( 'Latest release resolved to ' . $version['name'] );
				}

				// Check if this is a WordPress.org plugin/theme directory URL.
				// If so, extract the slug and treat it as a repository installation.
				// Pattern matches: https://wordpress.org/plugins/plugin-slug/ or https://wordpress.org/themes/theme-slug/
				// Capture group 1: type (plugins|themes)
				// Capture group 2: slug (the plugin or theme slug name)
				if ( preg_match( '~^https?://wordpress\.org/(plugins|themes)/([^/]+)/?$~', $slug, $matches ) ) {
					$slug      = $matches[2]; // Extract the slug.
					$is_remote = false;
					WP_CLI::log( sprintf( 'Detected WordPress.org %s directory URL, using slug: %s', $matches[1], $slug ) );
				}

				// Check if it's a GitHub Gist page URL and convert to raw URL
				$gist_id = $this->get_gist_id_from_url( $slug );
				if ( $gist_id && 'plugin' === $this->item_type ) {
					$raw_url = $this->get_raw_url_from_gist( $gist_id );

					if ( is_wp_error( $raw_url ) ) {
						WP_CLI::error( $raw_url->get_error_message() );
					}

					WP_CLI::log( 'Gist resolved to raw file URL.' );
					$slug = $raw_url;
				}
			}

			// Check if a URL to a remote or local PHP file has been specified (plugins only).
			if ( $this->is_php_file_url( $slug, $is_remote ) ) {
				// Install from remote PHP file.
				$result = $this->install_from_php_file( $slug, $assoc_args );

				if ( is_string( $result ) ) {
					// Update slug to the installed filename for activation.
					$slug   = $result;
					$result = true;
					++$successes;
				} else {
					// $result is WP_Error here
					WP_CLI::warning( $result->get_error_message() );
					if ( 'already_installed' !== $result->get_error_code() ) {
						++$errors;
					}
				}
			} elseif ( $is_remote || ( pathinfo( $slug, PATHINFO_EXTENSION ) === 'zip' && is_file( $slug ) ) ) {
				// Install from local or remote zip file.
				$file_upgrader = $this->get_upgrader( $assoc_args );

				$filter = false;
				// If a GitHub URL, do some guessing as to the correct plugin/theme directory.
				if ( $is_remote && 'github.com' === Utils\parse_url( $slug, PHP_URL_HOST )
						// Don't attempt to rename ZIPs uploaded to the releases page or coming from a raw source.
						&& ! preg_match( '#github\.com/[^/]+/[^/]+/(?:releases/download|raw)/#', $slug ) ) {

					$filter = function ( $source ) use ( $slug ) {
						/**
						 * @var string $path
						 */
						$path     = Utils\parse_url( $slug, PHP_URL_PATH );
						$slug_dir = Utils\basename( $path, '.zip' );

						// Don't use the zip name if archive attached to release, as name likely to contain version tag/branch.
						if ( preg_match( '#github\.com/[^/]+/([^/]+)/archive/#', $slug, $matches ) ) {
							// Note this will be wrong if the project name isn't the same as the plugin/theme slug name.
							$slug_dir = $matches[1];
						}

						$source_dir = Utils\basename( $source ); // `$source` is trailing-slashed path to the unzipped archive directory, so basename returns the unslashed directory.
						if ( $source_dir === $slug_dir ) {
							return $source;
						}
						$new_path = substr_replace( $source, $slug_dir, (int) strrpos( $source, $source_dir ), strlen( $source_dir ) );

						if ( $GLOBALS['wp_filesystem']->move( $source, $new_path ) ) {
							WP_CLI::log( sprintf( "Renamed Github-based project from '%s' to '%s'.", $source_dir, $slug_dir ) );
							return $new_path;
						}

						return new WP_Error( 'wpcli_install_github', "Couldn't move Github-based project to appropriate directory." );
					};
					add_filter( 'upgrader_source_selection', $filter, 10 );
				}

				// Add item to cache allowlist if it matches certain URL patterns.
				self::maybe_cache( $slug, $this->item_type );

				if ( $file_upgrader->install( $slug ) ) {
					$slug   = $file_upgrader->result['destination_name'];
					$result = true;
					if ( $filter ) {
						remove_filter( 'upgrader_source_selection', $filter, 10 );
					}
					++$successes;
				} else {
					++$errors;
				}
			} else {
				// Assume a plugin/theme slug from the WordPress.org repository has been specified.
				$result = $this->install_from_repo( $slug, $assoc_args );

				if ( is_null( $result ) ) {
					++$errors;
				} elseif ( is_wp_error( $result ) ) {
					$key = $result->get_error_code();
					if ( in_array( $key, [ 'plugins_api_failed', 'themes_api_failed' ], true )
						&& ! empty( $result->error_data[ $key ] ) && in_array( $result->error_data[ $key ], [ 'N;', 'b:0;' ], true ) ) {
						WP_CLI::warning( "Couldn't find '$slug' in the WordPress.org {$this->item_type} directory." );
						++$errors;
					} else {
						WP_CLI::warning( "$slug: " . $result->get_error_message() );
						if ( 'already_installed' !== $key ) {
							++$errors;
						}
					}
				} else {
					++$successes;
				}
			}

			// Check extension is available or not.
			$extension = $this->fetcher->get_many( array( $slug ) );

			// If installation goes well $result will be true.
			$allow_activation = $result;

			// Allow installation for installed extension.
			if ( is_wp_error( $result ) && 'already_installed' === $result->get_error_code() ) {
				$allow_activation = true;
			}

			if ( true === $allow_activation && count( $extension ) > 0 ) {
				$this->chained_command = true;
				if ( Utils\get_flag_value( $assoc_args, 'activate-network' ) ) {
					WP_CLI::log( "Network-activating '$slug'..." );
					$this->activate( array( $slug ), array( 'network' => true ) );
				}

				if ( Utils\get_flag_value( $assoc_args, 'activate' ) ) {
					WP_CLI::log( "Activating '$slug'..." );
					$this->activate( array( $slug ) );
				}
				$this->chained_command = false;
			}
		}
		Utils\report_batch_operation_results( $this->item_type, 'install', count( $args ), $successes, $errors );
	}

	/**
	 * Install a plugin from a single PHP file URL.
	 *
	 * @param string $url        URL to the PHP file.
	 * @param array  $assoc_args Associative arguments.
	 * @return string|WP_Error The installed filename on success, WP_Error on failure.
	 */
	protected function install_from_php_file( $url, $assoc_args ) {
		// Ensure required WordPress files are loaded.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Extract and validate filename before downloading.
		$url_path = (string) Utils\parse_url( $url, PHP_URL_PATH );
		$filename = Utils\basename( $url_path );

		// Validate the filename doesn't contain directory separators or relative path components.
		// Note: Utils\basename() already strips directory components (including ".."), so this check
		// is primarily a defense-in-depth safeguard in case its behavior changes or is bypassed.
		if ( strpos( $filename, '/' ) !== false || strpos( $filename, '\\' ) !== false || strpos( $filename, '..' ) !== false ) {
			return new WP_Error( 'invalid_filename', 'The filename contains invalid path components.' );
		}

		// Determine the destination filename and validate extension.
		$dest_filename = sanitize_file_name( $filename );

		// Ensure the sanitized filename still has a .php extension (case-insensitive).
		if ( strtolower( pathinfo( $dest_filename, PATHINFO_EXTENSION ) ) !== 'php' ) {
			return new WP_Error( 'invalid_filename', 'The sanitized filename does not have a .php extension.' );
		}

		// Construct destination path.
		$dest_path = trailingslashit( WP_PLUGIN_DIR ) . $dest_filename;

		// Check if plugin is already installed before downloading.
		if ( file_exists( $dest_path ) && ! Utils\get_flag_value( $assoc_args, 'force' ) ) {
			return new WP_Error( 'already_installed', 'Plugin already installed.' );
		}

		// Ensure plugin directory exists.
		if ( ! is_dir( WP_PLUGIN_DIR ) ) {
			wp_mkdir_p( WP_PLUGIN_DIR );

			// Verify that the plugin directory was successfully created.
			if ( ! is_dir( WP_PLUGIN_DIR ) ) {
				return new WP_Error( 'invalid_path', 'Unable to create plugin directory.' );
			}
		}

		// Validate the destination stays within the plugin directory (prevent directory traversal).
		// Since single-file plugins are installed directly in WP_PLUGIN_DIR, we just need to ensure
		// the destination resolves to a file within WP_PLUGIN_DIR.
		$real_plugin_dir = realpath( WP_PLUGIN_DIR );
		if ( false === $real_plugin_dir ) {
			return new WP_Error( 'invalid_path', 'Cannot validate plugin directory path.' );
		}

		// Verify the constructed path is within the plugin directory.
		if ( realpath( dirname( $dest_path ) ) !== $real_plugin_dir ) {
			return new WP_Error( 'invalid_path', 'The destination path is outside the plugin directory.' );
		}

		// Display info message before downloading.
		WP_CLI::log( sprintf( 'Downloading plugin file from %s...', esc_url( $url ) ) );

		// Download the file to a temporary location.
		$temp_file = download_url( $url );

		if ( is_wp_error( $temp_file ) ) {
			return new WP_Error( 'download_failed', sprintf( 'Could not download PHP file from %s: %s', esc_url( $url ), $temp_file->get_error_message() ) );
		}

		// Verify the downloaded file is a valid PHP file with plugin headers.
		$plugin_data = get_plugin_data( $temp_file, false, false );

		// Verify this is actually a plugin file with at least a plugin name.
		if ( empty( $plugin_data['Name'] ) ) {
			unlink( $temp_file );
			return new WP_Error( 'invalid_plugin', 'The downloaded file does not appear to be a valid WordPress plugin.' );
		}

		$plugin_name = $plugin_data['Name'];

		// Display plugin info.
		$version = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
		WP_CLI::log( sprintf( 'Installing %s%s', $plugin_name, $version ? " ($version)" : '' ) );

		// Move the file to the plugins directory.
		$result = copy( $temp_file, $dest_path );
		unlink( $temp_file );

		if ( ! $result ) {
			return new WP_Error( 'copy_failed', 'Could not copy plugin file to destination.' );
		}

		WP_CLI::log( 'Plugin installed successfully.' );

		// Return the filename for activation purposes.
		return $dest_filename;
	}

	/**
	 * Check if a URL points to a PHP file for plugin installation.
	 *
	 * @param string $slug      The slug/URL to check.
	 * @param bool   $is_remote Whether the slug is a remote URL.
	 * @return bool True if it's a PHP file URL for plugin installation.
	 */
	protected function is_php_file_url( $slug, $is_remote ) {
		if ( 'plugin' !== $this->item_type || ! $is_remote ) {
			return false;
		}

		$url_path = Utils\parse_url( $slug, PHP_URL_PATH );
		return is_string( $url_path ) && strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) ) === 'php';
	}

	/**
	 * Prepare an API response for downloading a particular version of an item.
	 *
	 * @param object $response Wordpress.org API response.
	 * @param string $version  The desired version of the package.
	 *
	 * @phpstan-param PluginInformation|ThemeInformation $response
	 */
	protected static function alter_api_response( $response, $version ) {
		if ( $response->version === $version ) {
			return;
		}

		// WordPress.org forces https, but still sometimes returns http
		// See https://twitter.com/nacin/status/512362694205140992
		$response->download_link = str_replace( 'http://', 'https://', $response->download_link );

		list( $link ) = explode( $response->slug, $response->download_link );

		if ( false !== strpos( $response->download_link, '/theme/' ) ) {
			$download_type = 'theme';
		} elseif ( false !== strpos( $response->download_link, '/plugin/' ) ) {
			$download_type = 'plugin';
		} else {
			$download_type = 'plugin/theme';
		}

		if ( 'dev' === $version ) {
			$response->download_link = $link . $response->slug . '.zip';
			$response->version       = 'Development Version';
		} else {
			$response->download_link = $link . $response->slug . '.' . $version . '.zip';
			$response->version       = $version;

			// Check if the requested version exists.
			$response      = wp_remote_head( $response->download_link );
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== (int) $response_code ) {
				if ( is_wp_error( $response ) ) {
					$error_msg = $response->get_error_message();
				} else {
					$error_msg = sprintf( 'HTTP code %d', $response_code );
				}
				WP_CLI::error(
					sprintf(
						"Can't find the requested %s's version %s in the WordPress.org %s repository (%s).",
						$download_type,
						$version,
						$download_type,
						$error_msg
					)
				);
			}
		}
	}

	protected function get_upgrader( $assoc_args ) {
		$force          = Utils\get_flag_value( $assoc_args, 'force', false );
		$insecure       = Utils\get_flag_value( $assoc_args, 'insecure', false );
		$upgrader_class = $this->get_upgrader_class( $force );
		return Utils\get_upgrader( $upgrader_class, $insecure );
	}

	protected function update_many( $args, $assoc_args ) {
		call_user_func( $this->upgrade_refresh );

		if ( ! empty( $assoc_args['format'] ) && in_array( $assoc_args['format'], [ 'json', 'csv' ], true ) ) {
			$logger = new Loggers\Quiet( WP_CLI::get_runner()->in_color() );
			WP_CLI::set_logger( $logger );
		}

		if ( ! Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( "Please specify one or more {$this->item_type}s, or use --all." );
		}

		if ( Utils\get_flag_value( $assoc_args, 'minor' ) && Utils\get_flag_value( $assoc_args, 'patch' ) ) {
			WP_CLI::error( '--minor and --patch cannot be used together.' );
		}

		$items = $this->get_item_list();

		$errors  = 0;
		$skipped = 0;
		if ( ! Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$items  = $this->filter_item_list( $items, $args );
			$errors = count( $args ) - count( $items );
		}

		$items_to_update = array_filter(
			$items,
			function ( $item ) {
				return isset( $item['update'] ) && 'none' !== $item['update'];
			}
		);

		$minor = Utils\get_flag_value( $assoc_args, 'minor', false );
		$patch = Utils\get_flag_value( $assoc_args, 'patch', false );

		if (
			in_array( $this->item_type, [ 'plugin', 'theme' ], true ) &&
			( $minor || $patch )
		) {
			$type     = $minor ? 'minor' : 'patch';
			$insecure = Utils\get_flag_value( $assoc_args, 'insecure', false );

			$items_to_update = self::get_minor_or_patch_updates( $items_to_update, $type, $insecure, true, $this->item_type );
		}

		/**
		 * @var string|null $exclude
		 */
		$exclude = Utils\get_flag_value( $assoc_args, 'exclude' );
		if ( isset( $exclude ) ) {
			$exclude_items = explode( ',', trim( $assoc_args['exclude'], ',' ) );
			unset( $assoc_args['exclude'] );
			foreach ( $exclude_items as $item ) {
				if ( 'plugin' === $this->item_type ) {
					$plugin = $this->fetcher->get( $item );
					if ( ! $plugin ) {
						continue;
					}
					unset( $items_to_update[ $plugin->file ] );
				} elseif ( 'theme' === $this->item_type ) {
					$theme = wp_get_theme( $item );
					if ( $theme->exists() ) {
						unset( $items_to_update[ $theme->get_stylesheet() ] );
					}
				}
			}
		}

		// Check for items to update and remove extensions that have version higher than expected.
		foreach ( $items_to_update as $item_key => $item_info ) {
			if ( static::INVALID_VERSION_MESSAGE === $item_info['update'] ) {
				WP_CLI::warning( "{$item_info['name']}: " . static::INVALID_VERSION_MESSAGE . '.' );
				++$skipped;
				unset( $items_to_update[ $item_key ] );
			}
			if ( 'unavailable' === $item_info['update'] ) {
				WP_CLI::warning( "{$item_info['name']}: {$item_info['update_unavailable_reason']}" );
				++$skipped;
				unset( $items_to_update[ $item_key ] );
			}
		}

		if ( Utils\get_flag_value( $assoc_args, 'dry-run' ) ) {
			if ( empty( $items_to_update ) ) {
				WP_CLI::log( "No {$this->item_type} updates available." );

				if ( null !== $exclude ) {
					WP_CLI::log( "Skipped updates for: $exclude" );
				}

				return;
			}

			if ( ! empty( $assoc_args['format'] ) && in_array( $assoc_args['format'], [ 'json', 'csv' ], true ) ) {
				Utils\format_items( $assoc_args['format'], $items_to_update, [ 'name', 'status', 'version', 'update_version' ] );
			} elseif ( ! empty( $assoc_args['format'] ) && 'summary' === $assoc_args['format'] ) {
				WP_CLI::log( "Available {$this->item_type} updates:" );
				foreach ( $items_to_update as $item_to_update => $info ) {
					WP_CLI::log( "{$info['title']} update from version {$info['version']} to version {$info['update_version']}" );
				}
			} else {
				WP_CLI::log( "Available {$this->item_type} updates:" );
				Utils\format_items( 'table', $items_to_update, [ 'name', 'status', 'version', 'update_version' ] );
			}

			if ( null !== $exclude ) {
				WP_CLI::log( "Skipped updates for: $exclude" );
			}

			return;
		}

		$result = array();

		// Only attempt to update if there is something to update.
		if ( ! empty( $items_to_update ) ) {
			$cache_manager = WP_CLI::get_http_cache_manager();
			foreach ( $items_to_update as $item ) {
				$cache_manager->whitelist_package( $item['update_package'], $this->item_type, $item['name'], $item['update_version'] );
			}
			$upgrader = $this->get_upgrader( $assoc_args );
			// Ensure the upgrader uses the download offer present in each item.
			$transient_filter = function ( $transient ) use ( $items_to_update ) {
				foreach ( $items_to_update as $name => $item_data ) {
					if ( isset( $transient->response[ $name ] ) ) {
						if ( is_object( $transient->response[ $name ] ) ) {
							/**
							 * @var object{response: array<string, ThemeInformation|PluginInformation>} $transient
							 */
							$transient->response[ $name ]->new_version = $item_data['update_version'];
							$transient->response[ $name ]->package     = $item_data['update_package'];
						} else {
							$transient->response[ $name ]['new_version'] = $item_data['update_version'];
							$transient->response[ $name ]['package']     = $item_data['update_package'];
						}
					}
				}
				return $transient;
			};
			add_filter( 'site_transient_' . $this->upgrade_transient, $transient_filter, 999 );
			$result = $upgrader->bulk_upgrade( wp_list_pluck( $items_to_update, 'update_id' ) );
			remove_filter( 'site_transient_' . $this->upgrade_transient, $transient_filter, 999 );
		}

		/**
		 * @var array $items_to_update
		 */

		// Let the user know the results.
		$num_to_update = count( $items_to_update );
		$num_updated   = count(
			array_filter(
				$result,
				static function ( $result ) {
					return $result && ! is_wp_error( $result );
				}
			)
		);

		if ( $num_to_update > 0 ) {
			if ( ! empty( $assoc_args['format'] ) && 'summary' === $assoc_args['format'] ) {
				foreach ( $items_to_update as $item_to_update => $info ) {
					$message = null !== $result[ $info['update_id'] ] ? 'updated successfully' : 'did not update';
					WP_CLI::log( "{$info['title']} {$message} from version {$info['version']} to version {$info['update_version']}" );
				}
			} else {
				$status = array();
				foreach ( $items_to_update as $item_to_update => $info ) {
					$status[ $item_to_update ] = [
						'name'        => $info['name'],
						'old_version' => $info['version'],
						'new_version' => $info['update_version'],
						'status'      => ( null !== $result[ $info['update_id'] ] && ! is_wp_error( $result[ $info['update_id'] ] ) ) ? 'Updated' : 'Error',
					];
					if ( null === $result[ $info['update_id'] ] || is_wp_error( $result[ $info['update_id'] ] ) ) {
						++$errors;
					}
				}

				$format = 'table';
				if ( ! empty( $assoc_args['format'] ) && in_array( $assoc_args['format'], [ 'json', 'csv' ], true ) ) {
					$format = $assoc_args['format'];
				}

				Utils\format_items( $format, $status, [ 'name', 'old_version', 'new_version', 'status' ] );
			}
		}

		$total_updated = Utils\get_flag_value( $assoc_args, 'all' ) ? $num_to_update : count( $args );
		if ( 0 === $num_updated && $skipped ) {
			$errors  = $skipped;
			$skipped = null;
		}
		Utils\report_batch_operation_results( $this->item_type, 'update', $total_updated, $num_updated, $errors, $skipped );
		if ( null !== $exclude ) {
			WP_CLI::log( "Skipped updates for: $exclude" );
		}
	}

	// phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- Whitelisting to provide backward compatibility to classes possibly extending this class.
	protected function _list( $_, $assoc_args ) {

		// Force WordPress to check for updates if `--skip-update-check` is not passed.
		if ( false === Utils\get_flag_value( $assoc_args, 'skip-update-check', false ) ) {
			delete_site_transient( $this->upgrade_transient );
			call_user_func( $this->upgrade_refresh );
		}

		$all_items = $this->get_all_items();

		if ( false !== Utils\get_flag_value( $assoc_args, 'recently-active', false ) ) {
			$all_items = array_filter(
				$all_items,
				function ( $value ) {
					return isset( $value['recently_active'] ) && true === $value['recently_active'];
				}
			);
		}

		if ( ! is_array( $all_items ) ) {
			WP_CLI::error( "No {$this->item_type}s found." );
		}

		foreach ( $all_items as $key => &$item ) {

			if ( empty( $item['version'] ) ) {
				$item['version'] = '';
			}

			if ( empty( $item['update_version'] ) ) {
				$item['update_version'] = '';
			}

			foreach ( $item as $field => &$value ) {
				if ( 'update' === $field ) {
					// If an update is unavailable, make sure to also show these fields which will explain why
					if ( 'unavailable' === $value ) {
						if ( ! in_array( 'requires', $this->obj_fields, true ) ) {
							array_push( $this->obj_fields, 'requires' );
						}
						if ( ! in_array( 'requires_php', $this->obj_fields, true ) ) {
							array_push( $this->obj_fields, 'requires_php' );
						}
					}
				} elseif ( 'auto_update' === $field ) {
					if ( true === $value ) {
						$value = 'on';
					} elseif ( false === $value ) {
						$value = 'off';
					}
				}
			}

			foreach ( $this->obj_fields as $field ) {
				if ( ! array_key_exists( $field, $assoc_args ) ) {
					continue;
				}

				// This can be either a value to filter by or a comma-separated list of values.
				// Also, it is not forbidden for a value to contain a comma (in which case we can filter only by one).
				$field_filter = $assoc_args[ $field ];
				if (
					$item[ $field ] !== $field_filter
					&& ! in_array( $item[ $field ], array_map( 'trim', explode( ',', $field_filter ) ), true )
				) {
					unset( $all_items[ $key ] );
				}
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $all_items );
	}

	/**
	 * Check whether an item has an update available or not.
	 *
	 * @param string $slug The plugin/theme slug
	 *
	 * @return bool
	 */
	protected function has_update( $slug ) {
		/**
		 * @var object{checked: array<string, string>, response: array<string, string>, no_update: array<string, object{new_version: string, package: string, requires: string}&\stdClass>} $update_list
		 */
		$update_list = get_site_transient( $this->upgrade_transient );

		return isset( $update_list->response[ $slug ] );
	}

	/**
	 * Get the available update info
	 *
	 * @return object{checked: array<string, string>, response: array<string, array<string, string|null>>, no_update: array<string, object{new_version: string, package: string, requires: string}&\stdClass>} $update_list
	 */
	protected function get_update_info() {
		/**
		 * @var object{checked: array<string, string>, response: array<string, array<string, string|null>>, no_update: array<string, object{new_version: string, package: string, requires: string}&\stdClass>} $update_list
		 */
		$update_list = get_site_transient( $this->upgrade_transient );

		return $update_list;
	}

	private $map = [
		'short' => [
			'inactive'       => 'I',
			'active'         => 'A',
			'active-network' => 'N',
			'must-use'       => 'M',
			'parent'         => 'P',
			'dropin'         => 'D',
		],
		'long'  => [
			'inactive'       => 'Inactive',
			'active'         => 'Active',
			'active-network' => 'Network Active',
			'must-use'       => 'Must Use',
			'parent'         => 'Parent',
			'dropin'         => 'Drop-In',
		],
	];

	protected function format_status( $status, $format ) {
		return $this->get_color( $status ) . $this->map[ $format ][ $status ];
	}

	private function get_color( $status ) {
		static $colors = [
			'inactive'       => '',
			'active'         => '%g',
			'active-network' => '%g',
			'must-use'       => '%c',
			'parent'         => '%p',
			'dropin'         => '%B',
		];

		return $colors[ $status ];
	}

	/**
	 * Get the minor or patch version for plugins and themes with available updates
	 *
	 * @param array  $items    Items with updates.
	 * @param string $type     Either 'minor' or 'patch'.
	 * @param bool   $insecure Whether to retry without certificate validation on TLS handshake failure.
	 * @param bool   $require_stable Whether to require stable version when comparing versions.
	 * @param string $item_type Item type, either 'plugin' or 'theme'.
	 * @return array
	 */
	private function get_minor_or_patch_updates( $items, $type, $insecure, $require_stable, $item_type ) {
		$wp_org_api = new WpOrgApi( [ 'insecure' => $insecure ] );
		foreach ( $items as $i => $item ) {
			try {
				/**
				 * @var callable $callback
				 */
				$callback = [ $wp_org_api, "get_{$item_type}_info" ];
				$data     = call_user_func(
					$callback,
					$item['name'],
					// The default.
					'en_US',
					// We are only interested in the versions field.
					[ 'versions' => true ]
				);
			} catch ( Exception $exception ) {
				unset( $items[ $i ] );
				continue;
			}
			// No minor or patch versions to access.
			if ( empty( $data['versions'] ) ) {
				unset( $items[ $i ] );
				continue;
			}
			$update_version = false;
			$update_package = false;
			foreach ( $data['versions'] as $version => $download_link ) {
				try {
					$update_type = Utils\get_named_sem_ver( $version, $item['version'] );
				} catch ( \Exception $e ) {
					continue;
				}
				// Compared version must be older.
				if ( ! $update_type ) {
					continue;
				}
				// Only permit 'patch' for 'patch'.
				if ( 'patch' === $type && 'patch' !== $update_type ) {
					continue;
				}
				// Permit 'minor' or 'patch' for 'minor' phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- False positive.
				if ( 'minor' === $type && ! in_array( $update_type, array( 'minor', 'patch' ), true ) ) {
					continue;
				}
				if ( $require_stable && 'stable' !== VersionParser::parseStability( $version ) ) {
					continue;
				}

				if ( $update_version && ! Comparator::greaterThan( $version, $update_version ) ) {
					continue;
				}
				$update_version = $version;
				$update_package = $download_link;
			}
			// If there's not a matching version, bail on updates.
			if ( ! $update_version ) {
				unset( $items[ $i ] );
				continue;
			}
			$items[ $i ]['update_version'] = $update_version;
			$items[ $i ]['update_package'] = $update_package;
		}
		return $items;
	}

	/**
	 * Search wordpress.org repo.
	 *
	 * @param  array $args       A arguments array containing the search term in the first element.
	 * @param  array $assoc_args Data passed in from command.
	 */
	// phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- Whitelisting to provide backward compatibility to classes possibly extending this class.
	protected function _search( $args, $assoc_args ) {
		$term = $args[0];

		$defaults   = [
			'per-page' => 10,
			'page'     => 1,
			'fields'   => implode( ',', [ 'name', 'slug', 'rating' ] ),
		];
		$assoc_args = array_merge( $defaults, $assoc_args );
		$fields     = array();
		foreach ( explode( ',', $assoc_args['fields'] ) as $field ) {
			$fields[ $field ] = true;
		}

		$format    = ! empty( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$formatter = $this->get_formatter( $assoc_args );

		$api_args = [
			'per_page' => (int) $assoc_args['per-page'],
			'page'     => (int) $assoc_args['page'],
			'search'   => $term,
			'fields'   => $fields,
		];

		if ( 'plugin' === $this->item_type ) {
			$api = plugins_api( 'query_plugins', $api_args );
		} else {
			// fields[screenshot_count] could be an int, not a bool.
			// @phpstan-ignore argument.type
			$api = themes_api( 'query_themes', $api_args );
		}

		/**
		 * @var \WP_Error|object{info: object{page: int, pages: int, results: int}} $api
		 */

		if ( is_wp_error( $api ) ) {
			WP_CLI::error( $api->get_error_message() . __( ' Try again' ) );
		}

		$plural = $this->item_type . 's';

		if ( ! isset( $api->$plural ) ) {
			WP_CLI::error( __( 'API error. Try Again.' ) );
		}

		$items = $api->$plural;

		// Add `url` for plugin or theme on wordpress.org.
		// In older WP versions these used to be objects.
		foreach ( $items as $index => $item_object ) {
			if ( is_array( $item_object ) ) {
				$items[ $index ]['url'] = "https://wordpress.org/{$plural}/{$item_object['slug']}/";
			} elseif ( $item_object instanceof \stdClass ) {
				$item_object->url = "https://wordpress.org/{$plural}/{$item_object->slug}/";
			}
		}

		if ( 'table' === $format ) {
			/**
			 * @var string $count
			 */
			$count = Utils\get_flag_value( (array) $api->info, 'results', 'unknown' );
			WP_CLI::success( sprintf( 'Showing %s of %s %s.', count( $items ), $count, $plural ) );
		}

		$formatter->display_items( $items );
	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->item_type );
	}

	/**
	 * Error handler to ignore failures on accessing SSL "https://api.wordpress.org/themes/update-check/1.1/" in `wp_update_themes()`
	 * and "https://api.wordpress.org/plugins/update-check/1.1/" in `wp_update_plugins()` which seem to occur intermittently.
	 */
	public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext = null ) {
		// If ignoring E_USER_WARNING | E_USER_NOTICE, default.
		if ( ! ( error_reporting() & $errno ) ) {
			return false;
		}
		// If not in "wp-includes/update.php", default.
		$update_php = 'wp-includes/update.php';
		if ( 0 !== substr_compare( $errfile, $update_php, -strlen( $update_php ) ) ) {
			return false;
		}
		// Else assume it's in `wp_update_themes()` or `wp_update_plugins()` and just ignore it.
		return true;
	}

	/**
	 * Add versioned GitHub URLs to cache allowlist.
	 *
	 * @param string $url The URL to check.
	 */
	protected static function maybe_cache( $url, $item_type ) {
		$matches = [];

		// cache release URLs like `https://github.com/wp-cli-test/generic-example-plugin/releases/download/v0.1.0/generic-example-plugin.0.1.0.zip`
		if ( preg_match( '#github\.com/[^/]+/([^/]+)/releases/download/v?([^/]+)/.+\.zip#', $url, $matches ) ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $url, $item_type, $matches[1], $matches[2] );
			// cache archive URLs like `https://github.com/wp-cli-test/generic-example-plugin/archive/v0.1.0.zip`
		} elseif ( preg_match( '#github\.com/[^/]+/([^/]+)/archive/(version/|)v?([^/]+)\.zip#', $url, $matches ) ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $url, $item_type, $matches[1], $matches[3] );
			// cache release URLs like `https://api.github.com/repos/danielbachhuber/one-time-login/zipball/v0.4.0`
		} elseif ( preg_match( '#api\.github\.com/repos/[^/]+/([^/]+)/zipball/v?([^/]+)#', $url, $matches ) ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $url, $item_type, $matches[1], $matches[2] );
		}
	}

	/**
	 * Get the latest package version based on a given repo slug.
	 *
	 * @param string $repo_slug
	 *
	 * @return array{ name: string, url: string }|\WP_Error
	 */
	protected function get_the_latest_github_version( $repo_slug ) {
		$api_url = sprintf( $this->github_releases_api_endpoint, $repo_slug );
		$token   = getenv( 'GITHUB_TOKEN' );

		$request_arguments = $token ? [ 'headers' => 'Authorization: Bearer ' . getenv( 'GITHUB_TOKEN' ) ] : [];

		$response = \wp_remote_get( $api_url, $request_arguments );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$body         = \wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $body );

		// WP_Http::FORBIDDEN doesn't exist in WordPress 3.7
		if ( 403 === wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				403,
				$this->build_rate_limiting_error_message( $decoded_body )
			);
		}

		if ( 404 === wp_remote_retrieve_response_code( $response ) ) {
			/**
			 * @var object{status: string, message: string} $decoded_body
			 */
			return new \WP_Error(
				$decoded_body->status,
				$decoded_body->message
			);
		}

		if ( null === $decoded_body ) {
			return new \WP_Error( 500, 'Empty response received from GitHub.com API' );
		}

		/**
		 * @var array<int, object{name: string}> $decoded_body
		 */

		if ( ! isset( $decoded_body[0] ) ) {
			return new \WP_Error( '400', 'The given Github repository does not have any releases' );
		}

		$latest_release = $decoded_body[0];

		return [
			'name' => $latest_release->name,
			'url'  => $this->get_asset_url_from_release( $latest_release ),
		];
	}

	/**
	 * Get the asset URL from the release array. When the asset is not present, we fallback to the zipball_url (source code) property.
	 */
	private function get_asset_url_from_release( $release ) {
		if ( isset( $release->assets[0]->browser_download_url ) ) {
			return $release->assets[0]->browser_download_url;
		}

		if ( isset( $release->zipball_url ) ) {
			return $release->zipball_url;
		}

		return null;
	}

	/**
	 * Get the GitHub repo from the URL.
	 *
	 * @param string $url
	 *
	 * @return string|null
	 */
	protected function get_github_repo_from_releases_url( $url ) {
		preg_match( $this->github_latest_release_url, $url, $matches );

		return isset( $matches[1] ) ? $matches[1] : null;
	}

	/**
	 * Build the error message we display in WP-CLI for the API Rate limiting error response.
	 *
	 * @param $decoded_body
	 *
	 * @return string
	 */
	private function build_rate_limiting_error_message( $decoded_body ) {
		return $decoded_body->message . PHP_EOL . $decoded_body->documentation_url . PHP_EOL . 'In order to pass the token to WP-CLI, you need to use the GITHUB_TOKEN environment variable.';
	}

	/**
	 * Check if a URL is a GitHub Gist page URL (not the raw URL).
	 *
	 * @param string $url The URL to check.
	 * @return string|null The gist ID if it's a gist URL, null otherwise.
	 */
	protected function get_gist_id_from_url( $url ) {
		// Match gist.github.com URLs but not gist.githubusercontent.com (raw URLs)
		// Supports both user-owned gists (gist.github.com/username/id) and anonymous gists (gist.github.com/id)
		// Gist IDs are hexadecimal strings that can contain both lowercase and uppercase
		if ( preg_match( '#^https?://gist\.github\.com/(?:[^/]+/)?([a-fA-F0-9]+)/?$#', $url, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Convert a GitHub Gist page URL to a raw PHP file URL.
	 *
	 * @param string $gist_id The gist ID.
	 * @return string|WP_Error The raw URL of the first PHP file in the gist, or WP_Error on failure.
	 */
	protected function get_raw_url_from_gist( $gist_id ) {
		$api_url = 'https://api.github.com/gists/' . $gist_id;
		$token   = getenv( 'GITHUB_TOKEN' );

		$request_arguments = $token ? [ 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ] : [];

		$response = \wp_remote_get( $api_url, $request_arguments );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = \wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $body );

		// Handle common HTTP error codes with specific error messages
		if ( 401 === $response_code ) {
			return new \WP_Error(
				'github_unauthorized',
				'Unauthorized: Invalid or missing GitHub token.',
				[ 'status' => 401 ]
			);
		}

		if ( 403 === $response_code ) {
			// Check if decoded_body is valid before using it
			if ( null !== $decoded_body && is_object( $decoded_body ) ) {
				return new \WP_Error(
					'github_rate_limited',
					$this->build_rate_limiting_error_message( $decoded_body ),
					[ 'status' => 403 ]
				);
			}
			return new \WP_Error(
				'github_forbidden',
				'Access forbidden. This may be due to rate limiting or insufficient permissions.',
				[ 'status' => 403 ]
			);
		}

		if ( 404 === $response_code ) {
			return new \WP_Error(
				'gist_not_found',
				'Gist not found.',
				[ 'status' => 404 ]
			);
		}

		if ( 500 === $response_code ) {
			return new \WP_Error(
				'github_server_error',
				'GitHub server error. Please try again later.',
				[ 'status' => 500 ]
			);
		}

		if ( 503 === $response_code ) {
			return new \WP_Error(
				'github_unavailable',
				'GitHub service is temporarily unavailable. Please try again later.',
				[ 'status' => 503 ]
			);
		}

		// Check for other non-2xx status codes
		if ( $response_code < 200 || $response_code >= 300 ) {
			return new \WP_Error(
				'github_api_error',
				sprintf( 'GitHub API returned unexpected status code: %d', $response_code ),
				[ 'status' => $response_code ]
			);
		}

		if ( null === $decoded_body || ! is_object( $decoded_body ) || ! isset( $decoded_body->files ) ) {
			return new \WP_Error(
				'invalid_gist_api_response',
				'Invalid response from GitHub Gist API.',
				[ 'status' => $response_code ]
			);
		}

		// Find PHP files in the gist
		$php_files = [];
		$files     = (array) $decoded_body->files;
		foreach ( $files as $filename => $file_data ) {
			if ( is_object( $file_data ) && isset( $file_data->raw_url ) && strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) === 'php' ) {
				$php_files[] = [
					'name'    => $filename,
					'raw_url' => $file_data->raw_url,
				];
			}
		}

		if ( empty( $php_files ) ) {
			return new \WP_Error( 'no_php_files', 'No PHP files found in the gist.' );
		}

		// Return the first PHP file found
		return $php_files[0]['raw_url'];
	}
}
