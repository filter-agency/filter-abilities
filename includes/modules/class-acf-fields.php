<?php

declare(strict_types=1);

/**
 * ACF Fields module.
 *
 * This module does not register its own abilities - instead it enhances
 * the content management abilities by making ACF field data available.
 * It's kept as a separate module so the auto-detection system can report
 * whether ACF support is available.
 *
 * The actual ACF integration happens in class-content-management.php via
 * function_exists('get_fields') checks.
 */
class Filter_Abilities_ACF_Fields extends Filter_Abilities_Module_Base {

	/**
	 * Register abilities (none — ACF support is provided via the content management module).
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// ACF field support is provided via content-management module.
		// This module exists for auto-detection reporting purposes.
	}
}
