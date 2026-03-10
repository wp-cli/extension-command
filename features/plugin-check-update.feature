Feature: Check for plugin updates

  @require-wp-5.2
  Scenario: Check for plugin updates with no updates available
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should not be empty

    When I run `wp plugin check-update --all`
    Then STDOUT should contain:
      """
      Success: All plugins are up to date.
      """

    When I run `wp plugin check-update wordpress-importer`
    Then STDOUT should contain:
      """
      Success: All plugins are up to date.
      """

  Scenario: Check for plugin updates should throw an error unless --all given
    Given a WP install

    When I try `wp plugin check-update`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Please specify one or more plugins, or use --all.
      """
    And STDOUT should be empty

  @require-wp-5.2
  Scenario: Check for specific plugin updates
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5`
    Then STDOUT should not be empty

    When I run `wp plugin check-update wordpress-importer --format=csv`
    Then STDOUT should contain:
      """
      wordpress-importer,inactive,0.5,
      """

  @require-wp-5.2
  Scenario: Check for all plugin updates with --all flag
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5 --activate`
    Then STDOUT should not be empty

    When I run `wp plugin check-update --all --format=csv`
    Then STDOUT should contain:
      """
      wordpress-importer,active,0.5,
      """

  @require-wp-5.2
  Scenario: Check for plugin updates in different output formats
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5`
    Then STDOUT should not be empty

    When I run `wp plugin check-update wordpress-importer --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"name":"wordpress-importer","status":"inactive","version":"0.5"}]
      """

    When I run `wp plugin check-update wordpress-importer --format=csv`
    Then STDOUT should contain:
      """
      name,status,version,update_version
      """
    And STDOUT should contain:
      """
      wordpress-importer,inactive,0.5
      """

  @require-wp-5.2
  Scenario: Check for plugin updates with custom fields
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5`
    Then STDOUT should not be empty

    When I run `wp plugin check-update wordpress-importer --fields=name,version`
    Then STDOUT should be a table containing rows:
      | name               | version |
      | wordpress-importer | 0.5     |

  Scenario: Check for invalid plugin should error
    Given a WP install

    When I try `wp plugin check-update invalid-plugin-name`
    Then STDERR should contain:
      """
      Warning: The 'invalid-plugin-name' plugin could not be found.
      """
