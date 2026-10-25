# PocketCat CMS Overhaul — Phase 1

Built on PocketCat V11.12.

## Implemented
- CMS schema/migrations for posts, pages, tags, post-term relationships, revisions, comments and post meta.
- Admin Posts and Pages listing with status counters, search, bulk publish/trash/restore/delete and row actions.
- Quick Edit entry point and content editor foundation.
- Visual / Code-Text editor, auto slug, permalink edit, formatting toolbar, lists, alignment, links, special characters, table insertion, fullscreen and word count/path indicators.
- Publish, visibility, scheduling, author, featured image, discussion and page-attribute/settings meta boxes.
- Categories and Tags administration with search, edit/delete and conversion between taxonomies.
- Comment moderation queue with pending/approved/spam/trash states.
- Admin top-bar `+ New` menu, Comments shortcut, Screen Options drawer and cache invalidation action.
- CMS admin navigation links and routing.
- Responsive CMS styling for desktop and mobile.

## Compatibility
- Existing legacy `cms_posts` / `cms_categories` tables are migrated in-place where possible.
- Existing V11.12 features are preserved as the base package.

## Validation
- PHP lint: 152 files, no syntax errors.
- Node syntax checks passed for CMS JavaScript.
- Final ZIP should be tested on a staging database before production replacement.
