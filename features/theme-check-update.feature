Feature: Check for theme updates

  Scenario: Check for theme updates with no updates available
    Given a WP install

    When I run `wp theme install twentytwelve --force`
    Then STDOUT should not be empty

    When I run `wp theme check-update --all`
    Then STDOUT should contain:
      """
      Success: All themes are up to date.
      """

    When I run `wp theme check-update twentytwelve`
    Then STDOUT should contain:
      """
      Success: All themes are up to date.
      """

  Scenario: Check for theme updates should throw an error unless --all given
    Given a WP install

    When I try `wp theme check-update`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Please specify one or more themes, or use --all.
      """
    And STDOUT should be empty

  Scenario: Check for specific theme updates
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0 --force`
    Then STDOUT should not be empty

    When I run `wp theme install twentytwelve --force`
    Then STDOUT should not be empty

    When I run `wp theme check-update twentyfourteen --format=csv`
    Then STDOUT should contain:
      """
      twentyfourteen,inactive,1.0,
      """

  Scenario: Check for all theme updates with --all flag
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0 --force`
    Then STDOUT should not be empty

    When I run `wp theme check-update --all --format=csv`
    Then STDOUT should contain:
      """
      twentyfourteen,inactive,1.0,
      """

  Scenario: Check for theme updates in different output formats
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0 --force`
    Then STDOUT should not be empty

    When I run `wp theme check-update twentyfourteen --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"name":"twentyfourteen","status":"inactive","version":"1.0"}]
      """

    When I run `wp theme check-update twentyfourteen --format=csv`
    Then STDOUT should contain:
      """
      name,status,version,update_version
      """
    And STDOUT should contain:
      """
      twentyfourteen,inactive,1.0
      """

  Scenario: Check for theme updates with custom fields
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0 --force`
    Then STDOUT should not be empty

    When I run `wp theme check-update twentyfourteen --fields=name,version`
    Then STDOUT should be a table containing rows:
      | name           | version |
      | twentyfourteen | 1.0     |

  Scenario: Check for invalid theme should error
    Given a WP install

    When I try `wp theme check-update invalid-theme-name`
    Then STDERR should contain:
      """
      Warning: The 'invalid-theme-name' theme could not be found.
      """
