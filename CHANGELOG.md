# Changelog

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
