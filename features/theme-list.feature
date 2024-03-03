Feature: List WordPress themes

  Background:
    Given a WP install
    And a setup.php file:
      """
      <?php
      $theme_transient = new stdClass;
      $theme_transient->last_checked = time();
      $theme_transient->checked = [];
      $theme_transient->response = [];
      $theme_transient->no_update = [];
      $theme_transient->translations = [];
      $return = update_option( '_site_transient_update_themes', $theme_transient );
      """
    And I run `wp transient delete update_themes --network`

  Scenario: Refresh update_themes transient when listing themes with --force-check flag

    # Listing the themes will populate the transient in the database
    When I run `wp theme list`
    Then STDOUT should not be empty

    When I run `wp transient set update_themes test_value --network`
    And I run `wp theme list --force-check`

    Then STDOUT should be empty
