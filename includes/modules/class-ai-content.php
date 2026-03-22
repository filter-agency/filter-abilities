<?php

declare(strict_types=1);

class Filter_Abilities_AI_Content extends Filter_Abilities_Module_Base {

	/**
	 * Register the AI content ability category.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-ai',
			__( 'AI Content', 'filter-abilities' ),
			__( 'Abilities for AI-powered content generation via Filter AI plugin.', 'filter-abilities' )
		);
	}

	/**
	 * Register all AI content abilities (discovery, batch, status, and settings).
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// Discovery abilities.
		$this->register_ability( 'filter/ai-missing-alt-text', [
			'label'               => __( 'Missing Alt Text', 'filter-abilities' ),
			'description'         => __( 'Get count of images missing alt text, broken down by supported and unsupported MIME types.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_images'     => [ 'type' => 'integer' ],
					'missing_supported'   => [ 'type' => 'integer' ],
					'missing_unsupported' => [ 'type' => 'integer' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_missing_alt_text' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/ai-missing-seo-titles', [
			'label'               => __( 'Missing SEO Titles', 'filter-abilities' ),
			'description'         => __( 'Get count of posts missing Yoast SEO titles, broken down by post type.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_missing' => [ 'type' => 'integer' ],
					'by_post_type'  => [
						'type'                 => 'object',
						'additionalProperties' => [ 'type' => 'integer' ],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_missing_seo_titles' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/ai-missing-seo-descriptions', [
			'label'               => __( 'Missing SEO Descriptions', 'filter-abilities' ),
			'description'         => __( 'Get count of posts missing Yoast meta descriptions, broken down by post type.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_missing' => [ 'type' => 'integer' ],
					'by_post_type'  => [
						'type'                 => 'object',
						'additionalProperties' => [ 'type' => 'integer' ],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_missing_seo_descriptions' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		// Batch trigger abilities.
		$this->register_ability( 'filter/ai-batch-alt-text', [
			'label'               => __( 'Batch AI Alt Text', 'filter-abilities' ),
			'description'         => __( 'Start batch AI alt text generation for all images missing alt text. Uses Action Scheduler for async processing.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'queued_count' => [ 'type' => 'integer' ],
					'message'      => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_batch_alt_text' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/ai-batch-seo-titles', [
			'label'               => __( 'Batch AI SEO Titles', 'filter-abilities' ),
			'description'         => __( 'Start batch AI SEO title generation for all posts missing Yoast SEO titles.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'queued_count' => [ 'type' => 'integer' ],
					'message'      => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_batch_seo_titles' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/ai-batch-seo-descriptions', [
			'label'               => __( 'Batch AI SEO Descriptions', 'filter-abilities' ),
			'description'         => __( 'Start batch AI meta description generation for all posts missing Yoast meta descriptions.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'queued_count' => [ 'type' => 'integer' ],
					'message'      => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_batch_seo_descriptions' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		// Status and control abilities.
		$this->register_ability( 'filter/ai-batch-status', [
			'label'               => __( 'Batch Status', 'filter-abilities' ),
			'description'         => __( 'Get current batch processing status (pending, running, complete, failed counts) for a specific batch type.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'batch_type' => [
						'type'        => 'string',
						'description' => __( 'Batch type: alt-text, seo-titles, or seo-descriptions.', 'filter-abilities' ),
						'enum'        => [ 'alt-text', 'seo-titles', 'seo-descriptions' ],
					],
				],
				'required'   => [ 'batch_type' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'batch_type' => [ 'type' => 'string' ],
					'total'      => [ 'type' => 'integer' ],
					'pending'    => [ 'type' => 'integer' ],
					'running'    => [ 'type' => 'integer' ],
					'complete'   => [ 'type' => 'integer' ],
					'failed'     => [ 'type' => 'integer' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_batch_status' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/ai-batch-cancel', [
			'label'               => __( 'Cancel Batch', 'filter-abilities' ),
			'description'         => __( 'Cancel a running batch operation by type.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'batch_type' => [
						'type'        => 'string',
						'description' => __( 'Batch type to cancel: alt-text, seo-titles, or seo-descriptions.', 'filter-abilities' ),
						'enum'        => [ 'alt-text', 'seo-titles', 'seo-descriptions' ],
					],
				],
				'required'   => [ 'batch_type' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'message' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_batch_cancel' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/ai-settings', [
			'label'               => __( 'AI Settings', 'filter-abilities' ),
			'description'         => __( 'Get current Filter AI settings including prompts, enabled features, and brand voice configuration.', 'filter-abilities' ),
			'category'            => 'filter-ai',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'settings' => [ 'type' => 'object' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_ai_settings' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );
	}

	private function get_hook_for_batch_type( string $batch_type ): string {
		$hooks = [
			'alt-text'         => 'filter_ai_batch_image_alt_text',
			'seo-titles'       => 'filter_ai_batch_seo_title',
			'seo-descriptions' => 'filter_ai_batch_seo_meta_description',
		];
		return $hooks[ $batch_type ] ?? '';
	}

	/**
	 * Get count of images missing alt text, split by supported and unsupported MIME types.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, int> Image alt text counts.
	 */
	public function execute_missing_alt_text(): array {
		$total_images     = function_exists( 'filter_ai_get_images_count' ) ? filter_ai_get_images_count() : 0;
		$missing_supported   = function_exists( 'filter_ai_get_images_without_alt_text_count' )
			? filter_ai_get_images_without_alt_text_count( 'supported' ) : 0;
		$missing_unsupported = function_exists( 'filter_ai_get_images_without_alt_text_count' )
			? filter_ai_get_images_without_alt_text_count( 'unsupported' ) : 0;

		return [
			'total_images'        => (int) $total_images,
			'missing_supported'   => (int) $missing_supported,
			'missing_unsupported' => (int) $missing_unsupported,
		];
	}

	/**
	 * Get count of posts missing Yoast SEO titles, grouped by post type.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Missing title counts.
	 */
	public function execute_missing_seo_titles(): array {
		$by_post_type  = [];
		$total_missing = 0;

		foreach ( get_post_types( [ 'public' => true ] ) as $pt ) {
			if ( function_exists( 'filter_ai_get_posts_default_seo_title_count' ) ) {
				$count = (int) filter_ai_get_posts_default_seo_title_count( $pt );
				if ( $count > 0 ) {
					$by_post_type[ $pt ] = $count;
					$total_missing      += $count;
				}
			}
		}

		return [
			'total_missing' => $total_missing,
			'by_post_type'  => $by_post_type,
		];
	}

	/**
	 * Get count of posts missing Yoast meta descriptions, grouped by post type.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Missing description counts.
	 */
	public function execute_missing_seo_descriptions(): array {
		$by_post_type  = [];
		$total_missing = 0;

		foreach ( get_post_types( [ 'public' => true ] ) as $pt ) {
			if ( function_exists( 'filter_ai_get_posts_missing_seo_meta_description_count' ) ) {
				$count = (int) filter_ai_get_posts_missing_seo_meta_description_count( $pt );
				if ( $count > 0 ) {
					$by_post_type[ $pt ] = $count;
					$total_missing      += $count;
				}
			}
		}

		return [
			'total_missing' => $total_missing,
			'by_post_type'  => $by_post_type,
		];
	}

	/**
	 * Queue all images missing alt text for batch AI generation.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Queued count and message, or error.
	 */
	public function execute_batch_alt_text(): array {
		if ( ! function_exists( 'filter_ai_reset_batch' ) || ! function_exists( 'filter_ai_get_images_without_alt_text' ) ) {
			return [ 'error' => __( 'Filter AI batch functions not available.', 'filter-abilities' ) ];
		}

		filter_ai_reset_batch( 'filter_ai_batch_image_alt_text' );

		$queued = 0;
		$paged  = 1;
		$user_id = get_current_user_id();

		do {
			$image_ids = filter_ai_get_images_without_alt_text( $paged, 500 );
			foreach ( $image_ids as $image_id ) {
				as_enqueue_async_action(
					'filter_ai_batch_image_alt_text',
					[ [ 'image_id' => $image_id, 'user_id' => $user_id ] ],
					'filter-ai-current'
				);
				$queued++;
			}
			$paged++;
		} while ( count( $image_ids ) >= 500 );

		return [
			'queued_count' => $queued,
			'message'      => sprintf( __( 'Queued %d images for AI alt text generation.', 'filter-abilities' ), $queued ),
		];
	}

	/**
	 * Queue all posts missing SEO titles for batch AI generation.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Queued count and message, or error.
	 */
	public function execute_batch_seo_titles(): array {
		if ( ! function_exists( 'filter_ai_reset_batch' ) || ! function_exists( 'filter_ai_get_posts_missing_seo_title' ) ) {
			return [ 'error' => __( 'Filter AI batch functions not available.', 'filter-abilities' ) ];
		}

		filter_ai_reset_batch( 'filter_ai_batch_seo_title' );

		$queued  = 0;
		$paged   = 1;
		$user_id = get_current_user_id();

		do {
			$posts = filter_ai_get_posts_missing_seo_title( $paged, 500 );
			foreach ( $posts as $post_id ) {
				as_enqueue_async_action(
					'filter_ai_batch_seo_title',
					[ [ 'post_id' => $post_id, 'user_id' => $user_id ] ],
					'filter-ai-current'
				);
				$queued++;
			}
			$paged++;
		} while ( count( $posts ) >= 500 );

		return [
			'queued_count' => $queued,
			'message'      => sprintf( __( 'Queued %d posts for AI SEO title generation.', 'filter-abilities' ), $queued ),
		];
	}

	/**
	 * Queue all posts missing meta descriptions for batch AI generation.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> Queued count and message, or error.
	 */
	public function execute_batch_seo_descriptions(): array {
		if ( ! function_exists( 'filter_ai_reset_batch' ) || ! function_exists( 'filter_ai_get_posts_missing_seo_meta_description' ) ) {
			return [ 'error' => __( 'Filter AI batch functions not available.', 'filter-abilities' ) ];
		}

		filter_ai_reset_batch( 'filter_ai_batch_seo_meta_description' );

		$queued  = 0;
		$paged   = 1;
		$user_id = get_current_user_id();

		do {
			$posts = filter_ai_get_posts_missing_seo_meta_description( $paged, 500 );
			foreach ( $posts as $post_id ) {
				as_enqueue_async_action(
					'filter_ai_batch_seo_meta_description',
					[ [ 'post_id' => $post_id, 'user_id' => $user_id ] ],
					'filter-ai-current'
				);
				$queued++;
			}
			$paged++;
		} while ( count( $posts ) >= 500 );

		return [
			'queued_count' => $queued,
			'message'      => sprintf( __( 'Queued %d posts for AI meta description generation.', 'filter-abilities' ), $queued ),
		];
	}

	/**
	 * Get current batch processing status for a specific batch type.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $input Ability input parameters.
	 * @return array<string, mixed> Batch status counts or error.
	 */
	public function execute_batch_status( array $input ): array {
		$batch_type = sanitize_text_field( $input['batch_type'] ?? '' );
		$hook       = $this->get_hook_for_batch_type( $batch_type );

		if ( empty( $hook ) ) {
			return [ 'error' => __( 'Invalid batch type.', 'filter-abilities' ) ];
		}

		if ( ! function_exists( 'filter_ai_get_action_count' ) ) {
			return [ 'error' => __( 'Filter AI batch status function not available.', 'filter-abilities' ) ];
		}

		$counts = filter_ai_get_action_count( $hook );

		return [
			'batch_type' => $batch_type,
			'total'      => (int) ( $counts->total ?? 0 ),
			'pending'    => (int) ( $counts->pending ?? 0 ),
			'running'    => (int) ( $counts->running ?? 0 ),
			'complete'   => (int) ( $counts->complete ?? 0 ),
			'failed'     => (int) ( $counts->failed ?? 0 ),
		];
	}

	/**
	 * Cancel all pending actions for a given batch type.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $input Ability input parameters.
	 * @return array<string, mixed> Cancellation message or error.
	 */
	public function execute_batch_cancel( array $input ): array {
		$batch_type = sanitize_text_field( $input['batch_type'] ?? '' );
		$hook       = $this->get_hook_for_batch_type( $batch_type );

		if ( empty( $hook ) ) {
			return [ 'error' => __( 'Invalid batch type.', 'filter-abilities' ) ];
		}

		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return [ 'error' => __( 'Action Scheduler not available.', 'filter-abilities' ) ];
		}

		as_unschedule_all_actions( $hook );

		return [
			'message' => sprintf( __( 'Cancelled all pending %s batch actions.', 'filter-abilities' ), $batch_type ),
		];
	}

	/**
	 * Get current Filter AI plugin settings.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> AI settings data.
	 */
	public function execute_ai_settings(): array {
		$settings = filter_ai_get_settings();

		return [
			'settings' => $settings,
		];
	}
}
