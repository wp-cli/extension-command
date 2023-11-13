Feature: List Recently Active WordPress plugins

  Scenario: Verify plugin installation, activation, deactivation and confirm listing recently active plugins list is correct
    Given a WP install
    When I run `wp plugin install classic-editor buddypress gutenberg --activate`
    Then STDOUT should contain:
      """
      Activating 'classic-editor'...
      """
    And STDOUT should contain:
      """
      Activating 'buddypress'...
      """
    And STDOUT should contain:
      """
      Activating 'gutenberg'...
      """

    When I run `wp plugin activate akismet`
    Then STDOUT should contain:
      """
      Plugin 'akismet' activated.
      """

    When I run `wp plugin deactivate classic-editor buddypress`
    Then STDOUT should contain:
      """
      Plugin 'classic-editor' deactivated.
      Plugin 'buddypress' deactivated.
      Success: Deactivated 2 of 2 plugins.
      """

    When I run `wp plugin list --recently-active --field=name --format=json`
    Then STDOUT should not be empty
    And STDOUT should contain:
      """
      ["classic-editor","buddypress"]
      """

  Scenario: Re confirm recently active list before and after deactivation
    Given a WP install
    When I run `wp plugin install classic-editor buddypress --activate`
    Then STDOUT should contain:
      """
      Activating 'classic-editor'...
      """
    And STDOUT should contain:
      """
      Activating 'buddypress'...
      """

    When I run `wp plugin list --recently-active --field=name --format=json`
    Then STDOUT should not be empty
    And STDOUT should contain:
      """
      []
      """

    When I run `wp plugin deactivate classic-editor buddypress`
    Then STDOUT should contain:
      """
      Plugin 'classic-editor' deactivated.
      Plugin 'buddypress' deactivated.
      Success: Deactivated 2 of 2 plugins.
      """

    When I run `wp plugin list --recently-active --field=name --format=json`
    Then STDOUT should not be empty
    And STDOUT should contain:
      """
      ["classic-editor","buddypress"]
      """

  Scenario: Use recently active plugin to activate plugins
    Given a WP install
    When I run `wp plugin install classic-editor buddypress --activate`
    Then STDOUT should contain:
      """
      Activating 'classic-editor'...
      """
    And STDOUT should contain:
      """
      Activating 'buddypress'...
      """

    When I run `wp plugin deactivate classic-editor buddypress`
    Then STDOUT should contain:
      """
      Plugin 'classic-editor' deactivated.
      Plugin 'buddypress' deactivated.
      Success: Deactivated 2 of 2 plugins.
      """

    When I run `wp plugin activate $(wp plugin list --recently-active --field=name)`
    Then STDOUT should not be empty
    And STDOUT should contain:
      """
      Plugin 'classic-editor' activated.
      Plugin 'buddypress' activated.
      """
