<?php

declare(strict_types=1);

/**
 * Custom WP_Ability subclass that handles stdClass-to-array conversion.
 *
 * The MCP adapter passes JSON-decoded stdClass objects as ability input,
 * but WordPress REST validation (rest_validate_value_from_schema) requires
 * PHP arrays for JSON Schema type 'object'. This class overrides execute()
 * to convert stdClass input before validation runs.
 */
class Filter_Abilities_MCP_Ability extends WP_Ability {

	/**
	 * Execute the ability, converting stdClass input to arrays first.
	 *
	 * @param mixed $input The input data.
	 * @return mixed|WP_Error The result or error.
	 */
	public function execute( $input = null ) {
		$input = self::stdclass_to_array( $input );
		return parent::execute( $input );
	}

	/**
	 * Recursively convert stdClass objects to associative arrays.
	 *
	 * @param mixed $data The data to convert.
	 * @return mixed Converted data.
	 */
	private static function stdclass_to_array( $data ) {
		if ( $data instanceof \stdClass ) {
			$data = (array) $data;
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::stdclass_to_array( $value );
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::stdclass_to_array( $value );
			}
		}
		return $data;
	}
}
