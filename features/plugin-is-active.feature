Feature: Check if a WordPress plugin is active

  Background:
    Given a WP install

  Scenario: Check if an active plugin is active
    When I run `wp plugin activate akismet`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp plugin is-active akismet`
    Then the return code should be 0

  Scenario: Check if an inactive plugin is not active
    When I run `wp plugin activate akismet`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp plugin deactivate akismet`
    Then STDOUT should contain:
      """
      Success:
      """

    When I try `wp plugin is-active akismet`
    Then the return code should be 1

  Scenario: Check if a non-existent plugin is not active
    When I try `wp plugin is-active non-existent-plugin`
    Then the return code should be 1

  Scenario: Warn when plugin is in active_plugins but file does not exist
    When I run `wp plugin activate akismet`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp plugin is-active akismet`
    Then the return code should be 0

    # Remove the plugin directory
    When I run `wp plugin path akismet --dir`
    Then save STDOUT as {PLUGIN_PATH}

    When I run `rm -rf {PLUGIN_PATH}`
    Then the return code should be 0

    # Now the plugin file is gone but still in active_plugins
    When I try `wp plugin is-active akismet`
    Then STDERR should contain:
      """
      Warning: Plugin 'akismet' is in the active_plugins option but the plugin file does not exist.
      """
    And the return code should be 1
