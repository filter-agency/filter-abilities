<?php

declare(strict_types=1);

class Filter_Abilities_Personalization_Teams extends Filter_Abilities_Module_Base {

	public function register_abilities(): void {
		$this->register_ability( 'filter/list-teams', [
			'label'               => __( 'List Teams', 'filter-abilities' ),
			'description'         => __( 'List all WooCommerce teams with member counts and PersonalizeWP contact counts per team.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'teams' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_teams' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/team-contacts', [
			'label'               => __( 'Team Contacts', 'filter-abilities' ),
			'description'         => __( 'List all PersonalizeWP contacts belonging to a specific WooCommerce team, with lead scores and last seen dates.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'team_id'  => [ 'type' => 'integer', 'description' => __( 'WooCommerce team ID.', 'filter-abilities' ) ],
					'per_page' => [ 'type' => 'integer', 'default' => 20 ],
					'page'     => [ 'type' => 'integer', 'default' => 1 ],
					'orderby'  => [ 'type' => 'string', 'default' => 'lead_score', 'description' => __( 'Order by: lead_score, last_seen, first_name.', 'filter-abilities' ) ],
					'order'    => [ 'type' => 'string', 'default' => 'DESC' ],
				],
				'required' => [ 'team_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'team_id'   => [ 'type' => 'integer' ],
					'team_name' => [ 'type' => 'string' ],
					'total'     => [ 'type' => 'integer' ],
					'contacts'  => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_team_contacts' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/team-activity', [
			'label'               => __( 'Team Activity', 'filter-abilities' ),
			'description'         => __( 'Get activity feed for all members of a WooCommerce team, showing which member performed each action. Filter by activity type and date range.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'team_id'       => [ 'type' => 'integer', 'description' => __( 'WooCommerce team ID.', 'filter-abilities' ) ],
					'activity_type' => [ 'type' => 'string', 'description' => __( 'Filter by activity type.', 'filter-abilities' ) ],
					'start_date'    => [ 'type' => 'string' ],
					'end_date'      => [ 'type' => 'string' ],
					'per_page'      => [ 'type' => 'integer', 'default' => 20 ],
					'page'          => [ 'type' => 'integer', 'default' => 1 ],
				],
				'required' => [ 'team_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'team_id'    => [ 'type' => 'integer' ],
					'team_name'  => [ 'type' => 'string' ],
					'total'      => [ 'type' => 'integer' ],
					'activities' => [ 'type' => 'array' ],
				],
			],
			'execute_callback'    => [ $this, 'execute_team_activity' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->register_ability( 'filter/team-analytics', [
			'label'               => __( 'Team Analytics', 'filter-abilities' ),
			'description'         => __( 'Aggregate analytics for a WooCommerce team: total activities, unique pages visited, average lead score, most active members, and top pages.', 'filter-abilities' ),
			'category'            => 'filter-personalization',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'team_id'    => [ 'type' => 'integer', 'description' => __( 'WooCommerce team ID.', 'filter-abilities' ) ],
					'start_date' => [ 'type' => 'string', 'description' => __( 'Start date for analytics period.', 'filter-abilities' ) ],
					'end_date'   => [ 'type' => 'string', 'description' => __( 'End date for analytics period.', 'filter-abilities' ) ],
				],
				'required' => [ 'team_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_team_analytics' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );
	}

	// --- Helpers ---

	private function get_pwp_table( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . 'pwp_' . $table;
	}

	private function get_team_name( int $team_id ): string {
		$team = wc_memberships_for_teams_get_team( $team_id );
		return $team ? $team->get_name() : '';
	}

	private function get_team_contact_ids( int $team_id ): array {
		global $wpdb;
		$meta_table = $this->get_pwp_table( 'contacts_meta' );

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT contact_id FROM {$meta_table} WHERE meta_key = 'wc_memberships_team_id' AND meta_value = %s",
			(string) $team_id
		) );

		return array_map( 'absint', $ids );
	}

	// --- Execute methods ---

	public function execute_list_teams(): array {
		$teams = wc_memberships_for_teams_get_teams( null, [ 'posts_per_page' => -1 ] );

		if ( ! is_array( $teams ) ) {
			$teams = [];
		}

		$result = [];
		foreach ( $teams as $team ) {
			$team_id     = $team->get_id();
			$contact_ids = $this->get_team_contact_ids( $team_id );

			$result[] = [
				'id'            => $team_id,
				'name'          => $team->get_name(),
				'member_count'  => count( $team->get_member_ids() ),
				'contact_count' => count( $contact_ids ),
			];
		}

		return [
			'total' => count( $result ),
			'teams' => $result,
		];
	}

	public function execute_team_contacts( array $input ): array {
		global $wpdb;
		$contacts_table = $this->get_pwp_table( 'contacts' );

		$team_id   = absint( $input['team_id'] ?? 0 );
		$per_page  = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page      = max( absint( $input['page'] ?? 1 ), 1 );
		$offset    = ( $page - 1 ) * $per_page;
		$orderby   = sanitize_text_field( $input['orderby'] ?? 'lead_score' );
		$order     = in_array( strtoupper( $input['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $input['order'] ?? 'DESC' ) : 'DESC';

		$allowed_orderby = [ 'lead_score', 'last_seen', 'first_name' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'lead_score';
		}

		$contact_ids = $this->get_team_contact_ids( $team_id );

		if ( empty( $contact_ids ) ) {
			return [
				'team_id'   => $team_id,
				'team_name' => $this->get_team_name( $team_id ),
				'total'     => 0,
				'contacts'  => [],
			];
		}

		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );

		$contacts = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$contacts_table} WHERE ID IN ({$placeholders}) ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...array_merge( $contact_ids, [ $per_page, $offset ] )
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
				'last_seen'  => $contact['last_seen'] ?? '',
			];
		}

		return [
			'team_id'   => $team_id,
			'team_name' => $this->get_team_name( $team_id ),
			'total'     => count( $contact_ids ),
			'contacts'  => $result,
		];
	}

	public function execute_team_activity( array $input ): array {
		global $wpdb;
		$activity_table = $this->get_pwp_table( 'activity' );
		$contacts_table = $this->get_pwp_table( 'contacts' );

		$team_id  = absint( $input['team_id'] ?? 0 );
		$per_page = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$contact_ids = $this->get_team_contact_ids( $team_id );

		if ( empty( $contact_ids ) ) {
			return [
				'team_id'    => $team_id,
				'team_name'  => $this->get_team_name( $team_id ),
				'total'      => 0,
				'activities' => [],
			];
		}

		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
		$params       = $contact_ids;
		$where_extra  = '';

		if ( ! empty( $input['activity_type'] ) ) {
			$where_extra .= ' AND a.activity_type = %s';
			$params[]     = sanitize_text_field( $input['activity_type'] );
		}
		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where_extra .= ' AND a.created >= %s';
			$params[]     = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}
		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$where_extra .= ' AND a.created <= %s';
			$params[]     = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$count_sql = "SELECT COUNT(*) FROM {$activity_table} a WHERE a.contact_id IN ({$placeholders}){$where_extra}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

		$data_sql = "SELECT a.ID as activity_id, a.activity_type, a.url, a.object_id, a.lead_score, a.created,
		             c.ID as contact_id, c.first_name, c.last_name, c.email
		             FROM {$activity_table} a
		             LEFT JOIN {$contacts_table} c ON a.contact_id = c.ID
		             WHERE a.contact_id IN ({$placeholders}){$where_extra}
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
				'lead_score'    => (int) ( $row['lead_score'] ?? 0 ),
				'created'       => $row['created'],
				'contact_id'    => (int) ( $row['contact_id'] ?? 0 ),
				'contact_name'  => trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ),
				'contact_email' => $row['email'] ?? '',
			];
		}

		return [
			'team_id'    => $team_id,
			'team_name'  => $this->get_team_name( $team_id ),
			'total'      => $total,
			'activities' => $result,
		];
	}

	public function execute_team_analytics( array $input ): array {
		global $wpdb;
		$activity_table = $this->get_pwp_table( 'activity' );
		$contacts_table = $this->get_pwp_table( 'contacts' );

		$team_id    = absint( $input['team_id'] ?? 0 );
		$contact_ids = $this->get_team_contact_ids( $team_id );

		if ( empty( $contact_ids ) ) {
			return [
				'team_id'   => $team_id,
				'team_name' => $this->get_team_name( $team_id ),
				'message'   => __( 'No contacts found for this team.', 'filter-abilities' ),
			];
		}

		$placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
		$date_where   = '';
		$date_params  = [];

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$date_where  .= ' AND a.created >= %s';
			$date_params[] = sanitize_text_field( $input['start_date'] ) . ' 00:00:00';
		}
		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$date_where  .= ' AND a.created <= %s';
			$date_params[] = sanitize_text_field( $input['end_date'] ) . ' 23:59:59';
		}

		$all_params = array_merge( $contact_ids, $date_params );

		// Total activities.
		$total_activities = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$activity_table} a WHERE a.contact_id IN ({$placeholders}){$date_where}",
			...$all_params
		) );

		// Unique pages.
		$unique_pages = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT url) FROM {$activity_table} a WHERE a.contact_id IN ({$placeholders}){$date_where}",
			...$all_params
		) );

		// Average lead score.
		$avg_lead_score = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(lead_score) FROM {$contacts_table} WHERE ID IN ({$placeholders})",
			...$contact_ids
		) );

		// Activity by type.
		$by_type = $wpdb->get_results( $wpdb->prepare(
			"SELECT activity_type, COUNT(*) as count FROM {$activity_table} a
			 WHERE a.contact_id IN ({$placeholders}){$date_where}
			 GROUP BY activity_type ORDER BY count DESC",
			...$all_params
		), ARRAY_A );

		$activity_breakdown = [];
		foreach ( $by_type as $row ) {
			$activity_breakdown[ $row['activity_type'] ] = (int) $row['count'];
		}

		// Most active members.
		$most_active = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.ID, c.first_name, c.last_name, c.email, COUNT(a.ID) as activity_count
			 FROM {$contacts_table} c
			 LEFT JOIN {$activity_table} a ON c.ID = a.contact_id
			 WHERE c.ID IN ({$placeholders}){$date_where}
			 GROUP BY c.ID ORDER BY activity_count DESC LIMIT 10",
			...$all_params
		), ARRAY_A );

		$active_members = [];
		foreach ( $most_active as $row ) {
			$active_members[] = [
				'contact_id'     => (int) $row['ID'],
				'name'           => trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ),
				'email'          => $row['email'] ?? '',
				'activity_count' => (int) $row['activity_count'],
			];
		}

		// Top pages.
		$top_pages = $wpdb->get_results( $wpdb->prepare(
			"SELECT url, COUNT(*) as view_count FROM {$activity_table} a
			 WHERE a.contact_id IN ({$placeholders}){$date_where}
			 AND a.activity_type = 'page_view'
			 GROUP BY url ORDER BY view_count DESC LIMIT 10",
			...$all_params
		), ARRAY_A );

		return [
			'team_id'            => $team_id,
			'team_name'          => $this->get_team_name( $team_id ),
			'total_contacts'     => count( $contact_ids ),
			'total_activities'   => $total_activities,
			'unique_pages'       => $unique_pages,
			'avg_lead_score'     => round( $avg_lead_score, 2 ),
			'activity_breakdown' => $activity_breakdown,
			'most_active_members' => $active_members,
			'top_pages'          => $top_pages,
		];
	}
}
