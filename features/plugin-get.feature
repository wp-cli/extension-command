Feature: Get WordPress plugin

  Scenario: Get plugin info
    Given a WP install
    And a wp-content/plugins/foo.php file:
      """
      /**
       * Plugin Name: Sample Plugin
       * Description: Description for sample plugin.
       * Requires at least: 6.0
       * Requires PHP: 5.6
       * Version: 1.0.0
       * Author: John Doe
       * Author URI: https://example.com/
       * License: GPLv2 or later
       * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
       * Text Domain: sample-plugin
       */
       """

    When I run `wp plugin get foo --fields=name,author,version,status`
    Then STDOUT should be a table containing rows:
      | Field   | Value    |
      | name    | foo      |
      | author  | John Doe |
      | version | 1.0.0    |
      | status  | inactive |

    When I run `wp plugin get foo --format=json`
    Then STDOUT should be:
      """
      {"name":"foo","title":"Sample Plugin","author":"John Doe","version":"1.0.0","description":"Description for sample plugin.","status":"inactive"}
      """

  @require-wp-6.5
  Scenario: Get Requires Plugins header of plugin
    Given a WP install
    And a wp-content/plugins/foo.php file:
      """
      <?php
      /**
       * Plugin Name: Foo
       * Description: Foo plugin
       * Author: John Doe
       * Requires Plugins: jetpack, woocommerce
       */
       """

    When I run `wp plugin get foo --field=requires_plugins`
    Then STDOUT should be:
      """
      jetpack, woocommerce
      """

  @require-wp-5.3
  Scenario: Get Requires PHP and Requires WP header of plugin
    Given a WP install
    And a wp-content/plugins/foo.php file:
      """
      <?php
      /**
       * Plugin Name: Foo
       * Description: Foo plugin
       * Author: John Doe
       * Requires at least: 6.2
       * Requires PHP: 7.4
       */
       """

    When I run `wp plugin get foo --fields=requires_wp,requires_php`
    Then STDOUT should be a table containing rows:
      | Field        | Value |
      | requires_wp  | 6.2   |
      | requires_php | 7.4   |
