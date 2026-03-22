<?php

declare(strict_types=1);

class Filter_Abilities_Site_Health extends Filter_Abilities_Module_Base {

	/**
	 * Register the site health ability category.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-site',
			__( 'Site Health', 'filter-abilities' ),
			__( 'Abilities for site information and content statistics.', 'filter-abilities' )
		);
	}

	/**
	 * Register site-info and content-stats abilities.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		$this->register_ability( 'filter/site-info', [
			'label'               => __( 'Site Info', 'filter-abilities' ),
			'description'         => __( 'Returns site URL, WordPress version, active theme, active plugins, registered post types, taxonomies, and detected Filter Abilities modules.', 'filter-abilities' ),
			'category'            => 'filter-site',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'site_name'         => [ 'type' => 'string' ],
					'site_url'          => [ 'type' => 'string' ],
					'wordpress_version' => [ 'type' => 'string' ],
					'php_version'       => [ 'type' => 'string' ],
					'active_theme'      => [
						'type'       => 'object',
						'properties' => [
							'name'    => [ 'type' => 'string' ],
							'version' => [ 'type' => 'string' ],
						],
					],
					'active_plugins'    => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'post_types'        => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'name'  => [ 'type' => 'string' ],
								'label' => [ 'type' => 'string' ],
							],
						],
					],
					'taxonomies'        => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'name'  => [ 'type' => 'string' ],
								'label' => [ 'type' => 'string' ],
							],
						],
					],
					'detected_modules'  => [
						'type'       => 'object',
						'additionalProperties' => [ 'type' => 'string' ],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_site_info' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

		$this->register_ability( 'filter/content-stats', [
			'label'               => __( 'Content Stats', 'filter-abilities' ),
			'description'         => __( 'Returns post counts by type and status, total media count, and total user count.', 'filter-abilities' ),
			'category'            => 'filter-site',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'post_types' => [
						'type'       => 'object',
						'additionalProperties' => [
							'type'       => 'object',
							'properties' => [
								'publish' => [ 'type' => 'integer' ],
								'draft'   => [ 'type' => 'integer' ],
								'pending' => [ 'type' => 'integer' ],
								'private' => [ 'type' => 'integer' ],
								'total'   => [ 'type' => 'integer' ],
							],
						],
					],
					'media_count' => [ 'type' => 'integer' ],
					'user_count'  => [ 'type' => 'integer' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_content_stats' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );
	}

	/**
	 * Return site information including theme, plugins, post types, and taxonomies.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Site information data.
	 */
	public function execute_site_info(): array {
		$theme = wp_get_theme();

		$active_plugins = [];
		foreach ( get_option( 'active_plugins', [] ) as $plugin_path ) {
			$plugin_data      = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
			$active_plugins[] = $plugin_data['Name'];
		}

		$post_types = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) {
			$post_types[] = [
				'name'  => $pt->name,
				'label' => $pt->label,
			];
		}

		$taxonomies = [];
		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
			$taxonomies[] = [
				'name'  => $tax->name,
				'label' => $tax->label,
			];
		}

		return [
			'site_name'         => get_bloginfo( 'name' ),
			'site_url'          => get_bloginfo( 'url' ),
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'active_theme'      => [
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			],
			'active_plugins'    => $active_plugins,
			'post_types'        => $post_types,
			'taxonomies'        => $taxonomies,
			'detected_modules'  => Filter_Abilities::instance()->get_detected_modules(),
		];
	}

	/**
	 * Return content statistics including post counts, media count, and user count.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Content statistics data.
	 */
	public function execute_content_stats(): array {
		$post_type_stats = [];
		foreach ( get_post_types( [ 'public' => true ] ) as $pt ) {
			$counts = wp_count_posts( $pt );
			$post_type_stats[ $pt ] = [
				'publish' => (int) ( $counts->publish ?? 0 ),
				'draft'   => (int) ( $counts->draft ?? 0 ),
				'pending' => (int) ( $counts->pending ?? 0 ),
				'private' => (int) ( $counts->private ?? 0 ),
				'total'   => (int) ( $counts->publish ?? 0 )
				             + (int) ( $counts->draft ?? 0 )
				             + (int) ( $counts->pending ?? 0 )
				             + (int) ( $counts->private ?? 0 ),
			];
		}

		$media_counts = wp_count_attachments();
		$total_media  = 0;
		foreach ( (array) $media_counts as $count ) {
			$total_media += (int) $count;
		}

		$user_counts = count_users();

		return [
			'post_types'  => $post_type_stats,
			'media_count' => $total_media,
			'user_count'  => $user_counts['total_users'] ?? 0,
		];
	}
}
