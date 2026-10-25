# PocketCat V17.7 — Advanced Upload System

## Release scope
V17.7 builds on V17.6 with a final upload UX/QA hardening pass for the File Storage workspace and Support Chat. The release keeps the existing upload architecture, storage drivers, database schema and sharing flows backward compatible.

## Changes
- **Network speed:** per-file and aggregate network speed now goes idle when no transfer-progress event has arrived recently, preventing stale speed values during a stalled connection.
- **Upload size:** per-file telemetry continues to show transferred bytes versus the complete file size; the batch summary reports total transferred versus total selected size.
- **Progress:** the uploader keeps an explicit `0% / 100%` state from queue creation through completion.
- **Retry upload:** resumable local uploads re-check the server's authoritative byte offset after a failed request before retransmitting a chunk, avoiding duplicate chunk writes after a lost response.
- **Cancel button:** active and queued uploads can be cancelled without corrupting the current upload session; retry creates a fresh safe session when necessary.
- **Duplicate filenames:** repeated uploads of the same filename are accepted. The server allocates `pigeon`, `pigeon (1)`, `pigeon (2)`, `pigeon (3)`, etc. while preserving the original extension.
- **Bulk upload:** multi-file, drag/drop and folder selections continue through a controlled three-transfer queue so shared hosting is not flooded with concurrent requests.
- **Support Chat:** customer and admin attachment uploaders use the same advanced resumable model with size, progress, network speed, retry and cancel controls.
- **Testing-mode quota display:** the File Storage dashboard no longer renders `PHP_INT_MAX` as the misleading `8388608 TB`; testing-mode storage and per-file quotas are displayed as `Unlimited`.
- **Cache/versioning:** active V17.7 asset and navigation cache markers are updated so browsers receive the new uploader immediately.

## Backwards compatibility
- No database migration is required.
- Existing `file_objects`, `file_uploads`, `upload_sessions`, support attachments, sharing links, analytics and storage-driver integrations are preserved.
- Existing local, S3/R2, Google Drive, OneDrive and Dropbox paths remain supported.

## QA targets
- PHP syntax validation on every changed PHP file.
- JavaScript syntax validation for both V17.7 upload clients.
- Static verification that V17.7 active references are consistent.
- Archive integrity verification before deployment.
