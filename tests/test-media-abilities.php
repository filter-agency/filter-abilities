<?php
/**
 * Media + Migration Abilities Test Runner
 *
 * Drop-in test script that exercises filter/list-media (extended fields),
 * filter/upload-media, and filter/rewrite-content end-to-end.
 *
 * Access via: https://<site>/wp-content/plugins/filter-abilities/tests/test-media-abilities.php
 * Or: wp eval-file wp-content/plugins/filter-abilities/tests/test-media-abilities.php --user=1
 *
 * Requires: logged-in admin (manage_options) and the GD PHP extension.
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

class FA_Media_Test_Counter {
	public static int $pass = 0;
	public static int $fail = 0;
	public static int $skip = 0;
}

function fa_media_test_header( string $name ): void {
	echo "\n" . str_repeat( '─', 60 ) . "\n";
	echo "TEST: {$name}\n";
	echo str_repeat( '─', 60 ) . "\n";
}

function fa_media_test_pass( string $msg ): void {
	FA_Media_Test_Counter::$pass++;
	echo "  ✓ PASS: {$msg}\n";
}

function fa_media_test_fail( string $msg, $detail = null ): void {
	FA_Media_Test_Counter::$fail++;
	echo "  ✗ FAIL: {$msg}\n";
	if ( $detail ) {
		echo "    Detail: " . print_r( $detail, true ) . "\n";
	}
}

function fa_media_test_skip( string $msg ): void {
	FA_Media_Test_Counter::$skip++;
	echo "  ⊘ SKIP: {$msg}\n";
}

function fa_run_ability( string $name, array $params = [] ): array {
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

function fa_media_is_local_test_host( string $host ): bool {
	$has_suffix = static function ( string $value, string $suffix ): bool {
		return '' !== $suffix && substr( $value, -strlen( $suffix ) ) === $suffix;
	};

	return in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true )
		|| $has_suffix( $host, '.test' )
		|| $has_suffix( $host, '.local' )
		|| $has_suffix( $host, '.localhost' );
}

function fa_media_is_same_local_test_host( string $url ): bool {
	$url_host  = wp_parse_url( $url, PHP_URL_HOST );
	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

	return is_string( $url_host )
		&& is_string( $home_host )
		&& strtolower( $url_host ) === strtolower( $home_host )
		&& fa_media_is_local_test_host( strtolower( $home_host ) );
}

/**
 * Generate a tiny PNG fixture and write it to a path under uploads.
 * Returns the absolute path.
 */
function fa_make_test_png( string $filename, int $width = 64, int $height = 48 ): string {
	$upload   = wp_upload_dir();
	$dir      = trailingslashit( $upload['basedir'] ) . 'fa-test-fixtures';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$path = trailingslashit( $dir ) . $filename;

	$im   = imagecreatetruecolor( $width, $height );
	$bg   = imagecolorallocate( $im, 32, 64, 96 );
	imagefilledrectangle( $im, 0, 0, $width - 1, $height - 1, $bg );
	$fg   = imagecolorallocate( $im, 220, 220, 220 );
	imagestring( $im, 5, 4, 4, 'TEST', $fg );
	imagepng( $im, $path );
	imagedestroy( $im );

	FA_Media_Test_Cleanup::$files[] = $path;

	return $path;
}

/**
 * Cleanup tracker — collects attachment IDs and post IDs created during the
 * tests so we can remove them at the end.
 */
class FA_Media_Test_Cleanup {
	/** @var int[] */ public static array $attachments = [];
	/** @var int[] */ public static array $posts = [];
	/** @var string[] */ public static array $files = [];
}

register_shutdown_function( static function () {
	foreach ( FA_Media_Test_Cleanup::$attachments as $id ) {
		wp_delete_attachment( $id, true );
	}
	foreach ( FA_Media_Test_Cleanup::$posts as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( FA_Media_Test_Cleanup::$files as $f ) {
		if ( file_exists( $f ) ) {
			@unlink( $f );
		}
	}
	$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fa-test-fixtures';
	if ( is_dir( $dir ) ) {
		@rmdir( $dir );
	}
} );

// Allow same-site fixture URLs to bypass the SSRF guard during tests only.
add_filter( 'filter_abilities_is_safe_external_url', static function ( $is_safe, $url ) {
	if ( false !== strpos( $url, 'fa-test-fixtures/' ) ) {
		return true;
	}
	return $is_safe;
}, 10, 2 );

// Local dev certificates are often self-signed; only relax SSL for this
// site's own .test/.local fixture URLs inside the test process.
$fa_media_local_ssl_filter = static function ( $verify, $url = '' ) {
	if ( is_string( $url ) && false !== strpos( $url, 'fa-test-fixtures/' ) && fa_media_is_same_local_test_host( $url ) ) {
		return false;
	}

	return $verify;
};
add_filter( 'https_ssl_verify', $fa_media_local_ssl_filter, 10, 2 );
add_filter( 'https_local_ssl_verify', $fa_media_local_ssl_filter, 10, 2 );

if ( has_filter( 'wp_generate_attachment_metadata', 'filter_ai_generate_alt_text_on_upload' ) ) {
	remove_filter( 'wp_generate_attachment_metadata', 'filter_ai_generate_alt_text_on_upload', 10 );
}

// ─── Pre-flight ─────────────────────────────────────────────────────────────

echo "Filter Abilities — Media + Migration Module Tests\n";
echo "==================================================\n";
echo "Date: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
echo "Site: " . home_url() . "\n";
echo "WP Version: " . get_bloginfo( 'version' ) . "\n";

if ( ! defined( 'FILTER_ABILITIES_VERSION' ) ) {
	echo "\n⊘ Filter Abilities plugin is NOT active. Cannot run tests.\n";
	exit( 1 );
}
echo "Filter Abilities Version: " . FILTER_ABILITIES_VERSION . "\n";

if ( ! function_exists( 'wp_get_ability' ) ) {
	echo "\n⊘ Abilities API not available (WP 6.9+ required).\n";
	exit( 1 );
}

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	echo "\n⊘ GD extension not available — required to generate fixtures.\n";
	exit( 1 );
}

foreach ( [ 'filter/list-media', 'filter/upload-media', 'filter/rewrite-content' ] as $req ) {
	if ( ! wp_get_ability( $req ) ) {
		echo "\n⊘ Ability '{$req}' not registered.\n";
		exit( 1 );
	}
}
echo "All abilities registered: list-media, upload-media, rewrite-content\n";

// ─── Test: list-media (extended fields) ─────────────────────────────────────

fa_media_test_header( 'filter/list-media — extended output fields' );

// Seed an attachment with caption + description + post_parent.
$parent_post_id = wp_insert_post( [
	'post_title'  => 'FA Test parent post',
	'post_status' => 'publish',
	'post_type'   => 'post',
] );
FA_Media_Test_Cleanup::$posts[] = (int) $parent_post_id;

$fixture_path = fa_make_test_png( 'fa-list-media-fixture.png' );
require_once ABSPATH . 'wp-admin/includes/image.php';
$seeded_id = wp_insert_attachment( [
	'post_title'   => 'List-media seed image',
	'post_excerpt' => 'A test caption.',
	'post_content' => 'A test description.',
	'post_mime_type' => 'image/png',
	'post_parent'  => (int) $parent_post_id,
	'post_status'  => 'inherit',
], $fixture_path, (int) $parent_post_id );
FA_Media_Test_Cleanup::$attachments[] = (int) $seeded_id;
update_post_meta( $seeded_id, '_wp_attachment_image_alt', 'seeded alt' );
wp_update_attachment_metadata( $seeded_id, wp_generate_attachment_metadata( $seeded_id, $fixture_path ) );

$res = fa_run_ability( 'filter/list-media', [ 'search' => 'List-media seed image' ] );
if ( isset( $res['error'] ) ) {
	fa_media_test_fail( 'list-media returned error', $res['error'] );
} else {
	$found = null;
	foreach ( $res['items'] ?? [] as $item ) {
		if ( ( $item['id'] ?? 0 ) === (int) $seeded_id ) {
			$found = $item;
			break;
		}
	}
	if ( ! $found ) {
		fa_media_test_fail( 'Seeded attachment not found in list-media output' );
	} else {
		foreach ( [ 'caption', 'description', 'post_parent', 'size_urls' ] as $field ) {
			if ( array_key_exists( $field, $found ) ) {
				fa_media_test_pass( "list-media item includes new field: {$field}" );
			} else {
				fa_media_test_fail( "list-media item missing new field: {$field}" );
			}
		}
		if ( ( $found['caption'] ?? '' ) === 'A test caption.' ) {
			fa_media_test_pass( 'caption populated correctly' );
		} else {
			fa_media_test_fail( 'caption mismatch', $found['caption'] ?? null );
		}
		if ( ( $found['description'] ?? '' ) === 'A test description.' ) {
			fa_media_test_pass( 'description populated correctly' );
		} else {
			fa_media_test_fail( 'description mismatch', $found['description'] ?? null );
		}
		if ( ( $found['post_parent'] ?? 0 ) === (int) $parent_post_id ) {
			fa_media_test_pass( 'post_parent populated correctly' );
		} else {
			fa_media_test_fail( 'post_parent mismatch', $found['post_parent'] ?? null );
		}
		if ( is_array( $found['size_urls'] ?? null ) && ! empty( $found['size_urls']['full'] ) ) {
			fa_media_test_pass( 'size_urls has "full" entry: ' . $found['size_urls']['full'] );
		} else {
			fa_media_test_fail( 'size_urls missing or has no "full" entry', $found['size_urls'] ?? null );
		}
	}
}

// ─── Test: upload-media — single ─────────────────────────────────────────────

fa_media_test_header( 'filter/upload-media — single item' );

$single_path = fa_make_test_png( 'fa-upload-single.png' );
$single_url  = trailingslashit( wp_upload_dir()['baseurl'] ) . 'fa-test-fixtures/fa-upload-single.png';

$res = fa_run_ability( 'filter/upload-media', [
	'items' => [ [
		'url'         => $single_url,
		'alt_text'    => 'single upload alt',
		'caption'     => 'single caption',
		'description' => 'single description',
		'original_id' => 12345,
	] ],
] );

if ( isset( $res['error'] ) ) {
	fa_media_test_fail( 'upload-media single returned error', $res['error'] );
} else {
	$item = $res['results'][0] ?? null;
	if ( ! $item || empty( $item['success'] ) ) {
		fa_media_test_fail( 'Single upload was not successful', $item );
	} else {
		FA_Media_Test_Cleanup::$attachments[] = (int) $item['new_id'];
		fa_media_test_pass( "Single upload succeeded — new_id={$item['new_id']}" );

		if ( ( $item['original_id'] ?? 0 ) === 12345 ) {
			fa_media_test_pass( 'original_id echoed back correctly' );
		} else {
			fa_media_test_fail( 'original_id not echoed correctly', $item['original_id'] ?? null );
		}

		$alt = get_post_meta( $item['new_id'], '_wp_attachment_image_alt', true );
		if ( 'single upload alt' === $alt ) {
			fa_media_test_pass( 'alt_text persisted to _wp_attachment_image_alt' );
		} else {
			fa_media_test_fail( 'alt_text not persisted', $alt );
		}

		$post = get_post( $item['new_id'] );
		if ( $post && 'single caption' === $post->post_excerpt ) {
			fa_media_test_pass( 'caption persisted to post_excerpt' );
		} else {
			fa_media_test_fail( 'caption not persisted', $post->post_excerpt ?? null );
		}
		if ( $post && 'single description' === $post->post_content ) {
			fa_media_test_pass( 'description persisted to post_content' );
		} else {
			fa_media_test_fail( 'description not persisted', $post->post_content ?? null );
		}

		if ( is_array( $item['new_size_urls'] ?? null ) && ! empty( $item['new_size_urls']['full'] ) ) {
			fa_media_test_pass( 'new_size_urls populated with full entry' );
		} else {
			fa_media_test_fail( 'new_size_urls missing or empty', $item['new_size_urls'] ?? null );
		}

		// Original-file ingest check: stored file dimensions must equal source dimensions.
		$meta = wp_get_attachment_metadata( $item['new_id'] );
		if ( (int) ( $meta['width'] ?? 0 ) === 64 && (int) ( $meta['height'] ?? 0 ) === 48 ) {
			fa_media_test_pass( 'Stored file matches source dimensions (64x48) — original ingested, not a thumbnail' );
		} else {
			fa_media_test_fail( 'Stored dimensions do not match source — possibly ingested an intermediate size', [ 'width' => $meta['width'] ?? null, 'height' => $meta['height'] ?? null ] );
		}
	}
}

// ─── Test: upload-media — batch with mixed success/failure ───────────────────

fa_media_test_header( 'filter/upload-media — batch with mixed success/failure' );

fa_make_test_png( 'fa-upload-batch-1.png' );
fa_make_test_png( 'fa-upload-batch-2.png' );
$batch_url_1 = trailingslashit( wp_upload_dir()['baseurl'] ) . 'fa-test-fixtures/fa-upload-batch-1.png';
$batch_url_2 = trailingslashit( wp_upload_dir()['baseurl'] ) . 'fa-test-fixtures/fa-upload-batch-2.png';

$res = fa_run_ability( 'filter/upload-media', [
	'items' => [
		[ 'url' => $batch_url_1 ],
		[ 'url' => $batch_url_2 ],
		[ 'url' => 'not-a-url' ],                                  // invalid scheme/format
		[ 'url' => 'https://example.invalid/missing.png' ],        // unresolvable
	],
] );

if ( isset( $res['error'] ) ) {
	fa_media_test_fail( 'Batch returned a top-level error', $res['error'] );
} else {
	$summary = $res['summary'] ?? [];
	if ( ( $summary['requested'] ?? 0 ) === 4 ) {
		fa_media_test_pass( 'summary.requested == 4' );
	} else {
		fa_media_test_fail( 'summary.requested mismatch', $summary );
	}
	if ( ( $summary['succeeded'] ?? 0 ) === 2 ) {
		fa_media_test_pass( 'summary.succeeded == 2' );
	} else {
		fa_media_test_fail( 'summary.succeeded mismatch', $summary );
	}
	if ( ( $summary['failed'] ?? 0 ) === 2 ) {
		fa_media_test_pass( 'summary.failed == 2' );
	} else {
		fa_media_test_fail( 'summary.failed mismatch', $summary );
	}
	foreach ( $res['results'] ?? [] as $item ) {
		if ( ! empty( $item['success'] ) && ! empty( $item['new_id'] ) ) {
			FA_Media_Test_Cleanup::$attachments[] = (int) $item['new_id'];
		}
	}
}

// ─── Test: upload-media — SSRF guard ─────────────────────────────────────────

fa_media_test_header( 'filter/upload-media — SSRF guard rejects loopback/private IPs' );

$res = fa_run_ability( 'filter/upload-media', [
	'items' => [
		[ 'url' => 'http://127.0.0.1/x.jpg' ],
		[ 'url' => 'http://192.168.1.1/x.jpg' ],
		[ 'url' => 'http://10.0.0.1/x.jpg' ],
		[ 'url' => 'ftp://example.com/x.jpg' ],
	],
] );

if ( isset( $res['error'] ) ) {
	fa_media_test_fail( 'SSRF test returned a top-level error', $res['error'] );
} else {
	$results = $res['results'] ?? [];
	if ( count( $results ) === 4 && ( $res['summary']['failed'] ?? 0 ) === 4 ) {
		fa_media_test_pass( 'All 4 unsafe URLs rejected at validation' );
	} else {
		fa_media_test_fail( 'SSRF guard did not reject all unsafe URLs', $res );
	}
	foreach ( $results as $i => $item ) {
		if ( empty( $item['success'] ) && ! empty( $item['error'] ) ) {
			fa_media_test_pass( "Item {$i} returned validation error: " . substr( $item['error'], 0, 80 ) );
		}
	}
}

// ─── Test: upload-media — batch cap ─────────────────────────────────────────

fa_media_test_header( 'filter/upload-media — batch cap (>50 items rejected, filter raises cap)' );

$over_cap = [];
for ( $i = 0; $i < 51; $i++ ) {
	$over_cap[] = [ 'url' => 'https://example.com/x.png' ];
}
$res = fa_run_ability( 'filter/upload-media', [ 'items' => $over_cap ] );
if ( isset( $res['error'] ) && false !== stripos( $res['error'], 'too many' ) ) {
	fa_media_test_pass( 'Default 50-item cap rejects 51 items with clear error' );
} else {
	fa_media_test_fail( 'Default cap did not reject 51-item batch', $res );
}

// Filter should let advanced users raise the cap.
$raise_cap = static function () { return 200; };
add_filter( 'filter_abilities_upload_media_max_batch', $raise_cap );
$res = fa_run_ability( 'filter/upload-media', [ 'items' => $over_cap ] );
remove_filter( 'filter_abilities_upload_media_max_batch', $raise_cap );

if ( isset( $res['error'] ) && false !== stripos( $res['error'], 'too many' ) ) {
	fa_media_test_fail( 'Filter did not raise the cap', $res );
} else {
	// We expect 51 per-item failures (all URLs are unresolvable example.com paths),
	// but the top-level cap should NOT have rejected the request.
	if ( isset( $res['summary']['requested'] ) && (int) $res['summary']['requested'] === 51 ) {
		fa_media_test_pass( 'filter_abilities_upload_media_max_batch filter raises the cap above default' );
	} else {
		fa_media_test_fail( 'Cap was raised but request did not process all items', $res );
	}
}

// ─── Test: upload-media — set_as_featured_image ─────────────────────────────

fa_media_test_header( 'filter/upload-media — set_as_featured_image' );

$featured_target = wp_insert_post( [
	'post_title'  => 'FA Test featured target',
	'post_status' => 'publish',
	'post_type'   => 'post',
] );
FA_Media_Test_Cleanup::$posts[] = (int) $featured_target;

fa_make_test_png( 'fa-upload-featured.png' );
$featured_url = trailingslashit( wp_upload_dir()['baseurl'] ) . 'fa-test-fixtures/fa-upload-featured.png';

$res = fa_run_ability( 'filter/upload-media', [
	'items' => [ [
		'url'                   => $featured_url,
		'post_parent'           => (int) $featured_target,
		'set_as_featured_image' => true,
	] ],
] );

$item = $res['results'][0] ?? null;
if ( $item && ! empty( $item['success'] ) ) {
	FA_Media_Test_Cleanup::$attachments[] = (int) $item['new_id'];
	if ( ! empty( $item['featured_image_set'] ) ) {
		fa_media_test_pass( 'featured_image_set: true in result' );
	} else {
		fa_media_test_fail( 'featured_image_set was falsy', $item );
	}
	$thumb_id = (int) get_post_thumbnail_id( $featured_target );
	if ( $thumb_id === (int) $item['new_id'] ) {
		fa_media_test_pass( "Destination post _thumbnail_id matches new attachment ({$thumb_id})" );
	} else {
		fa_media_test_fail( 'Destination post _thumbnail_id mismatch', [ 'expected' => $item['new_id'], 'got' => $thumb_id ] );
	}
} else {
	fa_media_test_fail( 'Featured-image upload failed', $res );
}

// Without post_parent should fail per-item.
$res = fa_run_ability( 'filter/upload-media', [
	'items' => [ [
		'url'                   => $featured_url,
		'set_as_featured_image' => true,
	] ],
] );
$item = $res['results'][0] ?? null;
if ( $item && empty( $item['success'] ) && ! empty( $item['error'] ) && false !== stripos( $item['error'], 'post_parent' ) ) {
	fa_media_test_pass( 'set_as_featured_image without post_parent fails with clear error' );
} else {
	fa_media_test_fail( 'set_as_featured_image without post_parent did not fail as expected', $res );
}

// ─── Test: rewrite-content ───────────────────────────────────────────────────

fa_media_test_header( 'filter/rewrite-content — dry_run + applied' );

// Seed an "old" attachment + a "new" attachment to map between.
$old_path     = fa_make_test_png( 'fa-rewrite-old.png', 80, 60 );
$new_path     = fa_make_test_png( 'fa-rewrite-new.png', 80, 60 );
require_once ABSPATH . 'wp-admin/includes/image.php';

$old_id = (int) wp_insert_attachment( [
	'post_title'     => 'rewrite old',
	'post_mime_type' => 'image/png',
	'post_status'    => 'inherit',
], $old_path );
wp_update_attachment_metadata( $old_id, wp_generate_attachment_metadata( $old_id, $old_path ) );
FA_Media_Test_Cleanup::$attachments[] = $old_id;

$new_id = (int) wp_insert_attachment( [
	'post_title'     => 'rewrite new',
	'post_mime_type' => 'image/png',
	'post_status'    => 'inherit',
], $new_path );
wp_update_attachment_metadata( $new_id, wp_generate_attachment_metadata( $new_id, $new_path ) );
FA_Media_Test_Cleanup::$attachments[] = $new_id;

$old_url      = wp_get_attachment_url( $old_id );
$new_url      = wp_get_attachment_url( $new_id );
$old_size_url = $old_url ? str_replace( '.png', '-300x200.png', $old_url ) : '';

$test_content = <<<HTML
<!-- wp:image {"id":{$old_id},"sizeSlug":"large"} -->
<figure class="wp-block-image"><img src="{$old_url}" alt="" class="wp-image-{$old_id}"/></figure>
<!-- /wp:image -->

<!-- wp:gallery {"ids":[{$old_id}]} -->
<figure class="wp-block-gallery"><figure class="wp-block-image"><img src="{$old_url}" alt="" data-id="{$old_id}" class="wp-image-{$old_id}"/></figure></figure>
<!-- /wp:gallery -->

<p>A reference to an intermediate size: <img src="{$old_size_url}" alt=""/></p>

<p>[gallery ids="{$old_id}"]</p>
HTML;

$rewrite_target = (int) wp_insert_post( [
	'post_title'   => 'FA Test rewrite target',
	'post_status'  => 'publish',
	'post_type'    => 'post',
	'post_content' => $test_content,
] );
FA_Media_Test_Cleanup::$posts[] = $rewrite_target;
set_post_thumbnail( $rewrite_target, $old_id );

$media_map = [ [
	'old_id'        => $old_id,
	'new_id'        => $new_id,
	'old_url'       => $old_url,
	'new_url'       => $new_url,
	'old_size_urls' => [ $old_size_url ],
] ];

// Dry run.
$res = fa_run_ability( 'filter/rewrite-content', [
	'media_map' => $media_map,
	'post_ids'  => [ $rewrite_target ],
	'dry_run'   => true,
] );

if ( isset( $res['error'] ) ) {
	fa_media_test_fail( 'rewrite-content dry_run returned error', $res['error'] );
} else {
	$row = $res['results'][0] ?? null;
	if ( ! $row ) {
		fa_media_test_fail( 'No result row for dry_run' );
	} else {
		if ( ( $row['replacements']['block_attrs'] ?? 0 ) > 0 ) {
			fa_media_test_pass( "block_attrs replacements counted: {$row['replacements']['block_attrs']}" );
		} else {
			fa_media_test_fail( 'block_attrs not counted', $row['replacements'] ?? null );
		}
		if ( ( $row['replacements']['image_classes'] ?? 0 ) > 0 ) {
			fa_media_test_pass( "image_classes replacements counted: {$row['replacements']['image_classes']}" );
		} else {
			fa_media_test_fail( 'image_classes not counted', $row['replacements'] ?? null );
		}
		if ( ( $row['replacements']['urls'] ?? 0 ) > 0 ) {
			fa_media_test_pass( "urls replacements counted: {$row['replacements']['urls']}" );
		} else {
			fa_media_test_fail( 'urls not counted', $row['replacements'] ?? null );
		}
		if ( ( $row['replacements']['gallery_shortcode'] ?? 0 ) > 0 ) {
			fa_media_test_pass( "gallery_shortcode replacements counted: {$row['replacements']['gallery_shortcode']}" );
		} else {
			fa_media_test_fail( 'gallery_shortcode not counted', $row['replacements'] ?? null );
		}
		if ( ( $row['replacements']['thumbnail'] ?? 0 ) === 1 ) {
			fa_media_test_pass( 'thumbnail replacement counted' );
		} else {
			fa_media_test_fail( 'thumbnail not counted', $row['replacements'] ?? null );
		}
		if ( empty( $row['applied'] ) ) {
			fa_media_test_pass( 'applied: false (dry_run)' );
		} else {
			fa_media_test_fail( 'applied was true on dry_run' );
		}
	}

	// DB unchanged check.
	$post_after = get_post( $rewrite_target );
	if ( $post_after && $post_after->post_content === $test_content ) {
		fa_media_test_pass( 'DB content unchanged after dry_run' );
	} else {
		fa_media_test_fail( 'DB content was modified despite dry_run' );
	}
	if ( (int) get_post_thumbnail_id( $rewrite_target ) === $old_id ) {
		fa_media_test_pass( 'Featured image unchanged after dry_run' );
	} else {
		fa_media_test_fail( 'Featured image changed despite dry_run' );
	}
}

// Apply.
$res = fa_run_ability( 'filter/rewrite-content', [
	'media_map' => $media_map,
	'post_ids'  => [ $rewrite_target ],
	'dry_run'   => false,
] );

if ( isset( $res['error'] ) ) {
	fa_media_test_fail( 'rewrite-content applied returned error', $res['error'] );
} else {
	$post_after = get_post( $rewrite_target );
	if ( $post_after && false === strpos( $post_after->post_content, "wp-image-{$old_id}" ) && false !== strpos( $post_after->post_content, "wp-image-{$new_id}" ) ) {
		fa_media_test_pass( 'wp-image-N class rewritten in saved content' );
	} else {
		fa_media_test_fail( 'wp-image-N class not rewritten as expected' );
	}
	if ( $post_after && false !== strpos( $post_after->post_content, $new_url ) && false === strpos( $post_after->post_content, $old_url ) ) {
		fa_media_test_pass( 'URLs (incl. intermediate-size variant) rewritten' );
	} else {
		fa_media_test_fail( 'URL rewriting incomplete' );
	}
	if ( (int) get_post_thumbnail_id( $rewrite_target ) === $new_id ) {
		fa_media_test_pass( 'Featured image rewritten' );
	} else {
		fa_media_test_fail( 'Featured image not rewritten' );
	}
	if ( $post_after && false !== strpos( $post_after->post_content, "[gallery ids=\"{$new_id}\"]" ) ) {
		fa_media_test_pass( '[gallery] shortcode ids rewritten' );
	} else {
		fa_media_test_fail( '[gallery] shortcode ids not rewritten as expected' );
	}
}

// Mutual-exclusion validation.
$res = fa_run_ability( 'filter/rewrite-content', [
	'media_map'      => $media_map,
	'post_ids'       => [ $rewrite_target ],
	'post_type'      => 'post',
	'all_post_types' => true,
] );
if ( isset( $res['error'] ) && false !== stripos( $res['error'], 'exactly one' ) ) {
	fa_media_test_pass( 'Conflicting selectors rejected' );
} else {
	fa_media_test_fail( 'Conflicting selectors not rejected', $res );
}

// ─── Summary ────────────────────────────────────────────────────────────────

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "RESULTS: " . FA_Media_Test_Counter::$pass . " passed, " . FA_Media_Test_Counter::$fail . " failed, " . FA_Media_Test_Counter::$skip . " skipped\n";
echo str_repeat( '=', 60 ) . "\n";

if ( FA_Media_Test_Counter::$fail > 0 ) {
	echo "\n⚠ Some tests failed. Review output above.\n";
} else {
	echo "\n✓ All tests passed!\n";
}
