# PocketCat V18.3 — Resilient Media Library Uploader

## Changes
- **Network recovery** → short transient failures previously exhausted a small fixed retry count → retry resumable chunks with 2s / 5s / 10s exponential backoff, network-online waiting, and server-offset probing before resending a chunk.
- **Upload controls** → the queue lacked granular pause/resume and selected-item actions → add per-file Pause, Resume, Cancel, Retry upload, Remove and Re-select File controls plus select-all, pause/resume/cancel/retry/remove selected actions.
- **Missing local file** → unreadable local files had only a generic failure state → show a persistent inline warning, `File Missing` status and a file-picker trigger when the browser reports an unreadable source.
- **Telemetry** → batch status did not show ETA or queue-state breakdown → add remaining-time estimate and active/queued/paused counts alongside aggregate bytes, speed and progress.
- **Zero-refresh completion** → successful upload triggered `location.reload()` and blocked users with alert popups → remove the reload and global upload alerts, insert completed assets into the existing media grid, add a copyable/openable URL, keep the item selectable for sharing, and show a non-blocking completion toast.
- **Preferences integration** → newly inserted assets were not automatically reprocessed by the V18.2 display preferences → notify the preference controller to re-apply sorting, pins, folder accents, borders and grid/list mode.
- **Responsive queue UI** → file controls and queue actions could become crowded on narrow screens → add wrapping, accessible status text, inline missing-file warning styling and mobile layout rules.

## Compatibility
- Reuses the existing `/api/upload-chunk.php` resumable upload protocol and upload-session database; no database migration is required.
- Chunk size remains constrained by the server's detected PHP request limits for shared-hosting compatibility.
