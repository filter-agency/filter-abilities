<?php

declare(strict_types=1);

/**
 * Main orchestrator for the Filter Abilities plugin.
 *
 * Handles auto-detection of compatible plugins, module loading,
 * category registration, and enabling core abilities for MCP.
 */
class Filter_Abilities {

	private static ?Filter_Abilities $instance = null;

	/** @var Filter_Abilities_Module_Base[] */
	private array $active_modules = [];

	/** @var array<string, string> Module slug => human-readable label for detected modules */
	private array $detected_modules = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_modules();

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
		add_filter( 'wp_register_ability_args', [ $this, 'enable_core_abilities_mcp' ], 10, 2 );
	}

	/**
	 * Get the module registry with dependency checks.
	 *
	 * @return array<string, array{file: string, class: string, label: string, check: callable|null}>
	 */
	private function get_module_registry(): array {
		return [
			'content-management' => [
				'file'  => 'class-content-management.php',
				'class' => 'Filter_Abilities_Content_Management',
				'label' => 'Content Management',
				'check' => null, // Always active.
			],
			'site-health' => [
				'file'  => 'class-site-health.php',
				'class' => 'Filter_Abilities_Site_Health',
				'label' => 'Site Health',
				'check' => null, // Always active.
			],
			'taxonomy-management' => [
				'file'  => 'class-taxonomy-management.php',
				'class' => 'Filter_Abilities_Taxonomy_Management',
				'label' => 'Taxonomy Management',
				'check' => null, // Always active.
			],
			'media-management' => [
				'file'  => 'class-media-management.php',
				'class' => 'Filter_Abilities_Media_Management',
				'label' => 'Media Management',
				'check' => null, // Always active.
			],
			'acf-fields' => [
				'file'  => 'class-acf-fields.php',
				'class' => 'Filter_Abilities_ACF_Fields',
				'label' => 'ACF Fields',
				'check' => fn() => function_exists( 'get_fields' ),
			],
			'seo-management' => [
				'file'  => 'class-seo-management.php',
				'class' => 'Filter_Abilities_SEO_Management',
				'label' => 'SEO Management (Yoast)',
				'check' => fn() => defined( 'WPSEO_VERSION' ),
			],
			'form-management' => [
				'file'  => 'class-form-management.php',
				'class' => 'Filter_Abilities_Form_Management',
				'label' => 'Form Management (Gravity Forms)',
				'check' => fn() => class_exists( 'GFAPI' ),
			],
			'ai-content' => [
				'file'  => 'class-ai-content.php',
				'class' => 'Filter_Abilities_AI_Content',
				'label' => 'AI Content (Filter AI)',
				'check' => fn() => function_exists( 'filter_ai_get_settings' ),
			],
			'personalization' => [
				'file'  => 'class-personalization.php',
				'class' => 'Filter_Abilities_Personalization',
				'label' => 'Personalization (PersonalizeWP)',
				'check' => fn() => function_exists( 'personalizewp' ),
			],
			'personalization-teams' => [
				'file'  => 'class-personalization-teams.php',
				'class' => 'Filter_Abilities_Personalization_Teams',
				'label' => 'Teams Analytics (PersonalizeWP + WC Teams)',
				'check' => fn() => function_exists( 'personalizewp' )
				                    && function_exists( 'wc_memberships_for_teams_get_team' ),
			],
		];
	}

	/**
	 * Load modules whose dependencies are met.
	 */
	private function load_modules(): void {
		$modules_dir = FILTER_ABILITIES_PATH . 'includes/modules/';

		foreach ( $this->get_module_registry() as $slug => $module ) {
			// Check dependency.
			if ( null !== $module['check'] && ! call_user_func( $module['check'] ) ) {
				continue;
			}

			$file = $modules_dir . $module['file'];
			if ( ! file_exists( $file ) ) {
				continue;
			}

			require_once $file;

			if ( class_exists( $module['class'] ) ) {
				$this->active_modules[ $slug ] = new $module['class']();
				$this->detected_modules[ $slug ] = $module['label'];
			}
		}
	}

	/**
	 * Register categories from all active modules.
	 */
	public function register_categories(): void {
		foreach ( $this->active_modules as $module ) {
			$module->register_categories();
		}
	}

	/**
	 * Register abilities from all active modules.
	 */
	public function register_abilities(): void {
		foreach ( $this->active_modules as $module ) {
			$module->register_abilities();
		}
	}

	/**
	 * Enable core WordPress abilities for MCP access.
	 */
	public function enable_core_abilities_mcp( array $args, string $ability_name ): array {
		$core_abilities = [
			'core/get-site-info',
			'core/get-user-info',
			'core/get-environment-info',
		];

		if ( in_array( $ability_name, $core_abilities, true ) ) {
			if ( ! isset( $args['meta'] ) ) {
				$args['meta'] = [];
			}
			$args['meta']['mcp'] = [ 'public' => true ];
		}

		return $args;
	}

	/**
	 * Get the list of detected/active modules.
	 */
	public function get_detected_modules(): array {
		return $this->detected_modules;
	}
}
