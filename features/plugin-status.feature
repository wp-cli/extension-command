Feature: List the status of plugins

  @require-wp-4.0
  Scenario: Status should include drop-ins
    Given a WP install
    And a wp-content/db-error.php file:
      """
      <?php
      """

    When I try `wp plugin status`
    Then STDOUT should contain:
      """
      D db-error.php
      """
    And STDOUT should contain:
      """
      D = Drop-In
      """

  Scenario: Plugin status command is deprecated
    Given a WP install

    When I try `wp plugin status`
    Then STDERR should contain:
      """
      Warning: The `plugin status` command is deprecated. Use `wp plugin list` or `wp plugin get <plugin>` instead.
      """
    And the return code should be 0
