Feature: Update WordPress themes

  Scenario: Update the private theme
    Given a WP installation
    And I run `wp theme uninstall --all --force`
    And I run `wp theme install https://github.com/getshifter/shifter-github-hosting-theme-sample/releases/download/0.0.1/shifter-github-hosting-theme-sample.zip --activate`
    And a wp-content/mu-plugins/test-private-theme-update.php file:
      """
      <?php
      /**
       * Plugin Name: Test Private Theme Update
       * Description: Fakes installed theme's data to update private theme
       * Author: WP-CLI tests
       */

      add_filter( 'site_transient_update_themes', function( $value ) {
          if ( ! is_object( $value ) ) {
              return $value;
          }

          $value->response = array();
          if ( '0.0.1' === wp_get_theme()->get( 'Version' ) ) {
            $theme_name = wp_get_theme()->get_stylesheet();
            $value->response[ $theme_name ] = array();
            $value->response[ $theme_name ]['theme'] = $theme_name;
            $value->response[ $theme_name ]['new_version'] = '1.0.0';
            $value->response[ $theme_name ]['package'] = 'https://github.com/getshifter/shifter-github-hosting-theme-sample/releases/download/1.0.0/shifter-github-hosting-theme-sample.zip';
          }
          return $value;

      } );
      ?>
      """

    When I run `wp theme list --field=name`
    And save STDOUT as {THEME_NAME}

    When I run `wp theme list --name={THEME_NAME}  --field=version`
    And save STDOUT as {THEME_VERSION}

    When I run `wp theme list`
    Then STDOUT should be a table containing rows:
      | name         | status | update    | version         |
      | {THEME_NAME} | active | available | {THEME_VERSION} |

    When I try `wp theme update {THEME_NAME}`
    Then STDOUT should contain:
    """
    Theme updated successfully.
    """

    When I run `wp theme list --field=name`
    And save STDOUT as {THEME_NAME}

    When I run `wp theme list`
    Then STDOUT should be a table containing rows:
      | name         | status | update | version |
      | {THEME_NAME} | active | none   | 1.0.0   |
