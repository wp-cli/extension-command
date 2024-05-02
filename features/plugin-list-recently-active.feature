Feature: List recently active WordPress plugins

  Scenario: Verify plugin installation, activation, deactivation and confirm listing recently active plugins list is correct
    Given a WP install

    When I run `wp plugin install site-secrets debug-bar p2-by-email --activate`
    Then STDOUT should contain:
      """
      Plugin 'site-secrets' activated.
      """
    And STDOUT should contain:
      """
      Plugin 'debug-bar' activated.
      """
    And STDOUT should contain:
      """
      Plugin 'p2-by-email' activated.
      """

    When I run `wp plugin list --recently-active --field=name --format=json`
    Then STDOUT should be:
      """
      []
      """

    When I run `wp plugin activate akismet`
    Then STDOUT should contain:
      """
      Plugin 'akismet' activated.
      """

    When I run `wp plugin deactivate site-secrets debug-bar`
    Then STDOUT should contain:
      """
      Plugin 'site-secrets' deactivated.
      Plugin 'debug-bar' deactivated.
      Success: Deactivated 2 of 2 plugins.
      """

    When I run `wp plugin list --recently-active --field=name`
    Then STDOUT should be a table containing rows:
      | debug-bar    |
      | site-secrets |

  Scenario: Use recently active plugin to activate plugins
    Given a WP install

    When I run `wp plugin install site-secrets debug-bar --activate`
    Then STDOUT should contain:
      """
      Plugin 'site-secrets' activated.
      """
    And STDOUT should contain:
      """
      Plugin 'debug-bar' activated.
      """

    When I run `wp plugin deactivate site-secrets debug-bar`
    Then STDOUT should be:
      """
      Plugin 'site-secrets' deactivated.
      Plugin 'debug-bar' deactivated.
      Success: Deactivated 2 of 2 plugins.
      """

    When I run `wp plugin activate $(wp plugin list --recently-active --field=name)`
    Then STDOUT should contain:
      """
      Plugin 'debug-bar' activated.
      """
    And STDOUT should contain:
      """
      Plugin 'site-secrets' activated.
      """

  Scenario: For a MU site, verify plugin installation, activation, deactivation and confirm listing recently active plugins list is correct
    Given a WP multisite install

    When I run `wp plugin install site-secrets debug-bar p2-by-email --activate-network`
    Then STDOUT should contain:
      """
      Plugin 'site-secrets' network activated.
      """
    And STDOUT should contain:
      """
      Plugin 'debug-bar' network activated.
      """
    And STDOUT should contain:
      """
      Plugin 'p2-by-email' network activated.
      """

    When I run `wp plugin activate akismet --network`
    Then STDOUT should contain:
      """
      Plugin 'akismet' network activated.
      """

    When I run `wp plugin list --recently-active --field=name --format=json`
    Then STDOUT should be:
      """
      []
      """
    When I run `wp plugin deactivate site-secrets debug-bar --network`
    Then STDOUT should be:
      """
      Plugin 'site-secrets' network deactivated.
      Plugin 'debug-bar' network deactivated.
      Success: Network deactivated 2 of 2 plugins.
      """

    When I run `wp plugin list --recently-active --field=name`
    Then STDOUT should be a table containing rows:
      | debug-bar    |
      | site-secrets |

  Scenario: For a MU site, use recently active plugin to activate plugins
    Given a WP multisite install

    When I run `wp plugin install site-secrets debug-bar --activate-network`
    Then STDOUT should contain:
      """
      Plugin 'site-secrets' network activated.
      """
    And STDOUT should contain:
      """
      Plugin 'debug-bar' network activated.
      """

    When I run `wp plugin deactivate site-secrets debug-bar --network`
    Then STDOUT should be:
      """
      Plugin 'site-secrets' network deactivated.
      Plugin 'debug-bar' network deactivated.
      Success: Network deactivated 2 of 2 plugins.
      """

    When I run `wp plugin activate $(wp plugin list --recently-active --field=name) --network`
    Then STDOUT should contain:
      """
      Plugin 'site-secrets' network activated.
      """
    And STDOUT should contain:
      """
      Plugin 'debug-bar' network activated.
      """
