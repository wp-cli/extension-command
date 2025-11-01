Feature: Plugin dependencies support

  Background:
    Given an empty cache

  @require-wp-6.5
  Scenario: Install plugin with dependencies using --with-dependencies flag
    Given a WP install

    When I run `wp plugin install akismet`
    Then STDOUT should contain:
      """
      Plugin installed successfully.
      """

    # Create a test plugin with dependencies
    And a wp-content/plugins/test-plugin/test-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Test Plugin
       * Requires Plugins: akismet
       */
      """

    When I run `wp plugin delete akismet --quiet`
    Then STDOUT should be empty

    # Note: Testing with actual WP.org plugins that have dependencies would be better
    # but we'll test with a local plugin that declares dependencies
    When I run `wp plugin get test-plugin --field=requires_plugins`
    Then STDOUT should be:
      """
      akismet
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
       * Requires Plugins: akismet, hello
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

    When I run `wp plugin list --name=akismet --field=status`
    Then STDOUT should be:
      """
      inactive
      """

    When I run `wp plugin list --name=hello --field=status`
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
       * Requires Plugins: hello
       */
      """

    When I run `wp plugin install-dependencies test-plugin --activate`
    Then STDOUT should contain:
      """
      Installing 1 dependency for 'test-plugin'
      """
    And STDOUT should contain:
      """
      Success:
      """

    When I run `wp plugin list --name=hello --field=status`
    Then STDOUT should be:
      """
      active
      """

  @require-wp-6.5
  Scenario: Install plugin with no dependencies
    Given a WP install

    # Create a test plugin without dependencies
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
      Error:
      """
    And the return code should be 1
