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

    When I run `wp plugin list --fields=name,wp_org`
    Then STDOUT should be a table containing rows:
      | name                   | status   | update    | version | wp_org |
      | wordpress-importer     | inactive | available | 0.5     | active |

# todo check
# - wp plugin list --fields=name,wp_org
# - wp plugin list --fields=name,wp_org_updated
# - wp plugin list --fields=name,wp_org,wp_org_updated
# extra challenge the wp_org_updated date will change.
# reference for variable https://github.com/wp-cli/extension-command/blob/main/features/plugin-update.feature#L37
