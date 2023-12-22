Feature: Check the status of plugins on WordPress.org

  @require-wp-5.2
  Scenario: Install plugins and check the status on wp.org.
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

    When I run `wp plugin list --name=wordpress-importer --field=wporg_last_updated`
    Then STDOUT should not be empty
    And save STDOUT as {COMMIT_DATE}

    When I run `wp plugin list --fields=name,wporg_status`
    Then STDOUT should be a table containing rows:
      | name                   | wporg_status    |
      | wordpress-importer     | active          |
      | no-longer-in-directory | closed          |
      | never-wporg            |                 |

    When I run `wp plugin list --fields=name,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name                   | wporg_last_updated |
      | wordpress-importer     | {COMMIT_DATE}      |
      | no-longer-in-directory | 2017-11-13         |
      | never-wporg            |                    |

    When I run `wp plugin list --fields=name,wporg_status,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name                   | wporg_status    | wporg_last_updated |
      | wordpress-importer     | active          | {COMMIT_DATE}      |
      | no-longer-in-directory | closed          | 2017-11-13         |
      | never-wporg            |                 |                    |
