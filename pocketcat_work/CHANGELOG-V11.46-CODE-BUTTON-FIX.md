# PocketCat V11.46 — Code Editor Button Fix

## Fixed
- Fixed Code Editor JavaScript initialization failure caused by references to removed `#codeCategory` and `#codeTags` controls.
- Restored functional bindings for New, Publish, Run Preview, Share, and Copy Link.
- Metadata/settings now safely use the existing Advanced Paste Settings category/tag controls when dedicated fields are absent.
- Publish/create/update flow remains API-backed with CSRF protection.
- Share uses the native share sheet when available and clipboard fallback otherwise.
- Copy Link uses secure clipboard with a browser fallback.
- Added a cache-busting `v=11.46` asset query so deployed browsers do not keep the broken editor JavaScript.
- The top Cancel button remains removed as requested.

## Validation
- `node --check assets/js/code-editor.js` passed.
- PHP syntax validation passed for changed PHP files.
