Feature: Download WordPress.org extensions without loading WordPress

  Scenario: Downloading a plugin package works before WordPress is loaded
    Given an empty directory

    When I run `wp plugin download debug-bar`
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

  Scenario: Downloading a plugin package to a custom path
    Given an empty directory

    When I run `wp plugin download debug-bar --target-path=/tmp/wp-cli-download-test-plugin`
    Then STDOUT should contain:
      """
      Success: Downloaded plugin package to
      """
    And save STDOUT 'Success: Downloaded plugin package to (.+)' as {DOWNLOADED_PLUGIN}
    And the {DOWNLOADED_PLUGIN} file should exist
    And STDERR should be empty

  Scenario: Downloading a specific version of a plugin
    Given an empty directory

    When I run `wp plugin download debug-bar --version=1.4`
    Then STDOUT should contain:
      """
      Downloading debug-bar (1.4)
      """
    And STDOUT should contain:
      """
      Success: Downloaded plugin package to
      """
    And STDERR should be empty

  Scenario: Downloading a non-existent version of a plugin fails with clear error
    Given an empty directory

    When I try `wp plugin download debug-bar --version=9.9.9`
    Then STDERR should contain:
      """
      Error: Can't find the requested plugin's version 9.9.9
      """
    And the return code should be 1

  Scenario: Downloading a plugin with --force overwrites existing file
    Given an empty directory

    When I run `wp plugin download debug-bar`
    And I run `wp plugin download debug-bar --force`
    Then STDOUT should contain:
      """
      Success: Downloaded plugin package to
      """
    And STDERR should be empty

  Scenario: Downloading a plugin without --force fails if destination exists
    Given an empty directory

    When I run `wp plugin download debug-bar`
    And I try `wp plugin download debug-bar`
    Then STDERR should contain:
      """
      Error: Destination file already exists:
      """
    And the return code should be 1

  Scenario: Downloading an unknown plugin fails with a clear error
    Given an empty directory

    When I try `wp plugin download this-plugin-does-not-exist-xyz-abc-123`
    Then STDERR should contain:
      """
      Error: The 'this-plugin-does-not-exist-xyz-abc-123' plugin could not be found.
      """
    And the return code should be 1

  Scenario: Downloading a theme package works before WordPress is loaded
    Given an empty directory

    When I run `wp theme download twentytwelve`
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

  Scenario: Downloading a theme package to a custom path
    Given an empty directory

    When I run `wp theme download twentytwelve --target-path=/tmp/wp-cli-download-test-theme`
    Then STDOUT should contain:
      """
      Success: Downloaded theme package to
      """
    And save STDOUT 'Success: Downloaded theme package to (.+)' as {DOWNLOADED_THEME}
    And the {DOWNLOADED_THEME} file should exist
    And STDERR should be empty

  Scenario: Downloading a specific version of a theme
    Given an empty directory

    When I run `wp theme download twentytwelve --version=1.3`
    Then STDOUT should contain:
      """
      Downloading twentytwelve (1.3)
      """
    And STDOUT should contain:
      """
      Success: Downloaded theme package to
      """
    And STDERR should be empty

  Scenario: Downloading a non-existent version of a theme fails with clear error
    Given an empty directory

    When I try `wp theme download twentytwelve --version=9.9.9`
    Then STDERR should contain:
      """
      Error: Can't find the requested theme's version 9.9.9
      """
    And the return code should be 1

  Scenario: Downloading a theme with --force overwrites existing file
    Given an empty directory

    When I run `wp theme download twentytwelve`
    And I run `wp theme download twentytwelve --force`
    Then STDOUT should contain:
      """
      Success: Downloaded theme package to
      """
    And STDERR should be empty

  Scenario: Downloading a theme without --force fails if destination exists
    Given an empty directory

    When I run `wp theme download twentytwelve`
    And I try `wp theme download twentytwelve`
    Then STDERR should contain:
      """
      Error: Destination file already exists:
      """
    And the return code should be 1

  Scenario: Downloading an unknown theme fails with a clear error
    Given an empty directory

    When I try `wp theme download this-theme-does-not-exist-xyz-abc-123`
    Then STDERR should contain:
      """
      Error: The 'this-theme-does-not-exist-xyz-abc-123' theme could not be found.
      """
    And the return code should be 1
