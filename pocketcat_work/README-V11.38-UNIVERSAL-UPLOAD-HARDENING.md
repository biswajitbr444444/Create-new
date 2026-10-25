# PocketCat V11.38 — Universal Upload Hardening

## Patch contents
- `includes/upload-engine.php` — shared resumable upload/session engine with safe paths, request-sized chunks, locking and structured errors.
- `api/upload-chunk.php` — authenticated/CSRF-protected init/chunk/complete/abort API.
- `api/files.php` + `files/index.php` — hardened file upload diagnostics, resumable local uploads and corrected S3 multipart URL handling.
- `media/index.php` + `admin/media.php` — resumable media uploads, directory selection, relative-path preservation and scoped persistence.
- `api/chat-upload.php` + `ai/index.php` — hardened chat attachments and resumable large-file attachments.
- `api/support-chat.php`, `support/index.php`, `admin/support.php` — hardened support attachments and resumable upload sessions.
- `database/migration-v11.38-universal-upload-hardening.sql` and `database/schema.sql` — `upload_sessions`.
- `cron/upload_cleanup.php` — removes abandoned chunk sessions older than 24 hours.

## Deployment
1. Back up the current PocketCat files and database.
2. Extract this archive over the application root, preserving directories.
3. Log in once so `bootstrap.php` creates `upload_sessions` automatically, or run the migration SQL manually.
4. Ensure `uploads/` is writable by PHP. The application creates user-scoped upload directories as needed.
5. If cron is available, run `*/30 * * * * /usr/bin/php /path/to/PocketCat/cron/upload_cleanup.php >/dev/null 2>&1`.
6. Do not depend on raising `upload_max_filesize` or `post_max_size` on shared hosting: resumable browser uploads are sliced into conservative 512 KiB-or-smaller requests. Large files are therefore transferred as many small requests, subject to disk quota and policy.
7. Authenticated diagnostics: `/api/files.php?action=diagnostics`.
