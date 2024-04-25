Feature: Delete WordPress themes

  Background:
    Given a WP install
    And I run `wp theme delete --all --force`
    And I run `wp theme install twentytwelve`
    And I run `wp theme install twentyeleven --activate`

  Scenario: Delete an installed theme
    When I run `wp theme delete twentytwelve`
    Then STDOUT should be:
      """
      Deleted 'twentytwelve' theme.
      Success: Deleted 1 of 1 themes.
      """
    And the return code should be 0

  Scenario: Delete an active theme
    When I run `wp theme activate twentytwelve`
    Then STDOUT should not be empty

    When I try `wp theme delete twentytwelve`
    Then STDERR should be:
      """
      Warning: Can't delete the currently active theme: twentytwelve
      Error: No themes deleted.
      """

    When I try `wp theme delete twentytwelve --force`
    Then STDOUT should contain:
      """
      Deleted 'twentytwelve' theme.
      """

  Scenario: Delete all installed themes
    When I run `wp theme list --status=active --field=name --porcelain`
    And save STDOUT as {ACTIVE_THEME}

    When I try `wp theme delete --all`
    Then STDOUT should contain:
      """
      Success: Deleted
      """
    And STDERR should be empty

    When I run `wp theme delete --all --force`
    Then STDOUT should be:
      """
      Deleted '{ACTIVE_THEME}' theme.
      Success: Deleted 1 of 1 themes.
      """

    When I try the previous command again
    Then STDOUT should be:
      """
      Success: No themes deleted.
      """

  Scenario: Delete all installed themes when active theme has a parent
    Given a WP install
    And I run `wp theme install moina-blog --activate`

    When I run `wp theme list --field=name`
    Then STDOUT should contain:
      """
      moina-blog
      moina
      """

    When I try `wp theme delete moina-blog`
    Then STDERR should contain:
      """
      Can't delete the currently active theme
      """
    And STDERR should contain:
      """
      Error: No themes deleted.
      """

    When I try `wp theme delete moina`
    Then STDERR should contain:
      """
      Can't delete the parent of the currently active theme
      """
    And STDERR should contain:
      """
      Error: No themes deleted.
      """

    When I run `wp theme delete --all`
    Then STDOUT should contain:
      """
      Success: Deleted
      """

    When I run `wp theme list --field=name`
    Then STDOUT should contain:
      """
      moina-blog
      moina
      """

    When I run `wp theme delete --all --force`
    Then STDOUT should contain:
      """
      Success: Deleted
      """

    When I run `wp theme list --field=name`
    Then STDOUT should be empty

  Scenario: Attempting to delete a theme that doesn't exist
    When I run `wp theme delete twentytwelve`
    Then STDOUT should not be empty

    When I try the previous command again
    Then STDOUT should be:
      """
      Success: Theme already deleted.
      """
    And STDERR should be:
      """
      Warning: The 'twentytwelve' theme could not be found.
      """
    And the return code should be 0
