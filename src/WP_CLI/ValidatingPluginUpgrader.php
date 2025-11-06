<?php

namespace WP_CLI;

/**
 * Plugin upgrader with package validation.
 *
 * This extends WordPress core's Plugin_Upgrader to add validation
 * of downloaded packages before installation.
 */
class ValidatingPluginUpgrader extends \Plugin_Upgrader {
	use UpgraderWithValidation;
}
