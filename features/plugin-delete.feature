Feature: Delete WordPress plugins

  Background:
    Given a WP install

  Scenario: Delete an installed plugin
    When I run `wp plugin delete akismet`
    Then STDOUT should be:
      """
      Deleted 'akismet' plugin.
      Success: Deleted 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Delete all installed plugins
    When I run `wp plugin delete --all`
    Then STDOUT should be:
      """
      Deleted 'akismet' plugin.
      Deleted 'hello' plugin.
      Success: Deleted 2 of 2 plugins.
      """
    And the return code should be 0

    When I run the previous command again
    Then STDOUT should be:
      """
      Success: No plugins deleted.
      """

  Scenario: Attempting to delete a plugin that doesn't exist
    When I try `wp plugin delete debug-bar`
    Then STDOUT should be:
      """
      Success: Plugin already deleted.
      """
    And STDERR should be:
      """
      Warning: The 'debug-bar' plugin could not be found.
      """
    And the return code should be 0

  Scenario: Excluding a plugin from deletion when using --all switch
    When I try `wp plugin delete --all --exclude=akismet,hello`
    Then STDOUT should be:
      """
      Success: No plugins deleted.
      """
    And the return code should be 0

  Scenario: Excluding a missing plugin should not throw an error
    Given a WP install
    And I run `wp plugin delete --all --exclude=missing-plugin`
    Then STDERR should be empty
    And STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

  Scenario: Reports a failure for a plugin that can't be deleted
    Given a WP install

    When I run `chmod -w wp-content/plugins/akismet`
    And I try `wp plugin delete akismet`
    Then STDERR should contain:
      """
      Warning: The 'akismet' plugin could not be deleted.
      """
    And STDERR should contain:
      """
      Error: No plugins deleted.
      """
    And STDOUT should not contain:
      """
      Success:
      """

    When I run `chmod +w wp-content/plugins/akismet`
    And I run `wp plugin delete akismet`
    Then STDERR should not contain:
      """
      Error:
      """
    And STDOUT should contain:
      """
      Success:
      """
