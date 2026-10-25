# PocketCat V17.8 — Advanced Upload & Bulk Upload Hardening

## Release scope
V17.8 upgrades the V17.7 upload system across File Storage and Media Library. Existing storage drivers, database tables, share links and file provenance remain backward compatible.

## Changes
- **File Storage upload telemetry** -> V17.7 already exposed progress/size/speed, but bulk management was limited -> added batch-level **Retry failed** and **Cancel active uploads** controls while preserving the per-file controls.
- **Network speed** -> speed could become stale during drag/drop or stalled transfers -> V17.8 keeps live per-file and aggregate speed telemetry idle when no recent transfer progress exists.
- **Upload size / progress** -> telemetry was available but not consistently presented as a complete transfer state -> V17.8 keeps explicit `uploaded / total` bytes and `0% / 100%` progress for every queued, active, cancelled and completed upload.
- **Retry upload** -> retry depended on the browser reusing the current upload state -> retry continues to use authoritative server offsets for resumable chunks and safely creates a fresh session after cancellation.
- **Cancel upload** -> individual cancellation existed, but bulk queues required manual cancellation -> added safe batch cancellation for queued and active File Storage uploads and Media Library uploads.
- **Bulk upload** -> File Storage supported controlled parallel uploads, while Media Library selections were uploaded sequentially and selection could start work before the submit action -> Media Library now queues selections and runs them through a controlled three-transfer worker pool.
- **Folder upload** -> folder picker worked, but folder drag/drop handling was less consistent -> File Storage and Media Library now resolve dropped directory entries recursively and preserve relative paths where the browser exposes them.
- **Duplicate filenames** -> File Storage had server-side duplicate allocation, while Media Library could create duplicate visible names -> Media Library now allocates `pigeon.jpg`, `pigeon (1).jpg`, `pigeon (2).jpg`, etc. per destination folder under a MySQL advisory lock.
- **Media Library upload lifecycle** -> the V17.7 client could create upload state during file selection and then create another set during form submission -> V17.8 separates selection from upload execution, preventing accidental double uploads.
- **Testing-mode quota label** -> very large PHP integer quotas could still render as an enormous TB value on stale deployments -> File Storage now explicitly renders `Unlimited` whenever Testing Mode is active.
- **Cache/versioning** -> active navigation and uploader assets used V17.7 cache markers -> V17.8 markers are applied to the active release and the Support uploader is copied to a V17.8 asset path without removing the previous asset.

## Backwards compatibility
- No database migration is required.
- Existing `file_objects`, `file_uploads`, `upload_sessions`, `media_assets`, support attachments, sharing links, analytics and storage-driver integrations are preserved.
- Local, S3/R2, Google Drive, OneDrive and Dropbox flows remain supported.
- Existing V17.7 uploader assets remain in the package for compatibility with cached/legacy pages.

## QA targets
- PHP syntax validation on changed PHP files.
- JavaScript syntax validation for File Storage, Media Library inline upload code and Support uploader.
- Static verification of V17.8 active version/cache references.
- Duplicate-name, retry, cancellation, folder-path and bulk-concurrency code-path review.
- Archive integrity verification before deployment.

## Production smoke tests recommended
Because this environment cannot execute an authenticated browser session against the live InfinityFree account, verify after deployment:
1. Upload one file and confirm `0% / 100%`, bytes, speed, completion and the new file card.
2. Upload the same filename repeatedly and confirm `(1)`, `(2)`, `(3)` naming.
3. Select 3+ files and confirm no more than three active transfers at once.
4. Cancel one active transfer and use **Retry upload**.
5. Use **Retry failed** and **Cancel active uploads** for a bulk queue.
6. Upload a folder and verify relative folder paths.
7. In Media Library, select files/folder and click **Upload selected**; confirm files do not upload before submission and are not uploaded twice.
