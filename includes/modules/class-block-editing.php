<?php

declare(strict_types=1);

/**
 * Block Editing module — surgical, block-aware Gutenberg content editing.
 *
 * Wraps the vendored GravityKit Block API engine (includes/block-engine/) and
 * exposes it as Abilities API abilities. Instead of regenerating an entire
 * post_content string (which routinely drops or mangles `<!-- wp:... -->`
 * delimiters), the AI reads a parsed block tree with stable references, then
 * submits a targeted change to a single block. The engine re-serialises via
 * WordPress core, so block markup stays valid and the editor never flags the
 * post as corrupted.
 *
 * The engine returns array|WP_Error; this module follows the existing Filter
 * convention of returning associative arrays and signalling failure with an
 * `error` key (see Filter_Abilities_Content_Management).
 *
 * @since 1.7.0
 */
class Filter_Abilities_Block_Editing extends Filter_Abilities_Module_Base {

	/** @var \GravityKit\BlockAPI\Block_CRUD|null */
	private $crud = null;

	/** @var \GravityKit\BlockAPI\Block_Mutator|null */
	private $mutator = null;

	/** @var \GravityKit\BlockAPI\Block_Registry|null */
	private $registry = null;

	/**
	 * Lazily load and wire the vendored block engine.
	 *
	 * Mirrors GravityKit's init_rest_api() wiring. Loaded on first use only, so
	 * requests that never touch a block ability pay nothing for the engine.
	 *
	 * @since 1.7.0
	 */
	private function boot_engine(): void {
		if ( null !== $this->crud ) {
			return;
		}

		require_once FILTER_ABILITIES_PATH . 'includes/block-engine/loader.php';

		$preferences = new \GravityKit\BlockAPI\Preferences();
		$inventory   = new \GravityKit\BlockAPI\Block_Inventory();
		$safety      = new \GravityKit\BlockAPI\Block_Safety();
		$transformer = new \GravityKit\BlockAPI\HTML_Transformer();

		$this->registry = new \GravityKit\BlockAPI\Block_Registry( $preferences, $inventory );
		$this->crud     = new \GravityKit\BlockAPI\Block_CRUD( $preferences, $safety, $transformer, $inventory );
		$this->mutator  = new \GravityKit\BlockAPI\Block_Mutator( $this->crud, $preferences, $safety, $transformer );
	}

	/**
	 * Register the block-editing ability category.
	 *
	 * @since 1.7.0
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-blocks',
			__( 'Block Editing', 'filter-abilities' ),
			__( 'Surgical, block-aware editing of Gutenberg content that preserves block markup.', 'filter-abilities' )
		);
	}

	/**
	 * Register all block-editing abilities.
	 *
	 * @since 1.7.0
	 */
	public function register_abilities(): void {
		$writer_permission = function () {
			return current_user_can( 'edit_posts' );
		};

		// --- Read ------------------------------------------------------------

		$this->register_ability( 'filter/get-post-blocks', [
			'label'               => __( 'Get Post Blocks', 'filter-abilities' ),
			'description'         => __( 'Read a post\'s Gutenberg content as a structured block tree. Each block carries a stable "ref", a flat "index", and a "path" you reuse in the other block abilities to make a targeted edit. ALWAYS call this before editing existing block content — then change a single block with update-block / mutate-block instead of rewriting the whole post (which corrupts block markup).', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id'      => [
						'type'        => 'integer',
						'description' => __( 'The post ID to read.', 'filter-abilities' ),
					],
					'render'       => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Render dynamic blocks and expand shortcodes in the output.', 'filter-abilities' ),
					],
					'persist_refs' => [
						'type'        => 'boolean',
						'default'     => true,
						'description' => __( 'Persist stable block refs into the post so they stay valid across edits. Writes benign metadata into block markup; leave true for multi-step editing.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'blocks'      => [ 'type' => 'array' ],
					'revision_id' => [ 'type' => 'integer' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_post_blocks' ],
			'permission_callback' => $writer_permission,
		] );

		$this->register_ability( 'filter/list-block-types', [
			'label'               => __( 'List Block Types', 'filter-abilities' ),
			'description'         => __( 'List the block types registered on this site, each with its attribute schema and a preference tier (preferred / acceptable / avoid / legacy) so you can choose a current, well-supported block and avoid deprecated ones when inserting content.', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'namespace'      => [
						'type'        => 'string',
						'description' => __( 'Filter by namespace, e.g. "core" or "filter".', 'filter-abilities' ),
					],
					'category'       => [
						'type'        => 'string',
						'description' => __( 'Filter by editor block category, e.g. "text", "media".', 'filter-abilities' ),
					],
					'tier'           => [
						'type'        => 'string',
						'enum'        => [ 'preferred', 'acceptable', 'avoid', 'legacy' ],
						'description' => __( 'Only return blocks in this preference tier.', 'filter-abilities' ),
					],
					'storage_mode'   => [
						'type'        => 'string',
						'enum'        => [ 'static', 'dynamic', 'dual' ],
						'description' => __( 'Only return blocks with this storage mode.', 'filter-abilities' ),
					],
					'search'         => [
						'type'        => 'string',
						'description' => __( 'Case-insensitive substring match against block name and title.', 'filter-abilities' ),
					],
					'preferred_only' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Only return blocks with a preference score of 50 or higher.', 'filter-abilities' ),
					],
					'usage_only'     => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Only return block types actually present somewhere on the site.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'block_types' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_block_types' ],
			'permission_callback' => $writer_permission,
		] );

		// --- Write -----------------------------------------------------------

		$this->register_ability( 'filter/update-block', [
			'label'               => __( 'Update Block', 'filter-abilities' ),
			'description'         => __( 'Update a single block\'s attributes and/or innerHTML, identified by its stable "ref" (preferred) or flat "index" from get-post-blocks. Static-block HTML is auto-synced so the edit never triggers a block-validation warning. Use this for the common case of tweaking existing content.', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id'            => [
						'type'        => 'integer',
						'description' => __( 'The post ID.', 'filter-abilities' ),
					],
					'ref'                => [
						'type'        => 'string',
						'description' => __( 'Stable block ref from get-post-blocks (e.g. "blk_a3f2c1q9d"). Preferred over index.', 'filter-abilities' ),
					],
					'index'              => [
						'type'        => 'integer',
						'description' => __( 'Flat block index from get-post-blocks. Use only if you have no ref.', 'filter-abilities' ),
					],
					'attributes'         => [
						'type'        => 'object',
						'description' => __( 'Partial block attributes to merge (deep-merged into existing attributes).', 'filter-abilities' ),
					],
					'inner_html'         => [
						'type'        => 'string',
						'description' => __( 'Replacement innerHTML for the block. Omit to leave the markup untouched.', 'filter-abilities' ),
					],
					'allow_bound_writes' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Permit writing attributes that are bound to/derived from innerHTML. Off by default for safety.', 'filter-abilities' ),
					],
					'expected_revision'  => [
						'type'        => [ 'integer', 'string' ],
						'description' => __( 'Optimistic-concurrency guard: the revision_id you last read. The write fails if the post changed since.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_update_block' ],
			'permission_callback' => $writer_permission,
		] );

		$this->register_ability( 'filter/insert-blocks', [
			'label'               => __( 'Insert Blocks', 'filter-abilities' ),
			'description'         => __( 'Insert one or more new blocks at a position. Each block definition is { name, attributes, innerHTML, innerBlocks }. Position is a numeric index to insert after, "start" to prepend, or omitted to append. Block markup is generated by WordPress core, so it is always valid.', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id'  => [
						'type'        => 'integer',
						'description' => __( 'The post ID.', 'filter-abilities' ),
					],
					'position' => [
						'type'        => [ 'integer', 'string' ],
						'description' => __( 'Numeric index to insert after, "start" to prepend, or omit to append.', 'filter-abilities' ),
					],
					'blocks'   => [
						'type'        => 'array',
						'description' => __( 'Block definitions to insert.', 'filter-abilities' ),
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'name'        => [
									'type'        => 'string',
									'description' => __( 'Block type name, e.g. "core/paragraph".', 'filter-abilities' ),
								],
								'attributes'  => [ 'type' => 'object' ],
								'innerHTML'   => [ 'type' => 'string' ],
								'innerBlocks' => [ 'type' => 'array' ],
							],
							'required'   => [ 'name' ],
						],
					],
				],
				'required'   => [ 'post_id', 'blocks' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_insert_blocks' ],
			'permission_callback' => $writer_permission,
		] );

		$this->register_ability( 'filter/delete-blocks', [
			'label'               => __( 'Delete Blocks', 'filter-abilities' ),
			'description'         => __( 'Delete one or more consecutive blocks, identified by the stable "ref" (preferred) or flat "index" of the first block, plus an optional count.', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id'           => [
						'type'        => 'integer',
						'description' => __( 'The post ID.', 'filter-abilities' ),
					],
					'ref'               => [
						'type'        => 'string',
						'description' => __( 'Stable ref of the first block to delete. Preferred over index.', 'filter-abilities' ),
					],
					'index'             => [
						'type'        => 'integer',
						'description' => __( 'Flat index of the first block to delete. Use only if you have no ref.', 'filter-abilities' ),
					],
					'count'             => [
						'type'        => 'integer',
						'default'     => 1,
						'description' => __( 'Number of consecutive blocks to remove.', 'filter-abilities' ),
					],
					'expected_revision' => [
						'type'        => [ 'integer', 'string' ],
						'description' => __( 'Optimistic-concurrency guard: the revision_id you last read.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_delete_blocks' ],
			'permission_callback' => $writer_permission,
		] );

		$this->register_ability( 'filter/mutate-block', [
			'label'               => __( 'Mutate Block (structural)', 'filter-abilities' ),
			'description'         => __( 'Perform a structural mutation on the block tree by path (an array of integer child positions, from get-post-blocks) or stable "ref". Operations: update-attrs, update-html, replace-block, remove-block, wrap-in-group, unwrap-group, insert-child, duplicate, move. Pass operation-specific data in "params". Use dry_run to validate without saving.', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id'           => [
						'type'        => 'integer',
						'description' => __( 'The post ID.', 'filter-abilities' ),
					],
					'operation'         => [
						'type'        => 'string',
						'enum'        => [ 'update-attrs', 'update-html', 'replace-block', 'remove-block', 'wrap-in-group', 'unwrap-group', 'insert-child', 'duplicate', 'move' ],
						'description' => __( 'The mutation to perform.', 'filter-abilities' ),
					],
					'path'              => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Path to the target block: child indices from the top level down (e.g. [2, 0] = first child of the third block). Either path or ref is required.', 'filter-abilities' ),
					],
					'ref'               => [
						'type'        => 'string',
						'description' => __( 'Stable ref of the target block, resolved to a path automatically. Alternative to path.', 'filter-abilities' ),
					],
					'params'            => [
						'type'        => 'object',
						'description' => __( 'Operation-specific parameters (e.g. attributes, innerHTML, block definition, destination index, wrapper tag/class).', 'filter-abilities' ),
					],
					'dry_run'           => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Validate and simulate the mutation without saving.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id', 'operation' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_mutate_block' ],
			'permission_callback' => $writer_permission,
		] );

		$this->register_ability( 'filter/batch-edit-blocks', [
			'label'               => __( 'Batch Edit Blocks', 'filter-abilities' ),
			'description'         => __( 'Apply up to 50 independent block updates atomically in a single revision. Each update targets one block by "ref" (preferred) or "flat_index" and sets "attributes" and/or "innerHTML". All-or-nothing: if any update fails, none are saved. Prefer this over many separate update-block calls.', 'filter-abilities' ),
			'category'            => 'filter-blocks',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'post_id'           => [
						'type'        => 'integer',
						'description' => __( 'The post ID.', 'filter-abilities' ),
					],
					'updates'           => [
						'type'        => 'array',
						'description' => __( 'List of independent block updates. Each item targets one block.', 'filter-abilities' ),
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'ref'        => [
									'type'        => 'string',
									'description' => __( 'Stable ref of the block to update. Preferred over flat_index.', 'filter-abilities' ),
								],
								'flat_index' => [
									'type'        => 'integer',
									'description' => __( 'Flat index of the block to update. Use only if you have no ref.', 'filter-abilities' ),
								],
								'attributes' => [ 'type' => 'object' ],
								'innerHTML'  => [ 'type' => 'string' ],
							],
						],
					],
					'verbose'           => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Include a saved snapshot of each block in the result.', 'filter-abilities' ),
					],
					'expected_revision' => [
						'type'        => [ 'integer', 'string' ],
						'description' => __( 'Optimistic-concurrency guard: the revision_id you last read.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'post_id', 'updates' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_batch_edit_blocks' ],
			'permission_callback' => $writer_permission,
		] );
	}

	// =========================================================================
	// Execute callbacks
	// =========================================================================

	/**
	 * Execute the get-post-blocks ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Block tree plus current revision id, or an error.
	 */
	public function execute_get_post_blocks( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$gate    = $this->require_post_edit( $post_id );
		if ( is_wp_error( $gate ) ) {
			return $this->result( $gate );
		}

		$this->boot_engine();

		$render  = ! empty( $input['render'] );
		$persist = array_key_exists( 'persist_refs', $input ) ? (bool) $input['persist_refs'] : true;

		$blocks = $this->crud->get_blocks( $post_id, $render, $persist );
		if ( is_wp_error( $blocks ) ) {
			return $this->result( $blocks );
		}

		return [
			'blocks'      => $blocks,
			'revision_id' => $this->crud->get_latest_revision_id( $post_id ),
		];
	}

	/**
	 * Execute the list-block-types ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Enriched block type list, or an error.
	 */
	public function execute_list_block_types( array $input ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return [ 'error' => __( 'Permission denied.', 'filter-abilities' ) ];
		}

		$this->boot_engine();

		$args = [];
		foreach ( [ 'namespace', 'category', 'tier', 'storage_mode', 'search' ] as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				$args[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}
		if ( ! empty( $input['preferred_only'] ) ) {
			$args['preferred_only'] = true;
		}
		if ( ! empty( $input['usage_only'] ) ) {
			$args['usage_only'] = true;
		}

		return [ 'block_types' => $this->registry->get_block_types( $args ) ];
	}

	/**
	 * Execute the update-block ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Updated block snapshot with revision id, or an error.
	 */
	public function execute_update_block( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$gate    = $this->require_post_edit( $post_id );
		if ( is_wp_error( $gate ) ) {
			return $this->result( $gate );
		}

		$this->boot_engine();

		$concurrency = $this->check_concurrency( $post_id, $input );
		if ( is_wp_error( $concurrency ) ) {
			return $this->result( $concurrency );
		}

		$index = $this->resolve_index( $post_id, $input );
		if ( is_wp_error( $index ) ) {
			return $this->result( $index );
		}

		$attributes = ( isset( $input['attributes'] ) && is_array( $input['attributes'] ) ) ? $input['attributes'] : [];
		$inner_html = array_key_exists( 'inner_html', $input ) ? (string) $input['inner_html'] : null;

		$options = [];
		if ( ! empty( $input['allow_bound_writes'] ) ) {
			$options['allow_bound_writes'] = true;
		}

		return $this->result( $this->crud->update_block( $post_id, $index, $attributes, $inner_html, $options ) );
	}

	/**
	 * Execute the insert-blocks ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Insert result with revision id, or an error.
	 */
	public function execute_insert_blocks( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$gate    = $this->require_post_edit( $post_id );
		if ( is_wp_error( $gate ) ) {
			return $this->result( $gate );
		}

		$blocks = $input['blocks'] ?? null;
		if ( ! is_array( $blocks ) || empty( $blocks ) ) {
			return [ 'error' => __( 'blocks must be a non-empty array of block definitions.', 'filter-abilities' ) ];
		}

		$position = null;
		if ( array_key_exists( 'position', $input ) && null !== $input['position'] ) {
			$position = is_numeric( $input['position'] ) ? (int) $input['position'] : (string) $input['position'];
		}

		$this->boot_engine();

		return $this->result( $this->crud->insert_blocks( $post_id, $position, $blocks ) );
	}

	/**
	 * Execute the delete-blocks ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Delete result with revision id, or an error.
	 */
	public function execute_delete_blocks( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$gate    = $this->require_post_edit( $post_id );
		if ( is_wp_error( $gate ) ) {
			return $this->result( $gate );
		}

		$this->boot_engine();

		$concurrency = $this->check_concurrency( $post_id, $input );
		if ( is_wp_error( $concurrency ) ) {
			return $this->result( $concurrency );
		}

		$index = $this->resolve_index( $post_id, $input );
		if ( is_wp_error( $index ) ) {
			return $this->result( $index );
		}

		$count = isset( $input['count'] ) ? max( 1, absint( $input['count'] ) ) : 1;

		return $this->result( $this->crud->delete_blocks( $post_id, $index, $count ) );
	}

	/**
	 * Execute the mutate-block ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Mutation result with revision ids, or an error.
	 */
	public function execute_mutate_block( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$gate    = $this->require_post_edit( $post_id );
		if ( is_wp_error( $gate ) ) {
			return $this->result( $gate );
		}

		$operation = isset( $input['operation'] ) ? sanitize_text_field( (string) $input['operation'] ) : '';
		if ( '' === $operation ) {
			return [ 'error' => __( 'operation is required.', 'filter-abilities' ) ];
		}

		$this->boot_engine();

		$path = $this->resolve_path( $post_id, $input );
		if ( is_wp_error( $path ) ) {
			return $this->result( $path );
		}

		$params  = ( isset( $input['params'] ) && is_array( $input['params'] ) ) ? $input['params'] : [];
		$dry_run = ! empty( $input['dry_run'] );

		return $this->result( $this->mutator->mutate( $post_id, $operation, $path, $params, $dry_run ) );
	}

	/**
	 * Execute the batch-edit-blocks ability.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed> Batch result with before/after revision ids, or an error.
	 */
	public function execute_batch_edit_blocks( array $input ): array {
		$post_id = absint( $input['post_id'] ?? 0 );
		$gate    = $this->require_post_edit( $post_id );
		if ( is_wp_error( $gate ) ) {
			return $this->result( $gate );
		}

		$updates = $input['updates'] ?? null;
		if ( ! is_array( $updates ) || empty( $updates ) ) {
			return [ 'error' => __( 'updates must be a non-empty array.', 'filter-abilities' ) ];
		}

		$this->boot_engine();

		$concurrency = $this->check_concurrency( $post_id, $input );
		if ( is_wp_error( $concurrency ) ) {
			return $this->result( $concurrency );
		}

		$verbose = ! empty( $input['verbose'] );

		return $this->result( $this->crud->update_blocks_batch( $post_id, $updates, $verbose ) );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Verify the post exists and the current user may edit it.
	 *
	 * Mirrors the coarse-then-fine permission pattern used across the plugin:
	 * the ability permission_callback checks the broad `edit_posts` cap, and
	 * each execute callback re-checks the per-post `edit_post` cap here.
	 *
	 * @since 1.7.0
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error The post, or a WP_Error to surface.
	 */
	private function require_post_edit( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'filter-abilities' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Permission denied.', 'filter-abilities' ) );
		}
		return $post;
	}

	/**
	 * Resolve a target block to a flat index from either a ref or an index.
	 *
	 * @since 1.7.0
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $input   Ability input.
	 * @return int|\WP_Error Flat index, or a WP_Error.
	 */
	private function resolve_index( int $post_id, array $input ) {
		if ( isset( $input['ref'] ) && '' !== $input['ref'] ) {
			return $this->crud->resolve_ref_to_index( $post_id, (string) $input['ref'] );
		}
		if ( isset( $input['index'] ) ) {
			return (int) $input['index'];
		}
		return new \WP_Error( 'missing_target', __( 'Provide either a ref or an index.', 'filter-abilities' ) );
	}

	/**
	 * Resolve a target block to a path from either an explicit path or a ref.
	 *
	 * @since 1.7.0
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $input   Ability input.
	 * @return int[]|\WP_Error Path of child indices, or a WP_Error.
	 */
	private function resolve_path( int $post_id, array $input ) {
		if ( isset( $input['path'] ) && is_array( $input['path'] ) && ! empty( $input['path'] ) ) {
			return array_map( 'intval', $input['path'] );
		}
		if ( isset( $input['ref'] ) && '' !== $input['ref'] ) {
			return $this->crud->resolve_ref( $post_id, (string) $input['ref'] );
		}
		return new \WP_Error( 'missing_target', __( 'Provide either a path or a ref.', 'filter-abilities' ) );
	}

	/**
	 * Apply the optional optimistic-concurrency guard before a write.
	 *
	 * @since 1.7.0
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $input   Ability input.
	 * @return null|\WP_Error null to proceed; WP_Error if the post changed.
	 */
	private function check_concurrency( int $post_id, array $input ) {
		if ( ! array_key_exists( 'expected_revision', $input ) || '' === $input['expected_revision'] ) {
			return null;
		}
		return $this->crud->check_if_match( $post_id, (string) $input['expected_revision'] );
	}

	/**
	 * Normalise an engine return value into the module's array convention.
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $value Engine return value (array|WP_Error).
	 * @return array<string, mixed>
	 */
	private function result( $value ): array {
		if ( is_wp_error( $value ) ) {
			return [ 'error' => $value->get_error_message() ];
		}
		if ( ! is_array( $value ) ) {
			return [ 'error' => __( 'Unexpected engine response.', 'filter-abilities' ) ];
		}
		return $value;
	}
}
