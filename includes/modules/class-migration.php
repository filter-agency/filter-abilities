<?php

declare(strict_types=1);

/**
 * Migration module — abilities for cross-site content/media migration.
 *
 * Hosts filter/rewrite-content, which rewrites media references inside post
 * content, block attributes, gallery shortcodes, featured images, and ACF
 * fields, using a caller-supplied id/url mapping table.
 *
 * @since 1.6.0
 */
class Filter_Abilities_Migration extends Filter_Abilities_Module_Base {

	/**
	 * Block-name → array of attribute paths that hold media references.
	 *
	 * Each entry describes which attribute keys hold an attachment ID, an
	 * attachment URL, or an array of IDs.
	 *
	 * @var array<string, array{id?: string[], url?: string[], ids?: string[]}>
	 */
	private const BLOCK_MEDIA_ATTRS = [
		'core/image'      => [ 'id' => [ 'id' ], 'url' => [ 'url' ] ],
		'core/gallery'    => [ 'ids' => [ 'ids' ] ],
		'core/cover'      => [ 'id' => [ 'id' ], 'url' => [ 'url' ] ],
		'core/media-text' => [ 'id' => [ 'mediaId' ], 'url' => [ 'mediaUrl' ] ],
		'core/video'      => [ 'id' => [ 'id' ], 'url' => [ 'src' ] ],
		'core/audio'      => [ 'id' => [ 'id' ], 'url' => [ 'src' ] ],
		'core/file'       => [ 'id' => [ 'id' ], 'url' => [ 'href' ] ],
	];

	/**
	 * Register the migration ability category.
	 *
	 * @since 1.6.0
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-migration',
			__( 'Migration Tools', 'filter-abilities' ),
			__( 'Abilities for cross-site content and media migration.', 'filter-abilities' )
		);
	}

	/**
	 * Register the rewrite-content ability.
	 *
	 * @since 1.6.0
	 */
	public function register_abilities(): void {
		$this->register_ability( 'filter/rewrite-content', [
			'label'               => __( 'Rewrite Content', 'filter-abilities' ),
			'description'         => __( 'Rewrite media references in post content, block attributes, gallery shortcodes, featured images, and ACF fields, using a caller-supplied media_map (and optional generic url_map). Defaults to dry_run for safety. Designed to follow filter/upload-media in a migration pipeline.', 'filter-abilities' ),
			'category'            => 'filter-migration',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'media_map' => [
						'type'        => 'array',
						'description' => __( 'Mapping of source-site media IDs/URLs to destination-site equivalents.', 'filter-abilities' ),
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'old_id'        => [ 'type' => 'integer' ],
								'new_id'        => [ 'type' => 'integer' ],
								'old_url'       => [
									'type'        => 'string',
									'description' => __( 'Source-site original (full / -scaled) URL. Strongly recommended.', 'filter-abilities' ),
								],
								'new_url'       => [
									'type'        => 'string',
									'description' => __( 'Destination-site original URL. Required if old_url provided.', 'filter-abilities' ),
								],
								'old_size_urls' => [
									'type'        => 'array',
									'items'       => [ 'type' => 'string' ],
									'description' => __( 'Optional list of ALL source-site URL variants (intermediate size files). Each is rewritten to new_url. Pass values from list-media\'s size_urls map. Without these, post-body img src URLs pointing at intermediate sizes are left in place — but wp-image-{ID} class rewriting still lets WP rebuild a working src/srcset at render time.', 'filter-abilities' ),
								],
							],
							'required' => [ 'old_id', 'new_id' ],
						],
					],
					'url_map' => [
						'type'        => 'array',
						'description' => __( 'Optional generic URL find/replace pairs (e.g. site URL change). Applied after media URL replacement.', 'filter-abilities' ),
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'old' => [ 'type' => 'string' ],
								'new' => [ 'type' => 'string' ],
							],
							'required' => [ 'old', 'new' ],
						],
					],
					'post_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Explicit list of post IDs to rewrite. Mutually exclusive with post_type/all_post_types.', 'filter-abilities' ),
					],
					'post_type' => [
						'type'        => 'string',
						'description' => __( 'Rewrite all posts of this post type. Use with per_page/page. Mutually exclusive with post_ids.', 'filter-abilities' ),
					],
					'all_post_types' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Rewrite posts across all public post types. Use with per_page/page. Mutually exclusive with post_ids and post_type.', 'filter-abilities' ),
					],
					'include_postmeta' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'include_acf' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'dry_run' => [
						'type'        => 'boolean',
						'default'     => true,
						'description' => __( 'Defaults to true. Pass false to apply changes.', 'filter-abilities' ),
					],
					'per_page' => [
						'type'    => 'integer',
						'default' => 25,
					],
					'page' => [
						'type'    => 'integer',
						'default' => 1,
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_posts' => [ 'type' => 'integer' ],
					'page'        => [ 'type' => 'integer' ],
					'total_pages' => [ 'type' => 'integer' ],
					'dry_run'     => [ 'type' => 'boolean' ],
					'results'     => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'post_id'      => [ 'type' => 'integer' ],
								'post_title'   => [ 'type' => 'string' ],
								'post_type'    => [ 'type' => 'string' ],
								'replacements' => [
									'type'       => 'object',
									'properties' => [
										'block_attrs'       => [ 'type' => 'integer' ],
										'image_classes'     => [ 'type' => 'integer' ],
										'urls'              => [ 'type' => 'integer' ],
										'gallery_shortcode' => [ 'type' => 'integer' ],
										'thumbnail'         => [ 'type' => 'integer' ],
										'acf_fields'        => [ 'type' => 'integer' ],
									],
								],
								'applied'      => [ 'type' => 'boolean' ],
								'error'        => [ 'type' => 'string' ],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_rewrite_content' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_others_posts' );
			},
		] );
	}

	/**
	 * Execute the rewrite-content ability.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Pagination + per-post results, or an error.
	 */
	public function execute_rewrite_content( array $input ): array {
		$media_map_raw = $input['media_map'] ?? [];
		if ( ! is_array( $media_map_raw ) || empty( $media_map_raw ) ) {
			return [ 'error' => __( 'media_map must be a non-empty array.', 'filter-abilities' ) ];
		}

		// Index id_map by old_id for O(1) lookup; also build a flat URL map from
		// every old_url + old_size_urls value to its corresponding new_url.
		$id_map  = [];
		$url_map = [];
		foreach ( $media_map_raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$old_id = isset( $entry['old_id'] ) ? (int) $entry['old_id'] : 0;
			$new_id = isset( $entry['new_id'] ) ? (int) $entry['new_id'] : 0;
			if ( $old_id <= 0 || $new_id <= 0 ) {
				continue;
			}
			$new_url = isset( $entry['new_url'] ) ? (string) $entry['new_url'] : '';
			$id_map[ $old_id ] = [
				'new_id'  => $new_id,
				'new_url' => $new_url,
			];

			if ( '' === $new_url ) {
				continue;
			}
			if ( ! empty( $entry['old_url'] ) ) {
				$url_map[ (string) $entry['old_url'] ] = $new_url;
			}
			if ( ! empty( $entry['old_size_urls'] ) && is_array( $entry['old_size_urls'] ) ) {
				foreach ( $entry['old_size_urls'] as $variant ) {
					if ( is_string( $variant ) && '' !== $variant ) {
						$url_map[ $variant ] = $new_url;
					}
				}
			}
		}

		if ( empty( $id_map ) ) {
			return [ 'error' => __( 'media_map contained no valid entries (old_id and new_id required, both must be positive integers).', 'filter-abilities' ) ];
		}

		// Generic URL map (applied after media URLs).
		$generic_url_map = [];
		if ( ! empty( $input['url_map'] ) && is_array( $input['url_map'] ) ) {
			foreach ( $input['url_map'] as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['old'] ) && isset( $entry['new'] ) ) {
					$generic_url_map[ (string) $entry['old'] ] = (string) $entry['new'];
				}
			}
		}

		$dry_run          = ! isset( $input['dry_run'] ) || (bool) $input['dry_run'];
		$include_postmeta = ! isset( $input['include_postmeta'] ) || (bool) $input['include_postmeta'];
		$include_acf      = ! isset( $input['include_acf'] ) || (bool) $input['include_acf'];
		$per_page         = max( 1, min( (int) ( $input['per_page'] ?? 25 ), 100 ) );
		$page             = max( 1, (int) ( $input['page'] ?? 1 ) );

		// Resolve target post set — exactly one of post_ids / post_type / all_post_types.
		$post_ids       = $input['post_ids'] ?? null;
		$post_type      = isset( $input['post_type'] ) ? (string) $input['post_type'] : '';
		$all_post_types = ! empty( $input['all_post_types'] );

		$selectors_used = (int) ( ! empty( $post_ids ) && is_array( $post_ids ) )
			+ (int) ( '' !== $post_type )
			+ (int) $all_post_types;
		if ( 1 !== $selectors_used ) {
			return [ 'error' => __( 'Provide exactly one of: post_ids, post_type, or all_post_types: true.', 'filter-abilities' ) ];
		}

		// Build the WP_Query args.
		$query_args = [
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'no_found_rows'  => false,
		];
		if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
			$query_args['post__in'] = array_map( 'absint', $post_ids );
			$query_args['post_type'] = 'any';
		} elseif ( '' !== $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				return [ 'error' => sprintf( /* translators: %s: post type slug */ __( 'Post type "%s" does not exist.', 'filter-abilities' ), $post_type ) ];
			}
			$query_args['post_type'] = $post_type;
		} else {
			$query_args['post_type'] = get_post_types( [ 'public' => true ], 'names' );
		}

		$query   = new WP_Query( $query_args );
		$results = [];

		foreach ( $query->posts as $post ) {
			$results[] = $this->rewrite_single_post( $post, $id_map, $url_map, $generic_url_map, [
				'dry_run'          => $dry_run,
				'include_postmeta' => $include_postmeta,
				'include_acf'      => $include_acf,
			] );
		}

		return [
			'total_posts' => (int) $query->found_posts,
			'page'        => $page,
			'total_pages' => (int) $query->max_num_pages,
			'dry_run'     => $dry_run,
			'results'     => $results,
		];
	}

	/**
	 * Rewrite references in a single post.
	 *
	 * @since 1.6.0
	 *
	 * @param WP_Post                                              $post            Post being rewritten.
	 * @param array<int, array{new_id:int, new_url:string}>        $id_map          old_id => entry.
	 * @param array<string, string>                                $url_map         old URL => new URL (media).
	 * @param array<string, string>                                $generic_url_map old URL => new URL (generic).
	 * @param array{dry_run:bool, include_postmeta:bool, include_acf:bool} $opts    Options.
	 * @return array<string, mixed>
	 */
	protected function rewrite_single_post( WP_Post $post, array $id_map, array $url_map, array $generic_url_map, array $opts ): array {
		$result = [
			'post_id'      => $post->ID,
			'post_title'   => $post->post_title,
			'post_type'    => $post->post_type,
			'replacements' => [
				'block_attrs'       => 0,
				'image_classes'     => 0,
				'urls'              => 0,
				'gallery_shortcode' => 0,
				'thumbnail'         => 0,
				'acf_fields'        => 0,
			],
			'applied'      => false,
		];

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			$result['error'] = __( 'You do not have permission to edit this post.', 'filter-abilities' );
			return $result;
		}

		// 1. Rewrite post_content (blocks → inner_html → gallery shortcode).
		$blocks       = parse_blocks( $post->post_content );
		$blocks_attrs = 0;
		$blocks       = $this->rewrite_blocks_recursively( $blocks, $id_map, $url_map, $generic_url_map, $blocks_attrs, $result['replacements'] );
		$content      = serialize_blocks( $blocks );

		$gallery_count = 0;
		$content       = $this->rewrite_gallery_shortcodes( $content, $id_map, $gallery_count );

		$result['replacements']['block_attrs']       = $blocks_attrs;
		$result['replacements']['gallery_shortcode'] = $gallery_count;

		$content_changed = ( $content !== $post->post_content );

		// 2. Featured image and ACF (postmeta).
		$thumbnail_change = null; // null = no change, [old,new] = pending
		if ( $opts['include_postmeta'] ) {
			$current_thumb = (int) get_post_thumbnail_id( $post->ID );
			if ( $current_thumb > 0 && isset( $id_map[ $current_thumb ] ) ) {
				$thumbnail_change                       = [ $current_thumb, $id_map[ $current_thumb ]['new_id'] ];
				$result['replacements']['thumbnail']    = 1;
			}
		}

		$acf_changes = [];
		if ( $opts['include_postmeta'] && $opts['include_acf'] && function_exists( 'get_field_objects' ) ) {
			$acf_changes                          = $this->collect_acf_rewrites( $post->ID, $id_map );
			$result['replacements']['acf_fields'] = count( $acf_changes );
		}

		// 3. Apply if not dry_run.
		if ( ! $opts['dry_run'] ) {
			if ( $content_changed ) {
				wp_update_post( [
					'ID'           => $post->ID,
					'post_content' => $content,
				] );
			}
			if ( null !== $thumbnail_change ) {
				set_post_thumbnail( $post->ID, $thumbnail_change[1] );
			}
			if ( ! empty( $acf_changes ) && function_exists( 'update_field' ) ) {
				foreach ( $acf_changes as $change ) {
					update_field( $change['field_key'], $change['new_value'], $post->ID );
				}
			}
			$result['applied'] = true;
		}

		return $result;
	}

	/**
	 * Walk an array of parsed blocks (recursing into innerBlocks), rewriting
	 * media references in known block attributes and inner HTML.
	 *
	 * @since 1.6.0
	 *
	 * @param array<int, array<string, mixed>>              $blocks          Parsed blocks.
	 * @param array<int, array{new_id:int, new_url:string}> $id_map          old_id => entry.
	 * @param array<string, string>                         $url_map         Media URL map.
	 * @param array<string, string>                         $generic_url_map Generic URL map.
	 * @param int                                           $attrs_count     By-ref counter for block attribute swaps.
	 * @param array<string, int>                            $counts          By-ref counters for image_classes / urls.
	 * @return array<int, array<string, mixed>> Rewritten blocks.
	 */
	protected function rewrite_blocks_recursively( array $blocks, array $id_map, array $url_map, array $generic_url_map, int &$attrs_count, array &$counts ): array {
		foreach ( $blocks as $i => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			// Rewrite known block attributes.
			if ( ! empty( $block['blockName'] ) && isset( self::BLOCK_MEDIA_ATTRS[ $block['blockName'] ] ) ) {
				$mapping = self::BLOCK_MEDIA_ATTRS[ $block['blockName'] ];
				$attrs   = $block['attrs'] ?? [];

				// Single-ID attributes.
				if ( ! empty( $mapping['id'] ) ) {
					foreach ( $mapping['id'] as $key ) {
						if ( isset( $attrs[ $key ] ) ) {
							$old = (int) $attrs[ $key ];
							if ( $old > 0 && isset( $id_map[ $old ] ) ) {
								$attrs[ $key ] = $id_map[ $old ]['new_id'];
								$attrs_count++;
							}
						}
					}
				}

				// Single-URL attributes.
				if ( ! empty( $mapping['url'] ) ) {
					foreach ( $mapping['url'] as $key ) {
						if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) && isset( $url_map[ $attrs[ $key ] ] ) ) {
							$attrs[ $key ] = $url_map[ $attrs[ $key ] ];
							$attrs_count++;
						}
					}
				}

				// Array-of-IDs attributes (core/gallery).
				if ( ! empty( $mapping['ids'] ) ) {
					foreach ( $mapping['ids'] as $key ) {
						if ( isset( $attrs[ $key ] ) && is_array( $attrs[ $key ] ) ) {
							foreach ( $attrs[ $key ] as $idx => $old ) {
								$old = (int) $old;
								if ( $old > 0 && isset( $id_map[ $old ] ) ) {
									$attrs[ $key ][ $idx ] = $id_map[ $old ]['new_id'];
									$attrs_count++;
								}
							}
						}
					}
				}

				$block['attrs'] = $attrs;
			}

			// Allow extensions to handle custom block types or override behaviour.
			$block = apply_filters(
				'filter_abilities_rewrite_block_attrs',
				$block,
				$id_map,
				$url_map,
				$generic_url_map
			);

			// Rewrite innerHTML.
			if ( ! empty( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
				$block['innerHTML'] = $this->rewrite_inner_html( $block['innerHTML'], $id_map, $url_map, $generic_url_map, $counts );
			}
			if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $idx => $chunk ) {
					if ( is_string( $chunk ) ) {
						$block['innerContent'][ $idx ] = $this->rewrite_inner_html( $chunk, $id_map, $url_map, $generic_url_map, $counts );
					}
				}
			}

			// Recurse into innerBlocks.
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->rewrite_blocks_recursively( $block['innerBlocks'], $id_map, $url_map, $generic_url_map, $attrs_count, $counts );
			}

			$blocks[ $i ] = $block;
		}

		return $blocks;
	}

	/**
	 * Rewrite a chunk of HTML: wp-image-N classes + URL substitutions.
	 *
	 * @since 1.6.0
	 *
	 * @param string                                        $html            HTML chunk.
	 * @param array<int, array{new_id:int, new_url:string}> $id_map          old_id => entry.
	 * @param array<string, string>                         $url_map         Media URL map.
	 * @param array<string, string>                         $generic_url_map Generic URL map.
	 * @param array<string, int>                            $counts          By-ref counters.
	 * @return string Rewritten HTML.
	 */
	protected function rewrite_inner_html( string $html, array $id_map, array $url_map, array $generic_url_map, array &$counts ): string {
		// 1. wp-image-{ID} class rewrites — most important for rendered fidelity:
		//    once the class points at the new attachment ID, WP rebuilds src/srcset
		//    at render time using the destination's own intermediate sizes.
		$html = preg_replace_callback(
			'/wp-image-(\d+)/',
			function ( $m ) use ( $id_map, &$counts ) {
				$old = (int) $m[1];
				if ( $old > 0 && isset( $id_map[ $old ] ) ) {
					$counts['image_classes']++;
					return 'wp-image-' . (int) $id_map[ $old ]['new_id'];
				}
				return $m[0];
			},
			$html
		);

		// 2. Media URL replacement (each old_url + old_size_url → new_url).
		// 3. Generic URL replacement (site URL changes etc).
		// Sort longest-first so e.g. "https://oldsite.com/wp-content/uploads/foo-300x200.jpg"
		// is matched before a shorter "https://oldsite.com" generic prefix.
		$combined = $url_map + $generic_url_map;
		uksort( $combined, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );

		foreach ( $combined as $old => $new ) {
			if ( '' === $old || $old === $new ) {
				continue;
			}
			$count = 0;
			$html  = str_replace( $old, $new, $html, $count );
			if ( $count > 0 ) {
				$counts['urls'] += $count;
			}
		}

		return $html;
	}

	/**
	 * Rewrite [gallery ids="..."] shortcodes' ids and include attributes.
	 *
	 * @since 1.6.0
	 *
	 * @param string                                        $content Post content (post-blocks-serialize).
	 * @param array<int, array{new_id:int, new_url:string}> $id_map  old_id => entry.
	 * @param int                                           $count   By-ref counter for gallery rewrites (one per shortcode that changed).
	 * @return string Rewritten content.
	 */
	protected function rewrite_gallery_shortcodes( string $content, array $id_map, int &$count ): string {
		if ( false === strpos( $content, '[gallery' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/\[gallery\b([^\]]*)\]/i',
			function ( $m ) use ( $id_map, &$count ) {
				$attrs = shortcode_parse_atts( $m[1] );
				if ( ! is_array( $attrs ) || empty( $attrs ) ) {
					return $m[0];
				}

				$changed = false;
				foreach ( [ 'ids', 'include' ] as $attr_key ) {
					if ( empty( $attrs[ $attr_key ] ) ) {
						continue;
					}
					$ids = array_filter( array_map( 'intval', explode( ',', (string) $attrs[ $attr_key ] ) ) );
					$new_ids = [];
					foreach ( $ids as $old ) {
						if ( isset( $id_map[ $old ] ) ) {
							$new_ids[] = (int) $id_map[ $old ]['new_id'];
							$changed   = true;
						} else {
							$new_ids[] = $old;
						}
					}
					$attrs[ $attr_key ] = implode( ',', $new_ids );
				}

				if ( ! $changed ) {
					return $m[0];
				}

				$count++;

				$rebuilt = '[gallery';
				foreach ( $attrs as $k => $v ) {
					$rebuilt .= ' ' . $k . '="' . esc_attr( (string) $v ) . '"';
				}
				$rebuilt .= ']';
				return $rebuilt;
			},
			$content
		);
	}

	/**
	 * Identify ACF fields on a post that need rewriting.
	 *
	 * Conservative — only handles field types that store attachment IDs:
	 * image, gallery, file. Other types (post_object etc.) are left alone.
	 *
	 * @since 1.6.0
	 *
	 * @param int                                           $post_id Post being checked.
	 * @param array<int, array{new_id:int, new_url:string}> $id_map  old_id => entry.
	 * @return array<int, array{field_key:string, new_value:mixed}> List of changes to apply.
	 */
	protected function collect_acf_rewrites( int $post_id, array $id_map ): array {
		$changes = [];

		$fields = get_field_objects( $post_id );
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $changes;
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['type'] ) || ! isset( $field['value'] ) ) {
				continue;
			}
			$type     = (string) $field['type'];
			$value    = $field['value'];
			$field_key = isset( $field['key'] ) ? (string) $field['key'] : ( isset( $field['name'] ) ? (string) $field['name'] : '' );
			if ( '' === $field_key ) {
				continue;
			}

			if ( 'image' === $type || 'file' === $type ) {
				$old_id = $this->extract_acf_attachment_id( $value );
				if ( $old_id > 0 && isset( $id_map[ $old_id ] ) ) {
					$changes[] = [
						'field_key' => $field_key,
						'new_value' => (int) $id_map[ $old_id ]['new_id'],
					];
				}
			} elseif ( 'gallery' === $type && is_array( $value ) ) {
				$new_ids = [];
				$any     = false;
				foreach ( $value as $entry ) {
					$old = $this->extract_acf_attachment_id( $entry );
					if ( $old > 0 && isset( $id_map[ $old ] ) ) {
						$new_ids[] = (int) $id_map[ $old ]['new_id'];
						$any       = true;
					} elseif ( $old > 0 ) {
						$new_ids[] = $old;
					}
				}
				if ( $any ) {
					$changes[] = [
						'field_key' => $field_key,
						'new_value' => $new_ids,
					];
				}
			}
		}

		return $changes;
	}

	/**
	 * Extract an attachment ID from a value that ACF might return for an
	 * image/file field (could be int, string, or array depending on field's
	 * "Return Format" setting).
	 *
	 * @since 1.6.0
	 *
	 * @param mixed $value ACF field value.
	 * @return int Attachment ID, or 0 if none could be extracted.
	 */
	protected function extract_acf_attachment_id( $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}
		if ( is_array( $value ) && ! empty( $value['ID'] ) ) {
			return (int) $value['ID'];
		}
		if ( is_array( $value ) && ! empty( $value['id'] ) ) {
			return (int) $value['id'];
		}
		return 0;
	}
}
