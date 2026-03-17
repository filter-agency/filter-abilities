<?php

declare(strict_types=1);

class Filter_Abilities_SEO_Management extends Filter_Abilities_Module_Base {

	public function register_categories(): void {
		$this->register_category(
			'filter-seo',
			__( 'SEO Management', 'filter-abilities' ),
			__( 'Abilities for managing Yoast SEO metadata.', 'filter-abilities' )
		);
	}

	public function register_abilities(): void {
		$this->register_ability( 'filter/get-seo-meta', [
			'label'               => __( 'Get SEO Meta', 'filter-abilities' ),
			'description'         => __( 'Get Yoast SEO metadata for a post including title, description, focus keyword, Open Graph data, and content score.', 'filter-abilities' ),
			'category'            => 'filter-seo',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => __( 'The post ID to get SEO data for.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'post_id'         => [ 'type' => 'integer' ],
					'post_title'      => [ 'type' => 'string' ],
					'seo_title'       => [ 'type' => 'string' ],
					'seo_description' => [ 'type' => 'string' ],
					'focus_keyword'   => [ 'type' => 'string' ],
					'canonical_url'   => [ 'type' => 'string' ],
					'og_title'        => [ 'type' => 'string' ],
					'og_description'  => [ 'type' => 'string' ],
					'content_score'   => [ 'type' => 'integer' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_seo_meta' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/update-seo-meta', [
			'label'               => __( 'Update SEO Meta', 'filter-abilities' ),
			'description'         => __( 'Update Yoast SEO metadata fields for a post.', 'filter-abilities' ),
			'category'            => 'filter-seo',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => __( 'The post ID to update SEO data for.', 'filter-abilities' ),
					],
					'seo_title' => [
						'type'        => 'string',
						'description' => __( 'SEO title.', 'filter-abilities' ),
					],
					'seo_description' => [
						'type'        => 'string',
						'description' => __( 'SEO meta description.', 'filter-abilities' ),
					],
					'focus_keyword' => [
						'type'        => 'string',
						'description' => __( 'Focus keyword.', 'filter-abilities' ),
					],
					'canonical_url' => [
						'type'        => 'string',
						'description' => __( 'Canonical URL.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [ 'type' => 'integer' ],
					'updated' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'message' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_update_seo_meta' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/find-seo-issues', [
			'label'               => __( 'Find SEO Issues', 'filter-abilities' ),
			'description'         => __( 'Find published posts missing SEO title, meta description, or focus keyword.', 'filter-abilities' ),
			'category'            => 'filter-seo',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'issue_type' => [
						'type'        => 'string',
						'description' => __( 'Type of issue: missing_title, missing_description, missing_keyword, or all. Defaults to all.', 'filter-abilities' ),
						'default'     => 'all',
						'enum'        => [ 'missing_title', 'missing_description', 'missing_keyword', 'all' ],
					],
					'post_type' => [
						'type'        => 'string',
						'description' => __( 'Limit to specific post type. Defaults to any public type.', 'filter-abilities' ),
						'default'     => 'any',
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => __( 'Number of results (max 50). Defaults to 20.', 'filter-abilities' ),
						'default'     => 20,
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'posts' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'        => [ 'type' => 'integer' ],
								'title'     => [ 'type' => 'string' ],
								'post_type' => [ 'type' => 'string' ],
								'permalink' => [ 'type' => 'string' ],
								'issues'    => [
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_find_seo_issues' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );
	}

	public function execute_get_seo_meta( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return [ 'error' => __( 'Post not found.', 'filter-abilities' ) ];
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [ 'error' => __( 'Permission denied.', 'filter-abilities' ) ];
		}

		return [
			'post_id'         => $post_id,
			'post_title'      => get_the_title( $post ),
			'seo_title'       => get_post_meta( $post_id, '_yoast_wpseo_title', true ) ?: '',
			'seo_description' => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ?: '',
			'focus_keyword'   => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) ?: '',
			'canonical_url'   => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ) ?: '',
			'og_title'        => get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ) ?: '',
			'og_description'  => get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ) ?: '',
			'content_score'   => (int) ( get_post_meta( $post_id, '_yoast_wpseo_content_score', true ) ?: 0 ),
		];
	}

	public function execute_update_seo_meta( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return [ 'error' => __( 'Post not found.', 'filter-abilities' ) ];
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [ 'error' => __( 'Permission denied.', 'filter-abilities' ) ];
		}

		$meta_map = [
			'seo_title'       => '_yoast_wpseo_title',
			'seo_description' => '_yoast_wpseo_metadesc',
			'focus_keyword'   => '_yoast_wpseo_focuskw',
			'canonical_url'   => '_yoast_wpseo_canonical',
		];

		$updated = [];
		foreach ( $meta_map as $field => $meta_key ) {
			if ( isset( $input[ $field ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $input[ $field ] ) );
				$updated[] = $field;
			}
		}

		return [
			'post_id' => $post_id,
			'updated' => $updated,
			'message' => empty( $updated )
				? __( 'No fields provided to update.', 'filter-abilities' )
				: sprintf( __( 'Updated %d field(s).', 'filter-abilities' ), count( $updated ) ),
		];
	}

	public function execute_find_seo_issues( array $input ): array {
		$issue_type = sanitize_text_field( $input['issue_type'] ?? 'all' );
		$post_type  = sanitize_text_field( $input['post_type'] ?? 'any' );
		$per_page   = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );

		$post_types = ( 'any' === $post_type )
			? array_values( get_post_types( [ 'public' => true ] ) )
			: [ $post_type ];

		$meta_checks = [];
		if ( 'all' === $issue_type || 'missing_title' === $issue_type ) {
			$meta_checks['missing_title'] = '_yoast_wpseo_title';
		}
		if ( 'all' === $issue_type || 'missing_description' === $issue_type ) {
			$meta_checks['missing_description'] = '_yoast_wpseo_metadesc';
		}
		if ( 'all' === $issue_type || 'missing_keyword' === $issue_type ) {
			$meta_checks['missing_keyword'] = '_yoast_wpseo_focuskw';
		}

		// Get published posts and check their meta.
		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page * 3, // Over-fetch to filter in PHP.
			'fields'         => 'ids',
		];

		$query   = new WP_Query( $args );
		$results = [];

		foreach ( $query->posts as $pid ) {
			$issues = [];
			foreach ( $meta_checks as $issue_label => $meta_key ) {
				$value = get_post_meta( $pid, $meta_key, true );
				if ( empty( $value ) ) {
					$issues[] = $issue_label;
				}
			}

			if ( ! empty( $issues ) ) {
				$results[] = [
					'id'        => $pid,
					'title'     => get_the_title( $pid ),
					'post_type' => get_post_type( $pid ),
					'permalink' => get_permalink( $pid ),
					'issues'    => $issues,
				];

				if ( count( $results ) >= $per_page ) {
					break;
				}
			}
		}

		return [
			'total' => count( $results ),
			'posts' => $results,
		];
	}
}
