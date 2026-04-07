<?php
/**
 * Redirection Abilities Test Runner
 *
 * Drop-in test script that exercises all Redirection module abilities.
 * Access via: https://filter-website.local/wp-content/plugins/filter-abilities/tests/test-redirection-abilities.php
 *
 * Requires: logged-in admin session (manage_options capability).
 *
 * @package Filter_Abilities
 */

// Bootstrap WordPress if not already loaded (supports both direct access and WP-CLI eval-file).
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

// Use a class to avoid scope issues with WP-CLI eval-file.
class FA_Test_Counter {
	public static int $pass = 0;
	public static int $fail = 0;
	public static int $skip = 0;
}

function test_header( string $name ): void {
	echo "\n" . str_repeat( '─', 60 ) . "\n";
	echo "TEST: {$name}\n";
	echo str_repeat( '─', 60 ) . "\n";
}

function test_pass( string $msg ): void {
	FA_Test_Counter::$pass++;
	echo "  ✓ PASS: {$msg}\n";
}

function test_fail( string $msg, $detail = null ): void {
	FA_Test_Counter::$fail++;
	echo "  ✗ FAIL: {$msg}\n";
	if ( $detail ) {
		echo "    Detail: " . print_r( $detail, true ) . "\n";
	}
}

function test_skip( string $msg ): void {
	FA_Test_Counter::$skip++;
	echo "  ⊘ SKIP: {$msg}\n";
}

/**
 * Execute an ability by name and return the result.
 */
function run_ability( string $name, array $params = [] ): array {
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

// ─── Pre-flight checks ─────────────────────────────────────────────────────

echo "Filter Abilities — Redirection Module Tests\n";
echo "=============================================\n";
echo "Date: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
echo "Site: " . home_url() . "\n";
echo "WP Version: " . get_bloginfo( 'version' ) . "\n";

// Check Redirection plugin is active.
if ( ! defined( 'REDIRECTION_VERSION' ) ) {
	echo "\n⊘ Redirection plugin is NOT active. Cannot run tests.\n";
	exit( 1 );
}
echo "Redirection Version: " . REDIRECTION_VERSION . "\n";

// Check Filter Abilities is loaded.
if ( ! defined( 'FILTER_ABILITIES_VERSION' ) ) {
	echo "\n⊘ Filter Abilities plugin is NOT active. Cannot run tests.\n";
	exit( 1 );
}
echo "Filter Abilities Version: " . FILTER_ABILITIES_VERSION . "\n";

// Check abilities API.
if ( ! function_exists( 'wp_get_ability' ) ) {
	echo "\n⊘ Abilities API not available (WP 6.9+ required).\n";
	exit( 1 );
}

// Verify the redirection abilities are registered.
$test_ability = wp_get_ability( 'filter/list-redirects' );
if ( ! $test_ability ) {
	echo "\n⊘ filter/list-redirects ability not registered. Module may not have loaded.\n";
	exit( 1 );
}
echo "Redirection module: LOADED\n";

// ─── Test 1: list-redirect-groups ───────────────────────────────────────────

test_header( 'filter/list-redirect-groups' );

$result = run_ability( 'filter/list-redirect-groups' );

if ( isset( $result['error'] ) ) {
	test_fail( 'Returned error', $result['error'] );
} else {
	if ( isset( $result['total'] ) && is_int( $result['total'] ) ) {
		test_pass( "Returned total: {$result['total']}" );
	} else {
		test_fail( 'Missing or invalid total field' );
	}

	if ( isset( $result['groups'] ) && is_array( $result['groups'] ) ) {
		test_pass( "Returned groups array with " . count( $result['groups'] ) . " group(s)" );
		if ( ! empty( $result['groups'] ) ) {
			$g = $result['groups'][0];
			$required_keys = [ 'id', 'name', 'status', 'redirect_count' ];
			$missing = array_diff( $required_keys, array_keys( $g ) );
			if ( empty( $missing ) ) {
				test_pass( "Group has required fields: " . implode( ', ', $required_keys ) );
			} else {
				test_fail( 'Group missing fields: ' . implode( ', ', $missing ) );
			}
		}
	} else {
		test_fail( 'Missing or invalid groups array' );
	}
}

// ─── Test 2: list-redirects ─────────────────────────────────────────────────

test_header( 'filter/list-redirects' );

$result = run_ability( 'filter/list-redirects', [ 'per_page' => 5 ] );

if ( isset( $result['error'] ) ) {
	test_fail( 'Returned error', $result['error'] );
} else {
	if ( isset( $result['total'] ) && is_int( $result['total'] ) ) {
		test_pass( "Returned total: {$result['total']}" );
	} else {
		test_fail( 'Missing or invalid total field' );
	}

	if ( isset( $result['page'] ) && 1 === $result['page'] ) {
		test_pass( 'Page is 1' );
	} else {
		test_fail( 'Missing or incorrect page field' );
	}

	if ( isset( $result['redirects'] ) && is_array( $result['redirects'] ) ) {
		$count = count( $result['redirects'] );
		test_pass( "Returned redirects array with {$count} item(s)" );
		if ( $count <= 5 ) {
			test_pass( 'Respects per_page=5 limit' );
		} else {
			test_fail( "Returned {$count} items, expected max 5" );
		}
		if ( ! empty( $result['redirects'] ) ) {
			$r = $result['redirects'][0];
			$required_keys = [ 'id', 'url', 'action_type', 'action_code', 'status', 'group_name' ];
			$missing = array_diff( $required_keys, array_keys( $r ) );
			if ( empty( $missing ) ) {
				test_pass( "Redirect has required fields" );
				echo "    Sample: [{$r['id']}] {$r['url']} → {$r['action_data']} ({$r['action_code']})\n";
			} else {
				test_fail( 'Redirect missing fields: ' . implode( ', ', $missing ) );
			}
		}
	} else {
		test_fail( 'Missing or invalid redirects array' );
	}
}

// Test filtering by status.
$result_enabled = run_ability( 'filter/list-redirects', [ 'status' => 'enabled', 'per_page' => 1 ] );
if ( ! isset( $result_enabled['error'] ) ) {
	test_pass( "Status filter 'enabled' works (total: {$result_enabled['total']})" );
} else {
	test_fail( 'Status filter failed', $result_enabled['error'] );
}

// ─── Test 3: redirect-stats ─────────────────────────────────────────────────

test_header( 'filter/redirect-stats' );

$result = run_ability( 'filter/redirect-stats', [ 'period_days' => 30 ] );

if ( isset( $result['error'] ) ) {
	test_fail( 'Returned error', $result['error'] );
} else {
	$expected_keys = [
		'total_redirects', 'enabled_redirects', 'disabled_redirects',
		'total_groups', 'total_404s_in_period', 'unique_404_urls_in_period',
		'top_404_urls', 'total_hits_in_period', 'most_used_redirects',
	];
	$missing = array_diff( $expected_keys, array_keys( $result ) );
	if ( empty( $missing ) ) {
		test_pass( 'All expected stat fields present' );
	} else {
		test_fail( 'Missing stat fields: ' . implode( ', ', $missing ) );
	}

	echo "    Stats summary:\n";
	echo "      Total redirects: {$result['total_redirects']} (enabled: {$result['enabled_redirects']}, disabled: {$result['disabled_redirects']})\n";
	echo "      Groups: {$result['total_groups']}\n";
	echo "      404s in period: {$result['total_404s_in_period']} (unique URLs: {$result['unique_404_urls_in_period']})\n";
	echo "      Redirect hits in period: {$result['total_hits_in_period']}\n";

	if ( is_array( $result['top_404_urls'] ) ) {
		test_pass( 'top_404_urls is an array (' . count( $result['top_404_urls'] ) . ' entries)' );
	} else {
		test_fail( 'top_404_urls is not an array' );
	}

	if ( is_array( $result['most_used_redirects'] ) ) {
		test_pass( 'most_used_redirects is an array (' . count( $result['most_used_redirects'] ) . ' entries)' );
	} else {
		test_fail( 'most_used_redirects is not an array' );
	}
}

// ─── Test 4: list-404-errors ────────────────────────────────────────────────

test_header( 'filter/list-404-errors' );

// Normal mode.
$result = run_ability( 'filter/list-404-errors', [ 'per_page' => 5 ] );

if ( isset( $result['error'] ) ) {
	test_fail( 'Returned error', $result['error'] );
} else {
	test_pass( "Normal mode: total={$result['total']}, returned " . count( $result['errors'] ) . " entries" );
	if ( ! empty( $result['errors'] ) ) {
		$e = $result['errors'][0];
		if ( isset( $e['id'], $e['url'], $e['created'] ) ) {
			test_pass( "Entry has expected fields (id, url, created)" );
			echo "    Sample: [{$e['id']}] {$e['url']} at {$e['created']}\n";
		} else {
			test_fail( 'Entry missing expected fields' );
		}
	}
}

// Grouped mode.
$result_grouped = run_ability( 'filter/list-404-errors', [ 'group_by_url' => true, 'per_page' => 5 ] );

if ( isset( $result_grouped['error'] ) ) {
	test_fail( 'Grouped mode returned error', $result_grouped['error'] );
} else {
	test_pass( "Grouped mode: total={$result_grouped['total']}, returned " . count( $result_grouped['errors'] ) . " entries" );
	if ( ! empty( $result_grouped['errors'] ) ) {
		$e = $result_grouped['errors'][0];
		if ( isset( $e['url'], $e['hit_count'], $e['first_seen'], $e['last_seen'] ) ) {
			test_pass( "Grouped entry has expected fields" );
			echo "    Top 404: {$e['url']} ({$e['hit_count']} hits, last: {$e['last_seen']})\n";
		} else {
			test_fail( 'Grouped entry missing expected fields' );
		}
	}
}

// Date filter validation.
$result_bad_date = run_ability( 'filter/list-404-errors', [ 'start_date' => 'not-a-date' ] );
if ( isset( $result_bad_date['error'] ) && strpos( $result_bad_date['error'], 'Invalid' ) !== false ) {
	test_pass( 'Invalid date correctly rejected' );
} else {
	test_fail( 'Invalid date was not rejected' );
}

// ─── Test 5: get-redirect-logs ──────────────────────────────────────────────

test_header( 'filter/get-redirect-logs' );

$result = run_ability( 'filter/get-redirect-logs', [ 'per_page' => 5 ] );

if ( isset( $result['error'] ) ) {
	test_fail( 'Returned error', $result['error'] );
} else {
	test_pass( "Returned total={$result['total']}, page={$result['page']}, entries=" . count( $result['logs'] ) );
	if ( ! empty( $result['logs'] ) ) {
		$l = $result['logs'][0];
		if ( isset( $l['id'], $l['url'], $l['sent_to'], $l['http_code'] ) ) {
			test_pass( "Log entry has expected fields" );
			echo "    Sample: {$l['url']} → {$l['sent_to']} ({$l['http_code']})\n";
		} else {
			test_fail( 'Log entry missing fields' );
		}
	} else {
		test_skip( 'No redirect log entries to validate structure' );
	}
}

// ─── Test 6: check-redirect ────────────────────────────────────────────────

test_header( 'filter/check-redirect' );

// Check a URL that is unlikely to have a redirect.
$result = run_ability( 'filter/check-redirect', [ 'url' => '/this-url-should-not-exist-' . wp_rand() ] );

if ( isset( $result['error'] ) ) {
	test_fail( 'Returned error', $result['error'] );
} else {
	if ( false === $result['has_redirect'] ) {
		test_pass( 'Correctly reports no redirect for random URL' );
	} else {
		test_fail( 'Unexpectedly found a redirect for random URL', $result['matches'] );
	}
}

// If there are existing redirects, check one of them.
$existing = run_ability( 'filter/list-redirects', [ 'per_page' => 1, 'status' => 'enabled' ] );
if ( ! empty( $existing['redirects'] ) ) {
	$test_url = $existing['redirects'][0]['url'];
	$is_regex = $existing['redirects'][0]['regex'];

	if ( ! $is_regex ) {
		$result = run_ability( 'filter/check-redirect', [ 'url' => $test_url ] );
		if ( ! empty( $result['has_redirect'] ) ) {
			test_pass( "Found redirect for existing URL: {$test_url}" );
		} else {
			test_fail( "Did not find redirect for existing URL: {$test_url}" );
		}
	} else {
		test_skip( 'First redirect is regex, skipping exact-match check' );
	}
} else {
	test_skip( 'No enabled redirects to test check-redirect against' );
}

// Empty URL validation.
$result_empty = run_ability( 'filter/check-redirect', [ 'url' => '' ] );
if ( isset( $result_empty['error'] ) ) {
	test_pass( 'Empty URL correctly rejected' );
} else {
	test_fail( 'Empty URL was not rejected' );
}

// ─── Test 7: manage-redirect (CRUD) ────────────────────────────────────────

test_header( 'filter/manage-redirect (create → update → delete)' );

$test_source = '/filter-abilities-test-' . wp_rand();
$test_target = home_url( '/test-target/' );

// Create.
$create_result = run_ability( 'filter/manage-redirect', [
	'action'      => 'create',
	'source_url'  => $test_source,
	'target_url'  => $test_target,
	'action_code' => 301,
	'title'       => 'Automated test redirect',
] );

if ( isset( $create_result['error'] ) ) {
	test_fail( 'Create failed', $create_result['error'] );
} else {
	$new_id = $create_result['redirect_id'] ?? 0;
	test_pass( "Created redirect ID {$new_id}: {$test_source} → {$test_target}" );

	// Verify it exists via check-redirect.
	$check = run_ability( 'filter/check-redirect', [ 'url' => $test_source ] );
	if ( ! empty( $check['has_redirect'] ) ) {
		test_pass( 'check-redirect confirms new redirect exists' );
	} else {
		test_fail( 'check-redirect did not find the newly created redirect' );
	}

	// Update.
	$new_target = home_url( '/updated-target/' );
	$update_result = run_ability( 'filter/manage-redirect', [
		'action'      => 'update',
		'redirect_id' => $new_id,
		'target_url'  => $new_target,
		'action_code' => 302,
		'title'       => 'Updated test redirect',
	] );

	if ( isset( $update_result['error'] ) ) {
		test_fail( 'Update failed', $update_result['error'] );
	} else {
		test_pass( "Updated redirect {$new_id}: target → {$new_target}, code → 302" );

		// Verify the update via list-redirects search.
		$search = run_ability( 'filter/list-redirects', [ 'search' => $test_source ] );
		if ( ! empty( $search['redirects'] ) ) {
			$found = $search['redirects'][0];
			if ( (int) $found['action_code'] === 302 ) {
				test_pass( 'Verified action_code updated to 302' );
			} else {
				test_fail( 'action_code not updated', $found['action_code'] );
			}
		} else {
			test_fail( 'Could not find updated redirect via search' );
		}
	}

	// Delete.
	$delete_result = run_ability( 'filter/manage-redirect', [
		'action'      => 'delete',
		'redirect_id' => $new_id,
	] );

	if ( isset( $delete_result['error'] ) ) {
		test_fail( 'Delete failed', $delete_result['error'] );
	} else {
		test_pass( "Deleted redirect {$new_id}" );

		// Verify it's gone.
		$check_after = run_ability( 'filter/check-redirect', [ 'url' => $test_source ] );
		if ( empty( $check_after['has_redirect'] ) ) {
			test_pass( 'Confirmed redirect no longer exists after deletion' );
		} else {
			test_fail( 'Redirect still found after deletion' );
		}
	}
}

// Validation: create without source_url.
$bad_create = run_ability( 'filter/manage-redirect', [ 'action' => 'create' ] );
if ( isset( $bad_create['error'] ) ) {
	test_pass( 'Create without source_url correctly rejected' );
} else {
	test_fail( 'Create without source_url was not rejected' );
}

// Validation: update without redirect_id.
$bad_update = run_ability( 'filter/manage-redirect', [ 'action' => 'update' ] );
if ( isset( $bad_update['error'] ) ) {
	test_pass( 'Update without redirect_id correctly rejected' );
} else {
	test_fail( 'Update without redirect_id was not rejected' );
}

// Validation: invalid action.
$bad_action = run_ability( 'filter/manage-redirect', [ 'action' => 'explode' ] );
if ( isset( $bad_action['error'] ) ) {
	test_pass( 'Invalid action correctly rejected' );
} else {
	test_fail( 'Invalid action was not rejected' );
}

// ─── Test 8: bulk-manage-redirects ──────────────────────────────────────────

test_header( 'filter/bulk-manage-redirects' );

// Create two test redirects for bulk operations.
$bulk_ids = [];
for ( $i = 1; $i <= 2; $i++ ) {
	$r = run_ability( 'filter/manage-redirect', [
		'action'     => 'create',
		'source_url' => '/bulk-test-' . wp_rand(),
		'target_url' => home_url( "/bulk-target-{$i}/" ),
		'title'      => "Bulk test {$i}",
	] );
	if ( ! isset( $r['error'] ) ) {
		$bulk_ids[] = $r['redirect_id'];
	}
}

if ( count( $bulk_ids ) < 2 ) {
	test_fail( 'Could not create test redirects for bulk operations' );
} else {
	test_pass( 'Created 2 test redirects: ' . implode( ', ', $bulk_ids ) );

	// Bulk disable.
	$result = run_ability( 'filter/bulk-manage-redirects', [
		'action'       => 'disable',
		'redirect_ids' => $bulk_ids,
	] );
	if ( ! isset( $result['error'] ) && $result['affected'] === 2 ) {
		test_pass( "Bulk disable: {$result['affected']} affected" );
	} else {
		test_fail( 'Bulk disable failed', $result );
	}

	// Bulk enable.
	$result = run_ability( 'filter/bulk-manage-redirects', [
		'action'       => 'enable',
		'redirect_ids' => $bulk_ids,
	] );
	if ( ! isset( $result['error'] ) && $result['affected'] === 2 ) {
		test_pass( "Bulk enable: {$result['affected']} affected" );
	} else {
		test_fail( 'Bulk enable failed', $result );
	}

	// Bulk reset (affected may be 0 if counters are already at default values).
	$result = run_ability( 'filter/bulk-manage-redirects', [
		'action'       => 'reset',
		'redirect_ids' => $bulk_ids,
	] );
	if ( ! isset( $result['error'] ) && 'reset' === $result['action'] ) {
		test_pass( "Bulk reset: {$result['affected']} affected" );
	} else {
		test_fail( 'Bulk reset failed', $result );
	}

	// Bulk delete (cleanup).
	$result = run_ability( 'filter/bulk-manage-redirects', [
		'action'       => 'delete',
		'redirect_ids' => $bulk_ids,
	] );
	if ( ! isset( $result['error'] ) && $result['affected'] === 2 ) {
		test_pass( "Bulk delete: {$result['affected']} affected (cleanup)" );
	} else {
		test_fail( 'Bulk delete failed', $result );
	}
}

// Validation: empty IDs.
$bad_bulk = run_ability( 'filter/bulk-manage-redirects', [
	'action'       => 'enable',
	'redirect_ids' => [],
] );
if ( isset( $bad_bulk['error'] ) ) {
	test_pass( 'Empty redirect_ids correctly rejected' );
} else {
	test_fail( 'Empty redirect_ids was not rejected' );
}

// ─── Summary ────────────────────────────────────────────────────────────────

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "RESULTS: " . FA_Test_Counter::$pass . " passed, " . FA_Test_Counter::$fail . " failed, " . FA_Test_Counter::$skip . " skipped\n";
echo str_repeat( '=', 60 ) . "\n";

if ( FA_Test_Counter::$fail > 0 ) {
	echo "\n⚠ Some tests failed. Review output above.\n";
} else {
	echo "\n✓ All tests passed!\n";
}
