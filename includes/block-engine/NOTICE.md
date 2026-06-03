# Vendored: GravityKit Block API engine

The PHP class files in this directory (`class-*.php` and `block-enrichers/*.php`)
are vendored **verbatim** from the **GK Block API** WordPress plugin, part of
GravityKit's Block MCP project.

- **Upstream:** https://github.com/GravityKit/block-mcp
  (subdirectory `wordpress-plugin/gk-block-api/includes/`)
- **Pinned version:** `v1.8.1` (see the `BLOCK_ENGINE_VERSION` file)
- **Copyright:** GravityKit / Katz Web Services, Inc.
- **License:** GPL-2.0-or-later — the same licence as Filter Abilities, so
  bundling is fully compliant. Attribution is the only obligation, and this file
  satisfies it.

## What we use it for

Filter Abilities drives this engine to perform surgical, block-aware edits to
Gutenberg `post_content` (parse → mutate one block → re-serialise via WordPress
core) instead of regenerating the whole content string. The engine is wired up
and exposed as Abilities API abilities in
[`../modules/class-block-editing.php`](../modules/class-block-editing.php); the
autoloader and enricher loading live in [`loader.php`](loader.php).

We vendor the **engine only**. We do NOT use GravityKit's REST controller, its
TypeScript MCP server, its settings UI, the per-site "instructions" addendum,
the Yoast bridge, or its post/term/media managers — Filter Abilities already
provides those via its own modules.

## Updating

Do **not** hand-edit the vendored files — local edits would be silently
overwritten on the next sync and would break the clean-mirror guarantee. To pull
a newer upstream tag:

```sh
bin/sync-block-engine.sh v1.9.0   # or whatever tag you want to pin to
```

Then bump `FILTER_ABILITIES_BLOCK_ENGINE_VERSION` in `loader.php`, re-run the
verification steps in `docs/BLOCK-EDITING.md`, and commit.
