# PocketCat V17.6 — Advanced Upload Hardening

## Release scope
V17.6 builds on V17.5 and hardens the advanced upload system for real-world shared-hosting conditions. Existing storage, sharing, analytics, support threads, database records, and storage drivers remain backward compatible.

### Changes
- **Network speed:** retained live per-file and aggregate transfer speed telemetry and improved request-state handling so server response time is not falsely reported as a stalled network upload.
- **Upload size:** retained transferred-versus-total byte telemetry for every file and the complete batch.
- **Progress 0/100:** fixed aggregate batch progress so completed files remain part of the denominator; progress no longer jumps backward as individual files finish.
- **Retry upload:** local chunk uploads now reconcile the authoritative server offset after a lost response, preventing duplicate-chunk/offset-mismatch failures on retry.
- **Cancel upload:** retained safe cancellation and cleanup for active and queued uploads; resumable sessions can be retried cleanly after cancellation.
- **Duplicate filenames:** advanced resumable sessions now allocate collision-safe display names for the same user/context, producing `pigeon.jpg`, `pigeon (1).jpg`, `pigeon (2).jpg`, `pigeon (3).jpg`, etc. without overwriting existing records.
- **Bulk upload:** retained controlled concurrency (maximum 3 active uploads) and added the same resilient resumable workflow to Support Chat attachments.
- **Support Chat upload UI:** replaced the basic browser file input workflow with the V17.6 advanced uploader: Upload files title, bulk selection, per-file progress, upload size, network speed, retry and cancel controls.
- **Shared-hosting recovery:** added a local-upload status endpoint so the browser can recover from a response lost after the server has already written a chunk.
- **Duplicate selection protection:** repeated selection of the same browser file in one active batch is ignored to avoid accidental duplicate queue entries.

## Code changes
- `VERSION` → `17.6`
- `assets/js/file-sharing-v14-upload.js` → hardened aggregate telemetry, stall detection, local-chunk retry reconciliation, and duplicate-selection handling.
- `api/files.php` → added authenticated `local_status` recovery endpoint.
- `includes/upload-engine.php` → collision-safe resumable-session display names and normalized relative paths under a MySQL advisory lock.
- `assets/js/support-upload-v17.6.js` → new advanced Support Chat bulk/resumable uploader with progress, speed, retry, cancel and queue control.
- `support/index.php` + `admin/support.php` → Support Chat upload controls upgraded to the V17.6 uploader.
- `assets/css/app.css` → responsive V17.6 support-upload telemetry and queue styling.
- `files/index.php`, `includes/navbar.php`, `includes/functions.php`, `admin/index.php`, `admin/version-details.php` → current release/cache labels updated to V17.6.

## Backwards compatibility
- No database migration is required.
- Existing `file_objects`, `file_uploads`, `upload_sessions`, support messages, sharing links, analytics and download flows are preserved.
- Existing local, S3/R2, Google Drive, OneDrive and Dropbox storage modes continue using their existing APIs.
- Existing support attachment URLs and historical messages remain readable.

## QA
- PHP syntax validation performed on all modified PHP files.
- JavaScript syntax validation performed on both modified upload scripts.
- Current-version references checked for stale V17.5 labels in active code paths.
- Deployment archive contains source only and excludes runtime uploads/user-generated storage.
