Feature: Show the status of auto-updates for WordPress plugins

  Background:
    Given a WP install
    And I run `wp plugin install duplicate-post https://github.com/wp-cli/sample-plugin/archive/refs/heads/master.zip --ignore-requirements`

  @require-wp-5.5
  Scenario: Show an error if required params are missing
    When I try `wp plugin auto-updates status`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      Error: Please specify one or more plugins, or use --all.
      """

  @require-wp-5.5
  Scenario: Show the status of auto-updates of a single plugin
    When I run `wp plugin auto-updates status sample-plugin`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | sample-plugin          | disabled |
    And the return code should be 0

  @require-wp-5.5
  Scenario: Show the status of auto-updates multiple plugins
    When I run `wp plugin auto-updates status duplicate-post sample-plugin`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | duplicate-post | disabled |
      | sample-plugin          | disabled |
    And the return code should be 0

  @require-wp-5.5
  Scenario: Show the status of auto-updates all installed plugins
    When I run `wp plugin auto-updates status --all`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | akismet        | disabled |
      | duplicate-post | disabled |
      | sample-plugin          | disabled |
    And the return code should be 0

    When I run `wp plugin auto-updates enable --all`
    And I run `wp plugin auto-updates status --all`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | akismet        | enabled  |
      | duplicate-post | enabled  |
      | sample-plugin          | enabled  |
    And the return code should be 0

  @require-wp-5.5
  Scenario: The status can be filtered to only show enabled or disabled plugins
    Given I run `wp plugin auto-updates enable sample-plugin`

    When I run `wp plugin auto-updates status --all`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | akismet        | disabled |
      | duplicate-post | disabled |
      | sample-plugin          | enabled  |
    And the return code should be 0

    When I run `wp plugin auto-updates status --all --enabled-only`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | sample-plugin          | enabled  |
    And the return code should be 0

    When I run `wp plugin auto-updates status --all --disabled-only`
    Then STDOUT should be a table containing rows:
      | name           | status   |
      | akismet        | disabled |
      | duplicate-post | disabled |
    And the return code should be 0

    When I try `wp plugin auto-updates status --all --enabled-only --disabled-only`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      Error: --enabled-only and --disabled-only are mutually exclusive and cannot be used at the same time.
      """

  @require-wp-5.5
  Scenario: The fields can be shown individually
    Given I run `wp plugin auto-updates enable sample-plugin`

    When I run `wp plugin auto-updates status --all --disabled-only --field=name`
    Then STDOUT should contain:
      """
      akismet
      """
    And STDOUT should contain:
      """
      duplicate-post
      """

    When I run `wp plugin auto-updates status sample-plugin --field=status`
    Then STDOUT should be:
      """
      enabled
      """

  @require-wp-5.5
  Scenario: Formatting options work
    When I run `wp plugin auto-updates status --all --format=json`
    Then STDOUT should contain:
      """
      {"name":"akismet","status":"disabled"}
      """
    And STDOUT should contain:
      """
      {"name":"sample-plugin","status":"disabled"}
      """
    And STDOUT should contain:
      """
      {"name":"duplicate-post","status":"disabled"}
      """

    When I run `wp plugin auto-updates status --all --format=csv`
    Then STDOUT should contain:
      """
      akismet,disabled
      """
    And STDOUT should contain:
      """
      sample-plugin,disabled
      """
    And STDOUT should contain:
      """
      duplicate-post,disabled
      """

  @require-wp-5.5
  Scenario: Handle malformed option value
    When I run `wp option update auto_update_plugins ""`
    And I try `wp plugin auto-updates status sample-plugin`
    Then the return code should be 0
    And STDERR should be empty
