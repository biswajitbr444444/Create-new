# PocketCat V11.35 — Link Creation Reliability Fix

## Fixed
- Added a dedicated `/api/create-link/` JSON endpoint for short-link creation.
- Removed dependence on HTML/redirect responses for AJAX link creation.
- Added explicit JSON error responses for CSRF, validation, policy, database and server failures.
- Added upgrade-safe checks for short-link columns used by the create flow.
- Preserved URL validation, threat checking, aliases, expiry, max clicks, routing, UTM, pixels and OpenGraph fields.
- Improved the frontend error message when a server returns non-JSON content.
- Fixed share fallback code that could pass a plain object where a DOM button was expected.

## Validation
- All PHP files pass `php -l`.
- `assets/js/links-ui.js` passes `node --check`.
- ZIP integrity verified with `unzip -t`.
