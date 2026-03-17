<?php

declare(strict_types=1);

class Filter_Abilities_Taxonomy_Management extends Filter_Abilities_Module_Base {

	public function register_categories(): void {
		$this->register_category(
			'filter-taxonomy',
			__( 'Taxonomy Management', 'filter-abilities' ),
			__( 'Abilities for managing taxonomy terms.', 'filter-abilities' )
		);
	}

	public function register_abilities(): void {
		$this->register_ability( 'filter/list-terms', [
			'label'               => __( 'List Terms', 'filter-abilities' ),
			'description'         => __( 'List terms for any registered public taxonomy with search, pagination, and hierarchy info.', 'filter-abilities' ),
			'category'            => 'filter-taxonomy',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'taxonomy' => [
						'type'        => 'string',
						'description' => __( 'Taxonomy slug (e.g. category, post_tag, resources-category).', 'filter-abilities' ),
					],
					'hide_empty' => [
						'type'        => 'boolean',
						'description' => __( 'Hide terms with no posts. Defaults to false.', 'filter-abilities' ),
						'default'     => false,
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => __( 'Number of terms to return (max 100). Defaults to 50.', 'filter-abilities' ),
						'default'     => 50,
					],
					'search' => [
						'type'        => 'string',
						'description' => __( 'Search terms by name.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'taxonomy' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'terms' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'          => [ 'type' => 'integer' ],
								'name'        => [ 'type' => 'string' ],
								'slug'        => [ 'type' => 'string' ],
								'description' => [ 'type' => 'string' ],
								'count'       => [ 'type' => 'integer' ],
								'parent_id'   => [ 'type' => 'integer' ],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_terms' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_categories' );
			},
		] );

		$this->register_ability( 'filter/manage-term', [
			'label'               => __( 'Manage Term', 'filter-abilities' ),
			'description'         => __( 'Create, update, or delete a taxonomy term.', 'filter-abilities' ),
			'category'            => 'filter-taxonomy',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => __( 'Action to perform: create, update, or delete.', 'filter-abilities' ),
						'enum'        => [ 'create', 'update', 'delete' ],
					],
					'taxonomy' => [
						'type'        => 'string',
						'description' => __( 'Taxonomy slug.', 'filter-abilities' ),
					],
					'term_id' => [
						'type'        => 'integer',
						'description' => __( 'Term ID. Required for update and delete.', 'filter-abilities' ),
					],
					'name' => [
						'type'        => 'string',
						'description' => __( 'Term name. Required for create.', 'filter-abilities' ),
					],
					'slug' => [
						'type'        => 'string',
						'description' => __( 'Term slug.', 'filter-abilities' ),
					],
					'description' => [
						'type'        => 'string',
						'description' => __( 'Term description.', 'filter-abilities' ),
					],
					'parent' => [
						'type'        => 'integer',
						'description' => __( 'Parent term ID for hierarchical taxonomies.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'action', 'taxonomy' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'term_id' => [ 'type' => 'integer' ],
					'name'    => [ 'type' => 'string' ],
					'slug'    => [ 'type' => 'string' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_manage_term' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_categories' );
			},
		] );
	}

	public function execute_list_terms( array $input ): array {
		$taxonomy   = sanitize_text_field( $input['taxonomy'] ?? '' );
		$hide_empty = (bool) ( $input['hide_empty'] ?? false );
		$per_page   = max( 1, min( absint( $input['per_page'] ?? 50 ), 100 ) );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [ 'error' => sprintf( __( 'Taxonomy "%s" does not exist.', 'filter-abilities' ), $taxonomy ) ];
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => $hide_empty,
			'number'     => $per_page,
		];

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return [ 'error' => $terms->get_error_message() ];
		}

		$result = [];
		foreach ( $terms as $term ) {
			$result[] = [
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'count'       => $term->count,
				'parent_id'   => $term->parent,
			];
		}

		return [
			'total' => count( $result ),
			'terms' => $result,
		];
	}

	public function execute_manage_term( array $input ): array {
		$action   = sanitize_text_field( $input['action'] ?? '' );
		$taxonomy = sanitize_text_field( $input['taxonomy'] ?? '' );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [ 'error' => sprintf( __( 'Taxonomy "%s" does not exist.', 'filter-abilities' ), $taxonomy ) ];
		}

		switch ( $action ) {
			case 'create':
				$name = sanitize_text_field( $input['name'] ?? '' );
				if ( empty( $name ) ) {
					return [ 'error' => __( 'Term name is required for create.', 'filter-abilities' ) ];
				}

				$term_args = [];
				if ( ! empty( $input['slug'] ) ) {
					$term_args['slug'] = sanitize_title( $input['slug'] );
				}
				if ( ! empty( $input['description'] ) ) {
					$term_args['description'] = sanitize_text_field( $input['description'] );
				}
				if ( isset( $input['parent'] ) ) {
					$term_args['parent'] = absint( $input['parent'] );
				}

				$result = wp_insert_term( $name, $taxonomy, $term_args );

				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				$term = get_term( $result['term_id'], $taxonomy );

				return [
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
					'message' => __( 'Term created successfully.', 'filter-abilities' ),
				];

			case 'update':
				$term_id = absint( $input['term_id'] ?? 0 );
				if ( ! $term_id ) {
					return [ 'error' => __( 'Term ID is required for update.', 'filter-abilities' ) ];
				}

				$term_args = [];
				if ( ! empty( $input['name'] ) ) {
					$term_args['name'] = sanitize_text_field( $input['name'] );
				}
				if ( ! empty( $input['slug'] ) ) {
					$term_args['slug'] = sanitize_title( $input['slug'] );
				}
				if ( ! empty( $input['description'] ) ) {
					$term_args['description'] = sanitize_text_field( $input['description'] );
				}
				if ( isset( $input['parent'] ) ) {
					$term_args['parent'] = absint( $input['parent'] );
				}

				$result = wp_update_term( $term_id, $taxonomy, $term_args );

				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				$term = get_term( $result['term_id'], $taxonomy );

				return [
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
					'message' => __( 'Term updated successfully.', 'filter-abilities' ),
				];

			case 'delete':
				$term_id = absint( $input['term_id'] ?? 0 );
				if ( ! $term_id ) {
					return [ 'error' => __( 'Term ID is required for delete.', 'filter-abilities' ) ];
				}

				$result = wp_delete_term( $term_id, $taxonomy );

				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				return [
					'term_id' => $term_id,
					'message' => __( 'Term deleted successfully.', 'filter-abilities' ),
				];

			default:
				return [ 'error' => __( 'Invalid action. Use create, update, or delete.', 'filter-abilities' ) ];
		}
	}
}
