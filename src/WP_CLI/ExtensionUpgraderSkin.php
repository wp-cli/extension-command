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
