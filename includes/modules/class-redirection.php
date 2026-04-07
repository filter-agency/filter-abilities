<?php

declare(strict_types=1);

class Filter_Abilities_Redirection extends Filter_Abilities_Module_Base {

	/**
	 * Register the Redirection management ability category.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-redirection',
			__( 'Redirection Management', 'filter-abilities' ),
			__( 'Abilities for managing redirects, monitoring 404 errors, and analyzing redirect logs.', 'filter-abilities' )
		);
	}

	/**
	 * Get fully-qualified Redirection table name.
	 *
	 * @since 1.3.0
	 *
	 * @param string $suffix Table suffix: items, groups, logs, or 404.
	 * @return string Full table name with prefix.
	 */
	private function get_redirection_table( string $suffix ): string {
		global $wpdb;
		return $wpdb->prefix . 'redirection_' . $suffix;
	}

	/**
	 * Verify Redirection tables exist before querying.
	 *
	 * @since 1.3.0
	 *
	 * @return array|null Error array if tables missing, null if OK.
	 */
	private function check_redirection_tables(): ?array {
		if ( ! $this->table_exists( $this->get_redirection_table( 'items' ) ) ) {
			return [ 'error' => __( 'Redirection database tables not found.', 'filter-abilities' ) ];
		}
		return null;
	}

	/**
	 * Register all Redirection abilities.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {

		// --- Read-only abilities ---

		$this->register_ability( 'filter/list-redirects', [
			'label'               => __( 'List Redirects', 'filter-abilities' ),
			'description'         => __( 'List redirect rules with filtering by status, group, and search term. Supports pagination and sorting.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'status'   => [
						'type'        => 'string',
						'enum'        => [ 'enabled', 'disabled', 'all' ],
						'default'     => 'all',
						'description' => __( 'Filter by status.', 'filter-abilities' ),
					],
					'group_id' => [
						'type'        => 'integer',
						'description' => __( 'Filter by redirect group ID.', 'filter-abilities' ),
					],
					'search'   => [
						'type'        => 'string',
						'description' => __( 'Search in source URL, target URL, and title.', 'filter-abilities' ),
					],
					'per_page' => [
						'type'        => 'integer',
						'default'     => 25,
						'description' => __( 'Results per page (max 100).', 'filter-abilities' ),
					],
					'page'     => [
						'type'        => 'integer',
						'default'     => 1,
						'description' => __( 'Page number.', 'filter-abilities' ),
					],
					'orderby'  => [
						'type'        => 'string',
						'enum'        => [ 'id', 'url', 'last_count', 'last_access', 'position' ],
						'default'     => 'id',
						'description' => __( 'Sort field.', 'filter-abilities' ),
					],
					'order'    => [
						'type'        => 'string',
						'enum'        => [ 'ASC', 'DESC' ],
						'default'     => 'DESC',
						'description' => __( 'Sort direction.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'     => [ 'type' => 'integer' ],
					'page'      => [ 'type' => 'integer' ],
					'redirects' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_redirects' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/list-redirect-groups', [
			'label'               => __( 'List Redirect Groups', 'filter-abilities' ),
			'description'         => __( 'List redirect groups with their redirect counts and status.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'status' => [
						'type'        => 'string',
						'enum'        => [ 'enabled', 'disabled', 'all' ],
						'default'     => 'all',
						'description' => __( 'Filter by group status.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'  => [ 'type' => 'integer' ],
					'groups' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_redirect_groups' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/list-404-errors', [
			'label'               => __( 'List 404 Errors', 'filter-abilities' ),
			'description'         => __( 'View 404 errors logged by the Redirection plugin. Use group_by_url to aggregate by URL and see which missing pages are hit most frequently.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'search'       => [
						'type'        => 'string',
						'description' => __( 'Search in URL or referrer.', 'filter-abilities' ),
					],
					'start_date'   => [
						'type'        => 'string',
						'description' => __( 'Filter after this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
					'end_date'     => [
						'type'        => 'string',
						'description' => __( 'Filter before this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
					'per_page'     => [
						'type'        => 'integer',
						'default'     => 25,
						'description' => __( 'Results per page (max 100).', 'filter-abilities' ),
					],
					'page'         => [
						'type'        => 'integer',
						'default'     => 1,
						'description' => __( 'Page number.', 'filter-abilities' ),
					],
					'group_by_url' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Group results by URL with hit count, first/last seen, and sample referrers.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total'  => [ 'type' => 'integer' ],
					'page'   => [ 'type' => 'integer' ],
					'errors' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_404_errors' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/get-redirect-logs', [
			'label'               => __( 'Get Redirect Logs', 'filter-abilities' ),
			'description'         => __( 'View the redirect hit log to verify redirects are working and see traffic patterns.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'redirect_id' => [
						'type'        => 'integer',
						'description' => __( 'Filter by specific redirect rule ID.', 'filter-abilities' ),
					],
					'search'      => [
						'type'        => 'string',
						'description' => __( 'Search in URL or target.', 'filter-abilities' ),
					],
					'start_date'  => [
						'type'        => 'string',
						'description' => __( 'Filter after this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
					'end_date'    => [
						'type'        => 'string',
						'description' => __( 'Filter before this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
					'per_page'    => [
						'type'        => 'integer',
						'default'     => 25,
						'description' => __( 'Results per page (max 100).', 'filter-abilities' ),
					],
					'page'        => [
						'type'        => 'integer',
						'default'     => 1,
						'description' => __( 'Page number.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'page'  => [ 'type' => 'integer' ],
					'logs'  => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_redirect_logs' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/redirect-stats', [
			'label'               => __( 'Redirect Stats', 'filter-abilities' ),
			'description'         => __( 'Get aggregate redirect statistics: total/enabled/disabled redirects, 404 counts, top 404 URLs, and most-used redirects in a given period.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'period_days' => [
						'type'        => 'integer',
						'default'     => 30,
						'description' => __( 'Number of days to analyze (default 30).', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_redirects'          => [ 'type' => 'integer' ],
					'enabled_redirects'        => [ 'type' => 'integer' ],
					'disabled_redirects'       => [ 'type' => 'integer' ],
					'total_groups'             => [ 'type' => 'integer' ],
					'total_404s_in_period'     => [ 'type' => 'integer' ],
					'unique_404_urls_in_period' => [ 'type' => 'integer' ],
					'top_404_urls'             => [ 'type' => 'array' ],
					'total_hits_in_period'     => [ 'type' => 'integer' ],
					'most_used_redirects'      => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_redirect_stats' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/check-redirect', [
			'label'               => __( 'Check Redirect', 'filter-abilities' ),
			'description'         => __( 'Check if a URL path has a matching redirect rule. Useful for verifying a redirect exists or testing before creating a new one.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'url' => [
						'type'        => 'string',
						'description' => __( 'URL path to check (e.g. /old-page/).', 'filter-abilities' ),
					],
				],
				'required'   => [ 'url' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'has_redirect' => [ 'type' => 'boolean' ],
					'matches'      => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_check_redirect' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		// --- Write abilities ---

		$this->register_ability( 'filter/manage-redirect', [
			'label'               => __( 'Manage Redirect', 'filter-abilities' ),
			'description'         => __( 'Create, update, or delete a redirect rule.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action'      => [
						'type'        => 'string',
						'enum'        => [ 'create', 'update', 'delete' ],
						'description' => __( 'Action: create, update, or delete.', 'filter-abilities' ),
					],
					'redirect_id' => [
						'type'        => 'integer',
						'description' => __( 'Redirect ID (required for update/delete).', 'filter-abilities' ),
					],
					'source_url'  => [
						'type'        => 'string',
						'description' => __( 'Source URL path (e.g. /old-page/). Required for create.', 'filter-abilities' ),
					],
					'target_url'  => [
						'type'        => 'string',
						'description' => __( 'Target URL to redirect to.', 'filter-abilities' ),
					],
					'action_type' => [
						'type'        => 'string',
						'enum'        => [ 'url', 'random', 'pass', 'error', 'nothing' ],
						'default'     => 'url',
						'description' => __( 'Redirect action type.', 'filter-abilities' ),
					],
					'action_code' => [
						'type'        => 'integer',
						'enum'        => [ 301, 302, 303, 304, 307, 308, 404, 410 ],
						'default'     => 301,
						'description' => __( 'HTTP status code.', 'filter-abilities' ),
					],
					'group_id'    => [
						'type'        => 'integer',
						'description' => __( 'Group ID to assign the redirect to.', 'filter-abilities' ),
					],
					'regex'       => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether the source URL is a regular expression.', 'filter-abilities' ),
					],
					'match_type'  => [
						'type'        => 'string',
						'enum'        => [ 'url', 'login', 'role', 'referrer', 'agent', 'cookie', 'header', 'custom', 'language', 'server', 'ip' ],
						'default'     => 'url',
						'description' => __( 'Match type for the redirect.', 'filter-abilities' ),
					],
					'title'       => [
						'type'        => 'string',
						'description' => __( 'Descriptive title for the redirect.', 'filter-abilities' ),
					],
					'status'      => [
						'type'        => 'string',
						'enum'        => [ 'enabled', 'disabled' ],
						'description' => __( 'Enable or disable the redirect.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'action' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'redirect_id' => [ 'type' => 'integer' ],
					'action'      => [ 'type' => 'string' ],
					'message'     => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_manage_redirect' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );

		$this->register_ability( 'filter/bulk-manage-redirects', [
			'label'               => __( 'Bulk Manage Redirects', 'filter-abilities' ),
			'description'         => __( 'Enable, disable, delete, or reset (clear hit counter) multiple redirects at once.', 'filter-abilities' ),
			'category'            => 'filter-redirection',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'action'       => [
						'type'        => 'string',
						'enum'        => [ 'enable', 'disable', 'delete', 'reset' ],
						'description' => __( 'Bulk action to perform.', 'filter-abilities' ),
					],
					'redirect_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Array of redirect IDs to act on.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'action', 'redirect_ids' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'action'   => [ 'type' => 'string' ],
					'affected' => [ 'type' => 'integer' ],
					'message'  => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_bulk_manage_redirects' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );
	}

	// =========================================================================
	// Read-only execute methods
	// =========================================================================

	/**
	 * List redirect rules with filtering, sorting, and pagination.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Filter/sort/pagination parameters.
	 * @return array<string, mixed> Paginated list of redirects.
	 */
	public function execute_list_redirects( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$items_table  = $this->get_redirection_table( 'items' );
		$groups_table = $this->get_redirection_table( 'groups' );

		$per_page = max( 1, min( absint( $input['per_page'] ?? 25 ), 100 ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;
		$order    = in_array( strtoupper( $input['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true )
			? strtoupper( $input['order'] ?? 'DESC' )
			: 'DESC';

		$orderby_map = [
			'id'          => 'r.id',
			'url'         => 'r.url',
			'last_count'  => 'r.last_count',
			'last_access' => 'r.last_access',
			'position'    => 'r.position',
		];
		$safe_orderby = $orderby_map[ $input['orderby'] ?? 'id' ] ?? $orderby_map['id'];

		$where  = [];
		$params = [];

		$status = sanitize_text_field( $input['status'] ?? 'all' );
		if ( 'enabled' === $status ) {
			$where[] = 'r.status = %s';
			$params[] = 'enabled';
		} elseif ( 'disabled' === $status ) {
			$where[] = 'r.status = %s';
			$params[] = 'disabled';
		}

		if ( ! empty( $input['group_id'] ) ) {
			$where[]  = 'r.group_id = %d';
			$params[] = absint( $input['group_id'] );
		}

		if ( ! empty( $input['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%';
			$where[]  = '(r.url LIKE %s OR r.action_data LIKE %s OR r.title LIKE %s)';
			$params[] = $search;
			$params[] = $search;
			$params[] = $search;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Count query.
		$count_sql = "SELECT COUNT(*) FROM {$items_table} r {$where_sql}";
		$total     = ! empty( $params )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: (int) $wpdb->get_var( $count_sql );

		// Data query.
		$data_sql = "SELECT r.*, g.name AS group_name
			FROM {$items_table} r
			LEFT JOIN {$groups_table} g ON r.group_id = g.id
			{$where_sql}
			ORDER BY {$safe_orderby} {$order}
			LIMIT %d OFFSET %d";

		$query_params = array_merge( $params, [ $per_page, $offset ] );
		$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$query_params ), ARRAY_A );

		$redirects = [];
		foreach ( $rows as $row ) {
			$redirects[] = [
				'id'          => (int) $row['id'],
				'url'         => $row['url'] ?? '',
				'match_url'   => $row['match_url'] ?? '',
				'action_type' => $row['action_type'] ?? '',
				'action_code' => (int) ( $row['action_code'] ?? 0 ),
				'action_data' => $row['action_data'] ?? '',
				'match_type'  => $row['match_type'] ?? 'url',
				'group_id'    => (int) ( $row['group_id'] ?? 0 ),
				'group_name'  => $row['group_name'] ?? '',
				'status'      => $row['status'] ?? 'enabled',
				'regex'       => (bool) ( $row['regex'] ?? false ),
				'last_count'  => (int) ( $row['last_count'] ?? 0 ),
				'last_access' => $row['last_access'] ?? '',
				'title'       => $row['title'] ?? '',
				'position'    => (int) ( $row['position'] ?? 0 ),
			];
		}

		return [
			'total'     => $total,
			'page'      => $page,
			'redirects' => $redirects,
		];
	}

	/**
	 * List redirect groups with redirect counts.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Filter parameters.
	 * @return array<string, mixed> List of groups.
	 */
	public function execute_list_redirect_groups( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$groups_table = $this->get_redirection_table( 'groups' );
		$items_table  = $this->get_redirection_table( 'items' );

		$where  = [];
		$params = [];

		$status = sanitize_text_field( $input['status'] ?? 'all' );
		if ( 'enabled' === $status ) {
			$where[] = 'g.status = %s';
			$params[] = 'enabled';
		} elseif ( 'disabled' === $status ) {
			$where[] = 'g.status = %s';
			$params[] = 'disabled';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$sql = "SELECT g.*, COUNT(r.id) AS redirect_count
			FROM {$groups_table} g
			LEFT JOIN {$items_table} r ON g.id = r.group_id
			{$where_sql}
			GROUP BY g.id
			ORDER BY g.position ASC, g.id ASC";

		$rows = ! empty( $params )
			? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		$groups = [];
		foreach ( $rows as $row ) {
			$groups[] = [
				'id'             => (int) $row['id'],
				'name'           => $row['name'] ?? '',
				'module_id'      => (int) ( $row['module_id'] ?? 1 ),
				'status'         => $row['status'] ?? 'enabled',
				'position'       => (int) ( $row['position'] ?? 0 ),
				'redirect_count' => (int) ( $row['redirect_count'] ?? 0 ),
			];
		}

		return [
			'total'  => count( $groups ),
			'groups' => $groups,
		];
	}

	/**
	 * List 404 errors with optional grouping by URL.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Filter/pagination parameters.
	 * @return array<string, mixed> Paginated list of 404 errors.
	 */
	public function execute_list_404_errors( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$table = $this->get_redirection_table( '404' );

		if ( ! $this->table_exists( $table ) ) {
			return [ 'error' => __( 'Redirection 404 log table not found.', 'filter-abilities' ) ];
		}

		$per_page     = max( 1, min( absint( $input['per_page'] ?? 25 ), 100 ) );
		$page         = max( absint( $input['page'] ?? 1 ), 1 );
		$offset       = ( $page - 1 ) * $per_page;
		$group_by_url = (bool) ( $input['group_by_url'] ?? false );

		$where  = [];
		$params = [];

		if ( ! empty( $input['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%';
			$where[]  = '(url LIKE %s OR referrer LIKE %s)';
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'created >= %s';
			$params[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'created <= %s';
			$params[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( $group_by_url ) {
			// Grouped mode: aggregate by URL.
			$count_sql = "SELECT COUNT(*) FROM (SELECT url FROM {$table} {$where_sql} GROUP BY url) AS grouped";
			$total     = ! empty( $params )
				? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
				: (int) $wpdb->get_var( $count_sql );

			$data_sql = "SELECT url, COUNT(*) AS hit_count,
					MIN(created) AS first_seen, MAX(created) AS last_seen,
					SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT referrer ORDER BY created DESC SEPARATOR '||'), '||', 5) AS sample_referrers
				FROM {$table}
				{$where_sql}
				GROUP BY url
				ORDER BY hit_count DESC
				LIMIT %d OFFSET %d";

			$query_params = array_merge( $params, [ $per_page, $offset ] );
			$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$query_params ), ARRAY_A );

			$errors = [];
			foreach ( $rows as $row ) {
				$referrers = array_filter( explode( '||', $row['sample_referrers'] ?? '' ) );
				$errors[]  = [
					'url'              => $row['url'] ?? '',
					'hit_count'        => (int) ( $row['hit_count'] ?? 0 ),
					'first_seen'       => $row['first_seen'] ?? '',
					'last_seen'        => $row['last_seen'] ?? '',
					'sample_referrers' => $referrers,
				];
			}
		} else {
			// Normal mode: individual entries.
			$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
			$total     = ! empty( $params )
				? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
				: (int) $wpdb->get_var( $count_sql );

			$data_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created DESC LIMIT %d OFFSET %d";
			$query_params = array_merge( $params, [ $per_page, $offset ] );
			$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$query_params ), ARRAY_A );

			$errors = [];
			foreach ( $rows as $row ) {
				$errors[] = [
					'id'             => (int) $row['id'],
					'url'            => $row['url'] ?? '',
					'referrer'       => $row['referrer'] ?? '',
					'agent'          => $row['agent'] ?? '',
					'created'        => $row['created'] ?? '',
					'ip'             => $row['ip'] ?? '',
					'http_code'      => (int) ( $row['http_code'] ?? 404 ),
					'request_method' => $row['request_method'] ?? 'GET',
				];
			}
		}

		return [
			'total'  => $total,
			'page'   => $page,
			'errors' => $errors,
		];
	}

	/**
	 * Get redirect hit logs.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Filter/pagination parameters.
	 * @return array<string, mixed> Paginated list of redirect logs.
	 */
	public function execute_get_redirect_logs( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$table = $this->get_redirection_table( 'logs' );

		if ( ! $this->table_exists( $table ) ) {
			return [ 'error' => __( 'Redirection log table not found.', 'filter-abilities' ) ];
		}

		$per_page = max( 1, min( absint( $input['per_page'] ?? 25 ), 100 ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [];
		$params = [];

		if ( ! empty( $input['redirect_id'] ) ) {
			$where[]  = 'redirection_id = %d';
			$params[] = absint( $input['redirect_id'] );
		}

		if ( ! empty( $input['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%';
			$where[]  = '(url LIKE %s OR sent_to LIKE %s)';
			$params[] = $search;
			$params[] = $search;
		}

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'created >= %s';
			$params[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}

		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where[]  = 'created <= %s';
			$params[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		$total     = ! empty( $params )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
			: (int) $wpdb->get_var( $count_sql );

		$data_sql     = "SELECT * FROM {$table} {$where_sql} ORDER BY created DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, [ $per_page, $offset ] );
		$rows         = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$query_params ), ARRAY_A );

		$logs = [];
		foreach ( $rows as $row ) {
			$logs[] = [
				'id'             => (int) $row['id'],
				'created'        => $row['created'] ?? '',
				'url'            => $row['url'] ?? '',
				'sent_to'        => $row['sent_to'] ?? '',
				'agent'          => $row['agent'] ?? '',
				'referrer'       => $row['referrer'] ?? '',
				'http_code'      => (int) ( $row['http_code'] ?? 0 ),
				'request_method' => $row['request_method'] ?? 'GET',
				'redirect_by'    => $row['redirect_by'] ?? '',
				'redirection_id' => (int) ( $row['redirection_id'] ?? 0 ),
			];
		}

		return [
			'total' => $total,
			'page'  => $page,
			'logs'  => $logs,
		];
	}

	/**
	 * Get aggregate redirect statistics.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Period parameters.
	 * @return array<string, mixed> Redirect health overview.
	 */
	public function execute_redirect_stats( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$items_table  = $this->get_redirection_table( 'items' );
		$groups_table = $this->get_redirection_table( 'groups' );
		$logs_table   = $this->get_redirection_table( 'logs' );
		$four04_table = $this->get_redirection_table( '404' );

		$period_days = max( 1, absint( $input['period_days'] ?? 30 ) );
		$since       = gmdate( 'Y-m-d H:i:s', strtotime( "-{$period_days} days" ) );

		// Redirect counts.
		$total_redirects   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$items_table}" );
		$enabled_redirects = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$items_table} WHERE status = %s", 'enabled' )
		);
		$disabled_redirects = $total_redirects - $enabled_redirects;
		$total_groups       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$groups_table}" );

		// 404 stats in period.
		$total_404s     = 0;
		$unique_404_urls = 0;
		$top_404_urls   = [];

		if ( $this->table_exists( $four04_table ) ) {
			$total_404s = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$four04_table} WHERE created >= %s",
				$since
			) );

			$unique_404_urls = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT url) FROM {$four04_table} WHERE created >= %s",
				$since
			) );

			$top_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT url, COUNT(*) AS hit_count
				FROM {$four04_table}
				WHERE created >= %s
				GROUP BY url
				ORDER BY hit_count DESC
				LIMIT 10",
				$since
			), ARRAY_A );

			foreach ( $top_rows as $row ) {
				$top_404_urls[] = [
					'url'   => $row['url'] ?? '',
					'count' => (int) ( $row['hit_count'] ?? 0 ),
				];
			}
		}

		// Redirect hit stats in period.
		$total_hits          = 0;
		$most_used_redirects = [];

		if ( $this->table_exists( $logs_table ) ) {
			$total_hits = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$logs_table} WHERE created >= %s",
				$since
			) );

			$top_redirects = $wpdb->get_results( $wpdb->prepare(
				"SELECT l.redirection_id, l.url, l.sent_to, COUNT(*) AS hit_count
				FROM {$logs_table} l
				WHERE l.created >= %s AND l.redirection_id > 0
				GROUP BY l.redirection_id, l.url, l.sent_to
				ORDER BY hit_count DESC
				LIMIT 10",
				$since
			), ARRAY_A );

			foreach ( $top_redirects as $row ) {
				$most_used_redirects[] = [
					'id'        => (int) ( $row['redirection_id'] ?? 0 ),
					'url'       => $row['url'] ?? '',
					'target'    => $row['sent_to'] ?? '',
					'hit_count' => (int) ( $row['hit_count'] ?? 0 ),
				];
			}
		}

		return [
			'total_redirects'           => $total_redirects,
			'enabled_redirects'         => $enabled_redirects,
			'disabled_redirects'        => $disabled_redirects,
			'total_groups'              => $total_groups,
			'total_404s_in_period'      => $total_404s,
			'unique_404_urls_in_period' => $unique_404_urls,
			'top_404_urls'              => $top_404_urls,
			'total_hits_in_period'      => $total_hits,
			'most_used_redirects'       => $most_used_redirects,
		];
	}

	/**
	 * Check if a URL path has a matching redirect rule.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input URL to check.
	 * @return array<string, mixed> Whether a redirect exists and match details.
	 */
	public function execute_check_redirect( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$items_table  = $this->get_redirection_table( 'items' );
		$groups_table = $this->get_redirection_table( 'groups' );
		$url          = sanitize_text_field( $input['url'] ?? '' );

		if ( empty( $url ) ) {
			return [ 'error' => __( 'URL is required.', 'filter-abilities' ) ];
		}

		$matches = [];

		// Check exact matches on the url column.
		$exact_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, g.name AS group_name
			FROM {$items_table} r
			LEFT JOIN {$groups_table} g ON r.group_id = g.id
			WHERE r.regex = 0 AND (r.url = %s OR r.match_url = %s)",
			$url,
			$url
		), ARRAY_A );

		foreach ( $exact_rows as $row ) {
			$matches[] = $this->format_redirect_match( $row );
		}

		// Check regex matches.
		$regex_rows = $wpdb->get_results(
			"SELECT r.*, g.name AS group_name
			FROM {$items_table} r
			LEFT JOIN {$groups_table} g ON r.group_id = g.id
			WHERE r.regex = 1",
			ARRAY_A
		);

		foreach ( $regex_rows as $row ) {
			$pattern = $row['url'] ?? '';
			if ( empty( $pattern ) ) {
				continue;
			}
			// Redirection uses unanchored patterns with case-insensitive matching.
			if ( @preg_match( '@' . str_replace( '@', '\\@', $pattern ) . '@i', $url ) ) {
				$matches[] = $this->format_redirect_match( $row );
			}
		}

		return [
			'has_redirect' => ! empty( $matches ),
			'matches'      => $matches,
		];
	}

	/**
	 * Format a redirect row into a match result array.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed> Formatted match.
	 */
	private function format_redirect_match( array $row ): array {
		return [
			'id'          => (int) $row['id'],
			'source_url'  => $row['url'] ?? '',
			'target_url'  => $row['action_data'] ?? '',
			'action_type' => $row['action_type'] ?? '',
			'action_code' => (int) ( $row['action_code'] ?? 0 ),
			'match_type'  => $row['match_type'] ?? 'url',
			'status'      => $row['status'] ?? 'enabled',
			'regex'       => (bool) ( $row['regex'] ?? false ),
			'group_name'  => $row['group_name'] ?? '',
			'last_count'  => (int) ( $row['last_count'] ?? 0 ),
			'last_access' => $row['last_access'] ?? '',
			'title'       => $row['title'] ?? '',
		];
	}

	// =========================================================================
	// Write execute methods
	// =========================================================================

	/**
	 * Create, update, or delete a redirect rule.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Action and redirect parameters.
	 * @return array<string, mixed> Result with redirect_id and message, or error.
	 */
	public function execute_manage_redirect( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$table  = $this->get_redirection_table( 'items' );
		$action = sanitize_text_field( $input['action'] ?? '' );

		switch ( $action ) {
			case 'create':
				$source_url = sanitize_text_field( $input['source_url'] ?? '' );
				if ( empty( $source_url ) ) {
					return [ 'error' => __( 'source_url is required for creating a redirect.', 'filter-abilities' ) ];
				}

				$group_id = absint( $input['group_id'] ?? 0 );
				if ( ! $group_id ) {
					// Use the first available group.
					$groups_table = $this->get_redirection_table( 'groups' );
					$group_id     = (int) $wpdb->get_var( "SELECT id FROM {$groups_table} ORDER BY position ASC LIMIT 1" );
					if ( ! $group_id ) {
						return [ 'error' => __( 'No redirect groups found. Create a group in the Redirection plugin first.', 'filter-abilities' ) ];
					}
				}

				$action_type = sanitize_text_field( $input['action_type'] ?? 'url' );
				$action_code = absint( $input['action_code'] ?? 301 );
				$match_type  = sanitize_text_field( $input['match_type'] ?? 'url' );
				$regex       = (bool) ( $input['regex'] ?? false );
				$title       = sanitize_text_field( $input['title'] ?? '' );
				$target_url  = esc_url_raw( $input['target_url'] ?? '' );
				$status      = sanitize_text_field( $input['status'] ?? 'enabled' );

				// Build match_url: lowercased version for non-regex, same as url for regex.
				$match_url = $regex ? $source_url : strtolower( $source_url );

				$data = [
					'url'         => $source_url,
					'match_url'   => $match_url,
					'match_type'  => $match_type,
					'action_type' => $action_type,
					'action_code' => $action_code,
					'action_data' => $target_url,
					'regex'       => $regex ? 1 : 0,
					'group_id'    => $group_id,
					'status'      => $status,
					'position'    => 0,
					'last_count'  => 0,
					'last_access' => '0000-00-00 00:00:00',
					'title'       => $title,
				];

				// Add match_data for non-url match types.
				if ( 'url' !== $match_type ) {
					$data['match_data'] = wp_json_encode( [ 'source' => [ 'flag_query' => 'exact', 'flag_case' => false, 'flag_trailing' => false ] ] );
				}

				$result = $wpdb->insert( $table, $data );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error creating redirect.', 'filter-abilities' ) ];
				}

				return [
					'redirect_id' => (int) $wpdb->insert_id,
					'action'      => 'create',
					'message'     => __( 'Redirect created.', 'filter-abilities' ),
				];

			case 'update':
				$redirect_id = absint( $input['redirect_id'] ?? 0 );
				if ( ! $redirect_id ) {
					return [ 'error' => __( 'redirect_id is required for updating.', 'filter-abilities' ) ];
				}

				// Verify redirect exists.
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$table} WHERE id = %d",
					$redirect_id
				) );
				if ( ! $exists ) {
					return [ 'error' => __( 'Redirect not found.', 'filter-abilities' ) ];
				}

				$data = [];

				if ( isset( $input['source_url'] ) ) {
					$source_url        = sanitize_text_field( $input['source_url'] );
					$data['url']       = $source_url;
					$data['match_url'] = strtolower( $source_url );
				}
				if ( isset( $input['target_url'] ) ) {
					$data['action_data'] = esc_url_raw( $input['target_url'] );
				}
				if ( isset( $input['action_type'] ) ) {
					$data['action_type'] = sanitize_text_field( $input['action_type'] );
				}
				if ( isset( $input['action_code'] ) ) {
					$data['action_code'] = absint( $input['action_code'] );
				}
				if ( isset( $input['group_id'] ) ) {
					$data['group_id'] = absint( $input['group_id'] );
				}
				if ( isset( $input['regex'] ) ) {
					$data['regex'] = $input['regex'] ? 1 : 0;
				}
				if ( isset( $input['match_type'] ) ) {
					$data['match_type'] = sanitize_text_field( $input['match_type'] );
				}
				if ( isset( $input['title'] ) ) {
					$data['title'] = sanitize_text_field( $input['title'] );
				}
				if ( isset( $input['status'] ) ) {
					$data['status'] = sanitize_text_field( $input['status'] );
				}

				if ( empty( $data ) ) {
					return [ 'error' => __( 'No fields provided to update.', 'filter-abilities' ) ];
				}

				$result = $wpdb->update( $table, $data, [ 'id' => $redirect_id ] );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error updating redirect.', 'filter-abilities' ) ];
				}

				return [
					'redirect_id' => $redirect_id,
					'action'      => 'update',
					'message'     => __( 'Redirect updated.', 'filter-abilities' ),
				];

			case 'delete':
				$redirect_id = absint( $input['redirect_id'] ?? 0 );
				if ( ! $redirect_id ) {
					return [ 'error' => __( 'redirect_id is required for deleting.', 'filter-abilities' ) ];
				}

				$result = $wpdb->delete( $table, [ 'id' => $redirect_id ] );
				if ( false === $result ) {
					return [ 'error' => __( 'Database error deleting redirect.', 'filter-abilities' ) ];
				}

				return [
					'redirect_id' => $redirect_id,
					'action'      => 'delete',
					'message'     => __( 'Redirect deleted.', 'filter-abilities' ),
				];

			default:
				return [ 'error' => __( 'Invalid action. Use create, update, or delete.', 'filter-abilities' ) ];
		}
	}

	/**
	 * Bulk enable, disable, delete, or reset multiple redirects.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $input Bulk action and redirect IDs.
	 * @return array<string, mixed> Result with affected count.
	 */
	public function execute_bulk_manage_redirects( array $input ): array {
		$table_error = $this->check_redirection_tables();
		if ( $table_error ) {
			return $table_error;
		}

		global $wpdb;
		$table  = $this->get_redirection_table( 'items' );
		$action = sanitize_text_field( $input['action'] ?? '' );
		$ids    = array_map( 'absint', (array) ( $input['redirect_ids'] ?? [] ) );
		$ids    = array_filter( $ids );

		if ( empty( $ids ) ) {
			return [ 'error' => __( 'No redirect IDs provided.', 'filter-abilities' ) ];
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$affected     = 0;

		switch ( $action ) {
			case 'enable':
				$sql      = $wpdb->prepare(
					"UPDATE {$table} SET status = 'enabled' WHERE id IN ({$placeholders})",
					...$ids
				);
				$affected = (int) $wpdb->query( $sql );
				break;

			case 'disable':
				$sql      = $wpdb->prepare(
					"UPDATE {$table} SET status = 'disabled' WHERE id IN ({$placeholders})",
					...$ids
				);
				$affected = (int) $wpdb->query( $sql );
				break;

			case 'delete':
				$sql      = $wpdb->prepare(
					"DELETE FROM {$table} WHERE id IN ({$placeholders})",
					...$ids
				);
				$affected = (int) $wpdb->query( $sql );
				break;

			case 'reset':
				$sql      = $wpdb->prepare(
					"UPDATE {$table} SET last_count = 0, last_access = '0000-00-00 00:00:00' WHERE id IN ({$placeholders})",
					...$ids
				);
				$affected = (int) $wpdb->query( $sql );
				break;

			default:
				return [ 'error' => __( 'Invalid action. Use enable, disable, delete, or reset.', 'filter-abilities' ) ];
		}

		return [
			'action'   => $action,
			'affected' => $affected,
			'message'  => sprintf(
				/* translators: 1: action performed, 2: number of redirects affected */
				__( '%1$s %2$d redirect(s).', 'filter-abilities' ),
				ucfirst( $action ) . ( 'reset' === $action ? '' : 'd' ),
				$affected
			),
		];
	}
}
