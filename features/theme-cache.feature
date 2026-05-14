Feature: Manage theme cache

  Background:
    Given a WP installation

  Scenario: Clear cache for a single theme
    When I run `wp theme install twentytwenty --force --activate`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp theme cache clear twentytwenty`
    Then STDOUT should be:
      """
      Success: Cleared cache for 'twentytwenty' theme.
      """

  Scenario: Clear cache for multiple themes
    When I run `wp theme install twentytwentyone --force`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp theme install twentytwenty --force `
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp theme cache clear twentytwentyone twentytwenty`
    Then STDOUT should be:
      """
      Success: Cleared cache for 2 themes.
      """

  Scenario: Clear cache for all themes
    When I run `wp theme install twentytwentyone --force`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp theme cache clear --all`
    Then STDOUT should contain:
      """
      Success: Cleared cache for
      """
    And STDOUT should contain:
      """
      themes.
      """

  Scenario: Clear cache for non-existent theme
    When I try `wp theme cache clear nonexistent`
    Then STDERR should contain:
      """
      Warning: Theme 'nonexistent' not found.
      """
    And the return code should be 1

  Scenario: Clear cache with no arguments
    When I try `wp theme cache clear`
    Then STDERR should be:
      """
      Error: Please specify one or more themes, or use --all.
      """
    And the return code should be 1

  Scenario: Flush the entire theme cache group
    When I run `wp theme cache flush`
    Then STDOUT should be:
      """
      Success: The theme cache was flushed.
      """
