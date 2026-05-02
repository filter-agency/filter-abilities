<?php
/**
 * Rebrand the StellarWP Telemetry opt-in and exit-interview modals so all
 * Liquid Web / Nexcess references are replaced with Filter Digital branding.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Filter_Abilities_Telemetry_Modals {

	private const PLUGIN_NAME    = 'Filter Abilities';
	private const FILTER_HOMEPAGE = 'https://filter.agency';

	public static function bootstrap(): void {
		add_filter( 'stellarwp/telemetry/optin_args', [ self::class, 'rebrand_optin' ], 10, 1 );
		add_filter( 'stellarwp/telemetry/exit_interview_args', [ self::class, 'rebrand_exit_interview' ], 10, 1 );
	}

	public static function rebrand_optin( array $args ): array {
		$args['plugin_logo']        = self::logo_url();
		$args['plugin_logo_width']  = 140;
		$args['plugin_logo_height'] = 40;
		$args['plugin_logo_alt']    = 'Filter Digital';
		$args['plugin_name']        = self::PLUGIN_NAME;
		$args['heading']            = sprintf(
			/* translators: %s: plugin name. */
			__( 'Help us improve %s.', 'filter-abilities' ),
			self::PLUGIN_NAME
		);
		$args['intro']              = __(
			'Share anonymous usage data with Filter Digital so we can keep improving the plugins you rely on. We collect your WordPress and PHP versions, locale, and which Filter plugins are active — never post content, user data, or anything personally identifiable.',
			'filter-abilities'
		);
		$args['permissions_url']    = self::FILTER_HOMEPAGE . '/legal/data-collection/';
		$args['tos_url']            = self::FILTER_HOMEPAGE . '/legal/terms/';
		$args['privacy_url']        = self::FILTER_HOMEPAGE . '/legal/privacy/';
		return $args;
	}

	public static function rebrand_exit_interview( array $args ): array {
		$args['plugin_logo']        = self::logo_url();
		$args['plugin_logo_width']  = 140;
		$args['plugin_logo_height'] = 40;
		$args['plugin_logo_alt']    = 'Filter Digital';
		$args['heading']            = __( 'Sorry to see you go.', 'filter-abilities' );
		$args['intro']              = __(
			'If you have a moment, let us know what made you deactivate — it helps us improve Filter Abilities.',
			'filter-abilities'
		);
		return $args;
	}

	private static function logo_url(): string {
		return plugins_url( 'assets/filter-logo-blue.svg', FILTER_ABILITIES_FILE );
	}
}
