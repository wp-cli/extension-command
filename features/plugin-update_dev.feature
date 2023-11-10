Feature: Update WordPress plugins

  @require-wp-5.2
  Scenario: development.
    Given a WP install

    When I run `wp plugin install wordpress-importer --version=0.5 --force`
    And I run `wp plugin install https://downloads.wordpress.org/plugin/no-longer-in-directory.1.0.62.zip`
    And a wp-content/plugins/never-wporg/never-wporg.php file:
      """
      <?php
      /**
       * Plugin Name: This plugin was never in the WordPress.org plugin directory
       * Version:     2.0.2
       */
       """

    When I run `wp plugin list --name=wordpress-importer --field=wp_org_updated`
    Then STDOUT should not be empty
    And save STDOUT as {COMMIT_DATE}

    When I run `wp plugin list --fields=name,wp_org`
    Then STDOUT should be a table containing rows:
      | name                   | wp_org    |
      | wordpress-importer     | active    |
      | no-longer-in-directory | closed    |
      | never-wporg            | no_wp_org |

    When I run `wp plugin list --fields=name,wp_org_updated`
    Then STDOUT should be a table containing rows:
      | name                   | wp_org_updated |
      | wordpress-importer     | {COMMIT_DATE}  |
      | no-longer-in-directory | 2017-11-13     |
      | never-wporg            | -              |

    When I run `wp plugin list --fields=name,wp_org,wp_org_updated`
    Then STDOUT should be a table containing rows:
      | name                   | wp_org    | wp_org_updated |
      | wordpress-importer     | active    | {COMMIT_DATE}  |
      | no-longer-in-directory | closed    | 2017-11-13     |
      | never-wporg            | no_wp_org | -              |


# todo check
# - wp plugin list --fields=name,wp_org
# - wp plugin list --fields=name,wp_org_updated
# - wp plugin list --fields=name,wp_org,wp_org_updated
# extra challenge the wp_org_updated date will change.
# reference for variable https://github.com/wp-cli/extension-command/blob/main/features/plugin-update.feature#L37
