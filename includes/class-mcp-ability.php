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
	 * Hard cap on recursion depth in stdclass_to_array() to prevent a
	 * pathological JSON input from blowing the PHP stack. PHP's json_decode
	 * defaults to a depth of 512 so we set ours just above that.
	 */
	private const MAX_RECURSION_DEPTH = 600;

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
	 * @param mixed $data  The data to convert.
	 * @param int   $depth Internal: current recursion depth.
	 * @return mixed Converted data.
	 */
	private static function stdclass_to_array( $data, int $depth = 0 ) {
		if ( $depth >= self::MAX_RECURSION_DEPTH ) {
			// Stop descending. Returning the raw value keeps the rest of the
			// payload intact at the cost of leaving this branch nested — the
			// ability callback may still reject it, but we won't crash PHP.
			return $data;
		}
		if ( $data instanceof \stdClass ) {
			$data = (array) $data;
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::stdclass_to_array( $value, $depth + 1 );
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = self::stdclass_to_array( $value, $depth + 1 );
			}
		}
		return $data;
	}
}
