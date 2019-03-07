Feature: Delete WordPress themes

  Background:
    Given a WP install
    And I run `wp theme install p2`

  Scenario: Delete an installed theme
    When I run `wp theme delete p2`
    Then STDOUT should be:
      """
      Deleted 'p2' theme.
      Success: Deleted 1 of 1 themes.
      """
    And the return code should be 0

  Scenario: Delete an active theme
    When I run `wp theme activate p2`
    Then STDOUT should not be empty

    When I try `wp theme delete p2`
    Then STDERR should be:
    """
    Warning: Can't delete the currently active theme: p2
    Error: No themes deleted.
    """

    When I try `wp theme delete p2 --force`
    Then STDOUT should contain:
    """
    Deleted 'p2' theme.
    """

  Scenario: Attempting to delete a theme that doesn't exist
    When I run `wp theme delete p2`
    Then STDOUT should not be empty

    When I try the previous command again
    Then STDOUT should be:
      """
      Success: Theme already deleted.
      """
    And STDERR should be:
      """
      Warning: The 'p2' theme could not be found.
      """
    And the return code should be 0
