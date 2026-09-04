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
    And that HTTP requests to https://plugins.trac.wordpress.org/log/never-wporg/?limit=1&mode=stop_on_copy&format=rss will respond with:
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

  @require-wp-5.2
  Scenario: The wp.org last updated date for an active plugin does not depend on a second, rate-limited request
    Given a WP install
    And I run `wp plugin install wordpress-importer --version=0.5 --force`
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
    # plugins.trac.wordpress.org is known to rate-limit this scrape (HTTP 429), with no
    # pubDate in the response body. wporg_last_updated must still resolve correctly for an
    # active plugin, because the date is meant to come from the plugin-info API response
    # above, not from a second request to trac.
    And that HTTP requests to https://plugins.trac.wordpress.org/log/wordpress-importer/?limit=1&mode=stop_on_copy&format=rss will respond with:
      """
      HTTP/1.1 429
      Content-Type: text/html

      <html><body>429 Too Many Requests</body></html>
      """

    When I run `wp plugin list --fields=name,wporg_status,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name               | wporg_status | wporg_last_updated |
      | wordpress-importer | active       | 2025-09-26         |

  @require-wp-5.2
  Scenario: The wp.org last updated date falls back to the trac log when the plugin-info API omits it
    Given a WP install
    And I run `wp plugin install wordpress-importer --version=0.5 --force`
    And that HTTP requests to https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=en_US&request%5Bslug%5D=wordpress-importer will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      {
        "name": "WordPress Importer",
        "slug": "wordpress-importer"
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

    When I run `wp plugin list --fields=name,wporg_status,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name               | wporg_status | wporg_last_updated |
      | wordpress-importer | active       | 2025-09-26         |

  @less-than-wp-5.3
  Scenario: The wp.org last updated date is still rendered on WordPress < 5.3
    Given a WP install
    And I run `wp option update timezone_string Asia/Tokyo`
    And a wp-content/plugins/wporg-dated/wporg-dated.php file:
      """
      <?php
      /**
       * Plugin Name: Plugin with a WordPress.org release date
       * Version:     1.0.0
       */
      """
    And that HTTP requests to https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=en_US&request%5Bslug%5D=wporg-dated will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      {
        "name": "Plugin with a WordPress.org release date",
        "slug": "wporg-dated",
        "last_updated": "2025-09-26 9:07pm GMT"
      }
      """
    And that HTTP requests to https://plugins.trac.wordpress.org/log/wporg-dated/?limit=1&mode=stop_on_copy&format=rss will respond with:
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

    # wp_date() only exists since WordPress 5.3, so this goes through the
    # get_date_from_gmt() fallback. The pubDate above is 21:07 UTC, which is already
    # the next day in Asia/Tokyo, so this also pins that the fallback renders in the
    # site timezone rather than in UTC.
    When I run `wp plugin list --fields=name,wporg_last_updated`
    Then STDOUT should be a table containing rows:
      | name        | wporg_last_updated |
      | wporg-dated | 2025-09-27         |
