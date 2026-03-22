<?php

declare(strict_types=1);

class Filter_Abilities_Media_Management extends Filter_Abilities_Module_Base {

	/**
	 * Register the media management ability category.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-media',
			__( 'Media Management', 'filter-abilities' ),
			__( 'Abilities for managing the media library.', 'filter-abilities' )
		);
	}

	/**
	 * Register the list-media ability.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		$this->register_ability( 'filter/list-media', [
			'label'               => __( 'List Media', 'filter-abilities' ),
			'description'         => __( 'List media library items with filtering by MIME type, search, and an option to show only items missing alt text.', 'filter-abilities' ),
			'category'            => 'filter-media',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'mime_type' => [
						'type'        => 'string',
						'description' => __( 'Filter by MIME type group: image, video, audio, application, or all. Defaults to all.', 'filter-abilities' ),
						'default'     => 'all',
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => __( 'Number of items per page (max 50). Defaults to 20.', 'filter-abilities' ),
						'default'     => 20,
					],
					'page' => [
						'type'        => 'integer',
						'description' => __( 'Page number. Defaults to 1.', 'filter-abilities' ),
						'default'     => 1,
					],
					'search' => [
						'type'        => 'string',
						'description' => __( 'Search media by title or filename.', 'filter-abilities' ),
					],
					'missing_alt_text' => [
						'type'        => 'boolean',
						'description' => __( 'Only show images missing alt text. Defaults to false.', 'filter-abilities' ),
						'default'     => false,
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'       => [ 'type' => 'integer' ],
					'total_pages' => [ 'type' => 'integer' ],
					'page'        => [ 'type' => 'integer' ],
					'items'       => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'        => [ 'type' => 'integer' ],
								'title'     => [ 'type' => 'string' ],
								'filename'  => [ 'type' => 'string' ],
								'url'       => [ 'type' => 'string' ],
								'mime_type' => [ 'type' => 'string' ],
								'alt_text'  => [ 'type' => 'string' ],
								'width'     => [ 'type' => 'integer' ],
								'height'    => [ 'type' => 'integer' ],
								'file_size' => [ 'type' => 'integer' ],
								'date'      => [ 'type' => 'string' ],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_media' ],
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
		] );
	}

	/**
	 * List media library items with optional MIME type, search, and alt text filters.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $input Ability input parameters.
	 * @return array<string, mixed> Paginated media items.
	 */
	public function execute_list_media( array $input ): array {
		$mime_type        = sanitize_text_field( $input['mime_type'] ?? 'all' );
		$per_page         = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page             = max( absint( $input['page'] ?? 1 ), 1 );
		$missing_alt_text = (bool) ( $input['missing_alt_text'] ?? false );

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( 'all' !== $mime_type ) {
			$args['post_mime_type'] = $mime_type;
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( $missing_alt_text ) {
			$args['post_mime_type'] = 'image';
			$args['meta_query']    = [
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				],
			];
		}

		$query = new WP_Query( $args );
		$items = [];

		foreach ( $query->posts as $attachment ) {
			$metadata  = wp_get_attachment_metadata( $attachment->ID );
			$file_path = get_attached_file( $attachment->ID );

			$items[] = [
				'id'        => $attachment->ID,
				'title'     => get_the_title( $attachment ),
				'filename'  => wp_basename( get_attached_file( $attachment->ID ) ?: '' ),
				'url'       => wp_get_attachment_url( $attachment->ID ) ?: '',
				'mime_type' => $attachment->post_mime_type,
				'alt_text'  => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ?: '',
				'width'     => (int) ( $metadata['width'] ?? 0 ),
				'height'    => (int) ( $metadata['height'] ?? 0 ),
				'file_size' => $file_path && file_exists( $file_path ) ? (int) filesize( $file_path ) : 0,
				'date'      => $attachment->post_date,
			];
		}

		return [
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'items'       => $items,
		];
	}
}
