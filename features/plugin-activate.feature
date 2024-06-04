Feature: Activate WordPress plugins

  Background:
    Given a WP install

  Scenario: Activate a plugin that's already installed
    When I run `wp plugin activate akismet`
    Then STDOUT should be:
      """
      Plugin 'akismet' activated.
      Success: Activated 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Attempt to activate a plugin that's not installed
    When I try `wp plugin activate debug-bar`
    Then STDERR should be:
      """
      Warning: The 'debug-bar' plugin could not be found.
      Error: No plugins activated.
      """
    And the return code should be 1

    When I try `wp plugin activate akismet hello debug-bar`
    Then STDERR should be:
      """
      Warning: The 'debug-bar' plugin could not be found.
      Error: Only activated 2 of 3 plugins.
      """
    And STDOUT should be:
      """
      Plugin 'akismet' activated.
      Plugin 'hello' activated.
      """
    And the return code should be 1

  Scenario: Activate all when one plugin is hidden by "all_plugins" filter
    Given I run `wp plugin install site-secrets`
    And a wp-content/mu-plugins/hide-us-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Hide Site Secrets on Production
       * Description: Hides the Site Secrets plugin on production sites
       * Author: WP-CLI tests
       */

       add_filter( 'all_plugins', function( $all_plugins ) {
          unset( $all_plugins['site-secrets/site-secrets.php'] );
          return $all_plugins;
       } );
       """

    When I run `wp plugin activate --all`
    Then STDOUT should contain:
      """
      Plugin 'akismet' activated.
      Plugin 'hello' activated.
      """
    And STDOUT should not contain:
      """
      Plugin 'site-secrets' activated.
      """

  @require-php-7
  Scenario: Activating a plugin with no network wide option passes down correct types
    Given a wp-content/plugins/example-plugin.php file:
      """
      <?php
      // Plugin Name: Example Plugin

      function example_plugin_activate( bool $network_wide = false ) {
        // Doesn't matter what we do here, we just need a function definition to check the type
        return;
      }

      register_activation_hook( __FILE__, 'example_plugin_activate' );
      """

    When I run `wp plugin activate example-plugin`
    Then STDOUT should be:
      """
      Plugin 'example-plugin' activated.
      Success: Activated 1 of 1 plugins.
      """
      And STDERR should be empty

  Scenario: Not giving a slug on activate should throw an error unless --all given
    When I try `wp plugin activate`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Please specify one or more plugins, or use --all.
      """
    And STDOUT should be empty

    # But don't give an error if no plugins and --all given for BC.
    Given I run `wp plugin path`
    And save STDOUT as {PLUGIN_DIR}
    And an empty {PLUGIN_DIR} directory
    When I run `wp plugin activate --all`
    Then STDOUT should be:
      """
      Success: No plugins activated.
      """

  @require-wp-5.2
  Scenario: Activating a plugin that does not meet PHP minimum throws a warning
    Given a wp-content/plugins/high-requirements.php file:
      """
      <?php
      /**
       * Plugin Name: High PHP Requirements
       * Description: This is meant to not activate because PHP version is too low.
       * Author: WP-CLI tests
       * Requires PHP: 99.99
       */
       """
    And I run `wp plugin deactivate --all`
    And I run `wp cli info | grep "PHP version" | awk '{print $3}'`
    And save STDOUT as {PHP_VERSION}

    When I try `wp plugin activate high-requirements`
    Then STDERR should contain:
      """
      Failed to activate plugin. Current PHP version ({PHP_VERSION}) does not meet minimum requirements for High PHP Requirements. The plugin requires PHP 99.99.
      """
    And STDOUT should not contain:
      """
      1 out of 1
      """
    And STDOUT should not contain:
      """
      Success:
      """

  Scenario: Adding --exclude with plugin activate --all should exclude the plugins specified via --exclude
    When I try `wp plugin activate --all --exclude=hello`
    Then STDOUT should be:
      """
      Plugin 'akismet' activated.
      Success: Activated 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Excluding a missing plugin should not throw an error
    Given a WP install
    And I run `wp plugin activate --all --exclude=missing-plugin`
    Then STDERR should be empty
    And STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0
