<?php

use WP_CLI\ParsePluginNameInput;
use WP_CLI\Utils;
use WP_CLI\WpOrgApi;

use function WP_CLI\Utils\normalize_path;

/**
 * Manages plugins, including installs, activations, and updates.
 *
 * See the WordPress [Plugin Handbook](https://developer.wordpress.org/plugins/) developer resource for more information on plugins.
 *
 * ## EXAMPLES
 *
 *     # Activate plugin
 *     $ wp plugin activate hello
 *     Plugin 'hello' activated.
 *     Success: Activated 1 of 1 plugins.
 *
 *     # Deactivate plugin
 *     $ wp plugin deactivate hello
 *     Plugin 'hello' deactivated.
 *     Success: Deactivated 1 of 1 plugins.
 *
 *     # Delete plugin
 *     $ wp plugin delete hello
 *     Deleted 'hello' plugin.
 *     Success: Deleted 1 of 1 plugins.
 *
 *     # Install the latest version from wordpress.org and activate
 *     $ wp plugin install bbpress --activate
 *     Installing bbPress (2.5.9)
 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.2.5.9.zip...
 *     Using cached file '/home/vagrant/.wp-cli/cache/plugin/bbpress-2.5.9.zip'...
 *     Unpacking the package...
 *     Installing the plugin...
 *     Plugin installed successfully.
 *     Activating 'bbpress'...
 *     Plugin 'bbpress' activated.
 *     Success: Installed 1 of 1 plugins.
 *
 * @package wp-cli
 */
class Plugin_Command extends \WP_CLI\CommandWithUpgrade {

	use ParsePluginNameInput;

	protected $item_type         = 'plugin';
	protected $upgrade_refresh   = 'wp_update_plugins';
	protected $upgrade_transient = 'update_plugins';
	protected $check_wporg       = [
		'status'       => false,
		'last_updated' => false,
	];
	protected $check_headers     = [
		'tested_up_to' => false,
	];

	protected $obj_fields = array(
		'name',
		'status',
		'update',
		'version',
		'update_version',
		'auto_update',
	);

	/**
	 * Plugin fetcher instance.
	 *
	 * @var \WP_CLI\Fetchers\Plugin
	 */
	protected $fetcher;

	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		parent::__construct();

		$this->fetcher = new WP_CLI\Fetchers\Plugin();
	}

	protected function get_upgrader_class( $force ) {
		return $force ? '\\WP_CLI\\DestructivePluginUpgrader' : 'Plugin_Upgrader';
	}

	/**
	 * Reveals the status of one or all plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : A particular plugin to show the status for.
	 *
	 * ## EXAMPLES
	 *
	 *     # Displays status of all plugins
	 *     $ wp plugin status
	 *     5 installed plugins:
	 *       I akismet                3.1.11
	 *       I easy-digital-downloads 2.5.16
	 *       A theme-check            20160523.1
	 *       I wen-logo-slider        2.0.3
	 *       M ns-pack                1.0.0
	 *     Legend: I = Inactive, A = Active, M = Must Use
	 *
	 *     # Displays status of a plugin
	 *     $ wp plugin status theme-check
	 *     Plugin theme-check details:
	 *         Name: Theme Check
	 *         Status: Active
	 *         Version: 20160523.1
	 *         Author: Otto42, pross
	 *         Description: A simple and easy way to test your theme for all the latest WordPress standards and practices. A great theme development tool!
	 */
	public function status( $args ) {
		parent::status( $args );
	}

	/**
	 * Searches the WordPress.org plugin directory.
	 *
	 * Displays plugins in the WordPress.org plugin directory matching a given
	 * search query.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : The string to search for.
	 *
	 * [--page=<page>]
	 * : Optional page to display.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--per-page=<per-page>]
	 * : Optional number of results to display.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each plugin.
	 *
	 * [--fields=<fields>]
	 * : Ask for specific fields from the API. Defaults to name,slug,author_profile,rating. Acceptable values:
	 *
	 *     **name**: Plugin Name
	 *     **slug**: Plugin Slug
	 *     **version**: Current Version Number
	 *     **author**: Plugin Author
	 *     **author_profile**: Plugin Author Profile
	 *     **contributors**: Plugin Contributors
	 *     **requires**: Plugin Minimum Requirements
	 *     **tested**: Plugin Tested Up To
	 *     **compatibility**: Plugin Compatible With
	 *     **rating**: Plugin Rating in Percent and Total Number
	 *     **ratings**: Plugin Ratings for each star (1-5)
	 *     **num_ratings**: Number of Plugin Ratings
	 *     **homepage**: Plugin Author's Homepage
	 *     **description**: Plugin's Description
	 *     **short_description**: Plugin's Short Description
	 *     **sections**: Plugin Readme Sections: description, installation, FAQ, screenshots, other notes, and changelog
	 *     **downloaded**: Plugin Download Count
	 *     **last_updated**: Plugin's Last Update
	 *     **added**: Plugin's Date Added to wordpress.org Repository
	 *     **tags**: Plugin's Tags
	 *     **versions**: Plugin's Available Versions with D/L Link
	 *     **donate_link**: Plugin's Donation Link
	 *     **banners**: Plugin's Banner Image Link
	 *     **icons**: Plugin's Icon Image Link
	 *     **active_installs**: Plugin's Number of Active Installs
	 *     **contributors**: Plugin's List of Contributors
	 *     **url**: Plugin's URL on wordpress.org
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin search dsgnwrks --per-page=20 --format=json
	 *     Success: Showing 3 of 3 plugins.
	 *     [{"name":"DsgnWrks Instagram Importer Debug","slug":"dsgnwrks-instagram-importer-debug","rating":0},{"name":"DsgnWrks Instagram Importer","slug":"dsgnwrks-instagram-importer","rating":84},{"name":"DsgnWrks Twitter Importer","slug":"dsgnwrks-twitter-importer","rating":80}]
	 *
	 *     $ wp plugin search dsgnwrks --fields=name,version,slug,rating,num_ratings
	 *     Success: Showing 3 of 3 plugins.
	 *     +-----------------------------------+---------+-----------------------------------+--------+-------------+
	 *     | name                              | version | slug                              | rating | num_ratings |
	 *     +-----------------------------------+---------+-----------------------------------+--------+-------------+
	 *     | DsgnWrks Instagram Importer Debug | 0.1.6   | dsgnwrks-instagram-importer-debug | 0      | 0           |
	 *     | DsgnWrks Instagram Importer       | 1.3.7   | dsgnwrks-instagram-importer       | 84     | 23          |
	 *     | DsgnWrks Twitter Importer         | 1.1.1   | dsgnwrks-twitter-importer         | 80     | 1           |
	 *     +-----------------------------------+---------+-----------------------------------+--------+-------------+
	 */
	public function search( $args, $assoc_args ) {
		parent::_search( $args, $assoc_args );
	}

	protected function status_single( $args ) {
		$plugin = $this->fetcher->get_check( $args[0] );
		$file   = $plugin->file;

		$details = $this->get_details( $file );

		$status = $this->format_status( $this->get_status( $file ), 'long' );

		$version = $details['Version'];

		if ( $this->has_update( $file ) ) {
			$version .= ' (%gUpdate available%n)';
		}

		echo WP_CLI::colorize(
			Utils\mustache_render(
				self::get_template_path( 'plugin-status.mustache' ),
				[
					'slug'        => Utils\get_plugin_name( $file ),
					'status'      => $status,
					'version'     => $version,
					'name'        => $details['Name'],
					'author'      => $details['Author'],
					'description' => $details['Description'],
				]
			)
		);
	}

	protected function get_all_items() {
		$items = $this->get_item_list();

		foreach ( get_mu_plugins() as $file => $mu_plugin ) {
			$mu_version = '';
			if ( ! empty( $mu_plugin['Version'] ) ) {
				$mu_version = $mu_plugin['Version'];
			}

			$mu_title = '';
			if ( ! empty( $mu_plugin['Name'] ) ) {
				$mu_title = $mu_plugin['Title'];
			}

			$mu_description = '';
			if ( ! empty( $mu_plugin['Description'] ) ) {
				$mu_description = $mu_plugin['Description'];
			}
			$mu_name    = Utils\get_plugin_name( $file );
			$wporg_info = $this->get_wporg_data( $mu_name );

			$items[ $file ] = array(
				'name'               => $mu_name,
				'status'             => 'must-use',
				'update'             => false,
				'update_version'     => null,
				'update_package'     => null,
				'version'            => $mu_version,
				'update_id'          => '',
				'title'              => $mu_title,
				'description'        => $mu_description,
				'file'               => $file,
				'auto_update'        => false,
				'tested_up_to'       => '',
				'requires'           => '',
				'requires_php'       => '',
				'wporg_status'       => $wporg_info['status'],
				'wporg_last_updated' => $wporg_info['last_updated'],
			);
		}

		$raw_items = get_dropins();
		$raw_data  = _get_dropins();
		foreach ( $raw_items as $name => $item_data ) {
			$description    = ! empty( $raw_data[ $name ][0] ) ? $raw_data[ $name ][0] : '';
			$items[ $name ] = [
				'name'               => $name,
				'title'              => $item_data['Title'],
				'description'        => $description,
				'status'             => 'dropin',
				'update'             => false,
				'update_version'     => null,
				'update_package'     => null,
				'update_id'          => '',
				'file'               => $name,
				'auto_update'        => false,
				'author'             => $item_data['Author'],
				'tested_up_to'       => '',
				'requires'           => '',
				'requires_php'       => '',
				'wporg_status'       => '',
				'wporg_last_updated' => '',
			];
		}

		return $items;
	}

	/**
	 * Activates one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to activate.
	 *
	 * [--all]
	 * : If set, all plugins will be activated.
	 *
	 * [--exclude=<name>]
	 * : Comma separated list of plugin slugs to be excluded from activation.
	 *
	 * [--network]
	 * : If set, the plugin will be activated for the entire multisite network.
	 *
	 * ## EXAMPLES
	 *
	 *     # Activate plugin
	 *     $ wp plugin activate hello
	 *     Plugin 'hello' activated.
	 *     Success: Activated 1 of 1 plugins.
	 *
	 *     # Activate plugin in entire multisite network
	 *     $ wp plugin activate hello --network
	 *     Plugin 'hello' network activated.
	 *     Success: Network activated 1 of 1 plugins.
	 *
	 *     # Activate plugins that were recently active.
	 *     $ wp plugin activate $(wp plugin list --recently-active --field=name)
	 *     Plugin 'bbpress' activated.
	 *     Plugin 'buddypress' activated.
	 *     Success: Activated 2 of 2 plugins.
	 *
	 *     # Activate plugins that were recently active on a multisite.
	 *     $ wp plugin activate $(wp plugin list --recently-active --field=name) --network
	 *     Plugin 'bbpress' network activated.
	 *     Plugin 'buddypress' network activated.
	 *     Success: Activated 2 of 2 plugins.
	 */
	public function activate( $args, $assoc_args = array() ) {
		$network_wide = Utils\get_flag_value( $assoc_args, 'network', false );
		$all          = Utils\get_flag_value( $assoc_args, 'all', false );
		$all_exclude  = Utils\get_flag_value( $assoc_args, 'exclude' );

		$args = $this->check_optional_args_and_all( $args, $all, 'activate', $all_exclude );
		if ( ! $args ) {
			return;
		}

		$successes = 0;
		$errors    = 0;
		$plugins   = $this->fetcher->get_many( $args );
		if ( count( $plugins ) < count( $args ) ) {
			$errors = count( $args ) - count( $plugins );
		}
		foreach ( $plugins as $plugin ) {
			$status = $this->get_status( $plugin->file );
			if ( $all && in_array( $status, [ 'active', 'active-network' ], true ) ) {
				continue;
			}
			// Network-active is the highest level of activation status.
			if ( 'active-network' === $status ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' is already network active." );
				continue;
			}
			// Don't reactivate active plugins, but do let them become network-active.
			if ( ! $network_wide && 'active' === $status ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' is already active." );
				continue;
			}

			// Plugins need to be deactivated before being network activated.
			if ( $network_wide && 'active' === $status ) {
				deactivate_plugins( $plugin->file, false, false );
			}

			$result = activate_plugin( $plugin->file, '', $network_wide );

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				$message = preg_replace( '/<a\s[^>]+>.*<\/a>/im', '', $message );
				$message = wp_strip_all_tags( $message );
				$message = str_replace( 'Error: ', '', $message );
				WP_CLI::warning( "Failed to activate plugin. {$message}" );
				++$errors;
			} else {
				$this->active_output( $plugin->name, $plugin->file, $network_wide, 'activate' );
				++$successes;
			}
		}

		if ( ! $this->chained_command ) {
			$verb = $network_wide ? 'network activate' : 'activate';
			Utils\report_batch_operation_results( 'plugin', $verb, count( $args ), $successes, $errors );
		}
	}

	/**
	 * Deactivates one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to deactivate.
	 *
	 * [--uninstall]
	 * : Uninstall the plugin after deactivation.
	 *
	 * [--all]
	 * : If set, all plugins will be deactivated.
	 *
	 * [--exclude=<name>]
	 * : Comma separated list of plugin slugs that should be excluded from deactivation.
	 *
	 * [--network]
	 * : If set, the plugin will be deactivated for the entire multisite network.
	 *
	 * ## EXAMPLES
	 *
	 *     # Deactivate plugin
	 *     $ wp plugin deactivate hello
	 *     Plugin 'hello' deactivated.
	 *     Success: Deactivated 1 of 1 plugins.
	 *
	 *     # Deactivate all plugins with exclusion
	 *     $ wp plugin deactivate --all --exclude=hello,wordpress-seo
	 *     Plugin 'contact-form-7' deactivated.
	 *     Plugin 'ninja-forms' deactivated.
	 *     Success: Deactivated 2 of 2 plugins.
	 */
	public function deactivate( $args, $assoc_args = array() ) {
		$network_wide        = Utils\get_flag_value( $assoc_args, 'network' );
		$disable_all         = Utils\get_flag_value( $assoc_args, 'all' );
		$disable_all_exclude = Utils\get_flag_value( $assoc_args, 'exclude' );

		$args = $this->check_optional_args_and_all( $args, $disable_all, 'deactivate', $disable_all_exclude );
		if ( ! $args ) {
			return;
		}

		$successes = 0;
		$errors    = 0;
		$plugins   = $this->fetcher->get_many( $args );
		if ( count( $plugins ) < count( $args ) ) {
			$errors = count( $args ) - count( $plugins );
		}

		foreach ( $plugins as $plugin ) {

			$status = $this->get_status( $plugin->file );
			if ( $disable_all && ! in_array( $status, [ 'active', 'active-network' ], true ) ) {
				continue;
			}

			// Network active plugins must be explicitly deactivated.
			if ( ! $network_wide && 'active-network' === $status ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' is network active and must be deactivated with --network flag." );
				++$errors;
				continue;
			}

			if ( ! in_array( $status, [ 'active', 'active-network' ], true ) ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' isn't active." );
				continue;
			}

			deactivate_plugins( $plugin->file, false, $network_wide );

			if ( ! is_network_admin() ) {
				update_option(
					'recently_activated',
					array( $plugin->file => time() ) + (array) get_option( 'recently_activated' )
				);
			} else {
				update_site_option(
					'recently_activated',
					array( $plugin->file => time() ) + (array) get_site_option( 'recently_activated' )
				);
			}

			$this->active_output( $plugin->name, $plugin->file, $network_wide, 'deactivate' );
			++$successes;

			if ( Utils\get_flag_value( $assoc_args, 'uninstall' ) ) {
				WP_CLI::log( "Uninstalling '{$plugin->name}'..." );
				$this->chained_command = true;
				$this->uninstall( array( $plugin->name ) );
				$this->chained_command = false;
			}
		}

		if ( ! $this->chained_command ) {
			$verb = $network_wide ? 'network deactivate' : 'deactivate';
			Utils\report_batch_operation_results( 'plugin', $verb, count( $args ), $successes, $errors );
		}
	}

	/**
	 * Toggles a plugin's activation state.
	 *
	 * If the plugin is active, then it will be deactivated. If the plugin is
	 * inactive, then it will be activated.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>...
	 * : One or more plugins to toggle.
	 *
	 * [--network]
	 * : If set, the plugin will be toggled for the entire multisite network.
	 *
	 * ## EXAMPLES
	 *
	 *     # Akismet is currently activated
	 *     $ wp plugin toggle akismet
	 *     Plugin 'akismet' deactivated.
	 *     Success: Toggled 1 of 1 plugins.
	 *
	 *     # Akismet is currently deactivated
	 *     $ wp plugin toggle akismet
	 *     Plugin 'akismet' activated.
	 *     Success: Toggled 1 of 1 plugins.
	 */
	public function toggle( $args, $assoc_args = array() ) {
		$network_wide = Utils\get_flag_value( $assoc_args, 'network' );

		$successes = 0;
		$errors    = 0;
		$plugins   = $this->fetcher->get_many( $args );
		if ( count( $plugins ) < count( $args ) ) {
			$errors = count( $args ) - count( $plugins );
		}
		$this->chained_command = true;
		foreach ( $plugins as $plugin ) {
			if ( $this->check_active( $plugin->file, $network_wide ) ) {
				$this->deactivate( array( $plugin->name ), $assoc_args );
			} else {
				$this->activate( array( $plugin->name ), $assoc_args );
			}
			++$successes;
		}
		$this->chained_command = false;
		Utils\report_batch_operation_results( 'plugin', 'toggle', count( $args ), $successes, $errors );
	}

	/**
	 * Gets the path to a plugin or to the plugin directory.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : The plugin to get the path to. If not set, will return the path to the
	 * plugins directory.
	 *
	 * [--dir]
	 * : If set, get the path to the closest parent directory, instead of the
	 * plugin file.
	 *
	 * ## EXAMPLES
	 *
	 *     $ cd $(wp plugin path) && pwd
	 *     /var/www/wordpress/wp-content/plugins
	 */
	public function path( $args, $assoc_args ) {
		$path = untrailingslashit( WP_PLUGIN_DIR );

		if ( ! empty( $args ) ) {
			$plugin = $this->fetcher->get_check( $args[0] );
			$path  .= '/' . $plugin->file;

			if ( Utils\get_flag_value( $assoc_args, 'dir' ) ) {
				$path = dirname( $path );
			}
		}

		WP_CLI::line( $path );
	}

	protected function install_from_repo( $slug, $assoc_args ) {
		global $wp_version;
		// Extract the major WordPress version (e.g., "6.3") from the full version string
		list($wp_core_version) = explode( '-', $wp_version );
		$wp_core_version       = implode( '.', array_slice( explode( '.', $wp_core_version ), 0, 2 ) );

		$api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( isset( $assoc_args['version'] ) ) {
			self::alter_api_response( $api, $assoc_args['version'] );
		} elseif ( ! Utils\get_flag_value( $assoc_args, 'ignore-requirements', false ) ) {
			$requires_php = isset( $api->requires_php ) ? $api->requires_php : null;
			$requires_wp  = isset( $api->requires ) ? $api->requires : null;

			$compatible_php = empty( $requires_php ) || version_compare( PHP_VERSION, $requires_php, '>=' );
			$compatible_wp  = empty( $requires_wp ) || version_compare( $wp_core_version, $requires_wp, '>=' );

			if ( ! $compatible_wp ) {
				return new WP_Error( 'requirements_not_met', "This plugin does not work with your version of WordPress. Minimum WordPress requirement is $requires_wp" );
			}

			if ( ! $compatible_php ) {
				return new WP_Error( 'requirements_not_met', "This plugin does not work with your version of PHP. Minimum PHP required is $compatible_php" );
			}
		}

		$status = install_plugin_install_status( $api );

		if ( ! Utils\get_flag_value( $assoc_args, 'force' ) && 'install' !== $status['status'] ) {
			// We know this will fail, so avoid a needless download of the package.
			return new WP_Error( 'already_installed', 'Plugin already installed.' );
		}

		WP_CLI::log( sprintf( 'Installing %s (%s)', html_entity_decode( $api->name, ENT_QUOTES ), $api->version ) );
		if ( Utils\get_flag_value( $assoc_args, 'version' ) !== 'dev' ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $api->download_link, $this->item_type, $api->slug, $api->version );
		}

		// Ignore failures on accessing SSL "https://api.wordpress.org/plugins/update-check/1.1/" in `wp_update_plugins()` which seem to occur intermittently.
		set_error_handler( array( __CLASS__, 'error_handler' ), E_USER_WARNING | E_USER_NOTICE );

		$result = $this->get_upgrader( $assoc_args )->install( $api->download_link );

		restore_error_handler();

		return $result;
	}

	/**
	 * Updates one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to update.
	 *
	 * [--all]
	 * : If set, all plugins that have updates will be updated.
	 *
	 * [--exclude=<name>]
	 * : Comma separated list of plugin names that should be excluded from updating.
	 *
	 * [--minor]
	 * : Only perform updates for minor releases (e.g. from 1.3 to 1.4 instead of 2.0)
	 *
	 * [--patch]
	 * : Only perform updates for patch releases (e.g. from 1.3 to 1.3.3 instead of 1.4)
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - summary
	 * ---
	 *
	 * [--version=<version>]
	 * : If set, the plugin will be updated to the specified version.
	 *
	 * [--dry-run]
	 * : Preview which plugins would be updated.
	 *
	 * [--insecure]
	 * : Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin update bbpress --version=dev
	 *     Installing bbPress (Development Version)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.zip...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *     Success: Updated 1 of 2 plugins.
	 *
	 *     $ wp plugin update --all
	 *     Enabling Maintenance mode...
	 *     Downloading update from https://downloads.wordpress.org/plugin/akismet.3.1.11.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *     Downloading update from https://downloads.wordpress.org/plugin/nginx-champuru.3.2.0.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *     Disabling Maintenance mode...
	 *     +------------------------+-------------+-------------+---------+
	 *     | name                   | old_version | new_version | status  |
	 *     +------------------------+-------------+-------------+---------+
	 *     | akismet                | 3.1.3       | 3.1.11      | Updated |
	 *     | nginx-cache-controller | 3.1.1       | 3.2.0       | Updated |
	 *     +------------------------+-------------+-------------+---------+
	 *     Success: Updated 2 of 2 plugins.
	 *
	 *     $ wp plugin update --all --exclude=akismet
	 *     Enabling Maintenance mode...
	 *     Downloading update from https://downloads.wordpress.org/plugin/nginx-champuru.3.2.0.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *     Disabling Maintenance mode...
	 *     +------------------------+-------------+-------------+---------+
	 *     | name                   | old_version | new_version | status  |
	 *     +------------------------+-------------+-------------+---------+
	 *     | nginx-cache-controller | 3.1.1       | 3.2.0       | Updated |
	 *     +------------------------+-------------+-------------+---------+
	 *
	 * @alias upgrade
	 */
	public function update( $args, $assoc_args ) {
		$all = Utils\get_flag_value( $assoc_args, 'all', false );

		$args = $this->check_optional_args_and_all( $args, $all );
		if ( ! $args ) {
			return;
		}

		if ( isset( $assoc_args['version'] ) ) {
			foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
				$assoc_args['force'] = 1;
				$this->install( array( $plugin->name ), $assoc_args );
			}
		} else {
			parent::update_many( $args, $assoc_args );
		}
	}

	protected function get_item_list() {
		global $wp_version;

		$items           = [];
		$duplicate_names = [];

		$auto_updates = get_site_option( Plugin_AutoUpdates_Command::SITE_OPTION );

		if ( ! is_array( $auto_updates ) ) {
			$auto_updates = [];
		}

		$recently_active = is_network_admin() ? get_site_option( 'recently_activated' ) : get_option( 'recently_activated' );

		if ( false === $recently_active ) {
			$recently_active = [];
		}

		foreach ( $this->get_all_plugins() as $file => $details ) {
			$all_update_info = $this->get_update_info();
			$update_info     = ( isset( $all_update_info->response[ $file ] ) && null !== $all_update_info->response[ $file ] ) ? (array) $all_update_info->response[ $file ] : null;
			$name            = Utils\get_plugin_name( $file );
			$wporg_info      = $this->get_wporg_data( $name );
			$plugin_data     = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );

			if ( ! isset( $duplicate_names[ $name ] ) ) {
				$duplicate_names[ $name ] = array();
			}

			$requires     = isset( $update_info ) && isset( $update_info['requires'] ) ? $update_info['requires'] : null;
			$requires_php = isset( $update_info ) && isset( $update_info['requires_php'] ) ? $update_info['requires_php'] : null;

			// If an update has requires_php set, check to see if the local version of PHP meets that requirement
			// The plugins update API already filters out plugins that don't meet WordPress requirements, but does not
			// filter out plugins based on PHP requirements -- so we must do that here
			$compatible_php = empty( $requires_php ) || version_compare( PHP_VERSION, $requires_php, '>=' );

			if ( ! $compatible_php ) {
				$update = 'unavailable';

				$update_unavailable_reason = sprintf(
					'This update requires PHP version %s, but the version installed is %s.',
					$requires_php,
					PHP_VERSION
				);
			} else {
				$update = $update_info ? 'available' : 'none';
			}

			// requires and requires_php are only provided by the plugins update API in the case of an update available.
			// For display consistency, get these values from the current plugin file if they aren't in this response
			if ( null === $requires ) {
				$requires = ! empty( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '';
			}

			if ( null === $requires_php ) {
					$requires_php = ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '';
			}

			$duplicate_names[ $name ][] = $file;
			$items[ $file ]             = [
				'name'                      => $name,
				'status'                    => $this->get_status( $file ),
				'update'                    => $update,
				'update_version'            => isset( $update_info ) && isset( $update_info['new_version'] ) ? $update_info['new_version'] : null,
				'update_package'            => isset( $update_info ) && isset( $update_info['package'] ) ? $update_info['package'] : null,
				'version'                   => $details['Version'],
				'update_id'                 => $file,
				'title'                     => $details['Name'],
				'description'               => wordwrap( $details['Description'] ),
				'file'                      => $file,
				'auto_update'               => in_array( $file, $auto_updates, true ),
				'author'                    => $details['Author'],
				'tested_up_to'              => '',
				'requires'                  => $requires,
				'requires_php'              => $requires_php,
				'wporg_status'              => $wporg_info['status'],
				'wporg_last_updated'        => $wporg_info['last_updated'],
				'recently_active'           => in_array( $file, array_keys( $recently_active ), true ),
				'update_unavailable_reason' => isset( $update_unavailable_reason ) ? $update_unavailable_reason : '',
			];

			if ( $this->check_headers['tested_up_to'] ) {
				$plugin_readme = normalize_path( dirname( WP_PLUGIN_DIR . '/' . $file ) . '/readme.txt' );

				if ( file_exists( $plugin_readme ) && is_readable( $plugin_readme ) ) {
					$readme_obj = new SplFileObject( $plugin_readme );
					$readme_obj->setFlags( SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY );
					$readme_line = 0;

					// Reading the whole file can exhaust the memory, so only read the first 100 lines of the file,
					// as the "Tested up to" header should be near the top.
					while ( $readme_line < 100 && ! $readme_obj->eof() ) {
						$line = $readme_obj->fgets();

						// Similar to WP.org, it matches for both "Tested up to" and "Tested" header in the readme file.
						preg_match( '/^tested(:| up to:) (.*)$/i', strtolower( $line ), $matches );

						if ( isset( $matches[2] ) && ! empty( $matches[2] ) ) {
							$items[ $file ]['tested_up_to'] = $matches[2];
							break;
						}

						++$readme_line;
					}

					$file_obj = null;
				}
			}

			if ( null === $update_info ) {
				// Get info for all plugins that don't have an update.
				$plugin_update_info = isset( $all_update_info->no_update[ $file ] ) ? $all_update_info->no_update[ $file ] : null;

				// Check if local version is newer than what is listed upstream.
				if ( null !== $plugin_update_info && version_compare( $details['Version'], $plugin_update_info->new_version, '>' ) ) {
					$items[ $file ]['update']       = static::INVALID_VERSION_MESSAGE;
					$items[ $file ]['requires']     = isset( $plugin_update_info->requires ) ? $plugin_update_info->requires : null;
					$items[ $file ]['requires_php'] = isset( $plugin_update_info->requires_php ) ? $plugin_update_info->requires_php : null;
				}

				// If there is a plugin in no_update with a newer version than the local copy, it is either because:
				// A: the plugins update API has already filtered it because the local WordPress version is too low
				// B: It is possibly a paid plugin that has an update which the user does not qualify for
				if ( null !== $plugin_update_info && version_compare( $details['Version'], $plugin_update_info->new_version, '<' ) ) {
					$items[ $file ]['update']         = 'unavailable';
					$items[ $file ]['update_version'] = $plugin_update_info->new_version;
					$items[ $file ]['requires']       = isset( $plugin_update_info->requires ) ? $plugin_update_info->requires : null;
					$items[ $file ]['requires_php']   = isset( $plugin_update_info->requires_php ) ? $plugin_update_info->requires_php : null;

					if ( isset( $plugin_update_info->requires ) && version_compare( $wp_version, $requires, '>=' ) ) {
						$reason = "This update requires WordPress version $plugin_update_info->requires, but the version installed is $wp_version.";
					} elseif ( ! isset( $update_info['package'] ) ) {
						$reason = 'Update file not provided. Contact author for more details';
					} else {
						$reason = 'Update not available';
					}

					$items[ $file ]['update_unavailable_reason'] = $reason;

				}
			}
		}

		foreach ( $duplicate_names as $name => $files ) {
			if ( count( $files ) <= 1 ) {
				continue;
			}
			foreach ( $files as $file ) {
				$items[ $file ]['name'] = str_replace( '.' . pathinfo( $file, PATHINFO_EXTENSION ), '', $file );
			}
		}

		return $items;
	}

	/**
	 * Get the wordpress.org status of a plugin.
	 *
	 * @param string $plugin_name The plugin slug.
	 *
	 * @return string The status of the plugin, includes the last update date.
	 */
	protected function get_wporg_data( $plugin_name ) {
		$data = [
			'status'       => '',
			'last_updated' => '',
		];
		if ( ! $this->check_wporg['status'] && ! $this->check_wporg['last_updated'] ) {
			return $data;
		}

		if ( $this->check_wporg ) {
			try {
				$plugin_data = ( new WpOrgApi() )->get_plugin_info( $plugin_name );
			} catch ( Exception $e ) {
				// Request failed. The plugin is not (active) on .org.
				$plugin_data = false;
			}
			if ( $plugin_data ) {
				$data['status'] = 'active';
				if ( ! $this->check_wporg['last_updated'] ) {
					return $data; // The plugin is active on .org, but we don't need the date.
				}
			}
			// Just because the plugin is not in the api, does not mean it was never on .org.
		}

		$request       = wp_remote_get( "https://plugins.trac.wordpress.org/log/{$plugin_name}/?limit=1&mode=stop_on_copy&format=rss" );
		$response_code = wp_remote_retrieve_response_code( $request );
		if ( 404 === $response_code ) {
			return $data; // This plugin was never on .org, there is no date to check.
		}
		if ( 'active' !== $data['status'] ) {
			$data['status'] = 'closed'; // This plugin was on .org at some point, but not anymore.
		}
		if ( ! class_exists( 'SimpleXMLElement' ) ) {
			WP_CLI::error( "The PHP extension 'SimpleXMLElement' is not available but is required for XML-formatted output." );
		}

		// Check the last update date.
		$r_body = wp_remote_retrieve_body( $request );
		if ( strpos( $r_body, 'pubDate' ) !== false ) {
			// Very raw check, not validating the format or anything else.
			$xml          = simplexml_load_string( $r_body );
			$xml_pub_date = $xml->xpath( '//pubDate' );
			if ( $xml_pub_date ) {
				$data['last_updated'] = wp_date( 'Y-m-d', (string) strtotime( $xml_pub_date[0] ) );
			}
		}

		return $data;
	}

	protected function filter_item_list( $items, $args ) {
		$basenames = wp_list_pluck( $this->fetcher->get_many( $args ), 'file' );
		return Utils\pick_fields( $items, $basenames );
	}

	/**
	 * Installs one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * <plugin|zip|url>...
	 * : One or more plugins to install. Accepts a plugin slug, the path to a local zip file, or a URL to a remote zip file.
	 *
	 * [--version=<version>]
	 * : If set, get that particular version from wordpress.org, instead of the
	 * stable version.
	 *
	 * [--force]
	 * : If set, the command will overwrite any installed version of the plugin, without prompting
	 * for confirmation.
	 *
	 * [--ignore-requirements]
	 * : If set, the command will install the plugin while ignoring any WordPress or PHP version requirements
	 * specified by the plugin authors.
	 *
	 * [--activate]
	 * : If set, the plugin will be activated immediately after install.
	 *
	 * [--activate-network]
	 * : If set, the plugin will be network activated immediately after install
	 *
	 * [--insecure]
	 * : Retry downloads without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the latest version from wordpress.org and activate
	 *     $ wp plugin install bbpress --activate
	 *     Installing bbPress (2.5.9)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.2.5.9.zip...
	 *     Using cached file '/home/vagrant/.wp-cli/cache/plugin/bbpress-2.5.9.zip'...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *     Activating 'bbpress'...
	 *     Plugin 'bbpress' activated.
	 *     Success: Installed 1 of 1 plugins.
	 *
	 *     # Install the development version from wordpress.org
	 *     $ wp plugin install bbpress --version=dev
	 *     Installing bbPress (Development Version)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.zip...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *     Success: Installed 1 of 1 plugins.
	 *
	 *     # Install from a local zip file
	 *     $ wp plugin install ../my-plugin.zip
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *     Success: Installed 1 of 1 plugins.
	 *
	 *     # Install from a remote zip file
	 *     $ wp plugin install http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 *     Downloading install package from http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *     Success: Installed 1 of 1 plugins.
	 *
	 *     # Update from a remote zip file
	 *     $ wp plugin install https://github.com/envato/wp-envato-market/archive/master.zip --force
	 *     Downloading install package from https://github.com/envato/wp-envato-market/archive/master.zip
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Renamed Github-based project from 'wp-envato-market-master' to 'wp-envato-market'.
	 *     Plugin updated successfully
	 *     Success: Installed 1 of 1 plugins.
	 *
	 *     # Forcefully re-install all installed plugins
	 *     $ wp plugin install $(wp plugin list --field=name) --force
	 *     Installing Akismet (3.1.11)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/akismet.3.1.11.zip...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully
	 *     Success: Installed 1 of 1 plugins.
	 */
	public function install( $args, $assoc_args ) {

		if ( ! is_dir( WP_PLUGIN_DIR ) ) {
			wp_mkdir_p( WP_PLUGIN_DIR );
		}

		parent::install( $args, $assoc_args );
	}

	/**
	 * Gets details about an installed plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole plugin, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for the plugin:
	 *
	 * * name
	 * * title
	 * * author
	 * * version
	 * * description
	 * * status
	 *
	 * These fields are optionally available:
	 *
	 * * requires_wp
	 * * requires_php
	 * * requires_plugins
	 *
	 * ## EXAMPLES
	 *
	 *     # Get plugin details.
	 *     $ wp plugin get bbpress --format=json
	 *     {"name":"bbpress","title":"bbPress","author":"The bbPress Contributors","version":"2.6.9","description":"bbPress is forum software with a twist from the creators of WordPress.","status":"active"}
	 */
	public function get( $args, $assoc_args ) {
		$default_fields = array(
			'name',
			'title',
			'author',
			'version',
			'description',
			'status',
		);

		$plugin = $this->fetcher->get_check( $args[0] );
		$file   = $plugin->file;

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );

		$plugin_obj = (object) [
			'name'             => Utils\get_plugin_name( $file ),
			'title'            => $plugin_data['Name'],
			'author'           => $plugin_data['Author'],
			'version'          => $plugin_data['Version'],
			'description'      => wordwrap( $plugin_data['Description'] ),
			'status'           => $this->get_status( $file ),
			'requires_wp'      => ! empty( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php'     => ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			'requires_plugins' => ! empty( $plugin_data['RequiresPlugins'] ) ? $plugin_data['RequiresPlugins'] : '',
		];

		if ( empty( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = $default_fields;
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $plugin_obj );
	}

	/**
	 * Uninstalls one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to uninstall.
	 *
	 * [--deactivate]
	 * : Deactivate the plugin before uninstalling. Default behavior is to warn and skip if the plugin is active.
	 *
	 * [--skip-delete]
	 * : If set, the plugin files will not be deleted. Only the uninstall procedure
	 * will be run.
	 *
	 * [--all]
	 * : If set, all plugins will be uninstalled.
	 *
	 * [--exclude=<name>]
	 * : Comma separated list of plugin slugs to be excluded from uninstall.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin uninstall hello
	 *     Uninstalled and deleted 'hello' plugin.
	 *     Success: Uninstalled 1 of 1 plugins.
	 *
	 *     # Uninstall all plugins excluding specified ones
	 *     $ wp plugin uninstall --all --exclude=hello-dolly,jetpack
	 *     Uninstalled and deleted 'akismet' plugin.
	 *     Uninstalled and deleted 'tinymce-templates' plugin.
	 *     Success: Uninstalled 2 of 2 plugins.
	 */
	public function uninstall( $args, $assoc_args = array() ) {

		$all         = Utils\get_flag_value( $assoc_args, 'all', false );
		$all_exclude = Utils\get_flag_value( $assoc_args, 'exclude', false );

		// Check if plugin names or --all is passed.
		$args = $this->check_optional_args_and_all( $args, $all, 'uninstall', $all_exclude );
		if ( ! $args ) {
			return;
		}

		$successes            = 0;
		$errors               = 0;
		$delete_errors        = array();
		$deleted_plugin_files = array();

		$plugins = $this->fetcher->get_many( $args );
		if ( count( $plugins ) < count( $args ) ) {
			$errors = count( $args ) - count( $plugins );
		}

		foreach ( $plugins as $plugin ) {
			if ( is_plugin_active( $plugin->file ) && ! WP_CLI\Utils\get_flag_value( $assoc_args, 'deactivate' ) ) {
				WP_CLI::warning( "The '{$plugin->name}' plugin is active." );
				++$errors;
				continue;
			}

			if ( Utils\get_flag_value( $assoc_args, 'deactivate' ) ) {
				WP_CLI::log( "Deactivating '{$plugin->name}'..." );
				$this->chained_command = true;
				$this->deactivate( array( $plugin->name ) );
				$this->chained_command = false;
			}

			uninstall_plugin( $plugin->file );

			$plugin_translations = wp_get_installed_translations( 'plugins' );

			$plugin_slug = dirname( $plugin->file );

			if ( 'hello.php' === $plugin->file ) {
				$plugin_slug = 'hello-dolly';
			}

			// Remove language files, silently.
			if ( '.' !== $plugin_slug && ! empty( $plugin_translations[ $plugin_slug ] ) ) {
				$translations = $plugin_translations[ $plugin_slug ];

				global $wp_filesystem;
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();

				foreach ( $translations as $translation => $data ) {
					$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.po' );
					$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.mo' );
					$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.l10n.php' );

					$json_translation_files = glob( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '-*.json' );
					if ( $json_translation_files ) {
						array_map( array( $wp_filesystem, 'delete' ), $json_translation_files );
					}
				}
			}

			if ( ! Utils\get_flag_value( $assoc_args, 'skip-delete' ) ) {
				if ( $this->delete_plugin( $plugin ) ) {
					$deleted_plugin_files[] = $plugin->file;
					WP_CLI::log( "Uninstalled and deleted '$plugin->name' plugin." );
				} else {
					$delete_errors[] = $plugin->file;
					WP_CLI::log( "Ran uninstall procedure for '$plugin->name' plugin. Deletion of plugin files failed" );
					++$errors;
					continue;
				}
			} else {
				WP_CLI::log( "Ran uninstall procedure for '$plugin->name' plugin without deleting." );
			}
			++$successes;
		}

		// Remove deleted plugins from the plugin updates list.
		$current = get_site_transient( $this->upgrade_transient );
		if ( $current ) {
			// Don't remove the plugins that weren't deleted.
			$deleted = array_diff( $deleted_plugin_files, $delete_errors );

			foreach ( $deleted as $plugin_file ) {
				unset( $current->response[ $plugin_file ] );
				unset( $current->checked[ $plugin_file ] );
			}

			set_site_transient( $this->upgrade_transient, $current );
		}

		if ( ! $this->chained_command ) {
			Utils\report_batch_operation_results( 'plugin', 'uninstall', count( $args ), $successes, $errors );
		}
	}

	/**
	 * Checks if a given plugin is installed.
	 *
	 * Returns exit code 0 when installed, 1 when uninstalled.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to check.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check whether plugin is installed; exit status 0 if installed, otherwise 1
	 *     $ wp plugin is-installed hello
	 *     $ echo $?
	 *     1
	 *
	 * @subcommand is-installed
	 */
	public function is_installed( $args, $assoc_args = array() ) {
		if ( $this->fetcher->get( $args[0] ) ) {
			WP_CLI::halt( 0 );
		} else {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Checks if a given plugin is active.
	 *
	 * Returns exit code 0 when active, 1 when not active.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to check.
	 *
	 * [--network]
	 * : If set, check if plugin is network-activated.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check whether plugin is Active; exit status 0 if active, otherwise 1
	 *     $ wp plugin is-active hello
	 *     $ echo $?
	 *     1
	 *
	 * @subcommand is-active
	 */
	public function is_active( $args, $assoc_args = array() ) {
		$network_wide = Utils\get_flag_value( $assoc_args, 'network' );

		$plugin = $this->fetcher->get( $args[0] );

		if ( ! $plugin ) {
			WP_CLI::halt( 1 );
		}

		$this->check_active( $plugin->file, $network_wide ) ? WP_CLI::halt( 0 ) : WP_CLI::halt( 1 );
	}

	/**
	 * Deletes plugin files without deactivating or uninstalling.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to delete.
	 *
	 * [--all]
	 * : If set, all plugins will be deleted.
	 *
	 * [--exclude=<name>]
	 * : Comma separated list of plugin slugs to be excluded from deletion.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete plugin
	 *     $ wp plugin delete hello
	 *     Deleted 'hello' plugin.
	 *     Success: Deleted 1 of 1 plugins.
	 *
	 *     # Delete inactive plugins
	 *     $ wp plugin delete $(wp plugin list --status=inactive --field=name)
	 *     Deleted 'tinymce-templates' plugin.
	 *     Success: Deleted 1 of 1 plugins.
	 *
	 *     # Delete all plugins excluding specified ones
	 *     $ wp plugin delete --all --exclude=hello-dolly,jetpack
	 *     Deleted 'akismet' plugin.
	 *     Deleted 'tinymce-templates' plugin.
	 *     Success: Deleted 2 of 2 plugins.
	 */
	public function delete( $args, $assoc_args = array() ) {
		$all         = Utils\get_flag_value( $assoc_args, 'all', false );
		$all_exclude = Utils\get_flag_value( $assoc_args, 'exclude', false );

		// Check if plugin names or --all is passed.
		$args = $this->check_optional_args_and_all( $args, $all, 'delete', $all_exclude );
		if ( ! $args ) {
			return;
		}

		$successes = 0;
		$errors    = 0;

		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
			if ( $this->delete_plugin( $plugin ) ) {
				WP_CLI::log( "Deleted '{$plugin->name}' plugin." );
				++$successes;
			} else {
				WP_CLI::warning( "The '{$plugin->name}' plugin could not be deleted." );
				++$errors;
			}
		}
		if ( ! $this->chained_command ) {
			Utils\report_batch_operation_results( 'plugin', 'delete', count( $args ), $successes, $errors );
		}
	}

	/**
	 * Gets a list of plugins.
	 *
	 * Displays a list of the plugins installed on the site with activation
	 * status, whether or not there's an update available, etc.
	 *
	 * Use `--status=dropin` to list installed dropins (e.g. `object-cache.php`).
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Filter results based on the value of a field.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each plugin.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--status=<status>]
	 * : Filter the output by plugin status.
	 * ---
	 * options:
	 *   - active
	 *   - active-network
	 *   - dropin
	 *   - inactive
	 *   - must-use
	 * ---
	 *
	 * [--skip-update-check]
	 * : If set, the plugin update check will be skipped.
	 *
	 * [--recently-active]
	 * : If set, only recently active plugins will be shown and the status filter will be ignored.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each plugin:
	 *
	 * * name
	 * * status
	 * * update
	 * * version
	 * * update_version
	 * * auto_update
	 *
	 * These fields are optionally available:
	 *
	 * * update_package
	 * * update_id
	 * * title
	 * * description
	 * * file
	 * * author
	 * * tested_up_to
	 * * requires
	 * * requires_php
	 * * wporg_status
	 * * wporg_last_updated
	 *
	 * ## EXAMPLES
	 *
	 *     # List active plugins on the site.
	 *     $ wp plugin list --status=active --format=json
	 *     [{"name":"dynamic-hostname","status":"active","update":"none","version":"0.4.2","update_version":"","auto_update":"off"},{"name":"tinymce-templates","status":"active","update":"none","version":"4.8.1","update_version":"","auto_update":"off"},{"name":"wp-multibyte-patch","status":"active","update":"none","version":"2.9","update_version":"","auto_update":"off"},{"name":"wp-total-hacks","status":"active","update":"none","version":"4.7.2","update_version":"","auto_update":"off"}]
	 *
	 *     # List plugins on each site in a network.
	 *     $ wp site list --field=url | xargs -I % wp plugin list --url=%
	 *     +---------+----------------+-----------+---------+-----------------+------------+
	 *     | name    | status         | update    | version | update_version | auto_update |
	 *     +---------+----------------+-----------+---------+----------------+-------------+
	 *     | akismet | active-network | none      | 5.3.1   |                | on          |
	 *     | hello   | inactive       | available | 1.6     | 1.7.2          | off         |
	 *     +---------+----------------+-----------+---------+----------------+-------------+
	 *     +---------+----------------+-----------+---------+----------------+-------------+
	 *     | name    | status         | update    | version | update_version | auto_update |
	 *     +---------+----------------+-----------+---------+----------------+-------------+
	 *     | akismet | active-network | none      | 5.3.1   |                | on          |
	 *     | hello   | inactive       | available | 1.6     | 1.7.2          | off         |
	 *     +---------+----------------+-----------+---------+----------------+-------------+
	 *
	 *     # Check whether plugins are still active on WordPress.org
	 *     $ wp plugin list --fields=name,wporg_status,wporg_last_updated
	 *     +--------------------+--------------+--------------------+
	 *     | name               | wporg_status | wporg_last_updated |
	 *     +--------------------+--------------+--------------------+
	 *     | akismet            | active       | 2023-12-11         |
	 *     | user-switching     | active       | 2023-11-17         |
	 *     | wordpress-importer | active       | 2023-04-28         |
	 *     | local              |              |                    |
	 *     +--------------------+--------------+--------------------+
	 *
	 *     # List recently active plugins on the site.
	 *     $ wp plugin list --recently-active --field=name --format=json
	 *     ["akismet","bbpress","buddypress"]
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		$fields = Utils\get_flag_value( $assoc_args, 'fields' );
		if ( ! empty( $fields ) ) {
			$fields                            = explode( ',', $fields );
			$this->check_wporg['status']       = in_array( 'wporg_status', $fields, true );
			$this->check_wporg['last_updated'] = in_array( 'wporg_last_updated', $fields, true );

			$this->check_headers['tested_up_to'] = in_array( 'tested_up_to', $fields, true );
		}

		$field = Utils\get_flag_value( $assoc_args, 'field' );
		if ( 'wporg_status' === $field ) {
			$this->check_wporg['status'] = true;
		} elseif ( 'wporg_last_updated' === $field ) {
			$this->check_wporg['last_updated'] = true;
		}

		$this->check_headers['tested_up_to'] = 'tested_up_to' === $field || $this->check_headers['tested_up_to'];

		parent::_list( $_, $assoc_args );
	}

	/* PRIVATES */

	private function check_active( $file, $network_wide ) {
		$required = $network_wide ? 'active-network' : 'active';

		return $required === $this->get_status( $file );
	}

	private function active_output( $name, $file, $network_wide, $action ) {
		$network_wide = $network_wide || ( is_multisite() && is_network_only_plugin( $file ) );

		$check = $this->check_active( $file, $network_wide );

		if ( ( 'activate' === $action ) ? $check : ! $check ) {
			if ( $network_wide ) {
				WP_CLI::log( "Plugin '{$name}' network {$action}d." );
			} else {
				WP_CLI::log( "Plugin '{$name}' {$action}d." );
			}
		} else {
			WP_CLI::warning( "Could not {$action} the '{$name}' plugin." );
		}
	}

	protected function get_status( $file ) {
		if ( is_plugin_active_for_network( $file ) ) {
			return 'active-network';
		}
		if ( is_plugin_active( $file ) ) {
			return 'active';
		}

		return 'inactive';
	}

	/**
	 * Gets the template path based on installation type.
	 */
	private static function get_template_path( $template ) {
		$command_root  = Utils\phar_safe_path( dirname( __DIR__ ) );
		$template_path = "{$command_root}/templates/{$template}";

		if ( ! file_exists( $template_path ) ) {
			WP_CLI::error( "Couldn't find {$template}" );
		}

		return $template_path;
	}

	/**
	 * Gets the details of a plugin.
	 *
	 * @param object
	 * @return array
	 */
	private function get_details( $file ) {
		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( $file ) ) );
		$plugin_file   = Utils\basename( $file );

		return $plugin_folder[ $plugin_file ];
	}

	/**
	 * Performs deletion of plugin files
	 *
	 * @param $plugin - Plugin fetcher object (name, file)
	 * @return bool - If plugin was deleted
	 */
	private function delete_plugin( $plugin ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'delete_plugin', $plugin->file );

		$plugin_dir = dirname( $plugin->file );
		if ( '.' === $plugin_dir ) {
			$plugin_dir = $plugin->file;
		}

		$path = path_join( WP_PLUGIN_DIR, $plugin_dir );

		if ( Utils\is_windows() ) {
			// Handles plugins that are not in own folders
			// e.g. Hello Dolly -> plugins/hello.php
			if ( is_file( $path ) ) {
				$command = 'del /f /q ';
			} else {
				$command = 'rd /s /q ';
			}
			$path = str_replace( '/', '\\', $path );
		} else {
			$command = 'rm -rf ';
		}

		$result = ! WP_CLI::launch( $command . escapeshellarg( $path ), false );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'deleted_plugin', $plugin->file, $result );

		return $result;
	}
}
