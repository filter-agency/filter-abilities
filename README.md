# Filter Abilities

A WordPress plugin that exposes WordPress functionality as **Abilities API** abilities for AI agent interaction via the **Model Context Protocol (MCP)**. It auto-detects compatible plugins and registers relevant abilities so AI agents can read, create, and manage WordPress content, media, SEO, forms, personalization, and more.

## Requirements

- **WordPress 6.9+** (includes the Abilities API)
- **PHP 7.4+**
- The **WordPress MCP Adapter** plugin must be installed and configured to expose abilities over MCP (uses REST transport via `@anthropic/mcp-wordpress-remote`)

## Installation

1. Upload the `filter-abilities` folder to `wp-content/plugins/`.
2. Activate the plugin via **Plugins > Installed Plugins** in WP Admin.
3. The plugin auto-detects which compatible plugins are active and loads only the relevant modules — no configuration needed.

## How It Works

Filter Abilities hooks into the WordPress Abilities API (`wp_abilities_api_init`) to register abilities that AI agents can invoke through MCP. Each ability is a defined action with a JSON Schema input, a callback, and metadata that marks it as MCP-accessible.

The plugin uses a modular architecture:

- **Modules** are loaded automatically based on plugin dependency checks (e.g. the SEO module only loads if Yoast SEO is active).
- A custom `WP_Ability` subclass (`Filter_Abilities_MCP_Ability`) handles `stdClass`-to-array conversion, since the MCP adapter passes JSON-decoded `stdClass` objects but WordPress REST validation expects PHP arrays.
- All registered abilities are flagged with `meta.mcp.public = true` so the MCP adapter exposes them.
- Three core WordPress abilities (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) are also enabled for MCP via a filter.

## Modules & Abilities

### Core Modules (always active)

#### Content Management (`filter-content`)
| Ability | Description |
|---|---|
| `filter/list-posts` | List posts by type with filtering, pagination, sorting, and search |
| `filter/get-post` | Get detailed post data including content, terms, and ACF fields |
| `filter/create-post` | Create a new post with optional taxonomy and ACF field assignments |
| `filter/update-post` | Update an existing post's title, content, status, taxonomies, or ACF fields |

#### Taxonomy Management (`filter-taxonomy`)
| Ability | Description |
|---|---|
| `filter/list-terms` | List terms for any public taxonomy with search, pagination, and hierarchy |
| `filter/manage-term` | Create, update, or delete a taxonomy term |

#### Media Management (`filter-media`)
| Ability | Description |
|---|---|
| `filter/list-media` | List media library items with MIME type filtering and missing alt-text detection |

#### Site Health (`filter-site`)
| Ability | Description |
|---|---|
| `filter/site-info` | Site URL, WP version, active theme/plugins, post types, taxonomies, and detected modules |
| `filter/content-stats` | Post counts by type/status, total media count, and total user count |

### Conditional Modules (loaded when their plugin dependency is active)

#### ACF Fields — requires [Advanced Custom Fields](https://www.advancedcustomfields.com/)
Enhances the Content Management abilities with ACF field data (read/write). No standalone abilities.

#### SEO Management (`filter-seo`) — requires [Yoast SEO](https://yoast.com/)
| Ability | Description |
|---|---|
| `filter/get-seo-meta` | Get Yoast metadata for a post (title, description, focus keyword, OG data, score) |
| `filter/update-seo-meta` | Update Yoast SEO fields for a post |
| `filter/find-seo-issues` | Find published posts missing SEO title, meta description, or focus keyword |

#### Form Management (`filter-forms`) — requires [Gravity Forms](https://www.gravityforms.com/)
| Ability | Description |
|---|---|
| `filter/list-forms` | List all forms with field definitions and entry counts |
| `filter/get-form-entries` | Get form entries with date filtering and pagination |

#### AI Content (`filter-ai`) — requires Filter AI
| Ability | Description |
|---|---|
| `filter/ai-missing-alt-text` | Count images missing alt text by MIME type |
| `filter/ai-missing-seo-titles` | Count posts missing Yoast SEO titles by post type |
| `filter/ai-missing-seo-descriptions` | Count posts missing Yoast meta descriptions by post type |
| `filter/ai-batch-alt-text` | Start batch AI alt-text generation (async via Action Scheduler) |
| `filter/ai-batch-seo-titles` | Start batch AI SEO title generation |
| `filter/ai-batch-seo-descriptions` | Start batch AI meta description generation |
| `filter/ai-batch-status` | Get batch processing status for a given batch type |
| `filter/ai-batch-cancel` | Cancel a running batch operation |
| `filter/ai-settings` | Get current Filter AI settings (prompts, features, brand voice) |

#### Personalization (`filter-personalization`) — requires [PersonalizeWP](https://personalizewp.com/)
| Ability | Description |
|---|---|
| `filter/list-rules` | List all personalization rules with conditions and status |
| `filter/manage-rule` | Create, update, or delete a personalization rule |
| `filter/list-segments` | List audience segments with membership counts and conditions |
| `filter/manage-segment` | Create, update, activate, or deactivate a segment |
| `filter/list-scoring-rules` | List lead scoring rules with conditions and point values |
| `filter/manage-scoring-rule` | Create, update, or delete a lead scoring rule |
| `filter/visitor-stats` | Aggregate visitor analytics (totals, new/active, average lead score) |
| `filter/list-contacts` | List contacts with filtering by segment, score, date range, and search |
| `filter/get-contact` | Full contact profile: fields, segments, activities, lead score |
| `filter/contacts-by-page` | Find contacts who visited a URL path with visit counts |
| `filter/contacts-by-segment` | List all contacts in a specific segment |
| `filter/activity-feed` | Recent activity feed filtered by type, URL, and date range |
| `filter/contact-activity-summary` | Per-contact summary: pages visited, forms submitted, activity breakdown |

#### Teams Analytics (`filter-personalization`) — requires PersonalizeWP + [WooCommerce Teams](https://woocommerce.com/products/teams-for-woocommerce-memberships/)
| Ability | Description |
|---|---|
| `filter/list-teams` | List WooCommerce teams with member and contact counts |
| `filter/team-contacts` | List PersonalizeWP contacts belonging to a team |
| `filter/team-activity` | Activity feed for all team members |
| `filter/team-analytics` | Aggregate team analytics (activities, pages, lead scores, top members) |

## What Else Needs to Be in Place

For this plugin to be useful, the following must be set up on the WordPress site:

1. **WordPress 6.9+** — the Abilities API is a core feature starting in 6.9.
2. **MCP Adapter Plugin** — install and activate the [WordPress MCP Adapter](https://github.com/Jenil/mcp-wordpress-remote) (or equivalent) that bridges the Abilities API to an MCP transport. This is what makes the abilities callable by AI agents.
3. **MCP Client Configuration** — your AI agent or tool (e.g. Claude Code, Claude Desktop) must be configured to connect to the WordPress site's MCP endpoint using the REST transport provided by the adapter.
4. **Authentication** — the MCP adapter must be configured with appropriate authentication (application passwords, OAuth, etc.) so that ability calls are authorized. Abilities that modify content require a user with sufficient WordPress capabilities.
5. **Optional Plugins** — install any of the supported plugins (ACF, Yoast SEO, Gravity Forms, PersonalizeWP, Filter AI, WooCommerce Teams) to unlock their corresponding abilities. The plugin detects these automatically at runtime.

## License

GPL-2.0-or-later
