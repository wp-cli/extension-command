Feature: Deactivate WordPress plugins

  Background:
    Given a WP install
    And I run `wp plugin activate akismet hello`

  Scenario: Deactivate a plugin that's already activated
    When I run `wp plugin deactivate akismet`
    Then STDOUT should be:
      """
      Plugin 'akismet' deactivated.
      Success: Deactivated 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Attempt to deactivate a plugin that's not installed
    When I try `wp plugin deactivate edit-flow`
    Then STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      Error: No plugins deactivated.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I try `wp plugin deactivate akismet hello edit-flow`
    Then STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      Error: Only deactivated 2 of 3 plugins.
      """
    And STDOUT should be:
      """
      Plugin 'akismet' deactivated.
      Plugin 'hello' deactivated.
      """
    And the return code should be 1

  Scenario: Deactivate all when a previously active plugin is hidden by "all_plugins" filter
    Given a wp-content/mu-plugins/hide-active-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Hide an Active Plugin
       * Description: Hides Akismet plugin, which is already active
       * Author: WP-CLI tests
       */

       add_filter( 'all_plugins', function( $all_plugins ) {
          unset( $all_plugins['akismet/akismet.php'] );
          return $all_plugins;
       } );
       """

    When I run `wp plugin deactivate --all`
    Then STDOUT should not contain:
    """
    Plugin 'akismet' deactivated.
    """

