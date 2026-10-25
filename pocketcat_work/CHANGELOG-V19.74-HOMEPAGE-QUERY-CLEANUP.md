# PocketCat V19.74 — Homepage Query Cleanup

- Added a narrow homepage URL normalization redirect that removes the unused `i=1` query flag from `/`.
- Preserves unrelated query parameters and their original encoding/order, so campaign and other legitimate query parameters are not discarded.
- Uses a temporary redirect to avoid permanently caching URL normalization before deployment behavior is confirmed.
- Does not alter homepage rendering or application/database state.

## Verification

- PHP syntax check for `index.php` passed.
- Redirect behavior checked with representative root URLs including `/?i=1`, `/?i=1&utm_source=example`, and non-matching query values.
- Live web-server/browser behavior was not tested in this local environment.
