<?php

declare(strict_types=1);

class Filter_Abilities_Personalization extends Filter_Abilities_Module_Base {

	/**
	 * Register personalization ability categories.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-personalization',
			__( 'Personalization', 'filter-abilities' ),
			__( 'Abilities for managing PersonalizeWP rules, segments, contacts, and visitor analytics.', 'filter-abilities' )
		);
	}

	/**
	 * Register all PersonalizeWP abilities.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// --- Configuration abilities ---
		$this->register_ability( 'filter/list-rules', [
			'label'               => __( 'List Personalization Rules', 'filter-abilities' ),
			'description'         => __( 'List all PersonalizeWP rules with their conditions, categories, and status.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'rules' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_rules' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/manage-rule', [
			'label'               => __( 'Manage Personalization Rule', 'filter-abilities' ),
			'description'         => __( 'Create, update, or delete a PersonalizeWP personalization rule with conditions (ALL/ANY logic).', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type' => 'string',
						'enum' => [ 'create', 'update', 'delete' ],
						'description' => __( 'Action: create, update, or delete.', 'filter-abilities' ),
					],
					'rule_id'    => [ 'type' => 'integer', 'description' => __( 'Rule ID (for update/delete).', 'filter-abilities' ) ],
					'name'       => [ 'type' => 'string', 'description' => __( 'Rule name.', 'filter-abilities' ) ],
					'conditions' => [ 'type' => 'array', 'description' => __( 'Array of condition objects.', 'filter-abilities' ) ],
					'operator'   => [ 'type' => 'string', 'enum' => [ 'ALL', 'ANY' ], 'description' => __( 'Boolean operator: ALL or ANY.', 'filter-abilities' ) ],
					'category_id' => [ 'type' => 'integer', 'description' => __( 'Category ID for the rule.', 'filter-abilities' ) ],
				],
				'required' => [ 'action' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_rule' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/list-segments', [
			'label'               => __( 'List Segments', 'filter-abilities' ),
			'description'         => __( 'List PersonalizeWP audience segments with membership counts, conditions, and active status.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'    => [ 'type' => 'integer' ],
					'segments' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_segments' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/manage-segment', [
			'label'               => __( 'Manage Segment', 'filter-abilities' ),
			'description'         => __( 'Create, update, activate, or deactivate a PersonalizeWP audience segment.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type' => 'string',
						'enum' => [ 'create', 'update', 'activate', 'deactivate', 'delete' ],
						'description' => __( 'Action to perform.', 'filter-abilities' ),
					],
					'segment_id' => [ 'type' => 'integer', 'description' => __( 'Segment ID (for update/activate/deactivate/delete).', 'filter-abilities' ) ],
					'name'       => [ 'type' => 'string', 'description' => __( 'Segment name.', 'filter-abilities' ) ],
					'type'       => [ 'type' => 'string', 'description' => __( 'Segment type.', 'filter-abilities' ) ],
					'conditions' => [ 'type' => 'array', 'description' => __( 'Array of condition objects.', 'filter-abilities' ) ],
					'operator'   => [ 'type' => 'string', 'enum' => [ 'ALL', 'ANY' ], 'description' => __( 'Boolean operator.', 'filter-abilities' ) ],
				],
				'required' => [ 'action' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_segment' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/list-scoring-rules', [
			'label'               => __( 'List Scoring Rules', 'filter-abilities' ),
			'description'         => __( 'List PersonalizeWP lead scoring rules with their conditions and point values.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'rules' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_scoring_rules' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/manage-scoring-rule', [
			'label'               => __( 'Manage Scoring Rule', 'filter-abilities' ),
			'description'         => __( 'Create, update, or delete a PersonalizeWP lead scoring rule.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action'  => [ 'type' => 'string', 'enum' => [ 'create', 'update', 'delete' ] ],
					'rule_id' => [ 'type' => 'integer', 'description' => __( 'Rule ID (for update/delete).', 'filter-abilities' ) ],
					'name'       => [ 'type' => 'string' ],
					'lead_score' => [ 'type' => 'integer', 'description' => __( 'Points to award.', 'filter-abilities' ) ],
					'conditions' => [ 'type' => 'array' ],
					'operator'   => [ 'type' => 'string', 'enum' => [ 'ALL', 'ANY' ] ],
				],
				'required' => [ 'action' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_scoring_rule' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		// --- Visitor analytics abilities ---
		$this->register_ability( 'filter/visitor-stats', [
			'label'               => __( 'Visitor Stats', 'filter-abilities' ),
			'description'         => __( 'Get aggregate visitor analytics: total contacts, known vs unknown, new/active in period, average lead score.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'start_date' => [ 'type' => 'string', 'description' => __( 'Start date (YYYY-MM-DD) for period stats.', 'filter-abilities' ) ],
					'end_date'   => [ 'type' => 'string', 'description' => __( 'End date (YYYY-MM-DD) for period stats.', 'filter-abilities' ) ],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_contacts'     => [ 'type' => 'integer' ],
					'known_contacts'     => [ 'type' => 'integer' ],
					'unknown_contacts'   => [ 'type' => 'integer' ],
					'new_in_period'      => [ 'type' => 'integer' ],
					'active_in_period'   => [ 'type' => 'integer' ],
					'average_lead_score' => [ 'type' => 'number' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_visitor_stats' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/list-contacts', [
			'label'               => __( 'List Contacts', 'filter-abilities' ),
			'description'         => __( 'List PersonalizeWP contacts with filtering by segment, lead score range, known/unknown, date range, and name/email search. Paginated.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'segment_id'     => [ 'type' => 'integer', 'description' => __( 'Filter by segment ID.', 'filter-abilities' ) ],
					'min_lead_score' => [ 'type' => 'integer', 'description' => __( 'Minimum lead score.', 'filter-abilities' ) ],
					'max_lead_score' => [ 'type' => 'integer', 'description' => __( 'Maximum lead score.', 'filter-abilities' ) ],
					'is_known'       => [ 'type' => 'boolean', 'description' => __( 'Filter by known (true) or unknown (false).', 'filter-abilities' ) ],
					'search'         => [ 'type' => 'string', 'description' => __( 'Search by name or email.', 'filter-abilities' ) ],
					'start_date'     => [ 'type' => 'string', 'description' => __( 'Filter contacts last seen after this date.', 'filter-abilities' ) ],
					'end_date'       => [ 'type' => 'string', 'description' => __( 'Filter contacts last seen before this date.', 'filter-abilities' ) ],
					'per_page'       => [ 'type' => 'integer', 'default' => 20, 'description' => __( 'Results per page (max 50).', 'filter-abilities' ) ],
					'page'           => [ 'type' => 'integer', 'default' => 1, 'description' => __( 'Page number.', 'filter-abilities' ) ],
					'orderby'        => [ 'type' => 'string', 'default' => 'last_seen', 'description' => __( 'Order by: last_seen, created, lead_score, first_name.', 'filter-abilities' ) ],
					'order'          => [ 'type' => 'string', 'default' => 'DESC', 'description' => __( 'ASC or DESC.', 'filter-abilities' ) ],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'    => [ 'type' => 'integer' ],
					'page'     => [ 'type' => 'integer' ],
					'contacts' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_contacts' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/get-contact', [
			'label'               => __( 'Get Contact', 'filter-abilities' ),
			'description'         => __( 'Get full PersonalizeWP contact profile: fields, metadata, segment memberships, recent activities, and lead score.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'contact_id' => [ 'type' => 'integer', 'description' => __( 'Contact ID.', 'filter-abilities' ) ],
				],
				'required' => [ 'contact_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_get_contact' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/contacts-by-page', [
			'label'               => __( 'Contacts by Page', 'filter-abilities' ),
			'description'         => __( 'Find contacts who visited a URL path (partial LIKE match, e.g. /services/ catches all subpages). Returns visit count and first/last visit dates. Sortable by visit count, last visit, or first visit.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'url_path'   => [ 'type' => 'string', 'description' => __( 'URL path to match (partial match, e.g. /services/).', 'filter-abilities' ) ],
					'start_date' => [ 'type' => 'string', 'description' => __( 'Only include visits after this date (YYYY-MM-DD).', 'filter-abilities' ) ],
					'end_date'   => [ 'type' => 'string', 'description' => __( 'Only include visits before this date (YYYY-MM-DD).', 'filter-abilities' ) ],
					'sort'       => [ 'type' => 'string', 'enum' => [ 'visit_count', 'last_visit', 'first_visit' ], 'default' => 'visit_count', 'description' => __( 'Sort contacts by visit count, most recent visit, or earliest visit.', 'filter-abilities' ) ],
					'per_page'   => [ 'type' => 'integer', 'default' => 20 ],
					'page'       => [ 'type' => 'integer', 'default' => 1 ],
				],
				'required' => [ 'url_path' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'    => [ 'type' => 'integer' ],
					'contacts' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_contacts_by_page' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/contacts-by-segment', [
			'label'               => __( 'Contacts by Segment', 'filter-abilities' ),
			'description'         => __( 'List all contacts in a specific segment with sorting and pagination.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'segment_id' => [ 'type' => 'integer', 'description' => __( 'Segment ID.', 'filter-abilities' ) ],
					'per_page'   => [ 'type' => 'integer', 'default' => 20 ],
					'page'       => [ 'type' => 'integer', 'default' => 1 ],
					'orderby'    => [ 'type' => 'string', 'default' => 'lead_score', 'description' => __( 'Order by: lead_score, last_seen, created.', 'filter-abilities' ) ],
					'order'      => [ 'type' => 'string', 'default' => 'DESC' ],
				],
				'required' => [ 'segment_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'        => [ 'type' => 'integer' ],
					'segment_name' => [ 'type' => 'string' ],
					'contacts'     => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_contacts_by_segment' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/activity-feed', [
			'label'               => __( 'Activity Feed', 'filter-abilities' ),
			'description'         => __( 'Get recent activity feed across all contacts, filtered by activity type, URL path, and date range. Shows contact name/email per activity.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'activity_type' => [ 'type' => 'string', 'description' => __( 'Filter by type: page_view, form_submission, etc. Leave empty for all.', 'filter-abilities' ) ],
					'url_path'      => [ 'type' => 'string', 'description' => __( 'Filter activities by URL path. Partial LIKE match by default, or exact match if exact_url is true.', 'filter-abilities' ) ],
					'exact_url'     => [ 'type' => 'boolean', 'default' => false, 'description' => __( 'If true, match url_path exactly instead of partial LIKE match.', 'filter-abilities' ) ],
					'start_date'    => [ 'type' => 'string', 'description' => __( 'Start date (YYYY-MM-DD).', 'filter-abilities' ) ],
					'end_date'      => [ 'type' => 'string', 'description' => __( 'End date (YYYY-MM-DD).', 'filter-abilities' ) ],
					'per_page'      => [ 'type' => 'integer', 'default' => 20 ],
					'page'          => [ 'type' => 'integer', 'default' => 1 ],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'      => [ 'type' => 'integer' ],
					'activities' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_activity_feed' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/contact-activity-summary', [
			'label'               => __( 'Contact Activity Summary', 'filter-abilities' ),
			'description'         => __( 'For a specific contact: pages visited with counts, forms submitted, activity by type breakdown, first/last visit.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'contact_id' => [ 'type' => 'integer', 'description' => __( 'Contact ID.', 'filter-abilities' ) ],
				],
				'required' => [ 'contact_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_contact_activity_summary' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );
	}

	/**
	 * Verify PersonalizeWP tables exist before querying.
	 *
	 * @since 1.2.0
	 *
	 * @return array|null Error array if tables missing, null if OK.
	 */
	private function check_pwp_tables(): ?array {
		if ( ! $this->table_exists( $this->get_pwp_table( 'contacts' ) ) ) {
			return [ 'error' => __( 'PersonalizeWP database tables not found.', 'filter-abilities' ) ];
		}
		return null;
	}

	// --- Configuration execute methods ---

	/**
	 * List all personalization rules.
	 *
	 * @since 1.2.0
	 *
	 * @return array{total: int, rules: array}
	 */
	public function execute_list_rules(): array {
		global $wpdb;
		$table = $this->get_pwp_table( 'rules' );
		$rules = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

		$result = [];
		foreach ( $rules as $rule ) {
			$result[] = [
				'id'         => (int) $rule['id'],
				'name'       => $rule['name'] ?? '',
				'type'       => $rule['type'] ?? '',
				'conditions' => json_decode( $rule['conditions_json'] ?? '[]', true ) ?? [],
				'operator'   => $rule['operator'] ?? 'ALL',
				'category_id' => (int) ( $rule['category_id'] ?? 0 ),
				'created_at' => $rule['created_at'] ?? '',
			];
		}

		return [
			'total' => count( $result ),
			'rules' => $result,
		];
	}

	/**
	 * Create, update, or delete a personalization rule.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input with 'action' and optional rule fields.
	 * @return array Result with rule_id/message or error.
	 */
	public function execute_manage_rule( array $input ): array {
		global $wpdb;
		$table  = $this->get_pwp_table( 'rules' );
		$action = sanitize_text_field( $input['action'] ?? '' );

		switch ( $action ) {
			case 'create':
				$data = [
					'name'            => sanitize_text_field( $input['name'] ?? '' ),
					'conditions_json' => wp_json_encode( $input['conditions'] ?? [] ),
					'operator'        => sanitize_text_field( $input['operator'] ?? 'ALL' ),
					'category_id'     => absint( $input['category_id'] ?? 0 ),
					'created_at'      => current_time( 'mysql' ),
					'modified_at'     => current_time( 'mysql' ),
				];
				$result = $wpdb->insert( $table, $data );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error creating rule.', 'filter-abilities' ) ];
				}
				return [ 'rule_id' => (int) $wpdb->insert_id, 'message' => __( 'Rule created.', 'filter-abilities' ) ];

			case 'update':
				$rule_id = absint( $input['rule_id'] ?? 0 );
				if ( ! $rule_id ) {
					return [ 'error' => __( 'Rule ID required.', 'filter-abilities' ) ];
				}
				$data = [ 'modified_at' => current_time( 'mysql' ) ];
				if ( isset( $input['name'] ) )       $data['name']            = sanitize_text_field( $input['name'] );
				if ( isset( $input['conditions'] ) )  $data['conditions_json'] = wp_json_encode( $input['conditions'] );
				if ( isset( $input['operator'] ) )    $data['operator']        = sanitize_text_field( $input['operator'] );
				if ( isset( $input['category_id'] ) ) $data['category_id']     = absint( $input['category_id'] );
				$result = $wpdb->update( $table, $data, [ 'id' => $rule_id ] );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error updating rule.', 'filter-abilities' ) ];
				}
				return [ 'rule_id' => $rule_id, 'message' => __( 'Rule updated.', 'filter-abilities' ) ];

			case 'delete':
				$rule_id = absint( $input['rule_id'] ?? 0 );
				if ( ! $rule_id ) {
					return [ 'error' => __( 'Rule ID required.', 'filter-abilities' ) ];
				}
				$result = $wpdb->delete( $table, [ 'id' => $rule_id ] );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error deleting rule.', 'filter-abilities' ) ];
				}
				return [ 'message' => __( 'Rule deleted.', 'filter-abilities' ) ];

			default:
				return [ 'error' => __( 'Invalid action.', 'filter-abilities' ) ];
		}
	}

	/**
	 * List all audience segments.
	 *
	 * @since 1.2.0
	 *
	 * @return array{total: int, segments: array}
	 */
	public function execute_list_segments(): array {
		global $wpdb;
		$table    = $this->get_pwp_table( 'segments' );
		$segments = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY ID DESC", ARRAY_A );

		$result = [];
		foreach ( $segments as $seg ) {
			$result[] = [
				'id'            => (int) $seg['ID'],
				'name'          => $seg['name'] ?? '',
				'type'          => $seg['type'] ?? '',
				'conditions'    => json_decode( $seg['conditions'] ?? '[]', true ) ?? [],
				'operator'      => $seg['operator'] ?? 'ANY',
				'is_active'     => (bool) ( $seg['is_active'] ?? false ),
				'contact_count' => (int) ( $seg['contact_count'] ?? 0 ),
				'created'       => $seg['created'] ?? '',
			];
		}

		return [
			'total'    => count( $result ),
			'segments' => $result,
		];
	}

	/**
	 * Create, update, activate, deactivate, or delete an audience segment.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input with 'action' and optional segment fields.
	 * @return array Result with segment_id/message or error.
	 */
	public function execute_manage_segment( array $input ): array {
		global $wpdb;
		$table  = $this->get_pwp_table( 'segments' );
		$action = sanitize_text_field( $input['action'] ?? '' );

		switch ( $action ) {
			case 'create':
				$data = [
					'name'       => sanitize_text_field( $input['name'] ?? '' ),
					'type'       => sanitize_text_field( $input['type'] ?? '' ),
					'conditions' => wp_json_encode( $input['conditions'] ?? [] ),
					'operator'   => sanitize_text_field( $input['operator'] ?? 'ANY' ),
					'is_active'  => 1,
					'contact_count' => 0,
					'created'    => current_time( 'mysql' ),
				];
				$result = $wpdb->insert( $table, $data );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error creating segment.', 'filter-abilities' ) ];
				}
				return [ 'segment_id' => (int) $wpdb->insert_id, 'message' => __( 'Segment created.', 'filter-abilities' ) ];

			case 'update':
				$segment_id = absint( $input['segment_id'] ?? 0 );
				if ( ! $segment_id ) return [ 'error' => __( 'Segment ID required.', 'filter-abilities' ) ];
				$data = [];
				if ( isset( $input['name'] ) )       $data['name']       = sanitize_text_field( $input['name'] );
				if ( isset( $input['type'] ) )       $data['type']       = sanitize_text_field( $input['type'] );
				if ( isset( $input['conditions'] ) ) $data['conditions'] = wp_json_encode( $input['conditions'] );
				if ( isset( $input['operator'] ) )   $data['operator']   = sanitize_text_field( $input['operator'] );
				$wpdb->update( $table, $data, [ 'ID' => $segment_id ] );
				return [ 'segment_id' => $segment_id, 'message' => __( 'Segment updated.', 'filter-abilities' ) ];

			case 'activate':
				$segment_id = absint( $input['segment_id'] ?? 0 );
				if ( ! $segment_id ) return [ 'error' => __( 'Segment ID required.', 'filter-abilities' ) ];
				$wpdb->update( $table, [ 'is_active' => 1 ], [ 'ID' => $segment_id ] );
				return [ 'segment_id' => $segment_id, 'message' => __( 'Segment activated.', 'filter-abilities' ) ];

			case 'deactivate':
				$segment_id = absint( $input['segment_id'] ?? 0 );
				if ( ! $segment_id ) return [ 'error' => __( 'Segment ID required.', 'filter-abilities' ) ];
				$wpdb->update( $table, [ 'is_active' => 0 ], [ 'ID' => $segment_id ] );
				return [ 'segment_id' => $segment_id, 'message' => __( 'Segment deactivated.', 'filter-abilities' ) ];

			case 'delete':
				$segment_id = absint( $input['segment_id'] ?? 0 );
				if ( ! $segment_id ) return [ 'error' => __( 'Segment ID required.', 'filter-abilities' ) ];
				$wpdb->delete( $table, [ 'ID' => $segment_id ] );
				$wpdb->delete( $this->get_pwp_table( 'segments_relationships' ), [ 'segment_id' => $segment_id ] );
				return [ 'message' => __( 'Segment deleted.', 'filter-abilities' ) ];

			default:
				return [ 'error' => __( 'Invalid action.', 'filter-abilities' ) ];
		}
	}

	/**
	 * List all lead scoring rules.
	 *
	 * @since 1.2.0
	 *
	 * @return array{total: int, rules: array}
	 */
	public function execute_list_scoring_rules(): array {
		global $wpdb;
		$table = $this->get_pwp_table( 'scoring_rules' );
		$rules = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY ID DESC", ARRAY_A );

		$result = [];
		foreach ( $rules as $rule ) {
			$result[] = [
				'id'         => (int) $rule['ID'],
				'name'       => $rule['name'] ?? '',
				'type'       => $rule['type'] ?? '',
				'lead_score' => (int) ( $rule['lead_score'] ?? 0 ),
				'conditions' => json_decode( $rule['conditions'] ?? '[]', true ) ?? [],
				'operator'   => $rule['operator'] ?? 'ALL',
				'created'    => $rule['created'] ?? '',
			];
		}

		return [
			'total' => count( $result ),
			'rules' => $result,
		];
	}

	/**
	 * Create, update, or delete a lead scoring rule.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Ability input with 'action' and optional scoring rule fields.
	 * @return array Result with rule_id/message or error.
	 */
	public function execute_manage_scoring_rule( array $input ): array {
		global $wpdb;
		$table  = $this->get_pwp_table( 'scoring_rules' );
		$action = sanitize_text_field( $input['action'] ?? '' );

		switch ( $action ) {
			case 'create':
				$data = [
					'name'       => sanitize_text_field( $input['name'] ?? '' ),
					'lead_score' => (int) ( $input['lead_score'] ?? 0 ),
					'conditions' => wp_json_encode( $input['conditions'] ?? [] ),
					'operator'   => sanitize_text_field( $input['operator'] ?? 'ALL' ),
					'created'    => current_time( 'mysql' ),
				];
				$result = $wpdb->insert( $table, $data );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error creating scoring rule.', 'filter-abilities' ) ];
				}
				return [ 'rule_id' => (int) $wpdb->insert_id, 'message' => __( 'Scoring rule created.', 'filter-abilities' ) ];

			case 'update':
				$rule_id = absint( $input['rule_id'] ?? 0 );
				if ( ! $rule_id ) return [ 'error' => __( 'Rule ID required.', 'filter-abilities' ) ];
				$data = [];
				if ( isset( $input['name'] ) )       $data['name']       = sanitize_text_field( $input['name'] );
				if ( isset( $input['lead_score'] ) ) $data['lead_score'] = (int) $input['lead_score'];
				if ( isset( $input['conditions'] ) ) $data['conditions'] = wp_json_encode( $input['conditions'] );
				if ( isset( $input['operator'] ) )   $data['operator']   = sanitize_text_field( $input['operator'] );
				$wpdb->update( $table, $data, [ 'ID' => $rule_id ] );
				return [ 'rule_id' => $rule_id, 'message' => __( 'Scoring rule updated.', 'filter-abilities' ) ];

			case 'delete':
				$rule_id = absint( $input['rule_id'] ?? 0 );
				if ( ! $rule_id ) return [ 'error' => __( 'Rule ID required.', 'filter-abilities' ) ];
				$wpdb->delete( $table, [ 'ID' => $rule_id ] );
				return [ 'message' => __( 'Scoring rule deleted.', 'filter-abilities' ) ];

			default:
				return [ 'error' => __( 'Invalid action.', 'filter-abilities' ) ];
		}
	}

	// --- Visitor analytics execute methods ---

	/**
	 * Get aggregate visitor analytics statistics.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Optional 'start_date' and 'end_date' for period stats.
	 * @return array Visitor counts, known/unknown breakdown, and average lead score.
	 */
	public function execute_visitor_stats( array $input ): array {
		$table_error = $this->check_pwp_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$contacts_table = $this->get_pwp_table( 'contacts' );

		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contacts_table}" );
		$known    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contacts_table} WHERE is_known = 1" );
		$unknown  = $total - $known;
		$avg_score = (float) $wpdb->get_var( "SELECT AVG(lead_score) FROM {$contacts_table}" );

		$new_in_period    = 0;
		$active_in_period = 0;

		if ( ! empty( $input['start_date'] ) && ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) || ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$start = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
			$end   = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';

			$new_in_period = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$contacts_table} WHERE created BETWEEN %s AND %s",
				$start,
				$end
			) );

			$active_in_period = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$contacts_table} WHERE last_seen BETWEEN %s AND %s",
				$start,
				$end
			) );
		}

		return [
			'total_contacts'     => $total,
			'known_contacts'     => $known,
			'unknown_contacts'   => $unknown,
			'new_in_period'      => $new_in_period,
			'active_in_period'   => $active_in_period,
			'average_lead_score' => round( $avg_score, 2 ),
		];
	}

	/**
	 * List contacts with filtering, sorting, and pagination.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Filter/sort/pagination parameters.
	 * @return array{total: int, page: int, contacts: array}
	 */
	public function execute_list_contacts( array $input ): array {
		$table_error = $this->check_pwp_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$contacts_table = $this->get_pwp_table( 'contacts' );
		$seg_rel_table  = $this->get_pwp_table( 'segments_relationships' );

		$per_page = max( 1, max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;
		$orderby  = sanitize_text_field( $input['orderby'] ?? 'last_seen' );
		$order    = in_array( strtoupper( $input['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $input['order'] ?? 'DESC' ) : 'DESC';

		// Map input to safe SQL column identifiers to prevent SQL injection via column names.
		$orderby_map = [
			'last_seen'  => 'c.last_seen',
			'created'    => 'c.created',
			'lead_score' => 'c.lead_score',
			'first_name' => 'c.first_name',
		];
		$safe_orderby = $orderby_map[ $orderby ] ?? $orderby_map['last_seen'];

		$where  = [];
		$joins  = '';
		$params = [];

		if ( isset( $input['segment_id'] ) ) {
			$joins   = "INNER JOIN {$seg_rel_table} sr ON c.ID = sr.contact_id";
			$where[] = 'sr.segment_id = %d';
			$params[] = absint( $input['segment_id'] );
		}

		if ( isset( $input['min_lead_score'] ) ) {
			$where[]  = 'c.lead_score >= %d';
			$params[] = absint( $input['min_lead_score'] );
		}

		if ( isset( $input['max_lead_score'] ) ) {
			$where[]  = 'c.lead_score <= %d';
			$params[] = absint( $input['max_lead_score'] );
		}

		if ( isset( $input['is_known'] ) ) {
			$where[]  = 'c.is_known = %d';
			$params[] = $input['is_known'] ? 1 : 0;
		}

		if ( ! empty( $input['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%';
			$where[]  = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'c.last_seen >= %s';
			$params[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'c.last_seen <= %s';
			$params[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Count query.
		$count_sql = "SELECT COUNT(DISTINCT c.ID) FROM {$contacts_table} c {$joins} {$where_sql}";
		$total     = empty( $params )
			? (int) $wpdb->get_var( $count_sql )
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

		// Data query — $safe_orderby and $order are validated above, not user-supplied raw values.
		$data_sql = "SELECT DISTINCT c.* FROM {$contacts_table} c {$joins} {$where_sql} ORDER BY {$safe_orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		$contacts = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$params ), ARRAY_A );

		$result = [];
		foreach ( $contacts as $contact ) {
			$result[] = [
				'id'         => (int) $contact['ID'],
				'first_name' => $contact['first_name'] ?? '',
				'last_name'  => $contact['last_name'] ?? '',
				'email'      => $contact['email'] ?? '',
				'lead_score' => (int) ( $contact['lead_score'] ?? 0 ),
				'is_known'   => (bool) ( $contact['is_known'] ?? false ),
				'created'    => $contact['created'] ?? '',
				'last_seen'  => $contact['last_seen'] ?? '',
			];
		}

		return [
			'total'    => $total,
			'page'     => $page,
			'contacts' => $result,
		];
	}

	/**
	 * Get a full contact profile with metadata, segments, and recent activities.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Must include 'contact_id'.
	 * @return array Contact profile or error.
	 */
	public function execute_get_contact( array $input ): array {
		global $wpdb;
		$contacts_table = $this->get_pwp_table( 'contacts' );
		$meta_table     = $this->get_pwp_table( 'contacts_meta' );
		$activity_table = $this->get_pwp_table( 'activity' );
		$seg_table      = $this->get_pwp_table( 'segments' );
		$seg_rel_table  = $this->get_pwp_table( 'segments_relationships' );

		$contact_id = absint( $input['contact_id'] ?? 0 );

		$contact = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$contacts_table} WHERE ID = %d",
			$contact_id
		), ARRAY_A );

		if ( ! $contact ) {
			return [ 'error' => __( 'Contact not found.', 'filter-abilities' ) ];
		}

		// Get metadata.
		$meta_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$meta_table} WHERE contact_id = %d",
			$contact_id
		), ARRAY_A );
		$metadata = [];
		foreach ( $meta_rows as $row ) {
			$metadata[ $row['meta_key'] ] = $row['meta_value'];
		}

		// Get segments.
		$segments = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.ID, s.name, s.type FROM {$seg_table} s
			 INNER JOIN {$seg_rel_table} sr ON s.ID = sr.segment_id
			 WHERE sr.contact_id = %d",
			$contact_id
		), ARRAY_A );

		// Get recent activities (last 20).
		$activities = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, activity_type, url, object_id, object_type, lead_score, created
			 FROM {$activity_table} WHERE contact_id = %d ORDER BY created DESC LIMIT 20",
			$contact_id
		), ARRAY_A );

		// Total activity count.
		$activity_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$activity_table} WHERE contact_id = %d",
			$contact_id
		) );

		return [
			'id'             => (int) $contact['ID'],
			'uid'            => $contact['uid'] ?? '',
			'first_name'     => $contact['first_name'] ?? '',
			'last_name'      => $contact['last_name'] ?? '',
			'email'          => $contact['email'] ?? '',
			'lead_score'     => (int) ( $contact['lead_score'] ?? 0 ),
			'is_known'       => (bool) ( $contact['is_known'] ?? false ),
			'is_verified'    => (bool) ( $contact['is_verified'] ?? false ),
			'created'        => $contact['created'] ?? '',
			'last_seen'      => $contact['last_seen'] ?? '',
			'metadata'       => $metadata,
			'segments'       => $segments,
			'recent_activities' => $activities,
			'total_activities'  => $activity_count,
		];
	}

	/**
	 * Find contacts who visited a given URL path.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Must include 'url_path'; optional date filters and pagination.
	 * @return array{total: int, contacts: array}
	 */
	public function execute_contacts_by_page( array $input ): array {
		global $wpdb;
		$contacts_table = $this->get_pwp_table( 'contacts' );
		$activity_table = $this->get_pwp_table( 'activity' );

		$url_path = sanitize_text_field( $input['url_path'] ?? '' );
		$per_page = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [ 'a.url LIKE %s' ];
		$params = [ '%' . $wpdb->esc_like( $url_path ) . '%' ];

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'a.created >= %s';
			$params[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}
		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'a.created <= %s';
			$params[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(DISTINCT a.contact_id) FROM {$activity_table} a {$where_sql}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

		$sort = sanitize_text_field( $input['sort'] ?? 'visit_count' );
		$allowed_sort = [ 'visit_count', 'last_visit', 'first_visit' ];
		if ( ! in_array( $sort, $allowed_sort, true ) ) {
			$sort = 'visit_count';
		}

		$data_sql = "SELECT c.ID, c.first_name, c.last_name, c.email, c.lead_score, c.is_known, c.last_seen,
		             COUNT(a.ID) as visit_count, MIN(a.created) as first_visit, MAX(a.created) as last_visit
		             FROM {$activity_table} a
		             INNER JOIN {$contacts_table} c ON a.contact_id = c.ID
		             {$where_sql}
		             GROUP BY c.ID
		             ORDER BY {$sort} DESC
		             LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		$contacts = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$params ), ARRAY_A );

		$result = [];
		foreach ( $contacts as $row ) {
			$result[] = [
				'id'          => (int) $row['ID'],
				'first_name'  => $row['first_name'] ?? '',
				'last_name'   => $row['last_name'] ?? '',
				'email'       => $row['email'] ?? '',
				'lead_score'  => (int) ( $row['lead_score'] ?? 0 ),
				'is_known'    => (bool) ( $row['is_known'] ?? false ),
				'last_seen'   => $row['last_seen'] ?? '',
				'visit_count' => (int) $row['visit_count'],
				'first_visit' => $row['first_visit'] ?? '',
				'last_visit'  => $row['last_visit'] ?? '',
			];
		}

		return [
			'total'    => $total,
			'contacts' => $result,
		];
	}

	/**
	 * List all contacts belonging to a specific segment.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Must include 'segment_id'; optional sorting and pagination.
	 * @return array{total: int, segment_name: string, contacts: array}
	 */
	public function execute_contacts_by_segment( array $input ): array {
		global $wpdb;
		$contacts_table = $this->get_pwp_table( 'contacts' );
		$seg_table      = $this->get_pwp_table( 'segments' );
		$seg_rel_table  = $this->get_pwp_table( 'segments_relationships' );

		$segment_id = absint( $input['segment_id'] ?? 0 );
		$per_page   = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page       = max( absint( $input['page'] ?? 1 ), 1 );
		$offset     = ( $page - 1 ) * $per_page;
		$orderby    = sanitize_text_field( $input['orderby'] ?? 'lead_score' );
		$order      = in_array( strtoupper( $input['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $input['order'] ?? 'DESC' ) : 'DESC';

		// Map input to safe SQL column identifiers to prevent SQL injection via column names.
		$orderby_map = [
			'lead_score' => 'c.lead_score',
			'last_seen'  => 'c.last_seen',
			'created'    => 'c.created',
		];
		$safe_orderby = $orderby_map[ $orderby ] ?? $orderby_map['lead_score'];

		$segment_name = $wpdb->get_var( $wpdb->prepare(
			"SELECT name FROM {$seg_table} WHERE ID = %d",
			$segment_id
		) ) ?: '';

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$seg_rel_table} WHERE segment_id = %d",
			$segment_id
		) );

		// $safe_orderby and $order are validated above, not user-supplied raw values.
		$contacts = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.* FROM {$contacts_table} c
			 INNER JOIN {$seg_rel_table} sr ON c.ID = sr.contact_id
			 WHERE sr.segment_id = %d
			 ORDER BY {$safe_orderby} {$order}
			 LIMIT %d OFFSET %d",
			$segment_id,
			$per_page,
			$offset
		), ARRAY_A );

		$result = [];
		foreach ( $contacts as $contact ) {
			$result[] = [
				'id'         => (int) $contact['ID'],
				'first_name' => $contact['first_name'] ?? '',
				'last_name'  => $contact['last_name'] ?? '',
				'email'      => $contact['email'] ?? '',
				'lead_score' => (int) ( $contact['lead_score'] ?? 0 ),
				'is_known'   => (bool) ( $contact['is_known'] ?? false ),
				'created'    => $contact['created'] ?? '',
				'last_seen'  => $contact['last_seen'] ?? '',
			];
		}

		return [
			'total'        => $total,
			'segment_name' => $segment_name,
			'contacts'     => $result,
		];
	}

	/**
	 * Get a paginated activity feed across all contacts.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Optional filters: activity_type, url_path, date range, pagination.
	 * @return array{total: int, activities: array}
	 */
	public function execute_activity_feed( array $input ): array {
		global $wpdb;
		$activity_table = $this->get_pwp_table( 'activity' );
		$contacts_table = $this->get_pwp_table( 'contacts' );

		$per_page = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [];
		$params = [];

		if ( ! empty( $input['activity_type'] ) ) {
			$where[]  = 'a.activity_type = %s';
			$params[] = sanitize_text_field( $input['activity_type'] );
		}

		if ( ! empty( $input['url_path'] ) ) {
			$url_path = sanitize_text_field( $input['url_path'] );
			if ( ! empty( $input['exact_url'] ) ) {
				$where[]  = 'a.url = %s';
				$params[] = $url_path;
			} else {
				$where[]  = 'a.url LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $url_path ) . '%';
			}
		}

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'a.created >= %s';
			$params[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'a.created <= %s';
			$params[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$activity_table} a {$where_sql}";
		$total     = empty( $params )
			? (int) $wpdb->get_var( $count_sql )
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

		$data_sql = "SELECT a.ID as activity_id, a.activity_type, a.url, a.object_id, a.object_type,
		             a.lead_score, a.created, a.referrer,
		             c.ID as contact_id, c.first_name, c.last_name, c.email
		             FROM {$activity_table} a
		             LEFT JOIN {$contacts_table} c ON a.contact_id = c.ID
		             {$where_sql}
		             ORDER BY a.created DESC
		             LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$params ), ARRAY_A );

		$result = [];
		foreach ( $rows as $row ) {
			$result[] = [
				'activity_id'   => (int) $row['activity_id'],
				'activity_type' => $row['activity_type'],
				'url'           => $row['url'],
				'object_id'     => (int) ( $row['object_id'] ?? 0 ),
				'object_type'   => $row['object_type'] ?? '',
				'lead_score'    => (int) ( $row['lead_score'] ?? 0 ),
				'created'       => $row['created'],
				'referrer'      => $row['referrer'] ?? '',
				'contact_id'    => (int) ( $row['contact_id'] ?? 0 ),
				'contact_name'  => trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ),
				'contact_email' => $row['email'] ?? '',
			];
		}

		return [
			'total'      => $total,
			'activities' => $result,
		];
	}

	/**
	 * Get an activity summary for a specific contact.
	 *
	 * @since 1.2.0
	 *
	 * @param array $input Must include 'contact_id'.
	 * @return array Activity breakdown, top pages, form submissions, or error.
	 */
	public function execute_contact_activity_summary( array $input ): array {
		global $wpdb;
		$activity_table = $this->get_pwp_table( 'activity' );
		$contacts_table = $this->get_pwp_table( 'contacts' );

		$contact_id = absint( $input['contact_id'] ?? 0 );

		$contact = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$contacts_table} WHERE ID = %d",
			$contact_id
		), ARRAY_A );

		if ( ! $contact ) {
			return [ 'error' => __( 'Contact not found.', 'filter-abilities' ) ];
		}

		// Activity by type.
		$by_type = $wpdb->get_results( $wpdb->prepare(
			"SELECT activity_type, COUNT(*) as count FROM {$activity_table}
			 WHERE contact_id = %d GROUP BY activity_type ORDER BY count DESC",
			$contact_id
		), ARRAY_A );

		// Top pages visited.
		$top_pages = $wpdb->get_results( $wpdb->prepare(
			"SELECT url, COUNT(*) as visit_count FROM {$activity_table}
			 WHERE contact_id = %d AND activity_type IN ('post', 'home', 'page_view')
			 GROUP BY url ORDER BY visit_count DESC LIMIT 20",
			$contact_id
		), ARRAY_A );

		// First and last visit.
		$first_visit = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(created) FROM {$activity_table} WHERE contact_id = %d",
			$contact_id
		) );
		$last_visit = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(created) FROM {$activity_table} WHERE contact_id = %d",
			$contact_id
		) );

		// Total activities.
		$total_activities = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$activity_table} WHERE contact_id = %d",
			$contact_id
		) );

		// Form submissions.
		$form_submissions = $wpdb->get_results( $wpdb->prepare(
			"SELECT url, created FROM {$activity_table}
			 WHERE contact_id = %d AND activity_type = 'form_submission'
			 ORDER BY created DESC",
			$contact_id
		), ARRAY_A );

		$activity_breakdown = [];
		foreach ( $by_type as $row ) {
			$activity_breakdown[ $row['activity_type'] ] = (int) $row['count'];
		}

		return [
			'contact_id'         => $contact_id,
			'contact_name'       => trim( ( $contact['first_name'] ?? '' ) . ' ' . ( $contact['last_name'] ?? '' ) ),
			'email'              => $contact['email'] ?? '',
			'lead_score'         => (int) ( $contact['lead_score'] ?? 0 ),
			'total_activities'   => $total_activities,
			'first_visit'        => $first_visit ?: '',
			'last_visit'         => $last_visit ?: '',
			'activity_breakdown' => $activity_breakdown,
			'top_pages'          => $top_pages,
			'form_submissions'   => $form_submissions,
		];
	}
}
