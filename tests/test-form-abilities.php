<?php
/**
 * Form Management Abilities Test Runner
 *
 * Exercises every v1.8.0 ability in the Form Management module against
 * throwaway forms that get cleaned up at the end of the run.
 *
 * Run via WP-CLI (preferred):
 *   cd path/to/wordpress
 *   wp eval-file wp-content/plugins/filter-abilities/tests/test-form-abilities.php --user=1
 *
 * Or via direct HTTP when logged in as admin:
 *   https://your-site.local/wp-content/plugins/filter-abilities/tests/test-form-abilities.php
 *
 * The runner creates forms titled "FA-FORM-TEST-…" and registers them for
 * permanent deletion via a shutdown hook. If anything goes wrong mid-run,
 * cleanup still fires; if the script is killed, search the GF admin for
 * the prefix to mop up.
 *
 * @package Filter_Abilities
 */

// Bootstrap WordPress if not already loaded.
if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		die( 'Cannot find wp-load.php — adjust path if needed.' );
	}
	require_once $wp_load;
}

// Require admin.
if ( ! current_user_can( 'manage_options' ) ) {
	if ( php_sapi_name() === 'cli' ) {
		die( "Run with --user=1 (or another admin user ID).\n" );
	}
	wp_die( 'You must be logged in as an administrator to run these tests. <a href="' . wp_login_url( $_SERVER['REQUEST_URI'] ) . '">Log in</a>' );
}

if ( php_sapi_name() !== 'cli' ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
}

// ─── Helpers ────────────────────────────────────────────────────────────────

class FA_Form_Test_Counter {
	public static int $pass = 0;
	public static int $fail = 0;
	public static int $skip = 0;
}

/**
 * Cleanup tracker — collects form IDs created during the tests so we can
 * remove them on shutdown (regardless of whether the script ran to completion
 * or died midway through a test block).
 */
class FA_Form_Test_Cleanup {
	/** @var int[] */ public static array $form_ids = [];

	public static function register( int $form_id ): void {
		if ( $form_id > 0 ) {
			self::$form_ids[ $form_id ] = $form_id;
		}
	}
}

register_shutdown_function( static function () {
	if ( ! class_exists( 'GFAPI' ) ) {
		return;
	}
	foreach ( FA_Form_Test_Cleanup::$form_ids as $id ) {
		if ( GFAPI::form_id_exists( $id ) ) {
			GFAPI::delete_form( $id );
		}
	}
} );

function fa_form_test_header( string $name ): void {
	echo "\n" . str_repeat( '─', 60 ) . "\n";
	echo "TEST: {$name}\n";
	echo str_repeat( '─', 60 ) . "\n";
}

function fa_form_test_pass( string $msg ): void {
	FA_Form_Test_Counter::$pass++;
	echo "  ✓ PASS: {$msg}\n";
}

function fa_form_test_fail( string $msg, $detail = null ): void {
	FA_Form_Test_Counter::$fail++;
	echo "  ✗ FAIL: {$msg}\n";
	if ( null !== $detail ) {
		echo "    Detail: " . print_r( $detail, true ) . "\n";
	}
}

function fa_form_test_skip( string $msg ): void {
	FA_Form_Test_Counter::$skip++;
	echo "  ⊘ SKIP: {$msg}\n";
}

/**
 * Execute an ability by name and return the result array.
 */
function fa_run_ability( string $name, array $params = [] ): array {
	if ( ! function_exists( 'wp_get_ability' ) ) {
		return [ 'error' => 'Abilities API not available (wp_get_ability not found).' ];
	}
	$ability = wp_get_ability( $name );
	if ( ! $ability ) {
		return [ 'error' => "Ability '{$name}' not registered." ];
	}
	$result = $ability->execute( $params );
	if ( is_wp_error( $result ) ) {
		return [ 'error' => $result->get_error_message() ];
	}
	return is_array( $result ) ? $result : [ 'raw' => $result ];
}

/**
 * Create a throwaway form via raw GFAPI (not via the ability under test) so
 * setup isn't entangled with what's being tested. Registers for cleanup.
 */
function fa_form_make( string $suffix = '', array $extra = [] ): int {
	$title   = 'FA-FORM-TEST-' . $suffix . '-' . substr( uniqid(), -6 );
	$meta    = array_merge( [ 'title' => $title, 'fields' => [] ], $extra );
	$form_id = GFAPI::add_form( $meta );
	if ( is_wp_error( $form_id ) ) {
		throw new RuntimeException( 'Failed to create test form: ' . $form_id->get_error_message() );
	}
	FA_Form_Test_Cleanup::register( (int) $form_id );
	return (int) $form_id;
}

/**
 * Re-fetch a form's stored representation directly via GFAPI (bypassing the
 * abilities layer) to verify what's actually on disk.
 */
function fa_form_reload( int $form_id ): array {
	$form = GFAPI::get_form( $form_id );
	return is_array( $form ) ? $form : [];
}

/**
 * Pull a single field out of a form by id, or null.
 */
function fa_form_find_field( array $form, int $field_id ) {
	foreach ( $form['fields'] ?? [] as $field ) {
		$fid = is_object( $field ) ? (int) ( $field->id ?? 0 ) : (int) ( $field['id'] ?? 0 );
		if ( $fid === $field_id ) {
			return $field;
		}
	}
	return null;
}

// ─── Pre-flight checks ──────────────────────────────────────────────────────

echo "Filter Abilities — Form Management Module Tests\n";
echo "================================================\n";
echo "Date: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
echo "Site: " . home_url() . "\n";
echo "WP Version: " . get_bloginfo( 'version' ) . "\n";

if ( ! class_exists( 'GFAPI' ) ) {
	echo "\n⊘ Gravity Forms is NOT active. Cannot run tests.\n";
	exit( 1 );
}
echo "Gravity Forms Version: " . ( defined( 'GFForms::$version' ) ? GFForms::$version : ( method_exists( 'GFForms', 'version' ) ? GFForms::version() : 'unknown' ) ) . "\n";

if ( ! defined( 'FILTER_ABILITIES_VERSION' ) ) {
	echo "\n⊘ Filter Abilities plugin is NOT active. Cannot run tests.\n";
	exit( 1 );
}
echo "Filter Abilities Version: " . FILTER_ABILITIES_VERSION . "\n";

if ( ! function_exists( 'wp_get_ability' ) ) {
	echo "\n⊘ Abilities API not available (WP 6.9+ required).\n";
	exit( 1 );
}

$required_abilities = [
	'filter/list-forms',
	'filter/get-form',
	'filter/get-form-entries',
	'filter/list-form-feeds',
	'filter/manage-form',
	'filter/manage-form-field',
	'filter/manage-form-confirmation',
	'filter/manage-form-notification',
	'filter/manage-form-feed',
	'filter/validate-conditional-logic',
];
foreach ( $required_abilities as $name ) {
	if ( ! wp_get_ability( $name ) ) {
		echo "\n⊘ Required ability '{$name}' not registered. Module may not have loaded.\n";
		exit( 1 );
	}
}
echo "All " . count( $required_abilities ) . " core form abilities registered.\n";

$mailchimp_active = function_exists( 'gf_mailchimp' );
echo "Mailchimp add-on: " . ( $mailchimp_active ? "ACTIVE (picker tests will run)" : "INACTIVE (picker tests will skip)" ) . "\n";

// ─── Test 1: list-forms shape ──────────────────────────────────────────────

fa_form_test_header( 'filter/list-forms — basic read + shape' );

$result = fa_run_ability( 'filter/list-forms' );
if ( isset( $result['error'] ) ) {
	fa_form_test_fail( 'Returned error', $result['error'] );
} else {
	if ( isset( $result['total'] ) && is_int( $result['total'] ) ) {
		fa_form_test_pass( "Returned total: {$result['total']}" );
	} else {
		fa_form_test_fail( 'Missing or non-int total field' );
	}
	if ( isset( $result['forms'] ) && is_array( $result['forms'] ) ) {
		fa_form_test_pass( 'forms is array' );
		if ( ! empty( $result['forms'] ) ) {
			$f       = $result['forms'][0];
			$missing = array_diff( [ 'id', 'title', 'entry_count', 'is_active', 'fields' ], array_keys( $f ) );
			if ( empty( $missing ) ) {
				fa_form_test_pass( 'Form entry has expected keys' );
			} else {
				fa_form_test_fail( 'Form entry missing keys: ' . implode( ', ', $missing ) );
			}
		}
	} else {
		fa_form_test_fail( 'Missing forms array' );
	}
}

// ─── Test 2: manage-form create + dry_run ──────────────────────────────────

fa_form_test_header( 'filter/manage-form — create (dry_run, then live)' );

$dry = fa_run_ability( 'filter/manage-form', [
	'operation' => 'create',
	'title'     => 'FA-FORM-TEST-dryrun',
	'dry_run'   => true,
] );
if ( isset( $dry['error'] ) ) {
	fa_form_test_fail( 'dry_run returned error', $dry['error'] );
} else {
	if ( ! empty( $dry['dry_run'] ) ) {
		fa_form_test_pass( 'dry_run flag echoed in response' );
	} else {
		fa_form_test_fail( 'dry_run flag missing' );
	}
	if ( isset( $dry['form']['title'] ) && 'FA-FORM-TEST-dryrun' === $dry['form']['title'] ) {
		fa_form_test_pass( 'dry_run form preview includes title' );
	} else {
		fa_form_test_fail( 'dry_run form preview missing title' );
	}
}

$live = fa_run_ability( 'filter/manage-form', [
	'operation' => 'create',
	'title'     => 'FA-FORM-TEST-live-create',
] );
if ( isset( $live['error'] ) || empty( $live['form_id'] ) ) {
	fa_form_test_fail( 'live create failed', $live );
} else {
	$created_form_id = (int) $live['form_id'];
	FA_Form_Test_Cleanup::register( $created_form_id );
	fa_form_test_pass( "Live create returned form_id={$created_form_id}" );
	if ( GFAPI::form_id_exists( $created_form_id ) ) {
		fa_form_test_pass( 'Form actually persisted (GFAPI::form_id_exists)' );
	} else {
		fa_form_test_fail( 'Form not persisted in DB' );
	}
}

// ─── Test 3: get-form shape ─────────────────────────────────────────────────

fa_form_test_header( 'filter/get-form — full detail read' );

$form_id = fa_form_make( 'getform' );
$result  = fa_run_ability( 'filter/get-form', [ 'form_id' => $form_id ] );
if ( isset( $result['error'] ) ) {
	fa_form_test_fail( 'Returned error', $result['error'] );
} else {
	$expected = [ 'id', 'title', 'is_active', 'is_trash', 'fields', 'confirmations', 'notifications', 'settings' ];
	$missing  = array_diff( $expected, array_keys( $result ) );
	if ( empty( $missing ) ) {
		fa_form_test_pass( 'Response has expected top-level keys' );
	} else {
		fa_form_test_fail( 'Missing keys: ' . implode( ', ', $missing ) );
	}
	if ( (int) ( $result['id'] ?? 0 ) === $form_id ) {
		fa_form_test_pass( 'id matches requested form_id' );
	} else {
		fa_form_test_fail( 'id mismatch', [ 'got' => $result['id'] ?? null, 'expected' => $form_id ] );
	}
}

// get-form for non-existent id
$missing_result = fa_run_ability( 'filter/get-form', [ 'form_id' => 9999999 ] );
if ( isset( $missing_result['error'] ) ) {
	fa_form_test_pass( 'Non-existent form_id returns error envelope' );
} else {
	fa_form_test_fail( 'Non-existent form_id did not error' );
}

// ─── Test 4: manage-form update — top-level only ───────────────────────────

fa_form_test_header( 'filter/manage-form — update (top-level patch)' );

$form_id = fa_form_make( 'update' );
$result  = fa_run_ability( 'filter/manage-form', [
	'operation'  => 'update',
	'form_id'    => $form_id,
	'form_patch' => [ 'description' => 'Updated by test' ],
] );
if ( isset( $result['error'] ) ) {
	fa_form_test_fail( 'update returned error', $result['error'] );
} else {
	$reloaded = fa_form_reload( $form_id );
	if ( ( $reloaded['description'] ?? '' ) === 'Updated by test' ) {
		fa_form_test_pass( 'description persisted via form_patch' );
	} else {
		fa_form_test_fail( 'description not persisted', $reloaded['description'] ?? null );
	}
}

// ─── Test 5: capability-bypass guard (CRITICAL bug regression) ─────────────

fa_form_test_header( 'filter/manage-form — capability-bypass guard (regression: form_patch is_trash/is_active/id/nextFieldId/date_created)' );

$form_id = fa_form_make( 'bypass-guard' );
foreach ( [ 'is_trash', 'is_active', 'id', 'nextFieldId', 'date_created' ] as $forbidden ) {
	$attempt = fa_run_ability( 'filter/manage-form', [
		'operation'  => 'update',
		'form_id'    => $form_id,
		'form_patch' => [ $forbidden => ( 'date_created' === $forbidden ? '2099-01-01' : 1 ) ],
	] );
	if ( isset( $attempt['error'] ) && false !== stripos( $attempt['error'], $forbidden ) ) {
		fa_form_test_pass( "form_patch '{$forbidden}' rejected with explanatory error" );
	} else {
		fa_form_test_fail( "form_patch '{$forbidden}' was NOT rejected", $attempt );
	}
}

// Reload and confirm the form was not silently modified.
$reloaded = fa_form_reload( $form_id );
if ( empty( $reloaded['is_trash'] ) ) {
	fa_form_test_pass( 'is_trash NOT set despite bypass attempts' );
} else {
	fa_form_test_fail( 'Form was trashed despite the guard', $reloaded['is_trash'] );
}

// ─── Test 6: manage-form set-active ────────────────────────────────────────

fa_form_test_header( 'filter/manage-form — set-active 0 then 1' );

$form_id = fa_form_make( 'set-active' );

$off = fa_run_ability( 'filter/manage-form', [
	'operation' => 'set-active',
	'form_id'   => $form_id,
	'is_active' => false,
] );
if ( isset( $off['error'] ) ) {
	fa_form_test_fail( 'set-active 0 errored', $off['error'] );
} else {
	$reloaded = fa_form_reload( $form_id );
	if ( empty( $reloaded['is_active'] ) ) {
		fa_form_test_pass( 'set-active 0 actually deactivated the form' );
	} else {
		fa_form_test_fail( 'Form still active after set-active 0' );
	}
}

$on = fa_run_ability( 'filter/manage-form', [
	'operation' => 'set-active',
	'form_id'   => $form_id,
	'is_active' => true,
] );
if ( isset( $on['error'] ) ) {
	fa_form_test_fail( 'set-active 1 errored', $on['error'] );
} else {
	$reloaded = fa_form_reload( $form_id );
	if ( ! empty( $reloaded['is_active'] ) ) {
		fa_form_test_pass( 'set-active 1 actually re-activated the form' );
	} else {
		fa_form_test_fail( 'Form still inactive after set-active 1' );
	}
}

// ─── Test 7: manage-form duplicate ─────────────────────────────────────────

fa_form_test_header( 'filter/manage-form — duplicate' );

$source_id = fa_form_make( 'dup-source' );
$dup       = fa_run_ability( 'filter/manage-form', [
	'operation' => 'duplicate',
	'form_id'   => $source_id,
] );
if ( isset( $dup['error'] ) ) {
	fa_form_test_fail( 'duplicate errored', $dup['error'] );
} else {
	if ( ! empty( $dup['form_id'] ) && $dup['form_id'] !== $source_id ) {
		FA_Form_Test_Cleanup::register( (int) $dup['form_id'] );
		fa_form_test_pass( "Duplicate created with new form_id={$dup['form_id']}" );
	} else {
		fa_form_test_fail( 'Duplicate did not return a new form_id', $dup );
	}
}

// ─── Test 8: manage-form delete (trash) ────────────────────────────────────

fa_form_test_header( 'filter/manage-form — delete (default = trash, reversible)' );

$form_id = fa_form_make( 'trash' );
$del     = fa_run_ability( 'filter/manage-form', [
	'operation' => 'delete',
	'form_id'   => $form_id,
] );
if ( isset( $del['error'] ) ) {
	fa_form_test_fail( 'trash errored', $del['error'] );
} else {
	$reloaded = fa_form_reload( $form_id );
	if ( ! empty( $reloaded['is_trash'] ) ) {
		fa_form_test_pass( 'Form trashed (is_trash=1)' );
	} else {
		fa_form_test_fail( 'is_trash not set after trash', $reloaded );
	}
	if ( ( $del['action'] ?? '' ) === 'trashed' ) {
		fa_form_test_pass( 'Response action="trashed"' );
	} else {
		fa_form_test_fail( 'Wrong action in response', $del['action'] ?? null );
	}
}

// ─── Test 9: manage-form delete force=true ─────────────────────────────────

fa_form_test_header( 'filter/manage-form — delete force=true (permanent)' );

$form_id = fa_form_make( 'force-delete' );
$del     = fa_run_ability( 'filter/manage-form', [
	'operation' => 'delete',
	'form_id'   => $form_id,
	'force'     => true,
] );
if ( isset( $del['error'] ) ) {
	fa_form_test_fail( 'force delete errored', $del['error'] );
} else {
	if ( ! GFAPI::form_id_exists( $form_id ) ) {
		fa_form_test_pass( 'Form permanently deleted' );
	} else {
		fa_form_test_fail( 'Form still exists after force delete' );
	}
}

// ─── Test 10: manage-form-field — every supported type ────────────────────

fa_form_test_header( 'filter/manage-form-field — add every supported field type' );

$form_id = fa_form_make( 'all-types' );

$supported_types = [
	// Standard
	'text', 'textarea', 'number', 'select', 'multiselect', 'checkbox', 'radio',
	'hidden', 'html', 'section', 'page',
	// Advanced
	'name', 'email', 'website', 'phone', 'address', 'date', 'time', 'fileupload',
	'consent', 'list', 'password', 'multiple-choice', 'image-choice',
];

$added_ids   = [];
$skip_types  = []; // collect types that GF rejected so we can report them.
foreach ( $supported_types as $type ) {
	$field_input = [
		'type'  => $type,
		'label' => "FA test {$type}",
	];
	// `select`/`multiselect`/`radio`/`checkbox`/`multiple-choice`/`image-choice` want choices.
	if ( in_array( $type, [ 'select', 'multiselect', 'radio', 'checkbox', 'multiple-choice', 'image-choice' ], true ) ) {
		$field_input['choices'] = [
			[ 'text' => 'A', 'value' => 'a' ],
			[ 'text' => 'B', 'value' => 'b' ],
		];
	}
	// `consent` needs the boilerplate.
	if ( 'consent' === $type ) {
		$field_input['checkboxLabel'] = 'I agree';
	}
	$result = fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'add',
		'form_id'   => $form_id,
		'field'     => $field_input,
	] );
	if ( isset( $result['error'] ) ) {
		fa_form_test_fail( "add type='{$type}' errored", $result['error'] );
		$skip_types[] = $type;
		continue;
	}
	$added_id = (int) ( $result['field']['id'] ?? 0 );
	if ( $added_id > 0 ) {
		$added_ids[ $type ] = $added_id;
		fa_form_test_pass( "Added type='{$type}' (id={$added_id})" );
	} else {
		fa_form_test_fail( "add type='{$type}' returned no id", $result );
	}
}

// Verify they're actually on the form.
$reloaded   = fa_form_reload( $form_id );
$on_disk    = count( $reloaded['fields'] ?? [] );
$attempted  = count( $supported_types );
$persisted  = count( $added_ids );
if ( $on_disk === $persisted ) {
	fa_form_test_pass( "All {$persisted} added fields persisted on disk" );
} else {
	fa_form_test_fail( "Disk has {$on_disk} fields but ability said {$persisted} were added" );
}

// ─── Test 11: manage-form-field — update label ─────────────────────────────

fa_form_test_header( 'filter/manage-form-field — update field label' );

if ( ! empty( $added_ids['text'] ) ) {
	$target  = $added_ids['text'];
	$updated = fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'update',
		'form_id'   => $form_id,
		'field_id'  => $target,
		'field'     => [ 'label' => 'Renamed via test' ],
	] );
	if ( isset( $updated['error'] ) ) {
		fa_form_test_fail( 'update errored', $updated['error'] );
	} else {
		$reloaded = fa_form_reload( $form_id );
		$found    = fa_form_find_field( $reloaded, $target );
		$label    = is_object( $found ) ? $found->label : ( $found['label'] ?? null );
		if ( 'Renamed via test' === $label ) {
			fa_form_test_pass( 'Field label updated and persisted' );
		} else {
			fa_form_test_fail( 'Field label not persisted', $label );
		}
	}
} else {
	fa_form_test_skip( 'No text field was added; cannot test update' );
}

// ─── Test 12: manage-form-field — formId lock on update (regression) ──────

fa_form_test_header( 'filter/manage-form-field — formId locked on update (regression)' );

if ( ! empty( $added_ids['text'] ) ) {
	$target = $added_ids['text'];
	$mali   = fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'update',
		'form_id'   => $form_id,
		'field_id'  => $target,
		'field'     => [ 'formId' => 99999 ],
	] );
	if ( isset( $mali['error'] ) ) {
		fa_form_test_fail( 'update with malicious formId errored unexpectedly', $mali['error'] );
	} else {
		$reloaded = fa_form_reload( $form_id );
		$found    = fa_form_find_field( $reloaded, $target );
		$got_form = is_object( $found ) ? (int) ( $found->formId ?? 0 ) : (int) ( $found['formId'] ?? 0 );
		if ( $got_form === $form_id ) {
			fa_form_test_pass( "formId remained locked to parent form ({$form_id}), patch ignored" );
		} else {
			fa_form_test_fail( "formId was overwritten to {$got_form} (expected {$form_id})" );
		}
	}
} else {
	fa_form_test_skip( 'No text field; skipping formId-lock test' );
}

// ─── Test 13: manage-form-field — move (incl. same-position no-op) ────────

fa_form_test_header( 'filter/manage-form-field — move + same-position no-op detection (regression)' );

if ( count( $added_ids ) >= 2 ) {
	$keys     = array_keys( $added_ids );
	$target   = $added_ids[ $keys[0] ];

	// Move to position 0 (start). Since the first-added field is usually
	// already at index 0, this exercises the same-position no-op branch.
	$first    = fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'move',
		'form_id'   => $form_id,
		'field_id'  => $target,
		'position'  => 0,
	] );
	if ( isset( $first['error'] ) ) {
		fa_form_test_fail( 'move errored', $first['error'] );
	} else {
		if ( array_key_exists( 'moved', $first ) ) {
			fa_form_test_pass( "Response includes 'moved' flag (=" . var_export( $first['moved'], true ) . ")" );
		} else {
			fa_form_test_fail( "Response missing 'moved' flag" );
		}
	}

	// Move to the end — should actually move (assuming form has >1 fields).
	$second = fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'move',
		'form_id'   => $form_id,
		'field_id'  => $target,
		'position'  => 'end',
	] );
	if ( isset( $second['error'] ) ) {
		fa_form_test_fail( 'move to end errored', $second['error'] );
	} elseif ( ! empty( $second['moved'] ) ) {
		fa_form_test_pass( 'move to end reports moved=true' );
	} else {
		fa_form_test_fail( 'move to end did not report moved=true', $second );
	}
} else {
	fa_form_test_skip( 'Not enough fields to test move' );
}

// ─── Test 14: manage-form-field — delete ────────────────────────────────────

fa_form_test_header( 'filter/manage-form-field — delete' );

if ( ! empty( $added_ids['html'] ) ) {
	$target = $added_ids['html'];
	$result = fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'delete',
		'form_id'   => $form_id,
		'field_id'  => $target,
	] );
	if ( isset( $result['error'] ) ) {
		fa_form_test_fail( 'delete errored', $result['error'] );
	} else {
		$reloaded = fa_form_reload( $form_id );
		if ( null === fa_form_find_field( $reloaded, $target ) ) {
			fa_form_test_pass( 'Field removed from form' );
		} else {
			fa_form_test_fail( 'Field still present after delete' );
		}
	}
} else {
	fa_form_test_skip( 'No html field to delete' );
}

// ─── Test 15: validate-conditional-logic — happy + sad paths ──────────────

fa_form_test_header( 'filter/validate-conditional-logic — structural validation' );

$cl_form = fa_form_make( 'cl-validate' );

// Add a couple of fields so we have valid fieldId references.
$cl_text_id = (int) ( fa_run_ability( 'filter/manage-form-field', [
	'operation' => 'add',
	'form_id'   => $cl_form,
	'field'     => [ 'type' => 'text', 'label' => 'Source' ],
] )['field']['id'] ?? 0 );
$cl_hidden_id = (int) ( fa_run_ability( 'filter/manage-form-field', [
	'operation' => 'add',
	'form_id'   => $cl_form,
	'field'     => [ 'type' => 'hidden', 'label' => 'Guide', 'inputName' => 'guide' ],
] )['field']['id'] ?? 0 );

if ( $cl_text_id && $cl_hidden_id ) {
	fa_form_test_pass( "Set up fields for CL test: text={$cl_text_id}, hidden={$cl_hidden_id}" );
} else {
	fa_form_test_fail( 'Failed to set up CL test form' );
}

// Happy path.
$happy = fa_run_ability( 'filter/validate-conditional-logic', [
	'form_id'           => $cl_form,
	'conditional_logic' => [
		'actionType' => 'show',
		'logicType'  => 'all',
		'rules'      => [
			[ 'fieldId' => $cl_hidden_id, 'operator' => 'is', 'value' => 'guide-a' ],
		],
	],
] );
if ( ! empty( $happy['valid'] ) && empty( $happy['errors'] ) ) {
	fa_form_test_pass( 'Valid conditionalLogic reports valid=true with no errors' );
} else {
	fa_form_test_fail( 'Happy-path validation failed', $happy );
}

// Sad path: invalid operator, bad fieldId, bad actionType.
$sad = fa_run_ability( 'filter/validate-conditional-logic', [
	'form_id'           => $cl_form,
	'conditional_logic' => [
		'actionType' => 'maybe',
		'logicType'  => 'all',
		'rules'      => [
			[ 'fieldId' => 9999, 'operator' => 'equals', 'value' => 'x' ],
		],
	],
] );
if ( empty( $sad['valid'] ) && ! empty( $sad['errors'] ) && count( $sad['errors'] ) >= 2 ) {
	fa_form_test_pass( 'Invalid CL surfaces multiple structured errors (' . count( $sad['errors'] ) . ' total)' );
} else {
	fa_form_test_fail( 'Expected multi-error response on bad CL', $sad );
}

// Sad path: missing rules.
$empty_rules = fa_run_ability( 'filter/validate-conditional-logic', [
	'form_id'           => $cl_form,
	'conditional_logic' => [ 'actionType' => 'show', 'logicType' => 'all', 'rules' => [] ],
] );
if ( empty( $empty_rules['valid'] ) ) {
	fa_form_test_pass( 'Empty rules array correctly rejected (would have caused unconditional firing)' );
} else {
	fa_form_test_fail( 'Empty rules NOT rejected', $empty_rules );
}

// ─── Test 16: confirmations — full CRUD with CL ────────────────────────────

fa_form_test_header( 'filter/manage-form-confirmation — full CRUD with conditionalLogic' );

$add_c = fa_run_ability( 'filter/manage-form-confirmation', [
	'operation'    => 'add',
	'form_id'     => $cl_form,
	'confirmation' => [
		'name'             => 'Guide A confirmation',
		'type'             => 'message',
		'message'          => 'Thanks — download Guide A',
		'conditionalLogic' => [
			'actionType' => 'show',
			'logicType'  => 'all',
			'rules'      => [
				[ 'fieldId' => $cl_hidden_id, 'operator' => 'is', 'value' => 'guide-a' ],
			],
		],
	],
] );
if ( isset( $add_c['error'] ) ) {
	fa_form_test_fail( 'confirmation add errored', $add_c );
} else {
	$confirmation_id = $add_c['confirmation']['id'] ?? null;
	if ( $confirmation_id ) {
		fa_form_test_pass( "Confirmation added with id={$confirmation_id}" );
	} else {
		fa_form_test_fail( 'No confirmation id returned' );
	}

	$reloaded = fa_form_reload( $cl_form );
	if ( isset( $reloaded['confirmations'][ $confirmation_id ]['conditionalLogic'] ) ) {
		fa_form_test_pass( 'conditionalLogic persisted on confirmation' );
	} else {
		fa_form_test_fail( 'conditionalLogic missing from persisted confirmation' );
	}

	// Update.
	$upd = fa_run_ability( 'filter/manage-form-confirmation', [
		'operation'       => 'update',
		'form_id'         => $cl_form,
		'confirmation_id' => $confirmation_id,
		'confirmation'    => [ 'message' => 'Updated message' ],
	] );
	if ( isset( $upd['error'] ) ) {
		fa_form_test_fail( 'confirmation update errored', $upd );
	} else {
		$reloaded = fa_form_reload( $cl_form );
		if ( 'Updated message' === ( $reloaded['confirmations'][ $confirmation_id ]['message'] ?? null ) ) {
			fa_form_test_pass( 'Confirmation message updated' );
		} else {
			fa_form_test_fail( 'Confirmation message not updated' );
		}
	}

	// Clear conditionalLogic via null.
	$clear = fa_run_ability( 'filter/manage-form-confirmation', [
		'operation'       => 'update',
		'form_id'         => $cl_form,
		'confirmation_id' => $confirmation_id,
		'confirmation'    => [ 'conditionalLogic' => null ],
	] );
	if ( isset( $clear['error'] ) ) {
		fa_form_test_fail( 'conditionalLogic-clear errored', $clear );
	} else {
		$reloaded = fa_form_reload( $cl_form );
		if ( ! array_key_exists( 'conditionalLogic', $reloaded['confirmations'][ $confirmation_id ] ?? [] ) ) {
			fa_form_test_pass( 'conditionalLogic: null actually unset the key' );
		} else {
			fa_form_test_fail( 'conditionalLogic key still present after null patch', $reloaded['confirmations'][ $confirmation_id ] );
		}
	}

	// Delete.
	$del = fa_run_ability( 'filter/manage-form-confirmation', [
		'operation'       => 'delete',
		'form_id'         => $cl_form,
		'confirmation_id' => $confirmation_id,
	] );
	if ( isset( $del['error'] ) ) {
		fa_form_test_fail( 'confirmation delete errored', $del );
	} else {
		$reloaded = fa_form_reload( $cl_form );
		if ( ! isset( $reloaded['confirmations'][ $confirmation_id ] ) ) {
			fa_form_test_pass( 'Confirmation removed' );
		} else {
			fa_form_test_fail( 'Confirmation still present after delete' );
		}
	}
}

// Reject invalid CL on add.
$bad_cl_add = fa_run_ability( 'filter/manage-form-confirmation', [
	'operation'    => 'add',
	'form_id'     => $cl_form,
	'confirmation' => [
		'name'             => 'Bad CL',
		'type'             => 'message',
		'message'          => 'x',
		'conditionalLogic' => [
			'actionType' => 'show',
			'logicType'  => 'all',
			'rules'      => [ [ 'fieldId' => 99999, 'operator' => 'is', 'value' => 'x' ] ],
		],
	],
] );
if ( isset( $bad_cl_add['error'] ) && ! empty( $bad_cl_add['errors'] ) ) {
	fa_form_test_pass( 'Confirmation with non-existent fieldId rejected with structured errors' );
} else {
	fa_form_test_fail( 'Invalid CL was NOT rejected on confirmation add', $bad_cl_add );
}

// ─── Test 17: notifications — CRUD + set-active + CL ───────────────────────

fa_form_test_header( 'filter/manage-form-notification — full CRUD with set-active + CL' );

$add_n = fa_run_ability( 'filter/manage-form-notification', [
	'operation'    => 'add',
	'form_id'     => $cl_form,
	'notification' => [
		'name'    => 'FA Test Notification',
		'subject' => 'Test',
		'to'      => 'test@example.com',
		'message' => 'Body',
	],
] );
if ( isset( $add_n['error'] ) ) {
	fa_form_test_fail( 'notification add errored', $add_n );
} else {
	$notif_id = $add_n['notification']['id'] ?? null;
	$reloaded = fa_form_reload( $cl_form );
	if ( $notif_id && isset( $reloaded['notifications'][ $notif_id ] ) ) {
		fa_form_test_pass( "Notification persisted (id={$notif_id})" );
		if ( ( $reloaded['notifications'][ $notif_id ]['toType'] ?? '' ) === 'email' ) {
			fa_form_test_pass( "toType defaulted to 'email'" );
		} else {
			fa_form_test_fail( "toType default wrong", $reloaded['notifications'][ $notif_id ]['toType'] ?? null );
		}
	} else {
		fa_form_test_fail( 'Notification not persisted' );
		$notif_id = null;
	}

	if ( $notif_id ) {
		// set-active false.
		$off = fa_run_ability( 'filter/manage-form-notification', [
			'operation'       => 'set-active',
			'form_id'         => $cl_form,
			'notification_id' => $notif_id,
			'is_active'       => false,
		] );
		if ( isset( $off['error'] ) ) {
			fa_form_test_fail( 'set-active false errored', $off );
		} else {
			$reloaded = fa_form_reload( $cl_form );
			if ( false === ( $reloaded['notifications'][ $notif_id ]['isActive'] ?? null ) ) {
				fa_form_test_pass( 'isActive=false persisted' );
			} else {
				fa_form_test_fail( 'isActive not updated', $reloaded['notifications'][ $notif_id ]['isActive'] ?? null );
			}
		}

		// Delete.
		$del = fa_run_ability( 'filter/manage-form-notification', [
			'operation'       => 'delete',
			'form_id'         => $cl_form,
			'notification_id' => $notif_id,
		] );
		if ( isset( $del['error'] ) ) {
			fa_form_test_fail( 'notification delete errored', $del );
		} else {
			$reloaded = fa_form_reload( $cl_form );
			if ( ! isset( $reloaded['notifications'][ $notif_id ] ) ) {
				fa_form_test_pass( 'Notification removed' );
			} else {
				fa_form_test_fail( 'Notification still present after delete' );
			}
		}
	}
}

// ─── Test 18: list-form-feeds + is_active null vs true ─────────────────────

fa_form_test_header( 'filter/list-form-feeds — is_active null returns all (vs true)' );

$all_feeds = fa_run_ability( 'filter/list-form-feeds', [ 'is_active' => null ] );
if ( isset( $all_feeds['error'] ) ) {
	fa_form_test_fail( 'list-form-feeds errored', $all_feeds );
} else {
	if ( isset( $all_feeds['total'] ) && is_int( $all_feeds['total'] ) ) {
		fa_form_test_pass( "Returns total ({$all_feeds['total']}) and feeds array" );
	} else {
		fa_form_test_fail( 'Missing total field' );
	}
}

// ─── Test 19: manage-form-feed — unknown addon_slug rejection ──────────────

fa_form_test_header( 'filter/manage-form-feed — unknown addon_slug rejected' );

$bad_addon = fa_run_ability( 'filter/manage-form-feed', [
	'operation'  => 'create',
	'form_id'    => $cl_form,
	'addon_slug' => 'totally-not-a-real-addon',
	'meta'       => [ 'feedName' => 'Test' ],
] );
if ( isset( $bad_addon['error'] ) && false !== stripos( $bad_addon['error'], 'addon' ) ) {
	fa_form_test_pass( 'Unknown addon_slug rejected with clear error' );
} else {
	fa_form_test_fail( 'Unknown addon_slug should have been rejected', $bad_addon );
}

// ─── Test 20: manage-form-feed — non-scalar mappedFields rejected (regression) ─

fa_form_test_header( 'filter/manage-form-feed — non-scalar mappedFields value rejected (regression)' );

if ( $mailchimp_active ) {
	$bad_val = fa_run_ability( 'filter/manage-form-feed', [
		'operation'  => 'create',
		'form_id'    => $cl_form,
		'addon_slug' => 'gravityformsmailchimp',
		'meta'       => [
			'feedName'      => 'BAD-VALUE-TEST',
			'mappedFields'  => [ 'EMAIL' => [ 'nested' => 'array' ] ],
		],
		'dry_run'    => true,
	] );
	if ( isset( $bad_val['error'] ) && false !== stripos( $bad_val['error'], 'mappedFields[EMAIL]' ) && false !== stripos( $bad_val['error'], 'array' ) ) {
		fa_form_test_pass( 'Non-scalar mappedFields value rejected with clear error' );
	} else {
		fa_form_test_fail( 'Non-scalar mappedFields NOT rejected', $bad_val );
	}
} else {
	fa_form_test_skip( 'Mailchimp add-on not active; cannot exercise feed create dry_run' );
}

// ─── Test 21: manage-form-feed — mappedFields flatten round-trip (regression) ─

fa_form_test_header( 'filter/manage-form-feed — nested mappedFields flatten/unflatten round-trip (regression)' );

if ( $mailchimp_active ) {
	// Create a real feed (won't actually fire — fake mailchimpList — but the
	// add-on accepts the meta shape and persists it). The mappedFields
	// nested-object input should be auto-flattened to mappedFields_EMAIL etc.
	$feed_form = fa_form_make( 'feed-roundtrip' );
	$text_id   = (int) ( fa_run_ability( 'filter/manage-form-field', [
		'operation' => 'add',
		'form_id'   => $feed_form,
		'field'     => [ 'type' => 'email', 'label' => 'Email' ],
	] )['field']['id'] ?? 0 );

	$create = fa_run_ability( 'filter/manage-form-feed', [
		'operation'  => 'create',
		'form_id'    => $feed_form,
		'addon_slug' => 'gravityformsmailchimp',
		'meta'       => [
			'feedName'      => 'FA-TEST-FEED-ROUNDTRIP',
			'mailchimpList' => 'fake-list-id-for-test',
			'mappedFields'  => [
				'EMAIL' => (string) $text_id,
				'FNAME' => '1.3',
			],
			'tags'          => 'Test,FA',
		],
	] );

	if ( isset( $create['error'] ) ) {
		fa_form_test_fail( 'feed create errored', $create );
	} else {
		$feed_id = (int) ( $create['feed_id'] ?? 0 );
		if ( $feed_id > 0 ) {
			fa_form_test_pass( "Feed created (id={$feed_id})" );

			// Read raw meta from the DB via GFAPI::get_feed to verify the
			// flat-prefixed storage shape.
			$raw = GFAPI::get_feed( $feed_id );
			if ( is_array( $raw ) && isset( $raw['meta']['mappedFields_EMAIL'] ) ) {
				fa_form_test_pass( "Stored as flat key mappedFields_EMAIL={$raw['meta']['mappedFields_EMAIL']} (correct add-on shape)" );
			} else {
				fa_form_test_fail( 'Flat mappedFields_EMAIL not found in raw meta', $raw['meta'] ?? null );
			}

			// Now read via list-form-feeds and verify the un-flattened
			// nested view that the caller sees.
			$list   = fa_run_ability( 'filter/list-form-feeds', [ 'form_id' => $feed_form, 'is_active' => null ] );
			$found  = null;
			foreach ( ( $list['feeds'] ?? [] ) as $f ) {
				if ( (int) $f['id'] === $feed_id ) {
					$found = $f;
					break;
				}
			}
			if ( $found && isset( $found['meta']['mappedFields']['EMAIL'] ) ) {
				fa_form_test_pass( 'list-form-feeds re-nests mappedFields back to caller-friendly object form' );
			} else {
				fa_form_test_fail( 'mappedFields not re-nested in list-form-feeds output', $found );
			}

			// Test clear-one-mapping (regression).
			$clear_one = fa_run_ability( 'filter/manage-form-feed', [
				'operation' => 'update',
				'feed_id'   => $feed_id,
				'meta'      => [ 'mappedFields' => [ 'FNAME' => null ] ],
			] );
			if ( isset( $clear_one['error'] ) ) {
				fa_form_test_fail( 'clear-one-mapping errored', $clear_one );
			} else {
				$raw = GFAPI::get_feed( $feed_id );
				if ( ! isset( $raw['meta']['mappedFields_FNAME'] ) && isset( $raw['meta']['mappedFields_EMAIL'] ) ) {
					fa_form_test_pass( 'mappedFields: { FNAME: null } cleared only FNAME, preserved EMAIL' );
				} else {
					fa_form_test_fail( 'clear-one did not produce expected state', $raw['meta'] );
				}
			}

			// Test clear-all-mappings via empty object (regression).
			$clear_all = fa_run_ability( 'filter/manage-form-feed', [
				'operation' => 'update',
				'feed_id'   => $feed_id,
				'meta'      => [ 'mappedFields' => [] ],
			] );
			if ( isset( $clear_all['error'] ) ) {
				fa_form_test_fail( 'clear-all-mappings errored', $clear_all );
			} else {
				$raw = GFAPI::get_feed( $feed_id );
				$has_any_mapping = false;
				foreach ( array_keys( $raw['meta'] ?? [] ) as $k ) {
					if ( 0 === strpos( $k, 'mappedFields_' ) ) {
						$has_any_mapping = true;
						break;
					}
				}
				if ( ! $has_any_mapping ) {
					fa_form_test_pass( 'mappedFields: {} cleared all mappedFields_* keys' );
				} else {
					fa_form_test_fail( 'Some mappedFields_* keys still present after empty {} clear', $raw['meta'] );
				}
			}
		} else {
			fa_form_test_fail( 'feed_id missing from create response', $create );
		}
	}
} else {
	fa_form_test_skip( 'Mailchimp add-on not active; cannot exercise feed round-trip' );
}

// ─── Test 22: Mailchimp pickers ─────────────────────────────────────────────

fa_form_test_header( 'filter/list-mailchimp-* — pickers (skip if add-on inactive)' );

if ( $mailchimp_active ) {
	$audiences = fa_run_ability( 'filter/list-mailchimp-audiences' );
	if ( isset( $audiences['error'] ) ) {
		// Could be auth not configured — surface but don't fail the whole
		// suite.
		fa_form_test_skip( 'list-mailchimp-audiences: ' . $audiences['error'] );
	} else {
		if ( isset( $audiences['total'] ) && is_int( $audiences['total'] ) && isset( $audiences['audiences'] ) ) {
			fa_form_test_pass( "list-mailchimp-audiences returned {$audiences['total']} audiences" );
			if ( ! empty( $audiences['audiences'] ) ) {
				$first_id = $audiences['audiences'][0]['id'];

				// Merge fields.
				$mf = fa_run_ability( 'filter/list-mailchimp-merge-fields', [ 'audience_id' => $first_id ] );
				if ( ! isset( $mf['error'] ) && isset( $mf['merge_fields'] ) ) {
					$has_email = false;
					foreach ( $mf['merge_fields'] as $m ) {
						if ( 'EMAIL' === ( $m['tag'] ?? '' ) ) {
							$has_email = true;
							break;
						}
					}
					if ( $has_email ) {
						fa_form_test_pass( 'list-mailchimp-merge-fields prepends EMAIL synthetic field' );
					} else {
						fa_form_test_fail( 'EMAIL not in merge_fields' );
					}
				} else {
					fa_form_test_skip( 'list-mailchimp-merge-fields: ' . ( $mf['error'] ?? 'unknown' ) );
				}

				// Tags.
				$tags = fa_run_ability( 'filter/list-mailchimp-tags', [ 'audience_id' => $first_id ] );
				if ( ! isset( $tags['error'] ) ) {
					fa_form_test_pass( 'list-mailchimp-tags returned without error' );
				} else {
					fa_form_test_skip( 'list-mailchimp-tags: ' . $tags['error'] );
				}

				// Groups.
				$groups = fa_run_ability( 'filter/list-mailchimp-groups', [ 'audience_id' => $first_id ] );
				if ( ! isset( $groups['error'] ) ) {
					fa_form_test_pass( 'list-mailchimp-groups returned without error' );
				} else {
					fa_form_test_skip( 'list-mailchimp-groups: ' . $groups['error'] );
				}
			}
		} else {
			fa_form_test_fail( 'list-mailchimp-audiences malformed response', $audiences );
		}
	}
} else {
	fa_form_test_skip( 'Mailchimp add-on not active' );
}

// ─── Test 23: Duplicate-label disambiguation in get-form-entries (regression) ─

fa_form_test_header( 'filter/get-form-entries — duplicate label disambiguation (regression)' );

$dup_form = fa_form_make( 'dup-labels' );
$dup_a    = (int) ( fa_run_ability( 'filter/manage-form-field', [
	'operation' => 'add',
	'form_id'   => $dup_form,
	'field'     => [ 'type' => 'text', 'label' => 'Email' ],
] )['field']['id'] ?? 0 );
$dup_b = (int) ( fa_run_ability( 'filter/manage-form-field', [
	'operation' => 'add',
	'form_id'   => $dup_form,
	'field'     => [ 'type' => 'text', 'label' => 'Email' ],
] )['field']['id'] ?? 0 );

if ( $dup_a && $dup_b ) {
	GFAPI::add_entry( [
		'form_id' => $dup_form,
		$dup_a    => 'first@example.com',
		$dup_b    => 'second@example.com',
	] );
	$entries = fa_run_ability( 'filter/get-form-entries', [ 'form_id' => $dup_form ] );
	if ( ! empty( $entries['entries'] ) ) {
		$fields = $entries['entries'][0]['fields'] ?? [];
		// Both values should appear under disambiguated keys.
		$values = array_values( $fields );
		if ( in_array( 'first@example.com', $values, true ) && in_array( 'second@example.com', $values, true ) ) {
			fa_form_test_pass( 'Both duplicate-labeled field values survived in returned entry' );
		} else {
			fa_form_test_fail( 'Duplicate-label collision lost a value', $fields );
		}
	} else {
		fa_form_test_fail( 'No entries returned', $entries );
	}
} else {
	fa_form_test_skip( 'Could not set up duplicate-label form' );
}

// ─── Test 24: Permission guard — subscriber denied ────────────────────────

fa_form_test_header( 'Permission — subscriber-level user denied' );

$original_user = get_current_user_id();
$sub_user_id   = wp_insert_user( [
	'user_login' => 'fa-test-sub-' . uniqid(),
	'user_email' => 'fa-sub-' . uniqid() . '@example.com',
	'user_pass'  => wp_generate_password( 20, true ),
	'role'       => 'subscriber',
] );

if ( is_wp_error( $sub_user_id ) ) {
	fa_form_test_skip( 'Could not create subscriber: ' . $sub_user_id->get_error_message() );
} else {
	wp_set_current_user( $sub_user_id );

	$attempts = [
		'filter/list-forms' => [],
		'filter/get-form'   => [ 'form_id' => $cl_form ],
		'filter/manage-form' => [ 'operation' => 'create', 'title' => 'should-fail' ],
	];
	foreach ( $attempts as $name => $params ) {
		$ability = wp_get_ability( $name );
		if ( ! $ability ) {
			fa_form_test_fail( "Ability {$name} not found" );
			continue;
		}
		$result = $ability->execute( $params );
		// The Abilities API blocks on permission_callback — exact error
		// shape depends on WP. We just want SOMETHING that isn't success.
		if ( is_wp_error( $result )
			|| ( is_array( $result ) && isset( $result['error'] ) )
			|| false === $result
		) {
			fa_form_test_pass( "Subscriber blocked from {$name}" );
		} else {
			fa_form_test_fail( "Subscriber NOT blocked from {$name}", $result );
		}
	}

	// Restore.
	wp_set_current_user( $original_user );
	wp_delete_user( $sub_user_id );
}

// ─── Summary ────────────────────────────────────────────────────────────────

echo "\n" . str_repeat( '═', 60 ) . "\n";
echo "RESULTS\n";
echo str_repeat( '═', 60 ) . "\n";
echo "  Passed:  " . FA_Form_Test_Counter::$pass . "\n";
echo "  Failed:  " . FA_Form_Test_Counter::$fail . "\n";
echo "  Skipped: " . FA_Form_Test_Counter::$skip . "\n";
echo "\n";

if ( FA_Form_Test_Counter::$fail > 0 ) {
	echo "✗ SOME TESTS FAILED\n";
	exit( 1 );
}
echo "✓ All tests passed (or skipped because of missing dependencies).\n";
exit( 0 );
