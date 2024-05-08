Feature: List WordPress themes

  Scenario: Refresh update_themes transient when listing themes with --force-check flag
    Given a WP install
    And I run `wp theme delete --all --force`
    And I run `wp theme install --force twentytwelve --version=4.2`
    And a update-transient.php file:
      """
      <?php
      $transient = get_site_transient( 'update_themes' );
      $transient->response['twentytwelve'] = (object) array(
        'theme'        => 'twentytwelve',
        'new_version'  => '100.0.0',
        'url'          => 'https://wordpress.org/themes/twentytwelve/',
        'package'      => 'https://downloads.wordpress.org/theme/twentytwelve.100.zip'
      );
      $transient->checked = array(
        'twentytwelve' => '4.2',
      );
      unset( $transient->no_update['twentytwelve'] );
      set_site_transient( 'update_themes', $transient );
      WP_CLI::success( 'Transient updated.' );
      """

    # Populates the initial transient in the database
    When I run `wp theme list --fields=name,update`
    Then STDOUT should be a table containing rows:
      | name              | update |
      | twentytwelve      | none   |

    # Modify the transient in the database to simulate an update
    When I run `wp eval-file update-transient.php`
    Then STDOUT should be:
      """
      Success: Transient updated.
      """

    # Verify the fake transient value produces the expected output
    When I run `wp theme list --fields=name,update`
    Then STDOUT should be a table containing rows:
      | name              | update    |
      | twentytwelve      | available |

    # Repeating the same command again should produce the same results
    When I run the previous command again
    Then STDOUT should be a table containing rows:
      | name              | update    |
      | twentytwelve      | available |

    # Using the --force-check flag should refresh the transient back to the original value
    When I run `wp theme list --fields=name,update --force-check`
    Then STDOUT should be a table containing rows:
      | name              | update |
      | twentytwelve      | none   |

    When I try `wp theme list --skip-update-check --force-check`
    Then STDERR should contain:
      """
      Error: theme updates cannot be both force-checked and skipped. Choose one.
      """
    And STDOUT should be empty
    And the return code should be 1
