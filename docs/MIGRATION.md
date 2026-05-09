# Cross-Site Migration Guide

This guide explains how to migrate **media and post content** from one WordPress site to another using the abilities exposed by Filter Abilities, driven by an MCP-connected AI agent (e.g. Claude).

If you only want a single-image upload or a one-off content rewrite, the per-ability descriptions in [the README](../README.md) are sufficient. This guide is for the larger workflow.

## Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Quickstart](#quickstart)
- [The mental model](#the-mental-model)
- [Phase 1 — Migrating media](#phase-1--migrating-media)
- [Phase 2 — Migrating posts](#phase-2--migrating-posts)
- [Phase 3 — Rewriting references](#phase-3--rewriting-references)
- [Worked example: 500 images and 200 posts](#worked-example-500-images-and-200-posts)
- [The dry-run checkpoint](#the-dry-run-checkpoint)
- [Failure modes and recovery](#failure-modes-and-recovery)
- [Recipes](#recipes)
- [Extension points](#extension-points)
- [Limitations and future work](#limitations-and-future-work)
- [Verification checklist](#verification-checklist)

---

## Overview

Cross-site migration is a three-phase workflow:

1. **Media** — enumerate every attachment on the source, sideload each one onto the destination, build an `old_id → new_id` map.
2. **Posts** — enumerate posts on the source, fetch their full content, create them on the destination (still containing references that point at the source's IDs/URLs).
3. **Rewrite** — apply the ID/URL map to the destination posts so block attributes, `wp-image-{ID}` classes, gallery shortcodes, featured images, and ACF fields all point at the new attachments.

Each phase uses one or two abilities:

| Phase | Abilities |
|---|---|
| Media | `filter/list-media` (source) → `filter/upload-media` (destination) |
| Posts | `filter/list-posts` + `filter/get-post` (source) → `filter/create-post` (destination) |
| Rewrite | `filter/rewrite-content` (destination) |

The orchestrator (Claude or another MCP agent) is responsible for chaining the calls, holding the ID maps in memory, and presenting failures back to the user.

## Prerequisites

- **Both sites running Filter Abilities 1.6.0 or higher** with the WordPress MCP Adapter installed and configured.
- **WordPress 6.9+** on both sides (Abilities API requirement).
- **MCP connections** to both sites set up in your AI agent. Claude Code, Claude Desktop, and any other MCP-compatible client all work.
- **User accounts with the right capabilities:**
  - On the **source**: any user who can `edit_posts` and `upload_files` (e.g. an Editor account).
  - On the **destination**: a user who can `upload_files` AND `edit_others_posts` AND `publish_posts` for the post types you're migrating. An Administrator account is the safest default.
- **GD or Imagick PHP extension** on the destination (needed by `wp_generate_attachment_metadata` to produce intermediate sizes).
- **The source site's media URLs are publicly reachable from the destination's server.** This is normally the case for any production WP site, but check first if the source is behind basic auth, a VPN, or password protection — `filter/upload-media` fetches the bytes server-side, so it must be able to GET the URL.

## Quickstart

For a full content + media migration, the user prompt is:

> Migrate everything from `oldsite.example` to `newsite.example`.

The agent will run the three phases automatically and pause at the rewrite-content dry-run for your approval before applying changes. Expected wall-clock for a typical small site (≤200 attachments, ≤100 posts): 5–15 minutes.

For media-only:

> Migrate just the media library from `oldsite.example` to `newsite.example`.

For posts-only (when the destination already has the media):

> Migrate the published posts from `oldsite.example` to `newsite.example`. The media is already in place on the destination — match attachments by filename when rewriting references.

## The mental model

Three things to keep in mind throughout:

1. **Bytes don't go through MCP.** `filter/upload-media` only receives URLs in the JSON tool arguments. The destination server fetches the actual image bytes directly from the source's public URL via an HTTP GET. This is what makes a 5GB media library practical: only the URLs travel through the MCP transport.
2. **IDs change across sites.** Attachment ID 47 on the source might become 152 on the destination. Post ID 30 on the source might become 88. Anything that references content by ID has to be translated. That's what `filter/rewrite-content` does for media references; post-to-post references are a current limitation (see [Limitations](#limitations-and-future-work)).
3. **The orchestrator (Claude) holds the maps.** The `old_id → new_id` correspondence is maintained in conversation memory by the agent. There's no server-side persistence — if the conversation is interrupted, the map is lost. Mitigation: ask Claude to save the map to a file mid-flight.

## Phase 1 — Migrating media

### What the source returns

`filter/list-media` (extended in 1.6.0) returns per-attachment:

- `id`, `title`, `filename`, `url`, `mime_type`, `width`, `height`, `file_size`, `date`
- `caption` (post_excerpt), `description` (post_content), `post_parent` (0 if unattached)
- `alt_text` (from `_wp_attachment_image_alt`)
- `size_urls` — a `{size_name: url}` map that includes `full` plus every registered intermediate size (`thumbnail`, `medium`, `large`, etc.)

The `size_urls` map is critical for the rewrite phase. Capture it for every attachment.

### What the destination does

`filter/upload-media` accepts an array of items (default cap 50, raise via the [`filter_abilities_upload_media_max_batch`](#extension-points) filter). For each item:

```json
{
  "url": "https://oldsite.example/wp-content/uploads/2024/01/photo.jpg",
  "title": "Photo title",
  "alt_text": "...",
  "caption": "...",
  "description": "...",
  "date": "2024-01-15 10:30:00",
  "original_id": 47
}
```

The destination:

1. Validates the URL (rejects loopback, link-local, RFC1918 — see [SSRF guard](#extension-points)).
2. Calls `download_url()` with a 30s timeout.
3. Calls `media_handle_sideload()`, which writes the file to `uploads/`, runs `wp_check_filetype_and_ext` (rejecting disallowed MIME types), and **regenerates intermediate sizes per the destination's own `add_image_size` registrations**. The destination's resize regime is honoured automatically — you do not need to and must not pass intermediate-size URLs as input.
4. Writes alt text, caption, description, and (optionally) the original date.
5. Returns a per-item result including `new_id`, `new_url`, `new_size_urls`, and the echoed `original_id` for ID-mapping.

### Building the ID map

After every batch, append the per-item results to a running list:

```json
[
  { "old_id": 47, "new_id": 152,
    "old_url": "https://oldsite.example/.../photo.jpg",
    "new_url": "https://newsite.example/.../photo.jpg",
    "old_size_urls": [
      "https://oldsite.example/.../photo-300x200.jpg",
      "https://oldsite.example/.../photo-1024x768.jpg"
    ]
  },
  ...
]
```

This is the `media_map` that `filter/rewrite-content` consumes in Phase 3. Build it incrementally — don't wait until all uploads are done.

## Phase 2 — Migrating posts

Post migration uses three abilities:

- `filter/list-posts` — paginated enumeration on the source (per-page cap 50). Filter by `post_type`, `status`, `search`. Returns headline data only — title, status, date, excerpt, etc.
- `filter/get-post` — full record for a single post: `content`, `taxonomies`, `acf_fields`, `featured_image`, `author`. Call this once per post in the migration list.
- `filter/create-post` — creates the post on the destination. Inputs include `title`, `content`, `status`, `excerpt`, `taxonomy_terms`, `acf_fields`, `author`.

### The order matters

**Always migrate media before posts.** Reasons:

- Featured image translation in Phase 3 requires the new attachment IDs to exist.
- Block attributes referencing media IDs need a complete `media_map` to resolve.
- ACF image fields referencing source-side attachment IDs would otherwise be broken.

If you migrate posts first, Phase 3 has nothing to remap to and the destination posts are left with broken refs.

### What gets preserved automatically

`filter/create-post` preserves whatever you pass in:

- `title`, `content`, `excerpt`, `status` — verbatim.
- `taxonomy_terms` — pass as `{"category": [1, 2], "post_tag": [5]}`. **Term IDs must already exist on the destination.** Use `filter/list-terms` on both sides and match by slug (or use `filter/manage-term` to create missing terms first — see the recipe below).
- `acf_fields` — pass key/value pairs. ACF handles validation; complex fields (relationship, image) need IDs translated *before* you pass them.

### What gets lost without translation

- **Author IDs** — user 5 on the source isn't user 5 on the destination. `filter/create-post` defaults to the current user (the one whose credentials the MCP adapter is using). To map authors, list users on both sides via the WP REST API and translate by email.
- **Permalinks** — based on slug + site URL. The slug is preserved, but `https://oldsite.example/about-us/` becomes `https://newsite.example/about-us/`. Internal links inside post content pointing at the old domain are handled by Phase 3's `url_map`.
- **Post-to-post ID references** (e.g. ACF `post_object` fields, `next_page` links) are NOT translated. See [Limitations](#limitations-and-future-work).

### Building the post ID map

As Phase 2 progresses, keep a running map of source post ID → destination post ID. You'll need it for any post-to-post translations you do manually, and to support the recipe "rewrite a specific subset of migrated posts."

```json
[
  { "old_id": 30, "new_id": 88 },
  { "old_id": 31, "new_id": 89 },
  ...
]
```

## Phase 3 — Rewriting references

`filter/rewrite-content` walks each post on the destination and rewrites:

- **Gutenberg block attributes** for `core/image`, `core/gallery`, `core/cover`, `core/media-text`, `core/video`, `core/audio`, `core/file` — `id`, `url`, `mediaId`, `mediaUrl`, `src`, `href`, and the `ids[]` array on galleries.
- **`wp-image-{ID}` classes** — *the most important rewrite for visual fidelity.* Once the class points at the destination's attachment ID, WordPress's frontend `wp_filter_content_tags` hook rebuilds correct `src` and `srcset` at render time using the destination's own intermediate sizes. So even if a literal `<img src=".../photo-300x200.jpg">` URL is left referencing a non-existent file, the rendered HTML will still load correctly.
- **Raw URLs in post content** — every entry in `media_map[i].old_url` and `media_map[i].old_size_urls[]` is replaced with the corresponding `new_url`. This catches `<img src>` URLs pointing at intermediate sizes that the source had registered but the destination doesn't.
- **`[gallery]` shortcodes** — both `ids` and `include` attributes are translated.
- **Featured images** — `_thumbnail_id` postmeta swapped via the ID map.
- **ACF fields** — image, gallery, file fields rewritten by ID. Other field types (post_object, relationship) are left alone.

### Targeting the right posts

Pass exactly **one** of:

- `post_ids: [88, 89, 90]` — explicit list. Best when you want to rewrite only the posts you just migrated.
- `post_type: "post"` — all posts of one type, paginated.
- `all_post_types: true` — every post across every public type, paginated.

Mismatch (e.g. providing both `post_ids` and `post_type`) returns a validation error.

### A complete `rewrite-content` call

```json
{
  "media_map": [
    { "old_id": 47, "new_id": 152,
      "old_url": "https://oldsite.example/.../photo.jpg",
      "new_url": "https://newsite.example/.../photo.jpg",
      "old_size_urls": [
        "https://oldsite.example/.../photo-300x200.jpg",
        "https://oldsite.example/.../photo-1024x768.jpg"
      ]
    }
  ],
  "url_map": [
    { "old": "https://oldsite.example", "new": "https://newsite.example" }
  ],
  "post_ids": [88, 89, 90],
  "include_postmeta": true,
  "include_acf": true,
  "dry_run": true
}
```

The `url_map` handles general site-URL changes (internal links inside post content, links to non-media URLs). It's applied *after* the media URL replacements, sorted longest-first so a long URL like `…/photo-300x200.jpg` matches before a generic `https://oldsite.example` prefix.

### Reading the output

```json
{
  "total_posts": 3,
  "page": 1,
  "total_pages": 1,
  "dry_run": true,
  "results": [
    {
      "post_id": 88,
      "post_title": "Welcome to our new site",
      "post_type": "post",
      "replacements": {
        "block_attrs": 4,
        "image_classes": 6,
        "urls": 11,
        "gallery_shortcode": 1,
        "thumbnail": 1,
        "acf_fields": 2
      },
      "applied": false
    }
  ]
}
```

Non-zero counts on the categories you expect = working as intended. Zero counts everywhere likely means the `media_map` didn't match anything in the post content (possibly a URL-formatting mismatch — see [Recipes](#recipes)).

## Worked example: 500 images and 200 posts

An end-user might say:

> Migrate everything from `oldsite.example` to `newsite.example`.

The agent does this, with rough wall-clock at each step:

| Step | Calls | Time |
|---|---|---|
| `list-media` paginated, 50 per page | 10 | ~30s |
| `upload-media` batched, 50 per call | 10 | ~15min |
| `list-posts` paginated, 50 per page | 4 | ~15s |
| `get-post` per post | 200 | ~3min |
| `create-post` per post | 200 | ~3min |
| `rewrite-content` (dry-run) on the 200 new posts | 4 batches of 50 | ~30s |
| **User reviews dry-run output, approves** | — | varies |
| `rewrite-content` (apply) | 4 | ~30s |

**Total: ~22 minutes** of agent-time, plus however long the user spends reviewing the dry-run report.

The dominant cost is the upload phase — `media_handle_sideload` does the heavy lifting per attachment (download + write + intermediate-size regeneration).

## The dry-run checkpoint

`filter/rewrite-content` defaults to `dry_run: true` because it's the only destructive bulk operation in the migration pipeline. Even when Claude runs the migration end-to-end automatically, it will pause at the dry-run step and present the per-post replacement counts.

What to look for in the dry-run report:

- **Are the replacement counts non-zero on the categories you expect?**
- **Are any posts you wanted migrated NOT in the results array?** That indicates a targeting mismatch.
- **Do any posts have an `error` field?** (Permission failure on a per-post basis lands here.)
- **Does the count of `image_classes` match roughly the number of images you'd expect to see in the posts?**

If everything looks reasonable, approve. If anything looks off, fix the input (`media_map`, `post_ids`, etc.) before applying.

## Failure modes and recovery

### Per-item upload failures

`filter/upload-media` returns per-item results. A typical failed item:

```json
{
  "success": false,
  "source_url": "https://oldsite.example/.../missing.jpg",
  "original_id": 47,
  "error": "A valid URL was not provided.",
  "featured_image_set": false
}
```

The orchestrator should:
- Log the failures.
- Optionally retry each individually (one-item batch) with a longer per-item budget.
- Report the final count to the user.

A failed media item silently breaks any post that referenced it — Phase 3's rewrite-content won't have a mapping entry for that ID, so the broken refs stay broken. Surface these clearly to the user.

### Per-post create failures

`filter/create-post` returns `{"error": "..."}` on failure. Common causes: invalid taxonomy terms, missing required fields for a custom post type, capability mismatch. The orchestrator should report which source post couldn't be created and continue with the rest.

### Per-post rewrite failures

`filter/rewrite-content` returns per-post results. Per-post errors land in the `error` field; the rest of the batch continues.

### Lost ID maps

If the conversation is interrupted partway through, the `media_map` and post ID map are lost from Claude's memory. To recover:

1. **If the map was saved to a file** (always recommended for migrations >50 items): feed it back in.
2. **If not**: call `filter/list-media` on both sides and reconstruct by matching filename + filesize. Brittle when filenames collided or got `-1`/`-2`/`-3` suffixes during sideload, but workable for clean migrations.

You can ask Claude to do this explicitly:

> Save the migration ID maps to a JSON file every time you finish a batch, so we can resume if needed.

### Partially-migrated destination

If you re-run a migration without dedupe, the destination ends up with duplicate attachments (each gets a `-1`/`-2` suffix in the filename). Two ways to handle:

1. Delete the partial migration's attachments first via `wp_delete_attachment` (no ability for this currently — use WP-CLI or the admin UI).
2. Have the agent call `filter/list-media` on the destination first and skip items whose `filename` already matches.

## Recipes

### Migrate just one post type

```
filter/list-posts { post_type: "page", per_page: 50, page: 1 }
... loop pages ...
filter/get-post   { post_id: <each> }
filter/create-post { post_type: "page", title, content, ... }
filter/rewrite-content { media_map, post_type: "page", dry_run: true }
filter/rewrite-content { media_map, post_type: "page", dry_run: false }
```

### Re-running on a partially-migrated destination

Before each `upload-media` batch, ask the agent to:
1. Call `filter/list-media` on the destination with `search: <filename>` for each pending item.
2. If a match is found with the same filename, skip the upload and reuse the existing attachment ID for the map.
3. Only sideload items that aren't already on the destination.

This is conversational (not built into the abilities) — the orchestrator handles it.

### Fixing image refs in already-migrated posts

If you migrated posts months ago using a different tool and have only just realised the images are broken:

1. Reconstruct or load the `media_map`.
2. Call `filter/rewrite-content` with `dry_run: true` first.
3. Eyeball the report.
4. Apply.

No re-creation of posts needed.

### Migrating taxonomies first

For categories/tags/custom taxonomies, call `filter/list-terms` on the source, then `filter/manage-term` on the destination to create the matching slugs. Build a `term_id_map` like the media map, and translate term IDs in `filter/create-post`'s `taxonomy_terms` input.

### Excluding certain media

If you want to skip large videos, full-resolution RAW files, or anything else:

- Filter the `list-media` output before passing to `upload-media`. The agent can apply any predicate (e.g. `mime_type` starts with `image/`, `file_size < 5MB`).
- `filter/upload-media` doesn't enforce server-side filtering beyond MIME validation — that's the orchestrator's job.

## Extension points

Three filter hooks let you customise behaviour:

### `filter_abilities_is_safe_external_url`

Default behaviour rejects loopback, link-local, and RFC1918 source URLs to prevent SSRF. If you have a legitimate internal mirror or staging server, whitelist it:

```php
add_filter( 'filter_abilities_is_safe_external_url', function ( $is_safe, $url ) {
    if ( false !== strpos( $url, 'mirror.internal.example' ) ) {
        return true;
    }
    return $is_safe;
}, 10, 2 );
```

Use sparingly — the default exists to protect against an attacker passing arbitrary URLs and having the WP server fetch them.

### `filter_abilities_upload_media_max_batch`

Default cap is 50 items per `filter/upload-media` call. Raise on hosts with strong execution-time/memory budgets:

```php
add_filter( 'filter_abilities_upload_media_max_batch', fn() => 200 );
```

Note that no matter how high you set this, real-world ceilings will be set by upstream proxies, load balancers, and browser timeouts — anything taking more than a few minutes synchronously is risky.

### `filter_abilities_rewrite_block_attrs`

`filter/rewrite-content` ships with handlers for the seven core media block types. To add support for a custom block (e.g. a third-party slider that stores `attachmentIds: [...]`):

```php
add_filter( 'filter_abilities_rewrite_block_attrs', function ( $block, $id_map, $url_map, $generic_url_map ) {
    if ( 'mytheme/slider' === ( $block['blockName'] ?? '' ) && ! empty( $block['attrs']['attachmentIds'] ) ) {
        foreach ( $block['attrs']['attachmentIds'] as $i => $old ) {
            if ( isset( $id_map[ (int) $old ] ) ) {
                $block['attrs']['attachmentIds'][ $i ] = $id_map[ (int) $old ]['new_id'];
            }
        }
    }
    return $block;
}, 10, 4 );
```

The hook fires after the built-in block attribute rewrites, so you can also override behaviour for core blocks.

## Limitations and future work

The current 1.6.0 implementation has known gaps. None are blocking for typical migrations but worth being aware of:

- **No post-to-post ID translation.** ACF `post_object` and `relationship` fields, custom post-link blocks, and similar references are not rewritten. The orchestrator can do this manually before calling `create-post` (translate IDs in the `acf_fields` input), but `rewrite-content` itself is media-only.
- **No author mapping.** The destination posts default to the current user. Mapping by email/username is a manual step.
- **No server-side persistence of migration state.** The orchestrator (Claude) holds the maps in memory. If the conversation is interrupted, recovery is manual. A future `filter/store-migration-map` ability would help.
- **No dedupe on retry.** Re-running an upload on the destination creates duplicates. Manual cleanup or pre-flight `list-media` matching is the workaround.
- **No background processing.** All calls are synchronous. Very large migrations chunk via the orchestrator.
- **Limited ACF field-type coverage** in `rewrite-content`: image, gallery, file. Taxonomy, page_link, post_object, relationship, and custom field types are intentionally not rewritten.
- **Custom block types** need explicit handler registration via the filter (see above).

## Verification checklist

After a migration, spot-check the destination:

- [ ] Open three or four random migrated posts on the frontend. Do all images render? Are any showing broken-image icons?
- [ ] In WP admin → Media, do the migrated attachments appear with correct titles, alt text, captions?
- [ ] On a post with a featured image, is the thumbnail set on the destination?
- [ ] On a post with a Gutenberg gallery, does it render with all images?
- [ ] On a post with a `[gallery]` shortcode, does it render with all images?
- [ ] In the database, run `SELECT post_content FROM wp_posts WHERE post_content LIKE '%wp-image-%';` and pick a random row — confirm the IDs in `wp-image-{N}` match attachment IDs that exist in `wp_posts WHERE post_type = 'attachment'`.
- [ ] If you used a `url_map`, search post content for the old domain — should return zero rows.
- [ ] If you set up a 301-redirect plan from old slugs to new slugs, test a sample.

If anything in the checklist fails, re-run `filter/rewrite-content` with `dry_run: true` and the same `media_map` against the affected posts — the dry-run will report what's still left to fix, and you can apply incrementally.
