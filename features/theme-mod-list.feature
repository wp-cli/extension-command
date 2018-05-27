Feature: Manage WordPress theme mods list

  Scenario: Getting theme mods
    Given a WP install

    When I run `wp theme mod list`
    Then STDOUT should be a table containing rows:
      | key  | value   |
    
    When I run `wp theme mod list --field=key`
    Then STDOUT should be:
      """
      """
    And STDOUT should be empty

    When I run `wp theme mod list --field=value`
    Then STDOUT should be:
      """
      """
    And STDOUT should be empty

    When I run `wp theme mod list --format=json`
    Then STDOUT should be:
      """
      []
      """

    When I run `wp theme mod list --format=csv`
    Then STDOUT should be:
      """
      key,value
      """

    When I run `wp theme mod list --format=yaml`
    Then STDOUT should be:
      """
      ---
      """