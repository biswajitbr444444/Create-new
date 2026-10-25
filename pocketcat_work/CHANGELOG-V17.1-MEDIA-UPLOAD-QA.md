# PocketCat V17.1 — Media Upload Telemetry & Retry QA

## Changes
- Media upload -> no live upload telemetry -> added overall Upload progress (0–100%), Upload size (uploaded/total), Network speed, and completed/total file counters.
- Per-file upload -> minimal status -> added upload size, exact progress percentage, live network speed, ETA, and progress bar.
- Failed media upload -> no retry action -> added functional Retry upload button that starts a fresh resumable upload session safely.
- Chunk transport -> `fetch()` hid upload progress -> switched media chunk requests to XMLHttpRequest upload progress events for real network-speed/progress telemetry.
- Mobile media upload -> telemetry could crowd the narrow viewport -> added responsive 2-column/1-column layouts.
- Asset caching -> V17.0 -> bumped relevant asset cache identifiers to V17.1.
- Compatibility -> existing media/file database schema -> no migrations, schema changes, or destructive data changes.

## QA
- Existing `/api/upload-chunk.php` resumable protocol retained.
- CSRF, same-origin credentials, upload size/type policy, folder path preservation and server-side finalization retained.
- Retry aborts the failed session before starting a new session.
