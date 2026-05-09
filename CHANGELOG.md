# Changelog

## 1.6.0

### Added
- **`filter/upload-media`** — sideload up to 50 remote URLs into the media library in one call (matches `filter/list-media`'s per-page cap; raise via the `filter_abilities_upload_media_max_batch` filter). Supports per-item `title`, `alt_text`, `caption`, `description`, `post_parent`, `date`, `set_as_featured_image`, and an `original_id` echo field for ID-mapping during cross-site migrations. Calls `set_time_limit(0)` and raises the image memory limit so large batches have a chance to complete. SSRF-guarded against loopback, link-local, and RFC1918 sources.
- **`filter/rewrite-content`** — new Migration Tools module. Rewrites media references in post content, Gutenberg block attributes (`core/image`, `core/gallery`, `core/cover`, `core/media-text`, `core/video`, `core/audio`, `core/file`), `wp-image-{ID}` classes, `[gallery]` shortcodes, featured-image postmeta, and ACF image/gallery/file fields, using a caller-supplied `media_map`. Defaults to `dry_run: true`; provide `dry_run: false` to apply.
- `filter/list-media` output extended with `caption`, `description`, `post_parent`, and `size_urls` (a `{size_name: url}` map covering all intermediate sizes, including `full`). The `size_urls` values are the input expected by `filter/rewrite-content`'s `media_map[].old_size_urls`.
- `filter_abilities_is_safe_external_url` filter — lets advanced users whitelist specific internal hostnames for `filter/upload-media`. Use sparingly.
- `filter_abilities_rewrite_block_attrs` filter — lets consumers register handlers for custom block types in `filter/rewrite-content`.
- `tests/test-media-abilities.php` — drop-in functional test runner covering all of the above (extended `list-media`, `upload-media` happy-path / batch / SSRF / batch cap / featured-image, `rewrite-content` dry-run / applied / mutual-exclusion validation).
- `docs/MIGRATION.md` — end-to-end cross-site migration guide covering the full media + post + reference-rewrite workflow, with worked examples, recovery patterns, recipes, and extension-point documentation.

## 1.5.1

### Fixed
- Plugin author metadata: now reads "Filter" linking to https://filter.agency.

## 1.5.0

### Added
- Anonymous opt-in telemetry via the StellarWP Telemetry library, sending only WordPress version, PHP version, locale, multisite flag, and active Filter plugins to https://telemetry.filter.agency.

## 1.4.3

### Changed
- Replaced StellarWP's default `Debug_Data` provider (≈60KB per ping with full plugin/theme/server config) with a minimal Filter-specific provider (≈500 bytes). Now sends only: WP version, PHP version, locale, multisite flag, site URL, and a map of active Filter plugin slugs → versions.

## 1.4.2

### Fixed
- StellarWP telemetry opt-in modal not appearing. The library exposes a `stellarwp/telemetry/optin` action but doesn't decide where to fire it; we now hook `admin_notices` to trigger the modal on admin pages for users with `manage_options`.

## 1.4.1

### Fixed
- Fatal `TypeError` on plugins_loaded when initialising telemetry: di52's `Container` does not formally implement StellarWP's `ContainerInterface`. Added a small adapter so the container satisfies the contract.

## 1.4.0

### Added
- **Anonymous opt-in telemetry** via the StellarWP Telemetry library. Sends WordPress version, PHP version, locale, multisite status, plugin version, and which Filter plugins are active to `https://telemetry.filter.agency`. Disabled by default — admins are prompted via the StellarWP opt-in modal on first activation.
- Server URL can be overridden via the `FILTER_ABILITIES_TELEMETRY_URL` constant in `wp-config.php` for local testing.

### Changed
- StellarWP Telemetry, lucatume/di52, and stellarwp/container-contract dependencies are bundled under the `Filter\Vendor\` namespace via Strauss to prevent collisions with other Filter plugins on the same site.

## 1.3.3

### Added
- `author` parameter on `filter/create-post` and `filter/update-post` — pass a user ID to set or reassign post authorship. Requires `edit_others_posts` capability.

## 1.3.2

### Added
- `date` parameter on `filter/update-post` — set post date in `YYYY-MM-DD` or `YYYY-MM-DD HH:MM:SS` format.

## 1.3.1

### Added
- **Content Management** — 3 new abilities:
  - `filter/get-post-by-url` — Look up a post by URL path or slug, returns full post data
  - `filter/delete-post` — Trash or permanently delete a post
  - `filter/bulk-post-actions` — Bulk publish, draft, trash, restore, or permanently delete multiple posts

## 1.3.0

### Added
- **Redirection Management module** — 8 new abilities for managing the [Redirection](https://redirection.me/) plugin:
  - `filter/list-redirects` — List redirect rules with filtering by status, group, and search term
  - `filter/list-redirect-groups` — List redirect groups with redirect counts
  - `filter/list-404-errors` — View 404 errors with optional URL grouping to identify most-hit missing pages
  - `filter/get-redirect-logs` — View redirect hit logs to verify redirects and see traffic patterns
  - `filter/redirect-stats` — Aggregate stats overview including top 404 URLs and most-used redirects
  - `filter/check-redirect` — Check if a URL path has a matching redirect rule (supports exact and regex matching)
  - `filter/manage-redirect` — Create, update, or delete a redirect rule
  - `filter/bulk-manage-redirects` — Enable, disable, delete, or reset hit counters for multiple redirects at once

## 1.2.1

- Bump version

## 1.2.0

- Security hardening and WP standards improvements

## 1.1.0

- Add table existence checks to all PWP execute methods + missing PHPDoc

## 1.0.0

- Initial release with Content Management, Site Health, Taxonomy Management, Media Management, ACF Fields, SEO Management, Form Management, AI Content, Personalization, and Teams Analytics modules
