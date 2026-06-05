<?php

declare(strict_types=1);

/**
 * Form Management module — Gravity Forms control surface.
 *
 * Read abilities surface forms, entries, and add-on feeds. Write abilities edit
 * forms surgically (read → mutate → save) rather than rewriting full form
 * objects, matching the v1.7 block-editing philosophy: full-object writes
 * routinely corrupt structured WordPress content, targeted writes do not.
 *
 * The module is gated on `class_exists( 'GFAPI' )` in the orchestrator's
 * module registry — when Gravity Forms is inactive the file is never loaded.
 *
 * @since 1.2.0 list-forms, get-form-entries.
 * @since 1.8.0 get-form, manage-form, manage-form-field, manage-form-confirmation,
 *              list-form-feeds, manage-form-feed.
 */
class Filter_Abilities_Form_Management extends Filter_Abilities_Module_Base {

	/**
	 * Field types supported by `manage-form-field`. `GF_Fields::create()` falls
	 * back to a generic `GF_Field` on unknown types instead of erroring, which
	 * produces a malformed field — so we validate against this list first.
	 */
	private const SUPPORTED_FIELD_TYPES = [
		// Standard
		'text',
		'textarea',
		'number',
		'select',
		'multiselect',
		'checkbox',
		'radio',
		'hidden',
		'html',
		'section',
		'page',
		// Advanced
		'name',
		'email',
		'website',
		'phone',
		'address',
		'date',
		'time',
		'fileupload',
		'consent',
		'list',
		'password',
		'multiple-choice',
		'image-choice',
	];

	/**
	 * Register the form management ability category.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		$this->register_category(
			'filter-forms',
			__( 'Form Management', 'filter-abilities' ),
			__( 'Abilities for managing Gravity Forms and retrieving submissions.', 'filter-abilities' )
		);
	}

	/**
	 * Register every Form Management ability.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// Permission closures. Gravity Forms has no `gravityforms_view_forms`
		// capability — form reading and editing are both gated by
		// `gravityforms_edit_forms`. Every callback retains the standard
		// `manage_options` admin fallback used elsewhere in the plugin.
		$form_reader = function () {
			return current_user_can( 'gravityforms_edit_forms' )
			       || current_user_can( 'manage_options' );
		};

		$entry_reader = function () {
			return current_user_can( 'gravityforms_view_entries' )
			       || current_user_can( 'manage_options' );
		};

		$form_editor = $form_reader; // Same cap as reader: edit_forms.

		$form_creator = function () {
			return current_user_can( 'gravityforms_create_form' )
			       || current_user_can( 'manage_options' );
		};

		$form_deleter = function () {
			return current_user_can( 'gravityforms_delete_forms' )
			       || current_user_can( 'manage_options' );
		};

		// =====================================================================
		// Read abilities
		// =====================================================================

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
			'permission_callback' => $form_reader,
		] );

		$this->register_ability( 'filter/get-form-entries', [
			'label'               => __( 'Get Form Entries', 'filter-abilities' ),
			'description'         => __( 'Get form submission entries with date filtering and pagination.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'form_id'    => [
						'type'        => 'integer',
						'description' => __( 'The Gravity Forms form ID.', 'filter-abilities' ),
					],
					'per_page'   => [
						'type'        => 'integer',
						'description' => __( 'Number of entries per page (max 50). Defaults to 20.', 'filter-abilities' ),
						'default'     => 20,
					],
					'page'       => [
						'type'        => 'integer',
						'description' => __( 'Page number. Defaults to 1.', 'filter-abilities' ),
						'default'     => 1,
					],
					'start_date' => [
						'type'        => 'string',
						'description' => __( 'Filter entries from this date (YYYY-MM-DD).', 'filter-abilities' ),
					],
					'end_date'   => [
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
			'permission_callback' => $entry_reader,
		] );

		$this->register_ability( 'filter/get-form', [
			'label'               => __( 'Get Form', 'filter-abilities' ),
			'description'         => __( 'Get a single Gravity Form in full detail: fields, confirmations, notifications, settings, is_active, is_trash. Pass include_feeds=true to also return the form\'s add-on feeds (Mailchimp, HubSpot, etc.) — inactive feeds are included by default so you can audit what would be retired.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'form_id'       => [
						'type'        => 'integer',
						'description' => __( 'The Gravity Forms form ID.', 'filter-abilities' ),
					],
					'include_feeds' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Also return all add-on feeds attached to this form (active and inactive).', 'filter-abilities' ),
					],
				],
				'required'   => [ 'form_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_get_form' ],
			'permission_callback' => $form_reader,
		] );

		$this->register_ability( 'filter/list-form-feeds', [
			'label'               => __( 'List Form Feeds', 'filter-abilities' ),
			'description'         => __( 'List Gravity Forms add-on feeds (Mailchimp, HubSpot, etc.). Use this as an introspection tool to capture the exact meta shape of an existing feed before writing new ones with manage-form-feed. is_active defaults to null, which returns all feeds including inactive — important when auditing or retiring feeds.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'form_id'     => [
						'type'        => 'integer',
						'description' => __( 'Filter feeds by form ID. Omit to return feeds from every form.', 'filter-abilities' ),
					],
					'addon_slug'  => [
						'type'        => 'string',
						'description' => __( 'Filter by add-on slug (e.g. "gravityformsmailchimp", "gravityformshubspot").', 'filter-abilities' ),
					],
					'is_active'   => [
						'type'        => [ 'boolean', 'null' ],
						'default'     => null,
						'description' => __( 'Filter by active state. null (default) returns all feeds; true returns only active; false returns only inactive.', 'filter-abilities' ),
					],
				],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_list_form_feeds' ],
			'permission_callback' => $form_reader,
		] );

		// =====================================================================
		// Write abilities
		// =====================================================================

		$this->register_ability( 'filter/manage-form', [
			'label'               => __( 'Manage Form', 'filter-abilities' ),
			'description'         => __( 'Form-level operations: create, update (top-level properties), delete (trash by default), duplicate, set-active. WARNING: delete with force=true PERMANENTLY removes the form AND ALL ITS ENTRIES — there is no undo. For targeted field edits use manage-form-field, not full-form update (full-object writes can corrupt the form). dry_run=true returns the computed form without persisting.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'operation'      => [
						'type'        => 'string',
						'enum'        => [ 'create', 'update', 'delete', 'duplicate', 'set-active' ],
						'description' => __( 'The form-level operation to perform.', 'filter-abilities' ),
					],
					'form_id'        => [
						'type'        => 'integer',
						'description' => __( 'Required for update, delete, duplicate, set-active.', 'filter-abilities' ),
					],
					'title'          => [
						'type'        => 'string',
						'description' => __( 'Form title (create only).', 'filter-abilities' ),
					],
					'fields'         => [
						'type'        => 'array',
						'description' => __( 'Initial field definitions (create only). Use manage-form-field for targeted edits afterwards.', 'filter-abilities' ),
					],
					'confirmations'  => [
						'type'        => 'object',
						'description' => __( 'Initial confirmations keyed by id (create only).', 'filter-abilities' ),
					],
					'notifications'  => [
						'type'        => 'object',
						'description' => __( 'Initial notifications keyed by id (create only).', 'filter-abilities' ),
					],
					'settings'       => [
						'type'        => 'object',
						'description' => __( 'Form settings object (create only).', 'filter-abilities' ),
					],
					'form_patch'     => [
						'type'        => 'object',
						'description' => __( 'Update only: top-level form properties to merge (e.g. title, description, button, labelPlacement, cssClass). Do NOT use to rewrite fields/confirmations — use the dedicated abilities.', 'filter-abilities' ),
					],
					'force'          => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Delete only: when true, permanently delete the form AND all its entries (irreversible). When false (default), trash the form (reversible, entries preserved).', 'filter-abilities' ),
					],
					'is_active'      => [
						'type'        => 'boolean',
						'description' => __( 'set-active only: 1 to enable, 0 to disable. This is the retire mechanism for consolidated forms.', 'filter-abilities' ),
					],
					'dry_run'        => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Compute and return the result without persisting.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'operation' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_form' ],
			'permission_callback' => function () use ( $form_editor, $form_creator, $form_deleter ) {
				// Coarse-grain capability check. The execute callback re-checks
				// the operation-specific cap once the operation is known.
				return $form_editor() || $form_creator() || $form_deleter();
			},
		] );

		$this->register_ability( 'filter/manage-form-field', [
			'label'               => __( 'Manage Form Field', 'filter-abilities' ),
			'description'         => __( 'Surgically add, update, delete, or move a single field on a form (read → mutate → save). Use this instead of full-form update — full-object writes routinely corrupt forms. Field type must be one of the supported types: text, textarea, number, select, multiselect, checkbox, radio, hidden, html, section, page, name, email, website, phone, address, date, time, fileupload, consent, list, password, multiple-choice, image-choice. (Post-fields and pricing fields are deliberately excluded — they require side-effect handling.) Field conditionalLogic (controlling visibility) follows the same shape as confirmations: { actionType: "show"|"hide", logicType: "all"|"any", rules: [{ fieldId, operator, value }] }. Indexed-array properties (conditionalLogic, choices, inputs) are REPLACED wholesale on update — pass the complete intended value, not a partial. dry_run=true returns the computed fields array without persisting.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'operation' => [
						'type'        => 'string',
						'enum'        => [ 'add', 'update', 'delete', 'move' ],
						'description' => __( 'The field operation to perform.', 'filter-abilities' ),
					],
					'form_id'   => [
						'type'        => 'integer',
						'description' => __( 'The form ID.', 'filter-abilities' ),
					],
					'field'     => [
						'type'        => 'object',
						'description' => __( 'Field definition. For "add": full field properties including type. For "update": partial properties to merge into the existing field. Composite fields (name, address) auto-build their sub-inputs.', 'filter-abilities' ),
					],
					'field_id'  => [
						'type'        => 'integer',
						'description' => __( 'Target field id for update, delete, move.', 'filter-abilities' ),
					],
					'position'  => [
						'type'        => [ 'integer', 'string' ],
						'description' => __( 'Insertion position for add and move. Numeric index, "start" to prepend, or "end" (default) to append.', 'filter-abilities' ),
					],
					'dry_run'   => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Compute and return the result without persisting.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'operation', 'form_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_form_field' ],
			'permission_callback' => $form_editor,
		] );

		$this->register_ability( 'filter/manage-form-confirmation', [
			'label'               => __( 'Manage Form Confirmation', 'filter-abilities' ),
			'description'         => __( 'Add, update, delete, or set-default a confirmation on a form. Confirmations live in $form[\'confirmations\'] keyed by uniqid. Each confirmation has id, name, type (message|page|redirect), message/pageId/url, queryString, conditionalLogic, isDefault. Use conditionalLogic to dispatch different confirmations from a single form (e.g. one Guide Download form with a hidden "guide" field, sending different download links per topic). conditionalLogic shape: { actionType: "show"|"hide", logicType: "all"|"any", rules: [{ fieldId, operator, value }] }. Valid operators: is, isnot, <>, in, not in, >, <, >=, <=, contains, starts_with, ends_with, like. fieldId references are validated against the form — a typo or stale id is a hard error, not silently-stored dead logic. Lint with filter/validate-conditional-logic first if unsure.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'operation'       => [
						'type'        => 'string',
						'enum'        => [ 'add', 'update', 'delete', 'set-default' ],
						'description' => __( 'The confirmation operation to perform.', 'filter-abilities' ),
					],
					'form_id'         => [
						'type'        => 'integer',
						'description' => __( 'The form ID.', 'filter-abilities' ),
					],
					'confirmation_id' => [
						'type'        => 'string',
						'description' => __( 'Target confirmation id (the uniqid key) for update, delete, set-default.', 'filter-abilities' ),
					],
					'confirmation'    => [
						'type'        => 'object',
						'description' => __( 'Confirmation definition. For "add": name (required), type, message/pageId/url, queryString, conditionalLogic, isDefault. For "update": partial properties to merge.', 'filter-abilities' ),
					],
					'dry_run'         => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Compute and return the result without persisting.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'operation', 'form_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_form_confirmation' ],
			'permission_callback' => $form_editor,
		] );

		$this->register_ability( 'filter/manage-form-notification', [
			'label'               => __( 'Manage Form Notification', 'filter-abilities' ),
			'description'         => __( 'Add, update, delete, or set-active a notification on a form. Notifications live in $form[\'notifications\'] keyed by uniqid. Common fields: id, name, service (default "wordpress"), event (default "form_submission"), to, toType ("email"|"field"|"routing"|"hidden"), bcc, fromName, from, replyTo, subject, message, disableAutoformat, enableAttachments, isActive, conditionalLogic, routing. Use conditionalLogic to fire notifications selectively (e.g. only when a hidden field has a specific value). conditionalLogic shape: { actionType: "show"|"hide", logicType: "all"|"any", rules: [{ fieldId, operator, value }] }. Valid operators: is, isnot, <>, in, not in, >, <, >=, <=, contains, starts_with, ends_with, like. fieldId references are validated against the form; malformed input is rejected, not silently coerced. Lint with filter/validate-conditional-logic.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'operation'       => [
						'type'        => 'string',
						'enum'        => [ 'add', 'update', 'delete', 'set-active' ],
						'description' => __( 'The notification operation to perform.', 'filter-abilities' ),
					],
					'form_id'         => [
						'type'        => 'integer',
						'description' => __( 'The form ID.', 'filter-abilities' ),
					],
					'notification_id' => [
						'type'        => 'string',
						'description' => __( 'Target notification id (the uniqid key) for update, delete, set-active.', 'filter-abilities' ),
					],
					'notification'    => [
						'type'        => 'object',
						'description' => __( 'Notification definition. For "add": name (required), to, toType, subject, message, etc. For "update": partial properties to merge.', 'filter-abilities' ),
					],
					'is_active'       => [
						'type'        => 'boolean',
						'description' => __( 'set-active only: true to enable, false to disable. Stored as isActive on the notification object.', 'filter-abilities' ),
					],
					'dry_run'         => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Compute and return the result without persisting.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'operation', 'form_id' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_form_notification' ],
			'permission_callback' => $form_editor,
		] );

		$this->register_ability( 'filter/manage-form-feed', [
			'label'               => __( 'Manage Form Feed', 'filter-abilities' ),
			'description'         => __( 'Create, update, delete, or set-active an add-on feed (Mailchimp, HubSpot, etc.). The feed "meta" is add-on specific — use list-form-feeds to introspect an existing feed first, then mirror its meta shape.

For MAILCHIMP feeds, the key meta you write is: feedName (string); mailchimpList (audience id from filter/list-mailchimp-audiences); mappedFields (object mapping merge-tag keys to form field ids, e.g. { "EMAIL": "3", "FNAME": "1.3", "LNAME": "1.6" } — pass the nested object; this ability transparently flattens it to the mappedFields_EMAIL / mappedFields_FNAME / ... keys the add-on actually persists); tags (comma-separated string of static-tag names, supports GF merge tags so dynamic per-submission tagging works — e.g. "Lead,Source:{form_title},Guide:{Guide:5}" tags every submission with "Lead", with "Source:<form title>", and with the value of field 5 prefixed by "Guide:"); double_optin (bool); markAsVIP (bool); note (string, supports merge tags); interestCategories (groups — note the dynamic-key storage makes this hard to write from outside; tagging via the tags field is the recommended consolidation mechanism).

Conditional firing of the feed is configured via two meta keys: feed_condition_conditional_logic (1 to enable, 0 to disable) and feed_condition_conditional_logic_object.conditionalLogic (the actual logic, same shape as everywhere else: { actionType, logicType, rules: [{ fieldId, operator, value }] }). Any conditionalLogic in the meta is validated against the target form on write — typos in fieldId fail loudly.

set-active is the retire mechanism for add-on integrations.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'operation'  => [
						'type'        => 'string',
						'enum'        => [ 'create', 'update', 'delete', 'set-active' ],
						'description' => __( 'The feed operation to perform.', 'filter-abilities' ),
					],
					'feed_id'    => [
						'type'        => 'integer',
						'description' => __( 'Target feed id for update, delete, set-active.', 'filter-abilities' ),
					],
					'form_id'    => [
						'type'        => 'integer',
						'description' => __( 'Form id (required for create).', 'filter-abilities' ),
					],
					'addon_slug' => [
						'type'        => 'string',
						'description' => __( 'Add-on slug (required for create), e.g. "gravityformsmailchimp", "gravityformshubspot".', 'filter-abilities' ),
					],
					'meta'       => [
						'type'        => 'object',
						'description' => __( 'Feed meta. Add-on specific — introspect an existing feed via list-form-feeds first.', 'filter-abilities' ),
					],
					'is_active'  => [
						'type'        => 'boolean',
						'description' => __( 'set-active only: 1 to enable, 0 to disable.', 'filter-abilities' ),
					],
					'dry_run'    => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Compute and return the result without persisting.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'operation' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_manage_form_feed' ],
			'permission_callback' => $form_editor,
		] );

		$this->register_ability( 'filter/validate-conditional-logic', [
			'label'               => __( 'Validate Conditional Logic', 'filter-abilities' ),
			'description'         => __( 'Lint a conditionalLogic object against a form before committing it. Checks structure (actionType in show|hide, logicType in all|any, rules is a non-empty array), each rule\'s shape (fieldId + operator + value), that operators are recognised (is, isnot, <>, in, not in, >, <, >=, <=, contains, starts_with, ends_with, like), and that fieldId references a field that actually exists on the form (composite sub-input ids like "1.3" are resolved to their parent field). GF would silently coerce most of these errors away at runtime, producing logic that never matches — this ability surfaces the problems up-front. Read-only.', 'filter-abilities' ),
			'category'            => 'filter-forms',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'form_id'           => [
						'type'        => 'integer',
						'description' => __( 'The form the logic targets. fieldId references are validated against this form.', 'filter-abilities' ),
					],
					'conditional_logic' => [
						'type'        => 'object',
						'description' => __( 'The conditionalLogic object to validate. Shape: { actionType, logicType, rules: [{ fieldId, operator, value }] }.', 'filter-abilities' ),
					],
				],
				'required'   => [ 'form_id', 'conditional_logic' ],
			],
			'output_schema'       => [ 'type' => 'object' ],
			'execute_callback'    => [ $this, 'execute_validate_conditional_logic' ],
			'permission_callback' => $form_reader,
		] );

		// =====================================================================
		// Add-on pickers — registered only when the relevant add-on is active.
		// These surface external service config (Mailchimp audiences, tags,
		// merge fields, groups) so an MCP session can compose feed `meta`
		// without leaving the conversation.
		// =====================================================================

		if ( function_exists( 'gf_mailchimp' ) ) {
			$this->register_ability( 'filter/list-mailchimp-audiences', [
				'label'               => __( 'List Mailchimp Audiences', 'filter-abilities' ),
				'description'         => __( 'List every Mailchimp audience (list) reachable with the Gravity Forms Mailchimp add-on credentials. Use the returned audience id as `mailchimpList` when composing a Mailchimp feed via manage-form-feed.', 'filter-abilities' ),
				'category'            => 'filter-forms',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'execute_list_mailchimp_audiences' ],
				'permission_callback' => $form_reader,
			] );

			$this->register_ability( 'filter/list-mailchimp-tags', [
				'label'               => __( 'List Mailchimp Tags', 'filter-abilities' ),
				'description'         => __( 'List the static tags (segments of type "static") defined on a Mailchimp audience. Tag names — NOT ids — are what go into a Mailchimp feed\'s `tags` meta key. Use this to capture the exact tag strings required for a Guide-download or multi-tag feed setup.', 'filter-abilities' ),
				'category'            => 'filter-forms',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'audience_id' => [
							'type'        => 'string',
							'description' => __( 'The Mailchimp audience (list) id. From filter/list-mailchimp-audiences.', 'filter-abilities' ),
						],
					],
					'required'   => [ 'audience_id' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'execute_list_mailchimp_tags' ],
				'permission_callback' => $form_reader,
			] );

			$this->register_ability( 'filter/list-mailchimp-merge-fields', [
				'label'               => __( 'List Mailchimp Merge Fields', 'filter-abilities' ),
				'description'         => __( 'List the merge fields (EMAIL, FNAME, LNAME, plus any custom ones) defined on a Mailchimp audience. The returned `tag` values are the keys you map Gravity Forms fields to inside a Mailchimp feed\'s `mappedFields` meta.', 'filter-abilities' ),
				'category'            => 'filter-forms',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'audience_id' => [
							'type'        => 'string',
							'description' => __( 'The Mailchimp audience (list) id.', 'filter-abilities' ),
						],
					],
					'required'   => [ 'audience_id' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'execute_list_mailchimp_merge_fields' ],
				'permission_callback' => $form_reader,
			] );

			$this->register_ability( 'filter/list-mailchimp-groups', [
				'label'               => __( 'List Mailchimp Interest Groups', 'filter-abilities' ),
				'description'         => __( 'List the interest groups (groupings + individual interests) defined on a Mailchimp audience. Interest ids are what go into a Mailchimp feed\'s `groups` meta key.', 'filter-abilities' ),
				'category'            => 'filter-forms',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'audience_id' => [
							'type'        => 'string',
							'description' => __( 'The Mailchimp audience (list) id.', 'filter-abilities' ),
						],
					],
					'required'   => [ 'audience_id' ],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'execute_list_mailchimp_groups' ],
				'permission_callback' => $form_reader,
			] );
		}
	}

	// =========================================================================
	// Read execute callbacks
	// =========================================================================

	/**
	 * List all Gravity Forms with field definitions and entry counts.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed> List of forms.
	 */
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

	/**
	 * Get paginated form entries with optional date filtering.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $input Ability input parameters.
	 * @return array<string, mixed> Paginated entries or error.
	 */
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
		$form         = GFAPI::get_form( $form_id );
		$field_map    = [];
		$label_counts = [];
		if ( $form && ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$field_map[ (string) $field->id ] = (string) $field->label;
				$label_key                        = (string) $field->label;
				$label_counts[ $label_key ]       = ( $label_counts[ $label_key ] ?? 0 ) + 1;
			}
		}

		$result = [];
		foreach ( $entries as $entry ) {
			$fields = [];
			foreach ( $entry as $key => $value ) {
				// Field values have numeric keys.
				if ( is_numeric( $key ) && '' !== $value ) {
					$label = $field_map[ $key ] ?? "Field $key";
					// Disambiguate duplicate labels by appending the field id.
					// Without this, two fields with the same label (e.g. two
					// "Email" fields) collide in the returned object and one
					// value is silently lost.
					if ( '' === $label || ( isset( $label_counts[ $label ] ) && $label_counts[ $label ] > 1 ) ) {
						$label = sprintf( '%s (id %s)', $label, (string) $key );
					}
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

	/**
	 * Get a single form in full detail.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_get_form( array $input ): array {
		$form_id = absint( $input['form_id'] ?? 0 );

		if ( ! GFAPI::form_id_exists( $form_id ) ) {
			return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! $form ) {
			return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
		}

		$include_feeds = ! empty( $input['include_feeds'] );

		return $this->serialize_form( $form, $include_feeds );
	}

	/**
	 * List add-on feeds (across forms or scoped to one form).
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_list_form_feeds( array $input ): array {
		$form_id    = isset( $input['form_id'] ) ? absint( $input['form_id'] ) : null;
		$addon_slug = isset( $input['addon_slug'] ) ? sanitize_text_field( (string) $input['addon_slug'] ) : null;

		// Default to null (= return all feeds, active and inactive). The
		// GFAPI::get_feeds() default is `true`, which would hide inactive
		// feeds — exactly the ones an audit needs to see.
		$is_active = null;
		if ( array_key_exists( 'is_active', $input ) && null !== $input['is_active'] ) {
			$is_active = (bool) $input['is_active'];
		}

		if ( null !== $form_id && 0 !== $form_id && ! GFAPI::form_id_exists( $form_id ) ) {
			return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
		}

		$feeds = GFAPI::get_feeds(
			null,
			$form_id ?: null,
			$addon_slug ?: null,
			$is_active
		);

		// GFAPI::get_feeds returns WP_Error('not_found') when no rows match;
		// surface that as an empty list rather than an error for ergonomics.
		if ( is_wp_error( $feeds ) ) {
			if ( 'not_found' === $feeds->get_error_code() ) {
				return [ 'total' => 0, 'feeds' => [] ];
			}
			return [ 'error' => $feeds->get_error_message() ];
		}

		$serialized = array_map( [ $this, 'serialize_feed' ], $feeds );

		return [
			'total' => count( $serialized ),
			'feeds' => $serialized,
		];
	}

	/**
	 * Lint a conditionalLogic object against a form.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute_validate_conditional_logic( array $input ): array {
		$form_id = absint( $input['form_id'] ?? 0 );
		$logic   = $input['conditional_logic'] ?? null;

		if ( 0 === $form_id || ! GFAPI::form_id_exists( $form_id ) ) {
			return [ 'error' => __( 'Valid form_id is required.', 'filter-abilities' ) ];
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! is_array( $form ) ) {
			return [ 'error' => __( 'Failed to load form.', 'filter-abilities' ) ];
		}

		$errors = $this->validate_conditional_logic( $logic, $form );

		return [
			'form_id' => $form_id,
			'valid'   => empty( $errors ),
			'errors'  => $errors,
			'operators_reference' => self::CONDITIONAL_LOGIC_OPERATORS,
		];
	}

	// =========================================================================
	// Write execute callbacks
	// =========================================================================

	/**
	 * Form-level operations.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_manage_form( array $input ): array {
		$operation = isset( $input['operation'] ) ? sanitize_text_field( (string) $input['operation'] ) : '';
		$dry_run   = ! empty( $input['dry_run'] );

		switch ( $operation ) {
			case 'create':
				if ( ! current_user_can( 'gravityforms_create_form' ) && ! current_user_can( 'manage_options' ) ) {
					return [ 'error' => __( 'Permission denied: gravityforms_create_form required.', 'filter-abilities' ) ];
				}

				$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
				if ( '' === $title ) {
					return [ 'error' => __( 'title is required for create.', 'filter-abilities' ) ];
				}

				$form_meta = [
					'title'  => $title,
					'fields' => [],
				];
				foreach ( [ 'fields', 'confirmations', 'notifications', 'settings' ] as $key ) {
					if ( isset( $input[ $key ] ) ) {
						$form_meta[ $key ] = $input[ $key ];
					}
				}

				if ( $dry_run ) {
					return [
						'operation' => 'create',
						'dry_run'   => true,
						'form'      => $form_meta,
					];
				}

				$form_id = GFAPI::add_form( $form_meta );
				if ( is_wp_error( $form_id ) ) {
					return [ 'error' => $form_id->get_error_message() ];
				}

				$new_form = GFAPI::get_form( $form_id );

				return [
					'operation' => 'create',
					'form_id'   => (int) $form_id,
					'form'      => $new_form ? $this->serialize_form( $new_form ) : null,
				];

			case 'update':
				if ( ! current_user_can( 'gravityforms_edit_forms' ) && ! current_user_can( 'manage_options' ) ) {
					return [ 'error' => __( 'Permission denied: gravityforms_edit_forms required.', 'filter-abilities' ) ];
				}

				$form_id = absint( $input['form_id'] ?? 0 );
				if ( ! GFAPI::form_id_exists( $form_id ) ) {
					return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
				}

				$form  = GFAPI::get_form( $form_id );
				$patch = ( isset( $input['form_patch'] ) && is_array( $input['form_patch'] ) ) ? $input['form_patch'] : [];
				if ( empty( $patch ) ) {
					return [ 'error' => __( 'form_patch is required and must be a non-empty object.', 'filter-abilities' ) ];
				}

				// Guard against using this for what manage-form-field /
				// manage-form-confirmation / manage-form-notification should
				// handle.
				foreach ( [ 'fields', 'confirmations', 'notifications' ] as $forbidden ) {
					if ( array_key_exists( $forbidden, $patch ) ) {
						return [
							'error' => sprintf(
								/* translators: %s: field name */
								__( 'Use the dedicated ability (manage-form-field / manage-form-confirmation / manage-form-notification) to change "%s". manage-form update is for top-level properties only.', 'filter-abilities' ),
								$forbidden
							),
						];
					}
				}

				// Block keys that have dedicated operations with their own
				// capability checks. Without this guard, a user with only
				// gravityforms_edit_forms can bypass gravityforms_delete_forms
				// by trashing via `is_trash: 1`, or do what set-active does
				// via `is_active: 0`. Also block surrogate keys (`id`,
				// `nextFieldId`, `date_created`) that must never come from
				// caller input.
				$forbidden_keys = [
					'is_trash'     => __( 'use manage-form delete instead (requires gravityforms_delete_forms).', 'filter-abilities' ),
					'is_active'    => __( 'use manage-form set-active instead.', 'filter-abilities' ),
					'id'           => __( 'form id is locked by the form_id parameter.', 'filter-abilities' ),
					'nextFieldId'  => __( 'managed automatically by manage-form-field.', 'filter-abilities' ),
					'date_created' => __( 'managed by Gravity Forms.', 'filter-abilities' ),
				];
				foreach ( $forbidden_keys as $key => $reason ) {
					if ( array_key_exists( $key, $patch ) ) {
						return [
							'error' => sprintf(
								/* translators: 1: forbidden key, 2: reason */
								__( '"%1$s" cannot be set via manage-form update — %2$s', 'filter-abilities' ),
								$key,
								$reason
							),
						];
					}
				}

				$updated = array_merge( $form, $patch );
				$updated['id'] = $form_id; // Lock id (defense in depth — patch is already guarded above).

				if ( $dry_run ) {
					return [
						'operation' => 'update',
						'dry_run'   => true,
						'form'      => $this->serialize_form( $updated ),
					];
				}

				$result = GFAPI::update_form( $updated );
				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				return [
					'operation' => 'update',
					'form'      => $this->serialize_form( GFAPI::get_form( $form_id ) ),
				];

			case 'delete':
				if ( ! current_user_can( 'gravityforms_delete_forms' ) && ! current_user_can( 'manage_options' ) ) {
					return [ 'error' => __( 'Permission denied: gravityforms_delete_forms required.', 'filter-abilities' ) ];
				}

				$form_id = absint( $input['form_id'] ?? 0 );
				if ( ! GFAPI::form_id_exists( $form_id ) ) {
					return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
				}

				$force = ! empty( $input['force'] );

				if ( $dry_run ) {
					return [
						'operation' => 'delete',
						'dry_run'   => true,
						'force'     => $force,
						'message'   => $force
							? __( 'Would PERMANENTLY delete the form and all its entries.', 'filter-abilities' )
							: __( 'Would trash the form (reversible, entries preserved).', 'filter-abilities' ),
						'form'      => $this->serialize_form( GFAPI::get_form( $form_id ) ),
					];
				}

				if ( $force ) {
					$result = GFAPI::delete_form( $form_id );
					if ( is_wp_error( $result ) ) {
						return [ 'error' => $result->get_error_message() ];
					}
					return [
						'operation' => 'delete',
						'form_id'   => $form_id,
						'action'    => 'permanently-deleted',
						'message'   => __( 'Form and all entries permanently deleted.', 'filter-abilities' ),
					];
				}

				$result = GFAPI::update_forms_property( [ $form_id ], 'is_trash', 1 );
				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}
				// GFAPI::update_forms_property falls through to $wpdb->query()
				// which returns int|false. WP_Error covers the validation
				// branches; false signals a DB-layer failure that we must
				// not report as success.
				if ( false === $result ) {
					return [ 'error' => __( 'Database query to trash the form failed. The form was not modified.', 'filter-abilities' ) ];
				}
				return [
					'operation' => 'delete',
					'form_id'   => $form_id,
					'action'    => 'trashed',
					'message'   => __( 'Form moved to trash. Restore via Gravity Forms UI or by updating is_trash=0.', 'filter-abilities' ),
				];

			case 'duplicate':
				if ( ! current_user_can( 'gravityforms_create_form' ) && ! current_user_can( 'manage_options' ) ) {
					return [ 'error' => __( 'Permission denied: gravityforms_create_form required.', 'filter-abilities' ) ];
				}

				$form_id = absint( $input['form_id'] ?? 0 );
				if ( ! GFAPI::form_id_exists( $form_id ) ) {
					return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
				}

				if ( $dry_run ) {
					return [
						'operation' => 'duplicate',
						'dry_run'   => true,
						'source_id' => $form_id,
						'message'   => __( 'Would duplicate the form. Note: add-on feeds (Mailchimp, HubSpot) do not duplicate — they must be recreated.', 'filter-abilities' ),
					];
				}

				$new_id = GFAPI::duplicate_form( $form_id );
				if ( is_wp_error( $new_id ) ) {
					return [ 'error' => $new_id->get_error_message() ];
				}

				return [
					'operation' => 'duplicate',
					'source_id' => $form_id,
					'form_id'   => (int) $new_id,
					'form'      => $this->serialize_form( GFAPI::get_form( $new_id ) ),
					'note'      => __( 'Add-on feeds do not duplicate — recreate them via manage-form-feed if needed.', 'filter-abilities' ),
				];

			case 'set-active':
				if ( ! current_user_can( 'gravityforms_edit_forms' ) && ! current_user_can( 'manage_options' ) ) {
					return [ 'error' => __( 'Permission denied: gravityforms_edit_forms required.', 'filter-abilities' ) ];
				}

				$form_id = absint( $input['form_id'] ?? 0 );
				if ( ! GFAPI::form_id_exists( $form_id ) ) {
					return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
				}
				if ( ! array_key_exists( 'is_active', $input ) ) {
					return [ 'error' => __( 'is_active is required for set-active.', 'filter-abilities' ) ];
				}

				$value = ! empty( $input['is_active'] ) ? 1 : 0;

				if ( $dry_run ) {
					return [
						'operation' => 'set-active',
						'dry_run'   => true,
						'form_id'   => $form_id,
						'is_active' => (bool) $value,
					];
				}

				$result = GFAPI::update_forms_property( [ $form_id ], 'is_active', $value );
				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}
				if ( false === $result ) {
					return [ 'error' => __( 'Database query to change is_active failed. The form was not modified.', 'filter-abilities' ) ];
				}

				return [
					'operation' => 'set-active',
					'form_id'   => $form_id,
					'is_active' => (bool) $value,
				];

			default:
				return [ 'error' => sprintf( __( 'Unknown operation: %s.', 'filter-abilities' ), $operation ) ];
		}
	}

	/**
	 * Targeted field operations on a form.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_manage_form_field( array $input ): array {
		$operation = isset( $input['operation'] ) ? sanitize_text_field( (string) $input['operation'] ) : '';
		$form_id   = absint( $input['form_id'] ?? 0 );
		$dry_run   = ! empty( $input['dry_run'] );

		if ( ! GFAPI::form_id_exists( $form_id ) ) {
			return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! is_array( $form ) ) {
			return [ 'error' => __( 'Failed to load form.', 'filter-abilities' ) ];
		}

		$fields = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : [];

		switch ( $operation ) {
			case 'add':
				$field_input = ( isset( $input['field'] ) && is_array( $input['field'] ) ) ? $input['field'] : [];
				if ( empty( $field_input ) ) {
					return [ 'error' => __( 'field is required for add.', 'filter-abilities' ) ];
				}

				$type = isset( $field_input['type'] ) ? sanitize_text_field( (string) $field_input['type'] ) : '';
				if ( ! in_array( $type, self::SUPPORTED_FIELD_TYPES, true ) ) {
					return [
						'error' => sprintf(
							/* translators: %s: supported field types */
							__( 'Unsupported field type. Supported types: %s.', 'filter-abilities' ),
							implode( ', ', self::SUPPORTED_FIELD_TYPES )
						),
					];
				}

				// Allocate the new id BEFORE validating conditionalLogic so a
				// field that references its own id (legal, if unusual) passes
				// the field-exists check. The validator gets a synthetic
				// form snapshot containing both the existing fields and a
				// stub for the new one.
				$next_id               = GFFormsModel::get_next_field_id( $fields );
				$field_input['id']     = (int) $next_id;
				$field_input['formId'] = $form_id;
				if ( ! isset( $field_input['label'] ) ) {
					$field_input['label'] = '';
				}

				$validation_form           = $form;
				$validation_form['fields'] = array_merge(
					$fields,
					[ (object) [ 'id' => (int) $next_id ] ]
				);
				$cl_err = $this->maybe_reject_invalid_conditional_logic( $field_input, $validation_form, 'field.conditionalLogic' );
				if ( null !== $cl_err ) {
					return $cl_err;
				}

				// Strip explicit-null patch values from the input before
				// constructing the field — same idiom as update (apply
				// `key: null` to drop a property).
				$field_input = $this->apply_null_unsets( $field_input, $field_input );

				$new_field = GF_Fields::create( $field_input );

				$position    = $input['position'] ?? 'end';
				$new_fields  = $this->insert_field_at( $fields, $new_field, $position );
				$form['fields']      = $new_fields;
				$form['nextFieldId'] = (int) $next_id + 1;

				if ( $dry_run ) {
					return [
						'operation' => 'add',
						'dry_run'   => true,
						'field'     => $this->serialize_field( $new_field ),
						'fields'    => array_map( [ $this, 'serialize_field' ], $new_fields ),
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation' => 'add',
					'field'     => $this->serialize_field( $new_field ),
				];

			case 'update':
				$field_id = absint( $input['field_id'] ?? 0 );
				$patch    = ( isset( $input['field'] ) && is_array( $input['field'] ) ) ? $input['field'] : [];
				if ( 0 === $field_id || empty( $patch ) ) {
					return [ 'error' => __( 'field_id and field (patch) are required for update.', 'filter-abilities' ) ];
				}

				$index = $this->find_field_index( $fields, $field_id );
				if ( null === $index ) {
					return [ 'error' => sprintf( __( 'Field %d not found on form.', 'filter-abilities' ), $field_id ) ];
				}

				// If the patch attempts a type change, re-validate.
				if ( isset( $patch['type'] ) && ! in_array( (string) $patch['type'], self::SUPPORTED_FIELD_TYPES, true ) ) {
					return [
						'error' => sprintf(
							__( 'Unsupported target type. Supported types: %s.', 'filter-abilities' ),
							implode( ', ', self::SUPPORTED_FIELD_TYPES )
						),
					];
				}

				$cl_err = $this->maybe_reject_invalid_conditional_logic( $patch, $form, 'field.conditionalLogic' );
				if ( null !== $cl_err ) {
					return $cl_err;
				}

				$existing   = $fields[ $index ];
				$merged     = $this->merge_field_properties( $existing, $patch, $form_id );
				$merged->id = (int) $field_id;
				$fields[ $index ] = $merged;
				$form['fields']   = $fields;

				if ( $dry_run ) {
					return [
						'operation' => 'update',
						'dry_run'   => true,
						'field'     => $this->serialize_field( $merged ),
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation' => 'update',
					'field'     => $this->serialize_field( $merged ),
				];

			case 'delete':
				$field_id = absint( $input['field_id'] ?? 0 );
				if ( 0 === $field_id ) {
					return [ 'error' => __( 'field_id is required for delete.', 'filter-abilities' ) ];
				}

				$index = $this->find_field_index( $fields, $field_id );
				if ( null === $index ) {
					return [ 'error' => sprintf( __( 'Field %d not found on form.', 'filter-abilities' ), $field_id ) ];
				}

				$removed = $fields[ $index ];
				array_splice( $fields, $index, 1 );
				$form['fields'] = $fields;

				if ( $dry_run ) {
					return [
						'operation' => 'delete',
						'dry_run'   => true,
						'removed'   => $this->serialize_field( $removed ),
						'fields'    => array_map( [ $this, 'serialize_field' ], $fields ),
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation' => 'delete',
					'field_id'  => $field_id,
				];

			case 'move':
				$field_id = absint( $input['field_id'] ?? 0 );
				if ( 0 === $field_id ) {
					return [ 'error' => __( 'field_id is required for move.', 'filter-abilities' ) ];
				}
				if ( ! array_key_exists( 'position', $input ) ) {
					return [ 'error' => __( 'position is required for move.', 'filter-abilities' ) ];
				}

				$index = $this->find_field_index( $fields, $field_id );
				if ( null === $index ) {
					return [ 'error' => sprintf( __( 'Field %d not found on form.', 'filter-abilities' ), $field_id ) ];
				}

				$original_index = $index;
				$field          = $fields[ $index ];
				array_splice( $fields, $index, 1 );
				$fields = $this->insert_field_at( $fields, $field, $input['position'] );
				// Compute the new index by id rather than by trusting position
				// resolution — insert_field_at silently coerces malformed
				// positions (e.g. "middle") to 0, so we need to confirm where
				// the field actually landed.
				$new_index      = $this->find_field_index( $fields, (int) $field_id );
				$moved          = ( null !== $new_index ) && ( $new_index !== $original_index );
				$form['fields'] = $fields;

				if ( $dry_run ) {
					return [
						'operation'      => 'move',
						'dry_run'        => true,
						'moved'          => $moved,
						'from_index'     => $original_index,
						'to_index'       => $new_index,
						'fields'         => array_map( [ $this, 'serialize_field' ], $fields ),
					];
				}

				// Same-position "move" is a no-op — skip the persistence
				// round-trip and tell the caller plainly.
				if ( ! $moved ) {
					return [
						'operation'      => 'move',
						'field_id'       => $field_id,
						'moved'          => false,
						'from_index'     => $original_index,
						'to_index'       => $new_index,
						'message'        => __( 'Field is already at the requested position; nothing changed.', 'filter-abilities' ),
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'  => 'move',
					'field_id'   => $field_id,
					'moved'      => true,
					'from_index' => $original_index,
					'to_index'   => $new_index,
				];

			default:
				return [ 'error' => sprintf( __( 'Unknown operation: %s.', 'filter-abilities' ), $operation ) ];
		}
	}

	/**
	 * Confirmation operations on a form.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_manage_form_confirmation( array $input ): array {
		$operation = isset( $input['operation'] ) ? sanitize_text_field( (string) $input['operation'] ) : '';
		$form_id   = absint( $input['form_id'] ?? 0 );
		$dry_run   = ! empty( $input['dry_run'] );

		if ( ! GFAPI::form_id_exists( $form_id ) ) {
			return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! is_array( $form ) ) {
			return [ 'error' => __( 'Failed to load form.', 'filter-abilities' ) ];
		}

		$confirmations = isset( $form['confirmations'] ) && is_array( $form['confirmations'] )
			? $form['confirmations']
			: [];

		switch ( $operation ) {
			case 'add':
				$confirmation_input = ( isset( $input['confirmation'] ) && is_array( $input['confirmation'] ) ) ? $input['confirmation'] : [];
				if ( empty( $confirmation_input ) || empty( $confirmation_input['name'] ) ) {
					return [ 'error' => __( 'confirmation.name is required for add.', 'filter-abilities' ) ];
				}

				$cl_err = $this->maybe_reject_invalid_conditional_logic( $confirmation_input, $form, 'confirmation.conditionalLogic' );
				if ( null !== $cl_err ) {
					return $cl_err;
				}

				// Drop any keys the caller explicitly set to null — same
				// idiom as update (the documented way to omit a property).
				$confirmation_input              = $this->apply_null_unsets( $confirmation_input, $confirmation_input );
				$new_id                          = uniqid();
				$confirmation_input['id']        = $new_id;
				$confirmation_input['type']      = isset( $confirmation_input['type'] ) ? sanitize_text_field( (string) $confirmation_input['type'] ) : 'message';
				$confirmation_input['isDefault'] = ! empty( $confirmation_input['isDefault'] );

				// If this is being added as the default, demote any existing default.
				if ( $confirmation_input['isDefault'] ) {
					foreach ( $confirmations as $cid => $c ) {
						$confirmations[ $cid ]['isDefault'] = false;
					}
				}

				$confirmations[ $new_id ] = $confirmation_input;
				$form['confirmations']    = $confirmations;

				if ( $dry_run ) {
					return [
						'operation'    => 'add',
						'dry_run'      => true,
						'confirmation' => $confirmation_input,
						'confirmations' => $confirmations,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'    => 'add',
					'confirmation' => $confirmation_input,
				];

			case 'update':
				$confirmation_id = isset( $input['confirmation_id'] ) ? sanitize_text_field( (string) $input['confirmation_id'] ) : '';
				$patch           = ( isset( $input['confirmation'] ) && is_array( $input['confirmation'] ) ) ? $input['confirmation'] : [];
				if ( '' === $confirmation_id || empty( $patch ) ) {
					return [ 'error' => __( 'confirmation_id and confirmation (patch) are required for update.', 'filter-abilities' ) ];
				}
				if ( ! isset( $confirmations[ $confirmation_id ] ) ) {
					return [ 'error' => sprintf( __( 'Confirmation "%s" not found.', 'filter-abilities' ), $confirmation_id ) ];
				}

				$cl_err = $this->maybe_reject_invalid_conditional_logic( $patch, $form, 'confirmation.conditionalLogic' );
				if ( null !== $cl_err ) {
					return $cl_err;
				}

				$merged = array_merge( $confirmations[ $confirmation_id ], $patch );
				$merged = $this->apply_null_unsets( $merged, $patch );
				$merged['id'] = $confirmation_id; // Lock id.

				// If this update sets isDefault=true, demote others.
				if ( ! empty( $merged['isDefault'] ) ) {
					foreach ( $confirmations as $cid => $c ) {
						if ( $cid !== $confirmation_id ) {
							$confirmations[ $cid ]['isDefault'] = false;
						}
					}
				}

				$confirmations[ $confirmation_id ] = $merged;
				$form['confirmations']             = $confirmations;

				if ( $dry_run ) {
					return [
						'operation'    => 'update',
						'dry_run'      => true,
						'confirmation' => $merged,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'    => 'update',
					'confirmation' => $merged,
				];

			case 'delete':
				$confirmation_id = isset( $input['confirmation_id'] ) ? sanitize_text_field( (string) $input['confirmation_id'] ) : '';
				if ( '' === $confirmation_id ) {
					return [ 'error' => __( 'confirmation_id is required for delete.', 'filter-abilities' ) ];
				}
				if ( ! isset( $confirmations[ $confirmation_id ] ) ) {
					return [ 'error' => sprintf( __( 'Confirmation "%s" not found.', 'filter-abilities' ), $confirmation_id ) ];
				}

				$was_default = ! empty( $confirmations[ $confirmation_id ]['isDefault'] );
				unset( $confirmations[ $confirmation_id ] );
				$form['confirmations'] = $confirmations;

				if ( $dry_run ) {
					return [
						'operation'       => 'delete',
						'dry_run'         => true,
						'confirmation_id' => $confirmation_id,
						'note'            => $was_default && empty( $confirmations )
							? __( 'You are deleting the default confirmation — the form will have no confirmation left.', 'filter-abilities' )
							: '',
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'       => 'delete',
					'confirmation_id' => $confirmation_id,
				];

			case 'set-default':
				$confirmation_id = isset( $input['confirmation_id'] ) ? sanitize_text_field( (string) $input['confirmation_id'] ) : '';
				if ( '' === $confirmation_id ) {
					return [ 'error' => __( 'confirmation_id is required for set-default.', 'filter-abilities' ) ];
				}
				if ( ! isset( $confirmations[ $confirmation_id ] ) ) {
					return [ 'error' => sprintf( __( 'Confirmation "%s" not found.', 'filter-abilities' ), $confirmation_id ) ];
				}

				foreach ( $confirmations as $cid => $c ) {
					$confirmations[ $cid ]['isDefault'] = ( $cid === $confirmation_id );
				}
				$form['confirmations'] = $confirmations;

				if ( $dry_run ) {
					return [
						'operation'       => 'set-default',
						'dry_run'         => true,
						'confirmation_id' => $confirmation_id,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'       => 'set-default',
					'confirmation_id' => $confirmation_id,
				];

			default:
				return [ 'error' => sprintf( __( 'Unknown operation: %s.', 'filter-abilities' ), $operation ) ];
		}
	}

	/**
	 * Notification operations on a form.
	 *
	 * Notifications live in $form['notifications'] keyed by uniqid, mirroring
	 * the confirmations structure. Stored via GFAPI::update_form().
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_manage_form_notification( array $input ): array {
		$operation = isset( $input['operation'] ) ? sanitize_text_field( (string) $input['operation'] ) : '';
		$form_id   = absint( $input['form_id'] ?? 0 );
		$dry_run   = ! empty( $input['dry_run'] );

		if ( ! GFAPI::form_id_exists( $form_id ) ) {
			return [ 'error' => __( 'Form not found.', 'filter-abilities' ) ];
		}

		$form = GFAPI::get_form( $form_id );
		if ( ! is_array( $form ) ) {
			return [ 'error' => __( 'Failed to load form.', 'filter-abilities' ) ];
		}

		$notifications = isset( $form['notifications'] ) && is_array( $form['notifications'] )
			? $form['notifications']
			: [];

		switch ( $operation ) {
			case 'add':
				$notification_input = ( isset( $input['notification'] ) && is_array( $input['notification'] ) ) ? $input['notification'] : [];
				if ( empty( $notification_input ) || empty( $notification_input['name'] ) ) {
					return [ 'error' => __( 'notification.name is required for add.', 'filter-abilities' ) ];
				}

				$cl_err = $this->maybe_reject_invalid_conditional_logic( $notification_input, $form, 'notification.conditionalLogic' );
				if ( null !== $cl_err ) {
					return $cl_err;
				}

				// Drop any keys the caller explicitly set to null — same
				// idiom as update.
				$notification_input       = $this->apply_null_unsets( $notification_input, $notification_input );
				$new_id                   = uniqid();
				$notification_input['id'] = $new_id;

				// Apply sane defaults so a minimal payload still produces a
				// working notification. These mirror what the GF admin sets
				// when creating a notification from scratch.
				if ( ! isset( $notification_input['service'] ) ) {
					$notification_input['service'] = 'wordpress';
				}
				if ( ! isset( $notification_input['event'] ) ) {
					$notification_input['event'] = 'form_submission';
				}
				if ( ! isset( $notification_input['toType'] ) ) {
					// Default to "email". Callers wanting "field", "routing",
					// or "hidden" must set toType explicitly — we don't try to
					// infer from the shape of `to`, which is brittle.
					$notification_input['toType'] = 'email';
				}
				if ( ! array_key_exists( 'isActive', $notification_input ) ) {
					$notification_input['isActive'] = true;
				}

				$notifications[ $new_id ] = $notification_input;
				$form['notifications']    = $notifications;

				if ( $dry_run ) {
					return [
						'operation'     => 'add',
						'dry_run'       => true,
						'notification'  => $notification_input,
						'notifications' => $notifications,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'    => 'add',
					'notification' => $notification_input,
				];

			case 'update':
				$notification_id = isset( $input['notification_id'] ) ? sanitize_text_field( (string) $input['notification_id'] ) : '';
				$patch           = ( isset( $input['notification'] ) && is_array( $input['notification'] ) ) ? $input['notification'] : [];
				if ( '' === $notification_id || empty( $patch ) ) {
					return [ 'error' => __( 'notification_id and notification (patch) are required for update.', 'filter-abilities' ) ];
				}
				if ( ! isset( $notifications[ $notification_id ] ) ) {
					return [ 'error' => sprintf( __( 'Notification "%s" not found.', 'filter-abilities' ), $notification_id ) ];
				}

				$cl_err = $this->maybe_reject_invalid_conditional_logic( $patch, $form, 'notification.conditionalLogic' );
				if ( null !== $cl_err ) {
					return $cl_err;
				}

				$merged       = array_merge( $notifications[ $notification_id ], $patch );
				$merged       = $this->apply_null_unsets( $merged, $patch );
				$merged['id'] = $notification_id; // Lock id.
				$notifications[ $notification_id ] = $merged;
				$form['notifications'] = $notifications;

				if ( $dry_run ) {
					return [
						'operation'    => 'update',
						'dry_run'      => true,
						'notification' => $merged,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'    => 'update',
					'notification' => $merged,
				];

			case 'delete':
				$notification_id = isset( $input['notification_id'] ) ? sanitize_text_field( (string) $input['notification_id'] ) : '';
				if ( '' === $notification_id ) {
					return [ 'error' => __( 'notification_id is required for delete.', 'filter-abilities' ) ];
				}
				if ( ! isset( $notifications[ $notification_id ] ) ) {
					return [ 'error' => sprintf( __( 'Notification "%s" not found.', 'filter-abilities' ), $notification_id ) ];
				}

				unset( $notifications[ $notification_id ] );
				$form['notifications'] = $notifications;

				if ( $dry_run ) {
					return [
						'operation'       => 'delete',
						'dry_run'         => true,
						'notification_id' => $notification_id,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'       => 'delete',
					'notification_id' => $notification_id,
				];

			case 'set-active':
				$notification_id = isset( $input['notification_id'] ) ? sanitize_text_field( (string) $input['notification_id'] ) : '';
				if ( '' === $notification_id ) {
					return [ 'error' => __( 'notification_id is required for set-active.', 'filter-abilities' ) ];
				}
				if ( ! isset( $notifications[ $notification_id ] ) ) {
					return [ 'error' => sprintf( __( 'Notification "%s" not found.', 'filter-abilities' ), $notification_id ) ];
				}
				if ( ! array_key_exists( 'is_active', $input ) ) {
					return [ 'error' => __( 'is_active is required for set-active.', 'filter-abilities' ) ];
				}

				$value = (bool) $input['is_active'];
				$notifications[ $notification_id ]['isActive'] = $value;
				$form['notifications'] = $notifications;

				if ( $dry_run ) {
					return [
						'operation'       => 'set-active',
						'dry_run'         => true,
						'notification_id' => $notification_id,
						'is_active'       => $value,
					];
				}

				$saved = GFAPI::update_form( $form );
				if ( is_wp_error( $saved ) ) {
					return [ 'error' => $saved->get_error_message() ];
				}

				return [
					'operation'       => 'set-active',
					'notification_id' => $notification_id,
					'is_active'       => $value,
				];

			default:
				return [ 'error' => sprintf( __( 'Unknown operation: %s.', 'filter-abilities' ), $operation ) ];
		}
	}

	/**
	 * Feed operations (add-on feeds — Mailchimp, HubSpot, etc.).
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public function execute_manage_form_feed( array $input ): array {
		$operation = isset( $input['operation'] ) ? sanitize_text_field( (string) $input['operation'] ) : '';
		$dry_run   = ! empty( $input['dry_run'] );

		switch ( $operation ) {
			case 'create':
				$form_id    = absint( $input['form_id'] ?? 0 );
				$addon_slug = isset( $input['addon_slug'] ) ? sanitize_text_field( (string) $input['addon_slug'] ) : '';
				$meta       = ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) ? $input['meta'] : [];

				if ( 0 === $form_id || ! GFAPI::form_id_exists( $form_id ) ) {
					return [ 'error' => __( 'Valid form_id is required for create.', 'filter-abilities' ) ];
				}
				if ( '' === $addon_slug ) {
					return [ 'error' => __( 'addon_slug is required for create.', 'filter-abilities' ) ];
				}
				if ( ! $this->addon_is_registered( $addon_slug ) ) {
					return [
						'error' => sprintf(
							/* translators: %s: add-on slug */
							__( 'Add-on "%s" is not installed or not active.', 'filter-abilities' ),
							$addon_slug
						),
					];
				}

				// Validate any embedded feed conditional logic against the
				// target form. Meta path: feed_condition_conditional_logic_object.conditionalLogic
				$feed_cl_err = $this->validate_feed_meta_conditional_logic( $meta, $form_id );
				if ( null !== $feed_cl_err ) {
					return $feed_cl_err;
				}

				// Flatten field-map shorthand. Callers may pass mappedFields
				// as a nested object ({EMAIL: "1", FNAME: "2.3"}) but the
				// add-on persists it as flat prefixed meta keys
				// (mappedFields_EMAIL, mappedFields_FNAME, ...). Without this
				// the mapping silently never engages. See
				// GFAddOn::get_field_map_fields() for the contract.
				$flattened = $this->flatten_field_map_meta( $meta );
				if ( is_wp_error( $flattened ) ) {
					return [ 'error' => $flattened->get_error_message() ];
				}
				$meta = $flattened;
				// `create` cannot meaningfully clear pre-existing mappings —
				// nothing exists yet. Strip the sentinel.
				unset( $meta[ self::FIELD_MAP_CLEAR_SENTINEL ] );

				if ( $dry_run ) {
					return [
						'operation'  => 'create',
						'dry_run'    => true,
						'form_id'    => $form_id,
						'addon_slug' => $addon_slug,
						'meta'       => $meta,
					];
				}

				$feed_id = GFAPI::add_feed( $form_id, $meta, $addon_slug );
				if ( is_wp_error( $feed_id ) ) {
					return [ 'error' => $feed_id->get_error_message() ];
				}

				return [
					'operation' => 'create',
					'feed_id'   => (int) $feed_id,
					'feed'      => $this->serialize_feed( GFAPI::get_feed( $feed_id ) ),
				];

			case 'update':
				$feed_id = absint( $input['feed_id'] ?? 0 );
				$meta    = ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) ? $input['meta'] : [];
				if ( 0 === $feed_id ) {
					return [ 'error' => __( 'feed_id is required for update.', 'filter-abilities' ) ];
				}

				$existing = GFAPI::get_feed( $feed_id );
				if ( is_wp_error( $existing ) ) {
					return [ 'error' => $existing->get_error_message() ];
				}

				$feed_cl_err = $this->validate_feed_meta_conditional_logic( $meta, (int) ( $existing['form_id'] ?? 0 ) );
				if ( null !== $feed_cl_err ) {
					return $feed_cl_err;
				}

				// Flatten field-map shorthand (see same call in create). On
				// update this also lets a caller patch a single field map
				// entry by passing { mappedFields: { EMAIL: "1" } } without
				// disturbing other prefixed keys, or clear one with
				// { mappedFields: { EMAIL: null } }, or clear all with
				// { mappedFields: {} } / { mappedFields: null }.
				$flattened = $this->flatten_field_map_meta( $meta );
				if ( is_wp_error( $flattened ) ) {
					return [ 'error' => $flattened->get_error_message() ];
				}
				$meta = $flattened;

				$existing_meta = is_array( $existing['meta'] ?? null ) ? $existing['meta'] : [];
				// Honour any "clear all entries with this prefix" sentinel
				// the flattener emitted before we do the recursive merge.
				[ $existing_meta, $meta ] = $this->apply_field_map_clears( $existing_meta, $meta );

				if ( $dry_run ) {
					$merged = array_replace_recursive( $existing_meta, $meta );
					// Null in the patch (single-entry clear) becomes a null
					// value in the merged map; the add-on would then try to
					// read field id `null`. Strip nulls here so dry_run
					// reflects what will actually persist.
					$merged = $this->apply_null_unsets( $merged, $meta );
					return [
						'operation' => 'update',
						'dry_run'   => true,
						'feed_id'   => $feed_id,
						'meta'      => $merged,
					];
				}

				// Merge for update: full meta replace would erase keys the
				// caller didn't supply. Recursive replace mirrors how the
				// admin UI saves partial settings.
				$merged_meta = array_replace_recursive( $existing_meta, $meta );
				// Strip any null values introduced by the patch — the
				// documented idiom for "clear this entry" / "delete this key".
				$merged_meta = $this->apply_null_unsets( $merged_meta, $meta );
				$result = GFAPI::update_feed( $feed_id, $merged_meta );
				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				return [
					'operation' => 'update',
					'feed'      => $this->serialize_feed( GFAPI::get_feed( $feed_id ) ),
				];

			case 'delete':
				$feed_id = absint( $input['feed_id'] ?? 0 );
				if ( 0 === $feed_id ) {
					return [ 'error' => __( 'feed_id is required for delete.', 'filter-abilities' ) ];
				}

				$existing = GFAPI::get_feed( $feed_id );
				if ( is_wp_error( $existing ) ) {
					return [ 'error' => $existing->get_error_message() ];
				}

				if ( $dry_run ) {
					return [
						'operation' => 'delete',
						'dry_run'   => true,
						'feed'      => $this->serialize_feed( $existing ),
					];
				}

				$result = GFAPI::delete_feed( $feed_id );
				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				return [
					'operation' => 'delete',
					'feed_id'   => $feed_id,
				];

			case 'set-active':
				$feed_id = absint( $input['feed_id'] ?? 0 );
				if ( 0 === $feed_id ) {
					return [ 'error' => __( 'feed_id is required for set-active.', 'filter-abilities' ) ];
				}
				if ( ! array_key_exists( 'is_active', $input ) ) {
					return [ 'error' => __( 'is_active is required for set-active.', 'filter-abilities' ) ];
				}

				$existing = GFAPI::get_feed( $feed_id );
				if ( is_wp_error( $existing ) ) {
					return [ 'error' => $existing->get_error_message() ];
				}

				$value = ! empty( $input['is_active'] ) ? 1 : 0;

				if ( $dry_run ) {
					return [
						'operation' => 'set-active',
						'dry_run'   => true,
						'feed_id'   => $feed_id,
						'is_active' => (bool) $value,
					];
				}

				// `GFAPI::update_feed_active` does NOT exist as a static method
				// (only as an instance method on GF_Feed_AddOn). Use
				// update_feed_property with the is_active column instead.
				$result = GFAPI::update_feed_property( $feed_id, 'is_active', $value );
				if ( false === $result ) {
					return [ 'error' => __( 'Failed to update feed active state.', 'filter-abilities' ) ];
				}
				if ( is_wp_error( $result ) ) {
					return [ 'error' => $result->get_error_message() ];
				}

				return [
					'operation' => 'set-active',
					'feed_id'   => $feed_id,
					'is_active' => (bool) $value,
				];

			default:
				return [ 'error' => sprintf( __( 'Unknown operation: %s.', 'filter-abilities' ), $operation ) ];
		}
	}

	// =========================================================================
	// Mailchimp picker execute callbacks
	// =========================================================================

	/**
	 * List every Mailchimp audience reachable with the GF add-on credentials.
	 *
	 * @since 1.8.0
	 *
	 * @return array<string, mixed>
	 */
	public function execute_list_mailchimp_audiences(): array {
		$api = $this->mailchimp_api();
		if ( is_wp_error( $api ) ) {
			return [ 'error' => $api->get_error_message() ];
		}

		// Mailchimp default count is 10; request the API max so a single call
		// covers most accounts. Accounts with >1000 lists are vanishingly
		// rare; we'd add explicit pagination if it ever comes up.
		$result = $api->get_lists( [ 'count' => 1000 ] );
		if ( is_wp_error( $result ) ) {
			return [ 'error' => $result->get_error_message() ];
		}

		$lists = isset( $result['lists'] ) && is_array( $result['lists'] ) ? $result['lists'] : [];

		$audiences = [];
		foreach ( $lists as $list ) {
			$audiences[] = [
				'id'           => (string) ( $list['id'] ?? '' ),
				'name'         => (string) ( $list['name'] ?? '' ),
				'member_count' => (int) ( $list['stats']['member_count'] ?? 0 ),
				'date_created' => (string) ( $list['date_created'] ?? '' ),
				'visibility'   => (string) ( $list['visibility'] ?? '' ),
			];
		}

		return [
			'total'     => count( $audiences ),
			'audiences' => $audiences,
		];
	}

	/**
	 * List static tags (segments) defined on a Mailchimp audience.
	 *
	 * The GF Mailchimp API wrapper does not expose segments; we hit the REST
	 * endpoint directly using the same access_token/server_prefix the add-on
	 * already has stored. Mailchimp models tags as `type=static` segments.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute_list_mailchimp_tags( array $input ): array {
		$audience_id = isset( $input['audience_id'] ) ? sanitize_text_field( (string) $input['audience_id'] ) : '';
		if ( '' === $audience_id ) {
			return [ 'error' => __( 'audience_id is required.', 'filter-abilities' ) ];
		}

		$result = $this->mailchimp_direct_get(
			'lists/' . rawurlencode( $audience_id ) . '/segments',
			[
				'type'  => 'static',
				'count' => 1000,
			]
		);
		if ( is_wp_error( $result ) ) {
			return [ 'error' => $result->get_error_message() ];
		}

		$segments = isset( $result['segments'] ) && is_array( $result['segments'] ) ? $result['segments'] : [];

		$tags = [];
		foreach ( $segments as $segment ) {
			$tags[] = [
				'id'           => (int) ( $segment['id'] ?? 0 ),
				'name'         => (string) ( $segment['name'] ?? '' ),
				'member_count' => (int) ( $segment['member_count'] ?? 0 ),
				'created_at'   => (string) ( $segment['created_at'] ?? '' ),
			];
		}

		return [
			'audience_id' => $audience_id,
			'total'       => count( $tags ),
			'tags'        => $tags,
		];
	}

	/**
	 * List merge fields defined on a Mailchimp audience.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute_list_mailchimp_merge_fields( array $input ): array {
		$audience_id = isset( $input['audience_id'] ) ? sanitize_text_field( (string) $input['audience_id'] ) : '';
		if ( '' === $audience_id ) {
			return [ 'error' => __( 'audience_id is required.', 'filter-abilities' ) ];
		}

		$api = $this->mailchimp_api();
		if ( is_wp_error( $api ) ) {
			return [ 'error' => $api->get_error_message() ];
		}

		$result = $api->get_list_merge_fields( $audience_id );
		if ( is_wp_error( $result ) ) {
			return [ 'error' => $result->get_error_message() ];
		}

		$raw = isset( $result['merge_fields'] ) && is_array( $result['merge_fields'] ) ? $result['merge_fields'] : [];

		// EMAIL is implicit on every Mailchimp list and never appears in the
		// merge_fields collection; prepend it so the picker shows the
		// complete set of `mappedFields` keys.
		$merge_fields = [
			[
				'tag'      => 'EMAIL',
				'name'     => __( 'Email Address', 'filter-abilities' ),
				'type'     => 'email',
				'required' => true,
				'public'   => true,
			],
		];

		foreach ( $raw as $field ) {
			$merge_fields[] = [
				'tag'      => (string) ( $field['tag'] ?? '' ),
				'name'     => (string) ( $field['name'] ?? '' ),
				'type'     => (string) ( $field['type'] ?? '' ),
				'required' => (bool) ( $field['required'] ?? false ),
				'public'   => (bool) ( $field['public'] ?? false ),
			];
		}

		return [
			'audience_id'  => $audience_id,
			'total'        => count( $merge_fields ),
			'merge_fields' => $merge_fields,
		];
	}

	/**
	 * List interest groups (groupings + interests) on a Mailchimp audience.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute_list_mailchimp_groups( array $input ): array {
		$audience_id = isset( $input['audience_id'] ) ? sanitize_text_field( (string) $input['audience_id'] ) : '';
		if ( '' === $audience_id ) {
			return [ 'error' => __( 'audience_id is required.', 'filter-abilities' ) ];
		}

		$api = $this->mailchimp_api();
		if ( is_wp_error( $api ) ) {
			return [ 'error' => $api->get_error_message() ];
		}

		$categories_result = $api->get_list_interest_categories( $audience_id );
		if ( is_wp_error( $categories_result ) ) {
			return [ 'error' => $categories_result->get_error_message() ];
		}

		$categories = isset( $categories_result['categories'] ) && is_array( $categories_result['categories'] )
			? $categories_result['categories']
			: [];

		// Each category triggers a second HTTP call for its interests. Cap
		// the fan-out to avoid timing out PHP on audiences with many
		// groupings, and signal truncation in the response so the caller
		// knows the picker view is incomplete.
		$max_categories       = 25;
		$total_categories     = count( $categories );
		$truncated            = $total_categories > $max_categories;
		$categories_to_expand = $truncated ? array_slice( $categories, 0, $max_categories ) : $categories;

		$groups = [];
		foreach ( $categories_to_expand as $category ) {
			$category_id = (string) ( $category['id'] ?? '' );

			$interests = [];
			if ( '' !== $category_id ) {
				$interests_result = $api->get_interest_category_interests( $audience_id, $category_id );
				if ( ! is_wp_error( $interests_result ) ) {
					$raw_interests = isset( $interests_result['interests'] ) && is_array( $interests_result['interests'] )
						? $interests_result['interests']
						: [];
					foreach ( $raw_interests as $interest ) {
						$interests[] = [
							'id'   => (string) ( $interest['id'] ?? '' ),
							'name' => (string) ( $interest['name'] ?? '' ),
						];
					}
				}
			}

			$groups[] = [
				'id'        => $category_id,
				'title'     => (string) ( $category['title'] ?? '' ),
				'type'      => (string) ( $category['type'] ?? '' ),
				'interests' => $interests,
			];
		}

		$response = [
			'audience_id' => $audience_id,
			'total'       => count( $groups ),
			'groups'      => $groups,
		];
		if ( $truncated ) {
			$response['truncated']        = true;
			$response['total_categories'] = $total_categories;
			$response['note']             = sprintf(
				/* translators: 1: shown count, 2: total */
				__( 'Showing first %1$d of %2$d interest categories. Add a separate ability call or contact Mailchimp support if you need to enumerate them all.', 'filter-abilities' ),
				$max_categories,
				$total_categories
			);
		}
		return $response;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Serialise a Gravity Forms form array for return.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $form          Form array as returned by GFAPI::get_form().
	 * @param bool                 $include_feeds Whether to attach the form's add-on feeds.
	 * @return array<string, mixed>
	 */
	private function serialize_form( array $form, bool $include_feeds = false ): array {
		$fields = [];
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$fields[] = $this->serialize_field( $field );
			}
		}

		$serialized = [
			'id'            => (int) ( $form['id'] ?? 0 ),
			'title'         => (string) ( $form['title'] ?? '' ),
			'description'   => (string) ( $form['description'] ?? '' ),
			'is_active'     => (bool) ( $form['is_active'] ?? true ),
			'is_trash'      => (bool) ( $form['is_trash'] ?? false ),
			'date_created'  => (string) ( $form['date_created'] ?? '' ),
			'fields'        => $fields,
			'confirmations' => isset( $form['confirmations'] ) && is_array( $form['confirmations'] )
				? $form['confirmations']
				: (object) [],
			'notifications' => isset( $form['notifications'] ) && is_array( $form['notifications'] )
				? $form['notifications']
				: (object) [],
			'settings'      => isset( $form['settings'] ) && is_array( $form['settings'] )
				? $form['settings']
				: (object) [],
			'button'        => $form['button'] ?? null,
			'nextFieldId'   => isset( $form['nextFieldId'] ) ? (int) $form['nextFieldId'] : null,
		];

		if ( $include_feeds && ! empty( $form['id'] ) ) {
			$feeds = GFAPI::get_feeds( null, (int) $form['id'], null, null );
			if ( is_wp_error( $feeds ) ) {
				$serialized['feeds'] = [];
			} else {
				$serialized['feeds'] = array_map( [ $this, 'serialize_feed' ], $feeds );
			}
		}

		return $serialized;
	}

	/**
	 * Serialise a GF_Field instance (or array) for return.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $field GF_Field instance or already-array representation.
	 * @return array<string, mixed>
	 */
	private function serialize_field( $field ): array {
		if ( is_object( $field ) ) {
			$vars = get_object_vars( $field );
		} elseif ( is_array( $field ) ) {
			$vars = $field;
		} else {
			return [];
		}

		// Normalise types for transport.
		if ( isset( $vars['id'] ) ) {
			$vars['id'] = (int) $vars['id'];
		}
		if ( isset( $vars['formId'] ) ) {
			$vars['formId'] = (int) $vars['formId'];
		}

		return $vars;
	}

	/**
	 * Serialise a feed array for return.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed>|null $feed
	 * @return array<string, mixed>
	 */
	private function serialize_feed( $feed ): array {
		if ( ! is_array( $feed ) ) {
			return [];
		}

		// Re-nest field-map prefixed keys so callers see the same shape they
		// should write. The raw flat keys remain available alongside the
		// collapsed view for anyone who needs them.
		$meta = is_array( $feed['meta'] ?? null ) ? $feed['meta'] : [];
		$meta = $this->unflatten_field_map_meta( $meta );

		return [
			'id'          => (int) ( $feed['id'] ?? 0 ),
			'form_id'     => (int) ( $feed['form_id'] ?? 0 ),
			'addon_slug'  => (string) ( $feed['addon_slug'] ?? '' ),
			'is_active'   => (bool) ( $feed['is_active'] ?? false ),
			'feed_order'  => isset( $feed['feed_order'] ) ? (int) $feed['feed_order'] : 0,
			'meta'        => empty( $meta ) ? (object) [] : $meta,
		];
	}

	/**
	 * Locate the array index of a field by its id within $form['fields'].
	 *
	 * @since 1.8.0
	 *
	 * @param array<int, mixed> $fields
	 * @param int               $field_id
	 * @return int|null
	 */
	private function find_field_index( array $fields, int $field_id ): ?int {
		foreach ( $fields as $i => $field ) {
			$fid = is_object( $field ) ? (int) ( $field->id ?? 0 ) : (int) ( $field['id'] ?? 0 );
			if ( $fid === $field_id ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Insert a field into the fields array at the requested position.
	 *
	 * @since 1.8.0
	 *
	 * @param array<int, mixed> $fields
	 * @param mixed             $field   GF_Field or array.
	 * @param int|string        $position Numeric index, "start", or "end".
	 * @return array<int, mixed>
	 */
	private function insert_field_at( array $fields, $field, $position ): array {
		if ( 'start' === $position ) {
			array_unshift( $fields, $field );
			return $fields;
		}
		if ( 'end' === $position || null === $position || '' === $position ) {
			$fields[] = $field;
			return $fields;
		}
		$index = max( 0, min( count( $fields ), (int) $position ) );
		array_splice( $fields, $index, 0, [ $field ] );
		return $fields;
	}

	/**
	 * Merge a property patch into a GF_Field-like value.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed                $existing GF_Field or array.
	 * @param array<string, mixed> $patch
	 * @return GF_Field
	 */
	private function merge_field_properties( $existing, array $patch, int $form_id = 0 ): GF_Field {
		if ( is_object( $existing ) ) {
			$existing_props = get_object_vars( $existing );
		} elseif ( is_array( $existing ) ) {
			$existing_props = $existing;
		} else {
			$existing_props = [];
		}

		$merged = array_replace_recursive( $existing_props, $patch );

		// Indexed-array properties must be REPLACED wholesale rather than
		// recursive-merged. array_replace_recursive() merges by index, so
		// passing rules: [A] to replace an existing rules: [X, Y, Z] would
		// leak Y and Z into the result. Same hazard for choices/inputs on
		// select-type fields. When the caller supplies one of these in the
		// patch, we honour the patch exactly.
		foreach ( [ 'conditionalLogic', 'choices', 'inputs' ] as $indexed_prop ) {
			if ( array_key_exists( $indexed_prop, $patch ) ) {
				$merged[ $indexed_prop ] = $patch[ $indexed_prop ];
			}
		}

		// Honor explicit-null as "delete this property" rather than "store
		// null"; without this, conditionalLogic: null leaves a null key that
		// some downstream consumers can choke on.
		$merged = $this->apply_null_unsets( $merged, $patch );

		// Lock surrogate keys that must never come from caller input. The id
		// is locked at the call site (because it's identified independently
		// of the patch), but formId — if patched accidentally — would leave
		// the field with stale parent-form metadata.
		if ( $form_id > 0 ) {
			$merged['formId'] = $form_id;
		}

		// Re-build via GF_Fields::create so the right subclass is used.
		return GF_Fields::create( $merged );
	}

	/**
	 * Check whether a Gravity Forms add-on with the given slug is registered.
	 *
	 * @since 1.8.0
	 *
	 * @param string $slug
	 * @return bool
	 */
	/**
	 * Canonical conditionalLogic operators (Gravity Forms 2.9 source of truth).
	 *
	 * Mirrors GFFormsModel::is_valid_operator(). GF silently coerces unknown
	 * operators to "is" at runtime, so we validate up-front to surface typos
	 * as a tool error instead of as a rule that never matches.
	 */
	private const CONDITIONAL_LOGIC_OPERATORS = [
		'is', 'isnot', '<>', 'not in', 'in',
		'>', '<', '>=', '<=',
		'contains', 'starts_with', 'ends_with', 'like',
	];

	/**
	 * Validate a conditionalLogic object against a form's field set.
	 *
	 * GF tolerates a lot of malformed input — invalid actionType is silently
	 * coerced to "show", invalid operators to "is", empty rules arrays dropped,
	 * nonexistent fieldIds simply never match. That's defensible behaviour for
	 * a UI but catastrophic for AI-agent workflows, which expect "write logic
	 * → it fires when the conditions say it should." This validator catches
	 * the silent-failure cases up-front and returns structured errors.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed                $logic     Caller-supplied conditionalLogic value.
	 * @param array<string, mixed> $form      The form the logic targets (for fieldId existence).
	 * @param string               $base_path JSONPath-like prefix used in error messages.
	 * @return array<int, array{path: string, message: string}> Empty array when valid.
	 */
	private function validate_conditional_logic( $logic, array $form, string $base_path = 'conditionalLogic' ): array {
		$errors = [];

		if ( ! is_array( $logic ) ) {
			$errors[] = [
				'path'    => $base_path,
				'message' => __( 'conditionalLogic must be an object with actionType, logicType, and rules.', 'filter-abilities' ),
			];
			return $errors;
		}

		// actionType — optional for confirmations/notifications/feeds (GF
		// defaults to "show"), but if supplied it must be one of show|hide.
		if ( array_key_exists( 'actionType', $logic ) ) {
			if ( ! in_array( $logic['actionType'], [ 'show', 'hide' ], true ) ) {
				$errors[] = [
					'path'    => $base_path . '.actionType',
					'message' => sprintf(
						/* translators: %s: invalid actionType value */
						__( 'actionType must be "show" or "hide" (got "%s"). GF would silently coerce this to "show".', 'filter-abilities' ),
						is_scalar( $logic['actionType'] ) ? (string) $logic['actionType'] : gettype( $logic['actionType'] )
					),
				];
			}
		}

		// logicType — required in practice. GF coerces unknown to "all".
		if ( ! isset( $logic['logicType'] ) ) {
			$errors[] = [
				'path'    => $base_path . '.logicType',
				'message' => __( 'logicType is required. Use "all" (every rule must match) or "any" (any rule matches).', 'filter-abilities' ),
			];
		} elseif ( ! in_array( $logic['logicType'], [ 'all', 'any' ], true ) ) {
			$errors[] = [
				'path'    => $base_path . '.logicType',
				'message' => sprintf(
					/* translators: %s: invalid logicType value */
					__( 'logicType must be "all" or "any" (got "%s"). GF would silently coerce this to "all".', 'filter-abilities' ),
					is_scalar( $logic['logicType'] ) ? (string) $logic['logicType'] : gettype( $logic['logicType'] )
				),
			];
		}

		// rules — required, non-empty array. GF strips empty rules silently
		// and treats an empty logic object as "no logic", which means the
		// confirmation/notification/feed always fires — exactly the opposite
		// of what the caller probably intended.
		if ( ! isset( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
			$errors[] = [
				'path'    => $base_path . '.rules',
				'message' => __( 'rules is required and must be a non-empty array.', 'filter-abilities' ),
			];
			return $errors;
		}
		if ( empty( $logic['rules'] ) ) {
			$errors[] = [
				'path'    => $base_path . '.rules',
				'message' => __( 'rules must be a non-empty array. Empty logic objects cause the parent (confirmation/notification/feed) to fire unconditionally.', 'filter-abilities' ),
			];
			return $errors;
		}

		// Pre-compute the set of valid parent field ids on the form for O(1)
		// lookup. Composite fieldIds (e.g. "1.3" = sub-input on field 1) get
		// split on the dot — the parent id is what we check.
		$valid_field_ids = [];
		if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$fid = is_object( $field ) ? ( $field->id ?? null ) : ( $field['id'] ?? null );
				if ( null !== $fid && '' !== $fid ) {
					$valid_field_ids[ (string) $fid ] = true;
				}
			}
		}

		foreach ( $logic['rules'] as $i => $rule ) {
			$rule_path = sprintf( '%s.rules[%d]', $base_path, $i );

			if ( ! is_array( $rule ) ) {
				$errors[] = [
					'path'    => $rule_path,
					'message' => __( 'Each rule must be an object with fieldId, operator, and value.', 'filter-abilities' ),
				];
				continue;
			}

			// fieldId
			if ( ! isset( $rule['fieldId'] ) || '' === $rule['fieldId'] ) {
				$errors[] = [
					'path'    => $rule_path . '.fieldId',
					'message' => __( 'fieldId is required.', 'filter-abilities' ),
				];
			} else {
				$field_id_str = (string) $rule['fieldId'];
				// Composite sub-inputs like "1.3" reference the parent field 1.
				$parent_id = strpos( $field_id_str, '.' ) !== false
					? substr( $field_id_str, 0, strpos( $field_id_str, '.' ) )
					: $field_id_str;
				if ( ! isset( $valid_field_ids[ $parent_id ] ) ) {
					$errors[] = [
						'path'    => $rule_path . '.fieldId',
						'message' => sprintf(
							/* translators: %s: the fieldId that doesn't resolve */
							__( 'fieldId "%s" does not exist on this form. The rule will never match and the condition will silently behave as if it were absent.', 'filter-abilities' ),
							$field_id_str
						),
					];
				}
			}

			// operator
			if ( ! isset( $rule['operator'] ) || '' === $rule['operator'] ) {
				$errors[] = [
					'path'    => $rule_path . '.operator',
					'message' => sprintf(
						/* translators: %s: comma-separated operator list */
						__( 'operator is required. Valid: %s.', 'filter-abilities' ),
						implode( ', ', self::CONDITIONAL_LOGIC_OPERATORS )
					),
				];
			} elseif ( ! in_array( strtolower( (string) $rule['operator'] ), self::CONDITIONAL_LOGIC_OPERATORS, true ) ) {
				$errors[] = [
					'path'    => $rule_path . '.operator',
					'message' => sprintf(
						/* translators: 1: invalid operator, 2: comma-separated list of valid operators */
						__( 'operator "%1$s" is not valid. GF would silently coerce this to "is". Valid: %2$s.', 'filter-abilities' ),
						(string) $rule['operator'],
						implode( ', ', self::CONDITIONAL_LOGIC_OPERATORS )
					),
				];
			}

			// value — required as a key (empty string is permitted; the
			// "is empty" check is a thing). Missing key entirely is wrong.
			if ( ! array_key_exists( 'value', $rule ) ) {
				$errors[] = [
					'path'    => $rule_path . '.value',
					'message' => __( 'value key is required (empty string is allowed, but the key must be present).', 'filter-abilities' ),
				];
			}
		}

		return $errors;
	}

	/**
	 * Validate and surface conditionalLogic errors via the [error] envelope.
	 *
	 * Convenience for write callbacks: pass the patch, the (loaded) form, and
	 * the key inside the patch where conditionalLogic lives. Returns null when
	 * the patch contains no conditionalLogic or it validates clean; returns a
	 * `[ 'error' => ..., 'errors' => [...] ]` array suitable for direct return
	 * otherwise.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed>|null $container Container that may hold conditionalLogic.
	 * @param array<string, mixed>      $form      The target form.
	 * @param string                    $base_path Error path prefix.
	 * @return array<string, mixed>|null
	 */
	private function maybe_reject_invalid_conditional_logic( $container, array $form, string $base_path = 'conditionalLogic' ): ?array {
		if ( ! is_array( $container ) || ! array_key_exists( 'conditionalLogic', $container ) ) {
			return null;
		}
		if ( null === $container['conditionalLogic'] ) {
			// Explicit null is the documented way to clear logic — allow.
			return null;
		}

		$errors = $this->validate_conditional_logic( $container['conditionalLogic'], $form, $base_path );
		if ( empty( $errors ) ) {
			return null;
		}

		return [
			'error'  => __( 'Invalid conditionalLogic. Use filter/validate-conditional-logic to lint before writing.', 'filter-abilities' ),
			'errors' => $errors,
		];
	}

	/**
	 * Flatten field-map shorthand keys in feed meta.
	 *
	 * Gravity Forms add-ons that use a `field_map` setting store each mapping
	 * as a separate flat key prefixed with the setting name and an underscore
	 * (`mappedFields_EMAIL`, `mappedFields_FNAME`, ...). This is opaque to
	 * anyone constructing meta from scratch — the intuitive shape is a nested
	 * object. We accept both: if the caller passes a nested object under
	 * `mappedFields` (or any other recognised field-map key), expand it to
	 * the flat-prefixed form the add-on actually reads, and drop the original
	 * nested key so it doesn't pollute meta.
	 *
	 * Currently flattens: `mappedFields` (Mailchimp), `listFields` (generic),
	 * `customFields` (generic). Add more here if a new add-on uses the same
	 * convention with a different setting name.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	/**
	 * Sentinel meta key used to communicate "clear all entries with this
	 * prefix from the existing meta" between flatten_field_map_meta() and
	 * the feed update merge step. Stripped before persistence.
	 */
	private const FIELD_MAP_CLEAR_SENTINEL = '__filter_clear_field_maps__';

	/**
	 * Flatten field-map shorthand keys in feed meta.
	 *
	 * Returns the transformed meta, or a WP_Error if any individual mapping
	 * value is not a scalar (or null — null is the documented way to clear
	 * a single mapping). Empty associative arrays and explicit null at the
	 * top level both mean "clear every prefixed key" and are communicated
	 * to the merge layer via a sentinel meta key.
	 *
	 * Caller MUST handle the WP_Error return.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>|\WP_Error
	 */
	private function flatten_field_map_meta( array $meta ) {
		$field_map_keys = [ 'mappedFields', 'listFields', 'customFields' ];
		$clears         = [];

		foreach ( $field_map_keys as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				continue;
			}
			$mapping = $meta[ $key ];

			// `mappedFields: null` — explicit "clear all mappings".
			if ( null === $mapping ) {
				$clears[] = $key;
				unset( $meta[ $key ] );
				continue;
			}

			if ( ! is_array( $mapping ) ) {
				// Some other shape (scalar) — leave alone, GF probably
				// rejects but we don't second-guess the add-on.
				continue;
			}

			$string_keyed = array_filter( array_keys( $mapping ), 'is_string' );
			$is_assoc     = ! empty( $string_keyed );

			// `mappedFields: {}` — empty associative, treat as "clear all
			// mappings". We can't distinguish JSON's `{}` from `[]` after
			// PHP decode, but feed mappings always use string keys; an
			// empty input is most likely intended as a clear.
			if ( empty( $mapping ) ) {
				$clears[] = $key;
				unset( $meta[ $key ] );
				continue;
			}

			// Indexed array (no string keys) — caller has probably done
			// their own flattening; leave alone.
			if ( ! $is_assoc ) {
				continue;
			}

			foreach ( $mapping as $merge_tag => $form_field_id ) {
				// Values must be scalar (string field id like "3" or "1.3",
				// or int) or null (single-entry clear). Anything else
				// silently breaks the feed at submission time.
				if ( ! is_scalar( $form_field_id ) && ! is_null( $form_field_id ) ) {
					return new \WP_Error(
						'invalid_field_map_value',
						sprintf(
							/* translators: 1: field-map key (e.g. mappedFields), 2: merge tag */
							__( '%1$s[%2$s] must be a string field id (e.g. "3" or "1.3"), not %3$s.', 'filter-abilities' ),
							$key,
							(string) $merge_tag,
							gettype( $form_field_id )
						)
					);
				}
				$meta[ $key . '_' . $merge_tag ] = $form_field_id;
			}
			unset( $meta[ $key ] );
		}

		if ( ! empty( $clears ) ) {
			$meta[ self::FIELD_MAP_CLEAR_SENTINEL ] = $clears;
		}

		return $meta;
	}

	/**
	 * Apply the field-map "clear all entries with this prefix" sentinel.
	 *
	 * The sentinel is emitted by flatten_field_map_meta() when the caller
	 * passes `mappedFields: null` or `mappedFields: {}`. This walks the
	 * existing feed meta and removes every key with the matching prefix.
	 * The sentinel itself is then stripped from the patch.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $existing  Existing feed meta (mutated).
	 * @param array<string, mixed> $patch     Flattened patch (mutated to remove the sentinel).
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>} [$existing, $patch]
	 */
	private function apply_field_map_clears( array $existing, array $patch ): array {
		if ( ! isset( $patch[ self::FIELD_MAP_CLEAR_SENTINEL ] ) ) {
			return [ $existing, $patch ];
		}
		$clears = (array) $patch[ self::FIELD_MAP_CLEAR_SENTINEL ];
		unset( $patch[ self::FIELD_MAP_CLEAR_SENTINEL ] );

		foreach ( $clears as $prefix_key ) {
			if ( ! is_string( $prefix_key ) ) {
				continue;
			}
			$prefix = $prefix_key . '_';
			foreach ( array_keys( $existing ) as $existing_key ) {
				if ( is_string( $existing_key ) && 0 === strpos( $existing_key, $prefix ) ) {
					unset( $existing[ $existing_key ] );
				}
			}
		}

		return [ $existing, $patch ];
	}

	/**
	 * Re-nest field-map prefixed keys for human-readable output.
	 *
	 * The inverse of flatten_field_map_meta(): collapses `mappedFields_EMAIL`,
	 * `mappedFields_FNAME` etc. on read into a nested `mappedFields` object,
	 * so feed introspection shows the same shape callers should write.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private function unflatten_field_map_meta( array $meta ): array {
		$field_map_keys = [ 'mappedFields', 'listFields', 'customFields' ];

		foreach ( $field_map_keys as $key ) {
			$prefix     = $key . '_';
			$prefix_len = strlen( $prefix );
			$collapsed  = [];
			foreach ( $meta as $name => $value ) {
				if ( is_string( $name ) && 0 === strpos( $name, $prefix ) ) {
					$collapsed[ substr( $name, $prefix_len ) ] = $value;
					unset( $meta[ $name ] );
				}
			}
			if ( ! empty( $collapsed ) ) {
				$meta[ $key ] = $collapsed;
			}
		}

		return $meta;
	}

	/**
	 * Apply explicit-null deletions from a patch on top of a merged result.
	 *
	 * Convention: when a caller wants to clear a property (e.g. drop
	 * conditionalLogic from a confirmation), they pass `key: null` in the
	 * patch. PHP's array_merge happily preserves null values, leaving a key
	 * with a null value behind — which GF then stores. This helper walks the
	 * patch and unsets every key the caller set explicitly to null on the
	 * merged result.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $merged
	 * @param array<string, mixed> $patch
	 * @return array<string, mixed>
	 */
	private function apply_null_unsets( array $merged, array $patch ): array {
		foreach ( $patch as $key => $value ) {
			if ( null === $value ) {
				unset( $merged[ $key ] );
			}
		}
		return $merged;
	}

	/**
	 * Validate feed-meta conditionalLogic, which lives at
	 * meta.feed_condition_conditional_logic_object.conditionalLogic.
	 *
	 * Returns null when there's nothing to validate or it validates clean;
	 * otherwise an error array ready for direct return.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $meta    Feed meta from the caller.
	 * @param int   $form_id Target form id (for field-existence checks).
	 * @return array<string, mixed>|null
	 */
	private function validate_feed_meta_conditional_logic( $meta, int $form_id ): ?array {
		if ( ! is_array( $meta ) || ! isset( $meta['feed_condition_conditional_logic_object'] ) ) {
			return null;
		}
		$wrapper = $meta['feed_condition_conditional_logic_object'];
		if ( ! is_array( $wrapper ) || ! array_key_exists( 'conditionalLogic', $wrapper ) ) {
			return null;
		}
		if ( null === $wrapper['conditionalLogic'] ) {
			return null;
		}

		$form = $form_id > 0 ? GFAPI::get_form( $form_id ) : null;
		if ( ! is_array( $form ) ) {
			// We can still validate structure without a form, but skip the
			// fieldId-existence check by passing an empty fields list — the
			// caller likely wants to know structural issues at least.
			$form = [ 'fields' => [] ];
		}

		$errors = $this->validate_conditional_logic(
			$wrapper['conditionalLogic'],
			$form,
			'meta.feed_condition_conditional_logic_object.conditionalLogic'
		);
		if ( empty( $errors ) ) {
			return null;
		}

		return [
			'error'  => __( 'Invalid feed conditionalLogic. Use filter/validate-conditional-logic to lint before writing.', 'filter-abilities' ),
			'errors' => $errors,
		];
	}

	private function addon_is_registered( string $slug ): bool {
		if ( '' === $slug || ! class_exists( 'GFAddOn' ) ) {
			return false;
		}
		// GF returns instances keyed by slug when both flags are true.
		$addons = GFAddOn::get_registered_addons( true, true );
		return is_array( $addons ) && isset( $addons[ $slug ] );
	}

	/**
	 * Return a ready-to-use Mailchimp API wrapper instance, or a WP_Error.
	 *
	 * Uses the Gravity Forms Mailchimp add-on's stored credentials and its
	 * already-wired auth/refresh logic; we never see the access token.
	 *
	 * @since 1.8.0
	 *
	 * @return GF_MailChimp_API|\WP_Error
	 */
	private function mailchimp_api() {
		if ( ! function_exists( 'gf_mailchimp' ) ) {
			return new \WP_Error( 'mailchimp_inactive', __( 'Gravity Forms Mailchimp add-on is not installed or activated.', 'filter-abilities' ) );
		}

		$addon = gf_mailchimp();
		if ( ! $addon || ! method_exists( $addon, 'initialize_api' ) ) {
			return new \WP_Error( 'mailchimp_unavailable', __( 'Mailchimp add-on instance is unavailable.', 'filter-abilities' ) );
		}

		// initialize_api() returns bool: true if auth succeeds (and assigns
		// the GF_MailChimp_API instance to $addon->api), false otherwise.
		$ok = $addon->initialize_api();
		if ( ! $ok || ! isset( $addon->api ) || ! is_object( $addon->api ) ) {
			return new \WP_Error( 'mailchimp_auth_failed', __( 'Could not authenticate with Mailchimp. Check the add-on settings.', 'filter-abilities' ) );
		}

		return $addon->api;
	}

	/**
	 * Hit a Mailchimp REST endpoint that the GF wrapper does not expose.
	 *
	 * Used for `/lists/{id}/segments?type=static` (tags). Auth uses the
	 * access_token + server_prefix already negotiated by the add-on.
	 *
	 * @since 1.8.0
	 *
	 * @param string               $path  Endpoint path, no leading slash (e.g. `lists/abc/segments`).
	 * @param array<string, mixed> $query Query string args.
	 * @return array<mixed>|\WP_Error Decoded JSON payload, or WP_Error.
	 */
	private function mailchimp_direct_get( string $path, array $query = [] ) {
		if ( ! function_exists( 'gf_mailchimp' ) ) {
			return new \WP_Error( 'mailchimp_inactive', __( 'Gravity Forms Mailchimp add-on is not installed or activated.', 'filter-abilities' ) );
		}

		$addon = gf_mailchimp();
		if ( ! $addon || ! method_exists( $addon, 'get_plugin_settings' ) ) {
			return new \WP_Error( 'mailchimp_unavailable', __( 'Mailchimp add-on instance is unavailable.', 'filter-abilities' ) );
		}

		// Make sure auth has been negotiated (this is what populates the
		// access_token / server_prefix settings during OAuth flows).
		if ( method_exists( $addon, 'initialize_api' ) ) {
			$addon->initialize_api();
		}

		$settings      = $addon->get_plugin_settings();
		$access_token  = is_array( $settings ) ? (string) ( $settings['access_token'] ?? '' ) : '';
		$server_prefix = is_array( $settings ) ? (string) ( $settings['server_prefix'] ?? '' ) : '';

		if ( '' === $access_token || '' === $server_prefix ) {
			return new \WP_Error( 'mailchimp_auth_missing', __( 'Mailchimp credentials are not configured.', 'filter-abilities' ) );
		}

		$url = sprintf( 'https://%s.api.mailchimp.com/3.0/%s', $server_prefix, ltrim( $path, '/' ) );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_get( $url, [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$detail = is_array( $data ) ? (string) ( $data['detail'] ?? $body ) : $body;
			return new \WP_Error(
				'mailchimp_http_' . $code,
				sprintf(
					/* translators: 1: HTTP status, 2: error detail */
					__( 'Mailchimp request failed (HTTP %1$d): %2$s', 'filter-abilities' ),
					$code,
					$detail
				)
			);
		}

		// A 2xx with an undecodable / non-array body is a transport problem
		// (proxy interstitial, gzip double-decode, truncated response). Do
		// NOT return an empty array — that would look indistinguishable from
		// a legitimate empty result and silently mislead the caller.
		if ( ! is_array( $data ) ) {
			if ( '' === trim( $body ) ) {
				return new \WP_Error(
					'mailchimp_empty_body',
					__( 'Mailchimp returned an empty response body.', 'filter-abilities' )
				);
			}
			return new \WP_Error(
				'mailchimp_decode_failed',
				sprintf(
					/* translators: %s: first 200 chars of malformed body */
					__( 'Mailchimp response could not be decoded as JSON. Body starts with: %s', 'filter-abilities' ),
					substr( $body, 0, 200 )
				)
			);
		}

		return $data;
	}
}
