Feature: Update WordPress plugins

  Scenario: Updating plugin with invalid version shouldn't remove the old version
    Given a WP install

    When I run `wp plugin install akismet --version=2.5.6 --force`
    Then STDOUT should not be empty

    When I run `wp plugin list --name=akismet --field=update_version`
    Then STDOUT should not be empty
    And save STDOUT as {UPDATE_VERSION}

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version   |
      | akismet    | inactive | available | 2.5.6     |

    When I try `wp plugin update akismet --version=2.9.0`
    Then STDERR should be:
      """
      Error: Can't find the requested plugin's version 2.9.0 in the WordPress.org plugin repository (HTTP code 404).
      """

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version   |
      | akismet    | inactive | available | 2.5.6     |

    When I run `wp plugin update akismet`
    Then STDOUT should not be empty

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version           |
      | akismet    | inactive | none      | {UPDATE_VERSION}  |

  Scenario: Error when both --minor and --patch are provided
    Given a WP install

    When I try `wp plugin update --patch --minor --all`
    Then STDERR should be:
      """
      Error: --minor and --patch cannot be used together.
      """

  Scenario: Exclude plugin updates from bulk updates.
    Given a WP install

    When I run `wp plugin install akismet --version=3.0.0 --force`    
    Then STDOUT should contain:
      """"
      Downloading install package from https://downloads.wordpress.org/plugin/akismet.3.0.0.zip...
      """"

    When I run `wp plugin status akismet`
    Then STDOUT should contain:
      """"
      Update available
      """"

    When I run `wp plugin update --all --exclude=akismet | grep 'Skipped'`
    Then STDOUT should contain:
      """
      akismet
      """

    When I run `wp plugin status akismet`
    Then STDOUT should contain:
      """"
      Update available
      """"

  Scenario: Update a plugin to its latest patch release
    Given a WP install
    And I run `wp plugin install --force akismet --version=2.5.4`

    When I run `wp plugin update akismet --patch`
    Then STDOUT should contain:
      """
      Success: Updated 1 of 1 plugins.
      """

    When I run `wp plugin get akismet --field=version`
    Then STDOUT should be:
      """
      2.5.10
      """

  Scenario: Update a plugin to its latest minor release
    Given a WP install
    And I run `wp plugin install --force akismet --version=2.5.4`

    When I run `wp plugin update akismet --minor`
    Then STDOUT should contain:
      """
      Success: Updated 1 of 1 plugins.
      """

    When I run `wp plugin get akismet --field=version`
    Then STDOUT should be:
      """
      2.6.1
      """
