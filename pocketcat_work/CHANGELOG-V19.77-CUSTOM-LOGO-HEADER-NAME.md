# v19.77 — Custom Logo and Header Name Controls

- Added a Website Logo uploader to Admin → Site settings with PNG, JPG/JPEG, SVG, and WebP support, a current/live preview, and remove/reset control.
- Stores the logo URL in the existing `system_settings` table (`logo_url`) and stores uploaded files under `uploads/site-branding/`; no database migration is required.
- Added a `show_site_name_header` setting and allows the Website Name to be saved blank.
- Updated the authenticated app header and public header (including login/signup pages) to use one shared brand renderer, respecting the visibility toggle and showing only the logo/default icon when the title is hidden.
- Added responsive logo sizing and `object-fit: contain` styling for mobile through desktop widths.
- Uploads are type-checked, size-limited, assigned random filenames, and basic unsafe SVG markup is rejected.

## Verification

- `make check` passes, including existing policy/catalog/webhook/security smoke tests and PHP syntax linting.
- Live browser, database, and production-hosting upload behavior were not exercised in this environment.
