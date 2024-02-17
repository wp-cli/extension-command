Feature: Install WordPress plugins

  Scenario: Verify that providing a plugin releases/latest GitHub URL will get the latest ZIP
    Given a WP install
    When I run `wp plugin install https://github.com/danielbachhuber/one-time-login/releases/latest`
    Then STDOUT should contain:
      """
      Latest release resolved to Version 0.4.0
      Downloading installation package from
      """
    And STDOUT should contain:
      """
      Plugin installed successfully.
      Success: Installed 1 of 1 plugins.
      """
