Feature: Check for theme updates

  Scenario: Check for theme updates with no updates available
    Given a WP install

    When I run `wp theme install twentytwelve`
    Then STDOUT should not be empty

    When I run `wp theme check-update`
    Then STDOUT should contain:
      """
      Success: All themes are up to date.
      """
    And the return code should be 0

  Scenario: Check for theme updates with updates available
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme check-update`
    Then STDOUT should be a table containing rows:
      | name           | status   | version |
      | twentyfourteen | inactive | 1.0     |
    And STDOUT should contain:
      """
      update_version
      """
    And the return code should be 0

  Scenario: Check for specific theme updates
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme install twentytwelve`
    Then STDOUT should not be empty

    When I run `wp theme check-update twentyfourteen`
    Then STDOUT should be a table containing rows:
      | name           | status   | version |
      | twentyfourteen | inactive | 1.0     |
    And STDOUT should contain:
      """
      update_version
      """
    And the return code should be 0

  Scenario: Check for all theme updates with --all flag
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme check-update --all`
    Then STDOUT should be a table containing rows:
      | name           | status   | version |
      | twentyfourteen | inactive | 1.0     |
    And STDOUT should contain:
      """
      update_version
      """
    And the return code should be 0

  Scenario: Check for theme updates in different output formats
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme check-update --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"name":"twentyfourteen","status":"inactive","version":"1.0"}]
      """
    And the return code should be 0

    When I run `wp theme check-update --format=csv`
    Then STDOUT should contain:
      """
      name,status,version,update_version
      """
    And STDOUT should contain:
      """
      twentyfourteen,inactive,1.0
      """
    And the return code should be 0

  Scenario: Check for theme updates with custom fields
    Given a WP install

    When I run `wp theme install twentyfourteen --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme check-update --fields=name,version`
    Then STDOUT should be a table containing rows:
      | name           | version |
      | twentyfourteen | 1.0     |
    And the return code should be 0

  Scenario: Check for theme updates when no specific theme has updates
    Given a WP install

    When I run `wp theme install twentytwelve`
    Then STDOUT should not be empty

    When I run `wp theme check-update twentytwelve`
    Then STDOUT should contain:
      """
      Success: All themes are up to date.
      """
    And the return code should be 0
