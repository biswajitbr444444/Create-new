# PocketCat V19.0 — Enterprise-Grade Bulk Uploader

## Changes
- **Network fault tolerance** → transient connection failures could leave queued uploads failed → added offline/online awareness, automatic retries with 2s / 5s / 10s backoff, a bounded retry policy, and preservation of the existing resumable upload session/verified byte offset.
- **Queue lifecycle** → uploads had cancel/retry controls but no pause/resume or robust batch selection → added per-file selection, Select All, Pause, Resume, Cancel, Retry, and Remove Selected actions alongside existing retry/cancel/clear actions.
- **Pause/resume** → active XHR requests could not be paused → pausing aborts the in-flight request safely, retains the session, and resumes through the existing server-offset recovery flow.
- **Missing local source** → source-read failures appeared as generic errors → added an inline warning, `File Missing` state, and Re-select File picker action without a blocking alert.
- **Telemetry** → queue summary did not distinguish paused work → aggregate queue breakdown now reports active, queued, and paused items; existing byte/progress/speed/ETA telemetry is retained.
- **Completion UX** → upload completion lacked a non-blocking confirmation → added an accessible toast while preserving reactive insertion into the file explorer and existing share workflow.
- **Responsive UI** → batch controls crowded mobile layouts → added wrapping, mobile-specific sizing, and visible inline missing-file styling.

## Compatibility
- Reuses the existing upload session and `/api/files.php` upload modes; no schema migration is introduced.
- Keeps the existing server upload policy, CSRF protections, scan workflow, file explorer actions, and sharing integration.
- Browser `File` objects remain readable after a user moves/deletes the source on some operating systems; the inline missing-file flow is shown when the browser or upload pipeline actually reports an unreadable/missing source.
