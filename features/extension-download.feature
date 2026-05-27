Feature: Download WordPress.org extensions without loading WordPress

  Scenario: Downloading a plugin package works before WordPress is loaded
    Given a WP install

    When I run `wp plugin download debug-bar --skip-wordpress`
    Then STDOUT should contain:
      """
      Downloading debug-bar
      """
    And STDOUT should contain:
      """
      Success: Downloaded plugin package to
      """
    And save STDOUT 'Success: Downloaded plugin package to (.+)' as {DOWNLOADED_PLUGIN}
    And the {DOWNLOADED_PLUGIN} file should exist
    And STDERR should be empty

  Scenario: Downloading a theme package works before WordPress is loaded
    Given a WP install

    When I run `wp theme download twentytwelve --skip-wordpress`
    Then STDOUT should contain:
      """
      Downloading twentytwelve
      """
    And STDOUT should contain:
      """
      Success: Downloaded theme package to
      """
    And save STDOUT 'Success: Downloaded theme package to (.+)' as {DOWNLOADED_THEME}
    And the {DOWNLOADED_THEME} file should exist
    And STDERR should be empty
