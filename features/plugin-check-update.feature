Feature: Check for plugin updates

  @require-wp-5.2
  Scenario: Check for plugin updates with no updates available
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should not be empty

    When I run `wp plugin check-update`
    Then STDOUT should contain:
      """
      Success: All plugins are up to date.
      """
    And the return code should be 0

  @require-wp-5.2
  Scenario: Check for plugin updates with updates available
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5 --activate`
    Then STDOUT should not be empty

    When I run `wp plugin check-update`
    Then STDOUT should be a table containing rows:
      | name               | status | version |
      | wordpress-importer | active | 0.5     |
    And STDOUT should contain:
      """
      update_version
      """
    And the return code should be 0

  @require-wp-5.2
  Scenario: Check for specific plugin updates
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5`
    Then STDOUT should not be empty

    When I run `wp plugin install akismet`
    Then STDOUT should not be empty

    When I run `wp plugin check-update wordpress-importer`
    Then STDOUT should be a table containing rows:
      | name               | status   | version |
      | wordpress-importer | inactive | 0.5     |
    And STDOUT should contain:
      """
      update_version
      """
    And the return code should be 0

  @require-wp-5.2
  Scenario: Check for all plugin updates with --all flag
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5 --activate`
    Then STDOUT should not be empty

    When I run `wp plugin check-update --all`
    Then STDOUT should be a table containing rows:
      | name               | status | version |
      | wordpress-importer | active | 0.5     |
    And STDOUT should contain:
      """
      update_version
      """
    And the return code should be 0

  @require-wp-5.2
  Scenario: Check for plugin updates in different output formats
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5`
    Then STDOUT should not be empty

    When I run `wp plugin check-update --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"name":"wordpress-importer","status":"inactive","version":"0.5"}]
      """
    And the return code should be 0

    When I run `wp plugin check-update --format=csv`
    Then STDOUT should contain:
      """
      name,status,version,update_version
      """
    And STDOUT should contain:
      """
      wordpress-importer,inactive,0.5
      """
    And the return code should be 0

  @require-wp-5.2
  Scenario: Check for plugin updates with custom fields
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5`
    Then STDOUT should not be empty

    When I run `wp plugin check-update --fields=name,version`
    Then STDOUT should be a table containing rows:
      | name               | version |
      | wordpress-importer | 0.5     |
    And the return code should be 0

  @require-wp-5.2
  Scenario: Check for plugin updates when no specific plugin has updates
    Given a WP install

    When I run `wp plugin install wordpress-importer`
    Then STDOUT should not be empty

    When I run `wp plugin check-update wordpress-importer`
    Then STDOUT should contain:
      """
      Success: All plugins are up to date.
      """
    And the return code should be 0
