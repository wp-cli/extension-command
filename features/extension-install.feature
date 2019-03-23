Feature: Manage WordPress extension installation

	Scenario: Installing Extensions theme or plugin
		Given a WP install

		When I run `wp theme install test-ext --activate`
		Then STDOUT should be:
			"""
			Warning: Couldn't find 'test-ext' in the WordPress.org theme directory.
			Warning: The 'test-ext' theme could not be found.
			Error: No themes installed.
			"""

		When I run `wp plugin install test-ext --activate`
		Then STDOUT should be:
			"""
			Warning: Couldn't find 'test-ext' in the WordPress.org plugin directory.
			Warning: The 'test-ext' plugin could not be found.
			Error: No plugins installed.
			"""
