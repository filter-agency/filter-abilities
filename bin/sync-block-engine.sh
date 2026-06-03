#!/usr/bin/env bash
#
# Sync the vendored GravityKit Block API engine from upstream.
#
# Downloads the engine class files (and read-side enrichers) from a pinned
# GravityKit/block-mcp git tag into includes/block-engine/, then records the
# version in includes/block-engine/BLOCK_ENGINE_VERSION.
#
# The vendored files are a clean MIRROR of upstream — do not hand-edit them.
# After syncing a new tag, bump FILTER_ABILITIES_BLOCK_ENGINE_VERSION in
# includes/block-engine/loader.php and re-run the checks in docs/BLOCK-EDITING.md.
#
# Usage:
#   bin/sync-block-engine.sh [tag]
#
# Example:
#   bin/sync-block-engine.sh v1.8.1
#
set -euo pipefail

REPO="GravityKit/block-mcp"
UPSTREAM_DIR="wordpress-plugin/gk-block-api/includes"

# Engine dependency closure — the files we actually consume. Anything not listed
# here (REST controller, post/term/media managers, settings page, Yoast bridge,
# instructions, the main plugin bootstrap) is intentionally NOT vendored.
ENGINE_FILES=(
  class-preferences.php
  class-block-inventory.php
  class-block-registry.php
  class-block-safety.php
  class-html-transformer.php
  class-block-reader.php
  class-block-writer.php
  class-block-crud.php
  class-block-mutator.php
)

ENRICHER_FILES=(
  class-core-block-enricher.php
  class-core-image-enricher.php
  class-yoast-faq-enricher.php
)

# Resolve paths relative to this script so it works from any CWD.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST_DIR="${SCRIPT_DIR}/../includes/block-engine"

# Default to the currently-pinned tag if none supplied.
if [[ $# -ge 1 ]]; then
  TAG="$1"
else
  TAG="$(tr -d '[:space:]' < "${DEST_DIR}/BLOCK_ENGINE_VERSION" 2>/dev/null || true)"
fi

if [[ -z "${TAG:-}" ]]; then
  echo "error: no tag supplied and BLOCK_ENGINE_VERSION is empty." >&2
  echo "usage: bin/sync-block-engine.sh <tag>   (e.g. v1.8.1)" >&2
  exit 1
fi

BASE_URL="https://raw.githubusercontent.com/${REPO}/${TAG}/${UPSTREAM_DIR}"

echo "Syncing GravityKit Block API engine @ ${TAG} from ${REPO}"
mkdir -p "${DEST_DIR}/block-enrichers"

fetch() {
  # fetch <url> <dest>
  local url="$1" dest="$2"
  local tmp
  tmp="$(mktemp)"
  if ! curl -fsSL "$url" -o "$tmp"; then
    echo "error: failed to download $url" >&2
    rm -f "$tmp"
    exit 1
  fi
  # Guard against a 404 page slipping through as a "success".
  if ! head -n 1 "$tmp" | grep -q '<?php'; then
    echo "error: $url did not return a PHP file (wrong tag or moved upstream?)" >&2
    rm -f "$tmp"
    exit 1
  fi
  mv "$tmp" "$dest"
  echo "  + ${dest#"${DEST_DIR}/"}"
}

for f in "${ENGINE_FILES[@]}"; do
  fetch "${BASE_URL}/${f}" "${DEST_DIR}/${f}"
done

for f in "${ENRICHER_FILES[@]}"; do
  fetch "${BASE_URL}/block-enrichers/${f}" "${DEST_DIR}/block-enrichers/${f}"
done

printf '%s\n' "${TAG}" > "${DEST_DIR}/BLOCK_ENGINE_VERSION"

echo
echo "Done. Pinned to ${TAG}."
echo "Next:"
echo "  1. Set FILTER_ABILITIES_BLOCK_ENGINE_VERSION to '${TAG#v}' in includes/block-engine/loader.php"
echo "  2. Re-run the verification steps in docs/BLOCK-EDITING.md"
echo "  3. Review the diff (the files are a verbatim upstream mirror) and commit"
