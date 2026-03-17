<?php

declare(strict_types=1);

class Filter_Abilities_Content_Management extends Filter_Abilities_Module_Base {

	public function register_categories(): void {
		$this->register_category(
			'filter-content',
			__( 'Content Management', 'filter-abilities' ),
			__( 'Abilities for managing posts, pages, and custom post types.', 'filter-abilities' )
		);
	}

	public function register_abilities(): void {
		$this->register_ability( 'filter/list-posts', [
			'label'               => __( 'List Posts', 'filter-abilities' ),
			'description'         => __( 'List posts by type with filtering, pagination, sorting, and search. Returns id, title, status, date, permalink, author, excerpt, and featured image URL.', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_type' => [
						'type'        => 'string',
						'description' => __( 'Post type slug (e.g. post, page, news, resources). Defaults to post.', 'filter-abilities' ),
						'default'     => 'post',
					],
					'status' => [
						'type'        => 'string',
						'description' => __( 'Post status: publish, draft, pending, private, any. Defaults to publish.', 'filter-abilities' ),
						'default'     => 'publish',
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => __( 'Number of posts per page (max 50). Defaults to 10.', 'filter-abilities' ),
						'default'     => 10,
					],
					'page' => [
						'type'        => 'integer',
						'description' => __( 'Page number. Defaults to 1.', 'filter-abilities' ),
						'default'     => 1,
					],
					'orderby' => [
						'type'        => 'string',
						'description' => __( 'Order by: date, title, modified, menu_order. Defaults to date.', 'filter-abilities' ),
						'default'     => 'date',
					],
					'order' => [
						'type'        => 'string',
						'description' => __( 'Sort order: ASC or DESC. Defaults to DESC.', 'filter-abilities' ),
						'default'     => 'DESC',
					],
					'search' => [
						'type'        => 'string',
						'description' => __( 'Search query to filter posts by.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'       => [ 'type' => 'integer' ],
					'total_pages' => [ 'type' => 'integer' ],
					'page'        => [ 'type' => 'integer' ],
					'posts'       => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'                 => [ 'type' => 'integer' ],
								'title'              => [ 'type' => 'string' ],
								'status'             => [ 'type' => 'string' ],
								'date'               => [ 'type' => 'string' ],
								'modified'           => [ 'type' => 'string' ],
								'permalink'          => [ 'type' => 'string' ],
								'author'             => [ 'type' => 'string' ],
								'excerpt'            => [ 'type' => 'string' ],
								'featured_image_url' => [ 'type' => 'string' ],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_posts' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/get-post', [
			'label'               => __( 'Get Post', 'filter-abilities' ),
			'description'         => __( 'Get detailed post data including content, taxonomy terms, and ACF fields (if ACF is active).', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => __( 'The post ID to retrieve.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'              => [ 'type' => 'integer' ],
					'title'           => [ 'type' => 'string' ],
					'content'         => [ 'type' => 'string' ],
					'excerpt'         => [ 'type' => 'string' ],
					'status'          => [ 'type' => 'string' ],
					'post_type'       => [ 'type' => 'string' ],
					'date'            => [ 'type' => 'string' ],
					'modified'        => [ 'type' => 'string' ],
					'permalink'       => [ 'type' => 'string' ],
					'author'          => [ 'type' => 'string' ],
					'featured_image'  => [ 'type' => 'string' ],
					'taxonomies'      => [ 'type' => 'object' ],
					'acf_fields'      => [ 'type' => 'object' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_post' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/create-post', [
			'label'               => __( 'Create Post', 'filter-abilities' ),
			'description'         => __( 'Create a new post with optional taxonomy assignments and ACF fields (if ACF is active).', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_type' => [
						'type'        => 'string',
						'description' => __( 'Post type slug. Defaults to post.', 'filter-abilities' ),
						'default'     => 'post',
					],
					'title' => [
						'type'        => 'string',
						'description' => __( 'Post title.', 'filter-abilities' ),
					],
					'content' => [
						'type'        => 'string',
						'description' => __( 'Post content.', 'filter-abilities' ),
						'default'     => '',
					],
					'status' => [
						'type'        => 'string',
						'description' => __( 'Post status: draft, publish, pending. Defaults to draft.', 'filter-abilities' ),
						'default'     => 'draft',
					],
					'excerpt' => [
						'type'        => 'string',
						'description' => __( 'Post excerpt.', 'filter-abilities' ),
					],
					'acf_fields' => [
						'type'        => 'object',
						'description' => __( 'Key-value pairs of ACF field names to values. Requires ACF.', 'filter-abilities' ),
					],
					'taxonomy_terms' => [
						'type'        => 'object',
						'description' => __( 'Taxonomy slug to array of term IDs. E.g. {"category": [1, 2]}.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'title' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'        => [ 'type' => 'integer' ],
					'title'     => [ 'type' => 'string' ],
					'status'    => [ 'type' => 'string' ],
					'permalink' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_create_post' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/update-post', [
			'label'               => __( 'Update Post', 'filter-abilities' ),
			'description'         => __( 'Update an existing post including title, content, status, taxonomies, and ACF fields.', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => __( 'The post ID to update.', 'filter-abilities' ),
					],
					'title' => [
						'type'        => 'string',
						'description' => __( 'New post title.', 'filter-abilities' ),
					],
					'content' => [
						'type'        => 'string',
						'description' => __( 'New post content.', 'filter-abilities' ),
					],
					'status' => [
						'type'        => 'string',
						'description' => __( 'New post status: draft, publish, pending, private.', 'filter-abilities' ),
					],
					'excerpt' => [
						'type'        => 'string',
						'description' => __( 'New post excerpt.', 'filter-abilities' ),
					],
					'acf_fields' => [
						'type'        => 'object',
						'description' => __( 'Key-value pairs of ACF field names to values. Requires ACF.', 'filter-abilities' ),
					],
					'taxonomy_terms' => [
						'type'        => 'object',
						'description' => __( 'Taxonomy slug to array of term IDs.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'        => [ 'type' => 'integer' ],
					'title'     => [ 'type' => 'string' ],
					'status'    => [ 'type' => 'string' ],
					'permalink' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_update_post' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );
	}

	public function execute_list_posts( array $input ): array {
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );
		$status    = sanitize_text_field( $input['status'] ?? 'publish' );
		$per_page  = max( 1, min( absint( $input['per_page'] ?? 10 ), 50 ) );
		$page      = max( absint( $input['page'] ?? 1 ), 1 );

		$allowed_orderby = [ 'date', 'title', 'modified', 'menu_order', 'ID' ];
		$orderby         = in_array( $input['orderby'] ?? 'date', $allowed_orderby, true )
			? $input['orderby'] : 'date';
		$order           = in_array( strtoupper( $input['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true )
			? strtoupper( $input['order'] ?? 'DESC' ) : 'DESC';

		if ( ! post_type_exists( $post_type ) ) {
			return [ 'error' => sprintf( __( 'Post type "%s" does not exist.', 'filter-abilities' ), $post_type ) ];
		}

		$args = [
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		];

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new WP_Query( $args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$author = get_userdata( $post->post_author );
			$thumb  = get_the_post_thumbnail_url( $post->ID, 'medium' );

			$posts[] = [
				'id'                 => $post->ID,
				'title'              => get_the_title( $post ),
				'status'             => $post->post_status,
				'date'               => $post->post_date,
				'modified'           => $post->post_modified,
				'permalink'          => get_permalink( $post ),
				'author'             => $author ? $author->display_name : '',
				'excerpt'            => get_the_excerpt( $post ),
				'featured_image_url' => $thumb ?: '',
			];
		}

		return [
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'posts'       => $posts,
		];
	}

	public function execute_get_post( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return [ 'error' => __( 'Post not found.', 'filter-abilities' ) ];
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [ 'error' => __( 'Permission denied.', 'filter-abilities' ) ];
		}

		$author = get_userdata( $post->post_author );
		$thumb  = get_the_post_thumbnail_url( $post_id, 'full' );

		// Collect taxonomy terms.
		$taxonomies     = get_object_taxonomies( $post->post_type );
		$taxonomy_terms = [];
		foreach ( $taxonomies as $tax ) {
			$terms = get_the_terms( $post_id, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$taxonomy_terms[ $tax ] = array_map( function ( $term ) {
					return [
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					];
				}, $terms );
			}
		}

		// ACF fields if available.
		$acf_fields = [];
		if ( function_exists( 'get_fields' ) ) {
			$fields = get_fields( $post_id );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $key => $value ) {
					if ( str_starts_with( $key, '_' ) ) {
						continue;
					}
					$acf_fields[ $key ] = $value;
				}
			}
		}

		return [
			'id'             => $post->ID,
			'title'          => get_the_title( $post ),
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'status'         => $post->post_status,
			'post_type'      => $post->post_type,
			'date'           => $post->post_date,
			'modified'       => $post->post_modified,
			'permalink'      => get_permalink( $post ),
			'author'         => $author ? $author->display_name : '',
			'featured_image' => $thumb ?: '',
			'taxonomies'     => $taxonomy_terms,
			'acf_fields'     => $acf_fields,
		];
	}

	public function execute_create_post( array $input ): array {
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );

		if ( ! post_type_exists( $post_type ) ) {
			return [ 'error' => sprintf( __( 'Post type "%s" does not exist.', 'filter-abilities' ), $post_type ) ];
		}

		$post_type_obj = get_post_type_object( $post_type );
		if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
			return [ 'error' => __( 'You do not have permission to create this post type.', 'filter-abilities' ) ];
		}

		$post_data = [
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
			'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
		];

		if ( ! empty( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return [ 'error' => $post_id->get_error_message() ];
		}

		// Set taxonomy terms.
		if ( ! empty( $input['taxonomy_terms'] ) && is_array( $input['taxonomy_terms'] ) ) {
			foreach ( $input['taxonomy_terms'] as $taxonomy => $term_ids ) {
				$taxonomy = sanitize_text_field( $taxonomy );
				$term_ids = array_map( 'absint', (array) $term_ids );
				wp_set_object_terms( $post_id, $term_ids, $taxonomy );
			}
		}

		// Set ACF fields if available.
		if ( ! empty( $input['acf_fields'] ) && is_array( $input['acf_fields'] ) && function_exists( 'update_field' ) ) {
			foreach ( $input['acf_fields'] as $field_name => $value ) {
				update_field( sanitize_text_field( $field_name ), $value, $post_id );
			}
		}

		return [
			'id'        => $post_id,
			'title'     => get_the_title( $post_id ),
			'status'    => get_post_status( $post_id ),
			'permalink' => get_permalink( $post_id ),
		];
	}

	public function execute_update_post( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return [ 'error' => __( 'Post not found.', 'filter-abilities' ) ];
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [ 'error' => __( 'Permission denied.', 'filter-abilities' ) ];
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['status'] ) ) {
			$post_data['post_status'] = sanitize_text_field( $input['status'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return [ 'error' => $result->get_error_message() ];
		}

		// Set taxonomy terms.
		if ( ! empty( $input['taxonomy_terms'] ) && is_array( $input['taxonomy_terms'] ) ) {
			foreach ( $input['taxonomy_terms'] as $taxonomy => $term_ids ) {
				$taxonomy = sanitize_text_field( $taxonomy );
				$term_ids = array_map( 'absint', (array) $term_ids );
				wp_set_object_terms( $post_id, $term_ids, $taxonomy );
			}
		}

		// Set ACF fields if available.
		if ( ! empty( $input['acf_fields'] ) && is_array( $input['acf_fields'] ) && function_exists( 'update_field' ) ) {
			foreach ( $input['acf_fields'] as $field_name => $value ) {
				update_field( sanitize_text_field( $field_name ), $value, $post_id );
			}
		}

		return [
			'id'        => $post_id,
			'title'     => get_the_title( $post_id ),
			'status'    => get_post_status( $post_id ),
			'permalink' => get_permalink( $post_id ),
		];
	}
}
