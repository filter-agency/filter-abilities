<?php

declare(strict_types=1);

class Filter_Abilities_Form_Management extends Filter_Abilities_Module_Base {

	public function register_categories(): void {
		$this->register_category(
			'filter-forms',
			__( 'Form Management', 'filter-abilities' ),
			__( 'Abilities for managing Gravity Forms and retrieving submissions.', 'filter-abilities' )
		);
	}

	public function register_abilities(): void {
		$this->register_ability( 'filter/list-forms', [
			'label'               => __( 'List Forms', 'filter-abilities' ),
			'description'         => __( 'List all Gravity Forms with their field definitions and entry counts.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total' => [ 'type' => 'integer' ],
					'forms' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'           => [ 'type' => 'integer' ],
								'title'        => [ 'type' => 'string' ],
								'entry_count'  => [ 'type' => 'integer' ],
								'is_active'    => [ 'type' => 'boolean' ],
								'date_created' => [ 'type' => 'string' ],
								'fields'       => [
									'type'  => 'array',
									'items' => [
										'type'       => 'object',
										'properties' => [
											'id'    => [ 'type' => 'integer' ],
											'label' => [ 'type' => 'string' ],
											'type'  => [ 'type' => 'string' ],
										],
									],
								],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_list_forms' ],
			'permission_callback' => function () {
				return current_user_can( 'gravityforms_view_forms' )
				       || current_user_can( 'manage_options' );
			},
		] );

		$this->register_ability( 'filter/get-form-entries', [
			'label'               => __( 'Get Form Entries', 'filter-abilities' ),
			'description'         => __( 'Get form submission entries with date filtering and pagination.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'form_id' => [
						'type'        => 'integer',
						'description' => __( 'The Gravity Forms form ID.', 'filter-abilities' ),
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => __( 'Number of entries per page (max 50). Defaults to 20.', 'filter-abilities' ),
						'default'     => 20,
					],
					'page' => [
						'type'        => 'integer',
						'description' => __( 'Page number. Defaults to 1.', 'filter-abilities' ),
						'default'     => 1,
					],
					'start_date' => [
						'type'        => 'string',
						'description' => __( 'Filter entries from this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
					'end_date' => [
						'type'        => 'string',
						'description' => __( 'Filter entries up to this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
				],
				'required'   => [ 'form_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'total_count' => [ 'type' => 'integer' ],
					'page'        => [ 'type' => 'integer' ],
					'entries'     => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'           => [ 'type' => 'string' ],
								'date_created' => [ 'type' => 'string' ],
								'source_url'   => [ 'type' => 'string' ],
								'ip'           => [ 'type' => 'string' ],
								'fields'       => [ 'type' => 'object' ],
							],
						],
					],
				],
			],
			'execute_callback'    => [ $this, 'execute_get_form_entries' ],
			'permission_callback' => function () {
				return current_user_can( 'gravityforms_view_entries' )
				       || current_user_can( 'manage_options' );
			},
		] );
	}

	public function execute_list_forms(): array {
		$forms  = GFAPI::get_forms();
		$result = [];

		foreach ( $forms as $form ) {
			$fields = [];
			if ( ! empty( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					$fields[] = [
						'id'    => (int) $field->id,
						'label' => $field->label,
						'type'  => $field->type,
					];
				}
			}

			$result[] = [
				'id'           => (int) $form['id'],
				'title'        => $form['title'],
				'entry_count'  => (int) GFAPI::count_entries( $form['id'] ),
				'is_active'    => (bool) ( $form['is_active'] ?? true ),
				'date_created' => $form['date_created'] ?? '',
				'fields'       => $fields,
			];
		}

		return [
			'total' => count( $result ),
			'forms' => $result,
		];
	}

	public function execute_get_form_entries( array $input ): array {
		$form_id  = absint( $input['form_id'] ?? 0 );
		$per_page = max( 1, min( absint( $input['per_page'] ?? 20 ), 50 ) );
		$page     = max( absint( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$search_criteria = [ 'status' => 'active' ];

		if ( ! empty( $input['start_date'] ) ) {
			if ( ! $this->is_valid_date( $input['start_date'] ) ) {
				return [ 'error' => __( 'Invalid start_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$search_criteria['start_date'] = sanitize_text_field( $input['start_date'] );
		}
		if ( ! empty( $input['end_date'] ) ) {
			if ( ! $this->is_valid_date( $input['end_date'] ) ) {
				return [ 'error' => __( 'Invalid end_date format. Use YYYY-MM-DD.', 'filter-abilities' ) ];
			}
			$search_criteria['end_date'] = sanitize_text_field( $input['end_date'] );
		}

		$sorting = [ 'key' => 'date_created', 'direction' => 'DESC' ];
		$paging  = [ 'offset' => $offset, 'page_size' => $per_page ];

		$total_count = GFAPI::count_entries( $form_id, $search_criteria );
		$entries     = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );

		if ( is_wp_error( $entries ) ) {
			return [ 'error' => $entries->get_error_message() ];
		}

		// Get form to map field IDs to labels.
		$form       = GFAPI::get_form( $form_id );
		$field_map  = [];
		if ( $form && ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$field_map[ (string) $field->id ] = $field->label;
			}
		}

		$result = [];
		foreach ( $entries as $entry ) {
			$fields = [];
			foreach ( $entry as $key => $value ) {
				// Field values have numeric keys.
				if ( is_numeric( $key ) && '' !== $value ) {
					$label          = $field_map[ $key ] ?? "Field $key";
					$fields[ $label ] = $value;
				}
			}

			$result[] = [
				'id'           => $entry['id'],
				'date_created' => $entry['date_created'],
				'source_url'   => $entry['source_url'] ?? '',
				'ip'           => $entry['ip'] ?? '',
				'fields'       => $fields,
			];
		}

		return [
			'total_count' => (int) $total_count,
			'page'        => $page,
			'entries'     => $result,
		];
	}
}
