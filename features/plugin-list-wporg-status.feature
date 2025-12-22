Feature: Check the status of plugins on WordPress.org

  @require-wp-5.2
  Scenario: Install plugins and check the status on wp.org.
    Given a WP install
    And I run `wp plugin install wordpress-importer --version=0.5 --force`
    And I run `wp plugin install https://downloads.wordpress.org/plugin/no-longer-in-directory.1.0.62.zip`
    And a wp-content/plugins/never-wporg/never-wporg.php file:
      """
      <?php
      /**
       * Plugin Name: This plugin was never in the WordPress.org plugin directory
       * Version:     2.0.2
       */
      """
    And that HTTP requests to https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=en_US&request%5Bslug%5D=wordpress-importer will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      {
        "name": "WordPress Importer",
        "slug": "wordpress-importer",
        "last_updated": "2025-09-26 9:07pm GMT"
      }
      """
    And that HTTP requests to https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=en_US&request%5Bslug%5D=no-longer-in-directory will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      {
        error: "closed",
        name: "No Longer in Directory",
        slug: "no-longer-in-directory",
        description: "This plugin has been closed as of October 2, 2018 and is not available for download. This closure is permanent. Reason: Guideline Violation.",
        closed: true,
        closed_date: "2018-10-02",
        reason: "guideline-violation",
        reason_text: "Guideline Violation"
      }
      """
    And that HTTP requests to https://plugins.trac.wordpress.org/log/wordpress-importer/?limit=1&mode=stop_on_copy&format=rss will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/rss+xml;charset=utf-8

      <?xml version="1.0"?>
        <rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">
          <channel>
            <item>
              <pubDate>Fri, 26 Sep 2025 21:07:26 GMT</pubDate>
            </item>
        </channel>
        </rss>
      """
    And that HTTP requests to https://plugins.trac.wordpress.org/log/no-longer-in-directory/?limit=1&mode=stop_on_copy&format=rss will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/rss+xml;charset=utf-8

      <?xml version="1.0"?>
        <rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">
          <channel>
            <item>
              <pubDate>Mon, 13 Nov 2017 20:51:35 GMT</pubDate>
            </item>
        </channel>
        </rss>
      """

    And that HTTP requests to https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=en_US&request%5Bslug%5D=never-wporg will respond with:
      """
      HTTP/1.1 404
      Content-Type: application/json

      {
        "error": "not_found"
      }
      """
    And that HTTP requests to https://plugins.trac.wordpress.org/log/never-wporg?limit=1&mode=stop_on_copy&format=rss will respond with:
      """
      HTTP/1.1 404
      Content-Type: application/rss+xml;charset=utf-8

      <?xml version="1.0"?>
        <rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">
          <channel>
          </channel>
        </rss>
      """

    When I run `wp plugin list --fields=name,wporg_status`
    Then STDOUT should be a table containing rows:
      | name                   | wporg_status    |
      | wordpress-importer     | active          |
      | no-longer-in-directory | closed          |
      | never-wporg            |                 |

    When I run `wp plugin list --fields=name,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name                   | wporg_last_updated |
      | wordpress-importer     | 2025-09-26      |
      | no-longer-in-directory | 2017-11-13         |
      | never-wporg            |                    |

    When I run `wp plugin list --fields=name,wporg_status,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name                   | wporg_status    | wporg_last_updated |
      | wordpress-importer     | active          | 2025-09-26         |
      | no-longer-in-directory | closed          | 2017-11-13         |
      | never-wporg            |                 |                    |
