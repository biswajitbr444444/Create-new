# v19.78 — Admin Site Settings POST Routing Fix

- Updated the Site Settings save form to submit directly to `/admin/settings.php` instead of posting back to the directory-style `/admin/settings/` URL. This avoids relying on a rewrite rule for multipart POST requests, which some OpenResty/Nginx hosting configurations reject before PHP runs.
- Updated the reset form to use the same explicit PHP handler URL.
- Kept the existing CSRF validation, admin authorization, database setting persistence, logo upload validation, and upload/reset behavior intact.
- Added a regression check to ensure both forms target the explicit PHP handler.

Note: This code change addresses the application form target. If a hosting-level OpenResty/WAF rule also blocks POST requests to `/admin/settings.php`, that rule must be reviewed by the hosting provider; application code cannot override a request rejected before it reaches PHP.
