Feature: Install WordPress themes

  Background:
    Given a WP install
    And I run `wp theme delete --all --force`

  Scenario: Return code is 1 when one or more theme installations fail
    When I try `wp theme install twentytwelve twentytwelve-not-a-theme`
    Then STDERR should contain:
      """
      Warning:
      """
    And STDERR should contain:
      """
      twentytwelve-not-a-theme
      """
    And STDERR should contain:
      """
      Error: Only installed 1 of 2 themes.
      """
    And STDOUT should contain:
      """
      Installing Twenty Twelve
      """
    And STDOUT should contain:
      """
      Theme installed successfully.
      """
    And the return code should be 1

    When I try `wp theme install twentytwelve`
    Then STDOUT should be:
      """
      Success: Theme already installed.
      """
    And STDERR should be:
      """
      Warning: twentytwelve: Theme already installed.
      """
    And the return code should be 0

    When I try `wp theme install twentytwelve-not-a-theme`
    Then STDERR should contain:
      """
      Warning:
      """
    And STDERR should contain:
      """
      twentytwelve-not-a-theme
      """
    And STDERR should contain:
      """
      Error: No themes installed.
      """
    And STDOUT should be empty
    And the return code should be 1

  Scenario: Ensure automatic parent theme installation uses http cacher
    Given a WP install
    And an empty cache

    When I run `wp theme install moina`
    Then STDOUT should contain:
      """
      Success: Installed 1 of 1 themes.
      """
    And STDOUT should not contain:
      """
      Using cached file
      """

    When I run `wp theme uninstall moina`
    Then STDOUT should contain:
      """
      Success: Deleted 1 of 1 themes.
      """

    When I run `wp theme install moina-blog`
    Then STDOUT should contain:
      """
      Success: Installed 1 of 1 themes.
      """
    And STDOUT should contain:
      """
      This theme requires a parent theme.
      """
    And STDOUT should contain:
      """
      Using cached file
      """

  Scenario: Verify installed theme activation
    When I run `wp theme install twentytwelve`
    Then STDOUT should not be empty

    When I try `wp theme install twentytwelve --activate`
    Then STDERR should contain:
    """
    Warning: twentytwelve: Theme already installed.
    """

    And STDOUT should contain:
    """
    Activating 'twentytwelve'...
    Success: Switched to 'Twenty Twelve' theme.
    Success: Theme already installed.
    """

  Scenario: Installation of multiple themes with activate
    When I try `wp theme install twentytwelve twentyeleven --activate`
    Then STDERR should contain:
      """
      Warning: Only this single theme will be activated: twentyeleven
      """

    When I run `wp theme list --field=name`
    Then STDOUT should contain:
      """
      twentyeleven
      twentytwelve
      """

    When I run `wp theme list --field=name --status=active`
    Then STDOUT should contain:
      """
      twentyeleven
      """
