<?php

namespace WP_CLI;

/**
 * Theme upgrader with package validation.
 *
 * This extends WordPress core's Theme_Upgrader to add validation
 * of downloaded packages before installation.
 */
class ValidatingThemeUpgrader extends \Theme_Upgrader {
	use UpgraderWithValidation;
}
