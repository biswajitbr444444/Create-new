# PocketCat V16.2 — Advanced Search & Precise Navigation

## Search flow
- Removed the Ctrl K keyboard hint from the search UI.
- Removed automatic viewport scrolling and result-group animation from the search results page.
- Added an Advanced Search panel with scope filtering and Contains / Exact phrase matching.
- Search result clicks carry the searched keyword to the destination for contextual highlighting.

## Click-to-navigate behavior
- Destination focus is processed centrally by `includes/functions.php`.
- Existing `focus=` and URL hash targets remain supported.
- The destination target is smoothly scrolled to the center only after navigation to a target.

## Visual timing
- Target container border/outline blink: exactly 5 seconds.
- Matching terms inside the target are dynamically wrapped in `<mark class="search-highlight">`.
- Keyword background blink: 10 seconds.
- After 10 seconds, generated `<mark>` elements are removed and the text returns to normal styling.
- No persistent background is applied to the entire target container.

## Cache
- Shared `app.css` and `responsive-system.css` cache versions are bumped to `16.2`.
