# PocketCat V17.5 — Advanced Bulk Upload System

## Release scope
V17.5 builds on V17.4 with a controlled bulk-upload queue, safer duplicate filename handling, clearer upload telemetry, and a resilient retry/cancel workflow. Existing file storage, sharing, analytics, database records, and storage drivers remain compatible.

### Changes
- **Bulk upload:** the File Storage uploader now exposes a dedicated Bulk upload action while retaining multi-select, drag-and-drop, and folder upload.
- **Controlled concurrency:** bulk selections are queued and processed with a maximum of 3 active uploads at once to avoid shared-hosting request overload.
- **Network speed:** live per-file and aggregate transfer speed remains visible and is calculated from actual browser upload progress.
- **Upload size:** each upload shows transferred bytes versus total size, while the aggregate panel shows the complete batch size.
- **Progress:** explicit `0% / 100%` progress is shown from the first queued state through completion.
- **Retry upload:** failed/stalled transfers retain their upload session when possible and can be retried without losing already uploaded local chunks.
- **Cancel button:** active or queued uploads can be cancelled; server-side temporary/final upload records are cleaned up safely.
- **Duplicate filenames:** concurrent bulk requests are protected by a per-user database advisory lock so repeated names reliably become `file (1).ext`, `file (2).ext`, `file (3).ext`, etc.
- **Upload title:** the File Storage uploader consistently uses the title `Upload files`, with `Bulk upload` as the explicit batch action.

## Code changes
- `VERSION` → `17.5`
- `files/index.php` → added dedicated Bulk upload action and updated advanced uploader messaging/cache version.
- `assets/js/file-sharing-v14-upload.js` → added controlled upload queue/concurrency, queued cancel/retry states, batch queue telemetry, and safer bulk scheduling.
- `assets/css/file-sharing-v12.css` → updated responsive batch telemetry layout for the additional queue metric.
- `includes/files.php` → added per-user advisory locking helpers around duplicate-name allocation.
- `api/files.php` → made upload-name allocation and initial `file_objects` creation concurrency-safe for simultaneous bulk uploads.
- `admin/version-details.php` → current-only V17.5 release details.
- `admin/version-details/index.php` + `.htaccess` → explicit directory-route fallback for case-sensitive/shared-hosting deployments.
- `includes/navbar.php`, `includes/functions.php`, `admin/index.php` → V17.5 labels/cache versions.

## Backwards compatibility
- No database migration is required.
- Existing `file_objects`, `file_uploads`, `upload_sessions`, sharing links, analytics, and download flows are preserved.
- Existing local, S3/R2, Google Drive, OneDrive, and Dropbox storage modes continue to use their existing APIs.

## QA
- PHP syntax validation performed on modified PHP files.
- JavaScript syntax validation performed on the updated uploader.
- Route and cache-version references checked for stale V17.4 values.
- Deployment archive contains no runtime uploads or user-generated storage data.
