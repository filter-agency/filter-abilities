# Block Editing

Surgical, block-aware editing of Gutenberg content over MCP, added in **v1.7.0**.

## Why this exists

`filter/update-post` writes `post_content` as one string. When an AI agent edits
a page that way it has to regenerate the *entire* block markup, and it routinely
drops or mangles the `<!-- wp:... -->` delimiters WordPress uses to identify
blocks — the page then opens in the editor flagged as corrupted.

The block-editing abilities take a different approach: **read a parsed block
tree, change one block, let WordPress core re-serialise.** Markup stays valid by
construction, and static-block HTML is auto-synced so attribute changes don't
trigger "this block contains unexpected content" warnings.

## Engine

The heavy lifting is done by the **GravityKit Block API** engine, vendored
verbatim under [`includes/block-engine/`](../includes/block-engine/) (GPL-2.0,
see its `NOTICE.md`). Filter Abilities wires it up and exposes it as abilities in
[`includes/modules/class-block-editing.php`](../includes/modules/class-block-editing.php).
We use the engine only — not GravityKit's REST controller, MCP server, settings
UI, or post/term/media managers.

### Updating the engine

The vendored files are a clean mirror of upstream — **do not hand-edit them**.

```sh
bin/sync-block-engine.sh v1.9.0     # pull a newer tag
```

Then bump `FILTER_ABILITIES_BLOCK_ENGINE_VERSION` in
`includes/block-engine/loader.php` to match, re-run the verification below, and
commit. The version-change self-heal in `loader.php` clears the block-inventory
cache automatically on the next request.

## Abilities

| Ability | What it does |
|---|---|
| `filter/get-post-blocks` | Read the block tree. Each block has a stable `ref`, a flat `index`, and a `path`. **Call this first.** Also returns `revision_id`. |
| `filter/list-block-types` | Discover registered block types with attribute schemas and preference tiers (`preferred` / `acceptable` / `avoid` / `legacy`). |
| `filter/update-block` | Update one block's `attributes` and/or `inner_html` by `ref` or `index`. |
| `filter/insert-blocks` | Insert new block definitions at a position (`start`, a numeric index, or append). |
| `filter/delete-blocks` | Delete one or more consecutive blocks by `ref`/`index` + `count`. |
| `filter/mutate-block` | Structural ops by `path` or `ref`: `update-attrs`, `update-html`, `replace-block`, `remove-block`, `wrap-in-group`, `unwrap-group`, `insert-child`, `duplicate`, `move`. |
| `filter/batch-edit-blocks` | Up to 50 independent updates atomically in one revision (all-or-nothing). |

### Typical flow

1. `filter/get-post-blocks` → note each block's `ref` (and the `revision_id`).
2. `filter/update-block` / `filter/mutate-block` / `filter/batch-edit-blocks`
   using those refs.
3. Optionally pass `expected_revision` (the `revision_id` from step 1) to writes
   for an optimistic-concurrency guard — the write fails if the post changed
   underneath you.

### Notes & limits

- **Permissions:** writes require `edit_post` on the target post; discovery
  requires `edit_posts`. (Same coarse-then-fine pattern as the rest of the plugin.)
- **Stable refs** are persisted into block metadata (`attrs.metadata.gk_ref`) the
  first time you read a post with `persist_refs` true (the default). This is
  benign metadata so refs survive across edits — don't mistake it for an
  unexpected content change.
- **Dual-storage blocks** (those storing data in both attributes and HTML, e.g.
  `yoast/faq-block`) are guarded: `update-html` is refused on them. Read such a
  block by `ref` to see its full shape.
- **Rate limits (upstream defaults):** 10 writes/min and 2 full-replaces/min per
  post; a batch counts as one write (max 50 items). These are inherited from the
  vendored engine and left at default.

## Verification

1. Lint: `php -l` the new module and every file in `includes/block-engine/`.
2. Activate the plugin on a local site and confirm no fatals.
3. Over MCP:
   - Discover abilities → the seven `filter/*` block abilities appear.
   - `filter/get-post-blocks` on a known page → block tree with refs + revision id.
   - `filter/update-block` to change a heading → reopen the page in the block
     editor: it loads cleanly with **no validation warning** and intact
     `<!-- wp:... -->` delimiters.
   - `filter/mutate-block` (`move`) and `filter/batch-edit-blocks` on a
     multi-block page → atomic result, clean editor render.
   - `filter/list-block-types` → a deprecated block is flagged `avoid`/`legacy`.
