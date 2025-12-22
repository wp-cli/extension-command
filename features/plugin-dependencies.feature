Feature: Plugin dependencies support

  Background:
    Given an empty cache

  @less-than-wp-6.5
  Scenario: Install plugin with dependencies using --with-dependencies flag
    Given a WP install

    When I try `wp plugin install --with-dependencies bp-classic`
    Then STDERR should contain:
      """
      Installing plugins with dependencies requires WordPress 6.5 or greater.
      """

  @require-wp-6.5
  Scenario: Install plugin with dependencies using --with-dependencies flag
    Given a WP install

    When I run `wp plugin install --with-dependencies bp-classic`
    Then STDOUT should contain:
      """
      Installing BuddyPress
      """
    And STDOUT should contain:
      """
      Installing BP Classic
      """
    And STDOUT should contain:
      """
      Success: Installed 2 of 2 plugins.
      """

    When I run `wp plugin list --fields=name,status --format=csv`
    Then STDOUT should contain:
      """
      buddypress,inactive
      """
    And STDOUT should contain:
      """
      bp-classic,inactive
      """

  @less-than-wp-6.5
  Scenario: Install dependencies of an installed plugin
    Given a WP install

    When I try `wp plugin install-dependencies akismet`
    Then STDERR should contain:
      """
      Installing plugin dependencies requires WordPress 6.5 or greater.
      """

  @require-wp-6.5
  Scenario: Install dependencies of an installed plugin
    Given a WP install

    # Create a test plugin with dependencies
    And a wp-content/plugins/test-plugin/test-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Test Plugin
       * Requires Plugins: duplicate-post, debug-bar
       */
      """

    When I run `wp plugin install-dependencies test-plugin`
    Then STDOUT should contain:
      """
      Installing 2 dependencies for 'test-plugin'
      """
    And STDOUT should contain:
      """
      Success:
      """

    When I run `wp plugin list --name=duplicate-post --field=status`
    Then STDOUT should be:
      """
      inactive
      """

    When I run `wp plugin list --name=debug-bar --field=status`
    Then STDOUT should be:
      """
      inactive
      """

  @require-wp-6.5
  Scenario: Install dependencies with activation
    Given a WP install

    # Create a test plugin with dependencies
    And a wp-content/plugins/test-plugin/test-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Test Plugin
       * Requires Plugins: akismet, buddypress
       */
      """

    When I try `wp plugin install-dependencies test-plugin --activate`
    Then STDOUT should contain:
      """
      Installing 2 dependencies for 'test-plugin'
      """
    And STDOUT should contain:
      """
      Plugin 'buddypress' activated
      """
    And STDOUT should contain:
      """
      Success: Installed 1 of 2 plugins.
      """
    And STDERR should contain:
      """
      Warning: akismet: Plugin already installed.
      """

    When I run `wp plugin list --fields=name,status --format=csv`
    Then STDOUT should contain:
      """
      buddypress,active
      """
    And STDOUT should contain:
      """
      akismet,active
      """
    # Only the dependencies are activated, not the plugin itself.
    And STDOUT should contain:
      """
      test-plugin,inactive
      """

  @require-wp-6.5
  Scenario: Force install dependencies
    Given a WP install

    # Create a test plugin with dependencies
    And a wp-content/plugins/test-plugin/test-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Test Plugin
       * Requires Plugins: akismet
       */
      """

    When I run `wp plugin install-dependencies test-plugin --force`
    Then STDOUT should contain:
      """
      Installing 1 dependency for 'test-plugin'
      """
    And STDOUT should contain:
      """
      Installing Akismet
      """
    And STDOUT should contain:
      """
      Success: Installed 1 of 1 plugins.
      """
    And STDERR should be empty

  @require-wp-6.5
  Scenario: Install plugin with no dependencies
    Given a WP install

    And a wp-content/plugins/test-plugin/test-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Test Plugin No Deps
       */
      """

    When I run `wp plugin install-dependencies test-plugin`
    Then STDOUT should contain:
      """
      Success: Plugin 'test-plugin' has no dependencies.
      """

  @require-wp-6.5
  Scenario: Error when installing dependencies of non-existent plugin
    Given a WP install

    When I try `wp plugin install-dependencies non-existent-plugin`
    Then STDERR should contain:
      """
      Error: The 'non-existent-plugin' plugin could not be found.
      """
    And the return code should be 1
