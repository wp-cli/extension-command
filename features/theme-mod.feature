Feature: Manage WordPress theme mods

  Scenario: Getting theme mods
    Given a WP install

    When I run `wp theme mod get --all`
    Then STDOUT should be a table containing rows:
      | key  | value   |

    When I run `wp theme mod get --all --format=csv`
    Then STDOUT should be CSV containing:
      | key  | value   |

    When I run `wp theme mod get --all --format=json`
    Then STDOUT should be:
      """
      []
      """

    When I run `wp theme mod get --all --format=yaml`
    Then STDOUT should be:
      """
      ---
      """

    When I try `wp theme mod get`
    Then STDERR should contain:
      """
      You must specify at least one mod or use --all.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp theme mod set background_color 123456`
    And I run `wp theme mod get --all`
    Then STDOUT should be a table containing rows:
      | key               | value    |
      | background_color  | 123456   |

    When I run `wp theme mod get --all --format=csv`
    Then STDOUT should be CSV containing:
      | key               | value    |
      | background_color  | 123456   |


    When I run `wp theme mod get --all --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"key":"background_color","value":"123456"}]
      """

    When I run `wp theme mod get --all --format=yaml`
    Then STDOUT should be YAML containing:
      """
      ---
      --
        key: background_color
        value: "123456"
      """

    When I run `wp theme mod get background_color --field=value`
    Then STDOUT should be:
      """
      123456
      """

    When I run `wp theme mod get background_color --field=value --format=csv`
    Then STDOUT should be:
      """
      123456
      """

    When I run `wp theme mod get background_color --field=value --format=json`
    Then STDOUT should be:
      """
      ["123456"]
      """

    When I run `wp theme mod get background_color --field=value --format=yaml`
    Then STDOUT should be YAML containing:
      """
      ---
      - "123456"
      """

    When I run `wp theme mod set background_color 123456`
    And I run `wp theme mod get background_color header_textcolor`
    Then STDOUT should be a table containing rows:
      | key               | value    |
      | background_color  | 123456   |
      | header_textcolor  |          |

    When I run `wp theme mod get background_color header_textcolor --format=csv`
    Then STDOUT should be CSV containing:
      | key               | value    |
      | background_color  | 123456   |
      | header_textcolor  |          |

    When I run `wp theme mod get background_color header_textcolor --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"key":"background_color","value":"123456"},{"key":"header_textcolor","value":null}]
      """

    When I run `wp theme mod get background_color header_textcolor --format=yaml`
    Then STDOUT should be YAML containing:
      """
      ---
      --
        key: background_color
        value: "123456"
      --
        key: header_textcolor
        value: null
      """

  Scenario: Setting theme mods
    Given a WP install

    When I run `wp theme mod set background_color 123456`
    Then STDOUT should be:
      """
      Success: Theme mod background_color set to 123456.
      """

  Scenario: Removing theme mods
    Given a WP install

    When I run `wp theme mod remove --all`
    Then STDOUT should be:
      """
      Success: Theme mods removed.
      """

    When I try `wp theme mod remove`
    Then STDERR should contain:
      """
      You must specify at least one mod or use --all.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp theme mod remove background_color`
    Then STDOUT should be:
      """
      Success: 1 mod removed.
      """

    When I run `wp theme mod remove background_color header_textcolor`
    Then STDOUT should be:
      """
      Success: 2 mods removed.
      """
