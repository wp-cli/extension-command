Feature: Update WordPress plugins

  Scenario: Updating plugin with invalid version shouldn't remove the old version
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5 --force`
    Then STDOUT should not be empty

    When I run `wp plugin list --name=wordpress-importer --field=update_version`
    Then STDOUT should not be empty
    And save STDOUT as {UPDATE_VERSION}

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name               | status   | update    | version |
      | wordpress-importer | inactive | available | 0.5     |

    When I try `wp plugin update akismet --version=0.5.3`
    Then STDERR should be:
      """
      Error: Can't find the requested plugin's version 0.5.3 in the WordPress.org plugin repository (HTTP code 404).
      """

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name               | status   | update    | version |
      | wordpress-importer | inactive | available | 0.5     |

    When I run `wp plugin update wordpress-importer`
    Then STDOUT should not be empty

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name               | status   | update    | version           |
      | wordpress-importer | inactive | none      | {UPDATE_VERSION}  |

  Scenario: Error when both --minor and --patch are provided
    Given a WP install

    When I try `wp plugin update --patch --minor --all`
    Then STDERR should be:
      """
      Error: --minor and --patch cannot be used together.
      """

  Scenario: Exclude plugin updates from bulk updates.
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5 --force`
    Then STDOUT should contain:
      """"
      Downloading install
      """"
    And STDOUT should contain:
      """"
      package from https://downloads.wordpress.org/plugin/wordpress-importer.0.5.zip...
      """"

    When I run `wp plugin status wordpress-importer`
    Then STDOUT should contain:
      """"
      Update available
      """"

    When I run `wp plugin update --all --exclude=wordpress-importer | grep 'Skipped'`
    Then STDOUT should contain:
      """
      wordpress-importer
      """

    When I run `wp plugin status wordpress-importer`
    Then STDOUT should contain:
      """"
      Update available
      """"

  Scenario: Update a plugin to its latest patch release
    Given a WP install
    And I run `wp plugin install --force wordpress-importer --version=0.5`

    When I run `wp plugin update wordpress-importer --patch`
    Then STDOUT should contain:
      """
      Success: Updated 1 of 1 plugins.
      """

    When I run `wp plugin get wordpress-importer --field=version`
    Then STDOUT should be:
      """
      0.5.2
      """

  @require-wp-4.0
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
