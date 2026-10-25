# PocketCat V17.4 — Advanced Upload System

## Release scope
V17.4 upgrades the File Storage & Sharing uploader with production-safe upload telemetry, cancellation, retry recovery, and automatic duplicate-name handling. Existing storage drivers and database structures remain compatible.

### Changes
- **Network speed:** live per-file and aggregate upload speed is calculated from XHR progress events.
- **Upload size:** each upload card and aggregate summary shows uploaded bytes versus total selected size.
- **Progress:** explicit `0% / 100%` progress is displayed and the progress bar remains synchronized with byte counts.
- **Retry upload:** failed uploads can resume their local chunk session where possible; cancelled uploads restart cleanly.
- **Cancel button:** active uploads can be cancelled; the pending file record and local partial chunk are cleaned up. S3 multipart uploads are aborted when applicable.
- **Duplicate filenames:** when the same filename is uploaded again by the same user, PocketCat automatically assigns `name (1).ext`, `name (2).ext`, etc.
- **Uploader title:** the file workspace now clearly identifies the area as `Upload files`.

## Code changes
- `VERSION` → `17.4`
- `includes/files.php` → added server-side duplicate-name resolver.
- `api/files.php` → applies unique display names and adds `cancel_upload`.
- `files/index.php` → updated uploader copy and asset cache version.
- `assets/js/file-sharing-v14-upload.js` → live telemetry, cancel/retry state handling, explicit progress and upload-size labels.
- `assets/css/file-sharing-v12.css` → responsive upload metrics and cancellation state styling.

## QA
- PHP syntax validation performed for all PHP files.
- JavaScript syntax validation performed for the modified uploader.
- Existing file storage drivers, file objects, sharing, analytics and download behavior preserved.
- No database schema migration required.
