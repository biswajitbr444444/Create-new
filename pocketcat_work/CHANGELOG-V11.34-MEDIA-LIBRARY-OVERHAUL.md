# PocketCat V11.34 — Media Library Overhaul

## What changed
- Reworked `/admin/media/` into a responsive media-management workspace.
- Added searchable filters for filename, alt text and description.
- Added type, folder and sort filters plus grid/list view.
- Added library statistics and favorites.
- Added image/video/audio preview modal.
- Added reliable copy URL and bulk copy URL actions with clipboard fallback.
- Added bulk select, move-to-folder and admin bulk delete.
- Added admin folder create, rename and safe empty-folder deletion.
- Added editable name, folder, alt text, description and favorite metadata.
- Added drag/drop file selection UI with client-side file-size display.
- Preserved public URL import and existing local media storage.
- Added idempotent runtime schema checks for new metadata columns.

## Safety / logic
- Folder deletion is refused while the folder contains real assets.
- Admin-only destructive bulk deletion and folder management.
- Uploaded filenames are sanitized and stored with random physical names.
- Public URL import only accepts HTTP/HTTPS URLs.
- Uploads over the current shared-hosting 2 MB media limit are skipped with a clear message.
- Existing media remains usable; new metadata fields are nullable/defaulted.

## Upgrade
Existing installations should run `database/migration-v11.34-media-library.sql`, or allow the normal application bootstrap to add missing columns.
