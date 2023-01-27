Feature: Update WordPress themes

  Scenario: Updating a theme with no version in the WordPress.org directory shouldn't delete the original theme
    Given a WP install

    When I run `wp scaffold underscores wpclitesttheme`
    Then STDOUT should contain:
      """
      Success: Created theme
      """
    And the wp-content/themes/wpclitesttheme directory should exist

    When I try `wp theme update wpclitesttheme --version=100.0.0`
    Then STDERR should contain:
      """
      Error: No themes installed
      """
    And the wp-content/themes/wpclitesttheme directory should exist
