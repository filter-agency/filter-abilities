<?php
/**
 * Plugin Name: Filter Abilities
 * Plugin URI: https://github.com/filter-agency/filter-abilities
 * Description: Exposes WordPress functionality as Abilities API abilities for AI agent interaction via MCP. Auto-detects compatible plugins (ACF, Yoast, Gravity Forms, PersonalizeWP, Filter AI, WooCommerce Teams) and registers relevant abilities.
 * Version: 1.2.1
 * Author: Filter Digital
 * Author URI: https://filterdigital.com
 * License: GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: filter-abilities
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FILTER_ABILITIES_VERSION', '1.2.1' );
define( 'FILTER_ABILITIES_PATH', plugin_dir_path( __FILE__ ) );
define( 'FILTER_ABILITIES_FILE', __FILE__ );

// Auto-update from GitHub releases.
if ( file_exists( FILTER_ABILITIES_PATH . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once FILTER_ABILITIES_PATH . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

	$filterAbilitiesUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/filter-agency/filter-abilities/',
		FILTER_ABILITIES_FILE,
		'filter-abilities'
	);
	$filterAbilitiesUpdateChecker->setBranch( 'main' );
	$filterAbilitiesUpdateChecker->getVcsApi()->enableReleaseAssets();
}

add_action( 'plugins_loaded', function () {
	// Abilities API requires WordPress 6.9+.
	if ( ! class_exists( 'WP_Ability' ) ) {
		return;
	}

	require_once FILTER_ABILITIES_PATH . 'includes/class-mcp-ability.php';
	require_once FILTER_ABILITIES_PATH . 'includes/modules/class-module-base.php';
	require_once FILTER_ABILITIES_PATH . 'includes/class-filter-abilities.php';

	Filter_Abilities::instance();
}, 20 );
