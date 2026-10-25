# PocketCat V11.57 — Search Result Redirect 10-Second Highlight

## Fixed
- Search result destination pages now keep the matched target visually highlighted for a full 10 seconds.
- The target is smoothly scrolled into the center of the viewport after opening a search result.
- Highlight animation restarts reliably even when the same target is opened repeatedly.
- Removed the old pointer/tap handler that cancelled the highlight before 10 seconds.
- Updated the shared `flash-target` animation so search redirects use the same 10-second visual treatment.
- Search result group highlighting remains 10 seconds.

## Scope
- Applies to search-result links that use `focus=` or a matching URL hash.
- Works with normal element IDs and `data-focus-key` targets.
