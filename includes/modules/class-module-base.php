<?php

declare(strict_types=1);

/**
 * Abstract base class for all ability modules.
 *
 * Each module represents a group of related abilities that may depend on
 * specific plugins being active. The orchestrator checks dependencies
 * before instantiating modules.
 */
abstract class Filter_Abilities_Module_Base {

	/**
	 * Register all abilities for this module.
	 * Called during the wp_abilities_api_init action.
	 */
	abstract public function register_abilities(): void;

	/**
	 * Register ability categories for this module.
	 * Called during the wp_abilities_api_categories_init action.
	 */
	public function register_categories(): void {
		// Override in subclasses if categories need to be registered.
	}

	/**
	 * Register an ability with MCP meta flag automatically set.
	 */
	protected function register_ability( string $name, array $args ): void {
		if ( ! isset( $args['meta'] ) ) {
			$args['meta'] = [];
		}
		$args['meta']['mcp'] = [ 'public' => true ];

		// Ensure input schemas have defaults for proper MCP/REST validation.
		if ( isset( $args['input_schema'] ) ) {
			if ( ! array_key_exists( 'default', $args['input_schema'] ) ) {
				$args['input_schema']['default'] = [];
			}
			if ( ! isset( $args['input_schema']['additionalProperties'] ) ) {
				$args['input_schema']['additionalProperties'] = false;
			}
		}

		// Use custom ability class that converts stdClass input to arrays.
		// The MCP adapter passes JSON-decoded stdClass objects, but WordPress
		// REST validation requires PHP arrays for type 'object'.
		$args['ability_class'] = 'Filter_Abilities_MCP_Ability';

		wp_register_ability( $name, $args );
	}

	/**
	 * Validate a date string is in YYYY-MM-DD format.
	 */
	protected function is_valid_date( string $date ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
	}

	/**
	 * Register an ability category.
	 */
	protected function register_category( string $slug, string $label, string $description ): void {
		wp_register_ability_category( $slug, [
			'label'       => $label,
			'description' => $description,
		] );
	}
}
