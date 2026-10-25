# PocketCat V19.67 — File Storage Download Reliability and Responsive QA

- **Original-file bulk downloads** → the browser could save an HTML fallback/route response as `download.html` rather than the selected file → added a one-time, same-session `download_stream` endpoint in `api/files.php`. Local files are streamed as binary with the stored MIME type, authoritative `Content-Disposition` filename, exact content length, `nosniff`, and no-store headers. The endpoint validates file ownership context through the download ticket, public token, clean status, Trash state, path containment, and file readability.
- **Download URL handoff** → original downloads relied on the general `/file/download/` route and client-side filename override → `download_start` now issues a short-lived ticket and returns the dedicated binary stream URL for local storage; frontend download handling relies on the server-provided filename instead of forcing an anchor filename.
- **File Storage asset cache** → browser caching could keep stale page scripts/styles after the release → bumped File Storage page CSS/JS cache query versions to V19.67.
- **Version Details** → admin release metadata was pinned to an older V19.2 changelog despite the V19.66 baseline → updated `VERSION`, release file, release date, scope, and displayed release heading to V19.66 → V19.67.
- **Responsive polish** → retained the existing adaptive file workspace and added targeted V19.67 overflow and narrow-screen refinements in the File Storage stylesheet (long filenames, toolbar controls, bulk actions, upload panel, and menus remain constrained to the viewport).

## Operational notes

- Download stream validation checks the one-time session ticket expiry, file ID/public token match, `status='clean'`, `trashed_at IS NULL`, canonical local storage root containment (including legacy `uploads/media` objects), and readable file existence before emitting binary bytes. The one-time stream action is exempted from the generic API request counter because the ticket itself is single-use and bulk original-file downloads can legitimately exceed 120 files.
- The local binary stream uses chunked reads (1 MiB) rather than loading the complete file into PHP memory.
- Non-local storage drivers continue to use the configured signed/provider download URL; actual provider download behavior depends on the storage provider's URL semantics and configuration.
- Updated file-page asset references to V19.67 to avoid stale cached scripts/styles.
- Static validation performed for the modified PHP and JavaScript files. The local verification session did not connect to a live deployment/database or run an authenticated browser session; therefore authenticated production downloads, provider-specific delivery, and cross-device live interactions must still be verified in the target deployment.
