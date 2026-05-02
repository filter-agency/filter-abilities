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

use Filter\Vendor\StellarWP\Telemetry\Config;
use Filter\Vendor\StellarWP\Telemetry\Core as Telemetry;
use Filter\Vendor\lucatume\DI52\Container;

class Filter_Abilities_Telemetry {

	private const HOOK_PREFIX  = 'filter-abilities';
	private const STELLAR_SLUG = 'filter-abilities';
	private const DEFAULT_URL  = 'https://telemetry.filter.agency/wp-json/filter-telemetry/v1';

	public static function bootstrap(): void {
		if ( ! class_exists( Telemetry::class ) ) {
			return;
		}

		Config::set_container( new Container() );
		Config::set_server_url( self::endpoint_url() );
		Config::set_hook_prefix( self::HOOK_PREFIX );
		Config::set_stellar_slug( self::STELLAR_SLUG );

		Telemetry::instance()->init( FILTER_ABILITIES_FILE );
	}

	private static function endpoint_url(): string {
		return defined( 'FILTER_ABILITIES_TELEMETRY_URL' )
			? rtrim( (string) FILTER_ABILITIES_TELEMETRY_URL, '/' )
			: self::DEFAULT_URL;
	}
}
