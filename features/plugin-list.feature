Feature: List WordPress plugins

  Background:
    Given a WP install
    And I run `wp plugin uninstall --all`
    And I run `wp plugin install hello-dolly`
    And a setup.php file:
      """
      <?php
      $plugin_transient = get_site_transient('update_plugins') ;
      # manually set the version
      $fake_version = '10.0.0';
      $plugin_transient->no_update["hello-dolly/hello.php"]->new_version = $fake_version;
      $return = set_site_transient( 'update_plugins', $plugin_transient );
      """
#    And a wp-content/mu-plugins/test-plugin-update.php file:
#      """
#      <?php
#      /**
#       * Plugin Name: Test Plugin Update
#       * Description: Fakes installed plugin's data to verify plugin version mismatch
#       * Author: WP-CLI tests
#       */
#
#      add_filter( 'site_transient_update_plugins', function( $value ) {
#          if ( ! is_object( $value ) ) {
#              return $value;
#          }
#          $fake_version = '10.0.0';
#
#          unset( $value->response['hello-dolly/hello.php'] );
#          $value->no_update['hello-dolly/hello.php']->new_version = $fake_version;
#
#          return $value;
#      } );
#      ?>
#      """

  Scenario: Refresh update_plugins transient when listing plugins with --force-check flag

    # Listing the plugins will populate the transient in the database
    When I run `wp plugin list`
    Then STDOUT should not be empty
    And save STDOUT as {PLUGIN_LIST_ORIGINAL_VERSIONS}

    # Write a test value
    When I run `wp eval-file setup.php`
    Then STDOUT should be empty

    # Get value of plugin transient
    #When I run `wp option get _site_transient_update_plugins`
    #Then STDOUT should be empty

    # Run list again
    When I run `wp plugin list`
    Then STDOUT should be empty
    And save STDOUT as {PLUGIN_LIST_FAKE_VERSIONS}

    # TODO: compare {PLUGIN_LIST_ORIGINAL_VERSIONS} to {PLUGIN_LIST_FAKE_VERSIONS}
    # expected result: they should be different

    # Get value of plugin transient
    When I run `wp option get _site_transient_update_plugins`
    Then STDOUT should be empty

    When I run `wp plugin list --force-check`
    Then STDOUT should not be empty
    And save STDOUT as {PLUGIN_LIST_FORCE_CHECK_VERSIONS}

    # TODO: compare {PLUGIN_LIST_ORIGINAL_VERSIONS} to {PLUGIN_LIST_FORCE_CHECK_VERSIONS}
    # expected result: they should be the same
