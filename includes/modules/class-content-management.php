<?php

declare(strict_types=1);

class Filter_Abilities_Content_Management extends Filter_Abilities_Module_Base {

	/**
	 * Register content management categories.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-content',
			__( 'Content Management', 'filter-abilities' ),
			__( 'Abilities for managing posts, pages, and custom post types.', 'filter-abilities' )
		);
	}

	/**
	 * Register content management abilities.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
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

		$this->register_ability( 'filter/get-post-by-url', [
			'label'               => __( 'Get Post by URL', 'filter-abilities' ),
			'description'         => __( 'Look up a post by its URL path or slug. Returns the same detailed data as get-post including content, taxonomies, and ACF fields.', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'url' => [
						'type'        => 'string',
						'description' => __( 'URL path (e.g. /about-us/) or full URL. Also accepts a plain slug (e.g. about-us).', 'filter-abilities' ),
					],
				],
				'required'   => [ 'url' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'             => [ 'type' => 'integer' ],
					'title'          => [ 'type' => 'string' ],
					'content'        => [ 'type' => 'string' ],
					'excerpt'        => [ 'type' => 'string' ],
					'status'         => [ 'type' => 'string' ],
					'post_type'      => [ 'type' => 'string' ],
					'date'           => [ 'type' => 'string' ],
					'modified'       => [ 'type' => 'string' ],
					'permalink'      => [ 'type' => 'string' ],
					'author'         => [ 'type' => 'string' ],
					'featured_image' => [ 'type' => 'string' ],
					'taxonomies'     => [ 'type' => 'object' ],
					'acf_fields'     => [ 'type' => 'object' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_post_by_url' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/delete-post', [
			'label'               => __( 'Delete Post', 'filter-abilities' ),
			'description'         => __( 'Trash or permanently delete a post. Defaults to trashing (reversible). Use force=true to permanently delete.', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => __( 'The post ID to delete.', 'filter-abilities' ),
					],
					'force' => [
						'type'        => 'boolean',
						'description' => __( 'Permanently delete instead of trashing. Defaults to false.', 'filter-abilities' ),
						'default'     => false,
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'      => [ 'type' => 'integer' ],
					'title'   => [ 'type' => 'string' ],
					'action'  => [ 'type' => 'string' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_delete_post' ],
			'permission_callback' => function () {
				return current_user_can( 'delete_posts' );
			},
		] );

		$this->register_ability( 'filter/bulk-post-actions', [
			'label'               => __( 'Bulk Post Actions', 'filter-abilities' ),
			'description'         => __( 'Perform bulk actions on multiple posts: publish, draft, trash, restore from trash, or permanently delete.', 'filter-abilities' ),
			'category'            => 'filter-content',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'enum'        => [ 'publish', 'draft', 'trash', 'restore', 'delete' ],
						'description' => __( 'Action: publish, draft, trash, restore (from trash), or delete (permanent).', 'filter-abilities' ),
					],
					'post_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Array of post IDs to act on.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'action', 'post_ids' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'action'   => [ 'type' => 'string' ],
					'affected' => [ 'type' => 'integer' ],
					'skipped'  => [ 'type' => 'integer' ],
					'message'  => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_bulk_post_actions' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );

		$this->register_ability( 'filter/update-post', [
			'label'               => __( 'Update Post', 'filter-abilities' ),
			'description'         => __( 'Update an existing post including title, content, status, date, taxonomies, and ACF fields.', 'filter-abilities' ),
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
					'date' => [
						'type'        => 'string',
						'description' => __( 'Post date in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format.', 'filter-abilities' ),
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

	/**
	 * Execute the list-posts ability.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input containing post_type, status, per_page, page, orderby, order, and search.
	 * @return array Paginated list of posts or error.
	 */
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

		// Verify user has edit capability for the requested post type.
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return [ 'error' => __( 'You do not have permission to view this post type.', 'filter-abilities' ) ];
		}

		// Validate status against allowlist.
		$allowed_statuses = [ 'publish', 'draft', 'pending', 'private', 'future', 'any' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'publish';
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

	/**
	 * Execute the get-post ability.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input containing post_id.
	 * @return array Post data including content, taxonomies, and ACF fields, or error.
	 */
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

	/**
	 * Execute the create-post ability.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input containing title, post_type, content, status, excerpt, acf_fields, and taxonomy_terms.
	 * @return array Created post data or error.
	 */
	public function execute_create_post( array $input ): array {
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );

		if ( ! post_type_exists( $post_type ) ) {
			return [ 'error' => sprintf( __( 'Post type "%s" does not exist.', 'filter-abilities' ), $post_type ) ];
		}

		$post_type_obj = get_post_type_object( $post_type );
		if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
			return [ 'error' => __( 'You do not have permission to create this post type.', 'filter-abilities' ) ];
		}

		// Validate and check capability for requested status.
		$status          = sanitize_text_field( $input['status'] ?? 'draft' );
		$allowed_statuses = [ 'draft', 'pending', 'publish', 'private' ];
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'draft';
		}
		if ( 'publish' === $status && ! current_user_can( $post_type_obj->cap->publish_posts ) ) {
			return [ 'error' => __( 'You do not have permission to publish this post type.', 'filter-abilities' ) ];
		}

		$post_data = [
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
			'post_status'  => $status,
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

		// Set ACF fields if available. String values are sanitised here;
		// arrays and other types are left for ACF's own validation layer.
		if ( ! empty( $input['acf_fields'] ) && is_array( $input['acf_fields'] ) && function_exists( 'update_field' ) ) {
			foreach ( $input['acf_fields'] as $field_name => $value ) {
				if ( is_string( $value ) ) {
					$value = wp_kses_post( $value );
				}
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

	/**
	 * Execute the update-post ability.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input containing post_id and optional title, content, status, excerpt, acf_fields, and taxonomy_terms.
	 * @return array Updated post data or error.
	 */
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
			$status          = sanitize_text_field( $input['status'] );
			$allowed_statuses = [ 'draft', 'pending', 'publish', 'private' ];
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return [ 'error' => sprintf( __( 'Invalid status "%s".', 'filter-abilities' ), $status ) ];
			}
			if ( 'publish' === $status && ! current_user_can( 'publish_post', $post_id ) ) {
				return [ 'error' => __( 'You do not have permission to publish this post.', 'filter-abilities' ) ];
			}
			$post_data['post_status'] = $status;
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
		}
		if ( isset( $input['date'] ) ) {
			$date = sanitize_text_field( $input['date'] );
			// Accept YYYY-MM-DD (append midnight) or full datetime.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$date .= ' 00:00:00';
			}
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
				return [ 'error' => __( 'Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 'filter-abilities' ) ];
			}
			$post_data['post_date']     = $date;
			$post_data['post_date_gmt'] = get_gmt_from_date( $date );
			$post_data['edit_date']     = true;
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

		// Set ACF fields if available. String values are sanitised here;
		// arrays and other types are left for ACF's own validation layer.
		if ( ! empty( $input['acf_fields'] ) && is_array( $input['acf_fields'] ) && function_exists( 'update_field' ) ) {
			foreach ( $input['acf_fields'] as $field_name => $value ) {
				if ( is_string( $value ) ) {
					$value = wp_kses_post( $value );
				}
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

	/**
	 * Look up a post by URL path or slug and return full post data.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Ability input containing url.
	 * @return array<string, mixed> Post data or error.
	 */
	public function execute_get_post_by_url( array $input ): array {
		$url = sanitize_text_field( $input['url'] ?? '' );

		if ( empty( $url ) ) {
			return [ 'error' => __( 'URL is required.', 'filter-abilities' ) ];
		}

		$post_id = 0;

		// Try url_to_postid first (handles full URLs and paths).
		if ( str_starts_with( $url, '/' ) || str_starts_with( $url, 'http' ) ) {
			// Ensure it's a full URL for url_to_postid.
			$full_url = str_starts_with( $url, 'http' ) ? $url : home_url( $url );
			$post_id  = url_to_postid( $full_url );
		}

		// Fallback: try as a slug across all public post types.
		if ( ! $post_id ) {
			$slug       = trim( $url, '/' );
			// Strip any path segments — use only the last segment as the slug.
			if ( str_contains( $slug, '/' ) ) {
				$slug = basename( $slug );
			}

			$post_types = array_values( get_post_types( [ 'public' => true ] ) );

			$found = get_posts( [
				'name'           => $slug,
				'post_type'      => $post_types,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			] );

			if ( ! empty( $found ) ) {
				$post_id = $found[0];
			}
		}

		if ( ! $post_id ) {
			return [ 'error' => __( 'No post found for that URL or slug.', 'filter-abilities' ) ];
		}

		// Delegate to the existing get-post logic.
		return $this->execute_get_post( [ 'post_id' => $post_id ] );
	}

	/**
	 * Trash or permanently delete a post.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Ability input containing post_id and optional force flag.
	 * @return array<string, mixed> Result or error.
	 */
	public function execute_delete_post( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$force   = (bool) ( $input['force'] ?? false );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return [ 'error' => __( 'Post not found.', 'filter-abilities' ) ];
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return [ 'error' => __( 'Permission denied.', 'filter-abilities' ) ];
		}

		$title = get_the_title( $post );

		if ( $force ) {
			$result = wp_delete_post( $post_id, true );
		} else {
			$result = wp_trash_post( $post_id );
		}

		if ( ! $result ) {
			return [ 'error' => __( 'Failed to delete post.', 'filter-abilities' ) ];
		}

		$action_label = $force ? 'deleted' : 'trashed';

		return [
			'id'      => $post_id,
			'title'   => $title,
			'action'  => $action_label,
			'message' => sprintf(
				/* translators: 1: post title, 2: action taken */
				__( '"%1$s" has been %2$s.', 'filter-abilities' ),
				$title,
				$action_label
			),
		];
	}

	/**
	 * Perform bulk actions on multiple posts.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Ability input containing action and post_ids.
	 * @return array<string, mixed> Result with affected count.
	 */
	public function execute_bulk_post_actions( array $input ): array {
		$action   = sanitize_text_field( $input['action'] ?? '' );
		$post_ids = array_map( 'absint', (array) ( $input['post_ids'] ?? [] ) );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			return [ 'error' => __( 'No post IDs provided.', 'filter-abilities' ) ];
		}

		$affected = 0;
		$skipped  = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$skipped++;
				continue;
			}

			switch ( $action ) {
				case 'publish':
					if ( ! current_user_can( 'publish_post', $post_id ) ) {
						$skipped++;
						continue 2;
					}
					wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
					$affected++;
					break;

				case 'draft':
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						$skipped++;
						continue 2;
					}
					wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
					$affected++;
					break;

				case 'trash':
					if ( ! current_user_can( 'delete_post', $post_id ) ) {
						$skipped++;
						continue 2;
					}
					wp_trash_post( $post_id );
					$affected++;
					break;

				case 'restore':
					if ( ! current_user_can( 'delete_post', $post_id ) ) {
						$skipped++;
						continue 2;
					}
					wp_untrash_post( $post_id );
					$affected++;
					break;

				case 'delete':
					if ( ! current_user_can( 'delete_post', $post_id ) ) {
						$skipped++;
						continue 2;
					}
					wp_delete_post( $post_id, true );
					$affected++;
					break;

				default:
					return [ 'error' => __( 'Invalid action. Use publish, draft, trash, restore, or delete.', 'filter-abilities' ) ];
			}
		}

		return [
			'action'   => $action,
			'affected' => $affected,
			'skipped'  => $skipped,
			'message'  => sprintf(
				/* translators: 1: number affected, 2: action, 3: number skipped */
				__( '%1$d post(s) %2$s. %3$d skipped (not found or insufficient permissions).', 'filter-abilities' ),
				$affected,
				$action . ( 'trash' === $action ? 'ed' : ( 'publish' === $action ? 'ed' : 'ed' ) ),
				$skipped
			),
		];
	}
}
