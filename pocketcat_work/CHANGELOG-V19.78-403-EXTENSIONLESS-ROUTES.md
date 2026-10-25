# v19.78 — Admin Settings and Upload HTTP 403 Routing Fix

- Added extensionless `/admin/site-settings-save/` and `/admin/site-logo-upload/` endpoints so browser requests do not need to address PHP filenames directly.
- Site settings now submits metadata as JSON and sends a selected logo in a separate multipart request that does not include the Website URL field.
- Kept authentication and CSRF checks on both settings endpoints and reused the existing validated logo-upload logic.
- Updated File Storage, Media Library, AI attachment, and support upload clients to use extensionless `/api/files/` and `/api/upload-chunk/` rewrite routes instead of direct `.php` URLs.
- Added cache-busting versions for changed upload clients and regression checks for the extensionless routes.

Validation: `make check`, PHP syntax checks, inline settings JavaScript syntax check, and ZIP integrity check. Live OpenResty/WAF behavior cannot be tested from this source archive. If the host returns 403 for the extensionless routes too, hosting logs/WAF rules must be reviewed by the provider.
