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
								'id'          => [ 'type' => 'integer' ],
								'title'       => [ 'type' => 'string' ],
								'filename'    => [ 'type' => 'string' ],
								'url'         => [ 'type' => 'string' ],
								'mime_type'   => [ 'type' => 'string' ],
								'alt_text'    => [ 'type' => 'string' ],
								'caption'     => [ 'type' => 'string' ],
								'description' => [ 'type' => 'string' ],
								'post_parent' => [ 'type' => 'integer' ],
								'width'       => [ 'type' => 'integer' ],
								'height'      => [ 'type' => 'integer' ],
								'file_size'   => [ 'type' => 'integer' ],
								'date'        => [ 'type' => 'string' ],
								'size_urls'   => [
									'type'        => 'object',
									'description' => __( 'Map of intermediate-size name to URL, including "full" for the original (or WP 5.3+ -scaled) file. Use the "full" entry as the source URL when migrating via filter/upload-media; pass any of the intermediate URLs in filter/rewrite-content media_map[].old_size_urls so post-body references to specific size variants get rewritten too.', 'filter-abilities' ),
								],
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

		$this->register_upload_media();
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
				'id'          => $attachment->ID,
				'title'       => get_the_title( $attachment ),
				'filename'    => wp_basename( get_attached_file( $attachment->ID ) ?: '' ),
				'url'         => wp_get_attachment_url( $attachment->ID ) ?: '',
				'mime_type'   => $attachment->post_mime_type,
				'alt_text'    => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ?: '',
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
				'post_parent' => (int) $attachment->post_parent,
				'width'       => (int) ( $metadata['width'] ?? 0 ),
				'height'      => (int) ( $metadata['height'] ?? 0 ),
				'file_size'   => $file_path && file_exists( $file_path ) ? (int) filesize( $file_path ) : 0,
				'date'        => $attachment->post_date,
				'size_urls'   => $this->build_size_urls( $attachment->ID ),
			];
		}

		return [
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'items'       => $items,
		];
	}

	/**
	 * Build a {size_name: url} map for an attachment, including a "full" entry.
	 *
	 * @since 1.6.0
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array<string, string> Map of size slug to URL.
	 */
	protected function build_size_urls( int $attachment_id ): array {
		$out = [];
		$full_url = wp_get_attachment_url( $attachment_id );
		if ( $full_url ) {
			$out['full'] = $full_url;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
				$src = wp_get_attachment_image_src( $attachment_id, $size_name );
				if ( $src && ! empty( $src[0] ) ) {
					$out[ (string) $size_name ] = (string) $src[0];
				}
			}
		}

		return $out;
	}

	/**
	 * Register the upload-media ability.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	protected function register_upload_media(): void {
		$this->register_ability( 'filter/upload-media', [
			'label'               => __( 'Upload Media', 'filter-abilities' ),
			'description'         => __( 'Sideload one or more remote URLs into the media library. Each item may include attachment metadata, an optional post_parent, and an optional flag to set the new attachment as that post\'s featured image. Designed for cross-site media migrations as well as single-image uploads from agent-supplied URLs.', 'filter-abilities' ),
			'category'            => 'filter-media',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'        => 'array',
						'description' => __( 'Up to 50 items per call by default (matches filter/list-media\'s per-page cap). Sites with strong hosting can raise the cap via the filter_abilities_upload_media_max_batch filter. The execution applies set_time_limit(0) and raises the image memory limit, but extremely large batches should still be chunked.', 'filter-abilities' ),
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'url'                   => [
									'type'        => 'string',
									'description' => __( 'Source URL of the ORIGINAL file (http/https only). Do not pass an intermediate-size URL — pass list-media\'s top-level "url" or "size_urls.full".', 'filter-abilities' ),
								],
								'title'                 => [
									'type'        => 'string',
									'description' => __( 'Optional title for the new attachment. Defaults to the filename.', 'filter-abilities' ),
								],
								'alt_text'              => [
									'type'        => 'string',
									'description' => __( 'Optional alt text (stored as _wp_attachment_image_alt postmeta).', 'filter-abilities' ),
								],
								'caption'               => [
									'type'        => 'string',
									'description' => __( 'Optional caption (stored as the attachment post_excerpt).', 'filter-abilities' ),
								],
								'description'           => [
									'type'        => 'string',
									'description' => __( 'Optional description (stored as the attachment post_content).', 'filter-abilities' ),
								],
								'post_parent'           => [
									'type'        => 'integer',
									'description' => __( 'Optional new-site post ID to set as parent. Caller is responsible for ID translation.', 'filter-abilities' ),
								],
								'date'                  => [
									'type'        => 'string',
									'description' => __( 'Optional original upload date (YYYY-MM-DD HH:MM:SS) to preserve on the new attachment.', 'filter-abilities' ),
								],
								'original_id'           => [
									'type'        => 'integer',
									'description' => __( 'Optional source-site attachment ID. Echoed back in the response for ID-mapping during migrations.', 'filter-abilities' ),
								],
								'set_as_featured_image' => [
									'type'        => 'boolean',
									'default'     => false,
									'description' => __( 'When true, also set this attachment as the featured image of post_parent. Requires post_parent to be set.', 'filter-abilities' ),
								],
							],
							'required' => [ 'url' ],
						],
					],
				],
				'required'   => [ 'items' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'results' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'success'            => [ 'type' => 'boolean' ],
								'new_id'             => [ 'type' => 'integer' ],
								'new_url'            => [ 'type' => 'string' ],
								'new_size_urls'      => [
									'type'        => 'object',
									'description' => __( 'Destination-side {size_name: url} map after intermediate-size regeneration.', 'filter-abilities' ),
								],
								'filename'           => [ 'type' => 'string' ],
								'mime_type'          => [ 'type' => 'string' ],
								'width'              => [ 'type' => 'integer' ],
								'height'             => [ 'type' => 'integer' ],
								'file_size'          => [ 'type' => 'integer' ],
								'featured_image_set' => [ 'type' => 'boolean' ],
								'original_id'        => [ 'type' => 'integer' ],
								'source_url'         => [ 'type' => 'string' ],
								'error'              => [ 'type' => 'string' ],
							],
						],
					],
					'summary' => [
						'type'       => 'object',
						'properties' => [
							'requested' => [ 'type' => 'integer' ],
							'succeeded' => [ 'type' => 'integer' ],
							'failed'    => [ 'type' => 'integer' ],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_upload_media' ],
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
		] );
	}

	/**
	 * Execute the upload-media ability.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $input Ability input containing items[].
	 * @return array<string, mixed> Per-item results plus summary, or an error.
	 */
	public function execute_upload_media( array $input ): array {
		$items = $input['items'] ?? [];
		if ( ! is_array( $items ) || empty( $items ) ) {
			return [ 'error' => __( 'items must be a non-empty array.', 'filter-abilities' ) ];
		}

		$max_batch = (int) apply_filters( 'filter_abilities_upload_media_max_batch', 50 );
		$max_batch = max( 1, $max_batch );
		if ( count( $items ) > $max_batch ) {
			return [
				'error' => sprintf(
					/* translators: %d: maximum batch size */
					__( 'Too many items: this ability accepts up to %d items per call. Chunk larger batches client-side, or raise the cap via the filter_abilities_upload_media_max_batch filter.', 'filter-abilities' ),
					$max_batch
				),
			];
		}

		// Give the request the best chance of completing a large batch.
		// set_time_limit can fail silently in safe-mode/restricted hosts; that's fine.
		@set_time_limit( 0 );
		wp_raise_memory_limit( 'image' );

		// Ensure media-handling helpers are loaded outside admin context.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$results   = [];
		$succeeded = 0;
		$failed    = 0;

		foreach ( $items as $item ) {
			$result = $this->upload_single_item( is_array( $item ) ? $item : [] );
			$results[] = $result;
			if ( ! empty( $result['success'] ) ) {
				$succeeded++;
			} else {
				$failed++;
			}
		}

		return [
			'results' => $results,
			'summary' => [
				'requested' => count( $items ),
				'succeeded' => $succeeded,
				'failed'    => $failed,
			],
		];
	}

	/**
	 * Process a single upload item.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $item Single item from the input array.
	 * @return array<string, mixed> Per-item result.
	 */
	protected function upload_single_item( array $item ): array {
		$source_url            = isset( $item['url'] ) ? (string) $item['url'] : '';
		$post_parent           = isset( $item['post_parent'] ) ? absint( $item['post_parent'] ) : 0;
		$set_as_featured_image = ! empty( $item['set_as_featured_image'] );
		$original_id           = isset( $item['original_id'] ) ? absint( $item['original_id'] ) : 0;

		$result = [
			'success'            => false,
			'source_url'         => $source_url,
			'original_id'        => $original_id,
			'featured_image_set' => false,
		];

		if ( '' === $source_url ) {
			$result['error'] = __( 'url is required.', 'filter-abilities' );
			return $result;
		}

		if ( ! $this->is_safe_external_url( $source_url ) ) {
			$result['error'] = __( 'url must be a public http(s) URL. Loopback, link-local, and private-network addresses are rejected.', 'filter-abilities' );
			return $result;
		}

		if ( $set_as_featured_image && 0 === $post_parent ) {
			$result['error'] = __( 'set_as_featured_image requires post_parent to be set.', 'filter-abilities' );
			return $result;
		}

		if ( $post_parent > 0 ) {
			if ( ! get_post( $post_parent ) ) {
				$result['error'] = sprintf(
					/* translators: %d: post ID */
					__( 'post_parent %d does not exist.', 'filter-abilities' ),
					$post_parent
				);
				return $result;
			}
			if ( ! current_user_can( 'edit_post', $post_parent ) ) {
				$result['error'] = __( 'You do not have permission to edit the parent post.', 'filter-abilities' );
				return $result;
			}
		}

		$tmp = download_url( $source_url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$result['error'] = $tmp->get_error_message();
			return $result;
		}

		$path     = (string) wp_parse_url( $source_url, PHP_URL_PATH );
		$filename = wp_basename( $path );
		if ( '' === $filename ) {
			@unlink( $tmp );
			$result['error'] = __( 'Could not derive a filename from the URL.', 'filter-abilities' );
			return $result;
		}

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		$desc       = isset( $item['title'] ) ? (string) $item['title'] : null;
		$attachment = media_handle_sideload( $file_array, $post_parent, $desc );

		if ( is_wp_error( $attachment ) ) {
			// media_handle_sideload cleans up tmp on error, but be defensive.
			if ( file_exists( $tmp ) ) {
				@unlink( $tmp );
			}
			$result['error'] = $attachment->get_error_message();
			return $result;
		}

		$attachment_id = (int) $attachment;

		// Apply remaining metadata.
		if ( isset( $item['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $item['alt_text'] ) );
		}

		$post_update = [];
		if ( isset( $item['caption'] ) ) {
			$post_update['post_excerpt'] = wp_kses_post( (string) $item['caption'] );
		}
		if ( isset( $item['description'] ) ) {
			$post_update['post_content'] = wp_kses_post( (string) $item['description'] );
		}
		if ( ! empty( $item['date'] ) ) {
			$date = (string) $item['date'];
			if ( strtotime( $date ) ) {
				$post_update['post_date']     = $date;
				$post_update['post_date_gmt'] = get_gmt_from_date( $date );
			}
		}
		if ( ! empty( $post_update ) ) {
			$post_update['ID'] = $attachment_id;
			wp_update_post( $post_update );
		}

		// Featured image.
		if ( $set_as_featured_image && $post_parent > 0 ) {
			set_post_thumbnail( $post_parent, $attachment_id );
			$result['featured_image_set'] = true;
		}

		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$file_path = get_attached_file( $attachment_id );

		$result['success']       = true;
		$result['new_id']        = $attachment_id;
		$result['new_url']       = wp_get_attachment_url( $attachment_id ) ?: '';
		$result['new_size_urls'] = $this->build_size_urls( $attachment_id );
		$result['filename']      = wp_basename( $file_path ?: '' );
		$result['mime_type']     = (string) get_post_mime_type( $attachment_id );
		$result['width']         = (int) ( $metadata['width'] ?? 0 );
		$result['height']        = (int) ( $metadata['height'] ?? 0 );
		$result['file_size']     = $file_path && file_exists( $file_path ) ? (int) filesize( $file_path ) : 0;

		return $result;
	}

	/**
	 * SSRF guard. Reject URLs that:
	 * - fail basic parsing
	 * - use a scheme other than http/https
	 * - resolve (any A/AAAA record) to a loopback, link-local, or RFC1918 private address
	 *
	 * Apply the `filter_abilities_is_safe_external_url` filter at the end so
	 * advanced users can whitelist specific internal hostnames. Use sparingly —
	 * the default behaviour exists to prevent SSRF.
	 *
	 * @since 1.6.0
	 *
	 * @param string $url URL to validate.
	 * @return bool True if the URL is safe to fetch from the WP server.
	 */
	protected function is_safe_external_url( string $url ): bool {
		$is_safe = $this->compute_is_safe_external_url( $url );
		return (bool) apply_filters( 'filter_abilities_is_safe_external_url', $is_safe, $url );
	}

	/**
	 * Compute the default SSRF verdict before the filter is applied.
	 *
	 * @since 1.6.0
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	protected function compute_is_safe_external_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return false;
		}

		$host = (string) $parts['host'];

		// IPv6 literal hosts (`[::1]`, `[fe80::1]`) — reject as a class.
		// Normal IPv6 hostname resolution would have happened via DNS for the AAAA record,
		// which gethostbynamel does not return; conservative reject avoids opening that path.
		if ( false !== strpos( $host, ':' ) ) {
			return false;
		}

		// Resolve all A records and check each. gethostbynamel returns false on failure.
		$addresses = gethostbynamel( $host );
		if ( false === $addresses || empty( $addresses ) ) {
			// If literal IPv4 in $host, gethostbynamel may still return it; otherwise treat as unresolvable.
			if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$addresses = [ $host ];
			} else {
				return false;
			}
		}

		foreach ( $addresses as $ip ) {
			if ( ! $this->is_public_ipv4( (string) $ip ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether an IPv4 address is in the public range (i.e. NOT loopback,
	 * link-local, RFC1918, CGNAT, or other reserved blocks).
	 *
	 * @since 1.6.0
	 *
	 * @param string $ip IPv4 address.
	 * @return bool True if public-routable.
	 */
	protected function is_public_ipv4( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}
		// FILTER_FLAG_NO_PRIV_RANGE rejects RFC1918 (10/8, 172.16/12, 192.168/16).
		// FILTER_FLAG_NO_RES_RANGE rejects loopback, link-local, multicast, and other reserved.
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}
}
