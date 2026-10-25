# V11.38 — Universal Upload Hardening

- Added authenticated, CSRF-protected resumable upload sessions.
- Kept browser upload requests at conservative 512 KiB-or-smaller chunks.
- Added user-scoped temporary and final upload directories.
- Added safe exception logging with request IDs and JSON error responses.
- Added resumable media, AI chat, and support attachment flows.
- Added folder/directory path preservation for Media Library uploads.
- Fixed S3 multipart URL generation to return a real JSON array of part URLs.
- Fixed support chat frontend runtime identifier bugs (`Customer` / `You`).
- Added abandoned-upload cleanup cron.
- Added `upload_sessions` migration and runtime schema creation.
