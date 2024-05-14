Feature: List WordPress plugins

  Scenario: Refresh update_plugins transient when listing plugins with --force-check flag
    Given a WP install
    And I run `wp plugin uninstall --all`
    And I run `wp plugin install hello-dolly`
    And a update-transient.php file:
      """
      <?php
      $transient = get_site_transient( 'update_plugins' );
      $transient->response['hello-dolly/hello.php'] = (object) array(
        'id'          => 'w.org/plugins/hello-dolly',
        'slug'        => 'hello-dolly',
        'plugin'      => 'hello-dolly/hello.php',
        'new_version' => '100.0.0',
        'url'         => 'https://wordpress.org/plugins/hello-dolly/',
        'package'     => 'https://downloads.wordpress.org/plugin/hello-dolly.100.0.0.zip',
      );
      $transient->checked = array(
        'hello-dolly/hello.php' => '1.7.2',
      );
      unset( $transient->no_update['hello-dolly/hello.php'] );
      set_site_transient( 'update_plugins', $transient );
      WP_CLI::success( 'Transient updated.' );
      """

    # Populates the initial transient in the database
    When I run `wp plugin list --fields=name,status,update`
    Then STDOUT should be a table containing rows:
      | name         | status   | update |
      | hello-dolly  | inactive | none   |

    # Modify the transient in the database to simulate an update
    When I run `wp eval-file update-transient.php`
    Then STDOUT should be:
      """
      Success: Transient updated.
      """

    # Verify the fake transient value produces the expected output
    When I run `wp plugin list --fields=name,status,update`
    Then STDOUT should be a table containing rows:
      | name         | status   | update    |
      | hello-dolly  | inactive | available |

    # Repeating the same command again should produce the same results
    When I run the previous command again
    Then STDOUT should be a table containing rows:
      | name         | status   | update    |
      | hello-dolly  | inactive | available |

    # Using the --force-check flag should refresh the transient back to the original value
    When I run `wp plugin list --fields=name,status,update --force-check`
    Then STDOUT should be a table containing rows:
      | name         | status   | update |
      | hello-dolly  | inactive | none   |

    When I try `wp plugin list --skip-update-check --force-check`
    Then STDERR should contain:
      """
      Error: plugin updates cannot be both force-checked and skipped. Choose one.
      """
    And STDOUT should be empty
    And the return code should be 1
