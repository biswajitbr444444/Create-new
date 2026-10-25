# PocketCat v19.68 hardening update notes

## Testing Mode
- Testing Mode is now explicitly controlled by the `testing_mode_enabled` system setting and defaults to OFF, regardless of legacy `policy_engine.testing_mode` values.
- Super Admins can change it in Admin → Control Plane → Testing Mode. Turning it on bypasses the policy plan limits; turning it off returns enforcement to the configured plan matrix and the existing task/project/note/AI allowances.
- Keep this setting OFF in production unless intentionally running a controlled test.

## Bootstrap schema checks
- Compatibility schema checks are gated by `storage/.schema-check-version` at version `19.68-schema-1`.
- To intentionally re-run compatibility checks after a deployment, remove that marker during a maintenance window. The first request after a version change performs the check.
- The codebase still contains feature-local schema helpers; this change removes the unconditional central bootstrap pass, not every DDL call in all modules.

## Upload execution restrictions
- Apache `.htaccess` rules were added to `uploads/`, `uploads/media/`, and `uploads/files/` to disable CGI execution, remove script handlers, and deny direct requests for common executable extensions.
- These rules require Apache `AllowOverride` support. For Nginx or hosts that ignore `.htaccess`, configure equivalent server-level deny/handler rules.

## Webhook and API hardening
- Webhook registration now accepts only HTTPS targets on the default HTTPS port, rejects URL credentials and localhost/private/reserved IPs, and validates DNS addresses before saving.
- Delivery paths revalidate existing webhook destinations, disable redirects, and restrict cURL to HTTPS to reduce SSRF and redirect-based bypasses. This is defense-in-depth; production deployments should still enforce outbound network egress restrictions.
- Session-based mutation endpoints in the file API require CSRF tokens; token-authenticated API calls remain bearer-authenticated. The existing endpoint-by-endpoint auth/ownership model remains in place and should receive full integration coverage before claiming a complete API-wide audit.
- The media catalog bridge continues to use `canonical_file_id` to synchronize canonical file objects into `media_assets`; this release does not attempt a destructive data migration or rewrite all legacy upload callers.

## Verification
Run `make check` from the project root. This is a syntax and policy smoke-test manifest, not a substitute for database-backed integration, authorization, upload, or browser tests.
