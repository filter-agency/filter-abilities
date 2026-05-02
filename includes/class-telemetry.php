<?php
/**
 * Bootstraps the StellarWP telemetry library for Filter Abilities.
 *
 * Uses the Filter-prefixed (Strauss) namespace so multiple Filter plugins can
 * each ship their own copy without class collisions. Server URL defaults to
 * the production receiver and can be overridden via the
 * FILTER_ABILITIES_TELEMETRY_URL constant in wp-config.php for local testing.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Filter\Vendor\StellarWP\ContainerContract\ContainerInterface;
use Filter\Vendor\StellarWP\Telemetry\Config;
use Filter\Vendor\StellarWP\Telemetry\Contracts\Data_Provider;
use Filter\Vendor\StellarWP\Telemetry\Core as Telemetry;
use Filter\Vendor\lucatume\DI52\Container;

/**
 * Adapter so di52's Container satisfies StellarWP's ContainerInterface contract.
 * di52 already provides every required method; we just need the formal interface.
 */
final class Filter_Abilities_Telemetry_Container extends Container implements ContainerInterface {
}

/**
 * Minimal data provider — just the fields we actually use for the receiver UI,
 * instead of the ~60KB WP_Debug_Data dump that StellarWP sends by default.
 */
final class Filter_Abilities_Data_Provider implements Data_Provider {

	public function get_data(): array {
		global $wp_version;

		return [
			'wp_version'     => (string) $wp_version,
			'php_version'    => PHP_VERSION,
			'site_url'       => home_url(),
			'locale'         => get_locale(),
			'multisite'      => is_multisite(),
			'filter_plugins' => self::filter_plugin_versions(),
		];
	}

	/**
	 * @return array<string, string> Map of slug => version for active Filter plugins.
	 */
	private static function filter_plugin_versions(): array {
		$known = [ 'filter-abilities', 'filter-ai', 'personalize-wp' ];

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = (array) get_option( 'active_plugins', [] );
		$all    = get_plugins();
		$out    = [];

		foreach ( $all as $file => $data ) {
			$slug = dirname( $file );
			if ( in_array( $slug, $known, true ) && in_array( $file, $active, true ) ) {
				$out[ $slug ] = (string) ( $data['Version'] ?? '' );
			}
		}

		return $out;
	}
}

class Filter_Abilities_Telemetry {

	private const HOOK_PREFIX  = 'filter-abilities';
	private const STELLAR_SLUG = 'filter-abilities';
	private const DEFAULT_URL  = 'https://telemetry.filter.agency/wp-json/filter-telemetry/v1';

	public static function bootstrap(): void {
		if ( ! class_exists( Telemetry::class ) ) {
			return;
		}

		Config::set_container( new Filter_Abilities_Telemetry_Container() );
		Config::set_server_url( self::endpoint_url() );
		Config::set_hook_prefix( self::HOOK_PREFIX );
		Config::set_stellar_slug( self::STELLAR_SLUG );

		Telemetry::instance()->init( FILTER_ABILITIES_FILE );

		// Replace StellarWP's default Debug_Data provider (~60KB per ping)
		// with our minimal one (~500 bytes).
		Config::get_container()->bind( Data_Provider::class, Filter_Abilities_Data_Provider::class );

		add_action( 'admin_notices', [ self::class, 'maybe_render_optin_modal' ] );
	}

	/**
	 * Fires the StellarWP optin action so the library can render the modal if
	 * its own should_render() check passes (i.e. the site hasn't already opted
	 * in or dismissed it).
	 */
	public static function maybe_render_optin_modal(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		do_action( 'stellarwp/telemetry/optin', self::STELLAR_SLUG );
	}

	private static function endpoint_url(): string {
		return defined( 'FILTER_ABILITIES_TELEMETRY_URL' )
			? rtrim( (string) FILTER_ABILITIES_TELEMETRY_URL, '/' )
			: self::DEFAULT_URL;
	}
}
