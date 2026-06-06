# Changelog

## 1.8.0

### Added — Form Management module expansion

Twelve new abilities extend `filter-forms` from read-only summaries into full Gravity Forms control, with first-class Mailchimp introspection and a conditional-logic linter so an MCP session can compose feeds and gated confirmations without leaving the conversation. Designed so a single session can audit a form estate, consolidate forms, manage notifications, and switch over add-on feeds without admin UI or WP-CLI access. Every write ability supports `dry_run` to preview the computed result without persisting.

- **`filter/get-form`** — detailed counterpart to `filter/list-forms`. Returns the complete form object: `fields[]`, `confirmations{}`, `notifications{}`, `settings`, `is_active`, `is_trash`, `date_created`. Optional `include_feeds: true` adds the form's add-on feeds (Mailchimp, HubSpot, etc.), explicitly passing `is_active=null` to `GFAPI::get_feeds()` so inactive feeds are included (the GFAPI default of `true` would otherwise hide them).
- **`filter/manage-form`** — `create | update | delete | duplicate | set-active`. Defaults to trash on delete (reversible, entries preserved); `force: true` permanently deletes the form **and all its entries** (irreversible). `duplicate` uses the `GFAPI::duplicate_form()` wrapper; add-on feeds do not duplicate (separate table, keyed by `form_id`).
- **`filter/manage-form-field`** — `add | update | delete | move`. Targeted, single-field edits in the spirit of the v1.7 block-editing module: read form, mutate the `fields` array, save — never rewrite the whole form (which corrupts it). Validates `type` against a supported allow-list before calling `GF_Fields::create()`, because the GF factory silently returns a generic `GF_Field` on an unknown type. Allocates new ids via `GFFormsModel::get_next_field_id()` and updates `$form['nextFieldId']`. Supported types: `text`, `email`, `name`, `textarea`, `select`, `checkbox`, `radio`, `website`, `phone`, `hidden`, `consent`, `html`, `section`, `date`, `time`, `fileupload`.
- **`filter/manage-form-confirmation`** — `add | update | delete | set-default` on `$form['confirmations']`. Supports per-rule `conditionalLogic`, enabling one-form-many-confirmations patterns (e.g. a single Guide Download form gated by a hidden `guide` field, dispatching different download links per topic).
- **`filter/manage-form-notification`** — `add | update | delete | set-active` on `$form['notifications']`. Same per-uniqid keying as confirmations. Supports the full notification shape (`service`, `event`, `to`, `toType`, `bcc`, `fromName`, `from`, `replyTo`, `subject`, `message`, `disableAutoformat`, `enableAttachments`, `conditionalLogic`, `routing`) with sensible defaults applied on `add` (`service: "wordpress"`, `event: "form_submission"`, `isActive: true`) so a minimal payload still produces a working notification. `set-active` toggles `isActive` — the retire mechanism for notifications.
- **`filter/list-form-feeds`** — feed introspection. Wraps `GFAPI::get_feeds()` with an explicit `is_active` input (defaults to `null` / all feeds). The introspection tool for capturing add-on `meta` shape before writing new feeds.
- **`filter/manage-form-feed`** — `create | update | delete | set-active`. `set-active` uses `GFAPI::update_feed_property( $feed_id, 'is_active', 0|1 )` (there is no `GFAPI::update_feed_active()` static method). Validates that the form exists and the target add-on slug is registered before `add_feed`.

### Added — Form usage finder

- **`filter/find-form-usage`** — read-only. Finds every post/page that embeds a given Gravity Form so a consolidation can repoint each embed. Three detection paths, none of which hard-code a site's block names:
  - **Exact, universal**: the `gravityforms/form` block (`formId`) and the `[gravityform id=N]` shortcode — identical on every GF install.
  - **Generic attribute heuristic**: any block (ACF or otherwise) whose attributes — including ACF's nested `data` — carry a *form-shaped* key whose numeric value equals the form id. Form-shaped is a word-boundary match (`gravity-form-id`, `formId`, `my_form_id` match; `platform_id`, `transformId` don't), so it catches bespoke ACF blocks on any site with zero configuration. Tunable via the `filter_abilities_form_reference_keys` filter, which is the single source of truth for both the heuristic and the SQL `LIKE` prefilter.
  - Each match carries `match_method` (`"exact"` vs `"heuristic"`), `embed_type`, `block_name`, `field_key`, `attribute_path`, and the block `ref`/`path` (reused verbatim from the block engine so results chain straight into `mutate-block`). Reusable blocks (`core/block` / `wp_block`) are resolved and flagged with `via_synced_pattern`. Integer comparison after `parse_blocks` is authoritative — `"12"` never matches `"120"`. Forms set via theme options, widgets, or template `gravity_form()` calls are out of scope (not in `post_content`).

### Added — Conditional-logic validator

- **`filter/validate-conditional-logic`** — read-only linter for `conditionalLogic` objects against a form. Checks structure (`actionType` in `show|hide`, `logicType` in `all|any`, non-empty `rules[]`), each rule's shape (`fieldId` + `operator` + `value`), that operators are in the canonical list (`is, isnot, <>, in, not in, >, <, >=, <=, contains, starts_with, ends_with, like`), and — critically — that every `fieldId` references a field that actually exists on the form (composite sub-input ids like `"1.3"` are resolved to their parent). Returns `{ valid, errors: [{ path, message }], operators_reference }`. Designed for AI agents to lint logic before writing.

### Changed — conditional logic is now validated on every write path

Direct `conditionalLogic` pass-through (the documented v1 approach — no DSL or builder) now runs through the same validator on every write that accepts a logic object: `manage-form-field` add/update, `manage-form-confirmation` add/update, `manage-form-notification` add/update, and `manage-form-feed` create/update (which checks `meta.feed_condition_conditional_logic_object.conditionalLogic`). Gravity Forms otherwise silently coerces invalid `actionType` to `"show"`, invalid operators to `"is"`, and accepts non-existent `fieldId` references — producing logic that never matches and gives no clue why. With this change those problems return as `[ 'error' => '…', 'errors' => [{ path, message }] ]` instead, with paths like `confirmation.conditionalLogic.rules[0].fieldId`.

Indexed-array properties (`conditionalLogic`, `choices`, `inputs`) on `manage-form-field` updates are now REPLACED wholesale instead of merged by index. The previous `array_replace_recursive` behaviour leaked stale entries when a patch shortened the array — e.g. patching `rules: [A, B]` over an existing `rules: [X, Y, Z]` would produce `[A, B, Z]`. Pass the complete intended value on update.

### Security

- **`manage-form update` capability bypass closed.** The previous `form_patch` guard only blocked `fields`, `confirmations`, `notifications`. It did not block `is_trash` or `is_active`, which meant a user with `gravityforms_edit_forms` (but not `gravityforms_delete_forms`) could trash a form by passing `form_patch: { is_trash: 1 }` — bypassing the deliberate capability separation between the `update` and `delete` operations. The patch guard now rejects `is_trash`, `is_active`, `id`, `nextFieldId`, and `date_created` with operation-specific guidance.

### Fixed — silent failures surfaced

- **`manage-form delete` (trash) and `set-active` silently reported success on DB failure.** `GFAPI::update_forms_property` returns `WP_Error` for validation errors but falls through to `$wpdb->query()` which returns `int|false`. The previous code only tested `is_wp_error()`, so a `false` (DB-layer failure) was reported as a successful trash/toggle. Both call sites now also test `false === $result`.
- **`manage-form-feed` rejected non-scalar `mappedFields` values.** Previously a payload like `mappedFields: { EMAIL: { complex: object } }` was flattened to `mappedFields_EMAIL: {complex: object}` and stored. The add-on then tried to treat the object as a field id at submission time, silently failing. The flattener now returns a clear `invalid_field_map_value` error before the bad data ever reaches storage.
- **Individual `mappedFields_*` entries can now actually be cleared.** Previously `mappedFields: { FNAME: null }` left `mappedFields_FNAME: null` in feed meta, which the add-on read as field id `null`. Null values in the flattened patch are now stripped from the merged meta before persistence.
- **`mappedFields: {}` and `mappedFields: null` now mean "clear all mappings".** Previously the empty-object form was a silent no-op — the `is_assoc` check counted string keys, got zero, and skipped the flatten. Both forms now emit a clear-sentinel that removes every `mappedFields_*` key from existing meta.
- **`mailchimp_direct_get` returned `[]` on a 200 with malformed JSON.** A WAF interstitial, gzip middleware glitch, or truncated body all looked identical to "no results" to the caller. The helper now returns `WP_Error( mailchimp_decode_failed )` or `WP_Error( mailchimp_empty_body )` instead.
- **`get-form-entries` (pre-1.8.0 ability) lost values when two fields shared a label.** Field values were keyed by label, so two `Email` fields collided. Duplicate labels are now disambiguated with the field id (`"Email (id 5)"`).
- **Field self-referencing `conditionalLogic` no longer wrongly rejected on add.** The validator now sees a synthetic form snapshot that includes the to-be-added field, so a field can reference its own id in its visibility rules.
- **`manage-form-field` update no longer lets `formId` be corrupted via patch.** `merge_field_properties` now locks `formId` to the parent form's id after merge, regardless of what the patch contained.
- **`list-mailchimp-groups` capped at 25 interest categories** with a `truncated: true` flag in the response. The previous N+1 HTTP fan-out (one request per category) could time out PHP on accounts with many groupings.

### Changed — minor

- **`manage-form-field` move** now reports `moved: false` with `from_index` and `to_index` when the resolved destination matches the current index, and skips the DB write. Previously the response said `operation: move` regardless, masking caller off-by-one bugs.
- **Notification `add` `toType` default** simplified to always `'email'` when not supplied (the previous `isset(to) ? 'email' : 'email'` ternary was dead code).
- **Confirmation, notification, and field `add` paths** now apply the same explicit-null-as-delete semantics as their `update` counterparts. Previously a caller passing `conditionalLogic: null` on `add` had the literal null persisted; now the key is unset, matching update.
- **`Filter_Abilities_MCP_Ability::stdclass_to_array()`** gained a recursion-depth guard (600 levels, just above PHP's default `json_decode` depth of 512) to prevent a pathological JSON payload from blowing the PHP stack.

### Changed — Mailchimp `mappedFields` ergonomics

The Gravity Forms Mailchimp add-on persists field-map settings as flat prefixed meta keys (`mappedFields_EMAIL`, `mappedFields_FNAME`, ...). Callers who wrote the intuitive nested form (`mappedFields: { EMAIL: "3", FNAME: "1.3" }`) would have their mapping silently ignored on submission. `manage-form-feed create` and `update` now accept either shape: a nested object is transparently flattened to the prefixed-key form before save. `list-form-feeds` and `get-form` (with `include_feeds`) re-nest on read, so introspection returns the same shape callers should write. Applies to other GF field-map settings too (`listFields`, `customFields`).

### Changed — Field-type allow-list expanded

`manage-form-field` now accepts: text, textarea, number, select, multiselect, checkbox, radio, hidden, html, section, page, name, email, website, phone, address, date, time, fileupload, consent, list, password, multiple-choice, image-choice (24 types). Post fields and pricing fields are still deliberately excluded — they require side-effect handling and would expand the surface meaningfully.

### Changed — `conditionalLogic: null` now truly deletes

Passing `conditionalLogic: null` in a confirmation/notification/field update used to leave a `key => null` on the merged object (via `array_merge`). GF tolerated this because its own runtime guards rely on `isset()`, but the serialised form was untidy and some other consumers can choke. The patch now `unset()`s any key the caller explicitly set to null — the documented "delete this property" idiom across all three abilities.

### Added — Mailchimp pickers (conditionally registered)

Registered only when the Gravity Forms Mailchimp add-on is active. They reuse the add-on's stored credentials via `gf_mailchimp()->initialize_api()`, so no API keys live in the abilities layer. Together they cover every value an MCP session needs to compose a Mailchimp feed end-to-end via `manage-form-feed`.

- **`filter/list-mailchimp-audiences`** — every audience (list) reachable with the add-on's credentials, with `id`, `name`, `member_count`. The `id` is what goes in `mailchimpList` on a feed.
- **`filter/list-mailchimp-tags`** — static tags on an audience, retrieved by hitting `/lists/{id}/segments?type=static` directly (the GF wrapper doesn't expose segments). Tag **names** — not ids — are what populate a feed's `tags` meta.
- **`filter/list-mailchimp-merge-fields`** — merge fields on an audience, with `EMAIL` prepended (Mailchimp omits it from `/merge-fields` because it's implicit). The `tag` values are the keys you map Gravity Forms fields to inside `mappedFields`.
- **`filter/list-mailchimp-groups`** — interest groupings + their interests on an audience. Interest ids populate a feed's `groups` meta.

### Fixed

- **`filter/list-forms` permission**: replaced the nonexistent `gravityforms_view_forms` capability with `gravityforms_edit_forms`. Gravity Forms has no separate "view forms" capability — form reading and editing are both gated by `gravityforms_edit_forms`. The `manage_options` fallback is retained, so this is a tightening for subscriber/contributor users only.

### Notes

- All new abilities re-check Gravity Forms capabilities per-call (`gravityforms_edit_forms` for reads / field / confirmation / feed writes; `gravityforms_create_form` for `manage-form create`; `gravityforms_delete_forms` for `manage-form delete`), each with a `manage_options` admin fallback.
- The error envelope follows the existing convention: `[ 'error' => 'message' ]` on failure; flat data array on success.

## 1.7.0

### Added
- **Block Editing module** (`filter-blocks`) — surgical, block-aware Gutenberg editing over MCP. Wraps the vendored GravityKit Block API engine so AI agents can read a parsed block tree with stable refs and submit targeted changes to a single block (instead of regenerating an entire `post_content` string, which routinely drops `<!-- wp:... -->` delimiters and corrupts the post). Abilities: `filter/get-post-blocks`, `filter/list-block-types`, `filter/update-block`, `filter/insert-blocks`, `filter/delete-blocks`, `filter/mutate-block`, `filter/batch-edit-blocks`. See `docs/BLOCK-EDITING.md`.

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
