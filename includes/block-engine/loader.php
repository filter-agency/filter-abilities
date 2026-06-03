<?php
/**
 * Loader for the vendored GravityKit Block API engine.
 *
 * Registers a PSR-4-style autoloader for the `GravityKit\BlockAPI` namespace
 * pointing at this directory, loads the read-side block enrichers, and clears
 * the block-inventory cache when the vendored engine version changes.
 *
 * The class files in this directory are vendored VERBATIM from GravityKit
 * Block MCP (https://github.com/GravityKit/block-mcp), GPL-2.0-or-later.
 * See NOTICE.md. Do NOT hand-edit them — run bin/sync-block-engine.sh to
 * pull a newer upstream tag.
 *
 * @package Filter_Abilities
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'FILTER_ABILITIES_BLOCK_ENGINE_LOADED' ) ) {
	return;
}
define( 'FILTER_ABILITIES_BLOCK_ENGINE_LOADED', true );

/**
 * Pinned upstream GravityKit Block API version vendored in this directory.
 * Keep in sync with bin/sync-block-engine.sh and the BLOCK_ENGINE_VERSION file.
 */
define( 'FILTER_ABILITIES_BLOCK_ENGINE_VERSION', '1.8.1' );

/**
 * PSR-4-style autoloader for the vendored `GravityKit\BlockAPI` namespace.
 *
 * Mirrors the upstream autoloader (so the vendored files need no edits) but
 * points the base directory here instead of the GK plugin. Maps:
 *   `GravityKit\BlockAPI\Some_Class`               -> class-some-class.php
 *   `GravityKit\BlockAPI\Block_Enrichers\Core_Foo` -> block-enrichers/class-core-foo.php
 */
spl_autoload_register(
	static function ( $class_name ): void {
		$prefix = 'GravityKit\\BlockAPI\\';
		$len    = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, $len );
		$parts    = explode( '\\', $relative );
		$basename = array_pop( $parts );

		$sub_path = '';
		if ( ! empty( $parts ) ) {
			$sub_path = strtolower( str_replace( '_', '-', implode( '/', $parts ) ) ) . '/';
		}

		$file = __DIR__ . '/' . $sub_path . 'class-' . strtolower( str_replace( '_', '-', $basename ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Load read-side enrichers — each file self-registers on `gk_block_api_format_block`.
foreach ( (array) glob( __DIR__ . '/block-enrichers/*.php' ) as $enricher_file ) {
	require_once $enricher_file;
}

// Self-heal: drop the block-inventory cache when the vendored engine version
// changes, so new schema logic never reads a payload written by an older one.
$filter_abilities_block_engine_stored = get_option( 'filter_abilities_block_engine_version', '' );
if ( FILTER_ABILITIES_BLOCK_ENGINE_VERSION !== $filter_abilities_block_engine_stored ) {
	delete_transient( \GravityKit\BlockAPI\Block_Inventory::CACHE_KEY );
	update_option( 'filter_abilities_block_engine_version', FILTER_ABILITIES_BLOCK_ENGINE_VERSION, false );
}
